<?php
/**
 * PHP red-green proof for TESTEB-24: checkout requires authorization.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/checkout-auth-required-redgreen-test.php
 *
 * The test builds broken copies of the checkout module:
 *
 * 1. the authorization check always reports a logged-in customer;
 * 2. WooCommerce is not forced to require a logged-in customer.
 *
 * The focused authorization test must fail against both broken copies and pass
 * against the real module. The mandatory checkout contract test must also fail
 * when the authorization check is bypassed and pass on the real module.
 *
 * @package Velotok_Core
 */

$root         = dirname( __DIR__ );
$modulePath   = $root . '/includes/checkout-order.php';
$focusedTest  = __DIR__ . '/checkout-auth-required-test.php';
$contractTest = __DIR__ . '/checkout-order-contract-test.php';

if ( ! is_readable( $modulePath ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль оформления заказа через WooCommerce Checkout.' . PHP_EOL );
	exit( 1 );
}

$source = file_get_contents( $modulePath );

if ( false === $source || '' === $source ) {
	fwrite( STDERR, 'FAIL: Не удалось прочитать модуль оформления заказа.' . PHP_EOL );
	exit( 1 );
}

$authLine   = "return velotok_core_checkout_bool( \$context['is_user_logged_in'] );";
$forceLogin = "function velotok_core_checkout_force_login( \$required = true ) {\n\t\treturn true;";
$forceGuest = "function velotok_core_checkout_force_login( \$required = true ) {\n\t\treturn false;";

$variants = array(
	'bypass-authorization' => array(
		'source'  => str_replace( $authLine, 'return true;', $source ),
		'contract' => true,
	),
	'allow-guest-checkout' => array(
		'source'  => str_replace( $forceLogin, $forceGuest, $source ),
		'contract' => false,
	),
);

$tmpDir = sys_get_temp_dir() . '/velotok-testeb-24-' . bin2hex( random_bytes( 6 ) );

if ( ! mkdir( $tmpDir, 0700, true ) && ! is_dir( $tmpDir ) ) {
	fwrite( STDERR, 'FAIL: Не удалось создать временный каталог для red-green проверки.' . PHP_EOL );
	exit( 1 );
}

/**
 * Run a PHP test against a checkout module override.
 *
 * @param string $testPath Test file.
 * @param string $module   Module path or empty string for the real module.
 * @return array{status:int,output:string}
 */
function velotok_testeb_24_run_php( $testPath, $module ) {
	$previous = getenv( 'VELOTOK_CHECKOUT_ORDER_STUB' );
	$module   = (string) $module;

	if ( '' === $module ) {
		putenv( 'VELOTOK_CHECKOUT_ORDER_STUB' );
	} else {
		putenv( 'VELOTOK_CHECKOUT_ORDER_STUB=' . $module );
	}

	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $testPath ) . ' 2>&1';
	$output  = array();
	$status  = 0;

	exec( $command, $output, $status );

	if ( false === $previous ) {
		putenv( 'VELOTOK_CHECKOUT_ORDER_STUB' );
	} else {
		putenv( 'VELOTOK_CHECKOUT_ORDER_STUB=' . $previous );
	}

	return array(
		'status' => (int) $status,
		'output' => implode( PHP_EOL, $output ),
	);
}

$failures = array();

foreach ( $variants as $name => $variant ) {
	if ( $variant['source'] === $source ) {
		$failures[] = 'Не удалось построить сломанную версию модуля: ' . $name . '.';
		continue;
	}

	$brokenPath = $tmpDir . '/checkout-order-' . $name . '.php';
	file_put_contents( $brokenPath, $variant['source'] );

	$broken = velotok_testeb_24_run_php( $focusedTest, $brokenPath );

	if ( 0 === $broken['status'] ) {
		$failures[] = 'Фокусный тест прошёл на сломанной версии «' . $name . '», хотя должен падать.';
	}

	if ( $variant['contract'] ) {
		$brokenContract = velotok_testeb_24_run_php( $contractTest, $brokenPath );

		if ( 0 === $brokenContract['status'] ) {
			$failures[] = 'Контрактный тест прошёл без проверки авторизации, хотя должен падать.';
		}
	}
}

$real = velotok_testeb_24_run_php( $focusedTest, '' );

if ( 0 !== $real['status'] ) {
	$failures[] = 'Фокусный тест должен проходить на настоящем модуле, но завершился с кодом ' . $real['status'] . ':' . PHP_EOL . $real['output'];
}

$realContract = velotok_testeb_24_run_php( $contractTest, '' );

if ( 0 !== $realContract['status'] ) {
	$failures[] = 'Контрактный тест должен проходить на настоящем модуле, но завершился с кодом ' . $realContract['status'] . ':' . PHP_EOL . $realContract['output'];
}

foreach ( glob( $tmpDir . '/*' ) as $file ) {
	@unlink( $file );
}

@rmdir( $tmpDir );

if ( array() !== $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

fwrite( STDOUT, 'PASS: TESTEB-24 падает без требования авторизации и проходит с ним.' . PHP_EOL );
exit( 0 );
