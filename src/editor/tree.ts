/**
 * The elements of a design as a tree: a group holds elements (`children`) that are placed from its top-left corner. Pure functions on the list of
 * elements, so the editor, the layers and the tests do the same; nothing here knows the page but through the box it is given.
 */
import { placeChildren } from '@camaleaun/draw';
import { box, moved, newId, placed, resolve, type Box, type Element, type GroupEl } from './model';

/** An element resolved to x, y, w, h, with the place of its parent's corner on the page (`ox`, `oy`: 0 for what is not in a group). */
export type Placed = Element & { ox?: number; oy?: number };

/** How many groups deep, and how many elements in all (camaleaun/draw's Clean). */
export const MAX_DEPTH = 3;
export const MAX_ELEMENTS = 300;

export const isGroup = ( el: Element | undefined | null ): el is GroupEl => !! el && el.type === 'group';

/** The elements a group holds (none for what is not a group). */
export const childrenOf = ( el: Element ): Element[] => ( isGroup( el ) ? el.children || [] : [] );

/** An element by id, anywhere in the tree. */
export function findEl< T extends Element >( list: T[], id: string | null | undefined ): T | undefined {
	for ( const el of list ) {
		if ( el.id === id ) {
			return el;
		}
		const inner = findEl( childrenOf( el ) as T[], id );
		if ( inner ) {
			return inner;
		}
	}
	return undefined;
}

/** The list with an element changed by `change` wherever it is (the same list when there is none by that id). */
export function mapEl( list: Element[], id: string, change: ( el: Element ) => Element ): Element[] {
	let any = false;
	const out = list.map( ( el ) => {
		if ( el.id === id ) {
			any = true;
			return change( el );
		}
		if ( isGroup( el ) ) {
			const children = mapEl( el.children || [], id, change );
			if ( children !== el.children ) {
				any = true;
				return { ...el, children };
			}
		}
		return el;
	} );
	return any ? out : list;
}

/** The list with an element left out wherever it is. */
export function removeEl( list: Element[], id: string ): Element[] {
	return list
		.filter( ( el ) => el.id !== id )
		.map( ( el ) => ( isGroup( el ) ? { ...el, children: removeEl( el.children || [], id ) } : el ) );
}

/** Every element, groups before what is in them, in the order of painting. */
export function flatten( list: Element[] ): Element[] {
	return list.flatMap( ( el ) => [ el, ...flatten( childrenOf( el ) ) ] );
}

/** How many elements the tree has. */
export const countEls = ( list: Element[] ): number => flatten( list ).length;

/** The group an element is in (null for what is on the page itself). */
export function parentOf( list: Element[], id: string ): GroupEl | null {
	for ( const el of list ) {
		if ( isGroup( el ) ) {
			if ( ( el.children || [] ).some( ( child ) => child.id === id ) ) {
				return el;
			}
			const inner = parentOf( el.children || [], id );
			if ( inner ) {
				return inner;
			}
		}
	}
	return null;
}

/** How many groups an element is inside. */
export function depthOf( list: Element[], id: string ): number {
	const group = parentOf( list, id );
	return group ? 1 + depthOf( list, group.id ) : 0;
}

/** How many levels of groups a tree has under its first level (a group with no group in it is 1). */
export function groupsDeep( el: Element ): number {
	return isGroup( el ) ? 1 + Math.max( 0, ...( el.children || [] ).map( groupsDeep ) ) : 0;
}

/** The page of what is in a group: its size, with its corner at 0, 0 and no safe margin. */
export const innerPage = ( group: { w: number; h: number } ) => ( { w: group.w, h: group.h, safe: 0 } as { w: number; h: number; safe: number; bg: string; bleed: number } );

/**
 * The elements with their places resolved. What is in a group is placed by the group: in a row, a stack or a grid by the flow, in a free group by its own
 * place or anchors to the group's size (its corner is 0, 0). Each carries the place of its group's corner on the page (`ox`, `oy`) so it can be drawn and
 * picked where it is.
 */
export function resolveTree( list: Element[], page: Parameters< typeof resolve >[ 1 ], ox = 0, oy = 0, group?: GroupEl ): Placed[] {
	const placedList = group ? ( placeChildren( group as never, list as never[] ) as Element[] ) : list.map( ( el ) => resolve( el, page ) );
	return placedList.map( ( r ) => {
		const out = ( ox || oy ? { ...r, ox, oy } : r ) as Placed;
		if ( isGroup( r ) ) {
			return { ...out, children: resolveTree( r.children || [], page, ox + r.x, oy + r.y, r ) } as Placed;
		}
		return out;
	} );
}

/** An element's box on the page (in a group its box is from the group's corner: `ox`, `oy` say where that is). */
export function pageBox( el: Placed ): Box {
	const b = box( el );
	return { ...b, x: b.x + ( el.ox || 0 ), y: b.y + ( el.oy || 0 ) };
}

type Page = Parameters< typeof resolve >[ 1 ];

