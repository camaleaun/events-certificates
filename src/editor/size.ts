/**
 * The size typed in the SVG editor's Size row, as the patch of the element it is for: the new width and height, and its layout (a layout
 * keeps the sizes as lengths) made again for the box it has now. Nothing when there is no size or it is the one the element already has.
 */
import { layoutFrom, lockedSide, parseLen, UNITS, type Axis, type AxisMode, type Layout, type PageBox } from '@camaleaun/draw';

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

export type Unit = 'pt' | 'px' | 'mm' | 'cm' | 'in' | '%';

/** The unit a length is written in (pt when it has none, or is a calc()). */
export function unitOf( length?: string ): Unit {
	const m = length && ! /calc/i.test( length ) ? /(pt|px|mm|cm|in|%)\s*$/i.exec( length ) : null;
	return ( m ? m[ 1 ].toLowerCase() : 'pt' ) as Unit;
}

/** A length in points written in a unit: two decimals (three in inches); % is of `total`. The same as lenLike() of camaleaun/draw. */
export function lengthIn( pt: number, unit: Unit, total = 0, digits?: number ): string {
	const round = ( n: number, d: number ) => Math.round( n * 10 ** d ) / 10 ** d;
	if ( unit === '%' ) {
		return `${ round( total ? ( pt / total ) * 100 : 0, digits ?? 2 ) }%`;
	}
	return `${ round( pt / ( UNITS[ unit ] || 1 ), digits ?? ( unit === 'in' ? 3 : 2 ) ) }${ unit }`;
}

/**
 * An axis aligned to the start, the center, the end or the whole of its box: the offsets are zero ("0 left", "0 center", "0 right", "0 and 0
 * fill") in the unit the axis was written in, and the element keeps its size (not the place it had). `total` is the length of the box on that axis.
 */
export function alignAxis( mode: AxisMode, sizePt: number, previous: Axis | undefined, total: number ): Axis {
	const unit = unitOf( previous?.size ?? previous?.start ?? previous?.end );
	const zero = `0${ unit }`;
	if ( mode === 'stretch' ) {
		return { mode, start: zero, end: zero };
	}
	const size = lengthIn( sizePt, unit, total );
	return mode === 'end' ? { mode, end: zero, size } : { mode, start: zero, size };
}

/** The layout an element has, or the one that holds it where it is (anchored to the start of the page). */
function layoutOf( el: Sized, page: PageBox ): Layout {
	return el.layout || layoutFrom( { h: { mode: 'start' }, v: { mode: 'start' }, ref: 'page' }, { x: el.x, y: el.y, w: el.w, h: el.h }, page );
}

/** The size of an element on a side as the unit of its layout shows it: the number and the unit (pt with no layout). */
export function sizeIn( el: Sized, side: 'w' | 'h' ): { value: number; unit: Unit } {
	const axis = side === 'w' ? el.layout?.h : el.layout?.v;
	const unit = unitOf( axis?.size );
	const pt = side === 'w' ? el.w : el.h;
	const digits = unit === 'in' ? 3 : 2;
	return { value: Math.round( ( pt / ( unit === '%' ? 1 : UNITS[ unit ] || 1 ) ) * 10 ** digits ) / 10 ** digits, unit };
}

/**
 * The patch of an element for a width or a height typed in its Size row with a unit ("42.5mm"): the size follows the ratio when it is kept, and
 * the layout is made again with the size written in that unit. The same unit as the layout's, or another one: a change of unit alone (the
 * value is the size the element has) only rewrites how the size is written.
 */
export function resizeSideLen( el: Sized, side: 'w' | 'h', text: string, page: PageBox ): Partial< Sized > {
	const unit = unitOf( text );
	const axisKey = side === 'w' ? 'h' : 'v';
	const base = layoutOf( el, page );
	if ( base[ axisKey ].mode === 'stretch' ) {
		return {};
	}
	const pt = unit === '%' ? 0 : parseLen( text, 0 );
	const writtenIn: Layout = { ...base, [ axisKey ]: { ...base[ axisKey ], size: `0${ unit }` } };
	const current = side === 'w' ? el.w : el.h;
	// A unit alone: the number is the size the element has, in that unit (rounded as it is written): the layout says it in the new unit.
	if ( ! ( pt > 0 ) || Number.parseFloat( lengthIn( current, unit ) ) === Number.parseFloat( text ) ) {
		return { layout: layoutFrom( writtenIn, { x: el.x, y: el.y, w: el.w, h: el.h }, page ) };
	}
	const patch = resizeSide( { ...el, layout: writtenIn }, side, pt, page );
	return patch.layout || ! Object.keys( patch ).length ? patch : { ...patch, layout: layoutFrom( writtenIn, { x: el.x, y: el.y, w: patch.w ?? el.w, h: patch.h ?? el.h }, page ) };
}

