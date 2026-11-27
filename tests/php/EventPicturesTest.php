<?php
/**
 * The pictures an event can carry for its certificates: the community's logo and the signature, as an inline
 * image or as paths, set on the admin Generator screen.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Events;
use EventsCertificate\Pictures;
use EventsCertificate\Render;

/**
 * Only safe pictures are kept, and they reach what the PDF is drawn from.
 */
class EventPicturesTest extends Test_Case {

	/**
	 * What is kept of a picture is the ID of an attachment that is a raster picture: not a data URI, not an address, not an SVG, not a
	 * number that is no picture.
	 */
	public function test_only_a_raster_attachment_is_kept() {
		$id = $this->make_picture();
		$this->assertSame( $id, Pictures\clean( $id ) );
		$this->assertSame( $id, Pictures\clean( (int) $id ) );
		$this->assertSame( '', Pictures\clean( 'data:image/png;base64,iVBORw0KGgo=' ) );
		$this->assertSame( '', Pictures\clean( 'https://example.com/logo.png' ) );
		$this->assertSame( '', Pictures\clean( '999999' ), 'No such attachment.' );
		$this->assertSame(
			'',
			Pictures\clean(
				(string) wp_insert_post(
					array(
						'post_title'  => 'x',
						'post_status' => 'publish',
					)
				)
			),
			'A post that is not a picture.'
		);
		$svg = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/svg+xml',
				'post_title'     => 'x',
				'post_status'    => 'inherit',
			)
		);
		$this->assertSame( '', Pictures\clean( $svg ), 'Never an SVG: drawings are paths.' );
		$this->assertSame( '', Pictures\clean( array( 'x' ) ) );
		$this->assertSame( '', Pictures\clean( '0' ) );
	}

	/**
	 * The address of a picture is its attachment's, and a data URI is only taken at run time (as the Generator sends it).
	 */
	public function test_the_address_of_a_picture() {
		$id = $this->make_picture();
		$this->assertStringContainsString( '/uploads/', Pictures\src( $id ) );
		$this->assertSame( '', Pictures\src( '999999' ), 'A picture that was deleted shows nothing.' );
		$data = 'data:image/png;base64,iVBORw0KGgo=';
		$this->assertSame( $data, Pictures\src( $data ) );
		$this->assertSame( '', Pictures\src( 'data:image/svg+xml;base64,PHN2Zy8+' ) );
		$this->assertSame( '', Pictures\clean_runtime( 'data:image/png;base64,' . str_repeat( 'A', 1500000 ) ) );
	}

	/**
	 * A drawing keeps its valid paths and a sane viewBox, and nothing else.
	 */
	public function test_a_drawing_keeps_only_valid_paths() {
		$kept = json_decode(
			Events\clean_vector( '{"vw":120,"vh":44,"paths":[{"d":"M0 0L10 10Z","fill":"#112233","rule":"evenodd"},{"d":"<script>"},{"d":"M1 1h5","fill":"red"}]}' ),
			true
		);
		$this->assertCount( 2, $kept['paths'] );
		$this->assertSame( '#112233', $kept['paths'][0]['fill'] );
		$this->assertSame( 'evenodd', $kept['paths'][0]['rule'] );
		$this->assertArrayNotHasKey( 'fill', $kept['paths'][1], 'a color name is not a fill the engine knows' );
		$this->assertSame( '', Events\clean_vector( '{"vw":0,"vh":44,"paths":[{"d":"M0 0"}]}' ) );
		$this->assertSame( '', Events\clean_vector( '{"vw":9000,"vh":44,"paths":[{"d":"M0 0"}]}' ) );
		$this->assertSame( '', Events\clean_vector( '{"vw":10,"vh":10,"paths":[{"d":"nope"}]}' ) );
		$this->assertSame( '', Events\clean_vector( 'not json' ) );
	}

	/**
	 * A drawing may prefer a size to be shown at (an icon of 24 x 24 at 512 x 512): both numbers or none, and not the viewBox itself.
	 */
	public function test_a_drawing_may_prefer_a_size() {
		$path = '"paths":[{"d":"M0 0L10 10Z"}]';
		$got  = json_decode( Events\clean_vector( '{"vw":24,"vh":24,"w":512,"h":512,' . $path . '}' ), true );
		$this->assertEquals( 512, $got['w'] );
		$this->assertEquals( 512, $got['h'] );
		foreach ( array( '"w":512', '"w":512,"h":0', '"w":-1,"h":5', '"w":24,"h":24', '"w":99999,"h":10', '"w":"x","h":"y"' ) as $bad ) {
			$got = json_decode( Events\clean_vector( '{"vw":24,"vh":24,' . $bad . ',' . $path . '}' ), true );
			$this->assertArrayNotHasKey( 'w', $got, $bad );
			$this->assertArrayNotHasKey( 'h', $got, $bad );
		}
		$sign = \EventsCertificate\Signer\sanitize_signature( json_decode( '{"vw":24,"vh":24,"w":512,"h":256,' . $path . '}', true ) );
		$this->assertEquals( 256, $sign['h'] );
	}

	/**
	 * The preferred size is a proportion inside the element's box: centered, as large as it fits.
	 */
	public function test_a_preferred_size_shapes_the_drawing_inside_its_box() {
		$box = array(
			'x' => 10,
			'y' => 10,
			'w' => 200,
			'h' => 100,
		);
		$fit = Render\fit_preferred(
			$box,
			array(
				'w' => 512,
				'h' => 512,
			)
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$this->assertEquals( array( 60, 10, 100, 100 ), array( $fit['x'], $fit['y'], $fit['w'], $fit['h'] ), 'A square in a wide box: centered.' );
		$wide = Render\fit_preferred(
			$box,
			array(
				'w' => 400,
				'h' => 100,
			)
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$this->assertEquals( array( 10, 35, 200, 50 ), array( $wide['x'], $wide['y'], $wide['w'], $wide['h'] ) );
		$this->assertSame( $box, Render\fit_preferred( $box, array( 'vw' => 24 ) ), 'No preferred size: the whole box.' );
	}

	/**
	 * The picture of a signature that prefers a size has the proportion of it (the drawing centered in it), and one that does not keeps the
	 * proportion of its viewBox.
	 */
	public function test_a_signature_picture_has_the_proportion_of_its_preferred_size() {
		$path   = array( array( 'd' => 'M0 0L5 5Z' ) );
		$design = array( 'elements' => array() );
		$wide   = Render\signature_src(
			$design,
			array(
				'signature' => (string) wp_json_encode(
					array(
						'vw'    => 24,
						'vh'    => 24,
						'w'     => 512,
						'h'     => 256,
						'paths' => $path,
					)
				),
			),
			'#000'
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$plain  = Render\signature_src(
			$design,
			array(
				'signature' => (string) wp_json_encode(
					array(
						'vw'    => 24,
						'vh'    => 24,
						'paths' => $path,
					)
				),
			),
			'#000'
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$decode = static function ( $src ) {
			return false !== strpos( $src, ';base64,' ) ? (string) base64_decode( substr( $src, strpos( $src, ',' ) + 1 ) ) : rawurldecode( substr( $src, strpos( $src, ',' ) + 1 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- a test reads the picture it made.
		};
		$this->assertStringContainsString( 'width="512" height="256" viewBox="0 0 24 24"', $decode( $wide ) );
		$this->assertStringNotContainsString( 'width=', $decode( $plain ) );
	}

	/**
	 * An event stores them, and a certificate's values carry them to the renderer.
	 */
	public function test_the_pictures_reach_the_certificate_values() {
		wp_set_current_user( 1 );
		$picture = $this->make_picture();
		$id      = Events\save(
			array(
				'title'         => 'A fictional meetup',
				'start'         => '2026-11-25 19:00',
				'logo'          => $picture,
				'signature_svg' => '{"vw":100,"vh":40,"paths":[{"d":"M0 0L50 20L100 0"}]}',
			)
		);
		$event   = Events\get( $id );
		$this->assertSame( $picture, $event['logo'] );
		$this->assertNotSame( '', $event['signature_svg'] );

		$values = Render\values(
			array(
				'name'      => 'Ana Example',
				'issued_at' => '2026-11-25 20:00:00',
				'data_json' => (string) wp_json_encode(
					array(
						'logo'          => $event['logo'],
						'signature_svg' => $event['signature_svg'],
					)
				),
			)
		);
		$this->assertSame( Pictures\src( $picture ), $values['logo_image'] );
		$this->assertStringContainsString( '/uploads/', $values['logo_image'] );
		$this->assertEquals( 100, json_decode( $values['signature'], true )['vw'] );
	}

	/**
	 * Saving an event from a form that does not send the pictures keeps them; sending an empty one clears them.
	 */
	public function test_saving_an_event_keeps_its_pictures() {
		wp_set_current_user( 1 );
		$picture = $this->make_picture();
		$id      = Events\save(
			array(
				'title'         => 'A fictional meetup',
				'start'         => '2026-11-25 19:00',
				'logo'          => $picture,
				'signature_svg' => '{"vw":100,"vh":40,"paths":[{"d":"M0 0L50 20L100 0"}]}',
			)
		);
		Events\save(
			array(
				'title' => 'A fictional meetup, renamed',
				'start' => '2026-11-25 19:00',
			),
			$id
		);
		$event = Events\get( $id );
		$this->assertSame( 'A fictional meetup, renamed', $event['title'] );
		$this->assertSame( $picture, $event['logo'], 'The form did not send the logo: it stays.' );
		$this->assertNotSame( '', $event['signature_svg'] );

		Events\save(
			array(
				'title' => 'A fictional meetup, renamed',
				'start' => '2026-11-25 19:00',
				'logo'  => '',
			),
			$id
		);
		$this->assertSame( '', Events\get( $id )['logo'], 'An empty logo clears it.' );
		$this->assertNotSame( '', Events\get( $id )['signature_svg'], 'The other picture stays.' );
	}

	/**
	 * The event's edit form posts its pictures and the screen saves them.
	 */
	public function test_the_event_form_saves_the_pictures_it_posts() {
		wp_set_current_user( 1 );
		$signature = $this->make_picture( 'evcert-signature.png' );
		$id        = Events\save(
			array(
				'title' => 'A fictional meetup',
				'start' => '2026-11-25 19:00',
			)
		);
		Events\save(
			array(
				'title'           => 'A fictional meetup',
				'start'           => '2026-11-25 19:00',
				'signature_image' => $signature,
			),
			$id
		);
		$this->assertSame( $signature, Events\get( $id )['signature_image'] );
		$this->assertSame( '', Events\get( $id )['logo'], 'What was not set stays empty.' );
	}
}
