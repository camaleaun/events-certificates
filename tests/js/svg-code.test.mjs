/**
 * SVG as text in the media modal's Edit SVG tab, and what the clipboard toast offers (src/media-chooser/svg-code.ts).
 * Run: npm run test:js
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
	stdin: { contents: "import * as m from './src/media-chooser/svg-code'; window.code = m;", resolveDir: root, loader: 'ts' },
	bundle: true,
	format: 'iife',
	write: false,
	plugins: [
		{
			name: 'stub-i18n',
			setup( build ) {
				build.onResolve( { filter: /^@wordpress\/i18n$/ }, () => ( { path: 'i18n', namespace: 'stub' } ) );
				build.onLoad( { filter: /.*/, namespace: 'stub' }, () => ( { contents: 'export const __ = ( s ) => s; export const sprintf = ( s ) => s;', loader: 'js' } ) );
			},
		},
	],
} );
// There is no DOMParser here: what is checked is what is decided before the parser runs. Parsing real SVG is checked in the browser.
const sandbox = { window: {} };
vm.runInNewContext( built.outputFiles[ 0 ].text, sandbox );
const m = sandbox.window.code;

test( 'the example is a clean SVG of one path', () => {
	assert.match( m.SVG_EXAMPLE, /^<svg[\s>]/ );
	assert.equal( ( m.SVG_EXAMPLE.match( /<path/g ) || [] ).length, 1 );
	assert.ok( ! /<script|on[a-z]+=/i.test( m.SVG_EXAMPLE ) );
	assert.ok( ! /fill/i.test( m.SVG_EXAMPLE ), 'no fill or fill-rule' );
} );

test( 'the XML prolog and the comments are dropped', () => {
	assert.equal( m.bareSvg( '<?xml version="1.0"?>\n<!-- made by x -->\n<svg></svg>\n' ), '<svg></svg>' );
} );

test( 'a text that is not a usable SVG says why, before any parsing', () => {
	assert.deepEqual( JSON.parse( JSON.stringify( m.parseSvgText( '   ' ) ) ), { problem: 'empty' } );
	assert.deepEqual( JSON.parse( JSON.stringify( m.parseSvgText( 'hello' ) ) ), { problem: 'not-svg' } );
	assert.deepEqual( JSON.parse( JSON.stringify( m.parseSvgText( '<svg><script>x()</script></svg>' ) ) ), { problem: 'forbidden' } );
	assert.deepEqual( JSON.parse( JSON.stringify( m.parseSvgText( '<svg onload="x()"></svg>' ) ) ), { problem: 'forbidden' } );
	assert.deepEqual( JSON.parse( JSON.stringify( m.parseSvgText( '<!DOCTYPE svg><svg></svg>' ) ) ), { problem: 'not-svg' }, 'the DOCTYPE is not the start of an <svg>' );
	assert.deepEqual( JSON.parse( JSON.stringify( m.parseSvgText( '<svg>' + 'a'.repeat( 600000 ) + '</svg>' ) ) ), { problem: 'too-big' } );
	for ( const problem of [ 'not-svg', 'forbidden', 'too-big', 'no-paths' ] ) {
		assert.notEqual( m.problemText( problem ), '' );
	}
	assert.equal( m.problemText( 'empty' ), '' );
} );

test( 'the clipboard offers new SVG code once: not what is not SVG, not what was used or dismissed', () => {
	assert.equal( m.offerFor( null, '' ), null, 'the clipboard could not be read' );
	assert.equal( m.offerFor( '', '' ), null );
	assert.equal( m.offerFor( 'just text', '' ), null );
	assert.equal( m.offerFor( '<svg><script>x()</script></svg>', '' ), null );
} );
