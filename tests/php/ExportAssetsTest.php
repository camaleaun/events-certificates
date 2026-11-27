<?php
/**
 * Export and import of the templates with the fonts and pictures they use (docs/decisions/0043): a ZIP with the JSON and the folders
 * fonts and images.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Bundle;
use EventsCertificate\Designs;
use EventsCertificate\Fonts;

/**
 * Fonts and pictures that travel with the templates.
 */
class ExportAssetsTest extends Test_Case {

	/**
	 * Files a test made (fonts in the fonts folder, pictures in uploads, ZIPs and folders in the temporary folder).
	 *
	 * @var string[]
	 */
	private $files = array();

	/**
	 * Attachments a test made.
	 *
	 * @var int[]
	 */
	private $attachments = array();

	/**
	 * A font file to install: the plugin's own.
	 *
	 * @return string
	 */
	private function ttf() {
		return dirname( __DIR__, 2 ) . '/fonts/CertSans-Regular.ttf';
	}

	/**
	 * Starts as an administrator, on a site with no installed font, and skips without ZipArchive.
	 */
	protected function set_up() {
		parent::set_up();
		// These tests are about packages: they are allowed here, as on a single site (a network leaves them to the super administrator).
		add_filter( 'evcert_restrictions', array( self::class, 'allow_everything' ) );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		add_filter( 'evcert_bundle_max_bytes', static fn () => 50 * MB_IN_BYTES ); // Whatever the server accepts for uploads.
		if ( ! class_exists( '\ZipArchive' ) ) {
			$this->markTestSkipped( 'This PHP has no ZipArchive.' );
		}
		wp_set_current_user(
			(int) get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
					'fields' => 'ID',
				)
			)[0]
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$this->empty_font_library();
		// Only the templates of the test: the site this runs on may have others with pictures of its own (rolled back with the transaction).
		foreach ( get_posts(
			array(
				'post_type'   => Designs\POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
			)
		) as $post ) {
			wp_delete_post( $post->ID, true );
		}
		Fonts\flush();
	}

	/**
	 * Takes away what the test left on the disk.
	 */
	protected function tear_down() {
		remove_filter( 'evcert_restrictions', array( self::class, 'allow_everything' ) );
		foreach ( $this->attachments as $id ) {
			wp_delete_attachment( $id, true );
		}
		foreach ( (array) glob( wp_get_font_dir()['path'] . '/Demo*' ) as $file ) {
			wp_delete_file( $file );
		}
		foreach ( $this->files as $file ) {
			is_dir( $file ) ? Bundle\remove_dir( $file ) : wp_delete_file( $file );
		}
		Fonts\flush();
		remove_all_filters( 'evcert_bundle_max_bytes' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * A picture in this site's Media Library.
	 *
	 * @return array{id: int, url: string, file: string}
	 */
	private function picture() {
		$png    = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- a one-pixel test picture.
		$upload = wp_upload_bits( 'evcert-test-' . wp_generate_password( 6, false ) . '.png', null, $png );
		$id     = (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'Test logo',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		update_post_meta( $id, '_wp_attachment_image_alt', 'A test logo' );
		$this->attachments[] = $id;
		$this->files[]       = $upload['file'];

		return array(
			'id'   => $id,
			'url'  => $upload['url'],
			'file' => $upload['file'],
		);
	}

	/**
	 * A template that uses an installed font and a Media Library picture, and the ones it should not take along.
	 *
	 * @param array{id: int, url: string, file: string} $picture The picture.
	 * @return int Template ID.
	 */
	private function template( array $picture ) {
		$design               = Designs\basic();
		$design['elements'][] = array(
			'id'     => 'demo-text',
			'type'   => 'text',
			'x'      => 10,
			'y'      => 10,
			'w'      => 200,
			'h'      => 20,
			'runs'   => array( array( 'text' => 'Hello' ) ),
			'font'   => 'Demo Sans',
			'weight' => 400,
			'size'   => 12,
			'color'  => '$text',
		);
		$design['elements'][] = array(
			'id'         => 'demo-logo',
			'type'       => 'image',
			'x'          => 10,
			'y'          => 40,
			'w'          => 50,
			'h'          => 50,
			'src'        => $picture['url'],
			'attachment' => $picture['id'],
			'fit'        => 'contain',
		);
		$design['elements'][] = array(
			'id'   => 'inline',
			'type' => 'image',
			'x'    => 0,
			'y'    => 0,
			'w'    => 10,
			'h'    => 10,
			'src'  => 'data:image/png;base64,AAAA',
		);
		$design['elements'][] = array(
			'id'   => 'remote',
			'type' => 'image',
			'x'    => 0,
			'y'    => 0,
			'w'    => 10,
			'h'    => 10,
			'src'  => 'https://example.org/remote.png',
		);

		return (int) wp_insert_post(
			array(
				'post_type'    => Designs\POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'With assets',
				'post_content' => wp_slash( (string) wp_json_encode( $design ) ),
			)
		);
	}

	/**
	 * Installs the test font.
	 */
	private function install_font() {
		$this->assertSame( 'installed', Fonts\install_face( 'Demo Sans', 'Demo Sans', 'sans-serif', '400', $this->ttf() ) );
	}

	/**
	 * What the templates use that can travel: the installed font and the Media Library picture; not the plugin's fonts, an inline picture
	 * or one on another site.
	 */
	public function test_it_finds_the_fonts_and_pictures_the_templates_use() {
		$this->install_font();
		$picture = $this->picture();
		$id      = $this->template( $picture );

		$found = Bundle\assets_of( array( array( 'design' => Designs\get( $id ) ) ) );
		$this->assertSame( array( 'Demo Sans' ), array_column( $found['fonts'], 'family' ) );
		$this->assertSame( '400', $found['fonts'][0]['weight'] );
		$this->assertSame( array( $picture['url'] ), array_column( $found['images'], 'url' ) );
		$this->assertSame( 'A test logo', $found['images'][0]['alt'] );

		$summary = Bundle\template_assets();
		$this->assertContains( 'Demo Sans', $summary['families'] );
		$this->assertGreaterThanOrEqual( 1, $summary['images'] );
	}

	/**
	 * Without the files asked for, the bundle is the JSON it always was; with them it lists them and the ZIP has them.
	 */
	public function test_the_zip_has_the_json_and_the_folders() {
		$this->install_font();
		$picture = $this->picture();
		$this->template( $picture );

		$plain = Bundle\export( array( 'templates' ) );
		$this->assertArrayNotHasKey( 'assets', $plain );

		$files  = array();
		$bundle = Bundle\export( array( 'templates' ), 0, $files );
		$this->assertCount( 1, $bundle['assets']['fonts'] );
		$this->assertCount( 1, $bundle['assets']['images'] );
		$this->assertSame( 'fonts/Demo-Sans-400.ttf', $bundle['assets']['fonts'][0]['file'] );
		$this->assertSame( hash_file( 'sha256', $this->ttf() ), $bundle['assets']['fonts'][0]['sha256'] );
		$this->assertSame( array( 'fonts/Demo-Sans-400.ttf', 'images/' . basename( $picture['file'] ) ), array_keys( $files ) );

		$zip           = Bundle\make_zip( $bundle, $files );
		$this->files[] = $zip;
		$this->assertIsString( $zip );
		$archive = new \ZipArchive();
		$this->assertTrue( $archive->open( $zip ) );
		$this->assertNotFalse( $archive->locateName( 'events-certificates.json' ) );
		$this->assertNotFalse( $archive->locateName( 'fonts/Demo-Sans-400.ttf' ) );
		$this->assertSame( Bundle\SCHEMA, json_decode( (string) $archive->getFromName( 'events-certificates.json' ), true )['$schema'] );
		$archive->close();
	}

	/**
	 * On another site (no font, no picture, no template) the import installs the font in the Font Library, makes the picture in the Media
	 * Library and points the template to it; a second import changes nothing.
	 */
	public function test_importing_the_zip_installs_the_fonts_and_the_pictures() {
		$this->install_font();
		$picture = $this->picture();
		$id      = $this->template( $picture );
		$files   = array();
		$bundle  = Bundle\export( array( 'templates' ), 0, $files );
		$zip     = Bundle\make_zip( $bundle, $files );
		$dir     = sys_get_temp_dir() . '/evcert-test-assets-' . wp_generate_password( 6, false );

		// Another site: nothing of it is here.
		wp_delete_post( $id, true );
		$this->empty_font_library();
		wp_delete_attachment( $picture['id'], true );
		foreach ( (array) glob( wp_get_font_dir()['path'] . '/Demo*' ) as $file ) {
			wp_delete_file( $file );
		}
		Fonts\flush();
		$this->assertNotContains( 'Demo Sans', array_column( Fonts\choices(), 'value' ) );

		$read          = Bundle\read( $zip, $dir );
		$this->files[] = $zip;
		$this->files[] = $dir;
		$this->assertIsArray( $read );
		$this->assertCount( 1, $read['assets']['fonts'] );
		$this->assertCount( 1, $read['assets']['images'] );

		$first = Bundle\import( $read, array( 'templates' ) );
		$this->assertSame( 1, $first['assets']['fonts'] );
		$this->assertSame( 1, $first['assets']['images'] );
		$this->assertSame( 1, $first['templates']['added'] );

		$families = array_column( Fonts\choices(), null, 'value' );
		$this->assertArrayHasKey( 'Demo Sans', $families );
		$this->assertTrue( $families['Demo Sans']['pdf'], 'Installed as TrueType: the PDF can read it.' );

		$made = get_posts(
			array(
				'post_type'   => Designs\POST_TYPE,
				'post_status' => 'publish',
				's'           => 'With assets',
				'numberposts' => 1,
			)
		)[0];
		$logo = array_values( array_filter( Designs\decode( $made->post_content )['elements'], static fn ( $e ) => 'demo-logo' === $e['id'] ) )[0];
		$this->assertNotSame( $picture['id'], $logo['attachment'], 'The picture is the one made here.' );
		$this->assertSame( wp_get_attachment_url( $logo['attachment'] ), $logo['src'] );
		$this->assertSame( 'A test logo', get_post_meta( $logo['attachment'], '_wp_attachment_image_alt', true ) );
		$this->attachments[] = (int) $logo['attachment'];
		$this->files[]       = (string) get_attached_file( $logo['attachment'] );

		$again = Bundle\import( $read, array( 'templates' ) );
		$this->assertSame( 0, $again['assets']['fonts'] );
		$this->assertSame( 0, $again['assets']['images'] );
		$this->assertSame( 2, $again['assets']['skipped'], 'The font face and the picture were already here.' );
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a test looks at what the import left.
		$made = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'meta_key'    => '_evcert_asset_sha256',
				'numberposts' => -1,
			)
		);
		// phpcs:enable
		$this->assertCount( 1, $made, 'One picture, not two.' );
	}

	/**
	 * The pictures of an event, of a certificate and of the signature of Settings are files in the ZIP and `picture:<sha256>` in the JSON,
	 * never a data URI; the import makes them attachments and the places point at them.
	 */
	public function test_the_pictures_of_events_certificates_and_settings_travel_as_files() {
		wp_set_current_user( 1 );
		$logo      = $this->make_picture( 'evcert-logo.png' );
		$signature = $this->make_picture( 'evcert-sign.png', 3 );
		update_option( \EventsCertificate\Signer\SIGNATURE_ATTACHMENT_OPTION, (int) $signature, false );
		$event = Fixtures::event(
			array(
				'title'           => 'Pictures meetup',
				'logo'            => $logo,
				'signature_image' => $signature,
			)
		);
		$ana   = Fixtures::attendee_of_event( $event, 'Ana Lima', 'ana@example.test' );
		$cert  = \EventsCertificate\People\issue( $ana );
		$files = array();

		$bundle = Bundle\export( array( 'certificates', 'settings' ), $event, $files );
		$json   = (string) wp_json_encode( $bundle );
		$this->assertStringNotContainsString( 'data:image', $json );
		$this->assertGreaterThanOrEqual( 2, preg_match_all( '/picture:[a-f0-9]{64}/', $json ) );
		$this->assertCount( 2, $bundle['assets']['images'], 'Two different files, however many places use them.' );
		$this->assertCount( 2, $files );
		$this->assertMatchesRegularExpression( '/^picture:[a-f0-9]{64}$/', $bundle['events'][0]['logo'] );
		$this->assertSame( $bundle['events'][0]['logo'], $bundle['certificates'][0]['data']['logo'] );
		$this->assertSame( $bundle['events'][0]['signature_image'], $bundle['settings']['signature_image'] );

		$zip           = Bundle\make_zip( $bundle, $files );
		$dir           = sys_get_temp_dir() . '/evcert-test-pictures-' . wp_generate_password( 6, false );
		$this->files[] = $zip;
		$this->files[] = $dir;

		// Another site: no event, no certificate, no attachments, no signature.
		global $wpdb;
		$wpdb->delete( \EventsCertificate\Certificates\table(), array( 'code' => $cert['code'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		\EventsCertificate\People\remove( array( (int) $ana['id'] ), $event );
		wp_delete_post( $event, true );
		wp_delete_attachment( (int) $logo, true );
		wp_delete_attachment( (int) $signature, true );
		delete_option( \EventsCertificate\Signer\SIGNATURE_ATTACHMENT_OPTION );

		$read = Bundle\read( $zip, $dir );
		$this->assertIsArray( $read );
		$summary = Bundle\import( $read, array( 'certificates', 'settings' ) );
		$this->assertSame( 2, $summary['assets']['images'] );

		$made = \EventsCertificate\Events\all();
		$this->assertCount( 1, array_filter( $made, static fn ( $post ) => 'Pictures meetup' === $post->post_title ) );
		$back = null;
		foreach ( $made as $post ) {
			if ( 'Pictures meetup' === $post->post_title ) {
				$back = \EventsCertificate\Events\get( (int) $post->ID );
			}
		}
		$this->assertNotSame( '', \EventsCertificate\Pictures\clean( $back['logo'] ), 'The logo is an attachment of this site again.' );
		$this->assertNotSame( $logo, $back['logo'] );
		$this->assertNotSame( '', \EventsCertificate\Pictures\clean( $back['signature_image'] ) );
		$this->assertSame( (int) $back['signature_image'], (int) get_option( \EventsCertificate\Signer\SIGNATURE_ATTACHMENT_OPTION ), 'The settings use the same file as the event.' );
		$row = \EventsCertificate\Certificates\get_by_code( $cert['code'] );
		$this->assertSame( $back['logo'], (string) json_decode( (string) $row['data_json'], true )['logo'], 'The certificate points at the new logo.' );
		foreach ( array( $back['logo'], $back['signature_image'] ) as $new_id ) {
			$this->attachments[] = (int) $new_id;
			$this->files[]       = (string) get_attached_file( (int) $new_id );
		}
	}

	/**
	 * Without a ZIP (a plain JSON, or pictures not allowed) the places stay empty and nothing is written as a data URI; an old file that has
	 * a data URI or an ID of another site gets no picture from it.
	 */
	public function test_a_plain_json_and_an_old_file_bring_no_pictures() {
		wp_set_current_user( 1 );
		$logo  = $this->make_picture();
		$event = Fixtures::event(
			array(
				'title' => 'Plain meetup',
				'logo'  => $logo,
			)
		);
		$plain = Bundle\export( array( 'events' ), $event );
		$this->assertSame( '', $plain['events'][0]['logo'] );

		$old                                 = $plain;
		$old['events'][0]['title']           = 'Old file meetup';
		$old['events'][0]['logo']            = 'data:image/png;base64,iVBORw0KGgo=';
		$old['events'][0]['signature_image'] = $logo;
		$bundle                              = json_decode( (string) wp_json_encode( $old ), true );
		Bundle\import( $bundle, array( 'events' ) );
		foreach ( \EventsCertificate\Events\all() as $post ) {
			if ( 'Old file meetup' === $post->post_title ) {
				$got = \EventsCertificate\Events\get( (int) $post->ID );
				$this->assertSame( '', $got['logo'], 'A data URI is never kept.' );
				$this->assertSame( '', $got['signature_image'], 'An ID is of the site it came from: not kept.' );
			}
		}
	}

	/**
	 * Whoever may not edit the theme does not get fonts installed (the Font Library asks for that), and the pictures come anyway.
	 */
	public function test_fonts_need_the_permission_the_font_library_asks_for() {
		$this->install_font();
		$picture = $this->picture();
		$this->template( $picture );
		$files  = array();
		$bundle = Bundle\export( array( 'templates' ), 0, $files );
		$zip    = Bundle\make_zip( $bundle, $files );
		$dir    = sys_get_temp_dir() . '/evcert-test-assets-' . wp_generate_password( 6, false );

		$editor = (int) wp_insert_user(
			array(
				'user_login' => 'evcert-editor-' . wp_generate_password( 5, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => 'editor',
			)
		);
		wp_set_current_user( $editor );
		$read          = Bundle\read( $zip, $dir );
		$this->files[] = $zip;
		$this->files[] = $dir;
		$this->empty_font_library();
		Fonts\flush();

		$result = Bundle\import( $read, array( 'templates' ) );
		$this->assertSame( 0, $result['assets']['fonts'] );
		$this->assertSame( 1, $result['assets']['denied'], 'An editor cannot edit the theme.' );
		$this->assertNotContains( 'Demo Sans', array_column( Fonts\choices(), 'value' ) );
		$this->assertSame( 1, $result['assets']['images'] + $result['assets']['skipped'], 'An editor can upload files.' );
	}

	/**
	 * A ZIP is not trusted: names that climb out, other kinds of file, bytes that are not a font, a SHA-256 that does not match and an
	 * entry the JSON does not list are all left out, and nothing is written outside the folder.
	 */
	public function test_a_zip_is_not_trusted() {
		$good     = (string) file_get_contents( $this->ttf() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a font of the plugin.
		$sha      = hash( 'sha256', $good );
		$entry    = static function ( $file, $bytes ) {
			return array(
				'family' => 'Demo Evil',
				'label'  => 'Demo Evil',
				'weight' => '400',
				'file'   => $file,
				'size'   => strlen( $bytes ),
				'sha256' => hash( 'sha256', $bytes ),
			);
		};
		$assets   = array(
			'fonts'  => array(
				$entry( '../evil.ttf', $good ),
				$entry( 'fonts/not-a-font.ttf', 'this is plain text, not a font' ),
				array(
				'file'   => 'fonts/wrong-hash.ttf',
				'size'   => strlen( $good ),
				'sha256' => str_repeat( '0', 64 ),
					) + $entry( 'x', $good ), // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
				$entry( 'fonts/script.php', $good ),
				$entry( 'fonts/good.ttf', $good ),
			),
			'images' => array(
				$entry( 'images/drawing.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' ),
			),
		);
		$zip_path = wp_tempnam( 'evcert-test.zip' );
		$zip      = new \ZipArchive();
		$zip->open( $zip_path, \ZipArchive::OVERWRITE );
		$zip->addFromString(
			Bundle\ZIP_JSON,
			(string) wp_json_encode(
				array(
					'$schema'  => Bundle\SCHEMA,
					'sections' => array( 'templates' ),
					'assets'   => $assets,
				)
			)
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$zip->addFromString( 'fonts/not-a-font.ttf', 'this is plain text, not a font' );
		$zip->addFromString( 'fonts/wrong-hash.ttf', $good );
		$zip->addFromString( 'fonts/script.php', $good );
		$zip->addFromString( 'fonts/good.ttf', $good );
		$zip->addFromString( 'fonts/unlisted.ttf', $good );
		$zip->addFromString( 'images/drawing.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );
		$zip->close();
		$dir           = sys_get_temp_dir() . '/evcert-test-assets-' . wp_generate_password( 6, false );
		$this->files[] = $zip_path;
		$this->files[] = $dir;

		$read = Bundle\read( $zip_path, $dir );
		$this->assertIsArray( $read );
		$this->assertCount( 1, $read['assets']['fonts'], 'Only the good one.' );
		$this->assertSame( 'fonts/good.ttf', $read['assets']['fonts'][0]['file'] );
		$this->assertSame( array(), $read['assets']['images'], 'No SVG.' );
		$this->assertCount( 5, $read['skipped'] );
		$this->assertCount( 1, (array) glob( $dir . '/*' ), 'Only the good one was taken out.' );
		$this->assertFileDoesNotExist( dirname( $dir ) . '/evil.ttf' );

		$this->assertSame( 'evcert_bundle_format', Bundle\read( $this->not_a_bundle(), $dir )->get_error_code() );
	}

	/**
	 * A ZIP with no JSON of ours.
	 *
	 * @return string Path.
	 */
	private function not_a_bundle() {
		$path = wp_tempnam( 'evcert-test.zip' );
		$zip  = new \ZipArchive();
		$zip->open( $path, \ZipArchive::OVERWRITE );
		$zip->addFromString( 'other.json', '{}' );
		$zip->close();
		$this->files[] = $path;

		return $path;
	}

	/**
	 * Importing without the box ticked leaves the fonts and pictures out; a JSON with none is imported as it always was.
	 */
	public function test_the_fonts_and_pictures_can_be_left_out() {
		$this->install_font();
		$picture = $this->picture();
		$this->template( $picture );
		$files  = array();
		$bundle = Bundle\export( array( 'templates' ), 0, $files );

		$state = Bundle\begin( json_decode( (string) wp_json_encode( $bundle ), true ), array( 'templates' ), 'skip', false );
		$this->assertNotContains( 'assets', $state['stages'] );
		$with = Bundle\begin( json_decode( (string) wp_json_encode( $bundle ), true ), array( 'templates' ), 'skip', true );
		$this->assertSame( array( 'assets', 'templates' ), $with['stages'] );

		$plain = Bundle\begin( Bundle\export( array( 'templates' ) ), array( 'templates' ) );
		$this->assertSame( array( 'templates' ), $plain['stages'] );
	}

	/**
	 * Every feature allowed (the filter of these tests).
	 *
	 * @return array<string, bool>
	 */
	public static function allow_everything() {
		return array_fill_keys( \EventsCertificate\Restrictions\FEATURES, true );
	}
}
