<?php
/**
 * Focused contract test for TESTEB-49.
 *
 * Spec: payment. Requirement: a full refund must move the payment (and the
 * matching WooCommerce order) to `refunded`.
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

if ( ! class_exists( 'Velotok_Refund_Test_Order' ) ) {
	class Velotok_Refund_Test_Order {
		public $id;
		public $total;
		public $currency;
		public $order_key;
		public $status = 'processing';
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
 * Mock the ЮKassa HTTP response and remember the last request.
 *
 * @param mixed $response Response.
 * @param mixed $method   HTTP method.
 * @param mixed $path     API path.
 * @param mixed $body     Request body.
 * @param mixed $key      Idempotency key.
 * @return mixed
 */
function velotok_payment_refund_test_api_response( $response, $method = null, $path = null, $body = null, $key = null ) {
	$GLOBALS['velotok_test_last_request'] = array(
		'method'          => $method,
		'path'            => $path,
		'body'            => $body,
		'idempotency_key' => $key,
	);

	return array_key_exists( 'velotok_test_api_response', $GLOBALS ) ? $GLOBALS['velotok_test_api_response'] : $response;
}

add_filter( 'velotok_core_yookassa_api_response', 'velotok_payment_refund_test_api_response', 10, 5 );

$GLOBALS['velotok_payment_refund_test_passed'] = 0;

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_payment_refund_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}

	$GLOBALS['velotok_payment_refund_test_passed']++;
}

/**
 * Create a paid order ready for a refund.
 *
 * @param int   $order_id Order id.
 * @param float $total    Total.
 * @return Velotok_Refund_Test_Order
 */
function velotok_payment_refund_test_order( $order_id, $total = 1000.0 ) {
	$order = new Velotok_Refund_Test_Order( $order_id, $total );
	$order->status = 'processing';
	$order->meta['_velotok_payment_id']     = 'pay-refund-' . $order_id;
	$order->meta['_velotok_payment_status'] = 'succeeded';
	$GLOBALS['velotok_test_orders'][ (int) $order_id ] = $order;

	return $order;
}

putenv( 'VELOTOK_YOOKASSA_SHOP_ID=test-shop-49' );
putenv( 'VELOTOK_YOOKASSA_SECRET_KEY=test-secret-49' );
putenv( 'VELOTOK_YOOKASSA_WEBHOOK_SECRET=test-webhook-secret-49' );
$GLOBALS['velotok_test_options'] = array();

velotok_payment_refund_test_assert(
	'refunded' === velotok_core_payment_refund_status( 1000, 1000 ),
	'Возврат всей суммы должен давать статус оплаты refunded.'
);
velotok_payment_refund_test_assert(
	'refunded' === velotok_core_yookassa_refund_status( 1000.0, 1000.0 ),
	'Совместимый алиас должен возвращать refunded для полного возврата.'
);
velotok_payment_refund_test_assert(
	'succeeded' === velotok_core_payment_refund_status( 999.99, 1000 ),
	'Частичный возврат не должен переводить оплату в refunded.'
);

$contract = velotok_core_payment_contract();

velotok_payment_refund_test_assert(
	'refunded' === $contract['refund']['full_status'],
	'Контракт должен объявлять refunded как статус полного возврата.'
);
velotok_payment_refund_test_assert(
	'refunded' === $contract['refund']['order_status'],
	'Контракт должен объявлять refunded как статус заказа после полного возврата.'
);
velotok_payment_refund_test_assert(
	'refunds' === $contract['refund']['api_path'],
	'Возврат должен выполняться через API ЮKassa refunds.'
);

$full_order = velotok_payment_refund_test_order( 4901, 1000 );
$GLOBALS['velotok_test_api_response'] = array(
	'id'     => 'refund-4901',
	'status' => 'succeeded',
);

$full = velotok_core_yookassa_refund( $full_order, null, 'Полный возврат' );

