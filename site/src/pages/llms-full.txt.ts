import type { APIRoute } from 'astro';
import { ordered } from '@libs/docs';
import { site } from '@libs/site';
import { faq, changelog } from '@libs/readme';

// All the docs in one file.
export const GET: APIRoute = async () => {
	const parts = [ `# ${ site.name }\n\n${ site.tagline }\n` ];
	for ( const doc of await ordered() ) {
		parts.push( `\n---\n\n# ${ doc.data.title }\n\n> ${ doc.data.description }\n\n${ doc.body ?? '' }` );
	}

	parts.push( '\n---\n\n# Frequently asked questions\n', ...faq().map( ( item ) => `\n## ${ item.title }\n\n${ item.body }\n` ) );
	parts.push( '\n---\n\n# Changelog\n', ...changelog().map( ( release ) => `\n## ${ release.title }\n\n${ release.body }\n` ) );

	return new Response( parts.join( '\n' ), { headers: { 'Content-Type': 'text/plain; charset=utf-8' } } );
};
