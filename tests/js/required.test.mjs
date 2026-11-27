/**
 * Nothing in a design is required: not even the name (docs/decisions/0004). Run: npm run test:js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const root = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const built = await esbuild.build( { absWorkingDir: root, entryPoints: [ 'src/editor/model.ts' ], bundle: true, format: 'esm', write: false } );
const { REQUIRED_SLOTS, isRequired } = await import( 'data:text/javascript;base64,' + Buffer.from( built.outputFiles[ 0 ].text ).toString( 'base64' ) );

test( 'no slot is required, the name included', () => {
	assert.deepEqual( REQUIRED_SLOTS, [] );
	const name = { id: 'n', type: 'text', slot: 'name' };
	assert.equal( isRequired( name, [ name ] ), false, 'the only name can be deleted' );
} );
