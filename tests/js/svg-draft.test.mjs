/**
 * The draft of a drawing in the SVG dialogs (src/editor/svg-draft.ts): what a new drawing is, what a removed last path gives back,
 * what a pasted SVG replaces, and the ink of the "current color" swatch. Run: npm run test:js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const root = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const built = await esbuild.build( { absWorkingDir: root, entryPoints: [ 'src/editor/svg-draft.ts' ], bundle: true, write: false, format: 'iife', globalName: 'draft' } );
const sandbox = {};
vm.runInNewContext( built.outputFiles[ 0 ].text + ';this.draft = draft;', sandbox );
// Objects of the sandbox have its own prototypes: they are compared as the plain data they are.
const plain = ( v ) => JSON.parse( JSON.stringify( v ) );
const { INFO_ICON, INFO_SIZE, INFO_VIEWBOX, infoSvg, inkOn } = sandbox.draft;
const emptiedElement = () => sandbox.draft.emptiedElement();
const defaultDrawing = () => plain( sandbox.draft.defaultDrawing() );
const refilled = ( ...args ) => plain( sandbox.draft.refilled( ...args ) );
const taken = ( ...args ) => plain( sandbox.draft.taken( ...args ) );

test( 'a new drawing is the info icon, 24 x 24, shown at 96 x 96, and always a new object', () => {
	assert.deepEqual( defaultDrawing(), { title: '', vw: 24, vh: 24, w: 96, h: 96, paths: [ { d: INFO_ICON } ] } );
	assert.equal( INFO_VIEWBOX, 24 );
	assert.equal( INFO_SIZE, 96 );
	const a = sandbox.draft.defaultDrawing();
	const b = sandbox.draft.defaultDrawing();
	assert.notEqual( a, b );
	assert.notEqual( a.paths, b.paths );
	a.paths[ 0 ].d = 'M0 0Z';
	assert.equal( b.paths[ 0 ].d, INFO_ICON );
} );

test( 'the info icon is one path with no fill-rule, and its code is the example of the SVG code box', () => {
	assert.match( INFO_ICON, /^M[0-9. ]+a/ );
	assert.equal( infoSvg(), `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">\n  <path d="${ INFO_ICON }"/>\n</svg>` );
	assert.ok( ! /fill/.test( infoSvg() ) );
} );

test( 'a drawing whose last path is removed goes back to what a new one is, with its title', () => {
	const gone = { title: 'Seal', vw: 600, vh: 200, w: 300, h: 100, paths: [] };
	assert.deepEqual( refilled( gone ), { title: 'Seal', vw: 24, vh: 24, w: 96, h: 96, paths: [ { d: INFO_ICON } ] } );
} );

test( 'an element of a template whose last path is removed gets the icon and keeps its size on the page', () => {
	const gone = { title: 'Seal', vw: 600, vh: 200, w: 210.5, h: 70, paths: [] };
	assert.deepEqual( refilled( gone, true ), { title: 'Seal', vw: 24, vh: 24, w: 210.5, h: 70, paths: [ { d: INFO_ICON } ] } );
} );

test( 'a pasted SVG replaces a drawing as it is, and an element keeps its title and size', () => {
	const pasted = { title: 'file', vw: 100, vh: 50, w: 400, h: 200, paths: [ { d: 'M0 0L1 1Z' } ] };
	const base = { title: 'Seal', vw: 24, vh: 24, w: 96, h: 96, paths: [ { d: INFO_ICON } ] };
	assert.deepEqual( taken( pasted, base ), pasted );
	assert.deepEqual( taken( pasted, base, true ), { title: 'Seal', vw: 100, vh: 50, w: 96, h: 96, paths: [ { d: 'M0 0L1 1Z' } ] } );
} );

test( 'the ink over a swatch is white over a dark color and near black over a light one', () => {
	assert.equal( inkOn( '#1e1e1e' ), '#fff' );
	assert.equal( inkOn( '#000' ), '#fff' );
	assert.equal( inkOn( '#000000ff' ), '#fff' );
	assert.equal( inkOn( 'rgb(30, 30, 30)' ), '#fff' );
	assert.equal( inkOn( '#ffffff' ), '#1e1e1e' );
	assert.equal( inkOn( '#f0f0f0' ), '#1e1e1e' );
	assert.equal( inkOn( '#fff' ), '#1e1e1e' );
	assert.equal( inkOn( 'rgba(250, 250, 250, 0.9)' ), '#1e1e1e' );
	assert.equal( inkOn( '#e2e4eb' ), '#1e1e1e' );
	assert.equal( inkOn( '#2c3e9e' ), '#fff' );
} );

test( 'a color that cannot be read is taken as dark', () => {
	assert.equal( inkOn( '' ), '#fff' );
	assert.equal( inkOn( 'none' ), '#fff' );
	assert.equal( inkOn( 'var(--x)' ), '#fff' );
	assert.equal( inkOn( '#12' ), '#fff' );
} );

test( 'the patch of an element whose last path is removed is the icon on a 24 x 24 viewBox, and nothing of its place', () => {
	const patch = emptiedElement();
	assert.deepEqual( Object.keys( patch ).sort(), [ 'fill', 'markup', 'paths', 'vh', 'vw' ] );
	assert.equal( patch.vw, 24 );
	assert.equal( patch.vh, 24 );
	assert.deepEqual( plain( patch.paths ), [ { d: INFO_ICON } ] );
	assert.equal( patch.markup, undefined );
	assert.equal( patch.fill, undefined );
	// As the editor merges a patch into the element: its size, place, title and color stay.
	const el = { id: 's', type: 'svg', title: 'Seal', x: 10, y: 20, w: 210.5, h: 70, vw: 600, vh: 200, color: '$accent', markup: '<svg/>', fill: '#f00', paths: [] };
	const merged = { ...el, ...patch };
	assert.deepEqual( [ merged.x, merged.y, merged.w, merged.h, merged.title, merged.color ], [ 10, 20, 210.5, 70, 'Seal', '$accent' ] );
	assert.equal( merged.markup, undefined );
	assert.equal( merged.fill, undefined );
	// A new object each time.
	const a = emptiedElement();
	a.paths[ 0 ].d = 'M0 0Z';
	assert.equal( emptiedElement().paths[ 0 ].d, INFO_ICON );
} );
