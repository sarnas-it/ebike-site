<?php
/**
 * Behaviour test for TESTEB-59: catalog sorting uses a fixed set of values.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/catalog-sorting-test.php
 *
 * The test fails without the sorting contract: unknown orderby values are not
 * normalised, the archive does not build its select from the contract and the
 * WooCommerce query receives arbitrary client values.
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

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) { return $text; }
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {}
}

require_once dirname( __DIR__ ) . '/inc/catalog-filters.php';

$GLOBALS['velotok_sorting_failures'] = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_sorting_assert( $condition, $message ) {
	if ( ! $condition ) {
		$GLOBALS['velotok_sorting_failures'][] = $message;
	}
}

/**
 * Minimal WP_Query double that records query vars.
 */
class Velotok_Sorting_Query {
	public $vars = array();

	public function set( $key, $value ) {
		$this->vars[ $key ] = $value;
	}

	public function get( $key, $default = '' ) {
		return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : $default;
	}
}

/*
 * 1. The contract exposes one fixed set of sorting values.
 */
velotok_sorting_assert( function_exists( 'velotok_catalog_sorting' ), 'Не определён фиксированный набор сортировок.' );
velotok_sorting_assert( function_exists( 'velotok_catalog_sorting_options' ), 'Не определены значения фиксированного набора сортировок.' );
velotok_sorting_assert( function_exists( 'velotok_catalog_normalize_orderby' ), 'Не определена нормализация orderby.' );

$sorting = velotok_catalog_sorting();
$options = velotok_catalog_sorting_options();
$contract = velotok_catalog_filter_contract();

velotok_sorting_assert( $sorting === $options, 'Псевдоним сортировки должен возвращать тот же фиксированный набор.' );
velotok_sorting_assert( isset( $contract['sorting'] ) && $sorting === $contract['sorting'], 'Контракт не содержит фиксированный набор сортировок.' );
velotok_sorting_assert( isset( $contract['sorting_parameter'] ) && 'orderby' === $contract['sorting_parameter'], 'Параметр сортировки должен называться orderby.' );

$expected = array( 'menu_order', 'popularity', 'rating', 'date', 'price', 'price-desc' );
velotok_sorting_assert( array_keys( $sorting ) === $expected, 'Набор сортировок должен быть фиксированным: ' . implode( ', ', array_keys( $sorting ) ) );
velotok_sorting_assert( $expected === velotok_catalog_sorting_keys(), 'Допустимые значения orderby не совпадают с набором.' );
velotok_sorting_assert( $sorting === velotok_catalog_sorting(), 'Набор сортировок должен быть неизменным между вызовами.' );

/*
 * 2. Only values from the fixed set survive normalisation.
 */
foreach ( $expected as $orderby ) {
	velotok_sorting_assert( $orderby === velotok_catalog_normalize_orderby( $orderby ), 'Потеряно допустимое значение сортировки: ' . $orderby );
}

foreach ( array( '', 'unknown', 'price;drop', array( 'price' ), null ) as $invalid ) {
	velotok_sorting_assert( 'menu_order' === velotok_catalog_normalize_orderby( $invalid ), 'Недопустимое значение orderby не заменено на значение по умолчанию.' );
}

velotok_sorting_assert( 'price-desc' === velotok_catalog_request_orderby( array( 'orderby' => 'price-desc' ) ), 'Допустимый orderby не читается из запроса.' );
velotok_sorting_assert( 'menu_order' === velotok_catalog_request_orderby( array( 'orderby' => 'hacked' ) ), 'Недопустимый orderby из запроса не заменён.' );
velotok_sorting_assert( 'menu_order' === velotok_catalog_request_orderby( array() ), 'Без orderby должна применяться сортировка по умолчанию.' );

$request = velotok_catalog_request_query( array( 'orderby' => 'price-desc' ) );
velotok_sorting_assert( isset( $request['orderby'] ) && 'price-desc' === $request['orderby'], 'Разбор запроса не возвращает нормализованный orderby.' );

/*
 * 3. The WooCommerce query receives only contract sorting rules.
 */
$query = new Velotok_Sorting_Query();
velotok_catalog_apply_sorting( $query, 'price' );
velotok_sorting_assert( '_price' === $query->get( 'meta_key' ), 'Сортировка по цене не использует мета-поле цены.' );
velotok_sorting_assert( 'meta_value_num' === $query->get( 'orderby' ) && 'ASC' === $query->get( 'order' ), 'Сортировка по возрастанию цены задана неверно.' );

$query = new Velotok_Sorting_Query();
velotok_catalog_apply_sorting( $query, 'price-desc' );
velotok_sorting_assert( 'meta_value_num' === $query->get( 'orderby' ) && 'DESC' === $query->get( 'order' ), 'Сортировка по убыванию цены задана неверно.' );

$query = new Velotok_Sorting_Query();
velotok_catalog_apply_sorting( $query, 'unknown' );
velotok_sorting_assert( 'menu_order' === $query->get( 'orderby' ), 'Недопустимая сортировка не заменена на menu_order.' );

/*
 * 4. The archive template builds the select from the contract.
 */
$archive = (string) file_get_contents( dirname( __DIR__ ) . '/woocommerce/archive-product.php' );
velotok_sorting_assert( false !== strpos( $archive, 'velotok_catalog_sorting' ), 'Архив не использует фиксированный набор сортировок.' );
velotok_sorting_assert( false !== strpos( $archive, 'velotok_catalog_request_orderby' ), 'Архив не нормализует orderby из запроса.' );

if ( $GLOBALS['velotok_sorting_failures'] ) {
	fwrite( STDERR, 'FAIL: ' . count( $GLOBALS['velotok_sorting_failures'] ) . ' проверок сортировки каталога не прошли:' . PHP_EOL );

	foreach ( $GLOBALS['velotok_sorting_failures'] as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: сортировка каталога поддерживает фиксированный набор значений (TESTEB-59).' . PHP_EOL;
exit( 0 );
