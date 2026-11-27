<?php
/**
 * The third-party PHP libraries, copied under the plugin's own namespace (bin/deps-lib.php).
 *
 * @package EventsCertificate
 */

namespace EventsCertificate\Tests;

// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- the tests write PHP code for another process.

/**
 * Prefixer tests: the rewriting of one file, and a real build of the runtime packages.
 */
class DepsTest extends Test_Case {

	/**
	 * The root namespaces of the libraries of these tests.
	 *
	 * @var string[]
	 */
	private $roots = array( 'Dompdf', 'FontLib', 'chillerlan' );

	/**
	 * Loads the helpers.
	 */
	protected function set_up() {
		parent::set_up();
		require_once dirname( __DIR__, 2 ) . '/bin/deps-lib.php';
	}

	/**
	 * Rewrites a snippet of a library.
	 *
	 * @param string $code PHP without its opening tag.
	 * @return string
	 */
	private function rewrite( $code ) {
		return substr( evcert_deps_rewrite( "<?php\n" . $code, $this->roots ), 6 );
	}

	/**
	 * The namespace of a library file moves under the prefix.
	 */
	public function test_namespace_declarations() {
		$this->assertSame( "namespace EventsCertificate\\Vendor\\Dompdf\\Css;\nclass A {}", $this->rewrite( "namespace Dompdf\\Css;\nclass A {}" ) );
		$this->assertSame( 'namespace EventsCertificate\\Vendor\\Dompdf;', $this->rewrite( 'namespace Dompdf;' ) );
		$this->assertSame( 'namespace Other\\Lib;', $this->rewrite( 'namespace Other\\Lib;' ), 'Not one of the libraries.' );
	}

	/**
	 * Imports of the libraries' own names: use, use function, use const, aliases, lists and groups.
	 */
	public function test_imports() {
		$this->assertSame( 'use EventsCertificate\\Vendor\\Dompdf\\Dompdf;', $this->rewrite( 'use Dompdf\\Dompdf;' ) );
		$this->assertSame( 'use function EventsCertificate\\Vendor\\Dompdf\\helper;', $this->rewrite( 'use function Dompdf\\helper;' ) );
		$this->assertSame( 'use const EventsCertificate\\Vendor\\FontLib\\SIZE;', $this->rewrite( 'use const FontLib\\SIZE;' ) );
		$this->assertSame( 'use EventsCertificate\\Vendor\\Dompdf\\Css as Style;', $this->rewrite( 'use Dompdf\\Css as Style;' ) );
		$this->assertSame( 'use Other\\Thing, EventsCertificate\\Vendor\\Dompdf\\Options;', $this->rewrite( 'use Other\\Thing, Dompdf\\Options;' ) );
		$this->assertSame( 'use EventsCertificate\\Vendor\\chillerlan\\QRCode\\{QRCode, QRCodeException};', $this->rewrite( 'use chillerlan\\QRCode\\{QRCode, QRCodeException};' ) );
		$this->assertSame( "use EventsCertificate\\Vendor\\chillerlan\\QRCode\\Data\\{\n\tByte,\n\tNumber,\n};", $this->rewrite( "use chillerlan\\QRCode\\Data\\{\n\tByte,\n\tNumber,\n};" ) );
		$this->assertSame( 'use Other\\Dompdf;', $this->rewrite( 'use Other\\Dompdf;' ), 'A name that only ends like a library.' );
		$this->assertSame( 'use Exception;', $this->rewrite( 'use Exception;' ) );
	}

	/**
	 * Fully qualified names in code, whatever they are used for.
	 */
	public function test_fully_qualified_names() {
		$this->assertSame( '$a = new \\EventsCertificate\\Vendor\\Dompdf\\Dompdf();', $this->rewrite( '$a = new \\Dompdf\\Dompdf();' ) );
		$this->assertSame( '$f = \\EventsCertificate\\Vendor\\FontLib\\Font::load( $p );', $this->rewrite( '$f = \\FontLib\\Font::load( $p );' ) );
		$this->assertSame( 'if ( $x instanceof \\EventsCertificate\\Vendor\\Dompdf\\Canvas ) {}', $this->rewrite( 'if ( $x instanceof \\Dompdf\\Canvas ) {}' ) );
		$this->assertSame( 'function f( \\EventsCertificate\\Vendor\\Dompdf\\Options $o ): \\EventsCertificate\\Vendor\\Dompdf\\Cpdf {}', $this->rewrite( 'function f( \\Dompdf\\Options $o ): \\Dompdf\\Cpdf {}' ) );
		$this->assertSame( '$c = \\EventsCertificate\\Vendor\\Dompdf\\Cpdf::class;', $this->rewrite( '$c = \\Dompdf\\Cpdf::class;' ) );
		$this->assertSame( 'class A extends \\EventsCertificate\\Vendor\\Dompdf\\Frame implements \\Countable {}', $this->rewrite( 'class A extends \\Dompdf\\Frame implements \\Countable {}' ) );
		$this->assertSame( 'class A { use \\EventsCertificate\\Vendor\\Dompdf\\Helper; }', $this->rewrite( 'class A { use \\Dompdf\\Helper; }' ) );
	}

