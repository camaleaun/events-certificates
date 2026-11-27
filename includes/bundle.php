<?php
/**
 * Export and import: one versioned JSON bundle (`events-certificates/bundle/1`) with the templates, the events, their
 * participants, the certificates and the settings, to move them to another site or keep them.
 *
 * What is exported is what the plugin keeps in its own tables and posts (the core's Tools > Export cannot carry the tables).
 * Ids do not travel: the bundle has its own keys and the import maps them to the new ids. Certificates keep their code, which
 * is their identity: importing twice changes nothing, and a code already there with other content is reported, never written
 * over. Everything is cleaned again on the way in; the file is never trusted (decision 0035).
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Bundle;

use EventsCertificate\Admin;
use EventsCertificate\Certificates;
use EventsCertificate\Code;
use EventsCertificate\Days;
use EventsCertificate\Designs;
use EventsCertificate\Documents;
use EventsCertificate\Events;
use EventsCertificate\Fonts;
use EventsCertificate\Restrictions;
use EventsCertificate\Install;
use EventsCertificate\People;
use EventsCertificate\Pictures;
use EventsCertificate\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The format of the file.
 */
const SCHEMA = 'events-certificates/bundle/1';

/**
 * The sections of a bundle. People and certificates need the events (their rows say which event they belong to), and the
 * certificates need the people they were issued to.
 */
const SECTIONS = array( 'templates', 'events', 'people', 'certificates', 'settings' );

/**
 * What may travel with the templates in a ZIP (fonts and pictures of the Media Library): how many files, how big each is and how big all
 * of them are once unpacked (the limits that keep a ZIP from filling the disk).
 */
const ASSETS_MAX_FILES = 200;
const ASSET_FONT_MAX   = 10 * MB_IN_BYTES;
const ASSET_IMAGE_MAX  = 5 * MB_IN_BYTES;
const ASSETS_MAX_TOTAL = 100 * MB_IN_BYTES;

/**
 * The most a picture may measure (a side, and all its pixels), and the most a file may grow when unpacked (a ZIP bomb is a ratio far above that).
 */
const IMAGE_MAX_SIDE   = 8000;
const IMAGE_MAX_PIXELS = 40000000;
const ZIP_MAX_RATIO    = 100;

/**
 * The name of the JSON in a ZIP.
 */
const ZIP_JSON = 'events-certificates.json';

/**
 * How deep the JSON of a bundle may be nested (a bundle is a few levels deep; the depth is what a bomb of nested arrays would use).
 */
const JSON_DEPTH = 40;

/**
 * The certificate statuses that travel.
 */
const STATUSES = array( 'issued', 'replaced', 'revoked', 'trash' );

/**
 * The certificate columns that travel (the rest is made again on the way in: id, fingerprint, e-mail state, dates of the row).
 */
const CERT_COLUMNS = array( 'context', 'group_id', 'code', 'status', 'name', 'email', 'wporg_username', 'role', 'details', 'attended', 'locale', 'event_title', 'event_start', 'event_end', 'event_tz', 'hours', 'doc_type', 'doc_label', 'doc_masked', 'doc_hash', 'self_service', 'renderer_version', 'source', 'source_ref', 'revoked_reason', 'trashed_from', 'trashed_at', 'issued_at' );

/**
 * The sections asked for, with the ones they need, in the order they are done.
 *
 * @param mixed $asked Section names.
 * @return string[]
 */
function sections( $asked ) {
	$asked = array_intersect( SECTIONS, array_map( 'sanitize_key', (array) $asked ) );
	if ( in_array( 'certificates', $asked, true ) ) {
		$asked[] = 'people';
	}
	if ( in_array( 'people', $asked, true ) || in_array( 'certificates', $asked, true ) ) {
		$asked[] = 'events';
	}

	return array_values( array_intersect( SECTIONS, $asked ) );
}

/**
 * Builds the bundle.
 *
 * @param string[]                   $asked    Sections (sections() adds the ones they need).
 * @param int                        $event_id Only this event (and its people and certificates); 0 for all.
 * @param array<string, string>|null $files    Pass an array to take along the fonts and pictures the templates use: it is filled with
 *                                             the name each has in the ZIP => its path, and the bundle lists them in `assets`.
 * @return array<string, mixed>
 */
