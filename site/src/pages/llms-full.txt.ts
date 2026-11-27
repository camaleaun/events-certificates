import type { APIRoute } from 'astro';
import { ordered } from '@libs/docs';
import { site } from '@libs/site';

// All the docs in one file.
export const GET: APIRoute = async () => {
	const parts = [ `# ${ site.name }\n\n${ site.tagline }\n` ];
	for ( const doc of await ordered() ) {
		parts.push( `\n---\n\n# ${ doc.data.title }\n\n> ${ doc.data.description }\n\n${ doc.body ?? '' }` );
	}

	return new Response( parts.join( '\n' ), { headers: { 'Content-Type': 'text/plain; charset=utf-8' } } );
};
