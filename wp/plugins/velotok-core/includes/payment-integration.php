<?php
/**
 * Payment system integration stub contract.
 *
 * This is the single access point for paying for an order through the payment
 * system. The current iteration deliberately uses a deterministic in-memory
 * stub: it does not read environment variables, files, the database or remote
 * payment services, and it never moves real money.
 *
 * The contract covers the payment provider, the payment lifecycle, the order
 * amounts and currencies, the mocked confirmation redirect and the future
 * environment variable names. A real ЮKassa integration is out of scope until
 * the open questions are resolved.
 *
 * Spec: ecommerce-store (requirement TESTEB-153).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

/**
 * Return the payment integration contract.
 *
 * The contract documents:
 * - the spec identifier, the explicit `mocked` marker and the stub source;
 * - the fixed ЮKassa provider, its modes and future environment variables;
 * - the supported payment statuses and the mocked lifecycle transitions;
 * - the request and response fields of the payment operation;
 * - the error codes returned for invalid payment data;
 * - the open questions that block a real payment integration.
 *
 * @return array<string,mixed>
 */
function velotok_core_payment_integration_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$statuses = array(
		'pending'              => array(
			'id'      => 'pending',
			'code'    => 'pending',
			'label'   => 'Ожидает оплаты',
			'paid'    => false,
			'final'   => false,
			'mocked'  => true,
		),
		'waiting_for_capture'  => array(
			'id'      => 'waiting_for_capture',
			'code'    => 'waiting_for_capture',
			'label'   => 'Ожидает списания',
			'paid'    => false,
			'final'   => false,
			'mocked'  => true,
		),
		'succeeded'            => array(
			'id'      => 'succeeded',
			'code'    => 'succeeded',
			'label'   => 'Оплачен',
			'paid'    => true,
			'final'   => true,
			'mocked'  => true,
		),
		'canceled'             => array(
			'id'      => 'canceled',
			'code'    => 'canceled',
			'label'   => 'Отменён',
			'paid'    => false,
			'final'   => true,
			'mocked'  => true,
		),
	);

	$status_transitions = array(
		'pending'             => array( 'waiting_for_capture', 'succeeded', 'canceled' ),
		'waiting_for_capture' => array( 'succeeded', 'canceled' ),
		'succeeded'           => array(),
		'canceled'            => array(),
	);

	$fields = array(
		'order_id'    => array(
			'type'     => 'string',
			'required' => true,
			'mocked'   => true,
		),
		'amount'      => array(
			'type'     => 'number',
			'required' => true,
			'mocked'   => true,
		),
		'currency'    => array(
			'type'     => 'string',
			'required' => true,
			'mocked'   => true,
		),
		'description' => array(
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
		'return_url'  => array(
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
	);

	$contract = array(
		'spec'                    => 'ecommerce-store',
		'requirement'             => 'TESTEB-153',
		'parent_spec'             => 'ecommerce-store',
		'checkout_spec'           => 'clarify-096bb945',
		'payment_spec'            => 'clarify-096bb945',
		'integration_spec'        => 'clarify-096bb945',
		'parent_relation'         => 'Интеграция с платёжной системой реализует требование родительской спецификации ecommerce-store без реального списания денег.',
		'source'                  => 'stub',
		'provider'                => 'yookassa',
		'provider_id'             => 'yookassa',
		'provider_name'           => 'ЮKassa',
		'provider_label'          => 'ЮKassa',
		'provider_source'         => 'stub',
		'mocked'                  => true,
		'network'                 => false,
		'persistent'              => false,
		'real_integration'        => array(
			'required' => false,
			'enabled'  => false,
			'source'   => 'stub',
			'mocked'   => true,
		),
		'payment_system'          => 'yookassa',
		'payment_system_slug'     => 'yookassa',
		'payment_system_name'     => 'ЮKassa',
		'payment_system_label'    => 'ЮKassa',
		'payment_method'          => 'yookassa',
		'payment_provider'        => 'yookassa',
		'payment_provider_label'  => 'ЮKassa',
		'payment_system_id'       => 'yookassa',
		'payment'                 => array(
			'provider'        => 'yookassa',
			'provider_name'   => 'ЮKassa',
			'provider_label'  => 'ЮKassa',
			'source'          => 'stub',
			'mocked'          => true,
			'network'         => false,
			'default_mode'    => 'test',
			'supported_modes' => array( 'test', 'prod' ),
			'currency'        => 'RUB',
			'capture'         => true,
			'confirmation'    => 'redirect',
			'initial_status'  => 'pending',
			'statuses'        => array_keys( $statuses ),
			'env'             => array(
				'shop_id'    => 'VELOTOK_YOOKASSA_SHOP_ID',
				'secret_key' => 'VELOTOK_YOOKASSA_SECRET_KEY',
				'mode'       => 'VELOTOK_YOOKASSA_MODE',
			),
		),
		'payment_statuses'        => array_keys( $statuses ),
		'payment_type'            => 'online_payment',
		'operation'               => 'process_payment',
		'mocked_operation'        => 'process_payment',
		'currency'                => 'RUB',
		'supported_currencies'    => array( 'RUB' ),
		'default_mode'            => 'test',
		'supported_modes'         => array( 'test', 'prod' ),
		'capture'                 => true,
		'confirmation'            => 'redirect',
		'confirmation_type'       => 'redirect',
		'mock_confirmation'       => 'mock_redirect',
		'initial_status'          => 'pending',
		'mock_status'             => 'pending',
		'final_status'            => 'succeeded',
		'statuses'                => $statuses,
		'status_ids'              => array_keys( $statuses ),
		'supported_statuses'      => array_keys( $statuses ),
		'status_map'              => array(
			'succeeded' => 'paid',
			'canceled'  => 'canceled',
			'pending'   => 'pending',
			'waiting_for_capture' => 'waiting_for_capture',
		),
		'status_transitions'      => $status_transitions,
		'fields'                  => $fields,
		'request'                 => array(
			'required' => array( 'order_id', 'amount', 'currency' ),
			'optional' => array( 'description', 'return_url' ),
			'fields'   => $fields,
		),
		'response'                => array(
			'required_keys' => array(
				'id',
				'provider',
				'mocked',
				'order_id',
				'status',
				'paid',
				'amount',
				'currency',
				'confirmation_url',
			),
		),
		'operations'              => array(
			'create'      => 'velotok_core_payment_integration_create',
			'process'     => 'velotok_core_payment_integration_process',
			'get_status'  => 'velotok_core_payment_integration_status',
			'confirm'     => 'velotok_core_payment_integration_confirm',
			'validate'    => 'velotok_core_payment_integration_validate',
			'is_paid'     => 'velotok_core_payment_integration_is_paid',
		),
		'future_source'           => 'env',
		'env_prefix'              => 'VELOTOK_YOOKASSA_',
		'env'                     => array(
			'shop_id'    => 'VELOTOK_YOOKASSA_SHOP_ID',
			'secret_key' => 'VELOTOK_YOOKASSA_SECRET_KEY',
			'mode'       => 'VELOTOK_YOOKASSA_MODE',
		),
		'error_codes'             => array(
			'provider_unsupported' => 'PAYMENT_PROVIDER_UNSUPPORTED',
			'amount_invalid'       => 'PAYMENT_AMOUNT_INVALID',
			'currency_unsupported' => 'PAYMENT_CURRENCY_UNSUPPORTED',
			'order_required'       => 'PAYMENT_ORDER_REQUIRED',
			'status_invalid'       => 'PAYMENT_STATUS_INVALID',
			'not_paid'             => 'PAYMENT_NOT_PAID',
		),
		'provider_unsupported'    => 'PAYMENT_PROVIDER_UNSUPPORTED',
		'amount_invalid'          => 'PAYMENT_AMOUNT_INVALID',
		'currency_unsupported'    => 'PAYMENT_CURRENCY_UNSUPPORTED',
		'status_invalid'          => 'PAYMENT_STATUS_INVALID',
		'not_paid'                => 'PAYMENT_NOT_PAID',
		'open_questions'          => array(
			'provider'         => 'Конкретная платёжная система и перечень поддерживаемых статусов оплаты (clarify-096bb945): зафиксирована ЮKassa как mocked-провайдер.',
			'modes'            => 'Разделение тестового и боевого режимов ЮKassa, источник и формат учётных данных магазина, подстановка секретов из env в runtime.',
			'statuses'         => 'Полный перечень статусов оплаты, правила переходов между ними и обработка возвратов и частичных списаний.',
			'confirmation'     => 'Сценарий подтверждения оплаты: redirect, возврат на сайт, обработка отмены пользователем и повторная оплата.',
			'order_sync'       => 'Как статус оплаты синхронизируется с заказом, корзиной и доставкой после реального ответа провайдера.',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer a full explicit contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_payment_integration_stub_contract() {
	return velotok_core_payment_integration_contract();
}

/**
 * Alias kept for callers that use the payment system wording.
 *
 * @return array<string,mixed>
 */
function velotok_core_payment_system_contract() {
	return velotok_core_payment_integration_contract();
}

/**
 * Alias kept for callers that use a short integration contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_payment_integration_mocked_contract() {
	return velotok_core_payment_integration_contract();
}

/**
 * Return the open questions that block a real payment integration.
 *
 * @return array<string,string>
 */
function velotok_core_payment_integration_open_questions() {
	$contract = velotok_core_payment_integration_contract();

	return $contract['open_questions'];
}

/**
 * Return every supported payment status keyed by identifier.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_payment_integration_statuses() {
	$contract = velotok_core_payment_integration_contract();

	return $contract['statuses'];
}

/**
 * Return the supported payment status identifiers.
 *
 * @return string[]
 */
function velotok_core_payment_integration_status_keys() {
	$contract = velotok_core_payment_integration_contract();

	return $contract['status_ids'];
}

/**
 * Normalize a payment status or key for comparisons.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function velotok_core_payment_integration_normalize_key( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	if ( function_exists( 'mb_strtolower' ) ) {
		return mb_strtolower( $value, 'UTF-8' );
	}

	return strtolower( $value );
}

/**
 * Resolve a payment status by identifier or Russian label.
 *
 * @param mixed $status Payment status.
 * @return array<string,mixed>|null Null for an unknown status.
 */
function velotok_core_payment_integration_resolve_status( $status ) {
	if ( ! is_scalar( $status ) ) {
		return null;
	}

	$key = velotok_core_payment_integration_normalize_key( $status );

	if ( '' === $key ) {
		return null;
	}

	$statuses = velotok_core_payment_integration_statuses();

	if ( isset( $statuses[ $key ] ) ) {
		return $statuses[ $key ];
	}

	$aliases = array(
		'оплачен'          => 'succeeded',
		'оплачено'         => 'succeeded',
		'успешно'          => 'succeeded',
		'успех'            => 'succeeded',
		'ожидает оплаты'   => 'pending',
		'ожидание оплаты'  => 'pending',
		'ожидает списания' => 'waiting_for_capture',
		'отменён'          => 'canceled',
		'отменен'          => 'canceled',
		'отмена'           => 'canceled',
		'не оплачен'       => 'canceled',
		'paid'             => 'succeeded',
		'cancelled'        => 'canceled',
		'cancel'           => 'canceled',
	);

	if ( isset( $aliases[ $key ] ) ) {
		$key = $aliases[ $key ];
	}

	if ( isset( $statuses[ $key ] ) ) {
		return $statuses[ $key ];
	}

	foreach ( $statuses as $status_contract ) {
		foreach ( array( 'id', 'code', 'label' ) as $field ) {
			if ( isset( $status_contract[ $field ] ) && velotok_core_payment_integration_normalize_key( $status_contract[ $field ] ) === $key ) {
				return $status_contract;
			}
		}
	}

	return null;
}

/**
 * Check whether a payment status is supported.
 *
 * @param mixed $status Payment status.
 * @return bool
 */
function velotok_core_payment_integration_status_is_valid( $status ) {
	return null !== velotok_core_payment_integration_resolve_status( $status );
}

/**
 * Return the human-readable label for a payment status.
 *
 * @param mixed $status Payment status.
 * @return string Empty string for an unknown status.
 */
function velotok_core_payment_integration_status_label( $status ) {
	$resolved = velotok_core_payment_integration_resolve_status( $status );

	return null === $resolved ? '' : $resolved['label'];
}

/**
 * Check whether a payment provider is supported by the stub.
 *
 * Accepts the machine slug and the human-readable label.
 *
 * @param mixed $provider Provider identifier or label.
 * @return bool
 */
function velotok_core_payment_integration_provider_is_supported( $provider ) {
	if ( function_exists( 'velotok_core_payment_provider_is_supported' ) ) {
		return velotok_core_payment_provider_is_supported( $provider );
	}

	$key = velotok_core_payment_integration_normalize_key( $provider );

	if ( '' === $key ) {
		return false;
	}

	$allowed = array( 'yookassa', 'yoo-kassa', 'yoo_kassa', 'юkassa', 'юкасса', 'ю-касса', 'ю касса' );

	foreach ( $allowed as $candidate ) {
		if ( velotok_core_payment_integration_normalize_key( $candidate ) === $key ) {
			return true;
		}
	}

	return false;
}

/**
 * Read an order total from an array or an object without external calls.
 *
 * @param mixed $order Order data.
 * @return float
 */
function velotok_core_payment_integration_order_amount( $order ) {
	$amount = 0.0;

	if ( is_array( $order ) ) {
		foreach ( array( 'total', 'order_total', 'amount', 'price' ) as $key ) {
			if ( isset( $order[ $key ] ) && is_numeric( $order[ $key ] ) ) {
				$amount = (float) $order[ $key ];
				break;
			}
		}
	} elseif ( is_object( $order ) && method_exists( $order, 'get_total' ) ) {
		$total = $order->get_total();

		if ( is_numeric( $total ) ) {
			$amount = (float) $total;
		}
	} elseif ( is_numeric( $order ) ) {
		$amount = (float) $order;
	}

	return round( $amount, 2 );
}

/**
 * Read an order identifier from an array or an object without external calls.
 *
 * @param mixed $order Order data.
 * @return string
 */
function velotok_core_payment_integration_order_id( $order ) {
	$id = '';

	if ( is_array( $order ) ) {
		foreach ( array( 'id', 'order_id' ) as $key ) {
			if ( isset( $order[ $key ] ) && is_scalar( $order[ $key ] ) ) {
				$id = (string) $order[ $key ];
				break;
			}
		}
	} elseif ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
		$id = (string) $order->get_id();
	} elseif ( is_scalar( $order ) ) {
		$id = (string) $order;
	}

	return $id;
}

/**
 * Build a deterministic mocked payment identifier.
 *
 * @param mixed $order Order data.
 * @return string
 */
function velotok_core_payment_integration_payment_id( $order ) {
	$order_id = velotok_core_payment_integration_order_id( $order );
	$token    = '' === $order_id ? 'order' : (string) preg_replace( '/[^a-z0-9_-]+/i', '-', $order_id );

	return 'mock-yookassa-' . $token;
}

/**
 * Create a mocked payment for an order.
 *
 * No network request is performed and no money is moved. The result is marked
 * as mocked and contains an internal mock confirmation URL.
 *
 * @param mixed               $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_payment_integration_create( $order = array(), $options = array() ) {
	$contract = velotok_core_payment_integration_contract();
	$amount   = velotok_core_payment_integration_order_amount( $order );
	$order_id = velotok_core_payment_integration_order_id( $order );
	$payment  = velotok_core_payment_integration_payment_id( $order );
	$status   = velotok_core_payment_integration_resolve_status( $contract['initial_status'] );

	if ( ! is_array( $options ) ) {
		$options = array();
	}

	if ( isset( $options['amount'] ) && is_numeric( $options['amount'] ) ) {
		$amount = round( (float) $options['amount'], 2 );
	}

	return array(
		'id'               => $payment,
		'payment_id'       => $payment,
		'provider'         => $contract['provider'],
		'provider_name'    => $contract['provider_name'],
		'payment_system'   => $contract['payment_system'],
		'source'           => 'stub',
		'mocked'           => true,
		'network'          => false,
		'real'             => false,
		'operation'        => $contract['operation'],
		'mode'             => $contract['default_mode'],
		'order_id'         => $order_id,
		'status'           => $status['id'],
		'status_label'     => $status['label'],
		'paid'             => false,
		'capture'          => true === $contract['capture'],
		'amount'           => array(
			'value'    => $amount,
			'currency' => $contract['currency'],
		),
		'amount_value'     => $amount,
		'currency'         => $contract['currency'],
		'confirmation'     => array(
			'type' => $contract['mock_confirmation'],
			'url'  => 'mock://yookassa/' . $payment,
		),
		'confirmation_url' => 'mock://yookassa/' . $payment,
		'options'          => $options,
	);
}

/**
 * Alias for creating a mocked payment.
 *
 * @param mixed               $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_payment_integration_process( $order = array(), $options = array() ) {
	return velotok_core_payment_integration_create( $order, $options );
}

/**
 * Alias for creating a mocked payment.
 *
 * @param mixed               $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_payment_integration_process_payment( $order = array(), $options = array() ) {
	return velotok_core_payment_integration_create( $order, $options );
}

/**
 * Alias for creating a mocked payment.
 *
 * @param mixed               $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_payment_integration_init( $order = array(), $options = array() ) {
	return velotok_core_payment_integration_create( $order, $options );
}

/**
 * Read the status from a payment array.
 *
 * @param array<string,mixed> $payment Payment data.
 * @return string
 */
function velotok_core_payment_integration_payment_status( $payment ) {
	if ( is_array( $payment ) ) {
		foreach ( array( 'status', 'payment_status', 'state' ) as $key ) {
			if ( isset( $payment[ $key ] ) && is_scalar( $payment[ $key ] ) && '' !== trim( (string) $payment[ $key ] ) ) {
				return (string) $payment[ $key ];
			}
		}
	}

	if ( is_object( $payment ) && method_exists( $payment, 'get_status' ) ) {
		return (string) $payment->get_status();
	}

	return '';
}

/**
 * Resolve a payment status and describe whether it is final and paid.
 *
 * The optional second argument lets callers apply a mocked status transition.
 *
 * @param mixed $payment Payment data, payment id or a status string.
 * @param mixed $status  Optional status to apply.
 * @return array<string,mixed>
 */
function velotok_core_payment_integration_status_result( $payment = array(), $status = null ) {
	$contract = velotok_core_payment_integration_contract();
	$value    = null;

	if ( null !== $status ) {
		$value = $status;
	} elseif ( is_array( $payment ) || is_object( $payment ) ) {
		$value = velotok_core_payment_integration_payment_status( $payment );
	} elseif ( is_scalar( $payment ) ) {
		$value = (string) $payment;
	}

	$resolved = velotok_core_payment_integration_resolve_status( $value );

	if ( null === $resolved ) {
		return array(
			'valid'   => false,
			'code'    => $contract['status_invalid'],
			'error'   => $contract['status_invalid'],
			'id'      => '',
			'status'  => '',
			'label'   => '',
			'paid'    => false,
			'final'   => false,
			'mocked'  => true,
			'source'  => 'stub',
		);
	}

	return array(
		'valid'   => true,
		'code'    => null,
		'error'   => null,
		'id'      => $resolved['id'],
		'status'  => $resolved['id'],
		'label'   => $resolved['label'],
		'paid'    => true === $resolved['paid'],
		'final'   => true === $resolved['final'],
		'mocked'  => true,
		'source'  => 'stub',
	);
}

/**
 * Return the mocked payment status result.
 *
 * @param mixed $payment Payment data, payment id or a status string.
 * @param mixed $status  Optional status to apply.
 * @return array<string,mixed>
 */
function velotok_core_payment_integration_status( $payment = array(), $status = null ) {
	return velotok_core_payment_integration_status_result( $payment, $status );
}

/**
 * Alias for reading the payment status.
 *
 * @param mixed $payment Payment data, payment id or a status string.
 * @return array<string,mixed>
 */
function velotok_core_payment_integration_get_status( $payment = array() ) {
	return velotok_core_payment_integration_status_result( $payment );
}

/**
 * Apply a mocked payment status transition.
 *
 * @param mixed $payment Payment data.
 * @param mixed $status  Target status.
 * @return array<string,mixed>
 */
function velotok_core_payment_integration_confirm( $payment = array(), $status = 'succeeded' ) {
	$result = velotok_core_payment_integration_status_result( $payment, $status );

	if ( is_array( $payment ) ) {
		$payment['status']       = $result['status'];
		$payment['status_label'] = $result['label'];
		$payment['paid']         = $result['paid'];
		$payment['mocked']       = true;
		$payment['confirmed']    = $result['valid'];
		$payment['source']       = 'stub';

		return $payment;
	}

	$result['confirmed'] = $result['valid'];

	return $result;
}

/**
 * Alias for confirming a mocked payment.
 *
 * @param mixed $payment Payment data.
 * @return array<string,mixed>
 */
function velotok_core_payment_integration_confirm_payment( $payment = array() ) {
	return velotok_core_payment_integration_confirm( $payment, 'succeeded' );
}

/**
 * Alias for marking a mocked payment as paid.
 *
 * @param mixed $payment Payment data.
 * @return array<string,mixed>
 */
function velotok_core_payment_integration_mark_paid( $payment = array() ) {
	return velotok_core_payment_integration_confirm( $payment, 'succeeded' );
}

/**
 * Check whether a payment is paid.
 *
 * The stub only reports paid for the `succeeded` status; a freshly created
 * payment is `pending` and therefore not paid.
 *
 * @param mixed $payment Payment data, status string or boolean.
 * @return bool
 */
function velotok_core_payment_integration_is_paid( $payment ) {
	if ( is_bool( $payment ) ) {
		return $payment;
	}

	$status = is_scalar( $payment ) && ! is_array( $payment )
		? (string) $payment
		: velotok_core_payment_integration_payment_status( $payment );
	$result = velotok_core_payment_integration_status_result( $status );

	return true === $result['valid'] && true === $result['paid'];
}

/**
 * Validate payment data against the mocked integration contract.
 *
 * @param mixed $data Payment data.
 * @return array<string,mixed>
 */
function velotok_core_payment_integration_validate( $data = array() ) {
	$contract = velotok_core_payment_integration_contract();

	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$provider = $contract['provider'];

	foreach ( array( 'payment_provider', 'provider', 'payment_system' ) as $key ) {
		if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) && '' !== trim( (string) $data[ $key ] ) ) {
			$provider = (string) $data[ $key ];
			break;
		}
	}

	$currency = $contract['currency'];

	foreach ( array( 'currency', 'payment_currency' ) as $key ) {
		if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) && '' !== trim( (string) $data[ $key ] ) ) {
			$currency = strtoupper( (string) $data[ $key ] );
			break;
		}
	}

	$amount = null;

	foreach ( array( 'amount', 'total', 'order_total', 'price' ) as $key ) {
		if ( isset( $data[ $key ] ) && is_numeric( $data[ $key ] ) ) {
			$amount = round( (float) $data[ $key ], 2 );
			break;
		}
	}

	if ( is_array( $data ) && isset( $data['order'] ) ) {
		$order_amount = velotok_core_payment_integration_order_amount( $data['order'] );

		if ( null === $amount && $order_amount > 0.0 ) {
			$amount = $order_amount;
		}
	}

	$status = $contract['initial_status'];

	foreach ( array( 'status', 'payment_status' ) as $key ) {
		if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) && '' !== trim( (string) $data[ $key ] ) ) {
			$status = (string) $data[ $key ];
			break;
		}
	}

	$errors = array();

	if ( ! velotok_core_payment_integration_provider_is_supported( $provider ) ) {
		$errors[] = $contract['error_codes']['provider_unsupported'];
	}

	if ( null === $amount || $amount < 0.0 ) {
		$errors[] = $contract['error_codes']['amount_invalid'];
	}

	if ( ! in_array( $currency, $contract['supported_currencies'], true ) ) {
		$errors[] = $contract['error_codes']['currency_unsupported'];
	}

	$status_result = velotok_core_payment_integration_status_result( $status );

	if ( ! $status_result['valid'] ) {
		$errors[] = $contract['error_codes']['status_invalid'];
	}

	return array(
		'valid'            => array() === $errors,
		'ok'               => array() === $errors,
		'spec'             => $contract['spec'],
		'requirement'      => $contract['requirement'],
		'mocked'           => true,
		'source'           => 'stub',
		'network'          => false,
		'payment_provider' => $contract['provider'],
		'provider'         => $provider,
		'provider_supported' => velotok_core_payment_integration_provider_is_supported( $provider ),
		'amount'           => $amount,
		'currency'         => $currency,
		'status'           => $status_result['status'],
		'status_valid'     => $status_result['valid'],
		'paid'             => true === $status_result['paid'],
		'errors'           => array_values( $errors ),
		'error'            => $errors ? $errors[0] : null,
	);
}

