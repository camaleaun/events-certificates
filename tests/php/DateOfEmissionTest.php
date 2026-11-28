<?php
/**
 * The date of emission: a text whose words are a date format; empty, the site's format.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Render;

/**
 * The date of emission on the page and in the PDF.
 */
class DateOfEmissionTest extends Test_Case {

	/**
	 * The values of a certificate issued on a day.
	 *
	 * @return array<string, string>
	 */
	private function values() {
		return Render\values(
			array(
				'name'      => 'Ana Lima',
				'issued_at' => '2026-10-31 15:00:00',
			)
		);
	}

	/**
	 * The date of emission, as an element with the words of its runs.
	 *
	 * @param array<int, array<string, mixed>>|null $runs Runs (none: not written).
	 * @return array<string, mixed>
	 */
	private function element( $runs = null ) {
		return array(
			'type' => 'text',
			'slot' => 'date-of-emission',
		) + ( null === $runs ? array() : array( 'runs' => $runs ) );
	}

	public function test_empty_it_is_the_date_in_the_format_of_the_site() {
		$values = $this->values();
		update_option( 'date_format', 'd/m/Y' );
		$this->assertSame( '31/10/2026', Render\runs_html( $this->element(), $values ) );
		update_option( 'date_format', 'F j, Y' );
		$this->assertSame( 'October 31, 2026', Render\runs_html( $this->element(), $values ) );
		$this->assertSame( 'October 31, 2026', Render\runs_html( $this->element( array( array( 'text' => '  ' ) ) ), $values ), 'Words that are only spaces are empty.' );
	}

	public function test_the_words_are_the_format() {
		$values = $this->values();
		update_option( 'date_format', 'F j, Y' );
		$this->assertSame( '2026', Render\runs_html( $this->element( array( array( 'text' => 'Y' ) ) ), $values ) );
		$this->assertSame( '31 de October de 2026', Render\runs_html( $this->element( array( array( 'text' => 'j \d\e F \d\e Y' ) ) ), $values ) );
		$this->assertSame(
			'<strong>31</strong>/<em>10</em>',
			Render\runs_html(
				$this->element(
					array(
						array(
							'text' => 'd',
							'b'    => true,
						),
						array( 'text' => '/' ),
						array(
							'text' => 'm',
							'i'    => true,
						),
					)
				),
				$values
			)
		);
	}

	public function test_a_language_has_its_own_format() {
		$values = $this->values();
		$el     = $this->element( array( array( 'text' => 'Y' ) ) ) + array( 'variants' => array( determine_locale() => array( array( 'text' => 'd.m.y' ) ) ) );
		$this->assertSame( '31.10.26', Render\runs_html( $el, $values ) );
	}

	public function test_a_text_that_is_not_the_date_of_emission_keeps_its_words() {
		$values = $this->values();
		$this->assertSame(
			'd/m/Y',
			Render\runs_html(
				array(
					'type' => 'text',
					'runs' => array( array( 'text' => 'd/m/Y' ) ),
				),
				$values
			)
		);
		$this->assertSame( '31/10/2026', Render\field_value( 'issued_date', 'd/m/Y', $values ), 'A format that is not a named one is a pattern.' );
		$this->assertSame( '2026-10-31', Render\field_value( 'issued_date', 'iso', $values ), 'The named ones stay.' );
	}
}
