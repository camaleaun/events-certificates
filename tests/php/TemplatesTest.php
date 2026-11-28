<?php
/**
 * Certificate templates: choice by role, duplicate, default, order.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Designs;
use EventsCertificate\Templates;

/**
 * Templates tests (they make templates and delete them after).
 */
class TemplatesTest extends Test_Case {

	/**
	 * Templates made by a test.
	 *
	 * @var int[]
	 */
	private $made = array();

	/**
	 * Default template before the test.
	 *
	 * @var int
	 */
	private $default = 0;

	/**
	 * Remembers the default template.
	 */
	protected function set_up() {
		parent::set_up();
		wp_set_current_user( 1 );
		$this->default = Designs\site_design_id();
	}

	/**
	 * Deletes the templates made and restores the default.
	 */
	protected function tear_down() {
		update_option( Designs\SITE_DESIGN_OPTION, $this->default, false );
		foreach ( $this->made as $id ) {
			wp_delete_post( $id, true );
		}
		parent::tear_down();
	}

	/**
	 * A copy of the default for some roles.
	 *
	 * @param string[] $roles Roles.
	 * @return int
	 */
	private function make( array $roles ) {
		$id = Designs\duplicate( $this->default );
		update_post_meta( $id, Designs\ROLES_META, $roles );
		$this->made[] = $id;

		return $id;
	}

	/**
	 * A certificate gets the first template for its role, else the default.
	 */
	public function test_choice_by_role() {
		$volunteers = $this->make( array( 'volunteer' ) );
		$this->assertSame( $volunteers, Designs\for_certificate( array( 'role' => 'volunteer' ) ) );
		$this->assertSame( $this->default, Designs\for_certificate( array( 'role' => 'attendee' ) ) );
		$this->assertSame( $this->default, Designs\for_certificate( array() ) );

		// Two for the same role: the first in the list wins; moving changes it.
		$second = $this->make( array( 'volunteer', 'speaker' ) );
		$this->assertSame( $volunteers, Designs\for_certificate( array( 'role' => 'volunteer' ) ) );
		$this->assertSame( $second, Designs\for_certificate( array( 'role' => 'speaker' ) ) );
		Designs\move( $second, 'up' );
		$this->assertSame( $second, Designs\for_certificate( array( 'role' => 'volunteer' ) ) );
	}

	/**
	 * Duplicating copies the content and the roles; unknown roles are dropped.
	 */
	public function test_duplicate_and_roles() {
		$source = $this->make( array( 'sponsor', 'nobody' ) );
		$this->assertSame( array( 'sponsor' ), Designs\roles( $source ) );
		$copy         = Designs\duplicate( $source );
		$this->made[] = $copy;
		$this->assertSame( array( 'sponsor' ), Designs\roles( $copy ) );
		$this->assertSame( get_post_field( 'post_content', $source ), get_post_field( 'post_content', $copy ) );
		$this->assertStringContainsString( get_the_title( $source ), get_the_title( $copy ) );
	}

	/**
	 * The default template applies to everyone else and keeps no roles.
	 */
	public function test_set_default() {
		$id = $this->make( array( 'organizer' ) );
		$this->assertTrue( Designs\set_default( $id ) );
		$this->assertSame( $id, Designs\site_design_id() );
		$this->assertSame( array(), Designs\roles( $id ) );
		$this->assertSame( $id, Designs\for_certificate( array( 'role' => 'attendee' ) ) );
	}

	/**
	 * A template can be made with the name typed in the form, from the Base design or from another template.
	 */
	public function test_duplicate_takes_a_name() {
		$base = Designs\duplicate( 0, 'Speakers' );
		$this->assertSame( 'Speakers', get_the_title( $base ) );
		$copy = Designs\duplicate( $base, 'Speakers, Portuguese' );
		$this->assertSame( 'Speakers, Portuguese', get_the_title( $copy ) );
		$this->assertSame( 'New template', get_the_title( Designs\duplicate( 0 ) ), 'No name: the default one.' );
	}

