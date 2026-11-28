<?php
/**
 * The renderer mirrors the editor's model.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Render;

/**
 * Lengths, anchors, corner shapes and fields.
 */
class RenderTest extends Test_Case {

	/**
	 * Lengths in units, percentages and calc().
	 */
	public function test_parse_len() {
		$this->assertSame( 20.0, Render\parse_len( '20pt', 100 ) );
		$this->assertEqualsWithDelta( 28.3465, Render\parse_len( '10mm', 100 ), 0.0001 );
		$this->assertSame( 50.0, Render\parse_len( '50%', 100 ) );
		$this->assertSame( 60.0, Render\parse_len( 'calc(100% - 40pt)', 100 ) );
		$this->assertSame( 12.0, Render\parse_len( 12, 100 ) );
		$this->assertSame( 0.0, Render\parse_len( '', 100 ) );
	}

	/**
	 * Anchors resolve against the page or the safe margin.
	 */
	public function test_resolve_anchors() {
		$page = array(
			'w'    => 842,
			'h'    => 595,
			'safe' => 28,
		);
		$el   = Render\resolve(
			array(
				'type'   => 'text',
				'layout' => array(
					'ref' => 'safe',
					'h'   => array(
						'mode' => 'center',
						'size' => '200pt',
					),
					'v'   => array(
						'mode' => 'end',
						'end'  => '10pt',
						'size' => '20pt',
					),
				),
			),
			$page
		);
		$this->assertEqualsWithDelta( 321.0, $el['x'], 0.001 );
		$this->assertEqualsWithDelta( 200.0, $el['w'], 0.001 );
		$this->assertEqualsWithDelta( 595 - 28 - 10 - 20, $el['y'], 0.001 );
	}

	/**
	 * Corner shapes become path data, radii clamped to half the side.
	 */
	/**
	 * A kept aspect ratio: the height follows the width, or the width follows a filled height.
	 */
	public function test_resolve_locked_ratio() {
		$page = array(
			'w'    => 800,
			'h'    => 600,
			'safe' => 20,
		);
		$el   = array(
			'type'   => 'svg',
			'lock'   => 2,
			'layout' => array(
				'ref' => 'page',
				'h'   => array(
					'mode'  => 'stretch',
					'start' => '0pt',
					'end'   => '0pt',
				),
				'v'   => array(
					'mode'  => 'center',
					'start' => '0pt',
					'size'  => '50pt',
				),
			),
		);
		$r    = Render\resolve( $el, $page );
		$this->assertEqualsWithDelta( 400, $r['h'], 0.01 );
		$this->assertEqualsWithDelta( 100, $r['y'], 0.01 );

		$el['layout']['h']   = array(
			'mode' => 'end',
			'end'  => '0pt',
			'size' => '10pt',
		);
		$el['layout']['v']   = array(
			'mode'  => 'stretch',
			'start' => '0pt',
			'end'   => '0pt',
		);
		$el['layout']['ref'] = 'safe';
		$r                   = Render\resolve( $el, $page );
		$this->assertEqualsWithDelta( 1120, $r['w'], 0.01 );
		$this->assertEqualsWithDelta( 780 - 1120, $r['x'], 0.01 );

		$image = array(
			'type'   => 'image',
			'ratio'  => '4/3',
			'layout' => $el['layout'],
		);
		$this->assertEqualsWithDelta( 560 * 4 / 3, Render\resolve( $image, $page )['w'], 0.01 );
	}

	/**
	 * Arcs become cubic curves for dompdf, relative commands absolute, compact flags read.
	 */
	public function test_without_arcs() {
		$d = Render\without_arcs( 'M10 10 a5 5 0 1 0 10 0 a5 5 0 01-10 0z m2 2h3v3H12z' );
		$this->assertStringNotContainsString( 'A', strtoupper( $d ) );
		$this->assertStringContainsString( '15 15', $d ); // Both half circles pass through the bottom.
		$this->assertStringEndsWith( 'M12 12L15 12L15 15L12 15Z', $d );
		$this->assertSame( 'M0 0L5 5', Render\without_arcs( 'M0 0 L5 5' ) );
	}

