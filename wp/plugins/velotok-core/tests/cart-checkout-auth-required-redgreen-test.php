<?php
/**
 * PHP red-green proof for TESTEB-13: a purchase requires authorization.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/cart-checkout-auth-required-redgreen-test.php
 *
 * The test builds broken copies of the WooCommerce cart integration:
 *
 * 1. the contract no longer requires authorization;
 * 2. the authorization requirement is always reported as disabled;
 * 3. the checkout status lets a guest through;
 * 4. the checkout process validation does not block a guest.
 *
 * The focused authorization test must fail against every broken copy and pass
 * against the real module.
 *
 * @package Velotok_Core
 */

$root        = dirname( __DIR__ );
$module_path = $root . '/includes/cart-woocommerce.php';
$focused     = __DIR__ . '/cart-checkout-auth-required-test.php';

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найдена интеграция корзины с WooCommerce.' . PHP_EOL );
	exit( 1 );
}

$source = file_get_contents( $module_path );

if ( false === $source || '' === $source ) {
	fwrite( STDERR, 'FAIL: Не удалось прочитать интеграцию корзины.' . PHP_EOL );
	exit( 1 );
}

$contract_source   = "\t\t\t'requires_auth'       => true,";
$required_source   = "\t\$required = ! empty( \$contract['checkout']['requires_auth'] );";
$allowed_source    = "\t\$allowed   = ! \$required || \$logged_in;";
$validation_source = "\tif ( \$status['allowed'] ) {";

$variants = array(
	'contract-allows-guest' => str_replace(
		$contract_source,
		"\t\t\t'requires_auth'       => false,",
		$source
	),
	'never-required' => str_replace(
		$required_source,
		"\t\$required = false;",
		$source
	),
	'guest-allowed' => str_replace(
		$allowed_source,
		"\t\$allowed   = true;",
		$source
	),
	'validation-bypassed' => str_replace(
		$validation_source,
		"\tif ( true ) {",
		$source
	),
);

$tmp_dir = sys_get_temp_dir() . '/velotok-testeb-13-' . bin2hex( random_bytes( 6 ) );

if ( ! mkdir( $tmp_dir, 0700, true ) && ! is_dir( $tmp_dir ) ) {
	fwrite( STDERR, 'FAIL: Не удалось создать временный каталог для red-green проверки.' . PHP_EOL );
	exit( 1 );
}

/**
 * Run the focused authorization test against a cart integration module.
 *
 * @param string $test_path   Test file.
 * @param string $module_file Module path or empty string for the real module.
 * @return array{status:int,output:string}
 */
function velotok_testeb_13_run_php( $test_path, $module_file ) {
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

	$broken = velotok_testeb_13_run_php( $focused, $broken_path );

	if ( 0 === $broken['status'] ) {
		$failures[] = 'Фокусный тест прошёл на сломанной версии «' . $name . '», хотя должен падать.';
	}
}

$real = velotok_testeb_13_run_php( $focused, '' );

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

fwrite( STDOUT, 'PASS: TESTEB-13 падает без требования авторизации для покупки и проходит с ним.' . PHP_EOL );
exit( 0 );
