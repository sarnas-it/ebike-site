<?php
/**
 * Delivery options stub contract.
 *
 * This is the single access point for the delivery options shown at checkout.
 * The current iteration deliberately uses a deterministic in-memory stub: it
 * does not read environment variables, files, the database or remote services.
 *
 * The stub supplies the delivery method list, the provider tariff grid and the
 * order persistence shape. A real logistics provider integration is out of
 * scope until the open questions are resolved.
 *
 * Spec: clarify-a8fe76a0.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

/**
 * Return the mocked delivery methods keyed by identifier.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_delivery_options_methods_data() {
	static $methods = null;

	if ( null !== $methods ) {
		return $methods;
	}

	$methods = array(
		'russian_post' => array(
			'id'                        => 'russian_post',
			'slug'                      => 'russian_post',
			'code'                      => 'russian_post',
			'label'                     => 'Почта РФ',
			'provider'                  => 'Почта России',
			'source'                    => 'stub',
			'mocked'                    => true,
			'available'                 => true,
			'requires_manual_agreement' => false,
		),
		'cdek'         => array(
			'id'                        => 'cdek',
			'slug'                      => 'cdek',
			'code'                      => 'cdek',
			'label'                     => 'СДЭК',
			'provider'                  => 'СДЭК',
			'source'                    => 'stub',
			'mocked'                    => true,
			'available'                 => true,
			'requires_manual_agreement' => false,
		),
		'other'        => array(
			'id'                        => 'other',
			'slug'                      => 'other',
			'code'                      => 'other',
			'label'                     => 'Иной логист',
			'provider'                  => 'Иной логист',
			'source'                    => 'stub',
			'mocked'                    => true,
			'available'                 => true,
			'requires_manual_agreement' => true,
		),
	);

	return $methods;
}

/**
 * Return the mocked provider tariff grid keyed by method identifier.
 *
 * Each method has a set of amount tiers selected by the order total. The grid
 * is deterministic, in-memory and marked as mocked.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_delivery_options_tariffs_data() {
	static $tariffs = null;

	if ( null !== $tariffs ) {
		return $tariffs;
	}

	$tariffs = array(
		'russian_post' => array(
			'id'                      => 'russian_post',
			'method'                  => 'russian_post',
			'label'                   => 'Почта РФ',
			'provider'                => 'stub',
			'source'                  => 'stub',
			'mocked'                  => true,
			'currency'                => 'RUB',
			'base_amount'             => 350.0,
			'amount'                  => 350.0,
			'free_from'               => 50000.0,
			'free_shipping_threshold' => 50000.0,
			'tiers'                   => array(
				array( 'min_total' => 0.0, 'max_total' => 19999.99, 'amount' => 350.0 ),
				array( 'min_total' => 20000.0, 'max_total' => 49999.99, 'amount' => 250.0 ),
				array( 'min_total' => 50000.0, 'max_total' => null, 'amount' => 0.0 ),
			),
		),
		'cdek'         => array(
			'id'                      => 'cdek',
			'method'                  => 'cdek',
			'label'                   => 'СДЭК',
			'provider'                => 'stub',
			'source'                  => 'stub',
			'mocked'                  => true,
			'currency'                => 'RUB',
			'base_amount'             => 490.0,
			'amount'                  => 490.0,
			'free_from'               => 50000.0,
			'free_shipping_threshold' => 50000.0,
			'tiers'                   => array(
				array( 'min_total' => 0.0, 'max_total' => 19999.99, 'amount' => 490.0 ),
				array( 'min_total' => 20000.0, 'max_total' => 49999.99, 'amount' => 390.0 ),
				array( 'min_total' => 50000.0, 'max_total' => null, 'amount' => 0.0 ),
			),
		),
		'other'        => array(
			'id'                      => 'other',
			'method'                  => 'other',
			'label'                   => 'Иной логист',
			'provider'                => 'stub',
			'source'                  => 'stub',
			'mocked'                  => true,
			'currency'                => 'RUB',
			'base_amount'             => 790.0,
			'amount'                  => 790.0,
			'free_from'               => null,
			'free_shipping_threshold' => null,
			'tiers'                   => array(
				array( 'min_total' => 0.0, 'max_total' => 19999.99, 'amount' => 790.0 ),
				array( 'min_total' => 20000.0, 'max_total' => 49999.99, 'amount' => 690.0 ),
				array( 'min_total' => 50000.0, 'max_total' => null, 'amount' => 590.0 ),
			),
		),
	);

	return $tariffs;
}

/**
 * Return the delivery options contract.
 *
 * The contract documents:
 * - the spec identifier and the explicit `mocked` marker;
 * - the delivery methods supplied by the stub provider;
 * - the provider tariff grid used for delivery cost calculation;
 * - the selected method and cost persistence shape for an order;
 * - the open questions that block a real integration.
 *
 * @return array<string,mixed>
 */
