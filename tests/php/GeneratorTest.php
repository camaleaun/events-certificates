<?php
/**
 * The admin Generator: what submits the form, which file it sends, and the event it makes.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Events;
use EventsCertificate\Generator;
use EventsCertificate\People;

/**
 * The download buttons of the screen must reach the code that issues: they once sent a key it never read.
 */
class GeneratorTest extends Test_Case {

	/**
	 * Either key a download button sends submits the form; nothing else does.
	 */
	public function test_a_download_button_submits_the_form() {
		$this->assertTrue( Generator\submitted( array( 'evcert_generator_format' => 'zip' ) ) );
		$this->assertTrue( Generator\submitted( array( 'evcert_generator' => 'pdf' ) ) );
		$this->assertFalse( Generator\submitted( array( 'evcert_title' => 'A title' ) ) );
		$this->assertFalse( Generator\submitted( array() ) );
	}

	/**
	 * A ZIP when asked, a PDF otherwise.
	 */
	public function test_the_file_asked_for() {
		$this->assertSame( 'zip', Generator\requested_format( array( 'evcert_generator_format' => 'zip' ) ) );
		$this->assertSame( 'pdf', Generator\requested_format( array( 'evcert_generator_format' => 'pdf' ) ) );
		$this->assertSame( 'zip', Generator\requested_format( array( 'evcert_generator' => 'zip' ) ) );
		$this->assertSame( 'pdf', Generator\requested_format( array( 'evcert_generator_format' => 'exe' ) ) );
		$this->assertSame( 'pdf', Generator\requested_format( array() ) );
	}