/**
 * Alias for validating payment data.
 *
 * @param mixed $data Payment data.
 * @return array<string,mixed>
 */
function velotok_core_validate_payment( $data = array() ) {
	return velotok_core_payment_integration_validate( $data );
}

/**
 * Alias for validating payment data.
 *
 * @param mixed $data Payment data.
 * @return array<string,mixed>
 */
function velotok_core_validate_payment_integration( $data = array() ) {
	return velotok_core_payment_integration_validate( $data );
}

if ( ! function_exists( 'velotok_core_payment_provider' ) ) {
	/**
	 * Fallback payment provider accessor for standalone usage.
	 *
	 * @return string
	 */
	function velotok_core_payment_provider() {
		$contract = velotok_core_payment_integration_contract();

		return $contract['provider'];
	}
}

if ( ! function_exists( 'velotok_core_payment_provider_label' ) ) {
	/**
	 * Fallback payment provider label accessor for standalone usage.
	 *
	 * @return string
	 */
	function velotok_core_payment_provider_label() {
		$contract = velotok_core_payment_integration_contract();

		return $contract['provider_name'];
	}
}

if ( ! function_exists( 'velotok_core_payment_is_mocked' ) ) {
	/**
	 * Fallback mocked marker accessor for standalone usage.
	 *
	 * @return bool
	 */
	function velotok_core_payment_is_mocked() {
		$contract = velotok_core_payment_integration_contract();

		return true === $contract['mocked'];
	}
}

