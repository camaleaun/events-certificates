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
