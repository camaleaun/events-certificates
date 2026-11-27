<?php
/**
 * CSV: the events, the participants and the certificates as a table for a spreadsheet, and events and participants read from one
 * with the columns mapped to fields, like WooCommerce's product importer. A CSV is one table; the JSON bundle (bundle.php) is
 * for moving everything. What is read from a CSV becomes a small bundle and goes through the same import, so it is cleaned and
 * matched the same way.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Csv;

use EventsCertificate\Certificates;
use EventsCertificate\Events;
use EventsCertificate\People;

defined( 'ABSPATH' ) || exit;

/**
 * What may be read: rows and the size of the file.
 */
const MAX_ROWS  = 20000;
const MAX_BYTES = 5242880;

/**
 * What a CSV can be of.
 *
 * @return string[]
 */
function kinds() {
	return array( 'people', 'events' );
}

/**
 * The fields a CSV of a kind can fill, with the headers that mean each (lower case, any language the plugin knows).
 *
 * @param string $kind people|events.
 * @return array<string, array{0: string, 1: string[], 2: bool}> Field => label, aliases, required.
 */
function fields( $kind ) {
	if ( 'events' === $kind ) {
		return array(
			'title'       => array( __( 'Name', 'events-certificates' ), array( 'title', 'name', 'event', 'nome', 'evento', 'título', 'titulo' ), true ),
			'start'       => array( __( 'Start', 'events-certificates' ), array( 'start', 'início', 'inicio', 'date', 'data' ), true ),
			'end'         => array( __( 'End', 'events-certificates' ), array( 'end', 'fim', 'término', 'termino' ), false ),
			'location'    => array( __( 'Place', 'events-certificates' ), array( 'location', 'local', 'place' ), false ),
			'venue'       => array( __( 'Venue', 'events-certificates' ), array( 'venue', 'espaço', 'espaco' ), false ),
			'city'        => array( __( 'City', 'events-certificates' ), array( 'city', 'cidade' ), false ),
			'community'   => array( __( 'Community', 'events-certificates' ), array( 'community', 'comunidade' ), false ),
			'speakers'    => array( __( 'Speakers', 'events-certificates' ), array( 'speakers', 'palestrantes' ), false ),
			'link'        => array( __( 'Link', 'events-certificates' ), array( 'link', 'url' ), false ),
			'hours'       => array( __( 'Workload (hours)', 'events-certificates' ), array( 'hours', 'workload', 'carga horária', 'carga horaria', 'horas' ), false ),
			'signer_name' => array( __( 'Signer', 'events-certificates' ), array( 'signer_name', 'signer', 'assina', 'assinatura' ), false ),
			'signer_role' => array( __( 'Signer’s role', 'events-certificates' ), array( 'signer_role', 'função de quem assina', 'funcao de quem assina' ), false ),
		);
	}

	return array(
		'name'           => array( __( 'Name', 'events-certificates' ), array( 'name', 'nome', 'full name', 'nome completo', 'participante', 'participant', 'attendee' ), true ),
		'email'          => array( __( 'E-mail', 'events-certificates' ), array( 'email', 'e-mail', 'mail' ), false ),
		'wporg_username' => array( __( 'WordPress.org username', 'events-certificates' ), array( 'wporg_username', 'username', 'usuário', 'usuario', 'wordpress.org' ), false ),
		'status'         => array( __( 'Status', 'events-certificates' ), array( 'status', 'situação', 'situacao', 'presença', 'presenca' ), false ),
		'roles'          => array( __( 'Roles', 'events-certificates' ), array( 'roles', 'role', 'papel', 'papéis', 'papeis', 'função', 'funcao' ), false ),
		'source_ref'     => array( __( 'Record ID', 'events-certificates' ), array( 'source_ref', 'id', 'registro' ), false ),
		'event'          => array( __( 'Event', 'events-certificates' ), array( 'event', 'evento' ), false ),
	);
}

/**
 * The field a header most likely means, by its name.
 *
 * @param string $header Header.
 * @param string $kind   people|events.
 * @return string Field, or ''.
 */
function guess( $header, $kind ) {
	$header = strtolower( trim( remove_accents( (string) $header ) ) );
	foreach ( fields( $kind ) as $field => $info ) {
		foreach ( array_merge( array( $field ), $info[1] ) as $alias ) {
			if ( strtolower( remove_accents( $alias ) ) === $header ) {
				return $field;
			}
		}
	}

	return '';
}

/**
 * Reads a CSV file: the first row is the headers. The delimiter (comma, semicolon or tab) is found from the first line.
 *
 * @param string $path File.
 * @return array{headers: string[], rows: array<int, string[]>}|\WP_Error
 */
