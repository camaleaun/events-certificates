<?php
/**
 * The pictures of the Meetup tutorial: the block, the steps, the icons and the drawing's markup.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

use EventsCertificate\TutorialStepImage;

/**
 * Tutorial step image tests.
 */
class TutorialStepImageTest extends Test_Case {

	/**
	 * The block is registered with its step.
	 */
	public function test_the_block_is_registered() {
		$block = \WP_Block_Type_Registry::get_instance()->get_registered( TutorialStepImage\BLOCK );
		$this->assertNotNull( $block );
		$this->assertSame( 1, $block->attributes['step']['default'] );
		$this->assertSame( array( TutorialStepImage\VIEW ), $block->view_script_handles, 'The enlarged view is the block\'s own script.' );
		$this->assertTrue( wp_script_is( TutorialStepImage\VIEW, 'registered' ) );
	}

	/**
	 * Runs a callback as if the site were in a language, since switch_to_locale() refuses one that is not installed
	 * (the CI has only English): determine_locale() answers it. English, always installed, is a real switch.
	 *
	 * @param string   $locale   The language.
	 * @param callable $callback What to run.
	 * @return mixed
	 */
	private function in_language( $locale, $callback ) {
		$switched = 'en_US' === $locale && determine_locale() !== 'en_US' && switch_to_locale( 'en_US' );
		$force    = static fn () => $locale;
		if ( ! $switched ) {
			add_filter( 'pre_determine_locale', $force );
		}
		try {
			return $callback();
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			} else {
				remove_filter( 'pre_determine_locale', $force );
			}
		}
	}

	/**
	 * A catalog of translations of the sample's texts that exists for every language but English (which has none):
	 * Curitiba's for pt_BR and Mexico City's for es_ES. The locale switcher says when the sample is read in English:
	 * the catalog of English is loaded then, so nothing is translated.
	 *
	 * @param \stdClass $state Has "english": whether the language was switched to English.
	 * @return callable
	 */
	private function catalog( $state ) {
		return static function ( $translation, $text, $context ) use ( $state ) {
			if ( $state->english ) {
				return $translation;
			}
			$maps = array(
				'pt_BR' => array(
					'Meetup|WordPress NYC Meetup Group' => 'Comunidade WordPress Curitiba',
					'Meetup|166 Crosby Street'          => 'HOTMILK',
				),
				'es_ES' => array(
					'Meetup|WordPress NYC Meetup Group' => 'Grupo de Ciudad de México',
					'Meetup|166 Crosby Street'          => 'Calle Reforma',
					'Meetup|New York, NY'               => 'Ciudad de México',
				),
			);
			return $maps[ determine_locale() ][ $context . '|' . $text ] ?? $translation;
		};
	}

	/**
	 * Runs a callback in a language with the catalog on, as a site would have it.
	 *
	 * @param string   $locale   The language.
	 * @param callable $callback What to run.
	 * @return mixed
	 */
	private function with_catalog( $locale, $callback ) {
		$state   = (object) array( 'english' => false );
		$listen  = static function ( $switched_to ) use ( $state ) {
			$state->english = 'en_US' === $switched_to;
		};
		$catalog = $this->catalog( $state );
		add_action( 'change_locale', $listen );
		add_filter( 'gettext_with_context', $catalog, 10, 3 );
		try {
			return $this->in_language( $locale, $callback );
		} finally {
			remove_filter( 'gettext_with_context', $catalog, 10 );
			remove_action( 'change_locale', $listen );
		}
	}

	/**
	 * The sample is read in the sample's language: Brazilian Portuguese on a site in it, English on every other; the
	 * site's language is its own again after.
	 */
	public function test_the_sample_is_read_in_its_own_language() {
		$this->assertSame( 'pt_BR', $this->in_language( 'pt_BR', static fn () => TutorialStepImage\sample_locale() ) );
		$this->assertSame( 'en_US', $this->in_language( 'es_ES', static fn () => TutorialStepImage\sample_locale() ) );
		$this->assertSame( 'en_US', $this->in_language( 'pt_PT', static fn () => TutorialStepImage\sample_locale() ), 'Portugal is not Brazil.' );

		$before = determine_locale();
		$seen   = array();
		$listen = static function ( $locale ) use ( &$seen ) {
			$seen[] = $locale;
		};
		add_action( 'change_locale', $listen );
		$this->in_language( 'pt_BR', static fn () => TutorialStepImage\in_sample( static fn () => 1 ) );
		$this->assertSame( array(), $seen, 'A site in Brazilian Portuguese is not switched.' );
		$this->in_language( 'es_ES', static fn () => TutorialStepImage\in_sample( static fn () => 1 ) );
		remove_action( 'change_locale', $listen );
		$this->assertContains( 'en_US', $seen, 'Another language is read in English.' );
		$this->assertSame( $before, determine_locale(), 'The language is restored.' );
	}

	/**
	 * The default sample is New York's; Brazilian Portuguese's is Curitiba's, and only it: a translation of the texts
	 * into another language changes nothing, and the numbers, pictures and times are not translatable at all.
	 */
	public function test_only_brazilian_portuguese_changes_the_sample() {
		// A translation of a number is no use: they are not read from the catalog.
		$number = static fn ( $translation, $text, $context ) => 'Meetup sample: number of reviews of the group, a number' === $context ? '1' : $translation;
		add_filter( 'gettext_with_context', $number, 10, 3 );

		$english = $this->with_catalog( 'en_US', static fn () => TutorialStepImage\scene() );
		$spanish = $this->with_catalog( 'es_ES', static fn () => TutorialStepImage\scene() );
		$brazil  = $this->with_catalog( 'pt_BR', static fn () => TutorialStepImage\scene() );

		remove_filter( 'gettext_with_context', $number, 10 );

		foreach ( array(
			'English'                     => $english,
			'Spanish, with a translation' => $spanish,
		) as $name => $scene ) {
			$this->assertSame( 'WordPress NYC Meetup Group', $scene['group']['name'], "$name: New York's." );
			$this->assertSame( '166 Crosby Street', $scene['venue']['name'] );
			$this->assertSame( 'America/New_York', $scene['timezone'] );
			$this->assertStringContainsString( '1,219', $scene['group']['reviews'] );
			$this->assertStringContainsString( '/event/536309234.webp', $scene['events'][0]['photo'] );
			$this->assertStringContainsString( '20', $scene['events'][0]['attendees'] );
		}

		// Brazilian Portuguese: the translated texts, and its own numbers, pictures and times (in code).
		$this->assertSame( 'Comunidade WordPress Curitiba', $brazil['group']['name'] );
		$this->assertSame( 'HOTMILK', $brazil['venue']['name'] );
		$this->assertSame( 'America/Sao_Paulo', $brazil['timezone'] );
		$this->assertStringContainsString( '555', $brazil['group']['reviews'] );
		$this->assertStringContainsString( '/event/536487429.webp', $brazil['events'][0]['photo'] );
		$this->assertStringContainsString( '40', $brazil['events'][0]['attendees'] );
	}

	/**
	 * The form's examples are the same sample, in the same languages.
	 */
	public function test_the_form_examples_follow_the_sample() {
		$english = $this->with_catalog( 'en_US', static fn () => \EventsCertificate\QuickGenerator\examples() );
		$spanish = $this->with_catalog( 'es_ES', static fn () => \EventsCertificate\QuickGenerator\examples() );
		$brazil  = $this->with_catalog( 'pt_BR', static fn () => \EventsCertificate\QuickGenerator\examples() );

		$this->assertSame( 'E.g.: 166 Crosby Street', $english['venue'] );
		$this->assertSame( 'E.g.: New York, NY', $english['city'] );
		$this->assertStringContainsString( 'New York, NY', $spanish['city'], 'A translation of the sample into Spanish changes nothing.' );
		$this->assertStringContainsString( 'HOTMILK', $brazil['venue'] );
	}

	/**
	 * A time zone a translation gets wrong is not a fatal error.
	 */
	public function test_a_bad_time_zone_falls_back_to_utc() {
		$this->assertSame( 'UTC', TutorialStepImage\time_zone( 'Not/AZone' )->getName() );
		$this->assertSame( 'America/Sao_Paulo', TutorialStepImage\time_zone( 'America/Sao_Paulo' )->getName() );
	}

	/**
	 * Every step marks what to click with a red box of the markup, which follows the size of what it frames.
	 */
	public function test_every_step_has_a_frame() {
		foreach ( array_keys( TutorialStepImage\steps() ) as $step ) {
			if ( ! is_readable( dirname( __DIR__, 2 ) . '/templates/tutorial-step-image/step-' . $step . '.php' ) ) {
				continue;
			}
			$html = TutorialStepImage\render( $step, 'en_US' );
			$this->assertStringContainsString( 'evcert-tsi-frame', $html, "Step $step has no red frame." );
		}
	}

	/**
	 * Every icon is a readable file and is registered with the Icon block's registry, once.
	 */
	public function test_the_icons_are_registered() {
		$this->assertTrue( \WP_Icon_Collections_Registry::get_instance()->is_registered( TutorialStepImage\COLLECTION ) );
		foreach ( array_keys( TutorialStepImage\icons() ) as $name ) {
			$file = dirname( __DIR__, 2 ) . '/assets/images/tutorial-step-image/icons/' . $name . '.svg';
			$this->assertFileExists( $file, "Icon $name." );
			$this->assertTrue( \WP_Icons_Registry::get_instance()->is_registered( TutorialStepImage\COLLECTION . '/' . $name ), "Icon $name is registered." );
		}
		// A second call changes nothing, and is not a notice.
		TutorialStepImage\register_icons();
	}

	/**
	 * Every icon a template uses is one the plugin registers.
	 */
	public function test_the_templates_use_only_registered_icons() {
		$templates = (array) glob( dirname( __DIR__, 2 ) . '/templates/tutorial-step-image/step-*.php' );
		$this->assertNotEmpty( $templates, 'The steps have templates.' );
		foreach ( $templates as $file ) {
			preg_match_all( '#"icon":"' . preg_quote( TutorialStepImage\COLLECTION, '#' ) . '/([a-z0-9-]+)"#', (string) file_get_contents( $file ), $found ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local template.
			foreach ( $found[1] as $name ) {
				$this->assertArrayHasKey( $name, TutorialStepImage\icons(), basename( $file ) . " uses $name." );
			}
		}
	}

	/**
	 * The underline below "Details" stretches to its title: the core icon block drops the file's preserveAspectRatio, so it is put back.
	 */
	public function test_the_underline_icon_can_stretch() {
		$html = TutorialStepImage\render( 2, 'en_US' );
		$this->assertMatchesRegularExpression( '#<svg[^>]*preserveaspectratio="none"[^>]*>#i', $html );
		$this->assertSame( 1, substr_count( strtolower( $html ), 'preserveaspectratio' ), 'Only the underline stretches.' );
	}

	/**
	 * The enlarged-view script is for pages: the admin screen's tutorial modal has its own, and a second one opened over it and stayed.
	 */
	public function test_the_enlarged_view_script_is_not_loaded_in_the_admin() {
		wp_dequeue_script( TutorialStepImage\VIEW );
		set_current_screen( 'dashboard' );
		TutorialStepImage\render( 1, 'en_US' );
		$in_admin = wp_script_is( TutorialStepImage\VIEW, 'enqueued' );
		wp_dequeue_script( TutorialStepImage\VIEW );
		set_current_screen( 'front' );
		TutorialStepImage\render( 1, 'en_US' );
		$on_page = wp_script_is( TutorialStepImage\VIEW, 'enqueued' );
		wp_dequeue_script( TutorialStepImage\VIEW );
		unset( $GLOBALS['current_screen'] );

		$this->assertFalse( $in_admin, 'The admin modal brings its own enlarged view.' );
		$this->assertTrue( $on_page, 'A page needs the enlarged view.' );
	}

	/**
	 * The script itself leaves a drawing inside the admin's tutorial modal to the modal.
	 */
	public function test_the_enlarged_view_script_ignores_the_admin_modal() {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/es/tutorial-step-image.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file.
		$this->assertStringContainsString( "closest( '.evcert-tutorial' )", $js );
	}

	/**
	 * The scene reaches past the viewBox's bottom edge: a scene that stops short leaves a white line along the picture's base.
	 */
	public function test_the_scene_reaches_past_the_bottom_of_the_window() {
		foreach ( TutorialStepImage\steps() as $number => $step ) {
			$this->assertGreaterThan(
				$step['height'],
				$step['fo']['y'] + $step['fo']['height'],
				"Step $number: the scene ends before the window does."
			);
		}
	}

	/**
	 * The drawing: an SVG with the right namespaces, a foreignObject as big as the viewBox, and nothing but local, quiet markup.
	 */
	public function test_a_step_is_drawn_inside_its_viewbox() {
		$html     = TutorialStepImage\render( 1, 'en_US' );
		$step_one = TutorialStepImage\steps()[1];

		$this->assertStringContainsString( 'xmlns="http://www.w3.org/2000/svg"', $html );
		// The picture is as big in pixels as the first generator's was; the viewBox is the window in units.
		$this->assertStringContainsString( 'width="' . $step_one['pixels'][0] . '" height="' . $step_one['pixels'][1] . '"', $html );
		$this->assertStringContainsString( 'xmlns="http://www.w3.org/1999/xhtml"', $html );
		// The drawing's window is the step's size; the page's block is placed inside it, as the step says.
		$step = TutorialStepImage\steps()[1];
		$this->assertStringContainsString( 'viewbox="0 0 ' . TutorialStepImage\num( $step['width'] ) . ' ' . TutorialStepImage\num( $step['height'] ) . '"', strtolower( $html ) );
		$this->assertMatchesRegularExpression(
			'#<foreignobject x="' . preg_quote( TutorialStepImage\num( $step['fo']['x'] ), '#' ) . '" y="' . preg_quote( TutorialStepImage\num( $step['fo']['y'] ), '#' ) . '" width="' . preg_quote( TutorialStepImage\num( $step['fo']['width'] ), '#' ) . '" height="' . preg_quote( TutorialStepImage\num( $step['fo']['height'] ), '#' ) . '">#i',
			$html
		);
		$this->assertStringContainsString( 'Past events', $html );
		$this->assertStringContainsString( 'role="img"', $html );
		$this->assertStringContainsString( 'aria-haspopup="dialog"', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertDoesNotMatchRegularExpression( '/\son[a-z]+=/i', $html );
		$this->assertDoesNotMatchRegularExpression( '#(src|href)="https?://(?!' . preg_quote( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '#' ) . ')#', $html, 'Nothing from another site.' );
	}

	/**
	 * Every svg of a drawing, the icons too, says its namespace: all of them do, or none would.
	 */
	public function test_every_svg_has_its_namespace() {
		foreach ( array_keys( TutorialStepImage\steps() ) as $step ) {
			$html = TutorialStepImage\render( $step, 'en_US' );
			preg_match_all( '/<svg\b[^>]*>/i', $html, $found );
			$this->assertNotEmpty( $found[0], "Step $step has svg." );
			foreach ( $found[0] as $tag ) {
				$this->assertStringContainsString( 'xmlns="http://www.w3.org/2000/svg"', $tag, "Step $step: $tag" );
			}
		}
	}

	/**
	 * A step that is not there, or has no template yet, draws nothing.
	 */
	public function test_an_unknown_step_draws_nothing() {
		$this->assertSame( '', TutorialStepImage\render( 0 ) );
		$this->assertSame( '', TutorialStepImage\render( 5 ) );
		foreach ( array_keys( TutorialStepImage\steps() ) as $step ) {
			if ( ! is_readable( dirname( __DIR__, 2 ) . '/templates/tutorial-step-image/step-' . $step . '.php' ) ) {
				$this->assertSame( '', TutorialStepImage\render( $step ), "Step $step has no template yet." );
			}
		}
	}

	/**
	 * The block draws what render() draws.
	 */
	public function test_the_block_renders_the_step() {
		$html = render_block(
			array(
				'blockName'    => TutorialStepImage\BLOCK,
				'attrs'        => array( 'step' => 1 ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		$this->assertStringContainsString( 'evcert-tsi__svg', $html );
	}

	/**
	 * A meetup's time is a compact UTC string, shown in the sample's zone; it ends two hours later, in any language.
	 */
	public function test_times_are_compact_utc_and_end_two_hours_later() {
		$this->assertSame( '20261027T2200', TutorialStepImage\starts_at( '20261027T2200' )->format( 'Ymd\THi' ) );
		$this->assertSame( '20261028T0000', TutorialStepImage\ends_at( '20261027T2200' )->format( 'Ymd\THi' ), 'Two hours later, across midnight.' );
		$this->assertSame( 0, TutorialStepImage\starts_at( 'not a time' )->getTimestamp(), 'A bad value is not a fatal error.' );

		$curitiba = array(
			'timezone' => 'America/Sao_Paulo',
			'zone'     => 'BRT',
		);
		$new_york = array(
			'timezone' => 'America/New_York',
			'zone'     => '',
		);
		$this->assertSame( 'Tue, Oct 27 · 7:00 PM BRT', TutorialStepImage\when_label( '20261027T2200', $curitiba ) );
		$this->assertSame( 'Tue, Oct 27 · 6:00 PM EDT', TutorialStepImage\when_label( '20261027T2200', $new_york ) );
		$this->assertSame( 'Tue, Oct 27 · 9:00 PM BRT', TutorialStepImage\format_time( TutorialStepImage\ends_at( '20261027T2200' ), $curitiba ) );
	}

	/**
	 * The classes WordPress puts for its own layout are renamed to the drawing's prefix, so the theme's CSS does not reach it.
	 */
	public function test_no_wordpress_layout_class_is_left() {
		foreach ( array_keys( TutorialStepImage\steps() ) as $step ) {
			if ( ! is_readable( dirname( __DIR__, 2 ) . '/templates/tutorial-step-image/step-' . $step . '.php' ) ) {
				continue;
			}
			$html = TutorialStepImage\render( $step, 'en_US' );
			preg_match_all( '/class="([^"]*)"/', $html, $found );
			foreach ( $found[1] as $classes ) {
				foreach ( preg_split( '/\s+/', $classes ) as $name ) {
					$this->assertDoesNotMatchRegularExpression( '/^(is-layout-|wp-container-|wp-block-group-is-layout-|wp-custom-css-|wp-elements-|has-global-padding$)/', $name, "Step $step keeps the WordPress class $name." );
				}
			}
		}
	}

	/**
	 * The CSS made from the blocks' attributes (bin/tutorial-css.php) has a rule for every container and custom-CSS class of every step and language.
	 */
	public function test_the_block_css_is_up_to_date() {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/scss/_tutorial-step-image-blocks.scss' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file.
		$this->assertNotSame( '', $css );
		foreach ( array_keys( TutorialStepImage\steps() ) as $step ) {
			if ( ! is_readable( dirname( __DIR__, 2 ) . '/templates/tutorial-step-image/step-' . $step . '.php' ) ) {
				continue;
			}
			foreach ( array( 'en_US', 'pt_BR' ) as $locale ) {
				$html = TutorialStepImage\render( $step, $locale );
				preg_match_all( '/\b(evcert-tsi-lay-[a-z0-9]+|evcert-tsi-css-[a-z0-9]+)\b/', $html, $found );
				foreach ( array_unique( $found[1] ) as $name ) {
					$this->assertStringContainsString( '.' . $name, $css, "Step $step, $locale: no rule for $name. Run: wp --require=bin/tutorial-css.php evcert tutorial-css" );
				}
			}
		}
	}
}
