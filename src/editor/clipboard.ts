/**
 * Copying and pasting elements (docs/decisions/0056): what a copy puts on the clipboard (the elements as they are on the page, so a paste keeps their place wherever it goes) and
 * where a paste puts them. The DOM side (the events, the formats) is in Editor.tsx.
 */
import { color, runsOf, moved, type Design, type Element } from './model';
import { textFromRuns } from './rich';
import { inListOrder } from './selection';
import { cloneEl, findEl, flatten, isGroup, MAX_ELEMENTS, moveEl, parentOf } from './tree';

type Page = Parameters< typeof moveEl >[ 1 ];
type Source = Pick< Design, 'colors' | 'palette' >;

/** What a copy writes (as `application/x-events-certificates+json`). */
export type ClipboardPayload = { version: 1; elements: Element[]; source: Source; cut?: boolean };

export const CLIPBOARD_TYPE = 'application/x-events-certificates+json';

/** The elements of a copy: the selected ones as they are on the page, not one that is in another that is copied too. */
export function copyPayload( tree: Element[], page: Page, ids: string[], source: Source ): ClipboardPayload {
	const picked = inListOrder( tree, ids );
	const top = picked.filter( ( id ) => ! picked.some( ( other ) => other !== id && !! findEl( flatten( [ findEl( tree, other ) as Element ] ), id ) ) );
	const elements = top
		.map( ( id ) => findEl( moveEl( tree, page, id, null ), id ) )
		.filter( Boolean ) as Element[];
	return { version: 1, elements: JSON.parse( JSON.stringify( elements ) ), source: { colors: source.colors, palette: source.palette } };
}

/** The words of the texts of a copy, a line each (`[[fields]]` and marks as they are written: ADR 0054). */
export function plainTextOf( els: Element[] ): string {
	return flatten( els )
		.filter( ( el ) => el.type === 'text' )
		.map( ( el ) => textFromRuns( runsOf( el as never ) ) )
		.filter( Boolean )
		.join( '\n' );
}

/** A color of the theme that the other template does not have (`$primary`) becomes its value, so a paste looks the same there. */
export function adoptColors( els: Element[], source: Source, target: Pick< Design, 'colors' | 'palette' > ): Element[] {
	const has = ( slug: string ) => !! ( target.colors || {} )[ slug ] || ( target.palette || [] ).some( ( p ) => p.slug === slug );
	const walk = ( value: unknown ): unknown => {
		if ( typeof value === 'string' && /^\$[a-z0-9_-]+$/i.test( value ) ) {
			if ( has( value.slice( 1 ) ) ) {
				return value;
			}
			const literal = color( { ...( source as object ), page: {}, elements: [] } as unknown as Design, value, 'light' );
			return literal || value;
		}
		if ( Array.isArray( value ) ) {
			return value.map( walk );
		}
		if ( value && typeof value === 'object' ) {
			return Object.fromEntries( Object.entries( value as Record< string, unknown > ).map( ( [ key, v ] ) => [ key, walk( v ) ] ) );
		}
		return value;
	};
	return walk( els ) as Element[];
}

/**
 * Elements put in a design: new ids; in the selected group when only that is selected, else right after the one at the front of the selection, in its own list; at the end of the page
 * when nothing is selected. Each keeps its place on the page (moved by `offset`); the code of authenticity and the verification are not pasted when there is one. Null when there
 * would be more than `max` elements.
 */
export function pasteEls( list: Element[], page: Page, els: Element[], where: { selected: string[]; offset: number; max?: number } ): { list: Element[]; ids: string[] } | null {
	const have = new Set( flatten( list ).map( ( el ) => el.type ) );
	const incoming = els.filter( ( el ) => ! ( ( el.type === 'qr' || el.type === 'verification' ) && have.has( el.type ) ) );
	if ( ! incoming.length ) {
		return { list, ids: [] };
	}
	if ( flatten( list ).length + flatten( incoming ).length > ( where.max ?? MAX_ELEMENTS ) ) {
		return null;
	}
	const clones = incoming.map( ( el ) => ( where.offset ? moved( cloneEl( el ), where.offset, where.offset ) : cloneEl( el ) ) );
	let out = [ ...list, ...clones ];
	const picked = inListOrder( list, where.selected );
	const only = picked.length === 1 ? findEl( list, picked[ 0 ] ) : undefined;
	let to: string | null = null;
	let at: number | undefined;
	if ( only && isGroup( only ) ) {
		to = only.id;
	} else if ( picked.length ) {
		const front = picked[ picked.length - 1 ];
		const holder = parentOf( list, front );
		to = holder ? holder.id : null;
		const siblings = holder ? holder.children || [] : list;
		at = siblings.findIndex( ( el ) => el.id === front ) + 1;
	} else {
		return { list: out, ids: clones.map( ( el ) => el.id ) };
	}
	clones.forEach( ( el, i ) => {
		out = moveEl( out, page, el.id, to, at === undefined ? undefined : at + i );
	} );
	return { list: out, ids: clones.map( ( el ) => el.id ) };
}
