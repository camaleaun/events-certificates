<?php
/**
 * Fonts: the two small ones the plugin ships (IBM Plex Sans and Instrument Serif, as subsets, for the Quick generator's Meetup WordPress
 * template) and the ones installed in WordPress itself, in the Font Library (Site Editor > Styles > Typography > Fonts). The plugin ships no
 * other font (docs/decisions/0042).
 *
 * A design names a font family; when that family is neither shipped nor installed (a template that asks for Inter on a site without it),
 * the page and the PDF use a shipped one (resolve()), so a template never breaks.
 *
 * The PDF engine reads TrueType, OpenType and WOFF files, not WOFF2, which is what the Font Library installs from Google Fonts: such a
 * family shows in the editor and on the page, and the PDF uses its shipped fallback.
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Fonts;

defined( 'ABSPATH' ) || exit;

/**
 * The faces the plugin ships: family, weight and the file in fonts/ (without .ttf).
 */
const SHIPPED = array(
	array( 'CertSans', 400, 'CertSans-Regular' ),
	array( 'CertSans', 600, 'CertSans-Bold' ),
	array( 'CertSerif', 400, 'CertSerif-Regular' ),
);

/**
 * The names the shipped families go by in the editor.
 */
const LABELS = array(
	'CertSans'  => 'IBM Plex Sans',
	'CertSerif' => 'Instrument Serif',
);

/**
 * Cache of the installed faces.
 */
const TRANSIENT = 'evcert_fonts_2';

/**
 * Hooks: the installed faces are remembered until a font is installed, changed or deleted.
 */
function bootstrap() {
	add_action( 'save_post', __NAMESPACE__ . '\forget' );
	add_action( 'deleted_post', __NAMESPACE__ . '\forget' );
}

/**
 * Forgets the installed faces when a font family or face is saved or deleted.
 *
 * @param int $post_id Post ID.
 */
function forget( $post_id ) {
	if ( in_array( get_post_type( $post_id ), array( 'wp_font_family', 'wp_font_face' ), true ) ) {
		flush();
	}
}

/**
 * Forgets the installed faces: the remembered list and the one read in this request.
 */
function flush() {
	delete_transient( TRANSIENT );
	$memory = &memory();
	$memory = null;
}

/**
 * The installed faces already read in this request (a reference, so saving a font can forget them).
 *
 * @return array<int, array<string, mixed>>|null
 */
function &memory() {
	static $memory = null;

	return $memory;
}

/**
 * A family name as a design and the CSS write it: letters, digits, spaces and hyphens.
 *
 * @param string $name Name.
 * @return string
 */
function clean_name( $name ) {
	return trim( (string) preg_replace( '/[^A-Za-z0-9 -]/', '', (string) $name ) );
}

/**
 * The faces the plugin ships.
 *
 * @return array<int, array<string, mixed>>
 */
function shipped() {
	$dir = dirname( __DIR__ ) . '/fonts/';
	$url = plugins_url( 'fonts/', dirname( __DIR__ ) . '/events-certificates.php' );
	$out = array();
	foreach ( SHIPPED as $face ) {
		$out[] = array(
			'family'  => $face[0],
			'label'   => LABELS[ $face[0] ] ?? $face[0],
			'weight'  => $face[1],
			'url'     => $url . $face[2] . '.ttf',
			'file'    => $dir . $face[2] . '.ttf',
			'format'  => 'truetype',
			'pdf'     => true,
			'ttf'     => true,
			'shipped' => true,
			'generic' => 'CertSerif' === $face[0] ? 'serif' : 'sans-serif',
		);
	}

	return $out;
}

/**
 * The file in the fonts folder of WordPress a font's address points to, when it is there.
 *
 * @param string $url Address of the font file.
 * @return string Path, or '' when it is not a readable file of that folder.
 */
function file_of( $url ) {
	$dir  = wp_get_font_dir();
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$base = rtrim( (string) wp_parse_url( $dir['baseurl'], PHP_URL_PATH ), '/' );
	if ( '' === $base || 0 !== strpos( $path, $base . '/' ) ) {
		return '';
	}
	$file = realpath( rtrim( $dir['basedir'], '/' ) . '/' . rawurldecode( substr( $path, strlen( $base ) + 1 ) ) );
	$root = realpath( $dir['basedir'] );

	return $file && $root && 0 === strpos( $file, $root . DIRECTORY_SEPARATOR ) && is_readable( $file ) ? $file : '';
}

