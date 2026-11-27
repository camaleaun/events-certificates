<?php
/**
 * The Generator: certificates of a registered event and its participants, in the editor's look.
 *
 * It works on what the site already has: an event is picked from the registered ones, its participants come from the
 * database with their status, roles and certificates, and "Issue and download" issues the certificates of who attended
 * (once: a second download gives the same ones) and sends them. Adding people, importing a Meetup list and editing the event
 * are on the Events screen; revoking is on Certificates. The Quick generator (includes/generator.php) is the one that
 * takes everything from a form and saves nothing.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\EventsGenerator;

use EventsCertificate\Admin;
use EventsCertificate\Events;
use EventsCertificate\EventsAdmin;
use EventsCertificate\People;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page slug.
 */
const SLUG = 'evcert-generator';

/**
 * Hooks.
 */
function bootstrap() {
	add_action( 'admin_menu', __NAMESPACE__ . '\add_page', 18 );
	add_filter( 'admin_body_class', __NAMESPACE__ . '\body_class' );
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
 * The menu item (where the site has its own events: not with CampTix or GatherPress).
 */
function add_page() {
	if ( ! \EventsCertificate\Environment\own_events() ) {
		return;
	}
	$title = __( 'Generator', 'events-certificates' );
	$hook  = add_submenu_page( Admin\SLUG, $title, $title, Admin\CAPABILITY, SLUG, __NAMESPACE__ . '\render' );
	if ( $hook ) {
		add_action( 'load-' . $hook, __NAMESPACE__ . '\load' );
	}
}

/**
 * Full-screen body class, like the editor's.
 *
 * @param string $classes Body classes.
 * @return string
 */
function body_class( $classes ) {
	return isset( $_GET['page'] ) && SLUG === $_GET['page'] ? $classes . ' evcert-editor-screen' : $classes; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reads which screen this is.
}

/**
 * Before the screen prints: its styles, and a download when one was asked.
 */
function load() {
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue' );
	if ( isset( $_POST['evcert_events_generator'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in download().
		download();
	}
}

/**
 * The screen's styles: the editor's shell, as the Quick generator has it.
 */
function enqueue() {
	$screen = \EventsCertificate\Assets\src( 'editor-screen', 'css' );
	wp_enqueue_style( 'evcert-editor-screen', $screen['url'], array(), $screen['version'] );
	wp_enqueue_style( 'wp-components' );
	wp_enqueue_style( 'evcert-quick-generator' );
	$admin = \EventsCertificate\Assets\src( 'generator', 'css' );
	wp_enqueue_style( 'evcert-generator', $admin['url'], array( 'wp-components', 'evcert-quick-generator' ), $admin['version'] );
	wp_enqueue_style( 'evcert-generator-sheet' );
	enqueue_commands();
}

/**
 * The command palette of the screen: its suggestions are the downloads, the event's screens, the other events and the
 * plugin's screens. Which downloads apply follows who attended.
 */
function enqueue_commands() {
	$built = dirname( __DIR__ ) . '/build-blocks/events-generator.min.asset.php';
	if ( ! is_readable( $built ) ) {
		return;
	}
	$asset = require $built;
	wp_register_script( 'evcert-events-generator', plugins_url( 'build-blocks/events-generator.min.js', dirname( __DIR__ ) . '/events-certificates.php' ), $asset['dependencies'], $asset['version'], true );
	wp_set_script_translations( 'evcert-events-generator', 'events-certificates' );

	$event    = current_event();
	$urls     = array(
		'events'       => EventsAdmin\url(),
		'certificates' => admin_url( 'admin.php?page=' . \EventsCertificate\Admin\SLUG ),
		'settings'     => admin_url( 'admin.php?page=' . \EventsCertificate\Admin\SLUG_SETTINGS ),
	);
	$events   = array();
	$can_one  = false;
	$can_many = false;
	foreach ( Events\all() as $item ) {
		$events[] = array(
			'id'    => (int) $item->ID,
			'title' => (string) $item->post_title,
			'url'   => url( array( 'event' => (int) $item->ID ) ),
		);
	}
	if ( $event ) {
		$urls['edit']         = EventsAdmin\url( array( 'event' => (int) $event['id'] ) );
		$urls['participants'] = EventsAdmin\participants_url( (int) $event['id'] );
		$counts               = People\counts( (int) $event['id'] );
		$can_many             = (int) $counts['attended'] > 0;
		$shown                = isset( $_GET['person'] ) ? absint( $_GET['person'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which person to preview.
		$person               = $shown ? People\get( $shown ) : null;
		$can_one              = $person ? ( 'attended' === $person['status'] ) : $can_many;
	}
	wp_add_inline_script(
		'evcert-events-generator',
		'window.evcertEventsGenerator = ' . wp_json_encode(
			array(
				'urls'    => $urls,
				'events'  => $events,
				'current' => $event ? (int) $event['id'] : 0,
				'canOne'  => $can_one,
				'canMany' => $can_many,
			)
		) . ';',
		'before'
	);
	wp_enqueue_script( 'evcert-events-generator' );
}

/**
 * The event shown: the one asked for, else the most recent.
 *
 * @return array<string, mixed>|null
 */
function current_event() {
	$id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which event to show.
	if ( $id ) {
		return Events\get( $id );
	}
	$all = Events\all( array( 'numberposts' => 1 ) );

	return $all ? Events\get( (int) $all[0]->ID ) : null;
}

/**
 * Issues the certificates of the people chosen (who attended) and sends them: a ZIP with one PDF each, or a single PDF.
 * Issuing again gives the same certificates.
 */
function download() {
	check_admin_referer( 'evcert_events_generator' );
	EventsAdmin\must_manage();
	$event_id = isset( $_POST['evcert_event_id'] ) ? absint( $_POST['evcert_event_id'] ) : 0;
	$event    = $event_id ? Events\get( $event_id ) : null;
	$ids      = isset( $_POST['evcert_people'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['evcert_people'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint() on each.
	$choice   = isset( $_POST['evcert_events_generator'] ) ? sanitize_key( wp_unslash( $_POST['evcert_events_generator'] ) ) : 'zip';
	$format   = 'zip' === $choice ? 'zip' : 'pdf';
	if ( 'current' === $choice ) {
		// Only the person shown.
		$ids = array( isset( $_POST['evcert_current'] ) ? absint( $_POST['evcert_current'] ) : 0 );
	} elseif ( 0 === strpos( $choice, 'one-' ) ) {
		// The PDF button of a row: only that person, whatever the boxes say.
		$ids    = array( absint( substr( $choice, 4 ) ) );
		$format = 'pdf';
	}
	if ( ! $event || ! $ids ) {
		wp_safe_redirect( url( array( 'event' => $event_id ) ) );
		exit;
	}
	// Only who attended gets a certificate.
	$attended = array();
	foreach ( $ids as $id ) {
		$person = People\get( $id );
		if ( $person && (int) $person['event_id'] === $event_id && 'attended' === $person['status'] ) {
			$attended[] = $id;
		}
	}
	People\issue_many( $attended, $event_id );
	EventsAdmin\download( $event_id, $format, $attended );
}

/**
 * The certificate a person gets in a role, drawn as it will be: the issued one, else a draft of it (with the verification
 * parts as placeholders), as an SVG that scales to its box.
 *
 * @param array<string, mixed> $person Participant.
 * @param array<string, mixed> $event  Event.
 * @return string
 */
function preview( array $person, array $event ) {
	$role = (string) ( $person['role'] ?? '' );
	$cert = People\certificate( $person, $role );
	$opts = array( 'mode' => 'light' );
	if ( ! $cert ) {
		$signer = \EventsCertificate\Signer\for_event( $event );
		$cert   = array(
			'context'     => Events\CONTEXT,
			'event_id'    => (int) $event['id'],
			'name'        => (string) $person['name'],
			'role'        => $role,
			'hours'       => (string) ( Events\hours( $event, $role ) ?? '' ),
			'code'        => '',
			'status'      => 'issued',
			'issued_at'   => gmdate( 'Y-m-d H:i:s' ),
			'event_title' => $event['title'],
			'event_start' => Events\utc( $event['start'] ),
			'event_end'   => Events\utc( $event['end'] ),
			'data_json'   => (string) wp_json_encode(
				array(
					'location'        => $event['location'],
					'signer_name'     => $signer ? $signer['name'] : '',
					'signer_roles'    => $signer ? $signer['roles'] : array(),
					'logo'            => $event['logo'],
					'logo_svg'        => $event['logo_svg'],
					'signature_image' => $event['signature_image'],
					'signature_svg'   => $event['signature_svg'],
				)
			),
		);
		// Not issued yet: no real code, so the verification parts show as placeholders.
		$opts['preview'] = true;
	}
	$design = \EventsCertificate\Certificates\design( $cert );
	$page   = (array) $design['page'];
	$px     = 96 / 72;

	return sprintf(
		'<svg class="evcert-sheet__svg" viewBox="0 0 %1$.2F %2$.2F" role="img" aria-label="%3$s"><foreignobject x="0" y="0" width="%1$.2F" height="%2$.2F">%4$s</foreignobject></svg>',
		$page['w'] * $px,
		$page['h'] * $px,
		esc_attr__( 'Certificate preview', 'events-certificates' ),
		\EventsCertificate\Render\artwork( $design, \EventsCertificate\Render\values( $cert ), $opts )
	);
}

/**
 * The words for a participant's status.
 *
 * @param string $status registered|attended|no_show.
 * @return string
 */
function status_label( $status ) {
	$labels = array(
		'registered' => __( 'Registered', 'events-certificates' ),
		'attended'   => __( 'Attended', 'events-certificates' ),
		'no_show'    => __( 'Did not attend', 'events-certificates' ),
	);

	return $labels[ $status ] ?? $status;
}

/**
 * Prints the screen.
 */
function render() {
	EventsAdmin\must_manage();
	$event   = current_event();
	$events  = Events\all();
	$people  = array();
	$counts  = array(
		'all'      => 0,
		'attended' => 0,
	);
	$current = null;
	$any     = false;
	$ok_one  = false;
	if ( $event ) {
		$people  = People\query( (int) $event['id'] )['rows'];
		$counts  = People\counts( (int) $event['id'] );
		$shown   = isset( $_GET['person'] ) ? absint( $_GET['person'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which person to preview.
		$current = null;
		foreach ( $people as $person ) {
			if ( ( $shown && (int) $person['id'] === $shown ) || ( ! $shown && ! $current && 'attended' === $person['status'] ) ) {
				$current = $person;
			}
		}
		$current = $current ? $current : ( $people ? $people[0] : null );
		$any     = (int) $counts['attended'] > 0;
		$ok_one  = $current && 'attended' === $current['status'];
	}
	?>
	<div class="wrap evcert-generator evcert-eg">
		<header class="evcert-generator__header">
			<div class="evcert-generator__header-left">
				<a class="evcert-generator__back" href="<?php echo esc_url( EventsAdmin\url() ); ?>" aria-label="<?php esc_attr_e( 'Back to Events', 'events-certificates' ); ?>">
					<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</a>
				<?php \EventsCertificate\Generator\modes_nav( 'event' ); ?>
			</div>
			<div class="evcert-generator__header-center">
				<?php // As the editor's document bar: a click opens the command palette, with the commands of the screen as suggestions. ?>
				<button type="button" class="evcert-generator__doc-bar" id="evcert-generator-docbar" aria-haspopup="dialog">
					<h1 class="evcert-generator__doc-heading">
						<span class="evcert-generator__doc-title"><?php esc_html_e( 'Generator', 'events-certificates' ); ?></span>
						<span class="evcert-generator__doc-type">· <?php esc_html_e( 'Certificates', 'events-certificates' ); ?></span>
					</h1>
				</button>
				<div id="evcert-eg-commands" hidden></div>
			</div>
			<div class="evcert-generator__header-right">
				<div class="evcert-generator__header-actions">
					<?php if ( $event ) : ?>
						<button type="submit" form="evcert-events-generator" name="evcert_events_generator" value="current" class="components-button is-tertiary" <?php disabled( ! $ok_one ); ?>><?php esc_html_e( 'Download current', 'events-certificates' ); ?></button>
						<button type="submit" form="evcert-events-generator" name="evcert_events_generator" value="pdf" class="components-button is-secondary" <?php disabled( ! $any ); ?>><?php esc_html_e( 'Download PDF', 'events-certificates' ); ?></button>
						<button type="submit" form="evcert-events-generator" name="evcert_events_generator" value="zip" class="components-button is-primary" <?php disabled( ! $any ); ?>><?php esc_html_e( 'Download ZIP', 'events-certificates' ); ?></button>
					<?php endif; ?>
				</div>
			</div>
		</header>
	<?php
	if ( ! $event ) {
		?>
		<div class="evcert-eg__empty">
			<p><?php esc_html_e( 'There are no events yet. Add one, then its participants: who came gets a certificate.', 'events-certificates' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( EventsAdmin\url( array( 'event' => 'new' ) ) ); ?>"><?php esc_html_e( 'Add event', 'events-certificates' ); ?></a></p>
		</div>
	</div>
		<?php
		return;
	}
	?>
		<form id="evcert-events-generator" method="post" action="<?php echo esc_url( url( array( 'event' => (int) $event['id'] ) ) ); ?>">
			<?php wp_nonce_field( 'evcert_events_generator' ); ?>
			<input type="hidden" name="evcert_event_id" value="<?php echo esc_attr( (string) $event['id'] ); ?>">
			<input type="hidden" name="evcert_current" value="<?php echo esc_attr( $current ? (string) $current['id'] : '0' ); ?>">
			<div class="evcert-generator__body">
				<div class="evcert-generator__canvas">
					<div class="evcert-generator__sheet">
						<?php
						if ( $current ) {
							echo wp_kses( preview( $current, $event ), \EventsCertificate\QuickGenerator\svg_allowed(), \EventsCertificate\Render\allowed_protocols() );
						} else {
							echo '<p class="evcert-eg__empty">' . esc_html__( 'No participants yet. Add or import them in Events.', 'events-certificates' ) . '</p>';
						}
						?>
					</div>
				</div>
				<aside class="evcert-generator__sidebar" aria-label="<?php esc_attr_e( 'Generator settings', 'events-certificates' ); ?>">
					<div class="evcert-eg__side">
						<h2 class="evcert-eg__heading"><?php esc_html_e( 'Event', 'events-certificates' ); ?></h2>
						<p>
							<label class="screen-reader-text" for="evcert-eg-event"><?php esc_html_e( 'Event', 'events-certificates' ); ?></label>
							<select id="evcert-eg-event" onchange="location = this.value">
								<?php foreach ( $events as $item ) : ?>
									<option value="<?php echo esc_url( url( array( 'event' => (int) $item->ID ) ) ); ?>" <?php selected( (int) $item->ID, (int) $event['id'] ); ?>><?php echo esc_html( $item->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<dl class="evcert-eg__rows">
							<dt><?php esc_html_e( 'Start', 'events-certificates' ); ?></dt>
							<dd><?php echo esc_html( \EventsCertificate\Dates\date_text( strtotime( Events\utc( $event['start'] ) . ' UTC' ) ) ); ?></dd>
							<?php if ( '' !== $event['location'] ) : ?>
								<dt><?php esc_html_e( 'Place', 'events-certificates' ); ?></dt>
								<dd><?php echo esc_html( $event['location'] ); ?></dd>
							<?php endif; ?>
							<dt><?php esc_html_e( 'Participants', 'events-certificates' ); ?></dt>
							<dd>
								<?php
								/* translators: 1: participants, 2: who attended. */
								echo esc_html( sprintf( __( '%1$d (%2$d attended)', 'events-certificates' ), (int) $counts['all'], (int) $counts['attended'] ) );
								?>
							</dd>
						</dl>
						<p><a href="<?php echo esc_url( EventsAdmin\url( array( 'event' => (int) $event['id'] ) ) ); ?>"><?php esc_html_e( 'Edit the event', 'events-certificates' ); ?></a>
						· <a href="<?php echo esc_url( EventsAdmin\participants_url( (int) $event['id'] ) ); ?>"><?php esc_html_e( 'Manage participants', 'events-certificates' ); ?></a></p>

						<h2 class="evcert-eg__heading"><?php esc_html_e( 'Participants', 'events-certificates' ); ?></h2>
						<ul class="evcert-eg__people">
							<?php foreach ( $people as $person ) : ?>
								<?php $attended = 'attended' === $person['status']; ?>
								<li class="<?php echo ( $current && (int) $current['id'] === (int) $person['id'] ) ? 'is-current' : ''; ?>">
									<input type="checkbox" name="evcert_people[]" value="<?php echo esc_attr( (string) $person['id'] ); ?>" id="evcert-eg-p<?php echo esc_attr( (string) $person['id'] ); ?>" <?php checked( $attended ); ?> <?php disabled( ! $attended ); ?>>
									<a href="
									<?php
									echo esc_url(
										url(
											array(
												'event'  => (int) $event['id'],
												'person' => (int) $person['id'],
											)
										)
									);
									?>
												"><?php echo esc_html( $person['name'] ); ?></a>
									<span class="evcert-eg__tag"><?php echo esc_html( status_label( (string) $person['status'] ) ); ?></span>
									<?php foreach ( array_keys( (array) $person['roles'] ) as $role ) : ?>
										<?php
										$issued = People\certificate( $person, (string) $role );
										$labels = \EventsCertificate\Designs\role_labels();
										?>
										<span class="evcert-eg__tag"><?php echo esc_html( ( $labels[ $role ] ?? $role ) . ( $issued ? ' ✓' : '' ) ); ?></span>
									<?php endforeach; ?>
									<?php if ( $attended ) : ?>
										<?php /* translators: %s: a participant's name. */ ?>
										<button type="submit" form="evcert-events-generator" name="evcert_events_generator" value="one-<?php echo esc_attr( (string) $person['id'] ); ?>" class="button button-small evcert-eg__pdf" aria-label="<?php echo esc_attr( sprintf( __( 'Download the certificate of %s', 'events-certificates' ), $person['name'] ) ); ?>"><?php esc_html_e( 'PDF', 'events-certificates' ); ?></button>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>

						<p class="description"><?php esc_html_e( 'Only who attended gets a certificate. Issuing again gives the same certificates; to revoke one, open Certificates.', 'events-certificates' ); ?></p>
					</div>
				</aside>
			</div>
		</form>
	</div>
	<?php
}
