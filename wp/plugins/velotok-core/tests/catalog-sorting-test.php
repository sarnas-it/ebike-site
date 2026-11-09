<?php
/**
 * Behaviour test for TESTEB-21: catalog sorting supports price ascending,
 * price descending and novelty.
 *
 * Run from the project root:
 *   php wp/plugins/velotok-core/tests/catalog-sorting-test.php
 *
 * The test fails without the sorting contract: price-desc and date are not in
 * the fixed set, unknown values are not normalised and the catalog accessor
 * cannot order products by price or publication date.
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
function velotok_catalog_sorting_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Return product names in list order.
 *
 * @param array $products Catalog products.
 * @return string[]
 */
function velotok_catalog_sorting_names( $products ) {
	$names = array();

	foreach ( (array) $products as $product ) {
		if ( isset( $product['name'] ) ) {
			$names[] = (string) $product['name'];
		}
	}

	return $names;
}

/*
 * 1. The contract exposes a fixed sorting set with price and novelty values.
 */
velotok_catalog_sorting_assert( function_exists( 'velotok_core_catalog_sorting_options' ), 'Не определён фиксированный набор сортировок каталога.' );
velotok_catalog_sorting_assert( function_exists( 'velotok_core_catalog_normalize_orderby' ), 'Не определена нормализация orderby.' );
velotok_catalog_sorting_assert( function_exists( 'velotok_core_catalog_request_orderby' ), 'Не определено чтение orderby из запроса.' );

$sorting  = velotok_core_catalog_sorting();
$contract = velotok_core_catalog_contract();

velotok_catalog_sorting_assert( isset( $contract['sorting_requirement'] ) && 'TESTEB-21' === $contract['sorting_requirement'], 'Контракт должен ссылаться на TESTEB-21.' );
velotok_catalog_sorting_assert( isset( $contract['sorting_parameter'] ) && 'orderby' === $contract['sorting_parameter'], 'Параметр сортировки должен называться orderby.' );
velotok_catalog_sorting_assert( isset( $contract['sorting'] ) && $sorting === $contract['sorting'], 'Контракт не содержит фиксированный набор сортировок.' );

foreach ( array( 'price', 'price-desc', 'date' ) as $required ) {
	velotok_catalog_sorting_assert( isset( $sorting[ $required ] ), 'В наборе сортировок нет значения: ' . $required );
	velotok_catalog_sorting_assert( in_array( $required, velotok_core_catalog_sorting_keys(), true ), 'Значение не входит в допустимые сортировки: ' . $required );
}

velotok_catalog_sorting_assert( 'price-desc' === velotok_core_catalog_normalize_orderby( 'price-desc' ), 'Сортировка по убыванию цены не нормализуется.' );
velotok_catalog_sorting_assert( 'date' === velotok_core_catalog_normalize_orderby( 'date' ), 'Сортировка по новинкам не нормализуется.' );

foreach ( array( '', 'unknown', 'price;drop', array( 'price' ), null ) as $invalid ) {
	velotok_catalog_sorting_assert( 'menu_order' === velotok_core_catalog_normalize_orderby( $invalid ), 'Недопустимое значение orderby не заменено на значение по умолчанию.' );
}

velotok_catalog_sorting_assert( 'price-desc' === velotok_core_catalog_request_orderby( array( 'orderby' => 'price-desc' ) ), 'Допустимый orderby не читается из запроса.' );
velotok_catalog_sorting_assert( 'date' === velotok_core_catalog_request_orderby( array( 'orderby' => 'date' ) ), 'Сортировка по новинкам не читается из запроса.' );
velotok_catalog_sorting_assert( 'menu_order' === velotok_core_catalog_request_orderby( array( 'orderby' => 'hacked' ) ), 'Недопустимый orderby из запроса не заменён.' );

/*
 * 2. Price sorting works in both directions.
 */
$ascending = velotok_catalog_sorting_names( velotok_core_catalog_products( array( 'orderby' => 'price' ) ) );
$expected_ascending = array( 'Ривьера', 'Складень 20', 'Стрит 7', 'Хребет Trail', 'Курьер', 'Семейный', 'Хребет Pro' );

velotok_catalog_sorting_assert( $expected_ascending === $ascending, 'Сортировка по возрастанию цены неверна: ' . implode( ', ', $ascending ) );

$descending = velotok_catalog_sorting_names( velotok_core_catalog_products( array( 'orderby' => 'price-desc' ) ) );
$expected_descending = array_reverse( $expected_ascending );

velotok_catalog_sorting_assert( $expected_descending === $descending, 'Сортировка по убыванию цены неверна: ' . implode( ', ', $descending ) );

$explicit_desc = velotok_catalog_sorting_names(
	velotok_core_catalog_products(
		array(
			'orderby' => 'price',
			'order'   => 'desc',
		)
	)
);
velotok_catalog_sorting_assert( $expected_descending === $explicit_desc, 'Явное направление desc не применяется к сортировке по цене.' );

/*
 * 3. Novelty sorting puts the newest products first.
 */
$novelty = velotok_catalog_sorting_names( velotok_core_catalog_products( array( 'orderby' => 'date' ) ) );
$expected_novelty = array( 'Складень 20', 'Курьер', 'Семейный', 'Хребет Trail', 'Хребет Pro', 'Стрит 7', 'Ривьера' );

velotok_catalog_sorting_assert( $expected_novelty === $novelty, 'Сортировка по новинкам неверна: ' . implode( ', ', $novelty ) );

/*
 * 4. Sorting combines with category filters and limits.
 */
$city = velotok_catalog_sorting_names(
	velotok_core_catalog_products(
		array(
			'category' => 'city',
			'orderby'  => 'price-desc',
			'limit'    => 1,
		)
	)
);

velotok_catalog_sorting_assert( array( 'Стрит 7' ) === $city, 'Сортировка не сочетается с категорией и лимитом.' );

$unknown = velotok_catalog_sorting_names( velotok_core_catalog_products( array( 'orderby' => 'unknown' ) ) );
$default = velotok_catalog_sorting_names( velotok_core_catalog_products() );

velotok_catalog_sorting_assert( $default === $unknown, 'Недопустимая сортировка должна сохранять порядок каталога.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок сортировки каталога не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: сортировка каталога поддерживает цену по возрастанию, убыванию и новинки (TESTEB-21).' . PHP_EOL;
exit( 0 );
