import type { APIRoute } from 'astro';
import { scenarioIds } from '@data/scenarios';
import { landingMarkdown, type ScenarioId } from '@libs/landing';

export const getStaticPaths = () => scenarioIds.map( ( id ) => ( { params: { scenario: id } } ) );

// A scenario landing as Markdown, for agents: the same data as the page.
export const GET: APIRoute = ( { params } ) =>
	new Response( landingMarkdown( params.scenario as ScenarioId ), { headers: { 'Content-Type': 'text/markdown; charset=utf-8' } } );
