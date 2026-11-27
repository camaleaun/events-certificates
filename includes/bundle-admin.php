<?php
/**
 * The Export and Import screens and the buttons that lead to them, laid out like WooCommerce's product exporter and importer:
 * a card for each, and the import a wizard of four steps (upload the file, review what it has, import in batches with a
 * progress bar, done). The work is includes/bundle.php.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\BundleAdmin;

use EventsCertificate\Admin;
use EventsCertificate\Bundle;
use EventsCertificate\Csv;
use EventsCertificate\Events;
use EventsCertificate\Restrictions;

defined( 'ABSPATH' ) || exit;

/**
 * The screens' slugs.
 */
const EXPORT = 'evcert-export';
const IMPORT = 'evcert-import';

/**
 * Hooks.
 */
function bootstrap() {
	add_action( 'admin_menu', __NAMESPACE__ . '\add_pages' );
	add_action( 'admin_post_evcert_export', __NAMESPACE__ . '\export' );
	add_action( 'admin_post_evcert_import_upload', __NAMESPACE__ . '\upload' );
	add_action( 'admin_post_evcert_import_start', __NAMESPACE__ . '\start' );
	add_action( 'wp_ajax_evcert_import_step', __NAMESPACE__ . '\ajax_step' );
	// The buttons next to the titles of the lists.
	add_action(
		'evcert_certificates_title_actions',
		static function () {
			render_buttons( 'certificates' );
		},
		7
	);
	add_action(
		'evcert_events_title_actions',
		static function () {
			render_buttons( 'events' );
		},
		7
	);
	add_action(
		'evcert_templates_title_actions',
		static function () {
			render_buttons( 'templates' );
		}
	);
}

/**
 * Registers the screens with no menu item (they open from the buttons).
 */
function add_pages() {
	$screens = array(
		EXPORT => array( __( 'Export', 'events-certificates' ), 'render_export' ),
		IMPORT => array( __( 'Import', 'events-certificates' ), 'render_import' ),
	);
	foreach ( $screens as $slug => $screen ) {
		if ( ! Restrictions\allows( EXPORT === $slug ? 'export' : 'import' ) ) {
			continue;
		}
		$hook = add_submenu_page( '', $screen[0], '', Admin\CAPABILITY, $slug, __NAMESPACE__ . '\\' . $screen[1] );
		if ( $hook ) {
			add_action(
				'load-' . $hook,
				static function () use ( $screen ) {
					load( $screen[0] );
				}
			);
		}
	}
}

/**
 * Before a screen prints: its title (a page with no menu item has none of its own), the Certificates menu kept open, and the
 * styles.
 *
 * @param string $title The screen's title.
 */
function load( $title ) {
	$GLOBALS['title'] = $title; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- a page with no menu item has no title of its own.
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
	$style = \EventsCertificate\Assets\src( 'transfer', 'css' );
	wp_enqueue_style( 'evcert-transfer', $style['url'], array(), $style['version'] );
}

/**
 * A screen's address.
 *
 * @param string               $screen EXPORT or IMPORT.
 * @param array<string, mixed> $args   More query arguments.
 * @return string
 */
function url( $screen, array $args = array() ) {
	return add_query_arg( array_filter( array( 'page' => $screen ) + $args ), admin_url( 'admin.php' ) );
}

/**
 * The Export and Import buttons of a list: each opens its screen with the list's section ticked.
 *
 * @param string $section Section the list is about.
 * @param int    $event   An event, for its participants.
 */
function render_buttons( $section, $event = 0 ) {
	if ( ! current_user_can( Admin\CAPABILITY ) ) {
		return;
	}
	$args = array(
		'section' => $section,
		'event'   => $event,
	);
	if ( Restrictions\allows( 'export' ) ) {
		printf( '<a class="page-title-action" href="%1$s">%2$s</a>', esc_url( url( EXPORT, $args ) ), esc_html__( 'Export', 'events-certificates' ) );
	}
	if ( Restrictions\allows( 'import' ) ) {
		printf( '<a class="page-title-action" href="%1$s">%2$s</a>', esc_url( url( IMPORT, $args ) ), esc_html__( 'Import', 'events-certificates' ) );
	}
}

/**
 * The names and descriptions of the sections.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function labels() {
	return array(
		'templates'    => array( __( 'Templates', 'events-certificates' ), __( 'The certificate templates, with the roles they are for and which one is the default.', 'events-certificates' ) ),
		'events'       => array( __( 'Events', 'events-certificates' ), __( 'The events, with their logo and signature pictures.', 'events-certificates' ) ),
		'people'       => array( __( 'Participants', 'events-certificates' ), __( 'The participants of the events, with their status, roles and the Trash. Needs the events.', 'events-certificates' ) ),
		'certificates' => array( __( 'Certificates', 'events-certificates' ), __( 'The certificates with their codes, in any state, and the participants and events they belong to.', 'events-certificates' ) ),
		'settings'     => array( __( 'Settings', 'events-certificates' ), __( 'The settings, the signer’s signature, the event days and the secret key that checks document numbers.', 'events-certificates' ) ),
	);
}

/**
 * The sections the address asks to have ticked (all when it asks for none).
 *
 * @return string[]
 */
