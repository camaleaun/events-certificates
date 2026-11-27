/**
 * The scale of the canvas fits (src/editor/zoom.ts): the page's width, its height, the whole page. Run: npm run test:js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const root = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const built = await esbuild.build( { absWorkingDir: root, entryPoints: [ 'src/editor/zoom.ts' ], bundle: true, write: false, format: 'iife', globalName: 'zoom' } );
const sandbox = {};
vm.runInNewContext( built.outputFiles[ 0 ].text + ';this.zoom = zoom;', sandbox );
const { fitScale, isFit, FIT_MAX, ZOOM_MIN } = sandbox.zoom;

const a4 = { w: 841.89, h: 595.28, bleed: 0 };
const area = { w: 1000, h: 600 };

test( 'a fit is the width, the height, or the smaller of the two', () => {
	assert.equal( fitScale( 'horizontal', a4, area ), 1000 / 841.89 );
	assert.equal( fitScale( 'vertical', a4, area ), 600 / 595.28 );
	assert.equal( fitScale( 'view', a4, area ), Math.min( 1000 / 841.89, 600 / 595.28 ) );
} );

test( 'the padding is left free on every side', () => {
	assert.equal( fitScale( 'horizontal', a4, area, 24 ), 952 / 841.89 );
	assert.equal( fitScale( 'vertical', a4, area, 24 ), 552 / 595.28 );
} );

test( 'the bleed is part of the page that has to fit', () => {
	const bled = { ...a4, bleed: 8.5 };
	assert.equal( fitScale( 'horizontal', bled, area ), 1000 / ( 841.89 + 17 ) );
	assert.equal( fitScale( 'view', bled, area ), Math.min( 1000 / 858.89, 600 / 612.28 ) );
} );

test( 'a portrait page: the height is the narrower fit, and the width fit leaves it taller than the area', () => {
	const portrait = { w: 595.28, h: 841.89 };
	assert.ok( fitScale( 'vertical', portrait, area ) < fitScale( 'horizontal', portrait, area ) );
	assert.equal( fitScale( 'view', portrait, area ), fitScale( 'vertical', portrait, area ) );
	// Fit horizontal: the page's height at that scale is more than the area (it scrolls).
	assert.ok( portrait.h * fitScale( 'horizontal', portrait, area ) > area.h );
} );

test( 'a fit stays between the smallest zoom and the most it enlarges', () => {
	assert.equal( fitScale( 'horizontal', a4, { w: 10000, h: 10000 } ), FIT_MAX );
	assert.equal( fitScale( 'horizontal', a4, { w: 20, h: 20 } ), ZOOM_MIN );
} );

test( 'with no area (the stage is not shown) or no room for the padding there is no scale', () => {
	assert.equal( fitScale( 'view', a4, { w: 0, h: 0 } ), null );
	assert.equal( fitScale( 'horizontal', a4, { w: 40, h: 600 }, 24 ), null );
} );

test( 'only the fits are modes: a number is a level', () => {
	assert.equal( isFit( 'horizontal' ), true );
	assert.equal( isFit( 1.5 ), false );
} );
