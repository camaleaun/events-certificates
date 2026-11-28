<?php
/**
 * Generator: an event and its list of names on one screen; one click
 * makes the event, its participants (as attended) and their certificates,
 * and downloads them, like the meetup certificate generators that run in a
 * browser, but every certificate is verifiable by its code.
 * docs/decisions/0015.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Generator;

use EventsCertificate\Admin;
use EventsCertificate\Events;
use EventsCertificate\EventsAdmin;
use EventsCertificate\People;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page slug.
 */
const SLUG = 'evcert-quick-generator';

/**
 * Hooks.
 */
function bootstrap() {
	// The hooks are always there (the other plugins load after this one, so the mode is not known yet);
	// the screen itself exists only without CampTix and GatherPress (decision 0026).
	add_action( 'admin_menu', __NAMESPACE__ . '\add_page', 19 );
	add_filter( 'admin_body_class', __NAMESPACE__ . '\body_class' );
	add_action( 'rest_api_init', __NAMESPACE__ . '\register_routes' );
}

/**
 * The most names the screen's list takes at a time.
 */
const MAX_PEOPLE = 2000;

/**
 * Most certificates one download makes (the PDF engine draws them in one request).
 */
const MAX_CERTIFICATES = 400;

/**
 * The route the screen's "Add names" button calls: the typed names and the CSV, read by the same code that issues.
 */
function register_routes() {
	register_rest_route(
		'evcert/v1',
		'/generator/preview',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\rest_preview',
			'permission_callback' => static function () {
				return current_user_can( Admin\CAPABILITY );
			},
		)
	);
	register_rest_route(
		'evcert/v1',
		'/generator/people',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\rest_people',
			'permission_callback' => static function () {
				return current_user_can( Admin\CAPABILITY );
			},
			'args'                => array(
				'lines' => array(
					'type'     => 'string',
					'required' => false,
				),
			),
		)
	);
}

/**
 * The people of the typed lines and the CSV, as the list shows them.
 *
 * @param string                              $lines Typed names (name, then e-mail and role cells), one per line.
 * @param array<int, array<int, string>>|null $rows  The rows of a CSV, when one was sent.
 * @return array<string, mixed>|\WP_Error people: name, email, roles, role_labels, status; info: rsvp_no, absent.
 */
function listed_people( $lines, $rows = null ) {
	$people = People\parse_lines( (string) $lines );
	$info   = array(
		'rsvp_no' => 0,
		'absent'  => 0,
	);
	if ( null !== $rows ) {
		$parsed = People\parse_rows( $rows );
		foreach ( $parsed['people'] as $person ) {
			$people[] = $person;
		}
		$info = array(
			'rsvp_no' => (int) $parsed['rsvp_no'],
			'absent'  => (int) $parsed['absent'],
		);
	}
	if ( count( $people ) > MAX_PEOPLE ) {
		/* translators: %d: the most names at a time. */
		return new \WP_Error( 'evcert_too_many', sprintf( __( 'Up to %d names at a time.', 'events-certificates' ), MAX_PEOPLE ), array( 'status' => 413 ) );
	}
	$labels = \EventsCertificate\Designs\role_labels();
	$out    = array();
	foreach ( $people as $person ) {
		$clean = People\clean( $person );
		if ( '' === $clean['name'] ) {
			continue;
		}
		$out[] = array(
			'name'        => $clean['name'],
			'email'       => $clean['email'],
			'roles'       => $clean['roles'],
			'role_labels' => array_map(
				static function ( $role ) use ( $labels ) {
					return (string) ( $labels[ $role ] ?? $role );
				},
				$clean['roles']
			),
			'status'      => 'no_show' === ( $person['status'] ?? '' ) ? 'no_show' : 'attended',
		);
	}

	return array(
		'people' => $out,
		'info'   => $info,
	);
}