	/**
	 * The signer follows the current one, like the design; the recorded one only when nobody signs.
	 */
	public function test_signer_follows_current() {
		$cert    = array(
			'code'      => '2611-04K7-M29Q-XAP3',
			'data_json' => wp_json_encode(
				array(
					'signer_name'  => 'Recorded Signer',
					'signer_roles' => array( 'en_US' => 'Old role' ),
				)
			),
		);
		$current = \EventsCertificate\Signer\current();
		$values  = Render\values( $cert );
		$this->assertSame( $current ? $current['name'] : 'Recorded Signer', $values['signer_name'] );
		if ( ! $current ) {
			$this->assertSame( 'Old role', $values['signer_role'] );
		}
	}

	/**
	 * The signature element is a place for the signer's signature (docs/decisions/0048): it draws it, upright, and has no drawing of its own.
	 */
	public function test_the_signature_is_a_place_for_the_signers_signature() {
		$design = array(
			'page'     => array(
				'w'     => 200,
				'h'     => 100,
				'bg'    => '#fff',
				'safe'  => 0,
				'bleed' => 0,
			),
			'colors'   => array(
				'text' => array(
					'light' => '#000',
					'dark'  => '#fff',
				),
			),
			'elements' => array(),
		);
		$place  = array(
			'id'    => 'signature',
			'type'  => 'signature',
			'x'     => 10,
			'y'     => 10,
			'w'     => 120,
			'h'     => 40,
			'color' => '$text',
		);
		$opts   = array( 'mode' => 'light' );
		$signer = (string) wp_json_encode(
			array(
				'vw'    => 600,
				'vh'    => 200,
				'paths' => array( array( 'd' => 'M0 0L600 200L0 200Z' ) ),
			)
		);
		$with   = Render\element_html( $place, $design, array( 'signature' => $signer ), $opts );
		$this->assertStringContainsString( '<img', $with, 'The signer\'s signature is drawn in the place.' );
		$this->assertSame( '', Render\element_html( $place, $design, array(), $opts ), 'No signature: nothing, not even in a preview.' );
		$this->assertSame(
			'',
			Render\element_html(
				$place,
				$design,
				array(),
				array(
					'mode'    => 'light',
					'preview' => true,
				)
			)
		);
		$picture = Render\element_html( $place, $design, array( 'signature_image' => 'data:image/png;base64,AAAA' ), $opts );
		$this->assertStringContainsString( 'object-fit:contain', $picture, 'A picture of the signature fills the place.' );
		$own = array_merge(
			$place,
			array(
				'paths' => array( array( 'd' => 'M0 0L9 9Z' ) ),
				'vw'    => 10,
				'vh'    => 10,
			)
		);
		$this->assertSame( '', Render\element_html( $own, $design, array(), $opts ), 'A drawing of its own is not drawn.' );
	}

