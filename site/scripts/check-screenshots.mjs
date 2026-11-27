// Fails when a page shows a screenshot its entry in src/data/screenshots.ts does not list, when an entry lists a page that does
// not show it, or when a file is missing. Run by `npm run check`.
import { readFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { resolve, join, dirname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { screenshots } from '../src/data/screenshots.ts';

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
// The readme's `== Screenshots ==` list is the manifest's captions, in order.
const readme = readFileSync( resolve( here, '../../readme.txt' ), 'utf8' );
const listed = ( readme.match( /== Screenshots ==\n+([\s\S]*?)(?:\n== |$)/ )?.[ 1 ] ?? '' ).split( '\n' ).filter( Boolean );
const wanted = screenshots.map( ( shot, i ) => `${ i + 1 }. ${ shot.caption }` );
if ( JSON.stringify( listed ) !== JSON.stringify( wanted ) ) {
	problems.push( 'readme.txt: the "== Screenshots ==" list is not the manifest\'s captions, in order:\n' + wanted.join( '\n' ) );
}
if ( problems.length ) {
	console.error( problems.join( '\n' ) );
	process.exit( 1 );
}
console.log( `screenshots: ${ screenshots.length } in the manifest, all shown where they say.` );
