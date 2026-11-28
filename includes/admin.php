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
 * Registers the settings with a settings group: the plugin's own, or the one
 * of the form they are shown in (CampTix's Setup).
 */
function register_settings() {
	/**
	 * Filters the settings group the plugin's settings are saved with: the
	 * option_page of the form that shows its fields.
	 *
	 * @param string $group Settings group.
	 */
	$group = (string) apply_filters( 'evcert_settings_group', GROUP );

	register_setting(
		$group,
		Settings\OPTION,
		array(
			'type'              => 'object',
			'sanitize_callback' => 'EventsCertificate\Admin\sanitize_posted',
			'default'           => Settings\defaults(),
		)
	);
}

/**
 * Keeps what the signature field posted: the paths (a JSON of the viewBox and filled paths) and the picture (the ID of an
 * attachment of the Media Library). Each is cleaned again here; an empty one clears the option.
 *
 * @param array<string, mixed> $posted The settings posted.
 */
function save_signature_choice( array $posted ) {
	$paths = json_decode( \EventsCertificate\Events\clean_vector( (string) ( $posted['signature_json'] ?? '' ) ), true );
	$paths = is_array( $paths ) ? \EventsCertificate\Signer\sanitize_signature( $paths ) : null;
	if ( $paths && ! empty( $paths['paths'] ) ) {
		update_option( \EventsCertificate\Signer\SIGNATURE_OPTION, $paths, false );
	} else {
		delete_option( \EventsCertificate\Signer\SIGNATURE_OPTION );
	}
	// A picture is the attachment of the Media Library (the option keeps only its ID).
	$attachment = \EventsCertificate\Pictures\clean( $posted['signature_attachment'] ?? '' );
	if ( '' !== $attachment ) {
		update_option( \EventsCertificate\Signer\SIGNATURE_ATTACHMENT_OPTION, (int) $attachment, false );
	} else {
		delete_option( \EventsCertificate\Signer\SIGNATURE_ATTACHMENT_OPTION );
	}
}

/**
 * Sanitizes the settings posted by a settings form. options.php updates every
 * option of the form's settings group, with null for those not posted (the
 * other tabs of CampTix's Setup, for example): then the saved settings stay.
 * It has to happen here, because sanitizing runs before the pre_update_option
 * filters and would turn null into the defaults.
 *
 * @param mixed $value Posted value.
 * @return array<string, mixed>
 */
function sanitize_posted( $value ) {
	if ( null === $value ) {
		return Settings\get();
	}
	// The page with the "Check a certificate" form is its own option; its slug becomes the certificates' slug.
	if ( is_array( $value ) && array_key_exists( 'verify_page', $value ) ) {
		$page = absint( $value['verify_page'] );
		$page = $page && 'page' === get_post_type( $page ) ? $page : 0;
		update_option( \EventsCertificate\VerifyPage\OPTION, $page, false );
		if ( $page && 'publish' === get_post_status( $page ) ) {
			$value['slug'] = (string) get_post_field( 'post_name', $page );
		}
		unset( $value['verify_page'] );
	}
	// The signature chosen in the field (its picture, or its paths): what is posted is what is kept, each one its own option.
	if ( is_array( $value ) && ( array_key_exists( 'signature_json', $value ) || array_key_exists( 'signature_image', $value ) ) ) {
		save_signature_choice( $value );
		unset( $value['signature_json'], $value['signature_image'], $value['signature_attachment'] );
	}
	// The signature is its own option (fields of Signer\parse_svg()): pasted SVG code replaces it.
	if ( is_array( $value ) && ( isset( $value['signature_svg'] ) || isset( $value['signature_remove'] ) ) ) {
		if ( ! empty( $value['signature_remove'] ) ) {
			delete_option( \EventsCertificate\Signer\SIGNATURE_OPTION );
		} elseif ( '' !== trim( (string) ( $value['signature_svg'] ?? '' ) ) ) {
			$signature = \EventsCertificate\Signer\sanitize_signature( \EventsCertificate\Signer\parse_svg( (string) $value['signature_svg'] ) );
			if ( $signature && $signature['paths'] ) {
				update_option( \EventsCertificate\Signer\SIGNATURE_OPTION, $signature, false );
			} else {
				add_settings_error( Settings\OPTION, 'evcert_signature', __( 'The signature was not saved: paste the code of an SVG file with filled paths.', 'events-certificates' ) );
			}
		}
		unset( $value['signature_svg'], $value['signature_remove'] );
	}
	// A setting whose field isn't shown (CampTix's self-issuing without CampTix) keeps its value.
	if ( is_array( $value ) && ! array_key_exists( 'self_issue', $value ) ) {
		$value['self_issue'] = Settings\value( 'self_issue' );
	}
	// The document fields: sent only with the declaration's CampTix form (the hidden mark says they were on it).
	if ( is_array( $value ) && ! array_key_exists( 'document_sent', $value ) ) {
		$value['document_country']   = Settings\value( 'document_country' );
		$value['document_foreigner'] = Settings\value( 'document_foreigner' );
	}
	unset( $value['document_sent'] );
	// The declaration box: sent only when ticked; its hidden mark says the field was on the form (else it keeps its value).
	if ( is_array( $value ) && ! array_key_exists( 'declaration_sent', $value ) ) {
		$value['declaration'] = Settings\value( 'declaration' );
	}
	unset( $value['declaration_sent'] );
	// The event's days are their own option (includes/days.php).
	if ( is_array( $value ) && isset( $value['days'] ) && is_array( $value['days'] ) ) {
		\EventsCertificate\Days\save( $value['days'] );
		unset( $value['days'] );
	}

	return Settings\sanitize( $value );
}

/**
 * Adds the settings sections and fields to a settings page.
 *
 * @param string $page Settings page slug (do_settings_sections()).
 */
function add_settings_fields( $page ) {
	// The script and the styles of the settings, on the page of the plugin and in CampTix's and GatherPress's tabs.
	$style  = \EventsCertificate\Assets\src( 'settings', 'css' );
	$script = \EventsCertificate\Assets\src( 'settings', 'js' );
	wp_enqueue_style( 'evcert-settings', $style['url'], array(), $style['version'] );
	wp_enqueue_script( 'evcert-settings', $script['url'], array(), $script['version'], true );

	// Self-issuing and the declaration's form come from CampTix (WordCamp.org): only shown with it.
	$camptix = \EventsCertificate\Environment\has_camptix();

	// 1. The switches: what the event needs, everything off until turned on (decision 0029).
	add_settings_section(
		'evcert_features',
		__( 'Features', 'events-certificates' ),
		__NAMESPACE__ . '\render_features_section',
		$page
	);
	add_settings_field(
		'evcert_verification',
		__( 'Verification', 'events-certificates' ),
		__NAMESPACE__ . '\render_verification_field',
		$page,
		'evcert_features',
		array( 'label_for' => 'evcert_verification' )
	);
	// The declaration page is a switch on every site: it also turns on the template editor's Declaration tab (the second page's design).
	add_settings_field(
		'evcert_declaration',
		__( 'Declaration page', 'events-certificates' ),
		__NAMESPACE__ . '\render_declaration_field',
		$page,
		'evcert_features',
		array( 'label_for' => 'evcert_declaration' )
	);
	if ( $camptix ) {
		add_settings_field(
			'evcert_self_issue',
			__( 'Attendees get their own', 'events-certificates' ),
			__NAMESPACE__ . '\render_self_issue_field',
			$page,
			'evcert_features',
			array( 'label_for' => 'evcert_self_issue' )
		);
	}

	// 2. What goes on every certificate.
	add_settings_section(
		'evcert_issuing',
		__( 'Issuing', 'events-certificates' ),
		'__return_false',
		$page
	);
	add_settings_field(
		'evcert_signer',
		__( 'Signed by', 'events-certificates' ),
		__NAMESPACE__ . '\render_signer_field',
		$page,
		'evcert_issuing',
		array( 'label_for' => 'evcert_signer_name' )
	);
	// The signature has a row of its own, as the Site Icon has in Settings > General: the label, then the button and its help.
	add_settings_field(
		'evcert_signature',
		__( 'Certificate signature', 'events-certificates' ),
		__NAMESPACE__ . '\render_signature_field',
		$page,
		'evcert_issuing'
	);
	add_settings_field(
		'evcert_location',
		__( 'Event location', 'events-certificates' ),
		__NAMESPACE__ . '\render_location_field',
		$page,
		'evcert_issuing',
		array( 'label_for' => 'evcert_location' )
	);
	add_settings_field(
		'evcert_languages',
		__( 'Languages', 'events-certificates' ),
		__NAMESPACE__ . '\render_languages_field',
		$page,
		'evcert_issuing',
		array( 'label_for' => 'evcert_languages_add' )
	);
	add_settings_field(
		'evcert_issued_date',
		__( 'Date of issue', 'events-certificates' ),
		__NAMESPACE__ . '\render_issued_date_field',
		$page,
		'evcert_issuing'
	);
	add_settings_field(
		'evcert_certificate_modes',
		__( 'Certificate colors', 'events-certificates' ),
		__NAMESPACE__ . '\render_certificate_modes_field',
		$page,
		'evcert_issuing'
	);

	// 3. Verification: only matters with the feature on (the script hides the rows while it is off).
	add_settings_section(
		'evcert_authenticity',
		__( 'Verification', 'events-certificates' ),
		__NAMESPACE__ . '\render_authenticity_section',
		$page
	);
	add_settings_field(
		'evcert_verify_page',
		__( 'Check page', 'events-certificates' ),
		__NAMESPACE__ . '\render_verify_page_field',
		$page,
		'evcert_authenticity',
		array(
			'label_for' => 'evcert_verify_page',
			'class'     => 'evcert-needs-verification',
		)
	);
	add_settings_field(
		'evcert_slug',
		__( 'Certificate address', 'events-certificates' ),
		__NAMESPACE__ . '\render_slug_field',
		$page,
		'evcert_authenticity',
		array(
			'label_for' => 'evcert_slug',
			'class'     => 'evcert-needs-verification',
		)
	);
	add_settings_field(
		'evcert_force_public',
		__( 'Public site', 'events-certificates' ),
		__NAMESPACE__ . '\render_force_public_field',
		$page,
		'evcert_authenticity',
		array(
			'label_for' => 'evcert_force_public',
			'class'     => 'evcert-needs-verification',
		)
	);

	// 4. The declaration (CampTix): the document, and the days it declares.
	if ( $camptix ) {
		add_settings_section(
			'evcert_document',
			__( 'Identification document', 'events-certificates' ),
			__NAMESPACE__ . '\render_document_section',
			$page
		);
		add_settings_field(
			'evcert_document_country',
			__( 'Document', 'events-certificates' ),
			__NAMESPACE__ . '\render_document_country_field',
			$page,
			'evcert_document',
			array(
				'label_for' => 'evcert_document_country',
				'class'     => 'evcert-needs-declaration',
			)
		);
		add_settings_field(
			'evcert_document_foreigner',
			__( 'Not from that country', 'events-certificates' ),
			__NAMESPACE__ . '\render_document_foreigner_field',
			$page,
			'evcert_document',
			array( 'class' => 'evcert-needs-declaration' )
		);
		add_settings_section(
			'evcert_days',
			__( 'Event days', 'events-certificates' ),
			__NAMESPACE__ . '\render_days_section',
			$page
		);
	}

	// 5. Getting a certificate by e-mail: how the e-mail is proven.
	add_settings_section(
		'evcert_mine',
		__( 'Get my certificate', 'events-certificates' ),
		__NAMESPACE__ . '\render_mine_section',
		$page
	);
	add_settings_field(
		'evcert_email_auth',
		__( 'Authentication', 'events-certificates' ),
		__NAMESPACE__ . '\render_email_auth_field',
		$page,
		'evcert_mine',
		array( 'label_for' => 'evcert_email_auth' )
	);

	// 6. Data.
	add_settings_section(
		'evcert_data',
		__( 'Data', 'events-certificates' ),
		'__return_false',
		$page
	);
	add_settings_field(
		'evcert_delete_data',
		__( 'Uninstall', 'events-certificates' ),
		__NAMESPACE__ . '\render_delete_data_field',
		$page,
		'evcert_data',
		array( 'label_for' => 'evcert_delete_data' )
	);
}

/**
 * What the switches are: everything off until turned on.
 */
function render_features_section() {
	echo '<p>' . esc_html__( 'Turn on what your event needs. Each one is off by default: they are customs of some countries, not needed everywhere.', 'events-certificates' ) . '</p>';
}

/**
 * Explains whether certificates get a QR code on this site, and why not.
 */
function render_authenticity_section() {
	if ( ! Environment\verification_enabled() ) {
		echo '<p>' . esc_html__( 'Verification is off: certificates carry no QR code, code or verification address. Turn it on above, in Features, if you want anyone to be able to check them.', 'events-certificates' ) . '</p>';
		return;
	}
	if ( Environment\is_public() ) {
		echo '<p>' . esc_html__( 'Certificates issued on this site carry a QR code and a code anyone can check on its verification page.', 'events-certificates' ) . '</p>';
		return;
	}

	$labels  = array(
		'playground'        => __( 'it runs on WordPress Playground', 'events-certificates' ),
		'local-environment' => __( 'its environment type is “local”', 'events-certificates' ),
		'local-host'        => __( 'its address only works on this computer or network', 'events-certificates' ),
		'private-ip'        => __( 'its address is a private IP', 'events-certificates' ),
	);
	$reasons = array();
	foreach ( Environment\non_public_reasons() as $reason ) {
		$reasons[] = $labels[ $reason ] ?? $reason;
	}

	echo '<div class="notice notice-warning inline"><p>';
	printf(
		/* translators: %s: reasons, e.g. "its address only works on this computer or network". */
		esc_html__( 'This site doesn’t look public (%s), so certificates show a placeholder where the QR code and the verification address go: nobody else could open them.', 'events-certificates' ),
		esc_html( implode( '; ', $reasons ) )
	);
	echo '</p></div>';
}

/**
 * Switch: verification (a code and a QR code on the certificates).
 */
function render_verification_field() {
	printf(
		'<label><input type="checkbox" id="evcert_verification" name="%1$s[verification]" value="1" %2$s> %3$s</label><p class="description">%4$s</p>',
		esc_attr( Settings\OPTION ),
		checked( Settings\value( 'verification' ), true, false ),
		esc_html__( 'Print a code and a QR code on the certificates', 'events-certificates' ),
		esc_html__( 'Anyone can then check a certificate at its address (needs a public site). Details in the Verification section below.', 'events-certificates' )
	);
}

/**
 * Field: force public.
 */
function render_force_public_field() {
	printf(
		'<label><input type="checkbox" id="evcert_force_public" name="%1$s[force_public]" value="1" %2$s> %3$s</label><p class="description">%4$s</p>',
		esc_attr( Settings\OPTION ),
		checked( Settings\value( 'force_public' ), true, false ),
		esc_html__( 'Treat this site as public', 'events-certificates' ),
		esc_html__( 'For a staging site with a public address. Only turn it on when anyone can open this site’s pages.', 'events-certificates' )
	);
}

/**
 * The event's days: a card each with its name per language, its kind and its tables (includes/days.php).
 * Until saved they come from the schedule.
 */
function render_days_section() {
	$days    = \EventsCertificate\Days\all();
	$locales = \EventsCertificate\Verify\locales();
	echo '<div class="evcert-needs-declaration">';
	if ( ! $days ) {
		printf( '<p class="description">%s</p></div>', esc_html__( 'No days yet: they come from the schedule (Sessions) once it has dates.', 'events-certificates' ) );
		return;
	}
	$name = Settings\OPTION . '[days]';
	echo '<div class="evcert-days">';
	foreach ( $days as $i => $day ) {
		echo '<fieldset class="evcert-day">';
		printf(
			'<legend><input type="hidden" name="%1$s[%2$d][date]" value="%3$s"><strong>%4$s</strong></legend>',
			esc_attr( $name ),
			(int) $i,
			esc_attr( $day['date'] ),
			esc_html( \EventsCertificate\Days\short_date( $day ) )
		);
		echo '<div class="evcert-day__names">';
		foreach ( $locales as $locale ) {
			printf(
				'<label><span>%5$s</span><input type="text" class="regular-text" name="%1$s[%2$d][names][%3$s]" value="%4$s" lang="%6$s"></label>',
				esc_attr( $name ),
				(int) $i,
				esc_attr( $locale ),
				esc_attr( $day['names'][ $locale ] ?? '' ),
				esc_html( \EventsCertificate\Verify\locale_name( $locale ) ),
				esc_attr( str_replace( '_', '-', $locale ) )
			);
		}
		echo '</div>';
		printf( '<p><label>%1$s <select name="%2$s[%3$d][mode]">', esc_html__( 'Kind', 'events-certificates' ), esc_attr( $name ), (int) $i );
		foreach ( array(
			'sessions' => __( 'Talks', 'events-certificates' ),
			'table'    => __( 'Tables', 'events-certificates' ),
		) as $mode => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $mode ), selected( $day['mode'], $mode, false ), esc_html( $label ) );
		}
		echo '</select></label></p><div class="evcert-day__tables">';
		foreach ( $locales as $locale ) {
			printf(
				'<label><span>%5$s: %7$s</span><textarea rows="3" class="large-text" name="%1$s[%2$d][tables][%3$s]" lang="%6$s">%4$s</textarea></label>',
				esc_attr( $name ),
				(int) $i,
				esc_attr( $locale ),
				esc_textarea( implode( "\n", (array) ( $day['tables'][ $locale ] ?? array() ) ) ),
				esc_html( \EventsCertificate\Verify\locale_name( $locale ) ),
				esc_attr( str_replace( '_', '-', $locale ) ),
				esc_html__( 'tables, one per line', 'events-certificates' )
			);
		}
		echo '</div></fieldset>';
	}
	echo '</div></div>';
}

