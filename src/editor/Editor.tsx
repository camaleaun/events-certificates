/**
 * The design editor: the page as an SVG that fills the window, elements
 * selected and dragged with snapping to the page centre, the safe margin and
 * the other elements, and a floating Dock with everything else.
 */
import { fontStack, weightFor } from './fonts';
import { darkEnabled } from './darkMode';
import apiFetch from '@wordpress/api-fetch';
import { Button, ColorPalette, DropdownMenu, MenuGroup, MenuItem, Popover, Snackbar, Spinner, RangeControl, SelectControl, TextControl, ToggleControl, __experimentalConfirmDialog as ConfirmDialog } from '@wordpress/components';
import { store as commandsStore, useCommand } from '@wordpress/commands';
import { dispatch as wpDispatch } from '@wordpress/data';
import { check, chevronLeft, close, copy, download, drawerRight, external, lineSolid, listView, moreVertical, pages, plus, redo as redoIcon, seen, styles, trash, undo as undoIcon, upload } from '@wordpress/icons';
import { useCallback, useEffect, useLayoutEffect, useMemo, useReducer, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
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
	type Mode,
	type Run,
	type TextEl,
	type View, lockedRatio, isRequired, toStored, type SvgPath, pageColor, baseBack, fromSide, toSide, type Side } from './model';
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
import { BlockLayers, BlocksProvider, registerBlocks } from './blocks';
import BlockCanvas from './BlockCanvas';
import { cleanForSlug } from '@wordpress/url';
import { fromMarkup, isMarkup, toMarkup } from '@camaleaun/draw';
import { BLOCK_OPTIONS, fromFile, toFile } from './dialect';
import { baseDesign, blankDesign, stockName, withRequired } from './base';
import { verificationParts } from './verification';
import { Shape, textStyle, LOCALE } from './Shape';
import { fitScale, isFit, ZOOM_MAX, ZOOM_MIN, type FitMode, type ZoomMode } from './zoom';
import './style.scss';