function export( array $asked, $event_id = 0, ?array &$files = null ) {
	global $wpdb;

	$sections = sections( $asked );
	$bundle   = array(
		'$schema'     => SCHEMA,
		'plugin'      => \EventsCertificate\VERSION,
		'exported_at' => gmdate( 'c' ),
		'site'        => home_url(),
		'sections'    => $sections,
	);

	if ( in_array( 'templates', $sections, true ) ) {
		$default = (int) Designs\site_design_id();
		$quick   = (int) Designs\quick_design_id();
		foreach ( Designs\templates() as $post ) {
			$bundle['templates'][] = array(
				'key'     => (int) $post->ID,
				'title'   => html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ),
				'design'  => restrict_design( Designs\get( (int) $post->ID ) ),
				'roles'   => Designs\roles( (int) $post->ID ),
				'order'   => (int) $post->menu_order,
				'default' => (int) $post->ID === $default,
				'quick'   => (int) $post->ID === $quick,
			);
		}
	}

	if ( null !== $files && Restrictions\allows( 'zip' ) && ! empty( $bundle['templates'] ) ) {
		$found = assets_of( $bundle['templates'] );
		if ( $found['fonts'] || $found['images'] ) {
			$bundle['assets'] = package_assets( $found, $files );
		}
	}

	// The pictures of events, certificates and the signature: each is a file in the ZIP and a `picture:<sha256>` in the JSON (a data
	// URI is never written). Without a ZIP to carry them (`$files` is null) the places stay empty.
	$pictures  = array();
	$event_ids = array();
	if ( in_array( 'events', $sections, true ) ) {
		foreach ( Events\all() as $post ) {
			if ( $event_id && (int) $post->ID !== (int) $event_id ) {
				continue;
			}
			$event_ids[]        = (int) $post->ID;
			$bundle['events'][] = array( 'key' => (int) $post->ID ) + restrict_event(
				null === $files ? array_merge(
					Events\get( (int) $post->ID ),
					array(
						'logo'            => '',
						'signature_image' => '',
					)
				) : refer_pictures(
					Events\get( (int) $post->ID ),
					$pictures
				)
			);
		}
	}

	$codes = array();
	if ( in_array( 'people', $sections, true ) ) {
		foreach ( $event_ids as $id ) {
			foreach ( array( false, true ) as $trash ) {
				foreach ( People\query( $id, array( 'trash' => $trash ) )['rows'] as $person ) {
					$roles = array();
					foreach ( (array) $person['roles'] as $role => $cert_id ) {
						$cert           = $cert_id ? Certificates\get( (int) $cert_id ) : null;
						$roles[ $role ] = $cert && 'trash' !== $cert['status'] ? (string) $cert['code'] : '';
					}
					$bundle['people'][] = array(
						'key'            => (int) $person['id'],
						'event'          => $id,
						'name'           => $person['name'],
						'email'          => $person['email'],
						'wporg_username' => $person['wporg_username'],
						'status'         => $person['status'],
						'source'         => $person['source'],
						'source_ref'     => $person['source_ref'],
						'created_at'     => $person['created_at'],
						'deleted_at'     => $person['deleted_at'],
						'roles'          => $roles,
					);
				}
			}
		}
	}

	if ( in_array( 'certificates', $sections, true ) ) {
		$table = Certificates\table();
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE status IN ('issued','replaced','revoked','trash') ORDER BY id ASC", $table ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		foreach ( $rows as $row ) {
			$codes[ (int) $row['id'] ] = (string) $row['code'];
		}
		$snapshots = array();
		foreach ( $rows as $row ) {
			// One event asked for: only its certificates. Else all of them, the ones of an event that is no longer there included
			// (a certificate says what it needs by itself).
			if ( $event_id && ( 'event' !== $row['context'] || (int) $row['event_id'] !== (int) $event_id ) ) {
				continue;
			}
			$item = array();
			foreach ( CERT_COLUMNS as $column ) {
				$item[ $column ] = $row[ $column ];
			}
			$item['event']       = 'event' === $row['context'] && in_array( (int) $row['event_id'], $event_ids, true ) ? (int) $row['event_id'] : 0;
			$item['source_ref']  = (string) $row['source_ref'];
			$item['replaced_by'] = $row['replaced_by'] ? ( $codes[ (int) $row['replaced_by'] ] ?? '' ) : '';
			$item['data']        = restrict_event(
				null === $files ? array_merge(
					(array) json_decode( (string) $row['data_json'], true ),
					array(
						'logo'            => '',
						'signature_image' => '',
					)
				) : refer_pictures(
					(array) json_decode(
						(string) $row['data_json'],
						true
					),
					$pictures
				)
			);
			$item['design_hash'] = (string) $row['design_hash'];
			if ( '' !== $item['design_hash'] && ! isset( $snapshots[ $item['design_hash'] ] ) ) {
				$json = $wpdb->get_var( $wpdb->prepare( 'SELECT design FROM %i WHERE hash = %s', Install\tables()['designs'], $item['design_hash'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
				if ( $json ) {
					$snapshots[ $item['design_hash'] ] = json_decode( (string) $json, true );
				}
			}
			$bundle['certificates'][] = $item;
		}
		$bundle['snapshots'] = $snapshots;
	}

	if ( in_array( 'settings', $sections, true ) ) {
		$settings = Settings\get();
		unset( $settings['slug'] ); // Where the check form lives is the other site's.
		$signature          = get_option( \EventsCertificate\Signer\SIGNATURE_OPTION );
		$bundle['settings'] = array(
			'values'          => $settings,
			'signature'       => is_array( $signature ) && Restrictions\allows( 'vectors' ) ? $signature : null,
			'signature_image' => null === $files ? '' : picture_ref( get_option( \EventsCertificate\Signer\SIGNATURE_ATTACHMENT_OPTION, 0 ), $pictures ),
			'days'            => Days\all(),
			// The pepper is what lets a document number typed later be checked against the hash kept on a certificate; without it
			// those checks stop matching on the other site. It is a secret: the file has to be kept as one.
			'pepper'          => (string) get_option( Documents\PEPPER_OPTION, '' ),
		);
	}

	if ( null !== $files && $pictures ) {
		$more = package_assets(
			array(
				'fonts'  => array(),
				'images' => array_map(
					static function ( $sha, $one ) {
						return array(
							'ref'   => 'picture:' . $sha,
							'url'   => '',
							'alt'   => $one['alt'],
							'title' => $one['title'],
							'path'  => $one['path'],
						);
					},
					array_keys( $pictures ),
					$pictures
				),
			),
			$files
		);

		$bundle['assets']           = (array) ( $bundle['assets'] ?? array(
			'fonts'  => array(),
			'images' => array(),
		) );
		$bundle['assets']['images'] = array_merge( (array) ( $bundle['assets']['images'] ?? array() ), $more['images'] );
	}

	return $bundle;
}

/**
 * The reference a bundle holds for a picture of the Media Library (`picture:<sha256>`), noting its file to go in the ZIP; '' when the
 * picture cannot travel (not allowed, no ZIP, not a readable file). The JSON never has the picture itself.
 *
 * @param mixed                                $id       The attachment ID kept by the plugin.
 * @param array<string, array<string, string>> $pictures sha256 => file, alt and title, filled.
 * @return string
 */
function picture_ref( $id, array &$pictures ) {
	$id = Pictures\clean( $id );
	if ( '' === $id || ! Restrictions\allows( 'pictures' ) ) {
		return '';
	}
	$path = (string) get_attached_file( (int) $id );
	$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	if ( '' === $path || ! is_readable( $path ) || ! in_array( $ext, array( 'png', 'jpg', 'jpeg', 'gif', 'webp' ), true ) ) {
		return '';
	}
	$sha = (string) hash_file( 'sha256', $path );
	if ( ! isset( $pictures[ $sha ] ) ) {
		$pictures[ $sha ] = array(
			'path'  => $path,
			'alt'   => (string) get_post_meta( (int) $id, '_wp_attachment_image_alt', true ),
			'title' => html_entity_decode( get_the_title( (int) $id ), ENT_QUOTES, 'UTF-8' ),
		);
	}

	return 'picture:' . $sha;
}

/**
 * The data of an event or a certificate with its pictures as references.
 *
 * @param array<string, mixed>                 $data     Event or certificate data.
 * @param array<string, array<string, string>> $pictures Filled (picture_ref()).
 * @return array<string, mixed>
 */
function refer_pictures( array $data, array &$pictures ) {
	foreach ( array( 'logo', 'signature_image' ) as $key ) {
		if ( isset( $data[ $key ] ) ) {
			$data[ $key ] = picture_ref( $data[ $key ], $pictures );
		}
	}

	return $data;
}

/**
 * A design without what this site does not let travel: the paths of the vector drawings (`vectors`) and the pictures (`pictures`).
 * The elements stay (their place, size and name): they are the empty places the person fills by hand.
 *
 * @param array<string, mixed> $design Design.
 * @return array<string, mixed>
 */
function restrict_design( array $design ) {
	$vectors  = Restrictions\allows( 'vectors' );
	$pictures = Restrictions\allows( 'pictures' );
	if ( $vectors && $pictures ) {
		return $design;
	}
	$fix = static function ( $elements ) use ( $vectors, $pictures ) {
		foreach ( (array) $elements as $i => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( ! $vectors && 'svg' === ( $element['type'] ?? '' ) ) {
				$elements[ $i ]['paths'] = array();
			}
			if ( ! $pictures && 'image' === ( $element['type'] ?? '' ) ) {
				$elements[ $i ]['src'] = '';
				unset( $elements[ $i ]['attachment'] );
			}
		}

		return $elements;
	};
	if ( isset( $design['elements'] ) ) {
		$design['elements'] = $fix( $design['elements'] );
	}
	if ( isset( $design['back']['elements'] ) ) {
		$design['back']['elements'] = $fix( $design['back']['elements'] );
	}

	return $design;
}

/**
 * An event's (or a certificate's frozen) data without what this site does not let travel: the drawings and the pictures.
 *
 * @param array<string, mixed> $data Event or certificate data.
 * @return array<string, mixed>
 */
function restrict_event( array $data ) {
	if ( ! Restrictions\allows( 'vectors' ) ) {
		foreach ( array( 'logo_svg', 'signature_svg' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$data[ $key ] = '';
			}
		}
	}
	if ( ! Restrictions\allows( 'pictures' ) ) {
		foreach ( array( 'logo', 'signature_image' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$data[ $key ] = '';
			}
		}
	}

	return $data;
}

/**
 * Whether a bundle has what a restricted site leaves out, to say so in the log.
 *
 * @param array<string, mixed> $bundle The file read.
 * @return string[] The features the file has data of that this site does not allow.
 */
function left_out( array $bundle ) {
	$out    = array();
	$assets = (array) ( $bundle['assets'] ?? array() );
	if ( ! Restrictions\allows( 'fonts' ) && ! empty( $assets['fonts'] ) ) {
		$out[] = 'fonts';
	}
	$vectors  = false;
	$pictures = false;
	$scan     = static function ( $data ) use ( &$vectors, &$pictures ) {
		foreach ( array( 'logo_svg', 'signature_svg' ) as $key ) {
			$vectors = $vectors || ! empty( $data[ $key ] );
		}
		foreach ( array( 'logo', 'signature_image' ) as $key ) {
			$pictures = $pictures || ! empty( $data[ $key ] );
		}
	};
	foreach ( (array) ( $bundle['events'] ?? array() ) as $event ) {
		$scan( (array) $event );
	}
	foreach ( (array) ( $bundle['certificates'] ?? array() ) as $cert ) {
		$scan( (array) ( $cert['data'] ?? array() ) );
	}
	foreach ( (array) ( $bundle['templates'] ?? array() ) as $template ) {
		foreach ( design_elements( (array) ( $template['design'] ?? array() ) ) as $element ) {
			$vectors  = $vectors || ( 'svg' === ( $element['type'] ?? '' ) && ! empty( $element['paths'] ) );
			$pictures = $pictures || ( 'image' === ( $element['type'] ?? '' ) && ! empty( $element['src'] ) );
		}
	}
	$settings = (array) ( $bundle['settings'] ?? array() );
	$vectors  = $vectors || ! empty( $settings['signature'] );
	$pictures = $pictures || ! empty( $settings['signature_image'] ) || ! empty( $assets['images'] );
	if ( $vectors && ! Restrictions\allows( 'vectors' ) ) {
		$out[] = 'vectors';
	}
	if ( $pictures && ! Restrictions\allows( 'pictures' ) ) {
		$out[] = 'pictures';
	}

	return $out;
}

/**
 * Every element of a design: the certificate's and the declaration page's.
 *
 * @param array<string, mixed> $design Design.
 * @return array<int, array<string, mixed>>
 */
function design_elements( array $design ) {
	$out = array();
	foreach ( array_merge( (array) ( $design['elements'] ?? array() ), (array) ( $design['back']['elements'] ?? array() ) ) as $element ) {
		if ( is_array( $element ) ) {
			$out[] = $element;
		}
	}

	return $out;
}

/**
 * The file of this site's uploads folder an address points to, when it is a readable picture the plugin accepts.
 *
 * @param string $url Address.
 * @return string Path, or ''.
 */
function upload_picture( $url ) {
	$uploads = wp_get_upload_dir();
	if ( 0 !== strpos( $url, $uploads['baseurl'] . '/' ) ) {
		return '';
	}
	$file = realpath( $uploads['basedir'] . '/' . rawurldecode( substr( $url, strlen( $uploads['baseurl'] ) + 1 ) ) );
	$base = realpath( $uploads['basedir'] );
	$ext  = $file ? strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) : '';

	return $file && $base && 0 === strpos( $file, $base . DIRECTORY_SEPARATOR ) && is_readable( $file ) && in_array( $ext, array( 'png', 'jpg', 'jpeg', 'gif', 'webp' ), true ) ? $file : '';
}

