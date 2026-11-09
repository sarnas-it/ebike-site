<?php
/**
 * Behaviour test for TESTEB-20: catalog filters are built from the contract.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/catalog-filters-contract-test.php
 *
 * The test checks that filter definitions and controls come from the catalog
 * filter contract instead of a hardcoded template list: every filterable
 * attribute gets a control with its contract label and type, unknown filters
 * are rejected, and the archive template uses the contract-driven controls.
 *
 * @package Velotok
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, 'This test must be run from the command line.' . PHP_EOL );
	exit( 1 );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_filter_contract_failures'] = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_filter_contract_assert( $condition, $message ) {
	if ( ! $condition ) {
		$GLOBALS['velotok_filter_contract_failures'][] = $message;
	}
}

/*
 * Minimal WordPress API used by the catalog filter path.
 */
function add_filter() { return true; }
function add_action() {}
function __( $text, $domain = null ) { return $text; }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_key( $key ) { return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }

require_once dirname( __DIR__ ) . '/inc/catalog-filters.php';

$products = array(
	array(
		'name'       => 'Ривьера',
		'price'      => 62900,
		'category'   => 'city',
		'range_km'   => 90,
		'motor_w'    => 250,
		'weight_kg'  => 23,
		'wheel'      => '28″',
		'badge'      => 'Хит',
		'attributes' => array(
			array( 'label' => 'Модель', 'value' => 'Riviera City' ),
			array( 'label' => 'Рама', 'value' => 'Алюминий 6061' ),
		),
	),
	array(
		'name'       => 'Хребет Pro',
		'price'      => 149900,
		'category'   => 'mountain',
		'range_km'   => 120,
		'motor_w'    => 750,
		'weight_kg'  => 25,
		'wheel'      => '29″',
		'badge'      => 'Для гор',
		'attributes' => array(
			array( 'label' => 'Модель', 'value' => 'Hrebet Pro' ),
			array( 'label' => 'Рама', 'value' => 'Алюминий 6061' ),
		),
	),
	array(
		'name'       => 'Семейный',
		'price'      => 132900,
		'category'   => 'cargo',
		'range_km'   => 80,
		'motor_w'    => 750,
		'weight_kg'  => 32,
		'wheel'      => '20″ / 26″',
		'badge'      => 'Для семьи',
		'attributes' => array(
			array( 'label' => 'Модель', 'value' => 'Family Cargo' ),
			array( 'label' => 'Рама', 'value' => 'Сталь' ),
		),
	),
);

/*
 * 1. The contract declares the requirement and one definition per filterable
 *    attribute. Nothing outside the contract becomes a filter.
 */
velotok_filter_contract_assert( function_exists( 'velotok_catalog_filter_contract' ), 'Не определён контракт фильтров каталога.' );
velotok_filter_contract_assert( function_exists( 'velotok_catalog_filter_controls' ), 'Не определены фильтры, построенные по контракту.' );

$contract = velotok_catalog_filter_contract();

velotok_filter_contract_assert( isset( $contract['filter_requirement'] ) && 'TESTEB-20' === $contract['filter_requirement'], 'Контракт должен ссылаться на TESTEB-20.' );
velotok_filter_contract_assert( isset( $contract['filter_parameter'] ) && 'velotok_filter' === $contract['filter_parameter'], 'Параметр фильтров должен называться velotok_filter.' );
velotok_filter_contract_assert( isset( $contract['filters'] ) && is_array( $contract['filters'] ), 'В контракте нет раздела фильтров.' );
velotok_filter_contract_assert( $contract['filterable_attributes'] === array_keys( $contract['filters'] ), 'Список фильтруемых атрибутов не совпадает с определениями фильтров.' );

foreach ( $contract['filters'] as $key => $definition ) {
	velotok_filter_contract_assert( isset( $definition['key'] ) && $key === $definition['key'], 'У фильтра неверный ключ: ' . $key );
	velotok_filter_contract_assert( isset( $definition['label'] ) && '' !== $definition['label'], 'У фильтра нет подписи: ' . $key );
	velotok_filter_contract_assert( isset( $definition['type'] ) && '' !== $definition['type'], 'У фильтра нет типа: ' . $key );
	velotok_filter_contract_assert( isset( $definition['control'] ) && in_array( $definition['control'], array( 'text', 'number', 'range', 'taxonomy' ), true ), 'У фильтра неверный элемент управления: ' . $key );
	velotok_filter_contract_assert( isset( $definition['query_parameter'] ) && 'velotok_filter[' . $key . ']' === $definition['query_parameter'], 'У фильтра неверный параметр запроса: ' . $key );
}

