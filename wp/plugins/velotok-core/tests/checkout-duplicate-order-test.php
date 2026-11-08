<?php
/**
 * Regression test for TESTEB-26: repeated checkout submission creates no duplicate.
 *
 * Requirement TESTEB-26 / spec checkout:
 * «Повторная отправка формы SHALL NOT создавать дубль заказа».
 *
 * A repeated form submission must be recognised as a duplicate even when it
 * arrives in a new request where the in-process registry is empty. The order is
 * looked up by the idempotency key persisted in the order meta, so the test
 * models the WooCommerce order store through wc_get_orders() and checks that the
 * lookup uses a meta query (the supported WooCommerce API) rather than an
 * unprocessed meta_key shortcut.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/checkout-duplicate-order-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_duplicate_orders']  = array();
$GLOBALS['velotok_duplicate_queries'] = array();

if ( ! function_exists( 'wc_get_orders' ) ) {
	/**
	 * Mimics the WooCommerce order store lookup by a persisted meta value.
	 *
	 * WC_Order_Query understands a meta_query argument. The legacy meta_key /
	 * meta_value shortcut is deliberately not interpreted here, because it is
	 * not a supported way of filtering orders in WooCommerce.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	function wc_get_orders( $args = array() ) {
		$GLOBALS['velotok_duplicate_queries'][] = $args;

		$key = null;

		if ( ! empty( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
			foreach ( $args['meta_query'] as $clause ) {
				if ( is_array( $clause ) && isset( $clause['key'], $clause['value'] ) && '_velotok_checkout_key' === $clause['key'] ) {
					$key = (string) $clause['value'];
					break;
				}
			}
		}

		if ( null !== $key && isset( $GLOBALS['velotok_duplicate_orders'][ $key ] ) ) {
			return array( $GLOBALS['velotok_duplicate_orders'][ $key ] );
		}

		return array();
	}
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

function velotok_checkout_duplicate_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

class Velotok_Checkout_Duplicate_Order {
	public $id;
	public $data;
	public $meta = array();
	public $saves = 0;

	public function __construct( $id, $data = array(), $meta = array() ) {
		$this->id   = $id;
		$this->data = $data;
		$this->meta = $meta;
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

class Velotok_Checkout_Duplicate_Checkout {
	public $calls = 0;
	private $next_id = 5000;

	public function create_order( $data ) {
		$this->calls++;

		return new Velotok_Checkout_Duplicate_Order( $this->next_id++, $data );
	}
}

function velotok_checkout_duplicate_data( $overrides = array() ) {
	$data = array(
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
		'velotok_checkout_key'     => 'checkout-duplicate-key-1',
	);

	foreach ( $overrides as $key => $value ) {
		$data[ $key ] = $value;
	}

	return $data;
}

function velotok_checkout_duplicate_context( $checkout, $overrides = array() ) {
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
		'data'              => velotok_checkout_duplicate_data(),
		'checkout'          => $checkout,
	);

	foreach ( $overrides as $key => $value ) {
		$context[ $key ] = $value;
	}

	return $context;
}

$contract = velotok_core_checkout_order_contract();

velotok_checkout_duplicate_assert(
	in_array( 'TESTEB-26', $contract['requirement_ids'], true ),
	'Контракт оформления должен ссылаться на TESTEB-26.'
);
velotok_checkout_duplicate_assert(
	isset( $contract['idempotency']['requirement'] ) && 'TESTEB-26' === $contract['idempotency']['requirement'],
	'Раздел идемпотентности должен ссылаться на TESTEB-26.'
);
velotok_checkout_duplicate_assert(
	isset( $contract['idempotency']['prevents_duplicate'] ) && true === $contract['idempotency']['prevents_duplicate'],
	'Повторная отправка не должна создавать дубль заказа.'
);
velotok_checkout_duplicate_assert(
	isset( $contract['idempotency']['reuse_existing'] ) && true === $contract['idempotency']['reuse_existing'],
	'Повторная отправка должна возвращать уже созданный заказ.'
);
velotok_checkout_duplicate_assert(
	'CHECKOUT_DUPLICATE' === $contract['idempotency']['duplicate_code'],
	'Дубль должен обозначаться кодом CHECKOUT_DUPLICATE.'
);

$first_data = velotok_checkout_duplicate_data();
$second_data = $first_data;
velotok_checkout_duplicate_assert(
	velotok_core_checkout_idempotency_key( $first_data ) === velotok_core_checkout_idempotency_key( $second_data ),
	'Повторная отправка тех же данных должна давать тот же ключ идемпотентности.'
);

$other_cart     = array(
	array(
		'product_id' => 99,
		'quantity'   => 1,
	),
);
$implicit_data  = velotok_checkout_duplicate_data();
unset( $implicit_data['velotok_checkout_key'] );
$first_cart     = array(
	array(
		'product_id' => 10,
		'quantity'   => 2,
	),
);
velotok_checkout_duplicate_assert(
	velotok_core_checkout_idempotency_key( $implicit_data, array( 'cart_items' => $first_cart ) )
	!== velotok_core_checkout_idempotency_key( $implicit_data, array( 'cart_items' => $other_cart ) ),
	'Разные составы корзины не должны считаться дублем.'
);

$context         = velotok_checkout_duplicate_context( new Velotok_Checkout_Duplicate_Checkout() );
$persisted_data  = velotok_checkout_duplicate_data( array( 'velotok_checkout_key' => 'checkout-duplicate-persisted-key' ) );
$persisted_key   = velotok_core_checkout_idempotency_key( $persisted_data, $context );
$persisted_order = new Velotok_Checkout_Duplicate_Order(
	9001,
	array(),
	array( $contract['idempotency']['meta'] => $persisted_key )
);

$GLOBALS['velotok_duplicate_orders'][ $persisted_key ] = $persisted_order;
$GLOBALS['velotok_duplicate_queries']                  = array();

$fresh_checkout = new Velotok_Checkout_Duplicate_Checkout();
$fresh_data     = velotok_checkout_duplicate_data();
$fresh_data['velotok_checkout_key'] = $persisted_key;
$fresh_context  = velotok_checkout_duplicate_context(
	$fresh_checkout,
	array( 'data' => $fresh_data )
);
$fresh_result   = velotok_core_checkout_place_order( $fresh_context );

velotok_checkout_duplicate_assert( false === $fresh_result['created'], 'Новый запрос не должен создавать заказ повторно.' );
velotok_checkout_duplicate_assert( true === $fresh_result['duplicate'], 'Новый запрос должен распознаваться как дубль.' );
velotok_checkout_duplicate_assert( 'CHECKOUT_DUPLICATE' === $fresh_result['code'], 'Дубль должен возвращать CHECKOUT_DUPLICATE.' );
velotok_checkout_duplicate_assert( 9001 === $fresh_result['order_id'], 'Дубль должен возвращать уже созданный заказ.' );
velotok_checkout_duplicate_assert( 0 === $fresh_checkout->calls, 'WC_Checkout::create_order не должен вызываться для дубля.' );

$has_meta_query = false;

foreach ( $GLOBALS['velotok_duplicate_queries'] as $query ) {
	if ( ! empty( $query['meta_query'] ) && is_array( $query['meta_query'] ) ) {
		foreach ( $query['meta_query'] as $clause ) {
			if ( is_array( $clause ) && isset( $clause['key'], $clause['value'] ) && $contract['idempotency']['meta'] === $clause['key'] && $persisted_key === (string) $clause['value'] ) {
				$has_meta_query = true;
				break 2;
			}
		}
	}
}

velotok_checkout_duplicate_assert( $has_meta_query, 'Поиск существующего заказа должен использовать meta_query по ключу идемпотентности.' );

$checkout = new Velotok_Checkout_Duplicate_Checkout();
$context  = velotok_checkout_duplicate_context( $checkout );
$created  = velotok_core_checkout_place_order( $context );

velotok_checkout_duplicate_assert( true === $created['created'], 'Первая отправка должна создавать заказ.' );
velotok_checkout_duplicate_assert( 1 === $checkout->calls, 'Первая отправка должна вызывать WC_Checkout::create_order один раз.' );
velotok_checkout_duplicate_assert(
	$context['data']['velotok_checkout_key'] === $created['order']->get_meta( $contract['idempotency']['meta'] ),
	'Ключ идемпотентности должен сохраняться в метаданных заказа.'
);

$duplicate = velotok_core_checkout_place_order( $context );

velotok_checkout_duplicate_assert( false === $duplicate['created'], 'Повторная отправка формы не должна создавать второй заказ.' );
velotok_checkout_duplicate_assert( true === $duplicate['duplicate'], 'Повторная отправка формы должна распознаваться как дубль.' );
velotok_checkout_duplicate_assert( 'CHECKOUT_DUPLICATE' === $duplicate['code'], 'Повторная отправка должна получать CHECKOUT_DUPLICATE.' );
velotok_checkout_duplicate_assert( $created['order_id'] === $duplicate['order_id'], 'Повторная отправка должна возвращать тот же заказ.' );
velotok_checkout_duplicate_assert( 1 === $checkout->calls, 'Повторная отправка не должна создавать второй заказ через WC_Checkout.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок защиты от дубля заказа не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: повторная отправка формы не создаёт дубль заказа (TESTEB-26).' . PHP_EOL;
exit( 0 );
