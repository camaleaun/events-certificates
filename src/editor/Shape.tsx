/**
 * One element of a certificate drawn as SVG on the canvas: the shapes, the picture, the QR placeholder, the verification parts, the
 * signature of the sample data and the text with its fields (as HTML in a foreignObject), the way the PDF draws them.
 */
import { __ } from '@wordpress/i18n';
import { fontStack, weightFor } from './fonts';
import { stockName } from './base';
import { QR_PLACEHOLDER } from './qr-placeholder';
import { verificationParts } from './verification';
import { color, rectPath, runsHtml, runsOf, svgPaths, type Design, type Element, type Mode, type SvgPath, type TextEl } from './model';

/** A text element's CSS, shared by the canvas and the inline editor. */
export function textStyle( el: TextEl, design: Design, mode: Mode ) {
	return {
		fontFamily: fontStack( el.font ),
		fontWeight: weightFor( el.font, el.weight ),
		fontSize: `${ el.size }px`,
		lineHeight: el.lh ?? 1.2,
		letterSpacing: el.ls ? `${ el.ls }px` : undefined,
		color: color( design, el.color, mode ),
		textAlign: el.align || 'left',
		whiteSpace: el.fit ? ( 'nowrap' as const ) : ( 'pre-line' as const ),
		textTransform: el.upper ? ( 'uppercase' as const ) : undefined,
	};
}
export const LOCALE = document.documentElement.lang || 'en';

/** One element, as SVG. */
/** The organizer's signature from the sample data (JSON), if any. */
function signatureOf( sample: Record< string, string > ): { vw: number; vh: number; paths: SvgPath[] } | null {
	try {
		const sig = sample.signature ? JSON.parse( sample.signature ) : null;
		return sig && sig.vw > 0 && sig.vh > 0 && Array.isArray( sig.paths ) && sig.paths.length ? sig : null;
	} catch {
		return null;
	}
}

/** A place with nothing in it (a logo, a signature, a drawing or a picture still to choose): dashed, with its name. */
function emptyPlace( el: { x: number; y: number; w: number; h: number; name?: string }, design: Design, mode: Mode, opacity: number | undefined, fallback: string ) {
	return (
		<g opacity={ opacity }>
			<rect x={ el.x } y={ el.y } width={ el.w } height={ el.h } fill="transparent" stroke={ color( design, '$muted', mode ) } strokeWidth={ 0.75 } strokeDasharray="4 3" />
			<text x={ el.x + el.w / 2 } y={ el.y + el.h / 2 } textAnchor="middle" dominantBaseline="central" fontSize={ Math.min( 9, el.h / 3 ) } fill={ color( design, '$muted', mode ) }>
				{ stockName( el.name || fallback ) }
			</text>
		</g>
	);
}

/** The width of what can be clicked along the outline of a shape with no fill (its stroke may be a line of one point). */
const EDGE = 10;
/** A shape with no fill (empty, or transparent) has only its outline to click on: it gets a wider, invisible one. */
const unfilled = ( design: Design, fill: string | undefined, mode: Mode ) => color( design, fill, mode ) === 'none';

