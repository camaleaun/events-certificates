/**
 * The fonts a design can use: the two the plugin ships (IBM Plex Sans and Instrument Serif) and the ones installed in WordPress (the Font
 * Library), listed by includes/fonts.php in window.evcertEditor.families. A design that names a family that is neither (a template that asks
 * for Inter on a site without it) shows, and prints, in the shipped one that stands in for it.
 */

export type FontFamily = { value: string; label: string; pdf: boolean; fallback: string; weights?: number[] };

/** The font a new text element has. */
export const DEFAULT_FONT = 'CertSans';

const SHIPPED: FontFamily[] = [
	{ value: 'CertSans', label: 'IBM Plex Sans', pdf: true, fallback: 'CertSans', weights: [ 400, 600 ] },
	{ value: 'CertSerif', label: 'Instrument Serif', pdf: true, fallback: 'CertSerif', weights: [ 400 ] },
];

/** The families to offer (the page's own list; without it, as in the tests, the two the plugin ships). */
export function fontFamilies(): FontFamily[] {
	const given = typeof window !== 'undefined' ? ( window as unknown as { evcertEditor?: { families?: FontFamily[] } } ).evcertEditor?.families : undefined;
	return given && given.length ? given : SHIPPED;
}

/** The shipped family that stands in for a font that is not there: the installed one's kind, Lora's serif, else the sans. */
export function fallbackOf( font: string ): string {
	const own = fontFamilies().find( ( f ) => f.value === font );
	if ( own ) {
		return own.fallback;
	}
	return font === 'Lora' ? 'CertSerif' : 'CertSans';
}

/** What a family is called in the editor. */
export function fontLabel( font: string ): string {
	return fontFamilies().find( ( f ) => f.value === font )?.label ?? SHIPPED.find( ( f ) => f.value === font )?.label ?? font;
}

/** The CSS font-family of a text: the font it names and, behind it, the shipped one that stands in for it (as the PDF does). */
export function fontStack( font: string ): string {
	const name = font || DEFAULT_FONT;
	const stand = fallbackOf( name );
	return name === stand ? `'${ name }'` : `'${ name }', '${ stand }'`;
}

/** The weight a text is drawn with: the one asked for when the font (or the one standing in for it) has it, else the one CSS would pick among the ones it has, as the PDF does. */
export function weightFor( font: string, weight: number ): number {
	const use = fontFamilies().find( ( f ) => f.value === ( font || DEFAULT_FONT ) ) ? font || DEFAULT_FONT : fallbackOf( font );
	const have = fontFamilies().find( ( f ) => f.value === use )?.weights ?? [];
	if ( ! have.length || have.includes( weight ) ) {
		return weight;
	}
	// CSS's own order: 400 to 500 look up to 500 first, then lighter, then heavier; lighter than 400 look lighter first; heavier than 500
	// look heavier first. So 500 between a 400 and a 600 is the 400.
	const sorted = [ ...have ].sort( ( x, y ) => x - y );
	const lighter = sorted.filter( ( w ) => w < weight ).reverse();
	const heavier = sorted.filter( ( w ) => w > weight );
	if ( weight >= 400 && weight <= 500 ) {
		return [ ...heavier.filter( ( w ) => w <= 500 ), ...lighter, ...heavier.filter( ( w ) => w > 500 ) ][ 0 ];
	}
	return ( weight < 400 ? [ ...lighter, ...heavier ] : [ ...heavier, ...lighter ] )[ 0 ];
}
