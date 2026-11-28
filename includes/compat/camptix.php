<?php
/**
 * CampTix (WordCamps): Certificates goes where organizers already look.
 *
 * - Tickets > Certificates: the screen, right after Attendees.
 * - Tickets > Setup > Certificates: the settings, one tab of Setup at Setup's own address (edit.php?post_type=tix_ticket&page=camptix_options&tix_section=evcert-certificates),
 *   with the plugin's own settings screen in it (its sub-tabs and its own form). CampTix wraps its tabs in a form and forms do not nest, so for that section the Setup page
 *   is drawn by the plugin: CampTix's title and tabs, then the settings.
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
 * The settings page: Setup's own (Tickets > Setup), so the menu item, the address and the highlight are CampTix's.
 */
const SETTINGS_SLUG = 'camptix_options';

/**
 * Setup tab key (?tix_section=).
 */
const SECTION = 'evcert-certificates';

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
	// The settings: a Certificates tab of Setup (its own address with tix_section) that shows the plugin's own settings screen.
	add_filter( 'evcert_settings_slug', __NAMESPACE__ . '\settings_slug' );
	add_filter( 'evcert_settings_url', __NAMESPACE__ . '\settings_address' );
	add_filter( 'camptix_setup_sections', __NAMESPACE__ . '\add_setup_tab' );
	add_action( 'admin_menu', __NAMESPACE__ . '\draw_setup_page', 999 );
	add_action( 'admin_menu', __NAMESPACE__ . '\move_after_attendees', 99 );
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
 * The address of the settings: Setup's with the plugin's section.
 *
 * @param string $url Setup's address.
 * @return string
 */
function settings_address( $url ) {
	return add_query_arg( 'tix_section', SECTION, $url );
}

/**
 * Setup is drawn by the plugin when the section asked for is its own: CampTix's page prints every section inside one form, and the plugin's settings have a form of their
 * own (forms do not nest). Every other section stays CampTix's.
 */
function draw_setup_page() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only reads which page is asked for.
	$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$section = isset( $_GET['tix_section'] ) ? sanitize_key( wp_unslash( $_GET['tix_section'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	if ( SETTINGS_SLUG !== $page || SECTION !== $section ) {
		return;
	}
	$hook = get_plugin_page_hookname( SETTINGS_SLUG, MENU );
	global $wp_filter;
	if ( empty( $wp_filter[ $hook ] ) ) {
		return;
	}
	// CampTix's callback is a method of its setup object: it is found by the method, wherever the object is kept.
	foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $id => $callback ) {
			if ( is_array( $callback['function'] ) && isset( $callback['function'][1] ) && 'menu_setup' === $callback['function'][1] ) {
				remove_action( $hook, $callback['function'], $priority );
			}
		}
	}
	add_action( $hook, __NAMESPACE__ . '\render_settings_page' );
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
 * Setup's page for the Certificates section: CampTix's title and tabs, and the plugin's own settings screen.
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
		<?php Admin\render_settings_body( true ); ?>
	</div>
	<?php
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
