<?php
/**
 * `evcert tutorial-css`: writes the CSS the tutorial's drawings need, the CSS WordPress makes from the blocks'
 * layout and custom CSS attributes (the wp-container-* and wp-custom-css-* classes), into
 * assets/scss/_tutorial-step-image-blocks.scss. WordPress prints that CSS only on the site's public pages;
 * written here, under the drawing's own prefix, the drawing looks the same on the page, in the editor, in the
 * enlarged view and in any theme. Run it after changing a template (a test fails when the file is behind).
 *
 * Needs a WordPress with the plugin active; it runs each step in en_US and pt_BR (the language only changes the data).
 *
 * Usage, from the plugin folder:
 *   wp --require=bin/tutorial-css.php evcert tutorial-css
 *
 * Not shipped (bin/ is export-ignored).
 *
 * @package EventsCertificate
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Makes the drawings' block CSS.
 */
class Evcert_Tutorial_Css_Command {

	/**
	 * Write assets/scss/_tutorial-step-image-blocks.scss.
	 *
	 * @param array<int, string>   $args       Positional arguments (none).
	 * @param array<string, mixed> $assoc_args Options (none).
	 */
	public function __invoke( $args, $assoc_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- the WP-CLI signature.
		$ns      = '\EventsCertificate\TutorialStepImage\\';
		$classes = array();
		$custom  = array();
		foreach ( array_keys( call_user_func( $ns . 'steps' ) ) as $step ) {
			foreach ( array( 'en_US', 'pt_BR' ) as $locale ) {
				switch_to_locale( $locale );
				$markup = call_user_func( $ns . 'markup', $step, call_user_func( $ns . 'scene' ), call_user_func( $ns . 'labels' ) );
				restore_previous_locale();
				if ( '' === $markup ) {
					continue;
				}
				$html = do_blocks( $markup );
				foreach ( call_user_func( $ns . 'container_css_rules', $html ) as $container_rules ) {
					$classes[ call_user_func( $ns . 'container_class', $container_rules ) ] = $container_rules;
				}
				foreach ( call_user_func( $ns . 'custom_css_rules' ) as $block_rules ) {
					$custom[ call_user_func( $ns . 'custom_css_class', $block_rules ) ] = $block_rules;
				}
			}
		}
		if ( ! $classes && ! $custom ) {
			WP_CLI::error( 'No block rendered a container or custom CSS class.' );
		}

		// Named after their rules, so the same under any theme; in the order WordPress emits them (of two rules of the same weight, the later wins).
		$rules = array();
		ksort( $classes );
		foreach ( $classes as $class => $container_rules ) {
			foreach ( $container_rules as list( $suffix, $declarations ) ) {
				$rules[ '.evcert-tsi .evcert-tsi__fo .' . $class . ( '' !== $suffix ? ' ' . $suffix : '' ) . ' { ' . $declarations . ' }' ] = true;
			}
		}
		ksort( $custom );
		foreach ( $custom as $class => $block_rules ) {
			foreach ( $block_rules as list( $suffix, $declarations ) ) {
				// Kept weak, like WordPress's own ":root :where()", so the base rules can still be told apart.
				$rules[ '.evcert-tsi .evcert-tsi__fo :where(.' . $class . ')' . ( '' !== $suffix ? ' ' . $suffix : '' ) . ' { ' . $declarations . ' }' ] = true;
			}
		}
		$lines = array_keys( $rules );

		$out  = "// Made by `wp --require=bin/tutorial-css.php evcert tutorial-css`: do not edit.\n";
		$out .= "// The CSS WordPress makes from the layout and custom CSS attributes of the tutorial's blocks, under the drawing's prefix.\n\n";
		$out .= implode( "\n", $lines ) . "\n";
		$file = dirname( __DIR__ ) . '/assets/scss/_tutorial-step-image-blocks.scss';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a dev tool.
		file_put_contents( $file, $out );
		WP_CLI::success( count( $lines ) . ' rules for ' . ( count( $classes ) + count( $custom ) ) . ' classes in ' . basename( $file ) . '.' );
	}
}

WP_CLI::add_command( 'evcert tutorial-css', 'Evcert_Tutorial_Css_Command' );
