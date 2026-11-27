/**
 * The zoom of the canvas (docs/decisions/0049): a level, or one of three fits of the page in the visible area: its width (the page
 * may scroll vertically), its height, or the whole page. Pure, so the scale is tested without the browser (tests/js).
 */
export const ZOOM_MIN = 0.1;
export const ZOOM_MAX = 8;
/** A fit never enlarges the page more than this. */
export const FIT_MAX = 3;

export type FitMode = 'horizontal' | 'vertical' | 'view';
export type ZoomMode = FitMode | number;

export const isFit = ( mode: ZoomMode ): mode is FitMode => typeof mode === 'string';

/**
 * The scale that makes the page (with its bleed) fit the area, per side leaving `padding` free; null when there is no area to fit in
 * (the stage is not shown yet).
 */
export function fitScale( mode: FitMode, page: { w: number; h: number; bleed?: number }, area: { w: number; h: number }, padding = 0 ): number | null {
	const bleed = page.bleed || 0;
	const w = ( area.w - padding * 2 ) / ( page.w + bleed * 2 );
	const h = ( area.h - padding * 2 ) / ( page.h + bleed * 2 );
	if ( ! ( area.w > 0 && area.h > 0 ) || ! ( w > 0 ) || ! ( h > 0 ) ) {
		return null;
	}
	const scale = mode === 'horizontal' ? w : mode === 'vertical' ? h : Math.min( w, h );
	return Math.max( ZOOM_MIN, Math.min( FIT_MAX, scale ) );
}
