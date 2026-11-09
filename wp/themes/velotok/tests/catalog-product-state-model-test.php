<?php
/**
 * Behaviour test for TESTEB-16: every catalog product has a model state.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/catalog-product-state-model-test.php
 *
 * The storefront keeps a fixed product state model (the WooCommerce post
 * statuses). A product is shown only when its state belongs to that model and
 * is public. Unknown states and states outside the model must never be treated
 * as published, even when the rest of the product data is valid.
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
function velotok_product_state_model_assert( $condition, $message ) {
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
 * Standalone product double with an explicit model state.
 */
class Velotok_Product_State_Model_Product {
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
	public function get_attributes() { return array(); }
	public function is_type( $type ) { return isset( $this->data['type'] ) && $type === $this->data['type']; }
	public function is_purchasable() { return true; }
	public function is_in_stock() { return true; }
	public function add_to_cart_url() { return $this->get_permalink(); }
}

/**
 * Build a product fixture.
 *
 * @param array $overrides Field overrides.
 * @return Velotok_Product_State_Model_Product
 */
function velotok_product_state_model_fixture( $overrides = array() ) {
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
		'type'               => 'simple',
	);

	return new Velotok_Product_State_Model_Product( array_merge( $defaults, $overrides ) );
}

$expected_states = array( 'publish', 'draft', 'pending', 'private', 'trash', 'future', 'auto-draft' );

velotok_product_state_model_assert( function_exists( 'velotok_product_state_model' ), 'Не определена модель состояний товара.' );
velotok_product_state_model_assert( function_exists( 'velotok_product_states' ), 'Не определён список состояний товара.' );
velotok_product_state_model_assert( function_exists( 'velotok_product_normalize_state' ), 'Не определена нормализация состояния товара.' );
velotok_product_state_model_assert( function_exists( 'velotok_product_state_is_valid' ), 'Не определена проверка состояния по модели.' );

$model  = velotok_product_state_model();
$states = velotok_product_states();

velotok_product_state_model_assert( is_array( $model ), 'Модель состояний должна быть массивом.' );
velotok_product_state_model_assert( array_keys( $model ) === $expected_states, 'Модель состояний должна содержать статусы WooCommerce.' );
velotok_product_state_model_assert( $states === $expected_states, 'Список состояний должен совпадать с моделью.' );

foreach ( $expected_states as $state ) {
	velotok_product_state_model_assert( isset( $model[ $state ] ), 'В модели нет состояния «' . $state . '».' );
	velotok_product_state_model_assert( isset( $model[ $state ]['id'] ) && $state === $model[ $state ]['id'], 'У состояния «' . $state . '» неверный идентификатор.' );
	velotok_product_state_model_assert( isset( $model[ $state ]['label'] ) && '' !== $model[ $state ]['label'], 'У состояния «' . $state . '» нет подписи.' );
	velotok_product_state_model_assert( isset( $model[ $state ]['public'] ), 'У состояния «' . $state . '» нет признака публичности.' );
	velotok_product_state_model_assert( true === velotok_product_state_is_valid( $state ), 'Состояние модели отклонено валидацией: ' . $state );
}

velotok_product_state_model_assert( array( 'publish' ) === velotok_product_public_states(), 'Публичным должно быть только состояние publish.' );

foreach ( array( '', 'unknown', 'archived', 'garbage', 'publish;drop' ) as $invalid ) {
	velotok_product_state_model_assert( false === velotok_product_state_is_valid( $invalid ), 'Состояние вне модели не должно считаться допустимым: ' . $invalid );
}

foreach ( array( 'published', 'public', 'live', 'active' ) as $alias ) {
	velotok_product_state_model_assert( 'publish' === velotok_product_normalize_state( $alias ), 'Псевдоним publish не нормализован: ' . $alias );
}

velotok_product_state_model_assert( 'draft' === velotok_product_normalize_state( 'unpublished' ), 'Псевдоним draft не нормализован.' );
velotok_product_state_model_assert( 'future' === velotok_product_normalize_state( 'scheduled' ), 'Псевдоним будущего состояния не нормализован.' );
velotok_product_state_model_assert( 'trash' === velotok_product_normalize_state( 'deleted' ), 'Псевдоним удалённого состояния не нормализован.' );
velotok_product_state_model_assert( false === velotok_product_state_is_valid( velotok_product_normalize_state( 'archived' ) ), 'Неизвестное состояние не должно попадать в модель после нормализации.' );

/*
 * Every product reports one of the model states, and only the public state is
 * published on the storefront.
 */
foreach ( $expected_states as $index => $state ) {
	$product = velotok_product_state_model_fixture(
		array(
			'id'     => 100 + $index,
			'name'   => 'Товар ' . $state,
			'slug'   => 'state-' . $state,
			'status' => $state,
		)
	);

	velotok_product_state_model_assert( $state === velotok_product_status( $product ), 'Товар не находится в состоянии модели: ' . $state );
	velotok_product_state_model_assert(
		( 'publish' === $state ) === velotok_product_is_published( $product ),
		'Публичность товара не совпадает с состоянием модели: ' . $state
	);
}

$unknown = velotok_product_state_model_fixture( array( 'id' => 300, 'name' => 'Архив', 'status' => 'archived' ) );
velotok_product_state_model_assert( false === velotok_product_is_published( $unknown ), 'Товар с состоянием вне модели не должен считаться опубликованным.' );
velotok_product_state_model_assert( false === velotok_is_valid_product( $unknown ), 'Товар с состоянием вне модели не должен попадать на витрину.' );

$GLOBALS['velotok_test_products'] = array();

foreach ( $expected_states as $index => $state ) {
	$GLOBALS['velotok_test_products'][] = velotok_product_state_model_fixture(
		array(
			'id'     => 400 + $index,
			'name'   => 'Витрина ' . $state,
			'slug'   => 'storefront-' . $state,
			'status' => $state,
		)
	);
}

$GLOBALS['velotok_test_products'][] = velotok_product_state_model_fixture( array( 'id' => 500, 'name' => 'Витрина вне модели', 'slug' => 'storefront-unknown', 'status' => 'archived' ) );

$models = velotok_get_home_models();

velotok_product_state_model_assert( 1 === count( $models ), 'Витрина должна оставлять только товар в публичном состоянии модели.' );
velotok_product_state_model_assert( isset( $models[0]['name'] ) && 'Витрина publish' === $models[0]['name'], 'На витрину попал товар не в публичном состоянии модели.' );

$GLOBALS['velotok_test_products'] = array();

$fallback = new Velotok_Product_State_Model_Product( array( 'id' => 600, 'name' => 'Без явного статуса', 'slug' => 'without-status' ) );
$GLOBALS['velotok_test_products'][] = $fallback;

$models = velotok_get_home_models();
velotok_product_state_model_assert( 1 === count( $models ), 'Товар без явно заданного статуса должен оставаться в допустимом состоянии publish.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: товар находится в одном из состояний модели (TESTEB-16).' . PHP_EOL;
