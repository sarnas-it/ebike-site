<?php
/**
 * Behaviour test for the published/valid storefront catalog guarantee.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/catalog-published-products-test.php
 *
 * The storefront (the #models catalog on the home page) must list only
 * published, public and structurally valid products. Drafts, pending products,
 * hidden products and products without a name or identifier must never leak
 * into the storefront. When WooCommerce is active and the catalog is empty the
 * section must render an empty state instead of demo cards.
 *
 * @package Velotok
 */

if ( false ) {
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
function velotok_catalog_published_assert( $condition, $message ) {
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
function taxonomy_exists( $taxonomy ) { return false; }
function get_term_by() { return false; }
function is_wp_error( $thing ) { return false; }
function get_term_link() { return ''; }
function wc_get_products( $args = array() ) { return $GLOBALS['velotok_test_products']; }
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

if ( ! class_exists( 'WooCommerce' ) ) {
	/**
	 * Marker used by the theme to detect an active WooCommerce store.
	 */
	class WooCommerce {}
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
		public function get_status() { return isset( $this->data['status'] ) ? $this->data['status'] : 'publish'; }
		public function get_catalog_visibility() { return isset( $this->data['catalog_visibility'] ) ? $this->data['catalog_visibility'] : 'visible'; }
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

/**
 * Build a catalog product double.
 *
 * @param array $overrides Field overrides.
 * @return WC_Product
 */
function velotok_catalog_published_product( $overrides = array() ) {
	$defaults = array(
		'id'                => 1,
		'name'              => 'Ривьера',
		'slug'              => 'riviera',
		'status'            => 'publish',
		'catalog_visibility' => 'visible',
		'permalink'         => 'http://example.test/product/riviera/',
		'price'             => 62900,
		'price_html'        => '62 900 ₽',
		'sku'               => 'RIV-1',
		'short_description' => 'Городская модель.',
		'description'       => 'Полное описание городской модели.',
		'attributes'        => array(),
		'type'              => 'simple',
		'purchasable'       => true,
		'in_stock'          => true,
	);

	return new WC_Product( array_merge( $defaults, $overrides ) );
}

/*
 * Scenario 1: only published, public, valid products reach the storefront.
 */
$GLOBALS['velotok_test_products'] = array(
	velotok_catalog_published_product( array( 'id' => 1, 'name' => 'Опубликованный', 'slug' => 'published' ) ),
	velotok_catalog_published_product( array( 'id' => 2, 'name' => 'Черновик', 'slug' => 'draft', 'status' => 'draft' ) ),
	velotok_catalog_published_product( array( 'id' => 3, 'name' => 'На проверке', 'slug' => 'pending', 'status' => 'pending' ) ),
	velotok_catalog_published_product( array( 'id' => 4, 'name' => 'Приватный', 'slug' => 'private', 'status' => 'private' ) ),
	velotok_catalog_published_product( array( 'id' => 5, 'name' => 'Удалённый', 'slug' => 'trash', 'status' => 'trash' ) ),
	velotok_catalog_published_product( array( 'id' => 6, 'name' => 'Скрытый', 'slug' => 'hidden', 'catalog_visibility' => 'hidden' ) ),
	velotok_catalog_published_product( array( 'id' => 7, 'name' => '', 'slug' => 'without-name' ) ),
	velotok_catalog_published_product( array( 'id' => 0, 'name' => 'Без идентификатора', 'slug' => 'without-id' ) ),
	velotok_catalog_published_product( array( 'id' => 9, 'name' => 'Без описания', 'slug' => 'without-description', 'short_description' => '', 'description' => '' ) ),
);

$models = velotok_get_home_models();
$names  = array();

foreach ( $models as $model ) {
	$names[] = isset( $model['name'] ) ? (string) $model['name'] : '';
}

velotok_catalog_published_assert( 2 === count( $models ), 'Витрина должна показывать только опубликованные валидные товары.' );
velotok_catalog_published_assert( in_array( 'Опубликованный', $names, true ), 'Опубликованный товар пропал с витрины.' );
velotok_catalog_published_assert( in_array( 'Без описания', $names, true ), 'Валидный товар без описания должен остаться на витрине.' );

foreach ( array( 'Черновик', 'На проверке', 'Приватный', 'Удалённый', 'Скрытый', 'Без идентификатора' ) as $excluded ) {
	velotok_catalog_published_assert( ! in_array( $excluded, $names, true ), 'На витрину попал недопустимый товар: ' . $excluded );
}

/*
 * Scenario 2: an empty active catalog renders an empty state, not demo cards.
 */
$GLOBALS['velotok_test_products'] = array();

$models = velotok_get_home_models();
velotok_catalog_published_assert( is_array( $models ) && array() === $models, 'Пустой активный каталог не должен подменяться демо-товарами.' );

$rendered = '';
ob_start();
get_template_part( 'template-parts/models' );
$rendered = (string) ob_get_clean();

velotok_catalog_published_assert( false === strpos( $rendered, 'class="model-card"' ), 'Пустой каталог не должен выводить демо-карточки.' );
velotok_catalog_published_assert( false === strpos( $rendered, 'Ривьера' ), 'Пустой каталог не должен показывать демо-модели.' );
velotok_catalog_published_assert( false !== strpos( $rendered, 'catalog-empty' ), 'Для пустого каталога нужно пустое состояние.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}
	exit( 1 );
}

echo 'OK: витрина показывает только опубликованные валидные товары и пустое состояние.' . PHP_EOL;
