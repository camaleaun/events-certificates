<?php
/**
 * Export and import: one JSON bundle of templates, events, participants, certificates and settings.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Bundle;
use EventsCertificate\BundleAdmin;
use EventsCertificate\Certificates;
use EventsCertificate\Documents;
use EventsCertificate\Events;
use EventsCertificate\People;

/**
 * The bundle keeps codes, remaps ids, is read again as untrusted input and changes nothing the second time.
 */
class ExportImportTest extends Test_Case {

	/**
	 * Verification on, so a correction replaces a certificate.
	 */
	protected function set_up() {
		parent::set_up();
		add_filter( 'evcert_verification_enabled', '__return_true' );
		wp_set_current_user( 1 );
	}

	/**
	 * Verification back to its default.
	 */
	protected function tear_down() {
		remove_filter( 'evcert_verification_enabled', '__return_true' );
		parent::tear_down();
	}

	/**
	 * An event with an attendee holding a corrected certificate, another who attended, and one in the Trash.
	 *
	 * @return array<string, mixed> event, codes (current and replaced), people.
	 */
	private function world() {
		$event = Fixtures::event(
			array(
				'title'     => 'A fictional meetup',
				'community' => 'A fictional community',
				'logo'      => $this->make_picture(),
			)
		);
		$ana   = Fixtures::attendee_of_event( $event, 'Ana Lima', 'ana@example.test' );
		$old   = People\issue( $ana );
		$new   = Certificates\reissue( $old, array( 'name' => 'Ana Lima Souza' ) );
		$bia   = Fixtures::attendee_of_event( $event, 'Bia Souza', 'bia@example.test' );
		$bia_c = People\issue( $bia );
		$caio  = Fixtures::attendee_of_event( $event, 'Caio Dias', 'caio@example.test' );
		People\trash( array( (int) $caio['id'] ), $event );

		return array(
			'event'  => $event,
			'old'    => $old['code'],
			'new'    => $new['code'],
			'bia'    => $bia_c['code'],
			'people' => array( (int) $ana['id'], (int) $bia['id'], (int) $caio['id'] ),
		);
	}

