/**
 * The signature on the Settings screen, as WordPress's Site Icon: a button that opens a dialog with three tabs: Upload files,
 * Media Library, and Edit SVG (the design editor's SVG component: a title, the viewBox and the path rows). By default the dialog
 * is WordPress's own media modal (the Site Icon's, with the tab added: `config.chooser` is `media`); a React copy of it is kept
 * (`react`). An SVG file is never uploaded: it is read in the browser into paths. When an SVG is on the clipboard a toast offers to
 * paste it. The choice is written to hidden inputs the form posts: the picture (a PNG data URI, which the PDF needs no file
 * for), the attachment it came from and, for a drawing, its paths as JSON.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, DropZone, FormFileUpload, Modal, Spinner } from '@wordpress/components';
import { createInterpolateElement, createRoot, useEffect, useRef, useState } from '@wordpress/element';
import { __, _x, sprintf } from '@wordpress/i18n';
import SvgSource from '../editor/SvgSource';
import { defaultDrawing, type SvgDraft } from '../editor/svg-draft';
import { PICTURE_PATH_DATA, SVG_LIMITS, sanitizeSvg, svgCodeAllowed } from '../editor/model';
import { ClipboardToast, hasMedia, openMediaChooser, type Choice } from '../media-chooser';
import { PATH_DESIGN, drawingJson, readVector } from '../media-chooser/shared';

type Config = { option: string; image: string; json: string; attachment: number; maxUpload: string; canUpload: boolean; suggested: string; chooser: 'media' | 'react' };
type Tab = 'upload' | 'library' | 'svg';
type Media = { id: number; mime_type: string; source_url: string; alt_text: string; title: { rendered: string }; media_details?: { sizes?: Record< string, { source_url: string } > } };

declare global {
	interface Window {
		evcertSignatureField?: Config;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		wp: any;
	}
}

const config = window.evcertSignatureField as Config;
const EMPTY: SvgDraft = { title: '', vw: 120, vh: 44, paths: [] };



function Preview( { choice }: { choice: Choice } ) {
	const drawn = readVector( choice.json );
	if ( choice.image ) {
		return <img className="evcert-signature-field__preview" src={ choice.image } alt={ __( 'Current signature', 'events-certificates' ) } />;
	}
	return (
		<svg className="evcert-signature-field__preview" viewBox={ `0 0 ${ drawn.vw } ${ drawn.vh }` } role="img" aria-label={ __( 'Current signature', 'events-certificates' ) }>
			{ drawn.paths.map( ( p, i ) => <path key={ i } d={ p.d } fill={ p.fill && ! p.fill.startsWith( '$' ) ? p.fill : 'currentColor' } fillRule={ p.rule } /> ) }
		</svg>
	);
}

function UploadTab( { onPicked, onSvg }: { onPicked: ( choice: Choice ) => void; onSvg: ( draft: SvgDraft ) => void } ) {
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const handle = async ( file: File ) => {
		setError( '' );
		setBusy( true );
		try {
			if ( 'image/svg+xml' === file.type || /\.svg$/i.test( file.name ) ) {
				// Never uploaded as a file: only its filled paths are kept (the same cleaning as the design editor's import), and
				// a file that is too big is not even read.
				const svg = file.size > SVG_LIMITS.bytes ? null : sanitizeSvg( await file.text(), { pathData: PICTURE_PATH_DATA } );
				if ( ! svg || ! svg.paths.length ) {
					setError( __( 'That SVG cannot be used: it is too big, carries scripts or has no filled paths. Paths are what a signature is made of.', 'events-certificates' ) );
				} else {
					onSvg( { title: file.name.replace( /\.svg$/i, '' ), ...svg } );
				}
				return;
			}
			// A picture is a file of the Media Library: it is uploaded there and only its ID is kept.
			if ( ! config.canUpload ) {
				setError( __( 'You are not allowed to upload files. Choose one from the media library, or use an SVG.', 'events-certificates' ) );
				return;
			}
			const body = new FormData();
			body.append( 'file', file, file.name );
			const made = ( await apiFetch( { path: '/wp/v2/media', method: 'POST', body } ) ) as { id: number; source_url: string; media_details?: { sizes?: { large?: { source_url: string } } } };
			onPicked( { image: made.media_details?.sizes?.large?.source_url || made.source_url, json: '', attachment: made.id } );
		} catch ( e ) {
			setError( __( 'That picture could not be read. Use a PNG, JPEG, GIF, WebP or SVG.', 'events-certificates' ) );
		} finally {
			setBusy( false );
		}
	};
	return (
		<div className="evcert-signature-upload">
			<DropZone onFilesDrop={ ( files ) => files[ 0 ] && handle( files[ 0 ] ) } label={ __( 'Drop files to upload', 'events-certificates' ) } />
			<p className="evcert-signature-upload__title">{ __( 'Drop files to upload', 'events-certificates' ) }</p>
			<p>{ __( 'or', 'events-certificates' ) }</p>
			<FormFileUpload accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml,.svg" onChange={ ( e ) => { const first = e.currentTarget.files?.[ 0 ]; if ( first ) { handle( first ); } e.currentTarget.value = ''; } } render={ ( { openFileDialog } ) => (
				<Button variant="secondary" __next40pxDefaultSize onClick={ openFileDialog } disabled={ busy }>
					{ __( 'Select Files', 'events-certificates' ) }
				</Button>
			) } />
			{ busy && <Spinner /> }
			{ error && <p className="evcert-signature-upload__error" role="alert">{ error }</p> }
			<p className="description">
				{ sprintf( /* translators: %s: the largest file size WordPress accepts (2 GB). */ __( 'Maximum upload file size: %s.', 'events-certificates' ), config.maxUpload ) }
				<br />
				{ sprintf( /* translators: %s: suggested pixels, e.g. 600 × 220. */ __( 'Suggested image dimensions: %s pixels.', 'events-certificates' ), config.suggested ) }
			</p>
		</div>
	);
}