/**
 * The patch of an element for another unit of a size (the size is the same): the layout says it in that unit with more decimals than a typed
 * length has, so the size does not drift (0.01 cm is 0.28 pt) however often the unit changes. Nothing for a size that fills.
 */
export function unitPatch( el: Sized, side: 'w' | 'h', unit: Unit, page: PageBox ): Partial< Sized > {
	const key = side === 'w' ? 'h' : 'v';
	const base = layoutOf( el, page );
	if ( base[ key ].mode === 'stretch' ) {
		return {};
	}
	const box = base.ref === 'safe' ? { w: page.w - page.safe * 2, h: page.h - page.safe * 2 } : { w: page.w, h: page.h };
	return { layout: { ...base, [ key ]: { ...base[ key ], size: lengthIn( side === 'w' ? el.w : el.h, unit, side === 'w' ? box.w : box.h, 4 ) } } };
}

/** A corner radius is a number (points) or a percentage of the shorter side ("25%", 0 to 50: 50% makes a pill). */
export type Radius = number | string;
export type RadiusUnit = 'pt' | '%';

export const radiusUnit = ( v?: Radius ): RadiusUnit => ( typeof v === 'string' && /%\s*$/.test( v ) ? '%' : 'pt' );
export const radiusNumber = ( v?: Radius ): number => ( typeof v === 'string' ? Number.parseFloat( v ) || 0 : v || 0 );

/** A radius for a number typed in a unit: a number in points, a string with % (kept between 0 and 50). */
export function radiusFrom( n: number, unit: RadiusUnit ): Radius {
	return unit === '%' ? `${ Math.max( 0, Math.min( n, 50 ) ) }%` : Math.max( 0, n );
}

/** The same radius in the other unit: the corner is the same (a % is of the shorter side of the box). */
export function radiusConvert( v: Radius | undefined, to: RadiusUnit, w: number, h: number ): Radius {
	const side = Math.min( w, h ) || 1;
	const pt = radiusUnit( v ) === '%' ? ( radiusNumber( v ) / 100 ) * side : radiusNumber( v );
	const round = ( n: number ) => Math.round( n * 100 ) / 100;
	return to === '%' ? radiusFrom( round( ( pt / side ) * 100 ), '%' ) : round( pt );
}

/** The patch of a line that has its box moved or stretched: `key` is the one that changes (x, y, w or h) to `value` points. */
export function lineBoxPatch( el: { x1: number; y1: number; x2: number; y2: number }, key: 'x' | 'y' | 'w' | 'h', value: number ): Partial< { x1: number; y1: number; x2: number; y2: number } > {
	const x = Math.min( el.x1, el.x2 );
	const y = Math.min( el.y1, el.y2 );
	const v = Math.max( 0, value );
	switch ( key ) {
		case 'x':
			return { x1: el.x1 + ( v - x ), x2: el.x2 + ( v - x ) };
		case 'y':
			return { y1: el.y1 + ( v - y ), y2: el.y2 + ( v - y ) };
		case 'w':
			// The left end stays where it is; the other end is the width away, on the side the line went.
			return el.x2 >= el.x1 ? { x2: el.x1 + v } : { x1: el.x2 + v };
		default:
			return el.y2 >= el.y1 ? { y2: el.y1 + v } : { y1: el.y2 + v };
	}
}

/** Whether an SVG says no size on a side (its layout has none there and does not stretch): it is as big as its viewBox. */
export function sizeEmpty( el: Sized & { type?: string }, side: 'w' | 'h' ): boolean {
	const axis = side === 'w' ? el.layout?.h : el.layout?.v;
	return el.type === 'svg' && !! axis && axis.mode !== 'stretch' && axis.size === undefined;
}

/** The patch of an SVG for a side left empty: its layout is made again with no size on that axis (what it is then is the viewBox's, which the layout does not say). */
export function clearSizePatch( el: Sized, side: 'w' | 'h', page: PageBox ): Partial< Sized > {
	const key = side === 'w' ? 'h' : 'v';
	const base = layoutOf( el, page );
	if ( base[ key ].mode === 'stretch' ) {
		return {};
	}
	const { size: _said, ...rest } = base[ key ];
	return { layout: { ...base, [ key ]: rest } };
}

/** A patch that makes a layout again: the sides that said no size keep saying none (only the one that was typed gets its size). */
export function keepEmpty( el: Sized & { type?: string }, patch: Partial< Sized >, typed: 'w' | 'h' ): Partial< Sized > {
	if ( ! patch.layout ) {
		return patch;
	}
	const layout = { ...patch.layout, h: { ...patch.layout.h }, v: { ...patch.layout.v } };
	( [ [ 'w', 'h' ], [ 'h', 'v' ] ] as const ).forEach( ( [ side, key ] ) => {
		if ( side !== typed && sizeEmpty( el, side ) ) {
			delete layout[ key ].size;
		}
	} );
	return { ...patch, layout };
}

