<?php
/**
 * Contract test for the mocked specification data stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/spec-data-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/spec-data-contract-test.php
 *
 * The test intentionally fails when the stub is removed or stops being marked
 * as mocked.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/spec-data.php';

$failures = array();

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_spec_contract_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_core_spec_data' ) ) {
	velotok_spec_contract_assert( false, 'Отсутствует контракт данных спецификации.' );
} else {
	$contract = velotok_core_spec_data();

	velotok_spec_contract_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_spec_contract_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_spec_contract_assert( isset( $contract['spec'] ) && 'clarify-a1b91b62' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_spec_contract_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_spec_contract_assert( isset( $contract['missing_key'] ) && 'empty_string' === $contract['missing_key'], 'Не зафиксировано поведение для неизвестного ключа.' );
	velotok_spec_contract_assert( isset( $contract['future_source'] ) && 'env' === $contract['future_source'], 'Не зафиксировано направление на env.' );
	velotok_spec_contract_assert( ! empty( $contract['values'] ) && is_array( $contract['values'] ), 'Не заданы значения данных спецификации.' );
	velotok_spec_contract_assert( ! empty( $contract['env_map'] ) && is_array( $contract['env_map'] ), 'Не задана карта env-переменных.' );

	foreach ( $contract['values'] as $key => $value ) {
		velotok_spec_contract_assert( isset( $contract['types'][ $key ] ), sprintf( 'Не задан тип ключа «%s».', $key ) );
		velotok_spec_contract_assert( isset( $contract['env_map'][ $key ] ) && '' !== $contract['env_map'][ $key ], sprintf( 'Не задано env-имя для ключа «%s».', $key ) );
		velotok_spec_contract_assert( '' !== (string) $value, sprintf( 'Пустое значение у известного ключа «%s».', $key ) );
	}
}

if ( function_exists( 'velotok_core_spec_get' ) ) {
	velotok_spec_contract_assert( '+7 (495) 123-45-67' === velotok_core_spec_get( 'shop_phone' ), 'Известный ключ не возвращает предопределённое значение.' );
	velotok_spec_contract_assert( 4 === velotok_core_spec_get( 'catalog_model_types' ), 'Числовое значение не сохраняет объявленный тип.' );
	velotok_spec_contract_assert( '' === velotok_core_spec_get( 'unknown_key' ), 'Неизвестный ключ должен возвращать пустую строку.' );
	velotok_spec_contract_assert( false === velotok_core_spec_has( 'unknown_key' ), 'Неизвестный ключ не должен считаться известным.' );
	velotok_spec_contract_assert( 'VELOTOK_SPEC_SHOP_PHONE' === velotok_core_spec_env_name( 'shop_phone' ), 'Неверное имя env-переменной для известного ключа.' );
	velotok_spec_contract_assert( '' === velotok_core_spec_env_name( 'unknown_key' ), 'Неизвестный ключ не должен получать env-имя.' );
}

$source_path = dirname( __DIR__ ) . '/includes/spec-data.php';

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
		velotok_spec_contract_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен содержать обращение к внешнему источнику: %s', $needle ) );
	}
} else {
	velotok_spec_contract_assert( false, 'Не найден файл заглушки данных спецификации.' );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт данных спецификации на месте.' . PHP_EOL;
