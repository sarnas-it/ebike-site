<?php
/**
 * Contract test for the mocked electric bicycle catalog stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/catalog-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/catalog-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer exposes the electric bicycle catalog, its categories and
 * the mocked product attributes.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/catalog.php';

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки каталога электровелосипедов.' . PHP_EOL );
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
function velotok_catalog_contract_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$expected_categories = array( 'city', 'mountain', 'cargo', 'folding' );
$expected_slugs      = array( 'riviera', 'street-7', 'hrebet-pro', 'hrebet-trail', 'family', 'courier', 'sklad-20' );

if ( ! function_exists( 'velotok_core_catalog_contract' ) ) {
	velotok_catalog_contract_assert( false, 'Отсутствует контракт каталога электровелосипедов.' );
} else {
	$contract = velotok_core_catalog_contract();

	velotok_catalog_contract_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_catalog_contract_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_catalog_contract_assert( isset( $contract['spec'] ) && 'ecommerce-store' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_catalog_contract_assert( isset( $contract['requirement'] ) && 'TESTEB-150' === $contract['requirement'], 'Контракт ссылается на неверное требование.' );
	velotok_catalog_contract_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_catalog_contract_assert( isset( $contract['parent_spec'] ) && 'ecommerce-store' === $contract['parent_spec'], 'Не зафиксирована родительская спецификация.' );
	velotok_catalog_contract_assert( isset( $contract['product_type'] ) && 'electric_bicycle' === $contract['product_type'], 'Каталог должен описывать электровелосипеды.' );
	velotok_catalog_contract_assert( isset( $contract['catalog_type'] ) && 'product_catalog' === $contract['catalog_type'], 'Неверный тип каталога.' );

	velotok_catalog_contract_assert(
		isset( $contract['required_categories'] ) && $expected_categories === $contract['required_categories'],
		'Каталог должен содержать ровно четыре обязательные категории.'
	);

	foreach ( $expected_categories as $category ) {
		velotok_catalog_contract_assert( isset( $contract['categories'][ $category ] ), sprintf( 'Не описана категория «%s».', $category ) );
		velotok_catalog_contract_assert(
			isset( $contract['categories'][ $category ]['mocked'] ) && true === $contract['categories'][ $category ]['mocked'],
			sprintf( 'Категория «%s» не помечена mocked: true.', $category )
		);
		velotok_catalog_contract_assert(
			isset( $contract['categories'][ $category ]['label'] ) && '' !== (string) $contract['categories'][ $category ]['label'],
			sprintf( 'Не задана подпись категории «%s».', $category )
		);
	}

	foreach ( array( 'slug', 'name', 'price', 'category' ) as $field ) {
		velotok_catalog_contract_assert(
			isset( $contract['required_fields'] ) && in_array( $field, $contract['required_fields'], true ),
			sprintf( 'Не задано обязательное поле товара «%s».', $field )
		);
	}

	foreach ( array( 'name', 'price', 'range_km', 'motor_w' ) as $field ) {
		velotok_catalog_contract_assert( isset( $contract['attributes'][ $field ] ), sprintf( 'Не описан атрибут товара «%s».', $field ) );
		velotok_catalog_contract_assert(
			isset( $contract['attributes'][ $field ]['mocked'] ) && true === $contract['attributes'][ $field ]['mocked'],
			sprintf( 'Атрибут товара «%s» не помечен mocked: true.', $field )
		);
	}

	velotok_catalog_contract_assert(
		isset( $contract['products'] ) && is_array( $contract['products'] ) && count( $contract['products'] ) >= 4,
		'В заглушке должно быть минимум четыре демонстрационных электровелосипеда.'
	);
	velotok_catalog_contract_assert(
		isset( $contract['product_count'] ) && (int) $contract['product_count'] === count( $contract['products'] ),
		'Счётчик товаров каталога не совпадает со списком.'
	);
	velotok_catalog_contract_assert(
		isset( $contract['open_questions'] ) && count( $contract['open_questions'] ) >= 1,
		'Не зафиксированы открытые вопросы каталога.'
	);
}

if ( function_exists( 'velotok_core_catalog_products' ) ) {
	$products = velotok_core_catalog_products();

	velotok_catalog_contract_assert( is_array( $products ) && ! empty( $products ), 'Каталог должен возвращать товары.' );

	$seen = array();

	foreach ( $products as $product ) {
		velotok_catalog_contract_assert( is_array( $product ), 'Каждый товар каталога должен быть массивом.' );
		velotok_catalog_contract_assert( isset( $product['mocked'] ) && true === $product['mocked'], 'Товар не помечен mocked: true.' );
		velotok_catalog_contract_assert( ! empty( $product['slug'] ), 'У товара должен быть slug.' );
		velotok_catalog_contract_assert( ! empty( $product['name'] ), 'У товара должно быть название.' );
		velotok_catalog_contract_assert( isset( $product['price'] ) && is_numeric( $product['price'] ) && (float) $product['price'] >= 0, 'У товара должна быть неотрицательная цена.' );
		velotok_catalog_contract_assert( ! empty( $product['category'] ), 'У товара должна быть категория.' );
		velotok_catalog_contract_assert( isset( $product['product_type'] ) && 'electric_bicycle' === $product['product_type'], 'Товар каталога должен быть электровелосипедом.' );
		velotok_catalog_contract_assert(
			isset( $product['category'] ) && velotok_core_catalog_has_category( $product['category'] ),
			'Категория товара должна входить в каталог.'
		);
		velotok_catalog_contract_assert(
			isset( $product['range_km'] ) || isset( $product['motor_w'] ),
			'У товара должны быть характеристики (запас хода или мотор).'
		);

		if ( isset( $product['slug'] ) ) {
			$seen[] = (string) $product['slug'];
		}
	}

	foreach ( $expected_slugs as $slug ) {
		velotok_catalog_contract_assert( in_array( $slug, $seen, true ), sprintf( 'В каталоге отсутствует товар «%s».', $slug ) );
	}

	foreach ( $expected_categories as $category ) {
		$filtered = velotok_core_catalog_products( $category );

		velotok_catalog_contract_assert( ! empty( $filtered ), sprintf( 'Категория «%s» не содержит товаров.', $category ) );

		foreach ( $filtered as $product ) {
			velotok_catalog_contract_assert(
				isset( $product['category'] ) && $category === $product['category'],
				sprintf( 'Фильтр категории «%s» вернул чужой товар.', $category )
			);
		}
	}

	velotok_catalog_contract_assert( array() === velotok_core_catalog_products( 'unknown-category' ), 'Неизвестная категория должна возвращать пустой список.' );
	velotok_catalog_contract_assert( (int) velotok_core_catalog_count() === count( $products ), 'Счётчик каталога должен совпадать с количеством товаров.' );

	$riviera = velotok_core_catalog_product( 'riviera' );
	velotok_catalog_contract_assert( is_array( $riviera ), 'Товар должен находиться по slug.' );
	velotok_catalog_contract_assert( null === velotok_core_catalog_product( 'unknown-product' ), 'Неизвестный товар должен возвращать null.' );
	velotok_catalog_contract_assert( true === velotok_core_catalog_has_product( 'riviera' ), 'Существующий товар должен находиться проверкой наличия.' );

	velotok_catalog_contract_assert(
		velotok_core_catalog_products( 'city' ) === velotok_core_get_catalog_products( 'city' ),
		'Алиас get_catalog_products должен совпадать с основным доступом.'
	);
} else {
	velotok_catalog_contract_assert( false, 'Отсутствует доступ к товарам каталога.' );
}

if ( function_exists( 'velotok_core_catalog_categories' ) ) {
	$categories = velotok_core_catalog_categories();

	foreach ( $expected_categories as $category ) {
		velotok_catalog_contract_assert( isset( $categories[ $category ] ), sprintf( 'Категория «%s» недоступна через контракт.', $category ) );
	}

	velotok_catalog_contract_assert( '' !== velotok_core_catalog_category_label( 'city' ), 'У категории должна быть подпись.' );
	velotok_catalog_contract_assert( '' === velotok_core_catalog_category_label( 'unknown-category' ), 'У неизвестной категории не должно быть подписи.' );
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
	velotok_catalog_contract_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт каталога электровелосипедов на месте.' . PHP_EOL;
