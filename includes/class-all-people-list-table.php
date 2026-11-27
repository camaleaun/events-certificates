<?php
/**
 * The participants of every event, in one list: the status views, the role filter and the bulk actions of an event's list, with
 * the event as a column. Each row acts on the person's own event.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\EventsAdmin;

use EventsCertificate\People;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-people-list-table.php';

/**
 * Participants of all events: built with event 0, which the query reads as every event.
 */
class All_People_List_Table extends People_List_Table {

	/**
	 * The event filtered by: the `event` of the address when it is an event, else every event.
	 *
	 * @return int
	 */
	protected function query_event() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a list filter.
		$event = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;

		return $event && \EventsCertificate\Events\POST_TYPE === get_post_type( $event ) ? $event : 0;
	}

	/**
	 * Columns: the event after the name, no source.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox">',
			'name'         => __( 'Name', 'events-certificates' ),
			'event'        => __( 'Event', 'events-certificates' ),
			'roles'        => __( 'Roles', 'events-certificates' ),
			'certificates' => __( 'Certificates', 'events-certificates' ),
			'status'       => __( 'Status', 'events-certificates' ),
		);
	}

	/**
	 * Bulk actions: the ones that make sense across events (the downloads and the role changes are one event's).
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		if ( $this->in_trash() ) {
			return array(
				'untrash' => __( 'Restore', 'events-certificates' ),
				'delete'  => __( 'Delete permanently', 'events-certificates' ),
			);
		}

		return array(
			'attended'   => __( 'Mark as attended', 'events-certificates' ),
			'no_show'    => __( 'Mark as no-show', 'events-certificates' ),
			'registered' => __( 'Mark as registered', 'events-certificates' ),
			'issue'      => __( 'Issue certificates', 'events-certificates' ),
			'trash'      => __( 'Move to Trash', 'events-certificates' ),
		);
	}

	/**
	 * Status views, with the counts of every event.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$counts = People\counts( $this->query_event() );
		$labels = array(
			''           => __( 'All', 'events-certificates' ),
			'registered' => People\status_labels()['registered'],
			'attended'   => People\status_labels()['attended'],
			'no_show'    => People\status_labels()['no_show'],
			'certified'  => __( 'With certificate', 'events-certificates' ),
		);
		if ( $counts['trash'] || $this->in_trash() ) {
			$labels['trash'] = __( 'Trash', 'events-certificates' );
		}
		$views = array();
		foreach ( $labels as $key => $label ) {
			$args = $this->query_event() ? array( 'event' => $this->query_event() ) : array();
			if ( '' !== $key ) {
				$args['status'] = $key;
			}
			if ( '' !== $this->role_view() ) {
				$args['role'] = $this->role_view();
			}
			$views[ '' === $key ? 'all' : $key ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( \EventsCertificate\PeopleAdmin\url( $args ) ),
				$this->view() === $key ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( '' === $key ? $counts['all'] : $counts[ $key ] ) )
			);
		}

		return $views;
	}

	/**
	 * Above the list: the filter by role, and in the Trash the button to empty it.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( $this->in_trash() ) {
			if ( $this->has_items() ) {
				echo '<div class="alignleft actions">';
				submit_button( __( 'Empty Trash', 'events-certificates' ), 'apply', 'top' === $which ? 'delete_all' : 'delete_all2', false, array( 'id' => 'top' === $which ? 'delete_all' : 'delete_all2' ) );
				echo '</div>';
			}
			return;
		}
		if ( 'top' !== $which ) {
			return;
		}
		$counts = People\counts( $this->query_event() )['roles'];
		$labels = \EventsCertificate\Designs\role_labels();
		echo '<div class="alignleft actions">';
		// The event: every one by default.
		echo '<label class="screen-reader-text" for="filter-by-event">' . esc_html__( 'Filter by event', 'events-certificates' ) . '</label>';
		echo '<select name="event" id="filter-by-event"><option value="">' . esc_html__( 'All events', 'events-certificates' ) . '</option>';
		foreach ( \EventsCertificate\Events\all() as $event ) {
			printf( '<option value="%d"%s>%s</option>', (int) $event->ID, selected( $this->query_event(), (int) $event->ID, false ), esc_html( get_the_title( $event ) ) );
		}
		echo '</select>';
		if ( count( $counts ) > 1 ) {
			echo '<label class="screen-reader-text" for="filter-by-role">' . esc_html__( 'Filter by role', 'events-certificates' ) . '</label>';
			echo '<select name="role" id="filter-by-role"><option value="">' . esc_html__( 'All roles', 'events-certificates' ) . '</option>';
			foreach ( array_intersect_key( $labels, $counts ) as $role => $label ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $role ), selected( $this->role_view(), $role, false ), esc_html( sprintf( '%s (%s)', $label, number_format_i18n( $counts[ $role ] ) ) ) );
			}
			echo '</select>';
		}
		if ( '' !== $this->view() ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( $this->view() ) . '">';
		}
		submit_button( __( 'Filter', 'events-certificates' ), '', 'filter_action', false, array( 'id' => 'evcert-people-query-submit' ) );
		echo '</div>';
	}

	/**
	 * Message when there is nobody.
	 */
	public function no_items() {
		esc_html_e( 'No participants yet. Add them from an event.', 'events-certificates' );
	}

	/**
	 * The event.
	 *
	 * @param array<string, mixed> $item Person.
	 * @return string
	 */
	protected function column_event( $item ) {
		$event = (int) $item['event_id'];
		if ( ! get_post( $event ) ) {
			// The event was deleted: the person stays, with no event to open.
			return '&mdash;';
		}

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				url(
					array(
						'event' => $event,
						'view'  => 'participants',
					)
				)
			),
			esc_html( get_the_title( $event ) )
		);
	}
}
