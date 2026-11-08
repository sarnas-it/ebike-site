<?php
/**
 * Behaviour test for the storefront price contract.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/catalog-negative-price-test.php
 *
 * The storefront may only show products with a non-negative numeric price. A
 * negative price (number or numeric string) is invalid and must be dropped
 * even when every other required attribute is present. A zero price stays
 * valid because the card renders it as «Цена по запросу».
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

$GLOBALS['velotok_test_meta']     = array();
$GLOBALS['velotok_test_products'] = array();
$failures                         = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_negative_price_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/*
 * Minimal WordPress API used by the live catalog path.
 */
function add_filter() { return true; }
function add_action() {}
function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_html_e( $text, $domain = null ) { echo esc_html( $text ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return (string) $url; }
function wp_kses_post( $text ) { return (string) $text; }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_trim_words( $text, $num_words = 55, $more = '…' ) {
	$words = preg_split( '/\s+/u', trim( (string) $text ) );
	if ( count( $words ) <= $num_words ) {
		return implode( ' ', $words );
	}
	return implode( ' ', array_slice( $words, 0, $num_words ) ) . $more;
}
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals, '.', ' ' ); }
function get_template_directory_uri() { return 'http://example.test/wp-content/themes/velotok'; }
function get_template_directory() { return dirname( __DIR__ ); }
function home_url( $path = '' ) { return 'http://example.test' . $path; }
function get_post_meta( $post_id, $key, $single = false ) {
	$meta = $GLOBALS['velotok_test_meta'];
	return isset( $meta[ $post_id ][ $key ] ) ? $meta[ $post_id ][ $key ] : '';
}
function get_post_thumbnail_id( $post_id ) { return 0; }
function get_the_terms( $post_id, $taxonomy ) { return array(); }
function taxonomy_exists( $taxonomy ) { return false; }
function get_term_by() { return false; }
function is_wp_error( $thing ) { return false; }
function get_term_link() { return ''; }
function wc_get_products( $args = array() ) { return $GLOBALS['velotok_test_products']; }
function wc_get_product( $id ) { return null; }
function wc_attribute_label( $name, $product = null ) { return (string) $name; }
function wc_get_product_terms() { return array(); }
function velotok_spec_value( $key, $fallback = '' ) { return $fallback; }

require_once dirname( __DIR__ ) . '/inc/woocommerce.php';

/**
 * Product double exposing a configurable price.
 */
class Velotok_Catalog_Price_Product {
	private $data;

	public function __construct( $data = array() ) {
		$this->data = $data;
	}

	public function get_id() { return isset( $this->data['id'] ) ? (int) $this->data['id'] : 0; }
	public function get_name() { return isset( $this->data['name'] ) ? $this->data['name'] : ''; }
	public function get_slug() { return isset( $this->data['slug'] ) ? $this->data['slug'] : ''; }
	public function get_status() { return isset( $this->data['status'] ) ? $this->data['status'] : 'publish'; }
	public function get_catalog_visibility() { return isset( $this->data['catalog_visibility'] ) ? $this->data['catalog_visibility'] : 'visible'; }
	public function get_permalink() { return isset( $this->data['permalink'] ) ? $this->data['permalink'] : ''; }
	public function get_price() { return array_key_exists( 'price', $this->data ) ? $this->data['price'] : 0; }
	public function get_price_html() { return isset( $this->data['price_html'] ) ? $this->data['price_html'] : ''; }
	public function get_sku() { return isset( $this->data['sku'] ) ? $this->data['sku'] : ''; }
	public function get_short_description() { return isset( $this->data['short_description'] ) ? $this->data['short_description'] : ''; }
	public function get_description() { return isset( $this->data['description'] ) ? $this->data['description'] : ''; }
	public function get_attributes() { return array(); }
	public function is_type( $type ) { return isset( $this->data['type'] ) && $type === $this->data['type']; }
	public function is_purchasable() { return ! empty( $this->data['purchasable'] ); }
	public function is_in_stock() { return ! empty( $this->data['in_stock'] ); }
	public function add_to_cart_url() { return $this->get_permalink(); }
}

