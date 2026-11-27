<?php
/**
 * The verification element: the QR code, the code and the hint as one element of a design.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Render;

/**
 * Stack or row, aligned left, center or right, and hidden where verification is.
 */
class VerificationElementTest extends Test_Case {

	/**
	 * A one-page design.
	 *
	 * @return array<string, mixed>
	 */
	private function design() {
		return array(
			'page'   => array(
				'w'  => 400,
				'h'  => 300,
				'bg' => '#fff',
			),
			'colors' => array(
				'text'  => array(
					'light' => '#000',
					'dark'  => '#fff',
				),
				'muted' => array(
					'light' => '#666',
					'dark'  => '#999',
				),
			),
		);
	}

	/**
	 * The element, with what it takes.
	 *
	 * @param array<string, mixed> $extra Settings of the element.
	 * @return array<string, mixed>
	 */
	private function element( array $extra = array() ) {
		return $extra + array(
			'id'   => 'verification',
			'type' => 'verification',
			'x'    => 100,
			'y'    => 200,
			'w'    => 200,
			'h'    => 80,
			'qr'   => 40,
		);
	}

	/**
	 * A stack puts the code under the QR code and the hint under the code, centered by default.
	 */
	public function test_a_stack_is_centered_by_default() {
		$parts = Render\verification_parts( $this->element() );
		$this->assertSame( array( 'qr', 'text', 'text' ), array_column( $parts, 'type' ) );
		$this->assertEqualsWithDelta( 180.0, $parts[0]['x'], 0.01, 'The QR code is centered in the element.' );
		$this->assertSame( 'center', $parts[1]['align'] );
		$this->assertGreaterThan( $parts[1]['y'], $parts[2]['y'], 'The hint is under the code.' );
		$this->assertGreaterThan( $parts[0]['y'] + 40, $parts[1]['y'] - 0.01, 'The code is under the QR code.' );

		$left  = Render\verification_parts( $this->element( array( 'align' => 'left' ) ) );
		$right = Render\verification_parts( $this->element( array( 'align' => 'right' ) ) );
		$this->assertEqualsWithDelta( 100.0, $left[0]['x'], 0.01 );
		$this->assertEqualsWithDelta( 260.0, $right[0]['x'], 0.01 );
		$this->assertSame( 'right', $right[2]['align'] );
	}

	/**
	 * A row puts the text beside the QR code: to its left when aligned right, to its right otherwise.
	 */
	public function test_a_row_puts_the_text_beside_the_qr_code() {
		$right = Render\verification_parts(
			$this->element(
				array(
					'arrange' => 'row',
					'align'   => 'right',
				)
			)
		);
		$this->assertEqualsWithDelta( 260.0, $right[0]['x'], 0.01, 'The QR code is at the right edge.' );
		$this->assertLessThan( $right[0]['x'], $right[1]['x'] + $right[1]['w'], 'The text ends before the QR code.' );
		$this->assertSame( 'right', $right[1]['align'] );

		$left = Render\verification_parts(
			$this->element(
				array(
					'arrange' => 'row',
					'align'   => 'left',
				)
			)
		);
		$this->assertEqualsWithDelta( 100.0, $left[0]['x'], 0.01 );
		$this->assertGreaterThan( $left[0]['x'] + 40, $left[1]['x'], 'The text starts after the QR code.' );
	}

	/**
	 * It is drawn only where verification is: not on a site that is not public, and not when the design leaves it out.
	 */
	public function test_it_is_drawn_only_where_verification_is() {
		$design = $this->design();
		$values = Render\values( array( 'code' => '2611-04K7-M29Q-XAP3' ) );
		$opts   = array( 'mode' => 'light' );

		add_filter( 'evcert_is_public_site', '__return_false' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the plugin's own hook.
		$this->assertSame( '', Render\element_html( $this->element(), $design, $values, $opts ) );
		remove_filter( 'evcert_is_public_site', '__return_false' );

		add_filter( 'evcert_is_public_site', '__return_true' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the plugin's own hook.
		$html = Render\element_html( $this->element(), $design, $values, $opts );
		remove_filter( 'evcert_is_public_site', '__return_true' );
		$this->assertStringContainsString( '<img', $html );
		$this->assertStringContainsString( '2611-04K7-M29Q-XAP3', $html );
		$this->assertSame(
			'',
			Render\element_html(
				$this->element(),
				$design,
				$values,
				array(
					'mode'            => 'light',
					'no_verification' => true,
				)
			),
			'A first page that leaves verification to the declaration page draws none.'
		);
	}
}
