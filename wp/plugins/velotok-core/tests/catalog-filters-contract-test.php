<?php
/**
 * Behaviour test for TESTEB-20: catalog filters are built from the contract.
 *
 * Run from the project root:
 *   php wp/plugins/velotok-core/tests/catalog-filters-contract-test.php
 *
 * The plugin catalog contract declares filterable attributes and their
 * controls. This test checks that filter definitions, values, sanitizing and
 * product matching are driven by that contract, so unknown filters are ignored
 * and every supported filter comes from the contract rather than the caller.
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
function velotok_catalog_filters_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = velotok_core_catalog_contract();

/*
 * 1. Filter definitions come from the catalog contract.
 */
velotok_catalog_filters_assert( function_exists( 'velotok_core_catalog_filter_contract' ), 'Не определён контракт фильтров каталога.' );
velotok_catalog_filters_assert( function_exists( 'velotok_core_catalog_filter_controls' ), 'Не определены фильтры, построенные по контракту.' );
velotok_catalog_filters_assert( isset( $contract['filter_requirement'] ) && 'TESTEB-20' === $contract['filter_requirement'], 'Контракт должен ссылаться на TESTEB-20.' );
velotok_catalog_filters_assert( isset( $contract['filter_parameter'] ) && 'velotok_filter' === $contract['filter_parameter'], 'Параметр фильтров должен называться velotok_filter.' );
velotok_catalog_filters_assert( isset( $contract['filters'] ) && is_array( $contract['filters'] ), 'В контракте нет раздела фильтров.' );
velotok_catalog_filters_assert( $contract['filterable_attributes'] === array_keys( $contract['filters'] ), 'Список фильтруемых атрибутов не совпадает с определениями фильтров.' );

foreach ( $contract['filters'] as $key => $definition ) {
	velotok_catalog_filters_assert( isset( $definition['key'] ) && $key === $definition['key'], 'У фильтра неверный ключ: ' . $key );
	velotok_catalog_filters_assert( isset( $definition['label'] ) && '' !== $definition['label'], 'У фильтра нет подписи: ' . $key );
	velotok_catalog_filters_assert( isset( $definition['type'] ) && '' !== $definition['type'], 'У фильтра нет типа: ' . $key );
	velotok_catalog_filters_assert( isset( $definition['control'] ) && in_array( $definition['control'], array( 'text', 'number', 'range', 'taxonomy' ), true ), 'У фильтра неверный элемент управления: ' . $key );
	velotok_catalog_filters_assert( isset( $definition['query_parameter'] ) && 'velotok_filter[' . $key . ']' === $definition['query_parameter'], 'У фильтра неверный параметр запроса: ' . $key );
}

velotok_catalog_filters_assert( in_array( 'category', velotok_core_catalog_filterable_attributes(), true ), 'Категория должна быть фильтруемым атрибутом.' );
velotok_catalog_filters_assert( in_array( 'badge', velotok_core_catalog_filterable_attributes(), true ), 'Бейдж должен быть фильтруемым атрибутом.' );
velotok_catalog_filters_assert( array() === velotok_core_catalog_filter_options( 'unknown' ), 'Неизвестный атрибут не должен давать значения фильтра.' );

$products = velotok_core_catalog_products( array( 'include_invalid' => true ) );

/*
 * 2. Controls and values are built from the contract and products.
 */
$controls = velotok_core_catalog_filter_controls( $products, array() );

velotok_catalog_filters_assert( array_keys( $controls ) === array_keys( $contract['filters'] ), 'Набор элементов управления не совпадает с контрактом.' );
velotok_catalog_filters_assert( ! isset( $controls['unknown'] ), 'В фильтры попал атрибут вне контракта.' );
velotok_catalog_filters_assert( 'range' === $controls['range_km']['control'], 'Числовой диапазон должен строиться как range.' );
velotok_catalog_filters_assert( ! empty( $controls['badge']['options'] ), 'Варианты бейджа не построены из товаров.' );
velotok_catalog_filters_assert( ! empty( $controls['category']['options'] ), 'Варианты категории не построены из товаров.' );

/*
 * 3. Unknown filter keys, including nested ones, never reach filtering.
 */
$filters = velotok_core_catalog_sanitize_filters(
	array(
		'velotok_filter' => array(
			'badge'        => 'Хит',
			'range_km_min' => '60',
			'range_km_max' => '100',
			'unknown'      => 'ignore-me',
			'attributes'   => array( 'unknown_field' => 'ignore-me' ),
		),
	)
);

velotok_catalog_filters_assert( isset( $filters['badge'] ) && 'Хит' === $filters['badge'], 'Фильтр по бейджу не разобран.' );
velotok_catalog_filters_assert( isset( $filters['range_km']['min'], $filters['range_km']['max'] ), 'Диапазон не разобран.' );
velotok_catalog_filters_assert( ! isset( $filters['unknown'] ), 'Неизвестный фильтр не отброшен.' );
velotok_catalog_filters_assert( empty( $filters['attributes'] ), 'Неизвестный вложенный фильтр не отброшен.' );

$filtered = velotok_core_catalog_products(
	array(
		'category' => 'city',
		'filters'  => array( 'range_km' => array( 'min' => 80, 'max' => 100 ) ),
	)
);

velotok_catalog_filters_assert( ! empty( $filtered ), 'Фильтр по контракту не вернул подходящий товар.' );

foreach ( $filtered as $product ) {
	velotok_catalog_filters_assert( 'city' === $product['category'], 'Фильтр по категории пропустил чужой товар.' );
	velotok_catalog_filters_assert( (float) $product['range_km'] >= 80 && (float) $product['range_km'] <= 100, 'Фильтр по диапазону пропустил товар вне границ.' );
}

velotok_catalog_filters_assert( array() === velotok_core_catalog_products( array( 'filters' => array( 'badge' => 'Несуществующий бейдж' ) ) ), 'Фильтр по несуществующему значению должен вернуть пустой список.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок фильтров по контракту не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: фильтры каталога строятся по контракту (TESTEB-20).' . PHP_EOL;
exit( 0 );
