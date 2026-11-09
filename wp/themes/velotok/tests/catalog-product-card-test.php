<?php
/**
 * Behaviour test for TESTEB-18: the product card shows product data.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/catalog-product-card-test.php
 *
 * The storefront card must render the data of the WooCommerce product it
 * represents: photo, name, description, price, category, SKU, model and seller
 * attributes. The test fails without the card changes because SKU and the
 * product model were missing from the normalized card and its HTML.
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

$failures = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_catalog_card_theme_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

function add_filter() { return true; }
function add_action() {}
function __( $text, $domain = null ) { return $text; }
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
function esc_html__( $text, $domain = null ) { return esc_html( $text ); }
function esc_attr__( $text, $domain = null ) { return esc_attr( $text ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return (string) $url; }
function wp_kses_post( $text ) { return (string) $text; }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_trim_words( $text, $num_words = 55, $more = '…' ) { return (string) $text; }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals, '.', ' ' ); }
function get_template_directory_uri() { return 'http://example.test/wp-content/themes/velotok'; }
function home_url( $path = '' ) { return 'http://example.test' . $path; }
function get_post_meta( $post_id, $key, $single = false ) {
	$meta = array(
		'_velotok_badge'   => 'Хит',
		'_velotok_tagline' => 'Слоган товара.',
		'_velotok_model'   => 'Card Model',
	);

	return isset( $meta[ $key ] ) ? $meta[ $key ] : '';
}
function get_post_thumbnail_id( $post_id ) { return 0; }
function get_the_terms( $post_id, $taxonomy ) {
	return array( (object) array( 'slug' => 'city', 'name' => 'Городские' ) );
}
function is_wp_error( $thing ) { return false; }
function velotok_spec_value( $key, $fallback = '' ) { return $fallback; }

/**
 * Minimal WooCommerce attribute double.
 */
class Velotok_Card_Test_Attribute {
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
class Velotok_Card_Test_Product {
	private $data;

	public function __construct( $data = array() ) {
		$this->data = $data;
	}

	public function get_id() { return isset( $this->data['id'] ) ? (int) $this->data['id'] : 0; }
	public function get_name() { return isset( $this->data['name'] ) ? $this->data['name'] : ''; }
	public function get_slug() { return isset( $this->data['slug'] ) ? $this->data['slug'] : ''; }
	public function get_status() { return 'publish'; }
	public function get_catalog_visibility() { return 'visible'; }
	public function get_permalink() { return isset( $this->data['permalink'] ) ? $this->data['permalink'] : ''; }
	public function get_price() { return isset( $this->data['price'] ) ? $this->data['price'] : 0; }
	public function get_price_html() { return isset( $this->data['price_html'] ) ? $this->data['price_html'] : ''; }
	public function get_sku() { return isset( $this->data['sku'] ) ? $this->data['sku'] : ''; }
	public function get_short_description() { return isset( $this->data['short_description'] ) ? $this->data['short_description'] : ''; }
	public function get_description() { return isset( $this->data['description'] ) ? $this->data['description'] : ''; }
	public function get_attributes() { return isset( $this->data['attributes'] ) ? $this->data['attributes'] : array(); }
	public function is_type( $type ) { return 'simple' === $type; }
	public function is_purchasable() { return true; }
	public function is_in_stock() { return true; }
	public function add_to_cart_url() { return $this->get_permalink(); }
}

require_once dirname( __DIR__ ) . '/inc/woocommerce.php';

/**
 * Render one card through the real template.
 *
 * @param array $model Normalized card data.
 * @return string
 */
function velotok_catalog_card_theme_render( $model ) {
	$args = $model;
	ob_start();
	include dirname( __DIR__ ) . '/template-parts/model-card.php';
	return (string) ob_get_clean();
}

$product = new Velotok_Card_Test_Product(
	array(
		'id'                => 77,
		'name'              => 'Ривьера',
		'slug'              => 'riviera',
		'permalink'         => 'http://example.test/product/riviera/',
		'price'             => 62900,
		'price_html'        => '62 900 ₽',
		'sku'               => 'RIV-18',
		'short_description' => 'Краткое описание Ривьеры.',
		'description'       => 'Полное описание Ривьеры.',
		'attributes'        => array(
			new Velotok_Card_Test_Attribute( 'Модель', array( 'Seller Model' ) ),
			new Velotok_Card_Test_Attribute( 'Рама', array( 'Алюминий 6061' ) ),
			new Velotok_Card_Test_Attribute( 'Гарантия', array( '2 года' ) ),
		),
	)
);

$model = velotok_normalize_product( $product, 1 );

velotok_catalog_card_theme_assert( isset( $model['name'] ) && 'Ривьера' === $model['name'], 'Карточка потеряла название товара.' );
velotok_catalog_card_theme_assert( isset( $model['description'] ) && 'Краткое описание Ривьеры.' === $model['description'], 'Карточка потеряла описание товара.' );
velotok_catalog_card_theme_assert( isset( $model['full_description'] ) && 'Полное описание Ривьеры.' === $model['full_description'], 'Карточка потеряла полное описание товара.' );
velotok_catalog_card_theme_assert( isset( $model['price_html'] ) && '62 900 ₽' === $model['price_html'], 'Карточка потеряла цену товара.' );
velotok_catalog_card_theme_assert( isset( $model['sku'] ) && 'RIV-18' === $model['sku'], 'Карточка потеряла SKU товара.' );
velotok_catalog_card_theme_assert( isset( $model['category'] ) && 'city' === $model['category'], 'Карточка потеряла категорию товара.' );
velotok_catalog_card_theme_assert( isset( $model['category_label'] ) && 'Городские' === $model['category_label'], 'Карточка потеряла название категории.' );

$labels = array();
foreach ( $model['specs'] as $spec ) {
	$labels[ $spec[0] ] = $spec[1];
}

velotok_catalog_card_theme_assert( isset( $labels['Артикул'] ) && 'RIV-18' === $labels['Артикул'], 'Карточка не показывает SKU товара.' );
velotok_catalog_card_theme_assert( isset( $labels['Модель'] ) && 'Seller Model' === $labels['Модель'], 'Карточка не показывает модель товара.' );
velotok_catalog_card_theme_assert( isset( $labels['Рама'] ) && 'Алюминий 6061' === $labels['Рама'], 'Карточка не показывает атрибут «Рама».' );
velotok_catalog_card_theme_assert( isset( $labels['Гарантия'] ) && '2 года' === $labels['Гарантия'], 'Карточка не показывает атрибут «Гарантия».' );

$html = velotok_catalog_card_theme_render( $model );

foreach ( array( 'Ривьера', 'Краткое описание Ривьеры.', '62 900', 'Городские', 'RIV-18', 'Seller Model', 'Алюминий 6061', '2 года', '<img', 'model-card' ) as $value ) {
	velotok_catalog_card_theme_assert( false !== strpos( $html, $value ), 'В HTML карточки нет данных товара: ' . $value );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: карточка товара на витрине показывает данные товара (TESTEB-18).' . PHP_EOL;
