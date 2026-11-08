<?php
/**
 * Behaviour test for the published/valid storefront state model.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/catalog-published-status-test.php
 *
 * The storefront must filter by product state and catalog visibility, not by a
 * concrete WooCommerce class. Only published, public, structurally valid
 * products may reach the catalog; draft, pending, private, trash, future,
 * hidden and invalid products must be dropped. Valid products without an image
 * or description stay because the card has safe fallbacks.
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
function velotok_catalog_published_status_assert( $condition, $message ) {
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

require_once dirname( __DIR__ ) . '/inc/woocommerce.php';

/**
 * Standalone product double that intentionally does not extend WC_Product.
 *
 * The storefront validation is contract based: any object exposing the
 * WooCommerce product methods is accepted or rejected by its state and data.
 */
class Velotok_Catalog_State_Product {
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

/**
 * Build a catalog product double.
 *
 * @param array $overrides Field overrides.
 * @return Velotok_Catalog_State_Product
 */
function velotok_catalog_state_product( $overrides = array() ) {
	$defaults = array(
		'id'                 => 1,
		'name'               => 'Ривьера',
		'slug'               => 'riviera',
		'status'             => 'publish',
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

	return new Velotok_Catalog_State_Product( array_merge( $defaults, $overrides ) );
}

$GLOBALS['velotok_test_products'] = array(
	velotok_catalog_state_product( array( 'id' => 1, 'name' => 'Опубликованный', 'slug' => 'published' ) ),
	velotok_catalog_state_product( array( 'id' => 2, 'name' => 'Черновик', 'slug' => 'draft', 'status' => 'draft' ) ),
	velotok_catalog_state_product( array( 'id' => 3, 'name' => 'На проверке', 'slug' => 'pending', 'status' => 'pending' ) ),
	velotok_catalog_state_product( array( 'id' => 4, 'name' => 'Приватный', 'slug' => 'private', 'status' => 'private' ) ),
	velotok_catalog_state_product( array( 'id' => 5, 'name' => 'Удалённый', 'slug' => 'trash', 'status' => 'trash' ) ),
	velotok_catalog_state_product( array( 'id' => 6, 'name' => 'Запланированный', 'slug' => 'future', 'status' => 'future' ) ),
	velotok_catalog_state_product( array( 'id' => 7, 'name' => 'Авто-черновик', 'slug' => 'auto-draft', 'status' => 'auto-draft' ) ),
	velotok_catalog_state_product( array( 'id' => 8, 'name' => 'Скрытый', 'slug' => 'hidden', 'catalog_visibility' => 'hidden' ) ),
	velotok_catalog_state_product( array( 'id' => 9, 'name' => 'Только поиск', 'slug' => 'search-only', 'catalog_visibility' => 'search' ) ),
	velotok_catalog_state_product( array( 'id' => 10, 'name' => 'Только каталог', 'slug' => 'catalog-only', 'catalog_visibility' => 'catalog' ) ),
	velotok_catalog_state_product( array( 'id' => 11, 'name' => '   ', 'slug' => 'blank-name' ) ),
	velotok_catalog_state_product( array( 'id' => 0, 'name' => 'Без идентификатора', 'slug' => 'without-id' ) ),
	velotok_catalog_state_product( array( 'id' => 13, 'name' => 'Отрицательная цена', 'slug' => 'negative-price', 'price' => -1 ) ),
	velotok_catalog_state_product( array( 'id' => 14, 'name' => 'Без описания', 'slug' => 'without-description', 'short_description' => '', 'description' => '' ) ),
	velotok_catalog_state_product( array( 'id' => 15, 'name' => 'Без цены', 'slug' => 'zero-price', 'price' => 0, 'price_html' => '' ) ),
);

$models = velotok_get_home_models();
$names  = array();

foreach ( $models as $model ) {
	$names[] = isset( $model['name'] ) ? (string) $model['name'] : '';
}

velotok_catalog_published_status_assert( 4 === count( $models ), 'Витрина должна оставлять только опубликованные валидные товары.' );

foreach ( array( 'Опубликованный', 'Только каталог', 'Без описания', 'Без цены' ) as $included ) {
	velotok_catalog_published_status_assert( in_array( $included, $names, true ), 'Валидный товар пропал с витрины: ' . $included );
}

foreach ( array( 'Черновик', 'На проверке', 'Приватный', 'Удалённый', 'Запланированный', 'Авто-черновик', 'Скрытый', 'Только поиск', 'Без идентификатора', 'Отрицательная цена' ) as $excluded ) {
	velotok_catalog_published_status_assert( ! in_array( $excluded, $names, true ), 'На витрину попал недопустимый товар: ' . $excluded );
}

foreach ( array( 'draft', 'pending', 'private', 'trash', 'future', 'auto-draft' ) as $status ) {
	$product = velotok_catalog_state_product( array( 'status' => $status ) );
	velotok_catalog_published_status_assert( false === velotok_product_is_published( $product ), 'Состояние «' . $status . '» не должно считаться опубликованным.' );
}

$published = velotok_catalog_state_product( array( 'status' => 'publish' ) );
velotok_catalog_published_status_assert( true === velotok_product_is_published( $published ), 'Состояние publish должно считаться опубликованным.' );

$GLOBALS['velotok_test_products'] = array();

$models = velotok_get_home_models();
velotok_catalog_published_status_assert( is_array( $models ) && array() === $models, 'Пустой каталог должен возвращать пустой список без демо-товаров.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}
	exit( 1 );
}

echo 'OK: витрина фильтрует товары по состояниям и видимости каталога.' . PHP_EOL;
