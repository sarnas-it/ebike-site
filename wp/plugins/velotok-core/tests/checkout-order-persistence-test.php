<?php
/**
 * Contract test for order persistence in WooCommerce Checkout.
 *
 * Requirement TESTEB-25 / spec checkout:
 * «Заказ SHALL сохранять состав, доставку, оплату и комментарий».
 *
 * The test proves that an authorized customer order created through
 * WC_Checkout keeps its line items, delivery method, payment method and
 * optional customer comment. The persisted values stay available both through
 * the order data/meta and through the standard WooCommerce order getters used
 * by the customer and the shop owner.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/checkout-order-persistence-test.php
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

function velotok_checkout_persistence_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

class Velotok_Checkout_Persistence_Order {
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

class Velotok_Checkout_Persistence_Shipping {
	private $method_id;

	public function __construct( $method_id ) {
		$this->method_id = $method_id;
	}

	public function get_method_id() {
		return $this->method_id;
	}
}

/**
 * WooCommerce-style order that only exposes the standard getters.
 *
 * It deliberately has no get_data() so the test proves the values are read
 * from the order itself and not only from the raw checkout payload.
 */
class Velotok_Checkout_Standard_Order {
	private $id;
	private $items;
	private $shipping;
	private $payment_method;
	private $customer_note;
	private $meta;

	public function __construct( $id, $items, $shipping, $payment_method, $customer_note, $meta = array() ) {
		$this->id             = $id;
		$this->items          = $items;
		$this->shipping       = $shipping;
		$this->payment_method = $payment_method;
		$this->customer_note  = $customer_note;
		$this->meta           = $meta;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_items() {
		return $this->items;
	}

	public function get_shipping_methods() {
		return $this->shipping;
	}

	public function get_payment_method() {
		return $this->payment_method;
	}

	public function get_customer_note() {
		return $this->customer_note;
	}

	public function get_meta( $key, $single = true ) {
		return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : '';
	}
}

class Velotok_Checkout_Persistence_Checkout {
	public $calls = 0;
	private $next_id = 4000;

	public function create_order( $data ) {
		$this->calls++;

		return new Velotok_Checkout_Persistence_Order( $this->next_id++, $data );
	}
}

function velotok_checkout_persistence_data() {
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
		'velotok_checkout_key'     => 'checkout-persistence-key-1',
	);
}

function velotok_checkout_persistence_context( $checkout, $overrides = array() ) {
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
			array(
				'product_id' => 11,
				'quantity'   => 1,
				'name'       => 'Шлем',
				'total'      => 5000,
			),
		),
		'data'              => velotok_checkout_persistence_data(),
		'checkout'          => $checkout,
	);

	foreach ( $overrides as $key => $value ) {
		$context[ $key ] = $value;
	}

	return $context;
}

$contract = velotok_core_checkout_order_contract();

velotok_checkout_persistence_assert(
	isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-25', $contract['requirement_ids'], true ),
	'Контракт оформления должен ссылаться на TESTEB-25.'
);
velotok_checkout_persistence_assert(
	isset( $contract['order']['requirement'] ) && 'TESTEB-25' === $contract['order']['requirement'],
	'Раздел заказа должен ссылаться на TESTEB-25.'
);
velotok_checkout_persistence_assert(
	isset( $contract['order']['persisted'] ) && true === $contract['order']['persisted'],
	'Заказ должен сохранять данные оформления.'
);
velotok_checkout_persistence_assert(
	isset( $contract['order']['composition_field'] ) && 'line_items' === $contract['order']['composition_field'],
	'Состав заказа должен сохраняться в line_items.'
);
velotok_checkout_persistence_assert(
	isset( $contract['order']['delivery_field'] ) && 'delivery_method' === $contract['order']['delivery_field'],
	'Способ доставки должен сохраняться в заказе.'
);
velotok_checkout_persistence_assert(
	isset( $contract['order']['payment_field'] ) && 'payment_method' === $contract['order']['payment_field'],
	'Способ оплаты должен сохраняться в заказе.'
);
velotok_checkout_persistence_assert(
	isset( $contract['order']['comment_field'] ) && 'customer_note' === $contract['order']['comment_field'],
	'Комментарий покупателя должен сохраняться в заказе.'
);
velotok_checkout_persistence_assert(
	isset( $contract['order']['visible_to'] ) && in_array( 'customer', $contract['order']['visible_to'], true ) && in_array( 'owner', $contract['order']['visible_to'], true ),
	'Данные заказа должны быть видны покупателю и владельцу.'
);

$checkout = new Velotok_Checkout_Persistence_Checkout();
$context  = velotok_checkout_persistence_context( $checkout );
$result   = velotok_core_checkout_place_order( $context );

velotok_checkout_persistence_assert( true === $result['created'], 'Авторизованный покупатель должен создать заказ.' );
velotok_checkout_persistence_assert( 1 === $checkout->calls, 'Заказ должен создаваться через WC_Checkout::create_order.' );

$created_order = $result['order'];
$created_data  = $created_order->get_data();

