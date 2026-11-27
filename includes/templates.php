<?php
/**
 * The Templates screen ("Modelos"): every certificate template, the roles each
 * applies to, the default one, and the actions to add, duplicate, reorder,
 * make default and trash. It opens from the Certificates screen's Templates
 * button; each template opens in the design editor. docs/decisions/0013.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Templates;

use EventsCertificate\Admin;
use EventsCertificate\AdminApp;
use EventsCertificate\Designs;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page slug.
 */
const SLUG = 'evcert-template';

/**
 * Hooks.
 */
function bootstrap() {
	add_action( 'admin_menu', __NAMESPACE__ . '\add_page', 17 );
	add_action( 'evcert_certificates_title_actions', __NAMESPACE__ . '\render_button', 5 );
}

/**
 * The screen's address.
 *
 * @param array<string, string|int> $args More query args.
 * @return string
 */
function url( array $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => SLUG ), $args ), admin_url( 'admin.php' ) );
}

/**
 * An action on a template, with its nonce.
 *
 * @param string $action duplicate|default|trash|untrash|delete|up|down|new.
 * @param int    $id     Template ID (0 for new).
 * @return string
 */
function action_url( $action, $id = 0 ) {
	return wp_nonce_url(
		url(
			array(
				'evcert_do' => $action,
				'template'  => (int) $id,
			)
		),
		'evcert_template_' . $action . '_' . (int) $id
	);
}

/**
 * Registers the screen: a menu item where the site has its own menu and events (not with CampTix or GatherPress), else a page with
 * no menu item that opens from Certificates.
 */
function add_page() {
	$own   = \EventsCertificate\Environment\own_events() && '' === Admin\menu_parent();
	$title = __( 'Templates', 'events-certificates' );
	$hook  = add_submenu_page( $own ? Admin\SLUG : '', __( 'Certificate templates', 'events-certificates' ), $own ? $title : '', Admin\CAPABILITY, SLUG, __NAMESPACE__ . '\render_screen' );
	if ( $hook ) {
		add_action( 'load-' . $hook, __NAMESPACE__ . '\load' );
	}
}

/**
 * The screen: the list of templates, or a template in the editor (`design` and `action=edit`, one address for both).
 */
function render_screen() {
	if ( \EventsCertificate\AdminApp\is_editor_screen() && \EventsCertificate\AdminApp\available() ) {
		call_user_func( \EventsCertificate\AdminApp\RENDER );

		return;
	}
	render();
}

/**
 * The Templates button on the Certificates screen.
 */
function render_button() {
	printf( '<a class="page-title-action" href="%s">%s</a>', esc_url( url() ), esc_html__( 'Templates', 'events-certificates' ) );
}

/**
 * Before the screen prints: the menu highlight, the screen's title, and an
 * action (with its nonce), then back to the list.
 */
