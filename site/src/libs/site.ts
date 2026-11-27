import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

// What the site says of the plugin is read from the plugin's own readme.txt, so it is never written twice.
const readme = readFileSync( resolve( process.cwd(), '../readme.txt' ), 'utf8' );
const header = ( key: string ) => readme.match( new RegExp( `^${ key }: *(.+)$`, 'm' ) )?.[ 1 ]?.trim() ?? '';

export const site = {
	name: 'Events Certificates',
	tagline: 'Attendance certificates for your events, made in WordPress.',
	version: header( 'Stable tag' ),
	requires: header( 'Requires at least' ),
	tested: header( 'Tested up to' ),
	php: header( 'Requires PHP' ),
	repo: 'https://github.com/camaleaun/events-certificates',
	playground: 'https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/camaleaun/events-certificates/trunk/.wordpress-org/blueprints/blueprint.json',
};

/** An address under the site's base ('/events-certificates/docs/' for 'docs/'). */
export const href = ( path = '' ) => `${ import.meta.env.BASE_URL.replace( /\/$/, '' ) }/${ path.replace( /^\//, '' ) }`;
