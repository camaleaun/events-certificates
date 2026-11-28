import type { APIRoute } from 'astro';
import { groups } from '@libs/docs';
import { site } from '@libs/site';
import { scenarios, scenarioIds } from '@data/scenarios';

// llmstxt.org: what the product is, then every page linked to its Markdown version.
export const GET: APIRoute = async ( { site: origin } ) => {
	const base = import.meta.env.BASE_URL.replace( /\/$/, '' );
	const abs = ( path: string ) => new URL( `${ base }/${ path }`, origin ).href;
	const lines = [ `# ${ site.name }`, '', `> ${ site.tagline } A WordPress plugin: GPL-2.0-or-later, WordPress ${ site.requires }+, PHP ${ site.php }+.`, '', `- [Source code](${ site.repo })`, `- [Home as Markdown](${ abs( 'index.md' ) })`, `- [What works where](${ abs( 'docs/what-works-where.md' ) }): every feature in each case`, `- [FAQ](${ abs( 'docs/faq.md' ) })`, `- [Changelog](${ abs( 'docs/changelog.md' ) })`, '', '## Cases', '', ...scenarioIds.map( ( id ) => `- [${ scenarios[ id ].name }](${ abs( `${ id }/index.md` ) }): ${ scenarios[ id ].audience }` ), '' ];
	for ( const group of await groups() ) {
		lines.push( `## ${ group.title }`, '', ...group.docs.map( ( doc ) => `- [${ doc.data.title }](${ abs( `docs/${ doc.id }.md` ) }): ${ doc.data.description }` ), '' );
	}

	return new Response( lines.join( '\n' ), { headers: { 'Content-Type': 'text/plain; charset=utf-8' } } );
};
