<?php
/**
 * The signature of Settings: a picture or paths, chosen in the field and saved cleaned, and what the certificates draw.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Admin;
use EventsCertificate\Pictures;
use EventsCertificate\Render;
use EventsCertificate\Settings;
use EventsCertificate\Signer;

/**
 * What the field posts is cleaned again; the picture reaches the certificate and the summary page.
 */
class SignatureSettingsTest extends Test_Case {

	/**
	 * The signature options the site had (the tests write them for real, so they are put back).
	 *
	 * @var array<string, mixed>
	 */
	private $had = array();

	/**
	 * The filter that gives the settings a signer.
	 *
	 * @var callable
	 */
	private $signer;

	/**
	 * A signer is set, with no signature.
	 */
	protected function set_up() {
		parent::set_up();
		foreach ( array( Signer\SIGNATURE_OPTION, Signer\SIGNATURE_ATTACHMENT_OPTION ) as $option ) {
			$this->had[ $option ] = get_option( $option, null );
		}
		// The base case answers the settings with the defaults (the site's own are never read or written); this adds a signer.
		$this->signer = static function () {
			return array_merge(
				Settings\defaults(),
				array(
					'signer_name' => 'Ana Fictícia',
					'signer_role' => 'Organizer',
				)
			);
		};
		add_filter( 'pre_option_' . Settings\OPTION, $this->signer, 2 );
		$this->clean();
	}

	/**
	 * Nothing of the signature stays.
	 */
	protected function tear_down() {
		remove_filter( 'pre_option_' . Settings\OPTION, $this->signer, 2 );
		foreach ( $this->had as $option => $value ) {
			if ( null === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value, false );
			}
		}
		parent::tear_down();
	}

	/**
	 * Clears the signature options.
	 */
	private function clean() {
		delete_option( Signer\SIGNATURE_OPTION );
		delete_option( Signer\SIGNATURE_ATTACHMENT_OPTION );
	}

	/**
	 * What the field posts, as the settings form sends it.
	 *
	 * @param array<string, string> $fields signature_json and signature_attachment.
	 * @return array<string, mixed> What is saved of the settings.
	 */
	private function post( array $fields ) {
		return Admin\sanitize_posted( array_merge( Settings\get(), $fields ) );
	}

	/**
	 * Paths are kept as paths: a viewBox and filled paths only.
	 */
	public function test_drawn_paths_are_saved_cleaned() {
		$saved = $this->post(
			array(
				'signature_json'       => '{"vw":120,"vh":44,"paths":[{"d":"M0 0L10 10Z","fill":"#112233"},{"d":"<script>alert(1)</script>"}]}',
				'signature_attachment' => '',
			)
		);
		$this->assertArrayNotHasKey( 'signature_json', $saved );
		$stored = get_option( Signer\SIGNATURE_OPTION );
		$this->assertCount( 1, $stored['paths'] );
		$this->assertSame( '#112233', $stored['paths'][0]['fill'] );
		$this->assertFalse( get_option( Signer\SIGNATURE_ATTACHMENT_OPTION ) );
	}

	/**
	 * A picture is kept as the ID of an attachment that is a raster picture; what is not one clears the option.
	 */
	public function test_a_picture_is_saved_only_when_it_is_a_raster_attachment() {
		$id = $this->make_picture();
		$this->post(
			array(
				'signature_json'       => '',
				'signature_attachment' => $id,
			)
		);
		$this->assertSame( (int) $id, get_option( Signer\SIGNATURE_ATTACHMENT_OPTION ) );
		$this->assertFalse( get_option( Signer\SIGNATURE_OPTION ), 'A picture and paths do not stay together: the field posts one.' );

		foreach ( array( '999999', 'data:image/png;base64,iVBORw0KGgo=', 'https://example.com/signature.png', '' ) as $bad ) {
			$this->post(
				array(
					'signature_json'       => '',
					'signature_attachment' => $bad,
				)
			);
			$this->assertFalse( get_option( Signer\SIGNATURE_ATTACHMENT_OPTION ), 'Not an attachment that is a picture: ' . substr( $bad, 0, 20 ) );
		}
	}

	/**
	 * The signer carries the picture, and a certificate draws it; the event's own picture or drawing comes first.
	 */
	public function test_the_certificate_draws_the_settings_picture_unless_the_event_has_its_own() {
		$id = $this->make_picture();
		$this->post(
			array(
				'signature_json'       => '',
				'signature_attachment' => $id,
			)
		);
		$url = Pictures\src( $id );
		$this->assertNotSame( '', $url );
		$this->assertSame( $url, Signer\current()['image'] );

		$cert   = array(
			'name'      => 'Bia',
			'issued_at' => '2026-11-25 20:00:00',
			'data_json' => '{}',
		);
		$values = Render\values( $cert );
		$this->assertSame( $url, $values['signature_image'] );

		// The summary page draws it too (it used to ignore a picture).
		$this->assertSame( $url, Render\signature_src( array( 'elements' => array() ), $values, '#000' ) );

		// The event drew its own signature: that one, not the picture of Settings.
		$cert['data_json'] = (string) wp_json_encode( array( 'signature_svg' => '{"vw":100,"vh":40,"paths":[{"d":"M0 0L50 20L100 0"}]}' ) );
		$this->assertSame( '', Render\values( $cert )['signature_image'] );
		$this->assertNotSame( '', Render\signature_src( array( 'elements' => array() ), Render\values( $cert ), '#000' ) );

		// The event has a picture of its own.
		$own               = $this->make_picture( 'evcert-own.png' );
		$cert['data_json'] = (string) wp_json_encode( array( 'signature_image' => $own ) );
		$this->assertSame( Pictures\src( $own ), Render\values( $cert )['signature_image'] );
		$this->assertNotSame( $url, Pictures\src( $own ) );
	}

	/**
	 * Paths of Settings win over its picture when both are there (the field posts one, but nothing else may break it).
	 */
	public function test_paths_of_settings_come_before_its_picture() {
		update_option( Signer\SIGNATURE_OPTION, array( 'vw' => 100, 'vh' => 40, 'paths' => array( array( 'd' => 'M0 0L50 20L100 0' ) ) ), false ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		update_option( Signer\SIGNATURE_ATTACHMENT_OPTION, (int) $this->make_picture(), false );
		$values = Render\values(
			array(
				'name'      => 'Bia',
				'issued_at' => '2026-11-25 20:00:00',
				'data_json' => '{}',
			)
		);
		$this->assertSame( '', $values['signature_image'] );
		$this->assertNotSame( 'null', $values['signature'] );
	}

	/**
	 * The get-settings ability says there is a signature when it is a picture.
	 */
	public function test_the_ability_counts_a_picture() {
		$this->assertFalse( \EventsCertificate\Abilities\read_settings()['has_signature'] );
		update_option( Signer\SIGNATURE_ATTACHMENT_OPTION, (int) $this->make_picture(), false );
		$this->assertTrue( \EventsCertificate\Abilities\read_settings()['has_signature'] );
	}
}
