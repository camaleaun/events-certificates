<?php
/**
 * The Events screen's list: each event with its date, place, participants
 * and certificates.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\EventsAdmin;

use EventsCertificate\Events;
use EventsCertificate\People;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Events list.
 */
class Events_List_Table extends \WP_List_Table {

	/**
	 * The months (YYYYMM) the listed events are in.
	 *
	 * @var array<string, bool>
	 */
	private $months = array();

	/**
	 * Setup.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'evcert_event',
				'plural'   => 'evcert_events',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox">',
			'title'        => __( 'Event', 'events-certificates' ),
			'date'         => __( 'Date', 'events-certificates' ),
			'location'     => __( 'Place', 'events-certificates' ),
			'participants' => __( 'Participants', 'events-certificates' ),
			'certificates' => __( 'Certificates', 'events-certificates' ),
		);
	}

	/**
	 * Whether the Trash is shown.
	 *
	 * @return bool
	 */
	private function in_trash() {
		return isset( $_GET['post_status'] ) && 'trash' === sanitize_key( wp_unslash( $_GET['post_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks a list.
	}

	/**
	 * The views over the list: all the events, and the Trash when it has some.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$counts = wp_count_posts( Events\POST_TYPE );
		$trash  = (int) ( $counts->trash ?? 0 );
		$views  = array(
			'all' => sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( url() ),
				$this->in_trash() ? '' : ' class="current" aria-current="page"',
				esc_html__( 'All', 'events-certificates' ),
				(int) ( $counts->publish ?? 0 )
			),
		);
		if ( $trash || $this->in_trash() ) {
			$views['trash'] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( url( array( 'post_status' => 'trash' ) ) ),
				$this->in_trash() ? ' class="current" aria-current="page"' : '',
				esc_html__( 'Trash', 'events-certificates' ),
				$trash
			);
		}

		return $views;
	}

	/**
	 * Bulk actions: to the Trash; in the Trash, restore or delete for good.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return $this->in_trash() ? array(
			'untrash' => __( 'Restore', 'events-certificates' ),
			'delete'  => __( 'Delete permanently', 'events-certificates' ),
		) : array(
			'trash' => __( 'Move to Trash', 'events-certificates' ),
		);
	}

	/**
	 * In the Trash, a button to empty it, next to the bulk actions.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		echo '<div class="alignleft actions">';
		if ( 'top' === $which && $this->months ) {
			$this->months_filter();
		}
		if ( $this->in_trash() && $this->has_items() ) {
			submit_button( __( 'Empty Trash', 'events-certificates' ), 'apply', 'top' === $which ? 'delete_all' : 'delete_all2', false, array( 'id' => 'top' === $which ? 'delete_all' : 'delete_all2' ) );
		}
		echo '</div>';
	}

	/**
	 * The date filter, like the posts list's: "All dates" and a month and a year for each month that has an event.
	 */
	private function months_filter() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a list filter.
		$chosen = isset( $_GET['m'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_GET['m'] ) ) ) : '';
		echo '<label for="filter-by-date" class="screen-reader-text">' . esc_html__( 'Filter by date', 'events-certificates' ) . '</label>';
		echo '<select name="m" id="filter-by-date"><option value="">' . esc_html__( 'All dates', 'events-certificates' ) . '</option>';
		foreach ( array_keys( $this->months ) as $month ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( (string) $month ),
				selected( (string) $chosen, (string) $month, false ),
				esc_html( mysql2date( 'F Y', substr( (string) $month, 0, 4 ) . '-' . substr( (string) $month, 4, 2 ) . '-01 00:00:00' ) )
			);
		}
		echo '</select>';
		submit_button( __( 'Filter', 'events-certificates' ), '', 'filter_action', false, array( 'id' => 'evcert-events-query-submit' ) );
	}

	/**
	 * The checkbox of a row.
	 *
	 * @param array<string, string> $item Event.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="evcert_events[]" value="%d"><label class="screen-reader-text">%s</label>', (int) $item['id'], esc_html( '' !== $item['title'] ? $item['title'] : __( '(no title)', 'events-certificates' ) ) );
	}

	/**
	 * Sortable columns: key => [orderby, descending first].
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'title' => array( 'title', false ),
			'date'  => array( 'date', true ),
		);
	}

	/**
	 * The primary column.
	 *
	 * @return string
	 */
	protected function get_default_primary_column_name() {
		return 'title';
	}

