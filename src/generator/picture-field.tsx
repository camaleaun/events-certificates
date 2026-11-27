/**
 * A picture for the certificate (the community's logo, or the signature), set like the editor's featured image:
 * a button, then a dialog to upload an image or to insert SVG paths as the design editor's SVG component does (its Edit SVG
 * dialog); for the signature, also to use the one of Settings. Used by the Generator and the event's form.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Modal, __experimentalVStack as VStack } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { SvgEl } from '../editor/model';
import SvgEditor from '../editor/SvgEditor';
import { hasMedia, openMediaChooser } from '../media-chooser';
import { PATH_DESIGN, drawingJson, readVector, type Vector } from '../media-chooser/shared';

export { PATH_DESIGN, readVector, type Vector };

// A raster picture is the ID of an attachment of the Media Library (never its bytes). This is what shows it: the address of each ID seen.
const addresses: Record< string, string > = {};
export const pictureSrc = ( id: string ) => ( id ? addresses[ id ] || '' : '' );
type Media = { id: number; source_url: string; media_details?: { sizes?: { large?: { source_url: string } } } };
const addressOf = ( media: Media ) => media.media_details?.sizes?.large?.source_url || media.source_url;

// A picture for the certificate (the community's logo, or the signature), set like the editor's featured image:
// a button, then a dialog to upload an image or to insert SVG paths as the design editor's SVG component does
// (a viewBox and path rows); for the signature, also to use the one of Settings.
export function ImageRow( { image, vector, label, title, global, kind = 'logo', onChange }: { image: string; vector: string; label: string; title: string; global?: boolean; kind?: 'logo' | 'signature'; onChange: ( image: string, vector: string ) => void } ) {
	const [ open, setOpen ] = useState( false );
	const [ drawing, setDrawing ] = useState( false );
	const [ error, setError ] = useState( '' );
	const input = useRef< HTMLInputElement >( null );
	const drawn = readVector( vector );
	const [ , refresh ] = useState( 0 );
	const shown = pictureSrc( image );
	const has = !! image || drawn.paths.some( ( p ) => p.d );
	// A picture that was already set: its address is asked of the Media Library once.
	useEffect( () => {
		if ( image && ! addresses[ image ] ) {
			apiFetch( { path: `/wp/v2/media/${ image }?_fields=id,source_url,media_details` } )
				.then( ( media ) => {
					addresses[ image ] = addressOf( media as Media );
					refresh( ( n ) => n + 1 );
				} )
				.catch( () => undefined );
		}
	}, [ image ] );
	const done = ( id: string, svg: string ) => {
		onChange( id, svg );
		setOpen( false );
		setError( '' );
	};
	// Without the media modal: the file goes to the Media Library and only its ID is kept.
	const fromFile = async ( file: File ) => {
		try {
			if ( /^image\/svg/i.test( file.type ) || /\.svg$/i.test( file.name ) ) {
				throw new Error( 'svg' );
			}
			const body = new FormData();
			body.append( 'file', file, file.name );
			const made = ( await apiFetch( { path: '/wp/v2/media', method: 'POST', body } ) ) as Media;
			addresses[ String( made.id ) ] = addressOf( made );
			done( String( made.id ), '' );
		} catch ( e ) {
			setError( __( 'That picture could not be uploaded. Use a PNG, JPEG, GIF or WebP; for a drawing, insert SVG paths.', 'events-certificates' ) );
		}
	};
	// The design editor's own Edit SVG dialog (a title, the viewBox and the path rows), on a copy of what is set.
	const svgEl = { id: 'picture', type: 'svg', name: title, x: 0, y: 0, w: 120, h: 44, vw: drawn.vw, vh: drawn.vh, paths: drawn.paths, color: '$text' } as SvgEl;
	return (
		<div className="evcert-image">
			<Button
				variant="secondary"
				className="evcert-image__button"
				onClick={ () => {
					if ( ! hasMedia() ) {
						setOpen( true );
						return;
					}
					// WordPress's media modal, as the Site Icon's, with the Edit SVG tab (src/media-chooser).
					openMediaChooser( {
						title: 'logo' === kind ? __( 'Choose a community logo', 'events-certificates' ) : __( 'Choose a signature image', 'events-certificates' ),
						button: 'logo' === kind ? __( 'Set as community logo', 'events-certificates' ) : __( 'Set as signature image', 'events-certificates' ),
						suggested: 'logo' === kind ? [ 600, 200 ] : [ 600, 220 ],
						start: drawn.paths.length ? { title: '', vw: drawn.vw, vh: drawn.vh, w: drawn.w, h: drawn.h, paths: drawn.paths } : { title: '', vw: 120, vh: 44, paths: [] },
						onChoose: ( choice ) => {
							if ( choice.attachment ) {
								addresses[ String( choice.attachment ) ] = choice.image;
							}
							onChange( choice.attachment ? String( choice.attachment ) : '', choice.json );
						},
					} );
				} }
			>
				{ shown && <img className="evcert-image__preview" src={ shown } alt="" /> }
				{ ! shown && drawn.paths.some( ( p ) => p.d ) && (
					<svg className="evcert-image__preview" viewBox={ `0 0 ${ drawn.vw } ${ drawn.vh }` } aria-hidden="true">
						{ drawn.paths.map( ( p, i ) => <path key={ i } d={ p.d } fill={ p.fill && ! p.fill.startsWith( '$' ) ? p.fill : 'currentColor' } fillRule={ p.rule } /> ) }
					</svg>
				) }
				{ has ? __( 'Replace', 'events-certificates' ) : label }
			</Button>
			{ has && (
				<Button variant="link" isDestructive onClick={ () => onChange( '', '' ) }>
					{ __( 'Remove', 'events-certificates' ) }
				</Button>
			) }
			{ open && (
				<Modal title={ title } size="small" onRequestClose={ () => setOpen( false ) }>
					<VStack spacing={ 3 }>
						<Button variant="secondary" onClick={ () => input.current?.click() }>
							{ __( 'Upload an image', 'events-certificates' ) }
						</Button>
						<input type="file" ref={ input } accept="image/png,image/jpeg,image/gif,image/webp" hidden onChange={ ( e ) => { const first = e.target.files?.[ 0 ]; if ( first ) { fromFile( first ); } e.target.value = ''; } } />
						<Button
							variant="secondary"
							onClick={ () => {
								setOpen( false );
								setDrawing( true );
							} }
						>
							{ __( 'Insert SVG paths', 'events-certificates' ) }
						</Button>
						{ global && (
							<Button variant="tertiary" onClick={ () => done( '', '' ) }>
								{ __( 'Use the one of Settings', 'events-certificates' ) }
							</Button>
						) }
						{ error && <p className="evcert-image__error" role="alert">{ error }</p> }
					</VStack>
				</Modal>
			) }
			{ drawing && (
				<SvgEditor
					el={ svgEl }
					design={ PATH_DESIGN }
					mode="light"
					preferred={ { w: drawn.w, h: drawn.h } }
					onApply={ () => undefined }
					onApplyDrawing={ ( draft ) => onChange( '', draft.paths.length ? drawingJson( draft, draft.paths ) : '' ) }
					onClose={ () => setDrawing( false ) }
				/>
			) }
		</div>
	);
}

