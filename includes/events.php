<?php
/**
 * Events of the plugin's own: a meetup, a course, a WordCamp without CampTix.
 * Each one has its participants (includes/people.php) and issues their
 * certificates; GatherPress events can be linked to one. docs/decisions/0015.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Post type.
 */
const POST_TYPE = 'evcert_event';

/**
 * The certificates' context for these events (certificates.context).
 */
const CONTEXT = 'event';

/**
 * Meta keys of an event, by field.
 */
const META = array(
	'start'           => '_evcert_start',
	'end'             => '_evcert_end',
	'location'        => '_evcert_location',
	'link'            => '_evcert_link',
	'hours'           => '_evcert_hours',
	'signer_name'     => '_evcert_signer_name',
	'signer_role'     => '_evcert_signer_role',
	'source'          => '_evcert_source',
	'source_ref'      => '_evcert_source_ref',
	'community'       => '_evcert_community',
	'speakers'        => '_evcert_speakers',
	'venue'           => '_evcert_venue',
	'city'            => '_evcert_city',
	'show_hours'      => '_evcert_show_hours',
	// Pictures of this event, as inline images (a data URI): the community's logo and the signature the PDF shows.
	'logo'            => '_evcert_logo',
	'signature_image' => '_evcert_signature_image',
	// The same pictures drawn as paths (JSON: vw, vh, paths), like the design editor's SVG component.
	'logo_svg'        => '_evcert_logo_svg',
	'signature_svg'   => '_evcert_signature_svg',
	// Hours by role, as JSON ({"organizer":"40"}); get() gives them as an array.
	'hours_by_role'   => '_evcert_hours_by_role',
);

/**
 * The fields that are pictures, kept when an update does not send them.
 */
const PICTURES = array( 'logo', 'logo_svg', 'signature_image', 'signature_svg' );

/**
 * Hooks.
 */
function bootstrap() {
	add_action( 'init', __NAMESPACE__ . '\register_post_type' );
}

/**
 * Registers the post type: not public, managed on the plugin's own screens.
 */
function register_post_type() {
	\register_post_type(
		POST_TYPE,
		array(
			'labels'          => array(
				'name'          => __( 'Events', 'events-certificates' ),
				'singular_name' => __( 'Event', 'events-certificates' ),
			),
			'public'          => false,
			'show_ui'         => false,
			'show_in_rest'    => false,
			'supports'        => array( 'title' ),
			'capability_type' => POST_TYPE,
			'map_meta_cap'    => false,
			'capabilities'    => array(
				'edit_post'          => \EventsCertificate\Admin\CAPABILITY,
				'read_post'          => \EventsCertificate\Admin\CAPABILITY,
				'delete_post'        => \EventsCertificate\Admin\CAPABILITY,
				'edit_posts'         => \EventsCertificate\Admin\CAPABILITY,
				'edit_others_posts'  => \EventsCertificate\Admin\CAPABILITY,
				'publish_posts'      => \EventsCertificate\Admin\CAPABILITY,
				'read_private_posts' => \EventsCertificate\Admin\CAPABILITY,
				'delete_posts'       => \EventsCertificate\Admin\CAPABILITY,
				'create_posts'       => \EventsCertificate\Admin\CAPABILITY,
			),
		)
	);
}

/**
 * An event's fields, or null.
 *
 * @param int  $id           Post ID.
 * @param bool $with_trashed Whether an event in the Trash is returned too (the Trash's list).
 * @return array<string, mixed>|null
 *         start and end in the site's time zone, "Y-m-d H:i"; hours_by_role: role => hours (an array).
 */
function get( $id, $with_trashed = false ) {
	$post = get_post( (int) $id );
	if ( ! $post || POST_TYPE !== $post->post_type || ( 'trash' === $post->post_status && ! $with_trashed ) ) {
		return null;
	}
	$event = array(
		'id'    => (int) $post->ID,
		'title' => html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ),
	);
	foreach ( META as $field => $key ) {
		$event[ $field ] = (string) get_post_meta( $post->ID, $key, true );
	}
	// The workload shows unless the event says not to (events made before this field have none).
	$event['show_hours']    = '0' === $event['show_hours'] ? '0' : '1';
	$by_role                = json_decode( (string) $event['hours_by_role'], true );
	$event['hours_by_role'] = is_array( $by_role ) ? $by_role : array();

	return $event;
}

/**
 * Cleans an event's fields.
 *
 * @param array<string, mixed> $data    Raw fields.
 * @param bool                 $lenient Whether nothing is required, as in a form (a name may be empty, a start is now, an end that is
 *                                      not after the start is two hours later), not an import or an agent.
 * @return array<string, string>|\WP_Error
 */