	/**
	 * Loads the page of events: searched, sorted (newest first by default) and paged.
	 */
	public function prepare_items() {
		$this->months          = array();
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'title' );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- list search and sorting.
		$search  = isset( $_REQUEST['s'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) : '';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'date';
		$order   = isset( $_GET['order'] ) && 'asc' === sanitize_key( wp_unslash( $_GET['order'] ) ) ? 'asc' : 'desc';
		// phpcs:enable
		$trash  = $this->in_trash();
		$events = array_filter(
			array_map(
				static function ( $id ) use ( $trash ) {
					return Events\get( $id, $trash );
				},
				wp_list_pluck( Events\all( $trash ? array( 'post_status' => 'trash' ) : array() ), 'ID' ),
			)
		);
		// The months the events are in, for the date filter (a month and a year, as the posts list has).
		foreach ( $events as $event ) {
			$month = str_replace( '-', '', substr( (string) $event['start'], 0, 7 ) );
			if ( 6 === strlen( $month ) ) {
				$this->months[ $month ] = true;
			}
		}
		krsort( $this->months );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a list filter.
		$chosen = isset( $_GET['m'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_GET['m'] ) ) ) : '';
		if ( 6 === strlen( (string) $chosen ) ) {
			$events = array_filter(
				$events,
				static function ( $event ) use ( $chosen ) {
					return str_replace( '-', '', substr( (string) $event['start'], 0, 7 ) ) === $chosen;
				}
			);
		}
		if ( '' !== $search ) {
			$events = array_filter(
				$events,
				static function ( $event ) use ( $search ) {
					return false !== stripos( $event['title'] . ' ' . $event['location'], $search );
				}
			);
		}
		usort(
			$events,
			static function ( $a, $b ) use ( $orderby, $order ) {
				$result = 'title' === $orderby ? strcasecmp( $a['title'], $b['title'] ) : strcmp( $a['start'], $b['start'] );

				return 'asc' === $order ? $result : -$result;
			}
		);
		$per_page    = $this->get_items_per_page( 'evcert_events_per_page', 20 );
		$this->items = array_slice( $events, ( $this->get_pagenum() - 1 ) * $per_page, $per_page );
		$this->set_pagination_args(
			array(
				'total_items' => count( $events ),
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * When there are none.
	 */
	public function no_items() {
		if ( $this->in_trash() ) {
			esc_html_e( 'No events in the Trash.', 'events-certificates' );
			return;
		}
		esc_html_e( 'No events yet. Add one, then its participants: who came gets a certificate.', 'events-certificates' );
	}

	/**
	 * Name, with Edit and Trash.
	 *
	 * @param array<string, string> $item Event.
	 * @return string
	 */
	protected function column_title( $item ) {
		if ( $this->in_trash() ) {
			$act = static function ( $change ) use ( $item ) {
				return wp_nonce_url( url( array( 'evcert_' . $change => (int) $item['id'] ) ), 'evcert_event_' . $change . '_' . (int) $item['id'] );
			};

			return sprintf( '<strong>%s</strong>', esc_html( '' !== $item['title'] ? $item['title'] : __( '(no title)', 'events-certificates' ) ) ) . $this->row_actions(
				array(
					'untrash' => sprintf( '<a href="%s">%s</a>', esc_url( $act( 'untrash' ) ), esc_html__( 'Restore', 'events-certificates' ) ),
					'delete'  => sprintf( '<a href="%s" class="submitdelete">%s</a>', esc_url( $act( 'delete' ) ), esc_html__( 'Delete permanently', 'events-certificates' ) ),
				)
			);
		}
		$edit    = url( array( 'event' => (int) $item['id'] ) );
		$actions = array(
			'edit'         => sprintf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html__( 'Edit', 'events-certificates' ) ),
			'participants' => sprintf( '<a href="%s">%s</a>', esc_url( participants_url( (int) $item['id'] ) ), esc_html__( 'Participants', 'events-certificates' ) ),
			'trash'        => sprintf( '<a href="%s" class="submitdelete">%s</a>', esc_url( wp_nonce_url( url( array( 'evcert_trash' => (int) $item['id'] ) ), 'evcert_event_trash_' . (int) $item['id'] ) ), esc_html__( 'Trash', 'events-certificates' ) ),
		);

		return sprintf( '<strong><a class="row-title" href="%s">%s</a></strong>', esc_url( $edit ), esc_html( '' !== $item['title'] ? $item['title'] : __( '(no title)', 'events-certificates' ) ) ) . $this->row_actions( $actions );
	}

	/**
	 * Date and times.
	 *
	 * @param array<string, string> $item Event.
	 * @return string
	 */
	protected function column_date( $item ) {
		$start = strtotime( (string) Events\utc( $item['start'] ) . ' UTC' );
		$end   = strtotime( (string) Events\utc( $item['end'] ) . ' UTC' );

		return esc_html( wp_date( (string) get_option( 'date_format' ), $start ) ) . '<br><span class="description">' . esc_html( wp_date( (string) get_option( 'time_format' ), $start ) . ( $end > $start ? '–' . wp_date( (string) get_option( 'time_format' ), $end ) : '' ) ) . '</span>';
	}

	/**
	 * Place.
	 *
	 * @param array<string, string> $item Event.
	 * @return string
	 */
	protected function column_location( $item ) {
		return '' !== $item['location'] ? esc_html( $item['location'] ) : '—';
	}

	/**
	 * Participants: registered, attended.
	 *
	 * @param array<string, string> $item Event.
	 * @return string
	 */
	protected function column_participants( $item ) {
		$counts = People\counts( (int) $item['id'] );

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( participants_url( (int) $item['id'] ) ),
			esc_html(
				sprintf(
					/* translators: 1: number of participants, 2: number who attended. */
					__( '%1$s (%2$s attended)', 'events-certificates' ),
					number_format_i18n( $counts['all'] ),
					number_format_i18n( $counts['attended'] )
				)
			)
		);
	}

	/**
	 * Certificates issued.
	 *
	 * @param array<string, string> $item Event.
	 * @return string
	 */
	protected function column_certificates( $item ) {
		return esc_html( number_format_i18n( People\counts( (int) $item['id'] )['certified'] ) );
	}
}
