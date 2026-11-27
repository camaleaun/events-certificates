<?php
/**
 * Certificates: issued records, frozen when issued (docs/decisions/0001).
 *
 * A certificate keeps everything it shows (name, event, dates, role) and the
 * design version it was issued with; nothing reads live data afterwards. A
 * correction issues a new certificate and marks the old one "replaced", so a
 * code people already shared keeps answering.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Certificates;

use EventsCertificate\Code;
use EventsCertificate\Designs;
use EventsCertificate\Install;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Fields a caller may set when issuing.
 */
const FIELDS = array( 'context', 'event_id', 'group_id', 'name', 'email', 'wporg_username', 'role', 'details', 'attended', 'locale', 'event_title', 'event_start', 'event_end', 'event_tz', 'hours', 'source', 'source_ref', 'self_service', 'doc_type', 'doc_label', 'doc_masked', 'doc_hash' );

/**
 * The version of the code that draws certificates, kept with each one (bump it when a certificate would be drawn differently).
 */
const RENDERER_VERSION = '1';

/**
 * Fields that make a certificate what it is (part of its fingerprint).
 */
const FROZEN = array( 'code', 'name', 'role', 'details', 'event_title', 'event_start', 'event_end', 'event_tz', 'hours', 'design_hash' );

/**
 * The certificates table.
 *
 * @return string
 */
function table() {
	return Install\tables()['certificates'];
}

/**
 * A certificate by ID.
 *
 * @param int $id Certificate ID.
 * @return array<string, mixed>|null
 */
function get( $id ) {
	global $wpdb;

	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', table(), $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table; certificates are not cached.

	return $row ? $row : null;
}

/**
 * A certificate by its code. One in the Trash is not on record: it is left out, unless asked for (to keep a code unique).
 *
 * @param string $code       Code as typed or scanned.
 * @param bool   $with_trash Also the ones in the Trash.
 * @return array<string, mixed>|null
 */
function get_by_code( $code, $with_trash = false ) {
	global $wpdb;

	$code = Code\normalize( (string) $code );
	if ( '' === $code ) {
		return null;
	}
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE code = %s', table(), $code ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
	if ( $row && 'trash' === $row['status'] && ! $with_trash ) {
		return null;
	}

	return $row ? $row : null;
}

/**
 * The current certificate issued from a source record (a CampTix attendee,
 * a GatherPress RSVP…) in a role, ignoring replaced and revoked ones. The role is part of
 * the identity: a person who is a speaker and an organizer has a certificate of each from
 * the same record, and without the role one would stand for the other.
 *
 * @param string $source     Source, e.g. "camptix".
 * @param string $source_ref Source record, e.g. the attendee ID.
 * @param string $role       Role.
 * @return array<string, mixed>|null
 */
function find_by_source( $source, $source_ref, $role ) {
	global $wpdb;

	$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		$wpdb->prepare(
			"SELECT * FROM %i WHERE source = %s AND source_ref = %s AND role = %s AND status = 'issued' ORDER BY id DESC LIMIT 1",
			table(),
			$source,
			(string) $source_ref,
			(string) $role
		),
		ARRAY_A
	);

	return $row ? $row : null;
}

/**
 * The latest certificate issued from a source record in any state: what
 * tells that the one it had was revoked, which find_by_source() leaves out.
 *
 * @param string $source     Source, e.g. "camptix".
 * @param string $source_ref Source record, e.g. the attendee ID.
 * @param string $role       Role.
 * @return array<string, mixed>|null
 */
function latest_by_source( $source, $source_ref, $role ) {
	global $wpdb;

	$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		$wpdb->prepare(
			"SELECT * FROM %i WHERE source = %s AND source_ref = %s AND role = %s AND status <> 'trash' ORDER BY id DESC LIMIT 1",
			table(),
			$source,
			(string) $source_ref,
			(string) $role
		),
		ARRAY_A
	);

	return $row ? $row : null;
}

/**
 * The valid certificate a person already has for an event from another
 * source: the same e-mail and event name, issued by something else than
 * $other_than. With CampTix and GatherPress together, the ticket page and an
 * imported event can both reach the same person; the first one issued is
 * the certificate, and the other source shows it instead of making a second.
 *
 * The role counts: the same person's speaker certificate is not their attendee one.
 *
 * @param string $email      E-mail (empty: nothing is matched).
 * @param string $title      Event name.
 * @param string $other_than Source to leave out.
 * @param string $role       Role.
 * @return array<string, mixed>|null
 */