/** Where the corner of a group is on the page. */
function cornerOf( resolved: Placed[], id: string | null ): { x: number; y: number } {
	const g = id ? ( findEl( resolved, id ) as ( Placed & { x: number; y: number } ) | undefined ) : undefined;
	return g ? { x: ( g.ox || 0 ) + g.x, y: ( g.oy || 0 ) + g.y } : { x: 0, y: 0 };
}

/** Whether an element may be put in a group: the authenticity code, the verification and a signature stay on the certificate itself. */
export const canHold = ( el: Element ): boolean => ! flatten( [ el ] ).some( ( e ) => e.type === 'qr' || e.type === 'verification' || e.type === 'signature' || e.slot === 'signature' );

/**
 * An element moved into a group (or out to the page, with `to` null) at a place in its list, keeping its place on the page: its numbers, or its anchors,
 * are written from the new origin. A group does not go into itself or into what it holds, nor deeper than MAX_DEPTH, and an element that has no place
 * in a group (see canHold) does not go into one: the list comes back as it was.
 */
export function moveEl( list: Element[], page: Page, id: string, to: string | null, index?: number ): Element[] {
	const el = findEl( list, id );
	const resolved = resolveTree( list, page );
	const now = findEl( resolved, id ) as Placed | undefined;
	if ( ! el || ! now ) {
		return list;
	}
	const target = to ? findEl( list, to ) : undefined;
	if ( to && ! isGroup( target ) ) {
		return list;
	}
	if ( target && ( target.id === id || findEl( childrenOf( el ), target.id ) || ! canHold( el ) || depthOf( list, target.id ) + 1 + groupsDeep( el ) > MAX_DEPTH ) ) {
		return list;
	}
	const origin = cornerOf( resolved, to );
	const before = cornerOf( resolved, parentOf( list, id )?.id ?? null );
	const was = pageBox( now );
	const local: Box = { x: was.x - origin.x, y: was.y - origin.y, w: was.w, h: was.h };
	const targetAt = to ? ( findEl( resolved, to ) as Placed & { w: number; h: number } ) : null;
	let next: Element;
	if ( el.type === 'line' ) {
		next = moved( el, before.x - origin.x, before.y - origin.y );
	} else if ( el.layout ) {
		const layout = { ...el.layout, ref: to ? ( 'parent' as const ) : el.layout.ref === 'parent' ? ( 'page' as const ) : el.layout.ref };
		next = placed( { ...el, layout }, local, ( targetAt ? innerPage( targetAt ) : page ) as never );
	} else {
		next = { ...el, x: local.x, y: local.y } as Element;
	}
	const without = removeEl( list, id );
	const put = ( items: Element[] ) => {
		const at = index === undefined ? items.length : Math.max( 0, Math.min( index, items.length ) );
		return [ ...items.slice( 0, at ), next, ...items.slice( at ) ];
	};
	return to ? mapEl( without, to, ( g ) => ( { ...g, children: put( ( g as GroupEl ).children || [] ) } as Element ) ) : put( without );
}

/**
 * The elements `ids` (all in the same list) put in a new group that is just as big as they are together, in their place; null when they are not
 * all in one list, or may not be in a group.
 */
export function groupEls( list: Element[], page: Page, ids: string[], group: GroupEl ): Element[] | null {
	const parent = parentOf( list, ids[ 0 ] );
	const siblings = parent ? parent.children || [] : list;
	const picked = siblings.filter( ( el ) => ids.includes( el.id ) );
	if ( ! picked.length || picked.length !== ids.length || ! picked.every( canHold ) || depthOf( list, ids[ 0 ] ) + 1 + Math.max( ...picked.map( groupsDeep ) ) > MAX_DEPTH ) {
		return null;
	}
	const resolved = resolveTree( list, page );
	const boxes = picked.map( ( el ) => pageBox( findEl( resolved, el.id ) as Placed ) );
	const x = Math.min( ...boxes.map( ( b ) => b.x ) );
	const y = Math.min( ...boxes.map( ( b ) => b.y ) );
	const w = Math.max( ...boxes.map( ( b ) => b.x + b.w ) ) - x;
	const h = Math.max( ...boxes.map( ( b ) => b.y + b.h ) ) - y;
	const at = siblings.findIndex( ( el ) => el.id === picked[ 0 ].id );
	const base = cornerOf( resolved, parent ? parent.id : null );
	// The group is made empty, in the place of the first of them, and each one is moved into it: each keeps its place on the page.
	const made = { ...group, x: x - base.x, y: y - base.y, w, h, children: [] } as GroupEl;
	const holder = ( items: Element[] ) => [ ...items.slice( 0, at ), made, ...items.slice( at ) ];
	let tree: Element[] = parent ? mapEl( list, parent.id, ( g ) => ( { ...g, children: holder( ( g as GroupEl ).children || [] ) } as Element ) ) : holder( list );
	for ( const el of picked ) {
		tree = moveEl( tree, page, el.id, made.id );
	}
	return tree;
}

/** The elements of a group put in its place in the list, and the group left out; the list as it was when it is not a group. */
export function ungroupEl( list: Element[], page: Page, id: string ): Element[] {
	const group = findEl( list, id );
	if ( ! isGroup( group ) ) {
		return list;
	}
	const parent = parentOf( list, id );
	const at = ( parent ? parent.children || [] : list ).findIndex( ( el ) => el.id === id );
	let tree = list;
	let n = 0;
	for ( const child of group.children || [] ) {
		tree = moveEl( tree, page, child.id, parent ? parent.id : null, at + n++ );
	}
	return removeEl( tree, id );
}

