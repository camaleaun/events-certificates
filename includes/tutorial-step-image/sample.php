<?php
/**
 * The sample the tutorial's drawings show: the data of the WordPress NYC group, in English, and, in Brazilian
 * Portuguese, WordPress Curitiba's.
 *
 * - Numbers, the pictures (only the number of the file, in assets/images/tutorial-step-image/ without its folder
 *   and extension), the times (UTC, as 20261027T2200) and the time zone are two sets in this file, the default and
 *   Brazilian Portuguese's: a translation cannot change them.
 * - The texts (a name, a place, a description) go through gettext, but only Brazilian Portuguese is translated:
 *   the caller reads this file in English for every other language (TutorialStepImage\in_sample()), so a
 *   translation of "New York" into another city changes nothing.
 * - The words and formats of Meetup's own screens are not here: they are translated in every language (labels()).
 *
 * @package EventsCertificate
 */

defined( 'ABSPATH' ) || exit;

$evcert_br = 'pt_BR' === determine_locale();

return array(
	'timezone'    => $evcert_br ? 'America/Sao_Paulo' : 'America/New_York',
	'zone'        => $evcert_br ? 'BRT' : 'EDT',
	'total'       => $evcert_br ? 91 : 398,
	'host'        => array(
		'name'   => $evcert_br ? 'WordPress' : 'Jeff M.',
		'others' => $evcert_br ? 4 : 0,
		'super'  => $evcert_br ? 1 : 0,
		'photo'  => $evcert_br ? 322336120 : 323853340,
	),
	'group'       => array(
		/* translators: Sample of the tutorial's pictures (name of the group). Only Brazilian Portuguese changes it, to your group's; every other language shows the English one. */
		'name'    => _x( 'WordPress NYC Meetup Group', 'Meetup', 'events-certificates' ),
		'rating'  => $evcert_br ? 4.9 : 4.7,
		'reviews' => $evcert_br ? 555 : 1219,
	),
	'venue'       => array(
		/* translators: Sample of the tutorial's pictures (name of the venue). Only Brazilian Portuguese changes it, to your group's; every other language shows the English one. */
		'name'    => _x( '166 Crosby Street', 'Meetup', 'events-certificates' ),
		/* translators: Sample of the tutorial's pictures (address of the venue). Only Brazilian Portuguese changes it, to your group's; every other language shows the English one. */
		'address' => _x( '166 Crosby St, New York, NY 10012, USA · New York, NY', 'Meetup sample: address of the venue', 'events-certificates' ),
	),
	'hero'        => $evcert_br ? 487615981 : 446074100,
	/* translators: Sample of the tutorial's pictures (description of the first event, one paragraph a line). Only Brazilian Portuguese changes it, to your group's; every other language shows the English one. */
	'description' => _x(
		'Community is vital to any open source ecosystem. It jumpstarts projects and careers, gives us encouragement, and helps us keep pushing forward together. And in open source communities, shouldn\'t the events platform be open too?

Mike Auteri decided to do something about it. He brought the idea to the WordPress community and spent years building GatherPress, while navigating the very real challenges of adoption across a global community. Along the way, GatherPress has found its way into communities well beyond WordPress.
Now the WordPress Foundation is building its events platform on GatherPress, and that work is moving quickly. Mike will share the story of how we got here, what the team has learned, and where we go next.',
		'Meetup sample: description of the first event, one paragraph a line',
		'events-certificates'
	),
	'events'      => array(
		array(
			/* translators: Sample of the tutorial's pictures (title of event 1). Only Brazilian Portuguese changes it, to your group's; every other language shows the English one. */
			'title'     => _x( 'A Case for Community: Open Source Events with GatherPress', 'Meetup', 'events-certificates' ),
			'at'        => $evcert_br ? '20261027T2200' : '20261027T2200',
			/* translators: Sample of the tutorial's pictures (place of event 1, as the list shows it). Only Brazilian Portuguese changes it, to your group's; every other language shows the English one. */
			'place'     => _x( '166 Crosby Street, 166 Crosby St, New York, NY 10012, USA, New York, NY, US', 'Meetup sample: place of event 1, as the list shows it', 'events-certificates' ),
			'attendees' => $evcert_br ? 40 : 20,
			'photo'     => $evcert_br ? 536487429 : 536309234,
			'avatars'   => array(
				$evcert_br ? 326222861 : 323853340,
				$evcert_br ? 264395791 : 286807252,
				$evcert_br ? 320110966 : 323732251,
			),
		),
		array(
			/* translators: Sample of the tutorial's pictures (title of event 2). Only Brazilian Portuguese changes it, to your group's; every other language shows the English one. */
			'title'     => _x( 'Pints, Prompts & Page Builders - An Open Source AI Meetup', 'Meetup sample: title of event 2', 'events-certificates' ),
			'at'        => $evcert_br ? '20260901T2200' : '20260825T2130',
			/* translators: Sample of the tutorial's pictures (place of event 2, as the list shows it). Only Brazilian Portuguese changes it, to your group's; every other language shows the English one. */
			'place'     => _x( '166 Crosby St, New York, NY, US', 'Meetup sample: place of event 2, as the list shows it', 'events-certificates' ),
			'attendees' => $evcert_br ? 20 : 41,
			'photo'     => $evcert_br ? 535827939 : 535504383,
			'avatars'   => array(
				$evcert_br ? 326222861 : 323853340,
				$evcert_br ? 264395791 : 326195550,
				$evcert_br ? 314209766 : 321854570,
			),
		),
		array(
			'photo' => $evcert_br ? 535432316 : 446074100,
		),
		array(
			'photo' => $evcert_br ? 506468373 : 533321270,
		),
	),
);
