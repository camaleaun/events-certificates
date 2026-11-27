<?php
/**
 * The third-party PHP libraries under the plugin's own namespace (docs/decisions/0032).
 *
 * Copies the runtime packages of composer.lock (dompdf, php-qrcode and what they
 * need) into includes/third-party/ and renames their namespaces to
 * EventsCertificate\Vendor\…, so two plugins with different versions of the same
 * library don't break each other. Pure functions, no WP-CLI and no WordPress, so
 * they are tested (tests/php/DepsTest.php); bin/deps.php is the command that runs them.
 *
 * What is rewritten, from the file's tokens (not its text): the namespace, the
 * imports, fully qualified names, and class names written as strings (the
 * libraries build "Dompdf\\Adapter\\GD" and the like). Relative and bare names
 * follow their namespace, so they are left alone. Works on PHP 7.4 and 8.x
 * (8 tokenizes names as one token, 7.4 as pieces).
 *
 * Not shipped (bin/ is export-ignored).
 *
 * @package EventsCertificate
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- a dev tool run from the command line.

/**
 * The namespace the libraries move under.
 */
const EVCERT_DEPS_PREFIX = 'EventsCertificate\\Vendor';

/**
 * The file's tokens, with the pieces of a name merged into one: array( kind, text ).
 * kind is a token id, or 'NAME' for a (qualified) name, or 'CHAR' for a one-character token.
 *
 * @param string $code PHP.
 * @return array<int, array{0: int|string, 1: string}>
 */
function evcert_deps_tokens( string $code ): array {
	$name_ids = array( T_STRING, T_NS_SEPARATOR );
	foreach ( array( 'T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED', 'T_NAME_RELATIVE' ) as $constant ) {
		if ( defined( $constant ) ) {
			$name_ids[] = constant( $constant );
		}
	}
	$raw    = token_get_all( $code );
	$count  = count( $raw );
	$tokens = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$token = $raw[ $i ];
		if ( ! is_array( $token ) ) {
			$tokens[] = array( 'CHAR', $token );
			continue;
		}
		if ( in_array( $token[0], $name_ids, true ) ) {
			$text = $token[1];
			while ( $i + 1 < $count && is_array( $raw[ $i + 1 ] ) && in_array( $raw[ $i + 1 ][0], $name_ids, true ) ) {
				$text .= $raw[ ++$i ][1];
			}
			$tokens[] = array( 'NAME', $text );
			continue;
		}
		$tokens[] = array( $token[0], $token[1] );
	}

	return $tokens;
}

/**
 * Whether a token is whitespace or a comment (what code reads past).
 *
 * @param array{0: int|string, 1: string} $token Token.
 * @return bool
 */
function evcert_deps_is_blank( array $token ): bool {
	return in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true );
}

/**
 * The next token that is not whitespace or a comment.
 *
 * @param array<int, array{0: int|string, 1: string}> $tokens Tokens.
 * @param int                                         $from   Index to start after.
 * @return array{0: int|string, 1: string}|null
 */
function evcert_deps_next( array $tokens, int $from ): ?array {
	$count = count( $tokens );
	for ( $i = $from + 1; $i < $count; $i++ ) {
		if ( ! evcert_deps_is_blank( $tokens[ $i ] ) ) {
			return $tokens[ $i ];
		}
	}

	return null;
}

/**
 * A name under the prefix when it begins with one of the libraries' namespaces.
 *
 * @param string   $name     Name, with a leading backslash when fully qualified.
 * @param string[] $roots    Root namespaces of the libraries.
 * @param bool     $absolute Whether a name with no leading backslash is absolute (imports and namespace declarations).
 * @return string
 */
function evcert_deps_prefix_name( string $name, array $roots, bool $absolute ): string {
	$lead = '';
	$rest = $name;
	if ( '\\' === substr( $name, 0, 1 ) ) {
		$lead     = '\\';
		$rest     = substr( $name, 1 );
		$absolute = true;
	}
	$first = explode( '\\', $rest, 2 )[0];
	if ( ! $absolute || ! in_array( $first, $roots, true ) ) {
		return $name;
	}

	return $lead . EVCERT_DEPS_PREFIX . '\\' . $rest;
}

