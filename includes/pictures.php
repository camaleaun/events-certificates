<?php
/**
 * Pictures: a logo or a signature that is a raster picture is a file of the Media Library, and what the plugin keeps is the ID of its
 * attachment (docs/decisions/0044). A data URI is never saved, exported or imported; it exists only while a page or a PDF is made (the
 * Generator screen sends the pictures it draws that way).
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Pictures;

defined( 'ABSPATH' ) || exit;

/**
 * The kinds of picture that may be an attachment of a certificate (never an SVG: drawings are paths, docs/decisions/0005).
 */
const MIMES = array( 'image/png', 'image/jpeg', 'image/gif', 'image/webp' );

/**
 * What is kept: the ID of an attachment that is a PNG, JPEG, GIF or WebP picture, as a string; '' for anything else (a data URI, a URL,
 * an attachment that is not such a picture).
 *
 * @param mixed $value Raw value.
 * @return string
 */
function clean( $value ) {
	$id = is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ? (int) $value : 0;
	if ( $id < 1 || 'attachment' !== get_post_type( $id ) || ! in_array( get_post_mime_type( $id ), MIMES, true ) ) {
		return '';
	}

	return (string) $id;
}

/**
 * An inline raster picture (a data URI up to about 1.4 MB), or ''. Only for what is not kept: the picture a Generator screen draws.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function clean_runtime( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';

	return strlen( $value ) <= 1400000 && preg_match( '#^data:image/(png|jpe?g|gif|webp);base64,[A-Za-z0-9+/=]+$#', $value ) ? $value : '';
}

/**
 * The URL of the picture of an attachment (its large size when there is one, so a huge logo does not swell a PDF), or ''. Also gives
 * back a data URI that is valid, which is how the Generator screen sends a picture.
 *
 * @param mixed $value An attachment ID (kept) or a data URI (runtime).
 * @return string
 */
function src( $value ) {
	$id = clean( $value );
	if ( '' === $id ) {
		return clean_runtime( $value );
	}
	$url = wp_get_attachment_image_url( (int) $id, 'large' );

	return $url ? (string) $url : '';
}