velotok_filter_contract_assert( array() === velotok_catalog_filter_options( 'unknown', $products ), 'Неизвестный атрибут не должен давать значения фильтра.' );
velotok_filter_contract_assert( in_array( 'Хит', velotok_catalog_filter_options( 'badge', $products ), true ), 'Значения бейджа не строятся по контракту.' );
velotok_filter_contract_assert( in_array( 'city', velotok_catalog_filter_options( 'category', $products ), true ), 'Значения категории не строятся по контракту.' );

/*
 * 2. Controls are generated from contract definitions only.
 */
$controls = velotok_catalog_filter_controls( $products, array() );

velotok_filter_contract_assert( array_keys( $controls ) === array_keys( $contract['filters'] ), 'Набор элементов управления не совпадает с контрактом.' );
velotok_filter_contract_assert( ! isset( $controls['unknown'] ), 'В фильтры попал атрибут вне контракта.' );
velotok_filter_contract_assert( 'range' === $controls['range_km']['control'], 'Числовой диапазон должен строиться как range.' );
velotok_filter_contract_assert( 80.0 === (float) $controls['range_km']['min'] && 120.0 === (float) $controls['range_km']['max'], 'Границы диапазона не построены по товарам.' );
velotok_filter_contract_assert( in_array( 'Хит', $controls['badge']['options'], true ), 'Варианты бейджа не попали в элемент управления.' );

/*
 * 3. Request filters are sanitized by the contract, including nested seller
 *    attributes: unknown keys never reach filtering.
 */
$filters = velotok_catalog_sanitize_filters(
	array(
		'velotok_filter' => array(
			'badge'        => 'Хит',
			'range_km_min' => '80',
			'range_km_max' => '100',
			'unknown'      => 'ignore-me',
			'attributes'   => array(
				'frame'         => 'Сталь',
				'unknown_field' => 'ignore-me',
			),
		),
	)
);

velotok_filter_contract_assert( isset( $filters['badge'] ) && 'Хит' === $filters['badge'], 'Фильтр по бейджу не разобран.' );
velotok_filter_contract_assert( isset( $filters['range_km']['min'], $filters['range_km']['max'] ), 'Диапазон не разобран.' );
velotok_filter_contract_assert( ! isset( $filters['unknown'] ), 'Неизвестный фильтр не отброшен.' );
velotok_filter_contract_assert( isset( $filters['attributes']['frame'] ) && 'Сталь' === $filters['attributes']['frame'], 'Вложенный атрибут контракта потерян.' );
velotok_filter_contract_assert( ! isset( $filters['attributes']['unknown_field'] ), 'Вложенный неизвестный атрибут не отброшен.' );

velotok_filter_contract_assert( velotok_catalog_product_matches_filters( $products[2], array( 'badge' => 'Для семьи' ) ), 'Фильтр по контракту не находит товар.' );
velotok_filter_contract_assert( ! velotok_catalog_product_matches_filters( $products[2], array( 'badge' => 'Хит' ) ), 'Фильтр по контракту пропускает чужой товар.' );

/*
 * 4. Rendering and the archive template use the contract-driven controls.
 */
$markup = velotok_catalog_render_filter_controls( $products, array( 'badge' => 'Хит' ) );

foreach ( array_keys( $contract['filters'] ) as $key ) {
	if ( 'category' === $key ) {
		continue;
	}

	velotok_filter_contract_assert( false !== strpos( $markup, 'name="velotok_filter[' . $key . ']"' ) || false !== strpos( $markup, 'name="velotok_filter[' . $key . '_min]"' ), 'В разметке нет фильтра: ' . $key );
}

velotok_filter_contract_assert( false !== strpos( $markup, 'value="Хит"' ), 'Активное значение фильтра не попало в разметку.' );

$archive = (string) file_get_contents( dirname( __DIR__ ) . '/woocommerce/archive-product.php' );

velotok_filter_contract_assert( false !== strpos( $archive, 'velotok_catalog_filter_contract_attributes' ), 'Архив не читает атрибуты контракта.' );
velotok_filter_contract_assert( false !== strpos( $archive, 'velotok_catalog_filter_controls' ), 'Архив не строит фильтры через контрактные элементы управления.' );
velotok_filter_contract_assert( false !== strpos( $archive, 'name="velotok_filter[' ), 'Архив не выводит фильтры контракта.' );

if ( $GLOBALS['velotok_filter_contract_failures'] ) {
	fwrite( STDERR, 'FAIL: ' . count( $GLOBALS['velotok_filter_contract_failures'] ) . ' проверок фильтров по контракту не прошли:' . PHP_EOL );

	foreach ( $GLOBALS['velotok_filter_contract_failures'] as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: фильтры каталога строятся по контракту (TESTEB-20).' . PHP_EOL;
exit( 0 );
