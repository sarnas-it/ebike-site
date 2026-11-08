<?php
/**
 * Focused contract test for TESTEB-46.
 *
 * Spec: payment. Requirement: WooCommerce order statuses must reflect the
 * payment status, including the full refund transition to `refunded`.
 *
 * The test can be pointed at another implementation through
 * VELOTOK_PAYMENT_GATEWAY_FILE; the red-green proof uses this to run against
 * deliberately broken copies of includes/payment-gateway.php.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'STDERR' ) ) {
	define( 'STDERR', fopen( 'php://stderr', 'w' ) );
}

$GLOBALS['velotok_test_filters'] = array();
$GLOBALS['velotok_test_actions'] = array();
$GLOBALS['velotok_test_options'] = array();
$GLOBALS['velotok_test_orders']  = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['velotok_test_filters'][ $tag ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		if ( ! empty( $GLOBALS['velotok_test_filters'][ $tag ] ) ) {
			foreach ( $GLOBALS['velotok_test_filters'][ $tag ] as $callback ) {
				$value = call_user_func( $callback, $value, ...$args );
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['velotok_test_actions'][ $tag ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $tag, ...$args ) {
		if ( ! empty( $GLOBALS['velotok_test_actions'][ $tag ] ) ) {
			foreach ( $GLOBALS['velotok_test_actions'][ $tag ] as $callback ) {
				call_user_func( $callback, ...$args );
			}
		}
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['velotok_test_options'] ) ? $GLOBALS['velotok_test_options'][ $name ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['velotok_test_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wc_get_order' ) ) {
	function wc_get_order( $order_id ) {
		return isset( $GLOBALS['velotok_test_orders'][ (int) $order_id ] ) ? $GLOBALS['velotok_test_orders'][ (int) $order_id ] : false;
	}
}

if ( ! function_exists( 'wc_get_checkout_url' ) ) {
	function wc_get_checkout_url() {
		return 'https://shop.test/checkout/';
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '/' ) {
		return 'https://shop.test' . $path;
	}
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	class WC_Payment_Gateway {
		public $id = '';
		public $method_title = '';
		public $method_description = '';
		public $has_fields = false;
		public $supports = array();
		public $title = '';
		public $description = '';
		public $enabled = 'yes';
		public $settings = array();

		public function __construct() {}

		public function init_settings() {
			$this->settings = array();
		}

		public function get_option( $key, $default = '' ) {
			return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
		}

		public function process_admin_options() {
			return true;
		}
	}
}

if ( ! class_exists( 'Velotok_Order_Status_Test_Order' ) ) {
	class Velotok_Order_Status_Test_Order {
		public $id;
		public $total;
		public $currency;
		public $order_key;
		public $status = 'pending';
		public $meta = array();
		public $notes = array();
		public $status_updates = 0;
		public $needs_processing = true;

		public function __construct( $id, $total = 1000.0, $currency = 'RUB' ) {
			$this->id        = (int) $id;
			$this->total     = (float) $total;
			$this->currency  = (string) $currency;
			$this->order_key = 'wc_order_' . $id;
		}

		public function get_id() {
			return $this->id;
		}

		public function get_total() {
			return $this->total;
		}

		public function get_currency() {
			return $this->currency;
		}

		public function get_order_key() {
			return $this->order_key;
		}

		public function get_status() {
			return $this->status;
		}

		public function get_meta( $key, $single = true ) {
			return array_key_exists( $key, $this->meta ) ? $this->meta[ $key ] : '';
		}

		public function update_meta_data( $key, $value ) {
			$this->meta[ $key ] = $value;
		}

		public function update_status( $status, $note = '' ) {
			$this->status = (string) $status;
			$this->status_updates++;
			if ( '' !== (string) $note ) {
				$this->notes[] = (string) $note;
			}
			return true;
		}

		public function add_order_note( $note ) {
			$this->notes[] = (string) $note;
		}

		public function needs_processing() {
			return (bool) $this->needs_processing;
		}

		public function save() {
			return true;
		}
	}
}

$implementation = getenv( 'VELOTOK_PAYMENT_GATEWAY_FILE' );

if ( false === $implementation || '' === $implementation ) {
	$implementation = dirname( __DIR__ ) . '/includes/payment-gateway.php';
}

if ( ! is_readable( $implementation ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл реализации платёжного шлюза: ' . $implementation . PHP_EOL );
	exit( 1 );
}

require_once $implementation;

/**
 * Mock the ЮKassa HTTP response.
 *
 * @param mixed $response Response.
 * @return mixed
 */
