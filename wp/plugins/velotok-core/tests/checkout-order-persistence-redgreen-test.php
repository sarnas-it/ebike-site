<?php
/**
 * PHP red-green proof for TESTEB-25: the order keeps its checkout data.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/checkout-order-persistence-redgreen-test.php
 *
 * The test builds broken copies of the checkout module:
 *
 * 1. the order section no longer declares TESTEB-25;
 * 2. the delivery method is not written into the order data;
 * 3. the order composition cannot be read through the standard order getters.
 *
 * The focused persistence test must fail against every broken copy and pass
 * against the real module. The mandatory checkout contract test must keep
 * passing, so the change does not break the existing checkout requirement.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$module_path = getenv( 'VELOTOK_CHECKOUT_ORDER_STUB' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__ ) . '/includes/checkout-order.php';
}

$focused_test = __DIR__ . '/checkout-order-persistence-test.php';
$contract_test = __DIR__ . '/checkout-order-contract-test.php';

if ( ! is_readable( $module_path ) || ! is_readable( $focused_test ) || ! is_readable( $contract_test ) ) {
	fwrite( STDERR, 'FAIL: Не найдены модуль или тесты сохранения заказа.' . PHP_EOL );
	exit( 1 );
}

$source = file_get_contents( $module_path );

/**
 * Run a checkout test against a module copy.
 *
 * @param string $test_path Test path.
 * @param string $module    Module path.
 * @return array{status:int,stdout:string,stderr:string}
 */
function velotok_checkout_persistence_run( $test_path, $module ) {
	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $test_path );
	$env     = array_merge(
		$_ENV,
		array(
			'PATH'                          => getenv( 'PATH' ),
			'VELOTOK_CHECKOUT_ORDER_STUB'   => $module,
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
 * Assert a mutation makes the focused test red.
 *
 * @param string $mutated_source Mutated module source.
 * @param string $label          Mutation label.
 * @param string $test_path      Test path.
 * @return void
 */
function velotok_checkout_persistence_red( $mutated_source, $label, $test_path ) {
	$path = tempnam( sys_get_temp_dir(), 'velotok-checkout-persistence-' );

	if ( false === $path ) {
		fwrite( STDERR, 'FAIL: Не удалось создать временный модуль.' . PHP_EOL );
		exit( 1 );
	}

	$path .= '.php';
	file_put_contents( $path, $mutated_source );
	$result = velotok_checkout_persistence_run( $test_path, $path );
	@unlink( $path );

	if ( 0 === $result['status'] ) {
		fwrite( STDERR, 'FAIL: Тест сохранения заказа не падает без ' . $label . '.' . PHP_EOL );
		exit( 1 );
	}
}

$green = velotok_checkout_persistence_run( $focused_test, $module_path );

if ( 0 !== $green['status'] ) {
	fwrite( STDERR, 'FAIL: Тест сохранения заказа должен проходить с реальным модулем.' . PHP_EOL );
	fwrite( STDERR, $green['stdout'] . $green['stderr'] );
	exit( 1 );
}

$mutations = array(
	'marking TESTEB-25' => str_replace(
		array(
			"array( 'TESTEB-23', 'TESTEB-24', 'TESTEB-25', 'TESTEB-26' )",
			"'requirement'       => 'TESTEB-25',",
		),
		array(
			"array( 'TESTEB-23', 'TESTEB-24', 'TESTEB-26' )",
			"'requirement'       => 'TESTEB-23',",
		),
		$source
	),
	'delivery persistence' => str_replace(
		"\$order_data[ \$contract['order']['delivery_field'] ] = \$delivery;",
		"\$order_data[ \$contract['order']['delivery_field'] ] = '';",
		$source
	),
	'standard composition getter' => str_replace(
		'return is_array( $items ) ? $items : array();',
		'return array();',
		$source
	),
);

foreach ( $mutations as $label => $mutated ) {
	if ( $mutated === $source ) {
		fwrite( STDERR, 'FAIL: Мутация не изменила исходный модуль: ' . $label . '.' . PHP_EOL );
		exit( 1 );
	}

	velotok_checkout_persistence_red( $mutated, $label, $focused_test );
}

$contract_green = velotok_checkout_persistence_run( $contract_test, $module_path );

if ( 0 !== $contract_green['status'] ) {
	fwrite( STDERR, 'FAIL: Обязательный контракт оформления должен проходить с реальным модулем.' . PHP_EOL );
	fwrite( STDERR, $contract_green['stdout'] . $contract_green['stderr'] );
	exit( 1 );
}

fwrite( STDOUT, 'PASS: TESTEB-25 падает без сохранения состава, доставки, оплаты и комментария и проходит с ним.' . PHP_EOL );
exit( 0 );
