<?php
/**
 * Behaviour test for TESTEB-54: the theme does not depend on stub plugins.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/theme-no-stub-plugins-test.php
 *
 * The theme must render the storefront, cards and specification values without
 * asking a stub plugin for data. The test scans the production theme files for
 * plugin accessors and then installs poisoned stub-plugin functions: if the
 * theme calls them, the values leak into the card or the local fallback is
 * replaced, so the test fails until the theme becomes self-contained.
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

$velotok_no_stub_theme_dir = dirname( __DIR__ );
$velotok_no_stub_failures  = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_no_stub_assert( $condition, $message ) {
	global $velotok_no_stub_failures;

	if ( ! $condition ) {
		$velotok_no_stub_failures[] = $message;
	}
}

/*
 * 1. Production theme files must not mention stub-plugin accessors or load
 *    plugin files. Test doubles under tests/ are not part of the theme runtime.
 */
$stub_prefix = 'velotok_' . 'core_';
$plugin_path = 'wp-content/' . 'plugins';
$iterator    = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $velotok_no_stub_theme_dir, FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
		continue;
	}

	$path = $file->getPathname();

	if ( false !== strpos( $path, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR ) ) {
		continue;
	}

	$source = (string) file_get_contents( $path );
	$label  = substr( $path, strlen( $velotok_no_stub_theme_dir ) + 1 );

	velotok_no_stub_assert(
		false === strpos( $source, $stub_prefix ),
		'Файл темы обращается к плагину-заглушке: ' . $label
	);
	velotok_no_stub_assert(
		false === strpos( $source, $plugin_path ),
		'Файл темы загружает код из каталога плагинов: ' . $label
	);
}

/*
 * 2. Poisoned stub-plugin accessors must be ignored by the theme.
 */
$stub_spec_function  = $stub_prefix . 'spec_get';
$stub_rows_function  = $stub_prefix . 'product_attributes_rows';

eval( 'function ' . $stub_spec_function . '( $key ) { return "STUB-SPEC"; }' );
eval( 'function ' . $stub_rows_function . '( $slug ) { return array( array( "Модель", "STUB-ATTRIBUTE" ) ); }' );

function add_filter() { return true; }
function add_action() {}
function __( $text, $domain = null ) { return $text; }
function _n( $single, $plural, $number, $domain = null ) { return $number > 1 ? $plural : $single; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $text, $domain = null ) { return esc_html( $text ); }
function esc_html_e( $text, $domain = null ) { echo esc_html( $text ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return (string) $url; }
function wp_kses_post( $text ) { return (string) $text; }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_trim_words( $text, $num_words = 55, $more = '…' ) { return (string) $text; }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals, '.', ' ' ); }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function get_template_directory() { return dirname( __DIR__ ); }
function get_template_directory_uri() { return 'https://example.test/wp-content/themes/velotok'; }
function get_theme_mod( $name, $default = false ) { return $default; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function get_post_meta( $post_id, $key, $single = false ) { return ''; }
function get_post_thumbnail_id( $post_id ) { return 0; }
function get_the_terms( $post_id, $taxonomy ) { return array(); }
function taxonomy_exists( $taxonomy ) { return false; }
function get_term_by() { return false; }
function is_wp_error( $thing ) { return false; }
function get_term_link() { return ''; }
function wc_get_products() { return array(); }
function wc_get_product() { return null; }
function wc_attribute_label( $name, $product = null ) { return (string) $name; }
function wc_get_product_terms() { return array(); }

require_once $velotok_no_stub_theme_dir . '/inc/customizer.php';
require_once $velotok_no_stub_theme_dir . '/inc/woocommerce.php';

velotok_no_stub_assert(
	'+7 (495) 123-45-67' === velotok_spec_value( 'shop_phone', '+7 (495) 123-45-67' ),
	'Локальное значение данных спецификации подменяется значением плагина-заглушки.'
);
velotok_no_stub_assert(
	'+7 (495) 123-45-67' === velotok_spec_value( 'shop_phone' ),
	'Тема должна хранить собственное значение телефона, не запрашивая его у плагина.'
);
velotok_no_stub_assert(
	'local-fallback' === velotok_spec_value( 'unknown_key', 'local-fallback' ),
	'Неизвестный ключ должен возвращать локальное значение по умолчанию.'
);

/**
 * Product double without seller-defined attributes.
 */
class Velotok_No_Stub_Product {
	public function get_id() { return 501; }
	public function get_name() { return 'Ривьера'; }
	public function get_slug() { return 'riviera'; }
	public function get_status() { return 'publish'; }
	public function get_catalog_visibility() { return 'visible'; }
	public function get_permalink() { return 'https://example.test/product/riviera/'; }
	public function get_price() { return 62900; }
	public function get_price_html() { return '62 900 ₽'; }
	public function get_sku() { return 'RIV-501'; }
	public function get_short_description() { return 'Городская модель.'; }
	public function get_description() { return 'Полное описание городской модели.'; }
	public function get_attributes() { return array(); }
	public function is_type( $type ) { return 'simple' === $type; }
	public function is_purchasable() { return true; }
	public function is_in_stock() { return true; }
	public function add_to_cart_url() { return $this->get_permalink(); }
}

$product = new Velotok_No_Stub_Product();
$specs   = velotok_product_card_specs( $product );

foreach ( $specs as $spec ) {
	velotok_no_stub_assert(
		'STUB-ATTRIBUTE' !== $spec[1],
		'Карточка товара получает дополнительные атрибуты из плагина-заглушки.'
	);
}

$fallback = velotok_normalize_fallback_model( velotok_fallback_models()[0], 1 );

foreach ( $fallback['specs'] as $spec ) {
	velotok_no_stub_assert(
		'STUB-ATTRIBUTE' !== $spec[1],
		'Демо-карточка получает дополнительные атрибуты из плагина-заглушки.'
	);
}

if ( $velotok_no_stub_failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $velotok_no_stub_failures ) . ' проверок независимости темы от плагинов-заглушек не прошли:' . PHP_EOL );

	foreach ( $velotok_no_stub_failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: тема не зависит от плагинов-заглушек (TESTEB-54).' . PHP_EOL;
exit( 0 );