function velotok_payment_order_status_test_api_response( $response ) {
	return array_key_exists( 'velotok_test_api_response', $GLOBALS ) ? $GLOBALS['velotok_test_api_response'] : $response;
}

add_filter( 'velotok_core_yookassa_api_response', 'velotok_payment_order_status_test_api_response', 10, 1 );

$GLOBALS['velotok_payment_order_status_test_passed'] = 0;

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_payment_order_status_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}

	$GLOBALS['velotok_payment_order_status_test_passed']++;
}

putenv( 'VELOTOK_YOOKASSA_SHOP_ID=test-shop-46' );
putenv( 'VELOTOK_YOOKASSA_SECRET_KEY=test-secret-46' );
putenv( 'VELOTOK_YOOKASSA_WEBHOOK_SECRET=test-webhook-secret-46' );
$GLOBALS['velotok_test_options'] = array();

$expected_order_statuses = array(
	'pending'             => 'pending',
	'waiting_for_capture' => 'on-hold',
	'succeeded'           => 'processing',
	'canceled'            => 'cancelled',
	'refunded'            => 'refunded',
);

foreach ( $expected_order_statuses as $payment_status => $order_status ) {
	velotok_payment_order_status_test_assert(
		$order_status === velotok_core_payment_order_status( $payment_status ),
		'Статус оплаты ' . $payment_status . ' должен отражаться статусом заказа ' . $order_status . '.'
	);
	velotok_payment_order_status_test_assert(
		$order_status === velotok_payment_order_status( $payment_status ),
		'Совместимый алиас должен возвращать статус заказа для ' . $payment_status . '.'
	);
}

$contract = velotok_core_payment_contract();
velotok_payment_order_status_test_assert(
	$expected_order_statuses === $contract['order_statuses'],
	'Контракт должен фиксировать соответствие статусов оплаты и заказов.'
);

$order_number = 700;

foreach ( $expected_order_statuses as $payment_status => $order_status ) {
	$order = new Velotok_Order_Status_Test_Order( ++$order_number, 1000 );
	$GLOBALS['velotok_test_orders'][ $order->id ] = $order;

	velotok_payment_order_status_test_assert(
		true === velotok_core_payment_apply_order_status( $order, $payment_status, 'Тестовый статус' ),
		'Статус ' . $payment_status . ' должен применяться к заказу.'
	);
	velotok_payment_order_status_test_assert(
		$order_status === $order->status,
		'Заказ должен перейти в статус ' . $order_status . ' при оплате ' . $payment_status . '.'
	);
	velotok_payment_order_status_test_assert(
		$payment_status === $order->get_meta( '_velotok_payment_status' ),
		'Статус оплаты ' . $payment_status . ' должен сохраняться в заказе.'
	);
	velotok_payment_order_status_test_assert(
		$payment_status === $order->get_meta( '_yookassa_payment_status' ),
		'Статус ЮKassa ' . $payment_status . ' должен сохраняться в заказе.'
	);
	velotok_payment_order_status_test_assert(
		$payment_status === $order->get_meta( '_payment_status' ),
		'Совместимый статус оплаты ' . $payment_status . ' должен сохраняться в заказе.'
	);
	velotok_payment_order_status_test_assert(
		$order_status === $order->get_meta( '_velotok_payment_order_status' ),
		'Заказ должен хранить применённый статус ' . $order_status . '.'
	);
}

$virtual_order = new Velotok_Order_Status_Test_Order( ++$order_number, 1000 );
$virtual_order->needs_processing = false;
$GLOBALS['velotok_test_orders'][ $virtual_order->id ] = $virtual_order;

velotok_payment_order_status_test_assert(
	true === velotok_core_payment_apply_order_status( $virtual_order, 'succeeded' ),
	'Оплата заказа без обработки должна применяться.'
);
velotok_payment_order_status_test_assert(
	'completed' === $virtual_order->status,
	'Оплаченный заказ без необходимости обработки должен переходить в completed.'
);
velotok_payment_order_status_test_assert(
	'completed' === $virtual_order->get_meta( '_velotok_payment_order_status' ),
	'Заказ без обработки должен хранить статус completed.'
);

$invalid_order = new Velotok_Order_Status_Test_Order( ++$order_number, 1000 );
$GLOBALS['velotok_test_orders'][ $invalid_order->id ] = $invalid_order;

velotok_payment_order_status_test_assert(
	false === velotok_core_payment_apply_order_status( $invalid_order, 'unknown' ),
	'Неизвестный статус оплаты не должен применяться к заказу.'
);
velotok_payment_order_status_test_assert(
	'pending' === $invalid_order->status,
	'Неизвестный статус оплаты не должен менять статус заказа.'
);
velotok_payment_order_status_test_assert(
	'' === $invalid_order->get_meta( '_velotok_payment_status' ),
	'Неизвестный статус оплаты не должен сохраняться в заказе.'
);

