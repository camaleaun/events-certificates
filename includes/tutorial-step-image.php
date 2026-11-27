<?php
/**
 * The pictures of the Meetup tutorial, drawn instead of photographed.
 *
 * Each step is the organizer's Meetup screen rebuilt with core blocks
 * (templates/tutorial-step-image/step-N.php), run through do_blocks() and
 * placed in an inline SVG, in a <foreignObject> that is exactly the size of the
 * viewBox, with a red frame around what the step asks to click. Meetup's words
 * are translatable (_x with the context "Meetup") and the sample data follows
 * the language: Brazilian for pt_BR, New York for the others. The result goes
 * through wp_kses(), the same on the page, in the editor and in the admin.
 * docs/decisions/0033.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\TutorialStepImage;

defined( 'ABSPATH' ) || exit;

const BLOCK      = 'events-certificates/tutorial-step-image';
const STYLE      = 'evcert-tutorial-step-image';
const VIEW       = 'evcert-tutorial-step-image-view';
const COLLECTION = 'events-certificates';

/**
 * Hooks.
 */
function bootstrap() {
	add_action( 'init', __NAMESPACE__ . '\register' );
}

/**
 * Registers the style, the icons and the block.
 */
function register() {
	$style = \EventsCertificate\Assets\src( 'tutorial-step-image', 'css' );
	wp_register_style( STYLE, $style['url'], array(), $style['version'] );
	$view = \EventsCertificate\Assets\src( 'tutorial-step-image', 'js' );
	wp_register_script(
		VIEW,
		$view['url'],
		array(),
		$view['version'],
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	register_icons();
	// Once: a second call (a test, a plugin loaded late) is a notice.
	if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( BLOCK ) ) {
		register_block_type( dirname( __DIR__ ) . '/blocks/tutorial-step-image' );
	}
}

/**
 * The icons Meetup's screens use, by file name (assets/images/tutorial-step-image/icons/<name>.svg).
 *
 * @return array<string, string> Name => label.
 */
function icons() {
	return array(
		'arrow-left'             => __( 'Arrow left', 'events-certificates' ),
		'calendar-off'           => __( 'Calendar off', 'events-certificates' ),
		'calendar-plus'          => __( 'Calendar plus', 'events-certificates' ),
		'circle-check'           => __( 'Circle check', 'events-certificates' ),
		'cloud-download'         => __( 'Cloud download', 'events-certificates' ),
		'check'                  => __( 'Check', 'events-certificates' ),
		'chevron-down'           => __( 'Chevron down', 'events-certificates' ),
		'comment'                => __( 'Comment', 'events-certificates' ),
		'copy'                   => __( 'Copy', 'events-certificates' ),
		'envelope'               => __( 'Envelope', 'events-certificates' ),
		'external'               => __( 'External link', 'events-certificates' ),
		'gear'                   => __( 'Gear', 'events-certificates' ),
		'heart'                  => __( 'Heart', 'events-certificates' ),
		'mail'                   => __( 'Mail', 'events-certificates' ),
		'people'                 => __( 'People', 'events-certificates' ),
		'search'                 => __( 'Search', 'events-certificates' ),
		'simple-horizontal-line' => __( 'Horizontal line', 'events-certificates' ),
		'sort'                   => __( 'Sort', 'events-certificates' ),
		'star'                   => __( 'Star', 'events-certificates' ),
		'x'                      => __( 'Cross', 'events-certificates' ),
	);
}

/**
 * Registers the icons once, with the Icon block's registry (WordPress 7.1).
 */
function register_icons() {
	if ( ! function_exists( 'wp_register_icon_collection' ) || ! class_exists( '\WP_Icon_Collections_Registry' ) || ! class_exists( '\WP_Icons_Registry' ) ) {
		return;
	}
	if ( ! \WP_Icon_Collections_Registry::get_instance()->is_registered( COLLECTION ) ) {
		wp_register_icon_collection( COLLECTION, array( 'label' => __( 'Events Certificates', 'events-certificates' ) ) );
	}
	foreach ( icons() as $name => $label ) {
		if ( \WP_Icons_Registry::get_instance()->is_registered( COLLECTION . '/' . $name ) ) {
			continue;
		}
		wp_register_icon(
			COLLECTION . '/' . $name,
			array(
				'label'     => $label,
				'file_path' => dirname( __DIR__ ) . '/assets/images/tutorial-step-image/icons/' . $name . '.svg',
			)
		);
	}
}

/**
 * The drawings: the size of each one (its viewBox) and the frame that marks what to click.
 *
 * @return array<int, array{width: float, height: float, pixels: array<int, int>, title: string, desc: string}>
 */
