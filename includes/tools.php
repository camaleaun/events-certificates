<?php
/**
 * Tools: actions that make again what the plugin makes once (pages, templates, tables, addresses, caches), from the Tools tab of
 * Settings and from WP-CLI (`wp evcert tools`). Each action is one function that both call (docs/decisions/0040).
 *
 * "Recreate" never deletes what a person made: a page that is there goes to the Trash first (or is left alone), a template that is
 * there is kept. Nothing here touches the documents' pepper: their hashes depend on it.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tools;

use EventsCertificate\Admin;
use EventsCertificate\Designs;
use EventsCertificate\Install;
use EventsCertificate\Mine;
use EventsCertificate\QuickGenerator;
use EventsCertificate\Verify;
use EventsCertificate\VerifyPage;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks.
 */
function bootstrap() {
	add_action( 'admin_post_evcert_tool', __NAMESPACE__ . '\handle' );
}

/**
 * The actions, by ID: label, what it does, whether it asks first, the choices it has (name => label) and the state it is in now.
 *
 * @return array<string, array<string, mixed>>
 */
function actions() {
	return array(
		'quick-page'  => array(
			'label'       => __( 'Generator page', 'events-certificates' ),
			'description' => __( 'Moves the page of the Quick generator to the Trash and makes it again, in the language of the site.', 'events-certificates' ),
			'button'      => __( 'Recreate the page', 'events-certificates' ),
			'confirm'     => __( 'The current page goes to the Trash and a new one is made. Go on?', 'events-certificates' ),
			'options'     => array(),
			'pages'       => true,
			'status'      => static function () {
				$id = QuickGenerator\page_id();

				return $id ? page_status( $id ) : __( 'No page.', 'events-certificates' );
			},
		),
		'verify-page' => array(
			'label'       => __( 'Check page', 'events-certificates' ),
			'description' => __( 'Makes the page where certificates are checked when it is missing; its address is the certificates\' address in Settings.', 'events-certificates' ),
			'button'      => __( 'Make the page', 'events-certificates' ),
			'confirm'     => __( 'The current page goes to the Trash and a new one is made. Go on?', 'events-certificates' ),
			'options'     => array(
				'trash_existing' => __( 'Also when there is one: move it to the Trash and make a new one', 'events-certificates' ),
			),
			'pages'       => true,
			'status'      => static function () {
				$id = VerifyPage\page_id();

				return $id ? page_status( $id ) : __( 'No page.', 'events-certificates' );
			},
		),
		'mine-page'   => array(
			'label'       => __( '"Get my certificate" page', 'events-certificates' ),
			'description' => __( 'Makes the page where people ask for their certificate when it is missing.', 'events-certificates' ),
			'button'      => __( 'Make the page', 'events-certificates' ),
			'confirm'     => __( 'The current page goes to the Trash and a new one is made. Go on?', 'events-certificates' ),
			'options'     => array(
				'trash_existing' => __( 'Also when there is one: move it to the Trash and make a new one', 'events-certificates' ),
			),
			'pages'       => true,
			'status'      => static function () {
				$id = Mine\page_id();

				return $id ? page_status( $id ) : __( 'No page.', 'events-certificates' );
			},
		),
		'templates'   => array(
			'label'       => __( 'Starter templates', 'events-certificates' ),
			'description' => __( 'Makes Basic and Meetup when they are missing. The templates you have stay as they are.', 'events-certificates' ),
			'button'      => __( 'Make the missing ones', 'events-certificates' ),
			'confirm'     => '',
			'options'     => array(
				'make_default' => __( 'Also make Basic the default template and Meetup the Quick generator\'s', 'events-certificates' ),
			),
			'pages'       => false,
			'status'      => static function () {
				$have = array();
				foreach ( array( 'Basic', 'Meetup' ) as $name ) {
					$have[] = sprintf(
						/* translators: 1: template name (Basic, Meetup WordPress), 2: yes or no. */
						__( '%1$s: %2$s', 'events-certificates' ),
						'Basic' === $name ? __( 'Basic', 'events-certificates' ) : __( 'Meetup WordPress', 'events-certificates' ),
						Designs\starter_id( $name ) ? __( 'there', 'events-certificates' ) : __( 'missing', 'events-certificates' )
					);
				}

				return implode( '. ', $have ) . '.';
			},
		),
		'tables'      => array(
			'label'       => _x( 'Tables', 'database tables', 'events-certificates' ),
			'description' => __( 'Creates or updates the plugin\'s tables. No data is lost.', 'events-certificates' ),
			'button'      => __( 'Repair the tables', 'events-certificates' ),
			'confirm'     => '',
			'options'     => array(),
			'pages'       => false,
			'status'      => static function () {
				/* translators: 1: schema version of the site, 2: schema version of the plugin. */
				return sprintf( __( 'Schema %1$s (the plugin\'s is %2$s).', 'events-certificates' ), (string) get_option( Install\DB_VERSION_OPTION, '-' ), Install\DB_VERSION );
			},
		),
		'addresses'   => array(
			'label'       => __( 'Addresses', 'events-certificates' ),
			'description' => __( 'Makes WordPress learn the addresses of the certificates again.', 'events-certificates' ),
			'button'      => __( 'Update the addresses', 'events-certificates' ),
			'confirm'     => '',
			'options'     => array(),
			'pages'       => false,
			'status'      => static function () {
				return '';
			},
		),
		'cache'       => array(
			'label'       => __( 'Caches', 'events-certificates' ),
			'description' => __( 'Empties the share images, the fonts of the PDFs and the language names. They are made again when needed.', 'events-certificates' ),
			'button'      => __( 'Clear the caches', 'events-certificates' ),
			'confirm'     => '',
			'options'     => array(),
			'pages'       => false,
			'status'      => static function () {
				/* translators: %s: number of files. */
				return sprintf( _n( '%s file.', '%s files.', count( cache_files() ), 'events-certificates' ), number_format_i18n( count( cache_files() ) ) );
			},
		),
	);
}

