<?php
/**
 * The palette: Background and Text always there, Dark falling back to Light, and a white page left unpainted in a PDF.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Designs;
use EventsCertificate\Render;

/**
 * The same cases as the editor's (tests/fixtures/palette-cases.json, tests/js/palette.test.mjs): both sides must agree, because
 * the editor saves what it normalizes and the renderer reads what is stored.
 */
class PaletteTest extends Test_Case {

	/**
	 * The cases shared with the editor.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function cases() {
		$list = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/palette-cases.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a test fixture.
		$out  = array();
		foreach ( $list as $item ) {
			$out[ $item['name'] ] = array( $item );
		}

		return $out;
	}

	/**
	 * What the editor makes of a design, the renderer makes too.
	 *
	 * @dataProvider cases
	 *
	 * @param array<string, mixed> $item Case.
	 */
	public function test_it_normalizes_like_the_editor( $item ) {
		$out = Render\with_palette( $item['input'] );

		$this->assertSame( $item['colors'], $out['colors'] );
		$this->assertSame( array_keys( $item['colors'] ), array_keys( $out['colors'] ) );
		$this->assertSame( $item['bg'], $out['page']['bg'] );
		$this->assertSame( $item['palette'], $out['palette'] );
		if ( isset( $item['elements'] ) ) {
			$this->assertSame( $item['elements'], $out['elements'] );
		}
		$this->assertSame( $out, Render\with_palette( $out ), 'running it again changes nothing' );
	}

	/**
	 * A color without a Dark value is its Light one in dark mode.
	 */
	public function test_dark_falls_back_to_light() {
		$design = array(
			'colors' => array(
				'a' => array(
					'name'  => 'A',
					'light' => '#111111',
				),
				'b' => array(
					'name'  => 'B',
					'light' => '#222222',
					'dark'  => '#333333',
				),
			),
		);

		$this->assertSame( '#111111', Render\color( $design, '$a', 'dark' ) );
		$this->assertSame( '#333333', Render\color( $design, '$b', 'dark' ) );
		$this->assertSame( '#222222', Render\color( $design, '$b', 'light' ) );
		$this->assertSame( 'none', Render\color( $design, '$missing', 'dark' ) );
	}

	/**
	 * White in any spelling, and nothing else.
	 */
	public function test_pure_white_is_spotted() {
		foreach ( array( '#fff', '#FFFFFF', '#ffffffff', 'white', 'rgb(255, 255, 255)', 'rgba(255,255,255,1)' ) as $white ) {
			$this->assertTrue( Render\is_white( $white ), $white );
		}
		foreach ( array( '#fefefe', '#ffffff00', 'transparent', 'rgba(255,255,255,0.5)', '' ) as $other ) {
			$this->assertFalse( Render\is_white( $other ), $other );
		}
	}

	/**
	 * The Base design starts filled with Background and Text (nothing is fixed: they may be removed and renamed), and a white page.
	 */
	public function test_the_base_design_starts_filled() {
		$colors = Designs\base()['colors'];

		$this->assertSame( array( 'background', 'text' ), array_slice( array_keys( $colors ), 0, 2 ) );
		$this->assertSame( '#ffffff', $colors['background']['light'] );
	}

	/**
	 * A PDF leaves a white page unpainted; a page of another color, and the verification page, keep it.
	 */
	public function test_a_white_page_is_not_painted_in_a_pdf() {
		$design             = Designs\base();
		$design['elements'] = array();
		$values             = array( 'name' => 'Maria' );

		$pdf = Render\artwork( $design, $values, array( 'for_pdf' => true ) );
		$web = Render\artwork( $design, $values, array() );
		$this->assertStringContainsString( 'background:transparent;', $pdf );
		$this->assertStringContainsString( 'background:#ffffff;', $web );

		$design['colors']['background']['light'] = '#14161A';
		$this->assertStringContainsString( 'background:#14161A;', Render\artwork( $design, $values, array( 'for_pdf' => true ) ) );
	}

	/**
	 * The Base design's theme starts with Base and Contrast, one swatch for each color and no Dark ones.
	 */
	public function test_the_base_design_theme() {
		$palette = Designs\base()['palette'];

		$this->assertSame( array( 'base', 'contrast' ), array_slice( wp_list_pluck( $palette, 'slug' ), 0, 2 ) );
		foreach ( $palette as $swatch ) {
			$this->assertStringNotContainsString( '(Dark)', (string) ( $swatch['name'] ?? '' ) );
		}
		$this->assertSame( $palette, Render\default_palette( Designs\base()['colors'] ) );
	}

	/**
	 * None of it is fixed: a design with no colors and no theme keeps none, and what the code reads of a color it does not have is the
	 * built-in one (black text on a white page), in light and in dark. Any other name is none.
	 */
	public function test_a_design_may_have_no_colors_at_all() {
		$design = Render\with_palette(
			array(
				'page'     => array(
					'w'  => 842,
					'h'  => 595,
					'bg' => '$background',
				),
				'colors'   => array(),
				'palette'  => array(),
				'elements' => array(),
			)
		);

		$this->assertSame( array(), $design['colors'] );
		$this->assertSame( array(), $design['palette'] );
		$this->assertSame( '#000000', Render\color( $design, '$text' ) );
		$this->assertSame( '#ffffff', Render\color( $design, '$text', 'dark' ) );
		$this->assertSame( 'transparent', Render\color( $design, '$background' ) );
		$this->assertSame( '#000000', Render\color( $design, '$muted' ) );
		$this->assertSame( '#000000', Render\color( $design, '$line' ) );
		$this->assertSame( '#3858e9', Render\color( $design, '$primary' ) );
		$this->assertSame( 'none', Render\color( $design, '$other' ) );
		$this->assertSame( '#ffffff', Render\page_color( $design ) );
		$this->assertSame( '#000000', Render\page_color( $design, 'dark' ) );

		$values = array( 'name' => 'Maria' );
		$this->assertStringContainsString( 'background:transparent;', Render\artwork( $design, $values, array( 'for_pdf' => true ) ), 'A white page is not painted in a PDF.' );
		$this->assertStringContainsString( 'background:#ffffff;', Render\artwork( $design, $values, array() ) );

		$design['colors']['background'] = array(
			'name'  => 'Background',
			'light' => '',
		);
		$this->assertSame( '#ffffff', Render\page_color( $design ), 'A Background with no color is white paper.' );
	}

	/**
	 * Base and Contrast are as any swatch: renamed, recolored and removed, and the slug stays.
	 */
	public function test_base_and_contrast_may_be_renamed_and_removed() {
		$palette = Render\clean_palette(
			array(
				array(
					'slug'  => 'base',
					'name'  => ' Paper ',
					'color' => '#fefefe',
				),
				array(
					'slug'  => 'contrast',
					'color' => '#111111',
				),
			)
		);
		$this->assertSame(
			array(
				array(
					'slug'  => 'base',
					'name'  => 'Paper',
					'color' => '#fefefe',
				),
				array(
					'slug'  => 'contrast',
					'color' => '#111111',
				),
			),
			$palette
		);
		$this->assertSame( array(), Render\clean_palette( array() ) );
	}
}
