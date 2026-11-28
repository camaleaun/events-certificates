import type { APIRoute } from 'astro';
import { hub } from '@data/hub';
import { scenarios, scenarioIds } from '@data/scenarios';
import { matrix, whereText } from '@libs/landing';
import { site } from '@libs/site';

// The home as Markdown, for agents: the same data as the page.
export const GET: APIRoute = () => {
	const abs = ( path: string ) => new URL( path, new URL( import.meta.env.BASE_URL, 'https://example.invalid' ) ).pathname;
	const body = [
		`# ${ site.name }`,
		'',
		`> ${ hub.hero.title }`,
		'',
		hub.hero.lead,
		'',
		'## Which one is you?',
		'',
		...scenarioIds.map( ( id ) => `- [${ scenarios[ id ].name }](${ abs( `${ id }/index.md` ) }): ${ scenarios[ id ].audience } ${ scenarios[ id ].hero.title }` ),
		'',
		'## What works where',
		'',
		'| Feature | ' + scenarioIds.map( ( id ) => scenarios[ id ].name ).join( ' | ' ) + ' |',
		'| --- | ' + scenarioIds.map( () => '---' ).join( ' | ' ) + ' |',
		...matrix( 'en', scenarioIds ).map( ( row ) => `| ${ row.feature.title } | ${ row.cells.map( ( cell ) => whereText( cell.where ) ).join( ' | ' ) } |` ),
		'',
		`Docs: ${ abs( 'docs/start/introduction/' ) }`,
	].join( '\n' );

	return new Response( body, { headers: { 'Content-Type': 'text/markdown; charset=utf-8' } } );
};
