<?php
/**
 * Admin: the Certificates screen and the settings fields.
 *
 * Where they appear depends on the site: with CampTix (Tickets) or GatherPress (Events) alone, the screen and the settings screen (page=certificates)
 * are submenus of their menu (includes/compat/camptix.php, gatherpress.php); otherwise the screen is a menu of its own with the settings beside it (page=evcert-options).
 * It is the same settings screen everywhere (docs/decisions/0057).
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Admin;

use EventsCertificate\Certificates;
use EventsCertificate\Designs;

use EventsCertificate\Environment;
use EventsCertificate\Install;
use EventsCertificate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page slug.
 */
const SLUG = 'evcert-certificate';

/**
 * The settings screen's slug, shown as a Configurações submenu of the
 * Certificates menu when no integration (CampTix, GatherPress) hosts the
 * settings in its own tabs.
 */
const SLUG_SETTINGS = 'evcert-options';

/**
 * Settings group of the plugin's own settings form (sites without CampTix).
 */
const GROUP = 'evcert';

/**
 * Capability to manage certificates.
 */
const CAPABILITY = 'manage_options';

/**
 * Hooks.
 */
function bootstrap() {
	add_action( 'admin_menu', __NAMESPACE__ . '\register_menu' );
	// After the screens that add themselves to the menu (Events, Generator): Settings is the last item.
	add_action( 'admin_menu', __NAMESPACE__ . '\register_settings_menu', 20 );
	add_action( 'admin_init', __NAMESPACE__ . '\register_settings' );
	add_action( 'admin_post_evcert_reissue', __NAMESPACE__ . '\handle_reissue' );
	// Settings live on their own Configurações submenu (standalone) or in an
	// integration's tab (CampTix, GatherPress); never inline on the screen.
}

/**
 * The menu the Certificates screen goes under: '' for a menu of its own.
 *
 * @return string Parent slug.
 */
function menu_parent() {
	/**
	 * Filters the admin menu the Certificates screen is a submenu of.
	 * Integrations (CampTix) set it; '' gives Certificates a menu of its own.
	 *
	 * @param string $parent Parent menu slug.
	 */
	return (string) apply_filters( 'evcert_admin_menu_parent', '' );
}

/**
 * The Certificates screen's address, under its parent menu
 * (edit.php?post_type=tix_ticket&page=evcert-certificate with CampTix).
 * menu_page_url() can't tell from a screen outside that menu, such as the
 * design editor.
 *
 * @return string
 */
function page_url() {
	$parent = menu_parent();
	$base   = '' !== $parent && false !== strpos( $parent, '.php' ) ? $parent : 'admin.php';

	return add_query_arg( 'page', SLUG, admin_url( $base ) );
}

/**
 * A settings screen of its own, under the menu, like the integration tabs:
 * only in the standalone menu (an integration owns the settings).
 */
function register_settings_menu() {
	if ( '' === menu_parent() ) {
		add_submenu_page( SLUG, __( 'Settings', 'events-certificates' ), __( 'Settings', 'events-certificates' ), CAPABILITY, SLUG_SETTINGS, __NAMESPACE__ . '\render_settings_page' );
	}
}

/**
 * Registers the Certificates screen.
 */
function register_menu() {
	$title  = __( 'Certificates', 'events-certificates' );
	$parent = menu_parent();

	if ( '' === $parent ) {
		$hook = add_menu_page( $title, $title, CAPABILITY, SLUG, __NAMESPACE__ . '\render_page', 'dashicons-awards', 26 );
	} else {
		$hook = add_submenu_page( $parent, $title, $title, CAPABILITY, SLUG, __NAMESPACE__ . '\render_page' );
	}
	if ( $hook ) {
		add_action( 'load-' . $hook, __NAMESPACE__ . '\load_screen' );
	}
}

/**
 * The participant's avatar, as WordPress.org shows it: from their
 * WordPress.org username (wordpress.org/grav-redirect.php sends the
 * browser to that account's Gravatar), else from the certificate's email.
 *
 * @param array<string, mixed> $item A certificate or a participant (wporg_username, email).
 * @return string
 */
function avatar( array $item ) {
	$size     = 32;
	$username = sanitize_user( (string) ( $item['wporg_username'] ?? '' ), true );
	if ( '' !== $username ) {
		return sprintf(
			'<img class="evcert-avatar avatar avatar-%1$d photo" src="%2$s" srcset="%3$s 2x" width="%1$d" height="%1$d" alt="" loading="lazy">',
			$size,
			esc_url(
				add_query_arg(
					array(
						'user' => $username,
						's'    => $size,
					),
					'https://wordpress.org/grav-redirect.php'
				)
			),
			esc_url(
				add_query_arg(
					array(
						'user' => $username,
						's'    => $size * 2,
					),
					'https://wordpress.org/grav-redirect.php'
				)
			)
		);
	}

	return (string) get_avatar( (string) ( $item['email'] ?? '' ), $size, '', '', array( 'class' => 'evcert-avatar' ) );
}