/**
 * Field: the page with the "Check a certificate" form.
 */
function render_verify_page_field() {
	echo wp_kses(
		wp_dropdown_pages(
			array(
				'name'              => Settings\OPTION . '[verify_page]',
				'id'                => 'evcert_verify_page',
				'selected'          => (int) get_option( \EventsCertificate\VerifyPage\OPTION, 0 ),
				'show_option_none'  => esc_html__( '— None (the plugin’s own page) —', 'events-certificates' ),
				'option_none_value' => '0',
				'echo'              => 0,
			)
		),
		array(
			'select' => array(
				'name'  => true,
				'id'    => true,
				'class' => true,
			),
			'option' => array(
				'value'    => true,
				'class'    => true,
				'selected' => true,
			),
		)
	);
	printf( '<p class="description">%s</p>', esc_html__( 'The page with the Check a certificate block. Certificates live under its address.', 'events-certificates' ) );
}

/**
 * Field: certificate address (the page's when there is one).
 */
function render_slug_field() {
	$page = \EventsCertificate\VerifyPage\page_id();
	if ( $page ) {
		printf(
			'<input type="hidden" name="%1$s[slug]" value="%2$s"><code>%3$s%2$s/&lt;%4$s&gt;/</code><p class="description">%5$s</p>',
			esc_attr( Settings\OPTION ),
			esc_attr( Settings\value( 'slug' ) ),
			esc_html( trailingslashit( home_url() ) ),
			esc_html_x( 'code', 'certificate code placeholder', 'events-certificates' ),
			esc_html__( 'Set by the check page’s address (change it in the page).', 'events-certificates' )
		);
		return;
	}
	printf(
		'<input type="text" id="evcert_slug" name="%2$s[slug]" value="%3$s" class="regular-text code"><p class="description">%4$s<br><code>%1$s%3$s/&lt;%5$s&gt;/</code></p>',
		esc_html( trailingslashit( home_url() ) ),
		esc_attr( Settings\OPTION ),
		esc_attr( Settings\value( 'slug' ) ),
		esc_html__( 'The address printed on certificates and in their QR code. Change it before issuing: printed certificates keep the old one.', 'events-certificates' ),
		/* translators: Stands for a certificate code in an example address, as in https://example.com/certificate/<code>/. */
		esc_html_x( 'code', 'certificate code placeholder', 'events-certificates' )
	);
}

