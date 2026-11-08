<?php
/**
 * Focused contract test for TESTEB-48.
 *
 * Spec: payment. Requirement: payment statuses form a fixed model with
 * canonical identifiers, labels, order-status mapping and transitions.
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

if ( ! class_exists( 'Velotok_Status_Model_Test_Order' ) ) {
	class Velotok_Status_Model_Test_Order {
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

$implementation = getenv( 'VELOTOK_PAYMENT_GATEWAY_FILE' );

if ( false === $implementation || '' === $implementation ) {
	$implementation = dirname( __DIR__ ) . '/includes/payment-gateway.php';
}

if ( ! is_readable( $implementation ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл реализации платёжного шлюза: ' . $implementation . PHP_EOL );
	exit( 1 );
}

require_once $implementation;

$GLOBALS['velotok_payment_status_model_test_passed'] = 0;

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_payment_status_model_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}

	$GLOBALS['velotok_payment_status_model_test_passed']++;
}

$expected_statuses = array( 'pending', 'waiting_for_capture', 'succeeded', 'canceled', 'refunded' );

$expected_order_statuses = array(
	'pending'             => 'pending',
	'waiting_for_capture' => 'on-hold',
	'succeeded'           => 'processing',
	'canceled'            => 'cancelled',
	'refunded'            => 'refunded',
);

$expected_transitions = array(
	'pending'             => array( 'waiting_for_capture', 'succeeded', 'canceled' ),
	'waiting_for_capture' => array( 'succeeded', 'canceled' ),
	'succeeded'           => array( 'refunded' ),
	'canceled'            => array(),
	'refunded'            => array(),
);

$model = velotok_core_payment_status_model();

velotok_payment_status_model_test_assert(
	is_array( $model ),
	'Модель статусов оплаты должна быть массивом.'
);
velotok_payment_status_model_test_assert(
	$expected_statuses === array_keys( $model ),
	'Статусы оплаты должны образовывать фиксированную модель.'
);
velotok_payment_status_model_test_assert(
	$expected_statuses === velotok_core_payment_statuses(),
	'Список статусов оплаты должен совпадать с фиксированной моделью.'
);
velotok_payment_status_model_test_assert(
	$expected_statuses === array_keys( velotok_core_payment_status_model() ),
	'Повторный вызов модели должен возвращать тот же фиксированный набор статусов.'
);
velotok_payment_status_model_test_assert(
	$model === velotok_payment_status_model(),
	'Совместимый алиас должен возвращать фиксированную модель статусов.'
);
velotok_payment_status_model_test_assert(
	$expected_statuses === velotok_core_payment_status_ids(),
	'Идентификаторы статусов должны совпадать с фиксированной моделью.'
);
velotok_payment_status_model_test_assert(
	'succeeded' === velotok_payment_normalize_status( 'Оплачен' ),
	'Совместимый алиас должен нормализовать статус в succeeded.'
);
velotok_payment_status_model_test_assert(
	velotok_payment_can_transition( 'succeeded', 'refunded' ),
	'Совместимый алиас должен разрешать переход succeeded -> refunded.'
);
velotok_payment_status_model_test_assert(
	'Оплачен' === velotok_payment_status_label( 'succeeded' ),
	'Совместимый алиас должен возвращать название статуса.'
);
velotok_payment_status_model_test_assert(
	array( 'refunded' ) === velotok_core_payment_status_transitions( 'succeeded' ),
	'Переходы статуса должны читаться из фиксированной модели.'
);

foreach ( $expected_statuses as $status_id ) {
	$entry = $model[ $status_id ];

	velotok_payment_status_model_test_assert(
		isset( $entry['id'], $entry['code'], $entry['label'], $entry['order_status'], $entry['transitions'] ),
		'Статус ' . $status_id . ' должен содержать id, code, label, order_status и transitions.'
	);
	velotok_payment_status_model_test_assert(
		$status_id === $entry['id'] && $status_id === $entry['code'],
		'Статус ' . $status_id . ' должен иметь канонический идентификатор.'
	);
	velotok_payment_status_model_test_assert(
		'' !== (string) $entry['label'],
		'Статус ' . $status_id . ' должен иметь человекочитаемое название.'
	);
	velotok_payment_status_model_test_assert(
		$expected_order_statuses[ $status_id ] === $entry['order_status'],
		'Статус ' . $status_id . ' должен отображаться в фиксированный статус заказа.'
	);
	velotok_payment_status_model_test_assert(
		$expected_transitions[ $status_id ] === $entry['transitions'],
		'Статус ' . $status_id . ' должен иметь фиксированный набор переходов.'
	);
	velotok_payment_status_model_test_assert(
		in_array( $status_id, array( 'canceled', 'refunded' ), true ) === (bool) $entry['final'],
		'Признак final статуса ' . $status_id . ' должен соответствовать модели.'
	);

	foreach ( $entry['transitions'] as $target ) {
		velotok_payment_status_model_test_assert(
			in_array( $target, $expected_statuses, true ),
			'Переход из ' . $status_id . ' должен вести только в статусы фиксированной модели.'
		);
	}

	velotok_payment_status_model_test_assert(
		velotok_core_payment_status_is_valid( $status_id ),
		'Статус ' . $status_id . ' должен быть валидным.'
	);
	velotok_payment_status_model_test_assert(
		$status_id === velotok_core_payment_normalize_status( $status_id ),
		'Статус ' . $status_id . ' должен нормализоваться в себя.'
	);
	velotok_payment_status_model_test_assert(
		(string) $entry['label'] === velotok_core_payment_status_label( $status_id ),
		'Название статуса ' . $status_id . ' должно читаться из модели.'
	);
	velotok_payment_status_model_test_assert(
		velotok_core_payment_can_transition( $status_id, $status_id ),
		'Повторное применение статуса ' . $status_id . ' должно быть допустимым.'
	);
}

foreach ( $expected_statuses as $from ) {
	foreach ( $expected_statuses as $to ) {
		$expected = $from === $to || in_array( $to, $expected_transitions[ $from ], true );

		velotok_payment_status_model_test_assert(
			$expected === velotok_core_payment_can_transition( $from, $to ),
			'Переход ' . $from . ' -> ' . $to . ' должен соответствовать фиксированной модели.'
		);
	}
}

$aliases = array(
	'new'                 => 'pending',
	'created'             => 'pending',
	'ожидает'             => 'pending',
	'ожидает оплаты'      => 'pending',
	'waiting'             => 'waiting_for_capture',
	'authorized'          => 'waiting_for_capture',
	'захвачен'            => 'waiting_for_capture',
	'paid'                => 'succeeded',
	'success'             => 'succeeded',
	'оплачен'             => 'succeeded',
	'успешно'             => 'succeeded',
	'cancelled'           => 'canceled',
	'cancel'              => 'canceled',
	'отменён'             => 'canceled',
	'отменен'             => 'canceled',
	'refund'              => 'refunded',
	'returned'            => 'refunded',
	'возврат'             => 'refunded',
	'возвращён'           => 'refunded',
	'возвращен'           => 'refunded',
);

foreach ( $aliases as $alias => $canonical ) {
	velotok_payment_status_model_test_assert(
		$canonical === velotok_core_payment_normalize_status( $alias ),
		'Значение «' . $alias . '» должно нормализоваться в ' . $canonical . '.'
	);
	velotok_payment_status_model_test_assert(
		velotok_core_payment_status_is_valid( $alias ),
		'Значение «' . $alias . '» должно разрешаться в статус фиксированной модели.'
	);
}

foreach ( array( 'unknown', 'processing', 'failure', '' ) as $invalid ) {
	velotok_payment_status_model_test_assert(
		! velotok_core_payment_status_is_valid( $invalid ),
		'Значение «' . $invalid . '» не должно быть валидным статусом оплаты.'
	);
	velotok_payment_status_model_test_assert(
		'' === velotok_core_payment_order_status( $invalid ),
		'Для неизвестного статуса «' . $invalid . '» не должно быть статуса заказа.'
	);
}

velotok_payment_status_model_test_assert(
	velotok_core_payment_can_transition( 'Оплачен', 'возврат' ),
	'Алиасы должны участвовать в проверке переходов.'
);
velotok_payment_status_model_test_assert(
	! velotok_core_payment_can_transition( 'refunded', 'succeeded' ),
	'Возврат должен быть конечным статусом без перехода в succeeded.'
);
velotok_payment_status_model_test_assert(
	! velotok_core_payment_can_transition( 'canceled', 'pending' ),
	'Отменённый платёж не должен возвращаться в pending.'
);

$contract = velotok_core_payment_contract();

velotok_payment_status_model_test_assert(
	$model === $contract['statuses'],
	'Контракт должен содержать фиксированную модель статусов.'
);
velotok_payment_status_model_test_assert(
	$expected_statuses === $contract['status_ids'],
	'Контракт должен фиксировать идентификаторы статусов.'
);
velotok_payment_status_model_test_assert(
	$expected_order_statuses === $contract['order_statuses'],
	'Контракт должен фиксировать отображение статусов оплаты в статусы заказа.'
);

$order_number = 480;

foreach ( $expected_statuses as $status_id ) {
	$order = new Velotok_Status_Model_Test_Order( ++$order_number, 1000 );

	velotok_payment_status_model_test_assert(
		true === velotok_core_payment_apply_order_status( $order, $status_id, 'Тестовый статус' ),
		'Статус ' . $status_id . ' должен применяться к заказу.'
	);
	velotok_payment_status_model_test_assert(
		$expected_order_statuses[ $status_id ] === $order->status,
		'Статус заказа должен соответствовать статусу оплаты ' . $status_id . '.'
	);
	velotok_payment_status_model_test_assert(
		$status_id === $order->get_meta( '_velotok_payment_status' ),
		'К заказу должен применяться канонический статус ' . $status_id . '.'
	);
	velotok_payment_status_model_test_assert(
		$expected_order_statuses[ $status_id ] === $order->get_meta( '_velotok_payment_order_status' ),
		'Заказ должен хранить отображённый статус для ' . $status_id . '.'
	);
}

$alias_order = new Velotok_Status_Model_Test_Order( ++$order_number, 1000 );

velotok_payment_status_model_test_assert(
	true === velotok_core_payment_apply_order_status( $alias_order, 'Оплачен' ),
	'Алиас статуса оплаты должен применяться к заказу.'
);
velotok_payment_status_model_test_assert(
	'succeeded' === $alias_order->get_meta( '_velotok_payment_status' ),
	'К заказу должен применяться канонический статус вместо алиаса.'
);

$invalid_order = new Velotok_Status_Model_Test_Order( ++$order_number, 1000 );

velotok_payment_status_model_test_assert(
	false === velotok_core_payment_apply_order_status( $invalid_order, 'unknown' ),
	'Неизвестный статус не должен применяться к заказу.'
);
velotok_payment_status_model_test_assert(
	'pending' === $invalid_order->status && '' === $invalid_order->get_meta( '_velotok_payment_status' ),
	'Неизвестный статус не должен менять заказ.'
);

echo 'PASS: статусы оплаты образуют фиксированную модель (' . (int) $GLOBALS['velotok_payment_status_model_test_passed'] . ' проверок, TESTEB-48).' . PHP_EOL;
