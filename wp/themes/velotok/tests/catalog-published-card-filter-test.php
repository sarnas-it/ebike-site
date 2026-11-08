<?php
/**
 * Behaviour test for the storefront product-card boundary.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/catalog-published-card-filter-test.php
 *
 * The storefront must never render a product card for a record that is not a
 * published, public and structurally valid product. The home catalog already
 * filters its products, but product loops (WooCommerce content-product) and
 * related products on the single-product page render cards from arbitrary
 * product objects. This test renders those two paths directly and fails when a
 * draft, hidden or invalid product leaks into a card.
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

$GLOBALS['velotok_test_meta']         = array();
$GLOBALS['velotok_test_products']     = array();
$GLOBALS['velotok_test_product_map']  = array();
$GLOBALS['velotok_test_related_ids']  = array();
$GLOBALS['velotok_test_current_id']   = 0;
$failures                             = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_published_card_filter_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/*
 * Minimal WordPress/WooCommerce API used by the storefront card path.
 */
function add_filter() { return true; }
function add_action() {}
function do_action() {}
function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_html_e( $text, $domain = null ) { echo esc_html( $text ); }
function esc_attr__( $text, $domain = null ) { return $text; }
function esc_attr_e( $text, $domain = null ) { echo esc_attr( $text ); }
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
function get_the_ID() { return (int) $GLOBALS['velotok_test_current_id']; }
function get_post_status( $post_id ) { return isset( $GLOBALS['velotok_test_post_status'][ $post_id ] ) ? $GLOBALS['velotok_test_post_status'][ $post_id ] : 'publish'; }
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
function wc_get_product( $id ) {
	return isset( $GLOBALS['velotok_test_product_map'][ (int) $id ] ) ? $GLOBALS['velotok_test_product_map'][ (int) $id ] : null;
}
function wc_get_related_products( $product_id, $limit = 3 ) { return $GLOBALS['velotok_test_related_ids']; }
function wc_attribute_label( $name, $product = null ) { return (string) $name; }
function wc_get_product_terms() { return array(); }
function velotok_spec_value( $key, $fallback = '' ) { return $fallback; }
function get_header() {}
function get_footer() {}
function have_posts() {
	static $done = false;

	if ( $done ) {
		return false;
	}

	$done = true;

	return true;
}
function the_post() {}
function woocommerce_breadcrumb() {}
function woocommerce_show_product_sale_flash() {}
function woocommerce_show_product_images() {}
function woocommerce_template_single_title() {}
function woocommerce_template_single_rating() {}
function woocommerce_template_single_price() {}
function woocommerce_template_single_excerpt() {}
function woocommerce_template_single_add_to_cart() {}
function woocommerce_template_single_meta() {}
function woocommerce_output_product_data_tabs() {}
function get_template_part( $slug, $name = null, $args = array() ) {
	$file = dirname( __DIR__ ) . '/' . $slug . '.php';
	if ( is_file( $file ) ) {
		include $file;
	}
}

