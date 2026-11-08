<?php

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$module_path = getenv( 'VELOTOK_CHECKOUT_ORDER_STUB' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__, 3 ) . '/plugins/velotok-core/includes/checkout-order.php';
}

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль оформления заказа через WooCommerce Checkout.' . PHP_EOL );
	exit( 1 );
}

require_once $module_path;

$failures = array();

function velotok_theme_checkout_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

class Velotok_Theme_Test_Order {
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

class Velotok_Theme_Test_Checkout {
	public $calls = 0;
	private $next_id = 2000;

	public function create_order( $data ) {
		$this->calls++;

		return new Velotok_Theme_Test_Order( $this->next_id++, $data );
	}
}

function velotok_theme_checkout_data() {
	return array(
		'billing_first_name'      => 'Иван',
		'billing_last_name'       => 'Петров',
		'billing_email'           => 'ivan@example.com',
		'billing_address_1'       => 'ул. Ленина, 1',
		'billing_city'            => 'Москва',
		'billing_postcode'        => '101000',
		'payment_method'          => 'bacs',
		'delivery_method'         => 'cdek',
		'customer_note'           => 'Позвонить перед доставкой',
		'delivery_tracking_number'=> 'TRACK-123',
		'velotok_checkout_key'    => 'theme-checkout-key-1',
	);
}

function velotok_theme_checkout_context( $checkout, $overrides = array() ) {
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
		'data'              => velotok_theme_checkout_data(),
		'checkout'          => $checkout,
	);

	foreach ( $overrides as $key => $value ) {
		$context[ $key ] = $value;
	}

	return $context;
}

$guest_checkout = new Velotok_Theme_Test_Checkout();
$guest_result   = velotok_core_checkout_place_order( velotok_theme_checkout_context( $guest_checkout, array( 'is_user_logged_in' => false ) ) );
velotok_theme_checkout_assert( false === $guest_result['created'], 'Гость не должен оформлять заказ через Checkout.' );
velotok_theme_checkout_assert( 'CHECKOUT_AUTH_REQUIRED' === $guest_result['code'], 'Гость должен получать CHECKOUT_AUTH_REQUIRED.' );
velotok_theme_checkout_assert( 0 === $guest_checkout->calls, 'WC_Checkout не должен вызываться без авторизации.' );

$checkout = new Velotok_Theme_Test_Checkout();
$context  = velotok_theme_checkout_context( $checkout );
$result   = velotok_core_checkout_place_order( $context );

velotok_theme_checkout_assert( true === $result['created'], 'Авторизованный покупатель должен оформить заказ.' );
velotok_theme_checkout_assert( 1 === $checkout->calls, 'Заказ должен создаваться через WC_Checkout::create_order.' );

$details = velotok_core_checkout_order_details( $result['order'] );
velotok_theme_checkout_assert( 'bacs' === $details['payment_method'], 'Способ оплаты должен сохраняться.' );
velotok_theme_checkout_assert( 'cdek' === $details['delivery_method'], 'Способ доставки должен сохраняться.' );
velotok_theme_checkout_assert( 'Позвонить перед доставкой' === $details['comment'], 'Комментарий должен сохраняться.' );
velotok_theme_checkout_assert( 'TRACK-123' === $details['tracking_number'], 'Трек-номер доставки должен сохраняться.' );

$duplicate = velotok_core_checkout_place_order( $context );
velotok_theme_checkout_assert( false === $duplicate['created'], 'Повторная отправка не должна создавать заказ.' );
velotok_theme_checkout_assert( 'CHECKOUT_DUPLICATE' === $duplicate['code'], 'Повторная отправка должна распознаваться как дубль.' );
velotok_theme_checkout_assert( 1 === $checkout->calls, 'Повторная отправка не должна создавать второй заказ.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' интеграционных проверок оформления заказа не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: интеграция оформления заказа с WooCommerce Checkout проверена.' . PHP_EOL;