	/**
	 * Takes what the world made off the site, as on a clean one.
	 *
	 * @param array<string, mixed> $world The world.
	 */
	private function wipe( array $world ) {
		global $wpdb;

		foreach ( array( $world['old'], $world['new'], $world['bia'] ) as $code ) {
			$wpdb->delete( Certificates\table(), array( 'code' => $code ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		}
		People\remove( $world['people'], $world['event'] );
		wp_delete_post( $world['event'], true );
	}

	/**
	 * Participants need their events, and certificates their participants.
	 */
	public function test_sections_bring_what_they_need() {
		$this->assertSame( array( 'templates' ), Bundle\sections( array( 'templates', 'nonsense' ) ) );
		$this->assertSame( array( 'events', 'people' ), Bundle\sections( array( 'people' ) ) );
		$this->assertSame( array( 'events', 'people', 'certificates' ), Bundle\sections( array( 'certificates' ) ) );
	}

	/**
	 * A decimal column gives "8.00" back where a bundle says 8: the same hours, the same fingerprint (a site on MySQL read them as different).
	 */
	public function test_the_fingerprint_reads_the_hours_as_one_number() {
		$row = array(
			'code' => 'X',
			'name' => 'Ana',
		);

		$this->assertSame( Certificates\fingerprint( $row + array( 'hours' => '8.00' ) ), Certificates\fingerprint( $row + array( 'hours' => 8.0 ) ) );
		$this->assertSame( Certificates\fingerprint( $row + array( 'hours' => '8.50' ) ), Certificates\fingerprint( $row + array( 'hours' => 8.5 ) ) );
		foreach ( array( '8', '8.0', '8.00', 8, 8.0 ) as $eight ) {
			$this->assertSame( '8', Certificates\hours_text( $eight ) );
		}
		$this->assertSame( '8.5', Certificates\hours_text( '8.50' ) );
		$this->assertSame( '10', Certificates\hours_text( '10.00' ), 'The zeros of 10 stay.' );
		$this->assertSame( '', Certificates\hours_text( '' ) );
		$this->assertNotSame( Certificates\fingerprint( $row + array( 'hours' => 8 ) ), Certificates\fingerprint( $row + array( 'hours' => 9 ) ) );
		$this->assertSame( Certificates\fingerprint( $row ), Certificates\fingerprint( $row + array( 'hours' => '' ) ) );
	}

	/**
	 * What is exported can be imported on a clean site, keeps the codes and the chain, and a second import changes nothing.
	 */
	public function test_a_round_trip_keeps_codes_and_importing_twice_changes_nothing() {
		$world  = $this->world();
		$bundle = Bundle\export( array( 'certificates' ), $world['event'] );
		$this->assertSame( Bundle\SCHEMA, $bundle['$schema'] );
		$this->assertCount( 1, $bundle['events'] );
		$this->assertCount( 3, $bundle['people'] );
		$this->assertCount( 3, $bundle['certificates'] );
		$this->assertArrayNotHasKey( 'settings', $bundle );
		$bundle = json_decode( (string) wp_json_encode( $bundle ), true ); // As the file is read back.

		$this->wipe( $world );
		$this->assertNull( Certificates\get_by_code( $world['new'], true ) );

		$first = Bundle\import( $bundle, array( 'certificates' ) );
		$this->assertSame( 1, $first['events']['added'] );
		$this->assertSame( 3, $first['people']['added'] );
		$this->assertSame( 3, $first['certificates']['added'] );
		$this->assertSame( array(), $first['conflicts'] );

		$new = Certificates\get_by_code( $world['new'] );
		$old = Certificates\get_by_code( $world['old'] );
		$this->assertSame( 'issued', $new['status'] );
		$this->assertSame( 'replaced', $old['status'] );
		$this->assertSame( (int) $new['id'], (int) $old['replaced_by'], 'The chain is remapped.' );
		$this->assertSame( 'Ana Lima Souza', $new['name'] );
		$this->assertNotSame( '', $new['fingerprint'] );

		$event = get_post( (int) $new['event_id'] );
		$this->assertSame( 'A fictional meetup', Events\get( (int) $event->ID )['title'] );
		$this->assertSame( '', Events\get( (int) $event->ID )['logo'], 'A plain JSON has no pictures: they travel in a ZIP.' );
		$this->assertSame( (int) $event->ID, (int) $new['event_id'] );
		$trash = People\query( (int) $event->ID, array( 'trash' => true ) )['rows'];
		$this->assertCount( 1, $trash );
		$this->assertSame( 'Caio Dias', $trash[0]['name'] );
		$ana = People\query( (int) $event->ID, array( 'search' => 'Ana' ) )['rows'][0];
		$this->assertSame( $world['new'], People\certificate( $ana, 'attendee' )['code'], 'The role points at the current certificate.' );

		$second = Bundle\import( $bundle, array( 'certificates' ) );
		$this->assertSame( 0, $second['events']['added'] + $second['people']['added'] + $second['certificates']['added'] );
		$this->assertSame( 3, $second['certificates']['skipped'] );
		$this->assertSame( array(), $second['conflicts'] );
		$this->assertCount( 3, Certificates\query( array( 'event_id' => (int) $event->ID, 'per_page' => 50 ) )['rows'], 'Nothing was made twice.' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	}

	/**
	 * A certificate of an event that is no longer there still travels: it says what it needs by itself.
	 */
	public function test_a_certificate_of_a_deleted_event_travels() {
		global $wpdb;

		$world = $this->world();
		wp_delete_post( $world['event'], true );
		$bundle = json_decode( (string) wp_json_encode( Bundle\export( array( 'certificates' ) ) ), true );
		$codes  = wp_list_pluck( $bundle['certificates'], 'code' );
		$this->assertContains( $world['bia'], $codes );

		$wpdb->delete( Certificates\table(), array( 'code' => $world['bia'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		$result = Bundle\import( $bundle, array( 'certificates' ) );
		$this->assertGreaterThanOrEqual( 1, $result['certificates']['added'] );
		$back = Certificates\get_by_code( $world['bia'] );
		$this->assertSame( 'Bia Souza', $back['name'] );
		$this->assertSame( 0, (int) $back['event_id'] );
	}

	/**
	 * A code that is already here with other content is reported and left as it is.
	 */
	public function test_a_code_with_other_content_is_a_conflict() {
		$world  = $this->world();
		$bundle = json_decode( (string) wp_json_encode( Bundle\export( array( 'certificates' ), $world['event'] ) ), true );
		foreach ( $bundle['certificates'] as $i => $item ) {
			if ( $item['code'] === $world['bia'] ) {
				$bundle['certificates'][ $i ]['name'] = 'Someone Else';
			}
		}
		$result = Bundle\import( $bundle, array( 'certificates' ) );
		$this->assertCount( 1, $result['conflicts'] );
		$this->assertStringContainsString( $world['bia'], $result['conflicts'][0] );
		$this->assertSame( 'Bia Souza', Certificates\get_by_code( $world['bia'] )['name'], 'Never written over.' );
	}

	/**
	 * The file is not trusted: odd statuses and codes are dropped, a data with scripts and bad pictures is cleaned, a design
	 * snapshot that is not the one it names is not kept.
	 */
	public function test_a_hostile_file_is_cleaned() {
		$event  = Fixtures::event();
		$good   = \EventsCertificate\Code\generate();
		$bundle = array(
			'$schema'      => Bundle\SCHEMA,
			'sections'     => array( 'events', 'people', 'certificates' ),
			'events'       => array( array( 'key' => 7 ) + Events\get( $event ) ),
			'people'       => array(),
			'snapshots'    => array( str_repeat( 'a', 64 ) => array( 'elements' => array( 'x' ) ) ),
			'certificates' => array(
				array(
					'context'     => 'event',
					'event'       => 7,
					'code'        => $good,
					'status'      => 'issued',
					'name'        => '<script>alert(1)</script>Eve',
					'role'        => 'attendee',
					'issued_at'   => '2026-11-25 20:00:00',
					'source'      => 'manual',
					'design_hash' => str_repeat( 'a', 64 ),
					'data'        => array(
						'location'        => '<img src=x onerror=alert(1)>Town',
						'logo'            => 'data:image/svg+xml;base64,PHN2Zy8+',
						'signature_image' => 'https://example.com/s.png',
						'signature_svg'   => '{"vw":10,"vh":10,"paths":[{"d":"<script>"}]}',
						'deep'            => array( 'a' => array( 'b' => array( 'c' => array( 'd' => array( 'e' => array( 'f' => 'too deep' ) ) ) ) ) ),
					),
				),
				array(
					'context' => 'event',
					'event'   => 7,
					'code'    => 'not a code',
					'status'  => 'issued',
					'name'    => 'Nobody',
				),
				array(
					'context' => 'event',
					'event'   => 7,
					'code'    => \EventsCertificate\Code\generate(),
					'status'  => 'draft',
					'name'    => 'Draft',
				),
			),
		);
		$result = Bundle\import( $bundle, array( 'certificates' ) );
		$this->assertSame( 1, $result['certificates']['added'] );
		$this->assertSame( 2, $result['certificates']['skipped'], 'A bad code and a status that does not travel.' );

		$cert = Certificates\get_by_code( $good );
		$this->assertStringNotContainsString( '<script', $cert['name'] );
		$this->assertSame( '', $cert['design_hash'], 'A snapshot that is not the one it names is not kept.' );
		$data = json_decode( $cert['data_json'], true );
		$this->assertStringNotContainsString( '<img', $data['location'] );
		$this->assertSame( '', $data['logo'] );
		$this->assertSame( '', $data['signature_image'] );
		$this->assertSame( '', $data['signature_svg'] );
		$this->assertStringNotContainsString( 'too deep', (string) wp_json_encode( $data ) );
	}

	/**
	 * A file that is not a bundle, or is too big, is refused.
	 */
	public function test_files_that_are_not_bundles_are_refused() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$file = wp_tempnam( 'bundle.json' );
		file_put_contents( $file, '{"hello":"world"}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a temporary file.
		$this->assertSame( 'evcert_bundle_format', Bundle\read( $file )->get_error_code() );
		file_put_contents( $file, 'not json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a temporary file.
		$this->assertSame( 'evcert_bundle_format', Bundle\read( $file )->get_error_code() );
		file_put_contents( $file, '{"$schema":"' . Bundle\SCHEMA . '"}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a temporary file.
		$this->assertIsArray( Bundle\read( $file ) );
		add_filter(
			'evcert_bundle_max_bytes',
			static function () {
				return 5;
			}
		);
		$this->assertSame( 'evcert_bundle_size', Bundle\read( $file )->get_error_code() );
		wp_delete_file( $file );
	}

	/**
	 * The settings travel with the secret key, without the address of the check page; the key is only taken by a site with no
	 * certificate checked by its own.
	 */
	public function test_settings_carry_the_secret_key_and_import_follows_its_rule() {
		update_option( Documents\PEPPER_OPTION, 'the-secret-of-this-site', false );
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE %i SET doc_hash = ''", Certificates\table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table; inside the test's transaction.
		$bundle = Bundle\export( array( 'settings' ) );
		$this->assertSame( 'the-secret-of-this-site', $bundle['settings']['pepper'] );
		$this->assertArrayNotHasKey( 'slug', $bundle['settings']['values'] );

		// The settings option itself is not written by this test.
		add_filter(
			'pre_update_option_evcert_settings',
			static function ( $value, $old ) {
				return $old;
			},
			10,
			2
		);
		$bundle['settings']['pepper'] = 'the-secret-of-the-other-site';
		$result                       = Bundle\import( $bundle, array( 'settings' ) );
		$this->assertSame( 'the-secret-of-the-other-site', get_option( Documents\PEPPER_OPTION ), 'No certificate here is checked by the old one: the file\'s key is taken.' );
		$this->assertSame( array(), $result['conflicts'] );

		// A certificate with a document hash: the site keeps its own key and says so.
		$cert = Fixtures::attendee_of_event( Fixtures::event(), 'Dora Reis', 'dora@example.test' );
		$made = People\issue( $cert );
		$wpdb->update( Certificates\table(), array( 'doc_hash' => '$argon2id$v=19$m=65536,t=4,p=1$abc$def' ), array( 'id' => $made['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		$bundle['settings']['pepper'] = 'a-third-secret';
		$result                       = Bundle\import( $bundle, array( 'settings' ) );
		$this->assertSame( 'the-secret-of-the-other-site', get_option( Documents\PEPPER_OPTION ) );
		$this->assertCount( 1, $result['conflicts'] );
	}

	/**
	 * Templates are matched by name: a copy already there is not made again; one that is new is made, for its roles.
	 */
	public function test_templates_are_matched_by_name() {
		$bundle = Bundle\export( array( 'templates' ) );
		$this->assertNotEmpty( $bundle['templates'] );
		$again = Bundle\import( json_decode( (string) wp_json_encode( $bundle ), true ), array( 'templates' ) );
		$this->assertSame( 0, $again['templates']['added'] );

		$bundle['templates'][0]['title'] = 'A template from another site';
		$bundle['templates'][0]['roles'] = array( 'speaker', 'nonsense' );
		$bundle['templates']             = array( $bundle['templates'][0] );
		$bundle['sections']              = array( 'templates' );
		$made                            = Bundle\import( $bundle, array( 'templates' ) );
		$this->assertSame( 1, $made['templates']['added'] );
		$post = get_posts(
			array(
				'post_type'   => \EventsCertificate\Designs\POST_TYPE,
				'title'       => 'A template from another site',
				'numberposts' => 1,
			)
		)[0];
		$this->assertSame( array( 'speaker' ), \EventsCertificate\Designs\roles( (int) $post->ID ) );
		$this->assertNotNull( \EventsCertificate\Designs\get( (int) $post->ID )['elements'] );
	}

	/**
	 * The buttons open the Export and the Import screens with their section ticked, and only for who can manage.
	 */
	public function test_the_buttons_and_the_address() {
		ob_start();
		BundleAdmin\render_buttons( 'certificates' );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'page=evcert-export', $html );
		$this->assertStringContainsString( 'page=evcert-import', $html );
		$this->assertStringContainsString( 'section=certificates', $html );

		wp_set_current_user( 0 );
		ob_start();
		BundleAdmin\render_buttons( 'certificates' );
		$this->assertSame( '', (string) ob_get_clean() );
	}

	/**
	 * The import goes in small steps whose state survives being written and read between requests, and the progress only grows.
	 */
	public function test_the_import_goes_in_steps() {
		$world  = $this->world();
		$bundle = json_decode( (string) wp_json_encode( Bundle\export( array( 'certificates' ), $world['event'] ) ), true );
		$this->wipe( $world );

		$state    = Bundle\begin( $bundle, array( 'certificates' ) );
		$steps    = 0;
		$progress = array();
		do {
			$state      = json_decode( (string) wp_json_encode( $state ), true ); // As the screen keeps it between requests.
			$finished   = Bundle\step( $state, $bundle );
			$progress[] = Bundle\progress( $state );
			++$steps;
		} while ( ! $finished && $steps < 50 );

		$this->assertTrue( $finished );
		$this->assertGreaterThan( 1, $steps, 'More than one request.' );
		$this->assertSame( 100, end( $progress ) );
		$sorted = $progress;
		sort( $sorted );
		$this->assertSame( $sorted, $progress, 'The progress only grows.' );
		$this->assertSame( 3, $state['summary']['certificates']['added'] );
		$this->assertNotNull( Certificates\get_by_code( $world['new'] ) );
	}

	/**
	 * The import wizard keeps what it needs in a file the web shows nothing of, only for its owner, and its addresses are plain.
	 */
	public function test_the_wizard_keeps_its_files_private_and_its_addresses_plain() {
		$key  = strtolower( wp_generate_password( 24, false ) );
		$data = array( 'a' => array( 'b' => 'c' ) );
		BundleAdmin\store( $key, 'bundle', $data );
		$file = BundleAdmin\path( $key, 'bundle' );
		$this->assertStringStartsWith( '<?php exit; ?>', (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a temporary file.
		$this->assertSame( $data, BundleAdmin\stored( $key, 'bundle' ) );
		$this->assertNull( BundleAdmin\stored( 'short', 'bundle' ) );

		$url = BundleAdmin\step_url( 'review', $key );
		$this->assertStringNotContainsString( '&#038;', $url );
		$this->assertStringNotContainsString( '&amp;', $url );
		$this->assertStringContainsString( 'step=review', $url );

		// Not the owner's: nothing opens.
		set_transient( 'evcert_import_' . $key, array( 'user' => 999999 ), 60 );
		$_GET['_wpnonce'] = wp_create_nonce( 'evcert_import_view_' . $key );
		$this->assertFalse( BundleAdmin\owns( $key ) );
		set_transient( 'evcert_import_' . $key, array( 'user' => get_current_user_id() ), 60 );
		$this->assertTrue( BundleAdmin\owns( $key ) );
		$_GET['_wpnonce'] = 'wrong';
		$this->assertFalse( BundleAdmin\owns( $key ), 'The address needs its nonce.' );
		unset( $_GET['_wpnonce'] );

		BundleAdmin\discard( $key );
		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * A template goes into the file as block markup and is read back as the array the importer works on; a file with the design as
	 * an array (the form before blocks) is not taken.
	 */
	public function test_a_template_travels_as_block_markup() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		add_filter( 'evcert_bundle_max_bytes', static fn () => 50 * MB_IN_BYTES ); // Whatever the server accepts for uploads.
		wp_set_current_user( 1 );
		$bundle = Bundle\export( array( 'templates' ) );
		$first  = $bundle['templates'][0];
		$this->assertIsArray( $first['design'], 'In memory the design is an array.' );

		$file = Bundle\for_file( $bundle );
		$this->assertIsString( $file['templates'][0]['design'] );
		$this->assertTrue( \EventsCertificate\Vendor\Camaleaun\Draw\Design::is_markup( $file['templates'][0]['design'] ) );
		$this->assertStringStartsWith( '<!-- wp:', ltrim( $file['templates'][0]['design'] ), 'Blocks, not the JSON of a design.' );

		$path = wp_tempnam( 'evcert-markup.json' );
		file_put_contents( $path, (string) wp_json_encode( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents -- a test file.
		$read = Bundle\read( $path );
		wp_delete_file( $path );
		remove_all_filters( 'evcert_bundle_max_bytes' );
		$this->assertIsArray( $read );
		$this->assertIsArray( $read['templates'][0]['design'] );
		$this->assertSame( count( $first['design']['elements'] ), count( $read['templates'][0]['design']['elements'] ), 'The same elements.' );

		// A file whose template has its design as an array (the form before blocks) brings no design: that template is left out.
		$old                          = $bundle;
		$old['templates'][0]['title'] = 'Array design';
		$read_old                     = Bundle\from_file( json_decode( (string) wp_json_encode( $old ), true ) );
		$this->assertSame( array(), $read_old['templates'][0]['design'] );
		$summary = Bundle\import( $read_old, array( 'templates' ) );
		$this->assertSame( 0, $summary['templates']['added'] );
		$this->assertSame( count( $old['templates'] ), $summary['templates']['skipped'], 'All of them: none has a design in blocks.' );
	}
}