/**
 * A string that holds a class name of the libraries, under the prefix. The separators keep the string's own style
 * ("Dompdf\\Css" in double quotes, 'Dompdf\Css' in single).
 *
 * @param string   $text   The string's source, with its quotes ($quoted) or without (a piece of an interpolated string).
 * @param string[] $roots  Root namespaces.
 * @param bool     $quoted Whether $text has its quotes.
 * @return string
 */
function evcert_deps_prefix_string( string $text, array $roots, bool $quoted ): string {
	$alternatives = implode( '|', array_map( 'preg_quote', $roots ) );
	$pattern      = '/^' . ( $quoted ? '(["\'])' : '()' ) . '(\\\\\\\\|\\\\)?(' . $alternatives . ')(\\\\\\\\|\\\\)/';

	return (string) preg_replace_callback(
		$pattern,
		static function ( $m ) {
			$sep = $m[4];

			return $m[1] . $m[2] . str_replace( '\\', $sep, EVCERT_DEPS_PREFIX ) . $sep . $m[3] . $sep;
		},
		$text,
		1
	);
}

/**
 * A file's code under the prefix.
 *
 * @param string   $code   PHP, with its opening tag.
 * @param string[] $roots  Root namespaces of the libraries (the first segment of each PSR-4 namespace).
 * @return string
 */
function evcert_deps_rewrite( string $code, array $roots ): string {
	$tokens   = evcert_deps_tokens( $code );
	$out      = '';
	$previous = null; // The last token that was not whitespace or a comment.
	$in_use   = false;
	$depth    = 0;
	foreach ( $tokens as $i => $token ) {
		list( $kind, $text ) = $token;
		if ( evcert_deps_is_blank( $token ) ) {
			$out .= $text;
			continue;
		}
		if ( 'NAME' === $kind ) {
			$absolute = ( $previous && T_NAMESPACE === $previous[0] ) || ( $in_use && 0 === $depth );
			$text     = evcert_deps_prefix_name( $text, $roots, $absolute );
		} elseif ( T_USE === $kind ) {
			// An import (or a trait's use), not a closure's "use ( $x )".
			$next   = evcert_deps_next( $tokens, $i );
			$in_use = ! ( $next && 'CHAR' === $next[0] && '(' === $next[1] );
			$depth  = 0;
		} elseif ( 'CHAR' === $kind && $in_use ) {
			if ( '{' === $text ) {
				++$depth;
			} elseif ( '}' === $text ) {
				--$depth;
				if ( 0 === $depth ) {
					// A group import ends with ";", a trait's conflict block does not.
					$next   = evcert_deps_next( $tokens, $i );
					$in_use = $next && 'CHAR' === $next[0] && ';' === $next[1];
				}
			} elseif ( ';' === $text && 0 === $depth ) {
				$in_use = false;
			}
		} elseif ( T_CONSTANT_ENCAPSED_STRING === $kind ) {
			$text = evcert_deps_prefix_string( $text, $roots, true );
		} elseif ( T_ENCAPSED_AND_WHITESPACE === $kind && $previous && 'CHAR' === $previous[0] && '"' === $previous[1] ) {
			$text = evcert_deps_prefix_string( $text, $roots, false );
		}
		$out     .= $text;
		$previous = array( $kind, $text );
	}

	return $out;
}

/**
 * The classes, interfaces and traits a file declares, with their namespaces (not the ones it only names).
 *
 * @param string $code PHP.
 * @return string[]
 */
