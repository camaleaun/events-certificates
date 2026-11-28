/**
 * The design model: elements in pt on a fixed page, colors as variables with
 * Light and Dark values. The same JSON is drawn by the editor (SVG + HTML
 * text in foreignObject) and by the PDF renderer (dompdf reads the same
 * positioned HTML).
 */
import {
	type Axis, type AxisMode, type Box, type CornerShape, type Layout, type PageBox, type SvgPath,
	SVG_LIMITS, UNITS, isFill, isLen, isPathData, lockedRatio, lockedSide, layoutFrom, pageSize, parseLen, placed as placedAt, rectPath, refBox, resolve as resolveAt, sanitizeSvg, svgCodeAllowed,
} from '@camaleaun/draw';
import { INFO_ICON } from './svg-draft';

export {
	SVG_LIMITS, UNITS, isFill, isLen, isPathData, lockedRatio, lockedSide, layoutFrom, parseLen, rectPath, refBox, sanitizeSvg, svgCodeAllowed,
};
export type { Axis, AxisMode, Box, CornerShape, Layout, SvgPath };

/** An element with its anchors resolved to x, y, w, h in pt (elements without anchors are returned as they are). */
export const resolve = < T extends Element >( el: T, page: Design[ 'page' ] ): T => resolveAt( el as never, page as PageBox ) as T;

/** An element placed at a box: absolute elements take the numbers, anchored ones update their anchors. */
export const placed = < T extends Element >( stored: T, b: Box, page: Design[ 'page' ] ): T => placedAt( stored as never, b, page as PageBox ) as T;
import { __ } from '@wordpress/i18n';

export type Mode = 'light' | 'dark';

// dark is optional: without it the light value is used in Dark mode.
export type ColorVar = { name: string; light: string; dark?: string };

/**
 * A color of the theme (design.palette). Picking one for an element copies its value; the element keeps its own. None is fixed: Base and
 * Contrast, which a new template starts with, may be renamed and removed like any other (a swatch with no stored name shows Base or
 * Contrast in the site's language).
 */
export type Swatch = { slug: string; name?: string; color: string };

/** What a new template starts with in its theme (the names are translated when shown). */
export function starterSwatches(): Swatch[] {
	return [ { slug: 'base', color: '#ffffff' }, { slug: 'contrast', color: '#000000' } ];
}

/** The label of a swatch: the name it was given, else Base and Contrast in the site's language, else its color. The only place they are translated. */
export function swatchName( s: Swatch ): string {
	if ( s.name ) {
		return s.name;
	}
	if ( s.slug === 'base' ) {
		return __( 'Base', 'events-certificates' );
	}
	if ( s.slug === 'contrast' ) {
		return __( 'Contrast', 'events-certificates' );
	}
	return s.color;
}

/** The slug after the highest "custom-N" in use (in the Theme; and no slug of the Elements: one name is never in both). */
export function nextSwatchSlug( palette: Array< { slug?: string } >, colors: Record< string, unknown > = {} ): string {
	const used = palette.map( ( s ) => Number( /^custom-(\d+)$/.exec( s.slug || '' )?.[ 1 ] ) || 0 );
	let n = Math.max( 0, ...used ) + 1;
	while ( `custom-${ n }` in colors ) {
		n++;
	}
	return `custom-${ n }`;
}

export type Base = {
	id: string;
	// In a row, a stack or a grid: shares the room that is left with the others that grow (0, or none, keeps its own size).
	grow?: number;
	name?: string;
	slot?: string;
	x: number;
	y: number;
	w: number;
	h: number;
	opacity?: number;
	rotation?: number;
	locked?: boolean;
	hidden?: boolean;
	layout?: Layout;
	// Keeps width ÷ height: the size along one axis follows the other (images use their aspect ratio setting).
	lock?: number;
	// Per-device position, size and visibility for the web views (the print page uses the element's own).
	views?: Partial< Record< Screen, ElementView > >;
};

export type TextEl = Base & {
	type: 'text';
	text?: string;
	runs?: Run[];
	/** A full list of runs for a language ("pt_BR"): the order of the fields changes between languages. */
	variants?: Record< string, Run[] >;
	font: string;
	weight: number;
	size: number;
	color: string;
	align?: 'left' | 'center' | 'right';
	// A number (a multiplier; points) or a length with a unit (camaleaun/draw's typography.ts): lh, %, px, pt, mm, cm, in.
	lh?: number | string;
	ls?: number | string;
	upper?: boolean;
	fit?: boolean;
	min?: number;
};
export type RectEl = Base & { type: 'rect'; fill?: string; stroke?: string; strokeWidth?: number; radius?: Array< number | string >; corner?: CornerShape };
/** One path of an SVG element: its outline (path data) and fill (a color or a $variable; none = the current color). */
/** An SVG drawing kept as fields, never as markup: a viewBox size and a list of filled paths. */
/** title: names the drawing (the SVG's <title> and the image's alt text); empty for decoration. */
export type SvgEl = Base & { type: 'svg'; title?: string; vw: number; vh: number; paths: SvgPath[]; color?: string; markup?: string; fill?: string };
/**
 * A box like a rectangle that holds other elements. What is in it (`children`) is placed from its top-left corner, and its anchors (`ref: 'parent'`) are
 * to its size; it clips them to its box unless `clip` is false. It holds no authenticity code, verification or signature.
 */
