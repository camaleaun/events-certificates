/**
 * The draft of a drawing in the SVG dialogs, and what is decided about it with no browser (tests/js): the "info" icon a drawing starts
 * from and is given back when its last path is removed, what a pasted SVG replaces, and the ink of the "current color" swatch.
 */
import type { SvgPath } from '@camaleaun/draw';

// `w` and `h` are the size the drawing prefers to be shown at (a logo or a signature) or, for an element of a template, its own size
// on the page in points; empty, a drawing is shown at its viewBox's.
export type SvgDraft = { title: string; vw: number; vh: number; w?: number; h?: number; paths: SvgPath[] };

/** The WordPress icon library's "info" icon: one path with no fill-rule (the winding does the hole). */
export const INFO_ICON = 'M5.5 12a6.5 6.5 0 1 1 13 0 6.5 6.5 0 0 1-13 0ZM12 4a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm.75 4v1.5h-1.5V8h1.5Zm0 8v-5h-1.5v5h1.5Z';
export const INFO_VIEWBOX = 24;
/** The size a new drawing is shown at: a square of 96 x 96. */
export const INFO_SIZE = 96;

/** The info icon as the SVG code the SVG code box shows as its example. */
export const infoSvg = (): string => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${ INFO_VIEWBOX } ${ INFO_VIEWBOX }">\n  <path d="${ INFO_ICON }"/>\n</svg>`;

/** What a new drawing is: the info icon, 24 x 24, shown at 96 x 96. Always a new object. */
export const defaultDrawing = (): SvgDraft => ( { title: '', vw: INFO_VIEWBOX, vh: INFO_VIEWBOX, w: INFO_SIZE, h: INFO_SIZE, paths: [ { d: INFO_ICON } ] } );

/**
 * What an element of a template takes when its last path is removed (in its sidebar, and in the draft of its dialog): the info icon on a
 * 24 x 24 viewBox. Its title, color, size on the page and layout are not in it, so they stay; the old markup and the one fill for all
 * are cleared, as for any new paths. Always a new object.
 */
export const emptiedElement = () => ( { vw: INFO_VIEWBOX, vh: INFO_VIEWBOX, paths: [ { d: INFO_ICON } ], markup: undefined, fill: undefined } );

/**
 * The draft once its last path is removed: the info icon again, so a drawing is never empty. A drawing goes back to what a new one is
 * (its title kept); an element of a template keeps its own size on the page, so it does not jump.
 */
export function refilled( draft: SvgDraft, element?: boolean ): SvgDraft {
	if ( element ) {
		const { vw, vh, paths } = emptiedElement();
		return { ...draft, vw, vh, paths };
	}
	return { ...defaultDrawing(), title: draft.title };
}

/** A pasted SVG in place of the draft: for an element of a template it keeps the title and the size on the page it already has. */
export const taken = ( pasted: SvgDraft, base: SvgDraft, element?: boolean ): SvgDraft => ( element ? { ...pasted, title: base.title, w: base.w, h: base.h } : pasted );

/**
 * The color of the stroke that goes over a swatch of this color (white over a dark one, near black over a light one), as the relative
 * luminance says. Reads #rgb, #rrggbb, #rrggbbaa and rgb()/rgba(); a color it cannot read is taken as dark.
 */
export function inkOn( color: string ): string {
	const c = String( color || '' ).trim().toLowerCase();
	let rgb: number[] | null = null;
	const hex = /^#([0-9a-f]{3,8})$/.exec( c );
	if ( hex ) {
		const h = hex[ 1 ];
		if ( h.length === 3 || h.length === 4 ) {
			rgb = [ 0, 1, 2 ].map( ( i ) => parseInt( h[ i ] + h[ i ], 16 ) );
		} else if ( h.length === 6 || h.length === 8 ) {
			rgb = [ 0, 2, 4 ].map( ( i ) => parseInt( h.slice( i, i + 2 ), 16 ) );
		}
	}
	const fn = /^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)/.exec( c );
	if ( ! rgb && fn ) {
		rgb = [ Number( fn[ 1 ] ), Number( fn[ 2 ] ), Number( fn[ 3 ] ) ];
	}
	if ( ! rgb || rgb.some( ( n ) => Number.isNaN( n ) ) ) {
		return '#fff';
	}
	const [ r, g, b ] = rgb.map( ( n ) => {
		const s = n / 255;
		return s <= 0.03928 ? s / 12.92 : Math.pow( ( s + 0.055 ) / 1.055, 2.4 );
	} );
	return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.4 ? '#1e1e1e' : '#fff';
}
