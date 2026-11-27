<?php
/**
 * The Certificates list: it looks like the posts list.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Admin;

/**
 * Filters in selects, the role as a link, the state beside the name, the date like a post's.
 */
class CertificatesListTest extends Test_Case {

	/**
	 * The table.
	 *
	 * @var Admin\List_Table
	 */
	private $table;

	/**
	 * Admin screens, not loaded outside wp-admin.
	 */
	protected function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-list-table.php';
		$this->table = new Admin\List_Table();
	}

	/**
	 * A protected method of the table.
	 *
	 * @param string $method Method.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	private function call( $method, array $args = array() ) {
		$reflection = new \ReflectionMethod( $this->table, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $this->table, $args );
	}

	/**
	 * A certificate row.
	 *
	 * @param array<string, string> $over Fields.
	 * @return array<string, mixed>
	 */
	private function item( array $over = array() ) {
		return $over + array(
			'id'             => 1,
			'code'           => '2611-ABCD-EFGH-JKLM',
			'name'           => 'Ana Lima',
			'email'          => 'ana@example.test',
			'role'           => 'speaker',
			'status'         => 'issued',
			'issued_at'      => '2026-11-18 15:00:00',
			'source'         => 'manual',
			'source_ref'     => '',
			'replaced_by'    => 0,
			'revoked_reason' => '',
			'context'        => 'event',
			'event_id'       => 0,
		);
	}

	/**
	 * The columns are the posts list's: name, code, role, event, workload, date; no status, template or source columns.
	 */
	public function test_columns() {
		$this->assertSame( array( 'cb', 'name', 'code', 'role', 'event', 'hours', 'issued_at' ), array_keys( $this->table->get_columns() ) );
	}

	/**
	 * The filters are selects with a Filter button, no chips.
	 */
	public function test_filters_are_selects() {
		ob_start();
		$this->call( 'extra_tablenav', array( 'top' ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<select name="role" id="filter-by-role">', $html );
		$this->assertStringContainsString( 'value="speaker"', $html );
		$this->assertStringContainsString( 'name="filter_action"', $html );
		$this->assertStringNotContainsString( 'evcert-role', $html );

		ob_start();
		$this->call( 'extra_tablenav', array( 'bottom' ) );
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * The role is a link that filters, like a post's tags; none is a dash.
	 */
	public function test_role_is_a_link() {
		$this->assertMatchesRegularExpression( '#<a href="[^"]*role=speaker[^"]*">Speaker</a>#', $this->call( 'column_role', array( $this->item() ) ) );
		$this->assertStringContainsString( '&#8212;', $this->call( 'column_role', array( $this->item( array( 'role' => '' ) ) ) ) );
	}

	/**
	 * A valid certificate has no state; a revoked one says so beside the name, like "— Draft"; Revoke asks through a data attribute.
	 */
	public function test_state_and_revoke() {
		$valid = $this->call( 'column_name', array( $this->item() ) );
		$this->assertStringNotContainsString( 'post-state', $valid );
		$this->assertStringContainsString( 'data-evcert-confirm=', $valid );
		$this->assertStringNotContainsString( 'onclick', $valid );

		$revoked = $this->call(
			'column_name',
			array(
				$this->item(
					array(
						'status'         => 'revoked',
						'revoked_reason' => 'Duplicate',
					)
				),
			)
		);
		$this->assertStringContainsString( '<span class="post-state">Revoked</span>', $revoked );
		$this->assertStringContainsString( 'Duplicate', $revoked );
		$this->assertStringNotContainsString( 'data-evcert-confirm=', $revoked, 'A revoked certificate cannot be revoked again.' );
	}

	/**
	 * A CampTix attendee that cannot be edited here gets no Attendee link, and no empty address is escaped (that was a PHP
	 * deprecation in the log).
	 */
	public function test_an_attendee_that_cannot_be_edited_has_no_link() {
		$post = wp_insert_post(
			array(
				'post_title'  => 'A fictional attendee',
				'post_status' => 'publish',
			)
		);
		$item = $this->item(
			array(
				'source'     => 'camptix',
				'source_ref' => (string) $post,
			)
		);
		wp_set_current_user( 0 );
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- a deprecation must fail this test.
			static function ( $no, $message ) {
				throw new \ErrorException( $message, 0, $no ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		);
		try {
			$html = $this->call( 'column_name', array( $item ) );
		} finally {
			restore_error_handler();
		}
		$this->assertStringNotContainsString( 'Attendee', $html );
	}

	/**
	 * The date column: the state, then the date and time.
	 */
	public function test_date_column() {
		$html = $this->call( 'column_issued_at', array( $this->item() ) );

		$this->assertStringStartsWith( 'Issued<br>', $html );
		$this->assertStringContainsString( ' at ', $html );
	}
}
