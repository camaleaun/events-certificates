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
// The stroke of the editor's signature icon (src/editor/icons.tsx), drawn as a pen would: thin at the ends, full in the middle.
update_option(
	'evcert_signature',
	array(
		'vw'    => 200,
		'vh'    => 116,
		'paths' => array( array( 'd' => 'M7 108L9 105L11 101L12 97L14 93L16 89L17 85L19 81L21 78L22 74L24 70L25 66L27 62L28 58L30 54L32 50L33 46L35 42L36 39L38 35L40 31L42 27L44 24L46 20L48 17L50 13L53 11L55 9L57 9L58 11L59 15L59 19L59 23L59 27L58 31L58 35L58 39L57 43L57 47L56 51L55 56L55 60L54 64L53 68L52 72L51 76L51 80L50 85L50 90L51 95L58 98L64 96L67 92L70 89L72 85L75 82L77 78L79 74L81 71L83 67L86 64L88 60L90 57L93 54L95 51L98 49L101 48L104 48L104 49L105 52L106 56L106 61L106 65L107 70L112 74L117 74L122 73L126 70L129 67L132 64L135 61L138 59L141 56L145 54L148 52L152 52L155 52L158 53L160 56L161 59L163 64L166 67L171 69L176 68L180 66L184 64L187 61L190 58L193 55L196 52L199 49L197 47L194 50L191 53L188 56L185 59L182 62L179 64L175 65L171 65L169 64L167 62L165 58L164 54L161 50L156 47L151 47L147 48L142 50L139 52L135 55L132 58L129 61L126 63L123 66L119 68L116 69L114 69L113 68L112 64L112 60L111 56L111 52L110 47L106 42L100 42L95 44L91 46L88 50L85 53L82 57L80 60L78 64L76 67L73 71L71 75L69 78L67 82L65 85L62 88L59 90L57 92L57 92L57 89L57 85L57 82L58 77L59 73L60 69L60 65L61 61L62 57L62 52L63 48L63 44L64 40L64 36L65 31L65 27L65 23L65 18L64 14L63 9L60 4L53 4L49 6L46 10L43 14L41 17L39 21L37 25L35 29L34 33L32 37L30 41L29 45L27 49L26 53L25 56L23 60L22 64L20 68L19 72L17 76L16 80L14 84L13 88L11 92L10 96L8 100L7 104L5 108Z', 'fill' => '#1d2327' ) ),
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

// The meetup is the sample of the tutorial's pictures (the WordPress NYC group and its GatherPress talk) with the two names the Quick
// generator's list shows as an example: what a person sees in the plugin's own examples is what the pictures show.
$event = (int) Events\save(
	array(
		'title'     => $br ? 'Blocos para iniciantes' : 'A Case for Community: Open Source Events with GatherPress',
		'community' => $br ? 'Meetup WordPress Curitiba' : 'WordPress NYC Meetup Group',
		'start'     => '2026-10-27 18:00',
		'end'       => '2026-10-27 20:00',
		'venue'     => $br ? 'Biblioteca Exemplo' : '166 Crosby Street',
		'city'      => $br ? 'Curitiba' : 'New York, NY',
		'speakers'  => $br ? 'Sam Taylor' : 'Mike Auteri',
		'hours'     => '2',
	)
);
foreach ( $br ? array( 'Ana Exemplo', 'Bruno Amostra' ) : array( 'Emily Johnson', 'John Smith' ) as $i => $name ) {
	People\add(
		$event,
		array(
			'name'   => $name,
			'email'  => 'person' . ( $i + 1 ) . '@example.test',
			'status' => 'attended',
			'roles'  => array( 'attendee' ),
			'source' => 'manual',
		)
	);
}
foreach ( People\query( $event )['rows'] as $person ) {
	People\issue( $person, (string) array_key_first( $person['roles'] ) );
}
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
	// A correction in the first class: the old certificate says it was replaced.
	if ( 1 === $class ) {
		$one = People\query( $id, array( 'search' => $br ? 'Ana' : 'Ana' ) )['rows'][0];
		Certificates\reissue( People\certificate( $one, 'attendee' ), array( 'name' => $br ? 'Ana Maria Exemplo' : 'Ana Maria Example' ) );
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
