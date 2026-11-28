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
// The drawing package too: an axis written back must resolve to the box it was written from.
const drawBuilt = await esbuild.build( {
	absWorkingDir: root,
	entryPoints: [ 'vendor/camaleaun/draw/packages/js/src/index.ts' ],
	bundle: true,
	write: false,
	format: 'iife',
	globalName: 'draw',
} );
vm.runInNewContext( drawBuilt.outputFiles[ 0 ].text + ';this.draw = draw;', sandbox );
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

const { alignAxis, anchorAxis, axisAt, fontSizePt, fontSizeText, unitOf, lengthIn, sizeIn, resizeSideLen, unitPatch, radiusUnit, radiusNumber, radiusFrom, radiusConvert } = sandbox.size;

test( 'a length says its unit, and a length in points is written in it', () => {
	assert.deepEqual( [ '12mm', '3.5in', '10px', '5', 'calc(1pt + 1pt)', '50%' ].map( unitOf ), [ 'mm', 'in', 'px', 'pt', 'pt', '%' ] );
	assert.equal( lengthIn( 120.472, 'mm' ), '42.5mm' );
	assert.equal( lengthIn( 90, 'in' ), '1.25in' );
	assert.equal( lengthIn( 12.345, 'px' ), '12.35px' );
	assert.equal( lengthIn( 50, '%', 200 ), '25%' );
} );

const MM = 2.83465;

test( 'changing the anchor keeps the place and the unit of the axis', () => {
	const prev = { mode: 'start', start: '12mm', size: '30mm' };
	const pos = 12 * MM;
	const size = 30 * MM;
	assert.deepEqual( plain( anchorAxis( 'end', pos, size, prev, 0, 842 ) ), { mode: 'end', end: lengthIn( 842 - pos - size, 'mm' ), size: '30mm' }, 'to the end: only the keys the mode reads' );
	assert.deepEqual( plain( anchorAxis( 'center', pos, size, prev, 0, 842 ) ), { mode: 'center', start: lengthIn( pos - ( 842 - size ) / 2, 'mm' ), size: '30mm' } );
	assert.deepEqual( plain( anchorAxis( 'stretch', pos, size, prev, 0, 842 ) ), { mode: 'stretch', start: lengthIn( pos, 'mm' ), end: lengthIn( 842 - pos - size, 'mm' ) }, 'to fill: two insets, no size' );
	assert.deepEqual( plain( anchorAxis( 'start', pos, size, { mode: 'end', end: '80mm', size: '30mm' }, 0, 842 ) ), { mode: 'start', start: '12mm', size: '30mm' }, 'no end left on a start axis' );
} );

test( 'a % axis stays a %, and what is not a plain length is written in points', () => {
	assert.deepEqual( plain( anchorAxis( 'end', 84.2, 168.4, { mode: 'start', start: '10%', size: '20%' }, 0, 842 ) ), { mode: 'end', end: '70%', size: '20%' } );
	const calc = plain( anchorAxis( 'end', 100, 96, { mode: 'start', start: 'calc(10% + 4pt)', size: 'calc(10% + 4pt)' }, 0, 842 ) );
	assert.match( calc.end, /pt$/ );
	assert.match( calc.size, /pt$/ );
} );

test( 'a drawing that says no size keeps saying none, unless it comes from a fill', () => {
	assert.deepEqual( plain( anchorAxis( 'end', 10, 100, { mode: 'start', start: '10pt' }, 0, 842 ) ), { mode: 'end', end: '732pt' } );
	assert.equal( plain( anchorAxis( 'start', 10, 100, { mode: 'stretch', start: '10pt', end: '10pt' }, 0, 842 ) ).size, '100pt' );
} );

test( 'an anchor written back resolves to the box it was written from, in every mode and box', () => {
	const box = { x: 123.4, y: 50, w: 85.04, h: 96 };
	for ( const ref of [ 'page', 'safe' ] ) {
		for ( const mode of [ 'start', 'center', 'end', 'stretch' ] ) {
			const origin = ref === 'safe' ? page.safe : 0;
			const total = ref === 'safe' ? page.w - page.safe * 2 : page.w;
			const axis = plain( anchorAxis( mode, box.x, box.w, { mode: 'start', start: '1mm', size: '1mm' }, origin, total ) );
			const el = { type: 'rect', x: 0, y: 0, w: 1, h: 1, layout: { ref, h: axis, v: { mode: 'start', start: '0pt', size: '10pt' } } };
			const out = sandbox.draw.resolve( el, page );
			// A length is written with two decimals in its unit (0.01 mm is 0.028 pt): the box comes back within 0.02 pt.
			assert.ok( Math.abs( out.x - box.x ) < 0.02 && Math.abs( out.w - box.w ) < 0.02, `${ ref } ${ mode }: ${ out.x }, ${ out.w }` );
		}
	}
} );

