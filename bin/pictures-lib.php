<?php
/**
 * Moves the pictures an older version kept as data URIs into the Media Library (docs/decisions/0044): events, the signature of Settings,
 * the certificates already issued and the templates. Functions, with no output, so they are tested (tests/php/MovePicturesTest.php);
 * bin/move-pictures-to-media.php is the command that uses them.
 *
 * Not shipped (bin/ is export-ignored).
 *
 * @package EventsCertificate
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery -- a command run by the site's owner.

/**
 * The raster picture a data URI holds: its kind, extension and bytes; null when it is not one the plugin takes.
 *
 * @param mixed $value A value.
 * @return array{mime: string, ext: string, bytes: string, sha: string}|null
 */
function evcert_pictures_decode( $value ) {
	if ( ! is_string( $value ) || ! preg_match( '#^data:image/(png|jpe?g|gif|webp);base64,([A-Za-z0-9+/=]+)$#', trim( $value ), $m ) ) {
		return null;
	}
	$bytes = base64_decode( $m[2], true );
	if ( false === $bytes || '' === $bytes ) {
		return null;
	}
	$kind = 'jpeg' === $m[1] ? 'jpg' : $m[1];

	return array(
		'mime'  => 'image/' . ( 'jpg' === $kind ? 'jpeg' : $kind ),
		'ext'   => $kind,
		'bytes' => $bytes,
		'sha'   => hash( 'sha256', $bytes ),
	);
}

/**
 * The attachment for a picture: one already made from the same bytes (its SHA-256 is in the attachment), else a new one. With
 * `$apply` false nothing is written and 0 is given.
 *
 * @param array<string, string>   $picture evcert_pictures_decode().
 * @param string                  $title   The title of a new attachment.
 * @param bool                    $apply   Whether to write.
 * @param array<string, int>      $made    sha256 => attachment, kept between calls so a picture is made once.
 * @return int Attachment ID, or 0.
 */
function evcert_pictures_attachment( array $picture, $title, $apply, array &$made ) {
	if ( isset( $made[ $picture['sha'] ] ) ) {
		return $made[ $picture['sha'] ];
	}
	$found = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'meta_key'    => '_evcert_asset_sha256',
			'meta_value'  => $picture['sha'],
			'fields'      => 'ids',
		)
	);
	if ( $found ) {
		return $made[ $picture['sha'] ] = (int) $found[0];
	}
	if ( ! $apply ) {
		return $made[ $picture['sha'] ] = 0;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = wp_tempnam( 'evcert-picture' );
	file_put_contents( $tmp, $picture['bytes'] );
	$id = media_handle_sideload(
		array(
			'name'     => sanitize_file_name( $title ) . '.' . $picture['ext'],
			'tmp_name' => $tmp,
		),
		0,
		null,
		array( 'post_title' => $title )
	);
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return $made[ $picture['sha'] ] = 0;
	}
	update_post_meta( $id, '_evcert_asset_sha256', $picture['sha'] );

	return $made[ $picture['sha'] ] = (int) $id;
}

/**
 * Finds the data URIs the plugin kept and, with `$apply`, moves each to an attachment. Run again, nothing is made twice.
 *
 * @param bool $apply Whether to write (false: only count).
 * @return array<string, int> found (places), pictures (different files), moved (places changed), failed, snapshots (not touched).
 */
