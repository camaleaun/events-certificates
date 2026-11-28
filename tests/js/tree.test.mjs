/**
 * The elements as a tree: a group holds elements placed from its corner (src/editor/tree.ts). Run: npm run test:js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const root = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const built = await esbuild.build( {
	absWorkingDir: root,
	stdin: { contents: "import * as tree from './src/editor/tree'; window.tree = tree;", resolveDir: root, loader: 'ts' },
	bundle: true,
	format: 'iife',
	write: false,
	plugins: [
		{
			name: 'stub-i18n',
			setup( build ) {
				build.onResolve( { filter: /^@wordpress\/i18n$/ }, () => ( { path: 'i18n', namespace: 'stub' } ) );
				build.onLoad( { filter: /.*/, namespace: 'stub' }, () => ( { contents: 'export const __ = ( s ) => s; export const sprintf = ( s, ...a ) => a.reduce( ( t, v ) => t.replace( "%s", v ), s );', loader: 'js' } ) );
			},
		},
	],
} );
const sandbox = { window: {} };
vm.runInNewContext( built.outputFiles[ 0 ].text, sandbox );
const t = sandbox.window.tree;
const plain = ( v ) => JSON.parse( JSON.stringify( v ) );

const page = { w: 800, h: 600, safe: 0, bg: '$background', bleed: 0 };
const rect = ( id, x, y, w, h, more = {} ) => ( { id, type: 'rect', x, y, w, h, ...more } );
const at = ( list, id ) => plain( t.pageBox( t.findEl( t.resolveTree( list, page ), id ) ) );

test( 'an element is found, changed and removed wherever it is in the tree', () => {
	const list = [ rect( 'a', 0, 0, 10, 10 ), { id: 'g', type: 'group', x: 5, y: 5, w: 100, h: 100, children: [ rect( 'b', 1, 1, 5, 5 ) ] } ];
	assert.equal( t.findEl( list, 'b' ).id, 'b' );
	assert.equal( t.parentOf( list, 'b' ).id, 'g' );
	assert.equal( t.depthOf( list, 'b' ), 1 );
	const changed = t.mapEl( list, 'b', ( el ) => ( { ...el, w: 9 } ) );
	assert.equal( t.findEl( changed, 'b' ).w, 9 );
	assert.equal( t.mapEl( list, 'nope', ( el ) => el ), list, 'the same list when there is none by that id' );
	assert.deepEqual( plain( t.flatten( t.removeEl( list, 'b' ) ).map( ( e ) => e.id ) ), [ 'a', 'g' ] );
} );

test( 'what is in a group is on the page from the corner of the group', () => {
	const list = [ { id: 'g', type: 'group', x: 100, y: 50, w: 200, h: 100, children: [ rect( 'b', 10, 5, 20, 10 ) ] } ];
	assert.deepEqual( at( list, 'b' ), { x: 110, y: 55, w: 20, h: 10 } );
} );

test( 'an element moved into a group or out of it keeps its place on the page', () => {
	const list = [ { id: 'g', type: 'group', x: 100, y: 50, w: 200, h: 100, children: [] }, rect( 'a', 120, 70, 30, 20 ) ];
	const into = t.moveEl( list, page, 'a', 'g' );
	assert.equal( t.parentOf( into, 'a' ).id, 'g' );
	assert.deepEqual( at( into, 'a' ), { x: 120, y: 70, w: 30, h: 20 } );
	assert.equal( t.findEl( into, 'a' ).x, 20, 'its numbers are now from the corner of the group' );
	const out = t.moveEl( into, page, 'a', null );
	assert.equal( t.parentOf( out, 'a' ), null );
	assert.deepEqual( at( out, 'a' ), { x: 120, y: 70, w: 30, h: 20 } );
	// An anchored element is anchored to the group once it is in it, and to the page again out of it.
	const anchored = [ { id: 'g', type: 'group', x: 100, y: 50, w: 200, h: 100, children: [] }, rect( 'c', 110, 60, 180, 20, { layout: { ref: 'page', h: { mode: 'stretch', start: '110pt', end: '510pt' }, v: { mode: 'start', start: '60pt', size: '20pt' } } } ) ];
	const inside = t.moveEl( anchored, page, 'c', 'g' );
	assert.equal( t.findEl( inside, 'c' ).layout.ref, 'parent' );
	assert.deepEqual( at( inside, 'c' ), { x: 110, y: 60, w: 180, h: 20 } );
} );

