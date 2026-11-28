import type { APIRoute } from 'astro';
import { matrix, whereText } from '@libs/landing';

export const GET: APIRoute = () => {
	const rows = matrix( 'en' );
	const body = [
		'# What works where',
		'',
		'| Feature | Standalone | GatherPress | WordCamp.org (CampTix) | Both |',
		'| --- | --- | --- | --- | --- |',
		...rows.map( ( row ) => `| ${ row.feature.title }${ row.feature.claimNote ? ` (${ row.feature.claimNote })` : '' } | ${ row.cells.map( ( cell ) => whereText( cell.where ) ).join( ' | ' ) } |` ),
	].join( '\n' );
	return new Response( body, { headers: { 'Content-Type': 'text/markdown; charset=utf-8' } } );
};
