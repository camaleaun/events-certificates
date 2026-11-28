// Takes the screenshots of src/data/screenshots.ts on the demo sites (npm run demo-sites): never the development site.
//
//   npm run screenshots                     every picture that is stale or missing (what `npm run check` asks for)
//   npm run screenshots -- --all            every picture
//   npm run screenshots -- generator editor those ids
//   npm run screenshots -- --scenario=standalone
//
// "Stale" is a picture whose `sources` changed after the commit recorded in screenshots-captured.json when it was taken.
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import sharp from 'sharp';
import { screenshots, viewport } from '../src/data/screenshots.ts';
import { addressOf, demoSite, openBrowser, siteRoot } from './lib/demo.mjs';
import { scenes } from './lib/scenes.mjs';
import { staleIds } from './lib/stale.mjs';

const args = process.argv.slice( 2 );
const ids = args.filter( ( arg ) => ! arg.startsWith( '--' ) );
const scenario = args.find( ( arg ) => arg.startsWith( '--scenario=' ) )?.slice( 11 );
const picked = screenshots.filter( ( shot ) => ( ! scenario || shot.scenario === scenario ) && ( ids.length ? ids.includes( shot.id ) : args.includes( '--all' ) || staleIds().includes( shot.id ) ) );
if ( ! picked.length ) {
	console.log( 'Nothing to take: every screenshot is up to date.' );
	process.exit( 0 );
}

const out = resolve( siteRoot, 'src/assets/screenshots' );
mkdirSync( out, { recursive: true } );
const captured = JSON.parse( readFileSync( resolve( siteRoot, 'screenshots-captured.json' ), 'utf8' ).trim() || '{}' );
const head = execFileSync( 'git', [ 'rev-parse', 'HEAD' ], { encoding: 'utf8' } ).trim();
const sites = {};
const session = {};

for ( const shot of picked ) {
	sites[ shot.scenario ] ??= demoSite( shot.scenario );
	const site = sites[ shot.scenario ];
	if ( ! site ) {
		console.error( `${ shot.id }: the demo site of "${ shot.scenario }" is not made (npm run demo-sites -- ${ shot.scenario }).` );
		process.exitCode = 1;
		continue;
	}
	session[ shot.scenario ] ??= await openBrowser( site, viewport );
	const { page } = session[ shot.scenario ];
	await page.goto( addressOf( site, shot.path ), { waitUntil: 'networkidle' } );
	// A public page shows no admin bar (a screen of the admin keeps it: it is part of what the person sees).
	// WordPress's and other plugins' admin notices (GatherPress's welcome, its Alpha reminder) are not what the picture is about.
	if ( shot.path.startsWith( '/wp-admin' ) ) {
		await page.addStyleTag( { content: '#wpbody-content .notice, #wpbody-content .gatherpress-welcome, #wpbody-content [class*="welcome-panel"]{display:none!important}' } ).catch( () => {} );
	}
	if ( ! shot.path.startsWith( '/wp-admin' ) ) {
		await page.addStyleTag( { content: '#wpadminbar{display:none!important}html{margin-top:0!important}' } ).catch( () => {} );
	}
	await page.waitForTimeout( 1500 );
	if ( shot.scene ) {
		await scenes[ shot.scene ]( page );
	}
	const png = await page.screenshot();
	await sharp( png ).webp( { quality: 92 } ).toFile( resolve( out, `${ shot.id }.webp` ) );
	const wporg = screenshots.filter( ( item ) => item.wporg );
	if ( shot.wporg ) {
		// The directory's own, numbered as the readme's `== Screenshots ==` lists them: PNG, 1440 wide.
		await sharp( png ).resize( viewport.width ).png().toFile( resolve( siteRoot, `../.wordpress-org/screenshot-${ wporg.indexOf( shot ) + 1 }.png` ) );
	}
	captured[ `${ shot.id }@en` ] = { commit: head, at: new Date().toISOString().slice( 0, 10 ) };
	console.log( `${ shot.id }: ${ addressOf( site, shot.path ) }` );
}
for ( const { browser } of Object.values( session ) ) {
	await browser.close();
}
writeFileSync( resolve( siteRoot, 'screenshots-captured.json' ), JSON.stringify( captured, null, '\t' ) + '\n' );