/**
 * The route's callback: reads the CSV sent, if any, and answers with the people.
 *
 * @param \WP_REST_Request $request The request (lines, csv).
 * @return \WP_REST_Response|\WP_Error
 */
function rest_people( \WP_REST_Request $request ) {
	$rows  = null;
	$files = $request->get_file_params();
	if ( ! empty( $files['csv']['name'] ) ) {
		$name = sanitize_file_name( (string) $files['csv']['name'] );
		$tmp  = (string) ( $files['csv']['tmp_name'] ?? '' );
		$type = wp_check_filetype( $name, array( 'csv' => 'text/csv' ) );
		if ( UPLOAD_ERR_OK !== (int) ( $files['csv']['error'] ?? UPLOAD_ERR_NO_FILE ) || ! is_uploaded_file( $tmp ) || 'csv' !== $type['ext'] ) {
			return new \WP_Error( 'evcert_csv', __( 'Send a .csv file (in Meetup: Attendees → Manage → Attendee details; in a spreadsheet: Save as CSV).', 'events-certificates' ), array( 'status' => 400 ) );
		}
		$rows = People\read_csv( $tmp );
		if ( is_wp_error( $rows ) ) {
			return new \WP_Error( $rows->get_error_code(), $rows->get_error_message(), array( 'status' => 400 ) );
		}
	}
	$result = listed_people( sanitize_textarea_field( (string) $request->get_param( 'lines' ) ), $rows );

	return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
}

/**
 * The screen's address.
 *
 * @param array<string, string|int> $args More query args.
 * @return string
 */
function url( array $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => SLUG ), $args ), admin_url( 'admin.php' ) );
}

/**
 * Registers the screen: a page with no menu item of its own. Where the Generator is (the plugin's own menu) it is reached
 * from the Generator, by the switch in the header of both; without it, from Certificates and Events.
 */
function add_page() {
	if ( ! \EventsCertificate\Environment\generator_enabled() ) {
		return;
	}
	$title = __( 'Quick generator', 'events-certificates' );
	$hook  = add_submenu_page( '', $title, '', Admin\CAPABILITY, SLUG, __NAMESPACE__ . '\render' );
	if ( $hook ) {
		add_action( 'load-' . $hook, __NAMESPACE__ . '\load' );
	}
}

/**
 * The way to the other generator, in the header of both: from the Generator (records) to the quick one and back. One tertiary link
 * to the one that is not on screen. Only where both exist.
 *
 * @param string $current event|quick.
 */
function modes_nav( $current ) {
	if ( ! \EventsCertificate\Environment\generator_enabled() ) {
		return;
	}
	if ( 'quick' === $current ) {
		$other = array( __( 'Records', 'events-certificates' ), \EventsCertificate\EventsGenerator\url() );
	} else {
		$other = array( __( 'Quick', 'events-certificates' ), url() );
	}
	/* translators: %s: the other generator (Quick, Records). */
	$label = sprintf( __( 'Switch to %s', 'events-certificates' ), $other[0] );
	printf( '<a class="components-button is-tertiary evcert-generator__switch" href="%s">%s</a>', esc_url( $other[1] ), esc_html( $label ) );
}

/**
 * The screen's full-screen editor chrome: on the quick-issue screen the admin
 * menu and footer are hidden (the same body class the design editor uses), so
 * the page takes the window like the block editor. Kept out of
 * EventsAdmin\enqueue() so other screens stay the same.
 */