function load() {
	// The editor is on this address too (design and action=edit): it sets its own title and has no menu.
	if ( \EventsCertificate\AdminApp\is_editor_screen() ) {
		return;
	}
	$GLOBALS['title'] = __( 'Certificate templates', 'events-certificates' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- a page with no menu item has no title of its own.
	add_filter(
		'parent_file',
		static function () {
			return Admin\menu_parent() ? Admin\menu_parent() : Admin\SLUG;
		}
	);
	add_filter(
		'submenu_file',
		static function () {
			return Admin\SLUG;
		}
	);

	// The form on the left: a new template, with a name, from the Base design or from another template.
	if ( isset( $_POST['evcert_template_add'] ) ) {
		check_admin_referer( 'evcert_template_add' );
		if ( ! current_user_can( Admin\CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change certificate templates.', 'events-certificates' ) );
		}
		$name  = isset( $_POST['template-name'] ) ? sanitize_text_field( wp_unslash( $_POST['template-name'] ) ) : '';
		$blank = isset( $_POST['template-from'] ) && 'blank' === sanitize_key( wp_unslash( $_POST['template-from'] ) );
		$from  = isset( $_POST['template-from'] ) ? absint( $_POST['template-from'] ) : 0;
		$from  = $from && Designs\POST_TYPE === get_post_type( $from ) ? $from : 0;
		$new   = Designs\duplicate( $from, $name, $blank );
		// A template added in the form opens in the editor; one duplicated from a row of the list stays in the list.
		wp_safe_redirect(
			url(
				$new ? array(
					'design' => $new,
					'action' => 'edit',
				) : array()
			)
		);
		exit;
	}

	if ( ( isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in empty_trash().
		empty_trash();
	}
	// The templates chosen in the list: to the Trash (never the default), back from it, or deleted for good.
	if ( isset( $_REQUEST['evcert_templates'] ) && is_array( $_REQUEST['evcert_templates'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked on the next line.
		bulk();
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- checked below for each action.
	$action = isset( $_GET['evcert_do'] ) ? sanitize_key( wp_unslash( $_GET['evcert_do'] ) ) : '';
	$id     = isset( $_GET['template'] ) ? absint( $_GET['template'] ) : 0;
	// phpcs:enable
	if ( '' === $action ) {
		return;
	}
	check_admin_referer( 'evcert_template_' . $action . '_' . $id );
	if ( ! current_user_can( Admin\CAPABILITY ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to change certificate templates.', 'events-certificates' ) );
	}
	if ( $id && Designs\POST_TYPE !== get_post_type( $id ) ) {
		wp_die( esc_html__( 'That template no longer exists.', 'events-certificates' ) );
	}

	$done = '';
	switch ( $action ) {
		case 'new':
		case 'duplicate':
			$new  = Designs\duplicate( 'new' === $action ? 0 : $id );
			$done = $new ? 'added' : '';
			break;
		case 'default':
			$done = Designs\set_default( $id ) ? 'default' : '';
			break;
		case 'quick':
			$done = Designs\set_quick( $id ) ? 'quick' : '';
			break;
		case 'trash':
			// The default one stays: there is always a template for everyone. The Quick generator's one may go: it falls back to the default.
			$was_quick = Designs\quick_design_id() === $id;
			$done      = Designs\site_design_id() !== $id && wp_trash_post( $id ) ? ( $was_quick ? 'trashed_quick' : 'trashed' ) : '';
			break;
		case 'untrash':
			// Back in the list as it was: a template of its own, published.
			wp_untrash_post( $id );
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			);
			$done = 'restored';
			break;
		case 'delete':
			$done = 'trash' === get_post_status( $id ) && wp_delete_post( $id, true ) ? 'deleted' : '';
			break;
		case 'up':
		case 'down':
			Designs\move( $id, $action );
			break;
	}
	wp_safe_redirect( url( $done ? array( 'evcert_done' => $done ) : array() ) );
	exit;
}

/**
 * Deletes every template in the Trash for good.
 */
function empty_trash() {
	check_admin_referer( 'bulk-templates' );
	if ( ! current_user_can( Admin\CAPABILITY ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to change certificate templates.', 'events-certificates' ) );
	}
	$n = 0;
	foreach ( Designs\trashed_templates() as $template ) {
		$n += wp_delete_post( $template->ID, true ) ? 1 : 0;
	}
	wp_safe_redirect( url( $n ? array( 'evcert_done' => 'deleted' ) : array() ) );
	exit;
}

/**
 * A change to the templates chosen in the list.
 */
function bulk() {
	check_admin_referer( 'bulk-templates' );
	if ( ! current_user_can( Admin\CAPABILITY ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to change certificate templates.', 'events-certificates' ) );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- checked above.
	$action = isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
	if ( '' === $action && isset( $_REQUEST['action2'] ) ) {
		$action = sanitize_key( wp_unslash( $_REQUEST['action2'] ) );
	}
	$ids = isset( $_REQUEST['evcert_templates'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['evcert_templates'] ) ) : array();
	// phpcs:enable
	$n = 0;
	foreach ( $ids as $id ) {
		if ( ! $id || Designs\POST_TYPE !== get_post_type( $id ) ) {
			continue;
		}
		if ( 'trash' === $action && Designs\site_design_id() !== $id ) {
			$n += wp_trash_post( $id ) ? 1 : 0;
		} elseif ( 'untrash' === $action && 'trash' === get_post_status( $id ) ) {
			wp_untrash_post( $id );
			$n += wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			) ? 1 : 0;
		} elseif ( 'delete' === $action && 'trash' === get_post_status( $id ) ) {
			$n += wp_delete_post( $id, true ) ? 1 : 0;
		}
	}
	$done = array(
		'trash'   => 'trashed',
		'untrash' => 'restored',
		'delete'  => 'deleted',
	);
	wp_safe_redirect( url( $n && isset( $done[ $action ] ) ? array( 'evcert_done' => $done[ $action ] ) : array() ) );
	exit;
}

/**
 * How many certificates each template draws now (by their roles).
 *
 * @return array<int, int> Template ID => certificates.
 */
function usage() {
	global $wpdb;

	$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT role, COUNT(*) AS n FROM %i WHERE status = 'issued' GROUP BY role", \EventsCertificate\Certificates\table() ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
	$counts = array();
	foreach ( (array) $rows as $row ) {
		$id            = Designs\for_certificate( array( 'role' => (string) $row['role'] ) );
		$counts[ $id ] = ( $counts[ $id ] ?? 0 ) + (int) $row['n'];
	}

	return $counts;
}

/**
 * Prints the screen.
 */
function render() {
	require_once __DIR__ . '/class-templates-list-table.php';
	$table = new Templates_List_Table();
	$table->prepare_items();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks a message.
	$done     = isset( $_GET['evcert_done'] ) ? sanitize_key( wp_unslash( $_GET['evcert_done'] ) ) : '';
	$messages = array(
		'added'         => __( 'Template added.', 'events-certificates' ),
		'default'       => __( 'Default template changed.', 'events-certificates' ),
		'trashed'       => __( 'Template moved to the Trash.', 'events-certificates' ),
		'quick'         => __( 'The Quick generator now uses this template.', 'events-certificates' ),
		'trashed_quick' => __( 'Template moved to the Trash. The Quick generator now uses the default template.', 'events-certificates' ),
		'restored'      => __( 'Template restored.', 'events-certificates' ),
		'deleted'       => __( 'Template deleted permanently.', 'events-certificates' ),
	);
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Certificate templates', 'events-certificates' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( Admin\page_url() ); ?>"><?php esc_html_e( 'Certificates', 'events-certificates' ); ?></a>
		<?php
		/**
		 * Fires next to the Templates screen title (Export and Import).
		 */
		do_action( 'evcert_templates_title_actions' );
		?>
		<hr class="wp-header-end">
		<?php if ( isset( $messages[ $done ] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $messages[ $done ] ); ?></p></div>
		<?php endif; ?>
		<div id="col-container" class="wp-clearfix">
			<div id="col-left">
				<div class="col-wrap">
					<div class="form-wrap">
						<h2><?php esc_html_e( 'Add new template', 'events-certificates' ); ?></h2>
						<form id="addtemplate" method="post" action="<?php echo esc_url( url() ); ?>">
							<input type="hidden" name="evcert_template_add" value="1">
							<?php wp_nonce_field( 'evcert_template_add' ); ?>
							<div class="form-field form-required template-name-wrap">
								<label for="template-name"><?php echo esc_html_x( 'Name', 'template name', 'events-certificates' ); ?></label>
								<input name="template-name" id="template-name" type="text" value="" size="40" aria-describedby="template-name-description">
								<p id="template-name-description"><?php esc_html_e( 'How the template is called in this list. Choose which certificates it draws in the editor.', 'events-certificates' ); ?></p>
							</div>
							<div class="form-field template-from-wrap">
								<label for="template-from"><?php esc_html_e( 'Start from', 'events-certificates' ); ?></label>
								<select name="template-from" id="template-from" class="postform" aria-describedby="template-from-description">
									<option value="0"><?php esc_html_e( 'The Basic design', 'events-certificates' ); ?></option>
									<option value="blank"><?php esc_html_e( 'A blank design', 'events-certificates' ); ?></option>
									<?php foreach ( Designs\templates() as $template ) : ?>
										<option value="<?php echo esc_attr( (string) $template->ID ); ?>"><?php echo esc_html( '' !== $template->post_title ? $template->post_title : __( '(no title)', 'events-certificates' ) ); ?></option>
									<?php endforeach; ?>
								</select>
								<p id="template-from-description"><?php esc_html_e( 'A copy of another template keeps its layout and texts. A blank design has only the page and the colors.', 'events-certificates' ); ?></p>
							</div>
							<p class="submit">
								<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Add new template', 'events-certificates' ); ?>">
							</p>
						</form>
					</div>
				</div>
			</div>
			<div id="col-right">
				<div class="col-wrap">
					<?php $table->views(); ?>
					<form method="get">
						<input type="hidden" name="page" value="<?php echo esc_attr( SLUG ); ?>">
						<?php
						if ( isset( $_GET['post_status'] ) && 'trash' === sanitize_key( wp_unslash( $_GET['post_status'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only keeps the list.
							echo '<input type="hidden" name="post_status" value="trash">';
						}
						$table->display();
						?>
					</form>
				</div>
			</div>
		</div>
	</div>
	<?php
}
