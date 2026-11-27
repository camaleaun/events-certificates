<?php
/**
 * Restrictions: what a network (WordPress.org's, a WordCamp network, any multisite) may switch off for security, with no screen in the
 * settings: a constant, an option and filters (docs/decisions/0044).
 *
 * The features are the ones that take data in or out of the site: exporting and importing, ZIP packages (which carry fonts and pictures),
 * fonts, pictures of the Media Library and the vector drawings (SVG as paths). With one off, an export is a JSON without that kind of
 * data, and an import leaves it out and says so: a template arrives with empty places to fill by hand.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Restrictions;

defined( 'ABSPATH' ) || exit;

/**
 * The features, in the order the documentation lists them.
 */
const FEATURES = array( 'export', 'import', 'zip', 'fonts', 'pictures', 'vectors' );

/**
 * The option that holds the choices (feature => bool): no field in the settings, set with `wp option update`.
 */
const OPTION = 'evcert_restrictions';

/**
 * What each feature is, for the messages.
 *
 * @return array<string, string>
 */
function labels() {
	return array(
		'export'   => __( 'Export', 'events-certificates' ),
		'import'   => __( 'Import', 'events-certificates' ),
		'zip'      => __( 'ZIP packages', 'events-certificates' ),
		'fonts'    => __( 'Fonts in packages', 'events-certificates' ),
		'pictures' => __( 'Pictures in packages', 'events-certificates' ),
		'vectors'  => __( 'Vector drawings (SVG paths) in packages', 'events-certificates' ),
	);
}

/**
 * What is allowed when nothing says otherwise: everything on a single site; on a network the features that carry files or drawings in
 * and out are for super administrators only.
 *
 * @param bool|null $multisite Whether it is a network (null: this site).
 * @param bool|null $super     Whether the user is a super administrator (null: the current user).
 * @return array<string, bool>
 */
function defaults( $multisite = null, $super = null ) {
	$allowed = array_fill_keys( FEATURES, true );
	if ( null === $multisite ? is_multisite() : $multisite ) {
		foreach ( array( 'zip', 'fonts', 'pictures', 'vectors' ) as $feature ) {
			$allowed[ $feature ] = null === $super ? is_super_admin() : $super;
		}
	}

	return $allowed;
}

/**
 * Takes the features out of a value someone gave (an array feature => bool); what is not a feature is ignored.
 *
 * @param mixed $given Value.
 * @return array<string, bool>
 */
function only_features( $given ) {
	$out = array();
	foreach ( is_array( $given ) ? $given : array() as $feature => $value ) {
		if ( in_array( $feature, FEATURES, true ) ) {
			$out[ $feature ] = (bool) $value;
		}
	}

	return $out;
}

/**
 * Every feature with whether it is allowed: the defaults, then the option, then the constant EVCERT_RESTRICTIONS, then the filter
 * `evcert_restrictions` (the last word is the filter's).
 *
 * @return array<string, bool>
 */
function all() {
	$map = only_features( get_option( OPTION, array() ) ) + defaults();
	if ( defined( 'EVCERT_RESTRICTIONS' ) ) {
		$map = only_features( EVCERT_RESTRICTIONS ) + $map;
	}
	/**
	 * Filters the features a network allows or restricts (feature => bool). There is no screen for this: it is for the network's
	 * administrator, in `wp-config.php` or a must-use plugin.
	 *
	 * @param array<string, bool> $map Features: export, import, zip, fonts, pictures, vectors.
	 */
	$map = only_features( apply_filters( 'evcert_restrictions', $map ) ) + $map;

	return $map;
}

/**
 * Whether a feature is allowed. Fonts and pictures travel in a ZIP, so they need it too.
 *
 * @param string $feature export, import, zip, fonts, pictures or vectors.
 * @return bool
 */
function allows( $feature ) {
	if ( ! in_array( $feature, FEATURES, true ) ) {
		return false;
	}
	$map     = all();
	$allowed = ! empty( $map[ $feature ] ) && ( ! in_array( $feature, array( 'fonts', 'pictures' ), true ) || ! empty( $map['zip'] ) );
	/**
	 * Filters whether one feature is allowed (the last word).
	 *
	 * @param bool $allowed Whether it is.
	 * @param int  $user    The current user.
	 */
	return (bool) apply_filters( 'evcert_allow_' . $feature, $allowed, get_current_user_id() );
}

/**
 * The message when a feature is off.
 *
 * @param string $feature The feature.
 * @return string
 */
function message( $feature ) {
	/* translators: %s: a feature (Export, Import, ZIP packages…). */
	return sprintf( __( '%s is turned off on this site.', 'events-certificates' ), labels()[ $feature ] ?? $feature );
}

/**
 * Stops a request for a feature that is off, with a 403.
 *
 * @param string $feature The feature.
 */
function must_allow( $feature ) {
	if ( ! allows( $feature ) ) {
		wp_die( esc_html( message( $feature ) ), '', array( 'response' => 403 ) );
	}
}
