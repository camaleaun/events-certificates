<?php
/**
 * Moves the pictures that older versions kept as data URIs into the Media Library (docs/decisions/0044).
 *
 *   studio wp eval-file wp-content/plugins/events-certificates/bin/move-pictures-to-media.php
 *       Shows what it would do and writes nothing.
 *   ... move-pictures-to-media.php yes
 *       Does it: a picture used in several places becomes one attachment; run again, nothing is made twice.
 *   ... move-pictures-to-media.php json old-export.json [new-export.zip]
 *       Turns an older export (JSON with data URIs) into a ZIP with the pictures as files, to import on another site.
 *
 * Not shipped (bin/ is export-ignored). Nothing is deleted before its attachment is made; the templates' snapshots are only counted.
 *
 * @package EventsCertificate
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped -- a command run by the site's owner.

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) ) {
	exit( "Run it with WP-CLI: wp eval-file bin/move-pictures-to-media.php\n" );
}
require_once __DIR__ . '/pictures-lib.php';

$words = isset( $args ) ? array_values( (array) $args ) : array();
if ( ( $words[0] ?? '' ) === 'json' ) {
	$from = (string) ( $words[1] ?? '' );
	if ( ! is_readable( $from ) ) {
		WP_CLI::error( 'Give the JSON file: json old-export.json [new-export.zip]' );
	}
	$bundle = json_decode( (string) file_get_contents( $from ), true );
	if ( ! is_array( $bundle ) ) {
		WP_CLI::error( 'That file is not JSON.' );
	}
	$out  = evcert_pictures_convert_bundle( $bundle );
	$to   = (string) ( $words[2] ?? preg_replace( '/\.json$/i', '', $from ) . '.zip' );
	$made = \EventsCertificate\Bundle\make_zip( $out['bundle'], $out['files'] );
	if ( is_wp_error( $made ) ) {
		WP_CLI::error( $made->get_error_message() );
	}
	copy( $made, $to );
	wp_delete_file( $made );
	foreach ( $out['files'] as $tmp ) {
		wp_delete_file( $tmp );
	}
	WP_CLI::success( sprintf( '%s: %d picture(s) as files.', $to, count( $out['files'] ) ) );
	return;
}

$apply = in_array( 'yes', $words, true ) || in_array( '--yes', $words, true ) || (bool) WP_CLI::get_config( 'yes' );
$sum   = evcert_pictures_move( $apply );
WP_CLI::log( sprintf( 'Places with a data URI: %d (%d different picture(s)).', $sum['found'], $sum['pictures'] ) );
if ( $apply ) {
	WP_CLI::log( sprintf( 'Moved: %d. Failed: %d.', $sum['moved'], $sum['failed'] ) );
} else {
	WP_CLI::log( 'Nothing was written. Run it again with the word yes to move them.' );
}
if ( $sum['snapshots'] ) {
	WP_CLI::log( sprintf( 'Templates kept with certificates (snapshots) that still have a data URI: %d (left as they are).', $sum['snapshots'] ) );
}
WP_CLI::success( $apply ? 'Done.' : 'Dry run.' );
