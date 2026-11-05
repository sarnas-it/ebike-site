<?php
/**
 * Проверка обязательных атрибутов карточки товара.
 *
 * Запуск: php wp/themes/velotok/tests/model-card-test.php
 *
 * @package Velotok
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

/**
 * Заглушка хуков WordPress.
 */
function add_filter() {}
function add_action() {}

function __( $text, $domain = null ) {
	return $text;
}

function _n( $single, $plural, $number, $domain = null ) {
	return 1 === (int) $number ? $single : $plural;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
	return (string) $url;
}

function esc_html__( $text, $domain = null ) {
	return esc_html( $text );
}

function esc_attr__( $text, $domain = null ) {
	return esc_attr( $text );
}

function wp_kses_post( $html ) {
	return (string) $html;
}

function wp_strip_all_tags( $html ) {
	return trim( strip_tags( (string) $html ) );
}

function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, (int) $decimals, '.', ' ' );
}

function get_post_meta( $post_id, $key, $single = false ) {
	return '_velotok_badge' === $key ? 'Хит' : '';
}

function get_the_terms( $post_id, $taxonomy ) {
	return array();
}

function is_wp_error( $thing ) {
	return false;
}

function get_template_directory_uri() {
	return 'https://example.test/wp-content/themes/velotok';
}

function get_post_thumbnail_id( $post_id ) {
	return 0;
}

function wp_get_attachment_image() {
	return '';
}

function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

function taxonomy_exists( $taxonomy ) {
	return false;
}

function get_term_by() {
	return false;
}

function get_term_link() {
	return '';
}

function wc_get_products() {
	return array();
}

class Velotok_Test_Product {
	private $short_description;
	private $description;

	public function __construct( $short_description, $description = '' ) {
		$this->short_description = $short_description;
		$this->description       = $description;
	}

	public function get_id() {
		return 42;
	}

	public function get_slug() {
		return 'riviera';
	}

	public function get_name() {
		return 'Ривьера';
	}

	public function get_short_description() {
		return $this->short_description;
	}

	public function get_description() {
		return $this->description;
	}

	public function get_price() {
		return 62900;
	}

	public function get_price_html() {
		return '<span class="price">62 900 ₽</span>';
	}

	public function get_permalink() {
		return 'https://example.test/product/riviera/';
	}

	public function get_sku() {
		return 'TEST-42';
	}

	public function is_type( $type ) {
		return 'simple' === $type;
	}

	public function is_purchasable() {
		return true;
	}

	public function is_in_stock() {
		return true;
	}

	public function add_to_cart_url() {
		return 'https://example.test/?add-to-cart=42';
	}
}

/**
 * Проверить условие и завершиться с ошибкой.
 *
 * @param bool   $condition Условие.
 * @param string $message   Сообщение.
 */
function velotok_test_check( $condition, $message ) {
	if ( ! $condition ) {
		file_put_contents( 'php://stderr', 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}

	echo 'ok - ' . $message . PHP_EOL;
}

/**
 * Отрисовать карточку и вернуть HTML.
 *
 * @param array $args Данные карточки.
 * @return string
 */
function velotok_test_render_card( array $args ) {
	ob_start();
	include dirname( __DIR__ ) . '/template-parts/model-card.php';
	return (string) ob_get_clean();
}

require dirname( __DIR__ ) . '/inc/woocommerce.php';

$description = 'Лёгкая низкая рама, 90 км запаса хода и спокойный характер.';
$product     = new Velotok_Test_Product( $description );
$model       = velotok_normalize_product( $product, 1 );

velotok_test_check( isset( $model['description'] ) && $description === $model['description'], 'нормализация товара передаёт описание в карточку' );

$html = velotok_test_render_card( $model );

velotok_test_check( false !== strpos( $html, '<img' ) && false !== strpos( $html, 'model-photo' ), 'карточка содержит фото товара' );
velotok_test_check( false !== strpos( $html, 'Ривьера' ), 'карточка содержит название товара' );
velotok_test_check( false !== strpos( $html, 'model-description' ) && false !== strpos( $html, $description ), 'карточка содержит описание товара' );
velotok_test_check( false !== strpos( $html, '62 900' ), 'карточка содержит цену товара' );

$full_only = new Velotok_Test_Product( '', 'Полное описание товара без краткого.' );
$full_model = velotok_normalize_product( $full_only, 2 );
velotok_test_check( 'Полное описание товара без краткого.' === $full_model['description'], 'если краткое описание пустое, используется полное' );

$fallback = velotok_normalize_fallback_model( velotok_fallback_models()[0], 1 );
$fallback_html = velotok_test_render_card( $fallback );
velotok_test_check( ! empty( $fallback['description'] ) && false !== strpos( $fallback_html, $fallback['description'] ), 'демо-карточка также содержит описание' );

echo 'PASS: обязательные атрибуты карточки отображаются.' . PHP_EOL;
