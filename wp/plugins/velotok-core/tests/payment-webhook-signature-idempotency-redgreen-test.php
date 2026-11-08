<?php
/**
 * PHP red-green proof for TESTEB-45.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/payment-webhook-signature-idempotency-redgreen-test.php
 *
 * The test builds deliberately broken copies of includes/payment-gateway.php and
 * runs the focused webhook test against them:
 *
 * 1. the webhook signature check is skipped;
 * 2. duplicate webhook events are not detected.
 *
 * Both broken copies must make the focused test fail. The unmodified
 * implementation must make it pass. This proves the test rejects the
 * requirement being removed.
 *
 * @package Velotok_Core
 */

$root             = dirname( __DIR__ );
$implementation   = $root . '/includes/payment-gateway.php';
$focusedTest      = __DIR__ . '/payment-webhook-signature-idempotency-test.php';

if ( ! is_readable( $implementation ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл реализации платёжного шлюза.' . PHP_EOL );
	exit( 1 );
}

$source = file_get_contents( $implementation );

if ( false === $source || '' === $source ) {
	fwrite( STDERR, 'FAIL: Не удалось прочитать реализацию платёжного шлюза.' . PHP_EOL );
	exit( 1 );
}

$signatureCheck = 'if ( ! velotok_core_payment_webhook_verify( $body, $signature ) ) {';
$duplicateCheck = 'if ( velotok_core_payment_webhook_is_duplicate( $event_id ) ) {';

$variants = array(
	'no-signature-check' => str_replace( $signatureCheck, 'if ( false ) {', $source ),
	'no-idempotency'     => str_replace( $duplicateCheck, 'if ( false ) {', $source ),
);

$tmpDir = sys_get_temp_dir() . '/velotok-testeb-45-' . bin2hex( random_bytes( 6 ) );

if ( ! mkdir( $tmpDir, 0700, true ) && ! is_dir( $tmpDir ) ) {
	fwrite( STDERR, 'FAIL: Не удалось создать временный каталог для red-green проверки.' . PHP_EOL );
	exit( 1 );
}

/**
 * Run a PHP test file and return its exit code and combined output.
 *
 * @param string $testPath Test file.
 * @param string $envName  Environment variable name.
 * @param string $envValue Environment variable value or empty string to unset.
 * @return array{status:int,output:string}
 */
function velotok_testeb_45_run_php( $testPath, $envName, $envValue ) {
	$previous = getenv( $envName );
	$envValue = (string) $envValue;

	if ( '' === $envValue ) {
		putenv( $envName );
	} else {
		putenv( $envName . '=' . $envValue );
	}

	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $testPath ) . ' 2>&1';
	$output  = array();
	$status  = 0;

	exec( $command, $output, $status );

	if ( false === $previous ) {
		putenv( $envName );
	} else {
		putenv( $envName . '=' . $previous );
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

	$brokenPath = $tmpDir . '/payment-gateway-' . $name . '.php';
	file_put_contents( $brokenPath, $variant );

	$broken = velotok_testeb_45_run_php( $focusedTest, 'VELOTOK_PAYMENT_GATEWAY_FILE', $brokenPath );

	if ( 0 === $broken['status'] ) {
		$failures[] = 'Тест прошёл на сломанной реализации «' . $name . '», хотя должен падать.';
	}
}

$real = velotok_testeb_45_run_php( $focusedTest, 'VELOTOK_PAYMENT_GATEWAY_FILE', '' );

if ( 0 !== $real['status'] ) {
	$failures[] = 'Тест должен проходить на настоящей реализации, но завершился с кодом ' . $real['status'] . ':' . PHP_EOL . $real['output'];
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

fwrite( STDOUT, 'PASS: TESTEB-45 падает без проверки подписи или идемпотентности webhook и проходит с ними.' . PHP_EOL );
exit( 0 );
