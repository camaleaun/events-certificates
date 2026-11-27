// Draws the brand's files from brand/logo.svg: the favicon, the touch icon, the social image and the WordPress.org icons and
// banners. The wordmark is Inter Bold turned into paths (opentype.js), so nothing depends on a font being installed.
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import opentype from 'opentype.js';
import sharp from 'sharp';

const here = dirname( fileURLToPath( import.meta.url ) );
const site = resolve( here, '..' );
const org = resolve( site, '../.wordpress-org' );
mkdirSync( org, { recursive: true } );

const logo = readFileSync( resolve( site, 'brand/logo.svg' ), 'utf8' );
const inner = logo.replace( /^[\s\S]*?<svg[^>]*>/, '' ).replace( /<\/svg>\s*$/, '' );
const mark = ( x, y, size ) => `<g transform="translate(${ x } ${ y }) scale(${ size / 64 })">${ inner }</g>`;

const font = opentype.parse( readFileSync( resolve( site, 'node_modules/@fontsource/inter/files/inter-latin-700-normal.woff' ) ).buffer.slice( 0 ) );
delete font.tables.gsub; // opentype.js cannot run some of Inter's lookups.
const words = ( text, x, y, size, fill ) => `<path d="${ font.getPath( text, x, y, size ).toPathData( 2 ) }" fill="${ fill }"/>`;

const svg = ( w, h, body, bg ) => Buffer.from( `<svg xmlns="http://www.w3.org/2000/svg" width="${ w }" height="${ h }" viewBox="0 0 ${ w } ${ h }">${ bg ? `<rect width="${ w }" height="${ h }" fill="${ bg }"/>` : '' }${ body }</svg>` );
const png = async ( file, w, h, body, bg ) => sharp( svg( w, h, body, bg ) ).png().toFile( file );

// A banner: the mark, the name, and a line under it, on the admin blue.
const banner = async ( file, w, h ) => {
	const s = h / 250;
	const size = 150 * s;
	const x = 40 * s;
	const name = 'Events Certificates';
	const line = 'Attendance certificates for your events';
	await png( file, w, h, mark( x, ( h - size ) / 2, size ) + words( name, x + size + 28 * s, h / 2 - 2 * s, 44 * s, '#fff' ) + words( line, x + size + 28 * s, h / 2 + 40 * s, 22 * s, '#dbe4ff' ), '#3858e9' );
};

writeFileSync( resolve( site, 'public/favicon.svg' ), logo );
await png( resolve( site, 'public/apple-touch-icon.png' ), 180, 180, mark( 0, 0, 180 ) );
await png( resolve( org, 'icon-128x128.png' ), 128, 128, mark( 0, 0, 128 ) );
await png( resolve( org, 'icon-256x256.png' ), 256, 256, mark( 0, 0, 256 ) );
await banner( resolve( org, 'banner-772x250.png' ), 772, 250 );
await banner( resolve( org, 'banner-1544x500.png' ), 1544, 500 );

// The social image, 1200 x 630.
const title = 'Events Certificates';
const sub = 'Attendance certificates for your events, made in WordPress';
await png(
	resolve( site, 'public/og-image.png' ),
	1200,
	630,
	mark( 90, 195, 240 ) + words( title, 380, 300, 78, '#fff' ) + words( sub.slice( 0, 33 ), 380, 372, 34, '#dbe4ff' ) + words( sub.slice( 33 ), 380, 418, 34, '#dbe4ff' ),
	'#3858e9'
);
console.log( 'brand: favicon, touch icon, social image and the WordPress.org icons and banners written.' );