if ( ! function_exists( 'velotok_core_payment_mode' ) ) {
	/**
	 * Fallback payment mode accessor for standalone usage.
	 *
	 * @return string
	 */
	function velotok_core_payment_mode() {
		$contract = velotok_core_payment_integration_contract();

		return $contract['default_mode'];
	}
}

if ( ! function_exists( 'velotok_core_payment_env_name' ) ) {
	/**
	 * Fallback future environment variable name accessor.
	 *
	 * @param mixed $key Payment setting key.
	 * @return string
	 */
	function velotok_core_payment_env_name( $key ) {
		$contract = velotok_core_payment_integration_contract();

		if ( ! is_string( $key ) || ! isset( $contract['env'][ $key ] ) ) {
			return '';
		}

		return $contract['env'][ $key ];
	}
}

if ( ! function_exists( 'velotok_core_payment_integration_provider' ) ) {
	/**
	 * Return the mocked payment provider identifier.
	 *
	 * @return string
	 */
	function velotok_core_payment_integration_provider() {
		$contract = velotok_core_payment_integration_contract();

		return $contract['provider'];
	}
}

if ( ! function_exists( 'velotok_core_payment_integration_is_mocked' ) ) {
	/**
	 * Check whether the payment integration is mocked.
	 *
	 * @return bool
	 */
	function velotok_core_payment_integration_is_mocked() {
		$contract = velotok_core_payment_integration_contract();

		return true === $contract['mocked'];
	}
}

