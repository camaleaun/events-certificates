/**
 * The picture chooser of the plugin's screens: WordPress's own media modal, as the Site Icon's ("Choose a Site Icon": the Upload
 * files and Media Library tabs, the suggested size, a button that sets the choice), with an SVG tab added (SVG code, then the
 * design editor's SVG component) and a toast that offers to paste an SVG found on the clipboard. An SVG file is never uploaded: it
 * is read in the browser into paths. The choice is a PNG data URI (the PDF needs no file) and the attachment it came from, or the
 * paths of a drawing as JSON.
 */
import { Button } from '@wordpress/components';
import { createRoot, useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import SvgSource from '../editor/SvgSource';
import { defaultDrawing, type SvgDraft } from '../editor/svg-draft';
import { PATH_DESIGN, drawingJson } from './shared';
import { PICTURE_PATH_DATA } from '../editor/model';
import { offerFor, parseSvgText } from './svg-code';

declare global {
	interface Window {
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		wp: any;
	}
}

export type Choice = { image: string; json: string; attachment: number };

/** Whether the media modal is on the page (the screen asked for wp_enqueue_media()). */
export const hasMedia = () => !! window.wp?.media;

/** The drawing in an SVG file (read here, in the browser, never uploaded: WordPress does not take SVG files), or null. */
async function svgFile( file: File ): Promise< SvgDraft | null > {
	const parsed = parseSvgText( await file.text(), file.name.replace( /\.svg$/i, '' ), PICTURE_PATH_DATA );
	return 'draft' in parsed ? parsed.draft : null;
}

const isSvgFile = ( file: File ) => /^image\/svg/i.test( file.type ) || /\.svg$/i.test( file.name );

// What the clipboard holds, or null when it can't be read. The browser refuses while the document has no focus: one more try.
async function readClipboard(): Promise< string | null > {
	const read = () => navigator.clipboard.readText().catch( () => null );
	return ( await read() ) ?? ( await new Promise< string | null >( ( resolve ) => window.setTimeout( () => read().then( resolve ), 150 ) ) );
}

const OFFER_MS = 9000;
const ERROR_MS = 6000;
const LEAVE_MS = 200;

type Toast = { kind: 'offer' | 'error'; text: string };

/**
 * A toast while the dialog is open: when an SVG is copied, it says so and offers to paste it (to the SVG tab). The clipboard
 * is looked at when the dialog opens and each time the page gets the focus back (the code is often copied from another tab or
 * app). It goes away by itself when not used (paused while the pointer or the focus is on it), and when used or closed; new
 * content is offered again, the same content is not. It comes and goes with a short animation.
 *
 * @param onPaste Called with the drawing when the link is used.
 * @param modal   The dialog's box, to look again when the pointer comes back into it.
 */
export function ClipboardToast( { onPaste, modal }: { onPaste: ( draft: SvgDraft ) => void; modal?: HTMLElement | null } ) {
	const [ toast, setToast ] = useState< Toast | null >( null );
	const [ leaving, setLeaving ] = useState( false );
	const [ paused, setPaused ] = useState( false );
	const handled = useRef( '' );
	const current = useRef< Toast | null >( null );
	const busy = useRef( false );
	const when = useRef( 0 );

	const show = useCallback( ( next: Toast | null ) => {
		current.current = next;
		setLeaving( false );
		setToast( next );
	}, [] );

	// Out with the animation; what was offered counts as handled, so the same content does not come back.
	const dismiss = useCallback( () => {
		const gone = current.current;
		if ( ! gone ) {
			return;
		}
		if ( gone.kind === 'offer' ) {
			handled.current = gone.text;
		}
		current.current = null;
		setLeaving( true );
		window.setTimeout( () => {
			if ( ! current.current ) {
				setToast( null );
				setLeaving( false );
			}
		}, LEAVE_MS );
	}, [] );

	const check = useCallback( async () => {
		if ( busy.current ) {
			return;
		}
		busy.current = true;
		const clip = await readClipboard();
		busy.current = false;
		if ( clip === null ) {
			return;
		}
		const code = offerFor( clip, handled.current );
		if ( code && current.current?.text !== code ) {
			show( { kind: 'offer', text: code } );
		} else if ( ! code && current.current?.kind === 'offer' ) {
			dismiss();
		}
	}, [ show, dismiss ] );

	useEffect( () => {
		const again = () => {
			// The pointer moves in and out a lot: look at most once a second.
			if ( Date.now() - when.current > 1000 ) {
				when.current = Date.now();
				check();
			}
		};
		const visible = () => document.visibilityState === 'visible' && again();
		const problem = ( event: Event ) => show( { kind: 'error', text: ( event as CustomEvent< string > ).detail } );
		check();
		window.addEventListener( 'focus', again );
		document.addEventListener( 'visibilitychange', visible );
		window.addEventListener( 'evcert:svg-problem', problem );
		modal?.addEventListener( 'pointerenter', again );
		return () => {
			window.removeEventListener( 'focus', again );
			document.removeEventListener( 'visibilitychange', visible );
			window.removeEventListener( 'evcert:svg-problem', problem );
			modal?.removeEventListener( 'pointerenter', again );
		};
	}, [ check, show, modal ] );

	// Not used: it goes away by itself, unless the pointer or the focus is on it.
	useEffect( () => {
		if ( ! toast || leaving || paused ) {
			return undefined;
		}
		const timer = window.setTimeout( dismiss, toast.kind === 'error' ? ERROR_MS : OFFER_MS );
		return () => window.clearTimeout( timer );
	}, [ toast, leaving, paused, dismiss ] );

	if ( ! toast ) {
		return null;
	}
	const hold = { onMouseEnter: () => setPaused( true ), onMouseLeave: () => setPaused( false ), onFocus: () => setPaused( true ), onBlur: () => setPaused( false ) };
	return (
		<div className={ `evcert-toast${ toast.kind === 'error' ? ' is-error' : '' }${ leaving ? ' is-leaving' : '' }` } role={ toast.kind === 'error' ? 'alert' : 'status' } { ...hold }>
			<span>{ toast.kind === 'error' ? toast.text : __( 'You have an SVG copied. Paste it as the signature?', 'events-certificates' ) }</span>
			{ toast.kind === 'offer' && (
				<button
					type="button"
					className="button-link"
					onClick={ () => {
						const parsed = parseSvgText( toast.text, '', PICTURE_PATH_DATA );
						if ( 'draft' in parsed ) {
							onPaste( parsed.draft );
						}
						dismiss();
					} }
				>
					{ __( 'Paste', 'events-certificates' ) }
				</button>
			) }
			<button type="button" className="evcert-toast__close" aria-label={ __( 'Close', 'events-certificates' ) } onClick={ dismiss }>
				×
			</button>
		</div>
	);
}

// The SVG tab of the media modal. It starts with the info icon (a square of 96 x 96) as the drawing; "Paste another SVG" goes to the SVG code
// box (src/editor/SvgSource.tsx, the same as the editor's dialog has), and a usable SVG (pasted, dropped as a file or offered by the toast)
// becomes the drawing: the preview, the viewBox and the paths.
function SvgTab( { start, onChoose, onClose }: { start: SvgDraft; onChoose: ( choice: Choice ) => void; onClose: () => void } ) {
	const [ draft, setDraft ] = useState< SvgDraft | null >( () => ( start.paths.length ? start : defaultDrawing() ) );
	useEffect( () => {
		const set = ( event: Event ) => setDraft( ( event as CustomEvent< SvgDraft > ).detail );
		window.addEventListener( 'evcert:svg-draft', set );
		return () => window.removeEventListener( 'evcert:svg-draft', set );
	}, [] );

	const usable = ( draft?.paths || [] ).filter( ( p ) => p.d );
	return (
		<>
			<div className="evcert-svg-tab__content">
				<SvgSource draft={ draft } onChange={ setDraft } design={ PATH_DESIGN } mode="light" base={ start } />
			</div>
			<div className="evcert-actions evcert-svg-editor__footer">
				<Button variant="tertiary" __next40pxDefaultSize onClick={ onClose }>
					{ __( 'Cancel', 'events-certificates' ) }
				</Button>
				<Button variant="primary" __next40pxDefaultSize disabled={ ! usable.length } accessibleWhenDisabled onClick={ () => draft && onChoose( { image: '', attachment: 0, json: drawingJson( draft, usable ) } ) }>
					{ __( 'Apply', 'events-certificates' ) }
				</Button>
			</div>
		</>
	);
}

/**
 * WordPress's media modal, as the Site Icon's ("Choose a Site Icon": Upload files and Media Library tabs, the suggested size, a
 * button that sets the choice), with an SVG tab added and the toast for a copied SVG.
 *
 * @param start    The drawing the SVG tab starts from.
 * @param onChoose Called with the choice.
 */
export function openMediaChooser( { title, button, suggested, start, onChoose }: { title: string; button: string; suggested: [ number, number ]; start: SvgDraft; onChoose: ( choice: Choice ) => void } ) {
	const media = window.wp.media;
	const frame = media( {
		title,
		button: { text: button, close: false },
		multiple: false,
		states: [
			new media.controller.Library( {
				title,
				library: media.query( { type: [ 'image/png', 'image/jpeg', 'image/gif', 'image/webp' ] } ), // Never an SVG file: drawings are paths.
				multiple: false,
				date: false,
				priority: 20,
				suggestedWidth: suggested[ 0 ],
				suggestedHeight: suggested[ 1 ],
			} ),
		],
	} );
	const roots: Array< { unmount: () => void } > = [];
	const mount = ( node: HTMLElement, element: JSX.Element ) => {
		const root = createRoot( node );
		root.render( element );
		roots.push( root );
	};
	frame.on( 'select', () => {
		const attachment = frame.state().get( 'selection' ).first().toJSON();
		// A picture is kept as its attachment (the ID); `image` is only its address, to show it.
		onChoose( { image: attachment.sizes?.large?.url || attachment.url, json: '', attachment: attachment.id } );
		frame.close();
	} );
	// The third tab.
	frame.on( 'router:render:browse', ( router: { set: ( tabs: Record< string, { text: string; priority: number } > ) => void } ) => {
		router.set( { 'evcert-svg': { text: __( 'SVG', 'events-certificates' ), priority: 60 } } );
	} );
	frame.on( 'content:create:evcert-svg', () => {
		const View = media.View.extend( {
			className: 'evcert-signature-svgtab',
			render() {
				mount( this.el, <SvgTab start={ start } onChoose={ ( choice ) => { onChoose( choice ); frame.close(); } } onClose={ () => frame.close() } /> );
				return this;
			},
		} );
		frame.content.set( new View() );
	} );
	// This tab has its own buttons: the modal's toolbar (it sets a picture) is hidden there.
	frame.on( 'content:activate:evcert-svg', () => frame.$el.addClass( 'evcert-svg-mode' ) );
	frame.on( 'content:deactivate:evcert-svg', () => frame.$el.removeClass( 'evcert-svg-mode' ) );
	// The SVG tab with a drawing in it (the tab mounts after the mode changes).
	const showDraft = ( draft: SvgDraft ) => {
		frame.content.mode( 'evcert-svg' );
		window.setTimeout( () => window.dispatchEvent( new CustomEvent( 'evcert:svg-draft', { detail: draft } ) ), 50 );
	};
	// An SVG file dropped on the dialog, or picked with Select Files, goes to the SVG tab instead of the uploader (which would
	// refuse it: WordPress does not take SVG files). The listeners are on the document, in the capture phase, so they run first.
	const files = ( event: Event ): File[] => {
		const target = event.target as HTMLInputElement;
		return Array.from( event.type === 'drop' ? ( event as DragEvent ).dataTransfer?.files || [] : target?.type === 'file' ? target.files || [] : [] );
	};
	const takeSvg = ( event: Event ) => {
		if ( ! frame.$el.closest( '.media-modal' ).is( ':visible' ) ) {
			return;
		}
		const svg = files( event ).find( isSvgFile );
		if ( ! svg ) {
			return;
		}
		event.preventDefault();
		event.stopImmediatePropagation();
		if ( event.type === 'change' ) {
			( event.target as HTMLInputElement ).value = '';
		} else {
			// The uploader's own drop handler never ran: it is what hides its "Drop files here" cover.
			frame.modal.$el.trigger( 'dropzone:leave' ).removeClass( 'drag-over' );
		}
		svgFile( svg ).then( ( draft ) => {
			if ( draft ) {
				showDraft( draft );
			} else {
				window.dispatchEvent( new CustomEvent( 'evcert:svg-problem', { detail: __( 'That SVG could not be used: only its paths are read, so shapes and text must be converted to paths first, and it must be a small file.', 'events-certificates' ) } ) );
			}
		} );
	};
	frame.on( 'open', () => {
		document.addEventListener( 'drop', takeSvg, true );
		document.addEventListener( 'change', takeSvg, true );
		const host = document.createElement( 'div' );
		host.className = 'evcert-toast-host';
		// The dialog's box is outside the frame's own element.
		const box = frame.$el.closest( '.media-modal' );
		box.append( host );
		mount( host, <ClipboardToast onPaste={ showDraft } modal={ box[ 0 ] } /> );
	} );
	frame.on( 'close', () => {
		document.removeEventListener( 'drop', takeSvg, true );
		document.removeEventListener( 'change', takeSvg, true );
		roots.splice( 0 ).forEach( ( root ) => window.setTimeout( () => root.unmount(), 0 ) );
		frame.$el.closest( '.media-modal' ).find( '.evcert-toast-host' ).remove();
	} );
	frame.open();
}