function ticked() {
	$ticked = isset( $_GET['section'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_GET['section'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which sections to tick.

	return $ticked ? $ticked : Bundle\SECTIONS;
}

/**
 * Stops whoever may not manage.
 */
function must_manage() {
	if ( ! current_user_can( Admin\CAPABILITY ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to export or import certificates.', 'events-certificates' ) );
	}
}

/**
 * Stops whoever may not export or import: the capability, and what the site allows (docs/decisions/0044).
 *
 * @param string $feature export or import.
 */
function must_be_allowed( $feature ) {
	must_manage();
	Restrictions\must_allow( $feature );
}

/**
 * The checkboxes of the sections.
 *
 * @param string[]                $ticked Sections ticked.
 * @param array<string, int>|null $counts How many each has in a file (the import's review), or null.
 */
function render_sections( array $ticked, $counts = null ) {
	foreach ( labels() as $key => $label ) {
		if ( null !== $counts && empty( $counts[ $key ] ) ) {
			continue;
		}
		printf(
			'<label class="evcert-sections__row"><input type="checkbox" name="sections[]" value="%1$s"%2$s> <strong>%3$s</strong>%4$s<span class="description">%5$s</span></label>',
			esc_attr( $key ),
			checked( in_array( $key, $ticked, true ), true, false ),
			esc_html( $label[0] ),
			null === $counts ? '<br>' : ' <span class="evcert-sections__count">' . esc_html( sprintf( /* translators: %s: how many are in the file. */ __( '%s in the file', 'events-certificates' ), number_format_i18n( (int) $counts[ $key ] ) ) ) . '</span><br>',
			esc_html( $label[1] )
		);
	}
}

/* ---------- Export ---------- */

/**
 * The Export screen: what to put in the file, and which event.
 */
function render_export() {
	must_be_allowed( 'export' );
	$event  = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which event to offer.
	$events = Events\all();
	?>
	<div class="wrap evcert-transfer">
		<h1 class="screen-reader-text"><?php esc_html_e( 'Export', 'events-certificates' ); ?></h1>
		<form class="evcert-card" method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="evcert_export">
			<?php wp_nonce_field( 'evcert_export' ); ?>
			<header>
				<h2><?php esc_html_e( 'Export to a file', 'events-certificates' ); ?></h2>
				<p><?php esc_html_e( 'This tool lets you generate and download a file with what you choose: to keep it, or to bring it to another site.', 'events-certificates' ); ?></p>
			</header>
			<section>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'What should be exported?', 'events-certificates' ); ?></th>
							<td class="evcert-sections"><?php render_sections( ticked() ); ?></td>
						</tr>
						<?php
						$with = Restrictions\allows( 'zip' ) ? Bundle\template_assets() : array(
							'families' => array(),
							'images'   => 0,
						); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment 
						?>
						<?php if ( $with['families'] || $with['images'] ) : ?>
							<tr class="evcert-export-assets">
								<th scope="row"><?php esc_html_e( 'Fonts and pictures', 'events-certificates' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="assets" value="1" <?php checked( class_exists( '\ZipArchive' ) ); ?> <?php disabled( ! class_exists( '\ZipArchive' ) ); ?>>
										<?php esc_html_e( 'Include the fonts and pictures the templates use', 'events-certificates' ); ?>
									</label>
									<p class="description">
										<?php
										$parts = array();
										if ( $with['families'] ) {
											/* translators: %s: font names, e.g. "Inter, Lora". */
											$parts[] = sprintf( __( 'Fonts: %s', 'events-certificates' ), implode( ', ', array_map( 'esc_html', $with['families'] ) ) );
										}
										if ( $with['images'] ) {
											/* translators: %d: how many pictures. */
											$parts[] = sprintf( _n( '%d picture of the Media Library', '%d pictures of the Media Library', $with['images'], 'events-certificates' ), $with['images'] );
										}
										echo wp_kses( implode( ' · ', $parts ), array() );
										?>
										<br>
										<?php
										echo esc_html(
											class_exists( '\ZipArchive' )
												? __( 'The file is then a ZIP with the JSON and the folders fonts and images; the Import installs them. Check that the licenses of the fonts let you share them. Applies when Templates is ticked.', 'events-certificates' )
												: __( 'This server cannot make ZIP files (PHP ZipArchive is missing), so the fonts and pictures cannot go in the file.', 'events-certificates' )
										);
										?>
									</p>
								</td>
							</tr>
						<?php endif; ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'In which format?', 'events-certificates' ); ?></th>
							<td>
								<label><input type="radio" name="format" value="json" checked> <?php esc_html_e( 'JSON: everything you tick, to move or keep', 'events-certificates' ); ?></label><br>
								<label><input type="radio" name="format" value="csv"> <?php esc_html_e( 'CSV: one table for a spreadsheet (the first of Events, Participants or Certificates you tick)', 'events-certificates' ); ?></label>
							</td>
						</tr>
						<?php if ( $events ) : ?>
							<tr>
								<th scope="row"><label for="evcert-export-event"><?php esc_html_e( 'Which event should be exported?', 'events-certificates' ); ?></label></th>
								<td>
									<select name="event" id="evcert-export-event">
										<option value="0"><?php esc_html_e( 'Export all events', 'events-certificates' ); ?></option>
										<?php foreach ( $events as $item ) : ?>
											<option value="<?php echo esc_attr( (string) $item->ID ); ?>" <?php selected( $event, (int) $item->ID ); ?>><?php echo esc_html( $item->post_title ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Only for the participants and certificates; the templates and the settings are the site’s.', 'events-certificates' ); ?></p>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</section>
			<footer>
				<p class="description"><?php esc_html_e( 'With the settings the file carries the secret key that checks document numbers: keep the file private.', 'events-certificates' ); ?></p>
				<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Generate file', 'events-certificates' ); ?></button>
			</footer>
		</form>
	</div>
	<?php
}

/**
 * Sends the bundle of the sections asked for, as a file.
 */
function export() {
	check_admin_referer( 'evcert_export' );
	must_be_allowed( 'export' );
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- checked above.
	$sections = isset( $_REQUEST['sections'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_REQUEST['sections'] ) ) : array();
	$event    = isset( $_REQUEST['event'] ) ? absint( $_REQUEST['event'] ) : 0;
	// phpcs:enable
	if ( ! Bundle\sections( $sections ) ) {
		wp_safe_redirect( url( EXPORT ) );
		exit;
	}
	wp_raise_memory_limit( 'admin' );
	$table = array_values( array_intersect( array( 'events', 'people', 'certificates' ), $sections ) );
	if ( isset( $_REQUEST['format'] ) && 'csv' === sanitize_key( wp_unslash( $_REQUEST['format'] ) ) && $table ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked above.
		$csv = Csv\render( Csv\export_rows( $table[0], $event ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="events-certificates-' . $table[0] . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'Content-Length: ' . strlen( $csv ) );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a CSV file for download.
		exit;
	}
	$with_assets = ! empty( $_REQUEST['assets'] ) && Restrictions\allows( 'zip' ) && class_exists( '\ZipArchive' ) && in_array( 'templates', Bundle\sections( $sections ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked above.
	$files       = array();
	$bundle      = $with_assets ? Bundle\export( $sections, $event, $files ) : Bundle\export( $sections, $event );
	if ( $files ) {
		$zip = Bundle\make_zip( $bundle, $files );
		if ( ! is_wp_error( $zip ) ) {
			nocache_headers();
			header( 'Content-Type: application/zip' );
			header( 'Content-Disposition: attachment; filename="events-certificates-' . gmdate( 'Y-m-d' ) . '.zip"' );
			header( 'Content-Length: ' . filesize( $zip ) );
			readfile( $zip ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- a ZIP file for download.
			wp_delete_file( $zip );
			exit;
		}
	}
	$json = (string) wp_json_encode( \EventsCertificate\Bundle\for_file( $bundle ) );
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="events-certificates-' . gmdate( 'Y-m-d' ) . '.json"' );
	header( 'Content-Length: ' . strlen( $json ) );
	echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a JSON file for download.
	exit;
}

/* ---------- Import ---------- */

/**
 * The steps of the import, in order.
 *
 * @return array<string, string>
 */
function steps() {
	return array(
		'upload' => __( 'Upload file', 'events-certificates' ),
		'review' => __( 'Review', 'events-certificates' ),
		'import' => __( 'Import', 'events-certificates' ),
		'done'   => __( 'Done!', 'events-certificates' ),
	);
}

/**
 * The Import screen: the step the address says.
 */
function render_import() {
	must_be_allowed( 'import' );
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- each step checks its own nonce.
	$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'upload';
	$key  = isset( $_GET['key'] ) ? sanitize_key( wp_unslash( $_GET['key'] ) ) : '';
	// phpcs:enable
	$step = isset( steps()[ $step ] ) ? $step : 'upload';
	if ( 'upload' !== $step && 'done' !== $step && ! owns( $key ) ) {
		$step = 'upload';
	}
	?>
	<div class="wrap evcert-transfer">
		<h1 class="screen-reader-text"><?php esc_html_e( 'Import', 'events-certificates' ); ?></h1>
		<ol class="evcert-steps">
			<?php
			$reached = false;
			foreach ( steps() as $id => $name ) {
				printf( '<li class="%1$s">%2$s</li>', esc_attr( $id === $step ? 'active' : ( $reached ? '' : 'done' ) ), esc_html( $name ) );
				$reached = $reached || $id === $step;
			}
			?>
		</ol>
		<?php
		notice();
		if ( 'review' === $step ) {
			render_review( $key );
		} elseif ( 'import' === $step ) {
			render_progress( $key );
		} elseif ( 'done' === $step ) {
			render_done( $key );
		} else {
			render_upload();
		}
		?>
	</div>
	<?php
}

/**
 * The error the last step left, if any.
 */
function notice() {
	$error = get_transient( 'evcert_import_error_' . get_current_user_id() );
	if ( $error ) {
		delete_transient( 'evcert_import_error_' . get_current_user_id() );
		echo '<div class="notice notice-error inline"><p>' . esc_html( (string) $error ) . '</p></div>';
	}
}

/**
 * Step 1: the file, and whether what is already here is updated.
 */
function render_upload() {
	?>
	<form class="evcert-card" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="evcert_import_upload">
		<?php wp_nonce_field( 'evcert_import_upload' ); ?>
		<header>
			<h2><?php esc_html_e( 'Import from a file', 'events-certificates' ); ?></h2>
			<p><?php esc_html_e( 'This tool lets you import (or merge) templates, events, participants, certificates and settings from a file made by Export (a JSON, or a ZIP with the fonts and pictures of the templates).', 'events-certificates' ); ?></p>
		</header>
		<section>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><label for="evcert-bundle"><?php esc_html_e( 'Choose a file from your computer:', 'events-certificates' ); ?></label></th>
						<td>
							<input type="file" id="evcert-bundle" name="evcert_bundle" accept="<?php echo esc_attr( Restrictions\allows( 'zip' ) ? '.json,application/json,.zip,application/zip,.csv,text/csv' : '.json,application/json,.csv,text/csv' ); ?>" required>
							<br><small>
								<?php
								/* translators: %s: the largest file size, e.g. 20 MB. */
								echo esc_html( sprintf( __( 'Maximum size: %s', 'events-certificates' ), size_format( Bundle\max_bytes() ) ) );
								?>
							</small>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="evcert-csv-kind"><?php esc_html_e( 'If it is a CSV file', 'events-certificates' ); ?></label></th>
						<td>
							<select name="kind" id="evcert-csv-kind">
								<option value="people"><?php esc_html_e( 'It has participants', 'events-certificates' ); ?></option>
								<option value="events"><?php esc_html_e( 'It has events', 'events-certificates' ); ?></option>
							</select>
							<select name="event" aria-label="<?php esc_attr_e( 'The event of the participants', 'events-certificates' ); ?>">
								<option value="0"><?php esc_html_e( 'The event is in a column', 'events-certificates' ); ?></option>
								<?php foreach ( Events\all() as $item ) : ?>
									<option value="<?php echo esc_attr( (string) $item->ID ); ?>"><?php echo esc_html( $item->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'The first row is the column names; the next step maps them to fields. A JSON file made by Export needs none of this.', 'events-certificates' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="evcert-update-existing"><?php esc_html_e( 'Update existing', 'events-certificates' ); ?></label></th>
						<td>
							<input type="checkbox" id="evcert-update-existing" name="existing" value="update">
							<label for="evcert-update-existing"><?php esc_html_e( 'Templates, events and participants that are already here will be updated. A certificate is found by its code and never written over.', 'events-certificates' ); ?></label>
						</td>
					</tr>
				</tbody>
			</table>
		</section>
		<footer>
			<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Continue', 'events-certificates' ); ?></button>
		</footer>
	</form>
	<?php
}

/**
 * Step 2: what the file has, to choose what to import.
 *
 * @param string $key The import.
 */
function render_review( $key ) {
	$csv = stored( $key, 'csv' );
	if ( is_array( $csv ) ) {
		render_mapping( $key, $csv );
		return;
	}
	$bundle = stored( $key, 'bundle' );
	if ( ! is_array( $bundle ) ) {
		wp_safe_redirect( url( IMPORT ) );
		exit;
	}
	$counts = array();
	foreach ( (array) ( $bundle['sections'] ?? array() ) as $section ) {
		$counts[ $section ] = 'settings' === $section ? 1 : count( (array) ( $bundle[ $section ] ?? array() ) );
	}
	$meta = (array) get_transient( 'evcert_import_' . $key );
	?>
	<form class="evcert-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="evcert_import_start">
		<input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>">
		<?php wp_nonce_field( 'evcert_import_start_' . $key ); ?>
		<header>
			<h2><?php esc_html_e( 'What is in the file', 'events-certificates' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: file name, 2: the site it came from, 3: the date. */
						__( '%1$s, made by %2$s on %3$s.', 'events-certificates' ),
						(string) ( $meta['name'] ?? '' ),
						(string) ( $bundle['site'] ?? '' ),
						(string) ( $bundle['exported_at'] ?? '' )
					)
				);
				?>
			</p>
		</header>
		<section>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'What should be imported?', 'events-certificates' ); ?></th>
						<td class="evcert-sections"><?php render_sections( array_keys( $counts ), $counts ); ?></td>
					</tr>
					<?php
					$brought = array(
						'fonts'  => (array) ( $bundle['assets']['fonts'] ?? array() ),
						'images' => (array) ( $bundle['assets']['images'] ?? array() ),
					);
					$may     = current_user_can( 'edit_theme_options' ) && current_user_can( 'upload_files' );
					?>
					<?php if ( $brought['fonts'] || $brought['images'] ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Fonts and pictures', 'events-certificates' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="install_assets" value="1" <?php checked( $may ); ?> <?php disabled( ! $may ); ?>>
									<?php esc_html_e( 'Install the fonts and pictures of the templates', 'events-certificates' ); ?>
								</label>
								<p class="description">
									<?php
									$names = array_values( array_unique( array_map( static fn ( $font ) => (string) ( '' !== $font['label'] ? $font['label'] : $font['family'] ), $brought['fonts'] ) ) );
									/* translators: 1: font names, 2: how many pictures. */
									echo esc_html( sprintf( _n( 'In the file: fonts %1$s; %2$d picture.', 'In the file: fonts %1$s; %2$d pictures.', count( $brought['images'] ), 'events-certificates' ), $names ? implode( ', ', $names ) : '–', count( $brought['images'] ) ) );
									?>
									<br>
									<?php echo esc_html( $may ? __( 'Fonts go to the Font Library and pictures to the Media Library; one that is already there is left as it is.', 'events-certificates' ) : __( 'Installing fonts and pictures needs the permission to edit the theme and to upload files, which you do not have.', 'events-certificates' ) ); ?>
								</p>
							</td>
						</tr>
					<?php endif; ?>
					<?php if ( ! empty( $counts['settings'] ) ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Secret key', 'events-certificates' ); ?></th>
							<td><p class="description"><?php esc_html_e( 'The settings carry the secret key that checks document numbers. It is taken only if no certificate here is checked with a key of its own.', 'events-certificates' ); ?></p></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</section>
		<footer>
			<a class="button button-large" href="<?php echo esc_url( url( IMPORT ) ); ?>"><?php esc_html_e( 'Choose another file', 'events-certificates' ); ?></a>
			<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Run the importer', 'events-certificates' ); ?></button>
		</footer>
	</form>
	<?php
}

/**
 * Step 2 for a CSV: which field each column fills, as WooCommerce's column mapping.
 *
 * @param string               $key The import.
 * @param array<string, mixed> $csv The file read.
 */
function render_mapping( $key, array $csv ) {
	$fields = Csv\fields( (string) $csv['kind'] );
	$meta   = (array) get_transient( 'evcert_import_' . $key );
	?>
	<form class="evcert-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="evcert_import_start">
		<input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>">
		<?php wp_nonce_field( 'evcert_import_start_' . $key ); ?>
		<header>
			<h2><?php esc_html_e( 'Map CSV fields to fields', 'events-certificates' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: file name, 2: how many rows. */
						__( '%1$s has %2$s rows. Choose the field each column fills; the ones you leave out are not imported.', 'events-certificates' ),
						(string) ( $meta['name'] ?? '' ),
						number_format_i18n( count( (array) $csv['rows'] ) )
					)
				);
				?>
			</p>
		</header>
		<section>
			<table class="widefat striped evcert-mapping">
				<thead>
					<tr><th><?php esc_html_e( 'Column name', 'events-certificates' ); ?></th><th><?php esc_html_e( 'Example', 'events-certificates' ); ?></th><th><?php esc_html_e( 'Map to field', 'events-certificates' ); ?></th></tr>
				</thead>
				<tbody>
					<?php foreach ( (array) $csv['headers'] as $column => $header ) : ?>
						<?php $guess = Csv\guess( (string) $header, (string) $csv['kind'] ); ?>
						<tr>
							<td><?php echo esc_html( (string) $header ); ?></td>
							<td><?php echo esc_html( (string) ( $csv['rows'][0][ $column ] ?? '' ) ); ?></td>
							<td>
								<select name="mapping[<?php echo esc_attr( (string) $column ); ?>]" aria-label="<?php echo esc_attr( (string) $header ); ?>">
									<option value=""><?php esc_html_e( 'Do not import', 'events-certificates' ); ?></option>
									<?php foreach ( $fields as $field => $info ) : ?>
										<option value="<?php echo esc_attr( $field ); ?>" <?php selected( $guess, $field ); ?>><?php echo esc_html( $info[0] . ( $info[2] ? ' *' : '' ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( '* required', 'events-certificates' ); ?></p>
		</section>
		<footer>
			<a class="button button-large" href="<?php echo esc_url( url( IMPORT ) ); ?>"><?php esc_html_e( 'Choose another file', 'events-certificates' ); ?></a>
			<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Run the importer', 'events-certificates' ); ?></button>
		</footer>
	</form>
	<?php
}

/**
 * Step 3: the progress bar; the script asks for one batch after the other.
 *
 * @param string $key The import.
 */
function render_progress( $key ) {
	if ( ! is_array( stored( $key, 'state' ) ) ) {
		wp_safe_redirect( url( IMPORT ) );
		exit;
	}
	$script = \EventsCertificate\Assets\src( 'import', 'js' );
	wp_enqueue_script( 'evcert-import', $script['url'], array(), $script['version'], true );
	wp_add_inline_script(
		'evcert-import',
		'window.evcertImport = ' . wp_json_encode(
			array(
				'ajax'   => admin_url( 'admin-ajax.php' ),
				'key'    => $key,
				'nonce'  => wp_create_nonce( 'evcert_import_step_' . $key ),
				'next'   => step_url( 'done', $key ),
				'failed' => __( 'The import stopped. What was done stays; reload this page to go on.', 'events-certificates' ),
			)
		) . ';',
		'before'
	);
	?>
	<div class="evcert-card">
		<header>
			<span class="spinner is-active"></span>
			<h2><?php esc_html_e( 'Importing', 'events-certificates' ); ?></h2>
			<p><?php esc_html_e( 'The file is being imported…', 'events-certificates' ); ?></p>
		</header>
		<section>
			<progress class="evcert-progress" id="evcert-import-progress" max="100" value="0"></progress>
			<p class="description" id="evcert-import-status" role="status"></p>
		</section>
	</div>
	<?php
}

/**
 * Step 4: what was done.
 *
 * @param string $key The import.
 */
function render_done( $key ) {
	$result = get_transient( 'evcert_import_done_' . get_current_user_id() );
	if ( ! is_array( $result ) || ( $result['key'] ?? '' ) !== $key ) {
		wp_safe_redirect( url( IMPORT ) );
		exit;
	}
	$summary = (array) $result['summary'];
	$labels  = labels();
	$totals  = array(
		'added'   => 0,
		'updated' => 0,
		'skipped' => 0,
	);
	foreach ( (array) $result['sections'] as $section ) {
		foreach ( $totals as $kind => $n ) {
			$totals[ $kind ] = $n + (int) ( $summary[ $section ][ $kind ] ?? 0 );
		}
	}
	$conflicts = (array) ( $summary['conflicts'] ?? array() );
	?>
	<div class="evcert-card">
		<section class="evcert-done">
			<p>
				<strong><?php esc_html_e( 'Import complete!', 'events-certificates' ); ?></strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: how many were added, 2: updated, 3: left as they were. */
						__( '%1$d added, %2$d updated, %3$d left as they were.', 'events-certificates' ),
						$totals['added'],
						$totals['updated'],
						$totals['skipped']
					)
				);
				?>
			</p>
			<ul>
				<?php foreach ( (array) $result['sections'] as $section ) : ?>
					<li>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: a section (Events), 2: how many were added, 3: updated, 4: left as they were. */
								__( '%1$s: %2$d added, %3$d updated, %4$d left as they were.', 'events-certificates' ),
								$labels[ $section ][0] ?? $section,
								(int) ( $summary[ $section ]['added'] ?? 0 ),
								(int) ( $summary[ $section ]['updated'] ?? 0 ),
								(int) ( $summary[ $section ]['skipped'] ?? 0 )
							)
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php
			$assets = (array) ( $summary['assets'] ?? array() );
			$parts  = array();
			if ( ! empty( $assets['fonts'] ) ) {
				/* translators: %d: how many fonts. */
				$parts[] = sprintf( _n( '%d font installed', '%d fonts installed', (int) $assets['fonts'], 'events-certificates' ), (int) $assets['fonts'] );
			}
			if ( ! empty( $assets['images'] ) ) {
				/* translators: %d: how many pictures. */
				$parts[] = sprintf( _n( '%d picture added', '%d pictures added', (int) $assets['images'], 'events-certificates' ), (int) $assets['images'] );
			}
			if ( ! empty( $assets['skipped'] ) ) {
				/* translators: %d: how many were already here. */
				$parts[] = sprintf( __( '%d already there', 'events-certificates' ), (int) $assets['skipped'] );
			}
			if ( ! empty( $assets['denied'] ) ) {
				/* translators: %d: how many were not installed. */
				$parts[] = sprintf( __( '%d not installed for lack of permission', 'events-certificates' ), (int) $assets['denied'] );
			}
			if ( ! empty( $assets['failed'] ) ) {
				/* translators: %d: how many failed. */
				$parts[] = sprintf( __( '%d failed', 'events-certificates' ), (int) $assets['failed'] );
			}
			if ( $parts ) :
				?>
				<p>
					<?php
					/* translators: %s: what happened to the fonts and pictures, e.g. "6 fonts installed, 1 picture added". */
					echo esc_html( sprintf( __( 'Fonts and pictures: %s.', 'events-certificates' ), implode( ', ', $parts ) ) );
					?>
				</p>
			<?php endif; ?>
			<?php if ( $conflicts ) : ?>
				<details>
					<summary><?php esc_html_e( 'View import log', 'events-certificates' ); ?></summary>
					<ul>
						<?php foreach ( $conflicts as $message ) : ?>
							<li><?php echo esc_html( (string) $message ); ?></li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
		</section>
		<footer>
			<a class="button button-large" href="<?php echo esc_url( url( IMPORT ) ); ?>"><?php esc_html_e( 'Import another file', 'events-certificates' ); ?></a>
			<a class="button button-primary button-large" href="<?php echo esc_url( Admin\page_url() ); ?>"><?php esc_html_e( 'View certificates', 'events-certificates' ); ?></a>
		</footer>
	</div>
	<?php
}

/* ---------- The steps' handlers ---------- */

/**
 * The address of a step that needs an import, with the nonce its page checks.
 *
 * @param string $step The step.
 * @param string $key  The import.
 * @return string
 */
function step_url( $step, $key ) {
	// Not wp_nonce_url(): it HTML-escapes the address, which is wrong for a redirect and for the script.
	return add_query_arg(
		'_wpnonce',
		wp_create_nonce( 'evcert_import_view_' . $key ),
		url(
			IMPORT,
			array(
				'step' => $step,
				'key'  => $key,
			)
		)
	);
}

/**
 * Step 1's handler: keeps the file where the web cannot reach it and goes to the review.
 */
function upload() {
	check_admin_referer( 'evcert_import_upload' );
	must_be_allowed( 'import' );
	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked above; a temporary path from PHP, checked with is_uploaded_file().
	$name  = isset( $_FILES['evcert_bundle']['name'] ) ? sanitize_file_name( wp_unslash( (string) $_FILES['evcert_bundle']['name'] ) ) : '';
	$tmp   = isset( $_FILES['evcert_bundle']['tmp_name'] ) ? (string) $_FILES['evcert_bundle']['tmp_name'] : '';
	$error = isset( $_FILES['evcert_bundle']['error'] ) ? (int) $_FILES['evcert_bundle']['error'] : UPLOAD_ERR_NO_FILE;
	$keep  = isset( $_POST['existing'] ) && 'update' === sanitize_key( wp_unslash( $_POST['existing'] ) ) ? 'update' : 'skip';
	// phpcs:enable
	$type  = wp_check_filetype(
		$name,
		array(
			'json' => 'application/json',
			'zip'  => 'application/zip',
			'csv'  => 'text/csv',
		)
	);
	$kinds = Restrictions\allows( 'zip' ) ? array( 'json', 'zip', 'csv' ) : array( 'json', 'csv' );
	if ( UPLOAD_ERR_OK !== $error || ! is_uploaded_file( $tmp ) || ! in_array( $type['ext'], $kinds, true ) ) {
		fail( __( 'Send the .json or .zip file made by Export, or a .csv file.', 'events-certificates' ) );
	}
	forget_old();
	$key = strtolower( wp_generate_password( 24, false ) );
	if ( 'csv' === $type['ext'] ) {
		$csv = Csv\read( $tmp );
		if ( is_wp_error( $csv ) ) {
			fail( $csv->get_error_message() );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
		$kind = isset( $_POST['kind'] ) && 'events' === sanitize_key( wp_unslash( $_POST['kind'] ) ) ? 'events' : 'people';
		store( $key, 'csv', $csv + array( 'kind' => $kind, 'event' => isset( $_POST['event'] ) ? absint( $_POST['event'] ) : 0 ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		// phpcs:enable
	} else {
		$bundle = Bundle\read( $tmp, Bundle\import_dir( $key ) );
		if ( is_wp_error( $bundle ) ) {
			Bundle\remove_dir( Bundle\import_dir( $key ) );
			fail( $bundle->get_error_message() );
		}
		store( $key, 'bundle', $bundle );
	}
	set_transient(
		'evcert_import_' . $key,
		array(
			'user'     => get_current_user_id(),
			'name'     => $name,
			'existing' => $keep,
		),
		DAY_IN_SECONDS
	);
	wp_safe_redirect( step_url( 'review', $key ) );
	exit;
}

/**
 * Step 2's handler: what to import is chosen; the import begins.
 */
function start() {
	$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked on the next line.
	check_admin_referer( 'evcert_import_start_' . $key );
	must_be_allowed( 'import' );
	$meta = get_transient( 'evcert_import_' . $key );
	$mine = is_array( $meta ) && get_current_user_id() === (int) $meta['user'];
	$csv  = $mine ? stored( $key, 'csv' ) : null;
	if ( is_array( $csv ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked above; each value is cleaned on the next line.
		$mapping = isset( $_POST['mapping'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['mapping'] ) ) : array();
		$needed  = array_keys(
			array_filter(
				Csv\fields( (string) $csv['kind'] ),
				static function ( $info ) {
					return $info[2];
				}
			)
		);
		if ( array_diff( $needed, $mapping ) ) {
			set_transient( 'evcert_import_error_' . get_current_user_id(), __( 'Map the required fields (marked with *): a column for each.', 'events-certificates' ), MINUTE_IN_SECONDS );
			wp_safe_redirect( step_url( 'review', $key ) );
			exit;
		}
		$bundle = Csv\to_bundle( (string) $csv['kind'], $csv, $mapping, (int) ( $csv['event'] ?? 0 ) );
		store( $key, 'bundle', $bundle );
		store( $key, 'state', Bundle\begin( $bundle, (array) $bundle['sections'], (string) ( $meta['existing'] ?? 'skip' ) ) );
		wp_safe_redirect( step_url( 'import', $key ) );
		exit;
	}
	$bundle = $mine ? stored( $key, 'bundle' ) : null;
	if ( ! is_array( $bundle ) ) {
		fail( __( 'That import is no longer there: send the file again.', 'events-certificates' ) );
	}
	$sections = isset( $_POST['sections'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sections'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
	if ( ! Bundle\sections( $sections ) ) {
		set_transient( 'evcert_import_error_' . get_current_user_id(), __( 'Choose what to import.', 'events-certificates' ), MINUTE_IN_SECONDS );
		wp_safe_redirect( step_url( 'review', $key ) );
		exit;
	}
	$assets = ! empty( $_POST['install_assets'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
	store( $key, 'state', Bundle\begin( $bundle, $sections, (string) ( $meta['existing'] ?? 'skip' ), $assets ) );
	wp_safe_redirect( step_url( 'import', $key ) );
	exit;
}

/**
 * One request of the import: as many batches as fit in a couple of seconds, then the progress.
 */
function ajax_step() {
	$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked on the next line.
	check_ajax_referer( 'evcert_import_step_' . $key, 'nonce' );
	$meta = get_transient( 'evcert_import_' . $key );
	if ( ! current_user_can( Admin\CAPABILITY ) || ! Restrictions\allows( 'import' ) || ! is_array( $meta ) || get_current_user_id() !== (int) $meta['user'] ) {
		wp_send_json_error( null, 403 );
	}
	$bundle = stored( $key, 'bundle' );
	$state  = stored( $key, 'state' );
	if ( ! is_array( $bundle ) || ! is_array( $state ) ) {
		wp_send_json_error( null, 410 );
	}
	wp_raise_memory_limit( 'admin' );
	$began = microtime( true );
	do {
		$finished = Bundle\step( $state, $bundle );
	} while ( ! $finished && microtime( true ) - $began < 2.0 );
	if ( $finished ) {
		set_transient(
			'evcert_import_done_' . get_current_user_id(),
			array(
				'key'      => $key,
				'summary'  => $state['summary'],
				'sections' => $state['sections'],
			),
			HOUR_IN_SECONDS
		);
		discard( $key );
	} else {
		store( $key, 'state', $state );
	}
	wp_send_json_success(
		array(
			'done'    => $finished,
			'percent' => $finished ? 100 : Bundle\progress( $state ),
		)
	);
}

/**
 * Goes back to the first step with a message.
 *
 * @param string $message What went wrong.
 */
function fail( $message ) {
	set_transient( 'evcert_import_error_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );
	wp_safe_redirect( url( IMPORT ) );
	exit;
}

/* ---------- Where an import waits ---------- */

/**
 * Whether a page of the wizard may open an import: it is the current user's and the address carries its nonce.
 *
 * @param string $key The import.
 * @return bool
 */
function owns( $key ) {
	$meta = 24 === strlen( $key ) ? get_transient( 'evcert_import_' . $key ) : false;
	if ( ! is_array( $meta ) || get_current_user_id() !== (int) $meta['user'] ) {
		return false;
	}
	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is the check.

	return (bool) wp_verify_nonce( $nonce, 'evcert_import_view_' . $key );
}

/**
 * The file of a part of an import. It is a PHP file that stops at once, so the web shows nothing of it even if the temporary
 * folder is under the site (a bundle can hold the secret key).
 *
 * @param string $key  The import.
 * @param string $part bundle|state.
 * @return string
 */
function path( $key, $part ) {
	return trailingslashit( get_temp_dir() ) . 'evcert-import-' . $key . '-' . $part . '.json.php';
}

/**
 * Keeps a part of an import.
 *
 * @param string               $key  The import.
 * @param string               $part bundle|state.
 * @param array<string, mixed> $data What to keep.
 */
function store( $key, $part, array $data ) {
	$file = path( $key, $part );
	file_put_contents( $file, "<?php exit; ?>\n" . wp_json_encode( $data ), LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a private temporary file.
	chmod( $file, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.chmod_chmod, WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- the file is the server's alone.
}

/**
 * A part of an import.
 *
 * @param string $key  The import.
 * @param string $part bundle|state.
 * @return array<string, mixed>|null
 */
function stored( $key, $part ) {
	$file = path( $key, $part );
	if ( 24 !== strlen( $key ) || ! is_readable( $file ) ) {
		return null;
	}
	$raw  = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a private temporary file.
	$data = json_decode( substr( $raw, (int) strpos( $raw, "\n" ) + 1 ), true );

	return is_array( $data ) ? $data : null;
}

/**
 * Throws an import's files away.
 *
 * @param string $key The import.
 */
function discard( $key ) {
	foreach ( array( 'bundle', 'csv', 'state' ) as $part ) {
		wp_delete_file( path( $key, $part ) );
	}
	Bundle\remove_dir( Bundle\import_dir( $key ) );
	delete_transient( 'evcert_import_' . $key );
}

/**
 * Throws away what imports left more than a day ago.
 */
function forget_old() {
	foreach ( (array) glob( trailingslashit( get_temp_dir() ) . 'evcert-import-*.json.php' ) as $file ) {
		if ( is_file( $file ) && filemtime( $file ) < time() - DAY_IN_SECONDS ) {
			wp_delete_file( $file );
		}
	}
	foreach ( (array) glob( trailingslashit( get_temp_dir() ) . 'evcert-import-*-assets', GLOB_ONLYDIR ) as $dir ) {
		if ( filemtime( $dir ) < time() - DAY_IN_SECONDS ) {
			Bundle\remove_dir( $dir );
		}
	}
}