if ( ! function_exists( 'velotok_core_payment_integration_mode' ) ) {
	/**
	 * Return the mocked payment mode.
	 *
	 * @return string
	 */
	function velotok_core_payment_integration_mode() {
		$contract = velotok_core_payment_integration_contract();

		return $contract['default_mode'];
	}
}

if ( ! function_exists( 'velotok_core_payment_integration_env_name' ) ) {
	/**
	 * Return the future environment variable name for a payment setting.
	 *
	 * @param mixed $key Payment setting key.
	 * @return string
	 */
	function velotok_core_payment_integration_env_name( $key ) {
		$contract = velotok_core_payment_integration_contract();

		if ( ! is_string( $key ) || ! isset( $contract['env'][ $key ] ) ) {
			return '';
		}

		return $contract['env'][ $key ];
	}
}

if ( ! function_exists( 'velotok_core_payment_system' ) ) {
	/**
	 * Return the mocked payment system identifier.
	 *
	 * @return string
	 */
	function velotok_core_payment_system() {
		$contract = velotok_core_payment_integration_contract();

		return $contract['payment_system'];
	}
}

if ( ! function_exists( 'velotok_core_payment_statuses' ) ) {
	/**
	 * Return every supported payment status.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function velotok_core_payment_statuses() {
		return velotok_core_payment_integration_statuses();
	}
}

if ( ! function_exists( 'velotok_core_payment_status_is_valid' ) ) {
	/**
	 * Check whether a payment status is supported.
	 *
	 * @param mixed $status Payment status.
	 * @return bool
	 */
	function velotok_core_payment_status_is_valid( $status ) {
		return velotok_core_payment_integration_status_is_valid( $status );
	}
}