export type GroupEl = Base & {
	type: 'group';
	fill?: string;
	stroke?: string;
	strokeWidth?: number;
	radius?: Array< number | string >;
	corner?: CornerShape;
	// Whether it cuts what goes out of its box (it shows it by default).
	clip?: boolean;
	// How it places what is in it, as the core's Group variations: none is the free Group (each by its own place), or a row, a stack or a grid.
	arrange?: 'row' | 'stack' | 'grid';
	gap?: number;
	padding?: number;
	justify?: 'start' | 'center' | 'end' | 'between';
	align?: 'start' | 'center' | 'end' | 'stretch';
	columns?: number;
	children: Element[];
};
export type EllipseEl = Base & { type: 'ellipse'; fill?: string; stroke?: string; strokeWidth?: number };
export type PathEl = Base & { type: 'path'; d: string; vw: number; vh: number; fill?: string; stroke?: string; strokeWidth?: number };
export type LineEl = Omit< Base, 'x' | 'y' | 'w' | 'h' > & {
	type: 'line';
	x1: number;
	y1: number;
	x2: number;
	y2: number;
	stroke?: string;
	strokeWidth?: number;
	dash?: 'solid' | 'dashed' | 'dotted';
	// Bounding box, derived (kept for a uniform API).
	x?: number;
	y?: number;
	w?: number;
	h?: number;
};
export type ImageEl = Base & { type: 'image'; src: string; fit?: 'contain' | 'cover'; alt?: string; attachment?: number; ratio?: string };
export type QrEl = Base & { type: 'qr' };
/** The place of the signer's signature (docs/decisions/0048): it has no drawing of its own, only the color of the ink. */
export type SignatureEl = Base & { type: 'signature'; color?: string };
export type Align = 'left' | 'center' | 'right';
/** The QR code, the code and the hint as one element (see ./verification.ts). */
export type VerificationEl = Base & {
	type: 'verification';
	arrange?: 'stack' | 'row';
	align?: Align;
	qr?: number;
	gap?: number;
	font?: string;
	code?: { size?: number; weight?: number; color?: string };
	hint?: { size?: number; weight?: number; color?: string; lh?: number };
};

export type Element = TextEl | RectEl | EllipseEl | PathEl | LineEl | ImageEl | QrEl | SignatureEl | SvgEl | VerificationEl | GroupEl;

export type Design = {
	// bg: one color for both modes, or one per mode (e.g. none in Light, a dark color in Dark); '' = the page's own color.
	page: { w: number; h: number; bg: string | { light?: string; dark?: string }; safe: number; bleed: number; safeUnit?: LengthUnit; bleedUnit?: LengthUnit };
	colors: Record< string, ColorVar >;
	// The colors an element's picker offers as a shortcut (the "Theme" list); absent in older designs, made from the colors when read.
	palette?: Swatch[];
	elements: Element[];
	// Preview values for the fields, only for the editor (certificates use real data).
	sample?: Record< string, string >;
	// The QR code, code and hint on the first page: always, or only without the declaration page (which then carries them).
	verifyFirstPage?: 'always' | 'without-declaration';
	// The declaration page (the second sheet), designed like the first: same page size, colors and fonts.
	back?: { elements: Element[] };
};

/** Which sheet of the template is being designed. */
export type Side = 'front' | 'back';

/** The design as the editor shows one sheet: the back's elements in place of the front's. */
export function toSide( design: Design, side: Side ): Design {
	return side === 'back' ? { ...design, elements: design.back?.elements || [] } : design;
}

/** Edits made on a sheet, back into the design. */
export function fromSide( stored: Design, edited: Design, side: Side ): Design {
	return side === 'back' ? { ...edited, elements: stored.elements, back: { elements: edited.elements } } : edited;
}

/**
 * The declaration sheet a template starts with: its title, the statement,
 * the activities, the place and date, the signature and signer, the QR code,
 * code and hint (always there), and the self-declaration note; all fields,
 * so the words come from the translation and the data from the certificate.
 */
export function baseBack( design: Design ): Element[] {
	const { w, h } = design.page;
	const pad = Math.max( 36, ( design.page.safe || 0 ) + 24 );
	const muted = design.colors.muted ? '$muted' : '$text';
	const inner = w - 2 * pad;
	const text = ( id: string, name: string, y: number, height: number, runs: Run[], more: Record< string, unknown > = {} ): Element =>
		( { id, name, type: 'text', x: pad, y, w: inner, h: height, runs, font: 'CertSans', weight: 400, size: 9.5, color: '$text', align: 'left', lh: 1.38, ...more } as unknown as Element );
	const qr = 52;
	const bottom = h - pad;
	const signature = design.elements.find( ( el ) => el.type === 'signature' ) as SignatureEl | undefined;
	return [
		text( 'back-title', __( 'Declaration title', 'events-certificates' ), pad, 24, [ { field: 'declaration_title' } ], { font: 'CertSerif', weight: 400, size: 17, lh: 1.2 } ),
		text( 'back-statement', __( 'Statement', 'events-certificates' ), pad + 34, 40, [ { field: 'declaration_statement' } ] ),
		text( 'back-activities', __( 'Activities', 'events-certificates' ), pad + 84, bottom - 150 - ( pad + 84 ), [ { field: 'activities' } ], { size: 9, lh: 1.4 } ),
		text( 'back-place', __( 'Place and date', 'events-certificates' ), bottom - 132, 14, [ { field: 'location' }, { text: ', ' }, { field: 'issued_date', format: 'long' } ] ),
		// Always a signature place: it draws the signer's signature.
		{ ...( signature || { type: 'signature', color: '$text' } ), id: 'back-signature', name: __( 'Signature', 'events-certificates' ), x: pad, y: bottom - 124, w: 120, h: 40, rotation: 0, layout: undefined, views: undefined } as Element,
		text( 'back-signer', __( 'Signer name', 'events-certificates' ), bottom - 68, 13, [ { field: 'signer_name', b: true } ] ),
		text( 'back-signer-role', __( 'Signer role', 'events-certificates' ), bottom - 55, 13, [ { field: 'signer_role' } ], { color: muted } ),
		// The QR code, the code and the hint as one element: a row aligned right, on the right of the sheet.
		{ id: 'back-verification', name: __( 'Verification', 'events-certificates' ), type: 'verification', slot: 'verification', arrange: 'row', align: 'right', x: w - pad - 260, y: bottom - 30 - qr, w: 260, h: qr, qr, gap: 2, font: 'CertSans', code: { size: 8, weight: 600, color: '$text' }, hint: { size: 7, weight: 400, color: muted, lh: 1.35 } } as unknown as Element,
		text( 'back-note', __( 'Self-declaration note', 'events-certificates' ), bottom - 18, 18, [ { field: 'declaration_note' } ], { size: 6.5, color: muted, lh: 1.35 } ),
	];
}