function steps() {
	// Each window is where the picture of the first generator looked at the page (the scale and the position were
	// fitted to those pictures): its size in units, the size in pixels of that picture (the svg's width and height, as the JPG was) and the page's block ("fo") placed inside it. The red frame is a
	// box of the markup (evcert-tsi-frame), so it follows the size of what it frames in any language.
	return array(
		1 => array(
			'width'  => 784,
			'height' => 621.7,
			'pixels' => array( 1000, 793 ),
			'fo'     => array(
				'x'      => 17.3,
				'y'      => -10.9,
				'width'  => 784,
				'height' => 633.7,
			),
			'title'  => __( 'Step 1: the group\'s past events on Meetup', 'events-certificates' ),
			'desc'   => __( 'The first meetup of the list is framed in red: open it.', 'events-certificates' ),
		),
		2 => array(
			'width'  => 1286.2,
			'height' => 321.5,
			'pixels' => array( 1000, 250 ),
			'fo'     => array(
				'x'      => 56.6,
				'y'      => -265.2,
				'width'  => 1219.3,
				'height' => 587.5,
			),
			'title'  => __( 'Step 2: the event page on Meetup', 'events-certificates' ),
			'desc'   => __( 'The Attendees button is framed in red.', 'events-certificates' ),
		),
		3 => array(
			'width'  => 911.6,
			'height' => 485,
			'pixels' => array( 1000, 532 ),
			'fo'     => array(
				'x'      => 128.5,
				'y'      => 26.6,
				'width'  => 740,
				'height' => 460.4,
			),
			'title'  => __( 'Step 3: the attendees screen on Meetup', 'events-certificates' ),
			'desc'   => __( 'The Manage button is framed in red.', 'events-certificates' ),
		),
		4 => array(
			'width'  => 952.6,
			'height' => 373.4,
			'pixels' => array( 1000, 392 ),
			'fo'     => array(
				'x'      => 134.7,
				'y'      => 45.65,
				'width'  => 800,
				'height' => 330.3,
			),
			'title'  => __( 'Step 4: the Manage menu on Meetup', 'events-certificates' ),
			'desc'   => __( 'The Attendee details item is framed in red.', 'events-certificates' ),
		),
	);
}

/**
 * Meetup's own words, in the current language.
 *
 * Each one is written as Meetup shows it in the language: where Meetup keeps a word in English, the
 * translation stays in English.
 *
 * @return array<string, string>
 */
function labels() {
	return array(
		/* translators: Meetup's own label: the group's finished meetups. Write it as Meetup shows it in your language. */
		'past_events'   => _x( 'Past events', 'Meetup', 'events-certificates' ),
		/* translators: Meetup's own label: the link to the whole list. Write it as Meetup shows it in your language. */
		'see_all'       => _x( 'See all', 'Meetup', 'events-certificates' ),
		/* translators: Meetup's own label: the organizer's button on a meetup. Write it as Meetup shows it in your language. */
		'manage'        => _x( 'Manage', 'Meetup', 'events-certificates' ),
		/* translators: Meetup's own label: the organizer's button on a meetup. The original picture of this step was taken with Meetup in English, so Brazilian Portuguese leaves it in English (do not translate it there); any other language writes it as Meetup shows it. */
		'manage_en'     => _x( 'Manage', 'Meetup (EN)', 'events-certificates' ),
		/* translators: Meetup's own label: the badge of a meetup the member attended. Write it as Meetup shows it in your language. */
		'went'          => _x( 'Went', 'Meetup', 'events-certificates' ),
		/* translators: Meetup's own label: the heading of a meetup's description. Write it as Meetup shows it in your language. */
		'details'       => _x( 'Details', 'Meetup', 'events-certificates' ),
		/* translators: Meetup's own label: the organizer's button to the list of people. Write it as Meetup shows it in your language. */
		'attendees'     => _x( 'Attendees', 'Meetup', 'events-certificates' ),
		/* translators: Meetup's own label: the organizer's button to the list of people. The original picture of this step was taken with Meetup in English, so Brazilian Portuguese leaves it in English (do not translate it there); any other language writes it as Meetup shows it. */
		'attendees_en'  => _x( 'Attendees', 'Meetup (EN)', 'events-certificates' ),
		/* translators: Meetup's own label: the organizer's button to copy a meetup. Write it as Meetup shows it in your language. */
		'duplicate'     => _x( 'Duplicate', 'Meetup', 'events-certificates' ),
		/* translators: Meetup's own label: the organizer's button to write to the people. Write it as Meetup shows it in your language. */
		'contact'       => _x( 'Contact', 'Meetup', 'events-certificates' ),
		/* translators: Meetup's own label: the organizer's button to the feedback. Write it as Meetup shows it in your language. */
		'feedback'      => _x( 'Feedback', 'Meetup', 'events-certificates' ),
		/* translators: Meetup's own label: the link back to the page before. The original picture of this step was taken with Meetup in English, so Brazilian Portuguese leaves it in English (do not translate it there); any other language writes it as Meetup shows it. */
		'back'          => _x( 'Back', 'Meetup (EN)', 'events-certificates' ),
		/* translators: Meetup's own label: the placeholder of the search field on the list of people. The original picture of this step was taken with Meetup in English, so Brazilian Portuguese leaves it in English (do not translate it there); any other language writes it as Meetup shows it. */
		'search_people' => _x( 'Search attendees', 'Meetup (EN)', 'events-certificates' ),
		/* translators: Meetup's own sentence on the banner of the list of people. The original picture of this step was taken with Meetup in English, so Brazilian Portuguese leaves it in English (do not translate it there); any other language writes it as Meetup shows it. */
		'promo_text'    => _x( 'See what you have in common with other event-goers!', 'Meetup (EN)', 'events-certificates' ),
		/* translators: Meetup's own label: the button on the banner of the list of people. The original picture of this step was taken with Meetup in English, so Brazilian Portuguese leaves it in English (do not translate it there); any other language writes it as Meetup shows it. */
		'promo_button'  => _x( 'Start your 7 day free trial', 'Meetup (EN)', 'events-certificates' ),
		/* translators: Meetup's own menu item of the Manage menu, to stop taking RSVPs. The original picture of this step was taken with Meetup in English, so Brazilian Portuguese leaves it in English (do not translate it there); any other language writes it as Meetup shows it. */
		'close_rsvps'   => _x( 'Close for RSVPs', 'Meetup (EN)', 'events-certificates' ),
		/* translators: Meetup's own menu item of the Manage menu, to write to the attendees. The original picture of this step was taken with Meetup in English, so Brazilian Portuguese leaves it in English (do not translate it there); any other language writes it as Meetup shows it. */
		'contact_all'   => _x( 'Contact attendees', 'Meetup (EN)', 'events-certificates' ),
		/* translators: Meetup's own menu item of the Manage menu, to select attendees. The original picture of this step was taken with Meetup in English, so Brazilian Portuguese leaves it in English (do not translate it there); any other language writes it as Meetup shows it. */
		'select'        => _x( 'Select', 'Meetup (EN)', 'events-certificates' ),
		/* translators: Meetup's own menu item of the Manage menu, to download the list of attendees. The original picture of this step was taken with Meetup in English, so Brazilian Portuguese leaves it in English (do not translate it there); any other language writes it as Meetup shows it. */
		'attendee_info' => _x( 'Attendee details', 'Meetup (EN)', 'events-certificates' ),
		/* translators: Meetup's own label: the ticket of a meetup. Write it as Meetup shows it in your language. */
		'ticket'        => _x( 'Ticket', 'Meetup', 'events-certificates' ),
		/* translators: Meetup's own label: the link to the ticket. Write it as Meetup shows it in your language. */
		'view_ticket'   => _x( 'View ticket', 'Meetup', 'events-certificates' ),
	);
}

