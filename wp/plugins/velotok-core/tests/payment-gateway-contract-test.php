<?php
/**
 * Contract test for the WooCommerce ЮKassa payment gateway.
 *
 * Spec: payment (requirement TESTEB-44).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'STDERR' ) ) {
	define( 'STDERR', fopen( 'php://stderr', 'w' ) );
}

$GLOBALS['velotok_test_filters']   = array();
$GLOBALS['velotok_test_actions']   = array();
$GLOBALS['velotok_test_options']   = array();
$GLOBALS['velotok_test_orders']    = array();
$GLOBALS['velotok_test_api_calls'] = 0;

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

if ( ! class_exists( 'Velotok_Test_Order' ) ) {
	class Velotok_Test_Order {
		public $id;
		public $total;
		public $currency;
		public $order_key;
		public $status = 'pending';
		public $meta = array();
		public $notes = array();
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

		public function get_meta( $key, $single = true ) {
			return array_key_exists( $key, $this->meta ) ? $this->meta[ $key ] : '';
		}

		public function update_meta_data( $key, $value ) {
			$this->meta[ $key ] = $value;
		}

		public function update_status( $status, $note = '' ) {
			$this->status = (string) $status;
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

require_once dirname( __DIR__ ) . '/includes/payment-gateway.php';

/**
 * Return a test order for webhook processing.
 *
 * @param mixed  $order   Resolved order.
 * @param string $order_id Order id.
 * @return mixed
 */
function velotok_payment_test_webhook_order( $order, $order_id ) {
	if ( isset( $GLOBALS['velotok_test_orders'][ (int) $order_id ] ) ) {
		return $GLOBALS['velotok_test_orders'][ (int) $order_id ];
	}

	return $order;
}

/**
 * Mock the ЮKassa HTTP response.
 *
 * @param mixed $response Response.
 * @return mixed
 */
function velotok_payment_test_api_response( $response ) {
	$GLOBALS['velotok_test_api_calls']++;

	return array_key_exists( 'velotok_test_api_response', $GLOBALS ) ? $GLOBALS['velotok_test_api_response'] : $response;
}

add_filter( 'velotok_core_payment_webhook_order', 'velotok_payment_test_webhook_order', 10, 2 );
add_filter( 'velotok_core_yookassa_api_response', 'velotok_payment_test_api_response', 10, 1 );

$GLOBALS['velotok_payment_test_passed'] = 0;

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_payment_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}

	$GLOBALS['velotok_payment_test_passed']++;
}

$contract = velotok_core_payment_contract();

velotok_payment_test_assert( 'payment' === $contract['spec'], 'Контракт должен ссылаться на спеку payment.' );
velotok_payment_test_assert( 'TESTEB-44' === $contract['requirement'], 'Контракт должен ссылаться на TESTEB-44.' );
velotok_payment_test_assert( 'WC_Payment_Gateway' === $contract['gateway']['woocommerce'], 'Шлюз должен быть WC_Payment_Gateway.' );
velotok_payment_test_assert( 'v3' === $contract['gateway']['api_version'], 'Платёж должен идти через ЮKassa API v3.' );
velotok_payment_test_assert( true === $contract['webhook']['idempotent'], 'Webhook должен быть идемпотентным.' );
velotok_payment_test_assert( 'refunded' === $contract['refund']['full_status'], 'Полный возврат должен давать статус refunded.' );
velotok_payment_test_assert( 'env_or_option' === $contract['secrets']['source'], 'Секреты должны храниться вне кода.' );

$expected_statuses = array( 'pending', 'waiting_for_capture', 'succeeded', 'canceled', 'refunded' );
velotok_payment_test_assert( $expected_statuses === velotok_core_payment_statuses(), 'Статусы оплаты должны образовывать фиксированную модель.' );
velotok_payment_test_assert( velotok_core_payment_status_is_valid( 'succeeded' ), 'Статус succeeded должен быть валидным.' );
velotok_payment_test_assert( velotok_core_payment_status_is_valid( 'Оплачен' ), 'Русское название статуса должно разрешаться.' );
velotok_payment_test_assert( ! velotok_core_payment_status_is_valid( 'unknown' ), 'Неизвестный статус не должен быть валидным.' );
velotok_payment_test_assert( 'refunded' === velotok_core_payment_normalize_status( 'возврат' ), 'Возврат должен нормализоваться в refunded.' );
velotok_payment_test_assert( velotok_core_payment_can_transition( 'succeeded', 'refunded' ), 'Переход succeeded -> refunded должен быть разрешён.' );

