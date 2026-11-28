/**
 * What SVG code may be read (src/editor/model.ts), with the limits of includes/signer.php: tests/php/SignerTest.php holds the
 * same cases for the server. Run: npm run test:js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const root = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const built = await esbuild.build( { absWorkingDir: root, entryPoints: [ 'src/editor/model.ts' ], bundle: true, format: 'esm', write: false } );
const { svgCodeAllowed, isPathData, isFill, SVG_LIMITS, PICTURE_PATH_DATA } = await import( 'data:text/javascript;base64,' + Buffer.from( built.outputFiles[ 0 ].text ).toString( 'base64' ) );

const path1 = '<path d="M0 0L10 10Z" fill="#112233"/>';

test( 'a plain drawing of paths may be read', () => {
	assert.ok( svgCodeAllowed( `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">${ path1 }</svg>` ) );
} );

test( 'DTDs, entities, scripts, style sheets and event handlers are refused before anything is parsed', () => {
	for ( const code of [
		`<!DOCTYPE svg [<!ENTITY a "b">]><svg>${ path1 }</svg>`,
		`<svg><script>alert(1)</script>${ path1 }</svg>`,
		`<svg onload="alert(1)">${ path1 }</svg>`,
		`<svg>${ path1.replace( '<path', '<path onclick="x()"' ) }</svg>`,
		`<?xml-stylesheet href="x.css"?><svg>${ path1 }</svg>`,
	] ) {
		assert.equal( svgCodeAllowed( code ), false, code );
	}
} );

test( 'a file over the limit is refused', () => {
	assert.equal( svgCodeAllowed( `<svg>${ path1 }${ ' '.repeat( SVG_LIMITS.bytes ) }</svg>` ), false );
	// A signature or a logo is kept by includes/signer.php (20000 characters a path); an SVG element of a template by camaleaun/draw's Clean::SVG_PATH_DATA (100000).
	assert.deepEqual( { ...SVG_LIMITS, pathData: PICTURE_PATH_DATA }, { bytes: 524288, paths: 50, pathData: 20000, viewBox: 5000 }, 'the numbers of includes/signer.php' );
	assert.equal( SVG_LIMITS.pathData, 100000, 'what the server keeps of an SVG element of a template' );
} );

test( 'path data is commands and numbers only, and a fill is a color', () => {
	assert.ok( isPathData( 'M0 0L10 10Z' ) );
	assert.equal( isPathData( 'M0 0 <script>' ), false );
	assert.equal( isPathData( 'javascript:alert(1)' ), false );
	assert.ok( isFill( '#112233' ) );
	assert.equal( isFill( 'url(javascript:alert(1))' ), false );
	assert.equal( isFill( 'url(#a)' ), false );
} );
