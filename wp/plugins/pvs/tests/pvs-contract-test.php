<?php

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$base = dirname( __DIR__ );
$files = array(
	$base . '/includes/class-pvs-contract.php',
	$base . '/includes/class-pvs-scanner.php',
	$base . '/includes/class-pvs-admin.php',
);

foreach ( $files as $file ) {
	if ( ! is_readable( $file ) ) {
		fwrite( STDERR, 'FAIL: Не найден файл ' . $file . PHP_EOL );
		exit( 1 );
	}

	require_once $file;
}

$failures = array();

function pvs_test_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = pvs_contract();

pvs_test_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
pvs_test_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
pvs_test_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
pvs_test_assert( isset( $contract['network'] ) && false === $contract['network'], 'Заглушка не должна ходить в сеть.' );
pvs_test_assert( isset( $contract['requirement'] ) && 'TESTEB-154' === $contract['requirement'], 'Контракт ссылается на неверное требование.' );
pvs_test_assert( isset( $contract['all_plugins'] ) && true === $contract['all_plugins'], 'Контракт не подтверждает проверку всех плагинов.' );
pvs_test_assert( isset( $contract['scans_all_plugins'] ) && true === $contract['scans_all_plugins'], 'Не выставлен маркер scans_all_plugins.' );
pvs_test_assert( isset( $contract['checks_all_plugins'] ) && true === $contract['checks_all_plugins'], 'Не выставлен маркер checks_all_plugins.' );
pvs_test_assert( isset( $contract['coverage']['includes_inactive'] ) && true === $contract['coverage']['includes_inactive'], 'Неактивные плагины должны проверяться.' );
pvs_test_assert( isset( $contract['coverage']['includes_mu_plugins'] ) && true === $contract['coverage']['includes_mu_plugins'], 'mu-плагины должны проверяться.' );
pvs_test_assert( isset( $contract['coverage']['includes_dropins'] ) && true === $contract['coverage']['includes_dropins'], 'Drop-ins должны проверяться.' );
pvs_test_assert( isset( $contract['real_integration']['required'] ) && false === $contract['real_integration']['required'], 'Реальная интеграция не требуется.' );
pvs_test_assert( 'PVS_Contract' === get_class( new PVS_Contract() ) || class_exists( 'PVS_Contract' ), 'Отсутствует класс PVS_Contract.' );
pvs_test_assert( is_array( PVS_Contract::get_contract() ), 'PVS_Contract::get_contract() не возвращает контракт.' );
pvs_test_assert( true === PVS_Contract::all_plugins(), 'PVS_Contract::all_plugins() должен возвращать true.' );
pvs_test_assert( 'Высокая' === pvs_severity_label( 'high' ), 'Неверная подпись важности high.' );
pvs_test_assert( 'VELOTOK_PVS_API_TOKEN' === PVS_Contract::env_name( 'api_token' ), 'Неверное имя будущей env-переменной.' );

$stub_result = pvs_scan_all_plugins();

pvs_test_assert( is_array( $stub_result ), 'Результат проверки должен быть массивом.' );
pvs_test_assert( ! empty( $stub_result['ok'] ), 'Проверка должна завершаться успешно.' );
pvs_test_assert( isset( $stub_result['mocked'] ) && true === $stub_result['mocked'], 'Результат проверки не помечен mocked: true.' );
pvs_test_assert( isset( $stub_result['network'] ) && false === $stub_result['network'], 'Результат проверки не должен ходить в сеть.' );
pvs_test_assert( isset( $stub_result['all_plugins'] ) && true === $stub_result['all_plugins'], 'Результат не подтверждает проверку всех плагинов.' );
pvs_test_assert( isset( $stub_result['plugins_scanned'] ) && $stub_result['plugins_scanned'] > 0, 'Не проверено ни одного плагина.' );
pvs_test_assert( isset( $stub_result['vulnerability_count'] ) && $stub_result['vulnerability_count'] > 0, 'Заглушка должна находить хотя бы одну уязвимость.' );

$inactive_found = false;
$mu_found       = false;

