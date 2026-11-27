<?php
/**
 * The Generator of registered events: what it previews and who it issues.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Events;
use EventsCertificate\Designs;
use EventsCertificate\EventsGenerator;
use EventsCertificate\People;

/**
 * The preview is the real certificate of a person; only who attended is issued.
 */
class EventsGeneratorTest extends Test_Case {

	/**
	 * A fictional event with an attendee, a no-show and a registered person.
	 *
	 * @return array<string, mixed> The event and its people by name.
	 */
	private function event() {
		wp_set_current_user( 1 );
		$id = (int) Events\save(
			array(
				'title'     => 'A fictional meetup',
				'start'     => '2026-11-25 19:00',
				'community' => 'A fictional community',
			)
		);
		foreach ( array(
			'attended'   => 'Ana Example',
			'no_show'    => 'Bruno Example',
			'registered' => 'Carla Example',
		) as $status => $name ) {
			People\add(
				$id,
				array(
					'name'   => $name,
					'status' => $status,
					'source' => 'manual',
				)
			);
		}
		$people = array();
		foreach ( People\query( $id )['rows'] as $person ) {
			$people[ $person['name'] ] = $person;
		}

		return array( Events\get( $id ), $people );
	}

	/**
	 * The preview draws the person's certificate with the event's words, and issues nothing.
	 */
	public function test_the_preview_is_the_persons_certificate() {
		list( $event, $people ) = $this->event();
		$html                   = EventsGenerator\preview( $people['Ana Example'], $event );

		$this->assertStringContainsString( 'Ana Example', $html );
		$this->assertStringContainsString( 'A fictional meetup', $html );
		$this->assertStringNotContainsStringIgnoringCase( 'A fictional community', $html, 'The Basic template is plain: no community.' );
		$this->assertNull( People\certificate( People\get( (int) $people['Ana Example']['id'] ), '' ), 'A preview issues nothing.' );
	}

	/**
	 * A template that has the community (Meetup) shows the event's.
	 */
	public function test_a_template_with_the_community_shows_the_events() {
		list( $event, $people ) = $this->event();
		Designs\restore_starters();
		$this->assertTrue( Designs\set_default( Designs\starter_id( 'Meetup' ) ) );
		$html = EventsGenerator\preview( $people['Ana Example'], $event );

		$this->assertStringContainsStringIgnoringCase( 'A fictional community', $html );
	}

	/**
	 * The words for a status, and the screen's address.
	 */
	public function test_status_words_and_address() {
		$this->assertSame( 'Attended', EventsGenerator\status_label( 'attended' ) );
		$this->assertStringContainsString( 'page=evcert-generator', EventsGenerator\url() );
		$this->assertStringContainsString( 'event=7', EventsGenerator\url( array( 'event' => 7 ) ) );
	}

	/**
	 * Each attendee in the list has a PDF button of their own; who did not attend has none. The bar at the top opens the palette.
	 */
	public function test_each_attendee_has_a_pdf_button() {
		list( $event, $people ) = $this->event();
		$_GET['event']          = (string) $event['id'];
		ob_start();
		EventsGenerator\render();
		$html = (string) ob_get_clean();
		unset( $_GET['event'] );

		$this->assertStringContainsString( 'name="evcert_events_generator" value="one-' . $people['Ana Example']['id'] . '"', $html );
		$this->assertStringContainsString( '<button type="button" class="evcert-generator__doc-bar" id="evcert-generator-docbar"', $html, 'The bar at the top is a button that opens the command palette.' );
		$this->assertStringNotContainsString( 'value="one-' . $people['Bruno Example']['id'] . '"', $html );
		$this->assertStringNotContainsString( 'value="one-' . $people['Carla Example']['id'] . '"', $html );
	}
}
