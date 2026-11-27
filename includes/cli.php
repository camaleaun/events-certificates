<?php
/**
 * WP-CLI: `wp evcert tools list` and `wp evcert tools run <id>`, the actions of the Tools tab (docs/decisions/0040). They run as
 * an administrator: `wp --user=<login> evcert tools run templates`. On a network, once for each site: `--url=<site>`.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Cli;

use EventsCertificate\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the commands (only loaded with WP-CLI).
 */
function bootstrap() {
	\WP_CLI::add_command( 'evcert tools list', __NAMESPACE__ . '\tools_list' );
	\WP_CLI::add_command( 'evcert tools run', __NAMESPACE__ . '\tools_run' );
}

/**
 * Lists the actions and the state each is in.
 *
 * ## OPTIONS
 *
 * [--format=<format>]
 * : table (default), json, csv or yaml.
 *
 * @param array<int, string>    $args  Positional arguments.
 * @param array<string, string> $assoc Options.
 */
function tools_list( $args, $assoc ) {
	$rows = array();
	foreach ( Tools\actions() as $id => $action ) {
		$rows[] = array(
			'id'          => $id,
			'description' => $action['description'],
			'state'       => (string) $action['status'](),
		);
	}
	\WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, array( 'id', 'description', 'state' ) );
}

/**
 * Runs an action: quick-page, verify-page, mine-page, templates, tables, addresses or cache; "all" runs the ones that do not touch
 * pages (templates, tables, addresses, cache).
 *
 * ## OPTIONS
 *
 * <id>
 * : The action.
 *
 * [--yes]
 * : Do not ask before an action that moves a page to the Trash.
 *
 * [--make-default]
 * : templates: also make Basic the default template and Meetup the Quick generator's.
 *
 * [--trash-existing]
 * : verify-page and mine-page: also when the page is there, move it to the Trash and make a new one.
 *
 * ## EXAMPLES
 *
 *     wp --user=admin evcert tools run templates --make-default
 *     wp --user=admin evcert tools run quick-page --yes
 *
 * @param array<int, string>    $args  Positional arguments.
 * @param array<string, string> $assoc Options.
 */
function tools_run( $args, $assoc ) {
	$actions = Tools\actions();
	$id      = (string) ( $args[0] ?? '' );
	$ids     = 'all' === $id
		? array_keys( array_filter( $actions, static fn ( $action ) => empty( $action['pages'] ) ) )
		: array( $id );
	$failed  = false;
	foreach ( $ids as $one ) {
		if ( ! isset( $actions[ $one ] ) ) {
			\WP_CLI::error( sprintf( 'Unknown action "%s". See: wp evcert tools list', $one ) );
		}
		if ( ! empty( $actions[ $one ]['pages'] ) && '' !== $actions[ $one ]['confirm'] ) {
			\WP_CLI::confirm( $actions[ $one ]['confirm'], $assoc );
		}
		$result = Tools\run(
			$one,
			array(
				'make_default'   => ! empty( $assoc['make-default'] ),
				'trash_existing' => ! empty( $assoc['trash-existing'] ),
			)
		);
		if ( 'done' === $result['status'] ) {
			\WP_CLI::success( $one . ': ' . $result['message'] );
		} elseif ( 'exists' === $result['status'] ) {
			\WP_CLI::warning( $one . ': ' . $result['message'] );
		} else {
			$failed = true;
			\WP_CLI::warning( $one . ': ' . $result['message'] . ( 'forbidden' === $result['status'] ? ' (run it with --user=<administrator>)' : '' ) );
		}
	}
	if ( $failed ) {
		\WP_CLI::halt( 1 );
	}
}