	/**
	 * Sites that aren't public draw no QR code, code, verification hint or address.
	 */
	public function test_non_public_sites_drop_verification() {
		$design = array(
			'page'   => array(
				'w'  => 100,
				'h'  => 100,
				'bg' => '#fff',
			),
			'colors' => array(
				'text' => array(
					'light' => '#000',
					'dark'  => '#fff',
				),
			),
		);
		$values = Render\values( array( 'code' => '2611-04K7-M29Q-XAP3' ) );
		$opts   = array( 'mode' => 'light' );
		$qr     = array(
			'type' => 'qr',
			'x'    => 0,
			'y'    => 0,
			'w'    => 10,
			'h'    => 10,
		);
		$hint   = array(
			'type' => 'text',
			'slot' => 'verify',
			'x'    => 0,
			'y'    => 0,
			'w'    => 10,
			'h'    => 10,
			'runs' => array( array( 'field' => 'verify_url' ) ),
		);
		$code   = array(
			'type' => 'text',
			'slot' => 'code',
			'x'    => 0,
			'y'    => 0,
			'w'    => 10,
			'h'    => 10,
			'runs' => array( array( 'field' => 'code' ) ),
		);

		add_filter( 'evcert_is_public_site', '__return_false' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the plugin's own hook.
		$this->assertSame( '', Render\element_html( $qr, $design, $values, $opts ) );
		$this->assertSame( '', Render\element_html( $hint, $design, $values, $opts ) );
		$this->assertSame( '', Render\element_html( $code, $design, $values, $opts ) );
		remove_filter( 'evcert_is_public_site', '__return_false' );

		add_filter( 'evcert_is_public_site', '__return_true' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the plugin's own hook.
		$this->assertNotSame( '', Render\element_html( $qr, $design, $values, $opts ) );
		$this->assertNotSame( '', Render\element_html( $hint, $design, $values, $opts ) );
		remove_filter( 'evcert_is_public_site', '__return_true' );
	}

	public function test_rect_path() {
		$this->assertSame( 'M0 0L10 0L10 10L0 10Z', Render\rect_path( 0, 0, 10, 10 ) );
		// Round corners are cubic curves bulging toward the corner (dompdf draws SVG arcs wrong).
		$this->assertSame( 'M0 5C0 2.24 2.24 0 5 0L10 0L10 10L0 10Z', Render\rect_path( 0, 0, 10, 10, array( 50, 0, 0, 0 ) ) );
		$this->assertStringNotContainsString( 'A', Render\rect_path( 0, 0, 10, 10, array( 2, 2, 2, 2 ), 'scoop' ) );
	}

	/**
	 * Fields read the certificate's frozen values; the event's name is the current one (the site's when it has one).
	 */
	public function test_fields() {
		$values = Render\values(
			array(
				'name'        => 'Ana Lima',
				'event_title' => 'WordCamp Test',
				'hours'       => '8.00',
				'role'        => 'attendee',
				'code'        => '2611-04K7-M29Q-XAP3',
				'issued_at'   => '2026-10-31 15:00:00',
			)
		);
		$this->assertSame( 'Ana Lima', Render\field_value( 'name', '', $values ) );
		$this->assertSame( '8 hours', Render\field_value( 'hours', '', $values ) );
		$this->assertSame( '8h', Render\field_value( 'hours', 'short', $values ) );
		$this->assertSame( 'Attendee', Render\field_value( 'role', '', $values ) );
		$this->assertSame( '2026-10-31', Render\field_value( 'issued_date', 'iso', $values ) );
		$this->assertSame(
			'<strong>' . esc_html( Render\event_title( array( 'event_title' => 'WordCamp Test' ) ) ) . '</strong> &amp; more',
			Render\runs_html(
				array(
					'runs' => array(
						array(
							'field' => 'event',
							'b'     => true,
						),
						array( 'text' => ' & more' ),
					),
				),
				$values
			)
		);
	}

	/**
	 * A rectangle's corner radius may be a percentage of its shorter side: 50% is a pill (the same path as half the shorter side in points).
	 */
	public function test_a_rect_radius_may_be_a_percentage() {
		$design                          = array(
			'page'     => array(
				'w'  => 200,
				'h'  => 100,
				'bg' => '$background',
			),
			'colors'   => array(),
			'elements' => array(
				array(
					'id'     => 'pill',
					'type'   => 'rect',
					'x'      => 10,
					'y'      => 10,
					'w'      => 100,
					'h'      => 40,
					'fill'   => '#112233',
					'radius' => array( '50%', '50%', '50%', '50%' ),
				),
			),
		);
		$percent                         = Render\artwork( $design, array(), array() );
		$design['elements'][0]['radius'] = array( 20, 20, 20, 20 );
		$points                          = Render\artwork( $design, array(), array() );

		$this->assertSame( $points, $percent, '50% of the shorter side (40) is 20 points.' );
		$design['elements'][0]['radius'] = array( '25%', '25%', '25%', '25%' );
		$quarter                         = Render\artwork( $design, array(), array() );
		$design['elements'][0]['radius'] = array( 10, 10, 10, 10 );
		$this->assertSame( Render\artwork( $design, array(), array() ), $quarter, '25% of the shorter side (40) is 10 points.' );
		$this->assertNotSame( $points, $quarter );
	}

	/**
	 * A margin and a bleed may be kept in any unit: the page reads them as points.
	 */
	public function test_a_margin_and_a_bleed_may_be_in_any_unit() {
		$design = \EventsCertificate\Vendor\Camaleaun\Draw\Page::in_points(
			array(
				'page' => array(
					'width'  => '297mm',
					'height' => '210mm',
					'safe'   => '28.35pt',
					'bleed'  => '0.5in',
				),
			)
		);
		$page   = $design['page'];

		$this->assertEqualsWithDelta( 28.35, $page['safe'], 0.01 );
		$this->assertEqualsWithDelta( 36.0, $page['bleed'], 0.01 );
	}

	/**
	 * A group: its own box, and what is in it placed from its corner and anchored to its size, shown even where it goes out of the box unless the group clips.
	 */
	public function test_a_group_holds_its_elements_from_its_corner() {
		$group  = array(
			'id'       => 'box',
			'type'     => 'group',
			'x'        => 100,
			'y'        => 50,
			'w'        => 200,
			'h'        => 80,
			'fill'     => '#eeeeee',
			'children' => array(
				array(
					'id'     => 'box-rect-1',
					'type'   => 'rect',
					'fill'   => '#ff0000',
					'layout' => array(
						'ref' => 'parent',
						'h'   => array(
							'mode'  => 'stretch',
							'start' => '10pt',
							'end'   => '10pt',
						),
						'v'   => array(
							'mode'  => 'start',
							'start' => '5pt',
							'size'  => '20pt',
						),
					),
				),
			),
		);
		$design = array(
			'page'     => array(
				'w'    => 800,
				'h'    => 600,
				'safe' => 0,
				'bg'   => '$background',
			),
			'colors'   => array(),
			'elements' => array( $group ),
		);
		$html   = Render\artwork( $design, array(), array() );
		// It shows what goes out of it by default, so there is no container: its child is on the page at the corner of the group plus 10, 5, with the width of the group less the two margins.
		$this->assertStringNotContainsString( 'overflow:visible', $html );
		$this->assertMatchesRegularExpression( '/left:110\.00pt;top:55\.00pt;width:180\.00pt;height:20\.00pt/', $html );
		$group['clip']      = true;
		$design['elements'] = array( $group );
		$clipped            = Render\artwork( $design, array(), array() );
		$this->assertMatchesRegularExpression( '/left:100\.00pt;top:50\.00pt;width:200\.00pt;height:80\.00pt;[^"]*overflow:hidden;/', $clipped, 'A group that clips cuts what goes out of it.' );
		$this->assertMatchesRegularExpression( '/left:10\.00pt;top:5\.00pt;width:180\.00pt;height:20\.00pt/', $clipped, 'and places what is in it from its corner.' );
		// Out of a group the same anchors are the page's.
		$loose = Render\resolve( $group['children'][0], $design['page'] );
		$this->assertEqualsWithDelta( 780.0, $loose['w'], 0.001 );
	}

	/**
	 * An ellipse is drawn with its outline, centered on the edge.
	 */
	public function test_an_ellipse_has_its_outline() {
		$design = array(
			'page'     => array(
				'w'    => 800,
				'h'    => 600,
				'safe' => 0,
				'bg'   => '$background',
			),
			'colors'   => array(),
			'elements' => array(
				array(
					'id'          => 'ring',
					'type'        => 'ellipse',
					'x'           => 10,
					'y'           => 10,
					'w'           => 80,
					'h'           => 80,
					'fill'        => '',
					'stroke'      => '#ff0000',
					'strokeWidth' => 4,
				),
			),
		);
		$html   = Render\artwork( $design, array(), array() );
		$this->assertStringContainsString( 'left:8.00pt;top:8.00pt;width:84.00pt;height:84.00pt', $html, 'The picture is bigger by half the outline on each side.' );
		$this->assertStringContainsString( 'src="data:image/svg+xml', $html );
	}

	/**
	 * A group that is a row places what is in it one after the other, a stack one under the other.
	 */
	public function test_a_row_group_places_its_elements_in_a_row() {
		$kids   = array(
			array(
				'id'   => 'a',
				'type' => 'rect',
				'fill' => '#111111',
				'w'    => 40,
				'h'    => 10,
			),
			array(
				'id'   => 'b',
				'type' => 'rect',
				'fill' => '#222222',
				'w'    => 30,
				'h'    => 10,
			),
		);
		$design = array(
			'page'     => array(
				'w'    => 800,
				'h'    => 600,
				'safe' => 0,
				'bg'   => '$background',
			),
			'colors'   => array(),
			'elements' => array(
				array(
					'id'       => 'row',
					'type'     => 'group',
					'x'        => 100,
					'y'        => 50,
					'w'        => 200,
					'h'        => 60,
					'arrange'  => 'row',
					'gap'      => 10,
					'padding'  => 5,
					'children' => $kids,
				),
			),
		);
		$html   = Render\artwork( $design, array(), array() );
		$this->assertMatchesRegularExpression( '/left:105\.00pt;top:55\.00pt;width:40\.00pt;height:10\.00pt/', $html );
		$this->assertMatchesRegularExpression( '/left:155\.00pt;top:55\.00pt;width:30\.00pt;height:10\.00pt/', $html );
		$design['elements'][0]['arrange'] = 'stack';
		$this->assertMatchesRegularExpression( '/left:105\.00pt;top:75\.00pt;width:30\.00pt;height:10\.00pt/', Render\artwork( $design, array(), array() ) );
	}

	/**
	 * A design with a group is kept as blocks and read back the same: the group, what is in it (down the levels) and the places, with no id twice.
	 */
	public function test_a_group_survives_being_kept_and_read() {
		$design = array(
			'page'     => array(
				'w'    => 842,
				'h'    => 595,
				'safe' => 0,
				'bg'   => '$background',
			),
			'colors'   => array(),
			'elements' => array(
				array(
					'id'       => 'box',
					'type'     => 'group',
					'x'        => 100,
					'y'        => 50,
					'w'        => 300,
					'h'        => 120,
					'arrange'  => 'row',
					'gap'      => 10,
					'padding'  => 8,
					'justify'  => 'between',
					'clip'     => true,
					'children' => array(
						array(
							'id'   => 'box-rect-1',
							'type' => 'rect',
							'w'    => 40,
							'h'    => 20,
							'fill' => '#112233',
							'grow' => 1,
						),
						array(
							'id'       => 'box-group-2',
							'type'     => 'group',
							'x'        => 0,
							'y'        => 0,
							'w'        => 60,
							'h'        => 60,
							'children' => array(
								array(
									'id'   => 'box-group-2-rect-1',
									'type' => 'rect',
									'x'    => 5,
									'y'    => 5,
									'w'    => 10,
									'h'    => 10,
								),
							),
						),
					),
				),
			),
		);
		$markup = \EventsCertificate\Designs\content( $design );
		$this->assertStringContainsString( '<!-- wp:events-certificates/certificate-group ', $markup );
		$this->assertStringNotContainsString( '"children"', $markup );
		$back = \EventsCertificate\Designs\decode( $markup );
		$box  = $back['elements'][0];
		$this->assertSame( 'group', $box['type'] );
		$this->assertSame( 'row', $box['arrange'] );
		$this->assertTrue( $box['clip'] );
		$this->assertSame( 2, count( $box['children'] ) );
		$this->assertSame( 'group', $box['children'][1]['type'] );
		$this->assertEquals( 10, $box['children'][1]['children'][0]['w'] );
		$ids  = array();
		$walk = static function ( $items ) use ( &$walk, &$ids ) {
			foreach ( $items as $el ) {
				$ids[] = $el['id'];
				$walk( $el['children'] ?? array() );
			}
		};
		$walk( $back['elements'] );
		$this->assertSame( count( $ids ), count( array_unique( $ids ) ), implode( ',', $ids ) );
		// And it draws, the same after the trip as before.
		$this->assertSame( Render\artwork( $design, array(), array() ), Render\artwork( $back, array(), array() ) );
	}

	/**
	 * The PDF of a design with a group is made (the group draws as a rectangle and a container, which the PDF reads).
	 */
	public function test_a_design_with_a_group_has_a_pdf() {
		$design = array(
			'page'     => array(
				'w'    => 842,
				'h'    => 595,
				'safe' => 0,
				'bg'   => '$background',
			),
			'colors'   => array(),
			'elements' => array(
				array(
					'id'       => 'box',
					'type'     => 'group',
					'x'        => 100,
					'y'        => 50,
					'w'        => 300,
					'h'        => 120,
					'fill'     => '#eeeeee',
					'arrange'  => 'stack',
					'gap'      => 6,
					'clip'     => true,
					'children' => array(
						array(
							'id'   => 'a',
							'type' => 'rect',
							'w'    => 100,
							'h'    => 20,
							'fill' => '#ff0000',
						),
						array(
							'id'   => 'b',
							'type' => 'rect',
							'w'    => 100,
							'h'    => 20,
							'fill' => '#0000ff',
						),
					),
				),
			),
		);
		$pdf    = Render\pdf( array( 'id' => 0 ), $design );
		$this->assertIsString( $pdf );
		$this->assertSame( '%PDF', substr( $pdf, 0, 4 ) );
	}

	/**
	 * A drawing that says no size on an axis is as big as its viewBox, like camaleaun/draw's resolve() says; one that says it has it.
	 */
	public function test_an_svg_with_no_size_is_as_big_as_its_viewbox() {
		$page = array(
			'w'    => 800,
			'h'    => 600,
			'safe' => 0,
		);
		$svg  = array(
			'type'   => 'svg',
			'vw'     => 24,
			'vh'     => 12,
			'layout' => array(
				'h' => array(
					'mode'  => 'start',
					'start' => '10pt',
				),
				'v' => array(
					'mode'  => 'center',
					'start' => '0pt',
				),
			),
		);
		$out  = Render\resolve( $svg, $page );
		$this->assertEqualsWithDelta( 24.0, $out['w'], 0.001 );
		$this->assertEqualsWithDelta( 12.0, $out['h'], 0.001 );
		$this->assertEqualsWithDelta( 294.0, $out['y'], 0.001, 'Centered with its own height.' );
		$svg['layout']['h']['size'] = '50pt';
		$this->assertEqualsWithDelta( 50.0, Render\resolve( $svg, $page )['w'], 0.001 );
		$rect = array(
			'type'   => 'rect',
			'vw'     => 24,
			'layout' => array(
				'h' => array(
					'mode'  => 'start',
					'start' => '0pt',
				),
				'v' => array(
					'mode'  => 'start',
					'start' => '0pt',
				),
			),
		);
		$this->assertEqualsWithDelta( 0.0, Render\resolve( $rect, $page )['w'], 0.001, 'Only a drawing has a size of its own.' );
	}
}