function enqueue() {
	$screen = \EventsCertificate\Assets\src( 'editor-screen', 'css' );
	wp_enqueue_style( 'evcert-editor-screen', $screen['url'], array(), $screen['version'] );
	// The real Gutenberg components stylesheet, so the panels, toggles and
	// buttons render exactly as in the block editor's Inspector.
	wp_enqueue_style( 'wp-components' );
	// The certificate sample's colors (the same file the front-end generator uses).
	wp_enqueue_style( 'evcert-quick-generator' );
	// The screen's own layout (the editor's full-screen shell around the form).
	$admin = \EventsCertificate\Assets\src( 'generator', 'css' );
	wp_enqueue_style( 'evcert-generator', $admin['url'], array( 'wp-components', 'evcert-quick-generator' ), $admin['version'] );

	// The form is the editor's own components, built (src/generator); its texts are translated there.
	$signer = \EventsCertificate\Signer\current();
	wp_add_inline_script(
		'evcert-generator',
		'window.evcertGenerator = ' . wp_json_encode(
			array(
				'signerName'     => $signer ? $signer['name'] : '',
				'signerRole'     => $signer && $signer['roles'] ? \EventsCertificate\Signer\role_in( $signer['roles'], get_locale() ) : '',
				'tutorial'       => tutorial(),
				// What the preview on the canvas is drawn from (as the public generator's: the Quick generator's template, its texts and the site's formats), and the signature of Settings.
				'quick'          => array_replace_recursive(
					\EventsCertificate\QuickGenerator\config(),
					// Here the title's placeholder is the event's name (the public page says Meetup title).
					array( 'i18n' => array( 'meetupTitle' => __( 'Event name', 'events-certificates' ) ) )
				),
				'signature'      => $signer && ! empty( $signer['signature'] ) ? (string) wp_json_encode( $signer['signature'] ) : '',
				'signatureImage' => $signer && ! empty( $signer['image'] ) ? (string) $signer['image'] : '',
				'examples'       => \EventsCertificate\QuickGenerator\examples(),
				'urls'           => array(
					'events'       => EventsAdmin\url(),
					'certificates' => admin_url( 'admin.php?page=' . Admin\SLUG ),
					'settings'     => Admin\settings_url(),
				),
			)
		) . ';',
		'before'
	);
	wp_enqueue_style( 'evcert-generator-sheet' );
	Admin\enqueue_media_chooser( 'evcert-generator' );
	wp_enqueue_script( 'evcert-generator' );
}

/**
 * Full-screen body class on the quick-issue screen, like the editor's.
 *
 * @param string $classes Body classes.
 * @return string
 */
function body_class( $classes ) {
	return isset( $_GET['page'] ) && SLUG === $_GET['page'] ? $classes . ' evcert-editor-screen' : $classes; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reads which screen this is.
}


/**
 * Before the screen prints: the menu highlight, the styles, and the form.
 */
