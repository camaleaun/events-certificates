<?php
/**
 * Plugin Name: Events Certificates: tutorial scene (development only)
 * Description: /?evcert_tsi=<step>&locale=<pt_BR|en_US> prints one step of the tutorial's pictures alone, at 1:1, for the pixel comparison; add &flat=1 for the picture at the top left on white (what the pixel comparison takes; by default it is centered on black, like the original's image on GitHub), &noframe=1 to take the red frame off, or &source=1 for the step's block markup, with its words and data in place, as plain text to paste into a page or a post (to debug or edit it). Copy this file to wp-content/mu-plugins/ of the Studio site; never ship it.
 *
 * @package EventsCertificate
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'template_redirect',
	static function () {
		if ( ! isset( $_GET['evcert_tsi'] ) || ! current_user_can( 'manage_options' ) || ! defined( 'EventsCertificate\VERSION' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a development page for an administrator.
			return;
		}
		$step   = absint( $_GET['evcert_tsi'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$locale = isset( $_GET['locale'] ) ? sanitize_text_field( wp_unslash( $_GET['locale'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$steps  = EventsCertificate\TutorialStepImage\steps();
		if ( ! isset( $steps[ $step ] ) ) {
			wp_die( 'No such step.' );
		}
		nocache_headers();
		if ( isset( $_GET['source'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$switched = null !== $locale && determine_locale() !== $locale && switch_to_locale( $locale );
			$markup   = EventsCertificate\TutorialStepImage\markup( $step, EventsCertificate\TutorialStepImage\scene(), EventsCertificate\TutorialStepImage\labels() );
			if ( $switched ) {
				restore_previous_locale();
			}
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block markup as text, for a developer.
			exit;
		}
		wp_enqueue_style( EventsCertificate\TutorialStepImage\STYLE );
		wp_enqueue_script( EventsCertificate\TutorialStepImage\VIEW );
		$width = (float) ( $steps[ $step ]['pixels'][0] ?? $steps[ $step ]['width'] );
		?>
<!doctype html>
<html lang="<?php echo esc_attr( str_replace( '_', '-', $locale ? $locale : get_locale() ) ); ?>">
<head>
<meta charset="utf-8">
<title>Tutorial step <?php echo (int) $step; ?></title>
		<?php wp_head(); ?>
<style>
	html, body { margin: 0 !important; background: #fff; }
	#wpadminbar { display: none !important; }
	<?php echo isset( $_GET['noframe'] ) ? '.evcert-tsi-frame { display: none !important; }' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.EscapeOutput.OutputNotEscaped -- a development page. ?>
	<?php echo isset( $_GET['overflow'] ) ? 'body { padding: 160px !important; } .evcert-tsi, .evcert-tsi__open, .evcert-tsi__svg { overflow: visible !important; max-width: none !important; }' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.EscapeOutput.OutputNotEscaped -- a development page. ?>
	<?php echo ( isset( $_GET['flat'] ) || isset( $_GET['overflow'] ) ) ? '' : 'html { background: #0e0e0e; } body { min-height: 100vh; display: grid; place-items: center; background: #0e0e0e !important; }'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.EscapeOutput.OutputNotEscaped -- a development page. ?>
	<?php echo apply_filters( 'evcert_tsi_scene_css', '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a development page: CSS from a file of the developer's own (the typeface), outside this repository. ?>
	.evcert-tsi { width: <?php echo esc_html( EventsCertificate\TutorialStepImage\num( $width ) ); ?>px; }
</style>
</head>
<body>
		<?php echo isset( $_GET['noframe'] ) ? preg_replace( '#<rect[^>]*stroke="\#e00"[^>]*>#', '', EventsCertificate\TutorialStepImage\render( $step, $locale ) ) : EventsCertificate\TutorialStepImage\render( $step, $locale ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.EscapeOutput.OutputNotEscaped -- filtered by wp_kses() in render(), a development page. WordPress.Security.EscapeOutput.OutputNotEscaped -- filtered by wp_kses() in render(). ?>
		<?php wp_footer(); ?>
</body>
</html>
		<?php
		exit;
	}
);
