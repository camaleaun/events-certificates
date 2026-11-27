<?php
/**
 * Fonts: the two the plugin ships, the ones installed in WordPress (the Font Library), and what a design gets when its font is neither
 * (docs/decisions/0042).
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Designs;
use EventsCertificate\Fonts;
use EventsCertificate\Render;

/**
 * Fonts tests.
 */
class FontsTest extends Test_Case {

	/**
	 * Files copied into the fonts folder of WordPress by a test.
	 *
	 * @var string[]
	 */
	private $files = array();

	/**
	 * Starts from a site with no installed font.
	 */
	protected function set_up() {
		parent::set_up();
		$this->empty_font_library();
		Fonts\flush();
	}

	/**
	 * Takes the copied files away.
	 */
	protected function tear_down() {
		foreach ( $this->files as $file ) {
			wp_delete_file( $file );
		}
		Fonts\flush();
		parent::tear_down();
	}

	/**
	 * Installs a family the way the Font Library keeps it: a post for the family and one for each face.
	 *
	 * @param string                            $name    Family name.
	 * @param string                            $stack   CSS font-family of the family ("Name, serif").
	 * @param array<int, array<string, string>> $faces   Faces: file (name in the fonts folder), weight, style.
	 * @param bool                              $copy    Whether to put a real font file in the fonts folder.
	 * @return int Family post ID.
	 */
	private function install( $name, $stack, array $faces, $copy = true ) {
		$dir = wp_get_font_dir();
		wp_mkdir_p( $dir['path'] );
		$family = (int) wp_insert_post(
			array(
				'post_type'    => 'wp_font_family',
				'post_status'  => 'publish',
				'post_title'   => $name,
				'post_content' => wp_json_encode(
					array(
						'name'       => $name,
						'slug'       => sanitize_title( $name ),
						'fontFamily' => $stack,
					)
				),
			)
		);
		foreach ( $faces as $face ) {
			if ( $copy ) {
				$target        = $dir['path'] . '/' . $face['file'];
				$this->files[] = $target;
				copy( dirname( __DIR__, 2 ) . '/fonts/CertSans-Regular.ttf', $target );
			}
			wp_insert_post(
				array(
					'post_type'    => 'wp_font_face',
					'post_status'  => 'publish',
					'post_parent'  => $family,
					'post_title'   => $name . ' ' . $face['weight'],
					'post_content' => wp_json_encode(
						array(
							'fontFamily' => $name,
							'fontStyle'  => $face['style'] ?? 'normal',
							'fontWeight' => $face['weight'],
							'src'        => $dir['url'] . '/' . $face['file'],
						)
					),
				)
			);
		}

		return $family;
	}

	/**
	 * A site with no font installed has the plugin's two, and they are the only files in fonts/ besides their licenses.
	 */
	public function test_the_plugin_ships_only_the_two_small_fonts() {
		$this->assertSame( array( 'CertSans', 'CertSerif' ), array_column( Fonts\choices(), 'value' ) );
		$this->assertSame( array( 'IBM Plex Sans', 'Instrument Serif' ), array_column( Fonts\choices(), 'label' ) );

		$files = array_map( 'basename', glob( dirname( __DIR__, 2 ) . '/fonts/*' ) );
		sort( $files );
		$this->assertSame(
			array( 'CertSans-Bold.ttf', 'CertSans-Regular.ttf', 'CertSerif-Regular.ttf', 'IBMPlexSans-OFL.txt', 'InstrumentSerif-OFL.txt' ),
			$files,
			'No Inter and no Lora: fonts are installed in WordPress.'
		);
	}