export function Shape( { el, design, mode, sample }: { el: Element; design: Design; mode: Mode; sample: Record< string, string > } ) {
	const opacity = el.opacity ?? 1;
	switch ( el.type ) {
		case 'rect':
		case 'group':
			// A group is drawn as its own rectangle (what is in it is drawn by the canvas, from its corner).
			return (
				<>
					{ unfilled( design, el.fill, mode ) && <path d={ rectPath( el.x, el.y, el.w, el.h, el.radius, el.corner ) } fill="none" stroke="transparent" strokeWidth={ Math.max( el.strokeWidth || 0, EDGE ) } pointerEvents="stroke" /> }
					<path d={ rectPath( el.x, el.y, el.w, el.h, el.radius, el.corner ) } fill={ color( design, el.fill, mode ) } stroke={ color( design, el.stroke, mode ) } strokeWidth={ el.strokeWidth || 0 } opacity={ opacity } />
				</>
			);
		case 'signature': {
			// The signature element previews the organizer's signature when one is set, upright like on certificates (includes/render.php).
			const signer = signatureOf( sample );
			// The signature can also be a picture (Settings): it fills the box as a picture, upright like the drawing.
			const picture = ! signer && sample.signature_image ? sample.signature_image : '';
			if ( picture ) {
				const turn = ( ( ( el.rotation || 0 ) % 360 ) + 360 ) % 360;
				const swap = turn === 90 || turn === 270;
				const cx = el.x + el.w / 2;
				const cy = el.y + el.h / 2;
				const w = swap ? el.h : el.w;
				const h = swap ? el.w : el.h;
				return (
					<g transform={ turn ? `rotate(${ -turn } ${ cx } ${ cy })` : undefined }>
						<rect x={ cx - w / 2 } y={ cy - h / 2 } width={ w } height={ h } fill="transparent" />
						<image href={ picture } x={ cx - w / 2 } y={ cy - h / 2 } width={ w } height={ h } preserveAspectRatio="xMidYMid meet" opacity={ opacity } />
					</g>
				);
			}
			if ( signer ) {
				const turn = ( ( ( el.rotation || 0 ) % 360 ) + 360 ) % 360;
				const swap = turn === 90 || turn === 270;
				const cx = el.x + el.w / 2;
				const cy = el.y + el.h / 2;
				const w = swap ? el.h : el.w;
				const h = swap ? el.w : el.h;
				return (
					<g transform={ turn ? `rotate(${ -turn } ${ cx } ${ cy })` : undefined }>
						<rect x={ cx - w / 2 } y={ cy - h / 2 } width={ w } height={ h } fill="transparent" />
						<svg x={ cx - w / 2 } y={ cy - h / 2 } width={ w } height={ h } viewBox={ `0 0 ${ signer.vw } ${ signer.vh }` } opacity={ opacity } overflow="hidden">
							{ signer.paths.map( ( p, i ) => (
								<path key={ i } d={ p.d } fill={ color( design, p.fill || el.color || '$text', mode ) } fillRule={ p.rule } />
							) ) }
						</svg>
					</g>
				);
			}
			// A place with no signature defined (Settings, the event): a dashed place, like the logo's. It has no drawing of its own: it takes the
			// one that is defined.
			return emptyPlace( el, design, mode, opacity, 'Signature' );
		}
		case 'svg': {
			// A drawing with no paths is a place with its name (a template that came in from a site that does not let drawings travel): it
			// is there for the person to draw again.
			if ( svgPaths( el ).length === 0 ) {
				return emptyPlace( el, design, mode, opacity, __( 'Drawing', 'events-certificates' ) );
			}
			return (
				<>
					{ /* A thin drawing (a signature) has little to click on: its whole box selects it. */ }
					<rect x={ el.x } y={ el.y } width={ el.w } height={ el.h } fill="transparent" />
					{ /* Clipped to the viewBox, as certificates draw it (an <img> of the SVG). */ }
					<svg x={ el.x } y={ el.y } width={ el.w } height={ el.h } viewBox={ `0 0 ${ el.vw } ${ el.vh }` } opacity={ opacity } overflow="hidden">
						{ svgPaths( el ).map( ( p, i ) => (
							<path key={ i } d={ p.d } fill={ color( design, p.fill || el.color || '$text', mode ) } fillRule={ p.rule } />
						) ) }
					</svg>
				</>
			);
		}
		case 'ellipse':
			return (
				<>
					{ unfilled( design, el.fill, mode ) && <ellipse cx={ el.x + el.w / 2 } cy={ el.y + el.h / 2 } rx={ el.w / 2 } ry={ el.h / 2 } fill="none" stroke="transparent" strokeWidth={ Math.max( el.strokeWidth || 0, EDGE ) } pointerEvents="stroke" /> }
					<ellipse cx={ el.x + el.w / 2 } cy={ el.y + el.h / 2 } rx={ el.w / 2 } ry={ el.h / 2 } fill={ color( design, el.fill, mode ) } stroke={ color( design, el.stroke, mode ) } strokeWidth={ el.strokeWidth || 0 } opacity={ opacity } />
				</>
			);
		case 'path':
			return (
				<svg x={ el.x } y={ el.y } width={ el.w } height={ el.h } viewBox={ `0 0 ${ el.vw } ${ el.vh }` } preserveAspectRatio="none" overflow="visible">
					<path d={ el.d } fill={ color( design, el.fill, mode ) } stroke={ color( design, el.stroke, mode ) } strokeWidth={ el.strokeWidth || 0 } opacity={ opacity } vectorEffect="non-scaling-stroke" />
				</svg>
			);
		case 'line': {
			const dash = el.dash === 'dashed' ? '6 4' : el.dash === 'dotted' ? '1 3' : undefined;
			return <line x1={ el.x1 } y1={ el.y1 } x2={ el.x2 } y2={ el.y2 } stroke={ color( design, el.stroke, mode ) } strokeWidth={ el.strokeWidth || 1 } strokeDasharray={ dash } strokeLinecap="round" opacity={ opacity } />;
		}
		case 'image':
			// The community's logo: a place, not a picture to pick (the logo comes with the form); it is drawn dashed, with its name.
			// Any other picture with no file is the same kind of place (the template came in from a site that does not let pictures travel).
			if ( ! el.src ) {
				return emptyPlace( el, design, mode, opacity, el.slot === 'logo' ? 'Community logo' : __( 'Picture', 'events-certificates' ) );
			}
			return el.src ? (
				<image href={ el.src } x={ el.x } y={ el.y } width={ el.w } height={ el.h } preserveAspectRatio={ el.fit === 'cover' ? 'xMidYMid slice' : 'xMidYMid meet' } opacity={ opacity } />
			) : null;
		case 'verification': {
			// The QR code, the code and the hint as one element; each part is drawn where verification_parts() puts it in the PDF.
			return (
				<g>
					<rect x={ el.x } y={ el.y } width={ el.w } height={ el.h } fill="transparent" />
					{ verificationParts( el ).map( ( part ) =>
						part.kind === 'qr' ? (
							<svg key="qr" x={ part.x } y={ part.y } width={ part.w } height={ part.h } viewBox={ QR_PLACEHOLDER.viewBox } opacity={ opacity } className="evcert-qr-placeholder" overflow="visible">
								<g fill={ color( design, '$text', mode ) } dangerouslySetInnerHTML={ { __html: QR_PLACEHOLDER.markup } } />
							</svg>
						) : (
							<Shape
								key={ part.kind }
								design={ design }
								mode={ mode }
								sample={ sample }
								el={ {
									id: `${ el.id }-${ part.kind }`,
									type: 'text',
									x: part.x,
									y: part.y,
									w: part.w,
									h: part.h,
									runs: part.kind === 'code' ? [ { field: 'code' } ] : [ { field: 'verify_hint' }, { text: '\n' }, { field: 'verify_url' } ],
									font: el.font || 'CertSans',
									weight: part.weight,
									size: part.size,
									color: part.color,
									align: part.align,
									lh: part.lh,
									opacity: el.opacity,
								} as Element }
							/>
						)
					) }
				</g>
			);
		}
		case 'qr':
			// The authenticity slot: a QR-shaped placeholder (the real code is drawn on certificates).
			return (
				<svg x={ el.x } y={ el.y } width={ el.w } height={ el.h } viewBox={ QR_PLACEHOLDER.viewBox } opacity={ opacity } className="evcert-qr-placeholder" overflow="visible">
					<g fill={ color( design, '$text', mode ) } dangerouslySetInnerHTML={ { __html: QR_PLACEHOLDER.markup } } />
				</svg>
			);
		case 'text': {
			const style = textStyle( el, design, mode );
			return (
				<foreignObject x={ el.x } y={ el.y } width={ el.w } height={ Math.max( el.h, el.size * ( el.lh ?? 1.2 ) * 6 ) } opacity={ opacity } style={ { overflow: 'visible' } }>
					{ /* Only the lines of the text take a click, not the whole box (a tall box would hide the text under it): a click beside the text goes to what is under it. */ }
					<div className="evcert-text" style={ { ...style, pointerEvents: 'none' } }>
						<span style={ { pointerEvents: 'auto' } } dangerouslySetInnerHTML={ { __html: runsHtml( runsOf( el ), sample, LOCALE, el.upper ) } } />
					</div>
				</foreignObject>
			);
		}
	}
	return null;
}

