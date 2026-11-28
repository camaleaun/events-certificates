import { getCollection, type CollectionEntry } from 'astro:content';
import { sidebar } from '@data/sidebar';

export type Doc = CollectionEntry< 'docs' >;

/** Whether a page is shown in English (a page with `locales` that leaves English out is translators' source only). */
export const shown = ( doc: Doc ) => ! doc.data.locales || doc.data.locales.includes( 'en' );

/** The docs in the sidebar's order, grouped; throws when the sidebar and the files disagree. */
export async function groups() {
	const all = await getCollection( 'docs' );
	const byId = new Map( all.map( ( doc ) => [ doc.id, doc ] ) );
	const listed = sidebar.flatMap( ( group ) => group.pages );
	for ( const id of listed ) {
		if ( ! byId.has( id ) ) {
			throw new Error( `The sidebar lists "${ id }" but there is no such page.` );
		}
	}
	for ( const id of byId.keys() ) {
		if ( ! listed.includes( id ) ) {
			throw new Error( `The page "${ id }" is not in the sidebar (src/data/sidebar.ts).` );
		}
	}

	return sidebar.map( ( group ) => ( { title: group.title, docs: group.pages.map( ( id ) => byId.get( id ) as Doc ).filter( shown ) } ) );
}

export async function ordered() {
	return ( await groups() ).flatMap( ( group ) => group.docs );
}
