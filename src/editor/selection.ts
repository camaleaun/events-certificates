/**
 * What is selected, as a list of ids (docs/decisions/0056): the editor's own list is the truth, as the core's store can only hold a run of siblings. These are the pure parts:
 * ranges and toggles (lifted to the same list, as the core's `getCommonDepthClientIds` does), select all, escape, what a click targets and what a marquee takes.
 */
import { box, type Element } from './model';
import { canHold, childrenOf, findEl, flatten, isGroup, parentOf, type Placed } from './tree';

type Tree = Element[];

/** The elements from the page down to one (itself last). */
function pathOf( tree: Tree, id: string ): Element[] {
	const path: Element[] = [];
	for ( let el: Element | undefined = findEl( tree, id ); el; el = parentOf( tree, el.id ) ?? undefined ) {
		path.unshift( el );
	}
	return path;
}

/** The elements that sit next to each other in one list: the page's, or a group's. */
const listOf = ( tree: Tree, parent: string | null ): Element[] => ( parent ? childrenOf( findEl( tree, parent ) as Element ) : tree );

/** The group an element is in (null: the page). */
const parentIdOf = ( tree: Tree, id: string ): string | null => parentOf( tree, id )?.id ?? null;

/** Ids in the order they are painted (and listed). */
export function inListOrder( tree: Tree, ids: string[] ): string[] {
	const order = flatten( tree ).map( ( el ) => el.id );
	return [ ...new Set( ids ) ].filter( ( id ) => order.includes( id ) ).sort( ( a, b ) => order.indexOf( a ) - order.indexOf( b ) );
}

/** The elements of a selection lifted to one list: each becomes the one that holds it, in the list of the lowest group that holds all. */
function lift( tree: Tree, ids: string[] ): string[] {
	const paths = ids.map( ( id ) => pathOf( tree, id ) ).filter( ( p ) => p.length );
	if ( ! paths.length ) {
		return [];
	}
	const shortest = Math.min( ...paths.map( ( p ) => p.length ) );
	let common = 0;
	while ( common < shortest && paths.every( ( p ) => p[ common ].id === paths[ 0 ][ common ].id ) ) {
		common++;
	}
	// The level just under the lowest group they share; if one is that group itself, the group.
	const level = Math.min( common, shortest - 1 );
	return inListOrder( tree, paths.map( ( p ) => p[ level ].id ) );
}

/** The two ends of a range, each lifted to its ancestor in the list the lowest shared group holds. */
export function commonDepth( tree: Tree, a: string, b: string ): { parent: string | null; start: string; end: string } {
	const pa = pathOf( tree, a );
	const pb = pathOf( tree, b );
	let common = 0;
	while ( common < Math.min( pa.length, pb.length ) && pa[ common ].id === pb[ common ].id ) {
		common++;
	}
	const level = Math.min( common, pa.length - 1, pb.length - 1 );
	const parent = level > 0 ? pa[ level - 1 ].id : null;
	return { parent, start: pa[ level ].id, end: pb[ level ].id };
}

/** The elements from one to another, in the order of their list (whichever was first). */
export function rangeIds( tree: Tree, anchor: string, id: string ): string[] {
	const { parent, start, end } = commonDepth( tree, anchor, id );
	const ids = listOf( tree, parent ).map( ( el ) => el.id );
	const from = ids.indexOf( start );
	const to = ids.indexOf( end );
	return ids.slice( Math.min( from, to ), Math.max( from, to ) + 1 );
}

/** A selection with one element added, or taken out if it was in (all lifted to one list first). */
export function toggleIds( tree: Tree, ids: string[], id: string ): string[] {
	if ( ! ids.length ) {
		return [ id ];
	}
	const lifted = lift( tree, [ ...ids, id ] );
	const target = lifted.find( ( l ) => l === id || pathOf( tree, id ).some( ( p ) => p.id === l ) ) ?? id;
	const current = lift( tree, ids );
	return inListOrder( tree, current.includes( target ) ? lifted.filter( ( l ) => l !== target ) : lifted );
}

/** The group all of them are in (null: the page's own list), or undefined when they are in different lists. */
export function commonParent( tree: Tree, ids: string[] ): string | null | undefined {
	const parents = ids.map( ( id ) => parentIdOf( tree, id ) );
	return parents.length && parents.every( ( p ) => p === parents[ 0 ] ) ? parents[ 0 ] : undefined;
}

