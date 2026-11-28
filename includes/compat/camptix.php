<?php
/**
 * CampTix (WordCamps): Certificates goes where organizers already look.
 *
 * - Tickets > Certificates: the screen, right after Attendees.
 * - Tickets > Setup > Certificates: the settings, one tab of Setup, with the plugin's own settings screen in it (its tabs and its own form), at the short
 *   address edit.php?post_type=tix_ticket&page=certificates (a page with no menu item that draws Setup's tabs).
 *
 * Only the plugin's own filters and CampTix's public hooks are used, so
 * CampTix could replace this integration with its own:
 * remove_action( 'evcert_register_compat', 'EventsCertificate\Compat\CampTix\register' ).
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Compat\CampTix;

use EventsCertificate\Admin;
use EventsCertificate\Environment;

defined( 'ABSPATH' ) || exit;

/**
 * CampTix's menu (its tickets post type screen).
 */
const MENU = 'edit.php?post_type=tix_ticket';

/**
 * The settings page: the short address, a page of the Tickets menu with no item in it (edit.php?post_type=tix_ticket&page=certificates).
 */
const SETTINGS_SLUG = 'certificates';

/**
 * Setup tab key (?tix_section=): its address goes to the short one.
 */
const SECTION = 'certificates';

/**
 * Hooks, when CampTix is active.
 */
function register() {
	if ( ! Environment\has_camptix() ) {
		return;
	}
	// Getting a certificate from the ticket page and the Attendees Days column
	// are CampTix data features: they stay even when GatherPress is also active
	// (the certificates menu itself then goes standalone).
	register_front();
	Days\register();

	// Certificates lives under CampTix only when it is the sole compatible
	// plugin; alongside GatherPress it keeps its own menu (and its own settings).
	if ( Environment\has_gatherpress() ) {
		return;
	}
	add_filter( 'evcert_admin_menu_parent', __NAMESPACE__ . '\menu_parent' );
	// The settings: a Certificates tab of Setup that shows the plugin's own settings screen, at a short address (a page with no menu item).
	add_filter( 'evcert_settings_slug', __NAMESPACE__ . '\settings_slug' );
	add_filter( 'camptix_setup_sections', __NAMESPACE__ . '\add_setup_tab' );
	add_action( 'admin_menu', __NAMESPACE__ . '\register_settings_page', 20 );
	add_action( 'admin_menu', __NAMESPACE__ . '\hide_settings_page', 999 );
	add_action( 'admin_menu', __NAMESPACE__ . '\move_after_attendees', 99 );
	add_action( 'admin_init', __NAMESPACE__ . '\redirect_setup_tab' );
}

/**
 * Certificates is a submenu of Tickets.
 *
 * @return string
 */
function menu_parent() {
	return MENU;
}

/**
 * The settings screen's slug.
 *
 * @return string
 */
function settings_slug() {
	return SETTINGS_SLUG;
}

/**
 * The settings page, with no menu item.
 */
function register_settings_page() {
	$title = __( 'Certificates', 'events-certificates' );
	add_submenu_page( MENU, $title, $title, Admin\CAPABILITY, SETTINGS_SLUG, __NAMESPACE__ . '\render_settings_page' );
}

/**
 * Takes the settings page out of the menu (it is still there): its way in is the Setup tab.
 */
function hide_settings_page() {
	remove_submenu_page( MENU, SETTINGS_SLUG );
}

/**
 * Setup's own tabs, as CampTix prints them, with the Certificates tab selected.
 */
function setup_tabs() {
	// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- CampTix's own words, in its text domain.
	$sections = apply_filters(
		'camptix_setup_sections', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- CampTix's filter.
		array(
			'general'         => __( 'General', 'wordcamporg' ),
			'payment'         => __( 'Payment', 'wordcamporg' ),
			'email-templates' => __( 'E-mail Templates', 'wordcamporg' ),
			'beta'            => __( 'Beta', 'wordcamporg' ),
		)
	);
	// phpcs:enable WordPress.WP.I18n.TextDomainMismatch
	foreach ( $sections as $key => $caption ) {
		printf(
			'<a class="nav-tab%1$s" href="%2$s">%3$s</a>',
			SECTION === $key ? ' nav-tab-active' : '',
			esc_url( add_query_arg( 'tix_section', $key, admin_url( MENU . '&page=camptix_options' ) ) ),
			esc_html( $caption )
		);
	}
}

/**
 * The settings page: Setup's title and tabs, and the plugin's own settings screen in the Certificates tab.
 */
function render_settings_page() {
	if ( ! current_user_can( Admin\CAPABILITY ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( __( 'CampTix Setup', 'wordcamporg' ) ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- CampTix's own words. ?></h1>
		<?php settings_errors(); ?>
		<h3 class="nav-tab-wrapper"><?php setup_tabs(); ?></h3>
		<?php Admin\render_settings_body(); ?>
	</div>
	<?php
}

/**
 * The Setup tab's own address (?page=camptix_options&tix_section=certificates) goes to the short one, so the settings have one address.
 */
function redirect_setup_tab() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only reads which page is asked for.
	$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$section = isset( $_GET['tix_section'] ) ? sanitize_key( wp_unslash( $_GET['tix_section'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	if ( 'camptix_options' === $page && SECTION === $section && current_user_can( Admin\CAPABILITY ) ) {
		wp_safe_redirect( Admin\settings_url() );
		exit;
	}
}

/**
 * Adds the Certificates tab to Tickets > Setup, before Beta.
 *
 * @param array<string, string> $sections Tabs.
 * @return array<string, string>
 */
function add_setup_tab( $sections ) {
	$tab = array( SECTION => __( 'Certificates', 'events-certificates' ) );

	// Before Beta, which CampTix keeps last; at the end when there is no Beta tab.
	$position = array_search( 'beta', array_keys( $sections ), true );
	if ( false === $position ) {
		return $sections + $tab;
	}

	return array_slice( $sections, 0, $position, true ) + $tab + array_slice( $sections, $position, null, true );
}

/**
 * Moves Tickets > Certificates right after Attendees. CampTix's submenu mixes
 * post type screens and add_submenu_page() items, so the position is found,
 * not fixed. Without Attendees the item stays last.
 */
function move_after_attendees() {
	global $submenu;

	if ( empty( $submenu[ MENU ] ) ) {
		return;
	}
	$items = array_values( $submenu[ MENU ] );
	$ours  = null;
	foreach ( $items as $index => $item ) {
		if ( Admin\SLUG === $item[2] ) {
			$ours = $item;
			unset( $items[ $index ] );
			break;
		}
	}
	if ( null === $ours ) {
		return;
	}
	$items    = array_values( $items );
	$position = count( $items );
	foreach ( $items as $index => $item ) {
		if ( 'edit.php?post_type=tix_attendee' === $item[2] ) {
			$position = $index + 1;
			break;
		}
	}
	array_splice( $items, $position, 0, array( $ours ) );
	$submenu[ MENU ] = $items; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reordering the submenu is the point.
}