foreach ( $stub_result['plugins'] as $plugin ) {
	pvs_test_assert( isset( $plugin['mocked'] ) && true === $plugin['mocked'], 'Плагин в отчёте не помечен mocked: true.' );
	pvs_test_assert( isset( $plugin['scanned'] ) && true === $plugin['scanned'], 'Плагин не отмечен как проверенный.' );

	if ( 'contact-form-7' === $plugin['slug'] && false === $plugin['active'] ) {
		$inactive_found = true;
	}

	if ( 'mu-plugin' === $plugin['type'] ) {
		$mu_found = true;
	}
}

pvs_test_assert( $inactive_found, 'Неактивный плагин не был проверен.' );
pvs_test_assert( $mu_found, 'mu-плагин не был проверен.' );

foreach ( $stub_result['vulnerabilities'] as $vulnerability ) {
	pvs_test_assert( isset( $vulnerability['mocked'] ) && true === $vulnerability['mocked'], 'Уязвимость не помечена mocked: true.' );
	pvs_test_assert( isset( $vulnerability['source'] ) && 'stub' === $vulnerability['source'], 'Уязвимость должна иметь источник stub.' );
	pvs_test_assert( isset( $vulnerability['network'] ) && false === $vulnerability['network'], 'Уязвимость не должна ходить в сеть.' );
}

$custom = array(
	'alpha/alpha.php' => array(
		'name'    => 'Alpha',
		'version' => '1.2.3',
		'active'  => true,
		'slug'    => 'alpha',
	),
	'beta/beta.php' => array(
		'name'    => 'Beta',
		'version' => '2.0.0',
		'active'  => false,
		'slug'    => 'beta',
	),
);

$custom_db = array(
	'alpha' => array(
		array(
			'id'       => 'TEST-1',
			'title'    => 'Test vulnerability',
			'severity' => 'critical',
			'affected' => '<2.0.0',
			'fixed_in' => '2.0.0',
		),
	),
);

$custom_result = pvs_scan( $custom, array( 'vulnerability_database' => $custom_db ) );

pvs_test_assert( 2 === $custom_result['plugins_scanned'], 'Должны проверяться все переданные плагины, включая неактивные.' );
pvs_test_assert( 1 === $custom_result['vulnerability_count'], 'Должна быть найдена одна уязвимость.' );
pvs_test_assert( 'alpha' === $custom_result['vulnerabilities'][0]['plugin'], 'Уязвимость должна относиться к alpha.' );
pvs_test_assert( 'Критическая' === $custom_result['vulnerabilities'][0]['severity_label'], 'Неверная подпись важности.' );

$safe_result = pvs_scan(
	array(
		'alpha/alpha.php' => array( 'name' => 'Alpha', 'version' => '2.0.0', 'slug' => 'alpha' ),
	),
	array( 'vulnerability_database' => $custom_db )
);

pvs_test_assert( 0 === $safe_result['vulnerability_count'], 'Исправленная версия не должна считаться уязвимой.' );

pvs_test_assert( true === pvs_version_matches( '5.3.1', '<5.3.2' ), 'Версия 5.3.1 должна попадать под <5.3.2.' );
pvs_test_assert( false === pvs_version_matches( '5.3.2', '<5.3.2' ), 'Версия 5.3.2 не должна попадать под <5.3.2.' );
pvs_test_assert( true === pvs_version_matches( '1.5.0', '>=1.0.0,<2.0.0' ), 'Диапазон версий должен поддерживаться.' );
pvs_test_assert( false === pvs_version_matches( '2.0.0', '>=1.0.0,<2.0.0' ), 'Верхняя граница диапазона должна исключаться.' );

pvs_test_assert( class_exists( 'PVS_Scanner' ), 'Отсутствует класс PVS_Scanner.' );
pvs_test_assert( is_array( PVS_Scanner::scan_all_plugins() ), 'PVS_Scanner::scan_all_plugins() не возвращает результат.' );
pvs_test_assert( class_exists( 'PVS_Admin' ), 'Отсутствует класс PVS_Admin.' );
pvs_test_assert( method_exists( 'PVS_Admin', 'handle_scan' ), 'Нет обработчика запуска проверки.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: pvs contract' . PHP_EOL;