function clean( array $data, $lenient = false ) {
	$title = sanitize_text_field( (string) ( $data['title'] ?? '' ) );
	if ( '' === $title && ! $lenient ) {
		return new \WP_Error( 'evcert_missing_title', __( 'The event needs a name.', 'events-certificates' ) );
	}
	$clean = array( 'title' => $title );
	foreach ( array( 'start', 'end' ) as $field ) {
		$value           = str_replace( 'T', ' ', sanitize_text_field( (string) ( $data[ $field ] ?? '' ) ) );
		$time            = '' !== $value ? date_create_immutable_from_format( 'Y-m-d H:i', substr( $value, 0, 16 ), wp_timezone() ) : false;
		$clean[ $field ] = $time ? $time->format( 'Y-m-d H:i' ) : '';
	}
	if ( '' === $clean['start'] ) {
		if ( ! $lenient ) {
			return new \WP_Error( 'evcert_missing_start', __( 'The event needs a start date.', 'events-certificates' ) );
		}
		// A form asks for nothing: no start is now.
		$clean['start'] = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d H:i' );
	}
	if ( '' === $clean['end'] || $clean['end'] < $clean['start'] ) {
		// A form that gives an end that is not after the start gets the usual two hours; the rest, an event that ends as it starts.
		$start        = date_create_immutable_from_format( 'Y-m-d H:i', $clean['start'], wp_timezone() );
		$clean['end'] = $lenient && $start ? $start->modify( '+2 hours' )->format( 'Y-m-d H:i' ) : $clean['start'];
	}
	$clean['location'] = sanitize_text_field( (string) ( $data['location'] ?? '' ) );
	$clean['link']     = esc_url_raw( (string) ( $data['link'] ?? '' ), array( 'http', 'https' ) );
	$hours             = str_replace( ',', '.', (string) ( $data['hours'] ?? '' ) );
	$clean['hours']    = is_numeric( $hours ) && (float) $hours >= 0 && (float) $hours < 1000 ? (string) round( (float) $hours, 2 ) : '';
	foreach ( array( 'community', 'speakers', 'venue', 'city' ) as $field ) {
		$clean[ $field ] = sanitize_text_field( (string) ( $data[ $field ] ?? '' ) );
	}
	$clean['hours_by_role'] = (string) wp_json_encode( hours_by_role_from( $data['hours_by_role'] ?? array() ) );
	$clean['show_hours']    = isset( $data['show_hours'] ) && ! filter_var( $data['show_hours'], FILTER_VALIDATE_BOOLEAN ) ? '0' : '1';
	// The place a certificate shows: the venue and the city, unless a place was given.
	if ( '' === $clean['location'] ) {
		$clean['location'] = implode( ', ', array_filter( array( $clean['venue'], $clean['city'] ), 'strlen' ) );
	}
	$clean['logo']            = \EventsCertificate\Pictures\clean( $data['logo'] ?? '' );
	$clean['signature_image'] = \EventsCertificate\Pictures\clean( $data['signature_image'] ?? '' );
	$clean['logo_svg']        = clean_vector( $data['logo_svg'] ?? '' );
	$clean['signature_svg']   = clean_vector( $data['signature_svg'] ?? '' );
	$clean['signer_name']     = sanitize_text_field( (string) ( $data['signer_name'] ?? '' ) );
	$clean['signer_role']     = sanitize_text_field( (string) ( $data['signer_role'] ?? '' ) );
	$clean['source']          = in_array( $data['source'] ?? '', array( 'manual', 'gatherpress' ), true ) ? (string) $data['source'] : 'manual';
	$clean['source_ref']      = (string) absint( $data['source_ref'] ?? 0 );

	return $clean;
}

/**
 * A drawing made of paths (JSON: vw, vh and paths with d, fill and rule), cleaned, as JSON; '' when there is none.
 *
 * @param mixed $value    JSON, or the decoded drawing.
 * @param int   $max_data Most characters of a path's data (0: the limit of the pictures of an event and the signature).
 * @return string
 */
function clean_vector( $value, $max_data = 0 ) {
	$drawing = \EventsCertificate\Vendor\Camaleaun\Draw\Paths::drawing( $value, $max_data );

	return $drawing ? (string) wp_json_encode( $drawing ) : '';
}

/**
 * The size a drawing prefers to be shown at (`w` and `h`, beside its viewBox): both numbers, positive and within the viewBox limit,
 * and not the viewBox itself (then there is nothing to say). Empty when there is none.
 *
 * @param array<string, mixed> $drawing The drawing given.
 * @param float                $vw      Its viewBox width.
 * @param float                $vh      Its viewBox height.
 * @return array<string, float>
 */
function preferred_size( array $drawing, $vw, $vh ) {
	$w = (float) ( $drawing['w'] ?? 0 );
	$h = (float) ( $drawing['h'] ?? 0 );
	if ( $w <= 0 || $h <= 0 || $w > \EventsCertificate\Signer\SVG_MAX_VIEWBOX || $h > \EventsCertificate\Signer\SVG_MAX_VIEWBOX || ( $w === $vw && $h === $vh ) ) {
		return array();
	}

	return array(
		'w' => $w,
		'h' => $h,
	);
}

/**
 * Creates or updates an event.
 *
 * @param array<string, mixed> $data Fields (title, start, end, location, link, hours, signer_name, signer_role, source, source_ref).
 * @param int                  $id   Event to update, 0 to create.
 * @param bool                 $lenient Whether nothing is required (see clean()).
 * @return int|\WP_Error Event ID.
 */
