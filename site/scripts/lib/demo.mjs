// What the capture scripts share: the demo sites (scripts/demo-sites.sh), a logged-in Chrome on one and the ids its seed made.
// Uses the system Chrome, so no browser is downloaded.
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

const here = dirname( fileURLToPath( import.meta.url ) );
export const siteRoot = resolve( here, '../..' );

/** The demo site of a case: its path, address and the seed's ids; null when it was not made (npm run demo-sites). */
export function demoSite( scenario ) {
	const path = join( process.env.EVCERT_DEMO_ROOT ?? join( homedir(), 'Studio' ), `evcert-demo-${ scenario }` );
	const ids = join( siteRoot, '.demo', `${ scenario }.json` );
	if ( ! existsSync( join( path, 'wp-config.php' ) ) || ! existsSync( ids ) ) {
		return null;
	}
	const wp = ( ...args ) => execFileSync( 'studio', [ 'wp', `--path=${ path }`, ...args ], { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } ).trim();
	// A site that is stopped is started, and stopped again when the script ends.
	const status = JSON.parse( execFileSync( 'studio', [ 'site', 'status', '--path', path, '--format', 'json' ], { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } ) );
	if ( ! status.isOnline ) {
		execFileSync( 'studio', [ 'site', 'start', '--path', path, '--skip-browser' ], { stdio: 'ignore' } );
		process.on( 'exit', () => execFileSync( 'studio', [ 'site', 'stop', '--path', path ], { stdio: 'ignore' } ) );
	}
	return { path, wp, url: wp( 'option', 'get', 'siteurl' ).replace( /\/$/, '' ), ids: JSON.parse( readFileSync( ids, 'utf8' ) ) };
}

/** A logged-in browser context: the admin bar's user is hidden, a screenshot shows the product and not who took it. */
export async function openBrowser( site, viewport, locale = 'en-US' ) {
	const browser = await chromium.launch( { channel: 'chrome' } );
	const context = await browser.newContext( { viewport: { width: viewport.width, height: viewport.height }, deviceScaleFactor: viewport.scale, locale } );
	await context.addInitScript( () => {
		addEventListener( 'DOMContentLoaded', () => {
			const style = document.createElement( 'style' );
			style.textContent = '#wp-admin-bar-my-account{display:none!important}';
			document.head.append( style );
		} );
	} );
	const page = await context.newPage();
	await page.goto( `${ site.url }/studio-auto-login?redirect_to=${ encodeURIComponent( `${ site.url }/wp-admin/` ) }` );
	return { browser, page };
}

/** The address of a manifest path, with the seed's ids. */
export const addressOf = ( site, path ) => site.url + path.replace( /\{(\w+)\}/g, ( match, key ) => String( site.ids[ key ] ?? match ) );