velotok_payment_refund_test_assert( true === $full['ok'], 'Полный возврат должен выполняться.' );
velotok_payment_refund_test_assert( true === $full['full'], 'Возврат всей суммы должен считаться полным.' );
velotok_payment_refund_test_assert( 'refunded' === $full['status'], 'Полный возврат должен возвращать статус refunded.' );
velotok_payment_refund_test_assert( 1000.0 === (float) $full['amount'], 'Полный возврат без суммы должен вернуть всю сумму заказа.' );
velotok_payment_refund_test_assert(
	'refunded' === $full_order->get_meta( '_velotok_payment_status' ),
	'Полный возврат должен переводить оплату в refunded.'
);
velotok_payment_refund_test_assert(
	'refunded' === $full_order->get_meta( '_yookassa_payment_status' ),
	'Полный возврат должен обновлять статус ЮKassa на refunded.'
);
velotok_payment_refund_test_assert(
	'refunded' === $full_order->get_meta( '_payment_status' ),
	'Полный возврат должен обновлять совместимый статус оплаты.'
);
velotok_payment_refund_test_assert(
	'refunded' === $full_order->get_meta( '_velotok_payment_order_status' ),
	'Полный возврат должен сохранять статус заказа refunded.'
);
velotok_payment_refund_test_assert(
	'refunded' === $full_order->status,
	'Полный возврат должен переводить заказ в refunded.'
);
velotok_payment_refund_test_assert(
	1000.0 === (float) $full_order->get_meta( '_velotok_refunded_amount' ),
	'Полный возврат должен сохранять возвращённую сумму.'
);

$request = isset( $GLOBALS['velotok_test_last_request'] ) ? $GLOBALS['velotok_test_last_request'] : array();

velotok_payment_refund_test_assert( 'POST' === $request['method'], 'Возврат должен отправляться POST-запросом.' );
velotok_payment_refund_test_assert( 'refunds' === $request['path'], 'Возврат должен идти в ЮKassa refunds.' );
velotok_payment_refund_test_assert(
	isset( $request['body']['payment_id'] ) && 'pay-refund-4901' === $request['body']['payment_id'],
	'Запрос возврата должен ссылаться на платёж ЮKassa.'
);
velotok_payment_refund_test_assert(
	isset( $request['body']['amount']['value'] ) && '1000.00' === $request['body']['amount']['value'],
	'Запрос возврата должен содержать сумму возврата.'
);
velotok_payment_refund_test_assert(
	isset( $request['body']['amount']['currency'] ) && 'RUB' === $request['body']['amount']['currency'],
	'Запрос возврата должен содержать валюту заказа.'
);
velotok_payment_refund_test_assert(
	'' !== (string) $request['idempotency_key'],
	'Запрос возврата должен передавать Idempotence-Key.'
);

$explicit_order = velotok_payment_refund_test_order( 4902, 500 );
$GLOBALS['velotok_test_api_response'] = array( 'id' => 'refund-4902', 'status' => 'succeeded' );

$explicit = velotok_core_yookassa_refund( $explicit_order, 500, 'Возврат всей суммы' );

velotok_payment_refund_test_assert( true === $explicit['ok'], 'Возврат с явной полной суммой должен выполняться.' );
velotok_payment_refund_test_assert( 'refunded' === $explicit['status'], 'Явная полная сумма должна давать refunded.' );
velotok_payment_refund_test_assert(
	'refunded' === $explicit_order->get_meta( '_velotok_payment_status' ) && 'refunded' === $explicit_order->status,
	'Явный полный возврат должен переводить оплату и заказ в refunded.'
);

$gateway_order = velotok_payment_refund_test_order( 4903, 800 );
$GLOBALS['velotok_test_api_response'] = array( 'id' => 'refund-4903', 'status' => 'succeeded' );
$gateway = new Velotok_Yookassa_Gateway();

velotok_payment_refund_test_assert(
	true === $gateway->process_refund( $gateway_order, null, 'Возврат через шлюз' ),
	'process_refund должен сообщать об успешном полном возврате.'
);
velotok_payment_refund_test_assert(
	'refunded' === $gateway_order->get_meta( '_velotok_payment_status' ),
	'process_refund должен переводить оплату в refunded.'
);
velotok_payment_refund_test_assert(
	'refunded' === $gateway_order->status,
	'process_refund должен переводить заказ в refunded.'
);

$partial_order = velotok_payment_refund_test_order( 4904, 1000 );
$GLOBALS['velotok_test_api_response'] = array( 'id' => 'refund-4904-a', 'status' => 'succeeded' );

$first_partial = velotok_core_yookassa_refund( $partial_order, 400, 'Первая часть' );

velotok_payment_refund_test_assert( true === $first_partial['ok'], 'Частичный возврат должен выполняться.' );
velotok_payment_refund_test_assert( 'succeeded' === $first_partial['status'], 'Частичный возврат сохраняет оплату succeeded.' );
velotok_payment_refund_test_assert(
	'succeeded' === $partial_order->get_meta( '_velotok_payment_status' ) && 'processing' === $partial_order->status,
	'Частичный возврат не должен переводить оплату и заказ в refunded.'
);

