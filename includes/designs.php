<?php
/**
 * Designs: the certificate artwork, one post per design (post type
 * evcert_design) holding the elements as JSON in post_content, so core gives
 * revisions, permissions and edit locking. The editor saves through the REST
 * API (/wp/v2/evcert-designs).
 *
 * Designs are the certificate templates ("Modelos"): each one may apply to
 * some roles (volunteers, speakers…); the default one (the site's design)
 * takes everyone else. The first matching template, in the list's order,
 * draws a certificate (for_certificate(); docs/decisions/0013). The "Base"
 * starting point lives in templates/base-design.json, shared by the editor
 * (bundled at build time) and the PHP renderer.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Designs;

defined( 'ABSPATH' ) || exit;

/**
 * Post type.
 */
const POST_TYPE = 'evcert_design';

/** The ids the editor makes for its elements (`svg-mwzbwe9c-1`): they mean nothing, so they are not kept in the file. */

/**
 * Option holding the site design's post ID.
 */
const SITE_DESIGN_OPTION = 'evcert_site_design';

/**
 * Option holding the post ID of the template the Quick generator draws (the Meetup one until it is changed).
 */
const QUICK_DESIGN_OPTION = 'evcert_quick_design';

/**
 * Meta: the roles a template applies to (empty: none; the default takes everyone left).
 */
const ROLES_META = '_evcert_roles';

/**
 * Hooks.
 */
function bootstrap() {
	add_action( 'init', __NAMESPACE__ . '\register_post_type' );
	add_filter( 'wp_insert_post_data', __NAMESPACE__ . '\keep_json', 10, 3 );
	add_action( 'init', __NAMESPACE__ . '\register_meta' );
}

/**
 * The roles a certificate can have, with their names.
 *
 * @return array<string, string>
 */
function role_labels() {
	return array(
		'attendee'  => __( 'Attendee', 'events-certificates' ),
		'speaker'   => __( 'Speaker', 'events-certificates' ),
		'organizer' => __( 'Organizer', 'events-certificates' ),
		'volunteer' => __( 'Volunteer', 'events-certificates' ),
		'sponsor'   => __( 'Sponsor', 'events-certificates' ),
	);
}

/**
 * Registers the roles meta for the editor (REST).
 */
