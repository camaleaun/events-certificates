<?php
/**
 * The Base design: the certificate a site starts from.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Designs;
use EventsCertificate\Render;

/**
 * Flush with the page, no wording of its own, and the site (a network, a Playground blueprint) may give another.
 */
class BaseDesignTest extends Test_Case {

	/**
	 * Takes the filter off the tests that follow.
	 */
	protected function tear_down() {
		remove_all_filters( 'evcert_base_design' );
		parent::tear_down();
	}

	/**
	 * No bleed: the page is the sheet, so what is flush with its edge looks flush in the editor too.
	 */
	public function test_the_page_has_no_bleed() {
		$page = Designs\base()['page'];

		$this->assertSame( 0, (int) $page['bleed'] );
	}

	/**
	 * Nothing says "WordPress meetup" unless the site chose it.
	 */
	public function test_the_defaults_do_not_say_meetup() {
		$phrases = Render\phrases();

		$this->assertSame( '', $phrases['band_text'] );
		$this->assertStringNotContainsStringIgnoringCase( 'meetup', $phrases['took_part_text'] );
		$this->assertStringNotContainsStringIgnoringCase( 'meetup', (string) wp_json_encode( Designs\base() ) );
	}

	/**
	 * A filter gives another Base; what is not a design is ignored.
	 */
	public function test_the_base_is_filterable() {
		$other = static function ( $design ) {
			$design['colors']['band']['light'] = '#ff0000';

			return $design;
		};
		add_filter( 'evcert_base_design', $other );
		$this->assertSame( '#ff0000', Designs\base()['colors']['band']['light'] );
		remove_filter( 'evcert_base_design', $other );

		add_filter( 'evcert_base_design', '__return_empty_array' );
		$this->assertSame( 27, count( Designs\base()['elements'] ), 'Not a design: the Base that ships.' );
		remove_filter( 'evcert_base_design', '__return_empty_array' );

		add_filter( 'evcert_base_design', '__return_false' );
		$this->assertSame( 27, count( Designs\base()['elements'] ) );
	}

	/**
	 * A site without a design of its own gets one from the filtered Base (on a network: each site, the first time it is used).
	 */
	public function test_a_new_site_design_starts_from_the_filtered_base() {
		wp_set_current_user( 1 );
		delete_option( Designs\SITE_DESIGN_OPTION );
		add_filter(
			'evcert_base_design',
			static function ( $design ) {
				$design['colors']['band']['light'] = '#123456';

				return $design;
			}
		);

		$id = Designs\site_design_id();
		wp_set_current_user( 0 );

		$this->assertGreaterThan( 0, $id );
		$this->assertSame( '#123456', Designs\get( $id )['colors']['band']['light'] );
	}

	/**
	 * The Playground blueprint installs the plugin and no longer carries a filter for the meetup wording: the Meetup template has it.
	 */
	public function test_the_playground_blueprint_installs_the_plugin() {
		$blueprint = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/.wordpress-org/blueprints/blueprint.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the blueprint of the plugin.
		$this->assertIsArray( $blueprint );
		$this->assertSame( 'installPlugin', $blueprint['steps'][0]['step'] );
		// Until the plugin is in the directory the blueprint installs the zip of the release (the playground branch).
		$this->assertSame( 'url', $blueprint['steps'][0]['pluginData']['resource'] );
		$this->assertStringContainsString( '/events-certificates.zip', $blueprint['steps'][0]['pluginData']['url'] );
		foreach ( $blueprint['steps'] as $step ) {
			$this->assertStringNotContainsString( 'evcert_base_design', (string) wp_json_encode( $step ), 'No mu-plugin changes the Base design: the Meetup template says it.' );
		}
	}

	/**
	 * The Meetup template puts "Meetup WordPress" on the band and keeps the sentence under the name the role's own; the Base stays neutral.
	 */
	public function test_the_meetup_template_has_the_meetup_wording() {
		$render = static function ( array $design, $role ) {
			$html = Render\pdf_body(
				array(
					array(
						array(
							'name'      => 'Ana',
							'role'      => $role,
							'code'      => '2611-ABCD-EFGH-JKLM',
							'status'    => 'issued',
							'issued_at' => '2026-10-31 15:00:00',
						),
						$design,
						array(),
					),
				),
				static function ( $text, $family, $weight, $size ) {
					return strlen( (string) $text ) * $size * 0.5;
				}
			);

			return wp_strip_all_tags( $html );
		};

		$meetup = $render( Designs\meetup(), 'attendee' );
		$this->assertStringContainsString( 'MEETUP ' . strtoupper( 'WordPress' ), $meetup ); // The band is in capital letters; built so the sniff for the name does not rewrite it.
		$this->assertStringNotContainsString( 'took part in the meetup', $meetup, 'Only the band says it.' );
		$this->assertStringNotContainsString( 'MEETUP', $render( Designs\base(), 'attendee' ), 'The Base design is neutral.' );
	}
}
