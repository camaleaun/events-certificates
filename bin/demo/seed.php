<?php
/**
 * Made-up data for the demo sites of the product site (site/scripts/demo-sites.sh): the screenshots and, later, the Playground blueprints
 * run this same file. One scenario and one language per run; nothing here is a real person or community.
 *
 *   studio wp --path=<site> eval-file bin/demo/seed.php standalone en
 *
 * It is made for a throwaway site: it adds data and remembers the ids in the option `evcert_demo`, and a second run only prints them.
 * The last line is JSON: the ids the screenshot manifest's paths need ({event}, {design}, {course}).
 *
 * @package EventsCertificate
 */

use EventsCertificate\Certificates;
use EventsCertificate\Designs;
use EventsCertificate\Events;
use EventsCertificate\People;
use EventsCertificate\Settings;

$evcert_args     = isset( $args ) ? (array) $args : array();
$evcert_scenario = isset( $evcert_demo_scenario ) ? $evcert_demo_scenario : ( $evcert_args[0] ?? 'standalone' );
$evcert_locale   = isset( $evcert_demo_locale ) ? $evcert_demo_locale : ( $evcert_args[1] ?? 'en' );

$evcert_done = get_option( 'evcert_demo' );
if ( is_array( $evcert_done ) ) {
	echo 'EVCERT_DEMO ' . wp_json_encode( $evcert_done ) . "\n";
	return;
}
if ( 'standalone' !== $evcert_scenario ) {
	WP_CLI::error( "The demo data of '$evcert_scenario' is not written yet." );
}

$br = 'br' === $evcert_locale;
wp_set_current_user( 1 );
update_option( 'permalink_structure', '/%postname%/' );
// The site's name shows on the certificates ("took part in <site>"): a made-up community, not the name of the demo site.
update_option( 'blogname', $br ? 'Meetup WordPress Curitiba' : 'WordPress Meetup New York' );
Settings\update(
	array(
		'verification' => true,
		'force_public' => true,
		'signer_name'  => $br ? 'Alex Exemplo' : 'Alex Example',
		'signer_role'  => $br ? 'Organização do meetup' : 'Meetup organizer',
		'location'     => $br ? 'Curitiba, Paraná' : 'New York, NY',
		'issued_date'  => 'event_end',
	)
);
update_option(
	'evcert_signature',
	array(
		'vw'    => 200,
		'vh'    => 60,
		'paths' => array( array( 'd' => 'M6 44C30 6 52 58 82 24S132 10 150 38L190 20L194 26C170 36 150 50 128 38C104 22 86 62 56 46C40 36 22 40 6 44Z', 'fill' => '#1d2327' ) ),
	),
	false
);

// The starter templates, and the pages the plugin makes (they are made on an administrator's first screen otherwise).
Designs\restore_starters( true );
\EventsCertificate\QuickGenerator\ensure_page();
\EventsCertificate\VerifyPage\ensure_page();
\EventsCertificate\Mine\ensure_page();

$names = $br
	? array( 'Ana Exemplo', 'Bruno Amostra', 'Carla Demonstração', 'Diego Teste', 'Elisa Ficção', 'Fábio Modelo', 'Gabi Maquete' )
	: array( 'Ana Example', 'Bruno Sample', 'Carla Demo', 'Diego Test', 'Elisa Fiction', 'Fabio Placeholder', 'Gabi Mockup' );

// The meetup: seven people, issued certificates, a correction and one in the Trash.
$event = (int) Events\save(
	array(
		'title'     => $br ? 'Blocos para iniciantes' : 'Blocks for beginners',
		'community' => $br ? 'Meetup WordPress Curitiba' : 'WordPress Meetup New York',
		'start'     => '2026-11-14 09:00',
		'end'       => '2026-11-14 12:00',
		'venue'     => $br ? 'Biblioteca Exemplo' : 'Example Library',
		'city'      => $br ? 'Curitiba' : 'New York',
		'speakers'  => 'Sam Taylor',
		'hours'     => '3',
	)
);
foreach ( $names as $i => $name ) {
	People\add(
		$event,
		array(
			'name'   => $name,
			'email'  => 'person' . ( $i + 1 ) . '@example.test',
			'status' => $i < 5 ? 'attended' : ( 5 === $i ? 'no_show' : 'registered' ),
			'roles'  => 1 === $i ? array( 'speaker' ) : array( 'attendee' ),
			'source' => 'manual',
		)
	);
}
foreach ( People\query( $event )['rows'] as $person ) {
	if ( 'attended' === $person['status'] ) {
		People\issue( $person, (string) array_key_first( $person['roles'] ) );
	}
}
$first = People\query( $event, array( 'search' => 'Ana' ) )['rows'][0];
Certificates\reissue( People\certificate( $first, 'attendee' ), array( 'name' => $br ? 'Ana Maria Exemplo' : 'Ana Maria Example' ) );
$last = People\query( $event, array( 'search' => $br ? 'Gabi' : 'Gabi' ) )['rows'][0];
People\trash( array( (int) $last['id'] ), $event );

// The course: four classes, each its own event; the instructor is a speaker.
$course = 0;
foreach ( range( 1, 4 ) as $class ) {
	$id = (int) Events\save(
		array(
			'title'     => ( $br ? 'Automaquiagem para iniciantes: aula ' : 'Self-makeup for beginners: class ' ) . $class,
			'community' => $br ? 'Estúdio Exemplo' : 'Example Studio',
			'start'     => sprintf( '2026-10-%02d 18:00', 5 + 7 * ( $class - 1 ) ),
			'end'       => sprintf( '2026-10-%02d 20:00', 5 + 7 * ( $class - 1 ) ),
			'venue'     => $br ? 'Estúdio Exemplo' : 'Example Studio',
			'hours'     => '2',
			'speakers'  => 'Marina Souza',
		)
	);
	$course = $course ? $course : $id;
	People\add(
		$id,
		array(
			'name'   => 'Marina Souza',
			'email'  => 'marina@example.test',
			'status' => 'attended',
			'roles'  => array( 'speaker' ),
			'source' => 'manual',
		)
	);
	foreach ( array_slice( $names, 0, 5 ) as $i => $name ) {
		People\add(
			$id,
			array(
				'name'   => $name,
				'email'  => 'person' . ( $i + 1 ) . '@example.test',
				'status' => 'attended',
				'roles'  => array( 'attendee' ),
				'source' => 'manual',
			)
		);
	}
	// The classes already given have their certificates issued.
	foreach ( People\query( $id )['rows'] as $person ) {
		People\issue( $person, (string) array_key_first( $person['roles'] ) );
	}
}

$ids = array(
	'scenario' => $evcert_scenario,
	'locale'   => $evcert_locale,
	'event'    => $event,
	'course'   => $course,
	'design'   => (int) Designs\site_design_id(),
);
update_option( 'evcert_demo', $ids, false );
echo 'EVCERT_DEMO ' . wp_json_encode( $ids ) . "\n";