	/**
	 * The Font Library's families are read with their weights; an italic face and a file that is not a font are left out.
	 */
	public function test_installed_families_are_read_from_the_font_library() {
		$this->install(
			'Inter',
			'Inter, sans-serif',
			array(
				array(
					'file'   => 'evcert-test-inter-400.ttf',
					'weight' => '400',
				),
				array(
					'file'   => 'evcert-test-inter-600.ttf',
					'weight' => '600',
				),
				array(
					'file'   => 'evcert-test-inter-400-italic.ttf',
					'weight' => '400',
					'style'  => 'italic',
				),
			)
		);

		$families = array_column( Fonts\choices(), null, 'value' );
		$this->assertSame( array( 'CertSans', 'CertSerif', 'Inter' ), array_keys( $families ) );
		$this->assertSame( array( 400, 600 ), $families['Inter']['weights'], 'Upright faces only.' );
		$this->assertTrue( $families['Inter']['pdf'] );
		$this->assertSame( 'CertSans', $families['Inter']['fallback'] );
		$this->assertStringContainsString( "font-family:'Inter';font-weight:600", Fonts\css( false ) );
		$this->assertStringContainsString( "font-family:'Inter';font-weight:600", Fonts\css( true ), 'The PDF reads it too (a TrueType file).' );
	}

	/**
	 * A family only in WOFF2 (what the Font Library installs from Google Fonts) shows in the editor and on the page; the PDF cannot read it.
	 */
	public function test_a_woff2_family_is_for_the_page_and_not_for_the_pdf() {
		$this->install(
			'Demo Web',
			'Demo Web, serif',
			array(
				array(
					'file'   => 'evcert-test-demo-web.woff2',
					'weight' => '400',
				),
			)
		);

		$demo = array_column( Fonts\choices(), null, 'value' )['Demo Web'];
		$this->assertFalse( $demo['pdf'] );
		$this->assertSame( 'CertSerif', $demo['fallback'], 'Its kind (a serif) decides what the PDF uses.' );
		$this->assertSame( "'Demo Web','CertSerif'", Fonts\stack( 'Demo Web', false ), 'The page: the font, then the one that stands in.' );
		$this->assertSame( "'CertSerif'", Fonts\stack( 'Demo Web', true ), 'The PDF: the one that stands in.' );
		$this->assertStringContainsString( "format('woff2')", Fonts\css( false ) );
		$this->assertStringNotContainsString( 'Demo Web', Fonts\css( true ), 'The PDF is not given a file it cannot read.' );
	}

	/**
	 * A variable font (one file for a range of weights) stands for the weights a design uses inside the range.
	 */
	public function test_a_variable_font_stands_for_its_weights() {
		$this->assertSame( array( 400, 500, 600 ), Fonts\weights_of( '100 600' ) );
		$this->assertSame( array( 400 ), Fonts\weights_of( 'normal' ) );
		$this->assertSame( array( 700 ), Fonts\weights_of( 'bold' ) );
		$this->assertSame( array( 300 ), Fonts\weights_of( '300' ) );
	}

	/**
	 * A font a design names that is neither shipped nor installed becomes a shipped one: Lora's kind is the serif, the rest the sans.
	 */
	public function test_a_font_that_is_not_there_becomes_a_shipped_one() {
		$this->assertSame( 'CertSans', Fonts\resolve( 'Inter' ) );
		$this->assertSame( 'CertSerif', Fonts\resolve( 'Lora' ) );
		$this->assertSame( 'CertSans', Fonts\resolve( 'Anything Else' ) );
		$this->assertSame( 'CertSans', Fonts\resolve( 'CertSans' ) );
		$this->assertSame( "'Inter','CertSans'", Fonts\stack( 'Inter' ) );
		$this->assertSame( "'CertSans'", Fonts\stack( '' ), 'No font: the default one.' );

		$this->install(
			'Inter',
			'Inter, sans-serif',
			array(
				array(
					'file'   => 'evcert-test-inter-400.ttf',
					'weight' => '400',
				),
			)
		);
		$this->assertSame( 'Inter', Fonts\resolve( 'Inter' ), 'Installed: itself.' );
	}

