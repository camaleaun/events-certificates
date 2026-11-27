<?php
/**
 * Made-up data for the screenshots of the product site (site/scripts/screenshots.mjs): one event with its participants, issued
 * and corrected certificates, a signer and a signature. Run on a throwaway site: studio wp eval "$(sed 1d bin/screenshots-seed.php)"
 *
 * @package EventsCertificate
 */

use EventsCertificate\Certificates;
use EventsCertificate\Events;
use EventsCertificate\People;
use EventsCertificate\Settings;

wp_set_current_user( 1 );
Settings\update(
	array(
		'verification' => true,
		'force_public' => true,
		'signer_name'  => 'Alex Example',
		'signer_role'  => 'Meetup organizer',
		'location'     => 'Curitiba, Paraná',
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

$event = (int) Events\save(
	array(
		'title'     => 'Blocks for beginners',
		'community' => 'WordPress Example Meetup',
		'start'     => '2026-11-14 09:00',
		'end'       => '2026-11-14 12:00',
		'venue'     => 'Example Library',
		'city'      => 'Curitiba',
		'speakers'  => 'Sam Taylor',
		'hours'     => '3',
	)
);
foreach ( array( 'Ana Example', 'Bruno Sample', 'Carla Demo', 'Diego Test', 'Elisa Fiction', 'Fabio Placeholder', 'Gabi Mockup' ) as $i => $name ) {
	People\add(
		$event,
		array(
			'name'   => $name,
			'email'  => strtolower( explode( ' ', $name )[0] ) . '@example.test',
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
// A correction (the old code says it was replaced) and a participant in the Trash.
$ana = People\query( $event, array( 'search' => 'Ana' ) )['rows'][0];
Certificates\reissue( People\certificate( $ana, 'attendee' ), array( 'name' => 'Ana Maria Example' ) );
$gabi = People\query( $event, array( 'search' => 'Gabi' ) )['rows'][0];
People\trash( array( (int) $gabi['id'] ), $event );
echo "seeded event $event\n";
