/**
 * What the picture choosers share: a picture as a small PNG data URI, the color the drawn paths take, and a drawing read from its
 * JSON.
 */
import { __ } from '@wordpress/i18n';
import type { Design, SvgPath } from '../editor/model';

// Any picture (PNG, JPEG, WebP or SVG file) as a PNG data URI of at most 600 px, small enough to go with the form.
export function toPng( source: string ): Promise< string > {
	return new Promise( ( resolve, reject ) => {
		const image = new Image();
		image.onload = () => {
			const scale = Math.min( 1, 600 / Math.max( image.naturalWidth || 600, image.naturalHeight || 600 ) );
			const canvas = document.createElement( 'canvas' );
			canvas.width = Math.max( 1, Math.round( ( image.naturalWidth || 600 ) * scale ) );
			canvas.height = Math.max( 1, Math.round( ( image.naturalHeight || 200 ) * scale ) );
			canvas.getContext( '2d' )?.drawImage( image, 0, 0, canvas.width, canvas.height );
			resolve( canvas.toDataURL( 'image/png' ) );
		};
		image.onerror = () => reject( new Error( 'image' ) );
		image.src = source;
	} );
}

// The color of the paths: the text color, like the signature of the design editor's SVG component.
export const PATH_DESIGN = { page: { w: 120, h: 44, bg: 'transparent', safe: 0, bleed: 0 }, colors: { text: { name: __( 'Text', 'events-certificates' ), light: '#1e1e1e', dark: '#f0f0f0' } }, elements: [] } as unknown as Design;
export type Vector = { vw: number; vh: number; w?: number; h?: number; paths: SvgPath[] };

// The JSON of a drawing as it is kept: the viewBox, the size it prefers when one is set, and its non-empty paths.
export const drawingJson = ( draft: { vw: number; vh: number; w?: number; h?: number }, paths: SvgPath[] ) =>
	JSON.stringify( { vw: draft.vw, vh: draft.vh, ...( draft.w && draft.h ? { w: draft.w, h: draft.h } : {} ), paths } );
export const readVector = ( json: string ): Vector => {
	try {
		const parsed = JSON.parse( json );
		return parsed && parsed.vw > 0 && parsed.vh > 0 && Array.isArray( parsed.paths ) ? parsed : { vw: 120, vh: 44, paths: [] };
	} catch ( e ) {
		return { vw: 120, vh: 44, paths: [] };
	}
};

