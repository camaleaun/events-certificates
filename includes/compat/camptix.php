<?php
/**
 * CampTix (WordCamps): Certificates goes where organizers already look.
 *
 * - Tickets > Certificates: the screen, right after Attendees.
 * - Tickets > Certificate settings: the plugin's own settings screen (page=certificates), with its tabs and its own form.
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
 * The settings screen's slug: a page of the Tickets menu (edit.php?post_type=tix_ticket&page=certificates).
 */
const SETTINGS_SLUG = 'certificates';

/**
 * Where the settings were before: a tab of Tickets > Setup (?page=camptix_options&tix_section=certificates). Its address goes to the settings screen.
 */
const OLD_SECTION = 'certificates';

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
	// The settings: the plugin's own screen (its short address, a submenu next to Certificates), not a tab of CampTix's Setup.
	add_filter( 'evcert_settings_slug', __NAMESPACE__ . '\settings_slug' );
	add_action( 'admin_menu', __NAMESPACE__ . '\register_settings_menu', 20 );
	add_action( 'admin_menu', __NAMESPACE__ . '\move_after_attendees', 99 );
	// WordPress refuses an unknown page before admin_init, so the redirect is done as the menu is made.
	add_action( 'admin_menu', __NAMESPACE__ . '\redirect_old_settings', 99 );
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
 * A settings submenu of Tickets, next to Certificates: the plugin's own settings screen (Features, Issuing, Verification, Declaration, Data, Tools).
 */
function register_settings_menu() {
	$title = __( 'Certificate settings', 'events-certificates' );
	add_submenu_page( MENU, $title, $title, Admin\CAPABILITY, SETTINGS_SLUG, 'EventsCertificate\Admin\render_settings_page' );
}

/**
 * The old address of the settings (a tab of Tickets > Setup) goes to the settings screen.
 */
function redirect_old_settings() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only reads which page is asked for.
	$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$section = isset( $_GET['tix_section'] ) ? sanitize_key( wp_unslash( $_GET['tix_section'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	if ( 'camptix_options' === $page && OLD_SECTION === $section && current_user_can( Admin\CAPABILITY ) ) {
		wp_safe_redirect( Admin\settings_url() );
		exit;
	}
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
	// Certificates, then its settings, kept in that order.
	$ours = array();
	foreach ( array( Admin\SLUG, SETTINGS_SLUG ) as $slug ) {
		foreach ( $items as $index => $item ) {
			if ( $slug === $item[2] ) {
				$ours[] = $item;
				unset( $items[ $index ] );
				break;
			}
		}
	}
	if ( ! $ours ) {
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
	array_splice( $items, $position, 0, $ours );
	$submenu[ MENU ] = $items; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reordering the submenu is the point.
}
