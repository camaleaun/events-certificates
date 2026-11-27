<?php
/**
 * The strict cleaning of a design (docs/decisions/0044): what is saved by the editor and what an import brings is checked against a list
 * of what a design may have, and the rest is removed. The cleaning itself is the `camaleaun/draw` package's (Clean); this is what
 * WordPress decides for it: which addresses are pictures of this site's Media Library, which font names are safe and which numbers are
 * attachments that are pictures.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Designs;

use EventsCertificate\Vendor\Camaleaun\Draw\Clean;

defined( 'ABSPATH' ) || exit;

/**
 * Whether an address is a file of this site's uploads folder (the Media Library): the only address a picture of a design may have.
 *
 * @param string $url Address.
 * @return bool
 */
function is_upload_url( $url ) {
	$base = preg_replace( '#^https?:#', '', (string) wp_get_upload_dir()['baseurl'] );
	$url  = preg_replace( '#^https?:#', '', $url );

	return '' !== $base && 0 === strpos( $url, $base . '/' ) && false === strpos( $url, '..' ) && 1 === preg_match( '#^//[A-Za-z0-9._~:/%@+-]+(\?[A-Za-z0-9._~%=&-]*)?$#', $url ) && ! preg_match( '#\.(php|phtml|phar|svg|html?|js)(\?|$)#i', $url );
}

/**
 * A design, strictly: only what a design may have. Null when it is not a design (no page, no elements, or too big). The page is
 * written with units (and no version).
 *
 * @param mixed                $design Design as the editor sent it or a file brought it.
 * @param array<string, mixed> $notes  What was removed, counted by kind: elements, pictures, paths (filled).
 * @return array<string, mixed>|null
 */
function clean( $design, array &$notes = array() ) {
	$cleaner = new Clean(
		array(
			'upload_url'          => __NAMESPACE__ . '\is_upload_url',
			'font_name'           => '\EventsCertificate\Fonts\clean_name',
			'attachment_is_image' => static function ( $id ) {
				return (bool) wp_attachment_is_image( $id );
			},
			// The signature element is of the certificate, not of the package: a place with only the color of its ink (ADR 0048).
			'types'               => array( 'signature' => array( 'color' ) ),
		)
	);

	// The page in points first, an A4 rounded to whole points as the A4 (snap_paper()): it is kept in millimeters, never as 842pt.
	if ( is_array( $design ) && isset( $design['page'] ) && is_array( $design['page'] ) ) {
		$design = \EventsCertificate\Render\page_in_points( $design );
	}

	return $cleaner->design( $design, $notes );
}