/** Whether they are next to each other in one list (what the core's store can hold as a range). */
export function isContiguous( tree: Tree, ids: string[] ): boolean {
	const parent = commonParent( tree, ids );
	if ( parent === undefined ) {
		return false;
	}
	const list = listOf( tree, parent ).map( ( el ) => el.id );
	const at = ids.map( ( id ) => list.indexOf( id ) ).sort( ( a, b ) => a - b );
	return at.every( ( n, i ) => i === 0 || n === at[ i - 1 ] + 1 );
}

/** Select all: the list the selection is in, then, when it was all of it, the group that holds it. */
export function selectAllIds( tree: Tree, ids: string[] ): string[] {
	const lifted = ids.length ? lift( tree, ids ) : [];
	const parent = lifted.length ? commonParent( tree, lifted ) ?? null : null;
	const siblings = listOf( tree, parent ).map( ( el ) => el.id );
	if ( lifted.length && lifted.length >= siblings.length && parent ) {
		return [ parent ];
	}
	return siblings;
}

/** Escape: several become the first, one goes up to the group that holds it, then nothing. */
export function escapeIds( tree: Tree, ids: string[] ): string[] {
	if ( ids.length > 1 ) {
		return inListOrder( tree, ids ).slice( 0, 1 );
	}
	const parent = ids.length ? parentIdOf( tree, ids[ 0 ] ) : null;
	return parent ? [ parent ] : [];
}

/**
 * What a click on an element selects: the outermost group (so a group can be moved), or, when something in its chain is already selected, that; and next to a selection inside a group,
 * the level of that group (so Shift-clicking a sibling does not jump out to the group above).
 */
export function clickTarget( tree: Tree, clicked: string, ids: string[] ): string {
	const chain = pathOf( tree, clicked ).map( ( el ) => el.id );
	const selected = chain.findIndex( ( id ) => ids.includes( id ) );
	if ( selected >= 0 ) {
		return chain[ selected ];
	}
	const context = ids.length ? commonParent( tree, ids ) : undefined;
	const inside = context ? chain.indexOf( context ) : -1;
	if ( inside >= 0 ) {
		return chain[ inside + 1 ] ?? clicked;
	}
	return chain[ 0 ] ?? clicked;
}

/**
 * What a marquee takes: what it touches, not what is locked or hidden. A group the box holds whole is taken as a group; one it only touches gives what is in it that the box touches (all of
 * them, so several inside a group are all selected), and the group itself when the box touches its frame and nothing in it. `placed` has their places on the page; `context` is the group the
 * box begins in (null: the page), whose elements are what is looked at.
 */
export function marqueeIds( placed: Placed[], rect: { x: number; y: number; w: number; h: number }, context: string | null ): string[] {
	const list = ( context ? childrenOf( findEl( placed, context ) as Element ) : placed ) as Placed[];
	const at = ( el: Placed ) => {
		const b = box( el );
		return { x: b.x + ( el.ox || 0 ), y: b.y + ( el.oy || 0 ), w: b.w, h: b.h };
	};
	const touches = ( b: { x: number; y: number; w: number; h: number } ) => b.x < rect.x + rect.w && b.x + b.w > rect.x && b.y < rect.y + rect.h && b.y + b.h > rect.y;
	const holds = ( b: { x: number; y: number; w: number; h: number } ) => b.x >= rect.x && b.y >= rect.y && b.x + b.w <= rect.x + rect.w && b.y + b.h <= rect.y + rect.h;
	const take = ( items: Placed[] ): string[] =>
		items.flatMap( ( el ) => {
			if ( el.hidden || el.locked || ! touches( at( el ) ) ) {
				return [];
			}
			if ( isGroup( el ) && ! holds( at( el ) ) ) {
				const inside = take( ( el.children || [] ) as Placed[] );
				return inside.length ? inside : [ el.id ];
			}
			return [ el.id ];
		} );
	return take( list );
}

/** The type of the selection when all are the same (the toolbar shows its icon), else null. */
export function selectionType( tree: Tree, ids: string[] ): { type: string; slot: string | undefined } | null {
	const els = ids.map( ( id ) => findEl( tree, id ) ).filter( Boolean ) as Element[];
	if ( ! els.length || els.length !== ids.length ) {
		return null;
	}
	const key = ( el: Element ) => `${ el.type }:${ el.slot ?? '' }`;
	return els.every( ( el ) => key( el ) === key( els[ 0 ] ) ) ? { type: els[ 0 ].type, slot: els[ 0 ].slot } : null;
}

/** Whether these can be grouped: all in one list, and none that has to stay on the page itself. */
export function canGroupIds( tree: Tree, ids: string[] ): boolean {
	if ( ! ids.length || commonParent( tree, ids ) === undefined ) {
		return false;
	}
	return ids.every( ( id ) => {
		const el = findEl( tree, id );
		return !! el && canHold( el );
	} );
}
