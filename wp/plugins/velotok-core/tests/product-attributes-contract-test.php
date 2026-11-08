<?php
/**
 * Contract test for the mocked additional product attributes stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/product-attributes-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/product-attributes-contract-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/product-attributes.php';

$failures = array();

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_product_attributes_contract_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_core_product_attributes_contract' ) ) {
	velotok_product_attributes_contract_assert( false, 'Отсутствует контракт дополнительных атрибутов товара.' );
} else {
	$contract = velotok_core_product_attributes_contract();

	velotok_product_attributes_contract_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_product_attributes_contract_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_product_attributes_contract_assert( isset( $contract['spec'] ) && 'clarify-2c698d44' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_product_attributes_contract_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_product_attributes_contract_assert( isset( $contract['missing_key'] ) && 'empty_array' === $contract['missing_key'], 'Не зафиксировано поведение для неизвестного товара.' );
	velotok_product_attributes_contract_assert( isset( $contract['future_source'] ) && 'seller' === $contract['future_source'], 'Не зафиксировано направление на данные продавца.' );
	velotok_product_attributes_contract_assert( isset( $contract['card_mandatory_attributes'] ) && array( 'photo', 'name', 'description', 'price' ) === $contract['card_mandatory_attributes'], 'Не зафиксированы обязательные атрибуты карточки.' );
	velotok_product_attributes_contract_assert( ! empty( $contract['attributes'] ) && is_array( $contract['attributes'] ), 'Не задан набор дополнительных атрибутов.' );
	velotok_product_attributes_contract_assert( ! empty( $contract['values'] ) && is_array( $contract['values'] ), 'Не заданы значения дополнительных атрибутов.' );

	foreach ( $contract['attributes'] as $key => $attribute ) {
		velotok_product_attributes_contract_assert( isset( $attribute['label'] ) && '' !== $attribute['label'], sprintf( 'Не задана подпись атрибута «%s».', $key ) );
		velotok_product_attributes_contract_assert( isset( $attribute['type'] ) && '' !== $attribute['type'], sprintf( 'Не задан тип атрибута «%s».', $key ) );
	}
}

if ( function_exists( 'velotok_core_product_attributes_get' ) ) {
	$rows = velotok_core_product_attributes_get( 'riviera' );

	velotok_product_attributes_contract_assert( ! empty( $rows ), 'Известный товар не получает дополнительных атрибутов.' );

	$labels = array();
	foreach ( $rows as $row ) {
		velotok_product_attributes_contract_assert( isset( $row['label'] ) && '' !== $row['label'], 'У дополнительного атрибута нет подписи.' );
		velotok_product_attributes_contract_assert( isset( $row['value'] ) && '' !== $row['value'], 'У дополнительного атрибута нет значения.' );
		$labels[ $row['label'] ] = $row['value'];
	}

	velotok_product_attributes_contract_assert( isset( $labels['Модель'] ) && 'Riviera City' === $labels['Модель'], 'Потерян произвольный атрибут «Модель».' );
	velotok_product_attributes_contract_assert( isset( $labels['Рама'] ) && 'Алюминий 6061' === $labels['Рама'], 'Потерян произвольный атрибут «Рама».' );
	velotok_product_attributes_contract_assert( isset( $labels['Гарантия'] ) && '2 года' === $labels['Гарантия'], 'Потерян произвольный атрибут «Гарантия».' );

	velotok_product_attributes_contract_assert( array() === velotok_core_product_attributes_get( 'unknown-product' ), 'Неизвестный товар должен получать пустой список атрибутов.' );
	velotok_product_attributes_contract_assert( false === velotok_core_product_attributes_has( 'unknown-product' ), 'Неизвестный товар не должен считаться известным.' );

	$tuples = velotok_core_product_attributes_rows( 'family' );
	velotok_product_attributes_contract_assert( is_array( $tuples ) && ! empty( $tuples ), 'Не формируются пары для карточки.' );
	velotok_product_attributes_contract_assert( isset( $tuples[0][0], $tuples[0][1] ) && is_string( $tuples[0][0] ) && is_string( $tuples[0][1] ), 'Пары для карточки имеют неверный формат.' );
}

$source_path = dirname( __DIR__ ) . '/includes/product-attributes.php';

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
		'$wpdb',
	);

	foreach ( $forbidden as $needle ) {
		velotok_product_attributes_contract_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен содержать обращение к внешнему источнику: %s', $needle ) );
	}
} else {
	velotok_product_attributes_contract_assert( false, 'Не найден файл заглушки дополнительных атрибутов.' );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт дополнительных атрибутов товара на месте.' . PHP_EOL;
