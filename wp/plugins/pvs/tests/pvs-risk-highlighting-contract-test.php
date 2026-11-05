<?php
/**
 * Contract test: all plugins are scanned and every detected risk is highlighted.
 *
 * Standalone:
 *   php wp/plugins/pvs/tests/pvs-risk-highlighting-contract-test.php
 *
 * With an alternate stub tree (used by the red-green test):
 *   PVS_PLUGIN_DIR=/tmp/pvs php wp/plugins/pvs/tests/pvs-risk-highlighting-contract-test.php
 *
 * The test fails when the scanner stops checking inactive/mu/drop-in plugins,
 * stops attaching risk highlight metadata, or when the admin report loses its
 * visual risk highlight classes/styles.
 *
 * @package PVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$plugin_dir = getenv( 'PVS_PLUGIN_DIR' );

if ( false === $plugin_dir || '' === $plugin_dir ) {
	$plugin_dir = dirname( __DIR__ );
}

$files = array(
	$plugin_dir . '/includes/class-pvs-contract.php',
	$plugin_dir . '/includes/class-pvs-scanner.php',
	$plugin_dir . '/includes/class-pvs-admin.php',
);

foreach ( $files as $file ) {
	if ( ! is_readable( $file ) ) {
		fwrite( STDERR, 'FAIL: Не найден файл заглушки ' . $file . PHP_EOL );
		exit( 1 );
	}

	require_once $file;
}

$failures = array();

function pvs_risk_highlighting_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = pvs_contract();

pvs_risk_highlighting_assert( true === $contract['mocked'], 'Контракт должен быть помечен mocked: true.' );
pvs_risk_highlighting_assert( true === $contract['all_plugins'], 'Контракт должен требовать проверку всех плагинов.' );
pvs_risk_highlighting_assert( true === $contract['risk_highlighting'], 'Контракт должен требовать подсветку рисков.' );
pvs_risk_highlighting_assert( isset( $contract['risk_styles'] ) && is_array( $contract['risk_styles'] ), 'В контракте отсутствует описание стилей рисков.' );

foreach ( array( 'critical', 'high', 'medium', 'low' ) as $severity ) {
	pvs_risk_highlighting_assert( isset( $contract['risk_styles'][ $severity ] ), 'Нет стиля риска для ' . $severity . '.' );

	$highlight = pvs_risk_highlight( $severity );

	pvs_risk_highlighting_assert( is_array( $highlight ), 'pvs_risk_highlight() должен возвращать массив.' );
	pvs_risk_highlighting_assert( 'pvs-risk-' . $severity === $highlight['highlight'], 'Неверный CSS-класс подсветки для ' . $severity . '.' );
	pvs_risk_highlighting_assert( 'pvs-severity-' . $severity === $highlight['severity_class'], 'Неверный CSS-класс важности для ' . $severity . '.' );
	pvs_risk_highlighting_assert( true === $highlight['highlighted'], 'Подсветка должна быть активна для ' . $severity . '.' );
	pvs_risk_highlighting_assert( true === $highlight['mocked'] && false === $highlight['network'], 'Подсветка должна быть mocked и без сети.' );
	pvs_risk_highlighting_assert( '' !== $highlight['highlight_color'] && '' !== $highlight['highlight_background'], 'У подсветки ' . $severity . ' нет цветов.' );
}

pvs_risk_highlighting_assert( 'PVS_Scanner' === get_class( new PVS_Scanner() ) || class_exists( 'PVS_Scanner' ), 'Отсутствует класс PVS_Scanner.' );
pvs_risk_highlighting_assert( is_array( PVS_Scanner::risk_highlight( 'critical' ) ), 'PVS_Scanner::risk_highlight() не возвращает данные подсветки.' );

$plugins = array(
	'alpha/alpha.php' => array(
		'name'    => 'Alpha',
		'version' => '1.0.0',
		'active'  => true,
		'slug'    => 'alpha',
	),
	'beta/beta.php' => array(
		'name'    => 'Beta',
		'version' => '2.0.0',
		'active'  => false,
		'slug'    => 'beta',
	),
	'gamma-mu.php' => array(
		'name'    => 'Gamma MU',
		'version' => '1.0.0',
		'active'  => true,
		'type'    => 'mu-plugin',
		'slug'    => 'gamma',
	),
	'advanced-cache.php' => array(
		'name'    => 'Safe Drop-in',
		'version' => '1.0.0',
		'active'  => true,
		'type'    => 'dropin',
		'slug'    => 'safe-dropin',
	),
);

$database = array(
	'alpha' => array(
		array(
			'id'       => 'TEST-HL-1',
			'title'    => 'Critical alpha issue',
			'severity' => 'critical',
			'affected' => '<2.0.0',
			'fixed_in' => '2.0.0',
		),
	),
	'beta' => array(
		array(
			'id'       => 'TEST-HL-2',
			'title'    => 'Medium beta issue',
			'severity' => 'medium',
			'affected' => '<3.0.0',
			'fixed_in' => '3.0.0',
		),
	),
	'gamma' => array(
		array(
			'id'       => 'TEST-HL-3',
			'title'    => 'High mu issue',
			'severity' => 'high',
			'affected' => '<1.5.0',
			'fixed_in' => '1.5.0',
		),
	),
);

$result = pvs_scan_all_plugins( $plugins, array( 'vulnerability_database' => $database ) );

pvs_risk_highlighting_assert( true === $result['ok'], 'Проверка должна завершаться успешно.' );
pvs_risk_highlighting_assert( 4 === $result['plugins_scanned'], 'Должны быть проверены все плагины, включая неактивный, mu и drop-in.' );
pvs_risk_highlighting_assert( true === $result['all_plugins'], 'Результат должен подтверждать проверку всех плагинов.' );
pvs_risk_highlighting_assert( 3 === $result['vulnerable_plugins'], 'Три плагина должны быть уязвимы.' );
pvs_risk_highlighting_assert( 3 === $result['vulnerability_count'], 'Должно быть найдено три уязвимости.' );
pvs_risk_highlighting_assert( true === $result['risk_highlighting'], 'Результат должен подтверждать подсветку рисков.' );
pvs_risk_highlighting_assert( 'critical' === $result['highest_risk'], 'Наибольший риск должен быть critical.' );
pvs_risk_highlighting_assert( isset( $result['risk_summary']['severity_counts']['critical'] ) && 1 === $result['risk_summary']['severity_counts']['critical'], 'Сводка рисков должна считать critical.' );
pvs_risk_highlighting_assert( true === $result['risk_summary']['risk_highlighting'], 'Сводка должна подтверждать подсветку рисков.' );

$by_slug = array();

foreach ( $result['plugins'] as $plugin ) {
	$by_slug[ $plugin['slug'] ] = $plugin;
}

foreach ( array( 'alpha', 'beta', 'gamma' ) as $slug ) {
	pvs_risk_highlighting_assert( isset( $by_slug[ $slug ] ), 'Плагин ' . $slug . ' не проверен.' );

	if ( ! isset( $by_slug[ $slug ] ) ) {
		continue;
	}

	$plugin = $by_slug[ $slug ];

	pvs_risk_highlighting_assert( true === $plugin['scanned'], 'Плагин ' . $slug . ' не отмечен как проверенный.' );
	pvs_risk_highlighting_assert( true === $plugin['vulnerable'], 'Плагин ' . $slug . ' должен быть уязвим.' );
	pvs_risk_highlighting_assert( true === $plugin['risk_highlighted'], 'Плагин ' . $slug . ' должен быть подсвечен.' );
	pvs_risk_highlighting_assert( 'pvs-risk-' . $plugin['risk_level'] === $plugin['highlight'], 'У плагина ' . $slug . ' неверный highlight.' );
	pvs_risk_highlighting_assert( '' !== $plugin['highlight_color'] && '' !== $plugin['highlight_background'], 'У плагина ' . $slug . ' нет цветов подсветки.' );
	pvs_risk_highlighting_assert( 'pvs-severity-' . $plugin['risk_level'] === $plugin['severity_class'], 'У плагина ' . $slug . ' неверный severity_class.' );
}

pvs_risk_highlighting_assert( isset( $by_slug['safe-dropin'] ), 'Безопасный drop-in не был проверен.' );
pvs_risk_highlighting_assert( false === $by_slug['safe-dropin']['vulnerable'], 'Безопасный drop-in не должен считаться уязвимым.' );
pvs_risk_highlighting_assert( false === $by_slug['safe-dropin']['risk_highlighted'], 'Безопасный drop-in не должен быть подсвечен.' );
pvs_risk_highlighting_assert( '' === $by_slug['safe-dropin']['highlight'], 'У безопасного плагина не должно быть highlight.' );

$expected_severity = array(
	'TEST-HL-1' => 'critical',
	'TEST-HL-2' => 'medium',
	'TEST-HL-3' => 'high',
);

foreach ( $result['vulnerabilities'] as $vulnerability ) {
	$id = isset( $vulnerability['id'] ) ? $vulnerability['id'] : '';

	pvs_risk_highlighting_assert( isset( $expected_severity[ $id ] ), 'Неожиданная уязвимость ' . $id . '.' );

	if ( ! isset( $expected_severity[ $id ] ) ) {
		continue;
	}

	$severity = $expected_severity[ $id ];

	pvs_risk_highlighting_assert( $severity === $vulnerability['severity'], 'Неверная важность у ' . $id . '.' );
	pvs_risk_highlighting_assert( 'pvs-risk-' . $severity === $vulnerability['highlight'], 'Неверная подсветка у ' . $id . '.' );
	pvs_risk_highlighting_assert( 'pvs-severity-' . $severity === $vulnerability['severity_class'], 'Неверный класс важности у ' . $id . '.' );
	pvs_risk_highlighting_assert( true === $vulnerability['highlighted'], 'Уязвимость ' . $id . ' не подсвечена.' );
	pvs_risk_highlighting_assert( '' !== $vulnerability['highlight_color'] && '' !== $vulnerability['highlight_background'], 'У уязвимости ' . $id . ' нет цветов.' );
}

$report = function_exists( 'pvs_admin_render_report' ) ? pvs_admin_render_report( $result ) : '';
$styles = class_exists( 'PVS_Admin' ) && method_exists( 'PVS_Admin', 'styles' ) ? PVS_Admin::styles() : '';

pvs_risk_highlighting_assert( false !== strpos( $report, 'pvs-risk-critical' ), 'В отчёте нет подсветки critical.' );
pvs_risk_highlighting_assert( false !== strpos( $report, 'pvs-severity-critical' ), 'В отчёте нет класса важности critical.' );
pvs_risk_highlighting_assert( false !== strpos( $report, 'pvs-risk-highlight' ), 'В отчёте нет маркера pvs-risk-highlight.' );
pvs_risk_highlighting_assert( false !== strpos( $report, 'data-highlight="pvs-risk-critical"' ), 'В отчёте нет data-highlight для critical.' );
pvs_risk_highlighting_assert( '' !== $styles, 'Нет CSS для подсветки рисков.' );

foreach ( array( 'critical', 'high', 'medium', 'low' ) as $severity ) {
	pvs_risk_highlighting_assert( false !== strpos( $styles, '.pvs-risk-' . $severity ), 'CSS не подсвечивает ' . $severity . '.' );
}

pvs_risk_highlighting_assert( false !== strpos( $styles, '.pvs-plugin-vulnerable' ), 'CSS не подсвечивает уязвимые плагины.' );
pvs_risk_highlighting_assert( false !== strpos( $styles, '.pvs-risk-highlight' ), 'CSS не содержит pvs-risk-highlight.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: pvs all plugins risk highlighting contract' . PHP_EOL;
