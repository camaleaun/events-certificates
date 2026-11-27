<?php
/**
 * `evcert deps`: copies the third-party PHP libraries (dompdf, php-qrcode and what they need, from
 * composer.lock) into includes/third-party/ under the plugin's own namespace, and writes their autoloader.
 * The folder is not in git; this makes it (docs/decisions/0032). The work is in bin/deps-lib.php.
 *
 * Needs the packages installed (`composer install`, which keeps them in vendor/ for development only) and
 * PHP's tokenizer extension.
 *
 * Usage, from the plugin folder:
 *   wp --require=bin/deps.php evcert deps
 *   wp --require=bin/deps.php evcert deps --target=/tmp/evcert-third-party
 *
 * bin/install.sh runs it after composer and npm; bin/package.sh and the release workflow run it too.
 *
 * Not shipped (bin/ is export-ignored).
 *
 * @package EventsCertificate
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once __DIR__ . '/deps-lib.php';

/**
 * Makes the third-party PHP libraries.
 */
class Evcert_Deps_Command {

	/**
	 * Copy the runtime packages under EventsCertificate\Vendor\ into includes/third-party/.
	 *
	 * ## OPTIONS
	 *
	 * [--target=<dir>]
	 * : Where to write. Default: includes/third-party. Made again from nothing; only a folder named
	 *   third-party, or one starting with evcert-, is ever emptied.
	 *
	 * ## EXAMPLES
	 *
	 *   wp --require=bin/deps.php evcert deps
	 *
	 * @when before_wp_load
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( ! function_exists( 'token_get_all' ) ) {
			WP_CLI::error( "PHP's tokenizer extension is needed to prefix the libraries." );
		}
		$root   = dirname( __DIR__ );
		$target = isset( $assoc_args['target'] ) ? (string) $assoc_args['target'] : $root . '/includes/third-party';
		try {
			$report = evcert_deps_build( $root . '/composer.lock', $root . '/vendor', $target );
		} catch ( RuntimeException $e ) {
			WP_CLI::error( $e->getMessage() );
		}
		WP_CLI::success( sprintf( '%d packages, %d files, %d classes under EventsCertificate\\Vendor in %s.', count( $report['packages'] ), $report['files'], $report['classes'], $target ) );
	}
}

WP_CLI::add_command( 'evcert deps', 'Evcert_Deps_Command' );