/** The list with an element put just after another, in the list the other is in (at the end of the page's list when the other is not found). */
export function insertAfter( list: Element[], id: string, el: Element ): Element[] {
	const at = list.findIndex( ( e ) => e.id === id );
	if ( at >= 0 ) {
		return [ ...list.slice( 0, at + 1 ), el, ...list.slice( at + 1 ) ];
	}
	let found = false;
	const out = list.map( ( e ) => {
		if ( found || ! isGroup( e ) ) {
			return e;
		}
		const children = insertAfter( e.children || [], id, el );
		if ( children.length !== ( e.children || [] ).length ) {
			found = true;
			return { ...e, children };
		}
		return e;
	} );
	return found ? out : [ ...list, el ];
}

/** An element as a copy: new ids for it and for all that is in it, and no slot (a slot is only of one element). */
export function cloneEl( el: Element, name?: string ): Element {
	const copy = { ...el, id: newId( el.type ), slot: undefined, ...( name ? { name } : {} ) } as Element;
	return isGroup( copy ) ? ( { ...copy, children: ( copy.children || [] ).map( ( child ) => cloneEl( child, child.name ) ) } as Element ) : copy;
}

/** An element sent up or down one place, or to the front or the back, among the elements of its own list. */
export function reorderEl( list: Element[], id: string, to: 'up' | 'down' | 'top' | 'bottom' ): Element[] {
	const i = list.findIndex( ( e ) => e.id === id );
	if ( i >= 0 ) {
		const out = [ ...list ];
		const [ el ] = out.splice( i, 1 );
		const j = to === 'top' ? out.length : to === 'bottom' ? 0 : to === 'up' ? Math.min( out.length, i + 1 ) : Math.max( 0, i - 1 );
		out.splice( j, 0, el );
		return out;
	}
	return list.map( ( e ) => ( isGroup( e ) ? { ...e, children: reorderEl( e.children || [], id, to ) } : e ) );
}

/**
 * What is in a group with the box it has now written as numbers (and no anchors): the group may change how it places them (free, row, stack, grid) and
 * each keeps the size it had, and the place it had to start from.
 */
export function bakeChildren( resolvedChildren: Element[] ): Element[] {
	return resolvedChildren.map( ( el ) => {
		const { ox: _ox, oy: _oy, ...rest } = el as Placed;
		return rest.type === 'line' ? ( rest as Element ) : ( { ...rest, x: rest.x, y: rest.y, w: rest.w, h: rest.h, layout: undefined } as Element );
	} );
}

/**
 * The elements as one flat list in the order of painting, each on the page (a group as its own rectangle, then what is in it with its place on the page and no
 * anchors): for the backends that draw one element after another (the quick generator's PDF and canvas). What is in a hidden group is not drawn.
 */
export function flattenTree( list: Element[], page: Parameters< typeof resolve >[ 1 ] ): Element[] {
	const out: Element[] = [];
	const walk = ( items: Placed[] ) => {
		for ( const el of items ) {
			if ( el.hidden ) {
				continue;
			}
			const { ox = 0, oy = 0, ...rest } = el;
			const there = rest.type === 'line' ? ( moved( rest as Element, ox, oy ) as Element ) : ( { ...rest, x: rest.x + ox, y: rest.y + oy, layout: undefined } as Element );
			if ( isGroup( there ) ) {
				const { children, ...frame } = there as GroupEl;
				out.push( { ...frame, type: 'rect' } as unknown as Element );
				walk( ( resolveTreeOf( el ) as Placed[] ) );
			} else {
				out.push( there );
			}
		}
	};
	walk( resolveTree( list, page ) );
	return out;
}

const resolveTreeOf = ( group: Placed ): Placed[] => ( isGroup( group ) ? ( group.children as Placed[] ) : [] );

/**
 * The group a new element goes in when `selected` is selected: the group itself, or the group the selected element is in; null when it goes on the page (nothing
 * selected, or an element of the page's own list), when it may not be held by a group (see canHold), or when the tree would be too deep or too big.
 */
export function holderFor( list: Element[], selected: string | null | undefined, el: Element ): GroupEl | null {
	const target = selected ? findEl( list, selected ) : undefined;
	const holder = target ? ( isGroup( target ) ? target : parentOf( list, target.id ) ) : null;
	if ( ! holder || ! canHold( el ) || depthOf( list, holder.id ) + 1 + groupsDeep( el ) > MAX_DEPTH || flatten( list ).length + 1 > MAX_ELEMENTS ) {
		return null;
	}
	return holder;
}

/** The list with an element put at the end of a group's own (the front of what is in it). */
export function insertInto( list: Element[], holderId: string, el: Element ): Element[] {
	return mapEl( list, holderId, ( g ) => ( { ...g, children: [ ...( ( g as GroupEl ).children || [] ), el ] } as Element ) );
}

