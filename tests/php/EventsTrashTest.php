<?php
/**
 * Events in the Trash: they come back, or are deleted for good only from there.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Events;

/**
 * Trash, restore and delete permanently.
 */
class EventsTrashTest extends Test_Case {

	/**
	 * A fictional published event.
	 *
	 * @param string $title Title.
	 * @return int
	 */
	private function make( $title ) {
		wp_set_current_user( 1 );

		return (int) Events\save(
			array(
				'title' => $title,
				'start' => '2026-11-25 19:00',
			)
		);
	}

	/**
	 * Trashed, restored, then trashed and deleted: only from the Trash, and only for good then.
	 */
	public function test_an_event_goes_to_the_trash_and_comes_back() {
		$id = $this->make( 'A fictional meetup' );
		$this->assertTrue( Events\trash( $id ) );
		$this->assertNull( Events\get( $id ), 'A trashed event is not listed.' );
		$this->assertNotNull( Events\get( $id, true ), 'It can still be read to be listed in the Trash.' );
		$this->assertContains( $id, wp_list_pluck( Events\all( array( 'post_status' => 'trash' ) ), 'ID' ) );

		$this->assertTrue( Events\untrash( $id ) );
		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertFalse( Events\untrash( $id ), 'Only an event in the Trash comes back.' );
		$this->assertFalse( Events\delete( $id ), 'An event outside the Trash is not deleted.' );

		Events\trash( $id );
		$this->assertTrue( Events\delete( $id ) );
		$this->assertNull( get_post( $id ) );
	}
}
