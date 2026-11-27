/**
 * The event form's small helper (includes/events-admin.php): the end is a length from the start (a select, 2 hours at first). "Set an
 * end time…" takes the select's place with a date and time field, and "Length" brings the select back. Only the one in use is sent
 * (the other is disabled): without JavaScript the server renders the one that fits the event, and the form still posts a length.
 */
( function () {
	'use strict';

	var start = document.getElementById( 'evcert_start' );
	var length = document.getElementById( 'evcert_length' );
	var end = document.getElementById( 'evcert_end' );
	if ( ! start || ! length || ! end ) {
		return;
	}
	var lengthField = length.closest( '.evcert-event__length' );
	var endField = end.closest( '.evcert-event__end' );
	var back = endField ? endField.querySelector( '.evcert-event__back' ) : null;

	/** A datetime-local value (YYYY-MM-DDTHH:MM) plus minutes, in the same form. */
	function plus( value, minutes ) {
		var m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec( value || '' );
		if ( ! m ) {
			return '';
		}
		var date = new Date( +m[ 1 ], +m[ 2 ] - 1, +m[ 3 ], +m[ 4 ], +m[ 5 ] + minutes );
		var pad = function ( n ) {
			return ( n < 10 ? '0' : '' ) + n;
		};
		return date.getFullYear() + '-' + pad( date.getMonth() + 1 ) + '-' + pad( date.getDate() ) + 'T' + pad( date.getHours() ) + ':' + pad( date.getMinutes() );
	}

	/** Shows the select (a length) or the field (an end time), and sends only that one. */
	function show( custom ) {
		length.disabled = custom;
		end.disabled = ! custom;
		if ( lengthField ) {
			lengthField.hidden = custom;
		}
		if ( endField ) {
			endField.hidden = ! custom;
		}
		if ( custom ) {
			if ( ! end.value ) {
				end.value = plus( start.value, 120 );
			}
			end.focus();
		} else {
			length.focus();
		}
	}

	length.addEventListener( 'change', function () {
		if ( 'custom' === length.value ) {
			show( true );
		}
	} );
	if ( back ) {
		back.addEventListener( 'click', function () {
			length.value = '120';
			show( false );
		} );
	}
} )();
