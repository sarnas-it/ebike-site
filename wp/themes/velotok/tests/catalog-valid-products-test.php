<?php
/**
 * Behaviour test for TESTEB-51: only valid published products are shown.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/catalog-valid-products-test.php
 *
 * WooCommerce normally limits the catalog query to published products. This
 * test deliberately makes the query return everything, including drafts,
 * private/trashed products, hidden products and published records with invalid
 * required data, and checks that the storefront filters them out.
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
function velotok_valid_products_assert( $condition, $message ) {
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
function wc_get_products( $args = array() ) {
	return $GLOBALS['velotok_test_products'];
}
function wc_get_product( $id ) { return null; }
function wc_attribute_label( $name, $product = null ) { return (string) $name; }
function wc_get_product_terms() { return array(); }
function velotok_spec_value( $key, $fallback = '' ) { return $fallback; }
function get_template_part( $slug, $name = null, $args = array() ) {
	$file = dirname( __DIR__ ) . '/' . $slug . '.php';
	if ( is_file( $file ) ) {
		include $file;
	}
}

if ( ! class_exists( 'WC_Product' ) ) {
	/**
	 * Minimal WooCommerce product double with publish state.
	 */
	class WC_Product {
		private $data;

		public function __construct( $data = array() ) {
			$this->data = $data;
		}

		public function get_id() { return isset( $this->data['id'] ) ? (int) $this->data['id'] : 0; }
		public function get_name() { return isset( $this->data['name'] ) ? $this->data['name'] : ''; }
		public function get_slug() { return isset( $this->data['slug'] ) ? $this->data['slug'] : ''; }
		public function get_status() { return isset( $this->data['status'] ) ? $this->data['status'] : 'publish'; }
		public function is_visible() { return ! isset( $this->data['visible'] ) || (bool) $this->data['visible']; }
		public function get_permalink() { return isset( $this->data['permalink'] ) ? $this->data['permalink'] : ''; }
		public function get_price() { return isset( $this->data['price'] ) ? $this->data['price'] : 0; }
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
}

require_once dirname( __DIR__ ) . '/inc/woocommerce.php';

/**
 * Build a product fixture.
 *
 * @param int          $id     Product ID.
 * @param string       $name   Product name.
 * @param string       $status Post status.
 * @param mixed        $price  Product price.
 * @param bool         $visible Catalog visibility.
 * @return WC_Product
 */
function velotok_valid_products_fixture( $id, $name, $status = 'publish', $price = 50000, $visible = true ) {
	return new WC_Product(
		array(
			'id'                => $id,
			'name'              => $name,
			'slug'              => 'model-' . $id,
			'status'            => $status,
			'visible'           => $visible,
			'permalink'         => 'http://example.test/product/model-' . $id . '/',
			'price'             => $price,
			'price_html'        => is_numeric( $price ) ? number_format_i18n( $price, 0 ) . ' ₽' : '',
			'sku'               => 'MOD-' . $id,
			'short_description' => 'Описание модели ' . $id . '.',
			'description'       => '',
			'type'              => 'simple',
			'purchasable'       => true,
			'in_stock'          => true,
		)
	);
}

$GLOBALS['velotok_test_products'] = array(
	velotok_valid_products_fixture( 1, 'Опубликованная модель' ),
	velotok_valid_products_fixture( 2, 'Черновик', 'draft' ),
	velotok_valid_products_fixture( 3, 'Приватная модель', 'private' ),
	velotok_valid_products_fixture( 4, 'Удалённая модель', 'trash' ),
	velotok_valid_products_fixture( 5, 'Скрытая из каталога', 'publish', 50000, false ),
	velotok_valid_products_fixture( 6, '', 'publish' ),
	velotok_valid_products_fixture( 7, 'Невалидная цена', 'publish', '-100' ),
	velotok_valid_products_fixture( 8, 'Цена-строка', 'publish', 'дорого' ),
);

$models = velotok_get_home_models();

velotok_valid_products_assert( 1 === count( $models ), 'На главной должна остаться только одна валидная опубликованная модель.' );
velotok_valid_products_assert( isset( $models[0]['name'] ) && 'Опубликованная модель' === $models[0]['name'], 'На главной выводится невалидный или неопубликованный товар.' );

$by_category = velotok_get_products_by_category( 'city', -1 );

velotok_valid_products_assert( 1 === count( $by_category ), 'В категории должна остаться только валидная опубликованная модель.' );
velotok_valid_products_assert( isset( $by_category[0]['name'] ) && 'Опубликованная модель' === $by_category[0]['name'], 'В категорию попадает невалидный или неопубликованный товар.' );

velotok_valid_products_assert( true === velotok_is_valid_product( $GLOBALS['velotok_test_products'][0] ), 'Валидный опубликованный товар должен приниматься.' );
velotok_valid_products_assert( false === velotok_is_valid_product( $GLOBALS['velotok_test_products'][1] ), 'Черновик не должен считаться валидным товаром витрины.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}
	exit( 1 );
}

echo 'PASS: витрина показывает только валидные опубликованные товары (TESTEB-51).' . PHP_EOL;