/**
 * Process a signed webhook for an order.
 *
 * @param string $event_name Event name.
 * @param string $object_status Payment object status.
 * @param int    $order_id Order id.
 * @param string $event_id Stable event id.
 * @return array<string,mixed>
 */
function velotok_payment_order_status_webhook( $event_name, $object_status, $order_id, $event_id ) {
	$payload = array(
		'event'  => array(
			'id'    => $event_id,
			'event' => $event_name,
		),
		'object' => array(
			'id'       => 'pay-' . $event_id,
			'status'   => $object_status,
			'metadata' => array( 'order_id' => (string) $order_id ),
		),
	);
	$raw = json_encode( $payload );

	return velotok_core_payment_webhook_process( $raw, velotok_core_payment_webhook_signature( $raw ) );
}

$webhook_cases = array(
	array( 'payment.succeeded', 'succeeded', 'processing', 'succeeded' ),
	array( 'payment.canceled', 'canceled', 'cancelled', 'canceled' ),
	array( 'payment.waiting_for_capture', 'waiting_for_capture', 'on-hold', 'waiting_for_capture' ),
	array( 'refund.succeeded', 'succeeded', 'refunded', 'refunded' ),
);

foreach ( $webhook_cases as $case ) {
	list( $event_name, $object_status, $order_status, $payment_status ) = $case;

	$order = new Velotok_Order_Status_Test_Order( ++$order_number, 1000 );
	$GLOBALS['velotok_test_orders'][ $order->id ] = $order;

	$result = velotok_payment_order_status_webhook( $event_name, $object_status, $order->id, 'evt-status-' . $order->id );

	velotok_payment_order_status_test_assert(
		true === $result['ok'] && true === $result['verified'],
		'Webhook ' . $event_name . ' должен приниматься после проверки подписи.'
	);
	velotok_payment_order_status_test_assert(
		$order_status === $result['order_status'],
		'Webhook ' . $event_name . ' должен возвращать статус заказа ' . $order_status . '.'
	);
	velotok_payment_order_status_test_assert(
		$order_status === $order->status,
		'Webhook ' . $event_name . ' должен переводить заказ в ' . $order_status . '.'
	);
	velotok_payment_order_status_test_assert(
		$payment_status === $order->get_meta( '_velotok_payment_status' ),
		'Webhook ' . $event_name . ' должен сохранять оплату ' . $payment_status . '.'
	);
}

$duplicate_order = new Velotok_Order_Status_Test_Order( ++$order_number, 1000 );
$GLOBALS['velotok_test_orders'][ $duplicate_order->id ] = $duplicate_order;

$first = velotok_payment_order_status_webhook( 'payment.succeeded', 'succeeded', $duplicate_order->id, 'evt-duplicate-' . $duplicate_order->id );
$duplicate = velotok_payment_order_status_webhook( 'payment.succeeded', 'succeeded', $duplicate_order->id, 'evt-duplicate-' . $duplicate_order->id );

velotok_payment_order_status_test_assert( true === $first['ok'], 'Первичный webhook должен применяться.' );
velotok_payment_order_status_test_assert( true === $duplicate['duplicate'], 'Повторный webhook должен быть дубликатом.' );
velotok_payment_order_status_test_assert(
	1 === $duplicate_order->status_updates,
	'Дубликат webhook не должен повторно менять статус заказа.'
);
velotok_payment_order_status_test_assert(
	'processing' === $duplicate_order->status,
	'Статус заказа должен оставаться processing после дубликата.'
);

$rejected_order = new Velotok_Order_Status_Test_Order( ++$order_number, 1000 );
$GLOBALS['velotok_test_orders'][ $rejected_order->id ] = $rejected_order;
$rejected_payload = array(
	'event'  => array(
		'id'    => 'evt-rejected-' . $rejected_order->id,
		'event' => 'payment.succeeded',
	),
	'object' => array(
		'id'       => 'pay-rejected',
		'status'   => 'succeeded',
		'metadata' => array( 'order_id' => (string) $rejected_order->id ),
	),
);
$rejected_raw = json_encode( $rejected_payload );
$rejected     = velotok_core_payment_webhook_process( $rejected_raw, 'bad-signature' );

velotok_payment_order_status_test_assert( false === $rejected['ok'], 'Webhook с плохой подписью должен отклоняться.' );
velotok_payment_order_status_test_assert(
	'pending' === $rejected_order->status && 0 === $rejected_order->status_updates,
	'Отклонённый webhook не должен менять статус заказа.'
);