velotok_payment_test_assert( 'processing' === velotok_core_payment_order_status( 'succeeded' ), 'Оплата должна переводить заказ в processing.' );
velotok_payment_test_assert( 'cancelled' === velotok_core_payment_order_status( 'canceled' ), 'Отмена должна переводить заказ в cancelled.' );
velotok_payment_test_assert( 'refunded' === velotok_core_payment_order_status( 'refunded' ), 'Возврат должен переводить заказ в refunded.' );

putenv( 'VELOTOK_YOOKASSA_SHOP_ID=test-shop' );
putenv( 'VELOTOK_YOOKASSA_SECRET_KEY=test-secret' );
putenv( 'VELOTOK_YOOKASSA_WEBHOOK_SECRET=test-webhook-secret' );
$GLOBALS['velotok_test_options'] = array();

velotok_payment_test_assert( 'test-shop' === velotok_core_yookassa_shop_id(), 'Shop id должен читаться из окружения.' );
velotok_payment_test_assert( 'test-secret' === velotok_core_yookassa_secret_key(), 'Secret key должен читаться из окружения.' );
velotok_payment_test_assert( velotok_core_yookassa_is_configured(), 'Шлюз с секретами должен быть настроен.' );

$payload = array(
	'event'  => 'payment.succeeded',
	'object' => array(
		'id'       => 'pay-webhook-' . uniqid(),
		'status'   => 'succeeded',
		'metadata' => array( 'order_id' => '42' ),
	),
);

$webhook_signature = velotok_core_payment_webhook_signature( $payload );
velotok_payment_test_assert( '' !== $webhook_signature, 'Подпись webhook должна вычисляться.' );
velotok_payment_test_assert( velotok_core_payment_webhook_verify( $payload, $webhook_signature ), 'Верная подпись должна приниматься.' );
velotok_payment_test_assert( ! velotok_core_payment_webhook_verify( $payload, 'bad-signature' ), 'Неверная подпись должна отклоняться.' );

$order_42 = new Velotok_Test_Order( 42, 1000 );
$GLOBALS['velotok_test_orders'][42] = $order_42;

$webhook = velotok_core_payment_webhook_process( $payload, $webhook_signature );
velotok_payment_test_assert( true === $webhook['ok'], 'Валидный webhook должен обрабатываться.' );
velotok_payment_test_assert( true === $webhook['verified'], 'Webhook должен подтверждать подпись.' );
velotok_payment_test_assert( true === $webhook['idempotent'], 'Webhook должен быть идемпотентным.' );
velotok_payment_test_assert( 'succeeded' === $webhook['status'], 'Webhook должен применять статус succeeded.' );
velotok_payment_test_assert( 'processing' === $order_42->status, 'Статус заказа должен отражать оплату.' );
velotok_payment_test_assert( 'succeeded' === $order_42->meta['_velotok_payment_status'], 'Статус оплаты должен сохраняться в заказе.' );

$duplicate = velotok_core_payment_webhook_process( $payload, $webhook_signature );
velotok_payment_test_assert( true === $duplicate['duplicate'], 'Повторный webhook должен распознаваться как дубликат.' );
velotok_payment_test_assert( 'succeeded' === $duplicate['status'], 'Дубликат должен возвращать уже применённый статус.' );

$invalid = velotok_core_payment_webhook_process( $payload, 'bad-signature' );
velotok_payment_test_assert( false === $invalid['ok'] && false === $invalid['verified'], 'Webhook с плохой подписью должен отклоняться.' );

$order_43 = new Velotok_Test_Order( 43, 1500 );
$GLOBALS['velotok_test_orders'][43] = $order_43;
$GLOBALS['velotok_test_api_response'] = array(
	'id'           => 'pay-43',
	'status'       => 'pending',
	'confirmation' => array( 'url' => 'https://yookassa.test/confirm/43' ),
);

