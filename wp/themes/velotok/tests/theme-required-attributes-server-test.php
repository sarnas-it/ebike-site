<?php
/**
 * Behaviour test for TESTEB-61: required attributes are validated on the server.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/theme-required-attributes-server-test.php
 *
 * The test submits products without relying on the HTML `required` attribute or
 * client-side JavaScript: a product with a missing mandatory card attribute
 * must be rejected by the server-side validator. A valid product passes. The
 * required set is the card contract: photo, name, description and price.
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

$GLOBALS['velotok_required_server_failures'] = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_required_server_assert( $condition, $message ) {
	if ( ! $condition ) {
		$GLOBALS['velotok_required_server_failures'][] = $message;
	}
}

function add_filter() { return true; }
function add_action() {}
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
function wp_trim_words( $text, $num_words = 55, $more = '…' ) { return (string) $text; }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_key( $key ) { return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals, '.', ' ' ); }
function get_template_directory() { return dirname( __DIR__ ); }
function get_template_directory_uri() { return 'http://example.test/wp-content/themes/velotok'; }
function home_url( $path = '' ) { return 'http://example.test' . $path; }
function get_post_meta() { return ''; }
function get_post_thumbnail_id() { return 0; }
function get_the_terms() { return array(); }
function taxonomy_exists() { return false; }
function get_term_by() { return false; }
function is_wp_error() { return false; }
function get_term_link() { return ''; }
function wc_get_products() { return array(); }
function wc_get_product() { return null; }
function wc_attribute_label( $name, $product = null ) { return (string) $name; }
function wc_get_product_terms() { return array(); }
function velotok_spec_value( $key, $fallback = '' ) { return $fallback; }

/**
 * Product double with the mandatory card attributes.
 */
class Velotok_Required_Server_Product {
	private $data;

	public function __construct( $data = array() ) {
		$this->data = $data;
	}

	public function get_id() { return isset( $this->data['id'] ) ? (int) $this->data['id'] : 0; }
	public function get_name() { return isset( $this->data['name'] ) ? $this->data['name'] : ''; }
	public function get_description() { return isset( $this->data['description'] ) ? $this->data['description'] : ''; }
	public function get_short_description() { return isset( $this->data['short_description'] ) ? $this->data['short_description'] : ''; }
	public function get_price() { return array_key_exists( 'price', $this->data ) ? $this->data['price'] : 0; }
	public function get_image_id() { return isset( $this->data['image_id'] ) ? (int) $this->data['image_id'] : 0; }
}

require_once dirname( __DIR__ ) . '/inc/woocommerce.php';

/**
 * Build a valid product double.
 *
 * @param array $overrides Field overrides.
 * @return Velotok_Required_Server_Product
 */
function velotok_required_server_product( $overrides = array() ) {
	return new Velotok_Required_Server_Product(
		array_merge(
			array(
				'id'                => 1,
				'name'              => 'Ривьера',
				'description'       => 'Городской электровелосипед для ежедневных поездок.',
				'short_description' => 'Лёгкая городская модель.',
				'price'             => 62900,
				'image_id'          => 101,
			),
			$overrides
		)
	);
}

/*
 * 1. The server contract lists the mandatory card attributes.
 */
velotok_required_server_assert( function_exists( 'velotok_product_validation_contract' ), 'Нет контракта серверной валидации обязательных атрибутов.' );

$contract = velotok_product_validation_contract();

velotok_required_server_assert( true === $contract['server_side'], 'Контракт должен требовать серверную валидацию.' );
velotok_required_server_assert( false === $contract['client_side_only'], 'Валидация не должна быть только клиентской.' );
velotok_required_server_assert( 'TESTEB-61' === $contract['requirement'], 'Контракт должен ссылаться на TESTEB-61.' );
velotok_required_server_assert( array( 'photo', 'name', 'description', 'price' ) === $contract['required_attributes'], 'Неверный набор обязательных атрибутов.' );

/*
 * 2. A complete product passes; every missing attribute is rejected on the server.
 */
$valid = velotok_required_server_product();
$result = velotok_validate_product_required_attributes( $valid );

velotok_required_server_assert( true === $result['valid'], 'Валидный товар отклонён серверной валидацией.' );
velotok_required_server_assert( true === velotok_product_has_required_attributes( $valid ), 'Валидный товар не прошёл проверку наличия атрибутов.' );

$missing_cases = array(
	'photo'       => array( 'image_id' => 0 ),
	'name'        => array( 'name' => '' ),
	'description' => array( 'description' => '', 'short_description' => '' ),
);

foreach ( $missing_cases as $attribute => $overrides ) {
	$result = velotok_validate_product_required_attributes( velotok_required_server_product( $overrides ) );

	velotok_required_server_assert( false === $result['valid'], 'Сервер принял товар без обязательного атрибута: ' . $attribute );
	velotok_required_server_assert( isset( $result['errors'][ $attribute ] ), 'Нет ошибки для обязательного атрибута: ' . $attribute );
	velotok_required_server_assert( false === velotok_product_has_required_attributes( velotok_required_server_product( $overrides ) ), 'Проверка наличия атрибутов пропустила товар без «' . $attribute . '».' );
}

/*
 * 3. The validator works on submitted data and ignores the client-only marker.
 *
 * The array intentionally has no HTML/JS validation marker: only server-side
 * values matter. Missing attributes are still rejected.
 */
$submitted = array(
	'name'        => 'Ривьера',
	'description' => 'Описание товара',
	'price'       => '62900',
	'image'       => 'model-city-classic-1-web.jpg',
);

$result = velotok_validate_product_request( $submitted );
velotok_required_server_assert( true === $result['valid'], 'Полные серверные данные товара отклонены.' );

unset( $submitted['description'] );
$result = velotok_validate_product_request( $submitted );
velotok_required_server_assert( false === $result['valid'], 'Сервер принял данные без обязательного описания.' );
velotok_required_server_assert( isset( $result['errors']['description'] ), 'Сервер не сообщил об отсутствующем описании.' );

/*
 * 4. An invalid price is rejected with a concrete message (TESTEB-62).
 */
$result = velotok_validate_product_required_attributes( velotok_required_server_product( array( 'price' => -100 ) ) );

velotok_required_server_assert( false === $result['valid'], 'Сервер принял отрицательную цену.' );
velotok_required_server_assert( 'PRODUCT_PRICE_INVALID' === $result['code'], 'Для невалидной цены нужен код PRODUCT_PRICE_INVALID.' );
velotok_required_server_assert( isset( $result['message'] ) && false !== mb_stripos( $result['message'], 'цен' ), 'Для невалидной цены нет конкретного сообщения.' );

if ( $GLOBALS['velotok_required_server_failures'] ) {
	fwrite( STDERR, 'FAIL: ' . count( $GLOBALS['velotok_required_server_failures'] ) . ' проверок серверной валидации не прошли:' . PHP_EOL );

	foreach ( $GLOBALS['velotok_required_server_failures'] as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: обязательные атрибуты товара валидируются на сервере (TESTEB-61).' . PHP_EOL;
exit( 0 );