/**
 * The fonts and pictures the templates use that a ZIP can carry: the installed fonts (not the plugin's, which every site has) with
 * their files, and the pictures chosen from the Media Library of this site. A picture inside the design (a data address) or on another
 * site is not here: the first travels in the JSON and the second is not ours to copy.
 *
 * @param array<int, array<string, mixed>> $templates Templates of a bundle (each with its `design`).
 * @return array{fonts: array<int, array<string, string>>, images: array<int, array<string, string>>} Each with its `path`.
 */
function assets_of( array $templates ) {
	$families = array();
	$urls     = array();
	foreach ( $templates as $item ) {
		foreach ( design_elements( (array) ( $item['design'] ?? array() ) ) as $element ) {
			if ( ! empty( $element['font'] ) ) {
				$families[ Fonts\clean_name( (string) $element['font'] ) ] = true;
			}
			if ( 'image' === ( $element['type'] ?? '' ) && is_string( $element['src'] ?? null ) && '' !== $element['src'] ) {
				$urls[ $element['src'] ] = (int) ( $element['attachment'] ?? 0 );
			}
		}
	}
	$fonts = array();
	$seen  = array();
	foreach ( Restrictions\allows( 'fonts' ) ? Fonts\installed() : array() as $face ) {
		if ( isset( $families[ $face['family'] ] ) && '' !== $face['file'] && ! isset( $seen[ $face['file'] ] ) ) {
			$seen[ $face['file'] ] = true;
			$fonts[]               = array(
				'family'  => (string) $face['family'],
				'label'   => (string) $face['label'],
				'generic' => (string) $face['generic'],
				'weight'  => (string) ( $face['raw'] ?? $face['weight'] ),
				'style'   => 'normal',
				'path'    => (string) $face['file'],
			);
		}
	}
	$images = array();
	foreach ( Restrictions\allows( 'pictures' ) ? $urls : array() as $url => $attachment ) {
		$path = upload_picture( $url );
		if ( '' !== $path ) {
			$images[] = array(
				'url'   => $url,
				'alt'   => $attachment ? (string) get_post_meta( $attachment, '_wp_attachment_image_alt', true ) : '',
				'title' => $attachment ? html_entity_decode( get_the_title( $attachment ), ENT_QUOTES, 'UTF-8' ) : '',
				'path'  => $path,
			);
		}
	}

	return array(
		'fonts'  => $fonts,
		'images' => $images,
	);
}

/**
 * What the site's templates have to take along: the families' names and how many pictures (for the Export screen).
 *
 * @return array{families: string[], images: int}
 */
function template_assets() {
	$items = array();
	foreach ( Designs\templates() as $post ) {
		$items[] = array( 'design' => Designs\get( (int) $post->ID ) );
	}
	$found = assets_of( $items );

	return array(
		'families' => array_values( array_unique( array_column( $found['fonts'], 'label' ) ) ),
		'images'   => count( $found['images'] ),
	);
}

/**
 * The list of a ZIP's files: gives each its name in the ZIP (without collisions), fills `$files` and returns what the bundle says.
 *
 * @param array<string, array<int, array<string, string>>> $found Files (assets_of()).
 * @param array<string, string>                            $files Name in the ZIP => path, filled.
 * @return array<string, array<int, array<string, mixed>>>
 */
function package_assets( array $found, array &$files ) {
	$out  = array(
		'fonts'  => array(),
		'images' => array(),
	);
	$name = static function ( $folder, $wanted ) use ( &$files ) {
		$base = sanitize_file_name( $wanted );
		$name = $folder . '/' . $base;
		for ( $i = 2; isset( $files[ $name ] ); $i++ ) {
			$name = $folder . '/' . pathinfo( $base, PATHINFO_FILENAME ) . '-' . $i . '.' . pathinfo( $base, PATHINFO_EXTENSION );
		}

		return $name;
	};
	foreach ( $found['fonts'] as $font ) {
		$ext            = strtolower( pathinfo( $font['path'], PATHINFO_EXTENSION ) );
		$file           = $name( 'fonts', $font['family'] . '-' . str_replace( ' ', '-', $font['weight'] ) . '.' . $ext );
		$files[ $file ] = $font['path'];
		$out['fonts'][] = array(
			'family'  => $font['family'],
			'label'   => $font['label'],
			'generic' => $font['generic'],
			'weight'  => $font['weight'],
			'style'   => $font['style'],
			'file'    => $file,
			'size'    => (int) filesize( $font['path'] ),
			'sha256'  => (string) hash_file( 'sha256', $font['path'] ),
		);
	}
	foreach ( $found['images'] as $image ) {
		$file            = $name( 'images', basename( $image['path'] ) );
		$files[ $file ]  = $image['path'];
		$out['images'][] = array(
			'ref'    => $image['ref'] ?? '',
			'url'    => $image['url'],
			'alt'    => $image['alt'],
			'title'  => $image['title'],
			'file'   => $file,
			'size'   => (int) filesize( $image['path'] ),
			'sha256' => (string) hash_file( 'sha256', $image['path'] ),
		);
	}

	return $out;
}

/**
 * The bundle as it is written to a file: each template's design as the block markup a template is kept as (docs/decisions/0045), not as
 * an array. The rest of the bundle is as it is.
 *
 * @param array<string, mixed> $bundle The bundle (export()).
 * @return array<string, mixed>
 */
function for_file( array $bundle ) {
	foreach ( (array) ( $bundle['templates'] ?? array() ) as $i => $template ) {
		if ( is_array( $template['design'] ?? null ) ) {
			$bundle['templates'][ $i ]['design'] = Designs\content( $template['design'] );
		}
	}

	return $bundle;
}

/**
 * The bundle as the importer works on it: each template's design as an array, read from its block markup. A template whose design is not
 * block markup (what a file of an earlier form had, an array) comes in with no design, so it is left out.
 *
 * @param array<string, mixed> $bundle The bundle read from a file.
 * @return array<string, mixed>
 */
function from_file( array $bundle ) {
	foreach ( (array) ( $bundle['templates'] ?? array() ) as $i => $template ) {
		$markup                              = is_string( $template['design'] ?? null ) ? $template['design'] : '';
		$design                              = \EventsCertificate\Vendor\Camaleaun\Draw\Design::is_markup( $markup ) ? Designs\decode( $markup ) : null;
		$bundle['templates'][ $i ]['design'] = is_array( $design ) ? $design : array();
	}

	return $bundle;
}

/**
 * Makes the ZIP: the bundle as JSON and the files it lists.
 *
 * @param array<string, mixed>  $bundle The bundle (export()).
 * @param array<string, string> $files  Name in the ZIP => path (export()).
 * @return string|WP_Error Path of a temporary file (the caller deletes it).
 */
function make_zip( array $bundle, array $files ) {
	if ( ! class_exists( '\ZipArchive' ) ) {
		return new WP_Error( 'evcert_bundle_zip', __( 'This server cannot make ZIP files (PHP ZipArchive is missing).', 'events-certificates' ) );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$path = wp_tempnam( 'evcert-export.zip' );
	$zip  = new \ZipArchive();
	if ( ! $path || true !== $zip->open( $path, \ZipArchive::OVERWRITE ) ) {
		return new WP_Error( 'evcert_bundle_zip', __( 'The ZIP file could not be made.', 'events-certificates' ) );
	}
	$zip->addFromString( ZIP_JSON, (string) wp_json_encode( for_file( $bundle ) ) );
	foreach ( $files as $name => $file ) {
		$zip->addFile( $file, $name );
	}
	$zip->close();

	return $path;
}

/**
 * Where the files of a ZIP being imported wait (a private folder outside the web).
 *
 * @param string $key The import.
 * @return string
 */
function import_dir( $key ) {
	return trailingslashit( get_temp_dir() ) . 'evcert-import-' . $key . '-assets';
}

/**
 * Deletes a folder and what is in it.
 *
 * @param string $dir Folder.
 */
function remove_dir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( (array) glob( rtrim( $dir, '/' ) . '/*' ) as $file ) {
		if ( is_dir( $file ) ) {
			remove_dir( $file );
		} else {
			wp_delete_file( $file );
		}
	}
	rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rmdir_rmdir, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- a private temporary folder.
}

/**
 * Whether a file is a picture of a size the plugin takes: one WordPress can read, with no side over IMAGE_MAX_SIDE and no more than
 * IMAGE_MAX_PIXELS pixels (a small file can hold an enormous picture, which would fill the memory when it is made again).
 *
 * @param string $file Path.
 * @return bool
 */
function picture_in_limits( $file ) {
	$size = wp_getimagesize( $file );
	if ( ! is_array( $size ) || empty( $size[0] ) || empty( $size[1] ) ) {
		return false;
	}

	return $size[0] <= IMAGE_MAX_SIDE && $size[1] <= IMAGE_MAX_SIDE && $size[0] * $size[1] <= IMAGE_MAX_PIXELS;
}

