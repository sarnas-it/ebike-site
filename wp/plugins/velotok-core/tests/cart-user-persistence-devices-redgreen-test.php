<?php
/**
 * PHP red-green proof for TESTEB-10: the customer cart is persisted between
 * sessions and devices.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/cart-user-persistence-devices-redgreen-test.php
 *
 * The test builds broken copies of the WooCommerce cart integration:
 *
 * 1. the customer cart is not written to persistent cart user meta;
 * 2. the customer cart is not read back from persistent cart user meta.
 *
 * The focused persistence test must fail against both broken copies and pass
 * against the real module.
 *
 * @package Velotok_Core
 */

$root        = dirname( __DIR__ );
$module_path = $root . '/includes/cart-woocommerce.php';
$focused     = __DIR__ . '/cart-user-persistence-devices-test.php';

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найдена интеграция корзины с WooCommerce.' . PHP_EOL );
	exit( 1 );
}

$source = file_get_contents( $module_path );

if ( false === $source || '' === $source ) {
	fwrite( STDERR, 'FAIL: Не удалось прочитать интеграцию корзины.' . PHP_EOL );
	exit( 1 );
}

$meta_store = "\tif ( function_exists( 'update_user_meta' ) ) {\n\t\t\$meta_key = velotok_core_wc_cart_user_meta_key( \$user_id );\n\n\t\tupdate_user_meta( \$user_id, \$meta_key, \$payload );\n\t\t\$stored = true;\n\n\t\tif ( VELOTOK_CART_USER_CART_META !== \$meta_key ) {\n\t\t\tupdate_user_meta( \$user_id, VELOTOK_CART_USER_CART_META, \$payload );\n\t\t}\n\t}";
$meta_read  = "\tif ( function_exists( 'get_user_meta' ) ) {\n\t\tforeach ( velotok_core_wc_cart_user_meta_keys( \$user_id ) as \$key ) {\n\t\t\t\$value = get_user_meta( \$user_id, \$key, true );\n\n\t\t\tif ( is_array( \$value ) && ! empty( \$value ) ) {\n\t\t\t\t\$raw = \$value;\n\t\t\t\tbreak;\n\t\t\t}\n\t\t}\n\t}";

$variants = array(
	'no-user-meta-write' => str_replace(
		$meta_store,
		"\t\$stored = false;",
		$source
	),
	'no-user-meta-read' => str_replace(
		$meta_read,
		"\tif ( false ) {\n\t\t\$raw = null;\n\t}",
		$source
	),
);

$tmp_dir = sys_get_temp_dir() . '/velotok-testeb-10-' . bin2hex( random_bytes( 6 ) );

if ( ! mkdir( $tmp_dir, 0700, true ) && ! is_dir( $tmp_dir ) ) {
	fwrite( STDERR, 'FAIL: Не удалось создать временный каталог для red-green проверки.' . PHP_EOL );
	exit( 1 );
}

/**
 * Run the focused customer persistence test against a cart integration module.
 *
 * @param string $test_path   Test file.
 * @param string $module_file Module path or empty string for the real module.
 * @return array{status:int,output:string}
 */
function velotok_testeb_10_run_php( $test_path, $module_file ) {
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

	$broken = velotok_testeb_10_run_php( $focused, $broken_path );

	if ( 0 === $broken['status'] ) {
		$failures[] = 'Фокусный тест прошёл на сломанной версии «' . $name . '», хотя должен падать.';
	}
}

$real = velotok_testeb_10_run_php( $focused, '' );

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

fwrite( STDOUT, 'PASS: TESTEB-10 падает без сохранения корзины пользователя между сессиями и устройствами и проходит с ним.' . PHP_EOL );
exit( 0 );
