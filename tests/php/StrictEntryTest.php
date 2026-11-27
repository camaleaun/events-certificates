<?php
/**
 * The strict entry (docs/decisions/0044): what is saved or imported is checked against what a design, a drawing, a picture, a font and
 * a package may have, with inputs made to do harm.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

// phpcs:disable WordPress.WP.AlternativeFunctions -- the test makes and reads files of its own in the temporary folder.

use EventsCertificate\Bundle;
use EventsCertificate\Designs;
use EventsCertificate\Events;
use EventsCertificate\Fonts;
use EventsCertificate\Render;

/**
 * Strict entry tests.
 */
class StrictEntryTest extends Test_Case {

	/**
	 * Files a test made.
	 *
	 * @var string[]
	 */
	private $files = array();

	/**
	 * The file functions of the admin.
	 */
	protected function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/file.php';
		// These tests are about packages: they are allowed here, as on a single site (a network leaves them to the super administrator).
		add_filter( 'evcert_restrictions', array( self::class, 'allow_everything' ) );
	}

	/**
	 * Every feature allowed (the filter of these tests).
	 *
	 * @return array<string, bool>
	 */
	public static function allow_everything() {
		return array_fill_keys( \EventsCertificate\Restrictions\FEATURES, true );
	}


	/**
	 * Takes the files away.
	 */
	protected function tear_down() {
		foreach ( $this->files as $file ) {
			is_dir( $file ) ? Bundle\remove_dir( $file ) : wp_delete_file( $file );
		}
		remove_all_filters( 'evcert_bundle_max_bytes' );
		remove_filter( 'evcert_restrictions', array( self::class, 'allow_everything' ) );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Path data is commands and numbers, read the way an SVG reader does: nothing else gets in.
	 */
	public function test_path_data_is_commands_and_numbers_only() {
		foreach ( array(
			'M0 0L100 0L100 40L0 40Z',
			'M0 0 10 10 20 0z',
			'M10 10a2 2 0 011-1.5',
			'M.5.5L1e2 3.5e-1',
			"M0,0 L10,10\n20,20",
		) as $good ) {
			$this->assertTrue( Render\is_path_data( $good ), $good );
		}
		foreach ( array(
			'',
			'L0 0',
			'M0 0<script>alert(1)</script>',
			'M0 0 onload=alert(1)',
			'M0 0L1e999 5',
			'M0 0L999999 5',
			'M0 0L10',
			'M0 0X5 5',
			'M0 0L5 5 javascript:alert(1)',
			'M0 0L5 5 url(#x)',
			'M0 0A1 1 0 2 1 5 5',
			'M0 0L5 5 5',
			'M0 0Z5',
			str_repeat( 'M0 0L1 1', 3000 ),
			'M0 0L' . str_repeat( '9', 40 ) . ' 5',
		) as $bad ) {
			$this->assertFalse( Render\is_path_data( $bad ), substr( $bad, 0, 40 ) );
		}
	}

	/**
	 * A drawing keeps only valid paths, with a fill that is a token or a color.
	 */
	public function test_a_drawing_keeps_only_valid_paths() {
		$drawing = json_decode(
			Events\clean_vector(
				array(
					'vw'    => 100,
					'vh'    => 40,
					'paths' => array(
						array( 'd' => 'M0 0L100 0L100 40Z' ),
						array( 'd' => 'M0 0<script>' ),
						array(
							'd'    => 'M0 0L5 5Z',
							'fill' => 'url(javascript:alert(1))',
						),
						array(
							'd'    => 'M0 0L5 5Z',
							'fill' => '$text',
							'rule' => 'evenodd',
						),
					),
				)
			),
			true
		);
		$this->assertCount( 3, $drawing['paths'], 'The invalid path is gone.' );
		$this->assertArrayNotHasKey( 'fill', $drawing['paths'][1], 'A fill that is not a color or a variable is gone.' );
		$this->assertSame( '$text', $drawing['paths'][2]['fill'] );
		$this->assertSame(
			'',
			Events\clean_vector(
				array(
					'vw'    => 99999,
					'vh'    => 40,
					'paths' => array( array( 'd' => 'M0 0L5 5Z' ) ),
				)
			),
			'A viewBox too big.'
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
	}

	/**
	 * A design that is data a design may have comes out as it went in (the shipped templates, all of them).
	 */
	public function test_the_shipped_designs_are_kept_as_they_are() {
		foreach ( array(
			'base'   => Designs\base(),
			'basic'  => Designs\basic(),
			'meetup' => Designs\meetup(),
		) as $name => $design ) {
			$notes = array();
			$clean = Designs\clean( $design, $notes );
			$this->assertSame( array(), $notes, $name );
			$this->assertEquals( $design, Render\page_in_points( $clean ), $name );
		}
	}

	/**
	 * What a design may not have is removed: other kinds of element, markup, scripts, colors that are not colors, addresses that are not
	 * a file of the Media Library, numbers out of range, keys it does not know.
	 */
	public function test_a_design_made_to_do_harm_is_cleaned() {
		$uploads = wp_get_upload_dir()['baseurl'];
		$design  = array(
			'page'     => array(
				'w'  => 841.89,
				'h'  => 595.28,
				'bg' => 'url(javascript:alert(1))',
			),
			'colors'   => array(
				'text' => array(
					'name'  => '<b>Text</b>',
					'light' => '#1B1F2E',
				),
				'evil' => array(
					'name'  => 'x',
					'light' => 'red;background:url(//evil)',
				),
			),
			'onload'   => 'alert(1)',
			'elements' => array(
				array(
					'id'   => 'a',
					'type' => 'script',
					'x'    => 0,
					'y'    => 0,
					'w'    => 5,
					'h'    => 5,
				),
				array(
					'id'      => 'b',
					'type'    => 'text',
					'x'       => '1e999',
					'y'       => 'abc',
					'w'       => 100,
					'h'       => 20,
					'onclick' => 'alert(1)',
					'style'   => 'background:url(javascript:alert(1))',
					'font'    => "Arial';}</style><script>alert(1)</script>",
					'color'   => 'expression(alert(1))',
					'weight'  => 99999,
					'size'    => 14,
					'runs'    => array(
						array( 'text' => '<script>alert(1)</script>Hello <img src=x onerror=alert(1)>, ' ),
						array( 'field' => 'name"><script>' ),
						array( 'field' => 'name' ),
					),
				),
				array(
					'id'   => 'c',
					'type' => 'image',
					'x'    => 0,
					'y'    => 0,
					'w'    => 10,
					'h'    => 10,
					'src'  => 'javascript:alert(1)',
				),
				array(
					'id'   => 'd',
					'type' => 'image',
					'x'    => 0,
					'y'    => 0,
					'w'    => 10,
					'h'    => 10,
					'src'  => 'data:image/png;base64,AAAA',
				),
				array(
					'id'   => 'e',
					'type' => 'image',
					'x'    => 0,
					'y'    => 0,
					'w'    => 10,
					'h'    => 10,
					'src'  => 'https://evil.example/x.png',
				),
				array(
					'id'   => 'f',
					'type' => 'image',
					'x'    => 0,
					'y'    => 0,
					'w'    => 10,
					'h'    => 10,
					'src'  => $uploads . '/2026/11/shell.php',
				),
				array(
					'id'   => 'g',
					'type' => 'image',
					'x'    => 0,
					'y'    => 0,
					'w'    => 10,
					'h'    => 10,
					'src'  => $uploads . '/2026/11/logo.png',
				),
				array(
					'id'     => 'h',
					'type'   => 'svg',
					'x'      => 0,
					'y'      => 0,
					'w'      => 10,
					'h'      => 10,
					'vw'     => 100,
					'vh'     => 100,
					'markup' => '<svg onload=alert(1)>',
					'paths'  => array(
						array( 'd' => 'M0 0L5 5Z' ),
						array( 'd' => 'M0 0<script>' ),
					),
				),
			),
		);
		$notes   = array();
		$clean   = Designs\clean( $design, $notes );
		$json    = (string) wp_json_encode( $clean );

		$this->assertNotNull( $clean );
		$this->assertArrayNotHasKey( 'onload', $clean );
		$this->assertSame( '', $clean['page']['backgroundColor'] );
		$this->assertSame( array( 'text' ), array_keys( $clean['colors'] ), 'A color that is not a color takes its entry with it.' );
		$this->assertSame( 'Text', $clean['colors']['text']['name'] );
		foreach ( array( '<script', 'onerror', 'onclick', 'javascript:', 'expression(', 'url(', 'onload', 'evil.example', 'data:image', 'shell.php' ) as $forbidden ) {
			$this->assertStringNotContainsString( $forbidden, $json, $forbidden );
		}
		$by_id = array_column( $clean['elements'], null, 'id' );
		$this->assertArrayNotHasKey( 'a', $by_id, 'Not an element a design may have.' );
		$this->assertArrayNotHasKey( 'x', $by_id['b'], 'Not a number.' );
		$this->assertSame( 900, $by_id['b']['weight'], 'In range.' );
		$this->assertSame( array( 'text', 'field' ), array_merge( array_keys( $by_id['b']['runs'][0] ), array_keys( $by_id['b']['runs'][1] ) ), 'Hello, and the one real field.' );
		$this->assertSame( 'Hello , ', $by_id['b']['runs'][0]['text'], 'No markup; the spaces stay.' );
		foreach ( array( 'c', 'd', 'e', 'f' ) as $id ) {
			$this->assertSame( '', $by_id[ $id ]['src'], "Picture $id is not a file of the Media Library." );
		}
		$this->assertSame( $uploads . '/2026/11/logo.png', $by_id['g']['src'], 'A file of the Media Library stays.' );
		$this->assertCount( 1, $by_id['h']['paths'] );
		$this->assertArrayNotHasKey( 'markup', $by_id['h'] );
		$this->assertSame( 1, $notes['elements'] );
		$this->assertSame( 4, $notes['pictures'] );
		$this->assertSame( 1, $notes['paths'] );
	}

	/**
	 * Not a design, or too big, is refused; a design with too many elements keeps the first ones.
	 */
	public function test_what_is_not_a_design_is_refused() {
		$this->assertNull( Designs\clean( 'text' ) );
		$this->assertNull( Designs\clean( array( 'elements' => array() ) ), 'No page.' );
		$this->assertNull(
			Designs\clean(
				array(
					'page' => array(
						'w' => 10,
						'h' => 10,
					),
				)
			),
			'No elements.'
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$big = array(
			'page'     => array(
			'w' => 100,
			'h' => 100,
			), // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
			'elements' => array(),
		);
		for ( $i = 0; $i < 400; $i++ ) {
			$big['elements'][] = array(
				'id'   => 'r' . $i,
				'type' => 'rect',
				'x'    => $i,
				'y'    => 0,
				'w'    => 1,
				'h'    => 1,
			);
		}
		$this->assertCount( \EventsCertificate\Vendor\Camaleaun\Draw\Clean::MAX_ELEMENTS, Designs\clean( $big )['elements'] );
		$big['elements'][0]['name'] = str_repeat( 'x', 3 * MB_IN_BYTES );
		$this->assertNull( Designs\clean( $big ), 'Over the bytes a design may have.' );
	}

	/**
	 * A design saved through the editor (the REST save) is what clean() makes of it, not what was sent.
	 */
	public function test_a_saved_design_is_cleaned() {
		wp_set_current_user(
			(int) get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
					'fields' => 'ID',
				)
			)[0]
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$design               = Designs\basic();
		$design['elements'][] = array(
			'id'      => 'evil',
			'type'    => 'text',
			'x'       => 1,
			'y'       => 1,
			'w'       => 10,
			'h'       => 10,
			'onclick' => 'alert(1)',
			'runs'    => array( array( 'text' => '<script>alert(1)</script>Safe' ) ),
		);
		$id                   = (int) wp_insert_post(
			array(
				'post_type'    => Designs\POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Strict',
				'post_content' => wp_slash( (string) wp_json_encode( $design ) ),
			)
		);
		$stored               = (string) get_post_field( 'post_content', $id );
		$this->assertStringNotContainsString( '<script', $stored );
		$this->assertStringNotContainsString( 'onclick', $stored );
		$this->assertStringContainsString( 'Safe', $stored );
	}

	/**
	 * A picture address that is not a Media Library file never reaches the page, even in a design stored before this cleaning.
	 */
	public function test_an_address_that_is_not_a_picture_never_reaches_the_page() {
		$design             = Designs\basic();
		$design['elements'] = array(
			array(
				'id'   => 'x',
				'type' => 'image',
				'x'    => 0,
				'y'    => 0,
				'w'    => 10,
				'h'    => 10,
				'src'  => 'javascript:alert(1)',
			),
		);
		$html               = Render\artwork(
			$design,
			Render\values(
				array(
					'name' => 'Ana',
					'role' => 'attendee',
				)
			),
			array( 'mode' => 'light' )
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	/**
	 * A font is checked in the way it is built: a header that says 9,999 tables is not a font.
	 */
	public function test_a_font_is_checked_in_the_way_it_is_built() {
		$good = dirname( __DIR__, 2 ) . '/fonts/CertSans-Regular.ttf';
		$this->assertTrue( Fonts\is_well_formed( $good, 'ttf' ) );
		$this->assertFalse( Fonts\is_well_formed( $good, 'woff' ), 'Not what the extension says.' );

		$bytes = (string) file_get_contents( $good ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a font of the plugin.
		$bad   = "\x00\x01\x00\x00" . pack( 'n', 9999 ) . substr( $bytes, 6, 200 );
		$path  = wp_tempnam( 'evcert-font.ttf' );
		file_put_contents( $path, $bad ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents -- a test file.
		$this->files[] = $path;
		$this->assertFalse( Fonts\is_well_formed( $path, 'ttf' ) );
		file_put_contents( $path, "\x00\x01\x00\x00" . pack( 'n', 1 ) . str_repeat( "\0", 6 ) . 'glyf' . pack( 'N', 0 ) . pack( 'N', 99999 ) . pack( 'N', 99999 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents -- a test file.
		$this->assertFalse( Fonts\is_well_formed( $path, 'ttf' ), 'A table that is not inside the file.' );
		file_put_contents( $path, 'wOFF' . str_repeat( "\0", 60 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents -- a test file.
		$this->assertFalse( Fonts\is_well_formed( $path, 'woff' ) );
	}

	/**
	 * A picture is written again by the image library: what was after the picture is not in the new file. And a picture bigger than
	 * the plugin takes is refused.
	 */
	public function test_a_picture_is_written_again_and_its_size_is_limited() {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'This PHP has no GD.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$small = imagecreatetruecolor( 20, 10 );
		$path  = wp_tempnam( 'evcert-polyglot.png' );
		imagepng( $small, $path );
		file_put_contents( $path, "\n<?php system(\$_GET['c']); ?>", FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents -- a test file with a payload after the picture.
		$this->files[] = $path;
		$this->assertStringContainsString( '<?php', (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test file.
		$this->assertTrue( Bundle\picture_in_limits( $path ) );

		$again = Bundle\rewrite_picture( $path );
		$this->assertIsString( $again, $this->why_not( $path ) );
		$this->files[] = $again;
		$this->assertStringNotContainsString( '<?php', (string) file_get_contents( $again ), 'Nothing hidden after the picture comes through.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test file.
		$this->assertSame( array( 20, 10 ), array_slice( wp_getimagesize( $again ), 0, 2 ) );

		$wide = imagecreatetruecolor( Bundle\IMAGE_MAX_SIDE + 1, 1 );
		$long = wp_tempnam( 'evcert-wide.png' );
		imagepng( $wide, $long );
		$this->files[] = $long;
		$this->assertFalse( Bundle\picture_in_limits( $long ), 'A side over the limit.' );
		$this->assertFalse( Bundle\picture_in_limits( __FILE__ ), 'Not a picture.' );
	}

	/**
	 * Builds a ZIP with a manifest and the entries given.
	 *
	 * @param array<string, string> $entries Name => bytes.
	 * @param array<string, mixed>  $assets  What the JSON lists.
	 * @return string Path.
	 */
	private function zip_with( array $entries, array $assets ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$path = wp_tempnam( 'evcert-strict.zip' );
		$zip  = new \ZipArchive();
		$zip->open( $path, \ZipArchive::OVERWRITE );
		$zip->addFromString(
			Bundle\ZIP_JSON,
			(string) wp_json_encode(
				array(
					'$schema'  => Bundle\SCHEMA,
					'sections' => array( 'templates' ),
					'assets'   => $assets,
				)
			)
		);
		foreach ( $entries as $name => $bytes ) {
			$zip->addFromString( $name, $bytes );
		}
		$zip->close();
		$this->files[] = $path;

		return $path;
	}

	/**
	 * A manifest entry for a font.
	 *
	 * @param string $file  Name in the ZIP.
	 * @param string $bytes Bytes.
	 * @return array<string, mixed>
	 */
	private function font_entry( $file, $bytes ) {
		return array(
			'family' => 'Demo Strict',
			'label'  => 'Demo Strict',
			'weight' => '400',
			'file'   => $file,
			'size'   => strlen( $bytes ),
			'sha256' => hash( 'sha256', $bytes ),
		);
	}

	/**
	 * A package with names that climb, that look like code, a link, a file that unpacks to far more than it weighs, a font that is not
	 * built like one and a picture bigger than allowed: none of them is taken, and each is said.
	 */
	public function test_a_package_made_to_do_harm_takes_nothing() {
		if ( ! class_exists( '\ZipArchive' ) || ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'This PHP has no ZipArchive or GD.' );
		}
		add_filter( 'evcert_bundle_max_bytes', static fn () => 50 * MB_IN_BYTES );
		$good = (string) file_get_contents( dirname( __DIR__, 2 ) . '/fonts/CertSans-Regular.ttf' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a font of the plugin.
		$bomb = "true\x00\x01" . str_repeat( "\0", 3 * MB_IN_BYTES ); // Compresses to almost nothing.
		$wide = imagecreatetruecolor( Bundle\IMAGE_MAX_SIDE + 1, 1 );
		$tmp  = wp_tempnam( 'evcert-wide.png' );
		imagepng( $wide, $tmp );
		$wide_bytes    = (string) file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test file.
		$this->files[] = $tmp;
		$zip_path      = $this->zip_with(
			array(
				'fonts/good.ttf'    => $good,
				'fonts/linked.ttf'  => $good,
				'fonts/bomb.ttf'    => $bomb,
				'fonts/broken.ttf'  => "\x00\x01\x00\x00" . pack( 'n', 9999 ) . str_repeat( "\0", 100 ),
				'images/x.php.png'  => $wide_bytes,
				'images/wide.png'   => $wide_bytes,
				'images/nested.zip' => 'PK',
			),
			array(
				'fonts'  => array(
					$this->font_entry( 'fonts/good.ttf', $good ),
					$this->font_entry( 'fonts/linked.ttf', $good ),
					$this->font_entry( 'fonts/bomb.ttf', $bomb ),
					$this->font_entry( 'fonts/broken.ttf', "\x00\x01\x00\x00" . pack( 'n', 9999 ) . str_repeat( "\0", 100 ) ),
				),
				'images' => array(
					array(
						'url'    => 'http://x/a.png',
						'file'   => 'images/x.php.png',
						'size'   => strlen( $wide_bytes ),
						'sha256' => hash( 'sha256', $wide_bytes ),
					),
					array(
						'url'    => 'http://x/b.png',
						'file'   => 'images/wide.png',
						'size'   => strlen( $wide_bytes ),
						'sha256' => hash( 'sha256', $wide_bytes ),
					),
					array(
						'url'    => 'http://x/c.zip',
						'file'   => 'images/nested.zip',
						'size'   => 2,
						'sha256' => hash( 'sha256', 'PK' ),
					),
				),
			)
		);
		// A link inside the ZIP.
		$zip = new \ZipArchive();
		$zip->open( $zip_path );
		$zip->setExternalAttributesName( 'fonts/linked.ttf', \ZipArchive::OPSYS_UNIX, 0120777 << 16 );
		$zip->close();
		$dir           = sys_get_temp_dir() . '/evcert-strict-' . wp_generate_password( 6, false );
		$this->files[] = $dir;

		$read = Bundle\read( $zip_path, $dir );
		$this->assertIsArray( $read );
		$this->assertSame( array( 'fonts/good.ttf' ), array_column( $read['assets']['fonts'], 'file' ), 'Only the good font.' );
		$this->assertSame( array(), $read['assets']['images'], 'No picture: a name that looks like code, a picture too wide, an archive.' );
		$said = implode( ' | ', $read['skipped'] );
		$this->assertStringContainsString( 'linked.ttf was left out: it is a link', $said );
		$this->assertStringContainsString( 'bomb.ttf was left out: it is too compressed', $said );
		$this->assertStringContainsString( 'broken.ttf was left out', $said );
		$this->assertStringContainsString( 'x.php.png was left out', $said );
		$this->assertStringContainsString( 'wide.png was left out', $said );
		$this->assertStringContainsString( 'nested.zip was left out', $said );
		$this->assertCount( 1, (array) glob( $dir . '/*' ), 'Only the good font was taken out.' );
	}

	/**
	 * A JSON nested far deeper than a bundle is, is not read.
	 */
	public function test_a_json_nested_too_deep_is_not_read() {
		$path = wp_tempnam( 'evcert-deep.json' );
		$json = '{"$schema":"' . Bundle\SCHEMA . '","x":' . str_repeat( '[', 100 ) . str_repeat( ']', 100 ) . '}';
		file_put_contents( $path, $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents -- a test file.
		$this->files[] = $path;
		$read          = Bundle\read( $path );
		$this->assertInstanceOf( \WP_Error::class, $read );
		$this->assertSame( 'evcert_bundle_format', $read->get_error_code() );
	}

	/**
	 * The page is kept with units ("297mm", "210mm", "10mm", backgroundColor) and no version, is read back in points, and the older forms
	 * (numbers in points, or "841.89px") are still read and kept this way.
	 */
	public function test_the_page_is_kept_with_units() {
		$old   = array(
			'version'  => 1,
			'page'     => array(
			'w'     => 841.89,
			'h'     => 595.28,
			'bg'    => '$background',
			'safe'  => 28.35,
			'bleed' => 0,
			), // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
			'elements' => array(),
		);
		$clean = Designs\clean( $old );
		$this->assertArrayNotHasKey( 'version', $clean );
		$this->assertSame(
			array(
				'width'           => '297mm',
				'height'          => '210mm',
				'backgroundColor' => '$background',
				'safe'            => '10mm',
			), // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
			$clean['page'],
			'No bleed when there is none.'
		);
		$again = Designs\clean( $clean );
		$this->assertSame( $clean['page'], $again['page'], 'Cleaning again changes nothing.' );

		$old['page']['bleed'] = 8.5;
		$this->assertSame( '3mm', Designs\clean( $old )['page']['bleed'] );
		$back = Render\page_in_points( Designs\clean( $old ) )['page'];
		$this->assertEqualsWithDelta( 841.89, $back['w'], 0.001 );
		$this->assertEqualsWithDelta( 28.35, $back['safe'], 0.01 );
		$this->assertEqualsWithDelta( 8.5, $back['bleed'], 0.05 );
	}

	/**
	 * A template is kept as block markup, whatever form it was sent in; a design saved by an earlier version (JSON) is still read; and
	 * markup made to do harm is cleaned like JSON is.
	 */
	public function test_a_template_is_kept_as_blocks_and_hostile_markup_is_cleaned() {
		wp_set_current_user(
			(int) get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
					'fields' => 'ID',
				)
			)[0]
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$markup = '<!-- wp:events-certificates/certificate {"page":{"width":"297mm","height":"210mm","backgroundColor":"$background","safe":"10mm"},"onload":"alert(1)"} -->'
			. '<!-- wp:events-certificates/certificate-rect {"id":"band","x":0,"y":0,"width":100,"height":10,"fill":"$primary","onclick":"x"} /-->'
			. '<!-- wp:events-certificates/certificate-text {"id":"t","x":1,"y":1,"width":50,"height":10,"runs":[{"text":"<script>alert(1)</script>Hi"}]} /-->'
			. '<!-- wp:events-certificates/certificate-image {"id":"i","x":0,"y":0,"width":5,"height":5,"src":"javascript:alert(1)"} /-->'
			. '<!-- wp:events-certificates/certificate-svg {"id":"s","x":0,"y":0,"width":24,"height":24,"viewBox":"0 0 24 24","paths":[{"d":"M0 0L10 10Z","fill":"$text","onclick":"x"},{"d":"<script>alert(1)</script>"}]} /-->'
			. '<!-- wp:events-certificates/certificate-unknown {"id":"u"} /-->'
			. '<!-- /wp:events-certificates/certificate -->';
		$id     = (int) wp_insert_post(
			array(
				'post_type'    => Designs\POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Blocks',
				'post_content' => wp_slash( $markup ),
			)
		);
		$stored = (string) get_post_field( 'post_content', $id );
		$this->assertTrue( \EventsCertificate\Vendor\Camaleaun\Draw\Design::is_markup( $stored ) );
		foreach ( array( '<script', 'onclick', 'onload', 'javascript:', 'certificate-unknown' ) as $forbidden ) {
			$this->assertStringNotContainsString( $forbidden, $stored, $forbidden );
		}
		$design = Designs\get( $id );
		$this->assertSame( array( 'band', 't', 'i', 's' ), array_column( $design['elements'], 'id' ) );
		// An SVG is one block with its paths as an attribute, and each path is cleaned: the one that is not a path is gone, and so is the
		// attribute that is not a path's.
		$this->assertSame( 'svg', $design['elements'][3]['type'] );
		$this->assertCount( 1, $design['elements'][3]['paths'] );
		$this->assertSame( 'M0 0L10 10Z', $design['elements'][3]['paths'][0]['d'] );
		$this->assertArrayNotHasKey( 'onclick', $design['elements'][3]['paths'][0] );
		$this->assertStringContainsString( 'wp:events-certificates/certificate-svg {', $stored );
		$this->assertStringNotContainsString( 'wp:draw/', $stored );
		$this->assertEqualsWithDelta( 28.35, $design['page']['safe'], 0.01, 'Read in points.' );

		// JSON from an earlier version is read as before, and kept as blocks the next time it is saved.
		$old = (int) wp_insert_post(
			array(
				'post_type'    => Designs\POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Old',
				'post_content' => wp_slash( (string) wp_json_encode( Designs\basic() ) ),
			)
		);
		$this->assertSame( count( Designs\basic()['elements'] ), count( Designs\get( $old )['elements'] ) );
		wp_update_post(
			array(
				'ID'           => $old,
				'post_content' => wp_slash( (string) get_post_field( 'post_content', $old ) ),
			)
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$this->assertTrue( \EventsCertificate\Vendor\Camaleaun\Draw\Design::is_markup( (string) get_post_field( 'post_content', $old ) ) );
	}

	/**
	 * Why a picture could not be written again, for the message of a failing test: what each step says.
	 *
	 * @param string $path Picture.
	 * @return string
	 */
	private function why_not( $path ) {
		$size = wp_getimagesize( $path );
		$copy = $path . '-in.png';
		copy( $path, $copy );
		$editor = wp_get_image_editor( $copy );
		$out    = 'getimagesize: ' . wp_json_encode( $size ) . '; editor: ' . ( is_wp_error( $editor ) ? $editor->get_error_message() : get_class( $editor ) );
		if ( ! is_wp_error( $editor ) ) {
			$saved = $editor->save( $path . '-out.png', 'image/png' );
			$out  .= '; save: ' . ( is_wp_error( $saved ) ? $saved->get_error_message() : wp_json_encode( $saved ) );
			wp_delete_file( $path . '-out.png' );
		}
		wp_delete_file( $copy );

		return $out . '; imagick: ' . ( extension_loaded( 'imagick' ) ? 'yes' : 'no' ) . '; tmp: ' . get_temp_dir();
	}
}
