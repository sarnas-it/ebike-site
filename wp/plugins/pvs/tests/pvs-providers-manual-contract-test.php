<?php

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$base = dirname( __DIR__ );

require_once $base . '/includes/class-pvs-contract.php';
require_once $base . '/includes/class-pvs-scanner.php';
require_once $base . '/includes/class-pvs-admin.php';

$failures = array();

function pvs_providers_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = pvs_contract();

pvs_providers_assert( isset( $contract['providers'] ) && is_array( $contract['providers'] ), 'Контракт должен описывать провайдеров проверки.' );

foreach ( array( 'wpscan', 'patchstack', 'manual' ) as $provider_id ) {
	pvs_providers_assert( isset( $contract['providers'][ $provider_id ] ), 'Нет провайдера ' . $provider_id . '.' );
	pvs_providers_assert( in_array( $provider_id, $contract['supported_providers'], true ), 'Провайдер ' . $provider_id . ' не поддержан.' );

	$provider = pvs_provider( $provider_id );

	pvs_providers_assert( is_array( $provider ) && ! empty( $provider ), 'pvs_provider() не возвращает ' . $provider_id . '.' );
	pvs_providers_assert( isset( $provider['mocked'] ) && true === $provider['mocked'], 'Провайдер ' . $provider_id . ' должен быть mocked.' );
	pvs_providers_assert( isset( $provider['network'] ) && false === $provider['network'], 'Провайдер ' . $provider_id . ' не должен ходить в сеть.' );
}

pvs_providers_assert( 'manual' === pvs_normalize_provider( 'manual_audit' ), 'Алиас manual_audit должен вести к manual.' );
pvs_providers_assert( 'patchstack' === pvs_normalize_provider( 'Patchstack' ), 'Нормализация Patchstack не работает.' );
pvs_providers_assert( 'wpscan' === pvs_default_provider(), 'Провайдер по умолчанию должен быть wpscan.' );

$format = pvs_highlight_format();

pvs_providers_assert( 'pvs-risk-' === $format['risk_class_prefix'], 'Неверный префикс класса риска.' );
pvs_providers_assert( 'pvs-severity-' === $format['severity_class_prefix'], 'Неверный префикс класса важности.' );
pvs_providers_assert( 'data-highlight' === $format['data_highlight'], 'Неверный data-атрибут подсветки.' );
pvs_providers_assert( true === $format['mocked'] && false === $format['network'], 'Формат подсветки должен быть mocked и без сети.' );

foreach ( array( 'critical', 'high', 'medium', 'low' ) as $severity ) {
	pvs_providers_assert( isset( $format['classes'][ $severity ] ), 'Нет класса подсветки для ' . $severity . '.' );
	pvs_providers_assert( isset( $format['severity_classes'][ $severity ] ), 'Нет класса важности для ' . $severity . '.' );
	pvs_providers_assert( ! empty( $format['colors'][ $severity ]['color'] ), 'Нет цвета подсветки для ' . $severity . '.' );
}

pvs_providers_assert( isset( $contract['manual_audit'] ) && true === $contract['manual_audit']['supported'], 'Ручной аудит не поддержан в контракте.' );
pvs_providers_assert( true === $contract['manual_audit']['highlighted'], 'Находки ручного аудита должны подсвечиваться.' );

$plugins = array(
	'alpha/alpha.php' => array( 'name' => 'Alpha', 'version' => '1.2.3', 'active' => true, 'slug' => 'alpha' ),
);

$result = pvs_scan(
	$plugins,
	array(
		'provider'        => 'manual',
		'manual_findings' => array(
			array(
				'plugin'   => 'alpha',
				'title'    => 'Ручная находка',
				'severity' => 'critical',
				'version'  => '1.2.3',
			),
		),
	)
);

pvs_providers_assert( 'manual' === $result['provider'], 'Результат должен сохранять выбранный провайдер.' );
pvs_providers_assert( 1 === $result['vulnerability_count'], 'Ручная находка должна попасть в отчёт.' );
pvs_providers_assert( 'manual' === $result['vulnerabilities'][0]['provider'], 'У ручной находки должен быть провайдер manual.' );
pvs_providers_assert( true === $result['vulnerabilities'][0]['manual'], 'Ручная находка должна быть помечена manual.' );
pvs_providers_assert( 'pvs-risk-critical' === $result['vulnerabilities'][0]['highlight'], 'Ручная находка должна быть подсвечена.' );
pvs_providers_assert( true === $result['plugins'][0]['vulnerable'], 'Плагин с ручной находкой должен быть уязвим.' );
pvs_providers_assert( 'critical' === $result['highest_risk'], 'Наибольший риск должен учитывать ручные находки.' );

$manual_only = pvs_scan(
	array(),
	array(
		'provider'        => 'manual',
		'manual_findings' => array(
			'gamma' => array(
				array( 'title' => 'Находка без установленного плагина', 'severity' => 'high' ),
			),
		),
	)
);

pvs_providers_assert( true === $manual_only['ok'], 'Проверка только ручных находок должна работать без списка плагинов.' );
pvs_providers_assert( 1 === $manual_only['vulnerability_count'], 'Ручная находка без плагина должна попасть в отчёт.' );
pvs_providers_assert( 'gamma' === $manual_only['vulnerabilities'][0]['plugin'], 'Ключ группы должен задавать slug ручной находки.' );

$report = pvs_admin_render_report( $result );
pvs_providers_assert( false !== strpos( $report, 'data-provider="manual"' ), 'В отчёте нет выбранного провайдера.' );
pvs_providers_assert( false !== strpos( $report, 'pvs-risk-critical' ), 'В отчёте нет подсветки ручной находки.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: pvs providers, manual audit and highlight format contract' . PHP_EOL;
