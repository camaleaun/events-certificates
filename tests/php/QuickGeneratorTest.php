<?php
/**
 * The generator page: one block markup, drawn by the page, the shortcode and the one-block version.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\QuickGenerator;

/**
 * The shortcode is the classic counterpart of the page: it renders the same blocks.
 */
class QuickGeneratorTest extends Test_Case {

	/**
	 * The hero's heading is the page's title, a Post Title block, only when asked.
	 */
	public function test_the_title_block_is_only_there_when_asked() {
		$this->assertStringContainsString( 'wp:post-title', QuickGenerator\page_content() );
		$this->assertStringNotContainsString( 'wp:post-title', QuickGenerator\page_content( array(), false ) );
		$this->assertStringNotContainsString( '<h1', QuickGenerator\page_content() );
	}

	/**
	 * The settings of the shortcode go to the blocks that have them, in their comments.
	 */
	public function test_settings_go_to_the_blocks() {
		$content = QuickGenerator\page_content(
			array(
				'community' => 'WP Curitiba',
				'city'      => 'Curitiba, PR',
				'signName'  => 'Ana Lima',
			)
		);

		$this->assertStringContainsString( '<!-- wp:events-certificates/event-info {"community":"WP Curitiba"} /-->', $content );
		$this->assertStringContainsString( '<!-- wp:events-certificates/event-schedule {"city":"Curitiba, PR"} /-->', $content );
		$this->assertStringContainsString( '<!-- wp:events-certificates/signature {"signName":"Ana Lima"} /-->', $content );
		$this->assertStringContainsString( '<!-- wp:events-certificates/people-list /-->', $content );
	}

	/**
	 * The shortcode draws the whole app with its blocks, with the settings given.
	 */
	public function test_the_shortcode_renders_the_blocks() {
		$html = QuickGenerator\render_shortcode( array( 'community' => 'WP Curitiba' ) );

		$this->assertStringContainsString( 'evcert-app', $html );
		$this->assertStringContainsString( 'tut-step', $html );
		$this->assertStringContainsString( 'class="panel"', $html );
		$this->assertStringContainsString( 'WP Curitiba', $html );
		$this->assertStringNotContainsString( 'wp-block-post-title', $html, 'The theme prints the title of a page the shortcode is in.' );
	}

	/**
	 * A custom accent color goes on the app's wrapper.
	 */
	public function test_the_accent_color_goes_on_the_wrapper() {
		$html = QuickGenerator\render_shortcode( array( 'accent' => '#cc0000' ) );

		$this->assertMatchesRegularExpression( '/<div[^>]*class="[^"]*evcert-app[^"]*"[^>]*style="--accent:#cc0000"|<div[^>]*style="--accent:#cc0000"[^>]*class="[^"]*evcert-app/', $html );
	}

	/**
	 * The one-block version draws the same as the shortcode.
	 */
	public function test_the_one_block_version_is_the_same_render() {
		$block = QuickGenerator\render_quick_generator( array( 'community' => 'WP Curitiba' ) );

		// The drawings of the tutorial have an id of their own on each print (wp_unique_id), so the two differ only there.
		$same = static fn ( $html ) => preg_replace( '/evcert-tsi-\d+/', 'evcert-tsi-N', $html );

		$this->assertSame( $same( QuickGenerator\render_shortcode( array( 'community' => 'WP Curitiba' ) ) ), $same( $block ) );
	}

	/**
	 * With the Classic Editor the page's content is the shortcode, not blocks.
	 */
	public function test_pages_use_the_shortcode_without_the_block_editor() {
		require_once ABSPATH . 'wp-admin/includes/post.php';

		// What the site's editor says (a plugin such as the Classic Editor may turn the block editor off), then off for sure.
		$this->assertSame( $this->block_editor_is_on(), QuickGenerator\blocks_for_page() );
		add_filter( 'use_block_editor_for_post_type', '__return_false' );
		$this->assertFalse( QuickGenerator\blocks_for_page() );
		remove_filter( 'use_block_editor_for_post_type', '__return_false' );
	}

	/**
	 * The page template is a block template of the plugin, for pages; the page's body class follows the template.
	 */
	public function test_the_page_template_is_registered() {
		// The hooks of init have run before the tests start, and a template registers once.
		QuickGenerator\register_template();
		$template = \WP_Block_Templates_Registry::get_instance()->get_registered( QuickGenerator\TEMPLATE_NAME );

		$this->assertNotNull( $template );
		$this->assertContains( 'page', (array) $template->post_types );
		$this->assertStringContainsString( 'wp:post-content', $template->content );
	}

