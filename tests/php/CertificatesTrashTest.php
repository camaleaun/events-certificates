<?php
/**
 * The Trash of certificates: out of the list and off the record, back as they were, or gone for good.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Admin;
use EventsCertificate\Certificates;
use EventsCertificate\Install;
use EventsCertificate\People;

/**
 * A certificate in the Trash answers nowhere; it comes back unless a newer valid one took its place.
 */
class CertificatesTrashTest extends Test_Case {

	/**
	 * The event.
	 *
	 * @var int
	 */
	private $event;

	/**
	 * An event, with verification on (a correction then replaces).
	 */
	protected function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-list-table.php';
		add_filter( 'evcert_verification_enabled', '__return_true' );
		$this->event = Fixtures::event();
	}

	/**
	 * Verification back to its default.
	 */
	protected function tear_down() {
		remove_filter( 'evcert_verification_enabled', '__return_true' );
		parent::tear_down();
	}

	/**
	 * A certificate of a participant who attended.
	 *
	 * @param string $name Name.
	 * @return array<string, mixed>
	 */
	private function certificate( $name = 'Ana Lima' ) {
		$person = Fixtures::attendee_of_event( $this->event, $name, strtolower( str_replace( ' ', '.', $name ) ) . '@example.test' );
		People\set_status( array( (int) $person['id'] ), 'attended', $this->event );

		return People\issue( People\get( (int) $person['id'] ), 'attendee' );
	}

	/**
	 * A certificate in the Trash is not in the list or the counts, and its code is not on record.
	 */
	public function test_trash_hides_the_certificate_and_its_code() {
		$cert = $this->certificate();
		$this->assertNotNull( Certificates\get_by_code( $cert['code'] ) );

		$this->assertTrue( Certificates\trash( (int) $cert['id'] ) );
		$this->assertSame( 'trash', Certificates\get( (int) $cert['id'] )['status'] );
		$this->assertSame( 'issued', Certificates\get( (int) $cert['id'] )['trashed_from'] );
		$this->assertNull( Certificates\get_by_code( $cert['code'] ), 'The code answers nowhere.' );
		$this->assertNotNull( Certificates\get_by_code( $cert['code'], true ), 'It exists, for the uniqueness of codes.' );

		$ids = wp_list_pluck( Certificates\query( array( 'per_page' => 100 ) )['rows'], 'id' );
		$this->assertNotContains( (string) $cert['id'], array_map( 'strval', $ids ) );
		$in_trash = wp_list_pluck( Certificates\query( array( 'status' => 'trash', 'per_page' => 100 ) )['rows'], 'id' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$this->assertContains( (string) $cert['id'], array_map( 'strval', $in_trash ) );
		$this->assertSame( 1, Admin\counts()['trash'] );
		$this->assertFalse( Certificates\trash( (int) $cert['id'] ), 'Already there.' );
	}

	/**
	 * The participant has no certificate while it is in the Trash, and gets it back with it.
	 */
	public function test_the_participant_has_none_in_the_trash_and_it_comes_back() {
		$cert   = $this->certificate();
		$person = People\get( (int) $cert['source_ref'] );
		$this->assertNotNull( People\certificate( $person, 'attendee' ) );

		Certificates\trash( (int) $cert['id'] );
		$person = People\get( (int) $cert['source_ref'] );
		$this->assertNull( People\certificate( $person, 'attendee' ) );
		$this->assertFalse( People\has_certificate( $person ) );

		$this->assertTrue( Certificates\restore( (int) $cert['id'] ) );
		$this->assertSame( 'issued', Certificates\get( (int) $cert['id'] )['status'] );
		$this->assertSame( (int) $cert['id'], (int) People\certificate( People\get( (int) $cert['source_ref'] ), 'attendee' )['id'] );
	}

	/**
	 * Issuing again while it is in the Trash makes a new certificate; the old one then stays in the Trash.
	 */
	public function test_a_valid_certificate_does_not_come_back_over_a_newer_one() {
		$old = $this->certificate();
		Certificates\trash( (int) $old['id'] );
		$new = People\issue( People\get( (int) $old['source_ref'] ), 'attendee' );
		$this->assertNotSame( $old['code'], $new['code'] );

		$result = Certificates\restore( (int) $old['id'] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'evcert_newer_certificate', $result->get_error_code() );
		$this->assertSame( 'trash', Certificates\get( (int) $old['id'] )['status'] );
		$this->assertSame( 'issued', Certificates\get( (int) $new['id'] )['status'] );
	}

	/**
	 * A replaced or revoked certificate goes back to the status it had.
	 */
	public function test_restore_goes_back_to_the_status_it_had() {
		$cert = $this->certificate();
		Certificates\revoke( (int) $cert['id'], 'Duplicate' );
		Certificates\trash( (int) $cert['id'] );
		$this->assertSame( 'revoked', Certificates\get( (int) $cert['id'] )['trashed_from'] );
		$this->assertTrue( Certificates\restore( (int) $cert['id'] ) );
		$this->assertSame( 'revoked', Certificates\get( (int) $cert['id'] )['status'] );
		$this->assertNull( Certificates\get( (int) $cert['id'] )['trashed_at'] );
	}

	/**
	 * Deleting for good is only from the Trash, takes the share images and the links to it, and leaves the one it replaced.
	 */
	public function test_delete_permanently() {
		global $wpdb;

		$old = $this->certificate();
		$new = Certificates\reissue( $old, array( 'name' => 'Ana Lima Souza' ) );
		$this->assertSame( 'replaced', Certificates\get( (int) $old['id'] )['status'] );
		$this->assertFalse( Certificates\delete_permanently( (int) $new['id'] ), 'Not in the Trash: stays.' );

		$uploads = wp_get_upload_dir();
		wp_mkdir_p( $uploads['basedir'] . '/evcert-share' );
		$image = $uploads['basedir'] . '/evcert-share/' . strtolower( $new['code'] ) . '-en_US-abc.png';
		file_put_contents( $image, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a temporary file.

		Certificates\trash( (int) $new['id'] );
		$this->assertTrue( Certificates\delete_permanently( (int) $new['id'] ) );
		$this->assertNull( Certificates\get( (int) $new['id'] ) );
		$this->assertFileDoesNotExist( $image );
		$this->assertNull( Certificates\get( (int) $old['id'] )['replaced_by'], 'The one it replaced no longer points at it.' );
		$this->assertSame( 'replaced', Certificates\get( (int) $old['id'] )['status'] );
		$links = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE certificate_id = %d', Install\tables()['person_roles'], (int) $new['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		$this->assertSame( 0, $links );
	}

	/**
	 * Emptying the Trash deletes what is in it and nothing else.
	 */
	public function test_empty_the_trash() {
		$one = $this->certificate();
		$two = $this->certificate( 'Bia Souza' );
		Certificates\trash( (int) $one['id'] );

		$this->assertSame( 1, Certificates\empty_trash() );
		$this->assertNull( Certificates\get( (int) $one['id'] ) );
		$this->assertNotNull( Certificates\get( (int) $two['id'] ) );
	}

	/**
	 * The verification page says nothing of a certificate in the Trash, and a trashed successor is not linked.
	 */
	public function test_the_public_page_ignores_the_trash() {
		$old = $this->certificate();
		$new = Certificates\reissue( $old, array( 'name' => 'Ana Lima Souza' ) );
		$this->assertStringContainsString( $new['code'], implode( ' ', \EventsCertificate\Verify\status( Certificates\get( (int) $old['id'] ) ) ) );

		Certificates\trash( (int) $new['id'] );
		$this->assertStringNotContainsString( $new['code'], implode( ' ', \EventsCertificate\Verify\status( Certificates\get( (int) $old['id'] ) ) ) );
		$this->assertNull( Certificates\get_by_code( $new['code'] ) );
	}

	/**
	 * The list: Move to Trash for each row and in bulk, a Trash view, and Restore / Delete permanently there.
	 */
	public function test_the_list_has_the_trash() {
		$cert = $this->certificate();
		Certificates\trash( (int) $this->certificate( 'Bia Souza' )['id'] );

		$table = new Admin\List_Table();
		$bulk  = new \ReflectionMethod( $table, 'get_bulk_actions' );
		$bulk->setAccessible( true );
		$this->assertSame( 'Move to Trash', $bulk->invoke( $table )['trash'] );
		$views = new \ReflectionMethod( $table, 'get_views' );
		$views->setAccessible( true );
		$this->assertArrayHasKey( 'trash', $views->invoke( $table ) );
		$name = new \ReflectionMethod( $table, 'column_name' );
		$name->setAccessible( true );
		$this->assertStringContainsString( 'action=trash', $name->invoke( $table, Certificates\get( (int) $cert['id'] ) ) );

		$_GET['status'] = 'trash';
		$table          = new Admin\List_Table();
		$bulk           = new \ReflectionMethod( $table, 'get_bulk_actions' );
		$bulk->setAccessible( true );
		$this->assertSame( array( 'untrash', 'delete' ), array_keys( $bulk->invoke( $table ) ) );
		$trashed = Certificates\query( array( 'status' => 'trash' ) )['rows'][0];
		$name    = new \ReflectionMethod( $table, 'column_name' );
		$name->setAccessible( true );
		$row = $name->invoke( $table, $trashed );
		unset( $_GET['status'] );
		$this->assertStringContainsString( 'action=untrash', $row );
		$this->assertStringContainsString( 'action=delete', $row );
		$this->assertStringNotContainsString( 'PDF', $row );
	}

	/**
	 * Runs an action of the screen and returns where it sends the person.
	 *
	 * @param string $action Action.
	 * @param int[]  $ids    Certificates ticked.
	 * @return string The address.
	 */
	private function act( $action, array $ids = array() ) {
		wp_set_current_user( 1 );
		$_REQUEST['_wpnonce']    = wp_create_nonce( 'bulk-certificates' );
		$_REQUEST['certificate'] = $ids;
		$where                   = '';
		$catch                   = static function ( $to ) use ( &$where ) {
			$where = $to;
			throw new \RuntimeException( 'redirected' );
		};
		add_filter( 'wp_redirect', $catch );
		try {
			Admin\act_on_certificates( $action );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirected', $e->getMessage() );
		} finally {
			remove_filter( 'wp_redirect', $catch );
			unset( $_REQUEST['_wpnonce'], $_REQUEST['certificate'] );
		}

		return $where;
	}

	/**
	 * The screen's actions: to the Trash, back, refused over a newer one, deleted for good and the Trash emptied.
	 */
	public function test_the_screens_actions() {
		$one = $this->certificate();
		$two = $this->certificate( 'Bia Souza' );

		$where = $this->act( 'trash', array( (int) $one['id'], (int) $two['id'] ) );
		$this->assertStringContainsString( 'evcert_done=trash', $where );
		$this->assertStringContainsString( 'evcert_n=2', $where );
		$this->assertSame( 'trash', Certificates\get( (int) $one['id'] )['status'] );

		// A newer certificate for the first person: it does not come back.
		People\issue( People\get( (int) $one['source_ref'] ), 'attendee' );
		$where = $this->act( 'untrash', array( (int) $one['id'], (int) $two['id'] ) );
		$this->assertStringContainsString( 'evcert_n=1', $where );
		$this->assertStringContainsString( 'evcert_kept=1', $where );
		$this->assertSame( 'trash', Certificates\get( (int) $one['id'] )['status'] );
		$this->assertSame( 'issued', Certificates\get( (int) $two['id'] )['status'] );

		$this->assertStringContainsString( 'evcert_done=delete', $this->act( 'delete', array( (int) $two['id'] ) ), 'Only what is in the Trash is deleted.' );
		$where = $this->act( 'delete_all' );
		$this->assertStringContainsString( 'evcert_n=1', $where );
		$this->assertNull( Certificates\get( (int) $one['id'] ) );
		$this->assertSame( '', $this->act( 'unknown' ), 'An action it does not know does nothing.' );
	}
}
