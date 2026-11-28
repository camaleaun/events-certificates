/**
 * Downloads each language's translation (translations.json) and lays it out for the build, in git-ignored folders:
 * translated text never lives in this repository.
 *
 *   .translations/<code>/        the language's repository (shallow clone)
 *   src/content/i18n/<code>/     strings.json (and docs/ when it has them)
 *   src/assets/i18n/<code>/      translated screenshots
 *
 * A language that cannot be downloaded is left out: the site builds in the languages it has.
 *
 *   npm run translations                                 download and lay out
 *   npm run translations -- --local br=../../events-certificates-ptb   use a local checkout
 *   npm run translations -- --ref site                   a branch or tag instead of each translations.json ref
 */
import { execFileSync } from 'node:child_process';
import { cpSync, existsSync, mkdirSync, readFileSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const site = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );
const config = JSON.parse( readFileSync( join( site, 'translations.json' ), 'utf8' ) );
const args = process.argv.slice( 2 );
const refIndex = args.indexOf( '--ref' );
const ref = refIndex >= 0 ? args[ refIndex + 1 ] : '';
const local = Object.fromEntries( args.flatMap( ( arg, i ) => ( arg === '--local' ? [ args[ i + 1 ].split( '=' ) ] : [] ) ).map( ( [ code, path ] ) => [ code, resolve( path ) ] ) );

for ( const locale of config.locales ) {
	const clone = join( site, '.translations', locale.code );
	const content = join( site, 'src/content/i18n', locale.code );
	rmSync( content, { recursive: true, force: true } );
	rmSync( join( site, 'src/assets/i18n', locale.code ), { recursive: true, force: true } );
	try {
		let from = local[ locale.code ];
		if ( ! from ) {
			rmSync( clone, { recursive: true, force: true } );
			execFileSync( 'git', [ 'clone', '--depth', '1', '--branch', ref || locale.ref, `https://github.com/${ locale.repo }.git`, clone ], { stdio: [ 'ignore', 'ignore', 'pipe' ], env: { ...process.env, GIT_TERMINAL_PROMPT: '0' } } );
			from = clone;
		}
		mkdirSync( content, { recursive: true } );
		cpSync( join( from, 'strings.json' ), join( content, 'strings.json' ) );
		if ( existsSync( join( from, 'docs' ) ) ) {
			cpSync( join( from, 'docs' ), join( content, 'docs' ), { recursive: true } );
		}
		if ( existsSync( join( from, 'assets/screenshots' ) ) ) {
			cpSync( join( from, 'assets/screenshots' ), join( site, 'src/assets/i18n', locale.code ), { recursive: true } );
		}
		console.log( `${ locale.code }: ${ locale.repo } (${ local[ locale.code ] ? 'local' : ref || locale.ref })` );
	} catch ( error ) {
		rmSync( content, { recursive: true, force: true } );
		console.warn( `${ locale.code }: not downloaded, the site builds without it (${ String( error.stderr ?? error.message ).trim().split( '\n' ).pop() })` );
	}
}
