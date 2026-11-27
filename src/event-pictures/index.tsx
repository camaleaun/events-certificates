/**
 * The pictures of an event on its edit form: the community's logo and the signature, as on the Generator (the same picture
 * field). Each is written to hidden inputs of the form (evcert_logo, evcert_logo_svg, evcert_signature_image,
 * evcert_signature_svg), which the form posts with the rest.
 */
import { createRoot, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ImageRow } from '../generator/picture-field';

type Pictures = { logo: string; logo_svg: string; signature_image: string; signature_svg: string };

function Fields( { start, only }: { start: Pictures; only: '' | 'logo' | 'signature' } ) {
	const [ values, setValues ] = useState< Pictures >( start );
	const keys = only === 'logo' ? [ 'logo', 'logo_svg' ] : only === 'signature' ? [ 'signature_image', 'signature_svg' ] : Object.keys( values );
	return (
		<div className="evcert-event-pictures">
			{ only !== 'signature' && <ImageRow image={ values.logo } vector={ values.logo_svg } label={ __( 'Set community logo', 'events-certificates' ) } title={ __( 'Community logo', 'events-certificates' ) } onChange={ ( image, svg ) => setValues( ( old ) => ( { ...old, logo: image, logo_svg: svg } ) ) } /> }
			{ only !== 'logo' && <ImageRow global kind="signature" image={ values.signature_image } vector={ values.signature_svg } label={ __( 'Set signature image', 'events-certificates' ) } title={ __( 'Signature image', 'events-certificates' ) } onChange={ ( image, svg ) => setValues( ( old ) => ( { ...old, signature_image: image, signature_svg: svg } ) ) } /> }
			{ Object.entries( values ).filter( ( [ key ] ) => keys.includes( key ) ).map( ( [ key, value ] ) => (
				<input key={ key } type="hidden" name={ 'evcert_' + key } value={ value } />
			) ) }
		</div>
	);
}

// One root for the whole set, or one each for the logo (beside the community) and the signature (in its box).
document.querySelectorAll< HTMLElement >( '.evcert-event-pictures-root' ).forEach( ( root ) => {
	let start: Pictures = { logo: '', logo_svg: '', signature_image: '', signature_svg: '' };
	try {
		start = { ...start, ...JSON.parse( root.getAttribute( 'data-pictures' ) || '{}' ) };
	} catch ( e ) {
		// No pictures to start from.
	}
	createRoot( root ).render( <Fields start={ start } only={ ( root.getAttribute( 'data-only' ) || '' ) as '' | 'logo' | 'signature' } /> );
} );
