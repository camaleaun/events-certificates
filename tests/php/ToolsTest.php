<?php
/**
 * The Tools: actions that make again what the plugin makes once, never deleting what a person made.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Designs;
use EventsCertificate\Mine;
use EventsCertificate\QuickGenerator;
use EventsCertificate\Tools;
use EventsCertificate\VerifyPage;

/**
 * Each action is a function the Tools tab and WP-CLI both call.
 */
class ToolsTest extends Test_Case {

	/**
	 * Options and templates before the test, put back after.
	 *
	 * @var array<string, mixed>
	 */
	private $options = array();

	/**
	 * Templates there were.
	 *
	 * @var int[]
	 */
	private $known = array();

	/**
	 * Published templates the test set aside, put back after.
	 *
	 * @var int[]
	 */
	private $aside = array();

	/**
	 * Pages made by a test.
	 *
	 * @var int[]
	 */
	private $pages = array();

	/**
	 * An administrator, and the site as it is.
	 */
	protected function set_up() {
		parent::set_up();
		wp_set_current_user( 1 );
		foreach ( array( Designs\SITE_DESIGN_OPTION, Designs\QUICK_DESIGN_OPTION, VerifyPage\OPTION, Mine\OPTION, QuickGenerator\PAGE_OPTION ) as $option ) {
			$this->options[ $option ] = get_option( $option );
		}
		$this->known = $this->templates();
	}