/**
 * The avatar's place beside the name, like the Users screen's: the lists of certificates and of participants.
 */
function avatar_style() {
	wp_add_inline_style( 'list-tables', '.column-name .evcert-avatar{float:left;margin:1px 10px 0 0;border-radius:50%}' );
}

/**
 * The lists' styles: the avatar, the role tags and the confirmation.
 */
function list_styles() {
	avatar_style();
	// The role tags are the Events screen's too.
	$style = \EventsCertificate\Assets\src( 'events-admin', 'css' );
	wp_enqueue_style( 'evcert-events', $style['url'], array(), $style['version'] );
	// A link with data-evcert-confirm asks first (Revoke).
	$script = \EventsCertificate\Assets\src( 'admin-confirm', 'js' );
	wp_enqueue_script( 'evcert-admin-confirm', $script['url'], array(), $script['version'], true );
}

/**
 * Before the Certificates screen prints: the per-page screen option and the
 * revoke action (row or bulk), then back to the list with a notice.
 */
function load_screen() {
	add_screen_option(
		'per_page',
		array(
			'label'   => __( 'Certificates per page', 'events-certificates' ),
			'default' => 20,
			'option'  => 'evcert_certificates_per_page',
		)
	);
	add_filter( 'set_screen_option_evcert_certificates_per_page', __NAMESPACE__ . '\save_per_page', 10, 3 );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\list_styles' );

	require_once __DIR__ . '/class-list-table.php';
	$table = new List_Table();
	// WP_List_Table knows the bulk actions of the select; the Empty Trash buttons are ours.
	$action = isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] ) ? 'delete_all' : $table->current_action(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in act_on_certificates().
	act_on_certificates( (string) $action );
}

/**
 * An action on certificates (row or bulk): revoke, to the Trash, back from it, delete for good, or empty the Trash. Then
 * back to the list with a notice.
 *
 * @param string $action revoke|trash|untrash|delete|delete_all; anything else does nothing.
 */
function act_on_certificates( $action ) {
	if ( ! in_array( $action, array( 'revoke', 'trash', 'untrash', 'delete', 'delete_all' ), true ) ) {
		return;
	}
	check_admin_referer( 'bulk-certificates' );
	if ( ! current_user_can( CAPABILITY ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to change certificates.', 'events-certificates' ) );
	}
	$ids  = isset( $_REQUEST['certificate'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['certificate'] ) ) : array();
	$done = 0;
	$kept = 0;
	if ( 'delete_all' === $action ) {
		$done = Certificates\empty_trash();
	}
	foreach ( 'delete_all' === $action ? array() : array_filter( $ids ) as $id ) {
		if ( 'revoke' === $action ) {
			$done += Certificates\revoke( $id ) ? 1 : 0;
		} elseif ( 'trash' === $action ) {
			$done += Certificates\trash( $id ) ? 1 : 0;
		} elseif ( 'untrash' === $action ) {
			// A valid one does not come back over a newer one: it stays in the Trash.
			$back  = Certificates\restore( $id );
			$done += true === $back ? 1 : 0;
			$kept += is_wp_error( $back ) && 'evcert_newer_certificate' === $back->get_error_code() ? 1 : 0;
		} else {
			$done += Certificates\delete_permanently( $id ) ? 1 : 0;
		}
	}
	$args = 'revoke' === $action ? array( 'revoked' => $done ) : array(
		'evcert_done' => 'delete_all' === $action ? 'delete' : $action,
		'evcert_n'    => $done,
		'evcert_kept' => $kept,
	);
	// Working in the Trash: back to it while it has something.
	if ( in_array( $action, array( 'untrash', 'delete', 'delete_all' ), true ) && ( counts()['trash'] ?? 0 ) ) {
		$args['status'] = 'trash';
	}
	wp_safe_redirect( add_query_arg( $args, remove_query_arg( array( 'action', 'action2', 'certificate', '_wpnonce', '_wp_http_referer', 'revoked', 'evcert_done', 'evcert_n', 'evcert_kept', 'status', 'delete_all', 'delete_all2' ) ) ) );
	exit;
}

/**
 * Keeps the per-page screen option.
 *
 * @param mixed  $keep   Value to keep (false to not save).
 * @param string $option Option name.
 * @param int    $value  Value chosen.
 * @return int
 */
function save_per_page( $keep, $option, $value ) {
	return max( 1, min( 999, (int) $value ) );
}

/**
 * Number of certificates by status.
 *
 * @return array<string, int>
 */
function counts() {
	global $wpdb;

	$table  = Install\tables()['certificates'];
	$counts = array(
		'draft'    => 0,
		'issued'   => 0,
		'replaced' => 0,
		'revoked'  => 0,
		'trash'    => 0,
	);
	$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS total FROM %i GROUP BY status', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( (array) $rows as $row ) {
		$counts[ $row->status ] = (int) $row->total;
	}

	return $counts;
}

