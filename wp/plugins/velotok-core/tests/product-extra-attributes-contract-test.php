<?php
/**
 * Contract test for the mocked product extra attributes stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/product-extra-attributes-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/product-extra-attributes-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer displays arbitrary extra attributes.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/product-extra-attributes.php';

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки дополнительных атрибутов товара.' . PHP_EOL );
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
function velotok_extra_contract_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Find a normalized attribute by key.
 *
 * @param array<int,array<string,mixed>> $items Normalized attributes.
 * @param string                         $key   Attribute key.
 * @return array<string,mixed>|null
 */
function velotok_extra_contract_find( $items, $key ) {
	foreach ( $items as $item ) {
		if ( isset( $item['key'] ) && $key === $item['key'] ) {
			return $item;
		}
	}

	return null;
}

$expected_required = array( 'photo', 'title', 'description', 'price' );

if ( ! function_exists( 'velotok_core_product_extra_attributes_contract' ) ) {
	velotok_extra_contract_assert( false, 'Отсутствует контракт дополнительных атрибутов товара.' );
} else {
	$contract = velotok_core_product_extra_attributes_contract();

	velotok_extra_contract_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_extra_contract_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_extra_contract_assert( isset( $contract['spec'] ) && 'clarify-2c698d44' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_extra_contract_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_extra_contract_assert( isset( $contract['parent_spec'] ) && 'ecommerce-store' === $contract['parent_spec'], 'Не зафиксирована родительская спецификация.' );
	velotok_extra_contract_assert( isset( $contract['required_attributes'] ) && $expected_required === $contract['required_attributes'], 'Обязательные атрибуты карточки должны оставаться неизменными.' );
	velotok_extra_contract_assert( ! empty( $contract['additional_attributes'] ) && is_array( $contract['additional_attributes'] ), 'Не задан набор дополнительных атрибутов.' );
	velotok_extra_contract_assert( isset( $contract['arbitrary'] ) && true === $contract['arbitrary'], 'Контракт должен разрешать произвольные дополнительные атрибуты.' );
	velotok_extra_contract_assert( isset( $contract['allow_arbitrary'] ) && true === $contract['allow_arbitrary'], 'Не зафиксировано разрешение произвольных ключей.' );
	velotok_extra_contract_assert( isset( $contract['unknown_attribute'] ) && 'rendered_humanized' === $contract['unknown_attribute'], 'Неизвестные ключи должны отображаться с человекочитаемой подписью.' );
	velotok_extra_contract_assert( isset( $contract['empty_value'] ) && 'hidden' === $contract['empty_value'], 'Пустые значения должны скрываться.' );
	velotok_extra_contract_assert( isset( $contract['future_source'] ) && '' !== (string) $contract['future_source'], 'Не зафиксирован будущий источник данных.' );
	velotok_extra_contract_assert( isset( $contract['open_questions'] ) && 3 === count( $contract['open_questions'] ), 'Не зафиксированы три открытых вопроса.' );
	velotok_extra_contract_assert( ! empty( $contract['stub_values'] ) && is_array( $contract['stub_values'] ), 'Не заданы mocked-значения дополнительных атрибутов.' );
	velotok_extra_contract_assert( isset( $contract['stub_values']['default'] ) && is_array( $contract['stub_values']['default'] ), 'Не задан набор значений по умолчанию.' );

	foreach ( $contract['additional_attributes'] as $key => $definition ) {
		velotok_extra_contract_assert( isset( $definition['label'] ) && '' !== (string) $definition['label'], sprintf( 'Не задана подпись дополнительного атрибута «%s».', $key ) );
		velotok_extra_contract_assert( isset( $definition['type'] ) && in_array( $definition['type'], $contract['value_types'], true ), sprintf( 'Неверный тип дополнительного атрибута «%s».', $key ) );
		velotok_extra_contract_assert( isset( $definition['mocked'] ) && true === $definition['mocked'], sprintf( 'Дополнительный атрибут «%s» не помечен mocked: true.', $key ) );
	}
}

if ( function_exists( 'velotok_core_get_product_extra_attributes' ) ) {
	$default = velotok_core_get_product_extra_attributes( 42 );

	velotok_extra_contract_assert( is_array( $default ) && ! empty( $default ), 'Заглушка должна возвращать дополнительные атрибуты для товара.' );

	$model = velotok_extra_contract_find( $default, 'model' );
	velotok_extra_contract_assert( null !== $model, 'В наборе дополнительных атрибутов отсутствует ключ «model».' );
	velotok_extra_contract_assert( null !== $model && 'Модель' === $model['label'], 'Неверная подпись атрибута «model».' );
	velotok_extra_contract_assert( null !== $model && '' !== (string) $model['value'], 'Пустое значение атрибута «model».' );
	velotok_extra_contract_assert( null !== $model && true === $model['mocked'], 'Нормализованный атрибут не помечен mocked.' );

	$battery = velotok_extra_contract_find( $default, 'battery' );
	velotok_extra_contract_assert( null !== $battery && '' !== (string) $battery['value'], 'В наборе дополнительных атрибутов отсутствует аккумулятор.' );

	$by_slug = velotok_core_get_product_extra_attributes( 'riviera' );
	$riviera = velotok_extra_contract_find( $by_slug, 'model' );
	velotok_extra_contract_assert( null !== $riviera && 'Ривьера' === $riviera['value'], 'Заглушка не учитывает slug товара.' );

	$arbitrary = velotok_core_get_product_extra_attributes(
		array(
			'Год выпуска' => '2026',
			'Пробег'      => '0 км',
		)
	);

	velotok_extra_contract_assert( 2 === count( $arbitrary ), 'Произвольные дополнительные атрибуты должны отображаться.' );
	velotok_extra_contract_assert( 'Год выпуска' === $arbitrary[0]['label'] && '2026' === $arbitrary[0]['value'], 'Произвольный атрибут с произвольным ключом потерялся.' );
	velotok_extra_contract_assert( 'Пробег' === $arbitrary[1]['label'] && '0 км' === $arbitrary[1]['value'], 'Второй произвольный атрибут потерялся.' );

	$hidden = velotok_core_get_product_extra_attributes(
		array(
			'model'   => '',
			'battery' => '48 В',
		)
	);

	velotok_extra_contract_assert( 1 === count( $hidden ), 'Пустые значения должны скрываться.' );
	velotok_extra_contract_assert( 'battery' === $hidden[0]['key'], 'После скрытия пустого значения должен остаться аккумулятор.' );

	$with_label = velotok_core_get_product_extra_attributes(
		array(
			array( 'Гарантия', '2 года' ),
		)
	);

	velotok_extra_contract_assert( 1 === count( $with_label ), 'Список пар «подпись — значение» должен поддерживаться.' );
	velotok_extra_contract_assert( 'Гарантия' === $with_label[0]['label'] && '2 года' === $with_label[0]['value'], 'Пара «подпись — значение» нормализована неверно.' );

	velotok_extra_contract_assert( velotok_core_get_product_extra_attributes( 'riviera' ) === velotok_core_product_extra_attributes( 'riviera' ), 'Алиас product_extra_attributes должен совпадать с основным доступом.' );
	velotok_extra_contract_assert( velotok_core_get_product_extra_attributes( 'riviera' ) === velotok_core_extra_attributes( 'riviera' ), 'Короткий алиас должен совпадать с основным доступом.' );
} else {
	velotok_extra_contract_assert( false, 'Отсутствует доступ к дополнительным атрибутам товара.' );
}

if ( function_exists( 'velotok_core_extra_attributes_open_questions' ) ) {
	$questions = velotok_core_extra_attributes_open_questions();

	velotok_extra_contract_assert( isset( $questions['required_set'] ), 'Не зафиксирован вопрос об обязательных атрибутах.' );
	velotok_extra_contract_assert( isset( $questions['source'] ), 'Не зафиксирован вопрос об источнике данных.' );
	velotok_extra_contract_assert( isset( $questions['filtering'] ), 'Не зафиксирован вопрос о фильтрации каталога.' );
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
);

foreach ( $forbidden as $needle ) {
	velotok_extra_contract_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт дополнительных атрибутов товара на месте.' . PHP_EOL;
