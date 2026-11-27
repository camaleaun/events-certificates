<?php
/**
 * CSV export and import: tables for a spreadsheet, columns mapped to fields.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Bundle;
use EventsCertificate\Csv;
use EventsCertificate\People;

/**
 * What a CSV may hold and what becomes of it.
 */
class CsvTest extends Test_Case {

	/**
	 * A file with some text.
	 *
	 * @param string $text CSV.
	 * @return string Path.
	 */
	private function file( $text ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$path = wp_tempnam( 'list.csv' );
		file_put_contents( $path, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a temporary file.

		return $path;
	}

	/**
	 * The delimiter and the encoding are found; the first row is the headers.
	 */
	public function test_a_csv_is_read_with_its_delimiter_and_encoding() {
		$semi = Csv\read( $this->file( "\xEF\xBB\xBFNome;E-mail\nAna;ana@example.test\nBia;bia@example.test\n" ) );
		$this->assertSame( array( 'Nome', 'E-mail' ), $semi['headers'] );
		$this->assertCount( 2, $semi['rows'] );
		$this->assertSame( 'Ana', $semi['rows'][0][0] );

		$latin = Csv\read( $this->file( mb_convert_encoding( "name,email\nJos\u{e9},j@example.test\n", 'Windows-1252', 'UTF-8' ) ) );
		$this->assertSame( "Jos\u{e9}", $latin['rows'][0][0] );

		$this->assertSame( 'evcert_csv_empty', Csv\read( $this->file( "name\n" ) )->get_error_code() );
	}

	/**
	 * Headers are guessed by name, in English and in Portuguese.
	 */
	public function test_headers_are_guessed() {
		$this->assertSame( 'name', Csv\guess( 'Nome completo', 'people' ) );
		$this->assertSame( 'email', Csv\guess( 'E-mail', 'people' ) );
		$this->assertSame( 'roles', Csv\guess( 'Papéis', 'people' ) );
		$this->assertSame( 'title', Csv\guess( 'Evento', 'events' ) );
		$this->assertSame( 'start', Csv\guess( 'Início', 'events' ) );
		$this->assertSame( '', Csv\guess( 'Whatever', 'people' ) );
	}

	/**
	 * Participants of a CSV are imported as any other: to the event chosen, cleaned, matched, once.
	 */
	public function test_participants_from_a_csv_are_imported_once() {
		wp_set_current_user( 1 );
		$event = Fixtures::event( array( 'title' => 'A fictional CSV meetup' ) );
		$csv   = Csv\read( $this->file( "Nome,E-mail,Papéis,Status\nAna Lima,ana@example.test,speaker,attended\n<b>Bia</b> Souza,bia@example.test,,registered\n" ) );
		$map   = array( 'name', 'email', 'roles', 'status' );

		$bundle = Csv\to_bundle( 'people', $csv, $map, $event );
		$first  = Bundle\import( $bundle, $bundle['sections'] );
		$this->assertSame( 2, $first['people']['added'] );

		$rows = People\query( $event )['rows'];
		$this->assertCount( 2, $rows );
		$ana = People\query( $event, array( 'search' => 'Ana' ) )['rows'][0];
		$this->assertSame( 'attended', $ana['status'] );
		$this->assertArrayHasKey( 'speaker', $ana['roles'] );
		$names = wp_list_pluck( $rows, 'name' );
		$this->assertNotContains( '<b>Bia</b> Souza', $names, 'Markup is cleaned.' );

		$again = Bundle\import( $bundle, $bundle['sections'] );
		$this->assertSame( 0, $again['people']['added'] );
		$this->assertSame( 2, $again['people']['skipped'] );
	}

	/**
	 * Participants of an event named in a column; one of an event this site does not have is left out.
	 */
	public function test_the_event_can_come_from_a_column() {
		$event  = Fixtures::event( array( 'title' => 'The column meetup' ) );
		$csv    = Csv\read( $this->file( "Name,Evento\nCaio,The column meetup\nDora,An event nobody has\n" ) );
		$bundle = Csv\to_bundle( 'people', $csv, array( 'name', 'event' ), 0 );
		$this->assertCount( 1, $bundle['people'] );
		Bundle\import( $bundle, $bundle['sections'] );
		$this->assertSame( 'Caio', People\query( $event )['rows'][0]['name'] );
	}

	/**
	 * Events of a CSV are made once, by name and start.
	 */
	public function test_events_from_a_csv() {
		wp_set_current_user( 1 );
		$csv    = Csv\read( $this->file( "title,start,city\nCSV Meetup One,2026-12-01 19:00,Curitiba\nNo start,,Rio\n" ) );
		$bundle = Csv\to_bundle( 'events', $csv, array( 'title', 'start', 'city' ) );
		$result = Bundle\import( $bundle, $bundle['sections'] );
		$this->assertSame( 1, $result['events']['added'] );
		$this->assertSame( 1, $result['events']['skipped'], 'An event with no start is not an event.' );
		$again = Bundle\import( $bundle, $bundle['sections'] );
		$this->assertSame( 0, $again['events']['added'] );
	}

	/**
	 * The export is a table a spreadsheet will not run: UTF-8 with a mark, and a cell that starts like a formula is quoted.
	 */
	public function test_the_export_is_safe_for_a_spreadsheet() {
		$this->assertSame( "'=1+1", Csv\safe_cell( '=1+1' ) );
		$this->assertSame( "'@SUM(A1)", Csv\safe_cell( '@SUM(A1)' ) );
		$this->assertSame( 'Ana', Csv\safe_cell( 'Ana' ) );

		$event = Fixtures::event( array( 'title' => '=HYPERLINK("http://example.com")' ) );
		Fixtures::attendee_of_event( $event, 'Ana Lima', 'ana@example.test' );
		$text = Csv\render( Csv\export_rows( 'people', $event ) );
		$this->assertStringStartsWith( "\xEF\xBB\xBF", $text );
		$this->assertStringContainsString( 'name', $text );
		$this->assertStringContainsString( 'Ana Lima', $text );
		$this->assertStringContainsString( "'=HYPERLINK", $text );
		$this->assertSame( array( 'code', 'status', 'name', 'email', 'role', 'event', 'event_start', 'hours', 'issued_at', 'revoked_reason' ), Csv\export_rows( 'certificates' )['headers'] );
	}
}
