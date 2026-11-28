import type { APIRoute } from 'astro';
import { faq } from '@libs/readme';

export const GET: APIRoute = () =>
	new Response( [ '# Frequently asked questions', '', ...faq().flatMap( ( item ) => [ `## ${ item.title }`, '', item.body, '' ] ) ].join( '\n' ), { headers: { 'Content-Type': 'text/markdown; charset=utf-8' } } );
