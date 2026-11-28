<?php
/**
 * Restrictions: what a network may switch off in export and import, with no screen (docs/decisions/0044).
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Bundle;
use EventsCertificate\Designs;
use EventsCertificate\Events;
use EventsCertificate\Restrictions;

/**
 * Restrictions tests.
 */
class RestrictionsTest extends Test_Case {

	/**
	 * A drawing as the plugin keeps it.
	 *
	 * @var string
	 */
	const DRAWING = '{"vw":100,"vh":40,"paths":[{"d":"M0 0L100 0L100 40L0 40Z"}]}';

	/**
	 * Starts as an administrator.
	 */
	protected function set_up() {
		parent::set_up();
		wp_set_current_user(
			(int) get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
					'fields' => 'ID',
				)
			)[0]
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
	}

	/**
	 * Takes the test's choices away.
	 */
	protected function tear_down() {
		delete_option( Restrictions\OPTION );
		remove_all_filters( 'evcert_restrictions' );
		remove_all_filters( 'evcert_bundle_max_bytes' );
		foreach ( Restrictions\FEATURES as $feature ) {
			remove_all_filters( 'evcert_allow_' . $feature );
		}
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * A single site allows everything; a network leaves the features that carry files and drawings to the super administrator.
	 */
	public function test_the_defaults() {
		$this->assertSame( array_fill_keys( Restrictions\FEATURES, true ), Restrictions\defaults( false, null, false ) );
		// With CampTix or GatherPress the lists do not travel (a single template does, from the editor).
		$integrated = Restrictions\defaults( false, null, true );
		$this->assertFalse( $integrated['export'] );
		$this->assertFalse( $integrated['import'] );
		$this->assertTrue( $integrated['zip'] );
		$network = Restrictions\defaults( true, false, false );
		$this->assertTrue( $network['export'] );
		$this->assertTrue( $network['import'] );
		foreach ( array( 'zip', 'fonts', 'pictures', 'vectors' ) as $feature ) {
			$this->assertFalse( $network[ $feature ], $feature );
			$this->assertTrue( Restrictions\defaults( true, true, false )[ $feature ], 'A super administrator.' );
		}
	}

	/**
	 * The option, then the filters; the filter has the last word. Nothing else is a feature.
	 */
	public function test_the_option_and_the_filters() {
		$this->assertTrue( Restrictions\allows( 'vectors' ) );
		update_option(
			Restrictions\OPTION,
			array(
				'vectors'  => false,
				'nonsense' => false,
			)
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$this->assertFalse( Restrictions\allows( 'vectors' ), 'The option.' );
		$this->assertSame( Restrictions\defaults()['export'], Restrictions\allows( 'export' ), 'What it does not say stays.' );
		$this->assertFalse( Restrictions\allows( 'nonsense' ), 'Not a feature.' );

		add_filter(
			'evcert_restrictions',
			static function ( $map ) {
				$map['vectors'] = true;
				return $map;
			}
		);
		$this->assertTrue( Restrictions\allows( 'vectors' ), 'The filter over the option.' );
		add_filter( 'evcert_allow_vectors', '__return_false' );
		$this->assertFalse( Restrictions\allows( 'vectors' ), 'The last word is the one feature filter.' );
	}

	/**
	 * Fonts and pictures travel in a ZIP: without it they are off too.
	 */
	public function test_fonts_and_pictures_need_the_zip() {
		$this->assertTrue( Restrictions\allows( 'fonts' ) );
		$this->assertTrue( Restrictions\allows( 'pictures' ) );
		update_option( Restrictions\OPTION, array( 'zip' => false ) );
		$this->assertFalse( Restrictions\allows( 'zip' ) );
		$this->assertFalse( Restrictions\allows( 'fonts' ) );
		$this->assertFalse( Restrictions\allows( 'pictures' ) );
		$this->assertTrue( Restrictions\allows( 'vectors' ), 'A drawing is JSON: it needs no ZIP.' );
	}

	/**
	 * A template with a drawing and a picture, and an event with a drawn logo.
	 *
	 * @return array{template: int, event: int}
	 */
	private function things() {
		$design               = Designs\basic();
		$design['elements'][] = array(
			'id'    => 'drawing',
			'name'  => 'Seal',
			'type'  => 'svg',
			'x'     => 10,
			'y'     => 10,
			'w'     => 50,
			'h'     => 20,
			'vw'    => 100,
			'vh'    => 40,
			'color' => '$text',
			'paths' => array( array( 'd' => 'M0 0L100 0L100 40L0 40Z' ) ),
		);
		$design['elements'][] = array(
			'id'         => 'photo',
			'name'       => 'Photo',
			'type'       => 'image',
			'x'          => 70,
			'y'          => 10,
			'w'          => 50,
			'h'          => 50,
			'src'        => wp_get_upload_dir()['baseurl'] . '/x.png',
			'attachment' => 12,
		);
		$template             = (int) wp_insert_post(
			array(
				'post_type'    => Designs\POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Restricted',
				'post_content' => wp_slash( (string) wp_json_encode( $design ) ),
			)
		);
		$event                = (int) Events\save(
			array(
				'title'         => 'Restricted event',
				'start'         => '2026-10-31 09:00',
				'end'           => '2026-10-31 17:00',
				'logo_svg'      => self::DRAWING,
				'signature_svg' => self::DRAWING,
			)
		);

		return array(
			'template' => $template,
			'event'    => $event,
		);
	}

	/**
	 * Elements of a template by id.
	 *
	 * @param array<string, mixed> $design Design.
	 * @return array<string, array<string, mixed>>
	 */
	private function by_id( array $design ) {
		return array_column( $design['elements'], null, 'id' );
	}

	/**
	 * With vectors and pictures allowed the export carries them; without, the elements stay with no paths and no picture, and the event
	 * has no drawing: the empty places to fill by hand.
	 */
	public function test_a_restricted_export_has_no_drawings_and_no_pictures() {
		$this->things();
		$open = Bundle\export( array( 'templates', 'events' ) );
		$mine = $this->by_id( array_values( array_filter( $open['templates'], static fn ( $t ) => 'Restricted' === $t['title'] ) )[0]['design'] );
		$this->assertNotEmpty( $mine['drawing']['paths'] );
		$this->assertNotSame( '', $mine['photo']['src'] );
		$event = array_values( array_filter( $open['events'], static fn ( $e ) => 'Restricted event' === $e['title'] ) )[0];
		$this->assertNotSame( '', $event['logo_svg'] );

		add_filter( 'evcert_allow_vectors', '__return_false' );
		add_filter( 'evcert_allow_pictures', '__return_false' );
		$shut = Bundle\export( array( 'templates', 'events' ) );
		$mine = $this->by_id( array_values( array_filter( $shut['templates'], static fn ( $t ) => 'Restricted' === $t['title'] ) )[0]['design'] );
		$this->assertSame( array(), $mine['drawing']['paths'] );
		$this->assertSame( 'Seal', $mine['drawing']['name'], 'The place stays, with its name.' );
		$this->assertSame( 50, $mine['drawing']['w'] );
		$this->assertSame( '', $mine['photo']['src'] );
		$this->assertArrayNotHasKey( 'attachment', $mine['photo'] );
		$event = array_values( array_filter( $shut['events'], static fn ( $e ) => 'Restricted event' === $e['title'] ) )[0];
		$this->assertSame( '', $event['logo_svg'] );
		$this->assertSame( '', $event['signature_svg'] );
	}

	/**
	 * A restricted import leaves the drawings and pictures of a file out, says so, and does not clear the site's own signature.
	 */
	public function test_a_restricted_import_leaves_them_out_and_says_so() {
		$things                          = $this->things();
		$bundle                          = json_decode( (string) wp_json_encode( Bundle\export( array( 'templates', 'events', 'settings' ) ) ), true );
		$bundle['settings']['signature'] = json_decode( self::DRAWING, true );
		wp_delete_post( $things['template'], true );
		wp_delete_post( $things['event'], true );
		update_option( \EventsCertificate\Signer\SIGNATURE_OPTION, json_decode( self::DRAWING, true ), false );

		add_filter( 'evcert_allow_vectors', '__return_false' );
		add_filter( 'evcert_allow_pictures', '__return_false' );
		$result = Bundle\import( $bundle, array( 'templates', 'events', 'settings' ) );

		$this->assertNotEmpty( array_filter( $result['conflicts'], static fn ( $m ) => false !== strpos( $m, 'left out of the import' ) ) );
		$made     = get_posts(
			array(
				'post_type'   => Designs\POST_TYPE,
				's'           => 'Restricted',
				'post_status' => 'publish',
				'numberposts' => 1,
			)
		)[0];
		$elements = $this->by_id( Designs\decode( $made->post_content ) );
		$this->assertSame( array(), $elements['drawing']['paths'] );
		$this->assertSame( '', $elements['photo']['src'] );
		$event = array_values( array_filter( array_map( static fn ( $e ) => Events\get( (int) $e->ID ), Events\all() ), static fn ( $e ) => 'Restricted event' === $e['title'] ) )[0];
		$this->assertSame( '', $event['logo_svg'] );
		$this->assertNotEmpty( get_option( \EventsCertificate\Signer\SIGNATURE_OPTION )['paths'], 'The site keeps its own signature.' );
	}

	/**
	 * A ZIP is refused where ZIP packages are off.
	 */
	public function test_a_zip_is_refused_when_zip_is_off() {
		if ( ! class_exists( '\ZipArchive' ) ) {
			$this->markTestSkipped( 'This PHP has no ZipArchive.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		add_filter( 'evcert_bundle_max_bytes', static fn () => 5 * MB_IN_BYTES ); // Whatever the server accepts for uploads.
		$path = wp_tempnam( 'evcert-test.zip' );
		$zip  = new \ZipArchive();
		$zip->open( $path, \ZipArchive::OVERWRITE );
		$zip->addFromString( Bundle\ZIP_JSON, (string) wp_json_encode( array( '$schema' => Bundle\SCHEMA ) ) );
		$zip->close();

		$this->assertIsArray( Bundle\read( $path, sys_get_temp_dir() . '/evcert-restrictions-test' ) );
		add_filter( 'evcert_allow_zip', '__return_false' );
		$read = Bundle\read( $path, sys_get_temp_dir() . '/evcert-restrictions-test' );
		wp_delete_file( $path );
		$this->assertInstanceOf( \WP_Error::class, $read );
		$this->assertSame( 'evcert_bundle_zip', $read->get_error_code() );
	}

	/**
	 * The buttons of a list are the ones that are allowed.
	 */
	public function test_the_buttons_follow_the_restrictions() {
		// Export and import are off by default with CampTix or GatherPress: these tests are about the buttons.
		update_option(
			Restrictions\OPTION,
			array(
				'export' => true,
				'import' => true,
			)
		);
		$both = $this->buttons();
		$this->assertStringContainsString( 'evcert-export', $both );
		$this->assertStringContainsString( 'evcert-import', $both );

		add_filter( 'evcert_allow_export', '__return_false' );
		$import_only = $this->buttons();
		$this->assertStringNotContainsString( 'evcert-export', $import_only );
		$this->assertStringContainsString( 'evcert-import', $import_only );

		add_filter( 'evcert_allow_import', '__return_false' );
		$this->assertSame( '', trim( $this->buttons() ) );
	}

	/**
	 * What the list's buttons print.
	 *
	 * @return string
	 */
	private function buttons() {
		ob_start();
		\EventsCertificate\BundleAdmin\render_buttons( 'templates' );

		return (string) ob_get_clean();
	}
}
