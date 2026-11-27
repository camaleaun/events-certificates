/**
 * Makes the pictures the tutorial's drawings show (dev only, not shipped).
 *
 *   node bin/tutorial-images.mjs --from <folder with event/ and member/ photos> [--only event|member|hero|ui]
 *
 * Each picture is cut to the shape it has in the drawing and resized to the largest size it is shown
 * at (the enlarged view, at twice the pixel density), then saved as WebP in
 * assets/images/tutorial-step-image/. The sizes are in the SPECS below; add a row when a step needs a picture.
 */
import { mkdir, readFile, stat } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const out = join( root, 'assets/images/tutorial-step-image' );
const args = process.argv.slice( 2 );
const from = args.includes( '--from' ) ? args[ args.indexOf( '--from' ) + 1 ] : '';
const only = args.includes( '--only' ) ? args[ args.indexOf( '--only' ) + 1 ] : '';
if ( ! from ) {
	console.error( 'Usage: node bin/tutorial-images.mjs --from <folder> [--only event|member]' );
	process.exit( 1 );
}

// Event photos keep Meetup's own 16:9 (a card crops them from the centre, a meetup's page shows them whole); member photos are round, drawn 24 to 32 units wide.
const SPECS = {
	event: { prefix: 'highres_', width: 920, height: 517, quality: 78, ids: [ 536309234, 536487429, 535504383, 535827939, 446074100, 535432316, 533321270, 506468373 ] },
	member: { prefix: 'thumb_', width: 96, height: 96, quality: 82, ids: [ 323853340, 286807252, 323732251, 326195550, 321854570, 326222861, 264395791, 320110966, 314209766, 322336120 ] },
	// The cover of a meetup on its own page, drawn 16:9 about 108 units wide.
	hero: { folder: 'event', prefix: 'event_', width: 360, height: 203, quality: 80, ids: [ 446074100, 487615981 ] },
	// Meetup's small pictures, as they come: [file, width].
	ui: { folder: '../images', prefix: '', suffix: '.webp', files: [ [ 'calendar', 112 ], [ 'pin', 112 ], [ 'ticket', 112 ], [ 'super-organizer', 54 ] ] },
};

let total = 0;
for ( const [ kind, spec ] of Object.entries( SPECS ) ) {
	if ( only && only !== kind ) {
		continue;
	}
	await mkdir( join( out, kind ), { recursive: true } );
	const jobs = spec.files
		? spec.files.map( ( [ name, width ] ) => ( { source: join( from, spec.folder, `${ name }${ spec.suffix }` ), target: join( out, kind, `${ name }.webp` ), resize: [ width, null ], quality: 85 } ) )
		: spec.ids.map( ( id ) => ( { source: join( from, spec.folder || kind, `${ spec.prefix }${ id }.avif` ), target: join( out, kind, `${ id }.webp` ), resize: [ spec.width, spec.height ], quality: spec.quality } ) );
	for ( const job of jobs ) {
		await sharp( await readFile( job.source ) )
			.resize( job.resize[ 0 ], job.resize[ 1 ], { fit: 'cover', position: kind === 'event' ? 'centre' : 'attention' } )
			.webp( { quality: job.quality, effort: 6 } )
			.toFile( job.target );
		const size = ( await stat( job.target ) ).size;
		total += size;
		console.log( `${ kind }/${ job.target.split( '/' ).pop() }  ${ ( size / 1024 ).toFixed( 1 ) } KB` );
	}
}
console.log( `total ${ ( total / 1024 ).toFixed( 1 ) } KB` );
