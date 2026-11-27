import type { APIRoute } from 'astro';
import { ordered } from '@libs/docs';

// Every doc page as Markdown at /docs/<group>/<page>.md, for agents.
export async function getStaticPaths() {
	return ( await ordered() ).map( ( doc ) => ( { params: { slug: doc.id }, props: { doc } } ) );
}

export const GET: APIRoute = ( { props } ) => {
	const { doc } = props as { doc: { data: { title: string; description: string }; body?: string } };

	return new Response( `# ${ doc.data.title }\n\n> ${ doc.data.description }\n\n${ doc.body ?? '' }\n`, { headers: { 'Content-Type': 'text/markdown; charset=utf-8' } } );
};