// The block types of a drawing, known to the block editor only to be listed (the canvas draws them): docs/decisions/0046.
registerBlocks( { text: __( 'Text', 'events-certificates' ), rect: __( 'Rectangle', 'events-certificates' ), ellipse: __( 'Ellipse', 'events-certificates' ), path: __( 'Shape', 'events-certificates' ), line: __( 'Line', 'events-certificates' ), image: __( 'Image', 'events-certificates' ), svg: __( 'SVG', 'events-certificates' ), qr: __( 'QR code', 'events-certificates' ), signature: __( 'Signature', 'events-certificates' ), verification: __( 'Verification', 'events-certificates' ) } );

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
	const resolvedShown = useMemo( () => ( { ...shown, elements: shown.elements.map( ( e ) => resolve( e, shown.page ) ) } ), [ shown ] );
	const [ selected, setSelected ] = useState< string | null >( null );
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
		const original = editing ? design.elements.find( ( e ) => e.id === editing.id ) : null;
		if ( editing && original && JSON.stringify( runsOf( original as TextEl ) ) !== JSON.stringify( editing.runs ) ) {
			update( editing.id, { runs: editing.runs, text: undefined } as Partial< Element > );
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
	useEffect( () => setAnchor( selRef.current ), [ selected, tab, zoom ] );

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
	const shift = ( e: Element, dx: number, dy: number ): Element => ( e.layout ? placed( e, box( moved( resolve( e, design.page ), dx, dy ) ), design.page ) : moved( e, dx, dy ) );
	const update = ( id: string, patch: Partial< Element > ) =>
		commit( { ...design, elements: design.elements.map( ( el ) => ( el.id === id ? ( { ...el, ...patch } as Element ) : el ) ) } );
	const current = resolvedShown.elements.find( ( el ) => el.id === selected ) || null;
	const currentStored = design.elements.find( ( el ) => el.id === selected ) || null;

	const remove = ( id: string ) => {
		const el = design.elements.find( ( e ) => e.id === id );
		if ( ! el || isRequired( el, design.elements ) ) {
			return;
		}
		commit( { ...design, elements: design.elements.filter( ( e ) => e.id !== id ) } );
		setSelected( null );
	};
	const duplicate = ( id: string ) => {
		const el = design.elements.find( ( e ) => e.id === id );
		if ( ! el ) {
			return;
		}
		const copy = { ...shift( el, 10, 10 ), id: newId( el.type ), slot: undefined, name: `${ el.name || el.type } copy` } as Element;
		commit( { ...design, elements: [ ...design.elements, copy ] } );
		setSelected( copy.id );
	};
	const reorder = ( id: string, to: 'up' | 'down' | 'top' | 'bottom' ) => {
		const list = [ ...design.elements ];
		const i = list.findIndex( ( e ) => e.id === id );
		const [ el ] = list.splice( i, 1 );
		const j = to === 'top' ? list.length : to === 'bottom' ? 0 : to === 'up' ? Math.min( list.length, i + 1 ) : Math.max( 0, i - 1 );
		list.splice( j, 0, el );
		commit( { ...design, elements: list } );
	};
	/** Restacks an element just in front of or behind another (dragging in Layers). */
	const move = ( id: string, target: string, place: 'front' | 'behind' ) => {
		const list = [ ...design.elements ];
		const [ el ] = list.splice( list.findIndex( ( e ) => e.id === id ), 1 );
		const t = list.findIndex( ( e ) => e.id === target );
		if ( ! el || t < 0 ) {
			return;
		}
		list.splice( place === 'front' ? t + 1 : t, 0, el );
		commit( { ...design, elements: list } );
	};
	const insert = ( type: Element[ 'type' ] | 'logo' ) => {
		const el = create( type, design );
		commit( { ...design, elements: [ ...design.elements, el ] } );
		setSelected( el.id );
		setSidebar( 'element' );
		setPanel( null );
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
		const el = design.elements.find( ( e ) => e.id === id );
		if ( ! el || el.type !== 'image' ) {
			return;
		}
		let next: Element = { ...el, src: picked.src, attachment: picked.attachment, alt: picked.alt ?? el.alt };
		if ( picked.width && picked.height && ( ! el.ratio || el.ratio === 'auto' ) ) {
			const b = box( resolve( el, design.page ) );
			next = placed( next, { ...b, h: Math.round( ( b.w * picked.height ) / picked.width * 100 ) / 100 }, design.page );
		}
		commit( { ...design, elements: design.elements.map( ( e ) => ( e.id === id ? next : e ) ) } );
	};
	const setBox = ( id: string, b: Box ) => commit( { ...design, elements: design.elements.map( ( e ) => ( e.id === id ? placed( e, b, design.page ) : e ) ) } );

	// Keyboard: arrows nudge, Delete removes, Cmd/Ctrl+Z/Shift+Z/D/S.
	useEffect( () => {
		const onKey = ( event: KeyboardEvent ) => {
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
			} else if ( mod && event.key.toLowerCase() === 'd' && selected ) {
				event.preventDefault();
				duplicate( selected );
			} else if ( ( event.key === 'Delete' || event.key === 'Backspace' ) && selected ) {
				event.preventDefault();
				remove( selected );
			} else if ( event.key === 'Enter' && selected && ! editing ) {
				const el = design.elements.find( ( e ) => e.id === selected );
				if ( el && el.type === 'text' ) {
					event.preventDefault();
					startEditing( el );
				}
			} else if ( event.key === 'Escape' ) {
				setSelected( null );
				setPanel( null );
			} else if ( selected && event.key.startsWith( 'Arrow' ) ) {
				event.preventDefault();
				const step = event.shiftKey ? 10 : 1;
				const el = design.elements.find( ( e ) => e.id === selected );
				if ( el && ! el.locked ) {
					const dx = event.key === 'ArrowLeft' ? -step : event.key === 'ArrowRight' ? step : 0;
					const dy = event.key === 'ArrowUp' ? -step : event.key === 'ArrowDown' ? step : 0;
					commit( { ...design, elements: design.elements.map( ( e ) => ( e.id === selected ? shift( e, dx, dy ) : e ) ) } );
				}
			}
		};
		window.addEventListener( 'keydown', onKey );
		return () => window.removeEventListener( 'keydown', onKey );
	} );

	const { w, h, safe, bleed } = shown.page;
	const bg = pageColor( shown, mode );
	const textEls = useMemo( () => shown.elements.filter( ( el ) => el.type === 'text' ), [ shown ] );
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
	const selectedEl = selected ? design.elements.find( ( e ) => e.id === selected ) : undefined;
	const palette = ( name: string, label: string, icon: JSX.Element, run: () => void, disabled = false ) => ( { name: `evcert/editor-${ name }`, label, icon, category: 'command' as const, context: 'root', disabled, callback: ( { close }: { close: () => void } ) => { close(); run(); } } );
	useCommand( palette( 'save', __( 'Save', 'events-certificates' ), check, () => { save(); }, status !== 'dirty' && status !== 'error' ) );
	useCommand( palette( 'undo', __( 'Undo', 'events-certificates' ), undoIcon, () => dispatch( { type: 'undo' } ), ! history.past.length ) );
	useCommand( palette( 'redo', __( 'Redo', 'events-certificates' ), redoIcon, () => dispatch( { type: 'redo' } ), ! history.future.length ) );
	useCommand( palette( 'add', __( 'Add an element', 'events-certificates' ), plus, () => setPanel( 'insert' ) ) );
	useCommand( palette( 'layers', panel === 'layers' ? __( 'Hide layers', 'events-certificates' ) : __( 'Show layers', 'events-certificates' ), listView, () => setPanel( panel === 'layers' ? null : 'layers' ) ) );
	useCommand( palette( 'sidebar', sidebarTab ? __( 'Hide settings', 'events-certificates' ) : __( 'Show settings', 'events-certificates' ), drawerRight, () => setSidebar( sidebarTab ? null : current ? 'element' : 'design' ) ) );
	useCommand( palette( 'side', side === 'back' ? __( 'Show the certificate', 'events-certificates' ) : __( 'Show the declaration', 'events-certificates' ), pages, () => showSide( side === 'back' ? 'front' : 'back' ) ) );
	useCommand( palette( 'preview', __( 'Preview in new tab', 'events-certificates' ), external, () => preview() ) );
	useCommand( palette( 'mode', mode === 'light' ? __( 'Preview in dark mode', 'events-certificates' ) : __( 'Preview in light mode', 'events-certificates' ), seen, () => setMode( mode === 'light' ? 'dark' : 'light' ), ! darkEnabled() ) );
	fitLabels().forEach( ( [ mode, label ] ) => useCommand( palette( `fit-${ mode }`, label, seen, () => fit( mode ) ) ) );
	useCommand( palette( 'duplicate', __( 'Duplicate the selected element', 'events-certificates' ), copy, () => selected && duplicate( selected ), ! selectedEl ) );
	useCommand( palette( 'delete', __( 'Delete the selected element', 'events-certificates' ), trash, () => selected && remove( selected ), ! selectedEl || isRequired( selectedEl, design.elements ) ) );
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
					<Button icon={ pages } isPressed={ side === 'back' } onClick={ () => showSide( side === 'back' ? 'front' : 'back' ) } label={ __( 'Declaration', 'events-certificates' ) } />
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
				selected={ selected }
				onSelect={ ( id ) => {
					setSelected( id );
					if ( id ) {
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
							<BlockLayers />
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
					onPointerDownCapture={ ( event ) => {
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
							overlay={
								editing
									? resolvedShown.elements
											.filter( ( el ) => el.id === editing.id && el.type === 'text' )
											.map( ( el ) => (
												<foreignObject key={ el.id } x={ el.x } y={ el.y } width={ el.w } height={ Math.max( ( el as TextEl ).h, ( el as TextEl ).size * ( ( el as TextEl ).lh ?? 1.2 ) * 6 ) } style={ { overflow: 'visible' } } onPointerDown={ ( event ) => event.stopPropagation() }>
													<FieldText
														inline
														label={ stockName( el.name || '' ) || __( 'Text', 'events-certificates' ) }
														runs={ editing.runs }
														onChange={ ( runs ) => setEditing( { id: el.id, runs } ) }
														sample={ sample }
														locale={ LOCALE }
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
								disabled={ ! current }
								onClick={ () => setSidebar( 'element' ) }
							>
								{ __( 'Element', 'events-certificates' ) }
							</button>
							{ /* As the core's: the close icon in a compact button, with a tooltip that has the shortcut. */ }
							<Button size="compact" icon={ close } className="evcert-sidebar-close" onClick={ () => setSidebar( null ) } label={ __( 'Close settings', 'events-certificates' ) } shortcut={ displayShortcut.primaryShift( ',' ) } aria-controls="evcert-settings" />
						</div>
						{ sidebarTab === 'element' && current && (
							<Inspector el={ current } stored={ currentStored } design={ design } mode={ mode } tab={ tab } locale={ LOCALE } sample={ sample } onChange={ ( patch ) => update( current.id, patch ) } onBox={ ( b ) => setBox( current.id, b ) } onImage={ ( picked ) => pickImage( current.id, picked ) } />
						) }
						{ sidebarTab === 'design' && <DesignPanel design={ stored } mode={ mode } title={ title } status={ statusLabel } sample={ sample } defaults={ config.sample } onTitle={ setTitle } roles={ roles } roleLabels={ config.roles } isDefault={ config.isDefault } onRoles={ setRoles } onChange={ replaceDesign } onMode={ setMode } /> }
					</aside>
				) }
			</div>
			</BlocksProvider>

			{ current && ( tab === 'art' || editing ) && ! drawing && (
				<ElementToolbar
					anchor={ editing ? editAnchor : anchor }
					editing={ !! editing }
					marks={ marks }
					onBold={ () => textCommands.current?.bold() }
					onItalic={ () => textCommands.current?.italic() }
					onInsertField={ ( field ) => textCommands.current?.insertField( field ) }
					onToggleEdit={ () => ( editing ? stopEditing() : current && startEditing( current ) ) }
					el={ current }
					required={ isRequired( current, design.elements ) }
					onSelectParent={ () => setSidebar( 'element' ) }
					onOrder={ ( to ) => reorder( current.id, to ) }
					onToggle={ ( key ) => update( current.id, { [ key ]: ! current[ key ] } as Partial< Element > ) }
					onDuplicate={ () => duplicate( current.id ) }
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
				const el = design.elements.find( ( e ) => e.id === drawing );
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

