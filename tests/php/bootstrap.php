<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads the WordPress site this plugin is installed in (set WP_LOAD to use
 * another wp-load.php). Tests that write to the plugin's tables run inside a
 * transaction rolled back after each test (Test_Case).
 *
 * EVCERT_INTEGRATIONS=none|camptix|gatherpress|both chooses which of the two
 * integrations are active for the run, whatever the site has active (the
 * plugins must be installed in the site); composer test:all runs all four.
 * Not set: the site as it is.
 *
 * @package EventsCertificate
 */

// PHPUnit polyfills (yoast/phpunit-polyfills) for the snake_case fixtures.
require_once dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$evcert_wp_load = getenv( 'WP_LOAD' ) ? getenv( 'WP_LOAD' ) : dirname( __DIR__, 5 ) . '/wp-load.php';
if ( ! is_file( $evcert_wp_load ) ) {
	fwrite( STDERR, "wp-load.php not found at $evcert_wp_load; set WP_LOAD.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Which integrations are active, decided before WordPress loads its plugins.
$evcert_scenario = getenv( 'EVCERT_INTEGRATIONS' ) ? getenv( 'EVCERT_INTEGRATIONS' ) : '';
if ( '' !== $evcert_scenario ) {
	$evcert_wanted = array(
		'none'        => array(),
		'camptix'     => array( 'camptix/camptix.php' ),
		'gatherpress' => array( 'gatherpress/gatherpress.php' ),
		'both'        => array( 'camptix/camptix.php', 'gatherpress/gatherpress.php' ),
	);
	if ( ! isset( $evcert_wanted[ $evcert_scenario ] ) ) {
		fwrite( STDERR, "EVCERT_INTEGRATIONS must be none, camptix, gatherpress or both, not '$evcert_scenario'.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.Security.EscapeOutput.OutputNotEscaped
		exit( 1 );
	}
	$evcert_integrations = array( 'camptix/camptix.php', 'gatherpress/gatherpress.php' );
	// Like WordPress's own tests_add_filter(): the hooks table exists before plugin.php is loaded.
	// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- what tests_add_filter() does, before WordPress is loaded.
	$GLOBALS['wp_filter']['option_active_plugins'][10]['evcert_scenario']               = array(
		'function'      => static function ( $plugins ) use ( $evcert_wanted, $evcert_scenario, $evcert_integrations ) {
			return array_values( array_unique( array_merge( array_diff( (array) $plugins, $evcert_integrations ), $evcert_wanted[ $evcert_scenario ] ) ) );
		},
		'accepted_args' => 1,
	);
	$GLOBALS['wp_filter']['site_option_active_sitewide_plugins'][10]['evcert_scenario'] = array(
		'function'      => static function ( $plugins ) use ( $evcert_integrations ) {
			return array_diff_key( (array) $plugins, array_flip( $evcert_integrations ) );
		},
		'accepted_args' => 1,
	);
	// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
}

// On a network, WP_TEST_URL picks the site the tests run in (http://localhost:8889/b/ for a subsite).
// phpcs:disable WordPress.WP.AlternativeFunctions.parse_url_parse_url, WordPress.Security.ValidatedSanitizedInput -- WordPress isn't loaded yet.
if ( getenv( 'WP_TEST_URL' ) ) {
	$evcert_url             = (string) getenv( 'WP_TEST_URL' );
	$_SERVER['HTTP_HOST']   = (string) parse_url( $evcert_url, PHP_URL_HOST ) . ( parse_url( $evcert_url, PHP_URL_PORT ) ? ':' . parse_url( $evcert_url, PHP_URL_PORT ) : '' );
	$_SERVER['REQUEST_URI'] = (string) parse_url( $evcert_url, PHP_URL_PATH );
}
// phpcs:enable WordPress.WP.AlternativeFunctions.parse_url_parse_url, WordPress.Security.ValidatedSanitizedInput

// A site on SQLite (WordPress Studio): the tests run on a copy of its database, not on the one the browser and WP-CLI use. A test runs
// in a transaction, which locks a SQLite file for as long as it lasts ("database is locked" in debug.log for whoever else asks), and
// what is rolled back can be what another request just saved (an autosave: warnings from core in debug.log). EVCERT_LIVE_DB=1 uses
// the site's own database.
$evcert_site = dirname( $evcert_wp_load );
$evcert_live = $evcert_site . '/wp-content/database/.ht.sqlite';
if ( ! getenv( 'EVCERT_LIVE_DB' ) && ! defined( 'DB_PATH' ) && is_file( $evcert_site . '/wp-content/db.php' ) && is_file( $evcert_live ) && extension_loaded( 'pdo_sqlite' ) ) {
	$evcert_copy = sys_get_temp_dir() . '/evcert-tests-' . getmypid() . '.sqlite';
	try {
		( new PDO( 'sqlite:' . $evcert_live ) )->exec( "VACUUM INTO '" . str_replace( "'", "''", $evcert_copy ) . "'" ); // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- a copy of a SQLite file, before WordPress loads.
		define( 'DB_PATH', $evcert_copy ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the SQLite drop-in's constant.
		register_shutdown_function(
			static function () use ( $evcert_copy ) {
				foreach ( array( '', '-wal', '-shm' ) as $suffix ) {
					if ( is_file( $evcert_copy . $suffix ) ) {
						unlink( $evcert_copy . $suffix ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- the copy this run made.
					}
				}
			}
		);
	} catch ( Throwable $evcert_error ) {
		fwrite( STDERR, "Could not copy the SQLite database; the tests use the site's own: " . $evcert_error->getMessage() . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

define( 'WP_USE_THEMES', false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- core constant.
require_once $evcert_wp_load;

// The plugin may not be active on that site.
if ( ! defined( 'EventsCertificate\VERSION' ) ) {
	require_once dirname( __DIR__, 2 ) . '/events-certificates.php';
}

// The run must have the integrations it was asked for (a plugin missing from the site would silently test another scenario).
if ( '' !== $evcert_scenario && \EventsCertificate\Environment\integration() !== $evcert_scenario ) {
	fwrite( STDERR, "EVCERT_INTEGRATIONS=$evcert_scenario, but the site loaded '" . \EventsCertificate\Environment\integration() . "': is the plugin installed (camptix, gatherpress)?\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once __DIR__ . '/class-test-case.php';
require_once __DIR__ . '/class-fixtures.php';
