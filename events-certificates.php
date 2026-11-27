<?php
/**
 * Plugin Name:       Events Certificates
 * Plugin URI:        https://github.com/wpbrasil/events-certificates
 * Description:       Attendance certificates for meetups, courses and WordCamps, with an optional code and QR code anyone can verify. Participants typed, pasted, from a Meetup CSV, GatherPress or WordCamp.org's CampTix.
 * Version:           1.0.0-dev
 * Requires at least: 7.1
 * Requires PHP:      7.4
 * Author:            camaleaun
 * Author URI:        https://profiles.wordpress.org/camaleaun/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       events-certificates
 * Domain Path:       /languages
 *
 * @package EventsCertificate
 */

namespace EventsCertificate;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.0.0-dev';

// Bundled libraries (dompdf, php-qrcode), copied under EventsCertificate\Vendor\ by `evcert deps` (bin/install.sh): docs/decisions/0032.
if ( is_readable( __DIR__ . '/includes/third-party/autoload.php' ) ) {
	require_once __DIR__ . '/includes/third-party/autoload.php';
} else {
	add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'manage_options' ) ) {
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'Events Certificates: the PDF and QR code libraries are missing. In the plugin folder, run bin/install.sh (see .github/CONTRIBUTING.md).', 'events-certificates' ) );
			}
		}
	);
}
require_once __DIR__ . '/includes/install.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/environment.php';
require_once __DIR__ . '/includes/dates.php';
require_once __DIR__ . '/includes/code.php';
require_once __DIR__ . '/includes/designs.php';
require_once __DIR__ . '/includes/design-clean.php';
require_once __DIR__ . '/includes/days.php';
require_once __DIR__ . '/includes/sessions.php';
require_once __DIR__ . '/includes/countries.php';
require_once __DIR__ . '/includes/documents.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/admin-app.php';
require_once __DIR__ . '/includes/templates.php';
require_once __DIR__ . '/includes/certificates.php';
require_once __DIR__ . '/includes/pictures.php';
require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/people.php';
require_once __DIR__ . '/includes/events-admin.php';
require_once __DIR__ . '/includes/people-admin.php';
require_once __DIR__ . '/includes/generator.php';
require_once __DIR__ . '/includes/events-generator.php';
require_once __DIR__ . '/includes/bundle.php';
require_once __DIR__ . '/includes/csv.php';
require_once __DIR__ . '/includes/bundle-admin.php';
require_once __DIR__ . '/includes/abilities.php';
require_once __DIR__ . '/includes/restrictions.php';
require_once __DIR__ . '/includes/fonts.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/verify.php';
require_once __DIR__ . '/includes/verify-page.php';
require_once __DIR__ . '/includes/share-image.php';
require_once __DIR__ . '/includes/preview.php';
require_once __DIR__ . '/includes/signer.php';
require_once __DIR__ . '/includes/compat/camptix.php';
require_once __DIR__ . '/includes/compat/camptix-front.php';
require_once __DIR__ . '/includes/compat/camptix-days.php';
require_once __DIR__ . '/includes/compat/camptix-choices.php';
require_once __DIR__ . '/includes/compat/gatherpress.php';
require_once __DIR__ . '/includes/compat/wcpt.php';
require_once __DIR__ . '/includes/mine-auth.php';
require_once __DIR__ . '/includes/mine.php';
require_once __DIR__ . '/includes/assets.php';
require_once __DIR__ . '/includes/quick-generator.php';
require_once __DIR__ . '/includes/tutorial-step-image.php';
require_once __DIR__ . '/includes/design-tokens.php';
require_once __DIR__ . '/includes/tools.php';

Install\bootstrap();
Fonts\bootstrap();
Designs\bootstrap();
Events\bootstrap();
Verify\bootstrap();
VerifyPage\bootstrap();
Mine\bootstrap();
TutorialStepImage\bootstrap();
ShareImage\bootstrap();
Preview\bootstrap();
Signer\bootstrap();
Admin\bootstrap();
AdminApp\bootstrap();
Templates\bootstrap();
EventsAdmin\bootstrap();
PeopleAdmin\bootstrap();
Generator\bootstrap();
EventsGenerator\bootstrap();
BundleAdmin\bootstrap();
Abilities\bootstrap();
QuickGenerator\bootstrap();
DesignTokens\bootstrap();
Tools\bootstrap();
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/includes/cli.php';
	Cli\bootstrap();
}

// Integrations with other plugins register on this action, after every
// plugin has loaded; each checks that its plugin is active.
add_action( 'evcert_register_compat', 'EventsCertificate\Compat\CampTix\register' );
add_action( 'evcert_register_compat', 'EventsCertificate\Compat\GatherPress\register' );
add_action( 'evcert_register_compat', 'EventsCertificate\Compat\WCPT\register' );
add_action(
	'plugins_loaded',
	static function () {
		/**
		 * Fires after all plugins have loaded, for the integrations with
		 * other plugins (CampTix, GatherPress) to register.
		 */
		do_action( 'evcert_register_compat' );
	},
	20
);