function evcert_deps_declared( string $code ): array {
	$tokens    = evcert_deps_tokens( $code );
	$namespace = '';
	$previous  = null;
	$found     = array();
	foreach ( $tokens as $i => $token ) {
		if ( evcert_deps_is_blank( $token ) ) {
			continue;
		}
		list( $kind ) = $token;
		if ( T_NAMESPACE === $kind ) {
			$next      = evcert_deps_next( $tokens, $i );
			$namespace = $next && 'NAME' === $next[0] ? ltrim( $next[1], '\\' ) : '';
		} elseif ( in_array( $kind, array( T_CLASS, T_INTERFACE, T_TRAIT ), true ) && ! ( $previous && in_array( $previous[0], array( T_DOUBLE_COLON, T_NEW ), true ) ) ) {
			$next = evcert_deps_next( $tokens, $i );
			if ( $next && ( T_STRING === $next[0] || 'NAME' === $next[0] ) ) {
				$found[] = ( '' !== $namespace ? $namespace . '\\' : '' ) . $next[1];
			}
		}
		$previous = $token;
	}

	return $found;
}

/**
 * Whether a file of a package stays out of the copy: tests, docs, examples, tooling, and dompdf's DejaVu fonts
 * (the plugin has its own fonts).
 *
 * @param string $path Path inside the package.
 * @return bool
 */
function evcert_deps_excluded( string $path ): bool {
	$path = ltrim( str_replace( '\\', '/', $path ), '/' );
	if ( preg_match( '#(^|/)(tests?|docs?|examples?|\.idea|\.github|\.git)(/|$)#i', $path ) ) {
		return true;
	}
	if ( preg_match( '#lib/fonts/DejaVu#', $path ) ) {
		return true;
	}

	return (bool) preg_match( '#(^|/)(phpunit\.xml(\.dist)?|composer\.(json|lock)|\.gitignore|\.gitattributes|\.editorconfig|\.travis\.yml|(README|CHANGELOG|CONTRIBUTING|UPGRAD(E|ING))[^/]*)$#i', $path );
}

/**
 * The autoloader written next to the copied libraries: a map from class to file, and the files that must
 * run first (they define aliases the others use).
 *
 * @param array<string, string> $classmap Class => file, relative to the autoloader.
 * @param string[]              $files    Files to require when the autoloader is, relative to it.
 * @return string
 */
function evcert_deps_autoload_source( array $classmap, array $files ): string {
	ksort( $classmap );
	$map = '';
	foreach ( $classmap as $class => $file ) {
		$map .= "\t\t" . var_export( $class, true ) . ' => ' . var_export( $file, true ) . ",\n";
	}
	$requires = '';
	foreach ( $files as $file ) {
		$requires .= 'require_once __DIR__ . ' . var_export( '/' . $file, true ) . ";\n";
	}

	return "<?php\n"
		. "/**\n"
		. " * Autoloader of the third-party libraries, made by `evcert deps` (bin/deps.php): do not edit.\n"
		. " * Their namespaces are renamed under " . EVCERT_DEPS_PREFIX . " (docs/decisions/0032).\n"
		. " *\n"
		. " * @package EventsCertificate\n"
		. " */\n\n"
		. "spl_autoload_register(\n"
		. "\tstatic function ( \$class ) {\n"
		. "\t\tstatic \$map = array(\n"
		. $map
		. "\t\t);\n"
		. "\t\tif ( isset( \$map[ \$class ] ) ) {\n"
		. "\t\t\trequire_once __DIR__ . '/' . \$map[ \$class ];\n"
		. "\t\t}\n"
		. "\t}\n"
		. ");\n\n"
		. $requires;
}

/**
 * The few changes a library needs besides its names, by "<package>/<file>": each is array( from, to ). They
 * must match exactly: a library that changes the line fails the build, so the change is looked at.
 *
 * @return array<string, array<int, array{0: string, 1: string}>>
 */
function evcert_deps_patches(): array {
	return array(
		// The font type is the namespace segment before the class, wherever the namespace starts.
		'dompdf/php-font-lib/src/FontLib/TrueType/File.php' => array(
			array(
				"\$class_parts = explode(\"\\\\\", get_class(\$this));\n    return \$class_parts[1];",
				"\$class_parts = explode(\"\\\\\", get_class(\$this));\n    return \$class_parts[count(\$class_parts) - 2];",
			),
		),
	);
}

