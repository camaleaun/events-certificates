/**
 * The design editor: the page as an SVG that fills the window, elements
 * selected and dragged with snapping to the page centre, the safe margin and
 * the other elements, and a floating Dock with everything else.
 */
import { fontStack, weightFor } from './fonts';
import { darkEnabled, declarationEnabled } from './darkMode';
import apiFetch from '@wordpress/api-fetch';
import { Button, ColorPalette, DropdownMenu, Icon, MenuGroup, MenuItem, Popover, Snackbar, Spinner, RangeControl, SelectControl, TextControl, ToggleControl, __experimentalConfirmDialog as ConfirmDialog } from '@wordpress/components';
import { store as commandsStore, useCommand } from '@wordpress/commands';
import { dispatch as wpDispatch } from '@wordpress/data';
import { check, chevronLeft, closeSmall, copy, download, drawerRight, external, lineSolid, listView, moreVertical, pages, plus, redo as redoIcon, seen, styles, trash, undo as undoIcon, upload } from '@wordpress/icons';
import { useCallback, useEffect, useLayoutEffect, useMemo, useReducer, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { elementIcon } from './icons';
import {
	box,
	color,
	create,
	moved,
	newId,
	placed,
	fromView,
	migrate,
	toView,
	svgPaths,
	resolve,
	rectPath,
	runsHtml,
	runsOf,
	type Box,
	type Design,
	type Element,
	type Insertable,
	type Mode,
	type Run,
	type TextEl,
	type GroupEl,
	type View, lockedRatio, isRequired, toStored, type SvgPath, type Paint, pageColor, baseBack, fromSide, toSide, type Side } from './model';
import DesignPanel, { setSample } from './DesignPanel';
import ElementToolbar from './ElementToolbar';
import FieldText, { type FieldTextCommands } from './FieldText';
import Inserter from './Inserter';
import { ImagePlaceholder } from './ImageSource';
import { QR_PLACEHOLDER } from './qr-placeholder';
import ImportModal, { type Imported } from './ImportModal';
import Inspector from './Inspector';
import PreviewDropdown from './PreviewDropdown';
import SvgEditor from './SvgEditor';
import type { Picked } from './media';
import { parse, serialize } from '@wordpress/blocks';
import { BlockLayers, BlocksProvider, registerBlocks, toBlocks, toElements, type Selection } from './blocks';
import { adoptColors, CLIPBOARD_TYPE, copyPayload, pasteEls, plainTextOf, type ClipboardPayload } from './clipboard';
import { runsFromText } from './rich';
import { FIELD_KEYS } from './fieldKeys';
import { BLOCK_NAMES } from './dialect';
import { canGroupIds, escapeIds, selectAllIds, selectionType } from './selection';
import SelectionToolbar from './SelectionToolbar';
import BlockCanvas from './BlockCanvas';
import { bakeChildren, cloneEl, duplicateEls, nudgeEls, removeEls, reorderEls, setFlag, findEl, holderFor, insertInto, flatten, groupEls, innerPage, insertAfter, isGroup, mapEl, parentOf, removeEl, reorderEl, resolveTree, ungroupEl, canHold, MAX_ELEMENTS, type Placed } from './tree';
import { cleanForSlug } from '@wordpress/url';
import { fromMarkup, isMarkup, lineHeightFactor, toMarkup } from '@camaleaun/draw';
import { BLOCK_OPTIONS, fromFile, toFile } from './dialect';
import { baseDesign, blankDesign, stockName, withRequired } from './base';
import { verificationParts } from './verification';
import { Shape, textStyle, LOCALE } from './Shape';
import { fitScale, isFit, ZOOM_MAX, ZOOM_MIN, type FitMode, type ZoomMode } from './zoom';
import './style.scss';

// The block types of a drawing, known to the block editor only to be listed (the canvas draws them): docs/decisions/0046.
registerBlocks( { text: __( 'Text', 'events-certificates' ), rect: __( 'Rectangle', 'events-certificates' ), ellipse: __( 'Ellipse', 'events-certificates' ), group: __( 'Group', 'events-certificates' ), path: __( 'Shape', 'events-certificates' ), line: __( 'Line', 'events-certificates' ), image: __( 'Image', 'events-certificates' ), svg: __( 'SVG', 'events-certificates' ), qr: __( 'QR code', 'events-certificates' ), signature: __( 'Signature', 'events-certificates' ), verification: __( 'Verification', 'events-certificates' ), 'text:date-of-emission': __( 'Date of emission', 'events-certificates' ) } );

declare global {
	interface Window {
		// development: unfinished features, off unless a constant turns them on (includes/admin-app.php).
		evcertEditor: { locales?: { locale: string; name: string }[]; design: number; base?: Design; exitUrl: string; urls?: Record< string, string >; families?: import( './fonts' ).FontFamily[]; sample: Record< string, string >; roles: Record< string, string >; isDefault: boolean; development?: { screenViews?: boolean } };
	}
}

type History = { past: Design[]; present: Design; future: Design[] };
type Action =
	| { type: 'load'; design: Design }
	| { type: 'commit'; design: Design }
	| { type: 'undo' }
	| { type: 'redo' };

function historyReducer( state: History, action: Action ): History {
	switch ( action.type ) {
		case 'load':
			return { past: [], present: action.design, future: [] };
		case 'commit':
			if ( action.design === state.present ) {
				return state;
			}
			return { past: [ ...state.past.slice( -99 ), state.present ], present: action.design, future: [] };
		case 'undo':
			if ( ! state.past.length ) {
				return state;
			}
			return { past: state.past.slice( 0, -1 ), present: state.past[ state.past.length - 1 ], future: [ state.present, ...state.future ] };
		case 'redo':
			if ( ! state.future.length ) {
				return state;
			}
			return { past: [ ...state.past, state.present ], present: state.future[ 0 ], future: state.future.slice( 1 ) };
	}
	return state;
}

/** A Cmd (Mac) or Ctrl shortcut as shown in menus. */
const primary = ( key: string ) => ( /Mac|iP(hone|ad)/.test( navigator.platform ) ? `⌘${ key }` : `Ctrl+${ key }` );
/** Shift + Cmd (Mac) or Ctrl + Shift, as the core shows them. */
const primaryShift = ( key: string ) => ( /Mac|iP(hone|ad)/.test( navigator.platform ) ? `⇧⌘${ key }` : `Ctrl+Shift+${ key }` );
const displayShortcut = { primary, primaryShift };


/** The fits of the page, in the order of the menu (and of the palette). */
const fitLabels = (): Array< [ FitMode, string ] > => [
	[ 'horizontal', __( 'Fit horizontal', 'events-certificates' ) ],
	[ 'vertical', __( 'Fit vertical', 'events-certificates' ) ],
	[ 'view', __( 'Fit view', 'events-certificates' ) ],
];

/** Zoom in the footer: out, the level (a menu: the three fits, 50–200%), in. The level shown is the real one, also in a fit. */
function ZoomControl( { zoom, zoomMode, onZoom, onFit }: { zoom: number; zoomMode: ZoomMode; onZoom: ( z: number ) => void; onFit: ( mode: FitMode ) => void } ) {
	return (
		<div className="evcert-zoom">
			<Button size="small" icon={ lineSolid } label={ __( 'Zoom out', 'events-certificates' ) } shortcut={ displayShortcut.primary( '-' ) } onClick={ () => onZoom( zoom / 1.25 ) } disabled={ zoom <= ZOOM_MIN } />
			<DropdownMenu
				icon={ null }
				label={ __( 'Zoom', 'events-certificates' ) }
				text={ `${ Math.round( zoom * 100 ) }%` }
				toggleProps={ { size: 'small', className: 'evcert-zoom__level' } as any }
				popoverProps={ { placement: 'top-end' } }
			>
				{ ( { onClose } ) => (
					<>
						<MenuGroup>
							{ fitLabels().map( ( [ mode, label ] ) => (
								<MenuItem
									key={ mode }
									role="menuitemradio"
									icon={ zoomMode === mode ? check : undefined }
									isSelected={ zoomMode === mode }
									shortcut={ mode === 'view' ? displayShortcut.primary( '0' ) : undefined }
									onClick={ () => { onFit( mode ); onClose(); } }
								>
									{ label }
								</MenuItem>
							) ) }
						</MenuGroup>
						<MenuGroup>
							{ [ 0.5, 1, 2 ].map( ( z ) => (
								<MenuItem
									key={ z }
									role="menuitemradio"
									icon={ zoomMode === z ? check : undefined }
									isSelected={ zoomMode === z }
									shortcut={ z === 1 ? displayShortcut.primary( '1' ) : undefined }
									onClick={ () => { onZoom( z ); onClose(); } }
								>
									{ `${ z * 100 }%` }
								</MenuItem>
							) ) }
						</MenuGroup>
					</>
				) }
			</DropdownMenu>
			<Button size="small" icon={ plus } label={ __( 'Zoom in', 'events-certificates' ) } shortcut={ displayShortcut.primary( '+' ) } onClick={ () => onZoom( zoom * 1.25 ) } disabled={ zoom >= ZOOM_MAX } />
		</div>
	);
}

export default function Editor() {
	const config = window.evcertEditor;
	const [ history, dispatch ] = useReducer( historyReducer, { past: [], present: baseDesign(), future: [] } );
	// The stored design, and the design as the current view shows it (the print page or a screen).
	const stored = history.present;
	const [ view, setView ] = useState< View >( { device: 'page', orientation: 'landscape' } );
	// The sheet being designed: the certificate (front) or the declaration page (back).
	const [ side, setSide ] = useState< Side >( 'front' );
	const design = useMemo( () => toView( toSide( stored, side ), view ), [ stored, view, side ] );
	const shown = design;
	// Preview values: the design's own, where set (an empty one means not set), over the defaults.
	const sample = useMemo( () => ( { ...config.sample, ...Object.fromEntries( Object.entries( design.sample || {} ).filter( ( [ , v ] ) => v !== '' ) ) } ), [ design.sample ] );
	// Anchored elements resolved to x/y/w/h for drawing, hit testing and snapping.
	// The elements placed (what is in a group from its corner: `ox`, `oy` say where that is on the page).
	const resolvedShown = useMemo( () => ( { ...shown, elements: resolveTree( shown.elements, shown.page ) as Element[] } ), [ shown ] );
	// The group that has just been put in and has no layout chosen yet.
	const [ choosing, setChoosing ] = useState< string | null >( null );
	// What is selected: the editor's own list of ids (the block editor's store can only hold a run of siblings), and where a range starts.
	const [ selection, setSelection ] = useState< Selection >( { ids: [], anchor: null } );
	// One element (what the Inspector and the toolbar show), or several (they can be put in a group, moved, copied or deleted together).
	const selected = selection.ids.length === 1 ? selection.ids[ 0 ] : null;
	const selectedMany = selection.ids.length > 1 ? selection.ids : [];
	const setSelected = ( id: string | null ) => setSelection( id ? { ids: [ id ], anchor: id } : { ids: [], anchor: null } );
	const selectIds = ( ids: string[], anchor: string | null = ids[ 0 ] ?? null ) => setSelection( { ids, anchor } );
	const [ mode, setMode ] = useState< Mode >( 'light' );
	// One mode, like the block editor: click selects and drags, double-click edits text.
	const tab = 'art' as 'content' | 'art';
	const [ panel, setPanel ] = useState< 'insert' | 'layers' | null >( null );
	// The Settings aside starts closed (docs/decisions/0049).
	const [ sidebar, setSidebar ] = useState< 'design' | 'element' | null >( null );
		const [ editing, setEditing ] = useState< { id: string; runs: Run[] } | null >( null );
	const textCommands = useRef< FieldTextCommands | null >( null );
	const [ marks, setMarks ] = useState( { b: false, i: false } );
	const [ drawing, setDrawing ] = useState< string | null >( null );
	const [ notice, setNotice ] = useState< string | null >( null );
	const [ importing, setImporting ] = useState( false );
	const [ restoring, setRestoring ] = useState( false );
	const [ editAnchor, setEditAnchor ] = useState< HTMLElement | null >( null );
	useEffect( () => {
		setEditAnchor( editing ? ( document.querySelector( '.evcert-text--editing' ) as HTMLElement | null ) : null );
	}, [ editing?.id ] );
	const startEditing = ( el: Element ) => {
		if ( el.type === 'text' ) {
			setSelected( el.id );
			setSidebar( 'element' );
			setEditing( { id: el.id, runs: runsOf( el ) } );
		}
	};
	const stopEditing = () => {
		const original = editing ? findEl( design.elements, editing.id ) : null;
		// The date of emission with no words writes none: it is the date in the site's format.
		const nothing = !! original && original.slot === 'date-of-emission' && editing?.runs.every( ( run ) => 'text' in run && ! run.text.trim() );
		if ( editing && original && JSON.stringify( runsOf( original as TextEl ) ) !== JSON.stringify( editing.runs ) ) {
			update( editing.id, ( nothing ? { runs: undefined, text: undefined } : { runs: editing.runs, text: undefined } ) as Partial< Element > );
		}
		setEditing( null );
	};
	const [ title, setTitle ] = useState( '' );
	const [ status, setStatus ] = useState< 'loading' | 'saved' | 'dirty' | 'saving' | 'error' >( 'loading' );
	// A level, or a fit of the page in the visible area: the editor opens fitting the whole page.
	const [ zoomMode, setZoomMode ] = useState< ZoomMode >( 'view' );
	// The visible area of the stage without its padding (null until it is measured).
	const [ area, setArea ] = useState< { w: number; h: number } | null >( null );
	// The zoom is the level, or the scale of the fit in the area; a fit follows the area (the window, the panels that open and close).
	const zoom = useMemo( () => ( typeof zoomMode === 'number' ? zoomMode : ( area && fitScale( zoomMode, design.page, area ) ) || 1 ), [ zoomMode, area, design.page ] );
	const fit = useCallback( ( mode: FitMode ) => setZoomMode( mode ), [] );
	// Fit horizontal and Fit vertical use all the area, with no room and no shadow around the page; Fit view and the levels keep their room.
	const fullFit = zoomMode === 'horizontal' || zoomMode === 'vertical';
	// ...and no border either, as long as the page fills the area both ways; with a gap at the sides (Fit vertical of a page narrower than the
	// area) or above and below, the page keeps its shadow and its edge.
	const fillsX = fullFit && !! area && ( design.page.w + design.page.bleed * 2 ) * zoom >= area.w - 0.5;
	const fillsY = fullFit && !! area && ( design.page.h + design.page.bleed * 2 ) * zoom >= area.h - 0.5;
	const flush = fillsX && fillsY;
	const loaded = status !== 'loading';
	const svgRef = useRef< SVGSVGElement >( null );
	const wrapRef = useRef< HTMLDivElement >( null );
	const savedRef = useRef< Design | null >( null );
	const savedTitleRef = useRef( '' );
	// The roles this template applies to (Designs\ROLES_META); the default template has none.
	const [ roles, setRoles ] = useState< string[] >( [] );
	const savedRolesRef = useRef( '' );
	const selRef = useRef< SVGRectElement >( null );
	const [ anchor, setAnchor ] = useState< SVGRectElement | null >( null );
	useEffect( () => setAnchor( selRef.current ), [ selection.ids.join( ',' ), tab, zoom ] );

	// Load the design.
	useEffect( () => {
		apiFetch< { title: { raw: string }; content: { raw: string }; meta?: { _evcert_roles?: string[] } } >( { path: `/wp/v2/evcert-designs/${ config.design }?context=edit` } )
			.then( ( post ) => {
				let loaded = baseDesign();
				try {
					// Block markup (docs/decisions/0045), or the JSON an earlier version saved.
					const parsed = isMarkup( post.content.raw ) ? fromFile( fromMarkup( post.content.raw, BLOCK_OPTIONS ) ) : JSON.parse( post.content.raw );
					if ( parsed && Array.isArray( parsed.elements ) ) {
						loaded = parsed;
					}
				} catch ( e ) {}
				loaded = withRequired( migrate( loaded ) );
				savedRef.current = loaded;
				dispatch( { type: 'load', design: loaded } );
				savedTitleRef.current = post.title.raw;
				const loadedRoles = post.meta?._evcert_roles || [];
				savedRolesRef.current = loadedRoles.join( ',' );
				setRoles( loadedRoles );
				setTitle( post.title.raw );
				setStatus( 'saved' );
			} )
			.catch( () => setStatus( 'error' ) );
	}, [ config.design ] );

	// Unsaved: the design or its name differ from what was saved.
	useEffect( () => {
		if ( status !== 'loading' && status !== 'saving' ) {
			setStatus( stored === savedRef.current && title === savedTitleRef.current && roles.join( ',' ) === savedRolesRef.current ? 'saved' : 'dirty' );
		}
	}, [ stored, title, roles ] );

	/**
	 * Zooms to a level keeping the point under the pointer (or the stage's
	 * centre) where it is, like vector apps.
	 */
	const zoomTo = ( next: number, at?: { clientX: number; clientY: number } ) => {
		const wrap = wrapRef.current;
		const svg = svgRef.current;
		const z = Math.max( ZOOM_MIN, Math.min( ZOOM_MAX, next ) );
		if ( ! wrap || ! svg ) {
			setZoomMode( z );
			return;
		}
		const stage = wrap.getBoundingClientRect();
		const rect = svg.getBoundingClientRect();
		const px = at ? at.clientX : stage.left + stage.width / 2;
		const py = at ? at.clientY : stage.top + stage.height / 2;
		// The page point under (px, py), to put back there after the zoom.
		const ux = ( px - rect.left ) / zoom;
		const uy = ( py - rect.top ) / zoom;
		setZoomMode( z );
		requestAnimationFrame( () => {
			const after = svg.getBoundingClientRect();
			wrap.scrollLeft += after.left + ux * z - px;
			wrap.scrollTop += after.top + uy * z - py;
		} );
	};
	const zoomRef = useRef( zoomTo );
	zoomRef.current = zoomTo;

	// Cmd/Ctrl + wheel and trackpad pinch (a wheel with ctrlKey) zoom around the pointer.
	useEffect( () => {
		const wrap = wrapRef.current;
		if ( ! wrap ) {
			return;
		}
		const onWheel = ( event: WheelEvent ) => {
			if ( ! event.ctrlKey && ! event.metaKey ) {
				return;
			}
			event.preventDefault();
			zoomRef.current( zoomLevel.current * Math.exp( -event.deltaY * ( event.ctrlKey && ! event.metaKey ? 0.01 : 0.002 ) ), event );
		};
		wrap.addEventListener( 'wheel', onWheel, { passive: false } );
		return () => wrap.removeEventListener( 'wheel', onWheel );
	}, [ loaded ] );
	const zoomLevel = useRef( zoom );
	zoomLevel.current = zoom;
	// The visible area of the stage, without its padding (CSS has it, 24px): a fit follows it, so the window, and the panels that open and
	// close, refit the page without anything listening for them.
	useEffect( () => {
		const wrap = wrapRef.current;
		if ( ! wrap ) {
			return;
		}
		const measure = () => {
			const style = getComputedStyle( wrap );
			const w = wrap.clientWidth - parseFloat( style.paddingLeft ) - parseFloat( style.paddingRight );
			const h = wrap.clientHeight - parseFloat( style.paddingTop ) - parseFloat( style.paddingBottom );
			setArea( ( prev ) => ( prev && Math.abs( prev.w - w ) < 1 && Math.abs( prev.h - h ) < 1 ? prev : { w, h } ) );
		};
		measure();
		const observer = new ResizeObserver( measure );
		observer.observe( wrap );
		return () => observer.disconnect();
	}, [ loaded, fullFit ] );
	// Entering a fit shows the page from its top, centred; the area changing inside a fit keeps the same part of the page in view.
	const zoomSeen = useRef< { zoom: number; mode: ZoomMode } | null >( null );
	useLayoutEffect( () => {
		const wrap = wrapRef.current;
		const prev = zoomSeen.current;
		zoomSeen.current = { zoom, mode: zoomMode };
		if ( ! wrap || ! isFit( zoomMode ) ) {
			return;
		}
		if ( ! prev || prev.mode !== zoomMode ) {
			wrap.scrollTop = 0;
			wrap.scrollLeft = ( wrap.scrollWidth - wrap.clientWidth ) / 2;
		} else if ( prev.zoom !== zoom ) {
			wrap.scrollTop *= zoom / prev.zoom;
			wrap.scrollLeft *= zoom / prev.zoom;
		}
	}, [ zoom, zoomMode ] );

	// Space + drag pans the view (the hand tool).
	const [ panning, setPanning ] = useState( false );
	const panRef = useRef< { x: number; y: number; left: number; top: number } | null >( null );
	useEffect( () => {
		const typing = ( t: EventTarget | null ) => !! t && ( ( t as HTMLElement ).isContentEditable || [ 'INPUT', 'TEXTAREA', 'SELECT', 'BUTTON' ].includes( ( t as HTMLElement ).tagName ) );
		const down = ( event: KeyboardEvent ) => {
			if ( event.code === 'Space' && ! typing( event.target ) ) {
				event.preventDefault();
				setPanning( true );
			}
		};
		const up = ( event: KeyboardEvent ) => event.code === 'Space' && setPanning( false );
		window.addEventListener( 'keydown', down );
		window.addEventListener( 'keyup', up );
		return () => {
			window.removeEventListener( 'keydown', down );
			window.removeEventListener( 'keyup', up );
		};
	}, [] );


	const commit = ( next: Design ) => dispatch( { type: 'commit', design: fromSide( stored, fromView( toSide( stored, side ), next, view ), side ) } );
	/** Shows a sheet; the declaration page starts from the standard one the first time. */
	const showSide = ( next: Side ) => {
		if ( next === 'back' && ! stored.back ) {
			dispatch( { type: 'commit', design: { ...stored, back: { elements: baseBack( stored ) } } } );
		}
		setSelected( null );
		setSide( next );
	};
	const replaceDesign = ( next: Design ) => dispatch( { type: 'commit', design: next } );
	/** An element moved by dx, dy (an anchored one by its anchors, to the size of its group when it is in one). */
	// `from` is the element that is placed now (a copy is moved from the place of the one it copies): its box, and the group it is in, are looked up by its id.
	const shift = ( e: Element, dx: number, dy: number, from: Element = e ): Element => {
		const holder = parentOf( resolvedShown.elements, from.id );
		const at = findEl( resolvedShown.elements, from.id ) as Element | undefined;
		return e.layout && at ? placed( e, box( moved( at, dx, dy ) ), ( holder ? innerPage( holder ) : design.page ) as never ) : moved( e, dx, dy );
	};
	// The Fill and the Stroke last set on a rectangle or an ellipse: the next one starts with them (only while the editor is open).
	const paintRef = useRef< Paint >( { fill: '#000000', stroke: '' } );
	const update = ( id: string, patch: Partial< Element > ) => {
		const changed = findEl( design.elements, id );
		// A group that changes how it places what is in it (free, row, stack, grid): they keep the size they have, and start from the place they have.
		if ( changed && isGroup( changed ) && 'arrange' in patch && ( patch as Partial< GroupEl > ).arrange !== changed.arrange ) {
			const now = findEl( resolvedShown.elements, id ) as GroupEl | undefined;
			if ( now && ( now.children || [] ).length ) {
				patch = { ...patch, children: bakeChildren( now.children ) } as Partial< Element >;
			}
		}
		if ( changed && ( changed.type === 'rect' || changed.type === 'ellipse' ) ) {
			const { fill, stroke } = patch as Partial< Paint >;
			paintRef.current = { fill: fill ?? paintRef.current.fill, stroke: stroke ?? paintRef.current.stroke };
		}
		commit( { ...design, elements: mapEl( design.elements, id, ( el ) => ( { ...el, ...patch } as Element ) ) } );
	};
	const current = ( findEl( resolvedShown.elements, selected ) as Placed | undefined ) || null;
	const currentStored = findEl( design.elements, selected ) || null;
	// The group the selected element is in (resolved): in a row, a stack or a grid its place is not the element's to say.
	const currentHolder = current ? parentOf( resolvedShown.elements, current.id ) : null;

	/** Several elements deleted at once (one undo): what must stay (the code of authenticity…) is left, and stays selected. */
	const removeMany = ( ids: string[] ) => {
		const out = removeEls( design.elements, ids, ( el ) => isRequired( el, flatten( design.elements ) ) );
		if ( out.kept.length ) {
			setNotice( _n( 'Kept: it has to be on every certificate.', 'Kept: they have to be on every certificate.', out.kept.length, 'events-certificates' ) );
		}
		if ( out.list === design.elements ) {
			return;
		}
		commit( { ...design, elements: out.list } );
		selectIds( out.kept );
	};
	const remove = ( id: string ) => removeMany( [ id ] );
	/** The last paste: the same copy pasted again is moved a little more each time, so the copies do not hide each other. */
	const pasted = useRef< { key: string; n: number } >( { key: '', n: 0 } );
	/** Copy or cut: what is selected goes on the clipboard as elements (to paste here or in another template), as the blocks of the layers (text/html) and as the words of its texts. */
	const toClipboard = ( data: DataTransfer, cut: boolean ): boolean => {
		const payload = copyPayload( design.elements, design.page, selection.ids, design );
		if ( ! payload.elements.length ) {
			return false;
		}
		data.setData( CLIPBOARD_TYPE, JSON.stringify( { ...payload, cut } as ClipboardPayload ) );
		data.setData( 'text/html', serialize( toBlocks( payload.elements ) as never ) );
		data.setData( 'text/plain', plainTextOf( payload.elements ) );
		pasted.current = { key: '', n: 0 };
		if ( cut ) {
			removeMany( selection.ids );
		}
		return true;
	};
	/** What a paste brings: our own elements, the blocks of a certificate (as the layers copy them), or plain text, which is a new text. */
	const fromClipboard = ( data: DataTransfer ): { els: Element[]; key: string; cut: boolean } | null => {
		const own = data.getData( CLIPBOARD_TYPE );
		if ( own ) {
			try {
				const payload = JSON.parse( own ) as ClipboardPayload;
				if ( payload.version === 1 && Array.isArray( payload.elements ) && payload.elements.length ) {
					return { els: adoptColors( payload.elements, payload.source || { colors: {}, palette: [] }, design ), key: payload.elements.map( ( el ) => el.id ).join( ',' ), cut: !! payload.cut };
				}
			} catch ( e ) {
				// Not ours: read what else it holds.
			}
		}
		const html = data.getData( 'text/html' );
		if ( html.includes( '<!-- wp:events-certificates/' ) ) {
			const known = Object.values( BLOCK_NAMES );
			const blocks = ( parse( html ) as { name: string }[] ).filter( ( b ) => known.includes( b.name ) );
			if ( blocks.length ) {
				return { els: toElements( blocks as never ), key: '', cut: false };
			}
		}
		const text = data.getData( 'text/plain' );
		if ( text.trim() ) {
			const el = create( 'text', design, paintRef.current ) as TextEl;
			return { els: [ { ...el, runs: runsFromText( text, FIELD_KEYS ) as Run[] } as Element ], key: '', cut: false };
		}
		return null;
	};
	const pasteFromClipboard = ( data: DataTransfer ): boolean => {
		const got = fromClipboard( data );
		if ( ! got ) {
			return false;
		}
		const same = got.key !== '' && pasted.current.key === got.key;
		const n = same ? pasted.current.n + 1 : got.cut ? 0 : 1;
		pasted.current = { key: got.key, n };
		const out = pasteEls( design.elements, design.page, got.els, { selected: selection.ids, offset: n * 10, max: MAX_ELEMENTS } );
		if ( ! out ) {
			setNotice( __( 'There is no room for more elements.', 'events-certificates' ) );
			return true;
		}
		if ( out.ids.length ) {
			commit( { ...design, elements: out.list } );
			selectIds( out.ids );
		}
		return true;
	};
	/** Several elements copied at once, each after the last that was selected of its list; the copies are selected. */
	const duplicateMany = ( ids: string[] ) => {
		const out = duplicateEls( design.elements, ids, shift, MAX_ELEMENTS );
		if ( ! out.ids.length ) {
			return;
		}
		commit( { ...design, elements: out.list } );
		selectIds( out.ids );
	};
	const duplicate = ( id: string ) => duplicateMany( [ id ] );
	const reorder = ( id: string, to: 'up' | 'down' | 'top' | 'bottom' ) => commit( { ...design, elements: reorderEl( design.elements, id, to ) } );
	// The same for several at once: each list keeps their order; lock and hide are on for all, or off when all had it.
	const reorderMany = ( ids: string[], to: 'up' | 'down' | 'top' | 'bottom' ) => commit( { ...design, elements: reorderEls( design.elements, ids, to ) } );
	const toggleMany = ( ids: string[], key: 'locked' | 'hidden' ) => commit( { ...design, elements: setFlag( design.elements, ids, key ) } );
	const insert = ( type: Insertable ) => {
		let el = create( type, design, paintRef.current );
		// With a group selected (or an element that is in one) the new element goes in that group, at the end of its own list (the front of what is in it), at its center.
		const holder = holderFor( design.elements, selected, el );
		const box = holder ? ( findEl( resolvedShown.elements, holder.id ) as GroupEl | undefined ) : undefined;
		if ( holder && box ) {
			el = create( type, { ...design, page: { ...design.page, w: box.w, h: box.h, safe: 0 } }, paintRef.current );
		}
		commit( { ...design, elements: holder && box ? insertInto( design.elements, holder.id, el ) : [ ...design.elements, el ] } );
		setSelected( el.id );
		// A group asks which layout it is (Group, Row, Stack, Grid) before anything is put in it.
		setChoosing( el.type === 'group' ? el.id : null );
		setSidebar( 'element' );
		setPanel( null );
	};
	/**
	 * The selected elements (one or more of one list) put in a group, as big as they are together and in their place: free, or as a row, a stack or a grid (the
	 * group's layout chosen with the toolbar of the selection; a group of one element asks for it). In a row, a stack or a grid each keeps the size it has.
	 */
	const group = ( ids: string[], arrange?: GroupEl[ 'arrange' ] ) => {
		const made = create( 'group', design ) as GroupEl;
		const next = groupEls( design.elements, design.page, ids, made );
		if ( ! next ) {
			return;
		}
		let elements = next;
		if ( arrange ) {
			const placed = findEl( resolveTree( next, design.page ), made.id ) as GroupEl | undefined;
			elements = mapEl( next, made.id, ( g ) => ( { ...g, arrange, children: bakeChildren( placed?.children || [] ) } as Element ) );
		}
		commit( { ...design, elements } );
		setSelected( made.id );
		// Asked for a layout already (the selection's toolbar) or not (one element: the group asks).
		setChoosing( ids.length === 1 && ! arrange ? made.id : null );
		setSidebar( 'element' );
	};
	/** A group undone: what is in it goes to its place in the list, each where it is on the page. */
	const ungroup = ( id: string ) => {
		const next = ungroupEl( design.elements, design.page, id );
		if ( next === design.elements ) {
			return;
		}
		commit( { ...design, elements: next } );
		setSelected( null );
	};

	const save = async () => {
		setStatus( 'saving' );
		try {
			await apiFetch( { path: `/wp/v2/evcert-designs/${ config.design }`, method: 'POST', data: { title, content: toMarkup( toFile( toStored( stored ) ) as never, BLOCK_OPTIONS ), ...( config.isDefault ? {} : { meta: { _evcert_roles: roles } } ) } } );
			savedRef.current = stored;
			savedTitleRef.current = title;
			savedRolesRef.current = roles.join( ',' );
			setStatus( 'saved' );
		} catch ( e ) {
			setStatus( 'error' );
		}
	};

	/** The design as a file of blocks (the markup a template is kept as, docs/decisions/0045). */
	const exportBlocks = () => {
		const blob = new Blob( [ toMarkup( toFile( toStored( stored ) ) as never, BLOCK_OPTIONS ) ], { type: 'text/html' } );
		const a = document.createElement( 'a' );
		a.href = URL.createObjectURL( blob );
		// certificate.<slug of the title>.html, the slug as WordPress makes it (no accents, lower case, dashes).
		a.download = `certificate.${ cleanForSlug( title ) || 'untitled' }.html`;
		a.click();
		URL.revokeObjectURL( a.href );
	};
	/** Replaces the whole design with an imported one, back on the print page with nothing selected. */
	const applyImport = ( { design: imported, title: importedTitle }: Imported ) => {
		if ( editing ) {
			setEditing( null );
		}
		setDrawing( null );
		setSelected( null );
		setView( { device: 'page', orientation: view.orientation } );
		replaceDesign( withRequired( migrate( { ...baseDesign(), ...imported } as Design ) ) );
		if ( importedTitle ) {
			setTitle( importedTitle );
		}
		setNotice( __( 'Design imported. Save to keep it.', 'events-certificates' ) );
	};
	/** An image picked for an element: keeps the element's width and, unless it covers, the image's ratio. */
	const pickImage = ( id: string, picked: Picked ) => {
		const el = findEl( design.elements, id );
		if ( ! el || el.type !== 'image' ) {
			return;
		}
		let next: Element = { ...el, src: picked.src, attachment: picked.attachment, alt: picked.alt ?? el.alt };
		if ( picked.width && picked.height && ( ! el.ratio || el.ratio === 'auto' ) ) {
			const b = box( resolve( el, design.page ) );
			next = placed( next, { ...b, h: Math.round( ( b.w * picked.height ) / picked.width * 100 ) / 100 }, design.page );
		}
		commit( { ...design, elements: mapEl( design.elements, id, () => next ) } );
	};
	const setBox = ( id: string, b: Box ) => {
		const holder = parentOf( resolvedShown.elements, id );
		commit( { ...design, elements: mapEl( design.elements, id, ( e ) => placed( e, b, ( holder ? innerPage( holder ) : design.page ) as never ) ) } );
	};

	// Copy, cut and paste of the selection (in a text field the text editor has its own: ADR 0054; in the layers the core's do the same with the same blocks).
	useEffect( () => {
		const mine = ( event: ClipboardEvent ) => {
			const target = event.target as HTMLElement | null;
			return ! event.defaultPrevented && !! target && ! target.closest?.( 'input, textarea, [contenteditable="true"], .block-editor-list-view' );
		};
		const onCopy = ( event: ClipboardEvent ) => {
			if ( mine( event ) && selection.ids.length && event.clipboardData && toClipboard( event.clipboardData, event.type === 'cut' ) ) {
				event.preventDefault();
			}
		};
		const onPaste = ( event: ClipboardEvent ) => {
			if ( mine( event ) && event.clipboardData && pasteFromClipboard( event.clipboardData ) ) {
				event.preventDefault();
			}
		};
		document.addEventListener( 'copy', onCopy );
		document.addEventListener( 'cut', onCopy );
		document.addEventListener( 'paste', onPaste );
		return () => {
			document.removeEventListener( 'copy', onCopy );
			document.removeEventListener( 'cut', onCopy );
			document.removeEventListener( 'paste', onPaste );
		};
	} );

	// Keyboard: arrows nudge, Delete removes, Cmd/Ctrl+Z/Shift+Z/D/S.
	useEffect( () => {
		const onKey = ( event: KeyboardEvent ) => {
			// What the layers (or anything else) already did with the key is theirs: Cmd+Shift+D there duplicates once.
			if ( event.defaultPrevented ) {
				return;
			}
			// Open or close the settings (the core's toggle-sidebar shortcut: Shift + Cmd/Ctrl + comma), wherever the focus is. With Shift the key is "<": the code is the comma's.
			if ( ( event.metaKey || event.ctrlKey ) && event.shiftKey && event.code === 'Comma' ) {
				event.preventDefault();
				setSidebar( sidebarTab ? null : current ? 'element' : 'design' );
				return;
			}
			const target = event.target as HTMLElement;
			if ( target && ( target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable ) ) {
				return;
			}
			const mod = event.metaKey || event.ctrlKey;
			// Zoom: Cmd/Ctrl + / - / 0 (fit) / 1 (100%), instead of the browser's page zoom.
			if ( mod && ( event.key === '=' || event.key === '+' ) ) {
				event.preventDefault();
				zoomTo( zoom * 1.25 );
				return;
			}
			if ( mod && event.key === '-' ) {
				event.preventDefault();
				zoomTo( zoom / 1.25 );
				return;
			}
			if ( mod && event.key === '0' ) {
				event.preventDefault();
				fit( 'view' );
				return;
			}
			if ( mod && event.key === '1' ) {
				event.preventDefault();
				zoomTo( 1 );
				return;
			}
			if ( mod && event.key.toLowerCase() === 'z' ) {
				event.preventDefault();
				dispatch( { type: event.shiftKey ? 'redo' : 'undo' } );
			} else if ( mod && event.key.toLowerCase() === 's' ) {
				event.preventDefault();
				save();
			} else if ( mod && event.key.toLowerCase() === 'g' && ( selected || selectedMany.length ) ) {
				event.preventDefault();
				const el = selected ? findEl( design.elements, selected ) : null;
				if ( event.shiftKey ) {
					if ( el && isGroup( el ) ) {
						ungroup( el.id );
					}
				} else {
					group( selectedMany.length > 1 ? selectedMany : [ selected as string ] );
				}
			} else if ( mod && event.key.toLowerCase() === 'd' && selection.ids.length ) {
				event.preventDefault();
				duplicateMany( selection.ids );
			} else if ( mod && event.key.toLowerCase() === 'a' && ! editing ) {
				event.preventDefault();
				selectIds( selectAllIds( design.elements, selection.ids ) );
			} else if ( ( event.key === 'Delete' || event.key === 'Backspace' ) && selection.ids.length ) {
				event.preventDefault();
				removeMany( selection.ids );
			} else if ( event.key === 'Enter' && selected && ! editing ) {
				const el = findEl( design.elements, selected );
				if ( el && el.type === 'text' ) {
					event.preventDefault();
					startEditing( el );
				}
			} else if ( event.key === 'Escape' ) {
				// Several become the first, one goes up to its group, then nothing.
				selectIds( escapeIds( design.elements, selection.ids ) );
				setPanel( null );
			} else if ( selection.ids.length && event.key.startsWith( 'Arrow' ) ) {
				event.preventDefault();
				const step = event.shiftKey ? 10 : 1;
				const dx = event.key === 'ArrowLeft' ? -step : event.key === 'ArrowRight' ? step : 0;
				const dy = event.key === 'ArrowUp' ? -step : event.key === 'ArrowDown' ? step : 0;
				// What is locked, or placed by a row, a stack or a grid, does not move by hand.
				const movable = ( el: Element ) => ! el.locked && ! parentOf( resolvedShown.elements, el.id )?.arrange;
				if ( ( dx || dy ) && selection.ids.some( ( id ) => { const el = findEl( design.elements, id ); return !! el && movable( el ); } ) ) {
					commit( { ...design, elements: nudgeEls( design.elements, selection.ids, dx, dy, ( e, x, y ) => shift( e, x, y ), movable ) } );
				}
			}
		};
		window.addEventListener( 'keydown', onKey );
		return () => window.removeEventListener( 'keydown', onKey );
	} );

	const { w, h, safe, bleed } = shown.page;
	const bg = pageColor( shown, mode );
	const textEls = useMemo( () => flatten( shown.elements ).filter( ( el ) => el.type === 'text' ), [ shown ] );
	const statusLabel = {
		loading: __( 'Loading…', 'events-certificates' ),
		saved: __( 'Saved', 'events-certificates' ),
		dirty: __( 'Unsaved changes', 'events-certificates' ),
		saving: __( 'Saving…', 'events-certificates' ),
		error: __( 'Couldn’t save', 'events-certificates' ),
	}[ status ];

	/**
	 * Opens the certificate page with the design as it is now (unsaved changes
	 * included) and the preview data. The tab opens first, so browsers don't
	 * block it, then goes to the address once the design is stored.
	 */
	const preview = () => {
		const tab = window.open( '', '_blank' );
		apiFetch< { url: string } >( { path: '/evcert/v1/preview', method: 'POST', data: { design: toStored( stored ), role: roles[ 0 ] || '' } } )
			.then( ( { url } ) => {
				if ( tab ) {
					tab.location.href = url;
				} else {
					window.open( url, '_blank' );
				}
			} )
			.catch( () => {
				tab?.close();
				setNotice( __( 'Couldn’t open the preview.', 'events-certificates' ) );
			} );
	};

	const layerName = ( el: Element ) => stockName( el.name || '' ) || el.type;

	const sidebarTab = current ? sidebar : sidebar === 'element' ? 'design' : sidebar;

	// The command palette (the bar at the top opens it): the editor's actions, the ones that do not apply left out ('root': listed as suggestions).
	const selectedEl = selected ? findEl( design.elements, selected ) : undefined;
	const palette = ( name: string, label: string, icon: JSX.Element, run: () => void, disabled = false ) => ( { name: `evcert/editor-${ name }`, label, icon, category: 'command' as const, context: 'root', disabled, callback: ( { close }: { close: () => void } ) => { close(); run(); } } );
	useCommand( palette( 'save', __( 'Save', 'events-certificates' ), check, () => { save(); }, status !== 'dirty' && status !== 'error' ) );
	useCommand( palette( 'undo', __( 'Undo', 'events-certificates' ), undoIcon, () => dispatch( { type: 'undo' } ), ! history.past.length ) );
	useCommand( palette( 'redo', __( 'Redo', 'events-certificates' ), redoIcon, () => dispatch( { type: 'redo' } ), ! history.future.length ) );
	useCommand( palette( 'add', __( 'Add an element', 'events-certificates' ), plus, () => setPanel( 'insert' ) ) );
	useCommand( palette( 'layers', panel === 'layers' ? __( 'Hide layers', 'events-certificates' ) : __( 'Show layers', 'events-certificates' ), listView, () => setPanel( panel === 'layers' ? null : 'layers' ) ) );
	useCommand( palette( 'sidebar', sidebarTab ? __( 'Hide settings', 'events-certificates' ) : __( 'Show settings', 'events-certificates' ), drawerRight, () => setSidebar( sidebarTab ? null : current ? 'element' : 'design' ) ) );
	useCommand( palette( 'side', side === 'back' ? __( 'Show the certificate', 'events-certificates' ) : __( 'Show the declaration', 'events-certificates' ), pages, () => showSide( side === 'back' ? 'front' : 'back' ), ! declarationEnabled() && side !== 'back' ) );
	useCommand( palette( 'preview', __( 'Preview in new tab', 'events-certificates' ), external, () => preview() ) );
	useCommand( palette( 'mode', mode === 'light' ? __( 'Preview in dark mode', 'events-certificates' ) : __( 'Preview in light mode', 'events-certificates' ), seen, () => setMode( mode === 'light' ? 'dark' : 'light' ), ! darkEnabled() ) );
	fitLabels().forEach( ( [ mode, label ] ) => useCommand( palette( `fit-${ mode }`, label, seen, () => fit( mode ) ) ) );
	useCommand( palette( 'duplicate', __( 'Duplicate the selection', 'events-certificates' ), copy, () => selection.ids.length && duplicateMany( selection.ids ), ! selection.ids.length ) );
	useCommand( palette( 'delete', __( 'Delete the selection', 'events-certificates' ), trash, () => selection.ids.length && removeMany( selection.ids ), ! selection.ids.length || selection.ids.every( ( id ) => { const el = findEl( design.elements, id ); return ! el || isRequired( el, flatten( design.elements ) ); } ) ) );
	useCommand( palette( 'export', __( 'Export', 'events-certificates' ), download, () => exportBlocks() ) );
	useCommand( palette( 'import', __( 'Import…', 'events-certificates' ), upload, () => setImporting( true ) ) );
	useCommand( palette( 'clear', __( 'Clear the design', 'events-certificates' ), trash, () => replaceDesign( blankDesign() ) ) );
	useCommand( palette( 'restore', __( 'Restore', 'events-certificates' ), upload, () => setRestoring( true ) ) );
	const go = ( name: string, label: string, url?: string ) => palette( `go-${ name }`, label, chevronLeft, () => { if ( url ) { window.location.href = url; } }, ! url );
	useCommand( go( 'templates', __( 'Go to Templates', 'events-certificates' ), config.exitUrl ) );
	useCommand( go( 'certificates', __( 'Go to Certificates', 'events-certificates' ), config.urls?.certificates ) );
	useCommand( go( 'events', __( 'Go to Events', 'events-certificates' ), config.urls?.events ) );
	useCommand( go( 'quick', __( 'Go to Quick generator', 'events-certificates' ), config.urls?.quick ) );
	useCommand( go( 'settings', __( 'Go to Settings', 'events-certificates' ), config.urls?.settings ) );

	return (
		<div className={ `evcert-editor is-${ mode }${ sidebarTab ? ' has-sidebar' : '' }${ panel ? ' has-panel' : '' }` }>
			<header className="evcert-header">
				<div className="evcert-header-left">
					<Button className="evcert-back" icon={ chevronLeft } href={ config.exitUrl } label={ __( 'Back to Certificates', 'events-certificates' ) } />
					{ tab === 'art' && (
						<Button
							className="evcert-inserter-toggle"
							variant="primary"
							size="compact"
							icon={ plus }
							isPressed={ panel === 'insert' }
							onClick={ () => setPanel( panel === 'insert' ? null : 'insert' ) }
							label={ panel === 'insert' ? __( 'Close', 'events-certificates' ) : __( 'Add element', 'events-certificates' ) }
						/>
					) }
					<Button size="compact" icon={ undoIcon } onClick={ () => dispatch( { type: 'undo' } ) } disabled={ ! history.past.length } accessibleWhenDisabled label={ __( 'Undo', 'events-certificates' ) } />
					<Button size="compact" icon={ redoIcon } onClick={ () => dispatch( { type: 'redo' } ) } disabled={ ! history.future.length } accessibleWhenDisabled label={ __( 'Redo', 'events-certificates' ) } />
					<Button size="compact" icon={ listView } isPressed={ panel === 'layers' } onClick={ () => setPanel( panel === 'layers' ? null : 'layers' ) } label={ __( 'Layers', 'events-certificates' ) } />
				</div>
				<div className="evcert-header-center">
					<button type="button" className="evcert-document-bar" aria-haspopup="dialog" onClick={ () => ( wpDispatch( commandsStore ) as unknown as { open: () => void } ).open() }>
						<span className="evcert-document-title">{ title || __( 'Certificate', 'events-certificates' ) }</span>
						<span className="evcert-document-type">· { __( 'Certificate design', 'events-certificates' ) }</span>
					</button>
				</div>
				<div className="evcert-header-right">
					{ /* Screen views (Desktop/Tablet/Mobile, orientation): unfinished, behind EVCERT_DEVELOPMENT_SCREEN_VIEWS. */ }
					{ config.development?.screenViews && <PreviewDropdown view={ view } onChange={ ( next ) => setView( next ) } /> }
					{ /* The declaration sheet: pressed while it is shown (the certificate is not shown then); a second press goes back. */ }
					{ ( declarationEnabled() || side === 'back' ) && <Button icon={ pages } isPressed={ side === 'back' } onClick={ () => showSide( side === 'back' ? 'front' : 'back' ) } label={ __( 'Declaration', 'events-certificates' ) } /> }
					<Button icon={ external } onClick={ preview } label={ __( 'Preview in new tab', 'events-certificates' ) } />
					{ /* The same icon as the Colors section's toggle, pressed in dark mode (@wordpress/icons has no moon: the half-filled circle of the Styles). */ }
					{ darkEnabled() && (
						<Button size="compact" icon={ styles } isPressed={ mode === 'dark' } onClick={ () => setMode( mode === 'light' ? 'dark' : 'light' ) } label={ mode === 'light' ? __( 'Preview in dark mode', 'events-certificates' ) : __( 'Preview in light mode', 'events-certificates' ) } />
					) }
					<Button size="compact" icon={ drawerRight } isPressed={ !! sidebarTab } onClick={ () => setSidebar( sidebarTab ? null : current ? 'element' : 'design' ) } label={ __( 'Settings', 'events-certificates' ) } shortcut={ displayShortcut.primaryShift( ',' ) } aria-controls="evcert-settings" aria-expanded={ !! sidebarTab } />
					<Button
						variant="primary"
						size="compact"
						className="evcert-save"
						onClick={ save }
						isBusy={ status === 'saving' }
						disabled={ status !== 'dirty' && status !== 'error' }
						accessibleWhenDisabled
						label={ status === 'error' ? __( 'Couldn’t save. Try again.', 'events-certificates' ) : undefined }
						showTooltip={ status === 'error' }
					>
						{ status === 'saving' ? __( 'Saving…', 'events-certificates' ) : __( 'Save', 'events-certificates' ) }
					</Button>
					<DropdownMenu icon={ moreVertical } label={ __( 'Options', 'events-certificates' ) } popoverProps={ { placement: 'bottom-end' } }>
						{ ( { onClose } ) => (
							<>
								<MenuGroup label={ __( 'Design', 'events-certificates' ) }>
									<MenuItem onClick={ () => { exportBlocks(); onClose(); } }>{ __( 'Export', 'events-certificates' ) }</MenuItem>
									<MenuItem onClick={ () => { setImporting( true ); onClose(); } }>{ __( 'Import…', 'events-certificates' ) }</MenuItem>
									<MenuItem onClick={ () => { setRestoring( true ); onClose(); } }>{ __( 'Restore', 'events-certificates' ) }</MenuItem>
									<MenuItem onClick={ () => { replaceDesign( blankDesign() ); setSelected( null ); onClose(); } }>{ __( 'Clear', 'events-certificates' ) }</MenuItem>
								</MenuGroup>
							</>
						) }
					</DropdownMenu>
				</div>
			</header>

			<BlocksProvider
				elements={ design.elements }
				page={ design.page }
				selection={ selection }
				onSelection={ ( ids, anchor ) => {
					selectIds( ids, anchor );
					if ( ids.length ) {
						setSidebar( 'element' );
					}
				} }
				onElements={ ( elements ) => commit( { ...design, elements } ) }
			>
			<div className="evcert-body">
				{ panel && (
					<aside className="evcert-left-panel">
						{ panel === 'insert' ? (
							<Inserter onInsert={ insert } onClose={ () => setPanel( null ) } />
						) : (
							<>
								{ /* As the core's List View sidebar: the tab (one: the list) and the close button, then the list with a little room around it. */ }
								<div className="evcert-sidebar-tabs">
									<button type="button" role="tab" aria-selected="true" className="is-active">
										{ __( 'Layers', 'events-certificates' ) }
									</button>
									<Button size="compact" icon={ closeSmall } className="evcert-sidebar-close" onClick={ () => setPanel( null ) } label={ __( 'Close', 'events-certificates' ) } />
								</div>
								<div className="evcert-layers-panel">
									<BlockLayers />
								</div>
							</>
						) }
					</aside>
				) }

				{ status === 'loading' && (
					<div className="evcert-loading">
						<Spinner />
					</div>
				) }
				<div
					className={ `evcert-stage${ fullFit ? ' is-fit-full' : '' }${ flush ? ' is-fit-flush' : '' }${ status === 'loading' || ! area ? ' is-waiting' : '' }${ panning ? ' is-panning' : '' }${ panRef.current ? ' is-grabbing' : '' }` }
					ref={ wrapRef }
					tabIndex={ -1 }
					onPointerDownCapture={ ( event ) => {
						// A click on the stage takes the focus from the layers: their own keys (copy, paste, delete) would otherwise answer to the canvas.
						( event.currentTarget as HTMLElement ).focus( { preventScroll: true } );
						if ( ! panning || ! wrapRef.current ) {
							return;
						}
						// The hand tool: nothing gets selected or dragged.
						event.preventDefault();
						event.stopPropagation();
						panRef.current = { x: event.clientX, y: event.clientY, left: wrapRef.current.scrollLeft, top: wrapRef.current.scrollTop };
						( event.currentTarget as HTMLElement ).setPointerCapture( event.pointerId );
					} }
					onPointerMoveCapture={ ( event ) => {
						const p = panRef.current;
						if ( p && wrapRef.current ) {
							event.stopPropagation();
							wrapRef.current.scrollLeft = p.left - ( event.clientX - p.x );
							wrapRef.current.scrollTop = p.top - ( event.clientY - p.y );
						}
					} }
					onPointerUpCapture={ ( event ) => {
						if ( panRef.current ) {
							event.stopPropagation();
							panRef.current = null;
						}
					} }
					onPointerDown={ () => {
						if ( editing ) {
							stopEditing();
						}
						setSelected( null );
					} }
				>
						<BlockCanvas
							design={ resolvedShown }
							mode={ mode }
							sample={ sample }
							zoom={ zoom }
							edges={ { x: ! fillsX, y: ! fillsY } }
							svgRef={ svgRef }
							selRef={ selRef }
							tab={ tab }
							editingId={ editing ? editing.id : null }
							onEdit={ startEditing }
							choosing={ choosing }
							onChoose={ ( id, arrange ) => {
								update( id, { arrange } as Partial< Element > );
								setChoosing( null );
							} }
							onGroup={ group }
							onPatch={ ( patches ) => commit( { ...design, elements: patches.reduce( ( list, { id, patch } ) => mapEl( list, id, ( el ) => ( { ...el, ...patch } as Element ) ), design.elements ) } ) }
							selection={ selection }
							onSelect={ ( ids, anchor ) => {
								selectIds( ids, anchor );
								if ( ids.length ) {
									setSidebar( 'element' );
								}
							} }
							overlay={
								editing
									? ( flatten( resolvedShown.elements ) as Placed[] )
											.filter( ( el ) => el.id === editing.id && el.type === 'text' )
											.map( ( el ) => (
												<foreignObject key={ el.id } x={ ( el.x || 0 ) + ( el.ox || 0 ) } y={ ( el.y || 0 ) + ( el.oy || 0 ) } width={ el.w } height={ Math.max( ( el as TextEl ).h, ( el as TextEl ).size * lineHeightFactor( ( el as TextEl ).lh, ( el as TextEl ).size ) * 6 ) } style={ { overflow: 'visible' } } onPointerDown={ ( event ) => event.stopPropagation() }>
													<FieldText
														inline
														label={ stockName( el.name || '' ) || __( 'Text', 'events-certificates' ) }
														runs={ editing.runs }
														onChange={ ( runs ) => setEditing( { id: el.id, runs } ) }
														sample={ sample }
														locale={ LOCALE }
														emptyText={ el.slot === 'date-of-emission' ? sample.date_format || 'F j, Y' : undefined }
														plain={ el.slot === 'date-of-emission' }
														style={ textStyle( el as TextEl, shown, mode ) }
														commands={ textCommands }
														onMarks={ setMarks }
														preview={ { defaults: config.sample, overrides: design.sample || {}, set: ( key, value ) => commit( { ...design, sample: setSample( design.sample, key, value ) } ) } }
														onDone={ stopEditing }
													/>
												</foreignObject>
											) )
									: null
							}
							placeholder={ ( el, select, startMove ) => (
								<foreignObject
									x={ el.x }
									y={ el.y }
									width={ el.w }
									height={ el.h }
									className={ `evcert-el${ selected === el.id ? ' is-selected' : '' }` }
									onPointerDown={ ( event ) => {
										event.stopPropagation();
										select();
										if ( ! el.locked && ( event.target as HTMLElement ).closest( 'button, input, a' ) === null ) {
											startMove( event );
										}
									} }
								>
									<div className="evcert-image-ph">
										<ImagePlaceholder onPick={ ( picked ) => pickImage( el.id, picked ) } />
									</div>
								</foreignObject>
							) }
						/>
				</div>

				{ sidebarTab && (
					<aside id="evcert-settings" className="evcert-sidebar" aria-label={ __( 'Settings', 'events-certificates' ) }>
						<div className="evcert-sidebar-tabs" role="tablist">
							<button type="button" role="tab" aria-selected={ sidebarTab === 'design' } className={ sidebarTab === 'design' ? 'is-active' : '' } onClick={ () => setSidebar( 'design' ) }>
								{ __( 'Certificate', 'events-certificates' ) }
							</button>
							<button
								type="button"
								role="tab"
								aria-selected={ sidebarTab === 'element' }
								className={ sidebarTab === 'element' ? 'is-active' : '' }
								disabled={ ! current && ! selectedMany.length }
								onClick={ () => setSidebar( 'element' ) }
							>
								{ __( 'Element', 'events-certificates' ) }
							</button>
							{ /* As the core's: the small close icon in a compact button, with a tooltip that has the shortcut. */ }
							<Button size="compact" icon={ closeSmall } className="evcert-sidebar-close" onClick={ () => setSidebar( null ) } label={ __( 'Close settings', 'events-certificates' ) } shortcut={ displayShortcut.primaryShift( ',' ) } aria-controls="evcert-settings" />
						</div>
						{ sidebarTab === 'element' && current && (
							<Inspector el={ current } stored={ currentStored } design={ design } mode={ mode } tab={ tab } locale={ LOCALE } sample={ sample } onChange={ ( patch ) => update( current.id, patch ) } onBox={ ( b ) => setBox( current.id, b ) } onImage={ ( picked ) => pickImage( current.id, picked ) } holder={ currentHolder } />
						) }
						{ sidebarTab === 'element' && ! current && selectedMany.length > 0 && (
							<div className="evcert-inspector">
								<div className="evcert-card">
									<span className="evcert-card__icon">
										<Icon icon={ copy } />
									</span>
									<div>
										<h2 className="evcert-card__title">{ sprintf( /* translators: %d: how many elements are selected. */ _n( '%d element selected', '%d elements selected', selectedMany.length, 'events-certificates' ), selectedMany.length ) }</h2>
									</div>
								</div>
								<ul className="evcert-selected-list">
									{ selectedMany.map( ( id ) => {
										const el = findEl( design.elements, id );
										return el ? (
											<li key={ id }>
												<Button variant="tertiary" icon={ elementIcon( el.type, el.slot ) } onClick={ () => setSelected( id ) }>
													{ layerName( el ) }
												</Button>
											</li>
										) : null;
									} ) }
								</ul>
							</div>
						) }
						{ sidebarTab === 'design' && <DesignPanel design={ stored } mode={ mode } title={ title } status={ statusLabel } sample={ sample } defaults={ config.sample } onTitle={ setTitle } roles={ roles } roleLabels={ config.roles } isDefault={ config.isDefault } onRoles={ setRoles } onChange={ replaceDesign } onMode={ setMode } /> }
					</aside>
				) }
			</div>
			</BlocksProvider>

			{ selectedMany.length > 1 && tab === 'art' && ! editing && ! drawing && (
				<SelectionToolbar
					anchor={ anchor }
					els={ selectedMany.map( ( id ) => findEl( design.elements, id ) ).filter( Boolean ) as Element[] }
					type={ selectionType( design.elements, selectedMany ) }
					canGroup={ canGroupIds( design.elements, selectedMany ) }
					required={ selectedMany.every( ( id ) => { const el = findEl( design.elements, id ); return ! el || isRequired( el, flatten( design.elements ) ); } ) }
					onGroup={ ( arrange ) => group( selectedMany, arrange ) }
					onOrder={ ( to ) => reorderMany( selectedMany, to ) }
					onToggle={ ( key ) => toggleMany( selectedMany, key ) }
					onDuplicate={ () => duplicateMany( selectedMany ) }
					onRemove={ () => removeMany( selectedMany ) }
					onCopy={ () => document.execCommand( 'copy' ) }
					onCut={ () => document.execCommand( 'cut' ) }
				/>
			) }
			{ current && selectedMany.length < 2 && ( tab === 'art' || editing ) && ! drawing && (
				<ElementToolbar
					anchor={ editing ? editAnchor : anchor }
					editing={ !! editing }
					marks={ marks }
					onBold={ () => textCommands.current?.bold() }
					onItalic={ () => textCommands.current?.italic() }
					onInsertField={ ( field ) => textCommands.current?.insertField( field ) }
					onToggleEdit={ () => ( editing ? stopEditing() : current && startEditing( current ) ) }
					el={ current }
					required={ isRequired( current, flatten( design.elements ) ) }
					onSelectParent={ () => setSidebar( 'element' ) }
					onOrder={ ( to ) => reorder( current.id, to ) }
					onToggle={ ( key ) => update( current.id, { [ key ]: ! current[ key ] } as Partial< Element > ) }
					onDuplicate={ () => duplicate( current.id ) }
					onCopy={ () => document.execCommand( 'copy' ) }
					onCut={ () => document.execCommand( 'cut' ) }
					onGroup={ canHold( current ) ? () => group( [ current.id ] ) : undefined }
					onUngroup={ isGroup( current ) ? () => ungroup( current.id ) : undefined }
					onRemove={ () => remove( current.id ) }
					onImage={ ( picked ) => pickImage( current.id, picked ) }
					onEditDrawing={ () => setDrawing( current.id ) }
				/>
			) }
			<footer className="evcert-footer">
				<nav className="evcert-breadcrumb" aria-label={ __( 'Selection', 'events-certificates' ) }>
					<button type="button" onClick={ () => setSelected( null ) }>
						{ __( 'Certificate', 'events-certificates' ) }
					</button>
					{ current && (
						<>
							<span aria-hidden="true"> › </span>
							<span>{ layerName( current ) }</span>
						</>
					) }
					{ selectedMany.length > 0 && (
						<>
							<span aria-hidden="true"> › </span>
							<span>{ sprintf( /* translators: %d: how many elements are selected. */ _n( '%d element', '%d elements', selectedMany.length, 'events-certificates' ), selectedMany.length ) }</span>
						</>
					) }
				</nav>
				{ view.device !== 'page' && (
					<span className="evcert-view-badge">
						{ sprintf(
							/* translators: 1: screen (Desktop, Tablet, Mobile), 2: orientation, 3: width, 4: height. */
							__( '%1$s · %2$s · %3$s × %4$s', 'events-certificates' ),
							{ desktop: __( 'Desktop', 'events-certificates' ), tablet: __( 'Tablet', 'events-certificates' ), mobile: __( 'Mobile', 'events-certificates' ) }[ view.device ],
							view.orientation === 'portrait' ? __( 'Portrait', 'events-certificates' ) : __( 'Landscape', 'events-certificates' ),
							String( design.page.w ),
							String( design.page.h )
						) }
					</span>
				) }
				<ZoomControl zoom={ zoom } zoomMode={ zoomMode } onZoom={ ( z ) => zoomTo( z ) } onFit={ fit } />
			</footer>

			{ drawing && ( () => {
				const el = findEl( design.elements, drawing );
				return el && el.type === 'svg' ? <SvgEditor el={ resolve( el, design.page ) as typeof el } design={ design } mode={ mode } onApply={ ( patch ) => update( el.id, patch as Partial< Element > ) } onClose={ () => setDrawing( null ) } /> : null;
			} )() }
			{ restoring && (
				<ConfirmDialog
					className="evcert-confirm"
					confirmButtonText={ __( 'Restore', 'events-certificates' ) }
					cancelButtonText={ __( 'Cancel', 'events-certificates' ) }
					onConfirm={ () => { replaceDesign( baseDesign() ); setSelected( null ); setRestoring( false ); } }
					onCancel={ () => setRestoring( false ) }
				>
					<p>{ __( 'This replaces everything in this template (the page, the elements and the colors) with the Basic design, the one a new site starts from.', 'events-certificates' ) }</p>
					<p>{ __( 'Nothing is saved until you click Save, and Undo brings your design back.', 'events-certificates' ) }</p>
				</ConfirmDialog>
			) }
			{ importing && <ImportModal onImport={ applyImport } onClose={ () => setImporting( false ) } /> }
			{ notice && (
				<div className="evcert-snackbar">
					<Snackbar onRemove={ () => setNotice( null ) }>{ notice }</Snackbar>
				</div>
			) }
		</div>
	);
}

