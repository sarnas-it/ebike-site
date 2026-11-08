<?php
/**
 * Regression test for TESTEB-24: checkout requires authorization.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/checkout-auth-required-test.php
 *
 * A guest must not create an order through WooCommerce Checkout even when the
 * cart, nonce and form are valid. WooCommerce is also configured to require a
 * logged-in customer, and the focused behavior is checked directly through the
 * checkout helpers.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$module_path = getenv( 'VELOTOK_CHECKOUT_ORDER_STUB' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__ ) . '/includes/checkout-order.php';
}

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль оформления заказа через WooCommerce Checkout.' . PHP_EOL );
	exit( 1 );
}

require_once $module_path;

$failures = array();

function velotok_checkout_auth_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

class Velotok_Checkout_Auth_Test_Order {
	public $id;
	public $data;
	public $meta = array();

	public function __construct( $id, $data ) {
		$this->id   = $id;
		$this->data = $data;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_data() {
		return $this->data;
	}

	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	public function get_meta( $key, $single = true ) {
		return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : '';
	}

	public function save() {
		return true;
	}
}

class Velotok_Checkout_Auth_Test_Checkout {
	public $calls = 0;
	private $next_id = 3000;

	public function create_order( $data ) {
		$this->calls++;

		return new Velotok_Checkout_Auth_Test_Order( $this->next_id++, $data );
	}
}

function velotok_checkout_auth_valid_data() {
	return array(
		'billing_first_name'       => 'Иван',
		'billing_last_name'        => 'Петров',
		'billing_email'            => 'ivan@example.com',
		'billing_address_1'        => 'ул. Ленина, 1',
		'billing_city'             => 'Москва',
		'billing_postcode'         => '101000',
		'payment_method'           => 'bacs',
		'delivery_method'          => 'cdek',
		'customer_note'            => 'Позвонить перед доставкой',
		'delivery_tracking_number' => 'TRACK-123',
		'velotok_checkout_key'     => 'checkout-auth-key-1',
	);
}

function velotok_checkout_auth_context( $checkout, $overrides = array() ) {
	$context = array(
		'is_user_logged_in' => true,
		'nonce_valid'       => true,
		'user_id'           => 42,
		'cart_items'        => array(
			array(
				'product_id' => 10,
				'quantity'   => 2,
				'name'       => 'Электровелосипед',
				'total'      => 100000,
			),
		),
		'data'              => velotok_checkout_auth_valid_data(),
		'checkout'          => $checkout,
	);

	foreach ( $overrides as $key => $value ) {
		$context[ $key ] = $value;
	}

	return $context;
}

$contract = velotok_core_checkout_order_contract();

velotok_checkout_auth_assert( true === $contract['auth']['required'], 'Оформление должно требовать авторизации.' );
velotok_checkout_auth_assert( false === $contract['auth']['guest_checkout'], 'Гостевое оформление должно быть запрещено.' );
velotok_checkout_auth_assert( 'CHECKOUT_AUTH_REQUIRED' === $contract['auth']['code'], 'Гость должен получать CHECKOUT_AUTH_REQUIRED.' );
velotok_checkout_auth_assert( in_array( 'TESTEB-24', $contract['requirement_ids'], true ), 'Контракт должен ссылаться на TESTEB-24.' );

velotok_checkout_auth_assert( false === velotok_core_checkout_is_authenticated( array() ), 'Без авторизации оформление должно считаться недоступным.' );

$guest_checkout = new Velotok_Checkout_Auth_Test_Checkout();
$guest_context  = velotok_checkout_auth_context( $guest_checkout, array( 'is_user_logged_in' => false ) );

$guest_auth = velotok_core_checkout_auth_result( $guest_context );
velotok_checkout_auth_assert( true === $guest_auth['required'], 'Проверка авторизации должна быть обязательной.' );
velotok_checkout_auth_assert( false === $guest_auth['authenticated'], 'Гость не должен быть авторизован.' );
velotok_checkout_auth_assert( 'CHECKOUT_AUTH_REQUIRED' === $guest_auth['code'], 'Проверка авторизации гостя должна возвращать CHECKOUT_AUTH_REQUIRED.' );
velotok_checkout_auth_assert( '' !== $guest_auth['message'], 'Для гостя должно быть сообщение о необходимости войти.' );

$guest_available = velotok_core_checkout_available( $guest_context );
velotok_checkout_auth_assert( false === $guest_available['available'], 'Оформление должно быть недоступно гостю.' );
velotok_checkout_auth_assert( 'CHECKOUT_AUTH_REQUIRED' === $guest_available['code'], 'Недоступное гостю оформление должно давать CHECKOUT_AUTH_REQUIRED.' );

$guest_validation = velotok_core_checkout_validate( $guest_context );
velotok_checkout_auth_assert( false === $guest_validation['valid'], 'Валидация гостевого оформления должна падать.' );
velotok_checkout_auth_assert( 'CHECKOUT_AUTH_REQUIRED' === $guest_validation['code'], 'Валидация гостя должна возвращать CHECKOUT_AUTH_REQUIRED.' );

$guest_result = velotok_core_checkout_place_order( $guest_context );
velotok_checkout_auth_assert( false === $guest_result['created'], 'Гость не должен создавать заказ.' );
velotok_checkout_auth_assert( 'CHECKOUT_AUTH_REQUIRED' === $guest_result['code'], 'Гость должен получать CHECKOUT_AUTH_REQUIRED.' );
velotok_checkout_auth_assert( null === $guest_result['order'], 'Для гостя заказ не должен создаваться.' );
velotok_checkout_auth_assert( 0 === $guest_checkout->calls, 'WC_Checkout не должен вызываться без авторизации.' );

velotok_checkout_auth_assert( true === velotok_core_checkout_force_login( false ), 'WooCommerce должен требовать вход независимо от настроек.' );
velotok_checkout_auth_assert( false === velotok_core_checkout_disable_guest_checkout( true ), 'Гостевое оформление WooCommerce должно быть отключено.' );
velotok_checkout_auth_assert( $contract['auth']['message'] === velotok_core_checkout_must_be_logged_in_message( 'other' ), 'Сообщение WooCommerce должно объяснять необходимость входа.' );

$checkout = new Velotok_Checkout_Auth_Test_Checkout();
$context  = velotok_checkout_auth_context( $checkout );

$authenticated_auth = velotok_core_checkout_auth_result( $context );
velotok_checkout_auth_assert( true === $authenticated_auth['authenticated'], 'Авторизованный покупатель должен проходить проверку.' );

$result = velotok_core_checkout_place_order( $context );
velotok_checkout_auth_assert( true === $result['created'], 'Авторизованный покупатель должен оформлять заказ.' );
velotok_checkout_auth_assert( 'CHECKOUT_CREATED' === $result['code'], 'Авторизованный заказ должен создаваться.' );
velotok_checkout_auth_assert( 1 === $checkout->calls, 'Авторизованный заказ должен создаваться через WC_Checkout.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок авторизации оформления заказа не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: оформление заказа требует авторизации (TESTEB-24).' . PHP_EOL;
exit( 0 );