	/**
	 * What must stay: PHP's own names, relative and bare names, comments, variables and closures.
	 */
	public function test_what_is_left_alone() {
		foreach ( array(
			'$a = new \\DOMDocument();',
			'throw new \\Exception( "x" );',
			'$a = new Dompdf();',
			'$a = new Css\\Style();',
			'$Dompdf = 1;',
			'// new \\Dompdf\\Dompdf()',
			"/**\n * @param \\Dompdf\\Options \$o\n */",
			'$f = function () use ( $x ) { return $x; };',
			'class A { use SomeTrait; }',
			'$a = \\Other\\Dompdf::x();',
		) as $code ) {
			$this->assertSame( $code, $this->rewrite( $code ), $code );
		}
	}

	/**
	 * Class names written as strings, the way the libraries build classes to load: all the quoting styles.
	 */
	public function test_class_names_in_strings() {
		$this->assertSame( '$c = "EventsCertificate\\\\Vendor\\\\Dompdf\\\\Adapter\\\\GD";', $this->rewrite( '$c = "Dompdf\\\\Adapter\\\\GD";' ) );
		$this->assertSame( "\$c = 'EventsCertificate\\Vendor\\Dompdf\\Adapter\\GD';", $this->rewrite( "\$c = 'Dompdf\\Adapter\\GD';" ) );
		$this->assertSame( "\$c = '\\\\EventsCertificate\\\\Vendor\\\\Dompdf\\\\Positioner\\\\'.\$type;", $this->rewrite( "\$c = '\\\\Dompdf\\\\Positioner\\\\'.\$type;" ), 'Leading separator, then a concatenation.' );
		$this->assertSame( '$c = "EventsCertificate\\\\Vendor\\\\FontLib\\\\Table\\\\Type\\\\$name";', $this->rewrite( '$c = "FontLib\\\\Table\\\\Type\\\\$name";' ), 'A variable inside the double quotes.' );
		$this->assertSame( '$ok = in_array( "EventsCertificate\\\\Vendor\\\\Dompdf\\\\Canvas", $implements );', $this->rewrite( '$ok = in_array( "Dompdf\\\\Canvas", $implements );' ) );
		foreach ( array( '$t = "Dompdf is a library";', "\$t = 'Dompdf';", '$t = "notDompdf\\\\X";', '$t = "Foo\\\\Dompdf\\\\Bar";', '$t = "";' ) as $code ) {
			$this->assertSame( $code, $this->rewrite( $code ), $code );
		}
	}

	/**
	 * Rewriting a file that is already rewritten changes nothing.
	 */
	public function test_it_is_idempotent() {
		$code = "namespace Dompdf;\nuse FontLib\\Font;\n\$a = new \\Dompdf\\Dompdf();\n\$c = \"Dompdf\\\\Adapter\\\\GD\";";
		$once = $this->rewrite( $code );
		$this->assertSame( $once, $this->rewrite( $once ) );
	}

	/**
	 * The classes, interfaces and traits a file declares, with their namespaces; not the ones it only names.
	 */
	public function test_declared_classes() {
		$code = <<<'PHP'
<?php
namespace A\B;

use C\D;

abstract class One extends D { const X = Two::class; }
final class Two {}
interface Three {}
trait Four {}
if ( ! interface_exists( Five::class, false ) ) {
	interface Five {}
}
$x = new class {};
$y = Other::class;
PHP;
		$this->assertSame( array( 'A\\B\\One', 'A\\B\\Two', 'A\\B\\Three', 'A\\B\\Four', 'A\\B\\Five' ), evcert_deps_declared( $code ) );
		$this->assertSame( array( 'Globe' ), evcert_deps_declared( "<?php\nclass Globe {}" ), 'No namespace.' );
	}

	/**
	 * What each package leaves out of the copy.
	 */
	public function test_exclusions() {
		foreach ( array( 'tests/A.php', 'test/B.php', 'docs/x.md', 'examples/y.php', '.idea/z.xml', 'phpunit.xml', 'phpunit.xml.dist', 'README.md', 'CHANGELOG.md', 'composer.json', 'lib/fonts/DejaVuSans.ttf', '.github/x.yml', '.gitignore' ) as $path ) {
			$this->assertTrue( evcert_deps_excluded( $path ), "$path is left out" );
		}
		foreach ( array( 'src/Dompdf.php', 'lib/Cpdf.php', 'lib/fonts/Courier.afm', 'lib/res/html.css', 'LICENSE', 'LICENSE.LGPL', 'AUTHORS.md', 'CREDITS', 'VERSION' ) as $path ) {
			$this->assertFalse( evcert_deps_excluded( $path ), "$path is kept" );
		}
	}

