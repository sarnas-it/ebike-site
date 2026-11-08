<?php
/**
 * PHP red-green proof for TESTEB-27: checkout is unavailable with an empty cart.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/checkout-empty-cart-redgreen-test.php
 *
 * The test builds broken copies of the checkout module:
 *
 * 1. the empty-cart guard is bypassed;
 * 2. the contract no longer marks TESTEB-27.
 *
 * The focused empty-cart test must fail against every broken copy and pass
 * against the real module. The mandatory checkout contract test must also pass
 * with the real module.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$module_path   = dirname( __DIR__ ) . '/includes/checkout-order.php';
$focused_test  = __DIR__ . '/checkout-empty-cart-test.php';
$contract_test = __DIR__ . '/checkout-order-contract-test.php';

if ( ! is_readable( $module_path ) || ! is_readable( $focused_test ) || ! is_readable( $contract_test ) ) {
	fwrite( STDERR, 'FAIL: Не найдены модуль или тесты пустой корзины при оформлении заказа.' . PHP_EOL );
	exit( 1 );
}

$source = file_get_contents( $module_path );

if ( false === $source || '' === $source ) {
	fwrite( STDERR, 'FAIL: Не удалось прочитать модуль оформления заказа.' . PHP_EOL );
	exit( 1 );
}

/**
 * Run a checkout test against a module copy.
 *
 * @param string $test_path Test path.
 * @param string $module    Module path.
 * @return array{status:int,stdout:string,stderr:string}
 */
function velotok_checkout_empty_run( $test_path, $module ) {
	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $test_path );
	$env     = array_merge(
		$_ENV,
		array(
			'PATH'                        => getenv( 'PATH' ),
			'VELOTOK_CHECKOUT_ORDER_STUB' => $module,
		)
	);
	$process = proc_open(
		$command,
		array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$pipes,
		null,
		$env
	);

	if ( ! is_resource( $process ) ) {
		return array( 'status' => 1, 'stdout' => '', 'stderr' => 'Не удалось запустить PHP.' );
	}

	fclose( $pipes[0] );
	$stdout = stream_get_contents( $pipes[1] );
	$stderr = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );

	return array(
		'status' => proc_close( $process ),
		'stdout' => (string) $stdout,
		'stderr' => (string) $stderr,
	);
}

/**
 * Assert a mutation makes the focused empty-cart test red.
 *
 * @param string $mutated_source Mutated module source.
 * @param string $label          Mutation label.
 * @return void
 */
function velotok_checkout_empty_red( $mutated_source, $label ) {
	global $focused_test;

	$path = tempnam( sys_get_temp_dir(), 'velotok-checkout-empty-' );

	if ( false === $path ) {
		fwrite( STDERR, 'FAIL: Не удалось создать временный модуль.' . PHP_EOL );
		exit( 1 );
	}

	$path .= '.php';
	file_put_contents( $path, $mutated_source );
	$result = velotok_checkout_empty_run( $focused_test, $path );
	@unlink( $path );

	if ( 0 === $result['status'] ) {
		fwrite( STDERR, 'FAIL: Тест пустой корзины не падает без ' . $label . '.' . PHP_EOL );
		exit( 1 );
	}
}

$green = velotok_checkout_empty_run( $focused_test, $module_path );

if ( 0 !== $green['status'] ) {
	fwrite( STDERR, 'FAIL: Тест пустой корзины должен проходить с реальным модулем.' . PHP_EOL );
	fwrite( STDERR, $green['stdout'] . $green['stderr'] );
	exit( 1 );
}

$mutations = array(
	'empty-cart guard' => str_replace(
		'return empty( velotok_core_checkout_cart_items( $context ) );',
		'return false;',
		$source
	),
	'marking TESTEB-27' => str_replace(
		array(
			"array( 'TESTEB-23', 'TESTEB-24', 'TESTEB-25', 'TESTEB-26', 'TESTEB-27' )",
			"'requirement' => 'TESTEB-27',",
		),
		array(
			"array( 'TESTEB-23', 'TESTEB-24', 'TESTEB-25', 'TESTEB-26' )",
			"'requirement' => 'TESTEB-23',",
		),
		$source
	),
);

foreach ( $mutations as $label => $mutated ) {
	if ( $mutated === $source ) {
		fwrite( STDERR, 'FAIL: Мутация не изменила исходный модуль: ' . $label . '.' . PHP_EOL );
		exit( 1 );
	}

	velotok_checkout_empty_red( $mutated, $label );
}

$contract_green = velotok_checkout_empty_run( $contract_test, $module_path );

if ( 0 !== $contract_green['status'] ) {
	fwrite( STDERR, 'FAIL: Обязательный контракт оформления должен проходить с реальным модулем.' . PHP_EOL );
	fwrite( STDERR, $contract_green['stdout'] . $contract_green['stderr'] );
	exit( 1 );
}

fwrite( STDOUT, 'PASS: TESTEB-27 падает без запрета оформления при пустой корзине и проходит с ним.' . PHP_EOL );
exit( 0 );