/**
 * A picture written again by the image library of WordPress (GD or Imagick), as PNG or JPEG: what the library did not read, such as
 * metadata or something hidden in the file after the picture, is not in the new file.
 *
 * @param string $file Path of the picture.
 * @return string|false Path of the new file (a temporary one the caller deletes), or false when the picture cannot be written again.
 */
function rewrite_picture( $file ) {
	if ( ! function_exists( 'wp_get_image_editor' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	// The kind is what the bytes say, and the editor gets a copy named for it (some image libraries pick the format from the name).
	$size = wp_getimagesize( $file );
	$mime = is_array( $size ) ? (string) ( $size['mime'] ?? '' ) : '';
	$exts = array(
		'image/png'  => 'png',
		'image/jpeg' => 'jpg',
		'image/gif'  => 'gif',
		'image/webp' => 'webp',
	);
	if ( ! isset( $exts[ $mime ] ) ) {
		return false;
	}
	$temp = wp_tempnam( 'evcert-picture' );
	if ( ! $temp ) {
		return false;
	}
	$source = $temp . '-in.' . $exts[ $mime ];
	if ( ! copy( $file, $source ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- a private temporary copy.
		wp_delete_file( $temp );
		return false;
	}
	$editor = wp_get_image_editor( $source );
	if ( is_wp_error( $editor ) ) {
		wp_delete_file( $source );
		wp_delete_file( $temp );
		return false;
	}
	$jpeg   = 'image/jpeg' === $mime;
	$target = $temp . '-out.' . ( $jpeg ? 'jpg' : 'png' );
	$saved  = $editor->save( $target, $jpeg ? 'image/jpeg' : 'image/png' );
	wp_delete_file( $source );
	wp_delete_file( $temp );
	if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! is_readable( $saved['path'] ) ) {
		return false;
	}

	return (string) $saved['path'];
}

/**
 * Whether the first bytes are those of a font of the kind the extension says.
 *
 * @param string $head First bytes of the file.
 * @param string $ext  Extension.
 * @return bool
 */
function looks_like_font( $head, $ext ) {
	$kinds = array(
		'ttf'   => array( "\x00\x01\x00\x00", 'true' ),
		'otf'   => array( 'OTTO', "\x00\x01\x00\x00" ),
		'woff'  => array( 'wOFF' ),
		'woff2' => array( 'wOF2' ),
	);

	return in_array( substr( $head, 0, 4 ), $kinds[ $ext ] ?? array(), true );
}

/**
 * Whether the first bytes are those of a picture of the kind the extension says.
 *
 * @param string $head First bytes of the file.
 * @param string $ext  Extension.
 * @return bool
 */
function looks_like_image( $head, $ext ) {
	switch ( $ext ) {
		case 'png':
			return 0 === strpos( $head, "\x89PNG" );
		case 'jpg':
		case 'jpeg':
			return 0 === strpos( $head, "\xFF\xD8\xFF" );
		case 'gif':
			return 0 === strpos( $head, 'GIF8' );
		case 'webp':
			return 'RIFF' === substr( $head, 0, 4 ) && 'WEBP' === substr( $head, 8, 4 );
	}

	return false;
}

/**
 * What is wrong with an entry of a ZIP, or '' when nothing is: encrypted, a link, named twice, or unpacking to far more than it weighs.
 *
 * @param \ZipArchive          $zip   The ZIP.
 * @param string               $name  Name of the entry.
 * @param array<string, mixed> $stat  Its statIndex().
 * @param array<string, int>   $count How many entries have each name.
 * @return string
 */
function zip_entry_problem( \ZipArchive $zip, $name, array $stat, array $count ) {
	if ( ( $count[ $name ] ?? 0 ) > 1 ) {
		return __( 'named twice', 'events-certificates' );
	}
	if ( ! empty( $stat['encryption_method'] ) ) {
		return __( 'encrypted', 'events-certificates' );
	}
	$os   = 0;
	$attr = 0;
	if ( $zip->getExternalAttributesIndex( (int) $stat['index'], $os, $attr ) && \ZipArchive::OPSYS_UNIX === $os && 0120000 === ( ( $attr >> 16 ) & 0170000 ) ) {
		return __( 'a link', 'events-certificates' );
	}
	if ( (int) $stat['comp_size'] > 0 && (int) $stat['size'] / (int) $stat['comp_size'] > ZIP_MAX_RATIO && (int) $stat['size'] > MB_IN_BYTES ) {
		return __( 'too compressed', 'events-certificates' );
	}

	return '';
}

/**
 * The files a ZIP lists, taken out and checked: only listed ones, with a plain name, an extension and first bytes of a font or a
 * picture, the size and the SHA-256 it says, and inside the limits. What fails is left out and said in `$skipped`. Nothing is written
 * outside the folder, and nothing is run.
 *
 * @param array<string, mixed> $assets  What the JSON says (`assets`).
 * @param \ZipArchive          $zip     The ZIP, open.
 * @param string               $dir     Folder for the files ('' takes none).
 * @param string[]             $skipped Messages, filled.
 * @return array{fonts: array<int, array<string, string>>, images: array<int, array<string, string>>}
 */
function clean_assets( array $assets, \ZipArchive $zip, $dir, array &$skipped ) {
	$out     = array(
		'fonts'  => array(),
		'images' => array(),
	);
	$count   = 0;
	$total   = 0;
	$names   = array();
	$entries = min( $zip->count(), 5000 );
	for ( $n = 0; $n < $entries; $n++ ) {
		$entry_name           = (string) $zip->getNameIndex( $n );
		$names[ $entry_name ] = ( $names[ $entry_name ] ?? 0 ) + 1;
	}
	foreach ( array( 'fonts', 'images' ) as $kind ) {
		$allowed = 'fonts' === $kind ? array( 'ttf', 'otf', 'woff', 'woff2' ) : array( 'png', 'jpg', 'jpeg', 'gif', 'webp' );
		$max     = 'fonts' === $kind ? ASSET_FONT_MAX : ASSET_IMAGE_MAX;
		foreach ( (array) ( $assets[ $kind ] ?? array() ) as $entry ) {
			$entry = (array) $entry;
			$name  = (string) ( $entry['file'] ?? '' );
			if ( ++$count > ASSETS_MAX_FILES ) {
				$skipped[] = __( 'The file has more fonts and pictures than are taken at once; the rest were left out.', 'events-certificates' );
				break 2;
			}
			if ( '' === $dir || ! Restrictions\allows( 'fonts' === $kind ? 'fonts' : 'pictures' ) ) {
				continue;
			}
			$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( ! preg_match( '#^' . $kind . '/[A-Za-z0-9][A-Za-z0-9._ -]*$#', $name ) || false !== strpos( $name, '..' ) || ! in_array( $ext, $allowed, true ) || preg_match( '/\.(php\d?|phtml|phar|pht|phps|htaccess|cgi|pl|py|sh|jsp|asp|aspx|exe)(\.|$)/i', $name ) ) {
				/* translators: %s: a file name from the file. */
				$skipped[] = sprintf( __( '%s was left out: it is not a font or picture with a plain name.', 'events-certificates' ), sanitize_text_field( $name ) );
				continue;
			}
			$index = $zip->locateName( $name );
			$stat  = false === $index ? false : $zip->statIndex( $index );
			$why   = $stat ? zip_entry_problem( $zip, $name, $stat, $names ) : '';
			if ( '' !== $why ) {
				/* translators: 1: a file name from the file, 2: what is wrong with it (encrypted, a link, named twice…). */
				$skipped[] = sprintf( __( '%1$s was left out: it is %2$s.', 'events-certificates' ), $name, $why );
				continue;
			}
			if ( ! $stat || $stat['size'] > $max || $total + $stat['size'] > ASSETS_MAX_TOTAL ) {
				/* translators: %s: a file name from the file. */
				$skipped[] = sprintf( __( '%s was left out: it is missing or too big.', 'events-certificates' ), $name );
				continue;
			}
			$stream = $zip->getStream( $name );
			if ( ! $stream ) {
				/* translators: %s: a file name from the file. */
				$skipped[] = sprintf( __( '%s was left out: it could not be read.', 'events-certificates' ), $name );
				continue;
			}
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
				chmod( $dir, 0700 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.chmod_chmod, WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- private.
			}
			$target = $dir . '/' . sprintf( '%03d', $count ) . '-' . sanitize_file_name( basename( $name ) );
			$handle = fopen( $target, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- a private temporary file.
			$hash   = hash_init( 'sha256' );
			$size   = 0;
			$head   = '';
			$ok     = (bool) $handle;
			while ( $ok && ! feof( $stream ) ) {
				$chunk = fread( $stream, 65536 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- the ZIP's stream.
				if ( false === $chunk ) {
					break;
				}
				$size += strlen( $chunk );
				if ( $size > $max ) {
					$ok = false; // More than the ZIP said: stop, it is not trusted.
					break;
				}
				$head = '' === $head ? substr( $chunk, 0, 12 ) : $head;
				hash_update( $hash, $chunk );
				fwrite( $handle, $chunk ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- a private temporary file.
			}
			if ( $handle ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- a private temporary file.
			}
			fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- the ZIP's stream.
			$sha    = hash_final( $hash );
			$wanted = strtolower( (string) ( $entry['sha256'] ?? '' ) );
			$magic  = $ok && 'fonts' === $kind ? looks_like_font( $head, $ext ) && Fonts\is_well_formed( $target, $ext ) : $ok && looks_like_image( $head, $ext ) && picture_in_limits( $target );
			if ( ! $ok || (int) ( $entry['size'] ?? -1 ) !== $size || '' === $wanted || ! hash_equals( $wanted, $sha ) || ! $magic ) {
				wp_delete_file( $target );
				/* translators: %s: a file name from the file. */
				$skipped[] = sprintf( __( '%s was left out: it is not what the file says it is.', 'events-certificates' ), $name );
				continue;
			}
			$total += $size;
			if ( 'fonts' === $kind ) {
				$weight         = (string) ( $entry['weight'] ?? '400' );
				$out['fonts'][] = array(
					'family'  => Fonts\clean_name( (string) ( $entry['family'] ?? '' ) ),
					'label'   => sanitize_text_field( (string) ( $entry['label'] ?? '' ) ),
					'generic' => 'serif' === ( $entry['generic'] ?? '' ) ? 'serif' : 'sans-serif',
					'weight'  => preg_match( '/^(normal|bold|\d{3}( \d{3})?)$/', $weight ) ? $weight : '400',
					'file'    => $name,
					'path'    => $target,
				);
			} else {
				$url             = esc_url_raw( (string) ( $entry['url'] ?? '' ), array( 'http', 'https' ) );
				$ref             = preg_match( '/^picture:[a-f0-9]{64}$/', (string) ( $entry['ref'] ?? '' ) ) ? (string) $entry['ref'] : '';
				$out['images'][] = array(
					'ref'    => $ref,
					'url'    => $url,
					'alt'    => sanitize_text_field( (string) ( $entry['alt'] ?? '' ) ),
					'title'  => sanitize_text_field( (string) ( $entry['title'] ?? '' ) ),
					'file'   => $name,
					'sha256' => $sha,
					'path'   => $target,
				);
			}
		}
	}

	return $out;
}

/**
 * Reads a ZIP made by Export: the JSON and, when a folder is given, the fonts and pictures it lists.
 *
 * @param string $path Path of the ZIP.
 * @param string $dir  Folder for its files ('' takes none).
 * @return array<string, mixed>|WP_Error
 */
function read_zip( $path, $dir ) {
	if ( ! class_exists( '\ZipArchive' ) ) {
		return new WP_Error( 'evcert_bundle_zip', __( 'This server cannot open ZIP files (PHP ZipArchive is missing).', 'events-certificates' ) );
	}
	$zip = new \ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return new WP_Error( 'evcert_bundle_format', __( 'That is not an Events Certificates file.', 'events-certificates' ) );
	}
	$index  = $zip->locateName( ZIP_JSON );
	$stat   = false === $index ? false : $zip->statIndex( $index );
	$bundle = $stat && $stat['size'] <= max_bytes() ? json_decode( (string) $zip->getFromIndex( $index, max_bytes() + 1 ), true, JSON_DEPTH ) : null;
	if ( ! is_array( $bundle ) || ( $bundle['$schema'] ?? '' ) !== SCHEMA ) {
		$zip->close();
		return new WP_Error( 'evcert_bundle_format', __( 'That is not an Events Certificates file.', 'events-certificates' ) );
	}
	$skipped           = array();
	$bundle['assets']  = clean_assets( (array) ( $bundle['assets'] ?? array() ), $zip, $dir, $skipped );
	$bundle['skipped'] = $skipped;
	$zip->close();

	return from_file( $bundle );
}

/**
 * Reads a bundle file, refusing what is not one.
 *
 * @param string $path Temporary path of the upload.
 * @param string $dir  Where the files of a ZIP are taken out ('' takes none).
 * @return array<string, mixed>|WP_Error
 */
function read( $path, $dir = '' ) {
	if ( ! is_readable( $path ) || (int) filesize( $path ) > max_bytes() ) {
		return new WP_Error( 'evcert_bundle_size', __( 'That file is too big or cannot be read.', 'events-certificates' ) );
	}
	wp_raise_memory_limit( 'admin' );
	if ( "PK\x03\x04" === (string) file_get_contents( $path, false, null, 0, 4 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- an upload being checked.
		if ( ! Restrictions\allows( 'zip' ) ) {
			return new WP_Error( 'evcert_bundle_zip', Restrictions\message( 'zip' ) );
		}

		return read_zip( $path, $dir );
	}
	$bundle = json_decode( (string) file_get_contents( $path ), true, JSON_DEPTH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- an upload being read.
	if ( ! is_array( $bundle ) || ( $bundle['$schema'] ?? '' ) !== SCHEMA ) {
		return new WP_Error( 'evcert_bundle_format', __( 'That is not an Events Certificates file.', 'events-certificates' ) );
	}

	return from_file( $bundle );
}

/**
 * The most a file may have.
 *
 * @return int Bytes.
 */
function max_bytes() {
	/**
	 * The largest bundle file the import reads, in bytes.
	 *
	 * @param int $bytes Default: the smaller of 20 MB and what the server accepts for uploads.
	 */
	return (int) apply_filters( 'evcert_bundle_max_bytes', min( 20 * MB_IN_BYTES, wp_max_upload_size() ) );
}

/**
 * How many items of each stage one step does (a step is one request of the import screen).
 */
const BATCH = array(
	'assets'       => 4,
	'templates'    => 25,
	'events'       => 25,
	'people'       => 150,
	'certificates' => 100,
	'links'        => 300,
);

/**
 * Starts an import: what will be done, in order, and the state the steps carry.
 *
 * @param array<string, mixed> $bundle   The file read (read()).
 * @param string[]             $asked    Sections to import (sections() adds the ones they need).
 * @param string               $existing skip|update: what to do with a template, event or participant that is already there.
 *                                       A certificate is never written over.
 * @param bool                 $assets   Whether to install the fonts and pictures a ZIP brings (only with the templates).
 * @return array<string, mixed> The state to give to step().
 */
function begin( array $bundle, array $asked, $existing = 'skip', $assets = true ) {
	$sections = array_values( array_intersect( sections( $asked ), (array) ( $bundle['sections'] ?? array() ) ) );
	$summary  = array(
		'conflicts' => array(),
		'assets'    => array(
			'fonts'   => 0,
			'images'  => 0,
			'skipped' => 0,
			'denied'  => 0,
			'failed'  => 0,
		),
	);
	$stages   = array();
	$total    = 0;
	foreach ( $sections as $section ) {
		$summary[ $section ] = array(
			'added'   => 0,
			'updated' => 0,
			'skipped' => 0,
		);
		if ( 'settings' === $section ) {
			$stages[] = 'settings';
			++$total;
			continue;
		}
		$stages[] = $section;
		$total   += count( (array) ( $bundle[ $section ] ?? array() ) );
	}
	$brought = count( (array) ( $bundle['assets']['fonts'] ?? array() ) ) + count( (array) ( $bundle['assets']['images'] ?? array() ) );
	if ( $assets && $brought ) {
		// The fonts and pictures come before what uses them (templates, events, certificates, the signature).
		array_unshift( $stages, 'assets' );
		$total += $brought;
	}
	foreach ( (array) ( $bundle['skipped'] ?? array() ) as $message ) {
		$summary['conflicts'][] = (string) $message;
	}
	foreach ( left_out( $bundle ) as $feature ) {
		/* translators: %s: what the site does not allow (e.g. "Fonts in packages"). */
		$summary['conflicts'][] = sprintf( __( '%s were left out of the import: this site does not allow them.', 'events-certificates' ), Restrictions\labels()[ $feature ] );
	}
	if ( in_array( 'certificates', $sections, true ) ) {
		// The links of "replaced by" and of the participants' roles come after the certificates and before the settings.
		array_splice( $stages, array_search( 'certificates', $stages, true ) + 1, 0, 'links' );
		$total += count( (array) ( $bundle['certificates'] ?? array() ) ) + count( (array) ( $bundle['people'] ?? array() ) );
	}

	return array(
		'sections'      => $sections,
		'update'        => 'update' === $existing,
		'stages'        => $stages,
		'stage'         => 0,
		'cursor'        => 0,
		'maps'          => array(
			'templates' => array(),
			'images'    => array(),
			'events'    => array(),
			'people'    => array(),
			'ids'       => array(),
		),
		'later'         => array(),
		'summary'       => $summary,
		'had_documents' => documents_exist(),
		'done'          => 0,
		'total'         => max( 1, $total ),
	);
}

/**
 * Does the next batch of an import.
 *
 * @param array<string, mixed> $state  The state of begin(), changed.
 * @param array<string, mixed> $bundle The file read.
 * @return bool Whether the import is finished.
 */
function step( array &$state, array $bundle ) {
	$stage = $state['stages'][ $state['stage'] ] ?? '';
	if ( '' === $stage ) {
		return true;
	}
	$cursor = (int) $state['cursor'];
	$size   = BATCH[ $stage ] ?? 1;
	$maps   = &$state['maps'];
	$sum    = &$state['summary'];
	if ( 'settings' === $stage ) {
		import_settings( use_pictures( (array) ( $bundle['settings'] ?? array() ), (array) ( $maps['images'] ?? array() ) ), (bool) $state['had_documents'], $sum );
		$count = 1;
		$items = 1;
	} elseif ( 'links' === $stage ) {
		$count = link_batch( $state, $bundle, $cursor, $size );
		$items = count( $state['later'] ) + count( (array) ( $bundle['people'] ?? array() ) );
	} elseif ( 'assets' === $stage ) {
		$list = array();
		foreach ( array( 'fonts', 'images' ) as $kind ) {
			foreach ( (array) ( $bundle['assets'][ $kind ] ?? array() ) as $entry ) {
				$list[] = array( $kind, (array) $entry );
			}
		}
		$batch = array_slice( $list, $cursor, $size );
		$items = count( $list );
		$count = count( $batch );
		foreach ( $batch as $one ) {
			import_asset( $one[1], $one[0], $state );
		}
	} else {
		$list  = array_values( (array) ( $bundle[ $stage ] ?? array() ) );
		$batch = array_slice( $list, $cursor, $size );
		$items = count( $list );
		$count = count( $batch );
		foreach ( $batch as $item ) {
			$item = (array) $item;
			if ( 'templates' === $stage ) {
				import_template( $item, $state['update'], $maps['templates'], $sum, (array) ( $maps['images'] ?? array() ) );
			} elseif ( 'events' === $stage ) {
				import_event( use_pictures( $item, (array) ( $maps['images'] ?? array() ) ), $state['update'], $maps['events'], $sum );
			} elseif ( 'people' === $stage ) {
				import_person( $item, $state['update'], $maps['events'], $maps['people'], $sum );
			} else {
				$item['data'] = use_pictures( (array) ( $item['data'] ?? array() ), (array) ( $maps['images'] ?? array() ) );
				import_certificate( $item, $bundle, $state );
			}
		}
	}
	$state['done']  += $count;
	$state['cursor'] = $cursor + $count;
	if ( $state['cursor'] >= $items || 0 === $count ) {
		++$state['stage'];
		$state['cursor'] = 0;
	}

	return ! isset( $state['stages'][ $state['stage'] ] );
}

/**
 * How far an import is, in percent.
 *
 * @param array<string, mixed> $state The state.
 * @return int
 */
function progress( array $state ) {
	return isset( $state['stages'][ $state['stage'] ] ) ? min( 99, (int) floor( 100 * $state['done'] / $state['total'] ) ) : 100;
}

/**
 * Imports a bundle in one go (the screen does it in steps).
 *
 * @param array<string, mixed> $bundle   The file read (read()).
 * @param string[]             $asked    Sections to import (sections() adds the ones they need).
 * @param string               $existing skip|update.
 * @return array<string, array<string, int>|string[]> Counts by section (added, updated, skipped) and `conflicts`: messages.
 */
function import( array $bundle, array $asked, $existing = 'skip' ) {
	$state = begin( $bundle, $asked, $existing );
	while ( ! step( $state, $bundle ) ) {
		continue;
	}

	return $state['summary'];
}

/**
 * Whether the site already has certificates with a document hash (the pepper is then not to be replaced).
 *
 * @return bool
 */
function documents_exist() {
	global $wpdb;

	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM %i WHERE doc_hash <> '' LIMIT 1", Certificates\table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
}

/**
 * A template: matched by its name and content (the same is skipped), else made; with `update`, one of the same name is rewritten.
 *
 * @param array<string, mixed> $item      The bundle's template.
 * @param bool                 $update    Whether to rewrite one of the same name.
 * @param array<int, int>      $templates Bundle key => template ID, filled.
 * @param array<string, mixed> $summary   Counts, filled.
 * @param array<string, mixed> $images    The pictures the file brought, made here (see rewrite_images()).
 */
function import_template( array $item, $update, array &$templates, array &$summary, array $images = array() ) {
	$design = $item['design'] ?? null;
	$title  = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
	if ( ! is_array( $design ) || ! isset( $design['elements'], $design['page'] ) || ! is_array( $design['elements'] ) || ! is_array( $design['page'] ) || '' === $title ) {
		++$summary['templates']['skipped'];
		return;
	}
	// The pictures of another site's Media Library become the ones made here, then only what a design may have is kept.
	$notes  = array();
	$design = Designs\clean( rewrite_images( $design, $images ), $notes );
	if ( null === $design ) {
		++$summary['templates']['skipped'];
		return;
	}
	if ( $notes ) {
		/* translators: 1: template name, 2: elements removed, 3: pictures removed, 4: paths removed. */
		$summary['conflicts'][] = sprintf( __( 'Template "%1$s": removed %2$d elements it may not have, %3$d pictures that are not in this site\'s Media Library and %4$d drawing paths that are not valid.', 'events-certificates' ), $title, (int) ( $notes['elements'] ?? 0 ), (int) ( $notes['pictures'] ?? 0 ), (int) ( $notes['paths'] ?? 0 ) );
	}
	$json  = Designs\content( restrict_design( $design ) );
	$found = 0;
	foreach ( Designs\templates() as $post ) {
		if ( html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ) === $title ) {
			$found = (int) $post->ID;
			break;
		}
	}
	$key = (int) ( $item['key'] ?? 0 );
	if ( $found ) {
		$templates[ $key ] = $found;
		if ( ! $update || hash( 'sha256', (string) get_post_field( 'post_content', $found ) ) === hash( 'sha256', $json ) ) {
			++$summary['templates']['skipped'];
			return;
		}
		wp_update_post(
			array(
				'ID'           => $found,
				'post_content' => wp_slash( $json ),
			)
		);
		++$summary['templates']['updated'];
		return;
	}
	$id = wp_insert_post(
		array(
			'post_type'    => Designs\POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => wp_slash( $json ),
			'menu_order'   => (int) ( $item['order'] ?? 0 ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		++$summary['templates']['skipped'];
		return;
	}
	update_post_meta( $id, Designs\ROLES_META, Designs\sanitize_roles( $item['roles'] ?? array() ) );
	$templates[ $key ] = (int) $id;
	++$summary['templates']['added'];
}

/**
 * A design with its Media Library pictures pointing to the ones this site made from the file (their address and their ID).
 *
 * @param array<string, mixed>                       $design Design.
 * @param array<string, array{id: int, url: string}> $images Original address => the picture here.
 * @return array<string, mixed>
 */
function rewrite_images( array $design, array $images ) {
	if ( ! $images ) {
		return $design;
	}
	$fix = static function ( $elements ) use ( $images ) {
		foreach ( (array) $elements as $i => $element ) {
			if ( is_array( $element ) && 'image' === ( $element['type'] ?? '' ) && is_string( $element['src'] ?? null ) && isset( $images[ $element['src'] ] ) ) {
				$elements[ $i ]['src']        = $images[ $element['src'] ]['url'];
				$elements[ $i ]['attachment'] = $images[ $element['src'] ]['id'];
			}
		}

		return $elements;
	};
	if ( isset( $design['elements'] ) ) {
		$design['elements'] = $fix( $design['elements'] );
	}
	if ( isset( $design['back']['elements'] ) ) {
		$design['back']['elements'] = $fix( $design['back']['elements'] );
	}

	return $design;
}

/**
 * Gives an event's, a certificate's or the settings' pictures the attachments the import made: `picture:<sha256>` becomes the ID.
 * What is not such a reference (an ID of another site, a data URI of an older file) is left out, never kept.
 *
 * @param array<string, mixed>                $data   The data from the file.
 * @param array<string, array<string, mixed>> $images Reference => id and url (import_asset()).
 * @return array<string, mixed>
 */
function use_pictures( array $data, array $images ) {
	foreach ( array( 'logo', 'signature_image' ) as $key ) {
		if ( isset( $data[ $key ] ) ) {
			$ref          = is_string( $data[ $key ] ) ? $data[ $key ] : '';
			$data[ $key ] = isset( $images[ $ref ]['id'] ) ? (string) $images[ $ref ]['id'] : '';
		}
	}

	return $data;
}

/**
 * What the pictures of an import are known by: the reference a bundle gave (`picture:<sha256>`), else the address of a template's picture.
 *
 * @param array<string, string> $entry The entry (clean_assets()).
 * @return string
 */
function self_key( array $entry ) {
	return empty( $entry['ref'] ) ? (string) $entry['url'] : (string) $entry['ref'];
}

/**
 * A font or a picture of the file: installed in the Font Library or made in the Media Library, when the user may (the permissions
 * WordPress asks for each). A picture already made from the same file (its SHA-256 is in the attachment) is used, not made again.
 *
 * @param array<string, string> $entry The entry (clean_assets()), with its `path`.
 * @param string                $kind  fonts or images.
 * @param array<string, mixed>  $state The state of begin(), changed.
 */
function import_asset( array $entry, $kind, array &$state ) {
	$sum = &$state['summary']['assets'];
	if ( 'fonts' === $kind ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			++$sum['denied'];
			return;
		}
		$result = Fonts\install_face( $entry['family'], $entry['label'], $entry['generic'], $entry['weight'], $entry['path'] );
		if ( 'installed' === $result ) {
			++$sum['fonts'];
		} elseif ( 'present' === $result ) {
			++$sum['skipped'];
		} else {
			++$sum['failed'];
		}
		return;
	}
	if ( ! current_user_can( 'upload_files' ) ) {
		++$sum['denied'];
		return;
	}
	$images = &$state['maps']['images'];
	$found  = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'meta_key'    => '_evcert_asset_sha256', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one lookup by the file's hash.
			'meta_value'  => $entry['sha256'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( $found && wp_attachment_is_image( $found[0]->ID ) ) {
		$images[ self_key( $entry ) ] = array(
			'id'  => (int) $found[0]->ID,
			'url' => (string) wp_get_attachment_url( $found[0]->ID ),
		);
		++$sum['skipped'];
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	// What goes into the Media Library is the picture written again by the image library (nothing else of the file comes in), and
	// only the four kinds of picture are accepted for this one upload (never an SVG).
	$copy = rewrite_picture( $entry['path'] );
	if ( false === $copy ) {
		++$sum['failed'];
		return;
	}
	$kinds = static function () {
		return array(
			'png'          => 'image/png',
			'jpg|jpeg|jpe' => 'image/jpeg',
		);
	};
	add_filter( 'upload_mimes', $kinds, 99 );
	$made = media_handle_sideload(
		array(
			'name'     => preg_replace( '/\.[A-Za-z0-9]+$/', '', preg_replace( '/^\d{3}-/', '', basename( $entry['path'] ) ) ) . '.' . pathinfo( $copy, PATHINFO_EXTENSION ),
			'tmp_name' => $copy,
		),
		0,
		null,
		'' !== $entry['title'] ? array( 'post_title' => $entry['title'] ) : array()
	);
	remove_filter( 'upload_mimes', $kinds, 99 );
	if ( is_wp_error( $made ) ) {
		wp_delete_file( $copy );
		++$sum['failed'];
		return;
	}
	update_post_meta( $made, '_evcert_asset_sha256', $entry['sha256'] );
	if ( '' !== $entry['alt'] ) {
		update_post_meta( $made, '_wp_attachment_image_alt', $entry['alt'] );
	}
	$images[ self_key( $entry ) ] = array(
		'id'  => (int) $made,
		'url' => (string) wp_get_attachment_url( $made ),
	);
	++$sum['images'];
}

/**
 * An event: matched by its source record (GatherPress) or its name and start, else made.
 *
 * @param array<string, mixed> $item    The bundle's event.
 * @param bool                 $update  Whether to rewrite one already there.
 * @param array<int, int>      $events  Bundle key => event ID, filled.
 * @param array<string, mixed> $summary Counts, filled.
 */
function import_event( array $item, $update, array &$events, array &$summary ) {
	$item  = restrict_event( $item );
	$clean = Events\clean( $item );
	$key   = (int) ( $item['key'] ?? 0 );
	if ( is_wp_error( $clean ) ) {
		++$summary['events']['skipped'];
		return;
	}
	$found = 0;
	foreach ( Events\all() as $post ) {
		$event = Events\get( (int) $post->ID );
		if ( ( 'gatherpress' === $clean['source'] && '0' !== $clean['source_ref'] && $event['source_ref'] === $clean['source_ref'] && 'gatherpress' === $event['source'] ) || ( $event['title'] === $clean['title'] && $event['start'] === $clean['start'] ) ) {
			$found = (int) $post->ID;
			break;
		}
	}
	if ( $found ) {
		$events[ $key ] = $found;
		if ( ! $update ) {
			++$summary['events']['skipped'];
			return;
		}
		Events\save( $item, $found );
		++$summary['events']['updated'];
		return;
	}
	$id = Events\save( $item );
	if ( is_wp_error( $id ) ) {
		++$summary['events']['skipped'];
		return;
	}
	$events[ $key ] = (int) $id;
	++$summary['events']['added'];
}

/**
 * A participant: matched in its event like any added one (source record, e-mail, name), else made; trashed ones stay in the Trash.
 *
 * @param array<string, mixed> $item    The bundle's participant.
 * @param bool                 $update  Whether to rewrite one already there.
 * @param array<int, int>      $events  Bundle key => event ID.
 * @param array<int, int>      $people  Bundle key => participant ID, filled.
 * @param array<string, mixed> $summary Counts, filled.
 */
function import_person( array $item, $update, array $events, array &$people, array &$summary ) {
	$event = $events[ (int) ( $item['event'] ?? 0 ) ] ?? 0;
	$clean = People\clean( array_merge( $item, array( 'roles' => array_keys( (array) ( $item['roles'] ?? array() ) ) ) ) );
	$key   = (int) ( $item['key'] ?? 0 );
	if ( ! $event || '' === $clean['name'] ) {
		++$summary['people']['skipped'];
		return;
	}
	$existing = People\find( $event, $clean );
	if ( $existing ) {
		$people[ $key ] = (int) $existing['id'];
		if ( ! $update ) {
			++$summary['people']['skipped'];
			return;
		}
	}
	// Added by hand so a person in the Trash of the file is not brought back by the "typed again" rule.
	$result = People\add( $event, $clean + array( 'source' => $clean['source'] ) );
	if ( is_wp_error( $result ) ) {
		++$summary['people']['skipped'];
		return;
	}
	$people[ $key ] = (int) $result['id'];
	if ( $result['added'] ) {
		++$summary['people']['added'];
		if ( ! empty( $item['deleted_at'] ) ) {
			People\trash( array( (int) $result['id'] ), $event );
		}
	} else {
		++$summary['people']['updated'];
	}
}

/**
 * One certificate, by its code: skipped when it is there with the same content, reported when the content is not the same, else made.
 *
 * @param array<string, mixed> $item   The bundle's certificate.
 * @param array<string, mixed> $bundle The file (its design snapshots).
 * @param array<string, mixed> $state  The import's state: the maps and the summary, changed.
 */
function import_certificate( array $item, array $bundle, array &$state ) {
	global $wpdb;

	$code = Code\normalize( (string) ( $item['code'] ?? '' ) );
	$row  = clean_certificate( $item, $code, $state['maps']['events'], $state['maps']['people'], (array) ( $bundle['snapshots'] ?? array() ) );
	if ( ! $row ) {
		++$state['summary']['certificates']['skipped'];
		return;
	}
	$there = Certificates\get_by_code( $code, true );
	if ( $there ) {
		$state['maps']['ids'][ $code ] = (int) $there['id'];
		if ( Certificates\fingerprint( $there ) !== $row['fingerprint'] ) {
			/* translators: %s: the code of a certificate. */
			$state['summary']['conflicts'][] = sprintf( __( 'The certificate %s is already here with other content; it was left as it is.', 'events-certificates' ), $code );
		}
		++$state['summary']['certificates']['skipped'];
		return;
	}
	$design = $bundle['snapshots'][ $row['design_hash'] ] ?? null;
	if ( is_array( $design ) ) {
		Certificates\store_design( $design );
	}
	$now = current_time( 'mysql', true );
	if ( ! $wpdb->insert( Certificates\table(), $row + array( 'created_at' => $now, 'updated_at' => $now ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- the plugin's own table.
		++$state['summary']['certificates']['skipped'];
		return;
	}
	$state['maps']['ids'][ $code ] = (int) $wpdb->insert_id;
	$state['later'][]              = array( $code, Code\normalize( (string) ( $item['replaced_by'] ?? '' ) ) );
	++$state['summary']['certificates']['added'];
}

/**
 * A batch of the links made after the certificates: the one a correction replaced points at the one that replaced it, then a
 * participant's role points at its certificate (the first of a chain).
 *
 * @param array<string, mixed> $state  The import's state.
 * @param array<string, mixed> $bundle The file.
 * @param int                  $cursor Where the batch starts, over the replaced links and then the participants.
 * @param int                  $size   How many to do.
 * @return int How many were done.
 */
function link_batch( array $state, array $bundle, $cursor, $size ) {
	global $wpdb;

	$ids   = $state['maps']['ids'];
	$later = $state['later'];
	$left  = $size;
	$done  = 0;
	if ( $cursor < count( $later ) ) {
		foreach ( array_slice( $later, $cursor, $left ) as $pair ) {
			if ( '' !== $pair[1] && isset( $ids[ $pair[1] ], $ids[ $pair[0] ] ) ) {
				$wpdb->update( Certificates\table(), array( 'replaced_by' => $ids[ $pair[1] ] ), array( 'id' => $ids[ $pair[0] ] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
			}
			++$done;
		}

		return $done;
	}
	$people = array_values( (array) ( $bundle['people'] ?? array() ) );
	foreach ( array_slice( $people, $cursor - count( $later ), $left ) as $item ) {
		$person = $state['maps']['people'][ (int) ( $item['key'] ?? 0 ) ] ?? 0;
		foreach ( (array) ( $item['roles'] ?? array() ) as $role => $code ) {
			$cert = Code\normalize( (string) $code );
			if ( $person && '' !== $cert && isset( $ids[ $cert ] ) ) {
				$wpdb->update( People\roles_table(), array( 'certificate_id' => $ids[ $cert ] ), array( 'person_id' => $person, 'role' => sanitize_key( (string) $role ), 'certificate_id' => 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- the plugin's own table.
			}
		}
		++$done;
	}

	return $done;
}

/**
 * A certificate of the file as a row to insert, every value checked again; false when it cannot be one.
 *
 * @param array<string, mixed> $item      The bundle's certificate.
 * @param string               $code      Its code, normalized.
 * @param array<int, int>      $events    Bundle key => event ID.
 * @param array<int, int>      $people    Bundle key => participant ID.
 * @param array<string, mixed> $snapshots Design snapshots of the file, by hash.
 * @return array<string, mixed>|false
 */
function clean_certificate( array $item, $code, array $events, array $people, array $snapshots ) {
	$status = (string) ( $item['status'] ?? '' );
	if ( '' === $code || ! in_array( $status, STATUSES, true ) || '' === trim( (string) ( $item['name'] ?? '' ) ) ) {
		return false;
	}
	$text    = static function ( $key, $length = 255 ) use ( $item ) {
		return substr( sanitize_text_field( (string) ( $item[ $key ] ?? '' ) ), 0, $length );
	};
	$date    = static function ( $key ) use ( $item ) {
		$value = (string) ( $item[ $key ] ?? '' );
		$time  = '' !== $value ? date_create_immutable_from_format( 'Y-m-d H:i:s', $value, new \DateTimeZone( 'UTC' ) ) : false;

		return $time ? $time->format( 'Y-m-d H:i:s' ) : null;
	};
	$context = 'event' === ( $item['context'] ?? '' ) ? 'event' : sanitize_key( (string) ( $item['context'] ?? '' ) );
	// An event that is not in the file (or was no longer there) leaves the certificate with none: it says its own event.
	$event      = 'event' === $context ? ( $events[ (int) ( $item['event'] ?? 0 ) ] ?? 0 ) : 0;
	$source     = substr( sanitize_key( (string) ( $item['source'] ?? 'manual' ) ), 0, 20 );
	$source_ref = $text( 'source_ref', 100 );
	if ( People\SOURCE === $source ) {
		// The participant it was issued to: the one of this site.
		$source_ref = (string) ( $people[ (int) $source_ref ] ?? 0 );
	}
	$hash = (string) ( $item['design_hash'] ?? '' );
	if ( '' !== $hash && ( ! isset( $snapshots[ $hash ] ) || hash( 'sha256', (string) wp_json_encode( $snapshots[ $hash ] ) ) !== $hash ) ) {
		$hash = ''; // A snapshot that is not the one it names is not kept: the certificate is drawn with the current template.
	}
	$hours              = $item['hours'] ?? null;
	$row                = array(
		'context'          => $context,
		'event_id'         => $event,
		'group_id'         => absint( $item['group_id'] ?? 0 ),
		'code'             => $code,
		'status'           => $status,
		'name'             => $text( 'name' ),
		'email'            => sanitize_email( (string) ( $item['email'] ?? '' ) ),
		'wporg_username'   => substr( sanitize_user( (string) ( $item['wporg_username'] ?? '' ), true ), 0, 60 ),
		'role'             => substr( sanitize_key( (string) ( $item['role'] ?? '' ) ), 0, 60 ),
		'details'          => sanitize_textarea_field( (string) ( $item['details'] ?? '' ) ),
		'attended'         => empty( $item['attended'] ) ? 0 : 1,
		'locale'           => substr( preg_replace( '/[^A-Za-z_]/', '', (string) ( $item['locale'] ?? '' ) ), 0, 12 ),
		'event_title'      => $text( 'event_title' ),
		'event_start'      => $date( 'event_start' ),
		'event_end'        => $date( 'event_end' ),
		'event_tz'         => substr( preg_replace( '/[^A-Za-z0-9_\/+:-]/', '', (string) ( $item['event_tz'] ?? '' ) ), 0, 40 ),
		'hours'            => is_numeric( $hours ) && (float) $hours >= 0 && (float) $hours < 1000 ? round( (float) $hours, 2 ) : null,
		'data_json'        => clean_data( restrict_event( (array) ( $item['data'] ?? array() ) ) ),
		'doc_type'         => $text( 'doc_type', 40 ),
		'doc_label'        => $text( 'doc_label', 60 ),
		'doc_masked'       => $text( 'doc_masked', 40 ),
		'doc_hash'         => substr( preg_replace( '/[^A-Za-z0-9$.\/+=,-]/', '', (string) ( $item['doc_hash'] ?? '' ) ), 0, 255 ),
		'self_service'     => $text( 'self_service', 20 ),
		'design_hash'      => $hash,
		'renderer_version' => $text( 'renderer_version', 10 ),
		'source'           => '' !== $source ? $source : 'manual',
		'source_ref'       => $source_ref,
		'revoked_reason'   => $text( 'revoked_reason' ),
		'trashed_from'     => 'trash' === $status && in_array( $item['trashed_from'] ?? '', array( 'issued', 'replaced', 'revoked' ), true ) ? (string) $item['trashed_from'] : null,
		'trashed_at'       => 'trash' === $status ? $date( 'trashed_at' ) : null,
		'issued_at'        => $date( 'issued_at' ),
	);
	$row['fingerprint'] = Certificates\fingerprint( $row );

	return $row;
}

/**
 * What a certificate recorded about itself (its data), kept only as plain values: pictures and drawings are checked, text has
 * no markup, nothing is deeper than a few levels or bigger than a certificate needs.
 *
 * @param mixed $data Decoded data.
 * @param int   $depth Level.
 * @return string|array<string, mixed> JSON at the top; a value inside.
 */
function clean_data( $data, $depth = 0 ) {
	$out = array();
	if ( is_array( $data ) && $depth < 5 ) {
		foreach ( array_slice( $data, 0, 200, true ) as $key => $value ) {
			$key = is_int( $key ) ? $key : substr( sanitize_key( (string) $key ), 0, 60 );
			if ( in_array( $key, array( 'logo', 'signature_image' ), true ) ) {
				$out[ $key ] = Pictures\clean( $value );
			} elseif ( in_array( $key, array( 'logo_svg', 'signature_svg' ), true ) ) {
				$out[ $key ] = Events\clean_vector( $value );
			} elseif ( 'signature' === $key ) {
				$out[ $key ] = Events\clean_vector( $value );
			} elseif ( is_array( $value ) ) {
				$out[ $key ] = clean_data( $value, $depth + 1 );
			} elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
				$out[ $key ] = $value;
			} else {
				$out[ $key ] = substr( sanitize_textarea_field( (string) $value ), 0, 5000 );
			}
		}
	}

	return 0 === $depth ? (string) wp_json_encode( $out ) : $out;
}

/**
 * The settings: the values go through the settings' own cleaning, the signature through its own, and the pepper only when
 * nothing checked by it is on this site yet.
 *
 * @param array<string, mixed> $settings    The bundle's settings.
 * @param bool                 $had_documents Whether this site already had certificates with a document hash (before the import).
 * @param array<string, mixed> $summary     Counts, filled.
 */
function import_settings( array $settings, $had_documents, array &$summary ) {
	$values = (array) ( $settings['values'] ?? array() );
	unset( $values['slug'] );
	Settings\update( array_intersect_key( $values, Settings\defaults() ) );
	$signature = $settings['signature'] ?? null;
	// A site that does not let drawings or pictures in keeps its own signature: leaving it out must not clear it.
	if ( Restrictions\allows( 'vectors' ) && Restrictions\allows( 'pictures' ) ) {
		Admin\save_signature_choice(
			array(
				'signature_json'       => is_array( $signature ) ? (string) wp_json_encode( $signature ) : '',
				'signature_attachment' => (string) ( $settings['signature_image'] ?? '' ),
			)
		);
	}
	if ( is_array( $settings['days'] ?? null ) ) {
		Days\save( $settings['days'] );
	}
	$pepper = (string) ( $settings['pepper'] ?? '' );
	$mine   = (string) get_option( Documents\PEPPER_OPTION, '' );
	if ( '' !== $pepper && $pepper !== $mine ) {
		if ( $had_documents ) {
			$summary['conflicts'][] = __( 'This site has certificates checked with its own secret key, so the one in the file was not used: document numbers of the imported certificates will not be matched here.', 'events-certificates' );
		} else {
			update_option( Documents\PEPPER_OPTION, substr( preg_replace( '/[^\x21-\x7E]/', '', $pepper ), 0, 128 ), false );
		}
	}
	++$summary['settings']['updated'];
}