function evcert_pictures_move( $apply ) {
	global $wpdb;

	$made  = array();
	$count = array(
		'found'     => 0,
		'pictures'  => 0,
		'moved'     => 0,
		'failed'    => 0,
		'snapshots' => 0,
	);
	// One place: gives the attachment ID (as a string) the value is replaced by, or '' when it is left as it is.
	$take = static function ( $value, $title ) use ( &$count, &$made, $apply ) {
		$picture = evcert_pictures_decode( $value );
		if ( ! $picture ) {
			return '';
		}
		++$count['found'];
		$id = evcert_pictures_attachment( $picture, $title, $apply, $made );
		if ( $apply && ! $id ) {
			++$count['failed'];
			return '';
		}
		$count['pictures'] = count( $made );
		if ( $apply ) {
			++$count['moved'];
		}

		return $apply ? (string) $id : '';
	};

	// Events.
	foreach ( get_posts(
		array(
			'post_type'   => \EventsCertificate\Events\POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	) as $event_id ) {
		foreach ( array(
			'_evcert_logo'            => __( 'Logo', 'events-certificates' ),
			'_evcert_signature_image' => __( 'Signature', 'events-certificates' ),
		) as $key => $label ) {
			$new = $take( get_post_meta( $event_id, $key, true ), $label . ' – ' . get_the_title( $event_id ) );
			if ( '' !== $new ) {
				update_post_meta( $event_id, $key, $new );
			}
		}
	}

	// The signature of Settings.
	$new = $take( get_option( 'evcert_signature_image', '' ), __( 'Signature', 'events-certificates' ) );
	if ( '' !== $new ) {
		update_option( 'evcert_signature_attachment', (int) $new, false );
		delete_option( 'evcert_signature_image' );
	}

	// Certificates already issued (in batches).
	$table = \EventsCertificate\Certificates\table();
	$last  = 0;
	do {
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, data_json, event_title FROM %i WHERE id > %d AND data_json LIKE %s ORDER BY id ASC LIMIT 200', $table, $last, '%data:image%' ), ARRAY_A );
		foreach ( $rows as $row ) {
			$last = (int) $row['id'];
			$data = json_decode( (string) $row['data_json'], true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$changed = false;
			foreach ( array( 'logo', 'signature_image' ) as $key ) {
				$new = $take( $data[ $key ] ?? '', ( 'logo' === $key ? __( 'Logo', 'events-certificates' ) : __( 'Signature', 'events-certificates' ) ) . ' – ' . $row['event_title'] );
				if ( '' !== $new ) {
					$data[ $key ] = $new;
					$changed      = true;
				}
			}
			if ( $changed ) {
				$wpdb->update( $table, array( 'data_json' => (string) wp_json_encode( $data ) ), array( 'id' => (int) $row['id'] ) );
			}
		}
	} while ( count( $rows ) === 200 );

	// Templates: the pictures of image elements.
	foreach ( get_posts(
		array(
			'post_type'   => \EventsCertificate\Designs\POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
		)
	) as $post ) {
		$design = json_decode( (string) $post->post_content, true );
		if ( ! is_array( $design ) ) {
			continue;
		}
		$changed = false;
		foreach ( array( 'elements', 'back' ) as $where ) {
			$list = 'back' === $where ? ( $design['back']['elements'] ?? null ) : ( $design['elements'] ?? null );
			if ( ! is_array( $list ) ) {
				continue;
			}
			foreach ( $list as $i => $element ) {
				if ( is_array( $element ) && 'image' === ( $element['type'] ?? '' ) ) {
					$new = $take( $element['src'] ?? '', $post->post_title );
					if ( '' !== $new ) {
						$list[ $i ]['src']        = (string) wp_get_attachment_url( (int) $new );
						$list[ $i ]['attachment'] = (int) $new;
						$changed                  = true;
					}
				}
			}
			if ( 'back' === $where ) {
				$design['back']['elements'] = $list;
			} else {
				$design['elements'] = $list;
			}
		}
		if ( $changed ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => (string) wp_json_encode( $design ) ), array( 'ID' => $post->ID ) );
			clean_post_cache( $post->ID );
		}
	}

	// Snapshots keep a certificate's template as it was (they are kept by their hash): counted, not changed.
	$snap_table         = \EventsCertificate\Install\tables()['designs'];
	$count['snapshots'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE design LIKE %s', $snap_table, '%data:image%' ) );

	return $count;
}

/**
 * Turns an older bundle (JSON) into a new one: each data URI becomes `picture:<sha256>` and its bytes a file in `images/` of a ZIP.
 *
 * @param array<string, mixed> $bundle The file, decoded.
 * @return array{bundle: array<string, mixed>, files: array<string, string>} The new bundle and name in the ZIP => path of a temporary file.
 */
function evcert_pictures_convert_bundle( array $bundle ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$files  = array();
	$images = array();
	$swap   = static function ( $value ) use ( &$files, &$images ) {
		$picture = evcert_pictures_decode( $value );
		if ( ! $picture ) {
			return $value;
		}
		$ref = 'picture:' . $picture['sha'];
		if ( ! isset( $images[ $ref ] ) ) {
			$name = 'images/' . substr( $picture['sha'], 0, 12 ) . '.' . $picture['ext'];
			$tmp  = wp_tempnam( 'evcert-convert' );
			file_put_contents( $tmp, $picture['bytes'] );
			$files[ $name ] = $tmp;
			$images[ $ref ] = array(
				'ref'    => $ref,
				'url'    => '',
				'alt'    => '',
				'title'  => '',
				'file'   => $name,
				'size'   => strlen( $picture['bytes'] ),
				'sha256' => $picture['sha'],
			);
		}

		return $ref;
	};
	foreach ( (array) ( $bundle['events'] ?? array() ) as $i => $event ) {
		foreach ( array( 'logo', 'signature_image' ) as $key ) {
			if ( isset( $event[ $key ] ) ) {
				$bundle['events'][ $i ][ $key ] = $swap( $event[ $key ] );
			}
		}
	}
	foreach ( (array) ( $bundle['certificates'] ?? array() ) as $i => $cert ) {
		foreach ( array( 'logo', 'signature_image' ) as $key ) {
			if ( isset( $cert['data'][ $key ] ) ) {
				$bundle['certificates'][ $i ]['data'][ $key ] = $swap( $cert['data'][ $key ] );
			}
		}
	}
	if ( isset( $bundle['settings']['signature_image'] ) ) {
		$bundle['settings']['signature_image'] = $swap( $bundle['settings']['signature_image'] );
	}
	if ( $images ) {
		$bundle['assets']           = (array) ( $bundle['assets'] ?? array( 'fonts' => array() ) );
		$bundle['assets']['images'] = array_merge( (array) ( $bundle['assets']['images'] ?? array() ), array_values( $images ) );
	}

	return array(
		'bundle' => $bundle,
		'files'  => $files,
	);
}