test( 'a group does not go into itself, and what may not be in a group stays out', () => {
	const list = [ { id: 'g', type: 'group', x: 0, y: 0, w: 100, h: 100, children: [ { id: 'h', type: 'group', x: 0, y: 0, w: 50, h: 50, children: [] } ] }, { id: 'q', type: 'qr', x: 0, y: 0, w: 10, h: 10 }, { id: 's', type: 'signature', x: 0, y: 0, w: 10, h: 10 } ];
	assert.equal( t.moveEl( list, page, 'g', 'g' ), list );
	assert.equal( t.moveEl( list, page, 'g', 'h' ), list, 'not into what it holds' );
	assert.equal( t.moveEl( list, page, 'q', 'g' ), list, 'the authenticity code stays on the certificate' );
	assert.notEqual( t.moveEl( list, page, 's', 'g' ), list, 'a signature may go in a group: it is where the signer signs' );
	assert.equal( t.canHold( { id: 'old', type: 'image', slot: 'signature' } ), false, 'the old signature image slot stays out' );
	assert.equal( t.canHold( { id: 'v', type: 'verification' } ), false );
	// Three groups deep, and no more.
	const deep = [ { id: 'g1', type: 'group', x: 0, y: 0, w: 100, h: 100, children: [ { id: 'g2', type: 'group', x: 0, y: 0, w: 90, h: 90, children: [ { id: 'g3', type: 'group', x: 0, y: 0, w: 80, h: 80, children: [] } ] } ] }, { id: 'g4', type: 'group', x: 0, y: 0, w: 10, h: 10, children: [] } ];
	assert.equal( t.moveEl( deep, page, 'g4', 'g3' ), deep, 'a fourth level of groups is refused' );
	assert.notEqual( t.moveEl( deep, page, 'g4', 'g2' ), deep );
} );

test( 'several elements are grouped in their place, and ungrouped back', () => {
	const list = [ rect( 'a', 10, 10, 20, 20 ), rect( 'b', 100, 60, 30, 30 ), rect( 'c', 400, 400, 5, 5 ) ];
	const made = { id: 'group-1', type: 'group', x: 0, y: 0, w: 1, h: 1, children: [] };
	const grouped = t.groupEls( list, page, [ 'a', 'b' ], made );
	assert.deepEqual( plain( grouped.map( ( e ) => e.id ) ), [ 'group-1', 'c' ], 'the group takes the place of the first' );
	const group = t.findEl( grouped, 'group-1' );
	assert.deepEqual( [ group.x, group.y, group.w, group.h ], [ 10, 10, 120, 80 ], 'as big as they are together' );
	assert.deepEqual( at( grouped, 'a' ), { x: 10, y: 10, w: 20, h: 20 } );
	assert.deepEqual( at( grouped, 'b' ), { x: 100, y: 60, w: 30, h: 30 } );
	assert.deepEqual( plain( group.children.map( ( e ) => e.id ) ), [ 'a', 'b' ] );
	const back = t.ungroupEl( grouped, page, 'group-1' );
	assert.deepEqual( plain( back.map( ( e ) => e.id ) ), [ 'a', 'b', 'c' ] );
	assert.deepEqual( at( back, 'b' ), { x: 100, y: 60, w: 30, h: 30 } );
	assert.equal( t.groupEls( [ ...list, { id: 'q', type: 'qr', x: 0, y: 0, w: 1, h: 1 } ], page, [ 'a', 'q' ], made ), null, 'the code cannot be grouped' );
} );

