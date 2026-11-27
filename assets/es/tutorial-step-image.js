/**
 * The enlarged view of a tutorial picture (includes/tutorial-step-image.php): a click on the picture opens
 * a copy of the same SVG over the page, as the generator's step-by-step does: a dark layer, the picture
 * centered up to 1000 px wide, and a click anywhere or the Escape key closes it. It is a copy of the
 * drawing, not another picture, so what is enlarged is what was clicked.
 */
( function () {
	'use strict';

	var zoom = null;

	var close = function () {
		if ( ! zoom ) {
			return;
		}
		var opener = zoom.opener;
		zoom.box.remove();
		document.removeEventListener( 'keydown', onKey, true );
		zoom = null;
		if ( opener && document.contains( opener ) ) {
			opener.focus();
		}
	};

	var onKey = function ( event ) {
		if ( 'Escape' === event.key ) {
			event.preventDefault();
			close();
		} else if ( 'Tab' === event.key ) {
			// Nothing inside to reach: the focus stays on the layer.
			event.preventDefault();
		}
	};

	var open = function ( button ) {
		var svg = button.querySelector( 'svg' );
		if ( ! svg || zoom ) {
			return;
		}
		var copy = svg.cloneNode( true );
		// The copy fills the layer by its viewBox; its ids must not repeat the picture's own.
		copy.removeAttribute( 'width' );
		copy.removeAttribute( 'height' );
		var labelled = copy.getAttribute( 'aria-labelledby' );
		if ( labelled ) {
			copy.setAttribute( 'aria-labelledby', labelled.split( ' ' ).map( function ( id ) {
				return id + '-zoom';
			} ).join( ' ' ) );
			copy.querySelectorAll( '[id]' ).forEach( function ( element ) {
				element.id += '-zoom';
			} );
		}

		var box = document.createElement( 'div' );
		box.className = 'evcert-tsi-lightbox';
		box.setAttribute( 'role', 'dialog' );
		box.setAttribute( 'aria-modal', 'true' );
		box.setAttribute( 'aria-label', button.getAttribute( 'aria-label' ) || '' );
		box.tabIndex = -1;
		// In a wrapper of the same class as the picture's own, so the drawing's CSS reaches the copy as it does the picture.
		var wrap = document.createElement( 'div' );
		wrap.className = 'evcert-tsi';
		wrap.appendChild( copy );
		box.appendChild( wrap );
		box.addEventListener( 'click', close );
		document.body.appendChild( box );
		zoom = { box: box, opener: button };
		document.addEventListener( 'keydown', onKey, true );
		box.focus();
	};

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '.evcert-tsi__open' ) : null;
		// The admin screen's tutorial modal enlarges the drawing by itself.
		if ( button && ! button.closest( '.evcert-tutorial' ) ) {
			open( button );
		}
	} );
}() );
