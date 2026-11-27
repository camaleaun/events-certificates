/**
 * Compares the same region of two screenshots pixel by pixel (dev only, not shipped).
 *
 *   node bin/tutorial-diff.mjs --ref <png>@<x>,<y> --ours <png>@<x>,<y> --size <w>,<h> [--scale 2] [--out <folder>] [--name card] [--max 0]
 *
 * x, y, w and h are in CSS pixels; --scale is the screenshots' pixel density (2 on a Retina screen).
 * --masks <json file> lists rectangles [x, y, w, h] (CSS pixels of the region) that are left out of the
 * comparison: pictures, which two encodings of the same photo never make identical.
 * Writes <name>-ref.png, <name>-ours.png, <name>-diff.png and <name>-side.png (reference, ours and the
 * difference side by side) to --out, prints the share of different pixels and exits with 1 when it is
 * above --max percent (default 0: pixelmatch with threshold 0, anti-aliasing counted).
 */
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import pixelmatch from 'pixelmatch';
import sharp from 'sharp';
import { PNG } from 'pngjs';

const arg = ( name, fallback = '' ) => {
	const at = process.argv.indexOf( `--${ name }` );
	return at > -1 ? process.argv[ at + 1 ] : fallback;
};
const place = ( value ) => {
	const [ file, xy ] = value.split( '@' );
	const [ x, y ] = xy.split( ',' ).map( Number );
	return { file, x, y };
};

const ref = place( arg( 'ref' ) );
const ours = place( arg( 'ours' ) );
const [ w, h ] = arg( 'size' ).split( ',' ).map( Number );
const scale = Number( arg( 'scale', '2' ) );
const out = arg( 'out', '.' );
const name = arg( 'name', 'diff' );
const max = Number( arg( 'max', '0' ) );
const offset = arg( 'mask-offset', '0,0' ).split( ',' ).map( Number );
const masks = arg( 'masks' ) ? JSON.parse( await readFile( arg( 'masks' ), 'utf8' ) ) : [];

const crop = async ( { file, x, y } ) => {
	const { data, info } = await sharp( file )
		.extract( { left: Math.round( x * scale ), top: Math.round( y * scale ), width: Math.round( w * scale ), height: Math.round( h * scale ) } )
		.ensureAlpha()
		.raw()
		.toBuffer( { resolveWithObject: true } );
	return { data, width: info.width, height: info.height };
};

const a = await crop( ref );
const b = await crop( ours );
// A masked rectangle is the same in both: ours is copied over the reference's.
let masked = 0;
for ( const [ mx, my, mw, mh ] of masks ) {
	const x0 = Math.max( 0, Math.floor( ( mx - offset[ 0 ] ) * scale ) );
	const y0 = Math.max( 0, Math.floor( ( my - offset[ 1 ] ) * scale ) );
	const x1 = Math.min( a.width, Math.ceil( ( mx - offset[ 0 ] + mw ) * scale ) );
	const y1 = Math.min( a.height, Math.ceil( ( my - offset[ 1 ] + mh ) * scale ) );
	for ( let y = y0; y < y1; y++ ) {
		for ( let x = x0; x < x1; x++ ) {
			const i = ( y * a.width + x ) * 4;
			for ( let c = 0; c < 4; c++ ) {
				a.data[ i + c ] = b.data[ i + c ];
			}
			masked++;
		}
	}
}
const diff = new PNG( { width: a.width, height: a.height } );
const different = pixelmatch( a.data, b.data, diff.data, a.width, a.height, { threshold: 0, includeAA: true } );
const percent = ( different / ( a.width * a.height ) ) * 100;

await mkdir( out, { recursive: true } );
const png = ( { data, width, height } ) => sharp( data, { raw: { width, height, channels: 4 } } ).png();
await png( a ).toFile( join( out, `${ name }-ref.png` ) );
await png( b ).toFile( join( out, `${ name }-ours.png` ) );
await writeFile( join( out, `${ name }-diff.png` ), PNG.sync.write( diff ) );
const gap = 16;
await sharp( {
	create: { width: a.width * 3 + gap * 2, height: a.height, channels: 4, background: '#cccccc' },
} )
	.composite( [
		{ input: await png( a ).toBuffer(), left: 0, top: 0 },
		{ input: await png( b ).toBuffer(), left: a.width + gap, top: 0 },
		{ input: PNG.sync.write( diff ), left: ( a.width + gap ) * 2, top: 0 },
	] )
	.png()
	.toFile( join( out, `${ name }-side.png` ) );

console.log( `${ name }: ${ different } of ${ a.width * a.height } pixels differ (${ percent.toFixed( 3 ) }%)${ masked ? `, ${ masked } masked` : '' }` );
process.exit( percent > max ? 1 : 0 );
