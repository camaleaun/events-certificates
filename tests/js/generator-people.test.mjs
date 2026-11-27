/**
 * The Generator screen's list of people (src/generator/people.ts). Run: npm run test:js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const root = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const built = await esbuild.build( { absWorkingDir: root, entryPoints: [ 'src/generator/people.ts' ], bundle: true, format: 'esm', write: false } );
const { mergePeople, setAll, countSelected, selectedPeople, whatIsMissing } = await import( 'data:text/javascript;base64,' + Buffer.from( built.outputFiles[ 0 ].text ).toString( 'base64' ) );

test( 'merging numbers the new people and starts who did not attend unchecked', () => {
	const merged = mergePeople( [], [ { name: 'Emily Johnson' }, { name: 'John Smith', status: 'no_show' } ], 10 );
	assert.deepEqual( merged.people.map( ( p ) => [ p.id, p.name, p.on ] ), [ [ 10, 'Emily Johnson', true ], [ 11, 'John Smith', false ] ] );
	assert.equal( merged.added, 2 );
	assert.equal( merged.nextId, 12 );
} );

test( 'the same name, in any case, is one person and the first one stays', () => {
	const first = mergePeople( [], [ { name: 'Emily Johnson', email: 'emily@example.com' } ], 1 );
	const again = mergePeople( first.people, [ { name: '  emily JOHNSON ' }, { name: 'John Smith' }, { name: '' } ], first.nextId );
	assert.equal( again.people.length, 2 );
	assert.equal( again.added, 1 );
	assert.equal( again.people[ 0 ].email, 'emily@example.com' );
} );

test( 'selecting and counting', () => {
	const { people } = mergePeople( [], [ { name: 'A' }, { name: 'B' }, { name: 'C', status: 'no_show' } ], 1 );
	assert.equal( countSelected( people ), 2 );
	assert.equal( countSelected( setAll( people, true ) ), 3 );
	assert.equal( countSelected( setAll( people, false ) ), 0 );
	assert.deepEqual( selectedPeople( people ).map( ( p ) => p.name ), [ 'A', 'B' ] );
} );

test( 'what is missing before a download', () => {
	assert.deepEqual( whatIsMissing( { title: '', date: '' }, 0, false, false ), [ 'title', 'date', 'people' ] );
	assert.deepEqual( whatIsMissing( { title: 'A title', date: '2026-11-25' }, 2, true, false ), [] );
	assert.deepEqual( whatIsMissing( { title: 'A title', date: '' }, 2, true, false ), [ 'date' ] );
	assert.deepEqual( whatIsMissing( { title: 'A title', date: '2026-11-25' }, 0, false, true ), [ 'current' ] );
	assert.deepEqual( whatIsMissing( { title: ' ', date: '2026-11-25' }, 1, true, true ), [ 'title' ] );
} );
