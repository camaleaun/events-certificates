/**
 * The certificate preview of the admin quick generator, drawn on a canvas in the browser from the site's default design,
 * as the public generator does (assets/es/quick-generator.js): no round trip to the server while the form is filled in.
 * The values of the fields follow that script's valueOf(); the drawing is design.ts's.
 */
import { phpDate } from '../editor/dates';
import type { Design } from '../editor/model';
import { cssFont, draw, prepare, type Pic } from './design';

/** What the server hands over (QuickGenerator\config()). */
export type PreviewConfig = {
	locale: string;
	design: Design;
	phrases: Record< string, string >;
	dateFormat: string;
	timeFormat: string;
	fonts: Record< string, string >;
	i18n: Record< string, string >;
};

/** What the form holds. */
export type PreviewEvent = { title: string; community: string; venue: string; city: string; speakers: string; link: string; date: string; start: string; end: string; showHours: boolean; signName: string; signRole: string };

const PT = 0.3528;

/** printf for the translated texts: %s, %d, %1$s, %2$02d. */
export function fmt( text: string, ...args: Array< string | number > ): string {
	let next = 0;
	return String( text ).replace( /%(?:(\d+)\$)?(0?)(\d*)[sd]/g, ( _match, position, zero, width ) => {
		let value = String( args[ position ? Number( position ) - 1 : next++ ] );
		if ( width ) {
			value = value.padStart( Number( width ), zero ? '0' : ' ' );
		}
		return value;
	} );
}

/** A date for a field's format: long, medium, short, month or iso by the language, the site's date format otherwise. */
export function dateText( cfg: PreviewConfig, iso: string, format?: string ): string {
	if ( ! iso ) {
		return '';
	}
	const [ y, m, d ] = iso.split( '-' ).map( Number );
	const date = new Date( y, m - 1, d );
	if ( 'iso' === format ) {
		return iso;
	}
	if ( 'month' === format ) {
		return new Intl.DateTimeFormat( cfg.locale, { month: 'long', year: 'numeric' } ).format( date );
	}
	if ( 'long' === format || 'medium' === format || 'short' === format ) {
		return new Intl.DateTimeFormat( cfg.locale, { dateStyle: format } ).format( date );
	}
	// Any other format is a date pattern (the date of emission writes its own); none is the site's.
	return phpDate( format && 'site' !== format ? format : cfg.dateFormat, date, cfg.locale );
}

const timeText = ( cfg: PreviewConfig, hhmm: string ) => {
	const [ h, m ] = hhmm.split( ':' ).map( Number );
	return phpDate( cfg.timeFormat, new Date( 2000, 0, 1, h, m ), cfg.locale );
};

function duration( cfg: PreviewConfig, from: string, to: string ): string {
	const t = cfg.i18n;
	const minutes = ( hhmm: string ) => {
		const [ h, m ] = hhmm.split( ':' ).map( Number );
		return h * 60 + m;
	};
	let total = minutes( to ) - minutes( from );
	if ( total <= 0 ) {
		total += 24 * 60;
	}
	const h = Math.floor( total / 60 );
	const m = total % 60;
	if ( ! m ) {
		return 1 === h ? t.oneHour : fmt( t.nHours, h );
	}
	return h ? fmt( t.hoursMinutes, h, m ) : fmt( t.nMinutes, m );
}

const shortLink = ( url: string ) => ( url || '' ).replace( /^https?:\/\/(www\.)?/, '' ).replace( /\?.*$/, '' ).replace( /\/$/, '' );

/** What each field of the design says for an event and a person. */
export function valueOf( cfg: PreviewConfig, ev: PreviewEvent, name: string ): ( field: string, format?: string ) => string {
	const t = cfg.i18n;
	const today = new Date().toLocaleDateString( 'sv-SE' );
	const many = /,|;/.test( ev.speakers ) || new RegExp( '\\s' + t.and + '\\s', 'i' ).test( ev.speakers );
	let time = '';
	if ( ev.start ) {
		time = ev.end ? fmt( t.fromTo, timeText( cfg, ev.start ), timeText( cfg, ev.end ) ) : fmt( t.startingAt, timeText( cfg, ev.start ) );
		if ( ev.showHours && ev.end ) {
			time = fmt( t.withSpan, time, duration( cfg, ev.start, ev.end ) );
		}
	}
	const fields: Record< string, string > = {
		...cfg.phrases,
		name: name || t.participantName,
		event: ev.title || t.meetupTitle,
		community: ev.community,
		location: [ ev.venue, ev.city ].filter( Boolean ).join( ', ' ),
		speakers: ev.speakers,
		speakers_label: ev.speakers.trim() ? ( many ? t.speakers : t.speaker ) : '',
		event_time: time,
		event_link: shortLink( ev.link ),
		issued_on: fmt( t.issuedOn, dateText( cfg, today, 'long' ) ),
		signer_name: ev.signName,
		signer_role: ev.signRole,
	};
	return ( field, format ) => {
		if ( 'event_date' === field ) {
			// An event with no date yet shows today's, as the design's own sample does.
			return dateText( cfg, ev.date || today, format );
		}
		if ( 'issued_date' === field ) {
			return dateText( cfg, today, format );
		}
		return fields[ field ] || '';
	};
}