function LibraryTab( { onPicked }: { onPicked: ( choice: Choice ) => void } ) {
	const [ items, setItems ] = useState< Media[] >( [] );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ pages, setPages ] = useState( 1 );
	const [ busy, setBusy ] = useState( true );
	const [ error, setError ] = useState( '' );
	const first = useRef( true );
	useEffect( () => {
		let live = true;
		setBusy( true );
		const timer = window.setTimeout( () => {
			apiFetch( { path: `/wp/v2/media?media_type=image&per_page=24&page=${ page }&search=${ encodeURIComponent( search ) }&_fields=id,mime_type,source_url,alt_text,title,media_details`, parse: false } )
				.then( async ( response ) => {
					const list = ( await ( response as Response ).json() ) as Media[];
					if ( ! live ) {
						return;
					}
					setPages( Number( ( response as Response ).headers.get( 'X-WP-TotalPages' ) ) || 1 );
					setItems( ( old ) => ( page > 1 ? [ ...old, ...list ] : list ).filter( ( m ) => 'image/svg+xml' !== m.mime_type ) );
					setError( '' );
				} )
				.catch( () => live && setError( __( 'The media library could not be loaded.', 'events-certificates' ) ) )
				.finally( () => live && setBusy( false ) );
		}, first.current ? 0 : 300 );
		first.current = false;
		return () => {
			live = false;
			window.clearTimeout( timer );
		};
	}, [ search, page ] );
	const pick = async ( media: Media ) => {
		setBusy( true );
		onPicked( { image: media.media_details?.sizes?.large?.source_url || media.source_url, json: '', attachment: media.id } );
		setBusy( false );
	};
	return (
		<div className="evcert-signature-library">
			<input type="search" className="evcert-signature-library__search" value={ search } placeholder={ __( 'Search media', 'events-certificates' ) } aria-label={ __( 'Search media', 'events-certificates' ) } onChange={ ( e ) => { setPage( 1 ); setSearch( e.target.value ); } } />
			{ error && <p className="evcert-signature-upload__error" role="alert">{ error }</p> }
			<ul className="evcert-signature-library__grid">
				{ items.map( ( media ) => (
					<li key={ media.id }>
						<button type="button" onClick={ () => pick( media ) } aria-label={ media.alt_text || media.title.rendered }>
							<img src={ media.media_details?.sizes?.thumbnail?.source_url || media.source_url } alt="" loading="lazy" />
						</button>
					</li>
				) ) }
			</ul>
			{ ! busy && ! items.length && ! error && <p className="description">{ __( 'No images found.', 'events-certificates' ) }</p> }
			{ busy && <Spinner /> }
			{ ! busy && page < pages && (
				<Button variant="secondary" onClick={ () => setPage( page + 1 ) }>
					{ __( 'Load more', 'events-certificates' ) }
				</Button>
			) }
		</div>
	);
}