function register_meta() {
	register_post_meta(
		POST_TYPE,
		ROLES_META,
		array(
			'type'              => 'array',
			'single'            => true,
			'default'           => array(),
			'sanitize_callback' => 'EventsCertificate\Designs\sanitize_roles',
			'auth_callback'     => static function () {
				return current_user_can( \EventsCertificate\Admin\CAPABILITY );
			},
			'show_in_rest'      => array(
				'schema' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
		)
	);
}

/**
 * Keeps known roles only, once each.
 *
 * @param mixed $value Roles.
 * @return string[]
 */
function sanitize_roles( $value ) {
	return array_values( array_intersect( array_keys( role_labels() ), array_map( 'strval', (array) $value ) ) );
}

/**
 * The roles a template applies to.
 *
 * @param int $id Post ID.
 * @return string[]
 */
function roles( $id ) {
	return sanitize_roles( get_post_meta( $id, ROLES_META, true ) );
}

/**
 * The templates, in the list's order (menu_order, then oldest first); the
 * default one included.
 *
 * @return \WP_Post[]
 */
function templates() {
	site_design_id(); // Make the default one exist.

	return get_posts(
		array(
			'post_type'   => POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => array(
				'menu_order' => 'ASC',
				'ID'         => 'ASC',
			),
		)
	);
}

/**
 * The templates in the Trash, newest first.
 *
 * @return \WP_Post[]
 */
function trashed_templates() {
	return get_posts(
		array(
			'post_type'   => POST_TYPE,
			'post_status' => 'trash',
			'numberposts' => -1,
			'orderby'     => 'modified',
			'order'       => 'DESC',
		)
	);
}

/**
 * The template that draws a certificate: the first one, in the list's order,
 * that applies to its role; else the default.
 *
 * @param array<string, mixed> $cert Certificate (role).
 * @return int Post ID.
 */
function for_certificate( array $cert ) {
	$default = site_design_id();
	$role    = (string) ( $cert['role'] ?? '' );
	if ( '' !== $role ) {
		foreach ( templates() as $post ) {
			if ( $post->ID !== $default && in_array( $role, roles( $post->ID ), true ) ) {
				return (int) $post->ID;
			}
		}
	}

	return $default;
}

/**
 * A short version of the templates as they are now (content, conditions,
 * order, default): changes when any certificate may look different.
 *
 * @return string
 */
function fingerprint() {
	$all = array( site_design_id() );
	foreach ( templates() as $post ) {
		$all[] = array( $post->ID, $post->post_modified_gmt, $post->menu_order, roles( $post->ID ) );
	}

	return md5( (string) wp_json_encode( $all ) );
}

/**
 * A blank design: the Base design's page with nothing on it, the two colors every design has (Text and Background) and a theme of
 * only Base and Contrast (stored by slug: their labels are translated when shown), to start from.
 *
 * @return array<string, mixed>
 */
function blank() {
	$design             = basic();
	$design['elements'] = array();
	// Nothing on it: no safe margin to keep.
	$design['page']['safe'] = 0;
	// No colors and no theme: nothing is fixed, so an empty design has none (the + of each group brings the first ones back).
	$design['colors']  = array();
	$design['palette'] = array();
	unset( $design['back'], $design['sample'] );

	return \EventsCertificate\Render\with_palette( $design );
}

/**
 * Makes a template from another one, from the Base design or from a blank one: its content
 * and conditions, named "<name> (copy)", last in the list.
 *
 * @param int    $from  Post ID to copy, 0 for the Base design.
 * @param string $name  Name of the new template ('' for "<name> (copy)" or "New template").
 * @param bool   $blank Start from a blank design (nothing on the page) instead of the Base design.
 * @return int New post ID, or 0.
 */
function duplicate( $from = 0, $name = '', $blank = false ) {
	$source = $from ? get_post( $from ) : null;
	$last   = templates();
	$order  = $last ? (int) end( $last )->menu_order + 1 : 0;
	$id     = wp_insert_post(
		array(
			'post_type'    => POST_TYPE,
			'post_status'  => 'publish',
			/* translators: %s: name of the certificate template copied. */
			'post_title'   => '' !== trim( $name ) ? trim( $name ) : ( $source ? sprintf( __( '%s (copy)', 'events-certificates' ), $source->post_title ) : __( 'New template', 'events-certificates' ) ),
			'post_content' => wp_slash( $source ? $source->post_content : content( $blank ? blank() : basic() ) ),
			'menu_order'   => $order,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	if ( $source ) {
		update_post_meta( $id, ROLES_META, roles( $source->ID ) );
	}

	return (int) $id;
}

/**
 * Makes a template the default one (it then applies to everyone left).
 *
 * @param int $id Post ID.
 * @return bool
 */
function set_default( $id ) {
	if ( POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
		return false;
	}
	update_option( SITE_DESIGN_OPTION, (int) $id, false );
	delete_post_meta( $id, ROLES_META );

	return true;
}

/**
 * Moves a template one place up or down in the list.
 *
 * @param int    $id  Post ID.
 * @param string $dir up|down.
 */
function move( $id, $dir ) {
	$ids = wp_list_pluck( templates(), 'ID' );
	$at  = array_search( (int) $id, array_map( 'intval', $ids ), true );
	$to  = 'up' === $dir ? $at - 1 : $at + 1;
	if ( false === $at || $to < 0 || $to >= count( $ids ) ) {
		return;
	}
	list( $ids[ $at ], $ids[ $to ] ) = array( $ids[ $to ], $ids[ $at ] );
	foreach ( $ids as $i => $post_id ) {
		wp_update_post(
			array(
				'ID'         => (int) $post_id,
				'menu_order' => $i,
			)
		);
	}
}

/**
 * A design's content is JSON read by the editor and the renderer, not HTML:
 * WordPress's kses (which runs for users without unfiltered_html, such as a
 * site administrator on a network) would turn "&" into "&amp;" and drop
 * "<...>" from its texts. The JSON is not run through kses but through clean()
 * (includes/design-clean.php): only what a design may have is kept, whoever
 * saves it. Every text is escaped, and the artwork is kses'd again, when a
 * certificate is drawn.
 *
 * @param array<string, mixed> $data      Sanitized post data.
 * @param array<string, mixed> $postarr   Post data as sent (slashed).
 * @param array<string, mixed> $unsanitized Post data before sanitizing (slashed).
 * @return array<string, mixed>
 */
function keep_json( $data, $postarr, $unsanitized = array() ) {
	if ( POST_TYPE !== ( $data['post_type'] ?? '' ) || ! current_user_can( \EventsCertificate\Admin\CAPABILITY ) || ! isset( $unsanitized['post_content'] ) ) {
		return $data;
	}
	$json = decode( wp_unslash( (string) $unsanitized['post_content'] ) );
	if ( is_array( $json ) && isset( $json['elements'] ) ) {
		// What is saved is what a design may have (clean()), not what was sent, and it is saved as blocks (docs/decisions/0045).
		$clean = clean( $json );
		if ( null !== $clean ) {
			$data['post_content'] = wp_slash( \EventsCertificate\Vendor\Camaleaun\Draw\Design::to_markup( to_file( $clean ), block_options() ) );
		}
	}

	return $data;
}

/**
 * The keys of the fields a text may have (`[name]`, `[issued_date:long]`): templates/fields.json, which the editor reads too.
 *
 * @return array<int, string>
 */
function field_keys() {
	static $keys = null;
	if ( null === $keys ) {
		$json    = file_get_contents( dirname( __DIR__ ) . '/templates/fields.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file of the plugin.
		$decoded = json_decode( (string) $json, true );
		$keys    = is_array( $decoded ) ? array_values( array_map( 'strval', $decoded ) ) : array();
	}

	return $keys;
}

/**
 * How a design is kept as blocks (docs/decisions/0047): a block of the plugin for every shape (`events-certificates/certificate-text`,
 * `-rect`…), and an SVG is one block with its paths in the `paths` attribute. The same in src/editor/dialect.ts.
 *
 * @return array<string, mixed>
 */
function block_options() {
	$blocks = array();
	foreach ( array( 'text', 'rect', 'ellipse', 'path', 'line', 'image', 'svg', 'group', 'signature' ) as $type ) {
		$blocks[ $type ] = 'events-certificates/certificate-' . $type;
	}

	return array(
		// The keys that are fields in a text (`[name]`); any other [word] is text (the same list as the editor's: templates/fields.json).
		'fields'   => field_keys(),
		'blocks'   => $blocks,
		'fallback' => 'events-certificates/certificate-element',
		'paths'    => 'attribute',
		// The names of the types of the certificate that the package does not know: not written, as the type says them.
		'names'    => array(
			'signature'  => 'Signature',
			'image:logo' => 'Community logo',
		),
	);
}

/**
 * A reference to a color of the design as the file says it, the way a block says a preset: "$background" is
 * "var:preset|color|background" (and the other way). Any value of the design, however deep.
 *
 * @param mixed $value Design or a part of it.
 * @param bool  $file  True to write the file's form, false to read it.
 * @return mixed
 */
function color_refs( $value, $file ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			$value[ $key ] = color_refs( $item, $file );
		}

		return $value;
	}
	if ( ! is_string( $value ) ) {
		return $value;
	}
	if ( $file ) {
		return 1 === preg_match( '/^\$([A-Za-z][A-Za-z0-9-]*)$/', $value, $m ) ? 'var:preset|color|' . $m[1] : $value;
	}

	return 1 === preg_match( '/^var:preset\|color\|(?:element\|)?([A-Za-z][A-Za-z0-9-]*)$/', $value, $m ) ? '$' . $m[1] : $value;
}

/**
 * The colors of a design as the file says them, like a theme.json: `color.palette` the Theme (slug, color, name) and `color.elements` the colors
 * of the Elements (slug, color, name; a color with a Dark value is light-dark(Light, Dark)). The model keeps `palette` and a `colors` map.
 * `palette` is written only when the design has one (none is a list with nothing in it).
 *
 * @param array<string, mixed> $design Design in the model's form.
 * @return array<string, mixed>
 */
function colors_to_file( array $design ) {
	$elements = array();
	foreach ( (array) ( $design['colors'] ?? array() ) as $slug => $color ) {
		if ( ! is_array( $color ) ) {
			continue;
		}
		// A color with a Dark value is written as CSS says it, light-dark(Light, Dark); either may point at a color of the Theme (var:preset|color|slug).
		$light = (string) ( $color['light'] ?? '' );
		$dark  = (string) ( $color['dark'] ?? '' );
		$entry = array(
			'slug'  => (string) $slug,
			'color' => '' !== $dark ? 'light-dark(' . $light . ',' . $dark . ')' : $light,
		);
		if ( '' !== (string) ( $color['name'] ?? '' ) ) {
			$entry['name'] = (string) $color['name'];
		}
		$elements[] = $entry;
	}
	$file = array();
	if ( isset( $design['palette'] ) && is_array( $design['palette'] ) ) {
		$palette = array();
		foreach ( array_values( $design['palette'] ) as $swatch ) {
			if ( is_array( $swatch ) ) {
				$palette[] = array_filter(
					array(
						'slug'  => $swatch['slug'] ?? '',
						'color' => $swatch['color'] ?? '',
						'name'  => $swatch['name'] ?? '',
					),
					static function ( $value ) {
						return '' !== $value;
					}
				);
			}
		}
		$file['palette'] = $palette;
	}
	$file['elements'] = $elements;
	$out              = array();
	foreach ( $design as $key => $value ) {
		if ( 'colors' === $key || 'palette' === $key ) {
			continue;
		}
		$out[ $key ] = $value;
		if ( 'page' === $key ) {
			$out['color'] = $file;
		}
	}
	if ( ! isset( $out['color'] ) ) {
		$out['color'] = $file;
	}

	return $out;
}

/**
 * `light-dark(Light, Dark)` as its two values (a pointer to a color of the Theme, var:preset|color|slug, is read back as "$slug"); any other value is
 * the Light one. The same as lightDark() in src/editor/dialect.ts.
 *
 * @param string $value What the file says.
 * @return array<int, string> Light, Dark.
 */
function light_dark( $value ) {
	$value = trim( $value );
	if ( 1 !== preg_match( '/^light-dark\((.*)\)$/s', $value, $m ) ) {
		return array( color_refs( $value, false ), '' );
	}
	$parts = array();
	$depth = 0;
	$start = 0;
	$text  = $m[1];
	$count = strlen( $text );
	for ( $i = 0; $i < $count; $i++ ) {
		$char = $text[ $i ];
		if ( '(' === $char ) {
			++$depth;
		} elseif ( ')' === $char ) {
			--$depth;
		} elseif ( ',' === $char && 0 === $depth ) {
			$parts[] = substr( $text, $start, $i - $start );
			$start   = $i + 1;
		}
	}
	$parts[] = substr( $text, $start );

	return array( color_refs( trim( $parts[0] ), false ), color_refs( trim( $parts[1] ?? '' ), false ) );
}

/**
 * The reverse of colors_to_file(): `colors` (a map with Light and Dark values) and `palette` from `color.elements` and `color.palette`.
 *
 * @param array<string, mixed> $design Design as the file says it.
 * @return array<string, mixed>
 */
function colors_from_file( array $design ) {
	if ( ! isset( $design['color'] ) || ! is_array( $design['color'] ) ) {
		return $design;
	}
	$file   = $design['color'];
	$colors = array();
	foreach ( (array) ( $file['elements'] ?? array() ) as $entry ) {
		if ( ! is_array( $entry ) || ! isset( $entry['slug'] ) || ! is_string( $entry['slug'] ) ) {
			continue;
		}
		list( $light, $dark ) = light_dark( (string) ( $entry['color'] ?? '' ) );
		$color                = array(
			'name'  => (string) ( $entry['name'] ?? '' ),
			'light' => $light,
		);
		if ( '' !== $dark ) {
			$color['dark'] = $dark;
		}
		$colors[ $entry['slug'] ] = $color;
	}
	$out = array();
	foreach ( $design as $key => $value ) {
		if ( 'color' === $key ) {
			$out['colors'] = $colors;
			if ( isset( $file['palette'] ) && is_array( $file['palette'] ) ) {
				$out['palette'] = array_values( $file['palette'] );
			}
			continue;
		}
		$out[ $key ] = $value;
	}

	return $out;
}

/**
 * A design as a file says it: the colors like a theme.json (colors_to_file()) and a reference to a color as a preset (color_refs()). The elements are
 * written by the package (`Names`, in `Design::to_markup()`): their names in full, no id that only counts, no default name.
 *
 * @param array<string, mixed> $design Design in the model's form.
 * @return array<string, mixed>
 */
function to_file( array $design ) {
	return colors_to_file( color_refs( $design, true ) );
}

/**
 * The reverse of to_file(): the model's colors and palette, and the references to a color as "$slug".
 *
 * @param array<string, mixed> $design Design as the file says it (read by the package).
 * @return array<string, mixed>
 */
function from_file( array $design ) {
	return colors_from_file( color_refs( $design, false ) );
}

/**
 * A design from what a template post holds: block markup (docs/decisions/0045) or the JSON an earlier version saved.
 *
 * @param string $content The post's content.
 * @return array<string, mixed>|null
 */
function decode( $content ) {
	if ( \EventsCertificate\Vendor\Camaleaun\Draw\Design::is_markup( $content ) ) {
		$design = \EventsCertificate\Vendor\Camaleaun\Draw\Design::from_markup( $content, block_options() );

		return is_array( $design ) ? from_file( $design ) : null;
	}
	$json = json_decode( (string) $content, true );

	return is_array( $json ) ? $json : null;
}

/**
 * A design as what a template post holds: cleaned, and written as block markup.
 *
 * @param array<string, mixed> $design Design.
 * @return string
 */
function content( array $design ) {
	$clean = clean( $design );

	return \EventsCertificate\Vendor\Camaleaun\Draw\Design::to_markup( to_file( null === $clean ? $design : $clean ), block_options() );
}

/**
 * Registers the post type. Not public: designs are only reached through the
 * editor and drawn on certificates.
 */
function register_post_type() {
	\register_post_type(
		POST_TYPE,
		array(
			'labels'          => array(
				'name'          => __( 'Certificate templates', 'events-certificates' ),
				'singular_name' => __( 'Certificate template', 'events-certificates' ),
			),
			'public'          => false,
			'show_ui'         => false,
			'show_in_rest'    => true,
			'rest_base'       => 'evcert-designs',
			'supports'        => array( 'title', 'editor', 'revisions' ),
			'capability_type' => 'evcert_design',
			'map_meta_cap'    => false,
			'capabilities'    => array(
				'edit_post'          => \EventsCertificate\Admin\CAPABILITY,
				'read_post'          => \EventsCertificate\Admin\CAPABILITY,
				'delete_post'        => \EventsCertificate\Admin\CAPABILITY,
				'edit_posts'         => \EventsCertificate\Admin\CAPABILITY,
				'edit_others_posts'  => \EventsCertificate\Admin\CAPABILITY,
				'publish_posts'      => \EventsCertificate\Admin\CAPABILITY,
				'read_private_posts' => \EventsCertificate\Admin\CAPABILITY,
				'delete_posts'       => \EventsCertificate\Admin\CAPABILITY,
				'create_posts'       => \EventsCertificate\Admin\CAPABILITY,
			),
		)
	);
}

/**
 * The Base design: the layout with a band that Meetup is made from. (The starting point of a new design is the Basic one, basic().)
 *
 * @return array<string, mixed>
 */
function base() {
	return shipped( 'base' );
}

/**
 * The Basic design, the starting point of every new design: a plain certificate on a background drawing, like the generators of the
 * Brazilian community had (the logo, a title, the name, what the person did, the event, the signature). The template a site starts with,
 * what "New template" copies and "Restore the Basic design" goes back to. (Meetup is made from the Base design, the one with a band.)
 *
 * @return array<string, mixed>
 */
function basic() {
	// The background (circles, frames and the band of the name) is one drawing of filled shapes in the Lines color, made by bin/make-basic-background.py.
	$design = shipped( 'basic' );

	return $design;
}

/**
 * A design that ships with the plugin, offered to the Base design filter.
 *
 * @param string $name 'base' or 'basic' (templates/<name>-design.json).
 * @return array<string, mixed>
 */
function shipped( $name ) {
	$json   = file_get_contents( dirname( __DIR__ ) . '/templates/' . $name . '-design.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file of the plugin.
	$design = json_decode( (string) $json, true );
	$design = is_array( $design ) ? $design : array();

	/**
	 * Filters the Base design: the certificate a site starts from. It is the design of a
	 * site that has none yet, what "New template" copies and "Restore the Basic design" goes
	 * back to. A network (a WordCamp.org or WordPress.org mu-plugin) or a Playground blueprint
	 * can give another. A design with a page and elements; anything else is ignored.
	 *
	 * @param array<string, mixed> $design The design (templates/base-design.json, or basic-design.json for the Basic one).
	 * @param string               $name   'base' or 'basic'.
	 */
	$filtered = apply_filters( 'evcert_base_design', $design, $name );

	return \EventsCertificate\Render\with_palette( is_array( $filtered ) && isset( $filtered['page'], $filtered['elements'] ) && is_array( $filtered['elements'] ) ? $filtered : $design );
}

/**
 * The Meetup template's design: the Base design with "Meetup WordPress" on its band. Only the band says it: the sentence under the
 * name stays the role's own ("took part in", "gave a talk at"…).
 *
 * @return array<string, mixed>
 */
function meetup() {
	$design = base();
	foreach ( $design['elements'] as $i => $element ) {
		if ( is_array( $element ) && 'band-text' === ( $element['id'] ?? '' ) ) {
			$design['elements'][ $i ]['runs'] = array(
				array( 'text' => __( 'Meetup WordPress', 'events-certificates' ) ),
			);
		}
	}

	return $design;
}

/**
 * Makes the two templates a site starts with, in the site's language: Basic (the default one: events, issuing, everything) and,
 * unless the Quick generator already has a template of its own, Meetup (the Quick generator's).
 *
 * @return int The default template's post ID, or 0 when it couldn't be created.
 */
function seed() {
	$switched = function_exists( 'switch_to_locale' ) ? switch_to_locale( get_locale() ) : false;
	$default  = wp_insert_post(
		array(
			'post_type'    => POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => __( 'Basic', 'events-certificates' ),
			'post_content' => wp_slash( content( basic() ) ),
			'menu_order'   => 0,
		),
		true
	);
	if ( ! is_wp_error( $default ) ) {
		update_option( SITE_DESIGN_OPTION, (int) $default, false );
		if ( false === get_option( QUICK_DESIGN_OPTION ) ) {
			$quick = wp_insert_post(
				array(
					'post_type'    => POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => __( 'Meetup WordPress', 'events-certificates' ),
					'post_content' => wp_slash( content( meetup() ) ),
					'menu_order'   => 1,
				),
				true
			);
			if ( ! is_wp_error( $quick ) ) {
				update_option( QUICK_DESIGN_OPTION, (int) $quick, false );
			}
		}
	}
	if ( $switched ) {
		restore_previous_locale();
	}

	return is_wp_error( $default ) ? 0 : (int) $default;
}

/**
 * The ID of a published template with a title, in the site's language or in English.
 *
 * @param string $english The English name ('Basic', 'Meetup'); the Meetup template is titled "Meetup WordPress", and the "Meetup" of the first sites is found too.
 * @return int Post ID, or 0.
 */
function starter_id( $english ) {
	$switched = function_exists( 'switch_to_locale' ) ? switch_to_locale( get_locale() ) : false;
	$titles   = array_unique( 'Basic' === $english ? array( $english, __( 'Basic', 'events-certificates' ) ) : array( $english, 'Meetup WordPress', __( 'Meetup WordPress', 'events-certificates' ) ) );
	if ( $switched ) {
		restore_previous_locale();
	}
	foreach ( templates() as $post ) {
		if ( in_array( $post->post_title, $titles, true ) ) {
			return (int) $post->ID;
		}
	}

	return 0;
}

/**
 * Makes the starter templates a site lacks, Basic and Meetup, in the site's language, without touching the ones it has (an edited
 * Basic stays as it is) and without changing which template is the default or the Quick generator's, unless asked.
 *
 * @param bool $make_default Whether Basic becomes the default template and Meetup the Quick generator's.
 * @return array{created: string[], present: string[], assigned: bool} The English names made, the ones that were there, and whether
 *         the default and the Quick generator's were set.
 */
function restore_starters( $make_default = false ) {
	$result   = array(
		'created'  => array(),
		'present'  => array(),
		'assigned' => false,
	);
	$switched = function_exists( 'switch_to_locale' ) ? switch_to_locale( get_locale() ) : false;
	$ids      = array();
	foreach ( array(
		'Basic'  => array( __( 'Basic', 'events-certificates' ), 'basic', 0 ),
		'Meetup' => array( __( 'Meetup WordPress', 'events-certificates' ), 'meetup', 1 ),
	) as $english => $starter ) {
		$ids[ $english ] = starter_id( $english );
		if ( $ids[ $english ] ) {
			$result['present'][] = $english;
			continue;
		}
		$made = wp_insert_post(
			array(
				'post_type'    => POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $starter[0],
				'post_content' => wp_slash( content( 'basic' === $starter[1] ? basic() : meetup() ) ),
				'menu_order'   => $starter[2],
			),
			true
		);
		if ( ! is_wp_error( $made ) ) {
			$ids[ $english ]     = (int) $made;
			$result['created'][] = $english;
		}
	}
	if ( $switched ) {
		restore_previous_locale();
	}
	if ( $make_default && $ids['Basic'] && $ids['Meetup'] ) {
		$result['assigned'] = set_default( $ids['Basic'] ) && set_quick( $ids['Meetup'] );
	}

	return $result;
}

/**
 * The site's design post ID, created (with the Meetup one) the first time.
 *
 * @return int Post ID, or 0 when it couldn't be created.
 */
function site_design_id() {
	$id = (int) get_option( SITE_DESIGN_OPTION );
	if ( $id && POST_TYPE === get_post_type( $id ) ) {
		return $id;
	}

	return seed();
}

/**
 * The template the Quick generator draws: the one the site chose (Meetup at first), else the Meetup starter when none was chosen or it
 * is gone (its band says "Meetup WordPress" along its side, as the generator it comes from does), else the default one.
 *
 * @return int Post ID, or 0.
 */
function quick_design_id() {
	$default = site_design_id();
	$id      = (int) get_option( QUICK_DESIGN_OPTION );

	/**
	 * Filters the template the Quick generator draws.
	 *
	 * @param int $id Template post ID.
	 */
	$id = (int) apply_filters( 'evcert_quick_design_id', $id );

	if ( $id && POST_TYPE === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
		return $id;
	}
	$meetup = starter_id( 'Meetup' );

	return $meetup ? $meetup : $default;
}

/**
 * Makes a template the Quick generator's one (its roles stay: they are for certificates, which the Quick generator doesn't issue).
 *
 * @param int $id Post ID.
 * @return bool
 */
function set_quick( $id ) {
	if ( POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
		return false;
	}
	update_option( QUICK_DESIGN_OPTION, (int) $id, false );

	return true;
}

/**
 * A design's elements, or the Base design when the post has none.
 *
 * @param int $id Post ID.
 * @return array<string, mixed>
 */
function get( $id ) {
	$post   = get_post( $id );
	$design = $post ? decode( $post->post_content ) : null;

	return is_array( $design ) && isset( $design['elements'] ) ? \EventsCertificate\Render\with_palette( $design ) : basic();
}