$payment = velotok_core_yookassa_process_payment( $order_43 );
velotok_payment_test_assert( 'success' === $payment['result'], 'Оплата должна запускаться через шлюз.' );
velotok_payment_test_assert( 'https://yookassa.test/confirm/43' === $payment['redirect'], 'Покупатель должен перенаправляться в ЮKassa.' );
velotok_payment_test_assert( 'pay-43' === $order_43->meta['_velotok_payment_id'], 'Идентификатор платежа должен сохраняться.' );
velotok_payment_test_assert( 'pending' === $order_43->meta['_velotok_payment_status'], 'Новый платёж должен быть pending.' );

$calls_before_retry = $GLOBALS['velotok_test_api_calls'];
$retry = velotok_core_yookassa_process_payment( $order_43 );
velotok_payment_test_assert( true === $retry['idempotent'], 'Повторный запуск платежа должен быть идемпотентным.' );
velotok_payment_test_assert( $calls_before_retry === $GLOBALS['velotok_test_api_calls'], 'Повторный платёж не должен создавать новый платёж в API.' );

$order_43->meta['_velotok_payment_status'] = 'succeeded';
$GLOBALS['velotok_test_api_response'] = array( 'id' => 'refund-43', 'status' => 'succeeded' );

$refund = velotok_core_yookassa_refund( $order_43, null, 'Полный возврат' );
velotok_payment_test_assert( true === $refund['ok'], 'Полный возврат должен выполняться.' );
velotok_payment_test_assert( 'refunded' === $refund['status'], 'Полный возврат должен давать статус refunded.' );
velotok_payment_test_assert( true === $refund['full'], 'Полный возврат должен помечаться как full.' );
velotok_payment_test_assert( 'refunded' === $order_43->meta['_velotok_payment_status'], 'Статус оплаты после возврата должен быть refunded.' );
velotok_payment_test_assert( 'refunded' === $order_43->status, 'Статус заказа после полного возврата должен быть refunded.' );

$order_44 = new Velotok_Test_Order( 44, 2000 );
$order_44->meta['_velotok_payment_id'] = 'pay-44';
$order_44->meta['_velotok_payment_status'] = 'succeeded';
$GLOBALS['velotok_test_orders'][44] = $order_44;
$GLOBALS['velotok_test_api_response'] = array( 'id' => 'refund-44', 'status' => 'succeeded' );

$partial = velotok_core_yookassa_refund( $order_44, 500, 'Частичный возврат' );
velotok_payment_test_assert( true === $partial['ok'], 'Частичный возврат должен выполняться.' );
velotok_payment_test_assert( 'succeeded' === $partial['status'], 'Частичный возврат не должен переводить оплату в refunded.' );
velotok_payment_test_assert( 'succeeded' === $order_44->meta['_velotok_payment_status'], 'Частичный возврат сохраняет succeeded.' );

velotok_payment_test_assert( class_exists( 'Velotok_Yookassa_Gateway' ), 'Класс платёжного шлюза должен существовать.' );
velotok_payment_test_assert( is_subclass_of( 'Velotok_Yookassa_Gateway', 'WC_Payment_Gateway' ), 'Шлюз должен расширять WC_Payment_Gateway.' );

velotok_payment_test_assert( ! empty( $GLOBALS['velotok_test_filters']['woocommerce_payment_gateways'] ), 'Шлюз должен регистрироваться в WooCommerce.' );

$gateway = new Velotok_Yookassa_Gateway();
velotok_payment_test_assert( 'velotok_yookassa' === $gateway->id, 'Идентификатор шлюза должен быть стабильным.' );
velotok_payment_test_assert( in_array( 'refunds', $gateway->supports, true ), 'Шлюз должен поддерживать возвраты.' );
velotok_payment_test_assert( isset( $gateway->form_fields['shop_id'], $gateway->form_fields['secret_key'], $gateway->form_fields['webhook_secret'] ), 'Настройки шлюза должны содержать поля секретов.' );

echo 'OK: контракт платёжного шлюза WooCommerce и ЮKassa проверен (' . (int) $GLOBALS['velotok_payment_test_passed'] . ' проверок).' . PHP_EOL;