function Chooser( { onChoose, onClose, start }: { onChoose: ( choice: Choice ) => void; onClose: () => void; start: SvgDraft } ) {
	const [ tab, setTab ] = useState< Tab >( 'upload' );
	// As the media modal's SVG tab: it starts with the info icon (96 x 96) when there is no drawing yet.
	const [ draft, setDraft ] = useState< SvgDraft | null >( () => ( start.paths.length ? start : defaultDrawing() ) );
	const tabs: Array< [ Tab, string ] > = [
		[ 'upload', __( 'Upload files', 'events-certificates' ) ],
		[ 'library', __( 'Media Library', 'events-certificates' ) ],
		[ 'svg', __( 'Edit SVG', 'events-certificates' ) ],
	];
	const usable = ( draft?.paths || [] ).filter( ( p ) => p.d );
	return (
		<Modal title={ __( 'Choose a certificate signature', 'events-certificates' ) } size="large" onRequestClose={ onClose } className="evcert-signature-modal">
			<ClipboardToast onPaste={ ( next ) => { setDraft( next ); setTab( 'svg' ); } } />
			<div className="evcert-signature-modal__tabs" role="tablist" aria-label={ __( 'Choose a certificate signature', 'events-certificates' ) }>
				{ tabs.map( ( [ id, label ] ) => (
					<button key={ id } type="button" role="tab" id={ `evcert-signature-tab-${ id }` } aria-selected={ tab === id } aria-controls={ `evcert-signature-panel-${ id }` } className={ tab === id ? 'is-active' : '' } onClick={ () => setTab( id ) }>
						{ label }
					</button>
				) ) }
			</div>
			<div role="tabpanel" id={ `evcert-signature-panel-${ tab }` } aria-labelledby={ `evcert-signature-tab-${ tab }` } className="evcert-signature-modal__panel">
				{ 'upload' === tab && <UploadTab onPicked={ onChoose } onSvg={ ( next ) => { setDraft( next ); setTab( 'svg' ); } } /> }
				{ 'library' === tab && <LibraryTab onPicked={ onChoose } /> }
				{ 'svg' === tab && (
					<>
						<SvgSource draft={ draft } onChange={ setDraft } design={ PATH_DESIGN } mode="light" base={ start } dropFiles />
						<div className="evcert-actions evcert-svg-editor__footer">
							<Button variant="tertiary" __next40pxDefaultSize onClick={ onClose }>
								{ __( 'Cancel', 'events-certificates' ) }
							</Button>
							<Button variant="primary" __next40pxDefaultSize disabled={ ! usable.length } accessibleWhenDisabled onClick={ () => draft && onChoose( { image: '', attachment: 0, json: drawingJson( draft, usable ) } ) }>
								{ __( 'Apply', 'events-certificates' ) }
							</Button>
						</div>
					</>
				) }
			</div>
		</Modal>
	);
}


function Field() {
	const [ choice, setChoice ] = useState< Choice >( { image: config.image, json: config.json, attachment: config.attachment } );
	const [ open, setOpen ] = useState( false );
	const drawn = readVector( choice.json );
	const has = !! choice.image || drawn.paths.some( ( p ) => p.d );
	const start: SvgDraft = drawn.paths.length ? { title: '', vw: drawn.vw, vh: drawn.vh, w: drawn.w, h: drawn.h, paths: drawn.paths } : EMPTY;
	const pick = ( next: Choice ) => {
		setChoice( next );
		setOpen( false );
	};
	// As the Site Icon's row (same classes, so the same sizes): the preview, the buttons, then the help text.
	return (
		<>
			<input type="hidden" name={ `${ config.option }[signature_json]` } value={ choice.json } />
			<input type="hidden" name={ `${ config.option }[signature_attachment]` } value={ choice.attachment || '' } />
			{ has && <div className="evcert-signature-field__current"><Preview choice={ choice } /></div> }
			<div className="site-icon-action-buttons">
				<button type="button" className="upload-button button-hero button" aria-haspopup="dialog" onClick={ () => ( 'media' === config.chooser && hasMedia() ? openMediaChooser( { title: __( 'Choose a certificate signature', 'events-certificates' ), button: __( 'Set as certificate signature', 'events-certificates' ), suggested: [ 600, 220 ], start, onChoose: pick } ) : setOpen( true ) ) }>
					{ has ? __( 'Change certificate signature', 'events-certificates' ) : __( 'Choose a certificate signature', 'events-certificates' ) }
				</button>
				{ has && (
					<button type="button" className="button button-secondary reset remove-site-icon evcert-remove" onClick={ () => setChoice( { image: '', json: '', attachment: 0 } ) }>
						{ __( 'Remove certificate signature', 'events-certificates' ) }
					</button>
				) }
			</div>
			<p className="description">
				{ createInterpolateElement(
					sprintf( /* translators: %s: suggested pixels wrapped in <code>, e.g. <code>600 by 220</code>. */ __( 'The signature closes each certificate. It should have a transparent background and be at least %s pixels.', 'events-certificates' ), '<code>' + config.suggested.replace( ' × ', ' ' + _x( 'by', 'between the width and the height of a picture', 'events-certificates' ) + ' ' ) + '</code>' ),
					{ code: <code /> }
				) }
			</p>
			{ open && <Chooser onChoose={ pick } onClose={ () => setOpen( false ) } start={ start } /> }
		</>
	);
}

const root = document.getElementById( 'evcert-signature-field' );
if ( root && config ) {
	createRoot( root ).render( <Field /> );
}
