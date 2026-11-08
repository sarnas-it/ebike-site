<?php
/**
 * Standalone behaviour test for the electric-bicycle catalog.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/catalog-render-test.php
 *
 * The test boots a minimal WordPress function stub, loads the theme catalog
 * renderer and checks that every catalog card exposes the mandatory product
 * attributes (photo, name, description, price) plus arbitrary extra attributes.
 * It fails on the original catalogue because the card had no description and
 * ignored arbitrary product attributes.
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

$GLOBALS['velotok_test_meta']  = array();
$GLOBALS['velotok_test_terms'] = array();
$failures                      = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_catalog_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/*
 * Minimal WordPress API used by the theme renderer.
 */
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
function get_template_directory() { return dirname( __DIR__ ); }
function home_url( $path = '' ) { return 'http://example.test' . $path; }
function get_post_meta( $post_id, $key, $single = false ) {
	$meta = $GLOBALS['velotok_test_meta'];
	return isset( $meta[ $post_id ][ $key ] ) ? $meta[ $post_id ][ $key ] : '';
}
function get_post_thumbnail_id( $post_id ) { return 0; }
function get_the_terms( $post_id, $taxonomy ) {
	return isset( $GLOBALS['velotok_test_terms'][ $post_id ] ) ? $GLOBALS['velotok_test_terms'][ $post_id ] : array();
}
function taxonomy_exists( $taxonomy ) { return false; }
function get_term_by() { return false; }
function is_wp_error( $thing ) { return false; }
function get_term_link() { return ''; }
function wc_get_products( $args = array() ) { return array(); }
function wc_get_product( $id ) { return null; }
function wc_get_related_products() { return array(); }
function wc_get_template_part() {}
function wc_setup_loop() {}
function woocommerce_pagination() {}
function wc_attribute_label( $name, $product = null ) {
	return ucfirst( str_replace( array( 'pa_', '-', '_' ), array( '', ' ', ' ' ), (string) $name ) );
}
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
		private $taxonomy;

		public function __construct( $name, $options, $visible = true, $taxonomy = false ) {
			$this->name     = $name;
			$this->options  = $options;
			$this->visible  = $visible;
			$this->taxonomy = $taxonomy;
		}

		public function get_name() { return $this->name; }
		public function get_options() { return $this->options; }
		public function get_visible() { return $this->visible; }
		public function is_taxonomy() { return $this->taxonomy; }
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

/*
 * 1. The fallback catalog (used when WooCommerce is not available) must show
 *    the mandatory card attributes.
 */
$models = velotok_get_home_models();

velotok_catalog_assert( is_array( $models ) && count( $models ) >= 1, 'Каталог электровелосипедов пуст.' );

foreach ( $models as $model ) {
	$name        = isset( $model['name'] ) ? (string) $model['name'] : '';
	$image       = isset( $model['image_html'] ) ? (string) $model['image_html'] : '';
	$description = isset( $model['description'] ) ? (string) $model['description'] : '';
	$price       = isset( $model['price_html'] ) ? (string) $model['price_html'] : '';

	velotok_catalog_assert( '' !== $name, 'В карточке каталога нет названия.' );
	velotok_catalog_assert( false !== strpos( $image, '<img' ), 'В карточке каталога нет фото: ' . $name );
	velotok_catalog_assert( '' !== $description, 'В карточке каталога нет описания: ' . $name );
	velotok_catalog_assert( '' !== $price, 'В карточке каталога нет цены: ' . $name );
}

$first   = $models[0];
$rendered = '';
ob_start();
get_template_part( 'template-parts/model-card', null, $first );
$rendered = (string) ob_get_clean();

velotok_catalog_assert( false !== strpos( $rendered, 'model-card' ), 'Шаблон карточки не рендерится.' );
velotok_catalog_assert( false !== strpos( $rendered, $first['name'] ), 'В HTML карточки нет названия.' );
velotok_catalog_assert( false !== strpos( $rendered, '<img' ), 'В HTML карточки нет фото.' );
velotok_catalog_assert( false !== strpos( $rendered, $first['description'] ), 'В HTML карточки нет описания.' );
velotok_catalog_assert( false !== strpos( $rendered, 'model-description' ), 'Описание не размечено в карточке.' );

/*
 * 2. A WooCommerce product must expose arbitrary additional attributes in the
 *    card, not only the fixed meta fields.
 */
$GLOBALS['velotok_test_meta'][77] = array(
	'_velotok_range_km'  => '90',
	'_velotok_motor_w'   => '250',
	'_velotok_weight_kg' => '23',
	'_velotok_wheel'     => '28″',
	'_velotok_badge'     => 'Хит',
	'_velotok_tagline'   => 'Городская модель для теста.',
);

$product = new WC_Product(
	array(
		'id'                => 77,
		'name'              => 'Тестовая модель',
		'slug'              => 'test-model',
		'permalink'         => 'http://example.test/product/test-model/',
		'price'             => 62900,
		'price_html'        => '62 900 ₽',
		'sku'               => 'TEST-1',
		'short_description' => 'Короткое описание тестовой модели для каталога.',
		'description'       => 'Полное описание тестовой модели.',
		'attributes'        => array(
			new WC_Product_Attribute( 'Рама', array( 'Алюминий 6061' ) ),
			new WC_Product_Attribute( 'Гарантия', array( '2 года' ) ),
		),
		'type'              => 'simple',
		'purchasable'       => true,
		'in_stock'          => true,
	)
);

$normalized = velotok_normalize_product( $product, 1 );

velotok_catalog_assert( 'Тестовая модель' === $normalized['name'], 'Нормализация потеряла название товара.' );
velotok_catalog_assert( false !== strpos( $normalized['image_html'], '<img' ), 'Нормализация потеряла фото товара.' );
velotok_catalog_assert( '' !== $normalized['description'], 'Нормализация потеряла описание товара.' );
velotok_catalog_assert( '' !== $normalized['price_html'], 'Нормализация потеряла цену товара.' );

$spec_labels = array();
foreach ( $normalized['specs'] as $spec ) {
	$spec_labels[ $spec[0] ] = $spec[1];
}

velotok_catalog_assert( isset( $spec_labels['Запас хода'] ), 'В карточке нет обязательной характеристики «Запас хода».' );
velotok_catalog_assert( isset( $spec_labels['Рама'] ) && 'Алюминий 6061' === $spec_labels['Рама'], 'В карточке нет произвольного атрибута «Рама».' );
velotok_catalog_assert( isset( $spec_labels['Гарантия'] ) && '2 года' === $spec_labels['Гарантия'], 'В карточке нет произвольного атрибута «Гарантия».' );

$rendered = '';
ob_start();
get_template_part( 'template-parts/model-card', null, $normalized );
$rendered = (string) ob_get_clean();

velotok_catalog_assert( false !== strpos( $rendered, 'Короткое описание тестовой модели' ), 'Описание товара не выведено в карточке.' );
velotok_catalog_assert( false !== strpos( $rendered, 'Алюминий 6061' ), 'Произвольный атрибут «Рама» не выведен в карточке.' );
velotok_catalog_assert( false !== strpos( $rendered, '2 года' ), 'Произвольный атрибут «Гарантия» не выведен в карточке.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}
	exit( 1 );
}

echo 'OK: каталог электровелосипедов выводит фото, название, описание, цену и произвольные атрибуты.' . PHP_EOL;