/**
 * Field: event location.
 */
function render_location_field() {
	printf(
		'<input type="text" id="evcert_location" name="%1$s[location]" value="%2$s" class="regular-text" placeholder="%3$s"><p class="description">%4$s</p>',
		esc_attr( Settings\OPTION ),
		esc_attr( Settings\value( 'location' ) ),
		esc_attr__( 'City, Country', 'events-certificates' ),
		esc_html__( 'Shown where the design uses the Location field. On WordCamp.org it comes from the WordCamp’s location.', 'events-certificates' )
	);
}

/**
 * Switch: who gets their own certificate from the tickets page.
 */
function render_self_issue_field() {
	$options = array(
		'attended'  => __( 'When marked as present at the event', 'events-certificates' ),
		'confirmed' => __( 'With any confirmed ticket', 'events-certificates' ),
		'off'       => __( 'No: only organizers issue certificates', 'events-certificates' ),
	);
	printf( '<select id="evcert_self_issue" name="%s[self_issue]">', esc_attr( Settings\OPTION ) );
	foreach ( $options as $value => $label ) {
		printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( Settings\value( 'self_issue' ), $value, false ), esc_html( $label ) );
	}
	echo '</select>';
	printf( '<p class="description">%s</p>', esc_html__( 'From the link in their ticket e-mail, attendees check how their name goes on the certificate and get it with its code.', 'events-certificates' ) );
}