function load() {
	// No menu item of its own: the menu shows the Generator, which this screen is the other half of.
	$GLOBALS['title'] = __( 'Quick generator', 'events-certificates' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- a page with no menu item has no title of its own.
	add_filter(
		'parent_file',
		static function () {
			return '' !== Admin\menu_parent() ? Admin\menu_parent() : Admin\SLUG;
		}
	);
	add_filter(
		'submenu_file',
		static function () {
			return \EventsCertificate\EventsGenerator\SLUG;
		}
	);
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue' );
	if ( submitted( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in issue().
		issue();
	}
}

/**
 * Whether the form was submitted by one of its download buttons (the header's send `evcert_generator_format`).
 *
 * @param array<string, mixed> $post The request's POST data.
 * @return bool
 */
function submitted( array $post ) {
	return isset( $post['evcert_generator_format'] ) || isset( $post['evcert_generator'] );
}

/**
 * The file asked for: a ZIP of one PDF each, or one PDF (the default, and for anything else).
 *
 * @param array<string, mixed> $post The request's POST data.
 * @return string 'zip' or 'pdf'.
 */
function requested_format( array $post ) {
	$format = $post['evcert_generator_format'] ?? ( $post['evcert_generator'] ?? '' );

	return 'zip' === sanitize_key( wp_unslash( (string) $format ) ) ? 'zip' : 'pdf';
}

/**
 * Back to the screen with an error.
 *
 * @param string $message Message.
 */
function fail( $message ) {
	set_transient( 'evcert_generator_error_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );
	wp_safe_redirect( url( array( 'evcert_error' => 1 ) ) );
	exit;
}

/**
 * The generator itself (the screen and the quick-issue ability): makes the
 * event (or updates the one given), adds the people (as attended, unless a
 * file said otherwise) and issues the certificates of who attended.
 *
 * @param array<string, mixed>             $fields   Event fields (Events\save()).
 * @param array<int, array<string, mixed>> $people   Participants (People\add()).
 * @param int                              $event_id An event of an earlier download of the same list, 0 for a new one.
 * @param array<int, int>                  $issued   Filled with the IDs of the people whose certificates were issued.
 * @return int|\WP_Error The event's ID.
 */
function run( array $fields, array $people, $event_id = 0, array &$issued = array() ) {
	if ( ! $people ) {
		return new \WP_Error( 'evcert_missing_participants', __( 'Add at least one name, or a CSV file with the participants.', 'events-certificates' ) );
	}
	$check = Events\clean( $fields );
	if ( is_wp_error( $check ) ) {
		return $check;
	}
	foreach ( $people as $i => $person ) {
		$people[ $i ]['status'] = $person['status'] ?? 'attended';
	}
	$event_id = Events\save( $fields, $event_id && Events\get( $event_id ) ? (int) $event_id : 0 );
	if ( is_wp_error( $event_id ) ) {
		return $event_id;
	}
	$issued = array();
	foreach ( $people as $person ) {
		$done = People\add( $event_id, $person );
		if ( ! is_wp_error( $done ) && 'attended' === $person['status'] ) {
			$issued[] = (int) $done['id'];
		}
	}
	People\issue_many( $issued, $event_id );

	return (int) $event_id;
}

/**
 * The people the screen's list posted (evcert_people[i][name|email|roles]): the ones it had checked.
 *
 * @param array<int|string, mixed> $rows The posted rows, already unslashed.
 * @return array<int, array<string, mixed>>
 */
function people_from_post( array $rows ) {
	$people = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$name = trim( sanitize_text_field( (string) ( $row['name'] ?? '' ) ) );
		if ( '' === $name ) {
			continue;
		}
		$people[] = array(
			'name'   => $name,
			'email'  => sanitize_email( (string) ( $row['email'] ?? '' ) ),
			'roles'  => array_map( 'sanitize_key', (array) ( $row['roles'] ?? array() ) ),
			'status' => 'attended',
			'source' => 'manual',
		);
	}

	return array_slice( $people, 0, MAX_PEOPLE );
}

/**
 * Makes the event, its participants and their certificates, then sends
 * them (ZIP or one PDF).
 */
function issue() {
	check_admin_referer( 'evcert_generator' );
	EventsAdmin\must_manage();

	// The same fields as the front-end generator: the date and the times are apart.
	$post = static function ( $key ) {
		return isset( $_POST[ 'evcert_' . $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'evcert_' . $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- issue() checked the nonce.
	};
	$date = $post( 'date' );
	$from = $post( 'start_time' );
	$to   = $post( 'end_time' );
	$end  = '' !== $date && '' !== $to ? $date . ' ' . $to : '';
	if ( '' !== $end && '' !== $from && $to < $from ) {
		// Past midnight: it ends the next day.
		$end = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) ) . ' ' . $to;
	}
	$fields = array(
		'title'           => $post( 'title' ),
		'community'       => $post( 'community' ),
		'start'           => '' !== $date ? $date . ' ' . ( '' !== $from ? $from : '00:00' ) : '',
		'end'             => $end,
		'venue'           => $post( 'venue' ),
		'city'            => $post( 'city' ),
		'speakers'        => $post( 'speakers' ),
		'link'            => $post( 'link' ),
		'show_hours'      => $post( 'show_hours' ),
		'signer_name'     => $post( 'signer_name' ),
		'signer_role'     => $post( 'signer_role' ),
		// The pictures are IDs of Media Library attachments, or drawings of paths; Events\clean() cleans them.
		'logo'            => isset( $_POST['evcert_logo'] ) ? wp_unslash( $_POST['evcert_logo'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked above; Events\clean_image() accepts only a raster data URI.
		'logo_svg'        => isset( $_POST['evcert_logo_svg'] ) ? wp_unslash( $_POST['evcert_logo_svg'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked above; Events\clean_vector() keeps only valid paths.
		'signature_svg'   => isset( $_POST['evcert_signature_svg'] ) ? wp_unslash( $_POST['evcert_signature_svg'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- same.
		'signature_image' => isset( $_POST['evcert_signature_image'] ) ? wp_unslash( $_POST['evcert_signature_image'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- same.
	);
	if ( isset( $_POST['evcert_people'] ) && is_array( $_POST['evcert_people'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked above; each row is sanitized by people_from_post().
		// The list on the screen: who is checked, already read by the route.
		$people = people_from_post( wp_unslash( $_POST['evcert_people'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	} else {
		// Without the list (no JavaScript): the typed lines and the file.
		$people = People\parse_lines( isset( $_POST['evcert_lines'] ) ? sanitize_textarea_field( wp_unslash( $_POST['evcert_lines'] ) ) : '' );
		$file   = EventsAdmin\uploaded_csv();
		if ( is_wp_error( $file ) ) {
			fail( $file->get_error_message() );
		}
		if ( $file ) {
			foreach ( People\parse_rows( $file )['people'] as $person ) {
				$people[] = $person + array( 'source' => 'csv' );
			}
		}
	}
	// Nothing is saved: the certificates are drawn from the form, with no records and no verification (they have no code).
	$items = items( $fields, $people );
	if ( is_wp_error( $items ) ) {
		fail( $items->get_error_message() );
	}
	download_items( $items, requested_format( $_POST ), sanitize_file_name( remove_accents( sanitize_title( $fields['title'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked at the top.
}

/**
 * Prints the screen with the design editor's chrome: a 64px header (back,
 * document bar, status), then the editor body — the certificate preview as the
 * canvas stage, and the issue form as the 280px Inspector sidebar styled after
 * the @wordpress/components panels.
 */
function render() {
	EventsAdmin\must_manage();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only shows the stored message.
	$error = isset( $_GET['evcert_error'] ) ? (string) get_transient( 'evcert_generator_error_' . get_current_user_id() ) : '';
	delete_transient( 'evcert_generator_error_' . get_current_user_id() );
	$has_error = '' !== $error;
	$back      = EventsAdmin\url();
	?>
	<div class="wrap evcert-generator">
		<header class="evcert-generator__header">
			<div class="evcert-generator__header-left">
				<a class="evcert-generator__back" href="<?php echo esc_url( $back ); ?>" aria-label="<?php esc_attr_e( 'Back to Events', 'events-certificates' ); ?>">
					<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</a>
				<button type="button" class="evcert-generator__header-icon evcert-generator__help" id="evcert-generator-help" aria-expanded="true" aria-controls="evcert-generator-left-panel" aria-label="<?php esc_attr_e( 'How to get participants', 'events-certificates' ); ?>">
					<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M9.6 9.4a2.4 2.4 0 1 1 3.4 2.2c-.9.4-1.4 1-1.4 1.9" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="12" cy="16.4" r="1" fill="currentColor"/></svg>
				</button>
				<?php modes_nav( 'quick' ); ?>
			</div>
			<div class="evcert-generator__header-center">
				<?php // Like the editor's document bar: the title and the type of what is open, and a click opens the command palette. ?>
				<button type="button" class="evcert-generator__doc-bar" id="evcert-generator-docbar" aria-haspopup="dialog">
					<h1 class="evcert-generator__doc-heading">
						<span class="evcert-generator__doc-title"><?php esc_html_e( 'Quick generator', 'events-certificates' ); ?></span>
						<span class="evcert-generator__doc-type">· <?php esc_html_e( 'Certificates', 'events-certificates' ); ?></span>
					</h1>
				</button>
			</div>
			<div class="evcert-generator__header-right">
				<?php // Like the editor's Settings toggle: opens and closes the sidebar (event, signature, participants). ?>
				<button type="button" class="evcert-generator__header-icon" id="evcert-generator-settings" aria-pressed="true" aria-controls="evcert-generator-sidebar" aria-label="<?php esc_attr_e( 'Settings', 'events-certificates' ); ?>">
					<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M18 4H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-4 14.5H6c-.3 0-.5-.2-.5-.5V6c0-.3.2-.5.5-.5h8v13zm4.5-.5c0 .3-.2.5-.5.5h-2.5v-13H18c.3 0 .5.2.5.5v12z"/></svg>
				</button>
				<div class="evcert-generator__header-actions" id="evcert-generator-actions"></div>
			</div>
		</header>

		<form id="evcert-generator-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( url() ); ?>">
			<?php wp_nonce_field( 'evcert_generator' ); ?>
			<div class="evcert-generator__body">
				<aside class="evcert-generator__left-panel is-open" id="evcert-generator-left-panel" aria-hidden="false">
					<div id="evcert-generator-left-root"></div>
				</aside>

				<div class="evcert-generator__canvas">
					<?php if ( $has_error ) : ?>
						<div class="evcert-generator__error"><?php echo esc_html( $error ); ?></div>
					<?php endif; ?>
					<div class="evcert-generator__sheet">
						<?php
						// A sample of the site's certificate design, drawn by the server (the browser app is on the front-end page).
						echo wp_kses( \EventsCertificate\QuickGenerator\sheet(), \EventsCertificate\QuickGenerator\svg_allowed(), \EventsCertificate\Render\allowed_protocols() );
						?>
					</div>
				</div>

				<aside class="evcert-generator__sidebar" id="evcert-generator-sidebar" aria-label="<?php esc_attr_e( 'Generator settings', 'events-certificates' ); ?>">
					<div id="evcert-generator-root"></div>
				</aside>
			</div>
		</form>
	</div>
	<?php
}

/**
 * The tutorial of the Generator screen: the generator page's, with the intro pointing at this screen's own panel,
 * its link opens the sidebar already on its Participants tab.
 *
 * @return array<string, mixed>
 */
function tutorial() {
	$data          = \EventsCertificate\QuickGenerator\tutorial_data();
	$data['intro'] = wp_kses(
		__( 'Log in to Meetup with the group\'s organizer account (only organizers can export). It takes four clicks and Meetup downloads a .csv file. Then just drop that file into <a href="#" data-evcert-open="people">Settings, in the Participants tab</a>.', 'events-certificates' ),
		array(
			'a' => array(
				'href'             => array(),
				'data-evcert-open' => array(),
			),
		)
	);
	return $data;
}

/**
 * The certificates of a form, as items for the PDF engine: one for each person and role, with the values the form gives and no
 * record behind them (no event, no participant, no code), so they carry no verification.
 *
 * @param array<string, mixed>             $fields The form's event fields (title, date, ...).
 * @param array<int, array<string, mixed>> $people The people: name, email, roles.
 * @return array<int, array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}>|\WP_Error
 */
function items( array $fields, array $people ) {
	if ( ! $people ) {
		return new \WP_Error( 'evcert_missing_participants', __( 'Add at least one name, or a CSV file with the participants.', 'events-certificates' ) );
	}
	$event = Events\clean( $fields );
	if ( is_wp_error( $event ) ) {
		return $event;
	}
	// Every page is drawn from the Quick generator's template, as its preview is, whatever the person's role.
	$design = \EventsCertificate\Designs\get( \EventsCertificate\Designs\quick_design_id() );
	$values = array(
		'community'      => $event['community'],
		'speakers'       => $event['speakers'],
		'speakers_label' => '' !== trim( $event['speakers'] ) ? \EventsCertificate\Render\speakers_label( $event['speakers'] ) : '',
		'event_time'     => \EventsCertificate\Render\event_time( $event + array( 'show_hours' => '1' ) ),
		'event_link'     => \EventsCertificate\Render\short_link( $event['link'] ),
		'event_ts'       => (string) strtotime( Events\utc( $event['start'] ) . ' UTC' ),
	);
	if ( '' !== $event['signer_name'] ) {
		$values['signer_name'] = $event['signer_name'];
		$values['signer_role'] = $event['signer_role'];
	}
	if ( '' !== $event['signature_svg'] ) {
		$values['signature'] = $event['signature_svg'];
	}
	$data  = array(
		'location'        => $event['location'],
		'logo'            => $event['logo'],
		'logo_svg'        => $event['logo_svg'],
		'signature_image' => $event['signature_image'],
	);
	$items = array();
	foreach ( $people as $person ) {
		if ( count( $items ) >= MAX_CERTIFICATES ) {
			return new \WP_Error(
				'evcert_too_many_certificates',
				/* translators: %d: the most certificates one download makes. */
				sprintf( __( 'One download makes up to %d certificates. Send the list in parts.', 'events-certificates' ), MAX_CERTIFICATES )
			);
		}
		$roles = array_filter( (array) ( $person['roles'] ?? array() ) );
		foreach ( $roles ? $roles : array( '' ) as $role ) {
			$cert    = array(
				'name'        => (string) $person['name'],
				'role'        => (string) $role,
				'hours'       => (string) ( Events\hours( $event, (string) $role ) ?? '' ),
				'code'        => '',
				'status'      => 'issued',
				'issued_at'   => gmdate( 'Y-m-d H:i:s' ),
				'event_title' => $event['title'],
				'event_start' => Events\utc( $event['start'] ),
				'event_end'   => Events\utc( $event['end'] ),
				'data_json'   => (string) wp_json_encode( $data ),
			);
			$items[] = array(
				$cert,
				$design,
				array(
					'values'          => $values,
					'no_verification' => true,
				),
			);
		}
	}

	return $items;
}

/**
 * Sends the items as one PDF, or as a ZIP with a PDF for each.
 *
 * @param array<int, array<int, mixed>> $items  From items().
 * @param string                        $format pdf|zip.
 * @param string                        $base   File name, without its extension.
 */
function download_items( array $items, $format, $base ) {
	wp_raise_memory_limit( 'admin' );
	$base = '' !== $base ? $base : 'certificates';
	if ( 'pdf' === $format || ! class_exists( 'ZipArchive' ) ) {
		EventsAdmin\send( \EventsCertificate\Render\pdf_many( $items ), 'application/pdf', $base . '-certificates.pdf' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$file = wp_tempnam( $base . '.zip' );
	$zip  = new \ZipArchive();
	if ( true !== $zip->open( $file, \ZipArchive::OVERWRITE ) ) {
		wp_die( esc_html__( 'The ZIP file could not be made.', 'events-certificates' ) );
	}
	$names = array();
	foreach ( $items as $item ) {
		$stem = sanitize_file_name( remove_accents( (string) $item[0]['name'] ) . ( '' !== $item[0]['role'] ? '-' . $item[0]['role'] : '' ) );
		$name = $stem . '.pdf';
		for ( $n = 2; isset( $names[ $name ] ); $n++ ) {
			$name = $stem . '-' . $n . '.pdf';
		}
		$names[ $name ] = true;
		$zip->addFromString( $name, \EventsCertificate\Render\pdf( $item[0], $item[1], $item[2] ) );
	}
	$zip->close();
	EventsAdmin\send_file( $file, 'application/zip', $base . '-certificates.zip' );
}
