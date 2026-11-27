/**
 * The dark colors are offered only with "Light and dark" (src/editor/darkMode.ts). Run: npm run test:js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const root = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const built = await esbuild.build( {
	absWorkingDir: root,
	stdin: { contents: "import { darkEnabled } from './src/editor/darkMode'; window.darkEnabled = darkEnabled;", resolveDir: root, loader: 'ts' },
	bundle: true,
	format: 'iife',
	write: false,
} );
const enabled = ( editor ) => {
	const sandbox = { window: editor === undefined ? {} : { evcertEditor: editor } };
	vm.runInNewContext( built.outputFiles[ 0 ].text, sandbox );
	return sandbox.window.darkEnabled();
};

test( 'darkEnabled: off when the site chose light only', () => {
	assert.equal( enabled( { darkMode: false } ), false );
} );

test( 'darkEnabled: on with light and dark', () => {
	assert.equal( enabled( { darkMode: true } ), true );
} );

test( 'darkEnabled: without the setting (tests, an old page) both are offered', () => {
	assert.equal( enabled( undefined ), true );
	assert.equal( enabled( {} ), true );
} );
