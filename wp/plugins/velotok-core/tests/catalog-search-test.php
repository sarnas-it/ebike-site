<?php
/**
 * Behaviour test for TESTEB-19: catalog search by name, description, model,
 * SKU and category.
 *
 * Run from the project root:
 *   php wp/plugins/velotok-core/tests/catalog-search-test.php
 *
 * The test fails on the previous catalog implementation because the products
 * accessor ignored the search argument and there was no search matcher at all.
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
function velotok_core_catalog_search_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Return the names of a product list.
 *
 * @param array $products Catalog products.
 * @return string[]
 */
function velotok_core_catalog_search_names( $products ) {
	$names = array();

	foreach ( (array) $products as $product ) {
		if ( isset( $product['name'] ) ) {
			$names[] = (string) $product['name'];
		}
	}

	return $names;
}

/*
 * 1. Search by name, description and category in the mocked catalog.
 */
$cases = array(
	'Ривьера'          => array( 'name', 1 ),
	'кожаным седлом'   => array( 'description', 1 ),
	'Городские'        => array( 'category name', 2 ),
	'mountain'         => array( 'category slug', 2 ),
	'подвеска'         => array( 'description', 1 ),
);

foreach ( $cases as $query => $expectation ) {
	list( $label, $count ) = $expectation;
	$found = velotok_core_catalog_products( array( 'search' => $query ) );

	velotok_core_catalog_search_assert(
		$count === count( $found ),
		'Поиск по полю «' . $label . '» вернул неверное число товаров для «' . $query . '».'
	);
}

$riviera = velotok_core_catalog_products( array( 'search' => 'Ривьера' ) );
velotok_core_catalog_search_assert(
	isset( $riviera[0]['name'] ) && 'Ривьера' === $riviera[0]['name'],
	'Поиск по названию вернул не тот товар.'
);

$empty = velotok_core_catalog_products( array( 'search' => 'такого-товара-нет' ) );
velotok_core_catalog_search_assert( array() === $empty, 'Неизвестный запрос должен возвращать пустой список.' );

/*
 * 2. Model and SKU are searchable contract attributes when present.
 */
$with_model = array(
	'name'        => 'Ривьера',
	'description' => 'Городской электровелосипед.',
	'model'       => 'Riviera City',
	'sku'         => 'RIV-1',
	'category'    => 'city',
);

velotok_core_catalog_search_assert(
	velotok_core_catalog_product_matches_search( $with_model, 'Riviera City' ),
	'Поиск не находит товар по модели.'
);
velotok_core_catalog_search_assert(
	velotok_core_catalog_product_matches_search( $with_model, 'RIV-1' ),
	'Поиск не находит товар по SKU.'
);
velotok_core_catalog_search_assert(
	velotok_core_catalog_product_matches_search( $with_model, 'городской RIV-1' ),
	'Поиск не находит товар по нескольким словам из разных полей.'
);
velotok_core_catalog_search_assert(
	false === velotok_core_catalog_product_matches_search( $with_model, 'Riviera Mountain' ),
	'Поиск не должен находить товар по отсутствующей модели.'
);

/*
 * 3. Search combines with category, ordering and limits.
 */
$city = velotok_core_catalog_products(
	array(
		'search'  => 'Городские',
		'orderby' => 'price',
		'order'   => 'desc',
		'limit'   => 1,
	)
);

velotok_core_catalog_search_assert( 1 === count( $city ), 'Поиск не сочетается с лимитом.' );
velotok_core_catalog_search_assert(
	isset( $city[0]['name'] ) && 'Стрит 7' === $city[0]['name'],
	'Поиск не сочетается с сортировкой по цене.'
);

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок поиска каталога не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: поиск каталога находит товары по названию, описанию, модели, SKU и категории (TESTEB-19).' . PHP_EOL;
exit( 0 );