	/**
	 * A stand-in font is not asked for a weight it lacks (the PDF engine would answer with another font): it gets the one CSS would pick.
	 */
	public function test_the_weight_is_one_the_font_has() {
		$this->assertSame( 400, Fonts\weight_for( 'CertSerif', 600, true ), 'The serif has only the regular.' );
		$this->assertSame( 400, Fonts\weight_for( 'Lora', 600, true ), 'Not installed: the serif that stands in.' );
		$this->assertSame( 400, Fonts\weight_for( 'Inter', 500, true ), '500 between 400 and 600 is the 400, as in CSS.' );
		$this->assertSame( 600, Fonts\weight_for( 'Inter', 700, true ) );
		$this->assertSame( 600, Fonts\weight_for( 'CertSans', 600, true ) );
		$this->assertSame( 400, Fonts\weight_for( 'CertSans', 300, true ) );
	}

	/**
	 * A design that asks for a font the site does not have still draws: the page names it and the one that stands in, the PDF uses the
	 * one that stands in.
	 */
	public function test_a_design_with_a_missing_font_still_draws_and_prints() {
		$design             = Designs\basic();
		$design['elements'] = array(
			array(
				'id'     => 'f',
				'type'   => 'text',
				'x'      => 10,
				'y'      => 10,
				'w'      => 300,
				'h'      => 30,
				'runs'   => array( array( 'text' => 'Hello' ) ),
				'font'   => 'Inter',
				'weight' => 600,
				'size'   => 18,
				'color'  => '$text',
				'align'  => 'left',
			),
		);
		$values             = array( 'name' => 'Ana' ) + Render\values( $this->cert() );

		$page = Render\artwork( $design, $values, array( 'mode' => 'light' ) );
		// The style is an HTML attribute: its quotes are escaped.
		$this->assertStringContainsString( 'font-family:&#039;Inter&#039;,&#039;CertSans&#039;;font-weight:600', $page, 'The page: the font and the one that stands in.' );
		$pdf = Render\artwork(
			$design,
			$values,
			array(
				'mode'    => 'light',
				'for_pdf' => true,
			)
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$this->assertStringContainsString( 'font-family:&#039;CertSans&#039;;font-weight:600', $pdf, 'The PDF: the one that stands in.' );

		$this->assertStringStartsWith( '%PDF', Render\pdf_many( array( array( $this->cert(), $design, array( 'no_verification' => true ) ) ) ) );
	}

	/**
	 * The declaration page (the standard one) is drawn in the shipped fonts: the plugin has no other.
	 */
	public function test_the_declaration_page_uses_the_shipped_fonts() {
		$cert   = $this->cert( array( 'data_json' => wp_json_encode( array( 'page2' => true ) ) ) );
		$design = Designs\meetup();
		unset( $design['back'] );
		$html = Render\declaration( $cert, $design, Render\values( $cert ), array( 'mode' => 'light' ) );

		$this->assertStringContainsString( "font-family:'CertSerif'", $html, 'The title.' );
		$this->assertStringContainsString( "font-family:'CertSans'", $html, 'The text.' );
		$this->assertStringNotContainsString( "'Inter'", $html );
		$this->assertStringNotContainsString( "'Lora'", $html );
	}

	/**
	 * A certificate that is not a record.
	 *
	 * @param array<string, mixed> $more Fields to change.
	 * @return array<string, mixed>
	 */
	private function cert( array $more = array() ) {
		return $more + array(
			'name'        => 'Ana Lima',
			'role'        => 'attendee',
			'code'        => '2611-ABCD-EFGH-JKLM',
			'status'      => 'issued',
			'issued_at'   => '2026-11-27 12:00:00',
			'event_title' => 'WordCamp Teste',
			'event_start' => '2026-10-31 09:00:00',
			'event_end'   => '2026-10-31 17:00:00',
			'hours'       => '8',
			'data_json'   => '{}',
		);
	}
}
