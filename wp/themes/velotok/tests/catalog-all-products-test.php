<?php
/**
 * Behaviour test for the complete rendered electric-bicycle catalog.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/catalog-all-products-test.php
 *
 * The test covers two catalogue guarantees: every published product is listed
 * (no fixed seven-item truncation) and every card keeps the mandatory
 * description even when the seller filled none of the description fields.
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
function velotok_catalog_all_assert( $condition, $message ) {
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
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
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
	$products = $GLOBALS['velotok_test_products'];
	$limit    = isset( $args['limit'] ) ? (int) $args['limit'] : -1;

	if ( $limit > 0 ) {
		return array_slice( $products, 0, $limit );
	}

	return $products;
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
	 * Minimal WooCommerce attribute double.
	 */
	class WC_Product_Attribute {
		private $name;
		private $options;
		private $visible;

		public function __construct( $name, $options, $visible = true ) {
			$this->name    = $name;
			$this->options = $options;
			$this->visible = $visible;
		}

		public function get_name() { return $this->name; }
		public function get_options() { return $this->options; }
		public function get_visible() { return $this->visible; }
		public function is_taxonomy() { return false; }
	}

	/**
	 * Minimal WooCommerce product double.
	 */
	class WC_Product {
		private $data;

		public function __construct( $data = array() ) {
			$this->data = $data;
		}

		public function get_id() { return isset( $this->data['id'] ) ? (int) $this->data['id'] : 0; }
		public function get_name() { return isset( $this->data['name'] ) ? $this->data['name'] : ''; }
		public function get_slug() { return isset( $this->data['slug'] ) ? $this->data['slug'] : ''; }
		public function get_permalink() { return isset( $this->data['permalink'] ) ? $this->data['permalink'] : ''; }
		public function get_price() { return isset( $this->data['price'] ) ? $this->data['price'] : 0; }
		public function get_price_html() { return isset( $this->data['price_html'] ) ? $this->data['price_html'] : ''; }
		public function get_sku() { return isset( $this->data['sku'] ) ? $this->data['sku'] : ''; }
		public function get_short_description() { return isset( $this->data['short_description'] ) ? $this->data['short_description'] : ''; }
		public function get_description() { return isset( $this->data['description'] ) ? $this->data['description'] : ''; }
		public function get_attributes() { return isset( $this->data['attributes'] ) ? $this->data['attributes'] : array(); }
		public function is_type( $type ) { return isset( $this->data['type'] ) && $type === $this->data['type']; }
		public function is_purchasable() { return ! empty( $this->data['purchasable'] ); }
		public function is_in_stock() { return ! empty( $this->data['in_stock'] ); }
		public function add_to_cart_url() { return $this->get_permalink(); }
	}
}

require_once dirname( __DIR__ ) . '/inc/woocommerce.php';

for ( $i = 1; $i <= 8; $i++ ) {
	$GLOBALS['velotok_test_products'][] = new WC_Product(
		array(
			'id'                => 100 + $i,
			'name'              => 'Модель ' . $i,
			'slug'              => 'model-' . $i,
			'permalink'         => 'http://example.test/product/model-' . $i . '/',
			'price'             => 50000 + $i * 1000,
			'price_html'        => number_format_i18n( 50000 + $i * 1000, 0 ) . ' ₽',
			'sku'               => 'MOD-' . $i,
			'short_description' => 'Описание модели ' . $i . '.',
			'description'       => '',
			'attributes'        => $i === 1 ? array( new WC_Product_Attribute( 'Модель', array( 'Riviera City' ) ) ) : array(),
			'type'              => 'simple',
			'purchasable'       => true,
			'in_stock'          => true,
		)
	);
}

$GLOBALS['velotok_test_products'][] = new WC_Product(
	array(
		'id'                => 200,
		'name'              => 'Модель без описания',
		'slug'              => 'model-without-description',
		'permalink'         => 'http://example.test/product/model-without-description/',
		'price'             => 99000,
		'price_html'        => '99 000 ₽',
		'sku'               => 'NO-DESC',
		'short_description' => '',
		'description'       => '',
		'attributes'        => array(),
		'type'              => 'simple',
		'purchasable'       => true,
		'in_stock'          => true,
	)
);

$models = velotok_get_home_models();

velotok_catalog_all_assert( is_array( $models ) && 9 === count( $models ), 'Каталог должен выводить все опубликованные товары, а не только первые семь.' );

$rendered = '';
ob_start();
get_template_part( 'template-parts/models' );
$rendered = (string) ob_get_clean();

velotok_catalog_all_assert( 9 === substr_count( $rendered, 'class="model-card"' ), 'Каталог должен выводить карточку для каждого товара.' );
velotok_catalog_all_assert( 9 === substr_count( $rendered, 'model-description' ), 'Каждая карточка должна содержать обязательное описание.' );
velotok_catalog_all_assert( false !== strpos( $rendered, 'Описание уточните у менеджера' ), 'Для товара без описания нужен текстовый запасной вариант.' );
velotok_catalog_all_assert( false !== strpos( $rendered, 'Riviera City' ), 'Дополнительный атрибут товара не выведен в каталоге.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}
	exit( 1 );
}

echo 'OK: каталог выводит все товары, обязательное описание и дополнительные атрибуты.' . PHP_EOL;