	/**
	 * Saving a template keeps the unit its margins were written in: a safe margin of 24pt is not turned into 8.47mm.
	 */
	public function test_saving_a_template_keeps_the_unit_of_its_margins() {
		foreach ( array( '24pt', '9.5mm', '0.4in' ) as $safe ) {
			$markup = '<!-- wp:events-certificates/certificate {"page":{"width":"297mm","height":"210mm","safe":"' . $safe . '","bleed":"' . $safe . '"}} /-->';
			$out    = Designs\content( Designs\decode( $markup ) );
			$this->assertStringContainsString( '"safe":"' . $safe . '"', $out, "safe $safe" );
			$this->assertStringContainsString( '"bleed":"' . $safe . '"', $out, "bleed $safe" );
			$this->assertStringNotContainsString( 'safeUnit', $out );
		}
	}

	/**
	 * A blank design keeps the Base design's page and has nothing on it: no colors and no theme (nothing is fixed).
	 */
	public function test_a_blank_design_has_the_page_only() {
		$id     = Designs\duplicate( 0, 'Blank', true );
		$design = Designs\decode( (string) get_post( $id )->post_content );
		$this->assertTrue( \EventsCertificate\Vendor\Camaleaun\Draw\Design::is_markup( (string) get_post( $id )->post_content ), 'Kept as blocks.' );
		$base = Designs\base();

		$this->assertSame( array(), $design['elements'] );
		// Kept with units and no version, read back in points; the size in the unit that makes it round.
		$this->assertArrayNotHasKey( 'version', $design );
		$this->assertSame( '297mm', $design['page']['width'] );
		$this->assertSame( '210mm', $design['page']['height'] );
		// The same page as the Base design, with no safe margin: nothing is on it to keep inside.
		$this->assertArrayNotHasKey( 'safe', $design['page'], 'No safe margin written when there is none.' );
		$this->assertEquals( array_merge( $base['page'], array( 'safe' => 0 ) ), \EventsCertificate\Render\page_in_points( $design )['page'] );
		$this->assertSame( array(), $design['colors'], 'No colors: the + of the Colors section brings the first ones back.' );
		$this->assertSame( array(), $design['palette'], 'No theme either, and not the default one made again when it is read.' );
		$this->assertNotEmpty( Designs\base()['elements'], 'The Base design is untouched.' );
		// The file says nothing but the page: no colors, no theme and no background that points at no color.
		$this->assertSame( '<!-- wp:events-certificates/certificate {"page":{"width":"297mm","height":"210mm"}} /-->', trim( (string) get_post( $id )->post_content ) );
	}

