/**
 * The Basic design (templates/basic-design.json) in the admin's language: its
 * texts, element names and color names go through translation when a design
 * starts from it. And the required parts every design keeps: when a design
 * (an import, an older save) lacks one, it is added from the Base, placed
 * under the authenticity QR code and the code.
 */
import { __ } from '@wordpress/i18n';
import raw from '../../templates/basic-design.json';
import { box, fromStored, migrate, REQUIRED_SLOTS, resolve, type Design, type Element } from './model';

/** English strings of the Base design and their translations. */
function strings(): Record< string, string > {
	return {
		// Texts.
		'took part in ': __( 'took part in ', 'events-certificates' ),
		', with a workload of ': __( ', with a workload of ', 'events-certificates' ),
		// Element names.
		Authenticity: __( 'Authenticity', 'events-certificates' ),
		Code: __( 'Code', 'events-certificates' ),
		'Background frame': __( 'Background frame', 'events-certificates' ),
		'Background rings': __( 'Background rings', 'events-certificates' ),
		'Community logo': __( 'Community logo', 'events-certificates' ),
		Date: __( 'Date', 'events-certificates' ),
		'Date label': __( 'Date label', 'events-certificates' ),
		'Date line': __( 'Date line', 'events-certificates' ),
		'Event title': __( 'Event title', 'events-certificates' ),
		Hours: __( 'Hours', 'events-certificates' ),
		'Issued on': __( 'Issued on', 'events-certificates' ),
		Intro: __( 'Intro', 'events-certificates' ),
		Name: __( 'Name', 'events-certificates' ),
		'Name band': __( 'Name band', 'events-certificates' ),
		'Name line': __( 'Name line', 'events-certificates' ),
		Signature: __( 'Signature', 'events-certificates' ),
		Signer: __( 'Signer', 'events-certificates' ),
		'Signature line': __( 'Signature line', 'events-certificates' ),
		'Signer name': __( 'Signer name', 'events-certificates' ),
		'Signer role': __( 'Signer role', 'events-certificates' ),
		Text: __( 'Text', 'events-certificates' ),
		'Took part': __( 'Took part', 'events-certificates' ),
		Verification: __( 'Verification', 'events-certificates' ),
		Title: __( 'Title', 'events-certificates' ),
		'Verification hint': __( 'Verification hint', 'events-certificates' ),
		// Color names.
		Primary: __( 'Primary', 'events-certificates' ),
		'Soft text': __( 'Soft text', 'events-certificates' ),
		Brand: __( 'Brand', 'events-certificates' ),
		Band: __( 'Band', 'events-certificates' ),
		Lines: __( 'Lines', 'events-certificates' ),
		Background: __( 'Background', 'events-certificates' ),
		'On primary': __( 'On primary', 'events-certificates' ),
	};
}

/** A name the Base design ships (a color's, an element's) in the admin's language; any other name stays as it is. */
export function stockName( name: string ): string {
	const t = strings();
	return t[ name ] !== undefined ? t[ name ] : name;
}

/** The Base design, translated. */
export function baseDesign(): Design {
	const t = strings();
	const tr = ( s?: string ) => ( s !== undefined && t[ s ] !== undefined ? t[ s ] : s );
	// The site's Base design (the evcert_base_design filter) when the page gives it; the one that ships otherwise.
	const served = typeof window !== 'undefined' ? window.evcertEditor?.base : undefined;
	const design = fromStored( JSON.parse( JSON.stringify( served && served.elements ? served : raw ) ) as Design );
	design.elements = design.elements.map( ( el ) => {
		const next = { ...el, name: tr( el.name ) } as Element;
		if ( next.type === 'text' && next.runs ) {
			next.runs = next.runs.map( ( r ) => ( 'text' in r ? { ...r, text: tr( r.text ) as string } : r ) );
		}
		return next;
	} );
	design.colors = Object.fromEntries( Object.entries( design.colors ).map( ( [ k, v ] ) => [ k, { ...v, name: tr( v.name ) as string } ] ) );
	// The theme's own swatches are named like the colors they came from; Base and Contrast have no stored name (their labels are translated when shown).
	design.palette = ( design.palette || [] ).map( ( s ) => ( s.name ? { ...s, name: tr( s.name ) as string } : s ) );
	return design;
}

/** An empty design: the page, no colors, no theme and nothing on it (Designs\blank() on the server). */
export function blankDesign(): Design {
	const base = baseDesign();
	const { back, sample, ...rest } = base;
	return migrate( {
		...rest,
		// Nothing on it: no safe margin to keep (and none drawn).
		page: { ...rest.page, safe: 0 },
		// No colors and no theme: nothing is fixed, so an empty design has none (the + of each group brings the first ones back; Designs\blank() on the server).
		colors: {},
		palette: [],
		elements: [],
	} as Design );
}

/** Slots every design keeps: the name. The verification parts are the designer's choice. */
export const KEPT_SLOTS = Array.from( new Set( REQUIRED_SLOTS ) );

/**
 * Adds the required parts a design lacks, from the Base (today only the name; a part that follows the QR code is placed under it).
 */
export function withRequired( design: Design ): Design {
	const missing = KEPT_SLOTS.filter( ( slot ) => ! design.elements.some( ( el ) => el.slot === slot ) );
	if ( ! missing.length ) {
		return design;
	}
	const from = baseDesign();
	const elements = [ ...design.elements ];
	const find = ( slot: string ) => elements.find( ( el ) => el.slot === slot );
	for ( const slot of missing ) {
		const source = from.elements.find( ( el ) => el.slot === slot );
		if ( ! source ) {
			continue;
		}
		const resolved = box( resolve( source, from.page ) );
		const above = slot === 'verify' ? find( 'code' ) || find( 'authenticity' ) : slot === 'code' ? find( 'authenticity' ) : null;
		let el: Element;
		if ( above ) {
			const a = box( resolve( above, design.page ) );
			const qr = find( 'authenticity' );
			const cx = qr ? box( resolve( qr, design.page ) ).x + box( resolve( qr, design.page ) ).w / 2 : a.x + a.w / 2;
			const { layout, ...rest } = source as Element & { layout?: unknown };
			el = { ...rest, x: Math.round( ( cx - resolved.w / 2 ) * 100 ) / 100, y: Math.round( ( a.y + a.h + 2 ) * 100 ) / 100, w: resolved.w, h: resolved.h } as Element;
		} else {
			el = source;
		}
		if ( elements.some( ( e ) => e.id === el.id ) ) {
			el = { ...el, id: `${ el.id }-${ Date.now().toString( 36 ) }` };
		}
		elements.push( el );
	}
	return { ...design, elements };
}
