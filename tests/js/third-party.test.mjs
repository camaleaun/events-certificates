import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { LIBS, copyThirdParty } from '../../bin/third-party-js.mjs';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const out = () => join( mkdtempSync( join( tmpdir(), 'evcert-third-party-' ) ), 'assets-third-party' );

test( 'every library is copied with its license', () => {
	const dir = out();
	const files = copyThirdParty( { root, out: dir } );
	assert.equal( files.length, LIBS.length * 2 );
	for ( const lib of LIBS ) {
		assert.ok( existsSync( join( dir, lib.out ) ), lib.out );
		assert.match( readFileSync( join( dir, lib.licenseOut ), 'utf8' ), /\S/, lib.licenseOut );
	}
} );

test( 'jsPDF loads nothing from a CDN, and the others are as published', () => {
	const dir = out();
	copyThirdParty( { root, out: dir } );
	assert.ok( ! readFileSync( join( dir, 'jspdf.umd.min.js' ), 'utf8' ).includes( 'cdnjs.cloudflare.com' ), 'the pdfobject CDN address is blanked' );
	for ( const [ lib, file ] of [ [ 'jszip', 'dist/jszip.min.js' ], [ 'xlsx', 'dist/xlsx.full.min.js' ] ] ) {
		assert.equal( readFileSync( join( dir, `${ lib === 'xlsx' ? 'xlsx.full' : lib }.min.js` ), 'utf8' ), readFileSync( join( root, 'node_modules', lib, file ), 'utf8' ), `${ lib } is untouched` );
	}
} );

test( 'a library that is not the pinned version stops the build', () => {
	const libs = LIBS.map( ( lib ) => ( { ...lib, version: '9.9.9' } ) );
	assert.throws( () => copyThirdParty( { root, out: out(), libs } ), /ships 9\.9\.9/ );
} );

test( 'a change that no longer applies stops the build', () => {
	const libs = LIBS.map( ( lib ) => ( { ...lib, patches: [ [ 'this text is not in the library', '' ] ] } ) );
	assert.throws( () => copyThirdParty( { root, out: out(), libs } ), /exactly once/ );
} );

test( 'the versions agree with the ones the plugin registers and package.json pins', () => {
	const php = readFileSync( join( root, 'includes/quick-generator.php' ), 'utf8' );
	const pkg = JSON.parse( readFileSync( join( root, 'package.json' ), 'utf8' ) );
	const registered = { jspdf: 'evcert-jspdf', jszip: 'evcert-jszip', xlsx: 'evcert-xlsx' };
	for ( const lib of LIBS ) {
		const found = php.match( new RegExp( `wp_register_script\\( '${ registered[ lib.name ] }', [^;]*?, '([0-9.]+)', true \\)` ) );
		assert.ok( found, `${ lib.name } is registered` );
		assert.equal( found[ 1 ], lib.version, `${ lib.name }: version registered in PHP` );
		assert.equal( pkg.devDependencies[ lib.name ], lib.version, `${ lib.name }: pinned in package.json` );
	}
} );
