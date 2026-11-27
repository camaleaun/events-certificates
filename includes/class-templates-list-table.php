<?php
/**
 * The Templates screen's list, like the Certificates list (WP_List_Table):
 * the templates in the order they are tried, what each applies to, how many
 * certificates it draws now.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Templates;

use EventsCertificate\AdminApp;
use EventsCertificate\Designs;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Templates list.
 */
class Templates_List_Table extends \WP_List_Table {

	/**
	 * Certificates each template draws.
	 *
	 * @var array<int, int>
	 */
	private $usage = array();

	/**
	 * Setup.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'template',
				'plural'   => 'templates',
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
			'cb'      => '<input type="checkbox">',
			'name'    => __( 'Name', 'events-certificates' ),
			'applies' => __( 'Applies to', 'events-certificates' ),
			'used'    => __( 'Certificates', 'events-certificates' ),
		);
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
		if ( $this->in_trash() && $this->has_items() ) {
			echo '<div class="alignleft actions">';
			submit_button( __( 'Empty Trash', 'events-certificates' ), 'apply', 'top' === $which ? 'delete_all' : 'delete_all2', false, array( 'id' => 'top' === $which ? 'delete_all' : 'delete_all2' ) );
			echo '</div>';
		}
	}

	/**
	 * The checkbox of a row. The default template has none: it is never trashed.
	 *
	 * @param \WP_Post $item Template.
	 * @return string
	 */
	protected function column_cb( $item ) {
		if ( ! $this->in_trash() && $this->is_default( $item ) ) {
			return '';
		}

		return sprintf( '<input type="checkbox" name="evcert_templates[]" value="%d"><label class="screen-reader-text">%s</label>', (int) $item->ID, esc_html( '' !== $item->post_title ? $item->post_title : __( '(no title)', 'events-certificates' ) ) );
	}

	/**
	 * The primary column.
	 *
	 * @return string
	 */
	protected function get_default_primary_column_name() {
		return 'name';
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
	 * The views over the list: all the templates, and the Trash when it has some.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$counts = wp_count_posts( Designs\POST_TYPE );
		$all    = (int) ( $counts->publish ?? 0 );
		$trash  = (int) ( $counts->trash ?? 0 );
		$views  = array(
			'all' => sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( url() ),
				$this->in_trash() ? '' : ' class="current" aria-current="page"',
				esc_html__( 'All', 'events-certificates' ),
				$all
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
	 * Loads the templates, in the order they are tried (or the ones in the Trash).
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'name' );
		$this->items           = $this->in_trash() ? Designs\trashed_templates() : Designs\templates();
		$this->usage           = usage();
		$this->set_pagination_args(
			array(
				'total_items' => count( $this->items ),
				'per_page'    => max( 1, count( $this->items ) ),
			)
		);
	}

	/**
	 * Whether a template is the default one.
	 *
	 * @param \WP_Post $item Template.
	 * @return bool
	 */
	private function is_default( $item ) {
		return Designs\site_design_id() === (int) $item->ID;
	}

	/**
	 * Whether a template is the one the Quick generator draws.
	 *
	 * @param \WP_Post $item Template.
	 * @return bool
	 */
	private function is_quick( $item ) {
		return Designs\quick_design_id() === (int) $item->ID;
	}

