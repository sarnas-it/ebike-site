<?php
/**
 * PHP red-green proof for TESTEB-3.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/accounts-existing-email-redgreen-test.php
 *
 * The test builds broken copies of the in-memory contract and the WordPress
 * integration where the existing-email guard is disabled. The focused TESTEB-3
 * test must fail against both broken copies and pass against the real code.
 *
 * @package Velotok_Core
 */

$root             = dirname( __DIR__ );
$stubPath         = $root . '/includes/accounts.php';
$integrationPath  = $root . '/includes/accounts-wordpress.php';
$focusedTest      = __DIR__ . '/accounts-existing-email-login-test.php';
$contractTest     = __DIR__ . '/accounts-contract-test.php';

foreach ( array( $stubPath, $integrationPath ) as $sourcePath ) {
	if ( ! is_readable( $sourcePath ) ) {
		fwrite( STDERR, 'FAIL: Не найден файл аккаунтов: ' . $sourcePath . PHP_EOL );
		exit( 1 );
	}
}

$stubSource        = file_get_contents( $stubPath );
$integrationSource = file_get_contents( $integrationPath );

if ( false === $stubSource || '' === $stubSource || false === $integrationSource || '' === $integrationSource ) {
	fwrite( STDERR, 'FAIL: Не удалось прочитать исходники аккаунтов.' . PHP_EOL );
	exit( 1 );
}

$stubGuard = "if ( isset( \$state['users'][ \$key ] ) ) {";
$wpGuard   = 'if ( null !== $existing ) {';
$wpFallback = 'if ( null !== $existing || velotok_core_wp_is_existing_email_error( $user_id ) ) {';

$variants = array(
	'stub-duplicate' => array(
		'path'   => 'accounts.php',
		'env'    => 'VELOTOK_ACCOUNTS_STUB',
		'source' => str_replace( $stubGuard, 'if ( false ) {', $stubSource ),
		'tests'  => array( 'focused' => $focusedTest, 'contract' => $contractTest ),
	),
	'wp-duplicate' => array(
		'path'   => 'accounts-wordpress.php',
		'env'    => 'VELOTOK_ACCOUNTS_WP_INTEGRATION',
		'source' => str_replace(
			array( $wpGuard, $wpFallback ),
			array( 'if ( false ) {', 'if ( false ) {' ),
			$integrationSource
		),
		'tests'  => array( 'focused' => $focusedTest ),
	),
);

$tmpDir = sys_get_temp_dir() . '/velotok-testeb-3-' . bin2hex( random_bytes( 6 ) );

if ( ! mkdir( $tmpDir, 0700, true ) && ! is_dir( $tmpDir ) ) {
	fwrite( STDERR, 'FAIL: Не удалось создать временный каталог для red-green проверки.' . PHP_EOL );
	exit( 1 );
}

/**
 * Run a PHP test file with an optional environment override.
 *
 * @param string $testPath Test file.
 * @param string $envName  Environment variable name.
 * @param string $envValue Environment variable value or empty string to unset.
 * @return array{status:int,output:string}
 */
function velotok_testeb_3_run_php( $testPath, $envName, $envValue ) {
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
	if ( $variant['source'] === $stubSource || $variant['source'] === $integrationSource ) {
		$failures[] = 'Не удалось построить сломанную версию: ' . $name . '.';
		continue;
	}

	$brokenPath = $tmpDir . '/' . $variant['path'];
	file_put_contents( $brokenPath, $variant['source'] );

	foreach ( $variant['tests'] as $label => $testPath ) {
		$broken = velotok_testeb_3_run_php( $testPath, $variant['env'], $brokenPath );

		if ( 0 === $broken['status'] ) {
			$failures[] = 'Тест ' . $label . ' прошёл на сломанной версии «' . $name . '», хотя должен падать.';
		}
	}
}

putenv( 'VELOTOK_ACCOUNTS_STUB' );
putenv( 'VELOTOK_ACCOUNTS_WP_INTEGRATION' );

foreach ( array( 'focused' => $focusedTest, 'contract' => $contractTest ) as $label => $testPath ) {
	$real = velotok_testeb_3_run_php( $testPath, 'VELOTOK_ACCOUNTS_STUB', '' );

	if ( 0 !== $real['status'] ) {
		$failures[] = 'Тест ' . $label . ' должен проходить на настоящей реализации, но завершился с кодом ' . $real['status'] . ':' . PHP_EOL . $real['output'];
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

fwrite( STDOUT, 'PASS: TESTEB-3 падает без обработки существующего email и проходит с ней.' . PHP_EOL );
exit( 0 );
