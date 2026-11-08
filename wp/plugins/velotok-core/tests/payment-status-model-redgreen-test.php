<?php
/**
 * PHP red-green proof for TESTEB-48.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/payment-status-model-redgreen-test.php
 *
 * The test builds deliberately broken copies of includes/payment-gateway.php and
 * runs the focused payment status model test against them:
 *
 * 1. the fixed model grows an extra, non-contract status;
 * 2. a final status gains an outgoing transition;
 * 3. an arbitrary unknown status is accepted as valid.
 *
 * Every broken copy must make the focused test fail. The unmodified
 * implementation must make it pass. This proves the test rejects the fixed
 * status model being removed.
 *
 * @package Velotok_Core
 */

$root           = dirname( __DIR__ );
$implementation = $root . '/includes/payment-gateway.php';
$focused_test   = __DIR__ . '/payment-status-model-test.php';

if ( ! is_readable( $implementation ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл реализации платёжного шлюза.' . PHP_EOL );
	exit( 1 );
}

$source = file_get_contents( $implementation );

if ( false === $source || '' === $source ) {
	fwrite( STDERR, 'FAIL: Не удалось прочитать реализацию платёжного шлюза.' . PHP_EOL );
	exit( 1 );
}

$targets = array(
	'extra-status' => array(
		"\t\t'refunded'            => 'Возврат',",
		"\t\t'refunded'            => 'Возврат',\n\t\t'processing'          => 'Обработка',",
	),
	'mutable-final-status' => array(
		"\t\t'refunded'            => array(),",
		"\t\t'refunded'            => array( 'succeeded' ),",
	),
	'accept-any-status' => array(
		"\treturn isset( \$model[ velotok_core_payment_normalize_status( \$status ) ] );",
		"\treturn true;",
	),
);

$variants = array();

foreach ( $targets as $name => $target ) {
	$variants[ $name ] = str_replace( $target[0], $target[1], $source );
}

$tmp_dir = sys_get_temp_dir() . '/velotok-testeb-48-' . bin2hex( random_bytes( 6 ) );

if ( ! mkdir( $tmp_dir, 0700, true ) && ! is_dir( $tmp_dir ) ) {
	fwrite( STDERR, 'FAIL: Не удалось создать временный каталог для red-green проверки.' . PHP_EOL );
	exit( 1 );
}

/**
 * Run a PHP test file and return its exit code and combined output.
 *
 * @param string $test_path Test file.
 * @param string $env_name  Environment variable name.
 * @param string $env_value Environment variable value or empty string to unset.
 * @return array{status:int,output:string}
 */
function velotok_testeb_48_run_php( $test_path, $env_name, $env_value ) {
	$previous  = getenv( $env_name );
	$env_value = (string) $env_value;

	if ( '' === $env_value ) {
		putenv( $env_name );
	} else {
		putenv( $env_name . '=' . $env_value );
	}

	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $test_path ) . ' 2>&1';
	$output  = array();
	$status  = 0;

	exec( $command, $output, $status );

	if ( false === $previous ) {
		putenv( $env_name );
	} else {
		putenv( $env_name . '=' . $previous );
	}

	return array(
		'status' => (int) $status,
		'output' => implode( PHP_EOL, $output ),
	);
}

$failures = array();

foreach ( $variants as $name => $variant ) {
	if ( $variant === $source ) {
		$failures[] = 'Не удалось построить сломанную версию реализации: ' . $name . '.';
		continue;
	}

	$broken_path = $tmp_dir . '/payment-gateway-' . $name . '.php';
	file_put_contents( $broken_path, $variant );

	$broken = velotok_testeb_48_run_php( $focused_test, 'VELOTOK_PAYMENT_GATEWAY_FILE', $broken_path );

	if ( 0 === $broken['status'] ) {
		$failures[] = 'Тест прошёл на сломанной реализации «' . $name . '», хотя должен падать.';
	}
}

$real = velotok_testeb_48_run_php( $focused_test, 'VELOTOK_PAYMENT_GATEWAY_FILE', '' );

if ( 0 !== $real['status'] ) {
	$failures[] = 'Тест должен проходить на настоящей реализации, но завершился с кодом ' . $real['status'] . ':' . PHP_EOL . $real['output'];
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

fwrite( STDOUT, 'PASS: TESTEB-48 падает без фиксированной модели статусов оплаты и проходит с ней.' . PHP_EOL );
exit( 0 );