	/**
	 * Without names there is nothing to issue.
	 */
	public function test_it_needs_a_name() {
		$result = Generator\run(
			array(
				'title' => 'A title',
				'start' => '2026-11-25 19:00',
			),
			array()
		);
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'evcert_missing_participants', $result->get_error_code() );
	}

	/**
	 * The event is made, with its people as attended.
	 */
	public function test_it_makes_the_event_and_its_people() {
		wp_set_current_user( 1 );
		$id = Generator\run(
			array(
				'title' => 'Release smoke: blocks in practice',
				'start' => '2026-11-25 19:00',
				'end'   => '2026-11-25 21:00',
			),
			array(
				array( 'name' => 'Emily Johnson' ),
				array( 'name' => 'John Smith' ),
			)
		);

		$this->assertIsInt( $id );
		$this->assertCount( 2, People\query( $id, array( 'status' => 'attended' ) )['rows'] );
	}

	/**
	 * The route is for who can manage the plugin.
	 */
	public function test_the_people_route_needs_the_capability() {
		wp_set_current_user( 0 );
		$denied = rest_do_request( new \WP_REST_Request( 'POST', '/evcert/v1/generator/people' ) );
		$this->assertContains( $denied->get_status(), array( 401, 403 ) );

		wp_set_current_user( 1 );
		$request = new \WP_REST_Request( 'POST', '/evcert/v1/generator/people' );
		$request->set_param( 'lines', "Emily Johnson\njohn@example.com, John Smith" );
		$answer = rest_do_request( $request );
		$this->assertSame( 200, $answer->get_status() );
		$this->assertSame( 'Emily Johnson', $answer->get_data()['people'][0]['name'] );
	}

	/**
	 * Typed lines: a name, then an e-mail and roles in any order of cells.
	 */
	public function test_typed_lines_become_people() {
		$result = Generator\listed_people( "Emily Johnson, emily@example.com, speaker\nJohn Smith\n\n" );

		$this->assertCount( 2, $result['people'] );
		$this->assertSame( 'emily@example.com', $result['people'][0]['email'] );
		$this->assertSame( array( 'speaker' ), $result['people'][0]['roles'] );
		$this->assertSame( 'attended', $result['people'][1]['status'] );
	}

	/**
	 * The Meetup export: the "No" RSVPs are left out and the ones marked absent come unchecked.
	 */
	public function test_a_meetup_csv_is_read_like_the_server_reads_it() {
		$csv  = "Name,RSVP,Attended\nEmily Johnson,Yes,Yes\nJohn Smith,Yes,No\nMary Major,No,No\n";
		$rows = People\parse_csv_text( $csv );
		$out  = Generator\listed_people( '', $rows );

		$this->assertSame( array( 'Emily Johnson', 'John Smith' ), wp_list_pluck( $out['people'], 'name' ) );
		$this->assertSame( array( 'attended', 'no_show' ), wp_list_pluck( $out['people'], 'status' ) );
		$this->assertSame( 1, $out['info']['rsvp_no'] );
		$this->assertSame( 1, $out['info']['absent'] );
	}

	/**
	 * A list that long is refused.
	 */
	public function test_too_many_names_are_refused() {
		$lines  = implode(
			"\n",
			array_map(
				static function ( $i ) {
					return "Person $i";
				},
				range( 1, Generator\MAX_PEOPLE + 1 )
			)
		); // phpcs:ignore Generic.Functions.FunctionCallArgumentSpacing
		$result = Generator\listed_people( $lines );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'evcert_too_many', $result->get_error_code() );
	}

	/**
	 * The list the screen posts comes row by row; a row without a name is dropped, and each cell is cleaned.
	 */
	public function test_the_posted_list_is_read_row_by_row() {
		$people = Generator\people_from_post(
			array(
				array(
					'name'  => 'Emily Johnson',
					'email' => 'emily@example.com',
					'roles' => array( 'speaker' ),
				),
				'not a row',
				array( 'name' => '' ),
				array( 'name' => '<b>John Smith</b>' ),
			)
		);

		$this->assertSame( array( 'Emily Johnson', 'John Smith' ), wp_list_pluck( $people, 'name' ) );
		$this->assertSame( array( 'speaker' ), $people[0]['roles'] );
		$this->assertSame( 'attended', $people[1]['status'] );
	}

	/**
	 * The same list downloaded again is the same event, and only who was sent is issued.
	 */
	public function test_a_later_download_of_the_same_batch_keeps_the_event() {
		wp_set_current_user( 1 );
		$first_issued = array();
		$first        = Generator\run(
			array(
				'title' => 'One batch',
				'start' => '2026-11-25 19:00',
			),
			array(
				array( 'name' => 'Emily Johnson' ),
			),
			0,
			$first_issued
		);
		$again_issued = array();
		$again        = Generator\run(
			array(
				'title' => 'One batch, renamed',
				'start' => '2026-11-25 19:00',
			),
			array(
				array( 'name' => 'John Smith' ),
			),
			$first,
			$again_issued
		);

		$this->assertSame( $first, $again );
		$this->assertCount( 1, $first_issued );
		$this->assertCount( 1, $again_issued );
		$this->assertNotSame( $first_issued, $again_issued );
		$this->assertCount( 2, People\query( $first )['rows'] );
	}

	/**
	 * The certificates of the form are drawn from it: one for each person and role, and nothing is saved.
	 */
	public function test_the_items_make_no_records() {
		wp_set_current_user( 1 );
		$events = count( Events\all() );
		$people = array(
			array(
				'name'  => 'Ana Example',
				'roles' => array( 'speaker', 'organizer' ),
			),
			array(
				'name'  => 'Bruno Example',
				'roles' => array(),
			),
		);
		$items  = Generator\items(
			array(
				'title'     => 'A fictional meetup',
				'start'     => '2026-11-25 19:00',
				'community' => 'A fictional community',
			),
			$people
		);

		$this->assertCount( 3, $items, 'One for each role, and one for who has none.' );
		$this->assertSame( 'A fictional community', $items[0][2]['values']['community'] );
		$this->assertTrue( $items[0][2]['no_verification'] );
		$this->assertSame( '', $items[0][0]['code'], 'No record, no code.' );
		$this->assertCount( $events, Events\all(), 'No event was made.' );

		$pdf = \EventsCertificate\Render\pdf_many( $items );
		$this->assertSame( '%PDF', substr( $pdf, 0, 4 ) );
		$this->assertInstanceOf( \WP_Error::class, Generator\items( array( 'title' => 'A fictional meetup' ), $people ), 'The event needs a date.' );
		$this->assertInstanceOf(
			\WP_Error::class,
			Generator\items(
				array(
					'title' => 'A fictional meetup',
					'start' => '2026-11-25 19:00',
				),
				array()
			),
			'It needs people.'
		);
	}

	/**
	 * A download makes a bounded number of certificates.
	 */
	public function test_a_download_is_bounded() {
		wp_set_current_user( 1 );
		$people = array();
		for ( $i = 0; $i < Generator\MAX_CERTIFICATES + 1; $i++ ) {
			$people[] = array( 'name' => 'Person ' . $i );
		}
		$fields = array(
			'title' => 'A fictional meetup',
			'start' => '2026-11-25 19:00',
		);

		$this->assertInstanceOf( \WP_Error::class, Generator\items( $fields, $people ) );
		$this->assertCount( Generator\MAX_CERTIFICATES, Generator\items( $fields, array_slice( $people, 0, Generator\MAX_CERTIFICATES ) ) );
	}
}
