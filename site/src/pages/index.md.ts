import type { APIRoute } from 'astro';
import { landing } from '@data/landing';
import { site } from '@libs/site';

// The landing page as Markdown, for agents: the same data as the page.
export const GET: APIRoute = () => {
	const body = [
		`# ${ site.name }`,
		'',
		`> ${ site.tagline }`,
		'',
		landing.lead,
		'',
		...landing.features.flatMap( ( feature ) => [ `## ${ feature.title }`, '', feature.text, '' ] ),
		'## In three steps',
		'',
		...landing.steps.map( ( step, i ) => `${ i + 1 }. ${ step }` ),
		'',
		`Docs: ${ new URL( 'docs/start/introduction/', new URL( import.meta.env.BASE_URL, 'https://example.invalid' ) ).pathname }`,
	].join( '\n' );

	return new Response( body, { headers: { 'Content-Type': 'text/markdown; charset=utf-8' } } );
};