/**
 * How many people came, with Meetup's wording ("20 attendees").
 *
 * @param int $count People.
 * @return string
 */
function attendees_label( $count ) {
	/* translators: Meetup's own label, with the number of people at a meetup: write it as Meetup shows it in your language. %d: people. */
	return sprintf( _nx( '%d attendee', '%d attendees', $count, 'Meetup', 'events-certificates' ), $count );
}

/**
 * How long a made-up meetup lasts: its end is its start plus this.
 */
const DURATION = 'PT2H';

/**
 * A meetup's time from its compact UTC form.
 *
 * @param string $compact UTC date and time, like 20261027T2200.
 * @return \DateTimeImmutable
 */
function starts_at( $compact ) {
	$date = \DateTimeImmutable::createFromFormat( 'Ymd\THi', (string) $compact, new \DateTimeZone( 'UTC' ) );

	return $date ? $date : new \DateTimeImmutable( '@0' );
}

/**
 * When a made-up meetup ends: two hours after it starts, in any language.
 *
 * @param string $compact Its start, UTC, like 20261027T2200.
 * @return \DateTimeImmutable
 */
function ends_at( $compact ) {
	return starts_at( $compact )->add( new \DateInterval( DURATION ) );
}

/**
 * A time zone by its PHP name; UTC when a translation gives one PHP does not know.
 *
 * @param string $name The name (America/New_York).
 * @return \DateTimeZone
 */
function time_zone( $name ) {
	try {
		return new \DateTimeZone( (string) $name );
	} catch ( \Exception $e ) {
		return new \DateTimeZone( 'UTC' );
	}
}

/**
 * A date as Meetup writes it in the language of the site.
 *
 * WordPress capitalizes the months and days of some languages (Oct) where Meetup writes them in lower case (oct).
 *
 * @param string        $format    PHP date format.
 * @param int           $timestamp The time.
 * @param \DateTimeZone $zone      The time zone.
 * @return string
 */
function meetup_date( $format, $timestamp, $zone ) {
	$text = wp_date( $format, $timestamp, $zone );
	/* translators: Whether Meetup writes the months and days of a date in lower case in your language: "yes" or "no". Spanish: yes. */
	return 'yes' === _x( 'no', 'Meetup lower case dates', 'events-certificates' ) ? mb_strtolower( $text ) : $text;
}