$GLOBALS['velotok_test_api_response'] = array( 'id' => 'refund-4904-b', 'status' => 'succeeded' );
$second_partial = velotok_core_yookassa_refund( $partial_order, 600, 'Вторая часть' );

velotok_payment_refund_test_assert( true === $second_partial['ok'], 'Вторая часть возврата должна выполняться.' );
velotok_payment_refund_test_assert( 'refunded' === $second_partial['status'], 'Возврат всей суммы частями даёт refunded.' );
velotok_payment_refund_test_assert(
	'refunded' === $partial_order->get_meta( '_velotok_payment_status' ),
	'Возврат всей суммы частями должен переводить оплату в refunded.'
);
velotok_payment_refund_test_assert(
	'refunded' === $partial_order->status,
	'Возврат всей суммы частями должен переводить заказ в refunded.'
);
velotok_payment_refund_test_assert(
	1000.0 === (float) $partial_order->get_meta( '_velotok_refunded_amount' ),
	'Возвращённая сумма должна накапливаться.'
);

$invalid_order = velotok_payment_refund_test_order( 4905, 1000 );
$invalid = velotok_core_yookassa_refund( $invalid_order, 1000.01, 'Слишком много' );

velotok_payment_refund_test_assert( false === $invalid['ok'], 'Возврат больше суммы заказа должен отклоняться.' );
velotok_payment_refund_test_assert(
	'PAYMENT_REFUND_AMOUNT_INVALID' === $invalid['code'],
	'Возврат больше суммы заказа должен возвращать PAYMENT_REFUND_AMOUNT_INVALID.'
);
velotok_payment_refund_test_assert(
	'succeeded' === $invalid_order->get_meta( '_velotok_payment_status' ) && 'processing' === $invalid_order->status,
	'Отклонённый возврат не должен менять оплату и заказ.'
);

$missing_order = new Velotok_Refund_Test_Order( 4906, 1000 );
$missing_order->status = 'processing';
$missing_order->meta['_velotok_payment_status'] = 'succeeded';
$GLOBALS['velotok_test_orders'][4906] = $missing_order;
$missing = velotok_core_yookassa_refund( $missing_order, null, 'Нет платежа' );

velotok_payment_refund_test_assert( false === $missing['ok'], 'Возврат без платежа должен отклоняться.' );
velotok_payment_refund_test_assert(
	'PAYMENT_REFUND_PAYMENT_MISSING' === $missing['code'],
	'Возврат без платежа должен возвращать PAYMENT_REFUND_PAYMENT_MISSING.'
);
velotok_payment_refund_test_assert(
	'succeeded' === $missing_order->get_meta( '_velotok_payment_status' ),
	'Возврат без платежа не должен менять статус оплаты.'
);

$error_order = velotok_payment_refund_test_order( 4907, 1000 );
$GLOBALS['velotok_test_api_response'] = array(
	'error' => array(
		'code'    => 'YOO_HTTP_400',
		'message' => 'Возврат недоступен.',
	),
);
$failed = velotok_core_yookassa_refund( $error_order, null, 'Ошибка API' );

velotok_payment_refund_test_assert( false === $failed['ok'], 'Ошибка API возврата должна отклоняться.' );
velotok_payment_refund_test_assert(
	'succeeded' === $error_order->get_meta( '_velotok_payment_status' ) && 'processing' === $error_order->status,
	'Ошибка API возврата не должна переводить оплату в refunded.'
);

$alias_order = velotok_payment_refund_test_order( 4908, 300 );
$GLOBALS['velotok_test_api_response'] = array( 'id' => 'refund-4908', 'status' => 'succeeded' );
$alias = velotok_payment_refund( $alias_order, null, 'Алиас' );

velotok_payment_refund_test_assert( true === $alias['ok'], 'Совместимый алиас возврата должен выполняться.' );
velotok_payment_refund_test_assert(
	'refunded' === $alias_order->get_meta( '_velotok_payment_status' ),
	'Совместимый алиас возврата должен переводить оплату в refunded.'
);

echo 'PASS: полный возврат переводит оплату в refunded (' . (int) $GLOBALS['velotok_payment_refund_test_passed'] . ' проверок, TESTEB-49).' . PHP_EOL;
