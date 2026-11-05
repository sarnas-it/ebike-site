<?php
/**
 * Проверка отображения каталога электровелосипедов.
 *
 * Запуск: php wp/themes/velotok/tests/catalog-display-test.php
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

function esc_html_e( $text, $domain = null ) {
	echo esc_html( $text );
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

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, is_array( $args ) ? $args : array() );
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

function wc_get_products( $query = array() ) {
	$products = isset( $GLOBALS['velotok_test_wc_products'] ) ? $GLOBALS['velotok_test_wc_products'] : array();
	$limit    = isset( $query['limit'] ) ? (int) $query['limit'] : -1;

	if ( ! is_array( $products ) ) {
		return array();
	}

	if ( $limit > 0 ) {
		$products = array_slice( $products, 0, $limit );
	}

	return $products;
}

/**
 * Отрисовать часть шаблона так же, как это делает WordPress.
 *
 * @param string     $slug Имя части шаблона.
 * @param string|null $name Вариант.
 * @param array|null  $args Данные для шаблона.
 */
function get_template_part( $slug, $name = null, $args = null ) {
	if ( 'template-parts/model-card' === $slug ) {
		include dirname( __DIR__ ) . '/template-parts/model-card.php';
	}
}

class WC_Product {}

class Velotok_Catalog_Test_Product extends WC_Product {
	private $id;
	private $slug;
	private $name;
	private $description;
	private $price;

	public function __construct( $id, $slug, $name, $description, $price ) {
		$this->id          = (int) $id;
		$this->slug        = (string) $slug;
		$this->name        = (string) $name;
		$this->description = (string) $description;
		$this->price       = (float) $price;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_slug() {
		return $this->slug;
	}

	public function get_name() {
		return $this->name;
	}

	public function get_short_description() {
		return $this->description;
	}

	public function get_description() {
		return '';
	}

	public function get_price() {
		return $this->price;
	}

	public function get_price_html() {
		return number_format_i18n( $this->price, 0 ) . ' ₽';
	}

	public function get_permalink() {
		return 'https://example.test/product/' . $this->slug . '/';
	}

	public function get_sku() {
		return 'TEST-' . $this->id;
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
		return 'https://example.test/?add-to-cart=' . $this->id;
	}
}

/**
 * Проверить условие и завершиться с ошибкой.
 *
 * @param bool   $condition Условие.
 * @param string $message   Сообщение.
 */
function velotok_catalog_check( $condition, $message ) {
	if ( ! $condition ) {
		file_put_contents( 'php://stderr', 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}

	echo 'ok - ' . $message . PHP_EOL;
}

require_once dirname( __DIR__, 3 ) . '/plugins/velotok-core/includes/product-extra-attributes.php';
require dirname( __DIR__ ) . '/inc/woocommerce.php';

$GLOBALS['velotok_test_wc_products'] = array();

$catalog = velotok_get_catalog_models();
$fallback_count = count( velotok_fallback_models() );

velotok_catalog_check( is_array( $catalog ) && count( $catalog ) === $fallback_count, 'каталог отдаёт все демонстрационные модели при отсутствии WooCommerce' );
velotok_catalog_check( count( $catalog ) >= 4, 'каталог содержит несколько моделей электровелосипедов' );

$slugs = array();
$required_present = true;
foreach ( $catalog as $model ) {
	foreach ( array( 'name', 'description', 'price_html' ) as $field ) {
		if ( empty( $model[ $field ] ) ) {
			$required_present = false;
		}
	}

	if ( empty( $model['image_html'] ) || false === strpos( $model['image_html'], '<img' ) ) {
		$required_present = false;
	}

	$slugs[] = isset( $model['slug'] ) ? $model['slug'] : '';
}

velotok_catalog_check( $required_present, 'каждая карточка каталога содержит фото, название, описание и цену' );
velotok_catalog_check( count( array_unique( $slugs ) ) === count( $slugs ), 'каталог не дублирует модели' );

$limited = velotok_get_catalog_models( array( 'limit' => 2 ) );
velotok_catalog_check( 2 === count( $limited ), 'каталог учитывает ограничение количества карточек' );

$GLOBALS['velotok_test_wc_products'] = array(
	new Velotok_Catalog_Test_Product( 101, 'woo-city', 'Городская тестовая', 'Описание городской модели.', 55000 ),
	new Velotok_Catalog_Test_Product( 102, 'woo-trail', 'Горная тестовая', 'Описание горной модели.', 99000 ),
);

$woo_catalog = velotok_get_catalog_models();
velotok_catalog_check( 2 === count( $woo_catalog ), 'каталог берёт товары из WooCommerce, когда они опубликованы' );
velotok_catalog_check( 'Городская тестовая' === $woo_catalog[0]['name'], 'каталог сохраняет данные товара WooCommerce' );

$GLOBALS['velotok_test_wc_products'] = array();

ob_start();
include dirname( __DIR__ ) . '/template-parts/models.php';
$html = (string) ob_get_clean();

velotok_catalog_check( false !== strpos( $html, 'id="models"' ), 'витрина выводит блок каталога моделей' );
velotok_catalog_check( substr_count( $html, 'class="model-card"' ) === $fallback_count, 'витрина выводит отдельную карточку для каждой модели каталога' );
velotok_catalog_check( false !== strpos( $html, 'class="model-extra"' ), 'карточки каталога выводят дополнительные атрибуты' );

foreach ( $catalog as $model ) {
	velotok_catalog_check( false !== strpos( $html, esc_html( $model['name'] ) ), 'каталог отображает модель «' . $model['name'] . '»' );
}

echo 'PASS: каталог электровелосипедов отображается с обязательными и дополнительными атрибутами.' . PHP_EOL;
