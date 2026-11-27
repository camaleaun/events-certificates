<?php
/**
 * Texts by language: a text can carry a full list of runs for each language; without one it keeps its own.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\Render;

/**
 * Text variants tests.
 */
class TextVariantsTest extends Test_Case {

	/**
	 * A text with a Portuguese variant.
	 *
	 * @return array<string, mixed>
	 */
	private function element() {
		return array(
			'id'       => 'body',
			'type'     => 'text',
			'runs'     => array(
				array( 'text' => 'Took part in ' ),
				array( 'field' => 'event' ),
				array( 'text' => ', ' ),
				array( 'field' => 'location' ),
			),
			'variants' => array(
				'pt_BR' => array(
					array( 'field' => 'location' ),
					array( 'text' => ': participou do ' ),
					array( 'field' => 'event' ),
				),
			),
		);
	}

	/**
	 * Values the runs ask for.
	 *
	 * @return array<string, string>
	 */
	private function values() {
		return array(
			'event'    => 'WordCamp Recife',
			'location' => 'Recife',
		);
	}

	/**
	 * The text of the language of the certificate; the order of the fields can differ from the default's.
	 */
	public function test_the_variant_of_the_language_is_used() {
		$this->assertSame( 'Took part in WordCamp Recife, Recife', Render\runs_text( $this->element(), $this->values() ) );

		$this->use_locale( 'pt_BR' );
		$this->assertSame( 'Recife: participou do WordCamp Recife', Render\runs_text( $this->element(), $this->values() ) );
		$this->restore_locale();
	}

	/**
	 * A language with no variant, or an empty one, keeps the default text.
	 */
	public function test_other_languages_keep_the_default_text() {
		$this->use_locale( 'es_ES' );
		$this->assertSame( 'Took part in WordCamp Recife, Recife', Render\runs_text( $this->element(), $this->values() ) );
		$this->restore_locale();

		$element                      = $this->element();
		$element['variants']['pt_BR'] = array();
		$this->use_locale( 'pt_BR' );
		$this->assertSame( 'Took part in WordCamp Recife, Recife', Render\runs_text( $element, $this->values() ) );
		$this->restore_locale();
	}

	/**
	 * A design without variants (the old ones, the exported ones) renders as before.
	 */
	public function test_designs_without_variants_are_unchanged() {
		$element = $this->element();
		unset( $element['variants'] );
		$this->use_locale( 'pt_BR' );
		$this->assertSame( 'Took part in WordCamp Recife, Recife', Render\runs_text( $element, $this->values() ) );
		$this->restore_locale();
	}

	/**
	 * The locale the filter answers with (switch_to_locale() refuses a language that is not installed).
	 *
	 * @var callable|null
	 */
	private $locale_filter = null;

	/**
	 * Makes the site speak a language, installed or not.
	 *
	 * @param string $locale Locale.
	 */
	private function use_locale( $locale ) {
		$this->restore_locale();
		$this->locale_filter = static fn () => $locale;
		// After the locale switcher's own answer (the base class switches to English): whatever the site's language is.
		add_filter( 'determine_locale', $this->locale_filter, 99 );
	}

	/**
	 * Back to the site's language.
	 */
	private function restore_locale() {
		if ( $this->locale_filter ) {
			remove_filter( 'determine_locale', $this->locale_filter, 99 );
			$this->locale_filter = null;
		}
	}
}
