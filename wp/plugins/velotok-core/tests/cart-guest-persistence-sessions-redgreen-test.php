<?php
/**
 * PHP red-green proof for TESTEB-9: a guest cart is persisted between sessions.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/cart-guest-persistence-sessions-redgreen-test.php
 *
 * The test builds broken copies of the WooCommerce cart integration:
 *
 * 1. the snapshot is stored in the session but not mirrored into the
 *    long-lived cookie;
 * 2. the snapshot is read from the session but the cookie fallback is ignored.
 *
 * The focused persistence test must fail against both broken copies and pass
 * against the real module.
 *
 * @package Velotok_Core
 */

$root        = dirname( __DIR__ );
$module_path = $root . '/includes/cart-woocommerce.php';
$focused     = __DIR__ . '/cart-guest-persistence-sessions-test.php';

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найдена интеграция корзины с WooCommerce.' . PHP_EOL );
	exit( 1 );
}

$source = file_get_contents( $module_path );

if ( false === $source || '' === $source ) {
	fwrite( STDERR, 'FAIL: Не удалось прочитать интеграцию корзины.' . PHP_EOL );
	exit( 1 );
}

$cookie_store   = "\t\$stored = velotok_core_wc_cart_write_guest_cookie( \$items ) || \$stored;";
$cookie_restore = "\tif ( empty( \$items ) ) {\n\t\t\$items = velotok_core_wc_cart_read_guest_cookie();\n\t}\n\n\treturn \$items;\n}";

$variants = array(
	'no-cookie-mirror' => str_replace(
		$cookie_store,
		"\t\$stored = \$stored;",
		$source
	),
	'no-cookie-restore' => str_replace(
		$cookie_restore,
		"\treturn \$items;\n}",
		$source
	),
);

$tmp_dir = sys_get_temp_dir() . '/velotok-testeb-9-' . bin2hex( random_bytes( 6 ) );

if ( ! mkdir( $tmp_dir, 0700, true ) && ! is_dir( $tmp_dir ) ) {
	fwrite( STDERR, 'FAIL: Не удалось создать временный каталог для red-green проверки.' . PHP_EOL );
	exit( 1 );
}

/**
 * Run the focused persistence test against a cart integration module.
 *
 * @param string $test_path   Test file.
 * @param string $module_file Module path or empty string for the real module.
 * @return array{status:int,output:string}
 */
function velotok_testeb_9_run_php( $test_path, $module_file ) {
	$previous = getenv( 'VELOTOK_CART_WC_INTEGRATION' );
	$module   = (string) $module_file;

	if ( '' === $module ) {
		putenv( 'VELOTOK_CART_WC_INTEGRATION' );
	} else {
		putenv( 'VELOTOK_CART_WC_INTEGRATION=' . $module );
	}

	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $test_path ) . ' 2>&1';
	$output  = array();
	$status  = 0;

	exec( $command, $output, $status );

	if ( false === $previous ) {
		putenv( 'VELOTOK_CART_WC_INTEGRATION' );
	} else {
		putenv( 'VELOTOK_CART_WC_INTEGRATION=' . $previous );
	}

	return array(
		'status' => (int) $status,
		'output' => implode( PHP_EOL, $output ),
	);
}

$failures = array();

foreach ( $variants as $name => $variant ) {
	if ( $variant === $source ) {
		$failures[] = 'Не удалось построить сломанную версию интеграции: ' . $name . '.';
		continue;
	}

	$broken_path = $tmp_dir . '/cart-woocommerce-' . $name . '.php';
	file_put_contents( $broken_path, $variant );

	$broken = velotok_testeb_9_run_php( $focused, $broken_path );

	if ( 0 === $broken['status'] ) {
		$failures[] = 'Фокусный тест прошёл на сломанной версии «' . $name . '», хотя должен падать.';
	}
}

$real = velotok_testeb_9_run_php( $focused, '' );

if ( 0 !== $real['status'] ) {
	$failures[] = 'Фокусный тест должен проходить на настоящей реализации, но завершился с кодом ' . $real['status'] . ':' . PHP_EOL . $real['output'];
}

foreach ( glob( $tmp_dir . '/*' ) as $file ) {
	@unlink( $file );
}

@rmdir( $tmp_dir );

if ( array() !== $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

fwrite( STDOUT, 'PASS: TESTEB-9 падает без сохранения гостевой корзины между сессиями и проходит с ним.' . PHP_EOL );
exit( 0 );