/**
 * A time the way Meetup writes it in a list, in the scenario's time zone.
 *
 * @param \DateTimeImmutable   $date     The time.
 * @param array<string, mixed> $scenario The scenario (time zone and its abbreviation).
 * @return string
 */
function format_time( \DateTimeImmutable $date, array $scenario ) {
	$zone = time_zone( $scenario['timezone'] );
	/* translators: Meetup's date and time in a list of meetups, as PHP date format. English example: Tue, Oct 27 · 6:00 PM. Write it as Meetup shows it in your language. */
	$format = _x( 'D, M j · g:i A', 'Meetup', 'events-certificates' );

	/* translators: Meetup's date and time followed by the time zone. %1$s: the date and time. %2$s: the zone (BRT, EDT). Write it as Meetup shows it in your language: in Portuguese there is no space before the zone. */
	$join = _x( '%1$s %2$s', 'Meetup', 'events-certificates' );

	return trim( sprintf( $join, meetup_date( $format, $date->getTimestamp(), $zone ), '' !== $scenario['zone'] ? $scenario['zone'] : wp_date( 'T', $date->getTimestamp(), $zone ) ) );
}

/**
 * A meetup's start, the way Meetup writes it in a list.
 *
 * @param string               $at       Its start, UTC, like 20261027T2200.
 * @param array<string, mixed> $scenario The scenario.
 * @return string
 */
function when_label( $at, array $scenario ) {
	return format_time( starts_at( $at ), $scenario );
}

/**
 * "Hosted by Jeff M." / "Hosted by WordPress and 4 others", with the name in bold.
 *
 * @param array<string, mixed> $host The host (name, others).
 * @return string HTML.
 */
function host_line( array $host ) {
	$name = (int) $host['others'] > 0
		/* translators: Meetup's own wording. %1$s: the main host. %2$d: how many other hosts. Write it as Meetup shows it in your language. */
		? sprintf( _nx( '%1$s and %2$d other', '%1$s and %2$d others', (int) $host['others'], 'Meetup', 'events-certificates' ), $host['name'], (int) $host['others'] )
		: $host['name'];

	/* translators: Meetup's own wording. %s: the host or hosts, in bold. Write it as Meetup shows it in your language. */
	return sprintf( _x( 'Hosted by %s', 'Meetup', 'events-certificates' ), '<strong>' . esc_html( $name ) . '</strong>' );
}

/**
 * "WordPress is a Super Organizer", or '' when the host is not one.
 *
 * @param array<string, mixed> $host The host (name, super).
 * @return string HTML.
 */
function organizer_line( array $host ) {
	if ( empty( $host['super'] ) ) {
		return '';
	}
	/* translators: Meetup's own wording. %1$s: the host. %2$s: the title, in bold. Write it as Meetup shows it in your language. */
	return sprintf( _x( '%1$s is a %2$s', 'Meetup', 'events-certificates' ), esc_html( $host['name'] ), '<strong>' . esc_html_x( 'Super Organizer', 'Meetup', 'events-certificates' ) . '</strong>' );
}

/**
 * A group's rating the way Meetup writes it: 4.7 or 4,9, and without ",0".
 *
 * @param float $rating The rating.
 * @return string
 */
function rating_label( $rating ) {
	/* translators: Meetup's decimal separator in a rating (4.8). Write it as Meetup shows it in your language. */
	$decimal = _x( '.', 'Meetup decimal separator', 'events-certificates' );

	return (string) preg_replace( '/' . preg_quote( $decimal, '/' ) . '0$/', '', number_format( (float) $rating, 1, $decimal, '' ) );
}

/**
 * "1,219 reviews" / "555 avaliações".
 *
 * @param int $count Reviews.
 * @return string
 */
function reviews_label( $count ) {
	/* translators: Meetup's decimal separator. Write it as Meetup shows it in your language. */
	$decimal = _x( '.', 'Meetup', 'events-certificates' );
	/* translators: Meetup's thousands separator. Write it as Meetup shows it in your language. */
	$thousands = _x( ',', 'Meetup', 'events-certificates' );

	/* translators: Meetup's own wording, with the number of reviews. %s: the number. Write it as Meetup shows it in your language. */
	return sprintf( _nx( '%s review', '%s reviews', (int) $count, 'Meetup', 'events-certificates' ), number_format( (float) $count, 0, $decimal, $thousands ) );
}

/**
 * The count tabs of the list of people, as Meetup writes them ("27 Attendees", "0 Checked in"...).
 * Meetup uses the plural for zero, so the plural form is chosen by 1 or not, not by the language's rule.
 *
 * @param array<string, int> $people The counts (attendees, checked, absent, canceled).
 * @return array<string, string>
 */