function read( $path ) {
	if ( ! is_readable( $path ) || (int) filesize( $path ) > MAX_BYTES ) {
		return new \WP_Error( 'evcert_csv_size', __( 'That file is too big or cannot be read.', 'events-certificates' ) );
	}
	$text = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- an upload being read.
	$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text );
	if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
		$text = (string) mb_convert_encoding( $text, 'UTF-8', 'Windows-1252' );
	}
	$first = (string) strtok( $text, "\n" );
	$delim = ',';
	foreach ( array( ';', "\t" ) as $try ) {
		if ( substr_count( $first, $try ) > substr_count( $first, $delim ) ) {
			$delim = $try;
		}
	}
	$lines  = array();
	$stream = fopen( 'php://memory', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- an in-memory stream.
	fwrite( $stream, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- an in-memory stream.
	rewind( $stream );
	$taken = 0;
	while ( $taken <= MAX_ROWS ) {
		$row = fgetcsv( $stream, 0, $delim, '"', '\\' );
		if ( false === $row ) {
			break;
		}
		++$taken;
		if ( array( null ) === $row ) {
			continue;
		}
		$lines[] = array_map(
			static function ( $cell ) {
				return trim( (string) $cell );
			},
			$row
		);
	}
	fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- an in-memory stream.
	if ( count( $lines ) < 2 ) {
		return new \WP_Error( 'evcert_csv_empty', __( 'That CSV has no rows: the first row is the column names, then one row for each.', 'events-certificates' ) );
	}

	return array(
		'headers' => array_shift( $lines ),
		'rows'    => $lines,
	);
}

/**
 * A small bundle from a CSV and its mapping, to import as any other.
 *
 * @param string                                               $kind    people|events.
 * @param array{headers: string[], rows: array<int, string[]>} $csv     The file read.
 * @param array<int, string>                                   $mapping Column index => field ('' for a column left out).
 * @param int                                                  $event   The event the participants are of when no column says (0: the column).
 * @return array<string, mixed>
 */
function to_bundle( $kind, array $csv, array $mapping, $event = 0 ) {
	$bundle = array(
		'$schema'  => \EventsCertificate\Bundle\SCHEMA,
		'sections' => 'events' === $kind ? array( 'events' ) : array( 'events', 'people' ),
		'events'   => array(),
		'people'   => array(),
	);
	$known  = array_keys( fields( $kind ) );
	$keys   = array();
	foreach ( $csv['rows'] as $n => $row ) {
		$item = array();
		foreach ( $mapping as $column => $field ) {
			if ( in_array( $field, $known, true ) && isset( $row[ (int) $column ] ) && '' !== $row[ (int) $column ] ) {
				$item[ $field ] = $row[ (int) $column ];
			}
		}
		if ( 'events' === $kind ) {
			$bundle['events'][] = array( 'key' => $n + 1 ) + $item;
			continue;
		}
		$title = (string) ( $item['event'] ?? '' );
		unset( $item['event'] );
		$target = $event;
		if ( ! $target && '' !== $title ) {
			foreach ( Events\all() as $post ) {
				if ( strtolower( $post->post_title ) === strtolower( $title ) ) {
					$target = (int) $post->ID;
					break;
				}
			}
		}
		if ( ! $target ) {
			continue; // A participant of no event this site has.
		}
		if ( ! isset( $keys[ $target ] ) ) {
			$keys[ $target ]    = true;
			$bundle['events'][] = array( 'key' => $target ) + (array) Events\get( $target );
		}
		$item['roles']      = isset( $item['roles'] ) ? array_fill_keys( People\roles_from( (string) $item['roles'] ), '' ) : array();
		$bundle['people'][] = array(
			'key'   => $n + 1,
			'event' => $target,
		) + $item;
	}

	return $bundle;
}

/**
 * The rows of a table to export.
 *
 * @param string $section events|people|certificates.
 * @param int    $event   Only this event (0 for all).
 * @return array{headers: string[], rows: array<int, array<int, string>>}
 */
function export_rows( $section, $event = 0 ) {
	$rows = array();
	if ( 'events' === $section ) {
		$headers = array( 'title', 'start', 'end', 'location', 'venue', 'city', 'community', 'speakers', 'link', 'hours', 'signer_name', 'signer_role' );
		foreach ( Events\all() as $post ) {
			if ( $event && (int) $post->ID !== (int) $event ) {
				continue;
			}
			$item   = Events\get( (int) $post->ID );
			$rows[] = array_map(
				static function ( $field ) use ( $item ) {
					return (string) $item[ $field ];
				},
				$headers
			);
		}
	} elseif ( 'people' === $section ) {
		$headers = array( 'event', 'name', 'email', 'wporg_username', 'status', 'roles', 'source_ref' );
		foreach ( Events\all() as $post ) {
			if ( $event && (int) $post->ID !== (int) $event ) {
				continue;
			}
			foreach ( People\query( (int) $post->ID )['rows'] as $person ) {
				$rows[] = array( $post->post_title, $person['name'], $person['email'], $person['wporg_username'], $person['status'], implode( ';', array_keys( (array) $person['roles'] ) ), $person['source_ref'] );
			}
		}
	} else {
		$headers = array( 'code', 'status', 'name', 'email', 'role', 'event', 'event_start', 'hours', 'issued_at', 'revoked_reason' );
		foreach ( Certificates\query(
			array(
				'event_id' => $event,
				'per_page' => 500,
			)
		)['rows'] as $cert ) {
			$rows[] = array( $cert['code'], $cert['status'], $cert['name'], $cert['email'], $cert['role'], $cert['event_title'], (string) $cert['event_start'], (string) $cert['hours'], (string) $cert['issued_at'], $cert['revoked_reason'] );
		}
	}

	return array(
		'headers' => $headers,
		'rows'    => $rows,
	);
}

/**
 * A cell that a spreadsheet will not take for a formula.
 *
 * @param string $cell Value.
 * @return string
 */
function safe_cell( $cell ) {
	$cell = (string) $cell;

	return '' !== $cell && false !== strpos( "=+-@\t\r", $cell[0] ) ? "'" . $cell : $cell;
}

/**
 * The CSV text of a table (UTF-8 with a mark, so a spreadsheet reads the accents).
 *
 * @param array{headers: string[], rows: array<int, array<int, string>>} $table The table.
 * @return string
 */
function render( array $table ) {
	$stream = fopen( 'php://memory', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- an in-memory stream.
	fputcsv( $stream, $table['headers'], ',', '"', '\\' );
	foreach ( $table['rows'] as $row ) {
		fputcsv( $stream, array_map( __NAMESPACE__ . '\safe_cell', $row ), ',', '"', '\\' );
	}
	rewind( $stream );
	$text = (string) stream_get_contents( $stream );
	fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- an in-memory stream.

	return "\xEF\xBB\xBF" . $text;
}