/**
 * Removes a folder made by the build.
 *
 * @param string $dir Folder.
 * @throws RuntimeException When the folder is not one the build makes.
 */
function evcert_deps_remove( string $dir ): void {
	$base = basename( $dir );
	if ( 'third-party' !== $base && 0 !== strpos( $base, 'evcert-' ) ) {
		throw new RuntimeException( "Refusing to remove $dir: not a third-party folder." );
	}
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $items as $item ) {
		$item->isDir() && ! $item->isLink() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}
	rmdir( $dir );
}

/**
 * Copies the runtime packages of composer.lock under the prefix.
 *
 * @param string $lock   Path of composer.lock.
 * @param string $vendor Folder Composer installed the packages in.
 * @param string $target Where the copy goes (it is made again from nothing).
 * @return array{packages: string[], files: int, classes: int, map: array<string, string>}
 * @throws RuntimeException When a package is not installed or a patch no longer applies.
 */
function evcert_deps_build( string $lock, string $vendor, string $target ): array {
	$data = json_decode( (string) file_get_contents( $lock ), true );
	if ( ! is_array( $data ) || empty( $data['packages'] ) ) {
		throw new RuntimeException( "$lock has no packages." );
	}
	$packages = $data['packages'];
	$roots    = array();
	foreach ( $packages as $package ) {
		foreach ( array_keys( (array) ( $package['autoload']['psr-4'] ?? array() ) ) as $namespace ) {
			$roots[] = explode( '\\', $namespace )[0];
		}
	}
	$roots = array_values( array_unique( $roots ) );

	evcert_deps_remove( $target );
	mkdir( $target, 0777, true );
	$patches = evcert_deps_patches();
	$map     = array();
	$files   = array();
	$count   = 0;
	foreach ( $packages as $package ) {
		$name = $package['name'];
		$from = rtrim( $vendor, '/' ) . '/' . $name;
		if ( ! is_dir( $from ) ) {
			throw new RuntimeException( "$name is not installed in $vendor: run composer install." );
		}
		$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $from, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $items as $item ) {
			if ( ! $item->isFile() ) {
				continue;
			}
			$relative = substr( $item->getPathname(), strlen( $from ) + 1 );
			if ( evcert_deps_excluded( $relative ) ) {
				continue;
			}
			$destination = $target . '/' . $name . '/' . $relative;
			if ( ! is_dir( dirname( $destination ) ) ) {
				mkdir( dirname( $destination ), 0777, true );
			}
			if ( '.php' !== substr( $relative, -4 ) ) {
				copy( $item->getPathname(), $destination );
				++$count;
				continue;
			}
			$code = evcert_deps_rewrite( (string) file_get_contents( $item->getPathname() ), $roots );
			foreach ( $patches[ $name . '/' . $relative ] ?? array() as $patch ) {
				if ( false === strpos( $code, $patch[0] ) ) {
					throw new RuntimeException( "$name/$relative changed: its patch no longer applies (bin/deps-lib.php, evcert_deps_patches)." );
				}
				$code = str_replace( $patch[0], $patch[1], $code );
			}
			file_put_contents( $destination, $code );
			++$count;
			foreach ( evcert_deps_declared( $code ) as $class ) {
				$map[ $class ] = $name . '/' . $relative;
			}
		}
		foreach ( (array) ( $package['autoload']['files'] ?? array() ) as $file ) {
			$files[] = $name . '/' . $file;
		}
	}
	file_put_contents( $target . '/autoload.php', evcert_deps_autoload_source( $map, $files ) );

	return array(
		'packages' => array_column( $packages, 'name' ),
		'files'    => $count,
		'classes'  => count( $map ),
		'map'      => $map,
	);
}
