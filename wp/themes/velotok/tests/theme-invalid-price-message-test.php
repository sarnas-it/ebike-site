<?php
/**
 * Behaviour test for TESTEB-62: an invalid price is rejected with a concrete
 * message.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/theme-invalid-price-message-test.php
 *
 * The storefront and the server-side validator must reject a negative or
 * non-numeric price and explain the problem. A missing price keeps its own
 * required-attribute message, and a zero price stays valid because the card
 * renders it as «Цена по запросу».
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

$GLOBALS['velotok_invalid_price_failures'] = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_invalid_price_assert( $condition, $message ) {
	if ( ! $condition ) {
		$GLOBALS['velotok_invalid_price_failures'][] = $message;
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
class Velotok_Invalid_Price_Product {
	private $data;

	public function __construct( $data = array() ) {
		$this->data = $data;
	}

	public function get_id() { return isset( $this->data['id'] ) ? (int) $this->data['id'] : 0; }
	public function get_name() { return isset( $this->data['name'] ) ? $this->data['name'] : ''; }
	public function get_description() { return isset( $this->data['description'] ) ? $this->data['description'] : ''; }
	public function get_short_description() { return isset( $this->data['short_description'] ) ? $this->data['short_description'] : ''; }
	public function get_price() { return array_key_exists( 'price', $this->data ) ? $this->data['price'] : ''; }
	public function get_image_id() { return isset( $this->data['image_id'] ) ? (int) $this->data['image_id'] : 0; }
}

require_once dirname( __DIR__ ) . '/inc/woocommerce.php';

/**
 * Build a product double with an overridable price.
 *
 * @param mixed $price Price value.
 * @return Velotok_Invalid_Price_Product
 */
function velotok_invalid_price_product( $price ) {
	return new Velotok_Invalid_Price_Product(
		array(
			'id'          => 1,
			'name'        => 'Ривьера',
			'description' => 'Городской электровелосипед для ежедневных поездок.',
			'price'       => $price,
			'image_id'    => 101,
		)
	);
}

$contract = velotok_product_validation_contract();

velotok_invalid_price_assert( 'TESTEB-62' === $contract['price_requirement'], 'Контракт не ссылается на TESTEB-62.' );
velotok_invalid_price_assert( ! empty( $contract['invalid_price_message'] ), 'В контракте нет конкретного сообщения о невалидной цене.' );
velotok_invalid_price_assert( false !== mb_stripos( $contract['invalid_price_message'], 'цен' ), 'Сообщение о невалидной цене не упоминает цену.' );
velotok_invalid_price_assert( 'PRODUCT_PRICE_INVALID' === $contract['invalid_price_code'], 'Неверный код невалидной цены.' );

$invalid_cases = array(
	'отрицательная' => -100,
	'дробная отрицательная' => '-0.01',
	'нечисловая' => 'дорого',
);

foreach ( $invalid_cases as $label => $price ) {
	$result = velotok_validate_product_required_attributes( velotok_invalid_price_product( $price ) );

	velotok_invalid_price_assert( false === $result['valid'], 'Сервер принял невалидную цену: ' . $label );
	velotok_invalid_price_assert( 'PRODUCT_PRICE_INVALID' === $result['code'], 'Для цены «' . $label . '» нужен код PRODUCT_PRICE_INVALID.' );
	velotok_invalid_price_assert( ! empty( $result['message'] ), 'Для цены «' . $label . '» нет конкретного сообщения.' );
	velotok_invalid_price_assert( false !== mb_stripos( $result['message'], 'цен' ), 'Сообщение для цены «' . $label . '» не упоминает цену.' );
	velotok_invalid_price_assert( $result['message'] === $contract['invalid_price_message'], 'Сообщение для цены «' . $label . '» отличается от контрактного.' );
	velotok_invalid_price_assert( isset( $result['errors']['price'] ) && $result['message'] === $result['errors']['price'], 'Ошибка цены не совпадает с сообщением результата.' );
}

$missing = velotok_validate_product_required_attributes( velotok_invalid_price_product( '' ) );

velotok_invalid_price_assert( false === $missing['valid'], 'Сервер принял пустую цену.' );
velotok_invalid_price_assert( 'PRODUCT_REQUIRED_ATTRIBUTE_MISSING' === $missing['code'], 'Для пустой цены нужен код обязательного атрибута.' );
velotok_invalid_price_assert( ! empty( $missing['message'] ), 'Для пустой цены нет конкретного сообщения.' );
velotok_invalid_price_assert( false !== mb_stripos( $missing['message'], 'цен' ), 'Сообщение о пустой цене не упоминает цену.' );

$valid = velotok_validate_product_required_attributes( velotok_invalid_price_product( 0 ) );

velotok_invalid_price_assert( true === $valid['valid'], 'Нулевая цена должна оставаться валидной.' );
velotok_invalid_price_assert( '' === $valid['message'], 'У валидной цены не должно быть сообщения об ошибке.' );

$request = velotok_validate_product_request(
	array(
		'name'        => 'Ривьера',
		'description' => 'Описание товара',
		'image'       => 'model-city-classic-1-web.jpg',
		'price'       => '-100',
	)
);

velotok_invalid_price_assert( false === $request['valid'], 'Сервер принял отрицательную цену из запроса.' );
velotok_invalid_price_assert( 'PRODUCT_PRICE_INVALID' === $request['code'], 'Цена из запроса отклонена без кода PRODUCT_PRICE_INVALID.' );
velotok_invalid_price_assert( false !== mb_stripos( $request['message'], 'цен' ), 'Для цены из запроса нет конкретного сообщения.' );

$catalog = velotok_catalog_filter_contract();

velotok_invalid_price_assert( isset( $catalog['validation']['invalid_price_message'] ), 'Контракт каталога не сообщает текст ошибки цены.' );
velotok_invalid_price_assert( false !== mb_stripos( $catalog['validation']['invalid_price_message'], 'цен' ), 'Текст ошибки цены в контракте каталога не упоминает цену.' );

if ( $GLOBALS['velotok_invalid_price_failures'] ) {
	fwrite( STDERR, 'FAIL: ' . count( $GLOBALS['velotok_invalid_price_failures'] ) . ' проверок невалидной цены не прошли:' . PHP_EOL );

	foreach ( $GLOBALS['velotok_invalid_price_failures'] as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: невалидная цена отклоняется с конкретным сообщением (TESTEB-62).' . PHP_EOL;
exit( 0 );
