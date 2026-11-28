import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const root = new URL( '../../', import.meta.url );
const listed = JSON.parse( readFileSync( new URL( 'templates/fields.json', root ), 'utf8' ) );

test( 'the fields of the editor are the keys of templates/fields.json (the server reads the same list)', () => {
	const source = readFileSync( new URL( 'src/editor/FieldText.tsx', root ), 'utf8' );
	const body = source.slice( source.indexOf( 'return {\n\t\tname:' ), source.indexOf( 'const MARKS' ) );
	const keys = [ ...body.matchAll( /^\t\t([a-z_]+): \{/gm ) ].map( ( m ) => m[ 1 ] );
	assert.deepEqual( [ ...keys ].sort(), [ ...listed ].sort() );
} );

import * as esbuild from 'esbuild';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const built = await esbuild.build( { absWorkingDir: dir, entryPoints: [ 'src/editor/rich.ts' ], bundle: true, format: 'esm', write: false, external: [ '@wordpress/*' ] } );
const rich = await import( 'data:text/javascript;base64,' + Buffer.from( built.outputFiles[ 0 ].text ).toString( 'base64' ) );

test( 'a copy says the runs as text and a paste reads them back, fields as fields', () => {
	const runs = [ { text: 'held in ' }, { field: 'location', b: true }, { text: ', ' }, { field: 'issued_date', format: 'long' } ];
	const text = rich.textFromRuns( runs );
	assert.equal( text, 'held in **[[location]]**, [[issued_date:long]]' );
	assert.deepEqual( rich.runsFromText( text, listed ), runs );
	assert.deepEqual( rich.runsFromText( 'a [name] [[nope]] b\r\nc', listed ), [ { text: 'a [name] [[nope]] b\nc' } ], 'A word in brackets that is not a field is text; a line break is one.' );
} );

test( 'a [field] in pasted rich text is a field and keeps the marks of its text', () => {
	const out = rich.fieldsInRuns( [ { text: 'Dear [[name]], [x] [[y]] *', b: true }, { field: 'event' } ], listed );
	assert.deepEqual( out, [ { text: 'Dear ', b: true }, { field: 'name', b: true }, { text: ', [x] [[y]] *', b: true }, { field: 'event' } ] );
} );