/** The element's box (lines: the box around both ends). */
export function box( el: Element ): Box {
	if ( el.type === 'line' ) {
		const x = Math.min( el.x1, el.x2 );
		const y = Math.min( el.y1, el.y2 );
		return { x, y, w: Math.abs( el.x2 - el.x1 ), h: Math.abs( el.y2 - el.y1 ) };
	}
	return { x: el.x, y: el.y, w: el.w, h: el.h };
}

/** Moves an element by dx, dy. */
export function moved< T extends Element >( el: T, dx: number, dy: number ): T {
	if ( el.type === 'line' ) {
		return { ...el, x1: el.x1 + dx, y1: el.y1 + dy, x2: el.x2 + dx, y2: el.y2 + dy };
	}
	return { ...el, x: ( el as Base ).x + dx, y: ( el as Base ).y + dy };
}

/** A palette entry's value in a mode: Dark falls back to Light when it has no Dark value of its own. */
export function colorOf( v: ColorVar, mode: Mode ): string {
	return mode === 'dark' && v.dark ? v.dark : v.light;
}

/** What a new template starts with: a white page and black text, and the inverse in Dark (the names are translated when shown). */
export function starterColors(): Record< string, ColorVar > {
	return {
		background: { name: 'Background', light: '#ffffff', dark: '#000000' },
		text: { name: 'Text', light: '#000000', dark: '#ffffff' },
	};
}

/**
 * What a color the design does not have is: the colors the code uses on its own (black text, a transparent page, lines and the hint as the text,
 * the blue of a shape), as a design may have no colors at all. Any other name is '' (none). The same as builtin_color() in includes/render.php.
 */
export function builtinColor( key: string, mode: Mode ): string {
	const dark = mode === 'dark';
	switch ( key ) {
		case 'text':
		case 'muted':
		case 'line':
			return dark ? '#ffffff' : '#000000';
		case 'background':
			return 'transparent';
		case 'primary':
			return '#3858e9';
	}
	return '';
}

/** The colors an element can pick: the ones of the design that have a Light value (an empty one is not offered), in the order they are kept, as the one "Elements" group of the pickers. */
export function colorGroups( design: Design ): Array< { name: 'elements'; keys: string[] } > {
	const keys = Object.keys( design.colors ).filter( ( k ) => design.colors[ k ].light && design.colors[ k ].light !== 'none' );
	return keys.length ? [ { name: 'elements' as const, keys } ] : [];
}

/** Pure white in any of its spellings: the PDF leaves such a page unpainted. */
export function isWhite( value: string | undefined ): boolean {
	const v = ( value || '' ).toLowerCase().replace( /\s+/g, '' );
	return /^#(f{3,4}|f{6}|f{8})$/.test( v ) || v === 'white' || v === 'rgb(255,255,255)' || v === 'rgba(255,255,255,1)';
}

/** A fully transparent color in hex (#00000000, #0000): it paints nothing, as no color does. */
const CLEAR_HEX = /^#(?:[0-9a-f]{3}0|[0-9a-f]{6}00)$/i;

/**
 * A color value: "$name" reads the variable in the mode, "currentColor" is the document's text color ($text), anything else is literal; a fully
 * transparent color (#00000000) is 'none'. The same as color() in includes/render.php.
 */
export function color( design: Design, value: string | undefined, mode: Mode, depth = 0 ): string {
	const out = resolveColor( design, value, mode, depth );
	return CLEAR_HEX.test( out ) ? 'none' : out;
}

function resolveColor( design: Design, value: string | undefined, mode: Mode, depth: number ): string {
	if ( ! value ) {
		return 'none';
	}
	if ( value.toLowerCase() === 'currentcolor' ) {
		return depth < 5 ? color( design, '$text', mode, depth + 1 ) : 'none';
	}
	if ( value.startsWith( '$' ) ) {
		// One name for a color, in the Elements or in the Theme (a slug is never in both): the element's own Light and Dark values, the swatch's one
		// value, else the built-in one.
		const key = value.slice( 1 );
		const v = design.colors[ key ];
		if ( v ) {
			// A color may point at another (a color picked from the Theme is kept as "$slug"): followed a few steps, so a loop ends as none.
			const own = colorOf( v, mode );
			if ( own.startsWith( '$' ) ) {
				return depth < 5 ? color( design, own, mode, depth + 1 ) : 'none';
			}
			return own || 'none';
		}
		const swatch = ( design.palette || [] ).find( ( s ) => s.slug === key );
		return swatch ? swatch.color : builtinColor( key, mode ) || 'none';
	}
	return value;
}

/** What a color of the Elements is in a mode, with a pointer to another color followed (the circle and the swatch of a color show this). */
export function colorIn( design: Design, v: ColorVar, mode: Mode ): string {
	return color( design, colorOf( v, mode ), mode );
}

/** The color of the page's paper: the Background in the mode, white (black in dark) when it has no color. */
export function pageColor( design: Design, mode: Mode ): string {
	const bg = color( design, pageBg( design.page, mode ), mode );
	return bg === 'none' || bg === 'transparent' ? ( mode === 'dark' ? '#000000' : '#ffffff' ) : bg;
}

/** Slots a design may not lose: they can be moved, not removed. None today: a design may be as empty as its author wants, the name included. */
export const REQUIRED_SLOTS: string[] = [];

/** Whether an element can't be deleted: the only one left of a required slot (a duplicate can go). */
export function isRequired( el: Element, elements: Element[] ): boolean {
	return !! el.slot && REQUIRED_SLOTS.includes( el.slot ) && elements.filter( ( e ) => e.slot === el.slot ).length < 2;
}

