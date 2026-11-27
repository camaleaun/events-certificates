/**
 * The registered-events Generator (Certificates > Generator): the page is drawn by the server; this adds the bar at the top,
 * which opens the command palette as the editor's document bar does, and the commands it lists as suggestions: the three
 * downloads (they press the header's buttons), the event's screens, the other events, and the plugin's screens.
 */
import { dispatch } from '@wordpress/data';
import { store as commandsStore, useCommand, useCommands } from '@wordpress/commands';
import { createRoot } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { calendar, cog, download, page, pencil } from '@wordpress/icons';

type Config = {
	urls?: Record< string, string >;
	events?: Array< { id: number; title: string; url: string } >;
	current?: number;
	canOne?: boolean;
	canMany?: boolean;
};

declare global {
	interface Window {
		evcertEventsGenerator?: Config;
	}
}

const config: Config = window.evcertEventsGenerator || {};
const CONTEXT = 'root';

type Args = { close: () => void };

const go = ( url?: string ) => ( { close }: Args ) => {
	close();
	if ( url ) {
		window.location.href = url;
	}
};

// A download is the header's own button, so the form posts as it always does.
const press = ( value: string ) => ( { close }: Args ) => {
	close();
	document.querySelector< HTMLButtonElement >( 'button[form="evcert-events-generator"][value="' + value + '"]' )?.click();
};

function Commands() {
	const urls = config.urls || {};
	useCommand( { name: 'evcert/eg-current', label: __( 'Download current', 'events-certificates' ), icon: download, category: 'command', context: CONTEXT, callback: press( 'current' ), disabled: ! config.canOne } );
	useCommand( { name: 'evcert/eg-pdf', label: __( 'Download PDF', 'events-certificates' ), icon: download, category: 'command', context: CONTEXT, callback: press( 'pdf' ), disabled: ! config.canMany } );
	useCommand( { name: 'evcert/eg-zip', label: __( 'Download ZIP', 'events-certificates' ), icon: download, category: 'command', context: CONTEXT, callback: press( 'zip' ), disabled: ! config.canMany } );
	useCommand( { name: 'evcert/eg-edit', label: __( 'Edit the event', 'events-certificates' ), icon: pencil, category: 'command', context: CONTEXT, callback: go( urls.edit ), disabled: ! urls.edit } );
	useCommand( { name: 'evcert/eg-participants', label: __( 'Manage participants', 'events-certificates' ), icon: page, category: 'command', context: CONTEXT, callback: go( urls.participants ), disabled: ! urls.participants } );
	useCommand( { name: 'evcert/eg-events', label: __( 'Go to Events', 'events-certificates' ), icon: calendar, category: 'command', context: CONTEXT, callback: go( urls.events ), disabled: ! urls.events } );
	useCommand( { name: 'evcert/eg-certificates', label: __( 'Go to Certificates', 'events-certificates' ), icon: page, category: 'command', context: CONTEXT, callback: go( urls.certificates ), disabled: ! urls.certificates } );
	useCommand( { name: 'evcert/eg-settings', label: __( 'Go to Settings', 'events-certificates' ), icon: cog, category: 'command', context: CONTEXT, callback: go( urls.settings ), disabled: ! urls.settings } );
	useCommands(
		( config.events || [] )
			.filter( ( event ) => event.id !== config.current )
			.map( ( event ) => ( {
				name: 'evcert/eg-event-' + event.id,
				/* translators: %s: an event's name. */
				label: sprintf( __( 'Generate certificates for %s', 'events-certificates' ), event.title ),
				icon: calendar,
				category: 'command' as const,
				context: CONTEXT,
				callback: go( event.url ),
			} ) )
	);
	return null;
}

// The bar at the top opens the command palette.
document.getElementById( 'evcert-generator-docbar' )?.addEventListener( 'click', () => ( dispatch( commandsStore ) as unknown as { open: () => void } ).open() );

const root = document.getElementById( 'evcert-eg-commands' );
if ( root ) {
	createRoot( root ).render( <Commands /> );
}
