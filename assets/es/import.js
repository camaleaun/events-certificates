/**
 * The import screen's third step: asks the server for one batch after the other (each request does a couple of seconds of
 * work) and moves the progress bar, then goes to the last step. What was done stays if it stops: the state is on the server.
 */
( function () {
	var config = window.evcertImport;
	var bar = document.getElementById( 'evcert-import-progress' );
	var status = document.getElementById( 'evcert-import-status' );
	if ( ! config || ! bar ) {
		return;
	}

	function fail() {
		if ( status ) {
			status.textContent = config.failed;
		}
		var spinner = document.querySelector( '.evcert-card .spinner' );
		if ( spinner ) {
			spinner.classList.remove( 'is-active' );
		}
	}

	function next() {
		var body = new URLSearchParams( { action: 'evcert_import_step', key: config.key, nonce: config.nonce } );
		fetch( config.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.ok ? response.json() : Promise.reject( new Error( 'http' ) );
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success ) {
					return fail();
				}
				bar.value = result.data.percent;
				if ( status ) {
					status.textContent = result.data.percent + '%';
				}
				if ( result.data.done ) {
					window.location.href = config.next;
					return;
				}
				next();
			} )
			.catch( fail );
	}

	next();
}() );
