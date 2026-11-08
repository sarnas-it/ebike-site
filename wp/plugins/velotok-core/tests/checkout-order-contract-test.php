<?php

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

function velotok_checkout_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

class Velotok_Test_Order {
	public $id;
	public $data;
	public $meta = array();
	public $saves = 0;

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
		$this->saves++;
	}
}

class Velotok_Test_Checkout {
	public $calls = 0;
	public $orders = array();
	private $next_id = 1000;

	public function create_order( $data ) {
		$this->calls++;
		$order          = new Velotok_Test_Order( $this->next_id++, $data );
		$this->orders[] = $order;

		return $order;
	}
}

function velotok_checkout_valid_data() {
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
		'velotok_checkout_key'    => 'checkout-key-1',
	);
}

function velotok_checkout_context( $checkout, $overrides = array() ) {
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
		'data'              => velotok_checkout_valid_data(),
		'checkout'          => $checkout,
	);

	foreach ( $overrides as $key => $value ) {
		$context[ $key ] = $value;
	}

	return $context;
}

$contract = velotok_core_checkout_order_contract();

velotok_checkout_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
velotok_checkout_assert( isset( $contract['spec'] ) && 'checkout' === $contract['spec'], 'Контракт должен относиться к спеке checkout.' );
velotok_checkout_assert( isset( $contract['requirement'] ) && 'TESTEB-23' === $contract['requirement'], 'Контракт должен ссылаться на TESTEB-23.' );
velotok_checkout_assert( isset( $contract['checkout_class'] ) && 'WC_Checkout' === $contract['checkout_class'], 'Заказ должен создаваться через WC_Checkout.' );
velotok_checkout_assert( isset( $contract['uses_wc_checkout'] ) && true === $contract['uses_wc_checkout'], 'Оформление должно использовать WooCommerce Checkout.' );
velotok_checkout_assert( isset( $contract['nonce']['server_side'] ) && true === $contract['nonce']['server_side'], 'Nonce должен проверяться на сервере.' );
velotok_checkout_assert( isset( $contract['auth']['required'] ) && true === $contract['auth']['required'], 'Оформление должно требовать авторизации.' );
velotok_checkout_assert( isset( $contract['auth']['guest_checkout'] ) && false === $contract['auth']['guest_checkout'], 'Гостевое оформление должно быть запрещено.' );
velotok_checkout_assert( isset( $contract['cart']['empty_code'] ) && 'CHECKOUT_EMPTY_CART' === $contract['cart']['empty_code'], 'Пустая корзина должна блокировать оформление.' );
velotok_checkout_assert( isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-26', $contract['requirement_ids'], true ), 'Контракт должен ссылаться на TESTEB-26.' );
velotok_checkout_assert( isset( $contract['idempotency']['requirement'] ) && 'TESTEB-26' === $contract['idempotency']['requirement'], 'Раздел идемпотентности должен ссылаться на TESTEB-26.' );
velotok_checkout_assert( isset( $contract['idempotency']['prevents_duplicate'] ) && true === $contract['idempotency']['prevents_duplicate'], 'Повторная отправка не должна создавать дубль.' );
velotok_checkout_assert( isset( $contract['order']['visible_to'] ) && in_array( 'customer', $contract['order']['visible_to'], true ), 'Данные заказа должны быть видны покупателю.' );
velotok_checkout_assert( isset( $contract['order']['visible_to'] ) && in_array( 'owner', $contract['order']['visible_to'], true ), 'Данные заказа должны быть видны владельцу.' );
velotok_checkout_assert( isset( $contract['validation']['optional_fields'] ) && in_array( 'customer_note', $contract['validation']['optional_fields'], true ), 'Комментарий должен быть необязательным.' );
velotok_checkout_assert( isset( $contract['validation']['optional_fields'] ) && in_array( 'delivery_tracking_number', $contract['validation']['optional_fields'], true ), 'Трек-номер должен быть необязательным.' );

$guest_checkout = new Velotok_Test_Checkout();
$guest_context  = velotok_checkout_context( $guest_checkout, array( 'is_user_logged_in' => false ) );
$guest_result   = velotok_core_checkout_place_order( $guest_context );
velotok_checkout_assert( false === $guest_result['created'], 'Гость не должен создавать заказ.' );
velotok_checkout_assert( 'CHECKOUT_AUTH_REQUIRED' === $guest_result['code'], 'Гость должен получать CHECKOUT_AUTH_REQUIRED.' );
velotok_checkout_assert( 0 === $guest_checkout->calls, 'WC_Checkout не должен вызываться для гостя.' );

$unavailable = velotok_core_checkout_available( $guest_context );
velotok_checkout_assert( false === $unavailable['available'], 'Оформление должно быть недоступно гостю.' );

$empty_checkout = new Velotok_Test_Checkout();
$empty_context  = velotok_checkout_context( $empty_checkout, array( 'cart_items' => array() ) );
$empty_result   = velotok_core_checkout_place_order( $empty_context );
velotok_checkout_assert( false === $empty_result['created'], 'Пустая корзина не должна создавать заказ.' );
velotok_checkout_assert( 'CHECKOUT_EMPTY_CART' === $empty_result['code'], 'Пустая корзина должна давать CHECKOUT_EMPTY_CART.' );
velotok_checkout_assert( 0 === $empty_checkout->calls, 'WC_Checkout не должен вызываться при пустой корзине.' );

$empty_available = velotok_core_checkout_available( $empty_context );
velotok_checkout_assert( false === $empty_available['available'], 'Оформление должно быть недоступно при пустой корзине.' );

$nonce_checkout = new Velotok_Test_Checkout();
$nonce_context  = velotok_checkout_context( $nonce_checkout, array( 'nonce_valid' => false ) );
$nonce_result   = velotok_core_checkout_place_order( $nonce_context );
velotok_checkout_assert( false === $nonce_result['created'], 'Невалидный nonce не должен создавать заказ.' );
velotok_checkout_assert( 'CHECKOUT_INVALID_NONCE' === $nonce_result['code'], 'Невалидный nonce должен давать CHECKOUT_INVALID_NONCE.' );
velotok_checkout_assert( 0 === $nonce_checkout->calls, 'WC_Checkout не должен вызываться при невалидном nonce.' );

$invalid_data = velotok_checkout_valid_data();
unset( $invalid_data['billing_email'], $invalid_data['payment_method'] );
$invalid_checkout = new Velotok_Test_Checkout();
$invalid_context  = velotok_checkout_context( $invalid_checkout, array( 'data' => $invalid_data ) );
$invalid_result   = velotok_core_checkout_place_order( $invalid_context );
velotok_checkout_assert( false === $invalid_result['created'], 'Невалидная форма не должна создавать заказ.' );
velotok_checkout_assert( 'CHECKOUT_INVALID_FORM' === $invalid_result['code'], 'Невалидная форма должна давать CHECKOUT_INVALID_FORM.' );
velotok_checkout_assert( isset( $invalid_result['errors']['billing_email'] ), 'Должна быть ошибка обязательного email.' );
velotok_checkout_assert( isset( $invalid_result['errors']['payment_method'] ), 'Должна быть ошибка обязательного способа оплаты.' );
velotok_checkout_assert( 0 === $invalid_checkout->calls, 'WC_Checkout не должен вызываться при невалидной форме.' );

$checkout = new Velotok_Test_Checkout();
$context  = velotok_checkout_context( $checkout );
$result   = velotok_core_checkout_place_order( $context );

velotok_checkout_assert( true === $result['created'], 'Авторизованный покупатель должен создать заказ.' );
velotok_checkout_assert( 'CHECKOUT_CREATED' === $result['code'], 'Успешное оформление должно давать CHECKOUT_CREATED.' );
velotok_checkout_assert( 1 === $checkout->calls, 'Заказ должен создаваться ровно через один вызов WC_Checkout::create_order.' );
velotok_checkout_assert( 1000 === $result['order_id'], 'Созданный заказ должен иметь идентификатор.' );

$created_order = $result['order'];
$created_data  = $created_order->get_data();
velotok_checkout_assert( isset( $created_data['line_items'] ) && 1 === count( $created_data['line_items'] ), 'Состав заказа должен сохраняться.' );
velotok_checkout_assert( 'bacs' === $created_data['payment_method'], 'Способ оплаты должен сохраняться в заказе.' );
velotok_checkout_assert( 'cdek' === $created_data['delivery_method'], 'Способ доставки должен сохраняться в заказе.' );
velotok_checkout_assert( 'Позвонить перед доставкой' === $created_data['customer_note'], 'Комментарий покупателя должен сохраняться.' );
velotok_checkout_assert( 'TRACK-123' === $created_data['delivery_tracking_number'], 'Трек-номер доставки должен сохраняться.' );

$details = velotok_core_checkout_order_details( $created_order );
velotok_checkout_assert( 'bacs' === $details['payment_method'], 'Способ оплаты должен быть доступен из заказа.' );
velotok_checkout_assert( 'cdek' === $details['delivery_method'], 'Способ доставки должен быть доступен из заказа.' );
velotok_checkout_assert( 'Позвонить перед доставкой' === $details['comment'], 'Комментарий должен быть доступен из заказа.' );
velotok_checkout_assert( 'TRACK-123' === $details['tracking_number'], 'Трек-номер должен быть доступен из заказа.' );
velotok_checkout_assert( isset( $details['items'] ) && 1 === count( $details['items'] ), 'Состав должен быть доступен из заказа.' );
velotok_checkout_assert( 'bacs' === $created_order->get_meta( '_payment_method' ), 'Способ оплаты должен сохраняться в метаданных заказа.' );
velotok_checkout_assert( 'cdek' === $created_order->get_meta( '_velotok_delivery_method' ), 'Способ доставки должен сохраняться в метаданных заказа.' );
velotok_checkout_assert( 'TRACK-123' === $created_order->get_meta( '_velotok_delivery_tracking_number' ), 'Трек-номер должен сохраняться в метаданных заказа.' );

$buyer_viewer = array( 'id' => 42, 'role' => 'customer' );
$owner_viewer = array( 'id' => 7, 'role' => 'shop_manager' );
$other_viewer = array( 'id' => 99, 'role' => 'subscriber' );
velotok_checkout_assert( true === velotok_core_checkout_order_visible_to( $created_order, $buyer_viewer ), 'Покупатель должен видеть данные своего заказа.' );
velotok_checkout_assert( true === velotok_core_checkout_order_visible_to( $created_order, $owner_viewer ), 'Владелец магазина должен видеть данные заказа.' );
velotok_checkout_assert( false === velotok_core_checkout_order_visible_to( $created_order, $other_viewer ), 'Посторонний пользователь не должен видеть данные заказа.' );

$optional_data = velotok_checkout_valid_data();
unset( $optional_data['customer_note'], $optional_data['delivery_tracking_number'] );
$optional_data['velotok_checkout_key'] = 'checkout-key-optional';
$optional_checkout = new Velotok_Test_Checkout();
$optional_context  = velotok_checkout_context( $optional_checkout, array( 'data' => $optional_data ) );
$optional_result   = velotok_core_checkout_place_order( $optional_context );
velotok_checkout_assert( true === $optional_result['created'], 'Заказ без необязательных комментария и трек-номера должен создаваться.' );
$optional_details = velotok_core_checkout_order_details( $optional_result['order'] );
velotok_checkout_assert( '' === $optional_details['comment'], 'Отсутствующий комментарий должен быть пустым.' );
velotok_checkout_assert( '' === $optional_details['tracking_number'], 'Отсутствующий трек-номер должен быть пустым.' );

$duplicate_result = velotok_core_checkout_place_order( $context );
velotok_checkout_assert( false === $duplicate_result['created'], 'Повторная отправка не должна создавать заказ.' );
velotok_checkout_assert( true === $duplicate_result['duplicate'], 'Повторная отправка должна распознаваться как дубль.' );
velotok_checkout_assert( 'CHECKOUT_DUPLICATE' === $duplicate_result['code'], 'Дубль должен давать CHECKOUT_DUPLICATE.' );
velotok_checkout_assert( 1 === $checkout->calls, 'Повторная отправка не должна вызывать WC_Checkout::create_order ещё раз.' );
velotok_checkout_assert( 1000 === $duplicate_result['order_id'], 'Повторная отправка должна возвращать существующий заказ.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок оформления заказа не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: оформление заказа через WooCommerce Checkout проверено.' . PHP_EOL;