function find_for_person( $email, $title, $other_than, $role ) {
	global $wpdb;

	$email = strtolower( trim( (string) $email ) );
	$title = strtolower( trim( (string) $title ) );
	if ( '' === $email || '' === $title ) {
		return null;
	}
	$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		$wpdb->prepare(
			"SELECT * FROM %i WHERE LOWER(email) = %s AND LOWER(event_title) = %s AND source <> %s AND role = %s AND status = 'issued' ORDER BY id ASC LIMIT 1",
			table(),
			$email,
			$title,
			$other_than,
			(string) $role
		),
		ARRAY_A
	);

	return $row ? $row : null;
}

/**
 * Stores a design version (by content hash) and returns its hash.
 *
 * @param array<string, mixed> $design Design.
 * @return string
 */
function store_design( array $design ) {
	global $wpdb;

	$json = (string) wp_json_encode( $design );
	$hash = hash( 'sha256', $json );
	$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (hash, design, created_at) VALUES (%s, %s, %s)', Install\tables()['designs'], $hash, $json, current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table; INSERT IGNORE keeps one copy per version.

	return $hash;
}

/**
 * The design a certificate is drawn with: the current template for its role
 * (Designs\for_certificate(), the default one otherwise), so a
 * change to the design (a logo, a date format) shows on every certificate
 * (docs/decisions/0001). What the certificate says (name, event, hours,
 * signer, code) stays frozen in the row. The version it was issued with
 * (design_hash) is kept as history, and drawn only if the site design is gone.
 *
 * @param array<string, mixed> $cert Certificate.
 * @return array<string, mixed>
 */
function design( array $cert ) {
	global $wpdb;

	$current = Designs\get( Designs\for_certificate( $cert ) );
	if ( is_array( $current ) && isset( $current['elements'], $current['page'] ) ) {
		return $current;
	}
	$json   = $wpdb->get_var( $wpdb->prepare( 'SELECT design FROM %i WHERE hash = %s', Install\tables()['designs'], (string) ( $cert['design_hash'] ?? '' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
	$design = $json ? json_decode( $json, true ) : null;

	return is_array( $design ) ? \EventsCertificate\Render\page_in_points( $design ) : (array) $current;
}

/**
 * Fingerprint of what a certificate says, so a changed row can be noticed.
 *
 * @param array<string, mixed> $row Certificate.
 * @return string
 */
function fingerprint( array $row ) {
	$data = array();
	foreach ( FROZEN as $key ) {
		$data[ $key ] = isset( $row[ $key ] ) ? (string) $row[ $key ] : '';
	}
	$data['hours'] = hours_text( $data['hours'] );

	return hash( 'sha256', (string) wp_json_encode( $data ) );
}

/**
 * The hours as text, one way however they were read: "8.00" from a decimal column, 8 or 8.0 from a form or a bundle all
 * become "8", and 8.50 becomes "8.5". Always a dot, whatever the locale (a float cast follows it on PHP 7.4).
 *
 * @param mixed $hours Hours.
 * @return string Text, or '' when it is not a number.
 */
function hours_text( $hours ) {
	if ( ! is_numeric( $hours ) ) {
		return (string) $hours;
	}

	return rtrim( rtrim( sprintf( '%.2F', (float) $hours ), '0' ), '.' );
}

/**
 * Issues a certificate.
 *
 * @param array<string, mixed> $data Values from FIELDS ("name" is required) and "data", extra frozen values (location, signer…).
 * @return array<string, mixed>|WP_Error The certificate.
 */
function issue( array $data ) {
	global $wpdb;

	$row = array_intersect_key( $data, array_flip( FIELDS ) );
	if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
		$row['data_json'] = (string) wp_json_encode( $data['data'] );
	}

	$row['name'] = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) ( $row['name'] ?? '' ) ) ) );
	if ( '' === $row['name'] ) {
		return new WP_Error( 'evcert_missing_name', __( 'The name on the certificate is required.', 'events-certificates' ) );
	}
	if ( function_exists( 'mb_substr' ) ) {
		$row['name'] = mb_substr( $row['name'], 0, 190 );
	}

	$now                     = current_time( 'mysql', true );
	$row['status']           = 'issued';
	$row['locale']           = $row['locale'] ?? get_locale();
	$row['design_hash']      = store_design( Designs\get( Designs\for_certificate( $row ) ) );
	$row['issued_at']        = $now;
	$row['created_at']       = $now;
	$row['updated_at']       = $now;
	$row['renderer_version'] = RENDERER_VERSION;

	for ( $try = 0; $try < 5; $try++ ) {
		$row['code']        = Code\generate();
		$row['fingerprint'] = fingerprint( $row );
		if ( $wpdb->insert( table(), $row ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
			return get( (int) $wpdb->insert_id );
		}
		if ( ! get_by_code( $row['code'], true ) ) {
			break; // Failed for another reason than a code collision.
		}
	}

	return new WP_Error( 'evcert_issue_failed', __( 'The certificate could not be issued. Try again.', 'events-certificates' ) );
}

