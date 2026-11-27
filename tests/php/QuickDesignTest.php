<?php
/**
 * The two templates a site starts with (Basic and Meetup) and the template the Quick generator draws.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Bundle;
use EventsCertificate\Designs;
use EventsCertificate\Generator;
use EventsCertificate\QuickGenerator;
use EventsCertificate\Templates;

/**
 * A fresh site (no options) makes Basic, the default, and Meetup, the Quick generator's; a Meetup that goes away leaves the default.
 */
class QuickDesignTest extends Test_Case {

	/**
	 * The options before the test.
	 *
	 * @var array<string, mixed>
	 */
	private $options = array();

	/**
	 * The templates there were before the test.
	 *
	 * @var int[]
	 */
	private $known = array();

	/**
	 * Remembers the site as it was.
	 */
	protected function set_up() {
		parent::set_up();
		foreach ( array( Designs\SITE_DESIGN_OPTION, Designs\QUICK_DESIGN_OPTION ) as $option ) {
			$this->options[ $option ] = get_option( $option );
		}
		$this->known = $this->all();
	}

	/**
	 * Takes the templates this test made off the site and puts its options back.
	 */
	protected function tear_down() {
		foreach ( array_diff( $this->all(), $this->known ) as $id ) {
			wp_delete_post( (int) $id, true );
		}
		foreach ( $this->options as $option => $value ) {
			if ( false === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value, false );
			}
		}
		remove_all_filters( 'evcert_quick_design_id' );
		parent::tear_down();
	}

	/**
	 * Every template, whatever its status.
	 *
	 * @return int[]
	 */
	private function all() {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'   => Designs\POST_TYPE,
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
	}

	/**
	 * A site that has made none: its two templates are made the first time one is asked for.
	 *
	 * @param bool $clear Whether to delete every template first (a site with none).
	 * @return array{0: int, 1: int} The default and the Quick generator's post IDs.
	 */
	private function fresh_site( $clear = false ) {
		if ( $clear ) {
			foreach ( get_posts(
				array(
					'post_type'   => Designs\POST_TYPE,
					'post_status' => 'any',
					'numberposts' => -1,
				)
			) as $post ) { // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
				wp_delete_post( $post->ID, true );
			}
		}
		delete_option( Designs\SITE_DESIGN_OPTION );
		delete_option( Designs\QUICK_DESIGN_OPTION );

		return array( Designs\site_design_id(), (int) get_option( Designs\QUICK_DESIGN_OPTION ) );
	}

	/**
	 * Basic (the default) and Meetup (the Quick generator's) are made once, in this order, and Meetup has no roles.
	 */
	public function test_a_new_site_starts_with_basic_and_meetup() {
		list( $basic, $meetup ) = $this->fresh_site();

		$made = array_values( array_diff( $this->all(), $this->known ) );
		sort( $made );
		$this->assertSame( array( $basic, $meetup ), $made );
		$this->assertSame( 'Basic', get_the_title( $basic ) );
		$this->assertSame( 'Meetup WordPress', get_the_title( $meetup ) );
		$this->assertSame( 0, (int) get_post( $basic )->menu_order );
		$this->assertSame( 1, (int) get_post( $meetup )->menu_order );
		$this->assertSame( $basic, Designs\site_design_id() );
		$this->assertSame( $meetup, Designs\quick_design_id() );
		$this->assertSame( array(), Designs\roles( $meetup ), 'No role picks it: it is not for certificates.' );

		$band = array_values(
			array_filter(
				Designs\get( $meetup )['elements'],
				static function ( $element ) {
					return 'band-text' === $element['id'];
				}
			)
		);
		$this->assertSame( 'Meetup WordPress', $band[0]['runs'][0]['text'] );
		$basic_ids = array_column( Designs\get( $basic )['elements'], 'id' );
		$this->assertNotContains( 'band-text', $basic_ids, 'Basic is a plain centred column: no band.' );
		$this->assertContains( 'background', $basic_ids, 'It has a background of its own, as the old generators had.' );
		$fields = array();
		foreach ( Designs\get( $basic )['elements'] as $element ) {
			foreach ( (array) ( $element['runs'] ?? array() ) as $run ) {
				$fields[] = $run['field'] ?? '';
			}
		}
		foreach ( array( 'community', 'speakers', 'location', 'event_time', 'event_link' ) as $field ) {
			$this->assertNotContains( $field, $fields, 'The Quick generator\'s data is not in Basic: ' . $field );
		}
	}

	/**
	 * Basic is as simple as makes sense: the title, the name on a band, one sentence (what the person did and the event), the hours,
	 * the signature and the date of issue, with no texts of its own to translate beyond the ones every template has.
	 */
	public function test_basic_is_as_simple_as_it_makes_sense() {
		$design = Designs\basic();
		$this->assertSame(
			array( 'background', 'title', 'intro', 'name', 'took-part', 'hours-label', 'hours', 'signature', 'signature-line', 'signer', 'signer-role', 'issued-on' ),
			array_column( $design['elements'], 'id' )
		);
		$background = array_values( array_filter( $design['elements'], static fn ( $element ) => 'background' === $element['id'] ) )[0];
		$this->assertSame( 'svg', $background['type'], 'One drawing of filled shapes (the guilloche and the band of the name) in a color of the template: it follows the light and the dark.' );
		$this->assertSame( '$line', $background['color'] );
		$this->assertLessThanOrEqual( \EventsCertificate\Signer\SVG_MAX_PATHS, count( $background['paths'] ) );
		foreach ( $background['paths'] as $path ) {
			$this->assertLessThanOrEqual( \EventsCertificate\Signer\SVG_MAX_PATH_DATA, strlen( $path['d'] ), 'Each path is under the limit of a drawing.' );
			$this->assertTrue( \EventsCertificate\Render\is_path_data( $path['d'] ) );
		}
		$signature = array_values( array_filter( $design['elements'], static fn ( $element ) => 'signature' === $element['id'] ) )[0];
		$this->assertSame( 'signature', $signature['type'], 'A place for the signer\'s signature (Settings or the event), with no drawing of its own.' );
		$this->assertArrayNotHasKey( 'paths', $signature );
		$title = array_values( array_filter( $design['elements'], static fn ( $element ) => 'title' === $element['id'] ) )[0];
		$this->assertSame( 'title_plain', $title['runs'][0]['field'], 'The title is the same word for every role.' );
		$literal = array();
		foreach ( $design['elements'] as $element ) {
			foreach ( (array) ( $element['runs'] ?? array() ) as $run ) {
				if ( isset( $run['text'] ) && 1 === preg_match( '/\p{L}/u', $run['text'] ) ) { // Punctuation and spaces are not words.
					$literal[] = $run['text'];
				}
			}
		}
		$this->assertSame( array(), $literal, 'Every word is a phrase the plugin translates (Render\\phrases()), none is written into the design.' );
		$label = array_values( array_filter( $design['elements'], static fn ( $element ) => 'hours-label' === $element['id'] ) )[0];
		$this->assertSame( 'hours_label', $label['runs'][0]['field'], 'The hours say what they are: "Workload:" and, below it, "8 hours".' );
	}

	/**
	 * Basic leaves the logo and the verification to the editor (the old generators put what is fixed in the background): it has none of
	 * them, and an event's logo still shows, in the corner every design without a place for it gives.
	 */
	public function test_basic_has_no_logo_nor_verification_and_the_events_logo_still_shows() {
		$design = Designs\basic();
		$ids    = array_column( $design['elements'], 'id' );
		$types  = array_column( $design['elements'], 'type' );
		$this->assertNotContains( 'logo', $ids );
		$this->assertNotContains( 'verification', $types );

		$png  = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
		$html = \EventsCertificate\Render\pdf_body(
			array(
				array(
					array(
						'name'   => 'Ana',
						'code'   => '2611-ABCD-EFGH-JKLM',
						'status' => 'issued',
					),
					$design,
					array( 'values' => array( 'logo_image' => $png ) ),
				),
			),
			static function ( $text, $family, $weight, $size ) {
				return strlen( (string) $text ) * $size * 0.5;
			}
		);
		$this->assertStringContainsString( $png, $html, 'The logo of the event is drawn though the design has no place for it.' );
	}

	/**
	 * The text keeps the WCAG minimum (4.5:1) on the page and on the shapes of the background, in the light and the dark mode.
	 */
	public function test_the_basic_text_is_readable_on_the_background() {
		$design     = Designs\basic();
		$background = array_values( array_filter( $design['elements'], static fn ( $element ) => 'background' === $element['id'] ) )[0];
		$channels   = static fn ( $hex ) => array_map( 'hexdec', str_split( ltrim( $hex, '#' ), 2 ) );
		$luminance  = static function ( $hex ) use ( $channels ) {
			$c = array_map( static fn ( $v ) => ( $v /= 255 ) <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 ), $channels( $hex ) );
			return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
		};
		$ratio      = static function ( $a, $b ) use ( $luminance ) {
			$x = $luminance( $a );
			$y = $luminance( $b );
			return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
		};
		foreach ( array( 'light', 'dark' ) as $mode ) {
			$page  = $design['colors']['background'][ $mode ];
			$ink   = $design['colors'][ ltrim( $background['color'], '$' ) ][ $mode ];
			$shape = sprintf( '#%02x%02x%02x', ...array_map( static fn ( $p, $i ) => (int) round( $i * ( 1 - $background['opacity'] ) + $p * $background['opacity'] ), $channels( $ink ), $channels( $page ) ) );
			foreach ( array( 'text', 'muted' ) as $token ) {
				$text = $design['colors'][ $token ][ $mode ];
				$this->assertGreaterThanOrEqual( 4.5, $ratio( $text, $page ), "$token on the page, $mode" );
				$this->assertGreaterThanOrEqual( 4.5, $ratio( $text, $shape ), "$token on the shapes, $mode" );
			}
		}
	}

	/**
	 * The PNG of the background (the same drawing, kept in the plugin) is grey and transparency only, and keeps the text readable (4.5:1) on the light page and on the dark one.
	 */
	public function test_the_basic_background_picture_keeps_the_text_readable() {
		$png = (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/basic-background.png' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file of the plugin.
		$this->assertSame( "\x89PNG\r\n\x1a\n", substr( $png, 0, 8 ) );
		$this->assertSame( 3, ord( $png[25] ), 'Indexed (compressed): one grey, with a transparency for each step.' );
		$this->assertStringContainsString( 'tRNS', $png, 'With transparency.' );
		$this->assertLessThan( 80000, strlen( $png ), 'Compressed.' );
		$this->assertSame( array( 2246, 1588 ), array_values( unpack( 'N2', substr( $png, 16, 8 ) ) ), 'A4 landscape at 192 dpi.' );
		$image = imagecreatefromstring( $png );
		$this->assertNotFalse( $image );
		imagesavealpha( $image, true );
		$pixel = imagecolorsforindex( $image, imagecolorat( $image, 2200, 600 ) ); // The band reaches the edges: a solid part of the drawing.
		$alpha = ( 127 - $pixel['alpha'] ) / 127;
		$this->assertGreaterThan( 0.1, $alpha, 'It is on the band.' );
		$design    = Designs\basic();
		$luminance = static function ( array $rgb ) {
			$c = array_map( static fn ( $v ) => ( $v /= 255 ) <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 ), $rgb );
			return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
		};
		foreach ( array( 'light', 'dark' ) as $mode ) {
			$page  = array_map( 'hexdec', str_split( ltrim( $design['colors']['background'][ $mode ], '#' ), 2 ) );
			$shape = array_map( static fn ( $p ) => $p * ( 1 - $alpha ) + $pixel['red'] * $alpha, $page );
			foreach ( array( 'text', 'muted' ) as $token ) {
				$text = array_map( 'hexdec', str_split( ltrim( $design['colors'][ $token ][ $mode ], '#' ), 2 ) );
				$a    = $luminance( $text );
				$b    = $luminance( $shape );
				$this->assertGreaterThanOrEqual( 4.5, ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 ), "$token on the PNG, $mode" );
			}
		}
	}

	/**
	 * The same drawing is kept as an SVG to look at and edit.
	 */
	public function test_the_basic_background_has_a_vector_source() {
		$svg = (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/basic-background.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file of the plugin.
		$this->assertStringContainsString( 'viewBox="0 0 1123 794"', $svg );
	}

	/**
	 * Asking again makes nothing; a default made again does not bring a second Meetup.
	 */
	public function test_nothing_is_made_twice() {
		$this->fresh_site();
		$before = count( $this->all() );

		Designs\templates();
		Designs\site_design_id();
		Designs\quick_design_id();
		$this->assertCount( $before, $this->all() );

		delete_option( Designs\SITE_DESIGN_OPTION );
		Designs\site_design_id();
		$this->assertCount( $before + 1, $this->all(), 'Only the default is made again.' );
	}

	/**
	 * With no choice (a site that never had one), the Quick generator draws the Meetup starter: its band says "Meetup WordPress" along
	 * its side; without Meetup it draws the default template.
	 */
	public function test_the_quick_generator_without_a_choice_draws_meetup() {
		list( $basic, $meetup ) = $this->fresh_site( true );

		delete_option( Designs\QUICK_DESIGN_OPTION );
		$this->assertSame( $meetup, Designs\quick_design_id() );
		$band = array_values( array_filter( Designs\get( Designs\quick_design_id() )['elements'], static fn ( $element ) => 'band-text' === $element['id'] ) )[0];
		$this->assertSame( -90, $band['rotation'], 'Along the side of the band.' );
		$this->assertSame( 'Meetup WordPress', $band['runs'][0]['text'] );

		wp_trash_post( $meetup );
		$this->assertSame( $basic, Designs\quick_design_id(), 'No Meetup: the default one.' );
	}

	/**
	 * A Quick generator's template that is trashed, deleted or not a template leaves the Meetup starter, else the default one; a
	 * filter can choose another.
	 */
	public function test_the_quick_generator_falls_back_to_the_default() {
		list( $basic, $meetup ) = $this->fresh_site( true );

		wp_trash_post( $meetup );
		$this->assertSame( $basic, Designs\quick_design_id(), 'In the Trash.' );
		wp_untrash_post( $meetup );
		wp_update_post(
			array(
				'ID'          => $meetup,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( $meetup, Designs\quick_design_id() );

		$page = (int) wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Not a template',
			)
		);
		update_option( Designs\QUICK_DESIGN_OPTION, $page, false );
		$this->assertSame( $meetup, Designs\quick_design_id(), 'Not a template: the Meetup starter.' );
		update_option( Designs\QUICK_DESIGN_OPTION, $meetup, false );

		add_filter(
			'evcert_quick_design_id',
			static function () use ( $basic ) {
				return $basic;
			}
		);
		$this->assertSame( $basic, Designs\quick_design_id(), 'The filter chose.' );
		remove_all_filters( 'evcert_quick_design_id' );

		wp_delete_post( $meetup, true );
		$this->assertSame( $basic, Designs\quick_design_id(), 'Deleted.' );
		wp_delete_post( $page, true );
	}

	/**
	 * A template is made the Quick generator's with set_quick(); a template in the Trash or a page is not accepted.
	 */
	public function test_a_template_is_made_the_quick_generators() {
		list( $basic, $meetup ) = $this->fresh_site();

		$this->assertTrue( Designs\set_quick( $basic ) );
		$this->assertSame( $basic, Designs\quick_design_id() );
		$this->assertFalse( Designs\set_quick( 999999 ) );
		$this->assertTrue( Designs\set_quick( $meetup ) );
		$this->assertSame( $meetup, Designs\quick_design_id() );
	}

	/**
	 * The public generator and the admin one draw the Quick generator's template, and the pages the admin one makes too, for every role.
	 */
	public function test_the_quick_generator_draws_its_template() {
		list( , $meetup ) = $this->fresh_site();

		$this->assertSame( Designs\get( $meetup ), QuickGenerator\config()['design'] );

		$items = Generator\items(
			array(
				'title' => 'A fictional meetup',
				'start' => '2026-11-25 19:00',
			),
			array(
				array(
					'name'  => 'Ana Example',
					'roles' => array( 'speaker', 'organizer' ),
				),
				array(
					'name'  => 'Bruno Example',
					'roles' => array(),
				),
			)
		);
		$this->assertCount( 3, $items );
		foreach ( $items as $item ) {
			$this->assertSame( Designs\get( $meetup ), $item[1] );
		}
	}

	/**
	 * The list says which template is the default and which is the Quick generator's, and offers to change it.
	 */
	public function test_the_list_marks_and_changes_it() {
		list( $basic, $meetup ) = $this->fresh_site();
		wp_set_current_user( 1 );
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

		$this->assertStringContainsString( 'Used by the Quick generator', $html );
		$this->assertStringContainsString( 'The Quick generator', $html, 'Applies to: the Quick generator, with no roles.' );
		$this->assertStringContainsString( 'evcert_do=quick&#038;template=' . $basic, $html, 'It can be made the Basic one.' );
		$this->assertStringNotContainsString( 'evcert_do=quick&#038;template=' . $meetup, $html, 'The one it already is has no link.' );
	}

	/**
	 * The export says which template is the Quick generator's, as it says which is the default.
	 */
	public function test_the_export_says_which_is_the_quick_generators() {
		list( $basic, $meetup ) = $this->fresh_site();
		$templates              = Bundle\export( array( 'templates' ) )['templates'];

		$quick = array_values(
			array_filter(
				$templates,
				static function ( $template ) {
					return $template['quick'];
				}
			)
		);
		$this->assertCount( 1, $quick );
		$this->assertSame( $meetup, $quick[0]['key'] );
		$this->assertSame(
			$basic,
			array_values(
				array_filter(
					$templates,
					static function ( $template ) {
						return $template['default'];
					}
				)
			)[0]['key']
		);
	}
}
