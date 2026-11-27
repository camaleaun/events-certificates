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

const { alignAxis, unitOf, lengthIn, sizeIn, resizeSideLen, unitPatch } = sandbox.size;

test( 'a length says its unit, and a length in points is written in it', () => {
	assert.deepEqual( [ '12mm', '3.5in', '10px', '5', 'calc(1pt + 1pt)', '50%' ].map( unitOf ), [ 'mm', 'in', 'px', 'pt', 'pt', '%' ] );
	assert.equal( lengthIn( 120.472, 'mm' ), '42.5mm' );
	assert.equal( lengthIn( 90, 'in' ), '1.25in' );
	assert.equal( lengthIn( 12.345, 'px' ), '12.35px' );
	assert.equal( lengthIn( 50, '%', 200 ), '25%' );
} );

test( 'aligning an axis zeroes the offsets in the unit it was written in and keeps the size', () => {
	assert.deepEqual( plain( alignAxis( 'start', 96, { mode: 'center', start: '33mm', size: '96pt' }, 842 ) ), { mode: 'start', start: '0pt', size: '96pt' } );
	assert.deepEqual( plain( alignAxis( 'start', 85.0395, { mode: 'start', start: '12mm', size: '30mm' }, 842 ) ), { mode: 'start', start: '0mm', size: '30mm' } );
	assert.deepEqual( plain( alignAxis( 'center', 85.0395, { mode: 'start', start: '12mm', size: '30mm' }, 842 ) ), { mode: 'center', start: '0mm', size: '30mm' } );
	assert.deepEqual( plain( alignAxis( 'end', 85.0395, { mode: 'start', start: '12mm', size: '30mm' }, 842 ) ), { mode: 'end', end: '0mm', size: '30mm' } );
	assert.deepEqual( plain( alignAxis( 'stretch', 85.0395, { mode: 'start', start: '12mm', size: '30mm' }, 842 ) ), { mode: 'stretch', start: '0mm', end: '0mm' } );
	assert.deepEqual( plain( alignAxis( 'end', 421, { mode: 'stretch', start: '10%', end: '10%' }, 842 ) ), { mode: 'end', end: '0%', size: '50%' }, 'a % axis stays a %' );
} );

test( 'the size of an element is shown in the unit of its layout', () => {
	const el = { x: 0, y: 0, w: 120.472, h: 96, layout: { h: { mode: 'start', start: '0pt', size: '42.5mm' }, v: { mode: 'start', start: '0pt', size: '96pt' } } };
	assert.deepEqual( plain( sizeIn( el, 'w' ) ), { value: 42.5, unit: 'mm' } );
	assert.deepEqual( plain( sizeIn( el, 'h' ) ), { value: 96, unit: 'pt' } );
	assert.deepEqual( plain( sizeIn( { x: 0, y: 0, w: 50.25, h: 10 }, 'w' ) ), { value: 50.25, unit: 'pt' }, 'no layout: points, decimals kept' );
} );

test( 'a size typed with a unit keeps the unit and the decimals, and a change of unit alone keeps the size', () => {
	const el = { x: 100, y: 50, w: 96, h: 96, layout: { h: { mode: 'start', start: '100pt', size: '96pt' }, v: { mode: 'start', start: '50pt', size: '96pt' }, ref: 'page' } };
	const typed = plain( resizeSideLen( el, 'w', '42.5mm', page ) );
	assert.equal( typed.w, 120.47 );
	assert.equal( typed.h, 96 );
	assert.equal( typed.layout.h.size, '42.5mm' );
	assert.equal( typed.layout.v.size, '96pt', 'the other side keeps its own unit' );
	const unit = plain( resizeSideLen( el, 'w', '33.87mm', page ) );
	assert.equal( unit.w, undefined, 'a change of unit alone does not change the size' );
	assert.equal( unit.layout.h.size, '33.87mm' );
	const bare = plain( resizeSideLen( { x: 10, y: 10, w: 96, h: 96 }, 'h', '1.5in', page ) );
	assert.equal( bare.h, 108, 'an element with no layout gets one to keep the unit' );
	assert.equal( bare.layout.v.size, '1.5in' );
	assert.deepEqual( plain( resizeSideLen( { ...el, layout: { ...el.layout, h: { mode: 'stretch', start: '0pt', end: '0pt' } } }, 'w', '10mm', page ) ), {}, 'a size that fills is decided by the margins' );
} );

test( 'another unit for the same size is written with more decimals, so the size does not drift', () => {
	const el = { x: 0, y: 0, w: 96, h: 96, layout: { h: { mode: 'start', start: '0pt', size: '96pt' }, v: { mode: 'start', start: '0pt', size: '96pt' }, ref: 'page' } };
	const cm = plain( unitPatch( el, 'w', 'cm', page ) );
	assert.equal( cm.layout.h.size, '3.3867cm' );
	assert.equal( cm.w, undefined, 'the size is not changed' );
	assert.equal( plain( unitPatch( { ...el, layout: cm.layout }, 'w', 'pt', page ) ).layout.h.size, '96pt', 'and comes back exact' );
	assert.equal( lengthIn( 96, 'in', 0, 4 ), '1.3333in' );
	assert.deepEqual( plain( unitPatch( { ...el, layout: { ...el.layout, h: { mode: 'stretch', start: '0pt', end: '0pt' } } }, 'w', 'mm', page ) ), {} );
	assert.equal( plain( unitPatch( { x: 5, y: 5, w: 96, h: 96 }, 'h', 'mm', page ) ).layout.v.size, '33.8666mm', 'an element with no layout gets one' );
} );