if ( ! function_exists( 'velotok_core_create_payment_intent' ) ) {
	/**
	 * Fallback payment intent helper for standalone usage.
	 *
	 * @param mixed               $order   Order data.
	 * @param array<string,mixed> $options Optional overrides.
	 * @return array<string,mixed>
	 */
	function velotok_core_create_payment_intent( $order = array(), $options = array() ) {
		return velotok_core_payment_integration_create( $order, $options );
	}
}

if ( ! function_exists( 'velotok_core_create_payment' ) ) {
	/**
	 * Fallback payment helper for standalone usage.
	 *
	 * @param mixed               $order   Order data.
	 * @param array<string,mixed> $options Optional overrides.
	 * @return array<string,mixed>
	 */
	function velotok_core_create_payment( $order = array(), $options = array() ) {
		return velotok_core_payment_integration_create( $order, $options );
	}
}

if ( ! function_exists( 'velotok_core_mock_payment' ) ) {
	/**
	 * Fallback mocked payment helper for standalone usage.
	 *
	 * @param mixed               $order   Order data.
	 * @param array<string,mixed> $options Optional overrides.
	 * @return array<string,mixed>
	 */
	function velotok_core_mock_payment( $order = array(), $options = array() ) {
		return velotok_core_payment_integration_create( $order, $options );
	}
}

if ( ! function_exists( 'velotok_core_payment_contract' ) ) {
	/**
	 * Fallback payment section accessor for standalone usage.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_core_payment_contract() {
		$contract = velotok_core_payment_integration_contract();

		return $contract['payment'];
	}
}

if ( ! function_exists( 'paymentIntegrationContract' ) ) {
	/**
	 * Short global alias for the payment integration contract.
	 *
	 * @return array<string,mixed>
	 */
	function paymentIntegrationContract() {
		return velotok_core_payment_integration_contract();
	}
}

if ( ! function_exists( 'createPaymentIntegration' ) ) {
	/**
	 * Short global alias for creating a mocked payment.
	 *
	 * @param mixed               $order   Order data.
	 * @param array<string,mixed> $options Optional overrides.
	 * @return array<string,mixed>
	 */
	function createPaymentIntegration( $order = array(), $options = array() ) {
		return velotok_core_payment_integration_create( $order, $options );
	}
}