test( 'a group that is a row places what is in it by the flow, and the tree says where', () => {
	const list = [ { id: 'g', type: 'group', x: 100, y: 50, w: 200, h: 50, arrange: 'row', gap: 10, children: [ rect( 'a', 0, 0, 40, 10 ), rect( 'b', 0, 0, 30, 10 ) ] } ];
	assert.deepEqual( at( list, 'a' ), { x: 100, y: 50, w: 40, h: 10 } );
	assert.deepEqual( at( list, 'b' ), { x: 150, y: 50, w: 30, h: 10 } );
} );

test( 'an element is put after another, copied with new ids, and sent up or down among its own', () => {
	const list = [ rect( 'a', 0, 0, 1, 1 ), { id: 'g', type: 'group', x: 0, y: 0, w: 50, h: 50, children: [ rect( 'b', 0, 0, 1, 1 ), rect( 'c', 0, 0, 1, 1 ) ] }, rect( 'd', 0, 0, 1, 1 ) ];
	assert.deepEqual( plain( t.insertAfter( list, 'a', rect( 'n', 0, 0, 1, 1 ) ).map( ( e ) => e.id ) ), [ 'a', 'n', 'g', 'd' ] );
	assert.deepEqual( plain( t.findEl( t.insertAfter( list, 'b', rect( 'n', 0, 0, 1, 1 ) ), 'g' ).children.map( ( e ) => e.id ) ), [ 'b', 'n', 'c' ], 'in the group of the other' );
	const copy = t.cloneEl( list[ 1 ], 'G copy' );
	assert.notEqual( copy.id, 'g' );
	assert.notEqual( copy.children[ 0 ].id, 'b' );
	assert.equal( copy.children.length, 2 );
	assert.deepEqual( plain( t.reorderEl( list, 'b', 'up' ) ).find( ( e ) => e.id === 'g' ).children.map( ( e ) => e.id ), [ 'c', 'b' ] );
	assert.deepEqual( plain( t.reorderEl( list, 'a', 'top' ).map( ( e ) => e.id ) ), [ 'g', 'd', 'a' ] );
	assert.deepEqual( plain( t.reorderEl( list, 'a', 'down' ).map( ( e ) => e.id ) ), [ 'a', 'g', 'd' ], 'the first cannot go further back' );
} );

test( 'a group that changes how it places what is in it leaves them their size and place', () => {
	const group = { id: 'g', type: 'group', x: 100, y: 50, w: 200, h: 100, children: [ rect( 'a', 0, 0, 1, 1, { layout: { ref: 'parent', h: { mode: 'stretch', start: '10pt', end: '10pt' }, v: { mode: 'start', start: '5pt', size: '20pt' } } } ) ] };
	const [ resolved ] = t.resolveTree( [ group ], page );
	const baked = plain( t.bakeChildren( resolved.children ) );
	assert.deepEqual( [ baked[ 0 ].x, baked[ 0 ].y, baked[ 0 ].w, baked[ 0 ].h ], [ 10, 5, 180, 20 ] );
	assert.equal( baked[ 0 ].layout, undefined );
	assert.equal( baked[ 0 ].ox, undefined );
} );