$pending_order = new Velotok_Order_Status_Test_Order( ++$order_number, 1000 );
$GLOBALS['velotok_test_orders'][ $pending_order->id ] = $pending_order;
$GLOBALS['velotok_test_api_response'] = array(
	'id'           => 'pay-pending-' . $pending_order->id,
	'status'       => 'pending',
	'confirmation' => array( 'url' => 'https://yookassa.test/confirm/pending' ),
);

$pending_payment = velotok_core_yookassa_process_payment( $pending_order );
velotok_payment_order_status_test_assert( 'success' === $pending_payment['result'], 'Платёж должен создаваться через шлюз.' );
velotok_payment_order_status_test_assert(
	'pending' === $pending_order->status,
	'Новый платёж должен оставлять заказ в статусе pending.'
);

$paid_order = new Velotok_Order_Status_Test_Order( ++$order_number, 1000 );
$GLOBALS['velotok_test_orders'][ $paid_order->id ] = $paid_order;
$GLOBALS['velotok_test_api_response'] = array(
	'id'     => 'pay-success-' . $paid_order->id,
	'status' => 'succeeded',
);

$paid_payment = velotok_core_yookassa_process_payment( $paid_order );
velotok_payment_order_status_test_assert( 'success' === $paid_payment['result'], 'Подтверждённый платёж должен завершаться успешно.' );
velotok_payment_order_status_test_assert(
	'processing' === $paid_order->status,
	'Оплаченный заказ должен переходить в processing.'
);

$refund_order = new Velotok_Order_Status_Test_Order( ++$order_number, 1000 );
$refund_order->status = 'processing';
$refund_order->meta['_velotok_payment_id']      = 'pay-refund-' . $refund_order->id;
$refund_order->meta['_velotok_payment_status']  = 'succeeded';
$GLOBALS['velotok_test_orders'][ $refund_order->id ] = $refund_order;
$GLOBALS['velotok_test_api_response'] = array(
	'id'     => 'refund-' . $refund_order->id,
	'status' => 'succeeded',
);

$full_refund = velotok_core_yookassa_refund( $refund_order, null, 'Полный возврат' );

velotok_payment_order_status_test_assert( true === $full_refund['ok'], 'Полный возврат должен выполняться.' );
velotok_payment_order_status_test_assert( true === $full_refund['full'], 'Возврат всей суммы должен быть полным.' );
velotok_payment_order_status_test_assert(
	'refunded' === $refund_order->get_meta( '_velotok_payment_status' ),
	'Полный возврат должен переводить оплату в refunded.'
);
velotok_payment_order_status_test_assert(
	'refunded' === $refund_order->status,
	'Полный возврат должен переводить заказ в refunded.'
);

$partial_order = new Velotok_Order_Status_Test_Order( ++$order_number, 1000 );
$partial_order->status = 'processing';
$partial_order->meta['_velotok_payment_id']      = 'pay-partial-' . $partial_order->id;
$partial_order->meta['_velotok_payment_status']  = 'succeeded';
$GLOBALS['velotok_test_orders'][ $partial_order->id ] = $partial_order;

$GLOBALS['velotok_test_api_response'] = array( 'id' => 'refund-partial-a', 'status' => 'succeeded' );
$first_partial = velotok_core_yookassa_refund( $partial_order, 400, 'Первая часть' );

velotok_payment_order_status_test_assert( true === $first_partial['ok'], 'Частичный возврат должен выполняться.' );
velotok_payment_order_status_test_assert( 'succeeded' === $first_partial['status'], 'Частичный возврат сохраняет оплату succeeded.' );
velotok_payment_order_status_test_assert(
	'processing' === $partial_order->status,
	'Частичный возврат не должен менять статус заказа.'
);

$GLOBALS['velotok_test_api_response'] = array( 'id' => 'refund-partial-b', 'status' => 'succeeded' );
$second_partial = velotok_core_yookassa_refund( $partial_order, 600, 'Вторая часть' );

velotok_payment_order_status_test_assert( true === $second_partial['ok'], 'Вторая часть возврата должна выполняться.' );
velotok_payment_order_status_test_assert( 'refunded' === $second_partial['status'], 'Возврат всей суммы частями завершает возврат.' );
velotok_payment_order_status_test_assert(
	'refunded' === $partial_order->status,
	'Возврат всей суммы частями должен переводить заказ в refunded.'
);

echo 'PASS: статусы заказа отражают оплату (' . (int) $GLOBALS['velotok_payment_order_status_test_passed'] . ' проверок, TESTEB-46).' . PHP_EOL;
