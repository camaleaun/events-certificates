<?php
/**
 * Pattern: the certificate generator page, in blocks.
 *
 * Registered with this file's path (QuickGenerator\register_pattern()), so the page's blocks, and the four
 * tutorial drawings in it, are only built when the pattern is read (the inserter, the pattern's page), not on
 * every request.
 *
 * @package EventsCertificate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo \EventsCertificate\QuickGenerator\page_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- blocks markup, escaped where it is built.