/**
 * Build a published product double with the given price.
 *
 * @param int|string $price Product price.
 * @param array      $extra Extra field overrides.
 * @return Velotok_Catalog_Price_Product
 */
function velotok_negative_price_product( $price, $extra = array() ) {
	return new Velotok_Catalog_Price_Product(
		array_merge(
			array(
				'id'                 => 1,
				'name'               => 'Ривьера',
				'slug'               => 'riviera',
				'status'             => 'publish',
				'catalog_visibility' => 'visible',
				'permalink'          => 'http://example.test/product/riviera/',
				'price'              => $price,
				'price_html'         => '',
				'sku'                => 'RIV-1',
				'short_description'  => 'Городская модель.',
				'description'        => 'Полное описание городской модели.',
				'type'               => 'simple',
				'purchasable'        => true,
				'in_stock'           => true,
			),
			$extra
		)
	);
}

$negative_prices = array( -1, -0.01, '-1', '-0.01', '-100' );

foreach ( $negative_prices as $price ) {
	$product = velotok_negative_price_product( $price );

	velotok_negative_price_assert( false === velotok_product_is_valid( $product ), 'Отрицательная цена принята как валидная: ' . var_export( $price, true ) );
	velotok_negative_price_assert( false === velotok_is_valid_product( $product ), 'Отрицательная цена прошла фильтр витрины: ' . var_export( $price, true ) );
	velotok_negative_price_assert( false === velotok_product_has_valid_price( $product ), 'Проверка цены не отклонила отрицательное значение: ' . var_export( $price, true ) );
}

$invalid_prices = array( '', 'дорого', null );

foreach ( $invalid_prices as $price ) {
	$product = velotok_negative_price_product( $price );

	velotok_negative_price_assert( false === velotok_product_is_valid( $product ), 'Нечисловая или пустая цена принята как валидная: ' . var_export( $price, true ) );
}

$valid_prices = array( 0, '0', 62900, '62900', 0.5 );

foreach ( $valid_prices as $price ) {
	$product = velotok_negative_price_product( $price );

	velotok_negative_price_assert( true === velotok_product_is_valid( $product ), 'Неотрицательная цена отклонена: ' . var_export( $price, true ) );
}

$GLOBALS['velotok_test_products'] = array(
	velotok_negative_price_product( 62900, array( 'id' => 1, 'name' => 'Опубликованный', 'slug' => 'published' ) ),
	velotok_negative_price_product( -1, array( 'id' => 13, 'name' => 'Отрицательная цена', 'slug' => 'negative-price' ) ),
	velotok_negative_price_product( 0, array( 'id' => 15, 'name' => 'Без цены', 'slug' => 'zero-price' ) ),
	velotok_negative_price_product( 50000, array( 'id' => 2, 'name' => 'Черновик', 'slug' => 'draft', 'status' => 'draft' ) ),
);

$models = velotok_get_home_models();
$names  = array();

foreach ( $models as $model ) {
	$names[] = isset( $model['name'] ) ? (string) $model['name'] : '';
}

velotok_negative_price_assert( 2 === count( $models ), 'Витрина должна оставить только товары с корректной ценой.' );
velotok_negative_price_assert( in_array( 'Опубликованный', $names, true ), 'Валидный товар пропал с витрины.' );
velotok_negative_price_assert( in_array( 'Без цены', $names, true ), 'Товар с нулевой ценой должен остаться на витрине.' );
velotok_negative_price_assert( ! in_array( 'Отрицательная цена', $names, true ), 'Товар с отрицательной ценой попал на витрину.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}
	exit( 1 );
}

echo 'PASS: витрина отбрасывает отрицательную и нечисловую цену, сохраняя нулевую.' . PHP_EOL;
