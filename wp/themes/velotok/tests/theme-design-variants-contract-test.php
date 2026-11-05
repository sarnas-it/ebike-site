<?php
/**
 * Contract test for the mocked theme design variants stub.
 *
 * Standalone:
 *   php wp/themes/velotok/tests/theme-design-variants-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/themes/velotok/tests/theme-design-variants-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked, no longer exposes several variants or stops marking unselected
 * variants as rejected.
 *
 * @package Velotok
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/inc/theme-design-variants.php';

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки вариантов дизайна темы.' . PHP_EOL );
	exit( 1 );
}

require_once $stub_path;

$failures = array();

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_theme_design_contract_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$expected_variants = array( 'urban', 'trail', 'family' );

if ( ! function_exists( 'velotok_theme_design_variants_contract' ) ) {
	velotok_theme_design_contract_assert( false, 'Отсутствует контракт вариантов дизайна темы.' );
} else {
	$contract = velotok_theme_design_variants_contract();

	velotok_theme_design_contract_assert( is_array( $contract ), 'Контракт вариантов дизайна должен быть массивом.' );
	velotok_theme_design_contract_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_theme_design_contract_assert( isset( $contract['spec'] ) && 'clarify-c1924ac5' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_theme_design_contract_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_theme_design_contract_assert( isset( $contract['operation'] ) && 'select_design_variant' === $contract['operation'], 'Не зафиксирована операция выбора варианта дизайна.' );
	velotok_theme_design_contract_assert( isset( $contract['single_choice'] ) && true === $contract['single_choice'], 'Выбор варианта должен быть одиночным.' );
	velotok_theme_design_contract_assert( isset( $contract['network'] ) && false === $contract['network'], 'Заглушка не должна выполнять сетевые вызовы.' );
	velotok_theme_design_contract_assert( isset( $contract['persistence'] ) && false === $contract['persistence'], 'Заглушка не должна сохранять состояние.' );
	velotok_theme_design_contract_assert( isset( $contract['real_integration'] ) && false === $contract['real_integration'], 'Реальная интеграция не должна требоваться.' );
	velotok_theme_design_contract_assert( isset( $contract['selected_status'] ) && 'selected' === $contract['selected_status'], 'Статус выбранного варианта должен быть selected.' );
	velotok_theme_design_contract_assert( isset( $contract['rejected_status'] ) && 'rejected' === $contract['rejected_status'], 'Статус невыбранных вариантов должен быть rejected.' );
	velotok_theme_design_contract_assert( isset( $contract['statuses'] ) && in_array( 'rejected', $contract['statuses'], true ), 'Контракт не описывает статус rejected.' );
	velotok_theme_design_contract_assert( ! empty( $contract['open_questions'] ) && is_array( $contract['open_questions'] ), 'Не зафиксированы открытые вопросы по реальной интеграции.' );
	velotok_theme_design_contract_assert( isset( $contract['error_codes']['missing_parameter'] ) && 'DESIGN_VARIANT_REQUIRED' === $contract['error_codes']['missing_parameter'], 'Неверный код отсутствующего варианта.' );
	velotok_theme_design_contract_assert( isset( $contract['error_codes']['unknown_variant'] ) && 'DESIGN_VARIANT_UNKNOWN' === $contract['error_codes']['unknown_variant'], 'Неверный код неизвестного варианта.' );
	velotok_theme_design_contract_assert( isset( $contract['error_codes']['invalid_input'] ) && 'DESIGN_VARIANT_INVALID' === $contract['error_codes']['invalid_input'], 'Неверный код невалидного ввода.' );

	velotok_theme_design_contract_assert(
		isset( $contract['required_variants'] ) && $expected_variants === $contract['required_variants'],
		'Контракт должен предоставлять несколько фиксированных вариантов дизайна.'
	);
	velotok_theme_design_contract_assert(
		isset( $contract['variants'] ) && count( $contract['variants'] ) >= 2,
		'Должно быть предложено не менее двух вариантов дизайна.'
	);

	foreach ( $expected_variants as $variant ) {
		velotok_theme_design_contract_assert( isset( $contract['variants'][ $variant ] ), sprintf( 'Не описан вариант дизайна «%s».', $variant ) );
		velotok_theme_design_contract_assert(
			isset( $contract['variants'][ $variant ]['mocked'] ) && true === $contract['variants'][ $variant ]['mocked'],
			sprintf( 'Вариант дизайна «%s» не помечен mocked: true.', $variant )
		);
		velotok_theme_design_contract_assert(
			isset( $contract['variants'][ $variant ]['label'] ) && '' !== $contract['variants'][ $variant ]['label'],
			sprintf( 'У варианта дизайна «%s» нет подписи.', $variant )
		);
	}
}

if ( function_exists( 'velotok_theme_design_variants' ) ) {
	$variants = velotok_theme_design_variants();

	velotok_theme_design_contract_assert( is_array( $variants ) && count( $variants ) >= 2, 'Функция вариантов дизайна должна возвращать несколько вариантов.' );
}

if ( function_exists( 'velotok_theme_design_variant' ) ) {
	velotok_theme_design_contract_assert( null !== velotok_theme_design_variant( 'urban' ), 'Вариант urban должен разрешаться.' );
	velotok_theme_design_contract_assert( null === velotok_theme_design_variant( 'unknown' ), 'Неизвестный вариант не должен разрешаться.' );
}

if ( function_exists( 'velotok_theme_select_design_variant' ) ) {
	foreach ( $expected_variants as $variant ) {
		$result = velotok_theme_select_design_variant( $variant );

		velotok_theme_design_contract_assert( is_array( $result ), 'Результат выбора должен быть массивом.' );
		velotok_theme_design_contract_assert( true === $result['ok'], sprintf( 'Выбор варианта «%s» должен завершаться успешно.', $variant ) );
		velotok_theme_design_contract_assert( true === $result['valid'], sprintf( 'Выбор варианта «%s» должен быть валидным.', $variant ) );
		velotok_theme_design_contract_assert( true === $result['mocked'], 'Результат выбора должен быть помечен mocked: true.' );
		velotok_theme_design_contract_assert( 'clarify-c1924ac5' === $result['spec'], 'Результат должен ссылаться на спеку clarify-c1924ac5.' );
		velotok_theme_design_contract_assert( $variant === $result['selected'], sprintf( 'Выбранным должен быть вариант «%s».', $variant ) );
		velotok_theme_design_contract_assert( 'selected' === $result['status'], 'Статус выбора должен быть selected.' );
		velotok_theme_design_contract_assert( false === $result['network'], 'Выбор не должен выполнять сетевые вызовы.' );
		velotok_theme_design_contract_assert( false === $result['persisted'], 'Выбор не должен сохранять состояние.' );
		velotok_theme_design_contract_assert( null === $result['error'], 'Успешный выбор не должен содержать ошибку.' );
		velotok_theme_design_contract_assert( array() === $result['errors'], 'Успешный выбор не должен содержать ошибок.' );

		$selected_count = 0;
		$rejected       = array();

		foreach ( $result['variants'] as $state ) {
			if ( true === $state['selected'] ) {
				$selected_count++;
				velotok_theme_design_contract_assert( $variant === $state['id'], 'Выбранный статус должен быть только у выбранного варианта.' );
				velotok_theme_design_contract_assert( 'selected' === $state['status'], 'У выбранного варианта должен быть статус selected.' );
			} else {
				$rejected[] = $state['id'];
				velotok_theme_design_contract_assert( true === $state['rejected'], sprintf( 'Невыбранный вариант «%s» должен быть помечен rejected.', $state['id'] ) );
				velotok_theme_design_contract_assert( 'rejected' === $state['status'], sprintf( 'У невыбранного варианта «%s» должен быть статус rejected.', $state['id'] ) );
			}
		}

		velotok_theme_design_contract_assert( 1 === $selected_count, 'Выбран должен быть ровно один вариант.' );
		velotok_theme_design_contract_assert( count( $rejected ) === count( $expected_variants ) - 1, 'Все невыбранные варианты должны быть отклонены.' );
		velotok_theme_design_contract_assert( $rejected === $result['rejected'], 'Список rejected должен совпадать со статусами вариантов.' );
	}

	$first  = velotok_theme_select_design_variant( 'urban' );
	$second = velotok_theme_select_design_variant( array( 'variant' => 'urban' ) );
	velotok_theme_design_contract_assert( $first === $second, 'Заглушка должна быть детерминированной при одинаковом вводе.' );

	$alias = velotok_theme_select_design_variant( array( 'design' => 'trail' ) );
	velotok_theme_design_contract_assert( true === $alias['ok'], 'Псевдоним поля design должен приниматься.' );
	velotok_theme_design_contract_assert( 'trail' === $alias['selected'], 'Псевдоним design должен выбирать вариант trail.' );

	$missing = velotok_theme_select_design_variant( array() );
	velotok_theme_design_contract_assert( false === $missing['ok'], 'Пустой ввод не должен приводить к выбору.' );
	velotok_theme_design_contract_assert( true === $missing['mocked'], 'Ошибочный ответ должен быть помечен mocked.' );
	velotok_theme_design_contract_assert( 'DESIGN_VARIANT_REQUIRED' === $missing['error']['code'], 'Для пустого ввода нужен DESIGN_VARIANT_REQUIRED.' );

	$unknown = velotok_theme_select_design_variant( 'unknown' );
	velotok_theme_design_contract_assert( false === $unknown['ok'], 'Неизвестный вариант не должен выбираться.' );
	velotok_theme_design_contract_assert( 'DESIGN_VARIANT_UNKNOWN' === $unknown['error']['code'], 'Для неизвестного варианта нужен DESIGN_VARIANT_UNKNOWN.' );

	$invalid = velotok_theme_select_design_variant( array( 'variant' => array( 'urban' ) ) );
	velotok_theme_design_contract_assert( false === $invalid['ok'], 'Неверный тип варианта должен отклоняться.' );
	velotok_theme_design_contract_assert( 'DESIGN_VARIANT_INVALID' === $invalid['error']['code'], 'Для неверного типа нужен DESIGN_VARIANT_INVALID.' );
}

$source = (string) file_get_contents( $stub_path );

$forbidden = array(
	'get' . 'env(',
	'apache_get' . 'env(',
	'$_' . 'ENV',
	'$_' . 'SERVER',
	'file_get' . '_contents(',
	'fopen(',
	'curl_' . 'init(',
	'wp_remote_' . 'get(',
	'wp_remote_' . 'post(',
	'fsockopen(',
	'stream_socket_' . 'client(',
	'socket_' . 'create(',
);

foreach ( $forbidden as $needle ) {
	velotok_theme_design_contract_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт вариантов дизайна темы на месте.' . PHP_EOL;