	/**
	 * Puts the site back.
	 */
	protected function tear_down() {
		foreach ( array_diff( $this->templates(), $this->known ) as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->aside as $id ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			);
		}
		foreach ( $this->pages as $page ) {
			wp_delete_post( $page, true );
		}
		foreach ( $this->options as $option => $value ) {
			if ( false === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value, false );
			}
		}
		parent::tear_down();
	}

	/**
	 * Every template.
	 *
	 * @return int[]
	 */
	private function templates() {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'   => Designs\POST_TYPE,
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
	}

	/**
	 * A visitor cannot run an action, and an action that does not exist says so.
	 */
	public function test_only_an_administrator_runs_an_action() {
		wp_set_current_user( 0 );
		$this->assertSame( 'forbidden', Tools\run( 'cache' )['status'] );
		wp_set_current_user( 1 );
		$this->assertSame( 'unknown', Tools\run( 'nothing' )['status'] );
		$this->assertSame( array( 'quick-page', 'verify-page', 'mine-page', 'templates', 'tables', 'addresses', 'cache' ), array_keys( Tools\actions() ) );
	}

	/**
	 * The starter templates: only the missing ones are made, an edited one stays, and the default changes only when asked.
	 */
	public function test_the_templates_are_made_when_missing_and_nothing_is_overwritten() {
		// The site may have starters of its own: they are set aside, so the test sees a site that has none.
		foreach ( get_posts(
			array(
				'post_type'   => Designs\POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		) as $existing ) {
			$this->aside[] = (int) $existing;
			wp_update_post(
				array(
					'ID'          => (int) $existing,
					'post_status' => 'draft',
				)
			);
		}
		delete_option( Designs\SITE_DESIGN_OPTION );
		delete_option( Designs\QUICK_DESIGN_OPTION );
		$basic = Designs\site_design_id();
		wp_update_post(
			array(
				'ID'           => $basic,
				'post_content' => wp_slash( (string) wp_json_encode( array_replace_recursive( Designs\basic(), array( 'page' => array( 'safe' => 17 ) ) ) ) ),
			)
		);
		$meetup = Designs\quick_design_id();
		wp_delete_post( $meetup, true );

		$this->assertSame( 'done', Tools\run( 'templates' )['status'] );
		$this->assertNotSame( 0, Designs\starter_id( 'Meetup' ), 'Meetup is back.' );
		$this->assertNotSame( $meetup, Designs\starter_id( 'Meetup' ) );
		$this->assertSame( $basic, Designs\starter_id( 'Basic' ), 'Basic was there: not made again.' );
		$this->assertSame( '6mm', Designs\decode( (string) get_post_field( 'post_content', $basic ) )['page']['safe'], 'And not overwritten (17 pt, kept as 6 mm).' );
		$this->assertSame( $basic, Designs\site_design_id(), 'The default did not change.' );

		$this->assertSame( 'exists', Tools\run( 'templates' )['status'], 'Nothing to make.' );
		$this->assertSame( 'done', Tools\run( 'templates', array( 'make_default' => true ) )['status'] );
		$this->assertSame( Designs\starter_id( 'Basic' ), Designs\site_design_id() );
		$this->assertSame( Designs\starter_id( 'Meetup' ), Designs\quick_design_id() );
	}

	/**
	 * A page that is there is left alone unless asked; then it goes to the Trash and a new one takes its address.
	 */
	public function test_a_page_is_made_only_when_missing_or_asked() {
		$page = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Old check page',
				'post_name'    => 'tools-check',
				'post_content' => 'edited by hand',
			)
		);
		update_option( VerifyPage\OPTION, $page, false );
		$this->pages[] = $page;

		$this->assertSame( 'exists', Tools\run( 'verify-page' )['status'] );
		$this->assertSame( 'publish', get_post_status( $page ) );

		$done = Tools\run( 'verify-page', array( 'trash_existing' => true ) );
		$this->assertContains( $done['status'], array( 'done', 'failed' ) );
		$this->assertSame( 'trash', get_post_status( $page ), 'The old page went to the Trash, not away.' );
		$this->assertSame( 'edited by hand', get_post_field( 'post_content', $page ), 'With what was in it.' );
		if ( 'done' === $done['status'] ) {
			$new = VerifyPage\page_id();
			$this->assertNotSame( $page, $new );
			$this->pages[] = $new;
		}
	}

	/**
	 * The generator page goes to the Trash and a new one is made.
	 */
	public function test_the_generator_page_goes_to_the_trash() {
		if ( ! QuickGenerator\wanted() ) {
			$this->markTestSkipped( 'The generator page is only made where no integration is active.' );
		}
		$old = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Old generator page',
				'post_content' => 'edited by hand',
			)
		);
		update_option( QuickGenerator\PAGE_OPTION, $old, false );
		$this->pages[] = $old;
		$done          = Tools\run( 'quick-page' );

		$this->assertSame( 'done', $done['status'] );
		$this->assertSame( 'trash', get_post_status( $old ), 'The old page is in the Trash.' );
		$this->assertNotSame( $old, QuickGenerator\page_id() );
		$this->pages[] = QuickGenerator\page_id();
	}

	/**
	 * Addresses, tables and caches: they run, and the documents' pepper stays.
	 */
	public function test_tables_addresses_and_caches() {
		$pepper = get_option( 'evcert_doc_pepper' );
		$this->assertSame( 'done', Tools\run( 'tables' )['status'] );
		$this->assertSame( 'done', Tools\run( 'addresses' )['status'] );

		$uploads = wp_get_upload_dir();
		wp_mkdir_p( $uploads['basedir'] . '/evcert-share' );
		file_put_contents( $uploads['basedir'] . '/evcert-share/test.png', 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a file for the test.
		update_option( 'evcert_locale_names', array( 'x' ), false );
		$this->assertContains( $uploads['basedir'] . '/evcert-share/test.png', Tools\cache_files() );
		$this->assertSame( 'done', Tools\run( 'cache' )['status'] );
		$this->assertSame( array(), Tools\cache_files(), 'The caches are empty.' );
		$this->assertFalse( get_option( 'evcert_locale_names' ) );
		$this->assertSame( $pepper, get_option( 'evcert_doc_pepper' ), 'The pepper stays: the documents\' hashes depend on it.' );
	}

	/**
	 * The button: a wrong nonce does nothing, a right one runs the action and goes back to the tab.
	 */
	public function test_the_form_runs_an_action_and_goes_back_to_the_tab() {
		$location = '';
		$catch    = static function ( $to ) use ( &$location ) {
			$location = $to;
			throw new \RuntimeException( 'redirected' );
		};
		add_filter( 'wp_redirect', $catch );
		$_POST    = array( 'evcert_tool' => 'tables' );
		$_REQUEST = array(
			'_wpnonce'         => wp_create_nonce( 'evcert_tool_tables' ),
			'_wp_http_referer' => '',
		);
		try {
			Tools\handle();
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirected', $e->getMessage() );
		} finally {
			remove_filter( 'wp_redirect', $catch );
			$_POST    = array();
			$_REQUEST = array();
		}

		$this->assertStringContainsString( 'tab=tools', $location );
		$result = get_transient( 'evcert_tool_result_1' );
		delete_transient( 'evcert_tool_result_1' );
		$this->assertSame( 'done', $result['status'] );
		$this->assertSame( 'tables', $result['id'] );
	}

	/**
	 * The tab lists every action in a form of its own.
	 */
	public function test_the_tab_has_a_form_for_each_action() {
		ob_start();
		Tools\render();
		$html = (string) ob_get_clean();

		$this->assertSame( count( Tools\actions() ), substr_count( $html, 'name="evcert_tool"' ) );
		$this->assertStringContainsString( 'name="trash_existing"', $html );
		$this->assertStringContainsString( 'name="make_default"', $html );
	}
}
