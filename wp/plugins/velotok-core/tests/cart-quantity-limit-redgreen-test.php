<?php
/**
 * PHP red-green proof for TESTEB-12: the quantity per cart position is
 * limited.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/cart-quantity-limit-redgreen-test.php
 *
 * The test builds broken copies of the WooCommerce cart integration:
 *
 * 1. adding does not cap the requested quantity;
 * 2. updating a position does not cap the new quantity;
 * 3. the product's own purchase limit is ignored;
 * 4. the add-to-cart quantity filter does not clamp the quantity;
 * 5. add-to-cart validation does not reject quantities above the limit.
 *
 * The focused quantity test must fail against every broken copy and pass
 * against the real module.
 *
 * @package Velotok_Core
 */

$root        = dirname( __DIR__ );
$module_path = $root . '/includes/cart-woocommerce.php';
$focused     = __DIR__ . '/cart-quantity-limit-test.php';

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найдена интеграция корзины с WooCommerce.' . PHP_EOL );
	exit( 1 );
}

$source = file_get_contents( $module_path );

if ( false === $source || '' === $source ) {
	fwrite( STDERR, 'FAIL: Не удалось прочитать интеграцию корзины.' . PHP_EOL );
	exit( 1 );
}

$add_cap_source        = "\t\$requested = min( \$max, \$existing + \$quantity );";
$update_cap_source     = "\t\$quantity = min( \$max, \$quantity );";
$product_limit_source  = "\t\t\t\$max = min( \$max, \$product_max );";
$filter_cap_source     = "\treturn min( \$quantity, \$remaining );";
$validation_cap_source = "\tif ( (int) \$quantity > \$max || \$requested > \$max ) {";

$variants = array(
	'no-add-cap'        => str_replace(
		$add_cap_source,
		"\t\$requested = \$existing + \$quantity;",
		$source
	),
	'no-update-cap'     => str_replace(
		$update_cap_source,
		"\t\$quantity = \$quantity;",
		$source
	),
	'no-product-limit'  => str_replace(
		$product_limit_source,
		"\t\t\t\$max = \$max;",
		$source
	),
	'no-filter-cap'     => str_replace(
		$filter_cap_source,
		"\treturn \$quantity;",
		$source
	),
	'no-validation-cap' => str_replace(
		$validation_cap_source,
		"\tif ( false ) {",
		$source
	),
);

$tmp_dir = sys_get_temp_dir() . '/velotok-testeb-12-' . bin2hex( random_bytes( 6 ) );

if ( ! mkdir( $tmp_dir, 0700, true ) && ! is_dir( $tmp_dir ) ) {
	fwrite( STDERR, 'FAIL: Не удалось создать временный каталог для red-green проверки.' . PHP_EOL );
	exit( 1 );
}

/**
 * Run the focused quantity test against a cart integration module.
 *
 * @param string $test_path   Test file.
 * @param string $module_file Module path or empty string for the real module.
 * @return array{status:int,output:string}
 */
function velotok_testeb_12_run_php( $test_path, $module_file ) {
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

	$broken = velotok_testeb_12_run_php( $focused, $broken_path );

	if ( 0 === $broken['status'] ) {
		$failures[] = 'Фокусный тест прошёл на сломанной версии «' . $name . '», хотя должен падать.';
	}
}

$real = velotok_testeb_12_run_php( $focused, '' );

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

fwrite( STDOUT, 'PASS: TESTEB-12 падает без ограничения количества на позицию и проходит с ним.' . PHP_EOL );
exit( 0 );