export const PLACEHOLDERS = [ 'name', 'event', 'location', 'issued_date', 'signer_name', 'signer_role', 'code', 'hours', 'role' ];

/**
 * Rich text with fields: a list of runs, plain text or a field (a chip in the
 * editor), each with its own marks. Stored as data, never as text with
 * braces, so a field can't be mistyped and formats travel with it.
 */
export type Run = { text: string; b?: boolean; i?: boolean } | { field: string; format?: string; b?: boolean; i?: boolean };

export type FieldDef = { key: string; label: string; kind?: 'date' | 'hours' | 'text'; formats?: { key: string; label: string }[] };

/** Fields a text can show. Labels are set by the editor (translated). */
export const FIELD_KEYS = [ 'name', 'event', 'location', 'issued_date', 'event_date', 'hours', 'role', 'details', 'signer_name', 'signer_role', 'code', 'document' ];

/** Old "{field} and **bold**" text, as runs. */
export function parseText( text: string ): Run[] {
	const runs: Run[] = [];
	const parts = text.split( /(\*\*.+?\*\*)/ );
	for ( const part of parts ) {
		const bold = /^\*\*(.+)\*\*$/.exec( part );
		const body = bold ? bold[ 1 ] : part;
		for ( const piece of body.split( /(\{[a-z_]+\})/ ) ) {
			if ( ! piece ) {
				continue;
			}
			const field = /^\{([a-z_]+)\}$/.exec( piece );
			const run: Run = field ? { field: field[ 1 ] } : { text: piece };
			if ( bold ) {
				run.b = true;
			}
			runs.push( run );
		}
	}
	return runs;
}

/** A text element's runs (from runs, or from its old text). */
export function runsOf( el: { runs?: Run[]; text?: string } ): Run[] {
	return el.runs || parseText( el.text || '' );
}

/** The runs a text shows in a language (pt_BR or pt-BR): its variant when it has one, else its own. */
export function runsIn( el: { runs?: Run[]; text?: string; variants?: Record< string, Run[] > }, locale: string ): Run[] {
	const variant = ( el.variants || {} )[ locale.replace( '-', '_' ) ];
	return variant && variant.length ? variant : runsOf( el );
}

// Text and attribute values alike: the quotes are escaped too, so a value can never close an attribute.
const esc = ( v: string ) => v.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );

/** Formats a field's sample value. */
export function fieldValue( field: string, format: string | undefined, sample: Record< string, string >, locale: string ): string {
	if ( field === 'issued_date' || field === 'event_date' ) {
		// Default: the site's date format (Settings > General), formatted by WordPress.
		if ( ! format || format === 'site' ) {
			return sample.date_site || sample.date_iso;
		}
		const date = new Date( sample.date_iso || Date.now() );
		const options: Record< string, Intl.DateTimeFormatOptions > = {
			long: { day: 'numeric', month: 'long', year: 'numeric' },
			medium: { day: 'numeric', month: 'short', year: 'numeric' },
			short: { day: '2-digit', month: '2-digit', year: 'numeric' },
			month: { month: 'long', year: 'numeric' },
		};
		if ( format === 'iso' ) {
			return date.toISOString().slice( 0, 10 );
		}
		return new Intl.DateTimeFormat( locale, options[ format || 'long' ] || options.long ).format( date );
	}
	if ( field === 'hours' ) {
		const hours = sample.hours || '8';
		if ( format === 'short' ) {
			return `${ hours }h`;
		}
		const pattern = ( Number( hours ) === 1 ? sample.hours_one : sample.hours_other ) || '%s h';
		return pattern.replace( '%s', hours );
	}
	return sample[ field ] !== undefined ? sample[ field ] : field;
}

/** Runs as HTML with the sample values in place of the fields. */
export function runsHtml( runs: Run[], sample: Record< string, string >, locale: string, upper?: boolean ): string {
	return runs
		.map( ( run ) => {
			let html = esc( 'field' in run ? fieldValue( run.field, run.format, sample, locale ) : run.text );
			if ( upper ) {
				html = html.toUpperCase();
			}
			if ( run.i ) {
				html = `<em>${ html }</em>`;
			}
			if ( run.b ) {
				html = `<strong>${ html }</strong>`;
			}
			return html;
		} )
		.join( '' );
}

let counter = 0;
/** A new element id. */
export function newId( type: string ): string {
	counter++;
	return `${ type }-${ Date.now().toString( 36 ) }-${ counter }`;
}

/** Default new elements, centred on the page. */
/** The colors a new rectangle or ellipse starts with: the editor remembers the last Fill and Stroke set on one (an empty Fill is a Fill too). */
export type Paint = { fill: string; stroke: string };

