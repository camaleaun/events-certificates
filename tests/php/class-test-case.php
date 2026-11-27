<?php
/**
 * Base test case.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use Yoast\PHPUnitPolyfills\TestCases\TestCase as Polyfill_Test_Case;

/**
 * Runs in English whatever the site's language, with the default settings
 * whatever the site saved, and inside a database transaction rolled back
 * after the test, so nothing a test writes stays on the dev site.
 */
abstract class Test_Case extends Polyfill_Test_Case {

	/**
	 * Locale, settings and transaction.
	 */
	protected function set_up() {
		global $wpdb;

		parent::set_up();
		// The admin screens' tables ask for it (core notices "incorrect usage" when it is not loaded, which WP_DEBUG turns into errors).
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		switch_to_locale( 'en_US' );
		add_filter( 'pre_option_' . \EventsCertificate\Settings\OPTION, '\EventsCertificate\Settings\defaults', 1 );
		// Verification is off by default; the tests run with it on, the one about the default turns it off.
		add_filter( 'evcert_verification_enabled', '__return_true' );
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// Every test starts from a new site's templates (Basic the default, Meetup the Quick generator's), as on CI, whatever
		// templates the site this runs on has made (rolled back with the transaction).
		delete_option( \EventsCertificate\Designs\SITE_DESIGN_OPTION );
		delete_option( \EventsCertificate\Designs\QUICK_DESIGN_OPTION );
		\EventsCertificate\Designs\site_design_id();
	}

	/**
	 * Skips the test unless the run has one of these integrations active
	 * (none, camptix, gatherpress, both; composer test:all runs the four).
	 *
	 * @param string ...$allowed Integrations the test is for.
	 */
	protected function requires_integration( string ...$allowed ) {
		$now = \EventsCertificate\Environment\integration();
		if ( ! in_array( $now, $allowed, true ) ) {
			$this->markTestSkipped( "For the integration '" . implode( "' or '", $allowed ) . "'; this run has '$now'." );
		}
	}

	/**
	 * Whether pages are edited with the block editor on this site. Any plugin can say no (the Classic Editor, a page builder) by
	 * hooking `use_block_editor_for_post_type`, so ask the way the plugin does instead of looking for one. A test of what a page
	 * holds (blocks, or the shortcode) expects what this says.
	 *
	 * @return bool
	 */
	protected function block_editor_is_on() {
		require_once ABSPATH . 'wp-admin/includes/post.php';

		return (bool) use_block_editor_for_post_type( 'page' );
	}

	/**
	 * Takes the installed fonts out of the Font Library for the test, without touching their files: WordPress deletes the file of a face
	 * when its post is deleted (the post says which file in `_wp_font_face_file`), and the files belong to the site the test runs on,
	 * where the posts come back with the rollback. Without that meta the posts go and the files stay.
	 */
	protected function empty_font_library() {
		foreach ( get_posts(
			array(
				'post_type'   => array( 'wp_font_face', 'wp_font_family' ),
				'post_status' => 'any',
				'numberposts' => -1,
			)
		) as $post ) {
			delete_post_meta( $post->ID, '_wp_font_face_file' );
		}
		foreach ( get_posts(
			array(
				'post_type'   => array( 'wp_font_family', 'wp_font_face' ),
				'post_status' => 'any',
				'numberposts' => -1,
			)
		) as $post ) {
			wp_delete_post( $post->ID, true );
		}
		\EventsCertificate\Fonts\flush();
	}

	/**
	 * Undoes set_up().
	 */
	protected function tear_down() {
		global $wpdb;

		foreach ( $this->picture_ids as $picture_id ) {
			wp_delete_attachment( $picture_id, true ); // Takes the files away too; the rollback takes the rows.
		}
		$this->picture_ids = array();
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_flush();
		remove_filter( 'pre_option_' . \EventsCertificate\Settings\OPTION, '\EventsCertificate\Settings\defaults', 1 );
		remove_filter( 'evcert_verification_enabled', '__return_true' );
		restore_previous_locale();
		parent::tear_down();
	}

	/**
	 * Attachments a test made.
	 *
	 * @var int[]
	 */
	protected $picture_ids = array();

	/**
	 * A picture in the Media Library (a one pixel PNG), as the plugin keeps one: the ID of its attachment.
	 *
	 * @param string $name  File name.
	 * @param int    $width Width in pixels: another width makes another file (another hash).
	 * @return string The attachment ID.
	 */
	protected function make_picture( $name = 'evcert-test.png', $width = 1 ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$file = get_temp_dir() . wp_unique_filename( get_temp_dir(), $name );
		// phpcs:disable WordPress.WP.AlternativeFunctions -- a test picture made in the temporary folder.
		if ( $width > 1 && function_exists( 'imagecreatetruecolor' ) ) {
			imagepng( imagecreatetruecolor( $width, 1 ), $file );
		} else {
			file_put_contents( $file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- a test picture.
		}
		// phpcs:enable
		$uploads = wp_upload_dir();
		$target  = $uploads['path'] . '/' . wp_unique_filename( $uploads['path'], $name );
		copy( $file, $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- a test picture.
		wp_delete_file( $file );
		$id = (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => $name,
				'post_status'    => 'inherit',
			),
			$target
		);
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $target ) );
		$this->picture_ids[] = $id;

		return (string) $id;
	}
}
