<?php
/**
 * Contract test for TESTEB-16: catalog products have a model state.
 *
 * Run from the project root:
 *   php wp/plugins/velotok-core/tests/catalog-product-state-model-test.php
 *
 * The catalog contract defines a fixed product state model (the WooCommerce
 * post statuses). Every mocked catalog product must be in one of those states,
 * and unknown states must be rejected instead of being treated as published.
 *
 * @package Velotok_Core
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, 'This test must be run from the command line.' . PHP_EOL );
	exit( 1 );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/catalog.php';

$failures = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_core_catalog_state_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$expected_states = array( 'publish', 'draft', 'pending', 'private', 'trash', 'future', 'auto-draft' );

velotok_core_catalog_state_assert( function_exists( 'velotok_core_catalog_product_state_model' ), 'Не определена модель состояний товара каталога.' );
velotok_core_catalog_state_assert( function_exists( 'velotok_core_catalog_product_states' ), 'Не определён список состояний товара каталога.' );
velotok_core_catalog_state_assert( function_exists( 'velotok_core_catalog_product_state_is_valid' ), 'Не определена проверка состояния товара каталога.' );

$contract = velotok_core_catalog_contract();
$model    = velotok_core_catalog_product_state_model();
$states   = velotok_core_catalog_product_states();

velotok_core_catalog_state_assert( isset( $contract['product_states'] ) && $model === $contract['product_states'], 'Контракт не содержит модель состояний товара.' );
velotok_core_catalog_state_assert( array_keys( $model ) === $expected_states, 'Модель состояний должна повторять статусы WooCommerce.' );
velotok_core_catalog_state_assert( $states === $expected_states, 'Список состояний должен совпадать с моделью.' );
velotok_core_catalog_state_assert( isset( $contract['default_product_state'] ) && 'publish' === $contract['default_product_state'], 'Публичное состояние по умолчанию должно быть publish.' );
velotok_core_catalog_state_assert( isset( $contract['product_public_states'] ) && array( 'publish' ) === $contract['product_public_states'], 'Публичным должно быть только состояние publish.' );
velotok_core_catalog_state_assert( isset( $contract['product_state_requirement'] ) && 'TESTEB-16' === $contract['product_state_requirement'], 'Контракт ссылается на неверное требование.' );

foreach ( $expected_states as $state ) {
	velotok_core_catalog_state_assert( isset( $model[ $state ] ), 'В модели нет состояния «' . $state . '».' );
	velotok_core_catalog_state_assert( true === velotok_core_catalog_product_state_is_valid( $state ), 'Состояние модели отклонено: ' . $state );
}

foreach ( array( '', 'unknown', 'archived', 'garbage' ) as $invalid ) {
	velotok_core_catalog_state_assert( false === velotok_core_catalog_product_state_is_valid( $invalid ), 'Состояние вне модели не должно быть допустимым: ' . $invalid );
}

velotok_core_catalog_state_assert( 'publish' === velotok_core_catalog_normalize_product_state( 'published' ), 'Псевдоним publish не нормализован.' );
velotok_core_catalog_state_assert( 'future' === velotok_core_catalog_normalize_product_state( 'scheduled' ), 'Псевдоним будущего состояния не нормализован.' );
velotok_core_catalog_state_assert( 'archived' === velotok_core_catalog_normalize_product_state( 'archived' ), 'Неизвестное состояние не должно подменяться на publish.' );

$products = velotok_core_catalog_products();

velotok_core_catalog_state_assert( ! empty( $products ), 'Каталог не содержит товаров для проверки состояний.' );

foreach ( $products as $product ) {
	$name = isset( $product['name'] ) ? (string) $product['name'] : '';

	velotok_core_catalog_state_assert( isset( $product['state'] ), 'У товара нет состояния модели: ' . $name );
	velotok_core_catalog_state_assert( true === velotok_core_catalog_product_state_is_valid( $product['state'] ), 'Товар находится вне модели состояний: ' . $name );
	velotok_core_catalog_state_assert( '' !== velotok_core_catalog_product_state( $product ), 'Не удалось прочитать состояние товара: ' . $name );
}

$unknown = array(
	'name'  => 'Архив',
	'state' => 'archived',
);

velotok_core_catalog_state_assert( '' === velotok_core_catalog_product_state( $unknown ), 'Товар вне модели не должен получать допустимое состояние.' );
velotok_core_catalog_state_assert( false === velotok_core_catalog_product_state_is_valid( $unknown['state'] ), 'Неизвестное состояние товара не должно считаться допустимым.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: товар каталога находится в одном из состояний модели (TESTEB-16).' . PHP_EOL;
