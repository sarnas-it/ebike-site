<?php
/**
 * Contract test for the mocked content thematic coverage stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/content-coverage-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/content-coverage-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked, or loses one of the three required thematic categories.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

$failures     = array();
$content_stub = dirname( __DIR__ ) . '/includes/content-coverage.php';

if ( is_readable( $content_stub ) ) {
	require_once $content_stub;
} else {
	$failures[] = 'Не найден файл заглушки тематического охвата.';
}

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_content_coverage_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_core_content_coverage_contract' ) ) {
	velotok_content_coverage_assert( false, 'Отсутствует контракт тематического охвата контента.' );
} else {
	$contract = velotok_core_content_coverage_contract();

	velotok_content_coverage_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_content_coverage_assert( isset( $contract['spec'] ) && 'clarify-a2fd2f66' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_content_coverage_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_content_coverage_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_content_coverage_assert( isset( $contract['classification'] ) && 'hybrid' === $contract['classification'], 'Не зафиксирован гибридный способ определения тематики.' );
	velotok_content_coverage_assert( isset( $contract['categories'] ) && is_array( $contract['categories'] ), 'Не заданы тематические категории.' );
	velotok_content_coverage_assert( 3 === count( $contract['categories'] ), 'Контракт должен описывать ровно три обязательные тематики.' );

	$expected = array(
		'model_review'      => 'Обзор моделей в зелёной энергетике',
		'legislation'       => 'Требования законодательства',
		'green_energy_news' => 'Новости о зелёной энергетике',
	);

	foreach ( $expected as $key => $label ) {
		velotok_content_coverage_assert( isset( $contract['categories'][ $key ] ), sprintf( 'Не задана тематика «%s».', $label ) );
		velotok_content_coverage_assert( isset( $contract['categories'][ $key ]['label'] ) && $label === $contract['categories'][ $key ]['label'], sprintf( 'Неверная подпись тематики «%s».', $key ) );
		velotok_content_coverage_assert( ! empty( $contract['categories'][ $key ]['slug'] ), sprintf( 'Не задан slug тематики «%s».', $key ) );
		velotok_content_coverage_assert( ! empty( $contract['categories'][ $key ]['all_of'] ) && is_array( $contract['categories'][ $key ]['all_of'] ), sprintf( 'Не заданы условия тематики «%s».', $key ) );
		velotok_content_coverage_assert( ! empty( $contract['categories'][ $key ]['criteria'] ) && is_array( $contract['categories'][ $key ]['criteria'] ), sprintf( 'Не заданы критерии проверки тематики «%s».', $key ) );
	}
}

if ( function_exists( 'velotok_core_content_coverage_has_full_coverage' ) ) {
	velotok_content_coverage_assert( true === velotok_core_content_coverage_has_full_coverage(), 'Не покрыты все три обязательные тематики.' );
}

$positive = array(
	'model_review'      => array(
		'title'   => 'Обзор моделей электровелосипедов для города',
		'content' => 'Сравниваем характеристики и запас хода популярных моделей.',
	),
	'legislation'       => array(
		'title'   => 'Требования законодательства к солнечным станциям',
		'content' => 'Нормативы и порядок сертификации оборудования.',
	),
	'green_energy_news' => array(
		'title'   => 'Новости зелёной энергетики',
		'content' => 'Солнечная станция введена в эксплуатацию, событие подтверждено источником.',
	),
);

if ( function_exists( 'velotok_core_content_coverage_is_compliant' ) ) {
	foreach ( $positive as $category => $material ) {
		velotok_content_coverage_assert( true === velotok_core_content_coverage_is_compliant( $material, $category ), sprintf( 'Материал, удовлетворяющий условиям, не признан соответствующим тематике «%s».', $category ) );
	}

	$model_evaluation = velotok_core_content_coverage_evaluate( $positive['model_review'] );
	velotok_content_coverage_assert( true === $model_evaluation['mocked'], 'Результат проверки не помечен mocked.' );
	velotok_content_coverage_assert( 'clarify-a2fd2f66' === $model_evaluation['spec'], 'Результат проверки ссылается на неверную спеку.' );
	velotok_content_coverage_assert( in_array( 'model_review', $model_evaluation['matched_categories'], true ), 'Обзор моделей не попал в совпавшие тематики.' );
	velotok_content_coverage_assert( 'model_review' === $model_evaluation['primary_category'], 'Обзор моделей не определён как основная тематика.' );

	velotok_content_coverage_assert( true === velotok_core_content_coverage_is_compliant( $positive['model_review'], 'model-review' ), 'Проверка по slug тематики не работает.' );
	velotok_content_coverage_assert( false === velotok_core_content_coverage_is_compliant( $positive['model_review'], 'legislation' ), 'Обзор моделей ошибочно признан требованием законодательства.' );
	velotok_content_coverage_assert( false === velotok_core_content_coverage_is_compliant( $positive['legislation'], 'model_review' ), 'Требования законодательства ошибочно признаны обзором моделей.' );
	velotok_content_coverage_assert( false === velotok_core_content_coverage_is_compliant( $positive['green_energy_news'], 'legislation' ), 'Новости о зелёной энергетике ошибочно признаны требованиями законодательства.' );

	$unrelated = array(
		'title'   => 'Рецепт борща со свёклой',
		'content' => 'Капуста, картофель, морковь и томатная паста.',
	);

	$unrelated_evaluation = velotok_core_content_coverage_evaluate( $unrelated );
	velotok_content_coverage_assert( empty( $unrelated_evaluation['matched_categories'] ), 'Посторонний материал ошибочно признан тематическим.' );
	velotok_content_coverage_assert( null === $unrelated_evaluation['primary_category'], 'У постороннего материала не должно быть основной тематики.' );

	foreach ( array_keys( $expected ) as $category ) {
		velotok_content_coverage_assert( false === velotok_core_content_coverage_is_compliant( $unrelated, $category ), sprintf( 'Посторонний материал ошибочно признан соответствующим тематике «%s».', $category ) );
	}

	$empty_evaluation = velotok_core_content_coverage_evaluate( array() );
	velotok_content_coverage_assert( empty( $empty_evaluation['matched_categories'] ), 'Пустой материал ошибочно признан тематическим.' );
	velotok_content_coverage_assert( false === velotok_core_content_coverage_is_compliant( array(), 'unknown-category' ), 'Неизвестная тематика не должна подтверждаться.' );
}

$source_path = dirname( __DIR__ ) . '/includes/content-coverage.php';

if ( is_readable( $source_path ) ) {
	$source = (string) file_get_contents( $source_path );

	$forbidden = array(
		'get' . 'env(',
		'apache_get' . 'env(',
		'$_' . 'ENV',
		'$_' . 'SERVER',
		'file_get' . '_contents(',
		'fopen(',
		'curl_' . 'init(',
		'wp_remote_' . 'get(',
	);

	foreach ( $forbidden as $needle ) {
		velotok_content_coverage_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен содержать обращение к внешнему источнику: %s', $needle ) );
	}
} else {
	velotok_content_coverage_assert( false, 'Не найден файл заглушки тематического охвата.' );
}

if ( $failures ) {
	$error_stream = defined( 'STDERR' ) ? STDERR : fopen( 'php://stderr', 'w' );

	foreach ( $failures as $failure ) {
		fwrite( $error_stream, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт тематического охвата контента на месте.' . PHP_EOL;