test( 'aligning moves the element to the side of its box and keeps its anchor', () => {
	const size = 30 * MM;
	// Anchored to the end: aligned to the left it is at 0, which is "right" = the box minus its width.
	const end = { mode: 'end', end: '12mm', size: '30mm' };
	assert.deepEqual( plain( alignAxis( 'start', size, end, 0, 842 ) ), { mode: 'end', end: lengthIn( 842 - size, 'mm' ), size: '30mm' }, 'the anchor stays the end' );
	assert.deepEqual( plain( alignAxis( 'end', size, end, 0, 842 ) ), { mode: 'end', end: '0mm', size: '30mm' } );
	assert.deepEqual( plain( alignAxis( 'center', size, end, 0, 842 ) ), { mode: 'end', end: lengthIn( ( 842 - size ) / 2, 'mm' ), size: '30mm' } );
	// Anchored to the start: left is 0, center is half of what is left, right is what is left.
	const start = { mode: 'start', start: '12mm', size: '30mm' };
	assert.deepEqual( plain( alignAxis( 'start', size, start, 0, 842 ) ), { mode: 'start', start: '0mm', size: '30mm' } );
	assert.deepEqual( plain( alignAxis( 'center', size, start, 0, 842 ) ), { mode: 'start', start: lengthIn( ( 842 - size ) / 2, 'mm' ), size: '30mm' } );
	assert.deepEqual( plain( alignAxis( 'end', size, start, 0, 842 ) ), { mode: 'start', start: lengthIn( 842 - size, 'mm' ), size: '30mm' } );
	// Anchored to the center: the offset is the shift from the center.
	const center = { mode: 'center', start: '33mm', size: '30mm' };
	assert.deepEqual( plain( alignAxis( 'center', size, center, 0, 842 ) ), { mode: 'center', start: '0mm', size: '30mm' } );
	assert.deepEqual( plain( alignAxis( 'start', size, center, 0, 842 ) ), { mode: 'center', start: lengthIn( -( 842 - size ) / 2, 'mm' ), size: '30mm' } );
	// An axis that fills has nothing to align.
	const fill = { mode: 'stretch', start: '10mm', end: '10mm' };
	assert.deepEqual( plain( alignAxis( 'start', size, fill, 0, 842 ) ), plain( fill ) );
} );