	/**
	 * Around the app, WordPress does not put an inner container in the groups (classic themes size what is in it);
	 * the rest of the content keeps it.
	 */
	public function test_the_inner_container_is_left_out_only_around_the_app() {
		$restore = 'wp_restore_group_inner_container';

		QuickGenerator\drop_inner_container( '<div class="wp-block-group">Another content</div>' );
		$this->assertNotFalse( has_filter( 'render_block_core/group', $restore ) );

		QuickGenerator\drop_inner_container( '<!-- wp:group {"className":"evcert-app"} -->' );
		$this->assertFalse( has_filter( 'render_block_core/group', $restore ) );

		QuickGenerator\restore_inner_container( '' );
		$this->assertNotFalse( has_filter( 'render_block_core/group', $restore ) );

		// Removed by someone else on purpose: it is not put back.
		remove_filter( 'render_block_core/group', $restore, 10 );
		QuickGenerator\restore_inner_container( '' );
		$this->assertFalse( has_filter( 'render_block_core/group', $restore ) );
		add_filter( 'render_block_core/group', $restore, 10, 2 );
	}

	/**
	 * The public generator draws the Quick generator's template (the one the server draws), with the site's formats.
	 */
	public function test_the_app_gets_the_quick_generators_design_and_the_sites_formats() {
		$config = QuickGenerator\config();

		$this->assertSame( \EventsCertificate\Designs\get( \EventsCertificate\Designs\quick_design_id() ), $config['design'] );
		$this->assertNotEmpty( $config['design']['elements'] );
		$this->assertSame( \EventsCertificate\Render\phrases(), $config['phrases'] );
		$this->assertSame( (string) get_option( 'date_format' ), $config['dateFormat'] );
		$this->assertSame( (string) get_option( 'time_format' ), $config['timeFormat'] );
		$this->assertArrayHasKey( 'withSpan', $config['i18n'] );
	}

	/**
	 * The generator page is made only where no event plugin brings its own way to issue certificates, and the
	 * Certificates screen has no button to it.
	 */
	public function test_page_only_without_integrations_and_no_button() {
		$this->assertSame( 'none' === \EventsCertificate\Environment\integration(), QuickGenerator\wanted() );
		$this->assertFalse( has_action( 'evcert_certificates_title_actions', 'EventsCertificate\QuickGenerator\render_button' ) );
		$this->assertFalse( function_exists( 'EventsCertificate\QuickGenerator\render_button' ) );
	}

	/**
	 * Recreating the page moves the old one to the Trash and makes a new one in the site's language; only an administrator can.
	 */
	public function test_the_page_is_recreated_in_the_sites_language() {
		if ( ! QuickGenerator\wanted() ) {
			$this->markTestSkipped( 'The generator page is only made where no integration is active.' );
		}
		$old = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Old, in the first language',
				'post_content' => 'edited by hand',
			)
		);
		update_option( QuickGenerator\PAGE_OPTION, $old );

		wp_set_current_user( 0 );
		$this->assertSame( 0, QuickGenerator\recreate_page(), 'A visitor cannot.' );
		$this->assertNotNull( get_post( $old ), 'The page is still there.' );

		wp_set_current_user(
			wp_insert_user(
				array(
					'user_login' => 'evcert_page_admin',
					'user_pass'  => wp_generate_password(),
					'role'       => 'administrator',
				)
			)
		);
		$new = QuickGenerator\recreate_page();

		$this->assertGreaterThan( 0, $new );
		$this->assertNotSame( $old, $new );
		$this->assertSame( 'trash', get_post_status( $old ), 'The old page is in the Trash, not deleted.' );
		$this->assertSame( $new, (int) get_option( QuickGenerator\PAGE_OPTION ) );
		// Blocks where the block editor is on; the shortcode where a plugin (the Classic Editor) turned it off.
		$this->assertStringContainsString( $this->block_editor_is_on() ? 'wp:events-certificates/tutorial-step-image' : '[evcert_quick_generator]', (string) get_post_field( 'post_content', $new ), 'The new page is the generator, as it is now.' );
		$this->assertArrayHasKey( 'evcert-quick-page', QuickGenerator\action_links( array() ), 'The link is on the plugin row.' );
	}

	/**
	 * The generator pattern is a file, so the page's blocks (and the tutorial drawings in them) are built when the
	 * pattern is read, not on every request.
	 */
	public function test_the_pattern_is_built_when_it_is_read() {
		$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( 'events-certificates/generator' );

		$this->assertIsArray( $pattern );
		$this->assertFileExists( $pattern['filePath'] );
		$this->assertStringContainsString( 'wp:events-certificates/tutorial-step-image', $pattern['content'], 'Reading the pattern builds the page.' );
	}

	/**
	 * The tutorial's steps carry Meetup's own words from its labels, each in the language Meetup shows it in.
	 */
	public function test_the_tutorial_names_meetups_own_labels() {
		$labels = \EventsCertificate\TutorialStepImage\labels();
		$steps  = QuickGenerator\tutorial_data()['steps'];

		$this->assertCount( 4, $steps );
		$this->assertStringContainsString( $labels['past_events'], $steps[0]['text'] );
		$this->assertStringContainsString( $labels['attendees'], $steps[1]['title'] );
		$this->assertStringContainsString( $labels['duplicate'] . ', ' . $labels['contact'], $steps[1]['text'] );
		$this->assertStringContainsString( $labels['manage_en'], $steps[2]['title'] );
		$this->assertStringContainsString( $labels['attendees_en'], $steps[2]['text'] );
		$this->assertStringContainsString( $labels['attendee_info'], $steps[3]['text'] );
	}
}