function people_tabs( array $people ) {
	// Meetup writes the plural for zero, so the form is 1 or the other, not the language's rule. The extractor needs a variable.
	$attendees = 1 === (int) $people['attendees'] ? 1 : 2;
	$absent    = 1 === (int) $people['absent'] ? 1 : 2;
	$canceled  = 1 === (int) $people['canceled'] ? 1 : 2;

	return array(
		/* translators: Meetup's own tab with the number of people. The original picture of this step was taken with Meetup in English, so Brazilian Portuguese leaves it in English (do not translate it there); any other language writes it as Meetup shows it. */
		'attendees'   => sprintf( _nx( '%d Attendee', '%d Attendees', $attendees, 'Meetup (EN)', 'events-certificates' ), $people['attendees'] ),
		/* translators: Meetup's own tab with the number of people checked in. The original picture of this step was taken with Meetup in English, so Brazilian Portuguese leaves it in English (do not translate it there); any other language writes it as Meetup shows it. */
		'checked'     => sprintf( _x( '%d Checked in', 'Meetup (EN)', 'events-certificates' ), $people['checked'] ),
		/* translators: Meetup's own tab with the number of people not checked in. The original picture of this step was taken with Meetup in English, so Brazilian Portuguese leaves it in English (do not translate it there); any other language writes it as Meetup shows it. */
		'not_checked' => sprintf( _x( '%d Not checked in', 'Meetup (EN)', 'events-certificates' ), $people['attendees'] - $people['checked'] ),
		/* translators: Meetup's own tab with the number of people absent. The original picture of this step was taken with Meetup in English, so Brazilian Portuguese leaves it in English (do not translate it there); any other language writes it as Meetup shows it. */
		'absent'      => sprintf( _nx( '%d Absent', '%d Absent', $absent, 'Meetup (EN)', 'events-certificates' ), $people['absent'] ),
		/* translators: Meetup's own tab with the number of people who canceled. The original picture of this step was taken with Meetup in English, so Brazilian Portuguese leaves it in English (do not translate it there); any other language writes it as Meetup shows it. */
		'canceled'    => sprintf( _nx( '%d Canceled', '%d Canceled', $canceled, 'Meetup (EN)', 'events-certificates' ), $people['canceled'] ),
	);
}

/**
 * A meetup's day and hours the way Meetup writes them on its page: "Tuesday, Oct 27 · 6:00 PM to 8:00 PM EDT".
 *
 * @param string               $at       Its start, UTC, like 20261027T2200 (it ends two hours later).
 * @param array<string, mixed> $scenario The scenario (time zone and its abbreviation).
 * @return string
 */
function range_label( $at, array $scenario ) {
	$zone  = time_zone( $scenario['timezone'] );
	$start = starts_at( $at );
	$end   = ends_at( $at );
	/* translators: Meetup's day and time on a meetup's page, as PHP date format. English example: Tuesday, Oct 27 · 6:00 PM. Write it as Meetup shows it in your language. */
	$day = _x( 'l, M j · g:i A', 'Meetup', 'events-certificates' );
	/* translators: Meetup's closing time on a meetup's page, as PHP date format. English example: 8:00 PM. Write it as Meetup shows it in your language. */
	$hour = _x( 'g:i A', 'Meetup', 'events-certificates' );
	/* translators: Meetup's wording for a time span. %1$s: day and start. %2$s: the end. Write it as Meetup shows it in your language. */
	$span = sprintf( _x( '%1$s to %2$s', 'Meetup', 'events-certificates' ), meetup_date( $day, $start->getTimestamp(), $zone ), meetup_date( $hour, $end->getTimestamp(), $zone ) );

	return $span . ' ' . ( '' !== $scenario['zone'] ? $scenario['zone'] : wp_date( 'T', $start->getTimestamp(), $zone ) );
}

/**
 * The language the sample is read in: Brazilian Portuguese on a site in it, English on every other (a translation
 * of the sample's texts into another language does not change the drawings).
 *
 * @return string pt_BR or en_US.
 */
function sample_locale() {
	return 'pt_BR' === determine_locale() ? 'pt_BR' : 'en_US';
}

/**
 * Runs a callback in the language of the sample (sample_locale()); the site's language is its own again after.
 *
 * @template T
 * @param callable(): T $callback What reads the sample's texts.
 * @return T
 */
function in_sample( $callback ) {
	$locale   = sample_locale();
	$switched = determine_locale() !== $locale && switch_to_locale( $locale );
	try {
		return $callback();
	} finally {
		if ( $switched ) {
			restore_previous_locale();
		}
	}
}

/**
 * The URL of a picture of the drawings, from its number.
 *
 * @param string $folder member, event or hero.
 * @param int    $id     The number (the file's name without the extension).
 * @return string
 */
function picture_url( $folder, $id ) {
	return plugins_url( 'assets/images/tutorial-step-image/' . $folder . '/' . (int) $id . '.webp', dirname( __DIR__ ) . '/events-certificates.php' );
}