test( 'an aligned axis resolves to the side it was aligned to, from any anchor and from a margin', () => {
	const w = 85.04;
	for ( const ref of [ 'page', 'safe' ] ) {
		const origin = ref === 'safe' ? page.safe : 0;
		const total = ref === 'safe' ? page.w - page.safe * 2 : page.w;
		for ( const mode of [ 'start', 'center', 'end' ] ) {
			for ( const side of [ 'start', 'center', 'end' ] ) {
				const axis = plain( alignAxis( side, w, { mode, start: '5mm', end: '5mm', size: `${ w }pt` }, origin, total ) );
				assert.equal( axis.mode, mode, 'the anchor stays' );
				const out = sandbox.draw.resolve( { type: 'rect', x: 0, y: 0, w: 1, h: 1, layout: { ref, h: axis, v: { mode: 'start', start: '0pt', size: '10pt' } } }, page );
				const want = side === 'start' ? origin : side === 'center' ? origin + ( total - w ) / 2 : origin + total - w;
				assert.ok( Math.abs( out.x - want ) < 0.02, `${ ref } ${ mode } ${ side }: ${ out.x } vs ${ want }` );
			}
		}
	}
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

test( 'a corner radius is points or a percentage of the shorter side, converted without moving the corner', () => {
	assert.deepEqual( [ radiusUnit( 12 ), radiusUnit( '25%' ), radiusUnit( undefined ) ], [ 'pt', '%', 'pt' ] );
	assert.deepEqual( [ radiusNumber( 12 ), radiusNumber( '25%' ), radiusNumber( undefined ) ], [ 12, 25, 0 ] );
	assert.equal( radiusFrom( 80, '%' ), '50%', 'no more than 50%' );
	assert.equal( radiusFrom( 12, 'pt' ), 12 );
	assert.equal( radiusConvert( 20, '%', 100, 40 ), '50%' );
	assert.equal( radiusConvert( '25%', 'pt', 100, 40 ), 10 );
	assert.equal( radiusConvert( 60, '%', 100, 40 ), '50%', 'a corner larger than half the side is the largest there is' );
} );


test( 'the box of a line moves or stretches its two ends and keeps the way it goes', () => {
	const patch = ( ...a ) => plain( sandbox.size.lineBoxPatch( ...a ) );
	const line = { x1: 100, y1: 50, x2: 300, y2: 50 };
	assert.deepEqual( patch( line, 'x', 120 ), { x1: 120, x2: 320 }, 'X moves both ends' );
	assert.deepEqual( patch( line, 'y', 80 ), { y1: 80, y2: 80 } );
	assert.deepEqual( patch( line, 'w', 150 ), { x2: 250 }, 'the width stretches the right end' );
	assert.deepEqual( patch( { x1: 300, y1: 50, x2: 100, y2: 50 }, 'w', 150 ), { x1: 250 }, 'a line drawn from right to left keeps its left end' );
	assert.deepEqual( patch( { x1: 10, y1: 90, x2: 40, y2: 20 }, 'h', 30 ), { y1: 50 }, 'and one that goes up' );
	assert.deepEqual( patch( line, 'w', -5 ), { x2: 100 }, 'no negative width' );
} );

test( 'a side of an SVG left empty says no size: the layout has none there, and only that side', () => {
	const svg = { type: 'svg', vw: 24, vh: 12, x: 100, y: 50, w: 96, h: 96, layout: { ref: 'page', h: { mode: 'start', start: '100pt', size: '96pt' }, v: { mode: 'start', start: '50pt', size: '96pt' } } };
	const cleared = plain( sandbox.size.clearSizePatch( svg, 'w', page ) );
	assert.equal( cleared.layout.h.size, undefined );
	assert.equal( cleared.layout.h.start, '100pt', 'the place is kept' );
	assert.equal( cleared.layout.v.size, '96pt', 'the other side keeps its size' );
	const empty = { ...svg, layout: cleared.layout };
	assert.equal( sandbox.size.sizeEmpty( empty, 'w' ), true );
	assert.equal( sandbox.size.sizeEmpty( empty, 'h' ), false );
	assert.equal( sandbox.size.sizeEmpty( { ...empty, type: 'rect' }, 'w' ), false, 'only a drawing has a size of its own' );
	// Typing the height makes the layout again with both sizes: the width, which said none, keeps saying none.
	const typed = plain( sandbox.size.keepEmpty( empty, { layout: { ...svg.layout } }, 'h' ) );
	assert.equal( typed.layout.h.size, undefined );
	assert.equal( typed.layout.v.size, '96pt' );
	// With no layout an SVG gets one that says no size on the side.
	const loose = plain( sandbox.size.clearSizePatch( { type: 'svg', vw: 24, vh: 12, x: 10, y: 20, w: 96, h: 96 }, 'h', page ) );
	assert.equal( loose.layout.v.size, undefined );
	assert.ok( loose.layout.h.size, 'the other side says what it is' );
} );


test( 'a font size typed with decimals keeps them, in points with up to three', () => {
	assert.equal( fontSizePt( '12.5pt' ), 12.5 );
	assert.equal( fontSizePt( '9.75pt' ), 9.75 );
	assert.equal( fontSizePt( '12.1234' ), 12.123, 'no unit: the unit given, points by default' );
	assert.equal( fontSizePt( '12,5' ), 12.5, 'a comma is a decimal point' );
	assert.equal( fontSizePt( '4.4mm' ), 12.472 );
	assert.equal( fontSizePt( '0.5in' ), 36 );
	assert.equal( fontSizePt( '9.5', 'px' ), 9.5, 'the unit of the field when the text has none' );
	assert.equal( fontSizePt( '12', 'mm' ), 34.016 );
	assert.equal( fontSizePt( '0.2' ), 1, 'at least 1 pt' );
	assert.equal( fontSizePt( '5000' ), 1000, 'at most 1000 pt (what a file may say)' );
	assert.equal( fontSizePt( '' ), undefined );
	assert.equal( fontSizePt( 'abc' ), undefined );
} );

test( 'a font size is shown in its unit with up to three decimals and no zeros at the end', () => {
	assert.equal( fontSizeText( 12.5, 'pt' ), '12.5pt' );
	assert.equal( fontSizeText( 12, 'pt' ), '12pt' );
	assert.equal( fontSizeText( 9.75, 'pt' ), '9.75pt' );
	assert.equal( fontSizeText( 12.3456, 'pt' ), '12.346pt' );
	assert.equal( fontSizeText( 12.472, 'mm' ), '4.4mm' );
	assert.equal( fontSizeText( 72, 'in' ), '1in' );
	assert.equal( fontSizeText( 12.5, 'px' ), '12.5px' );
} );
