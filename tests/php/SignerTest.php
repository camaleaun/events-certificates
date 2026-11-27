<?php
/**
 * The signer's signature is read into restricted fields.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Signer;

/**
 * Signature SVG and roles.
 */
class SignerTest extends Test_Case {

	/**
	 * Filled paths are kept, with fills from styles; outlines, DTDs and other shapes are not.
	 */
	public function test_parse_svg() {
		$svg = Signer\parse_svg( '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 44" fill="none"><g><path style="fill:#1a1919" d="M0 0L10 10Z"/><path fill="none" stroke="#000" d="M0 0L5 5"/><circle r="4"/><path d="javascript:alert(1)"/></g></svg>' );
		$this->assertSame( 120.0, $svg['vw'] );
		$this->assertSame( 44.0, $svg['vh'] );
		$this->assertSame(
			array(
				array(
					'd'    => 'M0 0L10 10Z',
					'fill' => '#1a1919',
				),
			),
			$svg['paths']
		);
		$this->assertNull( Signer\parse_svg( '<!DOCTYPE svg [<!ENTITY a "b">]><svg><path d="M0 0Z"/></svg>' ) );
		$this->assertNull( Signer\parse_svg( '<svg><path fill="none" d="M0 0L1 1"/></svg>' ) );
		$this->assertNull( Signer\parse_svg( 'not svg' ) );
	}

	/**
	 * The signature is in the organizers' REST API (a schema with one type), so the block editor saves it.
	 */
	public function test_signature_in_rest() {
		if ( ! post_type_exists( Signer\ORGANIZER ) ) {
			$this->markTestSkipped( 'wc-post-types is not active.' );
		}
		$fields = ( new \WP_REST_Post_Meta_Fields( Signer\ORGANIZER ) )->get_field_schema()['properties'];
		$this->assertArrayHasKey( Signer\SIGNATURE_META, $fields );
		$this->assertSame( 'object', $fields[ Signer\SIGNATURE_META ]['type'] );
	}

	/**
	 * A role falls back to the site language, then to any.
	 */
	public function test_role_in() {
		$roles = array(
			'en_US' => 'Event Coordinator',
			'pt_BR' => 'Coordenação do evento',
		);
		$this->assertSame( 'Coordenação do evento', Signer\role_in( $roles, 'pt_BR' ) );
		$this->assertSame( 'Event Coordinator', Signer\role_in( $roles, 'en_US' ) );
		$this->assertSame( 'Event Coordinator', Signer\role_in( array( 'en_US' => 'Event Coordinator' ), 'es_ES' ) );
		$this->assertSame( '', Signer\role_in( array(), 'pt_BR' ) );
	}

	/**
	 * An SVG is read as strictly as a wp_kses() list for icons and then some: no DTD or entity, no script, style sheet or event
	 * handler anywhere, nothing big; and only paths are read, whatever else the file holds.
	 */
	public function test_svg_is_read_strictly() {
		$path = '<path d="M0 0L10 10Z" fill="#112233"/>';
		$this->assertNotNull( Signer\parse_svg( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">' . $path . '</svg>' ) );
		foreach ( array(
			'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script>' . $path . '</svg>',
			'<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)">' . $path . '</svg>',
			'<svg xmlns="http://www.w3.org/2000/svg">' . str_replace( '<path', '<path onclick="x()"', $path ) . '</svg>',
			'<?xml-stylesheet href="http://example.com/x.css"?><svg xmlns="http://www.w3.org/2000/svg">' . $path . '</svg>',
			'<!DOCTYPE svg [<!ENTITY a "b">]><svg xmlns="http://www.w3.org/2000/svg">' . $path . '</svg>',
			'<svg xmlns="http://www.w3.org/2000/svg">' . $path . str_repeat( ' ', Signer\SVG_MAX_BYTES ) . '</svg>',
		) as $code ) {
			$this->assertNull( Signer\parse_svg( $code ), $code );
		}
		// What else the file holds is not read.
		$svg = Signer\parse_svg( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><image href="http://example.com/a.png"/><use href="#x"/><foreignObject><div>x</div></foreignObject>' . $path . '</svg>' );
		$this->assertCount( 1, $svg['paths'] );
	}

	/**
	 * The saved fields have limits: how many paths, how long each, how big the viewBox, and fills are colors only.
	 */
	public function test_a_signature_has_limits() {
		$many  = array_fill( 0, Signer\SVG_MAX_PATHS + 20, array( 'd' => 'M0 0L1 1Z' ) );
		$clean = Signer\sanitize_signature(
			array(
				'vw'    => 100,
				'vh'    => 40,
				'paths' => $many,
			)
		);
		$this->assertCount( Signer\SVG_MAX_PATHS, $clean['paths'] );

		$long = Signer\sanitize_signature(
			array(
				'vw'    => 100,
				'vh'    => 40,
				'paths' => array(
					array( 'd' => 'M0 0' . str_repeat( 'L1 1', Signer\SVG_MAX_PATH_DATA ) ),
					array( 'd' => 'M0 0L1 1Z' ),
				),
			)
		);
		$this->assertCount( 1, $long['paths'], 'A path that long is dropped.' );

		$this->assertNull(
			Signer\sanitize_signature(
				array(
					'vw'    => Signer\SVG_MAX_VIEWBOX + 1,
					'vh'    => 40,
					'paths' => array( array( 'd' => 'M0 0L1 1Z' ) ),
				)
			)
		);
		$odd = Signer\sanitize_signature(
			array(
				'vw'    => 10,
				'vh'    => 10,
				'paths' => array(
					array(
						'd'    => 'M0 0L1 1Z',
						'fill' => 'url(javascript:alert(1))',
					),
					'not a path',
					array( 'd' => array( 'M0 0' ) ),
				),
			)
		);
		$this->assertSame( array( array( 'd' => 'M0 0L1 1Z' ) ), $odd['paths'] );
	}
}