/**
 * The languages certificates are shown in, as tags: the site's own, and the ones added from the installed languages.
 */
function render_languages_field() {
	$installed = array_values( array_unique( array_merge( \EventsCertificate\Verify\locales(), get_available_languages() ) ) );
	$site      = get_locale();
	$chosen    = array_values( array_diff( (array) Settings\value( 'languages' ), array( $site ) ) );
	$name      = Settings\OPTION . '[languages][]';
	echo '<div class="evcert-tags" data-evcert-tags data-name="' . esc_attr( $name ) . '">';
	echo '<p class="evcert-tags__add"><select id="evcert_languages_add"><option value="">' . esc_html__( 'Choose a language…', 'events-certificates' ) . '</option>';
	foreach ( $installed as $locale ) {
		if ( $locale !== $site && ! in_array( $locale, $chosen, true ) ) {
			printf( '<option value="%1$s">%2$s</option>', esc_attr( $locale ), esc_html( \EventsCertificate\Verify\locale_name( $locale ) ) );
		}
	}
	printf( '</select> <button type="button" class="button" data-evcert-tag-add>%s</button></p>', esc_html__( 'Add', 'events-certificates' ) );
	echo '<ul class="evcert-tags__list" role="list">';
	printf( '<li class="is-site"><span>%s</span> <span class="description">%s</span></li>', esc_html( \EventsCertificate\Verify\locale_name( $site ) ), esc_html__( 'site language', 'events-certificates' ) );
	foreach ( $chosen as $locale ) {
		printf(
			'<li><input type="hidden" name="%1$s" value="%2$s"><span>%3$s</span> <button type="button" class="evcert-tags__remove" data-evcert-tag-remove aria-label="%4$s">×</button></li>',
			esc_attr( $name ),
			esc_attr( $locale ),
			esc_html( \EventsCertificate\Verify\locale_name( $locale ) ),
			/* translators: %s: language name. */
			esc_attr( sprintf( __( 'Remove %s', 'events-certificates' ), \EventsCertificate\Verify\locale_name( $locale ) ) )
		);
	}
	echo '</ul></div>';
	printf( '<p class="description">%s</p>', esc_html__( 'The languages a certificate can be shown in, and the ones you can write its texts in. With none added, every installed language is used.', 'events-certificates' ) );
}

