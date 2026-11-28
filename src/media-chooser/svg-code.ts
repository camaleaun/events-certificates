/**
 * SVG as text, for the media modal's SVG tab and the toast that offers a copied SVG: what is a usable SVG, why a text is
 * not one, and whether the clipboard holds something new to offer. Pure, so it is tested without the browser (tests/js).
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { PICTURE_PATH_DATA, sanitizeSvg, SVG_LIMITS, svgCodeAllowed } from '../editor/model';
import { infoSvg, type SvgDraft } from '../editor/svg-draft';

/** SVG code as a drawing program exports it, without the XML prolog and the comments before the <svg> tag. */
export const bareSvg = ( text: string ): string => text.replace( /<\?xml[^>]*\?>/gi, '' ).replace( /<!--[\s\S]*?-->/g, '' ).trim();

/** The textarea's placeholder: the WordPress icon library's "info" icon as a clean SVG of one path (src/editor/svg-draft.ts). */
export const SVG_EXAMPLE = infoSvg();

export type SvgProblem = 'empty' | 'not-svg' | 'forbidden' | 'too-big' | 'no-paths';

/** What is wrong with a text that is not a usable SVG. */
export function problemText( problem: SvgProblem ): string {
	switch ( problem ) {
		case 'not-svg':
			return __( 'That is not SVG code: it should start with <svg>.', 'events-certificates' );
		case 'forbidden':
			return __( 'That SVG has parts that are not allowed (a script, an event handler, a DOCTYPE or an entity).', 'events-certificates' );
		case 'too-big':
			return __( 'That SVG is too big.', 'events-certificates' );
		case 'no-paths':
			return __( 'That SVG has no paths: only its <path> elements are read, so shapes and text must be converted to paths first.', 'events-certificates' );
		default:
			return '';
	}
}

/** What to say when a drawing lost paths (too long for the place, or not path data). */
export function skippedText( skipped: number ): string {
	return sprintf( /* translators: %d: how many paths were left out of an SVG. */ _n( '%d path was left out: it is too long, or not made of path commands.', '%d paths were left out: they are too long, or not made of path commands.', skipped, 'events-certificates' ), skipped );
}

/**
 * The drawing in a text, or why it is not one. `pathData` is the most characters of a path in the place the drawing is for: an element of a template takes
 * what the draw package says (100000); a picture (a signature, a logo) takes PICTURE_PATH_DATA. `skipped` is how many paths were left out.
 */
export function parseSvgText( text: string, title = '', pathData?: number ): { draft: SvgDraft; skipped?: number } | { problem: SvgProblem } {
	const code = bareSvg( text );
	if ( ! code ) {
		return { problem: 'empty' };
	}
	if ( ! /^<svg[\s>]/i.test( code ) ) {
		return { problem: 'not-svg' };
	}
	if ( code.length > SVG_LIMITS.bytes ) {
		return { problem: 'too-big' };
	}
	if ( ! svgCodeAllowed( code ) ) {
		return { problem: 'forbidden' };
	}
	const svg = sanitizeSvg( code, pathData ? { pathData } : {} );
	if ( ! svg ) {
		return { problem: 'no-paths' };
	}
	const { skipped, ...drawing } = svg;
	return { draft: { title, ...drawing }, ...( skipped ? { skipped } : {} ) };
}

/**
 * The SVG to offer from what the clipboard holds: its code when it is a usable SVG that was not already used or dismissed, else
 * null. New content is offered; the same content is not offered twice.
 *
 * @param clip    What the clipboard holds (null when it could not be read).
 * @param handled The last code used or dismissed.
 */
export function offerFor( clip: string | null, handled: string ): string | null {
	const code = clip ? bareSvg( clip ) : '';
	// The media modal is for pictures (a signature, a logo): what the server keeps of those.
	return code && code !== handled && 'draft' in parseSvgText( code, '', PICTURE_PATH_DATA ) ? code : null;
}
