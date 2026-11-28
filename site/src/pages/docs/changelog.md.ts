import type { APIRoute } from 'astro';
import { changelog } from '@libs/readme';

export const GET: APIRoute = () =>
	new Response( [ '# Changelog', '', ...changelog().flatMap( ( release ) => [ `## ${ release.title }`, '', release.body, '' ] ) ].join( '\n' ), { headers: { 'Content-Type': 'text/markdown; charset=utf-8' } } );
