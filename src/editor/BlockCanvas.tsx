/**
 * The canvas of the certificate editor on the block editor's store (docs/decisions/0046): the elements it draws are the blocks of the
 * store, a selection is the store's, and what a drag or a resize ends in is one update of the attributes of a block. While the
 * pointer is down the box follows it as a preview, so the history keeps one step for the gesture.
 */
import { useRef, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { resizeBox, snap, type Guide } from '@camaleaun/draw';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { toElements, type Selection } from './blocks';
import { clickTarget, rangeIds, toggleIds } from './selection';
import { box, lockedRatio, moved, pageColor, placed, type Design, type Element, type GroupEl, type Mode } from './model';
import { Shape } from './Shape';
import { VARIATIONS } from './GroupPanel';
import { findEl, flatten, innerPage, isGroup, mapEl, pageBox, parentOf, resolveTree, type Placed } from './tree';

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
	/** The group that has just been put in and has no layout chosen yet: it shows the choice (as the core's Group does) until one is made. */
	choosing: string | null;
	onChoose: ( id: string, arrange: GroupEl[ 'arrange' ] ) => void;
	/** Puts the selected elements in a group (the editor knows how). */
	onGroup: ( ids: string[], arrange?: GroupEl[ 'arrange' ] ) => void;
	/** What the editor draws in place of a picture with nothing chosen yet (a foreignObject, or null for the usual drawing). */
	placeholder: ( el: Element, select: () => void, startMove: ( event: React.PointerEvent ) => void ) => React.ReactNode;
	/** What is selected (the editor's list of ids and where a range starts), and how to change it. */
	selection: Selection;
	onSelect: ( ids: string[], anchor: string | null ) => void;
};

const diff = ( before: Record< string, unknown >, after: Record< string, unknown > ) => Object.fromEntries( Object.entries( after ).filter( ( [ key, value ] ) => JSON.stringify( before[ key ] ) !== JSON.stringify( value ) ) );