	/**
	 * Name, with the row actions.
	 *
	 * @param \WP_Post $item Template.
	 * @return string
	 */
	protected function column_name( $item ) {
		if ( $this->in_trash() ) {
			$actions = array(
				'untrash' => sprintf( '<a href="%s">%s</a>', esc_url( action_url( 'untrash', $item->ID ) ), esc_html__( 'Restore', 'events-certificates' ) ),
				'delete'  => sprintf( '<a href="%s" class="submitdelete">%s</a>', esc_url( action_url( 'delete', $item->ID ) ), esc_html__( 'Delete permanently', 'events-certificates' ) ),
			);

			return sprintf( '<strong>%s</strong>%s', esc_html( '' !== $item->post_title ? $item->post_title : __( '(no title)', 'events-certificates' ) ), $this->row_actions( $actions ) );
		}
		$edit    = AdminApp\available() ? AdminApp\url( $item->ID ) : '';
		$actions = array();
		if ( $edit ) {
			$actions['edit'] = sprintf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html__( 'Edit', 'events-certificates' ) );
		}
		$actions['duplicate'] = sprintf( '<a href="%s">%s</a>', esc_url( action_url( 'duplicate', $item->ID ) ), esc_html__( 'Duplicate', 'events-certificates' ) );
		if ( ! $this->is_quick( $item ) ) {
			$actions['quick'] = sprintf( '<a href="%s">%s</a>', esc_url( action_url( 'quick', $item->ID ) ), esc_html__( 'Use in the Quick generator', 'events-certificates' ) );
		}
		if ( ! $this->is_default( $item ) ) {
			$actions['default'] = sprintf( '<a href="%s">%s</a>', esc_url( action_url( 'default', $item->ID ) ), esc_html__( 'Make default', 'events-certificates' ) );
			$actions['up']      = sprintf( '<a href="%s" aria-label="%s">↑</a>', esc_url( action_url( 'up', $item->ID ) ), esc_attr__( 'Move up', 'events-certificates' ) );
			$actions['down']    = sprintf( '<a href="%s" aria-label="%s">↓</a>', esc_url( action_url( 'down', $item->ID ) ), esc_attr__( 'Move down', 'events-certificates' ) );
			$actions['trash']   = sprintf( '<a href="%s" class="submitdelete">%s</a>', esc_url( action_url( 'trash', $item->ID ) ), esc_html__( 'Trash', 'events-certificates' ) );
		}
		$title = '' !== $item->post_title ? $item->post_title : __( '(no title)', 'events-certificates' );

		return sprintf(
			'<strong>%s%s</strong>%s',
			$edit ? sprintf( '<a class="row-title" href="%s">%s</a>', esc_url( $edit ), esc_html( $title ) ) : esc_html( $title ),
			$this->states( $item ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * What a template is used for beyond its roles: " — Default, Used by the Quick generator".
	 *
	 * @param \WP_Post $item Template.
	 * @return string
	 */
	private function states( $item ) {
		$states = array();
		if ( $this->is_default( $item ) ) {
			$states[] = esc_html__( 'Default', 'events-certificates' );
		}
		if ( $this->is_quick( $item ) ) {
			$states[] = esc_html__( 'Used by the Quick generator', 'events-certificates' );
		}

		return $states ? ' — <span class="post-state">' . implode( ', ', $states ) . '</span>' : '';
	}

	/**
	 * The roles it applies to.
	 *
	 * @param \WP_Post $item Template.
	 * @return string
	 */
	protected function column_applies( $item ) {
		if ( $this->is_default( $item ) ) {
			return esc_html__( 'Everyone else', 'events-certificates' );
		}
		$labels = Designs\role_labels();
		$roles  = array_map(
			static function ( $role ) use ( $labels ) {
				return $labels[ $role ];
			},
			Designs\roles( $item->ID )
		);

		if ( ! $roles && $this->is_quick( $item ) ) {
			return esc_html__( 'The Quick generator', 'events-certificates' );
		}

		return $roles ? esc_html( implode( ', ', $roles ) ) : '<span class="description">' . esc_html__( 'Nobody yet (choose roles in the editor)', 'events-certificates' ) . '</span>';
	}

	/**
	 * Certificates it draws now.
	 *
	 * @param \WP_Post $item Template.
	 * @return string
	 */
	protected function column_used( $item ) {
		return esc_html( number_format_i18n( $this->usage[ (int) $item->ID ] ?? 0 ) );
	}

	/**
	 * Empty list.
	 */
	public function no_items() {
		if ( $this->in_trash() ) {
			esc_html_e( 'No templates in the Trash.', 'events-certificates' );
			return;
		}
		esc_html_e( 'No templates yet.', 'events-certificates' );
	}
}