/**
 * Edits a certificate where it is (verification off, decision 0028): the same record and code, the fields changed,
 * nothing replaced. A valid certificate only; a name is required.
 *
 * @param array<string, mixed> $old     The certificate.
 * @param array<string, mixed> $changes Fields to change (name, hours, data…).
 * @return array<string, mixed>|WP_Error The certificate as it is now.
 */
function edit_in_place( array $old, array $changes ) {
	global $wpdb;

	if ( 'issued' !== ( $old['status'] ?? '' ) ) {
		return new WP_Error( 'evcert_not_current', __( 'Only a valid certificate can be corrected.', 'events-certificates' ) );
	}
	$row = array_intersect_key( $changes, array_flip( array( 'name', 'hours', 'email', 'event_title', 'event_start', 'event_end', 'event_tz', 'doc_type', 'doc_label', 'doc_masked', 'doc_hash' ) ) );
	if ( isset( $changes['data'] ) && is_array( $changes['data'] ) ) {
		$row['data_json'] = (string) wp_json_encode( $changes['data'] );
	}
	if ( array_key_exists( 'name', $row ) ) {
		$row['name'] = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $row['name'] ) ) );
		if ( '' === $row['name'] ) {
			return new WP_Error( 'evcert_missing_name', __( 'The name on the certificate is required.', 'events-certificates' ) );
		}
		if ( function_exists( 'mb_substr' ) ) {
			$row['name'] = mb_substr( $row['name'], 0, 190 );
		}
	}
	$row['updated_at']  = current_time( 'mysql', true );
	$row['fingerprint'] = fingerprint( array_merge( $old, $row ) );
	$wpdb->update( table(), $row, array( 'id' => (int) $old['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.

	return get( (int) $old['id'] );
}

/**
 * Corrects a certificate: issues a new one with the changes and marks the old
 * one replaced by it.
 *
 * @param array<string, mixed> $old     Certificate being corrected.
 * @param array<string, mixed> $changes Changed values from FIELDS.
 * @return array<string, mixed>|WP_Error The new certificate.
 */
function reissue( array $old, array $changes ) {
	global $wpdb;

	// Without verification there is no code to replace and nothing to revoke: a correction edits the certificate where it is.
	if ( ! \EventsCertificate\Environment\verification_enabled() ) {
		return edit_in_place( $old, $changes );
	}

	// Correcting a replaced or revoked one would leave two valid; a revoked certificate is final.
	if ( 'issued' !== ( $old['status'] ?? '' ) ) {
		return new WP_Error( 'evcert_not_current', __( 'Only a valid certificate can be corrected.', 'events-certificates' ) );
	}
	$data = array_merge( array_intersect_key( $old, array_flip( FIELDS ) ), $changes );
	// A correction does not change the role: that would be another certificate (revoke this one and issue the other).
	$data['role'] = (string) $old['role'];
	if ( ! isset( $changes['data'] ) ) {
		$kept         = json_decode( (string) ( $old['data_json'] ?? '' ), true );
		$data['data'] = is_array( $kept ) ? $kept : array();
	}
	$new = issue( $data );
	if ( is_wp_error( $new ) ) {
		return $new;
	}
	$replaced = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		table(),
		array(
			'status'      => 'replaced',
			'replaced_by' => (int) $new['id'],
			'updated_at'  => current_time( 'mysql', true ),
		),
		array(
			'id'     => (int) $old['id'],
			'status' => 'issued',
		)
	);
	if ( 1 !== $replaced ) {
		// The old one could not be replaced: do not leave two valid certificates.
		$wpdb->delete( table(), array( 'id' => (int) $new['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.

		return new WP_Error( 'evcert_reissue_failed', __( 'The certificate could not be corrected. Try again.', 'events-certificates' ) );
	}

	return $new;
}

/**
 * Revokes a certificate: its code then says it was revoked, with the reason.
 *
 * @param int    $id     Certificate ID.
 * @param string $reason Why (shown on the certificate page).
 * @return bool Whether it changed.
 */
function revoke( $id, $reason = '' ) {
	global $wpdb;

	return (bool) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		table(),
		array(
			'status'         => 'revoked',
			'revoked_reason' => sanitize_text_field( $reason ),
			'updated_at'     => current_time( 'mysql', true ),
		),
		array(
			'id'     => (int) $id,
			'status' => 'issued',
		)
	);
}

/**
 * Moves a certificate to the Trash: its code stops answering (as if there were none), nothing links to it, and it can come
 * back. The status it had is kept to go back to.
 *
 * @param int $id Certificate ID.
 * @return bool Whether it moved (drafts and what is already there stay).
 */
function trash( $id ) {
	global $wpdb;

	$cert = get( (int) $id );
	if ( ! $cert || ! in_array( $cert['status'], array( 'issued', 'replaced', 'revoked' ), true ) ) {
		return false;
	}
	$now   = current_time( 'mysql', true );
	$moved = (bool) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		table(),
		array(
			'status'       => 'trash',
			'trashed_from' => $cert['status'],
			'trashed_at'   => $now,
			'updated_at'   => $now,
		),
		array( 'id' => (int) $id )
	);
	if ( $moved ) {
		// The person's role stops pointing at it: they have none now (and Issue makes a new one).
		$wpdb->update( \EventsCertificate\Install\tables()['person_roles'], array( 'certificate_id' => 0 ), array( 'certificate_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
	}

	return $moved;
}

/**
 * Takes a certificate out of the Trash, back to the status it had. A valid one does not come back when a newer valid one
 * was issued to the same person in the same role meanwhile (there is never two).
 *
 * @param int $id Certificate ID.
 * @return true|\WP_Error
 */
function restore( $id ) {
	global $wpdb;

	$cert = get( (int) $id );
	if ( ! $cert || 'trash' !== $cert['status'] ) {
		return new \WP_Error( 'evcert_not_trashed', __( 'That certificate is not in the Trash.', 'events-certificates' ) );
	}
	$from = in_array( $cert['trashed_from'], array( 'issued', 'replaced', 'revoked' ), true ) ? (string) $cert['trashed_from'] : 'issued';
	if ( 'issued' === $from ) {
		$other = '' !== (string) $cert['source_ref'] ? find_by_source( (string) $cert['source'], (string) $cert['source_ref'], (string) $cert['role'] ) : null;
		$other = $other ? $other : find_for_person( (string) $cert['email'], (string) $cert['event_title'], (string) $cert['source'], (string) $cert['role'] );
		if ( $other && (int) $other['id'] !== (int) $cert['id'] ) {
			return new \WP_Error( 'evcert_newer_certificate', __( 'A newer certificate was issued to this person in this role, so this one is not restored.', 'events-certificates' ) );
		}
	}
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		table(),
		array(
			'status'       => $from,
			'trashed_from' => null,
			'trashed_at'   => null,
			'updated_at'   => current_time( 'mysql', true ),
		),
		array( 'id' => (int) $id )
	);
	// The role of the participant it was issued to points at it again, when it points at nothing.
	if ( \EventsCertificate\People\SOURCE === $cert['source'] && (int) $cert['source_ref'] > 0 && 'issued' === $from ) {
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
			\EventsCertificate\Install\tables()['person_roles'],
			array( 'certificate_id' => (int) $id ),
			array(
				'person_id'      => (int) $cert['source_ref'],
				'role'           => (string) $cert['role'],
				'certificate_id' => 0,
			)
		);
	}

	return true;
}

/**
 * Deletes a certificate that is in the Trash for good: its share images, the link of the person's role to it and the
 * "replaced by" of the one it replaced go with it. The design snapshots are shared and stay.
 *
 * @param int $id Certificate ID.
 * @return bool Whether it was deleted (only from the Trash).
 */
function delete_permanently( $id ) {
	global $wpdb;

	$cert = get( (int) $id );
	if ( ! $cert || 'trash' !== $cert['status'] ) {
		return false;
	}
	\EventsCertificate\ShareImage\delete_images( (string) $cert['code'] );
	$wpdb->update( \EventsCertificate\Install\tables()['person_roles'], array( 'certificate_id' => 0 ), array( 'certificate_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
	$wpdb->update( table(), array( 'replaced_by' => null ), array( 'replaced_by' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.

	return (bool) $wpdb->delete( table(), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
}

/**
 * Deletes everything in the Trash for good.
 *
 * @return int How many.
 */
function empty_trash() {
	global $wpdb;

	$n = 0;
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE status = %s', table(), 'trash' ) ) as $id ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		$n += delete_permanently( (int) $id ) ? 1 : 0;
	}

	return $n;
}

/**
 * Certificates (not drafts), searched and sorted: the Certificates screen and
 * the get-certificates ability.
 *
 * @param array<string, mixed> $args status (issued|replaced|revoked|trash: the Trash is only shown when asked for), search (name, e-mail or code),
 *                                   event_id (an event of the plugin's own), role, orderby (name|code|status|issued_at),
 *                                   order (asc|desc), per_page, page.
 * @return array{rows: array<int, array<string, mixed>>, total: int}
 */
function query( array $args = array() ) {
	global $wpdb;

	$trash    = 'trash' === ( $args['status'] ?? '' ) ? 1 : 0;
	$status   = in_array( $args['status'] ?? '', array( 'issued', 'replaced', 'revoked' ), true ) ? (string) $args['status'] : '';
	$search   = trim( (string) ( $args['search'] ?? '' ) );
	$like     = '' === $search ? '' : '%' . $wpdb->esc_like( $search ) . '%';
	$event    = absint( $args['event_id'] ?? 0 );
	$orderby  = in_array( $args['orderby'] ?? '', array( 'name', 'code', 'status', 'issued_at' ), true ) ? (string) $args['orderby'] : 'issued_at';
	$per_page = max( 1, min( 500, (int) ( $args['per_page'] ?? 20 ) ) );
	$offset   = $per_page * max( 0, (int) ( $args['page'] ?? 1 ) - 1 );
	$context  = $event ? \EventsCertificate\Events\CONTEXT : '';
	$role     = sanitize_key( (string) ( $args['role'] ?? '' ) );

	// Fixed SQL: empty filters match everything; the sort column is an identifier (%i), the order one of two strings.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- the plugin's own table, not cached.
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status <> 'draft' AND ( ( %d = 1 AND status = 'trash' ) OR ( %d = 0 AND status <> 'trash' ) ) AND ( %s = '' OR status = %s ) AND ( %s = '' OR name LIKE %s OR email LIKE %s OR code LIKE %s ) AND ( %d = 0 OR ( context = %s AND event_id = %d ) ) AND ( %s = '' OR role = %s )", table(), $trash, $trash, $status, $status, $like, $like, $like, $like, $event, $context, $event, $role, $role ) );
	if ( 'asc' === strtolower( (string) ( $args['order'] ?? '' ) ) ) {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE status <> 'draft' AND ( ( %d = 1 AND status = 'trash' ) OR ( %d = 0 AND status <> 'trash' ) ) AND ( %s = '' OR status = %s ) AND ( %s = '' OR name LIKE %s OR email LIKE %s OR code LIKE %s ) AND ( %d = 0 OR ( context = %s AND event_id = %d ) ) AND ( %s = '' OR role = %s ) ORDER BY %i ASC, id DESC LIMIT %d OFFSET %d", table(), $trash, $trash, $status, $status, $like, $like, $like, $like, $event, $context, $event, $role, $role, $orderby, $per_page, $offset ), ARRAY_A );
	} else {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE status <> 'draft' AND ( ( %d = 1 AND status = 'trash' ) OR ( %d = 0 AND status <> 'trash' ) ) AND ( %s = '' OR status = %s ) AND ( %s = '' OR name LIKE %s OR email LIKE %s OR code LIKE %s ) AND ( %d = 0 OR ( context = %s AND event_id = %d ) ) AND ( %s = '' OR role = %s ) ORDER BY %i DESC, id DESC LIMIT %d OFFSET %d", table(), $trash, $trash, $status, $status, $like, $like, $like, $like, $event, $context, $event, $role, $role, $orderby, $per_page, $offset ), ARRAY_A );
	}
	// phpcs:enable

	return array(
		'rows'  => (array) $rows,
		'total' => $total,
	);
}

/**
 * The public address of a certificate: its page, or with path segments a
 * language of it (/pt-br/) and its PDF (/pdf/), see Verify\page_url().
 *
 * @param string   $code     Code.
 * @param string[] $segments Path segments after the code.
 * @return string
 */
function url( $code, array $segments = array() ) {
	return home_url( user_trailingslashit( implode( '/', array_merge( array( \EventsCertificate\Settings\value( 'slug' ), $code ), $segments ) ) ) );
}
