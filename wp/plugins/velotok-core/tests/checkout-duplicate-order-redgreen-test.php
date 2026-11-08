<?php
/**
 * PHP red-green proof for TESTEB-26: repeated submission creates no duplicate.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/checkout-duplicate-order-redgreen-test.php
 *
 * The test builds broken copies of the checkout module:
 *
 * 1. the idempotency section no longer declares TESTEB-26;
 * 2. the existing-order guard is bypassed;
 * 3. the persisted order is no longer looked up through a WooCommerce meta query.
 *
 * The focused duplicate test must fail against every broken copy and pass
 * against the real module. The mandatory checkout contract test must keep
 * passing with the real module.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$module_path  = dirname( __DIR__ ) . '/includes/checkout-order.php';
$focused_test = __DIR__ . '/checkout-duplicate-order-test.php';
$contract_test = __DIR__ . '/checkout-order-contract-test.php';

if ( ! is_readable( $module_path ) || ! is_readable( $focused_test ) || ! is_readable( $contract_test ) ) {
	fwrite( STDERR, 'FAIL: Не найдены модуль или тесты защиты от дубля заказа.' . PHP_EOL );
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
function velotok_checkout_duplicate_run( $test_path, $module ) {
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
 * Assert a mutation makes the focused duplicate test red.
 *
 * @param string $mutated_source Mutated module source.
 * @param string $label          Mutation label.
 * @return void
 */
function velotok_checkout_duplicate_red( $mutated_source, $label ) {
	global $focused_test;

	$path = tempnam( sys_get_temp_dir(), 'velotok-checkout-duplicate-' );

	if ( false === $path ) {
		fwrite( STDERR, 'FAIL: Не удалось создать временный модуль.' . PHP_EOL );
		exit( 1 );
	}

	$path .= '.php';
	file_put_contents( $path, $mutated_source );
	$result = velotok_checkout_duplicate_run( $focused_test, $path );
	@unlink( $path );

	if ( 0 === $result['status'] ) {
		fwrite( STDERR, 'FAIL: Тест дубля заказа не падает без ' . $label . '.' . PHP_EOL );
		exit( 1 );
	}
}

$green = velotok_checkout_duplicate_run( $focused_test, $module_path );

if ( 0 !== $green['status'] ) {
	fwrite( STDERR, 'FAIL: Тест дубля заказа должен проходить с реальным модулем.' . PHP_EOL );
	fwrite( STDERR, $green['stdout'] . $green['stderr'] );
	exit( 1 );
}

$meta_query_block = "\t\t\t\$meta_query = array(\n"
	. "\t\t\t\tarray(\n"
	. "\t\t\t\t\t'key'     => \$contract['idempotency']['meta'],\n"
	. "\t\t\t\t\t'value'   => \$key,\n"
	. "\t\t\t\t\t'compare' => '=',\n"
	. "\t\t\t\t),\n"
	. "\t\t\t);";

$mutations = array(
	'marking TESTEB-26' => str_replace(
		array(
			"array( 'TESTEB-23', 'TESTEB-24', 'TESTEB-25', 'TESTEB-26' )",
			"'requirement'       => 'TESTEB-26',",
		),
		array(
			"array( 'TESTEB-23', 'TESTEB-24', 'TESTEB-25' )",
			"'requirement'       => 'TESTEB-23',",
		),
		$source
	),
	'duplicate guard' => str_replace(
		'if ( null !== $existing ) {',
		'if ( false && null !== $existing ) {',
		$source
	),
	'meta query lookup' => str_replace(
		$meta_query_block,
		"\t\t\t\$meta_query = array();",
		$source
	),
);

foreach ( $mutations as $label => $mutated ) {
	if ( $mutated === $source ) {
		fwrite( STDERR, 'FAIL: Мутация не изменила исходный модуль: ' . $label . '.' . PHP_EOL );
		exit( 1 );
	}

	velotok_checkout_duplicate_red( $mutated, $label );
}

$contract_green = velotok_checkout_duplicate_run( $contract_test, $module_path );

if ( 0 !== $contract_green['status'] ) {
	fwrite( STDERR, 'FAIL: Обязательный контракт оформления должен проходить с реальным модулем.' . PHP_EOL );
	fwrite( STDERR, $contract_green['stdout'] . $contract_green['stderr'] );
	exit( 1 );
}

fwrite( STDOUT, 'PASS: TESTEB-26 падает без защиты от повторной отправки и проходит с ней.' . PHP_EOL );
exit( 0 );