/**
 * A page's state, for the list.
 *
 * @param int $id Page ID.
 * @return string
 */
function page_status( $id ) {
	/* translators: 1: page ID, 2: page address. */
	return sprintf( __( 'Page %1$d: %2$s', 'events-certificates' ), $id, (string) get_permalink( $id ) );
}

/**
 * The files of the caches (share images and the PDFs' fonts).
 *
 * @return string[]
 */
function cache_files() {
	$uploads = wp_get_upload_dir();
	$files   = array();
	foreach ( array( 'evcert-share', 'evcert-fonts' ) as $dir ) {
		$found = glob( $uploads['basedir'] . '/' . $dir . '/*' );
		$files = array_merge( $files, is_array( $found ) ? $found : array() );
	}

	return $files;
}

/**
 * The result of an action.
 *
 * @param string $status  done, exists, failed, forbidden or unknown.
 * @param string $message What happened.
 * @return array{status: string, message: string}
 */
function result( $status, $message ) {
	return array(
		'status'  => $status,
		'message' => $message,
	);
}

/**
 * Runs an action, for an administrator. The choices (make_default, trash_existing) are in $args.
 *
 * @param string               $id   Action ID.
 * @param array<string, mixed> $args Choices.
 * @return array{status: string, message: string} done, exists (nothing to do), failed, forbidden or unknown.
 */
function run( $id, array $args = array() ) {
	if ( ! current_user_can( Admin\CAPABILITY ) ) {
		return result( 'forbidden', __( 'You are not allowed to do this.', 'events-certificates' ) );
	}
	switch ( $id ) {
		case 'quick-page':
			$page = QuickGenerator\recreate_page();

			return $page
				? result( 'done', __( 'The generator page was made again, in the language of the site.', 'events-certificates' ) )
				: result( 'failed', __( 'The generator page was not made: this site does not use it (an integration is active).', 'events-certificates' ) );
		case 'verify-page':
			return page( 'evcert_verify_page', 'VerifyPage', ! empty( $args['trash_existing'] ) );
		case 'mine-page':
			return page( 'evcert_mine_page', 'Mine', ! empty( $args['trash_existing'] ) );
		case 'templates':
			$made = Designs\restore_starters( ! empty( $args['make_default'] ) );
			if ( ! $made['created'] && ! $made['assigned'] ) {
				return result( 'exists', __( 'Both starter templates are there.', 'events-certificates' ) );
			}
			$text = $made['created']
				/* translators: %s: names of the templates made, separated by commas. */
				? sprintf( __( 'Made: %s.', 'events-certificates' ), implode( ', ', $made['created'] ) )
				: '';
			if ( $made['assigned'] ) {
				$text = trim( $text . ' ' . __( 'Basic is the default template and Meetup the Quick generator\'s.', 'events-certificates' ) );
			}

			return result( 'done', $text );
		case 'tables':
			Install\install( (string) get_option( Install\DB_VERSION_OPTION, '' ) );

			return result( 'done', __( 'The tables are up to date.', 'events-certificates' ) );
		case 'addresses':
			delete_option( Verify\RULES_OPTION );
			flush_rewrite_rules( false );

			return result( 'done', __( 'The addresses were updated.', 'events-certificates' ) );
		case 'cache':
			$files = count( cache_files() );
			clear_cache();

			/* translators: %s: number of files removed. */
			return result( 'done', sprintf( _n( 'The caches were emptied (%s file).', 'The caches were emptied (%s files).', $files, 'events-certificates' ), number_format_i18n( $files ) ) );
	}

	return result( 'unknown', __( 'That action does not exist.', 'events-certificates' ) );
}

