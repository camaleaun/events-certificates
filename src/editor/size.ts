/**
 * The size typed in the SVG editor's Size row, as the patch of the element it is for: the new width and height, and its layout (a layout
 * keeps the sizes as lengths) made again for the box it has now. Nothing when there is no size or it is the one the element already has.
 */
import { layoutFrom, lockedSide, type Layout, type PageBox } from '@camaleaun/draw';

type Sized = { x: number; y: number; w: number; h: number; layout?: Layout; lock?: number };

const round2 = ( n: number ) => Math.round( n * 100 ) / 100;

/** A size as the Size row shows it: points with two decimals at most. */
export const shown = round2;

export function sizePatch( el: Sized, w: number | undefined, h: number | undefined, page: PageBox ): Partial< Sized > {
	if ( ! w || ! h || ( round2( el.w ) === w && round2( el.h ) === h ) ) {
		return {};
	}
	return { w, h, ...( el.layout ? { layout: layoutFrom( el.layout, { x: el.x, y: el.y, w, h }, page ) } : {}) };
}

/** Which size follows the other when the element keeps its ratio (the `lock`): the height, unless only the height fills; null with no lock. */
export function followed( el: Sized ): 'w' | 'h' | null {
	if ( ! ( ( el.lock || 0 ) > 0 ) ) {
		return null;
	}
	return el.layout ? lockedSide( el.layout ) : 'h';
}

/** The sizes an axis in stretch (Fill) decides: the Size row does not set them, the margins do. */
export function stretched( el: Sized ): { w: boolean; h: boolean } {
	return { w: el.layout?.h.mode === 'stretch', h: el.layout?.v.mode === 'stretch' };
}

/**
 * The patch of an element for a width or a height typed in its Size row: the other side follows when the ratio is kept, and nothing
 * changes for a value that is not a size.
 */
export function resizeSide( el: Sized, side: 'w' | 'h', value: number, page: PageBox ): Partial< Sized > {
	if ( ! ( value > 0 ) ) {
		return {};
	}
	let w = side === 'w' ? value : el.w;
	let h = side === 'h' ? value : el.h;
	const follows = followed( el );
	if ( follows && el.lock ) {
		if ( side === 'w' && follows === 'h' ) {
			h = value / el.lock;
		} else if ( side === 'h' && follows === 'w' ) {
			w = value * el.lock;
		}
	}
	return sizePatch( el, round2( w ), round2( h ), page );
}