/**
 * The sample data the drawings show (includes/tutorial-step-image/sample.php): the WordPress NYC group's, which the
 * site's language translates (Brazilian Portuguese: WordPress Curitiba's), with the dates and counts written the
 * way Meetup writes them in that language.
 *
 * @return array<string, mixed>
 */
function scene() {
	$data  = in_sample( static fn () => require __DIR__ . '/tutorial-step-image/sample.php' );
	$items = array();
	foreach ( $data['events'] as $event ) {
		$item = array( 'photo' => picture_url( 'event', $event['photo'] ) );
		if ( isset( $event['title'] ) ) {
			$item['title']     = $event['title'];
			$item['when']      = when_label( $event['at'], $data );
			$item['place']     = $event['place'];
			$item['attendees'] = attendees_label( (int) $event['attendees'] );
			$item['avatars']   = array_map(
				static fn ( $id ) => picture_url( 'member', $id ),
				$event['avatars']
			);
		}
		$items[] = $item;
	}

	$base = plugins_url( 'assets/images/tutorial-step-image/ui/', dirname( __DIR__ ) . '/events-certificates.php' );
	$ui   = array();
	foreach ( array( 'calendar', 'pin', 'ticket', 'super-organizer', 'banner-bg', 'banner-people' ) as $name ) {
		$ui[ $name ] = $base . $name . '.webp';
	}

	return array(
		'timezone'    => $data['timezone'],
		'total'       => (int) $data['total'],
		'events'      => $items,
		'host'        => array(
			'super'     => ! empty( $data['host']['super'] ),
			'photo'     => picture_url( 'member', $data['host']['photo'] ),
			'line'      => host_line( $data['host'] ),
			'organizer' => organizer_line( $data['host'] ),
		),
		'group'       => array(
			'name'    => $data['group']['name'],
			'rating'  => rating_label( $data['group']['rating'] ),
			'reviews' => reviews_label( $data['group']['reviews'] ),
		),
		'venue'       => array(
			'name'    => $data['venue']['name'],
			'address' => $data['venue']['address'],
		),
		'hero'        => picture_url( 'hero', $data['hero'] ),
		'description' => $data['description'],
		'pills'       => people_tabs(
			array(
				'attendees' => (int) $data['events'][0]['attendees'],
				'checked'   => 0,
				'absent'    => 0,
				'canceled'  => 1,
			)
		),
		'range'       => range_label( $data['events'][0]['at'], $data ),
		'ui'          => $ui,
	);
}

/**
 * The block markup of a step, with the labels and the data in place.
 *
 * @param int                   $step   Step, 1 to 4.
 * @param array<string, mixed>  $scene  Sample data.
 * @param array<string, string> $labels Meetup's words.
 * @return string Block markup, or '' when the step has no template.
 */
function markup( $step, array $scene, array $labels ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $scene and $labels are the variables the included template reads.
	$file = dirname( __DIR__ ) . '/templates/tutorial-step-image/step-' . (int) $step . '.php';
	if ( ! is_readable( $file ) ) {
		return '';
	}
	ob_start();
	include $file;

	return (string) ob_get_clean();
}

/**
 * What the drawing may contain: the plugin's SVG list, with what the steps use.
 *
 * @return array<string, array<string, bool>>
 */
function allowed() {
	$allowed = \EventsCertificate\QuickGenerator\svg_allowed();

	$allowed['svg']           = array(
		'xmlns'               => true,
		'class'               => true,
		'viewbox'             => true,
		'width'               => true,
		'height'              => true,
		'role'                => true,
		'aria-labelledby'     => true,
		'focusable'           => true,
		'aria-hidden'         => true,
		'style'               => true,
		'preserveaspectratio' => true,
		'fill'                => true,
	);
	$allowed['title']         = array( 'id' => true );
	$allowed['desc']          = array( 'id' => true );
	$allowed['rect']          = array(
		'x'      => true,
		'y'      => true,
		'width'  => true,
		'height' => true,
		'rx'     => true,
		'ry'     => true,
		'fill'   => true,
	);
	$allowed['path']          = array(
		'd'    => true,
		'fill' => true,
	);
	$allowed['foreignobject'] = array(
		'x'      => true,
		'y'      => true,
		'width'  => true,
		'height' => true,
	);
	$allowed['div']           = array(
		'xmlns' => true,
		'class' => true,
		'style' => true,
	);
	$allowed['img']           = array(
		'src'      => true,
		'alt'      => true,
		'width'    => true,
		'height'   => true,
		'class'    => true,
		'style'    => true,
		'loading'  => true,
		'decoding' => true,
	);
	$allowed['figure']        = array(
		'class' => true,
		'style' => true,
	);
	$allowed['p']             = array(
		'class' => true,
		'style' => true,
	);
	$allowed['span']          = array(
		'class' => true,
		'style' => true,
	);
	$allowed['button']        = array(
		'type'          => true,
		'class'         => true,
		'aria-haspopup' => true,
		'aria-label'    => true,
	);

	return $allowed;
}