export function create( type: Element[ 'type' ] | 'logo', design: Design, paint?: Paint ): Element {
	const cx = design.page.w / 2;
	const cy = design.page.h / 2;
	// The community's logo may be placed more than once (two positions, say): the first is `logo`, the next ones get their own id.
	// The place of a signature or of the logo may be put more than once (two places, say): the first is `signature` / `logo`, the next ones get their own id.
	const first = ( 'logo' === type || 'signature' === type ) && ! design.elements.some( ( el ) => el.id === type );
	const id = first ? type : newId( 'logo' === type ? 'image' : type );
	switch ( type ) {
		case 'logo': {
			// The place of the community's logo: it shows the logo set for the event (nothing when none), right-aligned in its box.
			const right = design.page.w - ( design.page.safe || 28 );
			return { id, type: 'image', name: 'Community logo', slot: 'logo', x: right - 120, y: 20, w: 120, h: 36, src: '', fit: 'contain', alt: '' };
		}
		case 'text':
			return { id, type, name: 'Text', x: cx - 120, y: cy - 10, w: 240, h: 20, runs: [ { text: 'Text' } ], font: 'CertSans', weight: 400, size: 14, color: '$text', align: 'center' };
		case 'rect':
			return { id, type, name: 'Rectangle', x: cx - 60, y: cy - 40, w: 120, h: 80, fill: paint?.fill ?? '#000000', stroke: paint?.stroke ?? '', ...( paint?.stroke ? { strokeWidth: 1 } : {} ), radius: [ 0, 0, 0, 0 ] };
		case 'group':
			return { id, type, name: 'Group', x: cx - 140, y: cy - 70, w: 280, h: 140, fill: '', stroke: '', radius: [ 0, 0, 0, 0 ], children: [] };
		case 'ellipse':
			return { id, type, name: 'Ellipse', x: cx - 40, y: cy - 40, w: 80, h: 80, fill: paint?.fill ?? '#000000', stroke: paint?.stroke ?? '', ...( paint?.stroke ? { strokeWidth: 1 } : {} ) };
		case 'line':
			return { id, type, name: 'Line', x1: cx - 100, y1: cy, x2: cx + 100, y2: cy, stroke: '$line', strokeWidth: 1, dash: 'solid' };
		case 'signature':
			return { id, type, name: 'Signature', x: cx - 60, y: cy - 20, w: 120, h: 40, color: '$text' };
		case 'qr':
			return { id, type, name: 'Authenticity', slot: 'authenticity', x: cx - 24, y: cy - 24, w: 48, h: 48 };
		case 'verification':
			return { id, type, name: 'Verification', slot: 'verification', x: cx - 110, y: cy - 40, w: 220, h: 80, arrange: 'stack', align: 'center', qr: 44, gap: 2, font: 'CertSans', code: { size: 6.5, weight: 600, color: '$muted' }, hint: { size: 6, weight: 400, color: '$muted', lh: 1.3 } };
		case 'svg':
			return { id, type, name: 'SVG', x: cx - 48, y: cy - 48, w: 96, h: 96, vw: 24, vh: 24, paths: [ { d: INFO_ICON } ] };
		default:
			return { id, type: 'image', name: 'Image', x: cx - 130, y: cy - 90, w: 260, h: 180, src: '', fit: 'cover', alt: '' };
	}
}


/** The paths of an SVG element; designs saved before the fields kept markup (and one fill for all). */
export function svgPaths( el: SvgEl ): SvgPath[] {
	if ( el.paths ) {
		return el.paths;
	}
	const parsed = el.markup ? sanitizeSvg( el.markup ) : null;
	return ( parsed?.paths || [] ).map( ( p ) => ( el.fill ? { ...p, fill: el.fill } : p ) );
}

/** Moves SVG elements saved as markup to fields. */
/** Standard texts that are fields (FieldText fields(), group 'text'), by their English words. */
const PHRASES: Record< string, string > = {
	'Certificate of participation': 'title_text',
	'This is to certify that': 'intro_text',
	'Date of issue': 'issued_label',
	'Date of emission': 'issued_label',
	'Place and date': 'place_date_label',
	'Use the code above to verify at:': 'verify_hint',
	// The Base's sample signer role, before the signer had one.
	'Event Coordinator': 'signer_role',
};

/**
 * A text run that is a standard text, in English or in the admin's language,
 * becomes its field (with its trailing line break kept as text): designs
 * saved before these fields existed hold the words.
 */
function phraseRuns( runs: Run[] ): Run[] {
	const keys: Record< string, string > = {};
	for ( const [ english, key ] of Object.entries( PHRASES ) ) {
		keys[ english.toLowerCase() ] = key;
		keys[ __( english, 'events-certificates' ).toLowerCase() ] = key; // eslint-disable-line @wordpress/i18n-no-variables
	}
	return runs.flatMap( ( run ) => {
		if ( ! ( 'text' in run ) ) {
			return [ run ];
		}
		const m = /^(\s*)(.*?)(\s*)$/s.exec( run.text ) as RegExpExecArray;
		const key = keys[ m[ 2 ].toLowerCase() ];
		if ( ! key ) {
			return [ run ];
		}
		const { text, ...marks } = run;
		return [ m[ 1 ] && { ...marks, text: m[ 1 ] }, { ...marks, field: key }, m[ 3 ] && { ...marks, text: m[ 3 ] } ].filter( Boolean ) as Run[];
	} );
}

/** The page background in a mode ('' = none: the page's own color shows). */
export function pageBg( page: Design[ 'page' ], mode: Mode ): string {
	return typeof page.bg === 'string' ? page.bg : page.bg?.[ mode ] || '';
}

/** Every string of a value, with "$from" swapped for "to" (a color reference or a literal). */
export function replaceRefs< T >( value: T, from: string, to: string ): T {
	if ( typeof value === 'string' ) {
		return ( value === '$' + from ? to : value ) as unknown as T;
	}
	if ( Array.isArray( value ) ) {
		return value.map( ( item ) => replaceRefs( item, from, to ) ) as unknown as T;
	}
	if ( value && typeof value === 'object' ) {
		return Object.fromEntries( Object.entries( value ).map( ( [ k, v ] ) => [ k, replaceRefs( v, from, to ) ] ) ) as T;
	}
	return value;
}

/** Whether any element of the design (either sheet) uses the palette entry. */
export function usesColor( design: Design, key: string ): boolean {
	return JSON.stringify( [ design.elements, design.back?.elements ] ).includes( JSON.stringify( '$' + key ) );
}

