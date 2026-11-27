// Takes the screenshots of site/src/data/screenshots.ts on a throwaway Studio site (never the dev site: its names are real)
// seeded by ../bin/screenshots-seed.php. Needs Studio's CLI and Chrome. Usage, from site/:
//   npm run screenshots -- http://localhost:8930 <event-id> <design-id>
// The site is made once with `studio site create --path ~/Studio/evcert-shots`, the plugin zip installed and the seed run.
import { chromium } from 'playwright-core';
import sharp from 'sharp';
import { mkdirSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { screenshots, viewport } from '../src/data/screenshots.ts';

const [ base, event = '0', design = '0' ] = process.argv.slice( 2 );
if ( ! base ) {
	console.error( 'Give the address of the throwaway site: npm run screenshots -- http://localhost:8930 <event-id> <design-id>' );
	process.exit( 1 );
}
const here = dirname( fileURLToPath( import.meta.url ) );
const out = resolve( here, '../src/assets/screenshots' );
mkdirSync( out, { recursive: true } );

const browser = await chromium.launch( { channel: 'chrome' } );
const context = await browser.newContext( { viewport: { width: viewport.width, height: viewport.height }, deviceScaleFactor: viewport.scale, locale: 'en-US' } );
// The admin bar's user name and avatar are hidden: a screenshot shows the product, not who took it.
await context.addInitScript( () => {
	addEventListener( 'DOMContentLoaded', () => {
		const style = document.createElement( 'style' );
		style.textContent = '#wp-admin-bar-my-account{display:none!important}';
		document.head.append( style );
	} );
} );
const page = await context.newPage();
await page.goto( `${ base }/studio-auto-login?redirect_to=${ encodeURIComponent( `${ base }/wp-admin/` ) }` );

for ( const shot of screenshots ) {
	const url = base + shot.path.replace( '{event}', event ).replace( '{design}', design );
	await page.goto( url, { waitUntil: 'networkidle' } );
	await page.waitForTimeout( 1500 );
	const png = await page.screenshot();
	await sharp( png ).webp( { quality: 92 } ).toFile( resolve( out, `${ shot.id }.webp` ) );
	// The directory's own, numbered as the readme's `== Screenshots ==` lists them: PNG, 1440 wide.
	await sharp( png ).resize( viewport.width ).png().toFile( resolve( here, `../../.wordpress-org/screenshot-${ screenshots.indexOf( shot ) + 1 }.png` ) );
	console.log( `${ shot.id }: ${ url }` );
}
await browser.close();
