// Fails when the screenshots and the site disagree. Run by `npm run check`:
//   - a page shows a screenshot its entry in src/data/screenshots.ts does not list, or an entry lists a page that does not show it
//     (`catalog` = the scenario landings and the home, through src/data/catalog.ts);
//   - a file is missing;
//   - a picture is STALE: a plugin file it depends on (`sources`) changed after it was taken, or it was never taken
//     (the message says the command that takes it again);
//   - the readme's `== Screenshots ==` list is not the captions of the WordPress.org entries, in order.
// Staleness needs the git history: CI checks out with fetch-depth 0.
import { readFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { resolve, join, dirname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { screenshots } from '../src/data/screenshots.ts';
import { staleReport } from './lib/stale.mjs';

const here = dirname( fileURLToPath( import.meta.url ) );
const docs = resolve( here, '../src/content/docs' );
const walk = ( dir ) => readdirSync( dir ).flatMap( ( name ) => ( statSync( join( dir, name ) ).isDirectory() ? walk( join( dir, name ) ) : [ join( dir, name ) ] ) );

const shown = {};
for ( const file of walk( docs ).filter( ( f ) => f.endsWith( '.md' ) ) ) {
	const id = relative( docs, file ).replace( /\.md$/, '' );
	for ( const match of readFileSync( file, 'utf8' ).matchAll( /screenshots\/([a-z0-9-]+)\.webp/g ) ) {
		( shown[ match[ 1 ] ] ??= new Set() ).add( id );
	}
}
for ( const match of readFileSync( resolve( here, '../src/data/catalog.ts' ), 'utf8' ).matchAll( /screenshot: '([a-z0-9-]+)'/g ) ) {
	( shown[ match[ 1 ] ] ??= new Set() ).add( 'catalog' );
}

const problems = [];
for ( const shot of screenshots ) {
	if ( ! existsSync( resolve( here, `../src/assets/screenshots/${ shot.id }.webp` ) ) ) {
		problems.push( `${ shot.id }: the file is missing (npm run screenshots).` );
	}
	for ( const page of shot.usedIn ) {
		if ( ! shown[ shot.id ]?.has( page ) ) {
			problems.push( `${ shot.id }: listed in usedIn for "${ page }", which does not show it.` );
		}
	}
	for ( const page of shown[ shot.id ] ?? [] ) {
		if ( ! shot.usedIn.includes( page ) ) {
			problems.push( `${ shot.id }: shown by "${ page }", which its entry does not list.` );
		}
	}
}
for ( const id of Object.keys( shown ) ) {
	if ( ! screenshots.some( ( shot ) => shot.id === id ) ) {
		problems.push( `${ id }: shown by a page but not in the manifest.` );
	}
}
for ( const item of staleReport() ) {
	problems.push( `${ item.id }: out of date (${ item.why }). Take it again: npm run screenshots -- ${ item.id }` );
}
// The readme's `== Screenshots ==` list is the captions of the WordPress.org entries, in order.
const readme = readFileSync( resolve( here, '../../readme.txt' ), 'utf8' );
const listed = ( readme.match( /== Screenshots ==\n+([\s\S]*?)(?:\n== |$)/ )?.[ 1 ] ?? '' ).split( '\n' ).filter( Boolean );
const wanted = screenshots.filter( ( shot ) => shot.wporg ).map( ( shot, i ) => `${ i + 1 }. ${ shot.caption }` );
if ( JSON.stringify( listed ) !== JSON.stringify( wanted ) ) {
	problems.push( 'readme.txt: the "== Screenshots ==" list is not the captions of the WordPress.org entries, in order:\n' + wanted.join( '\n' ) );
}
if ( problems.length ) {
	console.error( problems.join( '\n' ) );
	process.exit( 1 );
}
console.log( `screenshots: ${ screenshots.length } in the manifest, all shown where they say and up to date.` );
