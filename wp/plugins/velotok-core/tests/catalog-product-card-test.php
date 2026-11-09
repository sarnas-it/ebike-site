<?php
/**
 * Behaviour test for TESTEB-18: the product card shows product data.
 *
 * Run from the project root:
 *   php wp/plugins/velotok-core/tests/catalog-product-card-test.php
 *
 * The catalog contract stores every product field (name, price, description,
 * category, image and characteristics). The card accessor must expose those
 * fields in a display-ready structure and render them, so the storefront never
 * shows an empty card for an existing product. The test fails without the card
 * accessor because there was no way to build a card from a catalog product.
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
function velotok_catalog_card_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$products = velotok_core_catalog_products();

velotok_catalog_card_assert( ! empty( $products ), 'Каталог не содержит товаров для проверки карточки.' );

foreach ( $products as $product ) {
	$card = velotok_core_catalog_product_card( $product );
	$name = isset( $product['name'] ) ? (string) $product['name'] : '';

	velotok_catalog_card_assert( is_array( $card ), 'Карточка не строится для товара: ' . $name );
	velotok_catalog_card_assert( isset( $card['requirement'] ) && 'TESTEB-18' === $card['requirement'], 'Карточка не ссылается на TESTEB-18.' );
	velotok_catalog_card_assert( isset( $card['name'] ) && $name === $card['name'], 'Карточка не содержит название товара: ' . $name );
	velotok_catalog_card_assert( isset( $card['price'] ) && '' !== (string) $card['price'], 'Карточка не содержит цену товара: ' . $name );
	velotok_catalog_card_assert( ! empty( $card['price_html'] ), 'Карточка не содержит отформатированную цену: ' . $name );
	velotok_catalog_card_assert( ! empty( $card['description'] ), 'Карточка не содержит описание товара: ' . $name );
	velotok_catalog_card_assert( ! empty( $card['category_label'] ), 'Карточка не содержит категорию товара: ' . $name );
	velotok_catalog_card_assert( ! empty( $card['image_html'] ) && false !== strpos( $card['image_html'], '<img' ), 'Карточка не содержит фото товара: ' . $name );
	velotok_catalog_card_assert( ! empty( $card['specs'] ), 'Карточка не содержит характеристики товара: ' . $name );
	velotok_catalog_card_assert( isset( $card['state'] ) && '' !== $card['state'], 'Карточка не содержит состояние товара: ' . $name );
	velotok_catalog_card_assert( isset( $card['fields'] ) && is_array( $card['fields'] ), 'Карточка не сохраняет исходные данные товара: ' . $name );
}

$card = velotok_core_catalog_product_card( 'riviera' );

velotok_catalog_card_assert( is_array( $card ), 'Карточка не находится по слагу товара.' );
velotok_catalog_card_assert( isset( $card['slug'] ) && 'riviera' === $card['slug'], 'Карточка потеряла слаг товара.' );
velotok_catalog_card_assert( isset( $card['name'] ) && 'Ривьера' === $card['name'], 'Карточка потеряла название товара.' );

$html = velotok_core_catalog_render_product_card( 'riviera' );

velotok_catalog_card_assert( false !== strpos( $html, 'model-card' ), 'Карточка не рендерится в HTML.' );
velotok_catalog_card_assert( false !== strpos( $html, 'Ривьера' ), 'В HTML карточки нет названия товара.' );
velotok_catalog_card_assert( false !== strpos( $html, '<img' ), 'В HTML карточки нет фото товара.' );
velotok_catalog_card_assert( false !== strpos( $html, 'Городские' ), 'В HTML карточки нет категории товара.' );
velotok_catalog_card_assert( false !== strpos( $html, '62 900' ), 'В HTML карточки нет цены товара.' );

$custom = array(
	'slug'        => 'card-test',
	'name'        => 'Карточка тест',
	'price'       => 1000,
	'category'    => 'city',
	'state'       => 'publish',
	'description' => 'Описание тестовой карточки.',
	'image'       => 'model-city-classic-1-web.jpg',
	'model'       => 'Card Model',
	'sku'         => 'CARD-1',
	'frame'       => 'Сталь',
	'warranty'    => '1 год',
	'max_load'    => '50 кг',
	'range_km'    => 10,
	'motor_w'     => 100,
	'weight_kg'   => 5,
	'wheel'       => '20″',
);

$custom_card = velotok_core_catalog_product_card( $custom );

velotok_catalog_card_assert( isset( $custom_card['sku'] ) && 'CARD-1' === $custom_card['sku'], 'Карточка не показывает SKU.' );
velotok_catalog_card_assert( isset( $custom_card['model'] ) && 'Card Model' === $custom_card['model'], 'Карточка не показывает модель.' );

$labels = array();

foreach ( $custom_card['specs'] as $spec ) {
	$labels[ $spec[0] ] = $spec[1];
}

foreach ( array( 'Артикул' => 'CARD-1', 'Модель' => 'Card Model', 'Рама' => 'Сталь', 'Гарантия' => '1 год', 'Максимальная нагрузка' => '50 кг' ) as $label => $value ) {
	velotok_catalog_card_assert( isset( $labels[ $label ] ) && $value === $labels[ $label ], 'Карточка не содержит характеристику: ' . $label );
}

$custom_html = velotok_core_catalog_render_product_card( $custom );

foreach ( array( 'Карточка тест', 'CARD-1', 'Card Model', 'Сталь', '50 кг', 'Артикул', 'Модель' ) as $value ) {
	velotok_catalog_card_assert( false !== strpos( $custom_html, $value ), 'В HTML карточки нет данных товара: ' . $value );
}

velotok_catalog_card_assert( null === velotok_core_catalog_product_card( 'missing-product' ), 'Для отсутствующего товара карточка должна быть пустой.' );
velotok_catalog_card_assert( '' === velotok_core_catalog_render_product_card( 'missing-product' ), 'Для отсутствующего товара не должен рендериться HTML.' );

velotok_catalog_card_assert( function_exists( 'catalogProductCard' ), 'Нет короткого псевдонима карточки товара.' );
velotok_catalog_card_assert( function_exists( 'catalogCardData' ), 'Нет псевдонима данных карточки товара.' );
velotok_catalog_card_assert( function_exists( 'catalogRenderProductCard' ), 'Нет псевдонима рендера карточки товара.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: карточка товара показывает данные товара (TESTEB-18).' . PHP_EOL;
