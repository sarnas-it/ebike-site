<?php
/**
 * PHP red-green proof for TESTEB-11: cart totals include discounts and
 * shipping.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/cart-totals-discounts-delivery-redgreen-test.php
 *
 * The test builds broken copies of the WooCommerce cart integration:
 *
 * 1. WooCommerce discounts are ignored in the totals;
 * 2. WooCommerce shipping is ignored in the totals;
 * 3. the grand total ignores the discount and shipping adjustments.
 *
 * The focused totals test must fail against every broken copy and pass against
 * the real module.
 *
 * @package Velotok_Core
 */

$root        = dirname( __DIR__ );
$module_path = $root . '/includes/cart-woocommerce.php';
$focused     = __DIR__ . '/cart-totals-discounts-delivery-test.php';

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найдена интеграция корзины с WooCommerce.' . PHP_EOL );
	exit( 1 );
}

$source = file_get_contents( $module_path );

if ( false === $source || '' === $source ) {
	fwrite( STDERR, 'FAIL: Не удалось прочитать интеграцию корзины.' . PHP_EOL );
	exit( 1 );
}

$discount_source = "\t\$discount_total = velotok_core_wc_cart_number( \$cart, array( 'get_discount_total' ), 0.0 )\n\t\t+ velotok_core_wc_cart_number( \$cart, array( 'get_discount_tax' ), 0.0 );";
$shipping_source = "\t\$shipping_total = velotok_core_wc_cart_number( \$cart, array( 'get_shipping_total' ), 0.0 )\n\t\t+ velotok_core_wc_cart_number( \$cart, array( 'get_shipping_tax' ), 0.0 );";
$total_source    = "\t\$total          = velotok_core_wc_cart_number( \$cart, array( 'get_total' ), \$subtotal - \$discount_total + \$shipping_total );";

$variants = array(
	'no-discount' => str_replace(
		$discount_source,
		"\t\$discount_total = 0.0;",
		$source
	),
	'no-shipping' => str_replace(
		$shipping_source,
		"\t\$shipping_total = 0.0;",
		$source
	),
	'total-ignores-adjustments' => str_replace(
		$total_source,
		"\t\$total          = \$subtotal;",
		$source
	),
);

$tmp_dir = sys_get_temp_dir() . '/velotok-testeb-11-' . bin2hex( random_bytes( 6 ) );

if ( ! mkdir( $tmp_dir, 0700, true ) && ! is_dir( $tmp_dir ) ) {
	fwrite( STDERR, 'FAIL: Не удалось создать временный каталог для red-green проверки.' . PHP_EOL );
	exit( 1 );
}

/**
 * Run the focused totals test against a cart integration module.
 *
 * @param string $test_path   Test file.
 * @param string $module_file Module path or empty string for the real module.
 * @return array{status:int,output:string}
 */
function velotok_testeb_11_run_php( $test_path, $module_file ) {
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

	$broken = velotok_testeb_11_run_php( $focused, $broken_path );

	if ( 0 === $broken['status'] ) {
		$failures[] = 'Фокусный тест прошёл на сломанной версии «' . $name . '», хотя должен падать.';
	}
}

$real = velotok_testeb_11_run_php( $focused, '' );

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

fwrite( STDOUT, 'PASS: TESTEB-11 падает без учёта скидок и доставки в итогах корзины и проходит с ним.' . PHP_EOL );
exit( 0 );
