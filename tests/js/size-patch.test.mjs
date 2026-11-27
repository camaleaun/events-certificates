/**
 * The size typed in the SVG editor's Size row, as the patch of the element (src/editor/size.ts). Run: npm run test:js
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
	entryPoints: [ 'src/editor/size.ts' ],
	bundle: true,
	write: false,
	format: 'iife',
	globalName: 'size',
	alias: { '@camaleaun/draw': 'vendor/camaleaun/draw/packages/js/src/index.ts' },
} );
const sandbox = {};
vm.runInNewContext( built.outputFiles[ 0 ].text + ';this.size = size;', sandbox );
// Objects of the sandbox have its own prototypes: they are compared as the plain data they are.
const plain = ( v ) => JSON.parse( JSON.stringify( v ) );
const sizePatch = ( ...args ) => plain( sandbox.size.sizePatch( ...args ) );
const { shown } = sandbox.size;
const followed = ( ...a ) => sandbox.size.followed( ...a );
const stretched = ( ...a ) => plain( sandbox.size.stretched( ...a ) );
const resizeSide = ( ...a ) => plain( sandbox.size.resizeSide( ...a ) );

const page = { w: 841.89, h: 595.28, safe: 28.35 };
const at = { x: 100, y: 50, w: 96, h: 96 };

test( 'nothing is changed when there is no size or it is the one the element has', () => {
	assert.deepEqual( sizePatch( at, undefined, undefined, page ), {} );
	assert.deepEqual( sizePatch( at, 96, undefined, page ), {} );
	assert.deepEqual( sizePatch( at, 96, 96, page ), {} );
	assert.deepEqual( sizePatch( { ...at, w: 96.004, h: 95.996 }, 96, 96, page ), {} );
} );

test( 'a new size is the element\'s width and height, with no layout when it has none', () => {
	assert.deepEqual( sizePatch( at, 200, 96, page ), { w: 200, h: 96 } );
} );

test( 'a layout is made again for the box the element has now', () => {
	const layout = { h: { mode: 'start', start: '100pt', size: '96pt' }, v: { mode: 'start', start: '50pt', size: '96pt' }, ref: 'page' };
	const patch = sizePatch( { ...at, layout }, 200, 120, page );
	assert.equal( patch.w, 200 );
	assert.equal( patch.h, 120 );
	assert.equal( patch.layout.h.size, '200pt' );
	assert.equal( patch.layout.v.size, '120pt' );
	assert.equal( patch.layout.h.start, '100pt' );
} );

test( 'the size is shown with two decimals at most', () => {
	assert.equal( shown( 96 ), 96 );
	assert.equal( shown( 372.94500732421875 ), 372.95 );
} );

const axis = ( mode, extra = {} ) => ( { mode, start: '0pt', size: '96pt', ...extra } );
const laid = ( h, v ) => ( { h, v, ref: 'page' } );

test( 'a size follows the other only when the ratio is kept: the height, unless only the height fills', () => {
	assert.equal( followed( at ), null );
	assert.equal( followed( { ...at, lock: 2 } ), 'h', 'No layout: the height follows.' );
	assert.equal( followed( { ...at, lock: 2, layout: laid( axis( 'start' ), axis( 'start' ) ) } ), 'h' );
	assert.equal( followed( { ...at, lock: 2, layout: laid( axis( 'start' ), axis( 'stretch' ) ) } ), 'w', 'Only the height fills: the width follows.' );
	assert.equal( followed( { ...at, lock: 2, layout: laid( axis( 'stretch' ), axis( 'stretch' ) ) } ), null, 'Filling both keeps both.' );
} );

test( 'an axis that fills decides its size: the Size row does not', () => {
	assert.deepEqual( stretched( at ), { w: false, h: false } );
	assert.deepEqual( stretched( { ...at, layout: laid( axis( 'stretch' ), axis( 'start' ) ) } ), { w: true, h: false } );
} );

test( 'a width typed in the Size row changes the element, and the height follows the lock', () => {
	assert.deepEqual( resizeSide( at, 'w', 200, page ), { w: 200, h: 96 } );
	assert.deepEqual( resizeSide( { ...at, lock: 2 }, 'w', 200, page ), { w: 200, h: 100 } );
	assert.deepEqual( resizeSide( { ...at, lock: 2 }, 'h', 50, page ), { w: 96, h: 50 }, 'The width does not follow: only the height does.' );
	assert.deepEqual( resizeSide( at, 'h', 0, page ), {} );
	assert.deepEqual( resizeSide( at, 'w', NaN, page ), {} );
} );

test( 'the layout keeps its unit when the size is typed', () => {
	const layout = laid( { mode: 'start', start: '10%', size: '10%' }, { mode: 'start', start: '0pt', size: '96pt' } );
	const patch = resizeSide( { x: 84.189, y: 0, w: 84.189, h: 96, layout }, 'w', 168.378, page );
	assert.equal( patch.layout.h.size, '20%' );
	assert.equal( patch.layout.v.size, '96pt' );
} );