test( 'the tree is flattened for the backends that draw one element after another, each on the page', () => {
	const list = [ { id: 'g', type: 'group', x: 100, y: 50, w: 200, h: 100, fill: '#eee', arrange: 'row', gap: 10, children: [ rect( 'a', 0, 0, 40, 10 ), rect( 'b', 0, 0, 30, 10, { hidden: true } ), rect( 'c', 0, 0, 20, 10 ) ] }, { id: 'h', type: 'group', x: 0, y: 0, w: 10, h: 10, hidden: true, children: [ rect( 'z', 0, 0, 1, 1 ) ] }, rect( 'd', 5, 5, 1, 1 ) ];
	const flat = plain( t.flattenTree( list, page ) );
	assert.deepEqual( flat.map( ( e ) => [ e.id, e.type ] ), [ [ 'g', 'rect' ], [ 'a', 'rect' ], [ 'c', 'rect' ], [ 'd', 'rect' ] ], 'a group is its rectangle, then what is in it; nothing hidden' );
	assert.deepEqual( [ flat[ 0 ].x, flat[ 0 ].fill, flat[ 0 ].children ], [ 100, '#eee', undefined ] );
	assert.deepEqual( [ flat[ 1 ].x, flat[ 1 ].y, flat[ 2 ].x ], [ 100, 50, 150 ], 'on the page, by the flow of the row' );
} );

test( 'a new element goes in the selected group, or in the group of the selected element, at the end of its own list', () => {
	const list = [ rect( 'a', 0, 0, 1, 1 ), { id: 'g', type: 'group', x: 0, y: 0, w: 100, h: 100, children: [ rect( 'b', 0, 0, 1, 1 ) ] } ];
	const el = rect( 'n', 0, 0, 1, 1 );
	assert.equal( t.holderFor( list, 'g', el ).id, 'g', 'a selected group holds it' );
	assert.equal( t.holderFor( list, 'b', el ).id, 'g', 'the group of the selected element holds it' );
	assert.equal( t.holderFor( list, 'a', el ), null, 'an element of the page: the page' );
	assert.equal( t.holderFor( list, null, el ), null );
	assert.equal( t.holderFor( list, 'g', { id: 'q', type: 'qr' } ), null, 'what has its place on the certificate stays on it' );
	const deep = [ { id: 'g1', type: 'group', x: 0, y: 0, w: 9, h: 9, children: [ { id: 'g2', type: 'group', x: 0, y: 0, w: 9, h: 9, children: [ { id: 'g3', type: 'group', x: 0, y: 0, w: 9, h: 9, children: [] } ] } ] } ];
	assert.equal( t.holderFor( deep, 'g3', { id: 'x', type: 'group', x: 0, y: 0, w: 1, h: 1, children: [] } ), null, 'not a fourth level of groups' );
	assert.notEqual( t.holderFor( deep, 'g3', el ), null, 'but an element is not a group');
	const made = t.insertInto( list, 'g', el );
	assert.deepEqual( plain( t.findEl( made, 'g' ).children.map( ( e ) => e.id ) ), [ 'b', 'n' ] );
	assert.deepEqual( plain( made.map( ( e ) => e.id ) ), [ 'a', 'g' ] );
} );

test( 'a copy of the date of emission is a date of emission (its slot is of a kind, not of a place); another slot is not kept', () => {
	const date = { id: 'd', type: 'text', slot: 'date-of-emission', x: 0, y: 0, w: 1, h: 1, runs: [ { text: 'd/m/Y' } ] };
	assert.equal( t.cloneEl( date ).slot, 'date-of-emission' );
	assert.equal( t.cloneEl( { ...date, slot: 'title' } ).slot, undefined );
} );

const mini = () => [ rect( 'a', 0, 0, 1, 1 ), { id: 'g', type: 'group', x: 0, y: 0, w: 50, h: 50, children: [ rect( 'b', 0, 0, 1, 1 ), rect( 'c', 0, 0, 1, 1 ), rect( 'd', 0, 0, 1, 1 ) ] }, rect( 'e', 0, 0, 1, 1 ), rect( 'f', 0, 0, 1, 1 ) ];
const ids = ( list ) => plain( list.map( ( e ) => e.id ) );

test( 'several elements are removed in one go, except the ones that must stay', () => {
	const out = t.removeEls( mini(), [ 'a', 'c', 'f' ], ( el ) => el.id === 'f' );
	assert.deepEqual( ids( out.list ), [ 'g', 'e', 'f' ] );
	assert.deepEqual( ids( out.list[ 0 ].children ), [ 'b', 'd' ] );
	assert.deepEqual( plain( out.kept ), [ 'f' ] );
} );

