/**
 * Copying and pasting elements: what goes on the clipboard and where a paste puts them (src/editor/clipboard.ts). Run: npm run test:js
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
	stdin: { contents: "import * as clipboard from './src/editor/clipboard'; import * as tree from './src/editor/tree'; window.clipboard = clipboard; window.tree = tree;", resolveDir: root, loader: 'ts' },
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
const c = sandbox.window.clipboard;
const t = sandbox.window.tree;
const plain = ( v ) => JSON.parse( JSON.stringify( v ) );

const page = { w: 800, h: 600, safe: 0 };
const rect = ( id, x, y, w, h, more = {} ) => ( { id, type: 'rect', x, y, w, h, fill: '#000000', ...more } );
const group = ( id, x, y, w, h, children ) => ( { id, type: 'group', x, y, w, h, children } );
const tree = () => [ rect( 'a', 0, 0, 10, 10 ), group( 'g', 50, 50, 100, 100, [ rect( 'b', 10, 10, 5, 5 ), rect( 'c', 20, 20, 5, 5 ) ] ), rect( 'e', 300, 0, 10, 10 ), { id: 'q', type: 'qr', x: 400, y: 0, w: 10, h: 10 } ];
const design = { page, colors: { primary: { name: 'Primary', light: '#3858e9' } }, palette: [ { slug: 'white', color: '#ffffff' } ], elements: [] };

test( 'a copy has the elements as they are on the page, and none that is in another that is copied too', () => {
	const out = c.copyPayload( tree(), page, [ 'b', 'e' ], design );
	assert.equal( out.version, 1 );
	assert.deepEqual( plain( out.elements.map( ( e ) => [ e.id, e.x, e.y ] ) ), [ [ 'b', 60, 60 ], [ 'e', 300, 0 ] ], 'b is in a group at 50, 50: on the page it is at 60, 60' );
	const both = c.copyPayload( tree(), page, [ 'g', 'b' ], design );
	assert.deepEqual( plain( both.elements.map( ( e ) => e.id ) ), [ 'g' ], 'b is in g, which is copied whole' );
	assert.equal( both.elements[ 0 ].children.length, 2 );
} );

test( 'the plain text of a copy is the words of its texts, one a line', () => {
	const els = [ { id: 't1', type: 'text', runs: [ { text: 'Dear ' }, { field: 'name' } ] }, rect( 'r', 0, 0, 1, 1 ), { id: 't2', type: 'text', runs: [ { text: 'Thanks', b: true } ] } ];
	assert.equal( c.plainTextOf( els ), 'Dear [[name]]\n**Thanks**' );
} );

test( 'a copy or a cut is the selected elements\' unless the user selected text of their own, or is in a field', () => {
	const base = { selected: 1, editable: false, hasTextSelection: false, defaultPrevented: false };
	assert.equal( c.copyIsOfElements( base ), true, 'an element is selected and nothing else' );
	assert.equal( c.copyIsOfElements( { ...base, hasTextSelection: true } ), false, 'text selected anywhere on the page (the sidebar, a notice) is copied as text' );
	assert.equal( c.copyIsOfElements( { ...base, editable: true } ), false, 'in a field the field copies' );
	assert.equal( c.copyIsOfElements( { ...base, selected: 0 } ), false, 'nothing selected: nothing of ours to copy' );
	assert.equal( c.copyIsOfElements( { ...base, defaultPrevented: true } ), false, 'what another handler took' );
} );

test( 'a paste has new ids, goes in the selected group when it holds them, and keeps its place on the page', () => {
	const copy = c.copyPayload( tree(), page, [ 'e' ], design );
	const out = c.pasteEls( tree(), page, copy.elements, { selected: [ 'g' ], offset: 0 } );
	const g = t.findEl( out.list, 'g' );
	assert.equal( g.children.length, 3, 'in the group, at the end' );
	assert.equal( out.ids.length, 1 );
	assert.notEqual( out.ids[ 0 ], 'e' );
	const pasted = t.findEl( out.list, out.ids[ 0 ] );
	assert.deepEqual( [ pasted.x, pasted.y ], [ 250, -50 ], 'from the corner of the group: 300 - 50, 0 - 50' );
} );

test( 'with something selected a paste goes right after it, in its own list; with nothing, at the end of the page', () => {
	const copy = c.copyPayload( tree(), page, [ 'e' ], design );
	const after = c.pasteEls( tree(), page, copy.elements, { selected: [ 'a' ], offset: 0 } );
	assert.deepEqual( plain( after.list.map( ( e ) => e.id ) ).slice( 0, 2 ), [ 'a', after.ids[ 0 ] ] );
	const inGroup = c.pasteEls( tree(), page, copy.elements, { selected: [ 'b' ], offset: 0 } );
	assert.deepEqual( plain( t.findEl( inGroup.list, 'g' ).children.map( ( e ) => e.id ) ).slice( 0, 2 ), [ 'b', inGroup.ids[ 0 ] ], 'in the group of what is selected' );
	const none = c.pasteEls( tree(), page, copy.elements, { selected: [], offset: 0 } );
	assert.equal( none.list[ none.list.length - 1 ].id, none.ids[ 0 ] );
	const front = c.pasteEls( tree(), page, copy.elements, { selected: [ 'a', 'e' ], offset: 0 } );
	assert.equal( front.list.findIndex( ( e ) => e.id === front.ids[ 0 ] ), front.list.findIndex( ( e ) => e.id === 'e' ) + 1, 'after the one at the front of the selection' );
} );

test( 'a paste is moved by the offset, and refused when there would be too many elements', () => {
	const copy = c.copyPayload( tree(), page, [ 'e' ], design );
	const out = c.pasteEls( tree(), page, copy.elements, { selected: [], offset: 10 } );
	assert.deepEqual( plain( [ out.list[ out.list.length - 1 ].x, out.list[ out.list.length - 1 ].y ] ), [ 310, 10 ] );
	assert.equal( c.pasteEls( tree(), page, copy.elements, { selected: [], offset: 0, max: 6 } ), null, 'six in all: no room for one more' );
} );

test( 'the code of authenticity is not pasted when the certificate has one', () => {
	const copy = c.copyPayload( tree(), page, [ 'q', 'e' ], design );
	const out = c.pasteEls( tree(), page, copy.elements, { selected: [], offset: 0 } );
	assert.equal( out.ids.length, 1, 'only e' );
	assert.equal( out.list.filter( ( e ) => e.type === 'qr' ).length, 1 );
} );

test( 'a color of the theme that the other template does not have becomes its color', () => {
	const els = [ rect( 'r', 0, 0, 1, 1, { fill: '$primary', stroke: '$white' } ), rect( 's', 0, 0, 1, 1, { fill: '$kept' } ) ];
	const target = { page, colors: { kept: { name: 'Kept', light: '#111111' } }, palette: [], elements: [] };
	const out = plain( c.adoptColors( els, design, target ) );
	assert.deepEqual( [ out[ 0 ].fill, out[ 0 ].stroke ], [ '#3858e9', '#ffffff' ], 'primary and white are not there: the values' );
	assert.equal( out[ 1 ].fill, '$kept', 'one that is there stays a reference' );
} );
