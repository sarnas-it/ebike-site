<?php

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$base = dirname( __DIR__ );

require_once $base . '/includes/class-pvs-contract.php';
require_once $base . '/includes/class-pvs-scanner.php';

$failures = array();

function pvs_all_plugins_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = pvs_contract();
$coverage = $contract['coverage'];

pvs_all_plugins_assert( true === $contract['all_plugins'], 'Контракт не требует проверки всех плагинов.' );
pvs_all_plugins_assert( true === $coverage['includes_active'], 'Активные плагины должны проверяться.' );
pvs_all_plugins_assert( true === $coverage['includes_inactive'], 'Неактивные плагины должны проверяться.' );
pvs_all_plugins_assert( true === $coverage['includes_mu_plugins'], 'mu-плагины должны проверяться.' );
pvs_all_plugins_assert( true === $coverage['includes_dropins'], 'Drop-ins должны проверяться.' );
pvs_all_plugins_assert( 'all_plugins' === $coverage['scope'], 'Область проверки должна быть all_plugins.' );

$plugins = array(
	'active/active.php' => array( 'name' => 'Active', 'version' => '1.0.0', 'active' => true, 'slug' => 'active' ),
	'inactive/inactive.php' => array( 'name' => 'Inactive', 'version' => '1.0.0', 'active' => false, 'slug' => 'inactive' ),
	'mu.php' => array( 'name' => 'MU', 'version' => '1.0.0', 'active' => true, 'type' => 'mu-plugin', 'slug' => 'mu' ),
	'advanced-cache.php' => array( 'name' => 'Dropin', 'version' => '1.0.0', 'active' => true, 'type' => 'dropin', 'slug' => 'dropin' ),
);

$result = pvs_scan_all_plugins( $plugins );

pvs_all_plugins_assert( 4 === $result['plugins_scanned'], 'Должны быть проверены все четыре плагина.' );
pvs_all_plugins_assert( true === $result['all_plugins'], 'Результат не подтверждает проверку всех плагинов.' );
pvs_all_plugins_assert( true === $result['coverage']['includes_inactive'], 'Результат не подтверждает проверку неактивных плагинов.' );

$types = array();

foreach ( $result['plugins'] as $plugin ) {
	$types[] = $plugin['type'];
	pvs_all_plugins_assert( true === $plugin['scanned'], 'Плагин не отмечен как проверенный.' );
}

foreach ( array( 'plugin', 'mu-plugin', 'dropin' ) as $type ) {
	pvs_all_plugins_assert( in_array( $type, $types, true ), 'Не проверен тип плагина ' . $type . '.' );
}

$filtered = pvs_scan_all_plugins(
	$plugins,
	array(
		'include_inactive'  => false,
		'include_mu_plugins' => false,
		'include_dropins'    => false,
	)
);

pvs_all_plugins_assert( 1 === $filtered['plugins_scanned'], 'Опции фильтрации должны работать.' );

$wrapper = dirname( __DIR__, 2 ) . '/velotok-core/includes/class-pvs-contract.php';
pvs_all_plugins_assert( is_readable( $wrapper ), 'Нет совместимого файла контракта в velotok-core.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: pvs all plugins contract' . PHP_EOL;
