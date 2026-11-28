// Which screenshots are stale: never taken, or a plugin file they depend on (`sources`) changed after the commit recorded when
// they were taken (screenshots-captured.json). Needs the git history (CI: fetch-depth 0).
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { screenshots } from '../../src/data/screenshots.ts';
import { siteRoot } from './demo.mjs';

const plugin = resolve( siteRoot, '..' );

/** The files of the plugin that changed since a commit, restricted to some paths (committed changes and uncommitted ones). */
const changed = ( since, paths ) => {
	try {
		const run = ( ...gitArgs ) => execFileSync( 'git', [ '-C', plugin, ...gitArgs ], { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } ).split( '\n' ).filter( Boolean );
		return [ ...run( 'diff', '--name-only', since, 'HEAD', '--', ...paths ), ...run( 'diff', '--name-only', 'HEAD', '--', ...paths ) ];
	} catch {
		// The commit is not in this clone: it cannot be said, so it counts as changed.
		return [ '?' ];
	}
};

export function staleReport() {
	const file = resolve( siteRoot, 'screenshots-captured.json' );
	const captured = existsSync( file ) ? JSON.parse( readFileSync( file, 'utf8' ).trim() || '{}' ) : {};
	const report = [];
	for ( const shot of screenshots ) {
		for ( const locale of shot.locales.filter( ( code ) => code === 'en' ) ) {
			const record = captured[ `${ shot.id }@${ locale }` ];
			if ( ! record ) {
				report.push( { id: shot.id, why: 'never taken' } );
				continue;
			}
			const files = changed( record.commit, shot.sources );
			if ( files.length ) {
				report.push( { id: shot.id, why: `${ files.slice( 0, 3 ).join( ', ' ) } changed after ${ record.commit.slice( 0, 7 ) }` } );
			}
		}
	}
	return report;
}

export const staleIds = () => staleReport().map( ( item ) => item.id );