if ( ! class_exists( 'WC_Product' ) ) {
	/**
	 * Minimal WooCommerce product double with publish state and visibility.
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
		public function get_catalog_visibility() { return isset( $this->data['catalog_visibility'] ) ? $this->data['catalog_visibility'] : 'visible'; }
		public function get_permalink() { return isset( $this->data['permalink'] ) ? $this->data['permalink'] : ''; }
		public function get_price() { return array_key_exists( 'price', $this->data ) ? $this->data['price'] : 0; }
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
 * Build a storefront product double.
 *
 * @param array $overrides Field overrides.
 * @return WC_Product
 */
function velotok_published_card_product( $overrides = array() ) {
	$defaults = array(
		'id'                 => 1,
		'name'               => 'Ривьера',
		'slug'               => 'riviera',
		'status'             => 'publish',
		'visible'            => true,
		'catalog_visibility' => 'visible',
		'permalink'          => 'http://example.test/product/riviera/',
		'price'              => 62900,
		'price_html'         => '62 900 ₽',
		'sku'                => 'RIV-1',
		'short_description'  => 'Городская модель.',
		'description'        => 'Полное описание городской модели.',
		'attributes'         => array(),
		'type'               => 'simple',
		'purchasable'        => true,
		'in_stock'           => true,
	);

	return new WC_Product( array_merge( $defaults, $overrides ) );
}

/**
 * Render a WooCommerce content-product template for one product.
 *
 * @param WC_Product $product Product to render.
 * @return string
 */
function velotok_published_card_render_content_product( $card_product ) {
	global $product;

	$product = $card_product;

	ob_start();
	include dirname( __DIR__ ) . '/woocommerce/content-product.php';

	return (string) ob_get_clean();
}

/*
 * Scenario 1: the product-loop card renders valid products only.
 */
$valid = velotok_published_card_product( array( 'id' => 100, 'name' => 'Опубликованная модель', 'slug' => 'published' ) );

$html = velotok_published_card_render_content_product( $valid );
velotok_published_card_filter_assert( false !== strpos( $html, 'class="model-card"' ), 'Валидный опубликованный товар не выводится в карточке.' );
velotok_published_card_filter_assert( false !== strpos( $html, 'Опубликованная модель' ), 'В карточке валидного товара нет названия.' );

$invalid_products = array(
	'Черновик'          => velotok_published_card_product( array( 'id' => 101, 'name' => 'Черновик', 'slug' => 'draft', 'status' => 'draft' ) ),
	'На проверке'       => velotok_published_card_product( array( 'id' => 102, 'name' => 'На проверке', 'slug' => 'pending', 'status' => 'pending' ) ),
	'Приватный'         => velotok_published_card_product( array( 'id' => 103, 'name' => 'Приватный', 'slug' => 'private', 'status' => 'private' ) ),
	'Удалённый'         => velotok_published_card_product( array( 'id' => 104, 'name' => 'Удалённый', 'slug' => 'trash', 'status' => 'trash' ) ),
	'Запланированный'   => velotok_published_card_product( array( 'id' => 105, 'name' => 'Запланированный', 'slug' => 'future', 'status' => 'future' ) ),
	'Скрытый'           => velotok_published_card_product( array( 'id' => 106, 'name' => 'Скрытый', 'slug' => 'hidden', 'catalog_visibility' => 'hidden' ) ),
	'Только поиск'      => velotok_published_card_product( array( 'id' => 107, 'name' => 'Только поиск', 'slug' => 'search-only', 'catalog_visibility' => 'search' ) ),
	'Невидимый'         => velotok_published_card_product( array( 'id' => 108, 'name' => 'Невидимый', 'slug' => 'invisible', 'visible' => false ) ),
	'Без названия'      => velotok_published_card_product( array( 'id' => 109, 'name' => '   ', 'slug' => 'blank-name' ) ),
	'Без идентификатора' => velotok_published_card_product( array( 'id' => 0, 'name' => 'Без идентификатора', 'slug' => 'without-id' ) ),
	'Отрицательная цена' => velotok_published_card_product( array( 'id' => 110, 'name' => 'Отрицательная цена', 'slug' => 'negative-price', 'price' => -1 ) ),
	'Нечисловая цена'   => velotok_published_card_product( array( 'id' => 111, 'name' => 'Нечисловая цена', 'slug' => 'text-price', 'price' => 'дорого' ) ),
);

foreach ( $invalid_products as $label => $invalid ) {
	$html = velotok_published_card_render_content_product( $invalid );

	velotok_published_card_filter_assert( false === strpos( $html, 'class="model-card"' ), 'Недопустимый товар «' . $label . '» выводится в карточке витрины.' );
	velotok_published_card_filter_assert( false === strpos( $html, (string) $invalid->get_name() ) || '   ' === $invalid->get_name(), 'Название недопустимого товара «' . $label . '» попало в витрину.' );
}

/*
 * Scenario 2: related products on the single-product page are filtered too.
 */
$main_product = velotok_published_card_product( array( 'id' => 200, 'name' => 'Основная модель', 'slug' => 'main' ) );

$related_valid   = velotok_published_card_product( array( 'id' => 201, 'name' => 'Соседняя модель', 'slug' => 'related-valid' ) );
$related_draft   = velotok_published_card_product( array( 'id' => 202, 'name' => 'Соседний черновик', 'slug' => 'related-draft', 'status' => 'draft' ) );
$related_hidden  = velotok_published_card_product( array( 'id' => 203, 'name' => 'Соседняя скрытая', 'slug' => 'related-hidden', 'catalog_visibility' => 'hidden' ) );
$related_invalid = velotok_published_card_product( array( 'id' => 204, 'name' => 'Соседняя с ценой', 'slug' => 'related-bad-price', 'price' => '-100' ) );

$GLOBALS['velotok_test_product_map'] = array(
	200 => $main_product,
	201 => $related_valid,
	202 => $related_draft,
	203 => $related_hidden,
	204 => $related_invalid,
);
$GLOBALS['velotok_test_related_ids'] = array( 201, 202, 203, 204 );
$GLOBALS['velotok_test_current_id']  = 200;

global $product;
$product = $main_product;

ob_start();
include dirname( __DIR__ ) . '/woocommerce/single-product.php';
$single_html = (string) ob_get_clean();

velotok_published_card_filter_assert( false !== strpos( $single_html, 'Соседняя модель' ), 'Валидный соседний товар не выводится на странице товара.' );

foreach ( array( 'Соседний черновик', 'Соседняя скрытая', 'Соседняя с ценой' ) as $leaked ) {
	velotok_published_card_filter_assert( false === strpos( $single_html, $leaked ), 'Недопустимый соседний товар «' . $leaked . '» попал на витрину.' );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}
	exit( 1 );
}

echo 'OK: витрина не выводит карточки неопубликованных и невалидных товаров в циклах и соседних товарах.' . PHP_EOL;