function velotok_core_delivery_options_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$methods  = velotok_core_delivery_options_methods_data();
	$tariffs  = velotok_core_delivery_options_tariffs_data();

	$contract = array(
		'spec'                  => 'clarify-a8fe76a0',
		'parent_spec'           => 'clarify-096bb945',
		'source'                => 'stub',
		'mocked'                => true,
		'provider'              => 'stub',
		'provider_label'        => 'Stub delivery provider',
		'network'               => false,
		'persistence'           => false,
		'methods'               => $methods,
		'delivery_methods'      => $methods,
		'method_ids'            => array_keys( $methods ),
		'options'               => array_values( $methods ),
		'delivery_options'      => array_values( $methods ),
		'default'               => 'russian_post',
		'default_method'        => 'russian_post',
		'required'              => true,
		'currency'              => 'RUB',
		'tariffs'               => $tariffs,
		'tariff_grid'           => $tariffs,
		'delivery_tariffs'      => $tariffs,
		'free_shipping_threshold' => 50000.0,
		'cost'                  => array(
			'source'     => 'stub',
			'provider'   => 'stub',
			'mocked'     => true,
			'amount'     => null,
			'currency'   => 'RUB',
			'calculated' => true,
			'grid'       => 'tariff_grid',
		),
		'order'                 => array(
			'source'    => 'stub',
			'mocked'    => true,
			'persisted' => false,
			'fields'    => array(
				'delivery_method'       => 'string',
				'delivery_method_label' => 'string',
				'delivery_cost'         => 'float',
				'delivery_currency'     => 'string',
			),
			'aliases'   => array(
				'delivery_method'       => array( 'shipping_method', 'delivery_method_id' ),
				'delivery_method_label' => array( 'shipping_method_label' ),
				'delivery_cost'         => array( 'shipping_cost', 'delivery_cost_amount' ),
				'delivery_currency'     => array( 'shipping_currency' ),
			),
		),
		'future_source'         => 'provider_api',
		'missing_method'        => 'DELIVERY_METHOD_REQUIRED',
		'invalid_method'        => 'DELIVERY_METHOD_INVALID',
		'open_questions'        => array(
			'providers'    => 'Каков финальный перечень реальных служб доставки, которые должны поддерживаться?',
			'tariffs'      => 'Каковы правила реальной тарификации доставки и пороги бесплатной доставки?',
			'integration'  => 'Какие требования предъявляются к реальной интеграции со службами доставки (создание отправления, трекинг, обработка ошибок) и какой формат данных передаётся в службу?',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer an explicit stub name.
 *
 * @return array<string,mixed>
 */
function velotok_core_delivery_options_stub_contract() {
	return velotok_core_delivery_options_contract();
}

/**
 * Alias kept for callers that prefer a delivery-prefixed contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_delivery_options_contract_data() {
	return velotok_core_delivery_options_contract();
}

/**
 * Return the open questions that block a real delivery integration.
 *
 * @return array<string,string>
 */
function velotok_core_delivery_options_open_questions() {
	$contract = velotok_core_delivery_options_contract();

	return $contract['open_questions'];
}

/**
 * Normalize a delivery method key for case-insensitive matching.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function velotok_core_delivery_options_normalize( $value ) {
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
 * Resolve a delivery method from the stub method list.
 *
 * @param mixed $method Identifier, slug, code, label or alias.
 * @return array<string,mixed>|null
 */
function velotok_core_delivery_options_method( $method ) {
	$key = velotok_core_delivery_options_normalize( $method );

	if ( '' === $key ) {
		return null;
	}

	$aliases = array(
		'russian-post' => 'russian_post',
		'russian post' => 'russian_post',
		'russianpost'  => 'russian_post',
		'post'         => 'russian_post',
		'pochta'       => 'russian_post',
		'почта рф'     => 'russian_post',
		'почта россии' => 'russian_post',
		'почта'        => 'russian_post',
		'sdek'         => 'cdek',
		'сдэк'         => 'cdek',
		'иной логист'  => 'other',
		'иной'         => 'other',
	);

	if ( isset( $aliases[ $key ] ) ) {
		$key = $aliases[ $key ];
	}

	$methods = velotok_core_delivery_options_methods_data();

	if ( isset( $methods[ $key ] ) ) {
		return $methods[ $key ];
	}

	foreach ( $methods as $definition ) {
		foreach ( array( 'id', 'slug', 'code', 'label', 'provider' ) as $field ) {
			if ( isset( $definition[ $field ] ) && velotok_core_delivery_options_normalize( $definition[ $field ] ) === $key ) {
				return $definition;
			}
		}
	}

	return null;
}

/**
 * Return the delivery options supplied by the stub provider.
 *
 * @param array<string,mixed> $order Optional order data used for cost calculation.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_delivery_options( $order = array() ) {
	$options = array();

	foreach ( velotok_core_delivery_options_methods_data() as $method ) {
		$cost      = velotok_core_delivery_options_cost( $method['id'], $order );
		$method['tariff']         = velotok_core_delivery_options_tariff( $method['id'] );
		$method['cost']           = $cost['amount'];
		$method['cost_currency']  = $cost['currency'];
		$method['cost_calculated'] = true;
		$method['cost_mocked']    = true;
		$options[]                = $method;
	}

	return $options;
}

/**
 * Alias for callers that use a getter-style name.
 *
 * @param array<string,mixed> $order Optional order data.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_get_delivery_options( $order = array() ) {
	return velotok_core_delivery_options( $order );
}

/**
 * Alias for callers that expect checkout-prefixed helpers.
 *
 * @param array<string,mixed> $order Optional order data.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_checkout_delivery_options( $order = array() ) {
	return velotok_core_delivery_options( $order );
}

/**
 * Return one delivery option from the stub provider.
 *
 * @param mixed $method Delivery method identifier.
 * @return array<string,mixed>|null
 */
function velotok_core_delivery_option( $method ) {
	return velotok_core_delivery_options_method( $method );
}

/**
 * Return the stub tariff grid.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_delivery_options_tariffs() {
	return velotok_core_delivery_options_tariffs_data();
}

/**
 * Alias for callers that use an explicit grid name.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_delivery_options_tariff_grid() {
	return velotok_core_delivery_options_tariffs_data();
}

/**
 * Return the stub tariff for a delivery method.
 *
 * @param mixed $method Delivery method identifier.
 * @return array<string,mixed>|null
 */
function velotok_core_delivery_options_tariff( $method ) {
	$resolved = velotok_core_delivery_options_method( $method );

	if ( null === $resolved ) {
		return null;
	}

	$tariffs = velotok_core_delivery_options_tariffs_data();

	return isset( $tariffs[ $resolved['id'] ] ) ? $tariffs[ $resolved['id'] ] : null;
}

/**
 * Read an order amount without external calls.
 *
 * @param mixed $order Order data.
 * @return float
 */
function velotok_core_delivery_options_order_amount( $order ) {
	$amount = 0.0;

	if ( is_array( $order ) ) {
		foreach ( array( 'total', 'order_total', 'amount', 'price', 'subtotal' ) as $key ) {
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
 * Select the tariff tier for an order amount.
 *
 * @param array<string,mixed> $tariff Tariff definition.
 * @param float               $amount Order amount.
 * @return array<string,mixed>|null
 */
function velotok_core_delivery_options_tier( $tariff, $amount ) {
	if ( ! is_array( $tariff ) || empty( $tariff['tiers'] ) || ! is_array( $tariff['tiers'] ) ) {
		return null;
	}

	$amount = is_numeric( $amount ) ? (float) $amount : 0.0;

	foreach ( $tariff['tiers'] as $tier ) {
		if ( ! is_array( $tier ) || ! isset( $tier['amount'] ) ) {
			continue;
		}

		$min = isset( $tier['min_total'] ) && is_numeric( $tier['min_total'] ) ? (float) $tier['min_total'] : 0.0;
		$max = isset( $tier['max_total'] ) && is_numeric( $tier['max_total'] ) ? (float) $tier['max_total'] : null;

		if ( $amount >= $min && ( null === $max || $amount <= $max ) ) {
			return $tier;
		}
	}

	return null;
}

/**
 * Calculate the mocked delivery cost using the stub tariff grid.
 *
 * @param mixed               $method Delivery method identifier.
 * @param array<string,mixed> $order  Optional order data.
 * @return array<string,mixed>
 */
function velotok_core_delivery_options_cost( $method, $order = array() ) {
	$contract = velotok_core_delivery_options_contract();
	$resolved = velotok_core_delivery_options_method( $method );
	$tariff   = null === $resolved ? null : velotok_core_delivery_options_tariff( $resolved['id'] );
	$total    = velotok_core_delivery_options_order_amount( $order );
	$tier     = null === $tariff ? null : velotok_core_delivery_options_tier( $tariff, $total );
	$amount   = null;

	if ( null !== $resolved && is_array( $tariff ) ) {
		if ( null !== $tier && isset( $tier['amount'] ) && is_numeric( $tier['amount'] ) ) {
			$amount = round( (float) $tier['amount'], 2 );
		} elseif ( isset( $tariff['amount'] ) && is_numeric( $tariff['amount'] ) ) {
			$amount = round( (float) $tariff['amount'], 2 );
		}
	}

	return array(
		'valid'       => null !== $resolved,
		'code'        => null === $resolved ? $contract['invalid_method'] : null,
		'error'       => null === $resolved ? $contract['invalid_method'] : null,
		'method'      => null === $resolved ? '' : $resolved['id'],
		'label'       => null === $resolved ? '' : $resolved['label'],
		'amount'      => $amount,
		'cost'        => $amount,
		'currency'    => $contract['currency'],
		'source'      => 'stub',
		'provider'    => 'stub',
		'mocked'      => true,
		'calculated'  => true,
		'free'        => null !== $amount && 0.0 === (float) $amount,
		'order_total' => $total,
		'tariff'      => $tariff,
		'tier'        => $tier,
		'order'       => is_array( $order ) ? $order : array(),
	);
}

/**
 * Apply the selected delivery method and cost to order data.
 *
 * The stub returns an order-shaped array and does not persist it.
 *
 * @param mixed $order  Order data.
 * @param mixed $method Delivery method identifier.
 * @return array<string,mixed>
 */
function velotok_core_delivery_options_apply_to_order( $order, $method ) {
	if ( is_array( $method ) && ! is_array( $order ) ) {
		$swap   = $order;
		$order  = $method;
		$method = $swap;
	}

	$order = is_array( $order ) ? $order : array();
	$cost  = velotok_core_delivery_options_cost( $method, $order );
	$label = '' !== $cost['method'] && null !== velotok_core_delivery_options_method( $cost['method'] )
		? velotok_core_delivery_options_method( $cost['method'] )['label']
		: '';

	$order['delivery_method']        = $cost['method'];
	$order['delivery_method_id']     = $cost['method'];
	$order['delivery_method_label']  = $label;
	$order['shipping_method']        = $cost['method'];
	$order['shipping_method_label']  = $label;
	$order['delivery_cost']          = $cost['amount'];
	$order['delivery_cost_amount']   = $cost['amount'];
	$order['shipping_cost']          = $cost['amount'];
	$order['delivery_currency']      = $cost['currency'];
	$order['shipping_currency']      = $cost['currency'];
	$order['delivery']               = array(
		'method'   => $cost['method'],
		'label'    => $label,
		'cost'     => $cost['amount'],
		'currency' => $cost['currency'],
		'mocked'   => true,
		'source'   => 'stub',
	);
	$order['delivery_options_saved'] = true;
	$order['delivery_mocked']        = true;

	return $order;
}

/**
 * Save the selected method and cost in an order (mocked).
 *
 * @param mixed $order  Order data.
 * @param mixed $method Delivery method identifier.
 * @return array<string,mixed>
 */
function velotok_core_delivery_options_save( $order, $method ) {
	return velotok_core_delivery_options_apply_to_order( $order, $method );
}

/**
 * Alias for callers that use a save-selection name.
 *
 * @param mixed $order  Order data.
 * @param mixed $method Delivery method identifier.
 * @return array<string,mixed>
 */
function velotok_core_save_delivery_options( $order, $method ) {
	return velotok_core_delivery_options_apply_to_order( $order, $method );
}

if ( ! function_exists( 'velotok_core_delivery_methods' ) ) {
	/**
	 * Return delivery methods from the standalone delivery options stub.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function velotok_core_delivery_methods() {
		return velotok_core_delivery_options_methods_data();
	}
}

if ( ! function_exists( 'velotok_core_delivery_method_labels' ) ) {
	/**
	 * Return delivery method labels keyed by identifier.
	 *
	 * @return array<string,string>
	 */
	function velotok_core_delivery_method_labels() {
		$labels = array();

		foreach ( velotok_core_delivery_options_methods_data() as $id => $method ) {
			$labels[ $id ] = $method['label'];
		}

		return $labels;
	}
}

if ( ! function_exists( 'velotok_core_delivery_method' ) ) {
	/**
	 * Resolve a delivery method from the standalone stub.
	 *
	 * @param mixed $method Delivery method identifier.
	 * @return array<string,mixed>|null
	 */
	function velotok_core_delivery_method( $method ) {
		return velotok_core_delivery_options_method( $method );
	}
}

if ( ! function_exists( 'velotok_core_delivery_method_is_valid' ) ) {
	/**
	 * Check whether a delivery method is declared by the standalone stub.
	 *
	 * @param mixed $method Delivery method identifier.
	 * @return bool
	 */
	function velotok_core_delivery_method_is_valid( $method ) {
		return null !== velotok_core_delivery_options_method( $method );
	}
}

if ( ! function_exists( 'velotok_core_validate_delivery_method' ) ) {
	/**
	 * Validate a delivery method against the standalone stub.
	 *
	 * @param mixed $method   Delivery method identifier.
	 * @param bool  $required Whether an empty selection is an error.
	 * @return array<string,mixed>
	 */
	function velotok_core_validate_delivery_method( $method, $required = true ) {
		$contract = velotok_core_delivery_options_contract();
		$value    = is_string( $method ) ? trim( $method ) : '';

		if ( '' === $value ) {
			$code = $required ? $contract['missing_method'] : null;

			return array(
				'valid'    => ! $required,
				'code'     => $code,
				'error'    => $code,
				'method'   => '',
				'mocked'   => true,
				'contract' => null,
			);
		}

		$resolved = velotok_core_delivery_options_method( $value );

		if ( null === $resolved ) {
			return array(
				'valid'    => false,
				'code'     => $contract['invalid_method'],
				'error'    => $contract['invalid_method'],
				'method'   => $value,
				'mocked'   => true,
				'contract' => null,
			);
		}

		return array(
			'valid'    => true,
			'code'     => null,
			'error'    => null,
			'method'   => $resolved['id'],
			'mocked'   => true,
			'contract' => $resolved,
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_tariffs' ) ) {
	/**
	 * Return the tariff grid from the standalone delivery options stub.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function velotok_core_delivery_tariffs() {
		return velotok_core_delivery_options_tariffs_data();
	}
}

if ( ! function_exists( 'velotok_core_delivery_tariff_grid' ) ) {
	/**
	 * Alias for callers that use an explicit grid name.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function velotok_core_delivery_tariff_grid() {
		return velotok_core_delivery_options_tariffs_data();
	}
}

if ( ! function_exists( 'velotok_core_get_delivery_tariffs' ) ) {
	/**
	 * Alias for callers that use a getter-style name.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function velotok_core_get_delivery_tariffs() {
		return velotok_core_delivery_options_tariffs_data();
	}
}

if ( ! function_exists( 'velotok_core_delivery_tariff' ) ) {
	/**
	 * Return a tariff definition from the standalone stub.
	 *
	 * @param mixed $method Delivery method identifier.
	 * @return array<string,mixed>|null
	 */
	function velotok_core_delivery_tariff( $method ) {
		return velotok_core_delivery_options_tariff( $method );
	}
}

if ( ! function_exists( 'velotok_core_delivery_tariff_tier' ) ) {
	/**
	 * Select a tariff tier for an order amount.
	 *
	 * @param array<string,mixed> $tariff Tariff definition.
	 * @param float               $amount Order amount.
	 * @return array<string,mixed>|null
	 */
	function velotok_core_delivery_tariff_tier( $tariff, $amount ) {
		return velotok_core_delivery_options_tier( $tariff, $amount );
	}
}

if ( ! function_exists( 'velotok_core_delivery_cost' ) ) {
	/**
	 * Calculate delivery cost using the standalone stub tariff grid.
	 *
	 * @param mixed               $method Delivery method identifier.
	 * @param array<string,mixed> $order  Optional order data.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_cost( $method, $order = array() ) {
		return velotok_core_delivery_options_cost( $method, $order );
	}
}

if ( ! function_exists( 'velotok_core_calculate_delivery_cost' ) ) {
	/**
	 * Alias for delivery cost calculation.
	 *
	 * @param mixed               $method Delivery method identifier.
	 * @param array<string,mixed> $order  Optional order data.
	 * @return array<string,mixed>
	 */
	function velotok_core_calculate_delivery_cost( $method, $order = array() ) {
		return velotok_core_delivery_options_cost( $method, $order );
	}
}

if ( ! function_exists( 'velotok_core_calculate_delivery' ) ) {
	/**
	 * Short alias for delivery cost calculation.
	 *
	 * @param mixed               $method Delivery method identifier.
	 * @param array<string,mixed> $order  Optional order data.
	 * @return array<string,mixed>
	 */
	function velotok_core_calculate_delivery( $method, $order = array() ) {
		return velotok_core_delivery_options_cost( $method, $order );
	}
}

if ( ! function_exists( 'velotok_core_delivery_apply_to_order' ) ) {
	/**
	 * Apply the selected delivery method and cost to order data.
	 *
	 * @param mixed $order  Order data.
	 * @param mixed $method Delivery method identifier.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_apply_to_order( $order, $method ) {
		return velotok_core_delivery_options_apply_to_order( $order, $method );
	}
}

if ( ! function_exists( 'velotok_core_save_delivery_option' ) ) {
	/**
	 * Save the selected delivery method and cost in an order.
	 *
	 * @param mixed $order  Order data.
	 * @param mixed $method Delivery method identifier.
	 * @return array<string,mixed>
	 */
	function velotok_core_save_delivery_option( $order, $method ) {
		return velotok_core_delivery_options_apply_to_order( $order, $method );
	}
}

if ( ! function_exists( 'velotok_core_save_delivery_selection' ) ) {
	/**
	 * Alias for saving a delivery selection.
	 *
	 * @param mixed $order  Order data.
	 * @param mixed $method Delivery method identifier.
	 * @return array<string,mixed>
	 */
	function velotok_core_save_delivery_selection( $order, $method ) {
		return velotok_core_delivery_options_apply_to_order( $order, $method );
	}
}

if ( ! function_exists( 'velotok_core_order_delivery' ) ) {
	/**
	 * Alias for applying delivery data to an order.
	 *
	 * @param mixed $order  Order data.
	 * @param mixed $method Delivery method identifier.
	 * @return array<string,mixed>
	 */
	function velotok_core_order_delivery( $order, $method ) {
		return velotok_core_delivery_options_apply_to_order( $order, $method );
	}
}

/**
 * Alias for callers that expect a generic delivery method list.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_get_delivery_methods() {
	return velotok_core_delivery_options_methods_data();
}

/**
 * Alias for callers that expect a list-shaped options accessor.
 *
 * @param array<string,mixed> $order Optional order data.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_delivery_options_list( $order = array() ) {
	return velotok_core_delivery_options( $order );
}

/**
 * Alias for calculating a delivery option cost for an order.
 *
 * @param mixed               $method Delivery method identifier.
 * @param array<string,mixed> $order  Optional order data.
 * @return array<string,mixed>
 */
function velotok_core_delivery_options_cost_for_order( $method, $order = array() ) {
	return velotok_core_delivery_options_cost( $method, $order );
}

/**
 * Alias for calculating a delivery option cost.
 *
 * @param mixed               $method Delivery method identifier.
 * @param array<string,mixed> $order  Optional order data.
 * @return array<string,mixed>
 */
function velotok_core_calculate_delivery_option( $method, $order = array() ) {
	return velotok_core_delivery_options_cost( $method, $order );
}

/**
 * Alias for saving the selected delivery option into an order.
 *
 * @param mixed $order  Order data.
 * @param mixed $method Delivery method identifier.
 * @return array<string,mixed>
 */
function velotok_core_delivery_options_save_to_order( $order, $method ) {
	return velotok_core_delivery_options_apply_to_order( $order, $method );
}

/**
 * Alias for saving the selected delivery option into an order.
 *
 * @param mixed $order  Order data.
 * @param mixed $method Delivery method identifier.
 * @return array<string,mixed>
 */
function velotok_core_save_delivery_to_order( $order, $method ) {
	return velotok_core_delivery_options_apply_to_order( $order, $method );
}

/**
 * Alias for applying a delivery option to an order.
 *
 * @param mixed $order  Order data.
 * @param mixed $method Delivery method identifier.
 * @return array<string,mixed>
 */
function velotok_core_apply_delivery_to_order( $order, $method ) {
	return velotok_core_delivery_options_apply_to_order( $order, $method );
}
