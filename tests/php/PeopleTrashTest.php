<?php
/**
 * The Trash of an event's participants: removing goes there first, and from there people come back or go for good.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Certificates;
use EventsCertificate\EventsAdmin;
use EventsCertificate\Install;
use EventsCertificate\People;

/**
 * Trashed people are out of the list, the counts, the certificates and the issuing, until restored.
 */
class PeopleTrashTest extends Test_Case {

	/**
	 * The event.
	 *
	 * @var int
	 */
	private $event;

	/**
	 * An event and the admin classes.
	 */
	protected function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once dirname( __DIR__, 2 ) . '/includes/events-admin.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-people-list-table.php';
		$this->event = Fixtures::event();
	}

	/**
	 * A participant who attended.
	 *
	 * @param string $name Name.
	 * @return array<string, mixed>
	 */
	private function person( $name = 'Ana Lima' ) {
		return Fixtures::attendee_of_event( $this->event, $name, strtolower( str_replace( ' ', '.', $name ) ) . '@example.test' );
	}

	/**
	 * The schema has the column of the Trash.
	 */
	public function test_the_people_table_has_a_deleted_at() {
		global $wpdb;

		$this->assertNotNull( $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', People\table(), 'deleted_at' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		$this->assertGreaterThanOrEqual( 4, (int) Install\DB_VERSION );
	}

	/**
	 * A trashed person leaves the list and the counts, shows in the Trash, and comes back with their roles and certificates.
	 */
	public function test_trash_and_restore() {
		$ana = $this->person();
		$bia = $this->person( 'Bia Souza' );
		$this->assertSame( 1, People\trash( array( (int) $ana['id'] ), $this->event ) );

		$this->assertSame( array( (int) $bia['id'] ), array_map( 'intval', wp_list_pluck( People\query( $this->event )['rows'], 'id' ) ) );
		$this->assertSame( array( (int) $ana['id'] ), array_map( 'intval', wp_list_pluck( People\query( $this->event, array( 'trash' => true ) )['rows'], 'id' ) ) );
		$counts = People\counts( $this->event );
		$this->assertSame( 1, $counts['all'] );
		$this->assertSame( 1, $counts['trash'] );

		$this->assertSame( 0, People\trash( array( (int) $ana['id'] ), $this->event ), 'Already there.' );
		$this->assertSame( 1, People\restore( array( (int) $ana['id'] ), $this->event ) );
		$this->assertCount( 2, People\query( $this->event )['rows'] );
		$this->assertSame( 0, People\counts( $this->event )['trash'] );
		$this->assertSame( 0, People\restore( array( (int) $bia['id'] ), $this->event ), 'It was never in the Trash.' );
	}

	/**
	 * Nothing is issued to someone in the Trash, and their certificates are not in the event's downloads; the ones issued
	 * stay valid.
	 */
	public function test_a_trashed_person_gets_no_certificate_and_keeps_theirs() {
		$ana  = $this->person();
		$cert = People\issue( $ana );
		$bia  = $this->person( 'Bia Souza' );
		People\trash( array( (int) $ana['id'], (int) $bia['id'] ), $this->event );

		$this->assertSame( 'evcert_trashed', People\issue( People\get( (int) $bia['id'] ) )->get_error_code() );
		$this->assertSame( 0, People\issue_many( array( (int) $bia['id'] ), $this->event )['issued'] );
		$this->assertSame( array(), People\certificates( $this->event ) );
		$this->assertSame( 'issued', Certificates\get( (int) $cert['id'] )['status'] );
	}

	/**
	 * Only who is in the Trash can be deleted for good, and the certificates stay.
	 */
	public function test_delete_permanently_only_from_the_trash() {
		$ana  = $this->person();
		$cert = People\issue( $ana );
		$bia  = $this->person( 'Bia Souza' );
		People\trash( array( (int) $ana['id'] ), $this->event );

		$this->assertSame( 1, People\delete_trashed( array( (int) $ana['id'], (int) $bia['id'] ), $this->event ) );
		$this->assertNull( People\get( (int) $ana['id'] ) );
		$this->assertNotNull( People\get( (int) $bia['id'] ), 'Not in the Trash: stays.' );
		$this->assertSame( 'issued', Certificates\get( (int) $cert['id'] )['status'] );
	}

	/**
	 * Emptying the Trash deletes what is in it and nothing else.
	 */
	public function test_empty_the_trash() {
		$ana = $this->person();
		$bia = $this->person( 'Bia Souza' );
		$cai = $this->person( 'Caio Dias' );
		People\trash( array( (int) $ana['id'], (int) $bia['id'] ), $this->event );

		$this->assertSame( 2, People\delete_trashed( array(), $this->event ) );
		$this->assertSame( array( (int) $cai['id'] ), array_map( 'intval', wp_list_pluck( People\query( $this->event )['rows'], 'id' ) ) );
		$this->assertSame( 0, People\counts( $this->event )['trash'] );
	}

	/**
	 * Typed or sent in a file again, someone in the Trash is back; a synchronization leaves them in it.
	 */
	public function test_adding_again_restores_but_a_synchronization_does_not() {
		$ana = $this->person();
		People\trash( array( (int) $ana['id'] ), $this->event );

		People\add(
			$this->event,
			array(
				'name'   => 'Ana Lima',
				'email'  => 'ana.lima@example.test',
				'source' => 'gatherpress',
			)
		);
		$this->assertSame( 1, People\counts( $this->event )['trash'], 'A synchronization does not bring them back.' );

		$result = People\add(
			$this->event,
			array(
				'name'   => 'Ana Lima',
				'email'  => 'ana.lima@example.test',
				'source' => 'manual',
			)
		);
		$this->assertFalse( $result['added'] );
		$this->assertSame( (int) $ana['id'], $result['id'] );
		$this->assertSame( 0, People\counts( $this->event )['trash'] );
		$this->assertCount( 1, People\query( $this->event )['rows'] );
	}

	/**
	 * The list: Move to Trash instead of Remove, the two downloads with the Generator's titles, and the Trash's own bulk
	 * actions, view and row actions.
	 */
	public function test_the_list_in_and_out_of_the_trash() {
		$ana = $this->person();
		People\issue( $ana );
		$bia = $this->person( 'Bia Souza' );
		People\trash( array( (int) $bia['id'] ), $this->event );

		$table  = new EventsAdmin\People_List_Table( $this->event );
		$method = new \ReflectionMethod( $table, 'get_bulk_actions' );
		$method->setAccessible( true );
		$actions = $method->invoke( $table );
		$this->assertSame( 'Move to Trash', $actions['trash'] );
		$this->assertSame( 'Download PDF', $actions['download_pdf'] );
		$this->assertSame( 'Download ZIP', $actions['download_zip'] );
		$this->assertArrayNotHasKey( 'remove', $actions );

		$views = new \ReflectionMethod( $table, 'get_views' );
		$views->setAccessible( true );
		$this->assertArrayHasKey( 'trash', $views->invoke( $table ), 'There is someone in the Trash.' );

		// A row of someone with a certificate has "Download current"; one without does not.
		$name = new \ReflectionMethod( $table, 'column_name' );
		$name->setAccessible( true );
		$this->assertStringContainsString( 'Download current', $name->invoke( $table, People\get( (int) $ana['id'] ) ) );
		$this->assertStringNotContainsString( 'Download current', $name->invoke( $table, People\get( (int) $this->person( 'Caio Dias' )['id'] ) ) );

		// In the Trash.
		$_GET['status'] = 'trash';
		$table          = new EventsAdmin\People_List_Table( $this->event );
		$method         = new \ReflectionMethod( $table, 'get_bulk_actions' );
		$method->setAccessible( true );
		$this->assertSame( array( 'untrash', 'delete' ), array_keys( $method->invoke( $table ) ) );
		$name = new \ReflectionMethod( $table, 'column_name' );
		$name->setAccessible( true );
		$row = $name->invoke( $table, People\get( (int) $bia['id'] ) );
		unset( $_GET['status'] );
		$this->assertStringContainsString( 'Restore', $row );
		$this->assertStringContainsString( 'Delete permanently', $row );
		$this->assertStringNotContainsString( 'Download current', $row );
	}

	/**
	 * The ability that removes participants moves them to the Trash.
	 */
	public function test_the_ability_trashes() {
		$ana = $this->person();
		wp_set_current_user( 1 );
		$result = \EventsCertificate\Abilities\remove_participants(
			array(
				'event_id'        => $this->event,
				'participant_ids' => array( (int) $ana['id'] ),
			)
		);
		$this->assertSame( 1, $result['removed'] );
		$this->assertNotNull( People\get( (int) $ana['id'] ), 'In the Trash, not gone.' );
		$this->assertSame( 1, People\counts( $this->event )['trash'] );
	}
}
