<?php
/**
 * Проверка обязательных атрибутов в реальном выводе карточки товара.
 *
 * Запуск: php wp/themes/velotok/tests/product-card-required-attributes-render-test.php
 *
 * Тест рендерит шаблон карточки и проверяет, что фото, название, описание и
 * цена присутствуют в HTML. Он падает, если шаблон снова начнёт скрывать
 * обязательный атрибут или если данные карточки перестанут проходить
 * серверную валидацию при сохранении.
 *
 * @package Velotok
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

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
	return 123;
}

function get_post_mime_type( $post_id ) {
	return 'image/jpeg';
}

function wp_get_attachment_image() {
	return '<img src="https://example.test/photo.jpg" width="800" height="600" alt="Ривьера" class="model-photo">';
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

class Velotok_Render_Test_Product {
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
		return 'Лёгкая низкая рама, 90 км запаса хода и спокойный характер.';
	}

	public function get_description() {
		return '';
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
function velotok_render_check( $condition, $message ) {
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
function velotok_render_card( array $args ) {
	ob_start();
	include dirname( __DIR__ ) . '/template-parts/model-card.php';
	return (string) ob_get_clean();
}

require dirname( __DIR__ ) . '/inc/woocommerce.php';
require_once dirname( __DIR__, 3 ) . '/plugins/velotok-core/includes/product-validation.php';

$required = array( 'photo', 'title', 'description', 'price' );

velotok_render_check(
	$required === velotok_product_card_required_attributes(),
	'список обязательных атрибутов карточки задан явно'
);

$product = new Velotok_Render_Test_Product();
$model   = velotok_normalize_product( $product, 1 );

foreach ( $required as $attribute ) {
	velotok_render_check(
		array_key_exists( $attribute, $model['card_attributes'] ),
		sprintf( 'данные карточки содержат обязательный атрибут «%s»', $attribute )
	);
}

$html = velotok_render_card( $model );

velotok_render_check(
	false !== strpos( $html, 'data-required-attributes="photo title description price"' ),
	'карточка объявляет обязательные атрибуты photo, title, description, price'
);

foreach ( $required as $attribute ) {
	velotok_render_check(
		false !== strpos( $html, 'data-card-attribute="' . $attribute . '"' ),
		sprintf( 'карточка выводит обязательный атрибут «%s»', $attribute )
	);
}

velotok_render_check( false !== strpos( $html, 'model-photo' ), 'в карточке присутствует фото' );
velotok_render_check( false !== strpos( $html, 'Ривьера' ), 'в карточке присутствует название' );
velotok_render_check( false !== strpos( $html, 'Лёгкая низкая рама' ), 'в карточке присутствует описание' );
velotok_render_check( false !== strpos( $html, '62 900' ), 'в карточке присутствует цена' );

$save_result = velotok_core_save_product_card( $model['card_attributes'], 'city' );
velotok_render_check( true === $save_result['saved'], 'данные карточки сохраняются без ошибок валидации' );
velotok_render_check( array() === $save_result['missing_attributes'], 'у данных карточки нет отсутствующих атрибутов' );
velotok_render_check( array() === $save_result['invalid_attributes'], 'у данных карточки нет невалидных атрибутов' );

$fallback      = velotok_normalize_fallback_model( velotok_fallback_models()[0], 1 );
$fallback_html = velotok_render_card( $fallback );

foreach ( $required as $attribute ) {
	velotok_render_check(
		false !== strpos( $fallback_html, 'data-card-attribute="' . $attribute . '"' ),
		sprintf( 'демо-карточка выводит обязательный атрибут «%s»', $attribute )
	);
}

$fallback_save = velotok_core_save_product_card( $fallback['card_attributes'], 'city' );
velotok_render_check( true === $fallback_save['saved'], 'данные демо-карточки проходят серверную валидацию' );

$partial = array(
	'card_id' => 'model-partial',
	'name'    => 'Модель без дополнительных данных',
	'url'     => 'https://example.test/product/partial/',
);
$partial_html = velotok_render_card( $partial );

foreach ( $required as $attribute ) {
	velotok_render_check(
		false !== strpos( $partial_html, 'data-card-attribute="' . $attribute . '"' ),
		sprintf( 'карточка не теряет обязательный атрибут «%s»', $attribute )
	);
}

echo 'PASS: карточка товара содержит фото, название, описание и цену.' . PHP_EOL;
