/**
 * How a design is kept as blocks (docs/decisions/0047): a block of the plugin for every shape (`events-certificates/certificate-text`,
 * `-rect`…), and an SVG is one block with its paths in the `paths` attribute. The same as block_options() in includes/designs.php; these are
 * the options of the camaleaun/draw package's toMarkup() and fromMarkup().
 */
import type { Options } from '@camaleaun/draw';
import { FIELD_KEYS } from './fieldKeys';

const SHAPES = [ 'text', 'rect', 'ellipse', 'path', 'line', 'image', 'svg', 'group', 'signature' ];

export const BLOCK_NAMES: Record< string, string > = {
	...Object.fromEntries( SHAPES.map( ( type ) => [ type, `events-certificates/certificate-${ type }` ] ) ),
	qr: 'events-certificates/certificate-qr',
	verification: 'events-certificates/certificate-verification',
};

export const FALLBACK = 'events-certificates/certificate-element';

// The names of the certificate's types that the package does not know: not written, as the type says them.
export const BLOCK_OPTIONS: Options = { fields: FIELD_KEYS, blocks: BLOCK_NAMES, fallback: FALLBACK, paths: 'attribute', names: { signature: 'Signature', 'image:logo': 'Community logo' } };

type Loose = Record< string, unknown >;

/**
 * A reference to a color of the design as the file says it, the way a block says a preset: "$background" is "var:preset|color|background"
 * (and the other way). Any value of the design, however deep. The same as color_refs() in includes/designs.php.
 */
function colorRefs( value: unknown, file: boolean ): unknown {
	if ( Array.isArray( value ) ) {
		return value.map( ( item ) => colorRefs( item, file ) );
	}
	if ( value && typeof value === 'object' ) {
		return Object.fromEntries( Object.entries( value ).map( ( [ key, item ] ) => [ key, colorRefs( item, file ) ] ) );
	}
	if ( typeof value !== 'string' ) {
		return value;
	}
	if ( file ) {
		const m = /^\$([A-Za-z][A-Za-z0-9-]*)$/.exec( value );
		return m ? `var:preset|color|${ m[ 1 ] }` : value;
	}
	const m = /^var:preset\|color\|(?:element\|)?([A-Za-z][A-Za-z0-9-]*)$/.exec( value );
	return m ? `$${ m[ 1 ] }` : value;
}

/**
 * The colors of a design as the file says them, like a theme.json: `color.palette` the Theme (slug, color, name) and `color.elements` the colors of the
 * Elements (slug, color, name; a color with a Dark value is light-dark(Light, Dark)). The same as colors_to_file() in includes/designs.php.
 */
function colorsToFile( design: Loose ): Loose {
	const colors = ( design.colors || {} ) as Record< string, { name?: string; light?: string; dark?: string } >;
	// A color with a Dark value is written as CSS says it, light-dark(Light, Dark); either may point at a color of the Theme (var:preset|color|slug).
	const elements = Object.entries( colors ).map( ( [ slug, c ] ) => ( { slug, color: c.dark ? `light-dark(${ c.light || '' },${ c.dark })` : c.light || '', ...( c.name ? { name: c.name } : {} ) } ) );
	const file: Loose = {};
	if ( Array.isArray( design.palette ) ) {
		file.palette = ( design.palette as Array< { slug?: string; color?: string; name?: string } > ).map( ( s ) => ( { ...( s.slug ? { slug: s.slug } : {} ), ...( s.color ? { color: s.color } : {} ), ...( s.name ? { name: s.name } : {} ) } ) );
	}
	file.elements = elements;
	const out: Loose = {};
	for ( const [ key, value ] of Object.entries( design ) ) {
		if ( key === 'colors' || key === 'palette' ) {
			continue;
		}
		out[ key ] = value;
		if ( key === 'page' ) {
			out.color = file;
		}
	}
	return 'color' in out ? out : { ...out, color: file };
}

/** `light-dark(Light, Dark)` as its two values (a pointer to a color of the Theme is read back as "$slug"); any other value is the Light one. */
function lightDark( value: string ): [ string, string ] {
	const text = value.trim();
	const m = /^light-dark\((.*)\)$/s.exec( text );
	if ( ! m ) {
		return [ colorRefs( text, false ) as string, '' ];
	}
	const parts: string[] = [];
	let depth = 0;
	let start = 0;
	for ( let i = 0; i < m[ 1 ].length; i++ ) {
		const c = m[ 1 ][ i ];
		depth += c === '(' ? 1 : c === ')' ? -1 : 0;
		if ( c === ',' && depth === 0 ) {
			parts.push( m[ 1 ].slice( start, i ) );
			start = i + 1;
		}
	}
	parts.push( m[ 1 ].slice( start ) );
	return [ colorRefs( ( parts[ 0 ] || '' ).trim(), false ) as string, colorRefs( ( parts[ 1 ] || '' ).trim(), false ) as string ];
}

/** The reverse of colorsToFile(): `colors` (a map with Light and Dark) and `palette`. */
function colorsFromFile( design: Loose ): Loose {
	const file = design.color as { palette?: unknown[]; elements?: Array< { slug?: string; name?: string; color?: string; dark?: string } > } | undefined;
	if ( ! file || typeof file !== 'object' ) {
		return design;
	}
	const colors: Loose = {};
	for ( const entry of file.elements || [] ) {
		if ( typeof entry?.slug === 'string' ) {
			const [ light, dark ] = lightDark( entry.color || '' );
			colors[ entry.slug ] = { name: entry.name || '', light, ...( dark || entry.dark ? { dark: dark || entry.dark } : {} ) };
		}
	}
	const out: Loose = {};
	for ( const [ key, value ] of Object.entries( design ) ) {
		if ( key === 'color' ) {
			out.colors = colors;
			if ( Array.isArray( file.palette ) ) {
				out.palette = file.palette;
			}
			continue;
		}
		out[ key ] = value;
	}
	return out;
}

/**
 * A design for its file: the colors like a theme.json and a reference to a color as a preset. The elements are written by the package (`Names`, in
 * `toMarkup()`): their names in full, no id that only counts, no default name. The same as to_file() in includes/designs.php.
 */
export function toFile< T >( design: T ): T {
	return colorsToFile( colorRefs( design, true ) as Loose ) as T;
}

/** The reverse of toFile(): the model's colors and palette, and the references to a color as "$slug". */
export function fromFile< T >( design: T ): T {
	return colorsFromFile( colorRefs( design, false ) as Loose ) as T;
}