/**
 * The weights a face of the Font Library stands for: "400", "700", "normal", "bold", or a range of a variable font ("100 900"), for
 * which they are the weights a design uses (400, 500, 600, 700) inside the range.
 *
 * @param string $weight Weight as the Font Library keeps it.
 * @return int[]
 */
function weights_of( $weight ) {
	$weight = strtolower( trim( (string) $weight ) );
	if ( 'normal' === $weight || '' === $weight ) {
		return array( 400 );
	}
	if ( 'bold' === $weight ) {
		return array( 700 );
	}
	if ( preg_match( '/^(\d+)\s+(\d+)$/', $weight, $range ) ) {
		return array_values(
			array_filter(
				array( 400, 500, 600, 700 ),
				static fn ( $w ) => $w >= (int) $range[1] && $w <= (int) $range[2]
			)
		);
	}

	return ctype_digit( $weight ) ? array( (int) $weight ) : array( 400 );
}

/**
 * The faces installed in WordPress (the Font Library), upright ones only.
 *
 * @return array<int, array<string, mixed>>
 */
function installed() {
	$memory = &memory();
	if ( null !== $memory ) {
		return $memory;
	}
	$kept = get_transient( TRANSIENT );
	if ( is_array( $kept ) ) {
		$memory = $kept;
		return $memory;
	}

	$out = array();
	if ( post_type_exists( 'wp_font_family' ) ) {
		$families = get_posts(
			array(
				'post_type'   => 'wp_font_family',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		foreach ( $families as $family ) {
			$settings = json_decode( (string) $family->post_content, true );
			$settings = is_array( $settings ) ? $settings : array();
			$stack    = (string) ( $settings['fontFamily'] ?? $family->post_title );
			$name     = clean_name( trim( explode( ',', $stack )[0], " \t\"'" ) );
			if ( '' === $name || isset( LABELS[ $name ] ) ) {
				continue;
			}
			// The Font Library keeps a generic family ("Lora, serif") when it installed the font from a collection, and not when a file was
			// uploaded: then Lora, the serif the first templates asked for, is a serif and the others are sans.
			$has_generic = false !== stripos( $stack, 'serif' ) || false !== stripos( $stack, 'sans-serif' ) || false !== stripos( $stack, 'monospace' );
			if ( $has_generic ) {
				$generic = false !== stripos( $stack, 'serif' ) && false === stripos( $stack, 'sans-serif' ) ? 'serif' : 'sans-serif';
			} else {
				$generic = 'Lora' === $name ? 'serif' : 'sans-serif';
			}
			$faces = get_posts(
				array(
					'post_type'   => 'wp_font_face',
					'post_status' => 'publish',
					'post_parent' => $family->ID,
					'numberposts' => -1,
					'orderby'     => 'ID',
					'order'       => 'ASC',
				)
			);
			foreach ( $faces as $face ) {
				$data = json_decode( (string) $face->post_content, true );
				$data = is_array( $data ) ? $data : array();
				if ( 'normal' !== strtolower( (string) ( $data['fontStyle'] ?? 'normal' ) ) ) {
					continue;
				}
				$src = $data['src'] ?? '';
				$src = is_array( $src ) ? (string) reset( $src ) : (string) $src;
				if ( '' === $src ) {
					continue;
				}
				$ext     = strtolower( pathinfo( (string) wp_parse_url( $src, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
				$formats = array(
					'ttf'   => 'truetype',
					'otf'   => 'opentype',
					'woff'  => 'woff',
					'woff2' => 'woff2',
				);
				if ( ! isset( $formats[ $ext ] ) ) {
					continue;
				}
				$file = file_of( $src );
				foreach ( weights_of( (string) ( $data['fontWeight'] ?? '400' ) ) as $weight ) {
					$out[] = array(
						'family'  => $name,
						'label'   => (string) $family->post_title,
						'weight'  => $weight,
						'raw'     => (string) ( $data['fontWeight'] ?? '400' ),
						'url'     => $src,
						'file'    => $file,
						'format'  => $formats[ $ext ],
						'pdf'     => '' !== $file && in_array( $ext, array( 'ttf', 'otf', 'woff' ), true ),
						'ttf'     => '' !== $file && 'ttf' === $ext,
						'shipped' => false,
						'generic' => $generic,
					);
				}
			}
		}
	}

	set_transient( TRANSIENT, $out, DAY_IN_SECONDS );
	$memory = $out;

	return $memory;
}

/**
 * Every face a certificate can use: the plugin's and the installed ones.
 *
 * @return array<int, array<string, mixed>>
 */
function faces() {
	return array_merge( shipped(), installed() );
}

/**
 * The families the editor offers (the font picker's list): the plugin's two, then the installed ones, each with whether the PDF can use it and what it uses when
 * it cannot.
 *
 * @return array<int, array{value: string, label: string, pdf: bool, fallback: string, weights: int[]}>
 */
function choices() {
	$out = array();
	foreach ( faces() as $face ) {
		$family = $face['family'];
		if ( ! isset( $out[ $family ] ) ) {
			$out[ $family ] = array(
				'value'    => $family,
				'label'    => (string) $face['label'],
				'pdf'      => false,
				'fallback' => 'serif' === $face['generic'] ? 'CertSerif' : 'CertSans',
				'weights'  => array(),
			);
		}
		$out[ $family ]['pdf']       = $out[ $family ]['pdf'] || ! empty( $face['pdf'] );
		$out[ $family ]['weights'][] = (int) $face['weight'];
	}

	return array_values( $out );
}

/**
 * The shipped family that stands in for a family that is not there: the one the first templates asked for by name (Lora is a serif),
 * else the family's own kind, else the sans.
 *
 * @param string $family Family name.
 * @return string CertSans or CertSerif.
 */
function fallback( $family ) {
	$family = clean_name( $family );
	foreach ( faces() as $face ) {
		if ( $face['family'] === $family ) {
			return 'serif' === $face['generic'] ? 'CertSerif' : 'CertSans';
		}
	}

	return 'Lora' === $family ? 'CertSerif' : 'CertSans';
}

/**
 * The family to use for the one a design names: itself when it is shipped or installed, else its fallback.
 *
 * @param string $family Family name.
 * @return string
 */
function resolve( $family ) {
	$family = clean_name( $family );
	foreach ( faces() as $face ) {
		if ( $face['family'] === $family ) {
			return $family;
		}
	}

	return fallback( $family );
}

/**
 * The family the PDF draws a design's font with: the family when the PDF can read it, else the shipped one that stands in for it.
 *
 * @param string $family Family the design names.
 * @return string
 */
function pdf_family( $family ) {
	$family = clean_name( $family );
	$family = '' === $family ? 'CertSans' : $family;

	return usable_in_pdf( $family ) ? $family : fallback( $family );
}

/**
 * Whether the PDF can use a family: a shipped one, or an installed one with a TrueType, OpenType or WOFF face.
 *
 * @param string $family Family name.
 * @return bool
 */
function usable_in_pdf( $family ) {
	$family = clean_name( $family );
	foreach ( faces() as $face ) {
		if ( $face['family'] === $family && ! empty( $face['pdf'] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * The weight a text is drawn with: the one asked for when the family has it, else the one CSS would pick among the ones it has, so a
 * stand-in font with fewer weights (the serif has only the regular) is not asked for one it lacks, which the PDF engine would answer with
 * another font.
 *
 * @param string $family  Family the design names.
 * @param int    $weight  Weight asked for.
 * @param bool   $for_pdf Whether it is for the PDF.
 * @return int
 */
function weight_for( $family, $weight, $for_pdf = false ) {
	$family = clean_name( $family );
	$family = '' === $family ? 'CertSans' : $family;
	$use    = $for_pdf ? pdf_family( $family ) : resolve( $family );
	$have   = array();
	foreach ( faces() as $face ) {
		if ( $face['family'] === $use && ( ! $for_pdf || ! empty( $face['pdf'] ) ) ) {
			$have[] = (int) $face['weight'];
		}
	}
	if ( ! $have || in_array( (int) $weight, $have, true ) ) {
		return (int) $weight;
	}
	// CSS's own order: 400 to 500 look up to 500 first, then lighter, then heavier; lighter than 400 look lighter first; heavier than 500
	// look heavier first. So 500 between a 400 and a 600 is the 400.
	sort( $have );
	$lighter = array_reverse( array_values( array_filter( $have, static fn ( $w ) => $w < $weight ) ) );
	$heavier = array_values( array_filter( $have, static fn ( $w ) => $w > $weight ) );
	if ( $weight >= 400 && $weight <= 500 ) {
		$between = array_values( array_filter( $heavier, static fn ( $w ) => $w <= 500 ) );
		$beyond  = array_values( array_filter( $heavier, static fn ( $w ) => $w > 500 ) );
		$order   = array_merge( $between, $lighter, $beyond );
	} elseif ( $weight < 400 ) {
		$order = array_merge( $lighter, $heavier );
	} else {
		$order = array_merge( $heavier, $lighter );
	}

	return (int) $order[0];
}

/**
 * The CSS font-family of a design's text: the family it names and, behind it, the shipped one that stands in for it, so a font that is
 * missing (or that the PDF cannot read) still draws.
 *
 * @param string $family  Family the design names (empty is the default).
 * @param bool   $for_pdf Whether it is for the PDF.
 * @return string E.g. 'Inter','CertSans'.
 */
function stack( $family, $for_pdf = false ) {
	$family = clean_name( $family );
	$family = '' === $family ? 'CertSans' : $family;
	$stand  = fallback( $family );
	if ( $for_pdf && ! usable_in_pdf( $family ) ) {
		return "'" . $stand . "'";
	}

	return $family === $stand ? "'" . $family . "'" : "'" . $family . "','" . $stand . "'";
}

/**
 * The @font-face rules of the faces.
 *
 * @param bool $for_pdf Files (the PDF engine; the faces it can read) or addresses (the browser; all of them).
 * @param bool $block   Whether the rules wait for the file (font-display: block), as the editor's canvas does.
 * @return string CSS.
 */
function css( $for_pdf, $block = false ) {
	$css = '';
	foreach ( faces() as $face ) {
		if ( $for_pdf && empty( $face['pdf'] ) ) {
			continue;
		}
		$css .= sprintf(
			"@font-face{font-family:'%s';font-weight:%d;font-style:normal;%ssrc:url('%s') format('%s');}",
			$face['family'],
			$face['weight'],
			$block ? 'font-display:block;' : '',
			$for_pdf ? $face['file'] : esc_url_raw( $face['url'] ),
			$face['format']
		);
	}

	return $css;
}

/**
 * The faces a design uses (and the shipped family that stands in for each), for the browser to load: the share image embeds exactly these.
 *
 * @param array<string, mixed> $design Design.
 * @return array<int, array{family: string, weight: int, url: string, format: string}>
 */
function used_by( array $design ) {
	$wanted = array( 'CertSans' => true );
	foreach ( (array) ( $design['elements'] ?? array() ) as $element ) {
		if ( is_array( $element ) && ! empty( $element['font'] ) ) {
			$family                        = clean_name( (string) $element['font'] );
			$wanted[ $family ]             = true;
			$wanted[ fallback( $family ) ] = true;
		}
	}
	$out = array();
	foreach ( faces() as $face ) {
		if ( isset( $wanted[ $face['family'] ] ) ) {
			$out[] = array(
				'family' => $face['family'],
				'weight' => (int) $face['weight'],
				'url'    => $face['url'],
				'format' => $face['format'],
			);
		}
	}

	return $out;
}

/**
 * The Font Library's family post for a family name, or 0.
 *
 * @param string $name Family name.
 * @return int Post ID.
 */
function post_of_family( $name ) {
	$slug = sanitize_title( $name );
	foreach ( get_posts(
		array(
			'post_type'   => 'wp_font_family',
			'post_status' => 'publish',
			'numberposts' => -1,
		)
	) as $post ) {
		$settings = json_decode( (string) $post->post_content, true );
		$stack    = is_array( $settings ) ? (string) ( $settings['fontFamily'] ?? '' ) : '';
		if ( $post->post_name === $slug || clean_name( trim( explode( ',', $stack )[0], " \t\"'" ) ) === $name ) {
			return (int) $post->ID;
		}
	}

	return 0;
}

/**
 * Installs a font face in the Font Library of WordPress, as its Upload tab does: the family (made when it is not there), the file in the
 * fonts folder and the face. A face the family already has (same weight) is left as it is, and a file is never written over another.
 *
 * Whoever calls it checks that the user may (the Font Library asks for `edit_theme_options`) and that the file is a font.
 *
 * @param string $family  Family name.
 * @param string $label   Name to show (the family's, by default).
 * @param string $generic serif or sans-serif.
 * @param string $weight  Weight as the Font Library keeps it ("400", "700", "100 900").
 * @param string $file    Path of the font file (it is copied).
 * @return string installed, present (the family has that weight) or failed.
 */
function install_face( $family, $label, $generic, $weight, $file ) {
	$name   = clean_name( $family );
	$weight = preg_match( '/^(normal|bold|\d{3}( \d{3})?)$/', $weight ) ? $weight : '400';
	$ext    = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	if ( '' === $name || ! is_readable( $file ) || ! in_array( $ext, array( 'ttf', 'otf', 'woff', 'woff2' ), true ) || ! is_well_formed( $file, $ext ) || ! post_type_exists( 'wp_font_family' ) ) {
		return 'failed';
	}
	$wanted = weights_of( $weight );
	foreach ( installed() as $face ) {
		if ( $face['family'] === $name && array_intersect( $wanted, array( (int) $face['weight'] ) ) ) {
			return 'present';
		}
	}
	$generic = 'serif' === $generic ? 'serif' : 'sans-serif';
	$family  = post_of_family( $name );
	if ( ! $family ) {
		$family = (int) wp_insert_post(
			array(
				'post_type'    => 'wp_font_family',
				'post_status'  => 'publish',
				'post_title'   => '' !== trim( $label ) ? trim( $label ) : $name,
				'post_name'    => sanitize_title( $name ),
				'post_content' => wp_json_encode(
					array(
						'name'       => '' !== trim( $label ) ? trim( $label ) : $name,
						'slug'       => sanitize_title( $name ),
						'fontFamily' => $name . ', ' . $generic,
					)
				),
			)
		);
	}
	if ( ! $family ) {
		return 'failed';
	}
	$dir = wp_get_font_dir();
	wp_mkdir_p( $dir['path'] );
	$target = $dir['path'] . '/' . wp_unique_filename( $dir['path'], sanitize_file_name( $name . '-' . str_replace( ' ', '-', $weight ) . '.' . $ext ) );
	if ( ! copy( $file, $target ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- a font file into the fonts folder, as the Font Library does.
		return 'failed';
	}
	chmod( $target, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.chmod_chmod, WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- readable by the web server.
	$face = wp_insert_post(
		array(
			'post_type'    => 'wp_font_face',
			'post_status'  => 'publish',
			'post_parent'  => $family,
			'post_title'   => $name . ' ' . $weight,
			'post_content' => wp_json_encode(
				array(
					'fontFamily' => $name,
					'fontStyle'  => 'normal',
					'fontWeight' => $weight,
					'src'        => $dir['url'] . '/' . basename( $target ),
				)
			),
		)
	);
	if ( ! $face ) {
		wp_delete_file( $target );
		return 'failed';
	}
	flush();

	return 'installed';
}

/**
 * Whether a file is a font in the way it is built, not only in its first bytes: the table directory of a TrueType or OpenType font
 * (at most 64 tables, each inside the file after the directory), the sizes a WOFF says and the header of a WOFF2. A file that claims to
 * be a font and is not built like one is refused before it is installed.
 *
 * @param string $file Path.
 * @param string $ext  ttf, otf, woff or woff2.
 * @return bool
 */
function is_well_formed( $file, $ext ) {
	$size = (int) filesize( $file );
	$raw  = (string) file_get_contents( $file, false, null, 0, min( $size, 12 + 64 * 20 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a font being checked.
	if ( $size < 12 || strlen( $raw ) < 12 ) {
		return false;
	}
	$tag = substr( $raw, 0, 4 );
	if ( 'wOF2' === $tag ) {
		$length = unpack( 'N', substr( $raw, 8, 4 ) )[1];
		$tables = unpack( 'n', substr( $raw, 12, 2 ) )[1];

		return 'woff2' === $ext && $length === $size && $tables >= 1 && $tables <= 64;
	}
	$tables = unpack( 'n', substr( $raw, 4, 2 ) )[1];
	if ( $tables < 1 || $tables > 64 ) {
		return false;
	}
	if ( 'wOFF' === $tag ) {
		$length = unpack( 'N', substr( $raw, 8, 4 ) )[1];
		if ( 'woff' !== $ext || $length !== $size || strlen( $raw ) < 44 + 20 * $tables ) {
			return false;
		}
		for ( $i = 0; $i < $tables; $i++ ) {
			$entry = unpack( 'Noffset/Ncomp/Norig', substr( $raw, 44 + 20 * $i, 12 ) );
			if ( $entry['offset'] < 44 + 20 * $tables || $entry['offset'] + $entry['comp'] > $size || $entry['comp'] > $entry['orig'] ) {
				return false;
			}
		}

		return true;
	}
	if ( ! in_array( $ext, array( 'ttf', 'otf' ), true ) || ! in_array( $tag, array( "\x00\x01\x00\x00", 'true', 'OTTO' ), true ) || strlen( $raw ) < 12 + 16 * $tables ) {
		return false;
	}
	for ( $i = 0; $i < $tables; $i++ ) {
		$entry = unpack( 'Noffset/Nlength', substr( $raw, 12 + 16 * $i + 8, 8 ) );
		if ( $entry['offset'] < 12 + 16 * $tables || $entry['offset'] + $entry['length'] > $size ) {
			return false;
		}
	}

	return true;
}
