import type { APIRoute } from 'astro';
import { scenarioIds } from '@data/scenarios';
import { landingMarkdown, type ScenarioId } from '@libs/landing';

export const getStaticPaths = () => scenarioIds.map( ( id ) => ( { params: { slug: id } } ) );

// A scenario landing as Markdown, for agents (English: the agent files stay in English).
export const GET: APIRoute = ( { params } ) =>
	new Response( landingMarkdown( params.slug as ScenarioId ), { headers: { 'Content-Type': 'text/markdown; charset=utf-8' } } );