export default function BlockCanvas( { design, mode, sample, zoom, edges = { x: true, y: true }, svgRef, selRef, tab, editingId, overlay, onEdit, placeholder, choosing, onChoose, onGroup, selection, onSelect }: Props ) {
	const blocks = ( useSelect as any )( ( select: any ) => select( blockEditorStore ).getBlocks(), [] ) as any[];
	// What is selected is the editor's own list (the store can only hold a run of siblings).
	const selected = selection.ids.length === 1 ? selection.ids[ 0 ] : null;
	const many = selection.ids.length > 1 ? selection.ids : [];
	const { updateBlockAttributes } = ( useDispatch as any )( blockEditorStore ) as { updateBlockAttributes: ( id: string, patch: Record< string, unknown > ) => void };
	const dragRef = useRef< Drag | null >( null );
	const [ preview, setPreview ] = useState< Element | null >( null );
	const [ guides, setGuides ] = useState< Guide[] >( [] );

	const { w, h, safe, bleed } = design.page;
	const stored = toElements( blocks );
	const resolved = resolveTree( stored, design.page );
	// The elements as they are drawn: with the preview of a drag in the place of the one that is dragged (it keeps where its group's corner is).
	const shownTree = preview ? ( mapEl( resolved, preview.id, ( el ) => ( { ...preview, ox: ( el as Placed ).ox, oy: ( el as Placed ).oy } as unknown as Element ) ) as Placed[] ) : resolved;
	const current = ( findEl( shownTree, selected ) as Placed | undefined ) || null;
	const selBox = current ? pageBox( current ) : null;
	// The box of all the selected elements together.
	const manyBoxes = many.map( ( id ) => findEl( shownTree, id ) as Placed | undefined ).filter( Boolean ).map( ( el ) => pageBox( el as Placed ) );
	const manyBox = manyBoxes.length
		? { x: Math.min( ...manyBoxes.map( ( b ) => b.x ) ), y: Math.min( ...manyBoxes.map( ( b ) => b.y ) ), w: Math.max( ...manyBoxes.map( ( b ) => b.x + b.w ) ) - Math.min( ...manyBoxes.map( ( b ) => b.x ) ), h: Math.max( ...manyBoxes.map( ( b ) => b.y + b.h ) ) - Math.min( ...manyBoxes.map( ( b ) => b.y ) ) }
		: null;
	// What a row, a stack or a grid places is not dragged or resized by hand: the layers and the group's own settings decide it.
	const flowed = ( el: Element ) => {
		const g = parentOf( shownTree, el.id );
		return !! g && [ 'row', 'stack', 'grid' ].includes( g.arrange || '' );
	};
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
	const select = ( id: string ) => onSelect( [ id ], id );

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
				const mine = flatten( [ drag.origin ] ).map( ( e ) => e.id );
				const others = flatten( resolved as Element[] ).filter( ( el ) => ! mine.includes( el.id ) && ! el.hidden && el.type !== 'line' ).map( ( el ) => pageBox( el as Placed ) );
				const s = snap( pageBox( next as Placed ), design.page, others, SNAP / zoom );
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
			const before = findEl( stored, drag.id ) as Element;
			// What is in a group is placed from the group's corner, and anchored to its size.
			const holder = parentOf( resolved, drag.id );
			const space = holder ? innerPage( holder ) : design.page;
			const after = drag.origin.type === 'line' ? preview : placed( before, box( preview ), space as never );
			updateBlockAttributes( drag.id, diff( before as never, after as never ) );
		} else if ( drag && drag.wasSelected && ! drag.moved && drag.origin.type === 'text' ) {
			onEdit( drag.origin );
		}
		setPreview( null );
		setGuides( [] );
	};


	const extend = ( event: React.PointerEvent, id: string ) => {
		// Shift selects a range, in the order of the list, from where the selection started to this one; Cmd or Ctrl adds this one or takes it out (as the post editor and the layers do).
		// Both ends are lifted to one list when they are in different groups.
		if ( ! selection.ids.length || ( ! event.shiftKey && ! event.metaKey && ! event.ctrlKey ) ) {
			return false;
		}
		event.stopPropagation();
		if ( event.metaKey || event.ctrlKey ) {
			onSelect( toggleIds( shownTree, selection.ids, id ), selection.anchor ?? selection.ids[ 0 ] );
		} else {
			const anchor = selection.anchor ?? selection.ids[ 0 ];
			onSelect( rangeIds( shownTree, anchor, id ), anchor );
		}
		return true;
	};

	/** An element as it is drawn; a group is its own box, and what is in it from its corner. */
	const draw = ( el: Placed ): React.ReactNode => {
		if ( el.hidden || ( editingId === el.id && el.type === 'text' ) ) {
			return null;
		}
		// A click on what is in a group selects the group (the outermost one) and drags it; a double click goes down one level, as in Figma. What is already selected stays the target.
		const chain: Placed[] = [ el ];
		for ( let up = parentOf( shownTree, el.id ); up; up = parentOf( shownTree, up.id ) ) {
			chain.unshift( up as unknown as Placed );
		}
		const at = chain.findIndex( ( c ) => c.id === selected || many.includes( c.id ) );
		const target = tab === 'content' ? el : ( findEl( shownTree, clickTarget( shownTree, el.id, selection.ids ) ) as Placed ) || chain[ 0 ];
		const begin = ( event: React.PointerEvent ) => start( event, { kind: 'move', id: target.id, start: toPage( event ), origin: target } );
		const ph = el.type === 'image' && ! el.src && el.slot !== 'logo' ? placeholder( el, () => select( target.id ), begin ) : null;
		if ( ph ) {
			return <g key={ el.id }>{ ph }</g>;
		}
		const shape = <Shape el={ el } design={ design } mode={ mode } sample={ sample } />;
		const inner = isGroup( el ) ? (
			<g transform={ `translate(${ el.x } ${ el.y })` } clipPath={ el.clip ? `url(#evcert-clip-${ el.id })` : undefined }>
				{ el.clip && (
					<clipPath id={ `evcert-clip-${ el.id }` }>
						<rect width={ Math.max( 0, el.w ) } height={ Math.max( 0, el.h ) } />
					</clipPath>
				) }
				{ ( el.children as Placed[] ).map( draw ) }
				{ choosing === el.id && ! ( el.children || [] ).length && <Variations group={ el } onChoose={ ( arrange ) => onChoose( el.id, arrange ) } /> }
			</g>
		) : null;
		const whole = (
			<>
				{ shape }
				{ inner }
			</>
		);
		return (
			<g
				key={ el.id }
				className={ `evcert-el${ el.locked ? ' is-locked' : '' }` }
				onDoubleClick={ ( event ) => {
					event.stopPropagation();
					// Inside a selected group: the next level down toward what was clicked; at the bottom, edit it.
					const down = tab !== 'content' && at >= 0 && at + 1 < chain.length ? chain[ at + 1 ] : null;
					if ( down ) {
						select( down.id );
						return;
					}
					onEdit( el );
				} }
				onPointerDown={ ( event ) => {
					if ( tab === 'content' && el.type !== 'text' ) {
						return;
					}
					event.stopPropagation();
					if ( ( event.shiftKey || event.metaKey || event.ctrlKey ) && extend( event, target.id ) ) {
						return;
					}
					select( target.id );
					if ( tab === 'content' ) {
						onEdit( el );
						return;
					}
					if ( ! target.locked && ! flowed( target ) ) {
						begin( event );
					} else {
						dragRef.current = null;
					}
				} }
			>
				{ el.rotation && el.type !== 'line' ? <g transform={ `rotate(${ el.rotation } ${ el.x + el.w / 2 } ${ el.y + el.h / 2 })` }>{ whole }</g> : whole }
			</g>
		);
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
				{ shownTree.map( draw ) }
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
			{ many.length > 1 && tab === 'art' && ! editingId && (
				<g className="evcert-selection is-many">
					{ /* Each selected element is a fill with some opacity (as the post editor's selected blocks); they are not drawn while one is dragged, so the guides can be seen. */ }
					{ ! dragRef.current?.moved &&
						many.map( ( id ) => {
							const el = findEl( shownTree, id ) as Placed | undefined;
							const b = el ? pageBox( el ) : null;
							return b ? <rect key={ id } className="evcert-selection__fill" x={ b.x } y={ b.y } width={ Math.max( 4 / zoom, b.w ) } height={ Math.max( 4 / zoom, b.h ) } /> : null;
						} ) }
					{ /* The box of them all: its outline, and where the toolbar is anchored. */ }
					{ manyBox && <rect ref={ selRef } className="evcert-selection__bounds" x={ manyBox.x } y={ manyBox.y } width={ Math.max( 1, manyBox.w ) } height={ Math.max( 1, manyBox.h ) } /> }
				</g>
			) }
			{ current && selBox && tab === 'art' && ! editingId && (
				<g className="evcert-selection">
					<rect ref={ selRef } x={ selBox.x } y={ selBox.y } width={ Math.max( 1, selBox.w ) } height={ Math.max( 1, selBox.h ) } />
					{ current.type === 'line'
						? ( [ 1, 2 ] as const ).map( ( end ) => (
								<circle key={ end } cx={ end === 1 ? current.x1 : current.x2 } cy={ end === 1 ? current.y1 : current.y2 } r={ 5 / zoom } onPointerDown={ ( event ) => start( event, { kind: 'point', id: current.id, end, start: toPage( event ), origin: current } ) } />
						  ) )
						: ! current.locked && ! flowed( current ) &&
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

/** The core's choice of a layout for a group that has just been put in: Group, Row, Stack or Grid, in that order. */
function Variations( { group, onChoose }: { group: GroupEl; onChoose: ( arrange: GroupEl[ 'arrange' ] ) => void } ) {
	const options = VARIATIONS;
	return (
		<foreignObject x={ 0 } y={ 0 } width={ Math.max( 0, group.w ) } height={ Math.max( 0, group.h ) } onPointerDown={ ( event ) => event.stopPropagation() }>
			<div className="evcert-variations" role="group" aria-label={ __( 'Choose a layout for the group', 'events-certificates' ) }>
				<p className="evcert-variations__title">{ __( 'Group: choose a layout', 'events-certificates' ) }</p>
				<div className="evcert-variations__list">
					{ options.map( ( o ) => (
						<Button key={ o.label } className="evcert-variations__option" icon={ o.icon } iconSize={ 24 } label={ o.label } showTooltip onClick={ () => onChoose( o.arrange ) }>
							<span>{ o.label }</span>
						</Button>
					) ) }
				</div>
			</div>
		</foreignObject>
	);
}
