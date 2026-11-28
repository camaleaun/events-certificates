// Fails when the catalog and the scenarios disagree, or when a term a language must never show reaches its built pages.
// Run by `npm run check` (the built part only when dist/ exists: `npm run build` first).
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { features } from '../src/data/catalog.ts';
import { scenarios, scenarioIds } from '../src/data/scenarios.ts';
import { product } from '../src/product.ts';

const here = dirname( fileURLToPath( import.meta.url ) );
const problems = [];
const keys = [ ...scenarioIds, 'both' ];

for ( const [ id, feature ] of Object.entries( features ) ) {
	for ( const key of keys ) {
		if ( ! feature.where?.[ key ] ) {
			problems.push( `${ id }: no "where" for ${ key }.` );
		}
	}
	if ( ! feature.prominence?.default ) {
		problems.push( `${ id }: no default prominence.` );
	}
}
for ( const id of scenarioIds ) {
	const s = scenarios[ id ];
	for ( const key of [ ...s.order.highlight, ...s.order.secondary ] ) {
		if ( ! features[ key ] ) {
			problems.push( `${ id }: order lists "${ key }", which is not in the catalog.` );
		} else if ( features[ key ].where[ id ] === 'no' ) {
			problems.push( `${ id }: order lists "${ key }", which does not work in this case.` );
		}
	}
	for ( const [ list, map ] of [ [ 'pains', s.pains ], [ 'steps', s.steps ], [ 'limits', s.limits ] ] ) {
		for ( const key of s.order[ list ] ) {
			if ( ! ( key in map ) ) {
				problems.push( `${ id }: order.${ list } lists "${ key }", which is not defined.` );
			}
		}
	}
}

// What a language must never show, looked for in its built pages (English: everything outside the other languages' folders).
const dist = resolve( here, '../dist' );
if ( existsSync( dist ) ) {
	const others = [ 'br' ];
	const walk = ( dir ) => readdirSync( dir ).flatMap( ( name ) => ( statSync( join( dir, name ) ).isDirectory() ? walk( join( dir, name ) ) : [ join( dir, name ) ] ) );
	for ( const file of walk( dist ).filter( ( f ) => /\.(html|md|txt)$/.test( f ) ) ) {
		const rel = file.slice( dist.length + 1 );
		const code = others.find( ( c ) => rel.startsWith( `${ c }/` ) ) ?? 'en';
		const text = readFileSync( file, 'utf8' );
		for ( const term of product.forbidden[ code ] ?? [] ) {
			// Docs pages of the plugin may name the CPF in text only meant for other languages; English must not.
			if ( term.test( text ) ) {
				problems.push( `${ rel }: contains ${ term }, which ${ code } pages must not show.` );
			}
		}
	}
}

if ( problems.length ) {
	console.error( problems.join( '\n' ) );
	process.exit( 1 );
}
console.log( 'prominence: ok' );