function save( array $data, $id = 0, $lenient = false ) {
	$clean = clean( $data, $lenient );
	if ( is_wp_error( $clean ) ) {
		return $clean;
	}
	if ( $id && ! get( $id ) ) {
		return new \WP_Error( 'evcert_invalid_event_id', __( 'That event no longer exists.', 'events-certificates' ) );
	}
	$post = array(
		'post_type'   => POST_TYPE,
		'post_status' => 'publish',
		'post_title'  => $clean['title'],
	);
	if ( $id ) {
		$post['ID'] = (int) $id;
	}
	$was = (bool) $id;
	$id  = $id ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	foreach ( META as $field => $key ) {
		// The pictures are set on the Generator: a form that does not send them (the Events screen) keeps them; an empty value clears them.
		if ( $was && in_array( $field, PICTURES, true ) && ! array_key_exists( $field, $data ) ) {
			continue;
		}
		update_post_meta( (int) $id, $key, wp_slash( $clean[ $field ] ) );
	}

	return (int) $id;
}

/**
/**
 * The hours of each role, cleaned: valid roles with a number of hours above zero.
 *
 * @param mixed $given role => hours, or its JSON.
 * @return array<string, string>
 */
function hours_by_role_from( $given ) {
	$given = is_string( $given ) ? json_decode( $given, true ) : $given;
	$clean = array();
	foreach ( is_array( $given ) ? $given : array() as $role => $value ) {
		$role  = sanitize_key( (string) $role );
		$hours = str_replace( ',', '.', trim( (string) $value ) );
		if ( isset( \EventsCertificate\Designs\role_labels()[ $role ] ) && is_numeric( $hours ) && (float) $hours > 0 && (float) $hours < 1000 ) {
			$clean[ $role ] = (string) round( (float) $hours, 2 );
		}
	}

	return $clean;
}

/**
 * Hours of an event: the ones set for the role if it has its own (an organizer's
 * 40 hours, a volunteer's 8), else the event's, else from its start to its end
 * (none for a whole-day event without times).
 *
 * @param array<string, mixed> $event Event.
 * @param string               $role  Role ('' for the event's own).
 * @return float|null
 */
function hours( array $event, $role = '' ) {
	if ( '0' === ( $event['show_hours'] ?? '1' ) ) {
		return null;
	}
	if ( '' !== $role && ! empty( $event['hours_by_role'][ $role ] ) ) {
		return (float) $event['hours_by_role'][ $role ];
	}
	if ( '' !== $event['hours'] ) {
		return (float) $event['hours'];
	}
	$start = strtotime( $event['start'] );
	$end   = strtotime( $event['end'] );
	if ( ! $start || ! $end || $end <= $start ) {
		return null;
	}

	return round( ( $end - $start ) / HOUR_IN_SECONDS, 2 );
}

/**
 * A time of an event as UTC "Y-m-d H:i:s" (certificates.event_start/end).
 *
 * @param string $local "Y-m-d H:i" in the site's time zone.
 * @return string|null
 */
function utc( $local ) {
	$time = '' !== $local ? date_create_immutable_from_format( 'Y-m-d H:i', $local, wp_timezone() ) : false;

	return $time ? $time->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) : null;
}

/**
 * The event a certificate belongs to (issued from one of these events).
 *
 * @param array<string, mixed> $cert Certificate.
 * @return array<string, mixed>|null
 */
function of_certificate( array $cert ) {
	return CONTEXT === ( $cert['context'] ?? '' ) && ! empty( $cert['event_id'] ) ? get( (int) $cert['event_id'] ) : null;
}

/**
 * Events, newest first.
 *
 * @param array<string, mixed> $args get_posts() args to add.
 * @return \WP_Post[]
 */
function all( array $args = array() ) {
	return get_posts(
		$args + array(
			'post_type'   => POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'meta_value',
			'meta_key'    => META['start'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- sorting the plugin's own events.
			'order'       => 'DESC',
		)
	);
}

/**
 * Moves an event to the Trash. Its participants stay (their certificates
 * keep pointing to it), and come back with it.
 *
 * @param int $id Event ID.
 * @return bool
 */
function trash( $id ) {
	return get( $id ) ? (bool) wp_trash_post( (int) $id ) : false;
}

/**
 * Brings an event back from the Trash.
 *
 * @param int $id Event ID.
 * @return bool
 */
function untrash( $id ) {
	if ( ! get( $id, true ) || 'trash' !== get_post_status( (int) $id ) ) {
		return false;
	}
	wp_untrash_post( (int) $id );

	// Published again, as it was (WordPress brings a post back as a draft).
	return (bool) wp_update_post(
		array(
			'ID'          => (int) $id,
			'post_status' => 'publish',
		)
	);
}

/**
 * Deletes an event that is in the Trash for good. Its participants and their certificates are not touched
 * (a certificate stays valid for who has it).
 *
 * @param int $id Event ID.
 * @return bool
 */
function delete( $id ) {
	return get( $id, true ) && 'trash' === get_post_status( (int) $id ) && (bool) wp_delete_post( (int) $id, true );
}
