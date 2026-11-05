<?php
/**
 * Checkout, delivery and payment stub contract.
 *
 * This is the single access point for the checkout rules used by the shop.
 * The current iteration deliberately uses fixed in-memory values: it does not
 * read environment variables, files, the database or remote services.
 *
 * Payment through ЮKassa and delivery through Почта РФ, СДЭК or another
 * logistics provider are mocked contracts. Real provider integrations are out
 * of scope until the open questions are resolved.
 *
 * Spec: clarify-096bb945.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once __DIR__ . '/delivery-options.php';

/**
 * Return the checkout, delivery and payment contract.
 *
 * The contract documents:
 * - the spec identifier and the explicit `mocked` marker;
 * - the fixed ЮKassa payment provider description and the future env names;
 * - the fixed delivery methods: Почта РФ, СДЭК or another logistics provider;
 * - the mandatory human content acceptance by the project manager role;
 * - the open questions that block real integrations.
 *
 * @return array<string,mixed>
 */
function velotok_core_checkout_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$delivery_methods = array(
		'russian_post' => array(
			'id'                       => 'russian_post',
			'slug'                     => 'russian_post',
			'code'                     => 'russian_post',
			'label'                    => 'Почта РФ',
			'provider'                 => 'Почта России',
			'mocked'                   => true,
			'available'                => true,
			'requires_manual_agreement' => false,
		),
		'cdek'         => array(
			'id'                       => 'cdek',
			'slug'                     => 'cdek',
			'code'                     => 'cdek',
			'label'                    => 'СДЭК',
			'provider'                 => 'СДЭК',
			'mocked'                   => true,
			'available'                => true,
			'requires_manual_agreement' => false,
		),
		'other'        => array(
			'id'                       => 'other',
			'slug'                     => 'other',
			'code'                     => 'other',
			'label'                    => 'Иной логист',
			'provider'                 => 'Иной логист',
			'mocked'                   => true,
			'available'                => true,
			'requires_manual_agreement' => true,
		),
	);

	$delivery_tariffs = function_exists( 'velotok_core_delivery_tariffs' ) ? velotok_core_delivery_tariffs() : array();

	foreach ( $delivery_methods as $method_id => &$delivery_method ) {
		$delivery_method['source'] = 'stub';

		if ( isset( $delivery_tariffs[ $method_id ] ) ) {
			$delivery_method['tariff']      = $delivery_tariffs[ $method_id ];
			$delivery_method['provider_id'] = 'stub';
		}
	}
	unset( $delivery_method );

	$contract = array(
		'spec'             => 'clarify-096bb945',
		'source'           => 'stub',
		'mocked'           => true,
		'payment'          => array(
			'provider'        => 'yookassa',
			'provider_name'   => 'ЮKassa',
			'provider_label'  => 'ЮKassa',
			'source'          => 'stub',
			'mocked'          => true,
			'default_mode'    => 'test',
			'supported_modes' => array( 'test', 'prod' ),
			'capture'         => true,
			'confirmation'    => 'redirect',
			'mock_status'     => 'pending',
			'statuses'        => array( 'pending', 'waiting_for_capture', 'succeeded', 'canceled' ),
			'future_source'   => 'env',
			'env_prefix'      => 'VELOTOK_YOOKASSA_',
			'env'             => array(
				'shop_id'    => 'VELOTOK_YOOKASSA_SHOP_ID',
				'secret_key' => 'VELOTOK_YOOKASSA_SECRET_KEY',
				'mode'       => 'VELOTOK_YOOKASSA_MODE',
			),
		),
		'payment_method'   => 'yookassa',
		'payment_provider' => 'yookassa',
		'payment_provider_label' => 'ЮKassa',
		'payment_system'   => 'ЮKassa',
		'payment_system_slug' => 'yookassa',
		'delivery'         => array(
			'methods'        => $delivery_methods,
			'default'        => 'russian_post',
			'source'         => 'stub',
			'provider'       => 'stub',
			'provider_label' => 'Stub delivery provider',
			'mocked'         => true,
			'required'       => true,
			'future_source'  => 'provider_api',
			'missing_method' => 'DELIVERY_METHOD_REQUIRED',
			'invalid_method' => 'DELIVERY_METHOD_INVALID',
			'tariffs'        => $delivery_tariffs,
			'tariff_grid'    => $delivery_tariffs,
			'cost'           => array(
				'source'     => 'stub',
				'mocked'     => true,
				'amount'     => null,
				'currency'   => 'RUB',
				'calculated' => true,
				'tariffs'    => $delivery_tariffs,
			),
		),
		'delivery_methods'      => $delivery_methods,
		'delivery_method_ids'   => array_keys( $delivery_methods ),
		'delivery_options'      => array_values( $delivery_methods ),
		'delivery_default'      => 'russian_post',
		'delivery_required'     => true,
		'content_acceptance'    => array(
			'required'      => true,
			'role'          => 'project_manager',
			'role_slug'     => 'project_manager',
			'role_label'    => 'руководитель проекта',
			'required_role' => 'руководитель проекта',
			'enforced_by'   => 'human',
			'field'         => 'velotok_content_accepted',
			'error_code'    => 'CONTENT_ACCEPTANCE_REQUIRED',
			'role_error_code' => 'CONTENT_ACCEPTANCE_ROLE_FORBIDDEN',
			'source'        => 'human',
			'mocked'        => true,
		),
		'content_acceptance_required' => true,
		'content_acceptance_role'     => 'руководитель проекта',
		'open_questions'   => array(
			'yookassa_modes'   => 'Тестовый и боевой режимы ЮKassa: разделение test/prod, источник и формат учётных данных магазина, процедура подстановки секретов из env в runtime.',
			'delivery_apis'    => 'Интеграция со службами доставки: какие API Почты РФ и СДЭК подключаются, какие данные и в каком объёме передаются, как обрабатывается вариант «иной логист».',
			'delivery_cost'    => 'Стоимость доставки: источники тарифов, правила расчёта, отображение стоимости в заказе до подключения реальных интеграций.',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer a full explicit contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_checkout_payment_delivery_contract() {
	return velotok_core_checkout_contract();
}

/**
 * Short alias for the checkout contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_payment_delivery_contract() {
	return velotok_core_checkout_contract();
}

/**
 * Return the payment section of the checkout contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_payment_contract() {
	$contract = velotok_core_checkout_contract();

	return $contract['payment'];
}

/**
 * Return the delivery section of the checkout contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_delivery_contract() {
	$contract = velotok_core_checkout_contract();

	return $contract['delivery'];
}

/**
 * Return the content acceptance section of the checkout contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_content_acceptance_contract() {
	$contract = velotok_core_checkout_contract();

	return $contract['content_acceptance'];
}

/**
 * Return the open questions that block real integrations.
 *
 * @return array<string,string>
 */
function velotok_core_checkout_open_questions() {
	$contract = velotok_core_checkout_contract();

	return $contract['open_questions'];
}

/**
 * Return the fixed payment provider identifier.
 *
 * @return string
 */
function velotok_core_payment_provider() {
	$contract = velotok_core_checkout_contract();

	return $contract['payment']['provider'];
}

/**
 * Return the human-readable payment provider name.
 *
 * @return string
 */
function velotok_core_payment_provider_label() {
	$contract = velotok_core_checkout_contract();

	return $contract['payment']['provider_name'];
}

/**
 * Check whether payment processing is currently mocked.
 *
 * @return bool
 */
function velotok_core_payment_is_mocked() {
	$contract = velotok_core_checkout_contract();

	return true === $contract['payment']['mocked'];
}

/**
 * Return the payment mode used by the stub.
 *
 * The stub always reports the test mode and never reads environment variables.
 *
 * @return string
 */
function velotok_core_payment_mode() {
	$contract = velotok_core_checkout_contract();

	return $contract['payment']['default_mode'];
}

/**
 * Return the future environment variable name for a payment setting.
 *
 * The stub only computes the name; it never reads environment variables.
 *
 * @param mixed $key Payment setting key.
 * @return string Empty string for unknown keys.
 */
function velotok_core_payment_env_name( $key ) {
	if ( ! is_string( $key ) ) {
		return '';
	}

	$contract = velotok_core_payment_contract();

	if ( ! isset( $contract['env'][ $key ] ) ) {
		return '';
	}

	return $contract['env'][ $key ];
}

/**
 * Return every delivery method declared by the contract.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_delivery_methods() {
	$contract = velotok_core_checkout_contract();

	return $contract['delivery']['methods'];
}

/**
 * Return delivery method options as a list.
 *
 * @return array<int,array<string,mixed>>
 */
function velotok_core_delivery_method_options() {
	return array_values( velotok_core_delivery_methods() );
}

/**
 * Alias for callers that expect a checkout-prefixed helper.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_checkout_delivery_methods() {
	return velotok_core_delivery_methods();
}

/**
 * Return delivery method labels keyed by identifier.
 *
 * @return array<string,string>
 */
function velotok_core_delivery_method_labels() {
	$labels = array();

	foreach ( velotok_core_delivery_methods() as $id => $method ) {
		$labels[ $id ] = $method['label'];
	}

	return $labels;
}

/**
 * Normalize a contract key for case-insensitive comparisons.
 *
 * @param mixed $value Raw key.
 * @return string Normalized key or an empty string.
 */
function velotok_core_checkout_normalize_key( $value ) {
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
 * Resolve a delivery method by identifier, label or a small set of aliases.
 *
 * @param mixed $method Delivery method identifier.
 * @return array<string,mixed>|null Null for an unknown method.
 */
function velotok_core_delivery_method( $method ) {
	if ( ! is_string( $method ) ) {
		return null;
	}

	$key = velotok_core_checkout_normalize_key( $method );

	if ( '' === $key ) {
		return null;
	}

	$aliases = array(
		'russian-post'    => 'russian_post',
		'russian post'    => 'russian_post',
		'russianpost'     => 'russian_post',
		'russian_post'    => 'russian_post',
		'post'            => 'russian_post',
		'pochta'          => 'russian_post',
		'pochta_rf'       => 'russian_post',
		'pochta rf'       => 'russian_post',
		'rf_post'         => 'russian_post',
		'rf post'         => 'russian_post',
		'почта рф'        => 'russian_post',
		'почта россии'    => 'russian_post',
		'почта'           => 'russian_post',
		'cdek'            => 'cdek',
		'sdek'            => 'cdek',
		'сдэк'            => 'cdek',
		'other'           => 'other',
		'other_logistic'  => 'other',
		'other_logist'    => 'other',
		'other_logistics' => 'other',
		'other logist'    => 'other',
		'иной логист'     => 'other',
		'иной'            => 'other',
	);

	if ( isset( $aliases[ $key ] ) ) {
		$key = $aliases[ $key ];
	}

	$methods = velotok_core_delivery_methods();

	if ( isset( $methods[ $key ] ) ) {
		return $methods[ $key ];
	}

	foreach ( $methods as $method_contract ) {
		foreach ( array( 'id', 'slug', 'code', 'label', 'provider' ) as $field ) {
			if ( ! isset( $method_contract[ $field ] ) ) {
				continue;
			}

			if ( velotok_core_checkout_normalize_key( $method_contract[ $field ] ) === $key ) {
				return $method_contract;
			}
		}
	}

	return null;
}

/**
 * Check whether a delivery method is declared by the contract.
 *
 * @param mixed $method Delivery method identifier.
 * @return bool
 */
function velotok_core_delivery_method_is_valid( $method ) {
	return null !== velotok_core_delivery_method( $method );
}

/**
 * Validate the selected delivery method.
 *
 * @param mixed $method   Delivery method identifier.
 * @param bool  $required Whether an empty selection is an error.
 * @return array<string,mixed>
 */
function velotok_core_validate_delivery_method( $method, $required = true ) {
	$contract = velotok_core_delivery_contract();
	$value    = is_string( $method ) ? trim( $method ) : '';

	if ( '' === $value ) {
		$code = $required ? $contract['missing_method'] : null;

		return array(
			'valid'   => ! $required,
			'code'    => $code,
			'error'   => $code,
			'method'  => '',
			'mocked'  => true,
			'contract' => null,
		);
	}

	$resolved = velotok_core_delivery_method( $value );

	if ( null === $resolved ) {
		return array(
			'valid'   => false,
			'code'    => $contract['invalid_method'],
			'error'   => $contract['invalid_method'],
			'method'  => $value,
			'mocked'  => true,
			'contract' => null,
		);
	}

	return array(
		'valid'   => true,
		'code'    => null,
		'error'   => null,
		'method'  => $resolved['id'],
		'mocked'  => true,
		'contract' => $resolved,
	);
}

/**
 * Return a mocked delivery cost for a method.
 *
 * Without order data the legacy checkout stub keeps returning a zero amount.
 * When order data is provided, the dedicated delivery-options stub applies its
 * tariff grid (spec clarify-a8fe76a0) and returns the calculated amount.
 *
 * @param mixed               $method Delivery method identifier.
 * @param array<string,mixed> $order  Optional order data.
 * @return array<string,mixed>
 */
function velotok_core_delivery_cost( $method, $order = array() ) {
	$contract = velotok_core_delivery_contract();
	$resolved = velotok_core_delivery_method( $method );
	$has_order = is_object( $order ) || is_numeric( $order ) || ( is_array( $order ) && ! empty( $order ) );

	if ( null !== $resolved && $has_order && function_exists( 'velotok_core_calculate_delivery_cost' ) ) {
		$calculated = velotok_core_calculate_delivery_cost( $method, $order );

		if ( ! empty( $calculated['valid'] ) ) {
			$calculated['source'] = 'stub';

			return $calculated;
		}
	}

	return array(
		'valid'    => null !== $resolved,
		'code'     => null === $resolved ? $contract['invalid_method'] : null,
		'error'    => null === $resolved ? $contract['invalid_method'] : null,
		'method'   => null === $resolved ? '' : $resolved['id'],
		'amount'   => null === $resolved ? null : 0.0,
		'currency' => $contract['cost']['currency'],
		'source'   => $contract['cost']['source'],
		'mocked'   => true,
		'order'    => is_array( $order ) ? $order : array(),
	);
}

/**
 * Read an order total from an array or an object without external calls.
 *
 * @param mixed $order Order data.
 * @return float
 */
function velotok_core_payment_order_amount( $order ) {
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
 * Read an order id from an array or an object without external calls.
 *
 * @param mixed $order Order data.
 * @return string
 */
function velotok_core_payment_order_id( $order ) {
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
 * Build a mocked ЮKassa payment intent for an order.
 *
 * No network request is performed. The result is explicitly marked as mocked
 * and uses an internal mock confirmation URL.
 *
 * @param mixed               $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_create_payment_intent( $order = array(), $options = array() ) {
	$contract = velotok_core_checkout_contract();
	$order_id = velotok_core_payment_order_id( $order );
	$amount   = velotok_core_payment_order_amount( $order );
	$token    = '' === $order_id ? 'order' : (string) preg_replace( '/[^a-z0-9_-]+/i', '-', $order_id );

	if ( ! is_array( $options ) ) {
		$options = array();
	}

	return array(
		'id'               => 'mock-yookassa-' . $token,
		'provider'         => $contract['payment']['provider'],
		'provider_name'    => $contract['payment']['provider_name'],
		'source'           => 'stub',
		'mocked'           => true,
		'mode'             => velotok_core_payment_mode(),
		'order_id'         => $order_id,
		'status'           => $contract['payment']['mock_status'],
		'paid'             => false,
		'capture'          => true === $contract['payment']['capture'],
		'amount'           => array(
			'value'    => $amount,
			'currency' => 'RUB',
		),
		'amount_value'     => $amount,
		'currency'         => 'RUB',
		'confirmation'     => array(
			'type' => 'mock_redirect',
			'url'  => 'mock://yookassa/' . $token,
		),
		'confirmation_url' => 'mock://yookassa/' . $token,
		'options'          => $options,
	);
}

/**
 * Alias for callers that use a shorter payment name.
 *
 * @param mixed               $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_create_payment( $order = array(), $options = array() ) {
	return velotok_core_create_payment_intent( $order, $options );
}

/**
 * Alias for callers that expect an explicit mocked payment helper.
 *
 * @param mixed               $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_mock_payment( $order = array(), $options = array() ) {
	return velotok_core_create_payment_intent( $order, $options );
}

/**
 * Check whether content acceptance is required.
 *
 * @return bool
 */
function velotok_core_content_acceptance_required() {
	$contract = velotok_core_content_acceptance_contract();

	return true === $contract['required'];
}

/**
 * Normalize a content acceptance value.
 *
 * Accepts a boolean, a scalar flag or an array with an `accepted` flag.
 *
 * @param mixed $acceptance Acceptance value.
 * @return bool
 */
function velotok_core_content_accepted( $acceptance ) {
	if ( is_bool( $acceptance ) ) {
		return $acceptance;
	}

	if ( is_array( $acceptance ) ) {
		foreach ( array( 'accepted', 'content_accepted', 'is_accepted' ) as $key ) {
			if ( array_key_exists( $key, $acceptance ) ) {
				return velotok_core_content_accepted( $acceptance[ $key ] );
			}
		}

		if ( isset( $acceptance['status'] ) && is_string( $acceptance['status'] ) ) {
			return 'accepted' === strtolower( trim( $acceptance['status'] ) );
		}

		return false;
	}

	if ( is_int( $acceptance ) || is_float( $acceptance ) ) {
		return 1 === (int) $acceptance;
	}

	if ( is_string( $acceptance ) ) {
		$value = strtolower( trim( $acceptance ) );

		return in_array( $value, array( '1', 'true', 'yes', 'on', 'accepted' ), true );
	}

	return false;
}

/**
 * Check whether a role is allowed to accept content.
 *
 * Accepts both the machine slug and the human label of the project manager.
 *
 * @param mixed $role Role identifier or label.
 * @return bool
 */
function velotok_core_content_acceptance_role_is_valid( $role ) {
	$contract = velotok_core_content_acceptance_contract();
	$key      = velotok_core_checkout_normalize_key( $role );

	if ( '' === $key ) {
		return false;
	}

	$allowed = array(
		$contract['role'],
		$contract['role_slug'],
		$contract['role_label'],
		$contract['required_role'],
	);

	foreach ( $allowed as $candidate ) {
		if ( velotok_core_checkout_normalize_key( $candidate ) === $key ) {
			return true;
		}
	}

	return false;
}

/**
 * Validate content acceptance by a human project manager.
 *
 * @param mixed $acceptance Acceptance value.
 * @return array<string,mixed>
 */
function velotok_core_validate_content_acceptance( $acceptance ) {
	$contract      = velotok_core_content_acceptance_contract();
	$role_provided = is_array( $acceptance )
		&& isset( $acceptance['role'] )
		&& is_scalar( $acceptance['role'] )
		&& '' !== trim( (string) $acceptance['role'] );
	$role          = $role_provided ? (string) $acceptance['role'] : $contract['role'];
	$role_valid    = ! $role_provided || velotok_core_content_acceptance_role_is_valid( $role );
	$accepted      = velotok_core_content_accepted( $acceptance ) && $role_valid;
	$code          = $accepted
		? null
		: ( $role_provided && ! $role_valid ? $contract['role_error_code'] : $contract['error_code'] );

	return array(
		'valid'       => $accepted,
		'required'    => true === $contract['required'],
		'accepted'    => $accepted,
		'code'        => $code,
		'error'       => $code,
		'role'        => $role,
		'role_label'  => $contract['role_label'],
		'role_valid'  => $role_valid,
		'enforced_by' => $contract['enforced_by'],
		'mocked'      => true,
	);
}

/**
 * Check whether a payment provider refers to the supported ЮKassa provider.
 *
 * Accepts the machine slug and the human-readable label.
 *
 * @param mixed $provider Provider identifier or label.
 * @return bool
 */
function velotok_core_payment_provider_is_supported( $provider ) {
	$contract = velotok_core_payment_contract();
	$key      = velotok_core_checkout_normalize_key( $provider );

	if ( '' === $key ) {
		return false;
	}

	$allowed = array(
		$contract['provider'],
		$contract['provider_name'],
		$contract['provider_label'],
		'yoo-kassa',
		'yoo_kassa',
		'юkassa',
		'юкасса',
		'ю-касса',
		'ю касса',
	);

	foreach ( $allowed as $candidate ) {
		if ( velotok_core_checkout_normalize_key( $candidate ) === $key ) {
			return true;
		}
	}

	return false;
}

/**
 * Validate the whole mocked checkout contract.
 *
 * @param array<string,mixed> $data Checkout data.
 * @return array<string,mixed>
 */
function velotok_core_validate_checkout( $data = array() ) {
	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$method = '';

	foreach ( array( 'velotok_delivery_method', 'delivery_method', 'shipping_method', 'delivery' ) as $key ) {
		if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) && '' !== trim( (string) $data[ $key ] ) ) {
			$method = (string) $data[ $key ];
			break;
		}
	}

	$acceptance = false;

	foreach ( array( 'velotok_content_accepted', 'content_accepted', 'accepted', 'content_acceptance' ) as $key ) {
		if ( array_key_exists( $key, $data ) ) {
			$acceptance = $data[ $key ];
			break;
		}
	}

	$payment = velotok_core_payment_contract();
	$provider = isset( $data['payment_provider'] ) && is_scalar( $data['payment_provider'] )
		? (string) $data['payment_provider']
		: $payment['provider'];

	$delivery_result = velotok_core_validate_delivery_method( $method );
	$accept_result   = velotok_core_validate_content_acceptance( $acceptance );
	$errors          = array();

	if ( ! velotok_core_payment_provider_is_supported( $provider ) ) {
		$errors[] = 'PAYMENT_PROVIDER_UNSUPPORTED';
	}

	if ( ! $delivery_result['valid'] ) {
		$errors[] = $delivery_result['code'];
	}

	if ( ! $accept_result['valid'] ) {
		$errors[] = $accept_result['code'];
	}

	return array(
		'valid'             => array() === $errors,
		'spec'              => 'clarify-096bb945',
		'mocked'            => true,
		'payment_provider'  => $payment['provider'],
		'payment'           => array(
			'provider' => $payment['provider'],
			'mocked'   => true,
			'mode'     => $payment['default_mode'],
		),
		'delivery_method'   => $delivery_result['method'],
		'delivery'          => $delivery_result,
		'content_acceptance' => $accept_result,
		'errors'            => array_values( $errors ),
		'error'             => $errors ? $errors[0] : null,
	);
}