test( 'several elements are copied after the last of them, in order, and the copies are what is selected', () => {
	const shiftBy = ( el, dx, dy ) => ( { ...el, x: el.x + dx, y: el.y + dy } );
	const out = t.duplicateEls( mini(), [ 'a', 'e' ], shiftBy, 300 );
	assert.equal( out.list.length, 6 );
	assert.deepEqual( ids( out.list ).filter( ( id ) => ! [ 'g', 'f' ].includes( id ) ).slice( 0, 2 ), [ 'a', 'e' ] );
	assert.equal( out.ids.length, 2 );
	assert.deepEqual( plain( out.ids.map( ( id ) => t.findEl( out.list, id ).x ) ), [ 10, 10 ], 'shifted' );
	assert.deepEqual( ids( out.list ).slice( 0, 2 ), [ 'a', 'g' ] );
	assert.equal( ids( out.list ).indexOf( out.ids[ 0 ] ) < ids( out.list ).indexOf( out.ids[ 1 ] ), true );
	assert.equal( ids( out.list ).indexOf( out.ids[ 0 ] ) > ids( out.list ).indexOf( 'e' ), true, 'after the last one that was selected' );
	assert.equal( t.duplicateEls( mini(), [ 'a', 'e' ], shiftBy, 5 ).list.length, 4, 'not over the most elements' );
	const inGroup = t.duplicateEls( mini(), [ 'b', 'c' ], shiftBy, 300 );
	assert.deepEqual( ids( t.findEl( inGroup.list, 'g' ).children ).slice( 0, 3 ), [ 'b', 'c', inGroup.ids[ 0 ] ], 'in their own group' );
} );

test( 'several elements go forward or back together, keeping their order, in their own lists', () => {
	assert.deepEqual( ids( t.reorderEls( mini(), [ 'a', 'e' ], 'up' ) ), [ 'g', 'a', 'f', 'e' ] );
	assert.deepEqual( ids( t.reorderEls( mini(), [ 'e', 'f' ], 'up' ) ), [ 'a', 'g', 'e', 'f' ], 'the front stays the front' );
	assert.deepEqual( ids( t.reorderEls( mini(), [ 'a', 'e' ], 'top' ) ), [ 'g', 'f', 'a', 'e' ] );
	assert.deepEqual( ids( t.reorderEls( mini(), [ 'e', 'f' ], 'bottom' ) ), [ 'e', 'f', 'a', 'g' ] );
	assert.deepEqual( ids( t.reorderEls( mini(), [ 'e', 'f' ], 'down' ) ), [ 'a', 'e', 'f', 'g' ] );
	assert.deepEqual( ids( t.findEl( t.reorderEls( mini(), [ 'b', 'd' ], 'up' ), 'g' ).children ), [ 'c', 'b', 'd' ] );
} );

test( 'lock and hide: if all have it, it is taken off; otherwise it is put on all', () => {
	assert.deepEqual( plain( t.setFlag( mini(), [ 'a', 'e' ], 'locked' ).filter( ( e ) => e.locked ).map( ( e ) => e.id ) ), [ 'a', 'e' ] );
	const some = t.setFlag( mini(), [ 'a' ], 'locked' );
	assert.deepEqual( plain( t.setFlag( some, [ 'a', 'e' ], 'locked' ).filter( ( e ) => e.locked ).map( ( e ) => e.id ) ), [ 'a', 'e' ], 'one had it: all get it' );
	assert.deepEqual( plain( t.setFlag( t.setFlag( mini(), [ 'a', 'e' ], 'hidden' ), [ 'a', 'e' ], 'hidden' ).filter( ( e ) => e.hidden ).map( ( e ) => e.id ) ), [], 'all had it: off' );
} );