/**
 * The custom CSS WordPress made for the blocks rendered so far, by the class it gave each block: every rule
 * of the block, with what follows the class in its selector ("" or " svg") and its declarations.
 *
 * @return array<string, array<int, array{0: string, 1: string}>>
 */
function custom_css_rules() {
	$rules = array();
	foreach ( (array) wp_styles()->get_data( 'wp-block-custom-css', 'after' ) as $css ) {
		if ( preg_match_all( '/\.(wp-custom-css-[a-z0-9]+)\)?([^{}]*)\{([^{}]*)\}/', (string) $css, $found, PREG_SET_ORDER ) ) {
			foreach ( $found as $rule ) {
				// WordPress writes a descendant inside the :where(): ":where(.class svg)".
				$rules[ $rule[1] ][] = array( trim( rtrim( $rule[2], ") \t" ) ), trim( $rule[3] ) );
			}
		}
	}

	return $rules;
}

/**
 * The drawing's own class for the custom CSS of a block, named by the CSS and not by the block: WordPress
 * names the class by a hash of the whole block, text included, which changes with the language.
 *
 * @param array<int, array{0: string, 1: string}> $rules The block's rules (custom_css_rules()).
 * @return string
 */
function custom_css_class( array $rules ) {
	return 'evcert-tsi-css-' . substr( md5( (string) wp_json_encode( $rules ) ), 0, 8 );
}

/**
 * The CSS WordPress made for each container class of a rendered drawing, as [ selector after the class, declarations ] lists.
 *
 * WordPress names a container class from a hash that also takes the active theme's default block gap, so the same block
 * gets another name under another theme. The drawing names these classes after their rules instead (container_class()).
 *
 * @param string $html The drawing's blocks, rendered.
 * @return array<string, array<int, array{0: string, 1: string}>> Rules by WordPress's class name.
 */
function container_css_rules( $html ) {
	preg_match_all( '/\b(wp-container-[a-z0-9-]+)\b/', (string) $html, $found );
	$wanted = array_flip( $found[1] );
	$rules  = array();
	$css    = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );
	foreach ( preg_split( '/(?<=\})/', (string) $css ) as $rule ) {
		if ( ! preg_match( '/^\s*([^{}]+)\{([^{}]*)\}\s*$/', $rule, $m ) ) {
			continue;
		}
		foreach ( explode( ',', $m[1] ) as $selector ) {
			if ( preg_match( '/^\s*\.(wp-container-[a-z0-9-]+)(.*)$/s', $selector, $one ) && isset( $wanted[ $one[1] ] ) ) {
				$rules[ $one[1] ][] = array( trim( $one[2] ), trim( $m[2] ) );
			}
		}
	}

	return $rules;
}

/**
 * The name a container class gets in the drawing: the same rules, the same name, under any theme.
 *
 * @param array<int, array{0: string, 1: string}> $rules One class's rules, from container_css_rules().
 * @return string
 */
function container_class( array $rules ) {
	return 'evcert-tsi-lay-' . substr( md5( (string) wp_json_encode( $rules ) ), 0, 8 );
}

/**
 * Gives the classes WordPress puts on blocks for its own layout and styles (flow, flex and grid layout, the
 * container and custom-CSS classes made from the blocks' attributes, the theme's global padding) the drawing's
 * prefix. The theme's and the screen's CSS point to the original names, so it no longer reaches the drawing:
 * it would look different in another theme, in the editor or in the enlarged view. The rules the drawing needs
 * are in assets/scss/tutorial-step-image.scss (the base ones) and _tutorial-step-image-blocks.scss (made by
 * bin/tutorial-css.php), under .evcert-tsi__fo. The element classes, whose number changes each request, go.
 *
 * @param string                                                 $html   Rendered blocks.
 * @param array<string, array<int, array{0: string, 1: string}>> $custom     Custom CSS by WordPress's class (custom_css_rules()).
 * @param array<string, array<int, array{0: string, 1: string}>> $containers Container rules by WordPress's class (container_css_rules()).
 * @return string
 */
function isolate( $html, array $custom = array(), array $containers = array() ) {
	$tags = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		$class = $tags->get_attribute( 'class' );
		if ( ! is_string( $class ) || '' === $class ) {
			continue;
		}
		$names = array();
		foreach ( preg_split( '/\s+/', trim( $class ) ) as $name ) {
			if ( preg_match( '/^wp-elements-\d+$/', $name ) ) {
				continue;
			}
			if ( isset( $custom[ $name ] ) ) {
				$names[] = custom_css_class( $custom[ $name ] );
			} elseif ( isset( $containers[ $name ] ) ) {
				$names[] = container_class( $containers[ $name ] );
			} elseif ( preg_match( '/^(is-layout-|wp-container-|wp-block-group-is-layout-|wp-custom-css-|has-global-padding$|is-nowrap$|is-vertical$|is-content-justification-|is-horizontal$)/', $name ) ) {
				$names[] = 'evcert-tsi-' . $name;
			} else {
				$names[] = $name;
			}
		}
		if ( $names ) {
			$tags->set_attribute( 'class', implode( ' ', $names ) );
		} else {
			$tags->remove_attribute( 'class' );
		}
	}

	return $tags->get_updated_html();
}

