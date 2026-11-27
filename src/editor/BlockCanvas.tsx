/**
 * The canvas of the certificate editor on the block editor's store (docs/decisions/0046): the elements it draws are the blocks of the
 * store, a selection is the store's, and what a drag or a resize ends in is one update of the attributes of a block. While the
 * pointer is down the box follows it as a preview, so the history keeps one step for the gesture.
 */
import { useRef, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { resizeBox, snap, type Guide } from '@camaleaun/draw';
import { toElements } from './blocks';
import { box, lockedRatio, moved, pageColor, placed, resolve, color, type Design, type Element, type Mode } from './model';
import { Shape } from './Shape';

const HANDLES = [ 'nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w' ];
const SNAP = 5;

type Drag = { kind: 'move' | 'resize' | 'point'; id: string; handle?: string; end?: 1 | 2; start: { x: number; y: number }; origin: Element; wasSelected: boolean; moved: boolean };

type Props = {
	design: Design;
	mode: Mode;
	sample: Record< string, string >;
	zoom: number;
	// The sides of the page's edge that are drawn (a page that fills the area has its edge over the stage's border, which would double it): the left
	// and right lines (x), the top and bottom ones (y). With a bleed the edge is the cut line and is always drawn whole.
	edges?: { x: boolean; y: boolean };
	svgRef: React.RefObject< SVGSVGElement >;
	selRef: React.Ref< SVGRectElement >;
	tab: string;
	/** The text being edited in place: not drawn under its editor, which the editor draws (`overlay`). */
	editingId: string | null;
	overlay: React.ReactNode;
	onEdit: ( el: Element ) => void;
	/** What the editor draws in place of a picture with nothing chosen yet (a foreignObject, or null for the usual drawing). */
	placeholder: ( el: Element, select: () => void, startMove: ( event: React.PointerEvent ) => void ) => React.ReactNode;
};

const diff = ( before: Record< string, unknown >, after: Record< string, unknown > ) => Object.fromEntries( Object.entries( after ).filter( ( [ key, value ] ) => JSON.stringify( before[ key ] ) !== JSON.stringify( value ) ) );

export default function BlockCanvas( { design, mode, sample, zoom, edges = { x: true, y: true }, svgRef, selRef, tab, editingId, overlay, onEdit, placeholder }: Props ) {
	const { blocks, selected } = ( useSelect as any )(
		( select: any ) => {
			const store = select( blockEditorStore );
			return { blocks: store.getBlocks(), selected: store.getSelectedBlockClientId() };
		},
		[]
	) as { blocks: any[]; selected: string | null };
	const { updateBlockAttributes, selectBlock } = ( useDispatch as any )( blockEditorStore ) as { updateBlockAttributes: ( id: string, patch: Record< string, unknown > ) => void; selectBlock: ( id: string ) => void };
	const dragRef = useRef< Drag | null >( null );
	const [ preview, setPreview ] = useState< Element | null >( null );
	const [ guides, setGuides ] = useState< Guide[] >( [] );

	const { w, h, safe, bleed } = design.page;
	const stored = toElements( blocks );
	const resolved = stored.map( ( el ) => resolve( el, design.page ) );
	const shownEls = resolved.map( ( el ) => ( preview && preview.id === el.id ? preview : el ) );
	const current = shownEls.find( ( el ) => el.id === selected ) || null;
	const selBox = current ? box( current ) : null;
	const bg = pageColor( design, mode );

	const toPage = ( event: { clientX: number; clientY: number } ) => {
		const rect = svgRef.current!.getBoundingClientRect();
		return { x: ( event.clientX - rect.left ) / zoom - bleed, y: ( event.clientY - rect.top ) / zoom - bleed };
	};
	const start = ( event: React.PointerEvent, drag: Omit< Drag, 'wasSelected' | 'moved' > ) => {
		event.stopPropagation();
		( event.target as Element & globalThis.Element ).setPointerCapture?.( event.pointerId );
		dragRef.current = { ...drag, wasSelected: selected === drag.id, moved: false };
	};
	const select = ( id: string ) => selectBlock( id );

	const onMove = ( event: React.PointerEvent ) => {
		const drag = dragRef.current;
		if ( ! drag ) {
			return;
		}
		const p = toPage( event );
		const dx = p.x - drag.start.x;
		const dy = p.y - drag.start.y;
		let next: Element = drag.origin;
		let found: Guide[] = [];
		if ( drag.kind === 'move' ) {
			next = moved( drag.origin, dx, dy );
			if ( ! event.altKey ) {
				const others = resolved.filter( ( el ) => el.id !== drag.id && ! el.hidden && el.type !== 'line' ).map( ( el ) => box( el ) );
				const s = snap( box( next ), design.page, others, SNAP / zoom );
				next = moved( next, s.dx, s.dy );
				found = s.guides;
			}
		} else if ( drag.kind === 'resize' && drag.origin.type !== 'line' ) {
			const b = resizeBox( box( drag.origin ), drag.handle || 'se', dx, dy, event.shiftKey || drag.origin.type === 'qr' || !! lockedRatio( drag.origin ), event.altKey );
			next = { ...drag.origin, ...b };
		} else if ( drag.origin.type === 'line' ) {
			const key = drag.end === 1 ? { x: 'x1', y: 'y1' } : { x: 'x2', y: 'y2' };
			let nx = ( drag.origin as any )[ key.x ] + dx;
			let ny = ( drag.origin as any )[ key.y ] + dy;
			if ( event.shiftKey ) {
				const ox = drag.end === 1 ? drag.origin.x2 : drag.origin.x1;
				const oy = drag.end === 1 ? drag.origin.y2 : drag.origin.y1;
				const angle = Math.round( Math.atan2( ny - oy, nx - ox ) / ( Math.PI / 4 ) ) * ( Math.PI / 4 );
				const len = Math.hypot( nx - ox, ny - oy );
				nx = ox + Math.cos( angle ) * len;
				ny = oy + Math.sin( angle ) * len;
			}
			next = { ...drag.origin, [ key.x ]: nx, [ key.y ]: ny } as Element;
		}
		drag.moved = drag.moved || Math.abs( dx ) + Math.abs( dy ) > 1 / zoom;
		setGuides( found );
		setPreview( next );
	};

	const onUp = () => {
		const drag = dragRef.current;
		dragRef.current = null;
		if ( drag && preview && drag.moved ) {
			// One update of the block: absolute elements take the numbers, anchored ones update their anchors (docs/decisions/0004).
			const before = stored.find( ( el ) => el.id === drag.id ) as Element;
			const after = drag.origin.type === 'line' ? preview : placed( before, box( preview ), design.page );
			updateBlockAttributes( drag.id, diff( before as never, after as never ) );
		} else if ( drag && drag.wasSelected && ! drag.moved && drag.origin.type === 'text' ) {
			onEdit( drag.origin );
		}
		setPreview( null );
		setGuides( [] );
	};

	return (
		<svg ref={ svgRef } className="evcert-canvas" width={ ( w + bleed * 2 ) * zoom } height={ ( h + bleed * 2 ) * zoom } viewBox={ `${ -bleed } ${ -bleed } ${ w + bleed * 2 } ${ h + bleed * 2 }` } onPointerMove={ onMove } onPointerUp={ onUp }>
			<defs>
				<pattern id="evcert-checker" width="12" height="12" patternUnits="userSpaceOnUse">
					<rect width="12" height="12" fill="#ffffff" />
					<rect width="6" height="6" fill="#f0f0f1" />
					<rect x="6" y="6" width="6" height="6" fill="#f0f0f1" />
				</pattern>
				<clipPath id="evcert-page">
					<rect x={ -bleed } y={ -bleed } width={ w + bleed * 2 } height={ h + bleed * 2 } />
				</clipPath>
			</defs>
			<rect className="evcert-sheet" x={ 0 } y={ 0 } width={ w } height={ h } fill={ bg === 'transparent' || bg === 'none' ? 'url(#evcert-checker)' : bg } />
			<g clipPath="url(#evcert-page)">
				{ shownEls.map( ( el ) => {
					if ( el.hidden ) {
						return null;
					}
					if ( editingId === el.id && el.type === 'text' ) {
						return null;
					}
					const begin = ( event: React.PointerEvent ) => start( event, { kind: 'move', id: el.id, start: toPage( event ), origin: el } );
					const ph = el.type === 'image' && ! el.src && el.slot !== 'logo' ? placeholder( el, () => select( el.id ), begin ) : null;
					if ( ph ) {
						return <g key={ el.id }>{ ph }</g>;
					}
					return (
						<g
							key={ el.id }
							className={ `evcert-el${ el.locked ? ' is-locked' : '' }` }
							onDoubleClick={ () => onEdit( el ) }
							onPointerDown={ ( event ) => {
								if ( tab === 'content' && el.type !== 'text' ) {
									return;
								}
								event.stopPropagation();
								select( el.id );
								if ( tab === 'content' ) {
									onEdit( el );
									return;
								}
								if ( ! el.locked ) {
									begin( event );
								} else {
									dragRef.current = null;
								}
							} }
						>
							{ el.rotation && el.type !== 'line' ? (
								<g transform={ `rotate(${ el.rotation } ${ el.x + el.w / 2 } ${ el.y + el.h / 2 })` }>
									<Shape el={ el } design={ design } mode={ mode } sample={ sample } />
								</g>
							) : (
								<Shape el={ el } design={ design } mode={ mode } sample={ sample } />
							) }
						</g>
					);
				} ) }
				{ overlay }
			</g>
			<g className="evcert-guides" pointerEvents="none">
				{ bleed > 0 || ( edges.x && edges.y ) ? (
					<rect x={ 0 } y={ 0 } width={ w } height={ h } className={ bleed > 0 ? 'evcert-trim has-bleed' : 'evcert-trim' } />
				) : (
					<>
						{ edges.x && <line x1={ 0 } y1={ 0 } x2={ 0 } y2={ h } className="evcert-trim" /> }
						{ edges.x && <line x1={ w } y1={ 0 } x2={ w } y2={ h } className="evcert-trim" /> }
						{ edges.y && <line x1={ 0 } y1={ 0 } x2={ w } y2={ 0 } className="evcert-trim" /> }
						{ edges.y && <line x1={ 0 } y1={ h } x2={ w } y2={ h } className="evcert-trim" /> }
					</>
				) }
				{ /* The safe margin is drawn only when the page has one. */ }
				{ safe > 0 && <rect x={ safe } y={ safe } width={ w - safe * 2 } height={ h - safe * 2 } className="evcert-safe" /> }
				{ guides.map( ( g, i ) =>
					g.kind === 'gap' ? null : g.axis === 'x' ? <line key={ i } x1={ g.at } x2={ g.at } y1={ -bleed } y2={ h + bleed } className="evcert-snap" /> : <line key={ i } y1={ g.at } y2={ g.at } x1={ -bleed } x2={ w + bleed } className="evcert-snap" />
				) }
			</g>
			{ current && selBox && tab === 'art' && ! editingId && (
				<g className="evcert-selection">
					<rect ref={ selRef } x={ selBox.x } y={ selBox.y } width={ Math.max( 1, selBox.w ) } height={ Math.max( 1, selBox.h ) } />
					{ current.type === 'line'
						? ( [ 1, 2 ] as const ).map( ( end ) => (
								<circle key={ end } cx={ end === 1 ? current.x1 : current.x2 } cy={ end === 1 ? current.y1 : current.y2 } r={ 5 / zoom } onPointerDown={ ( event ) => start( event, { kind: 'point', id: current.id, end, start: toPage( event ), origin: current } ) } />
						  ) )
						: ! current.locked &&
						  HANDLES.map( ( hd ) => {
								const hx = selBox.x + ( hd.includes( 'w' ) ? 0 : hd.includes( 'e' ) ? selBox.w : selBox.w / 2 );
								const hy = selBox.y + ( hd.includes( 'n' ) ? 0 : hd.includes( 's' ) ? selBox.h : selBox.h / 2 );
								const s = 8 / zoom;
								return <rect key={ hd } className={ `evcert-handle is-${ hd }` } x={ hx - s / 2 } y={ hy - s / 2 } width={ s } height={ s } onPointerDown={ ( event ) => start( event, { kind: 'resize', id: current.id, handle: hd, start: toPage( event ), origin: current } ) } />;
						  } ) }
				</g>
			) }
		</svg>
	);
}