const SWATCH_VALUE = /^(#[0-9a-f]{3,8}|rgba?\([\d\s.,%]+\))$/i;

/**
 * The theme a design gets when it has none: Base and Contrast, then one swatch for each color, from its Light value (never its Dark one:
 * a Dark value is set on the color itself). Each hex once.
 */
export function defaultPalette( colors: Record< string, ColorVar > ): Swatch[] {
	const list: Swatch[] = starterSwatches();
	const seen = new Set( list.map( ( swatch ) => swatch.color ) );
	let n = 0;
	for ( const v of Object.values( colors ) ) {
		const color = ( v.light || '' ).trim();
		if ( SWATCH_VALUE.test( color ) && ! seen.has( color.toLowerCase() ) ) {
			seen.add( color.toLowerCase() );
			list.push( { slug: `custom-${ ++n }`, name: v.name, color } );
		}
	}
	return list;
}

/**
 * A theme as it is stored: the entries that have a usable color, in order, none fixed. A slug that is invalid or repeated is made again;
 * an entry with no name is named by its color, except Base and Contrast, which are named when shown. The same as clean_palette() in includes/render.php.
 */
export function cleanPalette( palette: unknown[] ): Swatch[] {
	const valid = palette.filter( ( s ): s is Partial< Swatch > => !! s && typeof ( s as Swatch ).color === 'string' && SWATCH_VALUE.test( ( s as Swatch ).color.trim() ) );
	const taken = new Set< string >();
	const out = valid.map( ( s ) => {
		const slug = s.slug || '';
		const ok = /^[a-z0-9][a-z0-9-]*$/.test( slug ) && ! taken.has( slug );
		if ( ok ) {
			taken.add( slug );
		}
		return { slug: ok ? slug : '', name: typeof s.name === 'string' ? s.name.trim() : '', color: ( s.color as string ).trim() };
	} );
	return out.map( ( s ) => {
		const slug = s.slug || nextSwatchSlug( [ ...taken ].map( ( t ) => ( { slug: t } ) ) );
		taken.add( slug );
		const named = s.name || ( slug === 'base' || slug === 'contrast' ? '' : s.color );
		return { slug, ...( named ? { name: named } : {} ), color: s.color };
	} );
}

/** The theme as the groups a ColorPalette takes: one "Theme" group of the swatches, with their labels and slugs. */
export function themeOptions( palette: Swatch[] ): Array< { name: string; colors: Array< { name: string; color: string; slug: string } > } > {
	return [ { name: __( 'Theme', 'events-certificates' ), colors: palette.map( ( s ) => ( { name: swatchName( s ), color: s.color, slug: s.slug } ) ) } ];
}

/** A color's values after picking one for a mode, or clearing it (no color): Light goes to none; Dark goes back to "same as Light". */
export function setRoleValue( v: ColorVar, which: Mode, color?: string ): ColorVar {
	if ( which === 'dark' ) {
		if ( ! color ) {
			const { dark, ...rest } = v;
			return rest;
		}
		return { ...v, dark: color };
	}
	return { ...v, light: color || '' };
}

/** A name as a slug: lowercase, no accents, letters, digits and dashes, starting with a letter ('' when the name has nothing to make one of). */
export function slugify( name: string ): string {
	const slug = name
		.normalize( 'NFD' )
		.replace( /[\u0300-\u036f]/g, '' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' );
	return slug && ! /^[a-z]/.test( slug ) ? `color-${ slug }` : slug;
}

/** A slug that none of `taken` has: the one given, else with -2, -3… */
export function uniqueSlug( base: string, taken: string[] ): string {
	let slug = base;
	for ( let n = 2; taken.includes( slug ); n++ ) {
		slug = `${ base }-${ n }`;
	}
	return slug;
}

/** What every slug of the design is: one name for a color, whether it is in the Theme or in the Elements. */
const slugsOf = ( design: Design ): string[] => [ ...Object.keys( design.colors ), ...( design.palette || [] ).map( ( s ) => s.slug ) ];

/** Points what used a color at another name: the elements (both sheets) and the page's background. */
function retarget( design: Design, from: string, to: string ): Design {
	const ref = `$${ to }`;
	const bg = design.page.bg as unknown;
	return {
		...design,
		colors: replaceRefs( design.colors, from, ref ),
		page: { ...design.page, bg: replaceRefs( bg, from, ref ) as Design[ 'page' ][ 'bg' ] },
		elements: replaceRefs( design.elements, from, ref ),
		...( design.back ? { back: { ...design.back, elements: replaceRefs( design.back.elements, from, ref ) } } : {} ),
	};
}

/**
 * A color of the Elements renamed: its slug is made from the new name (as the Styles' are), and what used the old one follows it. A name with nothing
 * to make a slug of keeps the slug it had; the colors keep their order.
 */
export function renameColor( design: Design, key: string, name: string ): Design {
	const v = design.colors[ key ];
	if ( ! v ) {
		return design;
	}
	const base = slugify( name );
	const next = base ? uniqueSlug( base, slugsOf( design ).filter( ( k, i, all ) => k !== key || all.indexOf( k ) !== i ) ) : key;
	const colors = Object.fromEntries( Object.entries( design.colors ).map( ( [ k, c ] ) => ( k === key ? [ next, { ...c, name } ] : [ k, c ] ) ) );
	const renamed = { ...design, colors };
	return next === key ? renamed : retarget( renamed, key, next );
}

/** A swatch of the Theme renamed: the same as renameColor(), in the Theme. */
export function renameSwatch( design: Design, slug: string, name: string ): Design {
	const palette = design.palette || [];
	if ( ! palette.some( ( s ) => s.slug === slug ) ) {
		return design;
	}
	const base = slugify( name );
	const next = base ? uniqueSlug( base, [ ...Object.keys( design.colors ), ...palette.filter( ( s ) => s.slug !== slug ).map( ( s ) => s.slug ) ] ) : slug;
	const renamed = { ...design, palette: palette.map( ( s ) => ( s.slug === slug ? { ...s, slug: next, name } : s ) ) };
	return next === slug ? renamed : retarget( renamed, slug, next );
}

/** The swatch the + of the Theme adds: "Color N" in black, its slug made from the name. */
export function nextSwatch( palette: Swatch[], name: ( n: number ) => string, colors: Record< string, ColorVar > = {} ): Swatch {
	let n = 1;
	while ( palette.some( ( s ) => s.name === name( n ) ) ) {
		n++;
	}
	const base = slugify( name( n ) ) || 'color';
	return { slug: uniqueSlug( base, [ ...palette.map( ( s ) => s.slug ), ...Object.keys( colors ) ] ), name: name( n ), color: '#000000' };
}

/** The color the + of the Elements adds: "Color N" with no value yet (an empty circle), its key made from the name. */
export function nextColor( colors: Record< string, ColorVar >, name: ( n: number ) => string, palette: Swatch[] = [] ): [ string, ColorVar ] {
	let n = 1;
	while ( Object.values( colors ).some( ( c ) => c.name === name( n ) ) ) {
		n++;
	}
	const base = slugify( name( n ) ) || 'color';
	return [ uniqueSlug( base, [ ...Object.keys( colors ), ...palette.map( ( s ) => s.slug ) ] ), { name: name( n ), light: '' } ];
}

/**
 * The colors of a design as the code reads them (kept the same as with_palette() in includes/render.php): none is fixed, so none is added
 * or moved (a design may have no colors at all: a color it does not have is the built-in one, builtinColor()); the page is the color it says
 * (the Background by default: any color of the Theme or the Elements, or a literal); the theme is the one it has, cleaned, else made from the
 * colors; a slug is never in both the Theme and the Elements (a swatch that has the slug of a color gets another one). Safe to run again.
 */
export function normalizeColors( design: Design ): Design {
	const colors: Record< string, ColorVar > = { ...( design.colors || {} ) };
	let palette = Array.isArray( design.palette ) ? cleanPalette( design.palette ) : defaultPalette( colors );
	const kept: Swatch[] = [];
	for ( const s of palette ) {
		kept.push( s.slug in colors ? { ...s, slug: nextSwatchSlug( [ ...kept, ...palette ], colors ) } : s );
	}
	palette = kept;
	return { ...design, page: { ...design.page, bg: pageBg( design.page, 'light' ) ? design.page.bg : '$background' }, colors, palette };
}

const round2 = ( n: number ) => Math.round( n * 100 ) / 100;

/** The unit a margin or a bleed is kept in (mm unless the person chose another). */
export type LengthUnit = 'pt' | 'px' | 'mm' | 'cm' | 'in';
const unitIn = ( text: unknown ): LengthUnit | undefined => {
	const m = typeof text === 'string' ? /(pt|px|mm|cm|in)\s*$/i.exec( text ) : null;
	return m ? ( m[ 1 ].toLowerCase() as LengthUnit ) : undefined;
};
/** A length in points written in a unit, as it is kept in the file ("10mm", "28.35pt", "0.394in"). */
const keepIn = ( pt: number, unit: LengthUnit ) => `${ Math.round( ( pt / ( UNITS[ unit ] || 1 ) ) * ( unit === 'in' ? 1000 : 100 ) ) / ( unit === 'in' ? 1000 : 100 ) }${ unit }`;

/**
 * A design's page as it is kept: lengths with their unit, as CSS says them. The width and height are in the unit that makes both round (an A4 is
 * 297mm by 210mm, a letter 11in by 8.5in: pageSize() in the camaleaun/draw package), the margins in mm, the background as backgroundColor, and no
 * safe margin and no bleed when there is none. There is no version: the unit says what it is. In the editor the page is numbers in points (w, h, bg, safe, bleed).
 */
export function toStored( design: Design ): Design {
	const { w, h, bg, safe, bleed, safeUnit, bleedUnit, ...rest } = design.page;
	const [ width, height ] = pageSize( w, h );
	const page: Record< string, unknown > = { width, height, backgroundColor: bg, ...rest };
	// No safe margin and no bleed when there is none.
	if ( safe ) {
		page.safe = keepIn( safe, safeUnit || 'mm' );
	}
	if ( bleed ) {
		page.bleed = keepIn( bleed, bleedUnit || 'mm' );
	}
	const { version: _earlier, ...kept } = design as Design & { version?: number };
	return { ...kept, page } as unknown as Design;
}

/** The page of a stored design as numbers in points; a design of an earlier version (w, h, bg, safe, bleed) is left as it is. */
/** A page that is an A4 rounded to whole points (842 by 595) is the A4 (297 by 210 mm: 841.89 by 595.28), so it is kept in millimeters. The same as snap_paper() in includes/render.php. */
export function snapPaper( w: number, h: number ): [ number, number ] {
	const [ a, b ] = [ 841.89, 595.28 ];
	if ( Math.abs( w - a ) <= 0.5 && Math.abs( h - b ) <= 0.5 ) {
		return [ a, b ];
	}
	if ( Math.abs( w - b ) <= 0.5 && Math.abs( h - a ) <= 0.5 ) {
		return [ b, a ];
	}
	return [ w, h ];
}

export function fromStored( design: Design ): Design {
	const page = design.page as unknown as Record< string, unknown >;
	if ( ! page || page.width === undefined ) {
		return design;
	}
	const { width, height, backgroundColor, safe, bleed, ...rest } = page;
	const [ w, h ] = snapPaper( round2( parseLen( width as string, 0 ) ) || 841.89, round2( parseLen( height as string, 0 ) ) || 595.28 );
	return { ...design, page: { ...rest, w, h, bg: backgroundColor ?? '$background', safe: parseLen( safe as string, 0 ), bleed: parseLen( bleed as string, 0 ), ...( unitIn( safe ) && unitIn( safe ) !== 'mm' ? { safeUnit: unitIn( safe ) } : {} ), ...( unitIn( bleed ) && unitIn( bleed ) !== 'mm' ? { bleedUnit: unitIn( bleed ) } : {} ) } } as Design;
}

export function migrate( input: Design ): Design {
	const design = normalizeColors( fromStored( input ) );
	return {
		...design,
		elements: design.elements.map( ( el ) => {
			if ( el.type === 'text' && el.runs ) {
				return { ...el, runs: phraseRuns( el.runs ) };
			}
			if ( el.type !== 'svg' || el.paths ) {
				return el;
			}
			const { markup, fill, ...rest } = el;
			return { ...rest, paths: svgPaths( el ) } as Element;
		} ),
	};
}


/**
 * Anchors and relative sizes, like Figma's constraints: per axis, an element
 * is pinned to the start, the end or the centre of its reference box (the page
 * or the safe margin), or stretched between both with an inset on each side.
 * Lengths are open text: "20pt", "10mm", "5%", "calc(100% - 40pt)"; percentages
 * are of the reference box's size on that axis.
 */
/**
 * Previews: the print page (the format set in Size, never rotated) and the
 * screens the certificate page is seen on. Each screen has an orientation;
 * elements keep their content and style everywhere, and may have their own
 * anchors and visibility per screen (views). Without one, an element keeps
 * its anchors, and an element without anchors scales with the page.
 */
export type Screen = 'desktop' | 'tablet' | 'mobile';
export type Device = 'page' | Screen;
export type Orientation = 'landscape' | 'portrait';
export type View = { device: Device; orientation: Orientation };
export type ElementView = { layout?: Layout; hidden?: boolean; line?: { x1: number; y1: number; x2: number; y2: number } };

/** Screen sizes in CSS px (= pt on the canvas), landscape. */
export const SCREENS: Record< Screen, [ number, number ] > = {
	desktop: [ 1280, 800 ],
	tablet: [ 1024, 768 ],
	mobile: [ 844, 390 ],
};

export function viewPage( page: Design[ 'page' ], view: View ): Design[ 'page' ] {
	if ( view.device === 'page' ) {
		return page;
	}
	const [ w, h ] = SCREENS[ view.device ];
	const portrait = view.orientation === 'portrait';
	return { ...page, w: portrait ? h : w, h: portrait ? w : h, bleed: 0, safe: Math.min( page.safe, view.device === 'mobile' ? 16 : 32 ) };
}

const pct = ( n: number ) => `${ Math.round( n * 10000 ) / 100 }%`;

/**
 * Anchors for an unanchored element on a screen: its centre keeps its place
 * (as a share of the page); shapes keep their proportions (one scale for
 * both axes, so a QR code stays square), texts and rectangles take the same
 * share of the width and height.
 */
function scaled( el: Element, from: Design[ 'page' ], to: Design[ 'page' ] ): Layout {
	const b = box( el );
	const cx = ( b.x + b.w / 2 ) / from.w;
	const cy = ( b.y + b.h / 2 ) / from.h;
	const sx = to.w / from.w;
	const sy = to.h / from.h;
	const uniform = el.type === 'qr' || el.type === 'svg' || el.type === 'image' || el.type === 'ellipse';
	const k = Math.min( sx, sy );
	const w = Math.round( b.w * ( uniform ? k : sx ) * 100 ) / 100;
	const h = Math.round( b.h * ( uniform ? k : sy ) * 100 ) / 100;
	return {
		ref: 'page',
		h: { mode: 'start', start: `calc(${ pct( cx ) } - ${ w / 2 }pt)`, size: `${ w }pt` },
		v: { mode: 'start', start: `calc(${ pct( cy ) } - ${ h / 2 }pt)`, size: `${ h }pt` },
	};
}

/** The element as seen on a screen: its view's anchors and visibility, or its own. */
function inView( el: Element, design: Design, device: Screen, page: Design[ 'page' ] ): Element {
	const v = el.views?.[ device ];
	const hidden = v?.hidden ?? el.hidden;
	if ( el.type === 'line' ) {
		const sx = page.w / design.page.w;
		const sy = page.h / design.page.h;
		const line = v?.line || { x1: el.x1 * sx, y1: el.y1 * sy, x2: el.x2 * sx, y2: el.y2 * sy };
		return { ...el, ...line, hidden };
	}
	return { ...el, layout: v?.layout || el.layout || scaled( el, design.page, page ), hidden } as Element;
}

/** The design as the editor shows it for a view. */
export function toView( design: Design, view: View ): Design {
	if ( view.device === 'page' ) {
		return design;
	}
	const device = view.device;
	const page = viewPage( design.page, view );
	return { ...design, page, elements: design.elements.map( ( el ) => inView( el, design, device, page ) ) };
}

/** Edits made in a view, back into the design: content and style are shared, position and visibility go to the view. */
export function fromView( stored: Design, edited: Design, view: View ): Design {
	if ( view.device === 'page' ) {
		return edited;
	}
	const device = view.device;
	const page = viewPage( stored.page, view );
	const byId = new Map( stored.elements.map( ( el ) => [ el.id, el ] ) );
	const elements = edited.elements.map( ( next ) => {
		const orig = byId.get( next.id );
		if ( ! orig ) {
			return next;
		}
		const shown = inView( orig, stored, device, page );
		const views = { ...( orig.views || {} ) };
		const v: ElementView = { ...( views[ device ] || {} ) };
		let shared: Element;
		if ( next.type === 'line' && orig.type === 'line' && shown.type === 'line' ) {
			const { x1, y1, x2, y2, hidden, views: _v, ...rest } = next;
			if ( x1 !== shown.x1 || y1 !== shown.y1 || x2 !== shown.x2 || y2 !== shown.y2 ) {
				v.line = { x1, y1, x2, y2 };
			}
			shared = { ...orig, ...rest } as Element;
		} else {
			const { x, y, w, h, layout, hidden, views: _v, ...rest } = next as Element & { x: number };
			if ( JSON.stringify( layout ) !== JSON.stringify( ( shown as { layout?: Layout } ).layout ) ) {
				v.layout = layout;
			}
			shared = { ...orig, ...rest } as Element;
		}
		if ( !! next.hidden !== !! shown.hidden ) {
			v.hidden = !! next.hidden;
		}
		if ( Object.keys( v ).length ) {
			views[ device ] = v;
		}
		return { ...shared, ...( Object.keys( views ).length ? { views } : {} ) } as Element;
	} );
	return { ...edited, page: stored.page, elements };
}
