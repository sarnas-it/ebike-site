<?php
/**
 * PHP red-green proof for TESTEB-14: removing the last position turns the cart
 * into an empty state.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/cart-empty-on-remove-last-item-redgreen-test.php
 *
 * The test builds broken copies of the WooCommerce cart integration:
 *
 * 1. the position is not removed from WC()->cart;
 * 2. an emptied cart is not detected, so persisted snapshots survive;
 * 3. the guest snapshot and cookie are not cleared;
 * 4. the customer cart snapshot is not cleared.
 *
 * The focused empty-on-remove test must fail against every broken copy and pass
 * against the real module.
 *
 * @package Velotok_Core
 */

$root        = dirname( __DIR__ );
$module_path = $root . '/includes/cart-woocommerce.php';
$focused     = __DIR__ . '/cart-empty-on-remove-last-item-test.php';

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найдена интеграция корзины с WooCommerce.' . PHP_EOL );
	exit( 1 );
}

$source = file_get_contents( $module_path );

if ( false === $source || '' === $source ) {
	fwrite( STDERR, 'FAIL: Не удалось прочитать интеграцию корзины.' . PHP_EOL );
	exit( 1 );
}

$remove_source    = "\t\$cart->remove_cart_item( \$key );";
$empty_source     = "\tif ( empty( velotok_core_wc_cart_items() ) ) {";
$guest_source     = "\t\tvelotok_core_wc_cart_clear_guest_snapshot();";
$user_source      = "\t\tvelotok_core_wc_cart_clear_user_snapshot();";

$variants = array(
	'no-remove'           => str_replace(
		$remove_source,
		"\t// the last position is left in the cart",
		$source
	),
	'no-empty-detection'  => str_replace(
		$empty_source,
		"\tif ( false ) {",
		$source
	),
	'keeps-guest-snapshot' => str_replace(
		$guest_source,
		"\t\t// the guest snapshot is kept",
		$source
	),
	'keeps-user-snapshot' => str_replace(
		$user_source,
		"\t\t// the customer snapshot is kept",
		$source
	),
);

$tmp_dir = sys_get_temp_dir() . '/velotok-testeb-14-' . bin2hex( random_bytes( 6 ) );

if ( ! mkdir( $tmp_dir, 0700, true ) && ! is_dir( $tmp_dir ) ) {
	fwrite( STDERR, 'FAIL: Не удалось создать временный каталог для red-green проверки.' . PHP_EOL );
	exit( 1 );
}

/**
 * Run the focused empty-on-remove test against a cart integration module.
 *
 * @param string $test_path   Test file.
 * @param string $module_file Module path or empty string for the real module.
 * @return array{status:int,output:string}
 */
function velotok_testeb_14_run_php( $test_path, $module_file ) {
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

	$broken = velotok_testeb_14_run_php( $focused, $broken_path );

	if ( 0 === $broken['status'] ) {
		$failures[] = 'Фокусный тест прошёл на сломанной версии «' . $name . '», хотя должен падать.';
	}
}

$real = velotok_testeb_14_run_php( $focused, '' );

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

fwrite( STDOUT, 'PASS: TESTEB-14 падает без пустого состояния после удаления последней позиции и проходит с ним.' . PHP_EOL );
exit( 0 );