	/**
	 * The screen looks like Tags: a form to add on the left, the list on the right.
	 */
	public function test_screen_has_the_add_form_beside_the_list() {
		wp_set_current_user(
			(int) get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
					'fields' => 'ID',
				)
			)[0]
		);
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.Security.ValidatedSanitizedInput -- a screen outside wp-admin needs these.
		$GLOBALS['hook_suffix'] = 'admin_page_evcert-template';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		\set_current_screen( 'admin_page_evcert-template' );
		$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
		$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/wp-admin/admin.php?page=evcert-template';
		// phpcs:enable
		ob_start();
		Templates\render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'id="col-container"', $html );
		$this->assertStringContainsString( 'id="col-left"', $html );
		$this->assertStringContainsString( 'id="col-right"', $html );
		$this->assertStringContainsString( 'id="addtemplate"', $html );
		$this->assertStringContainsString( 'name="template-name"', $html );
		$this->assertStringContainsString( 'name="template-from"', $html );
		$this->assertStringContainsString( 'wp-list-table', $html );
		$this->assertLessThan( strpos( $html, 'id="col-right"' ), strpos( $html, 'id="col-left"' ), 'The form comes first, as in Tags.' );
	}

	/**
	 * A template added with the form opens in the editor (from the Base design, a blank design or another template).
	 */
	public function test_the_add_form_opens_the_editor() {
		wp_set_current_user( 1 );
		$known = get_posts(
			array(
				'post_type'   => Designs\POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		$catch = static function ( $to ) use ( &$location ) {
			$location = $to;
			throw new \RuntimeException( 'redirected' );
		};
		add_filter( 'wp_redirect', $catch );
		foreach ( array( '0', 'blank', (string) $this->default ) as $from ) {
			$location                     = '';
			$_POST['evcert_template_add'] = '1';
			$_POST['template-name']       = 'From the form';
			$_POST['template-from']       = $from;
			$_REQUEST['_wpnonce']         = wp_create_nonce( 'evcert_template_add' );
			$_REQUEST['_wp_http_referer'] = '';
			try {
				Templates\load();
			} catch ( \RuntimeException $e ) {
				$this->assertSame( 'redirected', $e->getMessage() );
			}
			$this->assertStringContainsString( 'action=edit', $location, $from );
			$this->assertStringContainsString( 'design=', $location, $from );
		}
		remove_filter( 'wp_redirect', $catch );
		unset( $_POST['evcert_template_add'], $_POST['template-name'], $_POST['template-from'], $_REQUEST['_wpnonce'], $_REQUEST['_wp_http_referer'] );
		foreach ( get_posts(
			array(
				'post_type'    => Designs\POST_TYPE,
				'post_status'  => 'any',
				'numberposts'  => -1,
				'fields'       => 'ids',
				'post__not_in' => $known,
			)
		) as $copy ) {
			wp_delete_post( (int) $copy, true );
		}
	}

	/**
	 * Duplicating a template from its row only makes it and goes back to the list, with the notice.
	 */
	public function test_duplicate_goes_back_to_the_list() {
		wp_set_current_user( 1 );
		$_GET['evcert_do']    = 'duplicate';
		$_GET['template']     = (string) $this->default;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'evcert_template_duplicate_' . $this->default );
		$location             = '';
		$catch                = static function ( $to ) use ( &$location ) {
			$location = $to;
			throw new \RuntimeException( 'redirected' );
		};
		add_filter( 'wp_redirect', $catch );
		$before = count( Designs\templates() );
		$known  = get_posts(
			array(
				'post_type'   => Designs\POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		try {
			Templates\load();
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirected', $e->getMessage() );
		} finally {
			remove_filter( 'wp_redirect', $catch );
			unset( $_GET['evcert_do'], $_GET['template'], $_REQUEST['_wpnonce'] );
		}
		$this->assertCount( $before + 1, Designs\templates() );
		// The copy this test made does not stay on the site.
		foreach ( get_posts(
			array(
				'post_type'    => Designs\POST_TYPE,
				'post_status'  => 'any',
				'numberposts'  => -1,
				'fields'       => 'ids',
				'post__not_in' => $known,
			)
		) as $copy ) {
			wp_delete_post( (int) $copy, true );
		}
		$this->assertStringContainsString( 'page=' . Templates\SLUG, $location );
		$this->assertStringContainsString( 'evcert_done=added', $location );
		$this->assertStringNotContainsString( 'action=edit', $location );
	}

	/**
	 * An empty template has nothing on it, and no safe margin.
	 */
	public function test_a_blank_template_has_no_safe_margin() {
		$blank = Designs\blank();
		$this->assertSame( array(), $blank['elements'] );
		$this->assertEquals( 0, $blank['page']['safe'] );
		$id = (int) wp_insert_post(
			array(
				'post_type'    => Designs\POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Blank',
				'post_content' => Designs\content( $blank ),
			)
		);
		$this->assertEquals( 0, Designs\get( $id )['page']['safe'], 'It is kept and read as 0.' );
	}

		/**
		 * A color of the design is written the way a block says a preset (var:preset|color|background), never as "$background";
		 * an A4 kept in whole points (842 by 595) is read as the A4 and written in millimeters.
		 */
	public function test_the_file_says_colors_as_presets_and_an_a4_in_millimeters() {
		$design              = Designs\base();
		$design['page']['w'] = 842;
		$design['page']['h'] = 595;
		$design['elements']  = array(
			array(
				'id'   => 'box',
				'type' => 'rect',
				'x'    => 0,
				'y'    => 0,
				'w'    => 10,
				'h'    => 10,
				'fill' => '$primary',
			),
		);
		$content             = Designs\content( $design );

		$this->assertStringContainsString( '"backgroundColor":"var:preset|color|background"', $content );
		$this->assertStringContainsString( 'var:preset|color|primary', $content );
		$this->assertSame( 0, preg_match( '/"\$[A-Za-z]/', $content ), 'No $name left in the file.' );
		$this->assertStringContainsString( '"width":"297mm","height":"210mm"', $content );
		$back = Designs\decode( $content );
		$this->assertSame( '$background', $back['page']['backgroundColor'] );
		$this->assertSame( '$primary', $back['elements'][0]['fill'] );
	}

	/**
	 * The colors are written like a theme.json (color.palette, color.elements: slug, color, name) and read back as the model's colors and palette.
	 */
	public function test_the_file_says_the_colors_like_a_theme_json() {
		$design               = Designs\blank();
		$design['colors']     = array(
			'fundo' => array(
				'name'  => 'Fundo',
				'light' => '#ffffff',
				'dark'  => '#000000',
			),
			'cor-1' => array(
				'name'  => 'Cor 1',
				'light' => '',
			),
		);
		$design['palette']    = array(
			array(
				'slug'  => 'base',
				'color' => '#ffffff',
			),
			array(
				'slug'  => 'mirtilo',
				'name'  => 'Mirtilo',
				'color' => '#3858e9',
			),
		);
		$design['page']['bg'] = '$fundo';
		$content              = Designs\content( $design );

		$this->assertStringContainsString( '"color":{"palette":[{"slug":"base","color":"#ffffff"},{"slug":"mirtilo","color":"#3858e9","name":"Mirtilo"}],"elements":[{"slug":"fundo","color":"light-dark(#ffffff,#000000)","name":"Fundo"},{"slug":"cor-1","color":"","name":"Cor 1"}]}', $content );
		$this->assertStringNotContainsString( '"colors"', $content );
		$this->assertStringContainsString( '"backgroundColor":"var:preset|color|fundo"', $content );
		$back = Designs\decode( $content );
		$this->assertSame( $design['colors'], $back['colors'] );
		$this->assertSame( 'Mirtilo', $back['palette'][1]['name'] );
		$this->assertSame( array( 'base', 'mirtilo' ), wp_list_pluck( $back['palette'], 'slug' ) );
	}


	/**
	 * What is saved is written by the package as blocks say it (names in full, no id that only counts, no default name, the certificate's own default
	 * names too) and read back as the model's.
	 */
	public function test_an_element_is_kept_as_its_block_says_it() {
		$design             = Designs\blank();
		$design['elements'] = array(
			array(
				'id'     => 'rect-2',
				'type'   => 'rect',
				'name'   => 'Rectangle',
				'x'      => 1,
				'y'      => 2,
				'w'      => 30,
				'h'      => 20,
				'layout' => array(
					'h'   => array( 'mode' => 'stretch' ),
					'v'   => array( 'mode' => 'start' ),
					'ref' => 'safe',
				),
			),
			array(
				'id'    => 'svg-mwzbwe9c-1',
				'type'  => 'svg',
				'name'  => 'SVG',
				'x'     => 0,
				'y'     => 0,
				'w'     => 96,
				'h'     => 96,
				'vw'    => 24,
				'vh'    => 24,
				'paths' => array( array( 'd' => 'M0 0L1 1Z' ) ),
			),
			array(
				'id'   => 'title',
				'type' => 'text',
				'name' => 'Band',
				'x'    => 1,
				'y'    => 2,
				'w'    => 50,
				'h'    => 10,
				'lh'   => 1.2,
				'runs' => array(
					array(
						'text' => 'a',
						'b'    => true,
					),
				),
			),
			array(
				'id'   => 'signature',
				'type' => 'signature',
				'name' => 'Signature',
				'x'    => 1,
				'y'    => 2,
				'w'    => 10,
				'h'    => 5,
			),
		);
		$content            = Designs\content( $design );

		$this->assertSame( 0, preg_match( '/"(w|h|vw|vh|lh|b)":/', $content ), 'No short name.' );
		$this->assertSame( 0, preg_match( '/"id":"(rect-2|svg-)/', $content ), 'No id that only counts.' );
		$this->assertSame( 0, preg_match( '/"name":"(Rectangle|SVG|Signature)"/', $content ), 'No default name.' );
		$this->assertStringContainsString( '"name":"Band"', $content );
		$this->assertStringContainsString( '"id":"title"', $content );
		$this->assertStringContainsString( '"viewBox":"0 0 24 24"', $content );
		$this->assertStringContainsString( '"horizontal":{"mode":"stretch"}', $content );
		$this->assertStringContainsString( '"lineHeight":1.2', $content );
		$this->assertStringContainsString( '"text":"**a**"', $content, 'A text is written as text.' );
		$this->assertStringNotContainsString( '"runs"', $content );

		$back = Designs\decode( $content );
		$this->assertSame( array( 'rect-1', 'svg-2', 'title', 'signature' ), array_column( $back['elements'], 'id' ) );
		$this->assertSame( 96, $back['elements'][1]['w'] );
		$this->assertSame( 24, $back['elements'][1]['vw'] );
		$this->assertSame( 1.2, $back['elements'][2]['lh'] );
		$this->assertTrue( $back['elements'][2]['runs'][0]['b'] );
		$this->assertSame( 'stretch', $back['elements'][0]['layout']['h']['mode'] );
	}

	public function test_a_text_keeps_the_words_in_brackets_that_are_not_fields() {
		$design  = array(
			'page'     => array(
				'w' => 100,
				'h' => 50,
			),
			'elements' => array(
				array(
					'id'   => 't',
					'type' => 'text',
					'x'    => 1,
					'y'    => 2,
					'w'    => 50,
					'h'    => 10,
					'runs' => array(
						array( 'text' => 'Dear ' ),
						array(
							'field' => 'name',
							'b'     => true,
						),
						array( 'text' => ' [not a field] and [[other]]' ),
					),
				),
			),
		);
		$content = Designs\content( $design );

		$this->assertStringContainsString( '"text":"Dear **[[name]]** [not a field] and \\\\[[other]]"', $content );
		$back = Designs\decode( $content );
		$this->assertSame( $design['elements'][0]['runs'], $back['elements'][0]['runs'] );
		$read = Designs\decode( str_replace( '\\\\[[other]]', '[[other]]', $content ) );
		$this->assertSame( ' [not a field] and [[other]]', $read['elements'][0]['runs'][2]['text'], 'A word in double brackets that is not a field is text.' );
	}

	public function test_the_date_of_emission_is_a_block_of_its_own() {
		$design             = Designs\blank();
		$design['elements'] = array(
			array(
				'id'   => 'text-3',
				'type' => 'text',
				'slot' => 'date-of-emission',
				'name' => 'Date of emission',
				'x'    => 1,
				'y'    => 2,
				'w'    => 200,
				'h'    => 16,
			),
			array(
				'id'   => 'date',
				'type' => 'text',
				'slot' => 'date-of-emission',
				'name' => 'Date of emission',
				'x'    => 1,
				'y'    => 2,
				'w'    => 200,
				'h'    => 16,
				'runs' => array( array( 'text' => 'd/m/Y' ) ),
			),
		);
		$content            = Designs\content( $design );

		$this->assertSame( 2, substr_count( $content, 'wp:events-certificates/certificate-date-of-emission' ) );
		$this->assertSame( 0, preg_match( '/"(slot|name|runs)"|"id":"text-3"/', $content ), 'The block says the slot and the name; an empty one has no text.' );
		$this->assertStringContainsString( '"text":"d/m/Y"', $content );

		$back = Designs\decode( $content );
		$this->assertSame( array( 'date-of-emission', 'date-of-emission' ), array_column( $back['elements'], 'slot' ) );
		$this->assertSame( array( 'text', 'text' ), array_column( $back['elements'], 'type' ) );
		$this->assertSame( 'd/m/Y', $back['elements'][1]['runs'][0]['text'] );
		$this->assertArrayNotHasKey( 'runs', $back['elements'][0] );
	}
}