velotok_checkout_persistence_assert(
	isset( $created_data['line_items'] ) && 2 === count( $created_data['line_items'] ),
	'Состав заказа должен сохраняться полностью.'
);
velotok_checkout_persistence_assert( 'cdek' === $created_data['delivery_method'], 'Способ доставки должен сохраняться в заказе.' );
velotok_checkout_persistence_assert( 'bacs' === $created_data['payment_method'], 'Способ оплаты должен сохраняться в заказе.' );
velotok_checkout_persistence_assert( 'Позвонить перед доставкой' === $created_data['customer_note'], 'Комментарий покупателя должен сохраняться в заказе.' );
velotok_checkout_persistence_assert( 'TRACK-123' === $created_data['delivery_tracking_number'], 'Трек-номер доставки должен сохраняться в заказе.' );

velotok_checkout_persistence_assert( 'cdek' === $created_order->get_meta( '_velotok_delivery_method' ), 'Способ доставки должен сохраняться в метаданных заказа.' );
velotok_checkout_persistence_assert( 'bacs' === $created_order->get_meta( '_payment_method' ), 'Способ оплаты должен сохраняться в метаданных заказа.' );
velotok_checkout_persistence_assert( 'Позвонить перед доставкой' === $created_order->get_meta( '_customer_note' ), 'Комментарий должен сохраняться в метаданных заказа.' );
velotok_checkout_persistence_assert( 'TRACK-123' === $created_order->get_meta( '_velotok_delivery_tracking_number' ), 'Трек-номер должен сохраняться в метаданных заказа.' );

$details = velotok_core_checkout_order_details( $created_order );
velotok_checkout_persistence_assert( 'cdek' === $details['delivery_method'], 'Способ доставки должен быть доступен из заказа.' );
velotok_checkout_persistence_assert( 'bacs' === $details['payment_method'], 'Способ оплаты должен быть доступен из заказа.' );
velotok_checkout_persistence_assert( 'Позвонить перед доставкой' === $details['comment'], 'Комментарий должен быть доступен из заказа.' );
velotok_checkout_persistence_assert( 'TRACK-123' === $details['tracking_number'], 'Трек-номер должен быть доступен из заказа.' );
velotok_checkout_persistence_assert( 2 === count( $details['items'] ), 'Состав должен быть доступен из заказа.' );

$standard_order = new Velotok_Checkout_Standard_Order(
	77,
	array(
		array( 'product_id' => 10, 'quantity' => 2 ),
		array( 'product_id' => 11, 'quantity' => 1 ),
	),
	array( new Velotok_Checkout_Persistence_Shipping( 'cdek' ) ),
	'bacs',
	'Позвонить перед доставкой',
	array( '_velotok_delivery_tracking_number' => 'TRACK-123' )
);
$standard_details = velotok_core_checkout_order_details( $standard_order );

velotok_checkout_persistence_assert( 2 === count( $standard_details['items'] ), 'Состав должен читаться через стандартный get_items() заказа WooCommerce.' );
velotok_checkout_persistence_assert( 'cdek' === $standard_details['delivery_method'], 'Способ доставки должен читаться из shipping methods заказа.' );
velotok_checkout_persistence_assert( 'bacs' === $standard_details['payment_method'], 'Способ оплаты должен читаться через get_payment_method() заказа.' );
velotok_checkout_persistence_assert( 'Позвонить перед доставкой' === $standard_details['comment'], 'Комментарий должен читаться через get_customer_note() заказа.' );
velotok_checkout_persistence_assert( 'TRACK-123' === $standard_details['tracking_number'], 'Трек-номер должен читаться из метаданных заказа.' );

$optional_data = velotok_checkout_persistence_data();
unset( $optional_data['customer_note'], $optional_data['delivery_tracking_number'] );
$optional_data['velotok_checkout_key'] = 'checkout-persistence-key-optional';
$optional_checkout = new Velotok_Checkout_Persistence_Checkout();
$optional_result   = velotok_core_checkout_place_order(
	velotok_checkout_persistence_context( $optional_checkout, array( 'data' => $optional_data ) )
);

velotok_checkout_persistence_assert( true === $optional_result['created'], 'Заказ без необязательного комментария и трек-номера должен создаваться.' );
$optional_details = velotok_core_checkout_order_details( $optional_result['order'] );
velotok_checkout_persistence_assert( '' === $optional_details['comment'], 'Отсутствующий комментарий должен оставаться пустым.' );
velotok_checkout_persistence_assert( '' === $optional_details['tracking_number'], 'Отсутствующий трек-номер должен оставаться пустым.' );
velotok_checkout_persistence_assert( 2 === count( $optional_details['items'] ), 'Состав заказа должен сохраняться и без необязательных полей.' );

$buyer_viewer = array( 'id' => 42, 'role' => 'customer' );
$owner_viewer = array( 'id' => 7, 'role' => 'shop_manager' );
$other_viewer = array( 'id' => 99, 'role' => 'subscriber' );
velotok_checkout_persistence_assert( true === velotok_core_checkout_order_visible_to( $created_order, $buyer_viewer ), 'Покупатель должен видеть данные своего заказа.' );
velotok_checkout_persistence_assert( true === velotok_core_checkout_order_visible_to( $created_order, $owner_viewer ), 'Владелец магазина должен видеть данные заказа.' );
velotok_checkout_persistence_assert( false === velotok_core_checkout_order_visible_to( $created_order, $other_viewer ), 'Посторонний пользователь не должен видеть данные заказа.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок сохранения заказа не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: заказ сохраняет состав, доставку, оплату и комментарий (TESTEB-25).' . PHP_EOL;
exit( 0 );
