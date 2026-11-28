/**
 * What is selected, as lists of ids: ranges, toggles, select all, escape, what a click targets and the marquee (src/editor/selection.ts). Run: npm run test:js
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
	stdin: { contents: "import * as selection from './src/editor/selection'; window.selection = selection;", resolveDir: root, loader: 'ts' },
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
const s = sandbox.window.selection;
const plain = ( v ) => JSON.parse( JSON.stringify( v ) );

const rect = ( id, x, y, w, h, more = {} ) => ( { id, type: 'rect', x, y, w, h, ...more } );
// a, g( b, c, h( d ) ), e, f (a locked background)
const tree = [
	rect( 'a', 0, 0, 10, 10 ),
	{ id: 'g', type: 'group', x: 50, y: 50, w: 100, h: 100, children: [ rect( 'b', 0, 0, 10, 10 ), rect( 'c', 30, 0, 10, 10 ), { id: 'h', type: 'group', x: 0, y: 40, w: 50, h: 50, children: [ rect( 'd', 0, 0, 5, 5 ) ] } ] },
	rect( 'e', 200, 0, 10, 10 ),
	rect( 'f', 0, 0, 500, 500, { locked: true } ),
	{ id: 'q', type: 'qr', x: 300, y: 0, w: 10, h: 10 },
];

test( 'two ends are lifted to the same list before a range is made, as the core does', () => {
	assert.deepEqual( plain( s.commonDepth( tree, 'b', 'c' ) ), { parent: 'g', start: 'b', end: 'c' } );
	assert.deepEqual( plain( s.commonDepth( tree, 'b', 'd' ) ), { parent: 'g', start: 'b', end: 'h' } );
	assert.deepEqual( plain( s.commonDepth( tree, 'a', 'd' ) ), { parent: null, start: 'a', end: 'g' } );
	assert.deepEqual( plain( s.rangeIds( tree, 'a', 'e' ) ), [ 'a', 'g', 'e' ] );
	assert.deepEqual( plain( s.rangeIds( tree, 'c', 'b' ) ), [ 'b', 'c' ], 'in the order of the list, whichever end was first' );
	assert.deepEqual( plain( s.rangeIds( tree, 'b', 'e' ) ), [ 'g', 'e' ], 'from inside a group to outside: the group' );
} );

test( 'a toggle adds or removes one element, even one that is not next to the others', () => {
	assert.deepEqual( plain( s.toggleIds( tree, [], 'a' ) ), [ 'a' ] );
	assert.deepEqual( plain( s.toggleIds( tree, [ 'a' ], 'e' ) ), [ 'a', 'e' ] );
	assert.deepEqual( plain( s.toggleIds( tree, [ 'a', 'e' ], 'a' ) ), [ 'e' ] );
	assert.deepEqual( plain( s.toggleIds( tree, [ 'b' ], 'e' ) ), [ 'g', 'e' ], 'what is in another list is lifted to the common one' );
	assert.deepEqual( plain( s.toggleIds( tree, [ 'e' ], 'a' ) ), [ 'a', 'e' ], 'in the order of the list' );
} );

test( 'order, contiguity and the parent of a selection', () => {
	assert.deepEqual( plain( s.inListOrder( tree, [ 'e', 'a', 'c' ] ) ), [ 'a', 'c', 'e' ] );
	assert.equal( s.isContiguous( tree, [ 'a', 'g' ] ), true );
	assert.equal( s.isContiguous( tree, [ 'a', 'e' ] ), false );
	assert.equal( s.isContiguous( tree, [ 'b', 'c' ] ), true );
	assert.equal( s.isContiguous( tree, [ 'a', 'b' ] ), false, 'two lists' );
	assert.equal( s.commonParent( tree, [ 'b', 'c' ] ), 'g' );
	assert.equal( s.commonParent( tree, [ 'a', 'e' ] ), null );
	assert.equal( s.commonParent( tree, [ 'b', 'e' ] ), undefined );
} );

test( 'select all: the level, then the group that holds it', () => {
	assert.deepEqual( plain( s.selectAllIds( tree, [] ) ), [ 'a', 'g', 'e', 'f', 'q' ] );
	assert.deepEqual( plain( s.selectAllIds( tree, [ 'a' ] ) ), [ 'a', 'g', 'e', 'f', 'q' ] );
	assert.deepEqual( plain( s.selectAllIds( tree, [ 'b' ] ) ), [ 'b', 'c', 'h' ] );
	assert.deepEqual( plain( s.selectAllIds( tree, [ 'b', 'c', 'h' ] ) ), [ 'g' ], 'all of a level selected: its group' );
	assert.deepEqual( plain( s.selectAllIds( tree, [ 'a', 'g', 'e', 'f', 'q' ] ) ), [ 'a', 'g', 'e', 'f', 'q' ], 'the top level has no group above' );
} );

test( 'escape: several become the first, one goes up to its group, then nothing', () => {
	assert.deepEqual( plain( s.escapeIds( tree, [ 'e', 'a' ] ) ), [ 'a' ] );
	assert.deepEqual( plain( s.escapeIds( tree, [ 'd' ] ) ), [ 'h' ] );
	assert.deepEqual( plain( s.escapeIds( tree, [ 'a' ] ) ), [] );
	assert.deepEqual( plain( s.escapeIds( tree, [] ) ), [] );
} );

test( 'a click targets the outermost group, or the level of what is already selected', () => {
	assert.equal( s.clickTarget( tree, 'd', [] ), 'g' );
	assert.equal( s.clickTarget( tree, 'd', [ 'g' ] ), 'g', 'the group is selected: it stays the target' );
	assert.equal( s.clickTarget( tree, 'd', [ 'h' ] ), 'h', 'what was gone down to stays the target' );
	assert.equal( s.clickTarget( tree, 'd', [ 'b' ] ), 'h', 'next to what is selected, in the same group: that level' );
	assert.equal( s.clickTarget( tree, 'c', [ 'b' ] ), 'c' );
	assert.equal( s.clickTarget( tree, 'e', [ 'b' ] ), 'e', 'outside that group: the outermost' );
	assert.equal( s.clickTarget( tree, 'a', [] ), 'a' );
} );

test( 'the marquee takes what it touches in one list, not what is locked or hidden', () => {
	const placed = [
		rect( 'a', 0, 0, 10, 10 ),
		{ id: 'g', type: 'group', x: 50, y: 50, w: 100, h: 100, children: [ rect( 'b', 0, 0, 10, 10, { ox: 50, oy: 50 } ), rect( 'c', 30, 0, 10, 10, { ox: 50, oy: 50 } ), rect( 'x', 0, 20, 10, 10, { ox: 50, oy: 50, hidden: true } ) ] },
		rect( 'e', 200, 0, 10, 10 ),
		rect( 'f', 0, 0, 500, 500, { locked: true } ),
	];
	assert.deepEqual( plain( s.marqueeIds( placed, { x: 0, y: 0, w: 60, h: 60 }, null ) ), [ 'a', 'b' ], 'a group the box only touches: what in it is touched' );
	assert.deepEqual( plain( s.marqueeIds( placed, { x: 0, y: 0, w: 100, h: 100 }, null ) ), [ 'a', 'b', 'c' ], 'several inside a group: all of them' );
	assert.deepEqual( plain( s.marqueeIds( placed, { x: -10, y: -10, w: 400, h: 400 }, null ) ), [ 'a', 'g', 'e' ], 'a group the box holds whole: the group' );
	assert.deepEqual( plain( s.marqueeIds( placed, { x: 140, y: 140, w: 5, h: 5 }, null ) ), [ 'g' ], 'its frame touched, nothing in it: the group' );
	assert.deepEqual( plain( s.marqueeIds( placed, { x: 45, y: 45, w: 20, h: 20 }, 'g' ) ), [ 'b' ], 'inside a group: what is in it' );
	assert.deepEqual( plain( s.marqueeIds( placed, { x: 45, y: 65, w: 20, h: 20 }, 'g' ) ), [], 'a hidden one is not taken' );
	assert.deepEqual( plain( s.marqueeIds( placed, { x: 190, y: 0, w: 5, h: 5 }, null ) ), [], 'touching nothing' );
} );

test( 'what the selection is: the type when all are the same, and whether it can be grouped', () => {
	assert.deepEqual( plain( s.selectionType( tree, [ 'a', 'e' ] ) ), { type: 'rect' } );
	assert.equal( s.selectionType( tree, [ 'a', 'g' ] ), null );
	assert.equal( s.selectionType( tree, [] ), null );
	assert.equal( s.canGroupIds( tree, [ 'a', 'e' ] ), true );
	assert.equal( s.canGroupIds( tree, [ 'b', 'e' ] ), false, 'two lists' );
	assert.equal( s.canGroupIds( tree, [ 'q' ] ), false, 'the authenticity code stays on the page' );
	assert.equal( s.canGroupIds( tree, [] ), false );
} );