/**
 * The Certificates screen: issued certificates in a list table, like
 * CampTix's Attendees.
 */
function render_page() {
	if ( ! current_user_can( CAPABILITY ) ) {
		return;
	}
	// A form to correct one certificate (the row action's link): reading it changes nothing; saving checks a nonce.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks which form to show.
	$correcting = isset( $_GET['evcert_reissue'] ) ? Certificates\get( absint( $_GET['evcert_reissue'] ) ) : null;
	if ( $correcting && 'issued' === $correcting['status'] ) {
		render_reissue_form( $correcting );
		return;
	}
	require_once __DIR__ . '/class-list-table.php';
	$table = new List_Table();
	$table->prepare_items();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only a count for the notice.
	$revoked = isset( $_GET['revoked'] ) ? absint( $_GET['revoked'] ) : null;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the new certificate's ID, for a link in the notice.
	$reissued = isset( $_GET['reissued'] ) ? Certificates\get( absint( $_GET['reissued'] ) ) : null;
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Certificates', 'events-certificates' ); ?></h1>
		<?php
		/**
		 * Fires next to the Certificates screen title (links like Edit certificate design).
		 */
		do_action( 'evcert_certificates_title_actions' );
		?>
		<hr class="wp-header-end">
		<?php settings_errors(); // "Settings saved." and the errors of the settings, after they are saved. ?>
		<?php if ( null !== $revoked ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php
				/* translators: %s: number of certificates. */
				echo esc_html( sprintf( _n( '%s certificate revoked.', '%s certificates revoked.', $revoked, 'events-certificates' ), number_format_i18n( $revoked ) ) );
				?>
			</p></div>
		<?php endif; ?>
		<?php
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only a count and a word for the notice.
		$done = isset( $_GET['evcert_done'] ) ? sanitize_key( wp_unslash( $_GET['evcert_done'] ) ) : '';
		$n    = isset( $_GET['evcert_n'] ) ? absint( $_GET['evcert_n'] ) : 0;
		$kept = isset( $_GET['evcert_kept'] ) ? absint( $_GET['evcert_kept'] ) : 0;
		// phpcs:enable
		$messages = array(
			/* translators: %s: number of certificates. */
			'trash'   => sprintf( _n( '%s certificate moved to the Trash.', '%s certificates moved to the Trash.', $n, 'events-certificates' ), number_format_i18n( $n ) ),
			/* translators: %s: number of certificates. */
			'untrash' => sprintf( _n( '%s certificate restored.', '%s certificates restored.', $n, 'events-certificates' ), number_format_i18n( $n ) ),
			/* translators: %s: number of certificates. */
			'delete'  => sprintf( _n( '%s certificate deleted permanently.', '%s certificates deleted permanently.', $n, 'events-certificates' ), number_format_i18n( $n ) ),
		);
		if ( isset( $messages[ $done ] ) ) :
			?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $messages[ $done ] ); ?></p></div>
		<?php endif; ?>
		<?php if ( $kept ) : ?>
			<div class="notice notice-warning is-dismissible"><p>
				<?php
				/* translators: %s: number of certificates. */
				echo esc_html( sprintf( _n( '%s certificate stays in the Trash: a newer one was issued to the same person in the same role.', '%s certificates stay in the Trash: a newer one was issued to the same person in the same role.', $kept, 'events-certificates' ), number_format_i18n( $kept ) ) );
				?>
			</p></div>
		<?php endif; ?>
		<?php if ( $reissued ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php echo esc_html( Environment\verification_enabled() ? __( 'Certificate reissued.', 'events-certificates' ) : __( 'Certificate saved.', 'events-certificates' ) ); ?>
				<a href="<?php echo esc_url( Certificates\url( $reissued['code'] ) ); ?>" target="_blank"><?php echo esc_html( Environment\verification_enabled() ? __( 'View the new certificate', 'events-certificates' ) : __( 'View the certificate', 'events-certificates' ) ); ?></a>
			</p></div>
		<?php endif; ?>
		<?php $table->views(); ?>
		<form method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( SLUG ); ?>">
			<?php if ( 'edit.php' === $GLOBALS['pagenow'] && isset( $_GET['post_type'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- keeps the parent screen in the form. ?>
				<input type="hidden" name="post_type" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_GET['post_type'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
			<?php endif; ?>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- keeps the status view in the search.
			if ( isset( $_GET['status'] ) ) :
				?>
				<input type="hidden" name="status" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_GET['status'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
			<?php endif; ?>
			<?php
			$table->search_box( __( 'Search certificates', 'events-certificates' ), 'evcert-search' );
			$table->display();
			?>
		</form>
		<?php
		/**
		 * Fires at the end of the Certificates screen, where the settings go
		 * when no integration shows them elsewhere.
		 */
		do_action( 'evcert_certificates_screen' );
		?>
	</div>
	<?php
}

/**
 * The form to correct a certificate: its name, role and workload. Saving
 * issues a new certificate (a new code) and marks this one as replaced.
 *
 * @param array<string, mixed> $cert Certificate (issued).
 */
function render_reissue_form( array $cert ) {
	// The message of a failed save comes from a transient of the user, never from the address (a link could say anything).
	$error = '';
	if ( isset( $_GET['reissue_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only says there is a message to read.
		$error = (string) get_transient( 'evcert_reissue_error_' . get_current_user_id() );
		delete_transient( 'evcert_reissue_error_' . get_current_user_id() );
	}
	?>
	<div class="wrap">
		<?php $verification = Environment\verification_enabled(); ?>
		<h1><?php echo esc_html( $verification ? __( 'Correct and reissue', 'events-certificates' ) : __( 'Edit certificate', 'events-certificates' ) ); ?></h1>
		<?php if ( '' !== $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>
		<p><?php echo esc_html( $verification ? __( 'The corrected certificate gets a new code. This one is marked as replaced and points to the new one.', 'events-certificates' ) : __( 'The changes are made to this certificate.', 'events-certificates' ) ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="evcert_reissue">
			<input type="hidden" name="evcert_certificate" value="<?php echo esc_attr( (string) (int) $cert['id'] ); ?>">
			<?php wp_nonce_field( 'evcert_reissue_' . (int) $cert['id'] ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="evcert-reissue-name"><?php esc_html_e( 'Name', 'events-certificates' ); ?></label></th>
					<td><input type="text" class="regular-text" id="evcert-reissue-name" name="name" value="<?php echo esc_attr( $cert['name'] ); ?>" required></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Role', 'events-certificates' ); ?></th>
					<td>
						<strong><?php echo esc_html( Designs\role_labels()[ $cert['role'] ] ?? (string) $cert['role'] ); ?></strong>
						<p class="description"><?php echo esc_html( $verification ? __( 'A correction keeps the role. To give someone another role, revoke this certificate and issue the other one.', 'events-certificates' ) : __( 'The role stays. To give someone another role, issue the other certificate from the participants.', 'events-certificates' ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="evcert-reissue-hours"><?php esc_html_e( 'Workload (hours)', 'events-certificates' ); ?></label></th>
					<td><input type="text" inputmode="decimal" class="small-text" id="evcert-reissue-hours" name="hours" value="<?php echo esc_attr( (string) $cert['hours'] ); ?>"></td>
				</tr>
			</table>
			<?php submit_button( $verification ? __( 'Reissue', 'events-certificates' ) : __( 'Save', 'events-certificates' ) ); ?>
			<a href="<?php echo esc_url( page_url() ); ?>"><?php esc_html_e( 'Back to the certificates', 'events-certificates' ); ?></a>
		</form>
	</div>
	<?php
}

/**
 * Saves the correction: a new certificate in place of the old one.
 */
function handle_reissue() {
	$id = isset( $_POST['evcert_certificate'] ) ? absint( $_POST['evcert_certificate'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- names the nonce, checked on the next line.
	check_admin_referer( 'evcert_reissue_' . $id );
	if ( ! current_user_can( CAPABILITY ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage certificates.', 'events-certificates' ), 403 );
	}
	$old = Certificates\get( $id );
	if ( ! $old || 'issued' !== $old['status'] ) {
		wp_safe_redirect( page_url() );
		exit;
	}
	$hours   = isset( $_POST['hours'] ) ? str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['hours'] ) ) ) : '';
	$changes = array(
		'name'  => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
		'hours' => is_numeric( $hours ) && (float) $hours >= 0 && (float) $hours < 1000 ? (string) round( (float) $hours, 2 ) : '',
	);
	$new     = Certificates\reissue( $old, $changes );
	if ( is_wp_error( $new ) ) {
		set_transient( 'evcert_reissue_error_' . get_current_user_id(), $new->get_error_message(), MINUTE_IN_SECONDS );
		wp_safe_redirect(
			add_query_arg(
				array(
					'evcert_reissue' => $id,
					'reissue_error'  => 1,
				),
				page_url()
			)
		);
		exit;
	}
	wp_safe_redirect( add_query_arg( 'reissued', (int) $new['id'], page_url() ) );
	exit;
}