/**
 * Says where the page is and what it does.
 */
function render_mine_section() {
	$id = \EventsCertificate\Mine\page_id();
	printf(
		'<p>%s</p>',
		$id
			? sprintf(
				/* translators: %s: link to the page. */
				esc_html__( 'Attendees type the e-mail they registered with in %s and get their certificate, if they were marked as present. You can also use the Get my certificate block or the [evcert_mine] shortcode on any page.', 'events-certificates' ),
				'<a href="' . esc_url( (string) get_permalink( $id ) ) . '">' . esc_html( (string) get_the_title( $id ) ) . '</a>'
			)
			: esc_html__( 'Attendees type the e-mail they registered with and get their certificate, if they were marked as present. Add the Get my certificate block or the [evcert_mine] shortcode to a page.', 'events-certificates' )
	);
}

/**
 * Select: how the e-mail is proven; checkbox: send the link too.
 */
function render_email_auth_field() {
	$options = array(
		'none'    => __( 'No authentication: the e-mail is enough', 'events-certificates' ),
		'simple'  => __( 'Simple: the e-mail shows a simplified view; the certificate asks for a code', 'events-certificates' ),
		'digits4' => __( '4-digit code sent by e-mail', 'events-certificates' ),
		'digits6' => __( '6-digit code sent by e-mail', 'events-certificates' ),
		'link'    => __( 'Link only: a one-use link sent by e-mail', 'events-certificates' ),
	);
	printf( '<select id="evcert_email_auth" name="%s[email_auth]">', esc_attr( Settings\OPTION ) );
	foreach ( $options as $value => $label ) {
		printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( Settings\value( 'email_auth' ), $value, false ), esc_html( $label ) );
	}
	echo '</select>';
	printf(
		'<p><label><input type="checkbox" id="evcert_email_auth_link" name="%1$s[email_auth_link]" value="1" %2$s> %3$s</label></p>',
		esc_attr( Settings\OPTION ),
		checked( Settings\value( 'email_auth_link' ), true, false ),
		esc_html__( 'Send the link too (for the 4 and 6-digit codes)', 'events-certificates' )
	);
	printf( '<p class="description">%s</p>', esc_html__( 'A code is good for 15 minutes and 5 tries; a link works once, for an hour. Except with “No authentication” and “Simple”, the page never says whether an e-mail is on the list.', 'events-certificates' ) );
}

/**
 * Says what the identification document is for, and that it is optional.
 */
function render_document_section() {
	printf(
		'<p>%s</p>',
		esc_html__( 'Optional. Asked on the declaration page, a custom of some countries, such as Brazil. Choose the country whose document you ask for, or none. Needs the declaration page turned on above.', 'events-certificates' )
	);
}

/**
 * Switch: whether attendees can add a declaration page to their certificate.
 */
