import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const dir = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const built = await esbuild.build( { absWorkingDir: dir, entryPoints: [ 'src/editor/dates.ts' ], bundle: true, format: 'esm', write: false } );
const { phpDate } = await import( 'data:text/javascript;base64,' + Buffer.from( built.outputFiles[ 0 ].text ).toString( 'base64' ) );

test( 'a date as PHP writes it: the letters of Settings → General, a backslash for a letter that is a word', () => {
	const d = new Date( 2026, 9, 31, 15, 5, 9 ); // 31 October 2026, a Saturday.
	assert.equal( phpDate( 'd/m/Y', d, 'en-US' ), '31/10/2026' );
	assert.equal( phpDate( 'j \\d\\e F \\d\\e Y', d, 'en-US' ), '31 de October de 2026' );
	assert.equal( phpDate( 'jS F Y', d, 'en-US' ), '31st October 2026' );
	assert.equal( phpDate( 'jS', new Date( 2026, 10, 2 ), 'en-US' ), '2nd' );
	assert.equal( phpDate( 'jS', new Date( 2026, 10, 3 ), 'en-US' ), '3rd' );
	assert.equal( phpDate( 'jS', new Date( 2026, 10, 11 ), 'en-US' ), '11th' );
	assert.equal( phpDate( 'jS', new Date( 2026, 10, 22 ), 'en-US' ), '22nd' );
	assert.equal( phpDate( 'N w z t L', d, 'en-US' ), '6 6 303 31 0' );
	assert.equal( phpDate( 'H:i:s', d, 'en-US' ), '15:05:09' );
} );
