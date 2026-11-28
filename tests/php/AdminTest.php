<?php
/**
 * Admin screens and settings.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Admin;
use EventsCertificate\Compat\CampTix;
use EventsCertificate\Settings;

/**
 * Admin tests.
 */
class AdminTest extends Test_Case {

	public function test_sanitize_posted_keeps_saved_settings_when_not_posted() {
		$saved = static function () {
			return array(
				'slug'         => 'certificado',
				'force_public' => true,
			);
		};
		add_filter( 'pre_option_' . Settings\OPTION, $saved, 5 );
		remove_filter( 'pre_option_' . Settings\OPTION, '\EventsCertificate\Settings\defaults', 1 );

		$kept = Admin\sanitize_posted( null );
		$this->assertSame( 'certificado', $kept['slug'] );
		$this->assertTrue( $kept['force_public'] );

		// A posted form without the checkbox turns it off.
		$posted = Admin\sanitize_posted( array( 'slug' => 'Certificado Válido' ) );
		$this->assertSame( 'certificado-valido', $posted['slug'] );
		$this->assertFalse( $posted['force_public'] );

		remove_filter( 'pre_option_' . Settings\OPTION, $saved, 5 );
	}

	public function test_empty_slug_falls_back_to_certificate() {
		$this->assertSame( 'certificate', Settings\sanitize( array( 'slug' => '  ' ) )['slug'] );
	}

	/**
	 * A certificate is light by default; "Light and dark" is a choice, and anything else falls back to light.
	 */
	public function test_certificate_colors_are_light_unless_both_is_chosen() {
		$this->assertSame( 'light', Settings\defaults()['certificate_modes'] );
		$this->assertFalse( Settings\dark_enabled() );
		$this->assertSame( 'both', Settings\sanitize( array( 'certificate_modes' => 'both' ) )['certificate_modes'] );
		$this->assertSame( 'light', Settings\sanitize( array( 'certificate_modes' => 'dark' ) )['certificate_modes'], 'Dark only is not a choice.' );
		$this->assertSame( 'light', Settings\sanitize( array() )['certificate_modes'] );
		$both = static fn () => array( 'certificate_modes' => 'both' ) + Settings\defaults();
		add_filter( 'pre_option_' . Settings\OPTION, $both, 5 ); // The tests pin the settings to the defaults.
		$this->assertTrue( Settings\dark_enabled() );
		remove_filter( 'pre_option_' . Settings\OPTION, $both, 5 );
	}

	public function test_certificates_goes_right_after_attendees_in_camptix_menu() {
		global $submenu;

		$backup                  = $submenu;
		$submenu[ CampTix\MENU ] = array( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			array( 'Tickets', 'read', CampTix\MENU ),
			array( 'Add New', 'read', 'post-new.php?post_type=tix_ticket' ),
			array( 'Attendees', 'read', 'edit.php?post_type=tix_attendee' ),
			array( 'Coupons', 'read', 'edit.php?post_type=tix_coupon' ),
			array( 'Setup', 'read', 'camptix_options' ),
			array( 'Certificates', 'read', Admin\SLUG ),
		);
		CampTix\move_after_attendees();
		$slugs   = wp_list_pluck( $submenu[ CampTix\MENU ], 2 );
		$submenu = $backup; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertSame( array( CampTix\MENU, 'post-new.php?post_type=tix_ticket', 'edit.php?post_type=tix_attendee', Admin\SLUG, 'edit.php?post_type=tix_coupon', 'camptix_options' ), array_values( $slugs ) );
	}
}