function render_declaration_field() {
	printf(
		'<label><input type="checkbox" id="evcert_declaration" name="%1$s[declaration]" value="1" %2$s> %3$s</label><p class="description">%4$s</p>',
		esc_attr( Settings\OPTION ),
		checked( Settings\value( 'declaration' ), true, false ),
		esc_html__( 'Offer attendees a declaration page', 'events-certificates' ),
		esc_html__( 'A second page of the certificate where they declare the days and talks they attended, with an identification document if you ask for one. A custom of some countries, such as Brazil.', 'events-certificates' )
	);
	// Unticked is sent as nothing: this keeps it a saved "off".
	printf( '<input type="hidden" name="%s[declaration_sent]" value="1">', esc_attr( Settings\OPTION ) );
}

/**
 * Field: the country whose identification document the declaration page asks for: one, or none (the default).
 */
function render_document_country_field() {
	$chosen = Settings\value( 'document_country' );
	$names  = \EventsCertificate\Countries\all();
	// Marks that the document fields are on this form (a ticked-off box is sent as nothing).
	printf( '<input type="hidden" name="%s[document_sent]" value="1">', esc_attr( Settings\OPTION ) );
	printf( '<select id="evcert_document_country" name="%s[document_country]">', esc_attr( Settings\OPTION ) );
	printf( '<option value="">%s</option>', esc_html__( 'None: no document is asked', 'events-certificates' ) );
	foreach ( \EventsCertificate\Documents\catalog() as $code => $document ) {
		printf(
			'<option value="%1$s"%2$s>%3$s</option>',
			esc_attr( $code ),
			selected( $chosen, $code, false ),
			/* translators: 1: a name, a label or a date, 2: its value or name (e.g. "Language: English", "Contributor Day", "Brazil: CPF"). */
			esc_html( sprintf( __( '%1$s: %2$s', 'events-certificates' ), $names[ $code ] ?? $code, $document['label'] ) )
		);
	}
	echo '</select>';
	printf( '<p class="description">%s</p>', esc_html__( 'The attendee types that document on the declaration page, checked by the country’s own rule.', 'events-certificates' ) );
}

/**
 * Field: whether an attendee may say they are not from that country.
 */
function render_document_foreigner_field() {
	printf(
		'<label><input type="checkbox" id="evcert_document_foreigner" name="%1$s[document_foreigner]" value="1" %2$s> %3$s</label><p class="description">%4$s</p>',
		esc_attr( Settings\OPTION ),
		checked( Settings\value( 'document_foreigner' ), true, false ),
		esc_html__( 'Let attendees say they are not from that country', 'events-certificates' ),
		esc_html__( 'A box “Not from the event’s country” (in Portuguese, “Sou estrangeiro”): they give a passport instead, or nothing at all.', 'events-certificates' )
	);
}

/**
 * Field: who signs (name, role and signature), when no WordCamp organizer
 * is marked as the signer. The signature can be chosen from an SVG file (shown before saving) or pasted.
 */
function render_signer_field() {
	$option = Settings\OPTION;
	printf(
		'<input type="text" class="regular-text" id="evcert_signer_name" name="%1$s[signer_name]" value="%2$s" placeholder="%3$s"> <input type="text" class="regular-text" name="%1$s[signer_role]" value="%4$s" placeholder="%5$s" aria-label="%6$s">',
		esc_attr( $option ),
		esc_attr( (string) Settings\value( 'signer_name' ) ),
		esc_attr__( 'Name', 'events-certificates' ),
		esc_attr( (string) Settings\value( 'signer_role' ) ),
		esc_attr__( 'Role, e.g. Meetup organizer', 'events-certificates' ),
		esc_attr__( 'Role of who signs', 'events-certificates' )
	);
}

/**
 * Field: the signature, as the Site Icon's: a mount for the button (the script draws it) and the dialog it opens.
 */
function render_signature_field() {
	$signature = get_option( \EventsCertificate\Signer\SIGNATURE_OPTION );
	echo '<div id="evcert-signature-field"></div>';
	enqueue_signature_field( is_array( $signature ) && ! empty( $signature['paths'] ) ? $signature : null );
}

/**
 * Which dialog chooses the signature: the media library's own (`media`, as the Site Icon's) or the plugin's React one (`react`).
 * It is a switch for development, not a setting: the option `evcert_signature_chooser` or the filter `evcert_signature_chooser`
 * change it, and a later version may change the default.
 *
 * @return string media|react
 */
function signature_chooser() {
	/**
	 * Which dialog chooses the signature of Settings.
	 *
	 * @param string $chooser media (the WordPress media modal, with an Edit SVG tab) or react.
	 */
	$chooser = (string) apply_filters( 'evcert_signature_chooser', (string) get_option( 'evcert_signature_chooser', 'media' ) );

	return 'react' === $chooser ? 'react' : 'media';
}

/**
 * The media modal for a screen's picture chooser (a logo, a signature): its scripts and the styles of the tab and the toast that
 * the plugin adds, and the script's dependency on it. Nothing is loaded when the `react` dialog is chosen
 * (signature_chooser()): the picture field then opens its own.
 *
 * @param string $handle The script that opens the chooser.
 */
