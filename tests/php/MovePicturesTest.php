<?php
/**
 * The command that moves the data URIs of older versions into the Media Library (bin/pictures-lib.php, docs/decisions/0044).
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Certificates;
use EventsCertificate\Events;
use EventsCertificate\People;
use EventsCertificate\Pictures;

/**
 * A data URI becomes one attachment however many places use it, and nothing is written without saying so.
 */
class MovePicturesTest extends Test_Case {

	/**
	 * Attachments the command made.
	 *
	 * @var int[]
	 */
	private $made = array();

	/**
	 * The command's functions.
	 */
	protected function set_up() {
		parent::set_up();
		require_once dirname( __DIR__, 2 ) . '/bin/pictures-lib.php';
	}

	/**
	 * Takes the files the command made.
	 */
	protected function tear_down() {
		foreach ( $this->made as $id ) {
			wp_delete_attachment( $id, true );
		}
		delete_option( 'evcert_signature_image' );
		delete_option( 'evcert_signature_attachment' );
		parent::tear_down();
	}

	/**
	 * The attachments made from a file by the plugin (they have the file's hash).
	 *
	 * @return int[]
	 */
	private function assets() {
		return array_map(
			'intval',
			(array) get_posts(
				array(
					'post_type'   => 'attachment',
					'post_status' => 'inherit',
					'numberposts' => -1,
					'meta_key'    => '_evcert_asset_sha256', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a test looks at what a run made.
					'fields'      => 'ids',
				)
			)
		);
	}

	/**
	 * A tiny PNG, as an older version kept it.
	 *
	 * @return string
	 */
	private function png() {
		return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
	}

	/**
	 * An event, a certificate and the settings of an older version, all with the same picture.
	 *
	 * @return array<string, mixed>
	 */
	private function old_world() {
		wp_set_current_user( 1 );
		$event = Fixtures::event( array( 'title' => 'Old meetup' ) );
		update_post_meta( $event, '_evcert_logo', $this->png() );
		$ana  = Fixtures::attendee_of_event( $event, 'Ana Lima', 'ana@example.test' );
		$cert = People\issue( $ana );
		global $wpdb;
		$wpdb->update( Certificates\table(), array( 'data_json' => (string) wp_json_encode( array( 'logo' => $this->png() ) ) ), array( 'code' => $cert['code'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		update_option( 'evcert_signature_image', $this->png(), false );

		return array(
			'event' => $event,
			'code'  => $cert['code'],
		);
	}

	/**
	 * Without the word yes nothing is written; with it, one attachment is made for the three places, and a second run makes nothing.
	 */
	public function test_a_dry_run_writes_nothing_and_a_run_makes_one_attachment() {
		$pre    = $this->assets();
		$before = evcert_pictures_move( false ); // What the site already has (a site may have its own).
		$world  = $this->old_world();

		$dry = evcert_pictures_move( false );
		$this->assertSame( $before['found'] + 3, $dry['found'], 'The event, the certificate and the settings.' );
		$this->assertSame( $before['pictures'] + 1, $dry['pictures'], 'All three use one file.' );
		$this->assertSame( $this->png(), get_post_meta( $world['event'], '_evcert_logo', true ), 'Nothing was written.' );
		$this->assertSame( $this->png(), get_option( 'evcert_signature_image' ) );

		$done = evcert_pictures_move( true );
		$this->assertSame( $dry['found'], $done['moved'] );
		$this->assertSame( 0, $done['failed'] );
		$logo = (string) get_post_meta( $world['event'], '_evcert_logo', true );
		$this->assertNotSame( '', Pictures\clean( $logo ), 'The logo of the event is an attachment now.' );
		$this->made[] = (int) $logo;
		$row          = Certificates\get_by_code( $world['code'] );
		$this->assertSame( $logo, (string) json_decode( (string) $row['data_json'], true )['logo'], 'The certificate uses the same one.' );
		$this->assertSame( (int) $logo, (int) get_option( 'evcert_signature_attachment' ) );
		$this->assertFalse( get_option( 'evcert_signature_image' ), 'The old option is gone once its picture is safe.' );
		$this->assertSame( $logo, Events\get( $world['event'] )['logo'] );

		$again = evcert_pictures_move( true );
		$this->assertSame( 0, $again['found'], 'A second run finds nothing.' );
		foreach ( array_diff( $this->assets(), $pre ) as $id ) {
			$this->made[] = (int) $id; // Only what the run made is taken away with the test.
		}
	}

	/**
	 * Only a raster data URI is a picture: an SVG, an address and a broken one are left alone.
	 */
	public function test_only_a_raster_data_uri_is_moved() {
		foreach ( array( 'data:image/svg+xml;base64,PHN2Zy8+', 'https://example.com/a.png', 'data:image/png;base64,@@@', '', 'x' ) as $not ) {
			$this->assertNull( evcert_pictures_decode( $not ) );
		}
		$picture = evcert_pictures_decode( $this->png() );
		$this->assertSame( 'image/png', $picture['mime'] );
		$this->assertSame( hash( 'sha256', $picture['bytes'] ), $picture['sha'] );
	}

	/**
	 * An older export becomes a bundle with references and the bytes as files.
	 */
	public function test_an_older_bundle_is_converted() {
		$old = array(
			'events'       => array(
			array(
			'logo'            => $this->png(),
			'signature_image' => '',
				),
			), // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
			'certificates' => array( array( 'data' => array( 'logo' => $this->png() ) ) ),
			'settings'     => array( 'signature_image' => $this->png() ),
		);
		$out = evcert_pictures_convert_bundle( $old );
		$ref = $out['bundle']['events'][0]['logo'];
		$this->assertMatchesRegularExpression( '/^picture:[a-f0-9]{64}$/', $ref );
		$this->assertSame( $ref, $out['bundle']['certificates'][0]['data']['logo'] );
		$this->assertSame( $ref, $out['bundle']['settings']['signature_image'] );
		$this->assertCount( 1, $out['files'], 'One file for the three places.' );
		$this->assertCount( 1, $out['bundle']['assets']['images'] );
		$this->assertStringNotContainsString( 'data:image', (string) wp_json_encode( $out['bundle'] ) );
		foreach ( $out['files'] as $tmp ) {
			wp_delete_file( $tmp );
		}
	}
}
