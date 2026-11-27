/**
 * The verification element's layout (src/editor/verification.ts), with the numbers of
 * tests/php/VerificationElementTest.php: the editor draws what the PDF draws. Run: npm run test:js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const root = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const built = await esbuild.build( { absWorkingDir: root, entryPoints: [ 'src/editor/verification.ts' ], bundle: true, format: 'esm', write: false } );
const { verificationParts } = await import( 'data:text/javascript;base64,' + Buffer.from( built.outputFiles[ 0 ].text ).toString( 'base64' ) );

const element = ( extra = {} ) => ( { id: 'verification', type: 'verification', x: 100, y: 200, w: 200, h: 80, qr: 40, ...extra } );
const near = ( actual, expected ) => assert.ok( Math.abs( actual - expected ) < 0.01, `${ actual } is not ${ expected }` );

test( 'a stack is centered by default: the code under the QR code and the hint under the code', () => {
	const parts = verificationParts( element() );
	assert.deepEqual( parts.map( ( p ) => p.kind ), [ 'qr', 'code', 'hint' ] );
	near( parts[ 0 ].x, 180 );
	assert.equal( parts[ 1 ].align, 'center' );
	assert.ok( parts[ 2 ].y > parts[ 1 ].y );
	assert.ok( parts[ 1 ].y >= parts[ 0 ].y + 40 );
} );

test( 'a stack follows the alignment', () => {
	near( verificationParts( element( { align: 'left' } ) )[ 0 ].x, 100 );
	const right = verificationParts( element( { align: 'right' } ) );
	near( right[ 0 ].x, 260 );
	assert.equal( right[ 2 ].align, 'right' );
} );

test( 'a row puts the text beside the QR code', () => {
	const right = verificationParts( element( { arrange: 'row', align: 'right' } ) );
	near( right[ 0 ].x, 260 );
	assert.ok( right[ 1 ].x + right[ 1 ].w < right[ 0 ].x, 'the text ends before the QR code' );
	assert.equal( right[ 1 ].align, 'right' );

	const left = verificationParts( element( { arrange: 'row', align: 'left' } ) );
	near( left[ 0 ].x, 100 );
	assert.ok( left[ 1 ].x > left[ 0 ].x + 40, 'the text starts after the QR code' );
} );