function enqueue_media_chooser( $handle ) {
	if ( 'media' !== signature_chooser() || ! wp_script_is( $handle, 'registered' ) ) {
		return;
	}
	$style = \EventsCertificate\Assets\src( 'signature-chooser', 'css' );
	wp_enqueue_media();
	wp_enqueue_style( 'wp-components' );
	wp_enqueue_style( 'evcert-signature-chooser', $style['url'], array( 'wp-components' ), $style['version'] );
	wp_scripts()->registered[ $handle ]->deps[] = 'media-views';
}

/**
 * The signature field's script, styles and what it starts from: the paths drawn, or the picture, and the attachment it came from.
 *
 * @param array<string, mixed>|null $signature The paths of the signature, or null.
 */
function enqueue_signature_field( $signature ) {
	if ( ! wp_script_is( 'evcert-settings-signature', 'registered' ) ) {
		return;
	}
	$style = \EventsCertificate\Assets\src( 'signature-chooser', 'css' );
	wp_enqueue_style( 'wp-components' );
	wp_enqueue_style( 'evcert-signature-chooser', $style['url'], array( 'wp-components' ), $style['version'] );
	$chooser = signature_chooser();
	if ( 'media' === $chooser ) {
		// The media modal and its scripts: the dialog is WordPress's own, with a tab added.
		wp_enqueue_media();
		wp_scripts()->registered['evcert-settings-signature']->deps[] = 'media-views';
	}
	wp_add_inline_script(
		'evcert-settings-signature',
		'window.evcertSignatureField = ' . wp_json_encode(
			array(
				'option'     => Settings\OPTION,
				'image'      => \EventsCertificate\Pictures\src( get_option( \EventsCertificate\Signer\SIGNATURE_ATTACHMENT_OPTION, 0 ) ),
				'json'       => $signature ? (string) wp_json_encode( $signature ) : '',
				'attachment' => (int) get_option( \EventsCertificate\Signer\SIGNATURE_ATTACHMENT_OPTION, 0 ),
				'maxUpload'  => size_format( wp_max_upload_size() ),
				'canUpload'  => current_user_can( 'upload_files' ),
				'suggested'  => '600 × 220',
				'chooser'    => $chooser,
			)
		) . ';',
		'before'
	);
	wp_enqueue_script( 'evcert-settings-signature' );
}

/**
 * Field: the date certificates show as their date of issue.
 */
function render_issued_date_field() {
	$options = array(
		'issued'    => array( __( 'The day each certificate was issued', 'events-certificates' ), __( 'Each certificate has its own date.', 'events-certificates' ) ),
		'event_end' => array( __( 'The event’s last day', 'events-certificates' ), __( 'Every certificate of the event has the same date.', 'events-certificates' ) ),
	);
	echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Date of issue', 'events-certificates' ) . '</legend>';
	foreach ( $options as $value => $texts ) {
		printf(
			'<label><input type="radio" name="%1$s[issued_date]" value="%2$s" %3$s> %4$s</label><p class="description">%5$s</p>',
			esc_attr( Settings\OPTION ),
			esc_attr( $value ),
			checked( Settings\value( 'issued_date' ), $value, false ),
			esc_html( $texts[0] ),
			esc_html( $texts[1] )
		);
	}
	echo '</fieldset>';
}

/**
 * Field: the colors a certificate comes in (light only, or light and dark).
 */
function render_certificate_modes_field() {
	$options = array(
		'light' => __( 'Light only', 'events-certificates' ),
		'both'  => __( 'Light and dark', 'events-certificates' ),
	);
	echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Certificate colors', 'events-certificates' ) . '</legend>';
	foreach ( $options as $value => $label ) {
		printf(
			'<label><input type="radio" name="%1$s[certificate_modes]" value="%2$s" %3$s> %4$s</label><br>',
			esc_attr( Settings\OPTION ),
			esc_attr( $value ),
			checked( Settings\value( 'certificate_modes' ), $value, false ),
			esc_html( $label )
		);
	}
	echo '<p class="description">' . esc_html__( 'The PDF is always light. With "Light and dark", the page that checks a certificate offers both, and the template editor shows the dark colors.', 'events-certificates' ) . '</p></fieldset>';
}

/**
 * Field: delete data on uninstall.
 */
