<?php
/**
 * Block render: one step of the Meetup tutorial, drawn by the server.
 *
 * @package EventsCertificate
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

// render() returns the drawing already filtered by wp_kses().
echo \EventsCertificate\TutorialStepImage\render( isset( $attributes['step'] ) ? (int) $attributes['step'] : 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