test( 'several elements are moved together, but not the ones that cannot move', () => {
	const shiftBy = ( el, dx, dy ) => ( { ...el, x: el.x + dx, y: el.y + dy } );
	const list = [ rect( 'a', 0, 0, 1, 1 ), rect( 'b', 0, 0, 1, 1, { locked: true } ), rect( 'c', 0, 0, 1, 1 ) ];
	const out = t.nudgeEls( list, [ 'a', 'b' ], 5, -2, shiftBy, ( el ) => ! el.locked );
	assert.deepEqual( plain( out.map( ( e ) => [ e.id, e.x, e.y ] ) ), [ [ 'a', 5, -2 ], [ 'b', 0, 0 ], [ 'c', 0, 0 ] ] );
} );

const slide = ( el, dx, dy ) => ( { ...el, x: el.x + dx, y: el.y + dy } );
const any = () => true;

test( 'several elements align to the box they make together, side by side', () => {
	const list = [ rect( 'a', 100, 40, 50, 20 ), rect( 'b', 30, 200, 80, 10 ), rect( 'c', 300, 120, 20, 60 ) ];
	const all = [ 'a', 'b', 'c' ];
	// The union is x 30 to 320 and y 40 to 210.
	const to = ( side ) => ( id ) => at( t.alignEls( list, page, all, side, slide, any ), id );
	assert.deepEqual( all.map( to( 'left' ) ).map( ( b ) => b.x ), [ 30, 30, 30 ] );
	assert.deepEqual( all.map( to( 'right' ) ).map( ( b ) => b.x + b.w ), [ 320, 320, 320 ] );
	assert.deepEqual( all.map( to( 'center' ) ).map( ( b ) => b.x + b.w / 2 ), [ 175, 175, 175 ] );
	assert.deepEqual( all.map( to( 'top' ) ).map( ( b ) => b.y ), [ 40, 40, 40 ] );
	assert.deepEqual( all.map( to( 'bottom' ) ).map( ( b ) => b.y + b.h ), [ 210, 210, 210 ] );
	assert.deepEqual( all.map( to( 'middle' ) ).map( ( b ) => b.y + b.h / 2 ), [ 125, 125, 125 ] );
	// Aligning on one axis leaves the other alone.
	assert.deepEqual( [ at( t.alignEls( list, page, all, 'left', slide, any ), 'c' ).y ], [ 120 ] );
} );

test( 'aligning counts what is in a group from the page, and does not move what cannot move', () => {
	const list = [ rect( 'a', 50, 10, 20, 20 ), { id: 'g', type: 'group', x: 100, y: 100, w: 200, h: 100, children: [ rect( 'b', 10, 5, 20, 10 ) ] }, rect( 'c', 400, 0, 20, 20, { locked: true } ) ];
	const out = t.alignEls( list, page, [ 'a', 'b', 'c' ], 'left', slide, ( el ) => ! el.locked );
	// The union starts at 50 (a): b is at 110 on the page and goes to 50, so its own x (from its group) is 10 - 60; c is locked: it stays.
	assert.equal( at( out, 'b' ).x, 50 );
	assert.equal( t.findEl( out, 'b' ).x, -50 );
	assert.equal( at( out, 'c' ).x, 400 );
	assert.equal( at( out, 'a' ).x, 50 );
} );

test( 'aligning needs two elements, and an element already there stays the same object', () => {
	const list = [ rect( 'a', 10, 10, 20, 20 ), rect( 'b', 10, 50, 40, 20 ) ];
	assert.equal( t.alignEls( list, page, [ 'a' ], 'left', slide, any ), list );
	const out = t.alignEls( list, page, [ 'a', 'b' ], 'left', slide, any );
	assert.equal( out[ 0 ], list[ 0 ], 'a is already at the left' );
	assert.equal( out[ 1 ], list[ 1 ], 'and so is b' );
} );