	/**
	 * The generated autoloader loads a class from its map and runs the files that must come first.
	 */
	public function test_autoload_source() {
		$dir = sys_get_temp_dir() . '/evcert-autoload-' . wp_generate_password( 6, false );
		mkdir( $dir . '/lib', 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		file_put_contents( $dir . '/lib/Thing.php', "<?php\nnamespace Zed;\nclass Thing { const OK = 1; }\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $dir . '/boot.php', "<?php\ndefine( 'EVCERT_DEPS_TEST_BOOT', 1 );\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $dir . '/autoload.php', evcert_deps_autoload_source( array( 'Zed\\Thing' => 'lib/Thing.php' ), array( 'boot.php' ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$out = $this->php( 'require ' . var_export( $dir . '/autoload.php', true ) . '; echo defined( "EVCERT_DEPS_TEST_BOOT" ) ? "boot " : "noboot ", class_exists( "Zed\\\\Thing" ) ? Zed\\Thing::OK : "missing", class_exists( "Zed\\\\Nope" ) ? " bad" : " ok";' );
		$this->assertSame( 'boot 1 ok', $out );
		foreach ( array( '/lib/Thing.php', '/boot.php', '/autoload.php' ) as $file ) {
			unlink( $dir . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		rmdir( $dir . '/lib' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/**
	 * Runs PHP in another process (the generated classes load there, with nothing else in memory).
	 *
	 * @param string $code PHP to run.
	 * @return string What it printed.
	 */
	private function php( $code ) {
		return trim( (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' -d display_errors=1 -r ' . escapeshellarg( $code ) . ' 2>&1' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
	}

	/**
	 * The real build: the runtime packages of composer.lock, copied and prefixed. Every class loads, and a PDF and a QR code are made.
	 */
	public function test_the_runtime_packages_build_and_work() {
		$root = dirname( __DIR__, 2 );
		if ( ! is_dir( $root . '/vendor/dompdf/dompdf' ) ) {
			$this->markTestSkipped( 'The packages are not installed (composer install).' );
		}
		$target = sys_get_temp_dir() . '/evcert-third-party-' . wp_generate_password( 6, false );
		$report = evcert_deps_build( $root . '/composer.lock', $root . '/vendor', $target );
		$this->assertGreaterThan( 200, $report['classes'] );
		$this->assertFileExists( $target . '/autoload.php' );
		$this->assertFileExists( $target . '/dompdf/dompdf/LICENSE.LGPL', 'Licenses travel with the code.' );
		$this->assertFileDoesNotExist( $target . '/dompdf/dompdf/lib/fonts/DejaVuSans.ttf' );

		// Nothing of the libraries is left under its own name.
		$bare = (string) shell_exec( 'grep -rlE "^(namespace|use) (Dompdf|FontLib|Svg|Masterminds|Sabberworm|chillerlan)\\\\" ' . escapeshellarg( $target ) . ' --include=*.php 2>/dev/null' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		$this->assertSame( '', trim( $bare ), 'No file keeps an original namespace or import.' );

		// Every file is valid PHP, and every declared class loads.
		$lint = (string) shell_exec( 'find ' . escapeshellarg( $target ) . ' -name "*.php" -print0 | xargs -0 -n1 ' . escapeshellarg( PHP_BINARY ) . ' -l 2>&1 | grep -v "^No syntax errors"' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		$this->assertSame( '', trim( $lint ), 'Every file lints.' );
		$loads = $this->php( 'require ' . var_export( $target . '/autoload.php', true ) . '; $bad = array(); foreach ( ' . var_export( array_keys( $report['map'] ), true ) . ' as $c ) { if ( ! class_exists( $c ) && ! interface_exists( $c ) && ! trait_exists( $c ) ) { $bad[] = $c; } } echo $bad ? implode( ",", $bad ) : "all";' );
		$this->assertSame( 'all', $loads, 'Every mapped class loads.' );

		// The two things the plugin does with them.
		$pdf = $this->php( 'require ' . var_export( $target . '/autoload.php', true ) . '; $o = new EventsCertificate\\Vendor\\Dompdf\\Options(); $o->set( "isRemoteEnabled", false ); $d = new EventsCertificate\\Vendor\\Dompdf\\Dompdf( $o ); $d->loadHtml( "<h1>Certificado</h1><svg width=\"20\" height=\"20\"><rect width=\"20\" height=\"20\" fill=\"#3858e9\"/></svg>" ); $d->render(); echo substr( $d->output(), 0, 5 );' );
		$this->assertSame( '%PDF-', $pdf, 'dompdf renders a PDF through the prefixed classes (fonts, SVG and CSS parsing included).' );
		$qr = $this->php( 'require ' . var_export( $target . '/autoload.php', true ) . '; $q = new EventsCertificate\\Vendor\\chillerlan\\QRCode\\QRCode( new EventsCertificate\\Vendor\\chillerlan\\QRCode\\QROptions( array( "outputType" => EventsCertificate\\Vendor\\chillerlan\\QRCode\\QRCode::OUTPUT_MARKUP_SVG ) ) ); echo substr( $q->render( "https://example.org/certificate/X/" ), 0, 20 );' );
		$this->assertStringContainsString( 'svg', $qr, 'php-qrcode makes an SVG.' );

		shell_exec( 'rm -rf ' . escapeshellarg( $target ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
	}
}