/** The canvas drawing target: millimetres, whole device pixels like the browser lays out the design's own HTML. */
function canvasBackend( ctx: CanvasRenderingContext2D, scale: number, faces: string[] ) {
	const font = ( face: string, size: number ) => cssFont( face, size * PT * scale );
	return {
		fonts: faces,
		alpha( a: number ) {
			ctx.globalAlpha = a;
		},
		rect( x: number, y: number, w: number, h: number, c: string ) {
			const left = Math.round( x * scale );
			const top = Math.round( y * scale );
			ctx.fillStyle = c;
			ctx.fillRect( left, top, Math.round( ( x + w ) * scale ) - left, Math.round( ( y + h ) * scale ) - top );
		},
		line( x1: number, y1: number, x2: number, y2: number, c: string, width: number ) {
			ctx.strokeStyle = c;
			ctx.lineWidth = Math.max( 1, width * scale );
			ctx.beginPath();
			ctx.moveTo( x1 * scale, y1 * scale );
			ctx.lineTo( x2 * scale, y2 * scale );
			ctx.stroke();
		},
		measure( text: string, f: string, size: number, spacing = 0 ) {
			ctx.font = font( f, size );
			( ctx as unknown as { letterSpacing: string } ).letterSpacing = '0px';
			return ctx.measureText( text ).width / scale + spacing * text.length;
		},
		text( text: string, x: number, y: number, o: { font: string; size: number; color: string; cs?: number; angle?: number } ) {
			ctx.save();
			ctx.font = font( o.font, o.size );
			ctx.fillStyle = o.color;
			ctx.textBaseline = 'alphabetic';
			( ctx as unknown as { letterSpacing: string } ).letterSpacing = ( o.cs || 0 ) * scale + 'px';
			ctx.translate( x * scale, o.angle ? y * scale : Math.round( y * scale ) );
			if ( o.angle ) {
				ctx.rotate( ( -o.angle * Math.PI ) / 180 );
			}
			ctx.fillText( text, 0, 0 );
			ctx.restore();
		},
		image( pic: Pic, x: number, y: number, w: number, h: number ) {
			if ( pic.img ) {
				const left = Math.round( x * scale );
				const top = Math.round( y * scale );
				ctx.drawImage( pic.img, left, top, Math.max( 1, Math.round( ( x + w ) * scale ) - left ), Math.max( 1, Math.round( ( y + h ) * scale ) - top ) );
			}
		},
	};
}

const pics = new Map< string, Promise< Pic | null > >();

/** A picture (a PNG data URI, or the SVG of paths) the backends can draw; cached by its source. */
export function picture( src: string ): Promise< Pic | null > {
	if ( ! src ) {
		return Promise.resolve( null );
	}
	let found = pics.get( src );
	if ( ! found ) {
		found = new Promise( ( resolve ) => {
			const img = new Image();
			img.onload = () => resolve( { src, img, ratio: ( img.naturalWidth || 1 ) / ( img.naturalHeight || 1 ), alias: 'pic' + pics.size } );
			img.onerror = () => resolve( null );
			img.src = src;
		} );
		pics.set( src, found );
	}
	return found;
}

/** The picture of a drawing made of paths ({ vw, vh, paths }), in the color of the text. */
export function drawingSrc( json: string ): string {
	try {
		const d = JSON.parse( json );
		if ( ! d || ! ( d.vw > 0 ) || ! ( d.vh > 0 ) || ! Array.isArray( d.paths ) ) {
			return '';
		}
		const paths = d.paths.map( ( p: { d: string; fill?: string; rule?: string } ) => `<path d="${ String( p.d ).replace( /"/g, '' ) }" fill="${ p.fill && ! p.fill.startsWith( '$' ) ? p.fill : '#1e1e1e' }"${ p.rule === 'evenodd' ? ' fill-rule="evenodd"' : '' }/>` ).join( '' );
		// A drawing that prefers a size (w and h beside its viewBox) is given the shape of it: the drawing sits centered in a picture of that
		// proportion, as the certificate fits it in its place (Render\fit_preferred()).
		const [ sw, sh ] = d.w > 0 && d.h > 0 ? [ d.w, d.h ] : [ d.vw, d.vh ];
		const k = Math.min( 8, 1200 / Math.max( sw, sh ) );
		return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent( `<svg xmlns="http://www.w3.org/2000/svg" width="${ sw * k }" height="${ sh * k }" viewBox="0 0 ${ d.vw } ${ d.vh }">${ paths }</svg>` );
	} catch ( e ) {
		return '';
	}
}

let art: Promise< Record< string, Pic > > | null = null;

/**
 * Draws the certificate on the canvas.
 *
 * @param canvas   Where to draw (its size sets the sharpness).
 * @param cfg      The server's config.
 * @param ev       The event as the form holds it.
 * @param name     The person shown ('' for the sample's).
 * @param pictures The logo and the signature as sources (a data URI or a drawing's SVG), '' for none.
 */
export async function drawPreview( canvas: HTMLCanvasElement, cfg: PreviewConfig, ev: PreviewEvent, name: string, pictures: { logo: string; signature: string } ): Promise< void > {
	const faces = Object.keys( cfg.fonts || {} );
	art = art || prepare( cfg.design );
	const [ shapes, logo, signature ] = await Promise.all( [ art, picture( pictures.logo ), picture( pictures.signature ) ] );
	// The fonts come from the page's own @font-face: wait for the faces the design uses before measuring text.
	if ( document.fonts && faces.length ) {
		await Promise.all( faces.map( ( face ) => document.fonts.load( cssFont( face, 16 ) ).catch( () => [] ) ) );
	}
	const W = cfg.design.page.w * PT;
	const scale = canvas.width / W;
	const ctx = canvas.getContext( '2d' );
	if ( ! ctx ) {
		return;
	}
	ctx.clearRect( 0, 0, canvas.width, canvas.height );
	draw( canvasBackend( ctx, scale, faces ), cfg.design, valueOf( cfg, ev, name ), { signature, logo, art: shapes }, cfg.locale );
}
