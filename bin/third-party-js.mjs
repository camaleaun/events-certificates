#!/usr/bin/env node
/**
 * Copies the third-party scripts of the generator (jsPDF, JSZip, SheetJS) from node_modules to
 * assets/third-party/, with their licenses. The folder is not in git: it is made here, from the exact
 * versions package.json pins (docs/decisions/0032). `npm run build` runs it.
 *
 * jsPDF's code that loads pdfobject from a CDN is blanked: the plugin loads nothing from a CDN. A library
 * whose version is not the pinned one, or whose line to change is not there, stops the build.
 *
 * Usage: node bin/third-party-js.mjs
 */

import { copyFileSync, existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname( fileURLToPath( import.meta.url ) );

/** The libraries: the file to ship, its license, and what to change in it. */
export const LIBS = [
	{
		name: 'jspdf',
		version: '2.5.1',
		file: 'dist/jspdf.umd.min.js',
		out: 'jspdf.umd.min.js',
		license: 'LICENSE',
		licenseOut: 'jsPDF-LICENSE.txt',
		patches: [ [ '"https://cdnjs.cloudflare.com/ajax/libs/pdfobject/2.1.1/pdfobject.min.js"', '""' ] ],
	},
	{
		name: 'jszip',
		version: '3.10.1',
		file: 'dist/jszip.min.js',
		out: 'jszip.min.js',
		license: 'LICENSE.markdown',
		licenseOut: 'JSZip-LICENSE.txt',
		patches: [],
	},
	{
		name: 'xlsx',
		version: '0.18.5',
		file: 'dist/xlsx.full.min.js',
		out: 'xlsx.full.min.js',
		license: 'LICENSE',
		licenseOut: 'SheetJS-LICENSE.txt',
		patches: [],
	},
];

/**
 * Copies the libraries.
 *
 * @param {Object} options Options.
 * @param {string} options.root Plugin folder (where node_modules is).
 * @param {string} options.out  Folder to fill (emptied first).
 * @param {Array}  options.libs Libraries (LIBS).
 * @return {string[]} The files written.
 */
export function copyThirdParty( { root, out, libs = LIBS } ) {
	rmSync( out, { recursive: true, force: true } );
	mkdirSync( out, { recursive: true } );
	const written = [];
	for ( const lib of libs ) {
		const folder = join( root, 'node_modules', lib.name );
		if ( ! existsSync( folder ) ) {
			throw new Error( `${ lib.name } is not installed: run npm ci.` );
		}
		const installed = JSON.parse( readFileSync( join( folder, 'package.json' ), 'utf8' ) ).version;
		if ( installed !== lib.version ) {
			throw new Error( `${ lib.name } is ${ installed }, the plugin ships ${ lib.version }: change both together (bin/third-party-js.mjs, package.json, includes/quick-generator.php).` );
		}
		let code = readFileSync( join( folder, lib.file ), 'utf8' );
		for ( const [ from, to ] of lib.patches ) {
			if ( code.split( from ).length !== 2 ) {
				throw new Error( `${ lib.name }: the text to change is not there exactly once (${ from.slice( 0, 60 ) }…).` );
			}
			code = code.replace( from, () => to );
		}
		writeFileSync( join( out, lib.out ), code );
		copyFileSync( join( folder, lib.license ), join( out, lib.licenseOut ) );
		written.push( lib.out, lib.licenseOut );
	}

	return written;
}

if ( process.argv[ 1 ] === fileURLToPath( import.meta.url ) ) {
	const root = join( here, '..' );
	const files = copyThirdParty( { root, out: join( root, 'assets/third-party' ) } );
	console.log( `third-party-js: ${ files.length } files in assets/third-party.` );
}