/**
 * A number for an SVG attribute: whole when it is whole, up to two decimals otherwise, never in the site's locale.
 *
 * @param int|float|string $value The number.
 * @return string
 */
function num( $value ) {
	return rtrim( rtrim( sprintf( '%.2F', (float) $value ), '0' ), '.' );
}

/**
 * Lets the underline icon stretch to the width of its title.
 *
 * The core icon block keeps only an icon's paths and viewBox, so the file's preserveAspectRatio="none" is lost; it is
 * put back here, only for that icon and only while a drawing is rendered.
 *
 * @param string               $content The icon block's HTML.
 * @param array<string, mixed> $block   The parsed block.
 * @return string
 */
function stretch_line( $content, $block ) {
	if ( COLLECTION . '/simple-horizontal-line' !== ( $block['attrs']['icon'] ?? '' ) ) {
		return $content;
	}
	$tags = new \WP_HTML_Tag_Processor( $content );
	if ( $tags->next_tag( 'svg' ) ) {
		$tags->set_attribute( 'preserveAspectRatio', 'none' );
	}
	return $tags->get_updated_html();
}

/**
 * A step's drawing: the button that enlarges it, around the SVG.
 *
 * @param int         $step   Step, 1 to 4.
 * @param string|null $locale Locale, or null for the current one.
 * @return string Filtered HTML, or '' for an unknown step or one without a template.
 */
function render( $step, $locale = null ) {
	$step  = (int) $step;
	$steps = steps();
	if ( ! isset( $steps[ $step ] ) ) {
		return '';
	}
	$switched = null !== $locale && determine_locale() !== $locale && switch_to_locale( $locale );
	$config   = $steps[ $step ];
	$blocks   = markup( $step, scene(), labels() );
	$title    = $config['title'];
	$desc     = $config['desc'];
	if ( $switched ) {
		restore_previous_locale();
	}
	if ( '' === $blocks ) {
		return '';
	}
	// The drawing's own style, wherever it is printed (the block, the shortcode, the admin screen). The enlarged view is for pages:
	// the admin screen's modal has one of its own, and a second one would open over it and stay.
	wp_enqueue_style( STYLE );
	if ( ! is_admin() ) {
		wp_enqueue_script( VIEW );
	}
	add_filter( 'render_block_core/icon', __NAMESPACE__ . '\\stretch_line', 10, 2 );
	$content = do_blocks( $blocks );
	remove_filter( 'render_block_core/icon', __NAMESPACE__ . '\\stretch_line', 10 );
	// What is drawn inside the picture is cleaned on its own: WordPress 7.2's wp_kses() drops an <svg> whose <foreignObject>
	// holds HTML (everything after it is lost), so the wrapper below is built from values escaped one by one instead.
	$content = wp_kses( isolate( $content, custom_css_rules(), container_css_rules( $content ) ), allowed(), array( 'http', 'https' ) );
	$id      = wp_unique_id( 'evcert-tsi-' );
	$width   = num( $config['width'] );
	$height  = num( $config['height'] );
	$html    = sprintf(
		'<div class="evcert-tsi"><button type="button" class="evcert-tsi__open" aria-haspopup="dialog" aria-label="%1$s">' .
		'<svg xmlns="http://www.w3.org/2000/svg" class="evcert-tsi__svg" viewBox="%8$s %9$s %2$s %3$s" width="%14$s" height="%15$s" role="img" aria-labelledby="%4$s-t %4$s-d">' .
		'<title id="%4$s-t">%5$s</title><desc id="%4$s-d">%6$s</desc>' .
		'<rect x="%8$s" y="%9$s" width="%2$s" height="%3$s" fill="#fcfcfd"/>' .
		'<foreignObject x="%10$s" y="%11$s" width="%12$s" height="%13$s"><div xmlns="http://www.w3.org/1999/xhtml" class="evcert-tsi__fo">%7$s</div></foreignObject>' .
		'</svg></button></div>',
		/* translators: %s: what the picture shows. */
		esc_attr( sprintf( __( 'Enlarge the picture. %s', 'events-certificates' ), $title ) ),
		$width,
		$height,
		esc_attr( $id ),
		esc_html( $title ),
		esc_html( $desc ),
		$content,
		num( $config['x'] ?? 0 ),
		num( $config['y'] ?? 0 ),
		num( $config['fo']['x'] ?? 0 ),
		num( $config['fo']['y'] ?? 0 ),
		num( $config['fo']['width'] ?? $width ),
		num( $config['fo']['height'] ?? $height ),
		num( $config['pixels'][0] ?? $width ),
		num( $config['pixels'][1] ?? $height )
	);

	return $html;
}
