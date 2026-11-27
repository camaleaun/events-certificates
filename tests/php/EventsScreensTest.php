<?php
/**
 * The Events screens: the list, the event's form, its participants and the form to add them.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\EventsAdmin;

/**
 * Each screen has one job (docs/decisions/0026): a wide list is not on the same screen as a form.
 */
class EventsScreensTest extends Test_Case {

	/**
	 * Admin screens, not loaded outside wp-admin.
	 */
	protected function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once dirname( __DIR__, 2 ) . '/includes/events-admin.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-events-list-table.php';
	}

	/**
	 * Clean query.
	 */
	protected function tear_down() {
		$_GET     = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/**
	 * The screen asked for by the address.
	 */
	public function test_the_view_comes_from_the_address() {
		$event = Fixtures::event();

		$_GET = array();
		$this->assertSame( 'list', EventsAdmin\current_view(), 'No event: the list.' );

		$_GET = array( 'event' => 'new' );
		$this->assertSame( 'edit', EventsAdmin\current_view() );

		$_GET = array( 'event' => (string) $event );
		$this->assertSame( 'edit', EventsAdmin\current_view(), 'An event: its form.' );

		foreach ( array( 'participants', 'add', 'edit' ) as $view ) {
			$_GET = array(
				'event' => (string) $event,
				'view'  => $view,
			);
			$this->assertSame( $view, EventsAdmin\current_view() );
		}

		$_GET = array(
			'event' => (string) $event,
			'view'  => 'whatever',
		);
		$this->assertSame( 'edit', EventsAdmin\current_view(), 'An unknown view is the form.' );

		$_GET = array(
			'event' => '99999999',
			'view'  => 'participants',
		);
		$this->assertSame( 'list', EventsAdmin\current_view(), 'An event that does not exist: the list.' );
	}

	/**
	 * The address of the participants.
	 */
	public function test_participants_url() {
		$url = EventsAdmin\participants_url( 7, array( 'evcert_done' => 'issue' ) );

		$this->assertStringContainsString( 'page=evcert-event', $url );
		$this->assertStringContainsString( 'event=7', $url );
		$this->assertStringContainsString( 'view=participants', $url );
		$this->assertStringContainsString( 'evcert_done=issue', $url );
	}

	/**
	 * The list of events is searched, sorted and paged like the posts list.
	 */
	public function test_events_list_search_and_sort() {
		Fixtures::event(
			array(
				'title' => 'Zeta Screens Meetup',
				'start' => '2026-01-10 19:00',
			)
		);
		Fixtures::event(
			array(
				'title' => 'Alfa Screens Meetup',
				'start' => '2026-03-10 19:00',
			)
		);
		Fixtures::event(
			array(
				'title' => 'Other Thing',
				'start' => '2026-02-10 19:00',
			)
		);

		$_REQUEST['s'] = 'Screens';
		$_GET          = array(
			'orderby' => 'title',
			'order'   => 'asc',
		);
		$table         = new EventsAdmin\Events_List_Table();
		$table->prepare_items();
		$this->assertSame( array( 'Alfa Screens Meetup', 'Zeta Screens Meetup' ), wp_list_pluck( $table->items, 'title' ), 'Searched and by name.' );

		$_GET  = array();
		$table = new EventsAdmin\Events_List_Table();
		$table->prepare_items();
		$this->assertSame( array( 'Alfa Screens Meetup', 'Zeta Screens Meetup' ), wp_list_pluck( $table->items, 'title' ), 'By default the newest first.' );
		$this->assertSame( 2, $table->get_pagination_arg( 'total_items' ) );
	}

	/**
	 * The event's form saves the fields it shows, including the ones it did not have.
	 */
	public function test_the_form_fields_are_saved() {
		$id    = \EventsCertificate\Events\save(
			array(
				'title'      => 'Form Fields',
				'start'      => '2026-05-01 10:00',
				'community'  => 'Comunidade Teste',
				'venue'      => 'Centro',
				'city'       => 'Recife, PE',
				'speakers'   => 'Ana Lima',
				'show_hours' => '0',
			)
		);
		$event = \EventsCertificate\Events\get( $id );

		$this->assertSame( 'Comunidade Teste', $event['community'] );
		$this->assertSame( 'Centro, Recife, PE', $event['location'], 'The place is the venue and the city.' );
		$this->assertSame( 'Ana Lima', $event['speakers'] );
		$this->assertSame( '0', $event['show_hours'] );
	}

	/**
	 * Runs save_event() with what a form posts and returns where it went.
	 *
	 * @param array<string, string> $post The posted fields.
	 * @param int                   $id   The event (0 for a new one).
	 * @return string The address it redirected to.
	 */
	private function post_form( array $post, $id = 0 ) {
		wp_set_current_user( 1 );
		$location = '';
		$catch    = static function ( $to ) use ( &$location ) {
			$location = $to;
			throw new \RuntimeException( 'redirected' );
		};
		add_filter( 'wp_redirect', $catch );
		$_POST    = $post + array(
			'evcert_event_id' => (string) $id,
			'_wpnonce'        => wp_create_nonce( 'evcert_event_save_' . $id ),
		);
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- a test posting the form it made the nonce for.
		try {
			EventsAdmin\save_event();
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirected', $e->getMessage() );
		} finally {
			remove_filter( 'wp_redirect', $catch );
			$_POST = array();
		}

		return $location;
	}

	/**
	 * The form's markup: the logo beside the community, grouped fields, the required marks and the workload fields out of reach.
	 */
	public function test_the_form_is_laid_out_for_the_person() {
		wp_set_current_user( 1 );
		$id = \EventsCertificate\Events\save(
			array(
				'title'      => 'Form Layout',
				'start'      => '2026-05-01 10:00',
				'venue'      => 'Centro',
				'city'       => 'Recife, PE',
				'end'        => '2026-05-01 12:00',
				'show_hours' => '0',
			)
		);
		ob_start();
		EventsAdmin\render_event( $id );
		$html = (string) ob_get_clean();
		\wp_delete_post( $id, true );

		$this->assertStringNotContainsString( ' required', $html, 'Core does not require a field of a new post.' );
		$this->assertSame( 2, substr_count( $html, 'evcert-event-pictures-root' ), 'The logo and the signature, each where it belongs.' );
		$this->assertLessThan( strpos( $html, 'data-only="logo"' ), strpos( $html, 'id="evcert_community"' ), 'The logo comes after the community.' );
		$this->assertLessThan( strpos( $html, 'id="evcert_speakers"' ), strpos( $html, 'data-only="logo"' ), 'And before the speakers.' );
		$this->assertGreaterThan( strpos( $html, 'id="evcert-signature"' ), strpos( $html, 'data-only="signature"' ), 'The signature picture is in the Signature box.' );
		$this->assertStringContainsString( 'name="evcert_length"', $html, 'The end is a length.' );
		$this->assertMatchesRegularExpression( '/<option value="120"\s+selected/', $html, 'The length the event has (here two hours; two hours too for a new one).' );
		$this->assertStringContainsString( 'value="custom"', $html, 'And "Set an end time…".' );
		$this->assertMatchesRegularExpression( '/id="evcert_end"[^>]*disabled/', $html, 'The end time is out of reach until it is chosen.' );
		$this->assertStringNotContainsString( 'evcert_hours', $html, 'The Quick generator has no hours fields, nor has this form.' );
		$this->assertDoesNotMatchRegularExpression( '/href="\s/', $html, 'No address with spaces and line breaks in it.' );
		$this->assertMatchesRegularExpression( '/id="evcert_location"[^>]*value=""/', $html, 'The place that follows the venue and the city is shown empty.' );
	}

	/**
	 * Nothing is required, as in a new post: no name, no start and an end that is not after the start are all fine.
	 */
	public function test_nothing_in_the_form_is_required() {
		$this->post_form( array( 'evcert_length' => 'custom' ) );
		$posts = get_posts(
			array(
				'post_type'   => \EventsCertificate\Events\POST_TYPE,
				'post_status' => 'any',
				'numberposts' => 1,
				'orderby'     => 'ID',
				'order'       => 'DESC',
			)
		);
		$this->assertNotEmpty( $posts, 'An empty form still makes an event.' );
		$event = \EventsCertificate\Events\get( $posts[0]->ID );
		$this->assertSame( '', $event['title'] );
		$this->assertNotSame( '', $event['start'], 'No start is now.' );
		$this->assertSame( ( new \DateTimeImmutable( $event['start'], wp_timezone() ) )->modify( '+2 hours' )->format( 'Y-m-d H:i' ), $event['end'], 'No end is two hours after.' );
		\wp_delete_post( $event['id'], true );

		$this->post_form(
			array(
				'evcert_title'  => 'Odd End Event',
				'evcert_start'  => '2026-05-01T19:00',
				'evcert_length' => 'custom',
				'evcert_end'    => '2026-05-01T18:00',
			)
		);
		$id = $this->event_id_by_title( 'Odd End Event' );
		$this->assertSame( '2026-05-01 21:00', \EventsCertificate\Events\get( $id )['end'], 'An end before the start is two hours after it.' );
		\wp_delete_post( $id, true );
	}

	/**
	 * When an event is refused (here: it is gone) the form comes back with what was typed.
	 */
	public function test_the_form_keeps_what_was_typed_when_the_event_is_refused() {
		$location = $this->post_form(
			array(
				'evcert_title'     => 'Refused Event',
				'evcert_start'     => '2026-05-01T19:00',
				'evcert_community' => 'Comunidade Teste',
			),
			999999
		);

		$this->assertStringContainsString( 'evcert_done=error', $location );
		$this->assertSame( 'That event no longer exists.', get_transient( 'evcert_event_error_1' ) );
		$_GET['evcert_done'] = 'error';
		ob_start();
		EventsAdmin\render_event( 0 );
		$html = (string) ob_get_clean();
		delete_transient( 'evcert_event_error_1' );

		$this->assertStringContainsString( 'value="Refused Event"', $html );
		$this->assertStringContainsString( 'value="Comunidade Teste"', $html );
		$this->assertStringContainsString( 'value="2026-05-01T19:00"', $html );
		$this->assertFalse( get_transient( 'evcert_event_form_1' ), 'It is shown once.' );
	}

	/**
	 * The form has no hours fields: what an import or an agent stored stays.
	 */
	public function test_the_hours_stay_when_the_form_has_no_hours() {
		$id = \EventsCertificate\Events\save(
			array(
				'title' => 'Hours Stay',
				'start' => '2026-05-01 10:00',
				'hours' => '8',
			)
		);
		$this->post_form(
			array(
				'evcert_title' => 'Hours Stay',
				'evcert_start' => '2026-05-01T10:00',
			),
			$id
		);

		$this->assertSame( '8', \EventsCertificate\Events\get( $id )['hours'] );
		$this->assertSame( '0', \EventsCertificate\Events\get( $id )['show_hours'] );
		\wp_delete_post( $id, true );
	}

	/**
	 * The end is the start plus the length chosen; "Set an end time…" gives its own time instead.
	 */
	public function test_the_end_is_a_length_or_a_time() {
		$this->post_form(
			array(
				'evcert_title'  => 'Length Event',
				'evcert_start'  => '2026-05-01T19:00',
				'evcert_length' => '180',
				'evcert_end'    => '2026-05-01T20:00', // Not posted by a browser (disabled); ignored when it is.
			)
		);
		$id = $this->event_id_by_title( 'Length Event' );
		$this->assertSame( '2026-05-01 22:00', \EventsCertificate\Events\get( $id )['end'], 'Three hours from the start.' );
		\wp_delete_post( $id, true );

		$this->post_form(
			array(
				'evcert_title'  => 'Custom End Event',
				'evcert_start'  => '2026-05-01T19:00',
				'evcert_length' => 'custom',
				'evcert_end'    => '2026-05-02T01:30',
			)
		);
		$id = $this->event_id_by_title( 'Custom End Event' );
		$this->assertSame( '2026-05-02 01:30', \EventsCertificate\Events\get( $id )['end'], 'Its own end time.' );
		\wp_delete_post( $id, true );
	}

	/**
	 * The ID of the event with a title.
	 *
	 * @param string $title Title.
	 * @return int
	 */
	private function event_id_by_title( $title ) {
		$posts = get_posts(
			array(
				'post_type'   => \EventsCertificate\Events\POST_TYPE,
				'post_status' => 'any',
				'title'       => $title,
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		$this->assertNotEmpty( $posts, 'The event was saved.' );

		return (int) $posts[0];
	}

	/**
	 * The screens' slugs are singular.
	 */
	public function test_the_slugs_are_singular() {
		$this->assertSame( 'evcert-event', EventsAdmin\SLUG );
		$this->assertSame( 'evcert-certificate', \EventsCertificate\Admin\SLUG );
		$this->assertSame( 'evcert-options', \EventsCertificate\Admin\SLUG_SETTINGS );
	}

	/**
	 * Applying a bulk action with no action chosen, or no event ticked, goes back to the list: it used to call back() with no argument, a fatal error.
	 */
	public function test_a_bulk_action_with_nothing_chosen_goes_back_to_the_list() {
		wp_set_current_user( 1 );
		foreach ( array(
			'no action chosen' => array(
				'action'        => '-1',
				'evcert_events' => array( '1' ),
			),
			'no event ticked'  => array(
				'action' => 'trash',
			),
		) as $what => $request ) {
			$location = '';
			$catch    = static function ( $to ) use ( &$location ) {
				$location = $to;
				throw new \RuntimeException( 'redirected' );
			};
			add_filter( 'wp_redirect', $catch );
			$_REQUEST = $request + array( '_wpnonce' => wp_create_nonce( 'bulk-evcert_events' ) );
			try {
				EventsAdmin\bulk_events();
				$this->fail( 'It must redirect: ' . $what );
			} catch ( \RuntimeException $e ) {
				$this->assertSame( 'redirected', $e->getMessage(), $what );
			} finally {
				remove_filter( 'wp_redirect', $catch );
				$_REQUEST = array();
			}
			$this->assertStringContainsString( 'page=', $location, $what );
		}
	}
}
