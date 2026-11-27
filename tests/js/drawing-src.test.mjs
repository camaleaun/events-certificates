/**
 * The picture of a drawing made of paths (src/quick-generator/preview.ts): the shape of its preferred size when it has one, the shape of
 * its viewBox when it does not. Run: npm run test:js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const root = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
// The module as the browser gets it, with the one WordPress package it imports (translations) stubbed.
const stub = {
	name: 'stub-i18n',
	setup( build ) {
		build.onResolve( { filter: /^@wordpress\/i18n$/ }, () => ( { path: 'i18n', namespace: 'stub' } ) );
		build.onLoad( { filter: /.*/, namespace: 'stub' }, () => ( { contents: 'export const __ = ( s ) => s; export const sprintf = ( s, ...a ) => a.reduce( ( t, v ) => t.replace( /%[0-9$]*[sd]/, v ), s ); export const _n = ( a, b, n ) => ( n === 1 ? a : b );', loader: 'js' } ) );
	},
};
const built = await esbuild.build( { absWorkingDir: root, entryPoints: [ 'src/quick-generator/preview.ts' ], bundle: true, format: 'iife', globalName: 'preview', write: false, plugins: [ stub ] } );
const sandbox = { window: {}, document: { documentElement: { lang: 'en' } }, navigator: {}, Image: class {} };
const { drawingSrc } = vm.runInNewContext( built.outputFiles[ 0 ].text + '; preview', sandbox );
const svgOf = ( json ) => decodeURIComponent( drawingSrc( json ).split( ',' )[ 1 ] );
const d = 'M0 0L5 5Z';

test( 'a drawing with no preferred size has the shape of its viewBox', () => {
	assert.match( svgOf( JSON.stringify( { vw: 24, vh: 24, paths: [ { d } ] } ) ), /width="192" height="192" viewBox="0 0 24 24"/ );
	assert.match( svgOf( JSON.stringify( { vw: 120, vh: 44, paths: [ { d } ] } ) ), /width="960" height="352" viewBox="0 0 120 44"/ );
} );

test( 'a drawing that prefers a size has the shape of it, the drawing centered in it', () => {
	const svg = svgOf( JSON.stringify( { vw: 24, vh: 24, w: 512, h: 256, paths: [ { d } ] } ) );
	assert.match( svg, /width="1200" height="600" viewBox="0 0 24 24"/ );
} );

test( 'what is not a drawing gives no picture', () => {
	assert.equal( drawingSrc( 'x' ), '' );
	assert.equal( drawingSrc( JSON.stringify( { vw: 0, vh: 4, paths: [] } ) ), '' );
} );