/**
 * Makes a page again when it is missing, or when asked, after moving the one that is there to the Trash.
 *
 * @param string $option        The option that holds the page's ID.
 * @param string $namespace_    Module (VerifyPage or Mine): its page_id() and ensure_page().
 * @param bool   $trash_existing Whether a page that is there goes to the Trash and a new one is made.
 * @return array{status: string, message: string}
 */
function page( $option, $namespace_, $trash_existing ) {
	$page_id = '\\EventsCertificate\\' . $namespace_ . '\\page_id';
	$ensure  = '\\EventsCertificate\\' . $namespace_ . '\\ensure_page';
	$old     = (int) $page_id();
	if ( $old && ! $trash_existing ) {
		return result( 'exists', __( 'The page is there; nothing was changed.', 'events-certificates' ) );
	}
	if ( $old ) {
		wp_trash_post( $old );
	}
	// ensure_page() makes it once: the option is what says it was made.
	delete_option( $option );
	$ensure();
	$new = (int) $page_id();

	return $new
		? result( 'done', __( 'The page was made.', 'events-certificates' ) )
		: result( 'failed', __( 'The page was not made: another page already has its address.', 'events-certificates' ) );
}

/**
 * Empties the caches: share images, the PDFs' font metrics and the language names.
 */
function clear_cache() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$uploads = wp_get_upload_dir();
	if ( WP_Filesystem() ) {
		global $wp_filesystem;
		foreach ( array( 'evcert-share', 'evcert-fonts' ) as $dir ) {
			$path = $uploads['basedir'] . '/' . $dir;
			if ( $wp_filesystem->is_dir( $path ) ) {
				$wp_filesystem->rmdir( $path, true );
			}
		}
	}
	delete_option( 'evcert_locale_names' );
	delete_transient( 'evcert_locale_names_tried' );
	\EventsCertificate\Fonts\flush(); // The installed fonts are read again.
}

/**
 * The admin-post action of the buttons: checks, runs, and goes back to the tab with what happened.
 */
function handle() {
	$id = isset( $_POST['evcert_tool'] ) ? sanitize_key( wp_unslash( $_POST['evcert_tool'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked on the next line.
	check_admin_referer( 'evcert_tool_' . $id );
	if ( ! current_user_can( Admin\CAPABILITY ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'events-certificates' ), '', array( 'response' => 403 ) );
	}
	$args = array(
		'trash_existing' => ! empty( $_POST['trash_existing'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
		'make_default'   => ! empty( $_POST['make_default'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
	);
	set_transient( 'evcert_tool_result_' . get_current_user_id(), array( 'id' => $id ) + run( $id, $args ), MINUTE_IN_SECONDS );
	wp_safe_redirect(
		add_query_arg(
			'tab',
			'tools',
			Admin\page_url_of( Admin\SLUG_SETTINGS )
		)
	);
	exit;
}

/**
 * The tab: what happened, then one form for each action.
 */
function render() {
	$script = \EventsCertificate\Assets\src( 'admin-confirm', 'js' );
	wp_enqueue_script( 'evcert-admin-confirm', $script['url'], array(), $script['version'], true );
	$key  = 'evcert_tool_result_' . get_current_user_id();
	$done = get_transient( $key );
	delete_transient( $key );
	?>
	<div class="evcert-tools">
		<h2><?php esc_html_e( 'Tools', 'events-certificates' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Make again what the plugin makes once. Nothing here deletes what you made: a page that is there goes to the Trash, a template that is there stays.', 'events-certificates' ); ?></p>
		<?php if ( is_array( $done ) ) : ?>
			<div class="notice notice-<?php echo 'done' === $done['status'] ? 'success' : ( 'exists' === $done['status'] ? 'info' : 'error' ); ?> is-dismissible inline"><p><?php echo esc_html( $done['message'] ); ?></p></div>
		<?php endif; ?>
		<?php foreach ( actions() as $id => $action ) : ?>
			<form class="evcert-tools__item" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="evcert_tool">
				<input type="hidden" name="evcert_tool" value="<?php echo esc_attr( $id ); ?>">
				<?php wp_nonce_field( 'evcert_tool_' . $id ); ?>
				<div class="evcert-tools__text">
					<h3><?php echo esc_html( $action['label'] ); ?></h3>
					<p><?php echo esc_html( $action['description'] ); ?></p>
					<?php $state = (string) $action['status'](); ?>
					<?php if ( '' !== $state ) : ?>
						<p class="description"><?php echo esc_html( $state ); ?></p>
					<?php endif; ?>
					<?php foreach ( $action['options'] as $name => $label ) : ?>
						<p><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1"> <?php echo esc_html( $label ); ?></label></p>
					<?php endforeach; ?>
				</div>
				<div class="evcert-tools__button">
					<button type="submit" class="button"<?php echo '' !== $action['confirm'] ? ' data-evcert-confirm="' . esc_attr( $action['confirm'] ) . '"' : ''; ?>><?php echo esc_html( $action['button'] ); ?></button>
				</div>
			</form>
		<?php endforeach; ?>
	</div>
	<?php
}
