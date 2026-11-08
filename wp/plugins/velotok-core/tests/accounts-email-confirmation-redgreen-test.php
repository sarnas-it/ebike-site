<?php
/**
 * PHP red-green proof for TESTEB-2.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/accounts-email-confirmation-redgreen-test.php
 *
 * The test builds two broken copies of the real WordPress integration and runs
 * the focused test and the mandatory contract test against them:
 *
 * 1. registration stores the account as already confirmed (status active);
 * 2. registration does not send the confirmation email.
 *
 * Both broken copies must make the tests fail. The unmodified integration must
 * make them pass. This proves the tests reject the requirement being removed.
 *
 * @package Velotok_Core
 */

$root            = dirname( __DIR__ );
$integrationPath = $root . '/includes/accounts-wordpress.php';
$focusedTest     = __DIR__ . '/accounts-email-confirmation-wp-test.php';
$contractTest    = __DIR__ . '/accounts-contract-test.php';

if ( ! is_readable( $integrationPath ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл WordPress-интеграции аккаунтов.' . PHP_EOL );
	exit( 1 );
}

$source = file_get_contents( $integrationPath );

if ( false === $source || '' === $source ) {
	fwrite( STDERR, 'FAIL: Не удалось прочитать WordPress-интеграцию аккаунтов.' . PHP_EOL );
	exit( 1 );
}

$confirmedLine = "update_user_meta( \$user_id, VELOTOK_ACCOUNT_CONFIRMED_META, '0' );";
$pendingLine   = "update_user_meta( \$user_id, VELOTOK_ACCOUNT_STATUS_META, 'pending' );";
$emailLine     = '$sent = velotok_core_wp_send_confirmation_email( $email, $token, $user_id );';

$variants = array(
	'auto-confirmed' => str_replace(
		array( $confirmedLine, $pendingLine ),
		array(
			"update_user_meta( \$user_id, VELOTOK_ACCOUNT_CONFIRMED_META, '1' );",
			"update_user_meta( \$user_id, VELOTOK_ACCOUNT_STATUS_META, 'active' );",
		),
		$source
	),
	'no-confirmation-email' => str_replace( $emailLine, '$sent = false;', $source ),
);

$tmpDir = sys_get_temp_dir() . '/velotok-testeb-2-' . bin2hex( random_bytes( 6 ) );

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
function velotok_testeb_2_run_php( $testPath, $envName, $envValue ) {
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
		$failures[] = 'Не удалось построить сломанную версию интеграции: ' . $name . '.';
		continue;
	}

	$brokenPath = $tmpDir . '/accounts-wordpress-' . $name . '.php';
	file_put_contents( $brokenPath, $variant );

	foreach ( array( 'focused' => $focusedTest, 'contract' => $contractTest ) as $label => $testPath ) {
		$broken = velotok_testeb_2_run_php( $testPath, 'VELOTOK_ACCOUNTS_WP_INTEGRATION', $brokenPath );

		if ( 0 === $broken['status'] ) {
			$failures[] = 'Тест ' . $label . ' прошёл на сломанной интеграции «' . $name . '», хотя должен падать.';
		}
	}
}

foreach ( array( 'focused' => $focusedTest, 'contract' => $contractTest ) as $label => $testPath ) {
	$real = velotok_testeb_2_run_php( $testPath, 'VELOTOK_ACCOUNTS_WP_INTEGRATION', '' );

	if ( 0 !== $real['status'] ) {
		$failures[] = 'Тест ' . $label . ' должен проходить на настоящей интеграции, но завершился с кодом ' . $real['status'] . ':' . PHP_EOL . $real['output'];
	}
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

fwrite( STDOUT, 'PASS: TESTEB-2 падает без обязательного подтверждения email и проходит с ним.' . PHP_EOL );
exit( 0 );