function render_delete_data_field() {
	printf(
		'<label><input type="checkbox" id="evcert_delete_data" name="%1$s[delete_data]" value="1" %2$s> %3$s</label><p class="description">%4$s</p>',
		esc_attr( Settings\OPTION ),
		checked( Settings\value( 'delete_data' ), true, false ),
		esc_html__( 'Delete all certificates when the plugin is deleted', 'events-certificates' ),
		esc_html__( 'Printed certificates would no longer be verifiable. Leave it off unless you are sure.', 'events-certificates' )
	);
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
		<?php settings_errors(); // "Settings saved." and errors after the settings form below (options.php). ?>
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

/**
 * The plugin's own settings screen, on the Configurações submenu of the
 * standalone Certificates menu (sites without an integration that hosts the
 * settings in one of its tabs).
 */
function render_settings_page() {
	if ( ! current_user_can( CAPABILITY ) ) {
		return;
	}
	?>
	<div class="wrap evcert-settings">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Settings', 'events-certificates' ); ?></h1>
		<?php \EventsCertificate\BundleAdmin\render_buttons( 'settings' ); ?>
		<hr class="wp-header-end">
		<?php settings_errors(); ?>
		<?php render_settings_body(); ?>
	<?php
}

/**
 * The body of the settings screen: its tabs (Features, Issuing, Verification, Declaration, Data, Tools) and the form. The screen of the plugin wraps it with its title; an
 * integration that has the settings as a tab of its own (GatherPress > Settings > Certificates) prints it inside that tab (`$hosted`: the tabs are a list of links, and Export and
 * Import are with them), with its own form, as GatherPress's own Tools tab has.
 *
 * @param bool $hosted Whether it is inside another plugin's settings tab.
 */
function render_settings_body( $hosted = false ) {
	add_settings_fields( SLUG_SETTINGS );
	$sections = array_keys( (array) ( $GLOBALS['wp_settings_sections'][ SLUG_SETTINGS ] ?? array() ) );
	// One tab per group of sections; every section is on the form (the others are only hidden), so saving keeps them all.
	$tabs = array_filter(
		array(
			'features'     => array( __( 'Features', 'events-certificates' ), array( 'evcert_features' ) ),
			'certificates' => array( __( 'Issuing', 'events-certificates' ), array( 'evcert_issuing' ) ),
			'verification' => array( __( 'Verification', 'events-certificates' ), array( 'evcert_authenticity' ) ),
			'declaration'  => array( __( 'Declaration', 'events-certificates' ), array( 'evcert_document', 'evcert_days' ) ),
			'data'         => array( __( 'Data', 'events-certificates' ), array( 'evcert_data' ) ),
		),
		static function ( $tab ) use ( $sections ) {
			return (bool) array_intersect( $tab[1], $sections );
		}
	);
	// The Tools tab has no settings to save: its own forms, apart from the one of the options.
	$tabs['tools'] = array( __( 'Tools', 'events-certificates' ), array() );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which tab to show.
	$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	$current = isset( $tabs[ $current ] ) ? $current : (string) key( $tabs );
	?>
	<?php if ( $hosted ) : ?>
		<p class="evcert-settings__buttons"><?php \EventsCertificate\BundleAdmin\render_buttons( 'settings' ); ?></p>
	<?php endif; ?>
	<?php
	$tag   = $hosted ? 'ul' : 'nav';
	$class = $hosted ? 'subsubsub evcert-settings__tabs' : 'nav-tab-wrapper';
	?>
	<<?php echo esc_html( $tag ); ?> class="<?php echo esc_attr( $class ); ?>" aria-label="<?php esc_attr_e( 'Settings sections', 'events-certificates' ); ?>">
		<?php foreach ( $tabs as $id => $tab ) : ?>
			<?php
			$link = sprintf(
				'<a class="%1$s" href="%2$s"%3$s>%4$s</a>',
				esc_attr( $hosted ? ( $id === $current ? 'current' : '' ) : 'nav-tab' . ( $id === $current ? ' nav-tab-active' : '' ) ),
				esc_url( add_query_arg( 'tab', $id, settings_url() ) ),
				$id === $current ? ' aria-current="page"' : '',
				esc_html( $tab[0] )
			);
			echo $hosted ? '<li>' . $link . ( array_key_last( $tabs ) === $id ? '' : ' |' ) . '</li> ' : $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			?>
		<?php endforeach; ?>
	</<?php echo esc_html( $tag ); ?>>
	<?php if ( $hosted ) : ?>
		<br class="clear">
	<?php endif; ?>
		<?php if ( 'tools' === $current ) : ?>
			<?php \EventsCertificate\Tools\render(); ?>
		<?php else : ?>
		<form method="post" action="options.php">
			<?php settings_fields( GROUP ); ?>
			<?php foreach ( $tabs as $id => $tab ) : ?>
				<div class="evcert-tab"<?php echo $id === $current ? '' : ' hidden'; ?>>
					<?php foreach ( $tab[1] as $section ) : ?>
						<?php
						if ( ! isset( $GLOBALS['wp_settings_sections'][ SLUG_SETTINGS ][ $section ] ) ) {
							continue;
						}
						$info = $GLOBALS['wp_settings_sections'][ SLUG_SETTINGS ][ $section ];
						?>
						<h2><?php echo esc_html( $info['title'] ); ?></h2>
						<?php
						if ( $info['callback'] ) {
							call_user_func( $info['callback'], $info );
						}
						?>
						<table class="form-table" role="presentation"><?php do_settings_fields( SLUG_SETTINGS, $section ); ?></table>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
			<?php submit_button(); ?>
		</form>
		<?php endif; ?>
	<?php
}

/**
 * The address of one of the plugin's admin pages.
 *
 * @param string $slug Page.
 * @return string
 */
function page_url_of( $slug ) {
	return add_query_arg( 'page', $slug, admin_url( 'admin.php' ) );
}

/**
 * The slug of the settings screen: its own (evcert-options), or the one an integration gives it (GatherPress: certificates, a page of its Events menu).
 *
 * @return string
 */
function settings_slug() {
	/**
	 * Filters the slug of the settings screen.
	 *
	 * @param string $slug Slug.
	 */
	return (string) apply_filters( 'evcert_settings_slug', SLUG_SETTINGS );
}

/**
 * The address of the settings screen, wherever it is: a page of the plugin's own menu, or of the menu of the integration that hosts it.
 *
 * @return string
 */
function settings_url() {
	$slug   = settings_slug();
	$parent = menu_parent();
	if ( SLUG_SETTINGS !== $slug && '' !== $parent && false !== strpos( $parent, '.php' ) ) {
		return add_query_arg( 'page', $slug, admin_url( $parent ) );
	}

	return page_url_of( $slug );
}
