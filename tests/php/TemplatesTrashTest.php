<?php
/**
 * The templates list: the Trash can be opened, and a template comes back from it.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Designs;

/**
 * A trashed template is listed in the Trash, not among the templates, and goes back as a published one.
 */
class TemplatesTrashTest extends Test_Case {

	/**
	 * Trashed templates are the Trash's, not the list's.
	 */
	public function test_a_trashed_template_is_in_the_trash_list() {
		wp_set_current_user( 1 );
		$id = Designs\duplicate( 0, 'A fictional template' );
		$this->assertGreaterThan( 0, $id );
		wp_trash_post( $id );

		$this->assertContains( $id, wp_list_pluck( Designs\trashed_templates(), 'ID' ) );
		$this->assertNotContains( $id, wp_list_pluck( Designs\templates(), 'ID' ) );

		wp_untrash_post( $id );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);
		$this->assertContains( $id, wp_list_pluck( Designs\templates(), 'ID' ) );
		$this->assertNotContains( $id, wp_list_pluck( Designs\trashed_templates(), 'ID' ) );
	}

	/**
	 * The views name the Trash, with its count, only when there is something in it (or it is open).
	 */
	public function test_the_views_open_the_trash() {
		wp_set_current_user( 1 );
		require_once dirname( __DIR__, 2 ) . '/includes/class-templates-list-table.php';
		$table = new \EventsCertificate\Templates\Templates_List_Table();
		$views = ( new \ReflectionMethod( $table, 'get_views' ) );
		$views->setAccessible( true );
		$this->assertArrayHasKey( 'all', $views->invoke( $table ) );

		wp_trash_post( Designs\duplicate( 0, 'Another fictional template' ) );
		$shown = $views->invoke( $table );
		$this->assertArrayHasKey( 'trash', $shown );
		$this->assertStringContainsString( 'post_status=trash', $shown['trash'] );
	}
}
