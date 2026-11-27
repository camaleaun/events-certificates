<?php
/**
 * The Participants screen of the plugin's own menu: everyone who was added to any event, in one list with their event. The
 * list of one event (with its actions) stays on the event; this one is where to find a person without knowing the event.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\PeopleAdmin;

use EventsCertificate\Admin;
use EventsCertificate\Environment;
use EventsCertificate\EventsAdmin;
use EventsCertificate\People;

defined( 'ABSPATH' ) || exit;

const SLUG = 'evcert-people';

/**
 * Hooks.
 */
function bootstrap() {
	add_action( 'admin_menu', __NAMESPACE__ . '\add_page', 16 );
}

/**
 * The screen's address.
 *
 * @param array<string, mixed> $args Query arguments.
 * @return string
 */
function url( array $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => SLUG ), $args ), admin_url( 'admin.php' ) );
}

/**
 * The menu item, where the site has events of its own (not with CampTix or GatherPress).
 */
function add_page() {
	if ( ! Environment\own_events() || '' !== Admin\menu_parent() ) {
		return;
	}
	$title = __( 'Participants', 'events-certificates' );
	$hook  = add_submenu_page( Admin\SLUG, $title, $title, Admin\CAPABILITY, SLUG, __NAMESPACE__ . '\render' );
	if ( $hook ) {
		add_action( 'load-' . $hook, __NAMESPACE__ . '\load' );
	}
}

/**
 * Styles, as the events' screens have them.
 */
function load() {
	// The list's bulk actions, then back to the list: an action is run on each person's own event.
	if ( isset( $_REQUEST['participant'] ) || isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in bulk().
		bulk();
	}
	// The notice's arguments stay out of the list's sort and page links, and the page size is the event list's option.
	add_filter(
		'removable_query_args',
		static function ( $args ) {
			return array_merge( $args, EventsAdmin\NOTICE_ARGS );
		}
	);
	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$_SERVER['REQUEST_URI'] = remove_query_arg( EventsAdmin\NOTICE_ARGS, esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
	}
	add_screen_option(
		'per_page',
		array(
			'label'   => __( 'Participants per page', 'events-certificates' ),
			'default' => 50,
			'option'  => 'evcert_people_per_page',
		)
	);
	add_action(
		'admin_enqueue_scripts',
		static function () {
			Admin\avatar_style();
			$style = \EventsCertificate\Assets\src( 'events-admin', 'css' );
			wp_enqueue_style( 'evcert-events', $style['url'], array(), $style['version'] );
		}
	);
}

/**
 * A bulk action: what was ticked is grouped by event and the action runs on each, as it does on the event's own list.
 */
function bulk() {
	check_admin_referer( 'bulk-participants' );
	EventsAdmin\must_manage();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- checked above.
	$action = isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
	if ( '' === $action && isset( $_REQUEST['action2'] ) ) {
		$action = sanitize_key( wp_unslash( $_REQUEST['action2'] ) );
	}
	$ids = isset( $_REQUEST['participant'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['participant'] ) ) : array();
	if ( isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] ) ) {
		// Empty Trash: everyone who is in it, whatever the page.
		$action = 'delete';
		$ids    = wp_list_pluck( People\query( 0, array( 'trash' => true ) )['rows'], 'id' );
	}
	// phpcs:enable
	$allowed = array( 'attended', 'no_show', 'registered', 'issue', 'trash', 'untrash', 'delete' );
	if ( ! in_array( $action, $allowed, true ) || ! $ids ) {
		return;
	}
	$by_event = array();
	foreach ( array_filter( $ids ) as $id ) {
		$person = People\get( $id );
		if ( $person ) {
			$by_event[ (int) $person['event_id'] ][] = $id;
		}
	}
	$n       = 0;
	$skipped = 0;
	$revoked = 0;
	foreach ( $by_event as $event_id => $people ) {
		switch ( $action ) {
			case 'issue':
				$result   = People\issue_many( $people, $event_id );
				$n       += $result['issued'];
				$skipped += $result['skipped'];
				$revoked += $result['revoked'];
				break;
			case 'trash':
				$n += People\trash( $people, $event_id );
				break;
			case 'untrash':
				$n += People\restore( $people, $event_id );
				break;
			case 'delete':
				$n += People\delete_trashed( $people, $event_id );
				break;
			default:
				$n += People\set_status( $people, $action, $event_id );
		}
	}
	$args = array(
		'evcert_done' => $action,
		'evcert_n'    => $n,
	);
	if ( 'issue' === $action ) {
		$args['evcert_skipped'] = $skipped;
		$args['evcert_revoked'] = $revoked;
	}
	if ( in_array( $action, array( 'untrash', 'delete' ), true ) && People\counts( 0 )['trash'] ) {
		$args['status'] = 'trash';
	}
	wp_safe_redirect( url( $args ) );
	exit;
}

/**
 * The screen.
 */
function render() {
	EventsAdmin\must_manage();
	require_once __DIR__ . '/class-all-people-list-table.php';
	$table = new EventsAdmin\All_People_List_Table( 0 );
	$table->prepare_items();
	?>
	<div class="wrap evcert-event">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Participants', 'events-certificates' ); ?></h1>
		<hr class="wp-header-end">
		<?php
		EventsAdmin\notice();
		$table->views();
		?>
		<form method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( SLUG ); ?>">
			<?php
			$table->search_box( __( 'Search participants', 'events-certificates' ), 'evcert-people-search' );
			$table->display();
			?>
		</form>
	</div>
	<?php
}
