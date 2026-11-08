<?php
/**
 * Behaviour test for arbitrary additional attributes in catalog cards.
 *
 * Run from the project root:
 *   php wp/plugins/velotok-core/tests/product-attributes-card-test.php
 *
 * The test combines the theme card renderer with the product attributes stub.
 * It fails if the catalog stops showing arbitrary seller attributes. The theme
 * itself must not read the stub: TESTEB-54 makes the card independent, so the
 * stub is not loaded by the theme and cannot leak into the card output.
 *
 * @package Velotok_Core
 */

if ( false ) {
	fwrite( STDERR, 'This test must be run from the command line.' . PHP_EOL );
	exit( 1 );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_test_meta'] = array();
$failures = array();

function add_filter() { return true; }
function add_action() {}
function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return $text; }
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
function get_template_directory() { return dirname( __DIR__, 3 ) . '/themes/velotok'; }
function home_url( $path = '' ) { return 'http://example.test' . $path; }
function get_post_meta( $post_id, $key, $single = false ) {
	return isset( $GLOBALS['velotok_test_meta'][ $post_id ][ $key ] ) ? $GLOBALS['velotok_test_meta'][ $post_id ][ $key ] : '';
}
function get_post_thumbnail_id( $post_id ) { return 0; }
function get_the_terms( $post_id, $taxonomy ) { return array(); }
function wc_get_products() { return array(); }
function wc_get_product() { return null; }
function wc_attribute_label( $name, $product = null ) { return (string) $name; }
function wc_get_product_terms() { return array(); }
function velotok_spec_value( $key, $fallback = '' ) { return $fallback; }
function get_template_part( $slug, $name = null, $args = array() ) {
	$file = get_template_directory() . '/' . $slug . '.php';
	if ( is_file( $file ) ) {
		include $file;
	}
}

if ( ! class_exists( 'WC_Product' ) ) {
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

require_once dirname( __DIR__ ) . '/includes/product-attributes.php';
require_once get_template_directory() . '/inc/woocommerce.php';

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_product_attributes_card_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Render one card and return its HTML.
 *
 * @param array $model Card data.
 * @return string
 */
function velotok_product_attributes_render_card( $model ) {
	ob_start();
	get_template_part( 'template-parts/model-card', null, $model );
	return (string) ob_get_clean();
}

$fallback = velotok_normalize_fallback_model( velotok_fallback_models()[0], 1 );
$fallback_labels = array();
foreach ( $fallback['specs'] as $spec ) {
	$fallback_labels[ $spec[0] ] = $spec[1];
}

velotok_product_attributes_card_assert( isset( $fallback_labels['Запас хода'] ), 'Демо-карточка потеряла обязательные характеристики.' );
velotok_product_attributes_card_assert( ! isset( $fallback_labels['Модель'] ), 'Демо-карточка не должна получать атрибуты из плагина-заглушки.' );

$fallback_html = velotok_product_attributes_render_card( $fallback );
velotok_product_attributes_card_assert( false !== strpos( $fallback_html, $fallback['name'] ), 'Демо-карточка не выводит название.' );
velotok_product_attributes_card_assert( false !== strpos( $fallback_html, $fallback['description'] ), 'Демо-карточка не выводит описание.' );

$product = new WC_Product(
	array(
		'id'                => 77,
		'name'              => 'Ривьера',
		'slug'              => 'riviera',
		'permalink'         => 'http://example.test/product/riviera/',
		'price'             => 62900,
		'price_html'        => '62 900 ₽',
		'sku'               => 'RIV-1',
		'short_description' => 'Городская модель для ежедневных поездок.',
		'description'       => '',
		'attributes'        => array(),
		'type'              => 'simple',
		'purchasable'       => true,
		'in_stock'          => true,
	)
);

$model = velotok_normalize_product( $product, 1 );
$labels = array();
foreach ( $model['specs'] as $spec ) {
	$labels[ $spec[0] ] = $spec[1];
}

velotok_product_attributes_card_assert( ! isset( $labels['Модель'] ), 'Товар без атрибутов продавца не должен получать данные из плагина-заглушки.' );
velotok_product_attributes_card_assert( ! isset( $labels['Гарантия'] ), 'Товар без атрибутов продавца не должен получать «Гарантию» из плагина-заглушки.' );

$seller_product = new WC_Product(
	array(
		'id'                => 78,
		'name'              => 'Ривьера',
		'slug'              => 'riviera',
		'permalink'         => 'http://example.test/product/riviera/',
		'price'             => 62900,
		'price_html'        => '62 900 ₽',
		'sku'               => 'RIV-2',
		'short_description' => 'Городская модель для ежедневных поездок.',
		'description'       => '',
		'attributes'        => array(
			new WC_Product_Attribute( 'Модель', array( 'Seller Model' ) ),
			new WC_Product_Attribute( 'Рама', array( 'Seller Frame' ) ),
		),
		'type'              => 'simple',
		'purchasable'       => true,
		'in_stock'          => true,
	)
);

$seller_model = velotok_normalize_product( $seller_product, 1 );
$seller_labels = array();
foreach ( $seller_model['specs'] as $spec ) {
	$seller_labels[ $spec[0] ] = $spec[1];
}

velotok_product_attributes_card_assert( isset( $seller_labels['Модель'] ) && 'Seller Model' === $seller_labels['Модель'], 'Атрибут продавца «Модель» не выводится в карточке.' );
velotok_product_attributes_card_assert( isset( $seller_labels['Рама'] ) && 'Seller Frame' === $seller_labels['Рама'], 'Атрибут продавца «Рама» не выводится в карточке.' );
velotok_product_attributes_card_assert( ! isset( $seller_labels['Гарантия'] ), 'Карточка не должна дополнять атрибуты продавца данными из плагина-заглушки.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}
	exit( 1 );
}

echo 'OK: карточки каталога выводят атрибуты продавца и не зависят от плагина-заглушки.' . PHP_EOL;
