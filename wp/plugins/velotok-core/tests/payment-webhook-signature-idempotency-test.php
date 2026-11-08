<?php
/**
 * Focused contract test for TESTEB-45.
 *
 * Spec: payment. Requirement: webhook must verify its signature and be
 * idempotent.
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

$GLOBALS['velotok_test_filters']   = array();
$GLOBALS['velotok_test_actions']   = array();
$GLOBALS['velotok_test_options']   = array();
$GLOBALS['velotok_test_orders']    = array();

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

if ( ! class_exists( 'Velotok_Webhook_Test_Order' ) ) {
	class Velotok_Webhook_Test_Order {
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

$GLOBALS['velotok_payment_webhook_test_passed'] = 0;

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_payment_webhook_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}

	$GLOBALS['velotok_payment_webhook_test_passed']++;
}

putenv( 'VELOTOK_YOOKASSA_SHOP_ID=test-shop-45' );
putenv( 'VELOTOK_YOOKASSA_SECRET_KEY=test-secret-45' );
putenv( 'VELOTOK_YOOKASSA_WEBHOOK_SECRET=test-webhook-secret-45' );
$GLOBALS['velotok_test_options'] = array();

$secret = 'test-webhook-secret-45';

$payload = array(
	'event'  => 'payment.succeeded',
	'object' => array(
		'id'       => 'pay-testeb-45-a',
		'status'   => 'succeeded',
		'metadata' => array( 'order_id' => '501' ),
	),
);
$raw       = json_encode( $payload );
$signature = velotok_core_payment_webhook_signature( $raw );

velotok_payment_webhook_test_assert( '' !== $signature, 'Подпись webhook должна вычисляться через HMAC.' );
velotok_payment_webhook_test_assert( $signature === velotok_core_payment_webhook_signature( $raw, $secret ), 'Подпись должна зависеть от секрета.' );
velotok_payment_webhook_test_assert( velotok_core_payment_webhook_verify( $raw, $signature ), 'Верная подпись должна приниматься.' );
velotok_payment_webhook_test_assert( velotok_core_payment_webhook_verify( $raw, 'sha256=' . $signature ), 'Подпись с префиксом sha256= должна приниматься.' );
velotok_payment_webhook_test_assert( ! velotok_core_payment_webhook_verify( $raw, 'bad-signature' ), 'Неверная подпись должна отклоняться.' );
velotok_payment_webhook_test_assert( ! velotok_core_payment_webhook_verify( $raw, $signature, 'wrong-secret' ), 'Подпись с другим секретом должна отклоняться.' );
velotok_payment_webhook_test_assert( ! velotok_core_payment_webhook_verify( $raw, $signature, '' ), 'Пустой секрет должен отклонять webhook.' );
velotok_payment_webhook_test_assert( ! velotok_core_payment_webhook_verify( $raw, '' ), 'Пустая подпись должна отклоняться.' );
velotok_payment_webhook_test_assert( $signature === velotok_core_payment_webhook_signature_from_headers( array( 'HTTP_X_VELOTOK_SIGNATURE' => $signature ) ), 'Подпись должна читаться из серверного заголовка.' );
velotok_payment_webhook_test_assert( $signature === velotok_core_payment_webhook_signature_from_headers( array( 'x-yookassa-signature' => $signature ) ), 'Подпись должна читаться из заголовка без учёта регистра.' );

$order_501 = new Velotok_Webhook_Test_Order( 501, 1000 );
$GLOBALS['velotok_test_orders'][501] = $order_501;

$rejected = velotok_core_payment_webhook_process( $raw, 'bad-signature' );
velotok_payment_webhook_test_assert( false === $rejected['ok'], 'Webhook с неверной подписью не должен применяться.' );
velotok_payment_webhook_test_assert( false === $rejected['verified'], 'Webhook с неверной подписью не должен считаться проверенным.' );
velotok_payment_webhook_test_assert( 'PAYMENT_WEBHOOK_SIGNATURE_INVALID' === $rejected['code'], 'Неверная подпись должна давать код PAYMENT_WEBHOOK_SIGNATURE_INVALID.' );
velotok_payment_webhook_test_assert( 'pending' === $order_501->status, 'Отклонённый webhook не должен менять статус заказа.' );
velotok_payment_webhook_test_assert( 0 === $order_501->status_updates, 'Отклонённый webhook не должен обновлять заказ.' );
velotok_payment_webhook_test_assert( '' === $order_501->get_meta( '_velotok_payment_status' ), 'Отклонённый webhook не должен сохранять статус оплаты.' );

$missing = velotok_core_payment_webhook_process( $raw );
velotok_payment_webhook_test_assert( false === $missing['ok'] && false === $missing['verified'], 'Webhook без подписи должен отклоняться.' );

$first = velotok_core_payment_webhook_process( $raw, $signature );
velotok_payment_webhook_test_assert( true === $first['ok'], 'Валидный webhook должен приниматься.' );
velotok_payment_webhook_test_assert( true === $first['verified'], 'Валидный webhook должен быть проверен по подписи.' );
velotok_payment_webhook_test_assert( true === $first['idempotent'], 'Webhook должен быть идемпотентным.' );
velotok_payment_webhook_test_assert( false === $first['duplicate'], 'Первый webhook не должен считаться дубликатом.' );
velotok_payment_webhook_test_assert( 'succeeded' === $first['status'], 'Webhook должен применять статус succeeded.' );
velotok_payment_webhook_test_assert( '501' === $first['order_id'], 'Webhook должен находить заказ по metadata.order_id.' );
velotok_payment_webhook_test_assert( 'processing' === $order_501->status, 'Статус заказа должен отражать оплату.' );
velotok_payment_webhook_test_assert( 'succeeded' === $order_501->get_meta( '_velotok_payment_status' ), 'Статус оплаты должен сохраняться в заказе.' );
velotok_payment_webhook_test_assert( 1 === $order_501->status_updates, 'Первый webhook должен обновить заказ ровно один раз.' );

$duplicate = velotok_core_payment_webhook_process( $raw, $signature );
velotok_payment_webhook_test_assert( true === $duplicate['ok'], 'Повторный webhook должен отвечать успешно.' );
velotok_payment_webhook_test_assert( true === $duplicate['duplicate'], 'Повторный webhook должен распознаваться как дубликат.' );
velotok_payment_webhook_test_assert( true === $duplicate['idempotent'], 'Дубликат должен оставаться идемпотентным.' );
velotok_payment_webhook_test_assert( 'PAYMENT_WEBHOOK_DUPLICATE' === $duplicate['code'], 'Дубликат должен возвращать код PAYMENT_WEBHOOK_DUPLICATE.' );
velotok_payment_webhook_test_assert( 'succeeded' === $duplicate['status'], 'Дубликат должен возвращать уже применённый статус.' );
velotok_payment_webhook_test_assert( 'processing' === $duplicate['order_status'], 'Дубликат должен возвращать статус заказа.' );
velotok_payment_webhook_test_assert( 1 === $order_501->status_updates, 'Дубликат не должен повторно обновлять заказ.' );

$replay_with_bad_signature = velotok_core_payment_webhook_process( $raw, 'bad-signature' );
velotok_payment_webhook_test_assert( false === $replay_with_bad_signature['ok'], 'Повтор с неверной подписью должен отклоняться, а не считаться дубликатом.' );
velotok_payment_webhook_test_assert( 'PAYMENT_WEBHOOK_SIGNATURE_INVALID' === $replay_with_bad_signature['code'], 'Повтор с неверной подписью должен возвращать ошибку подписи.' );

$payload_b = array(
	'event'  => 'payment.canceled',
	'object' => array(
		'id'       => 'pay-testeb-45-b',
		'status'   => 'canceled',
		'metadata' => array( 'order_id' => '502' ),
	),
);
$raw_b       = json_encode( $payload_b );
$signature_b = velotok_core_payment_webhook_signature( $raw_b );

$order_502 = new Velotok_Webhook_Test_Order( 502, 700 );
$GLOBALS['velotok_test_orders'][502] = $order_502;

$from_header = velotok_core_payment_webhook_process( $raw_b, null, array( 'HTTP_X_VELOTOK_SIGNATURE' => $signature_b ) );
velotok_payment_webhook_test_assert( true === $from_header['ok'] && true === $from_header['verified'], 'Подпись из заголовка должна приниматься.' );
velotok_payment_webhook_test_assert( 'canceled' === $from_header['status'], 'Webhook отмены должен применять canceled.' );
velotok_payment_webhook_test_assert( 'cancelled' === $order_502->status, 'Отмена должна переводить заказ в cancelled.' );
velotok_payment_webhook_test_assert( 1 === $order_502->status_updates, 'Webhook отмены должен обновить заказ ровно один раз.' );

$event_payload = array(
	'event'  => 'payment.succeeded',
	'object' => array(
		'id'     => 'pay-testeb-45-id',
		'status' => 'succeeded',
	),
);
$event_id_a = velotok_core_payment_webhook_event_id( $event_payload );
$event_id_b = velotok_core_payment_webhook_event_id( $event_payload );
velotok_payment_webhook_test_assert( '' !== $event_id_a, 'Стабильный ключ события webhook должен вычисляться.' );
velotok_payment_webhook_test_assert( $event_id_a === $event_id_b, 'Ключ события без event.id должен быть стабильным.' );

echo 'PASS: webhook проверяет подпись и идемпотентен (' . (int) $GLOBALS['velotok_payment_webhook_test_passed'] . ' проверок, TESTEB-45).' . PHP_EOL;
