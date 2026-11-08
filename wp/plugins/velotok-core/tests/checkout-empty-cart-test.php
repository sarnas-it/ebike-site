<?php
/**
 * Regression test for TESTEB-27: checkout is unavailable with an empty cart.
 *
 * Requirement TESTEB-27 / spec checkout:
 * «Оформление SHALL быть недоступно при пустой корзине».
 *
 * An empty WooCommerce cart must never reach WC_Checkout::create_order, even
 * when the customer is authenticated and the nonce and form data are valid.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/checkout-empty-cart-test.php
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

function velotok_checkout_empty_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

class Velotok_Checkout_Empty_Order {
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

class Velotok_Checkout_Empty_Checkout {
	public $calls = 0;
	private $next_id = 7000;

	public function create_order( $data ) {
		$this->calls++;

		return new Velotok_Checkout_Empty_Order( $this->next_id++, $data );
	}
}

class Velotok_Checkout_Empty_Cart {
	private $empty;

	public function __construct( $empty ) {
		$this->empty = (bool) $empty;
	}

	public function is_empty() {
		return $this->empty;
	}
}

function velotok_checkout_empty_data() {
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
		'velotok_checkout_key'     => 'checkout-empty-key-1',
	);
}

function velotok_checkout_empty_context( $checkout, $overrides = array() ) {
	$context = array(
		'is_user_logged_in' => true,
		'nonce_valid'       => true,
		'user_id'           => 42,
		'cart_items'        => array(
			array(
				'product_id' => 10,
				'quantity'   => 2,
			),
		),
		'data'              => velotok_checkout_empty_data(),
		'checkout'          => $checkout,
	);

	foreach ( $overrides as $key => $value ) {
		$context[ $key ] = $value;
	}

	return $context;
}

$contract = velotok_core_checkout_order_contract();

velotok_checkout_empty_assert(
	in_array( 'TESTEB-27', $contract['requirement_ids'], true ),
	'Контракт оформления должен ссылаться на TESTEB-27.'
);
velotok_checkout_empty_assert(
	isset( $contract['cart']['requirement'] ) && 'TESTEB-27' === $contract['cart']['requirement'],
	'Раздел корзины должен ссылаться на TESTEB-27.'
);
velotok_checkout_empty_assert(
	isset( $contract['cart']['required'] ) && true === $contract['cart']['required'],
	'Оформление должно требовать непустую корзину.'
);
velotok_checkout_empty_assert(
	isset( $contract['cart']['empty_code'] ) && 'CHECKOUT_EMPTY_CART' === $contract['cart']['empty_code'],
	'Пустая корзина должна обозначаться кодом CHECKOUT_EMPTY_CART.'
);

$empty_checkout = new Velotok_Checkout_Empty_Checkout();
$empty_context  = velotok_checkout_empty_context( $empty_checkout, array( 'cart_items' => array() ) );

$available = velotok_core_checkout_available( $empty_context );
velotok_checkout_empty_assert( false === $available['available'], 'Оформление должно быть недоступно при пустой корзине.' );
velotok_checkout_empty_assert( 'CHECKOUT_EMPTY_CART' === $available['code'], 'Пустая корзина должна давать CHECKOUT_EMPTY_CART.' );
velotok_checkout_empty_assert( '' !== $available['message'], 'Для пустой корзины должно быть сообщение.' );

$validation = velotok_core_checkout_validate( $empty_context );
velotok_checkout_empty_assert( false === $validation['valid'], 'Валидация пустой корзины должна падать.' );
velotok_checkout_empty_assert( 'CHECKOUT_EMPTY_CART' === $validation['code'], 'Валидация пустой корзины должна возвращать CHECKOUT_EMPTY_CART.' );

$result = velotok_core_checkout_place_order( $empty_context );
velotok_checkout_empty_assert( false === $result['created'], 'Пустая корзина не должна создавать заказ.' );
velotok_checkout_empty_assert( 'CHECKOUT_EMPTY_CART' === $result['code'], 'Пустое оформление должно возвращать CHECKOUT_EMPTY_CART.' );
velotok_checkout_empty_assert( null === $result['order'], 'Для пустой корзины заказ не должен создаваться.' );
velotok_checkout_empty_assert( 0 === $result['order_id'], 'Для пустой корзины не должно быть идентификатора заказа.' );
velotok_checkout_empty_assert( 0 === $empty_checkout->calls, 'WC_Checkout не должен вызываться при пустой корзине.' );

$flag_checkout = new Velotok_Checkout_Empty_Checkout();
$flag_context  = velotok_checkout_empty_context( $flag_checkout, array( 'cart_empty' => true ) );
$flag_result   = velotok_core_checkout_place_order( $flag_context );
velotok_checkout_empty_assert( false === $flag_result['created'], 'Флаг пустой корзины не должен создавать заказ.' );
velotok_checkout_empty_assert( 0 === $flag_checkout->calls, 'WC_Checkout не должен вызываться при флаге пустой корзины.' );

$cart_checkout = new Velotok_Checkout_Empty_Checkout();
$cart_context  = velotok_checkout_empty_context(
	$cart_checkout,
	array( 'cart' => new Velotok_Checkout_Empty_Cart( true ) )
);
unset( $cart_context['cart_items'] );
$cart_result = velotok_core_checkout_place_order( $cart_context );
velotok_checkout_empty_assert( false === $cart_result['created'], 'Пустой объект корзины WooCommerce не должен создавать заказ.' );
velotok_checkout_empty_assert( 'CHECKOUT_EMPTY_CART' === $cart_result['code'], 'Пустой объект корзины должен давать CHECKOUT_EMPTY_CART.' );
velotok_checkout_empty_assert( 0 === $cart_checkout->calls, 'WC_Checkout не должен вызываться для пустого объекта корзины.' );

$no_cart_context = velotok_checkout_empty_context( new Velotok_Checkout_Empty_Checkout() );
unset( $no_cart_context['cart_items'] );
$no_cart = velotok_core_checkout_available( $no_cart_context );
velotok_checkout_empty_assert( false === $no_cart['available'], 'Без товаров в корзине оформление должно быть недоступно.' );
velotok_checkout_empty_assert( 'CHECKOUT_EMPTY_CART' === $no_cart['code'], 'Отсутствие товаров должно давать CHECKOUT_EMPTY_CART.' );

$filled_checkout = new Velotok_Checkout_Empty_Checkout();
$filled_context  = velotok_checkout_empty_context(
	$filled_checkout,
	array( 'cart' => new Velotok_Checkout_Empty_Cart( false ) )
);
unset( $filled_context['cart_items'] );
$filled_available = velotok_core_checkout_available( $filled_context );
velotok_checkout_empty_assert( true === $filled_available['available'], 'Непустая корзина должна разрешать оформление.' );

$filled_result = velotok_core_checkout_place_order( $filled_context );
velotok_checkout_empty_assert( true === $filled_result['created'], 'Непустая корзина должна создавать заказ.' );
velotok_checkout_empty_assert( 1 === $filled_checkout->calls, 'Непустая корзина должна вызывать WC_Checkout один раз.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок пустой корзины при оформлении заказа не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: оформление заказа недоступно при пустой корзине (TESTEB-27).' . PHP_EOL;
exit( 0 );
