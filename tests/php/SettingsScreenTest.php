<?php
/**
 * The settings screen: features first, then what each one needs (decision 0029).
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Admin;
use EventsCertificate\Environment;
use EventsCertificate\Settings;

/**
 * Sections, rows that depend on a feature, and the better fields.
 */
class SettingsScreenTest extends Test_Case {

	/**
	 * A page name for the sections of the test.
	 */
	const PAGE = 'evcert-settings-test';

	/**
	 * Admin functions, and the sections added.
	 */
	protected function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/template.php';
		Admin\add_settings_fields( self::PAGE );
	}

	/**
	 * Takes the test's sections away.
	 */
	protected function tear_down() {
		unset( $GLOBALS['wp_settings_sections'][ self::PAGE ], $GLOBALS['wp_settings_fields'][ self::PAGE ] );
		parent::tear_down();
	}

	/**
	 * Features on top, then the certificates, the verification, the declaration (CampTix) and the data.
	 */
	public function test_sections_are_in_the_order_one_configures() {
		$expected = Environment\has_camptix()
			? array( 'evcert_features', 'evcert_issuing', 'evcert_authenticity', 'evcert_document', 'evcert_days', 'evcert_mine', 'evcert_data' )
			: array( 'evcert_features', 'evcert_issuing', 'evcert_authenticity', 'evcert_mine', 'evcert_data' );

		$this->assertSame( $expected, array_keys( $GLOBALS['wp_settings_sections'][ self::PAGE ] ) );
	}

	/**
	 * The colors a certificate comes in are a field of the issuing section, and its help says the PDF is always light.
	 */
	public function test_the_certificate_colors_field_says_the_pdf_is_light() {
		$this->assertArrayHasKey( 'evcert_certificate_modes', $GLOBALS['wp_settings_fields'][ self::PAGE ]['evcert_issuing'] );
		ob_start();
		Admin\render_certificate_modes_field();
		$html = (string) ob_get_clean();
		$this->assertMatchesRegularExpression( '/value="light"\s+checked/', $html, 'Light only is the default.' );
		$this->assertStringContainsString( 'value="both"', $html );
		$this->assertStringContainsString( 'The PDF is always light.', $html );
	}

	/**
	 * The switches are in the features section: verification and the declaration page always (the declaration also turns on the editor's Declaration tab),
	 * self-issuing with CampTix.
	 */
	public function test_the_switches_are_the_first_section() {
		$fields = array_keys( $GLOBALS['wp_settings_fields'][ self::PAGE ]['evcert_features'] );

		$this->assertContains( 'evcert_verification', $fields );
		$this->assertSame( Environment\has_camptix(), in_array( 'evcert_self_issue', $fields, true ) );
		$this->assertContains( 'evcert_declaration', $fields );
		$this->assertFalse( (bool) Settings\defaults()['declaration'], 'The declaration page starts off.' );
		$this->assertArrayNotHasKey( 'evcert_verification', $GLOBALS['wp_settings_fields'][ self::PAGE ]['evcert_authenticity'], 'It is not repeated in its own section.' );
	}

	/**
	 * What only matters with a feature on is marked, so the script can hide it while the feature is off.
	 */
	public function test_rows_that_need_a_feature_are_marked() {
		$authenticity = $GLOBALS['wp_settings_fields'][ self::PAGE ]['evcert_authenticity'];
		foreach ( array( 'evcert_force_public', 'evcert_verify_page', 'evcert_slug' ) as $field ) {
			$this->assertSame( 'evcert-needs-verification', $authenticity[ $field ]['args']['class'], $field );
		}
		if ( Environment\has_camptix() ) {
			$this->assertSame( 'evcert-needs-declaration', $GLOBALS['wp_settings_fields'][ self::PAGE ]['evcert_document']['evcert_document_country']['args']['class'] );
		}
	}

	/**
	 * The signature is a field like WordPress's Site Icon: a mount for the chooser (a dialog with Upload files, Media Library and
	 * Edit SVG), and nothing else to type in; its script and what it starts from are loaded.
	 */
	public function test_the_signature_is_a_chooser_not_a_file_input() {
		ob_start();
		Admin\render_signature_field();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'id="evcert-signature-field"', $html );
		$this->assertStringNotContainsString( 'type="file"', $html );
		$this->assertStringNotContainsString( 'signature_svg', $html, 'The code is no longer pasted.' );
		$this->assertStringNotContainsString( 'style=', $html, 'No inline style.' );
		if ( wp_script_is( 'evcert-settings-signature', 'registered' ) ) {
			$this->assertTrue( wp_script_is( 'evcert-settings-signature', 'enqueued' ) );
			$data = implode( '', (array) wp_scripts()->get_data( 'evcert-settings-signature', 'before' ) );
			$this->assertStringContainsString( 'evcertSignatureField', $data );
			$this->assertStringContainsString( '"chooser":"media"', $data, 'The media modal is the default.' );
		}

		// It is a row of its own, like the Site Icon's, apart from who signs.
		ob_start();
		Admin\render_signer_field();
		$signer = ob_get_clean();
		$this->assertStringNotContainsString( 'evcert-signature-field', $signer );
		$this->assertArrayHasKey( 'evcert_signature', $GLOBALS['wp_settings_fields'][ self::PAGE ]['evcert_issuing'] );
	}

	/**
	 * Which dialog chooses the signature is a switch for development: the media modal by default, the React one by option or
	 * filter; nothing else is accepted.
	 */
	public function test_the_chooser_is_a_switch_not_a_setting() {
		$this->assertSame( 'media', Admin\signature_chooser() );

		update_option( 'evcert_signature_chooser', 'react' );
		$this->assertSame( 'react', Admin\signature_chooser() );
		update_option( 'evcert_signature_chooser', 'nonsense' );
		$this->assertSame( 'media', Admin\signature_chooser() );
		delete_option( 'evcert_signature_chooser' );

		$react = static function () {
			return 'react';
		};
		add_filter( 'evcert_signature_chooser', $react );
		$this->assertSame( 'react', Admin\signature_chooser() );
		remove_filter( 'evcert_signature_chooser', $react );
		$this->assertSame( 'media', Admin\signature_chooser() );
	}

	/**
	 * The date of issue is two choices, each said.
	 */
	public function test_the_date_of_issue_is_two_radios() {
		ob_start();
		Admin\render_issued_date_field();
		$html = ob_get_clean();

		$this->assertSame( 2, substr_count( $html, 'type="radio"' ) );
		$this->assertStringContainsString( 'value="issued"', $html );
		$this->assertStringContainsString( 'value="event_end"', $html );
		$this->assertStringContainsString( 'checked', $html );
	}

	/**
	 * The days are cards (a name per language, a kind, tables), not a wide table, and carry no inline style.
	 */
	public function test_days_are_cards_without_inline_style() {
		\EventsCertificate\Days\save(
			array(
				array(
					'date'   => '2026-11-14',
					'names'  => array( 'en_US' => 'Day one' ),
					'mode'   => 'table',
					'tables' => array( 'en_US' => array( 'Table 1' ) ),
				),
			)
		);
		ob_start();
		Admin\render_days_section();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'class="evcert-day', $html );
		$this->assertStringNotContainsString( '<table', $html );
		$this->assertStringNotContainsString( 'style=', $html );
		$this->assertStringContainsString( 'evcert-needs-declaration', $html );
	}

	/**
	 * The Settings screen has Export and Import next to its title, as the lists have, with the settings ticked.
	 */
	public function test_the_settings_screen_has_export_and_import_buttons() {
		wp_set_current_user( 1 );
		// Export and import are off by default with CampTix or GatherPress: these tests are about the buttons.
		update_option(
			\EventsCertificate\Restrictions\OPTION,
			array(
				'export' => true,
				'import' => true,
			)
		);

		ob_start();
		Admin\render_settings_page();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'page-title-action', $html );
		$this->assertStringContainsString( 'page=evcert-export', $html );
		$this->assertStringContainsString( 'page=evcert-import', $html );
		$this->assertStringContainsString( 'section=settings', $html );
	}

	/**
	 * The Tools tab is last, has a form for each action and none of the options' form.
	 */
	public function test_the_tools_tab_is_apart_from_the_options_form() {
		wp_set_current_user( 1 );
		$_GET['tab'] = 'tools';
		ob_start();
		Admin\render_settings_page();
		$html = (string) ob_get_clean();
		unset( $_GET['tab'] );

		$this->assertStringContainsString( 'tab=tools', $html, 'The tab is in the navigation.' );
		$this->assertStringContainsString( 'evcert-tools__item', $html );
		$this->assertStringNotContainsString( 'action="options.php"', $html, 'No settings to save on this tab.' );

		ob_start();
		Admin\render_settings_page();
		$other = (string) ob_get_clean();
		$this->assertStringContainsString( 'action="options.php"', $other, 'The other tabs keep their form.' );
		$this->assertStringNotContainsString( 'evcert-tools__item', $other );
	}

	/**
	 * The screens that have a picture field (the Quick generator, the event form) get the media modal, unless the React dialog is
	 * chosen; then the picture field opens its own.
	 */
	public function test_the_picture_screens_load_the_media_modal_by_the_same_switch() {
		if ( ! wp_script_is( 'evcert-generator', 'registered' ) ) {
			$this->markTestSkipped( 'The built scripts are not here.' );
		}
		Admin\enqueue_media_chooser( 'evcert-generator' );
		$this->assertContains( 'media-views', wp_scripts()->registered['evcert-generator']->deps );
		$this->assertTrue( wp_style_is( 'evcert-signature-chooser', 'enqueued' ) );

		wp_scripts()->registered['evcert-generator']->deps = array_diff( wp_scripts()->registered['evcert-generator']->deps, array( 'media-views' ) );
		$react = static function () {
			return 'react';
		};
		add_filter( 'evcert_signature_chooser', $react );
		Admin\enqueue_media_chooser( 'evcert-generator' );
		remove_filter( 'evcert_signature_chooser', $react );
		$this->assertNotContains( 'media-views', wp_scripts()->registered['evcert-generator']->deps );
	}

	/**
	 * The body of the settings screen, as the three mounts print it.
	 *
	 * @return string
	 */
	private function body() {
		wp_set_current_user( 1 );
		ob_start();
		Admin\render_settings_body();
		return (string) ob_get_clean();
	}

	/**
	 * The screen is one piece: the same tabs, the same markup, whichever site it is on (docs/decisions/0057).
	 */
	public function test_the_settings_screen_has_the_same_tabs_and_markup_on_every_site() {
		$html = $this->body();
		foreach ( array( 'Features', 'Issuing', 'Verification', 'Data', 'Tools' ) as $caption ) {
			$this->assertStringContainsString( '>' . $caption . '</a>', $html, $caption );
		}
		$this->assertStringContainsString( 'class="evcert-settings"', $html );
		$this->assertStringContainsString( 'subsubsub evcert-settings__tabs', $html );
		$this->assertStringContainsString( 'evcert-settings__buttons', $html );
		$this->assertStringNotContainsString( 'nav-tab', $html, 'The tabs are a list of links, not a second row of tabs.' );
		// The declaration tab is there when its settings are (CampTix, or the declaration turned on); the others always.
		$this->assertSame( array( 'features', 'certificates', 'verification', 'data', 'tools' ), array_values( array_diff( array_keys( Admin\settings_tabs() ), array( 'declaration' ) ) ) );
	}

	/**
	 * Each mount prints the screen inside its own frame and nothing else of the settings: the body is in it whole.
	 */
	public function test_every_mount_prints_the_same_body() {
		$body = $this->body();

		ob_start();
		Admin\render_settings_page();
		$this->assertStringContainsString( $body, (string) ob_get_clean(), 'A site with no integration.' );

		ob_start();
		\EventsCertificate\Compat\CampTix\render_settings_page();
		$this->assertStringContainsString( $body, (string) ob_get_clean(), 'CampTix.' );

		if ( class_exists( '\GatherPress\Core\Settings' ) ) {
			ob_start();
			\EventsCertificate\Compat\GatherPress\render_settings_tab( 'gatherpress_certificates' );
			$this->assertStringContainsString( $body, (string) ob_get_clean(), 'GatherPress.' );
		}
	}

	/**
	 * A tab taken away (or added) once is taken away in every mount: the list of tabs is one.
	 */
	public function test_a_tab_changed_in_the_filter_is_changed_in_every_mount() {
		$drop = static function ( $tabs ) {
			unset( $tabs['data'] );
			return $tabs;
		};
		add_filter( 'evcert_settings_tabs', $drop );
		$body = $this->body();
		$this->assertStringNotContainsString( '>Data</a>', $body );
		$this->assertStringContainsString( '>Tools</a>', $body );

		ob_start();
		\EventsCertificate\Compat\CampTix\render_settings_page();
		$this->assertStringNotContainsString( '>Data</a>', (string) ob_get_clean() );
		remove_filter( 'evcert_settings_tabs', $drop );
	}

	/**
	 * The mounts only mount: nothing in them draws a field, a section or the form of the settings (options.php) (that is includes/admin-settings.php, for all of them).
	 */
	public function test_the_mounts_draw_no_settings_of_their_own() {
		$root   = dirname( __DIR__, 2 ) . '/includes';
		$mounts = array( $root . '/compat/gatherpress.php', $root . '/compat/camptix.php', $root . '/admin.php' );
		foreach ( $mounts as $file ) {
			$code = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the source of the plugin.
			foreach ( array( 'add_settings_field(', 'add_settings_section(', 'settings_fields(', 'do_settings_fields(', 'options.php' ) as $forbidden ) {
				$this->assertStringNotContainsString( $forbidden, $code, basename( $file ) . ' draws settings of its own: ' . $forbidden );
			}
		}
	}
}
