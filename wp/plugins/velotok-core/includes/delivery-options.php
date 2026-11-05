<?php
/**
 * Delivery options stub contract.
 *
 * This is the single access point for the delivery options shown at checkout.
 * The current iteration deliberately uses a deterministic in-memory stub
 * provider: it does not read environment variables, files, the database or
 * remote delivery services.
 *
 * The stub supplies the delivery method list, the provider tariff grid and the
 * order persistence helpers. Both tariff views are exposed so checkout code can
 * use amount tiers while order delivery code can use the base-per-kilogram grid.
 * A real logistics provider integration is out of scope until the open
 * questions are resolved.
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
			'provider'                  => 'stub',
			'provider_label'            => 'Почта России',
			'source'                    => 'stub',
			'mocked'                    => true,
			'available'                 => true,
			'requires_manual_agreement' => false,
			'eta'                       => array(
				'min_days' => 3,
				'max_days' => 7,
			),
			'tariff'                    => array(
				'id'                      => 'russian_post',
				'method'                  => 'russian_post',
				'label'                   => 'Почта РФ',
				'provider'                => 'stub',
				'source'                  => 'stub',
				'mocked'                  => true,
				'currency'                => 'RUB',
				'base'                    => 350.0,
				'per_kg'                  => 100.0,
				'free_from'               => 50000.0,
				'min'                     => 350.0,
				'max'                     => null,
				'manual'                  => false,
				'base_amount'             => 350.0,
				'amount'                  => 350.0,
				'free_shipping_threshold' => 50000.0,
				'tiers'                   => array(
					array( 'min_total' => 0.0, 'max_total' => 19999.99, 'amount' => 350.0 ),
					array( 'min_total' => 20000.0, 'max_total' => 49999.99, 'amount' => 250.0 ),
					array( 'min_total' => 50000.0, 'max_total' => null, 'amount' => 0.0 ),
				),
			),
		),
		'cdek'         => array(
			'id'                        => 'cdek',
			'slug'                      => 'cdek',
			'code'                      => 'cdek',
			'label'                     => 'СДЭК',
			'provider'                  => 'stub',
			'provider_label'            => 'СДЭК',
			'source'                    => 'stub',
			'mocked'                    => true,
			'available'                 => true,
			'requires_manual_agreement' => false,
			'eta'                       => array(
				'min_days' => 1,
				'max_days' => 3,
			),
			'tariff'                    => array(
				'id'                      => 'cdek',
				'method'                  => 'cdek',
				'label'                   => 'СДЭК',
				'provider'                => 'stub',
				'source'                  => 'stub',
				'mocked'                  => true,
				'currency'                => 'RUB',
				'base'                    => 490.0,
				'per_kg'                  => 120.0,
				'free_from'               => 70000.0,
				'min'                     => 490.0,
				'max'                     => null,
				'manual'                  => false,
				'base_amount'             => 490.0,
				'amount'                  => 490.0,
				'free_shipping_threshold' => 70000.0,
				'tiers'                   => array(
					array( 'min_total' => 0.0, 'max_total' => 19999.99, 'amount' => 490.0 ),
					array( 'min_total' => 20000.0, 'max_total' => 49999.99, 'amount' => 390.0 ),
					array( 'min_total' => 50000.0, 'max_total' => null, 'amount' => 0.0 ),
				),
			),
		),
		'other'        => array(
			'id'                        => 'other',
			'slug'                      => 'other',
			'code'                      => 'other',
			'label'                     => 'Иной логист',
			'provider'                  => 'stub',
			'provider_label'            => 'Иной логист',
			'source'                    => 'stub',
			'mocked'                    => true,
			'available'                 => true,
			'requires_manual_agreement' => true,
			'eta'                       => array(
				'min_days' => 2,
				'max_days' => 10,
			),
			'tariff'                    => array(
				'id'                      => 'other',
				'method'                  => 'other',
				'label'                   => 'Иной логист',
				'provider'                => 'stub',
				'source'                  => 'stub',
				'mocked'                  => true,
				'currency'                => 'RUB',
				'base'                    => 590.0,
				'per_kg'                  => 150.0,
				'free_from'               => null,
				'min'                     => 590.0,
				'max'                     => null,
				'manual'                  => true,
				'base_amount'             => 590.0,
				'amount'                  => 590.0,
				'free_shipping_threshold' => null,
				'tiers'                   => array(
					array( 'min_total' => 0.0, 'max_total' => 19999.99, 'amount' => 790.0 ),
					array( 'min_total' => 20000.0, 'max_total' => 49999.99, 'amount' => 690.0 ),
					array( 'min_total' => 50000.0, 'max_total' => null, 'amount' => 590.0 ),
				),
			),
		),
	);

	return $methods;
}

/**
 * Return the mocked provider tariff grid keyed by method identifier.
 *
 * Each method has both amount tiers selected by order total and a
 * base-per-kilogram tariff used by order delivery calculations.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_delivery_options_tariffs_data() {
	static $tariffs = null;

	if ( null !== $tariffs ) {
		return $tariffs;
	}

	$tariffs = array();

	foreach ( velotok_core_delivery_options_methods_data() as $id => $method ) {
		$tariffs[ $id ] = $method['tariff'];
	}

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

	$methods = velotok_core_delivery_options_methods_data();
	$tariffs = velotok_core_delivery_options_tariffs_data();

	$contract = array(
		'spec'                    => 'clarify-a8fe76a0',
		'parent_spec'             => 'clarify-096bb945',
		'source'                  => 'stub',
		'mocked'                  => true,
		'provider'                => 'stub',
		'provider_id'             => 'stub',
		'provider_label'          => 'Stub delivery provider',
		'provider_contract'       => array(
			'id'      => 'stub',
			'name'    => 'stub',
			'label'   => 'Stub delivery provider',
			'source'  => 'stub',
			'mocked'  => true,
			'network' => false,
		),
		'network'                 => false,
		'persistence'             => false,
		'currency'                => 'RUB',
		'methods'                 => $methods,
		'delivery_methods'        => $methods,
		'method_ids'              => array_keys( $methods ),
		'delivery_method_ids'     => array_keys( $methods ),
		'options'                 => array_values( $methods ),
		'delivery_options'        => array_values( $methods ),
		'default'                 => 'russian_post',
		'default_method'          => 'russian_post',
		'delivery_default'        => 'russian_post',
		'required'                => true,
		'delivery_required'       => true,
		'tariffs'                 => $tariffs,
		'tariff_grid'             => $tariffs,
		'delivery_tariffs'        => $tariffs,
		'free_shipping_threshold' => 50000.0,
		'calculation'             => array(
			'strategy'        => 'base_plus_weight',
			'weight_unit'     => 'kg',
			'weight_rounding' => 'ceil',
			'free_delivery'   => 'subtotal_threshold',
			'currency'        => 'RUB',
			'mocked'          => true,
		),
		'cost'                    => array(
			'source'     => 'stub',
			'provider'   => 'stub',
			'mocked'     => true,
			'amount'     => null,
			'currency'   => 'RUB',
			'calculated' => true,
			'grid'       => 'tariff_grid',
			'tariffs'    => $tariffs,
		),
		'order'                   => array(
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
		'order_fields'            => array(
			'method'          => 'delivery_method',
			'method_label'    => 'delivery_method_label',
			'provider'        => 'delivery_provider',
			'cost'            => 'delivery_cost',
			'currency'        => 'delivery_currency',
			'shipping_method' => 'shipping_method',
			'shipping_cost'   => 'shipping_cost',
			'mocked'          => 'delivery_mocked',
			'meta'            => array(
				'method'   => '_velotok_delivery_method',
				'label'    => '_velotok_delivery_method_label',
				'provider' => '_velotok_delivery_provider',
				'cost'     => '_velotok_delivery_cost',
				'currency' => '_velotok_delivery_currency',
				'mocked'   => '_velotok_delivery_mocked',
			),
		),
		'error_codes'             => array(
			'missing_method'  => 'DELIVERY_METHOD_REQUIRED',
			'invalid_method'  => 'DELIVERY_METHOD_INVALID',
			'option_required' => 'DELIVERY_OPTION_REQUIRED',
			'option_invalid'  => 'DELIVERY_OPTION_INVALID',
		),
		'future_source'           => 'provider_api',
		'missing_method'          => 'DELIVERY_METHOD_REQUIRED',
		'invalid_method'          => 'DELIVERY_METHOD_INVALID',
		'open_questions'          => array(
			'providers'        => 'Каков финальный перечень реальных служб доставки, которые должны поддерживаться?',
			'tariffs'          => 'Каковы правила реальной тарификации доставки и пороги бесплатной доставки?',
			'integration'      => 'Какие требования предъявляются к реальной интеграции со службами доставки (создание отправления, трекинг, обработка ошибок) и какой формат данных передаётся в службу?',
			'address_dependent' => 'Как источник реальных данных о способах доставки зависит от адреса и параметров заказа?',
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
 * Alias kept for callers that use the delivery-prefixed name.
 *
 * @return array<string,mixed>
 */
function velotok_core_delivery_contract_options() {
	return velotok_core_delivery_options_contract();
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
 * Alias for the generic normalization helper.
 *
 * @param mixed $value Raw key.
 * @return string Normalized key or an empty string.
 */
function velotok_core_delivery_normalize_key( $value ) {
	return velotok_core_delivery_options_normalize( $value );
}

/**
 * Alias kept for checkout callers.
 *
 * @param mixed $value Raw key.
 * @return string Normalized key or an empty string.
 */
function velotok_core_checkout_normalize_key( $value ) {
	return velotok_core_delivery_options_normalize( $value );
}

/**
 * Resolve a delivery option by identifier, label or a small set of aliases.
 *
 * @param mixed $method Delivery option identifier.
 * @return array<string,mixed>|null Null for an unknown option.
 */
function velotok_core_delivery_option( $method ) {
	if ( ! is_scalar( $method ) ) {
		return null;
	}

	$key = velotok_core_delivery_options_normalize( $method );

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

	$methods = velotok_core_delivery_options_methods_data();

	if ( isset( $methods[ $key ] ) ) {
		return $methods[ $key ];
	}

	foreach ( $methods as $definition ) {
		foreach ( array( 'id', 'slug', 'code', 'label', 'provider_label' ) as $field ) {
			if ( ! isset( $definition[ $field ] ) ) {
				continue;
			}

			if ( velotok_core_delivery_options_normalize( $definition[ $field ] ) === $key ) {
				return $definition;
			}
		}
	}

	return null;
}

/**
 * Resolve a delivery method from the stub method list.
 *
 * @param mixed $method Identifier, slug, code, label or alias.
 * @return array<string,mixed>|null
 */
function velotok_core_delivery_options_method( $method ) {
	return velotok_core_delivery_option( $method );
}

/**
 * Alias for callers that use a get-prefixed helper.
 *
 * @param mixed $method Delivery option identifier.
 * @return array<string,mixed>|null
 */
function velotok_core_get_delivery_option( $method ) {
	return velotok_core_delivery_option( $method );
}

/**
 * Alias used by checkout callers.
 *
 * @param mixed $method Delivery method identifier.
 * @return array<string,mixed>|null
 */
function velotok_core_delivery_method( $method ) {
	return velotok_core_delivery_option( $method );
}

/**
 * Check whether a delivery option is declared by the stub provider.
 *
 * @param mixed $method Delivery option identifier.
 * @return bool
 */
function velotok_core_delivery_option_is_valid( $method ) {
	return null !== velotok_core_delivery_option( $method );
}

/**
 * Alias kept for checkout callers.
 *
 * @param mixed $method Delivery method identifier.
 * @return bool
 */
function velotok_core_delivery_method_is_valid( $method ) {
	return velotok_core_delivery_option_is_valid( $method );
}

/**
 * Validate the selected delivery option.
 *
 * @param mixed $method   Delivery option identifier.
 * @param bool  $required Whether an empty selection is an error.
 * @return array<string,mixed>
 */
function velotok_core_validate_delivery_option( $method, $required = true ) {
	$contract = velotok_core_delivery_options_contract();
	$value    = is_string( $method ) ? trim( $method ) : '';

	if ( '' === $value ) {
		$code = $required ? $contract['missing_method'] : null;

		return array(
			'valid'    => ! $required,
			'code'     => $code,
			'error'    => $code,
			'codes'    => $required ? array( $contract['missing_method'], $contract['error_codes']['option_required'] ) : array(),
			'method'   => '',
			'option'   => null,
			'mocked'   => true,
			'provider' => $contract['provider_id'],
		);
	}

	$resolved = velotok_core_delivery_option( $value );

	if ( null === $resolved ) {
		return array(
			'valid'    => false,
			'code'     => $contract['invalid_method'],
			'error'    => $contract['invalid_method'],
			'codes'    => array( $contract['invalid_method'], $contract['error_codes']['option_invalid'] ),
			'method'   => $value,
			'option'   => null,
			'mocked'   => true,
			'provider' => $contract['provider_id'],
		);
	}

	return array(
		'valid'    => true,
		'code'     => null,
		'error'    => null,
		'codes'    => array(),
		'method'   => $resolved['id'],
		'option'   => $resolved,
		'mocked'   => true,
		'provider' => $contract['provider_id'],
	);
}

/**
 * Alias for callers that use a method-prefixed validator.
 *
 * @param mixed $method   Delivery option identifier.
 * @param bool  $required Whether an empty selection is an error.
 * @return array<string,mixed>
 */
function velotok_core_validate_delivery_option_selection( $method, $required = true ) {
	return velotok_core_validate_delivery_option( $method, $required );
}

/**
 * Validate a delivery method against the standalone stub.
 *
 * @param mixed $method   Delivery method identifier.
 * @param bool  $required Whether an empty selection is an error.
 * @return array<string,mixed>
 */
function velotok_core_validate_delivery_method( $method, $required = true ) {
	$result = velotok_core_validate_delivery_option( $method, $required );
	$result['contract'] = $result['option'];

	return $result;
}

/**
 * Return the mocked delivery options supplied by the stub provider.
 *
 * @param array<string,mixed> $order Optional order data used for cost calculation.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_delivery_options( $order = array() ) {
	$options = array();

	foreach ( velotok_core_delivery_options_methods_data() as $method ) {
		$cost                    = velotok_core_delivery_options_cost( $method['id'], $order );
		$method['tariff']        = velotok_core_delivery_options_tariff( $method['id'] );
		$method['cost']          = $cost['amount'];
		$method['cost_currency'] = $cost['currency'];
		$method['cost_calculated'] = true;
		$method['cost_mocked']   = true;
		$options[]               = $method;
	}

	return $options;
}

/**
 * Alias for callers that use a get-prefixed helper.
 *
 * @param array<string,mixed> $order Optional order data.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_get_delivery_options( $order = array() ) {
	return velotok_core_delivery_options( $order );
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
 * Return delivery options keyed by identifier.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_delivery_option_map() {
	return velotok_core_delivery_options_methods_data();
}

/**
 * Return delivery methods from the standalone delivery options stub.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_delivery_methods() {
	return velotok_core_delivery_options_methods_data();
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
 * Return delivery method options as a list.
 *
 * @return array<int,array<string,mixed>>
 */
function velotok_core_delivery_method_options() {
	return array_values( velotok_core_delivery_options_methods_data() );
}

/**
 * Alias for callers that expect a checkout-prefixed helper.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_checkout_delivery_methods() {
	return velotok_core_delivery_options_methods_data();
}

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

/**
 * Return the stub delivery provider description.
 *
 * @return array<string,mixed>
 */
function velotok_core_delivery_stub_provider() {
	$contract = velotok_core_delivery_options_contract();

	return $contract['provider_contract'];
}

/**
 * Alias for callers that use a stub-provider-prefixed name.
 *
 * @return array<string,mixed>
 */
function velotok_core_stub_delivery_provider() {
	return velotok_core_delivery_stub_provider();
}

/**
 * Return the full tariff grid of the stub provider.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_delivery_tariffs() {
	return velotok_core_delivery_options_tariffs_data();
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
 * Alias for callers that use an explicit grid name.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_delivery_tariff_grid() {
	return velotok_core_delivery_options_tariffs_data();
}

/**
 * Alias for callers that use a getter-style name.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_get_delivery_tariffs() {
	return velotok_core_delivery_options_tariffs_data();
}

/**
 * Return the tariff definition for a delivery method.
 *
 * @param mixed $method Delivery method identifier.
 * @return array<string,mixed>|null Null for an unknown method.
 */
function velotok_core_delivery_tariff( $method ) {
	$resolved = velotok_core_delivery_option( $method );

	if ( null === $resolved ) {
		return null;
	}

	$tariffs = velotok_core_delivery_options_tariffs_data();

	return isset( $tariffs[ $resolved['id'] ] ) ? $tariffs[ $resolved['id'] ] : null;
}

/**
 * Return the stub tariff for a delivery method.
 *
 * @param mixed $method Delivery method identifier.
 * @return array<string,mixed>|null
 */
function velotok_core_delivery_options_tariff( $method ) {
	return velotok_core_delivery_tariff( $method );
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
 * Alias for callers that use the checkout tier helper name.
 *
 * @param array<string,mixed> $tariff Tariff definition.
 * @param float               $amount Order amount.
 * @return array<string,mixed>|null
 */
function velotok_core_delivery_tariff_tier( $tariff, $amount ) {
	return velotok_core_delivery_options_tier( $tariff, $amount );
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
 * Read an order subtotal from an array or an object without external calls.
 *
 * @param mixed $order Order data.
 * @return float
 */
function velotok_core_delivery_order_subtotal( $order ) {
	$amount = 0.0;

	if ( is_array( $order ) ) {
		foreach ( array( 'subtotal', 'order_subtotal', 'cart_subtotal', 'total', 'order_total', 'amount' ) as $key ) {
			if ( isset( $order[ $key ] ) && is_numeric( $order[ $key ] ) ) {
				$amount = (float) $order[ $key ];
				break;
			}
		}
	} elseif ( is_object( $order ) ) {
		if ( method_exists( $order, 'get_subtotal' ) ) {
			$subtotal = $order->get_subtotal();
			if ( is_numeric( $subtotal ) ) {
				$amount = (float) $subtotal;
			}
		}

		if ( 0.0 === $amount && method_exists( $order, 'get_total' ) ) {
			$total = $order->get_total();
			if ( is_numeric( $total ) ) {
				$amount = (float) $total;
			}
		}
	} elseif ( is_numeric( $order ) ) {
		$amount = (float) $order;
	}

	return round( $amount, 2 );
}

/**
 * Read an order weight from an array or an object without external calls.
 *
 * @param mixed $order Order data.
 * @return float Weight in kilograms.
 */
function velotok_core_delivery_order_weight( $order ) {
	$weight = 0.0;

	if ( is_array( $order ) ) {
		foreach ( array( 'weight', 'order_weight', 'total_weight' ) as $key ) {
			if ( isset( $order[ $key ] ) && is_numeric( $order[ $key ] ) ) {
				$weight = (float) $order[ $key ];
				break;
			}
		}

		if ( 0.0 === $weight && isset( $order['items'] ) && is_array( $order['items'] ) ) {
			foreach ( $order['items'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$item_weight = isset( $item['weight'] ) && is_numeric( $item['weight'] ) ? (float) $item['weight'] : 0.0;
				$quantity    = isset( $item['quantity'] ) && is_numeric( $item['quantity'] ) ? (float) $item['quantity'] : 1.0;
				$weight     += $item_weight * $quantity;
			}
		}
	} elseif ( is_object( $order ) && method_exists( $order, 'get_weight' ) ) {
		$value = $order->get_weight();
		if ( is_numeric( $value ) ) {
			$weight = (float) $value;
		}
	}

	return round( max( 0.0, $weight ), 3 );
}

/**
 * Calculate the mocked delivery cost using the stub amount tiers.
 *
 * @param mixed               $method Delivery method identifier.
 * @param array<string,mixed> $order  Optional order data.
 * @return array<string,mixed>
 */
function velotok_core_delivery_options_cost( $method, $order = array(), $options = array() ) {
	$contract = velotok_core_delivery_options_contract();
	$resolved = velotok_core_delivery_option( $method );
	$tariff   = null === $resolved ? null : velotok_core_delivery_options_tariff( $resolved['id'] );
	$total    = isset( $options['subtotal'] ) && is_numeric( $options['subtotal'] )
		? round( (float) $options['subtotal'], 2 )
		: velotok_core_delivery_options_order_amount( $order );
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
 * Calculate delivery cost using the stub provider tariff grid.
 *
 * The amount is the base tariff plus the per-kilogram rate multiplied by the
 * order weight rounded up to a whole kilogram. Delivery is free when the order
 * subtotal reaches the method threshold. No external service is called.
 *
 * @param mixed               $method  Delivery option identifier.
 * @param array<string,mixed> $order   Order data.
 * @param array<string,mixed> $options Optional overrides: subtotal, weight.
 * @return array<string,mixed>
 */
function velotok_core_calculate_delivery_cost( $method, $order = array(), $options = array() ) {
	$contract = velotok_core_delivery_options_contract();
	$resolved = velotok_core_delivery_option( $method );

	if ( null === $resolved ) {
		return array(
			'valid'    => false,
			'code'     => $contract['invalid_method'],
			'error'    => $contract['invalid_method'],
			'method'   => '',
			'option'   => null,
			'amount'   => null,
			'cost'     => null,
			'currency' => $contract['currency'],
			'source'   => 'stub',
			'mocked'   => true,
			'provider' => $contract['provider_id'],
			'order'    => is_array( $order ) ? $order : array(),
		);
	}

	if ( ! is_array( $options ) ) {
		$options = array();
	}

	$uses_total_tiers = is_array( $order )
		&& array_key_exists( 'total', $order )
		&& ! array_key_exists( 'subtotal', $order )
		&& ! array_key_exists( 'order_subtotal', $order )
		&& ! array_key_exists( 'cart_subtotal', $order )
		&& ! array_key_exists( 'weight', $order )
		&& ! array_key_exists( 'items', $order )
		&& ! isset( $options['subtotal'] )
		&& ! isset( $options['weight'] );

	if ( $uses_total_tiers ) {
		return velotok_core_delivery_options_cost( $method, $order, $options );
	}

	$subtotal = isset( $options['subtotal'] ) && is_numeric( $options['subtotal'] )
		? (float) $options['subtotal']
		: velotok_core_delivery_order_subtotal( $order );
	$weight   = isset( $options['weight'] ) && is_numeric( $options['weight'] )
		? (float) $options['weight']
		: velotok_core_delivery_order_weight( $order );
	$weight   = max( 0.0, $weight );

	$tariff     = $resolved['tariff'];
	$base       = (float) $tariff['base'];
	$per_kg     = (float) $tariff['per_kg'];
	$free_from  = null === $tariff['free_from'] ? null : (float) $tariff['free_from'];
	$billable   = (float) ceil( $weight );
	$weight_fee = round( $per_kg * $billable, 2 );
	$amount     = round( $base + $weight_fee, 2 );
	$free       = null !== $free_from && $subtotal >= $free_from;

	if ( $free ) {
		$amount = 0.0;
	}

	if ( null !== $tariff['min'] ) {
		$amount = max( $amount, (float) $tariff['min'] );
	}

	if ( null !== $tariff['max'] ) {
		$amount = min( $amount, (float) $tariff['max'] );
	}

	if ( $free ) {
		$amount = 0.0;
	}

	$amount = round( $amount, 2 );

	return array(
		'valid'           => true,
		'code'            => null,
		'error'           => null,
		'method'          => $resolved['id'],
		'method_label'    => $resolved['label'],
		'option'          => $resolved,
		'provider'        => $contract['provider_id'],
		'mocked'          => true,
		'source'          => 'stub',
		'amount'          => $amount,
		'cost'            => $amount,
		'delivery_cost'   => $amount,
		'currency'        => $contract['currency'],
		'free'            => $free,
		'free_from'       => $free_from,
		'subtotal'        => round( $subtotal, 2 ),
		'weight'          => $weight,
		'billable_weight' => $billable,
		'tariff'          => $tariff,
		'breakdown'       => array(
			'base'       => $base,
			'per_kg'     => $per_kg,
			'weight_fee' => $weight_fee,
			'discount'   => $free ? -1 * round( $base + $weight_fee, 2 ) : 0.0,
			'total'      => $amount,
		),
		'order'           => is_array( $order ) ? $order : array(),
	);
}

/**
 * Alias for callers that use a shorter calculation name.
 *
 * @param mixed               $method  Delivery option identifier.
 * @param array<string,mixed> $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_delivery_calculate_cost( $method, $order = array(), $options = array() ) {
	return velotok_core_calculate_delivery_cost( $method, $order, $options );
}

/**
 * Short alias for delivery cost calculation.
 *
 * @param mixed               $method Delivery method identifier.
 * @param array<string,mixed> $order  Optional order data.
 * @return array<string,mixed>
 */
function velotok_core_calculate_delivery( $method, $order = array() ) {
	return velotok_core_calculate_delivery_cost( $method, $order );
}

/**
 * Return a mocked delivery cost for a method using the stub amount tiers.
 *
 * @param mixed               $method Delivery method identifier.
 * @param array<string,mixed> $order  Optional order data.
 * @return array<string,mixed>
 */
function velotok_core_delivery_cost( $method, $order = array() ) {
	return velotok_core_delivery_options_cost( $method, $order );
}

/**
 * Alias for callers that use a shipping-cost name.
 *
 * @param mixed               $method  Delivery option identifier.
 * @param array<string,mixed> $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_calculate_shipping_cost( $method, $order = array(), $options = array() ) {
	return velotok_core_calculate_delivery_cost( $method, $order, $options );
}

/**
 * Alias for callers that use a get-cost name.
 *
 * @param mixed               $method  Delivery option identifier.
 * @param array<string,mixed> $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_get_delivery_cost( $method, $order = array(), $options = array() ) {
	return velotok_core_calculate_delivery_cost( $method, $order, $options );
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
	$label = '' !== $cost['method'] && null !== velotok_core_delivery_option( $cost['method'] )
		? velotok_core_delivery_option( $cost['method'] )['label']
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

/**
 * Alias for applying a delivery option to an order.
 *
 * @param mixed $order  Order data.
 * @param mixed $method Delivery method identifier.
 * @return array<string,mixed>
 */
function velotok_core_delivery_apply_to_order( $order, $method ) {
	return velotok_core_delivery_options_apply_to_order( $order, $method );
}

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

/**
 * Alias for callers that use an order-prefixed name.
 *
 * @param mixed $order  Order data.
 * @param mixed $method Delivery method identifier.
 * @return array<string,mixed>
 */
function velotok_core_order_delivery( $order, $method ) {
	return velotok_core_delivery_options_apply_to_order( $order, $method );
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
 * Build the delivery selection that is stored in an order.
 *
 * @param mixed               $method  Delivery option identifier.
 * @param array<string,mixed> $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_delivery_selection( $method, $order = array(), $options = array() ) {
	$contract = velotok_core_delivery_options_contract();
	$cost     = velotok_core_calculate_delivery_cost( $method, $order, $options );

	if ( empty( $cost['valid'] ) ) {
		return array(
			'valid'    => false,
			'code'     => $cost['code'],
			'error'    => $cost['error'],
			'method'   => '',
			'cost'     => null,
			'amount'   => null,
			'currency' => $contract['currency'],
			'mocked'   => true,
			'saved'    => false,
			'provider' => $contract['provider_id'],
		);
	}

	$fields = $contract['order_fields'];
	$meta   = array(
		$fields['meta']['method']   => $cost['method'],
		$fields['meta']['label']    => $cost['method_label'],
		$fields['meta']['provider'] => $contract['provider_id'],
		$fields['meta']['cost']     => $cost['amount'],
		$fields['meta']['currency'] => $contract['currency'],
		$fields['meta']['mocked']   => 'yes',
	);

	return array(
		'valid'                    => true,
		'code'                     => null,
		'error'                    => null,
		'spec'                     => $contract['spec'],
		'mocked'                   => true,
		'saved'                    => false,
		'provider'                 => $contract['provider_id'],
		'method'                   => $cost['method'],
		'method_id'                => $cost['method'],
		'method_label'             => $cost['method_label'],
		'label'                    => $cost['method_label'],
		'amount'                   => $cost['amount'],
		'cost'                     => $cost['amount'],
		'currency'                 => $contract['currency'],
		'method_field'             => $fields['method'],
		'cost_field'               => $fields['cost'],
		$fields['method']          => $cost['method'],
		$fields['method_label']    => $cost['method_label'],
		$fields['provider']        => $contract['provider_id'],
		$fields['cost']            => $cost['amount'],
		$fields['currency']        => $contract['currency'],
		$fields['shipping_method'] => $cost['method'],
		$fields['shipping_cost']   => $cost['amount'],
		$fields['mocked']          => true,
		'meta'                     => $meta,
		'calculation'              => $cost,
	);
}

/**
 * Alias for callers that use a get-prefixed selection name.
 *
 * @param mixed               $method  Delivery option identifier.
 * @param array<string,mixed> $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_get_delivery_selection( $method, $order = array(), $options = array() ) {
	return velotok_core_delivery_selection( $method, $order, $options );
}

/**
 * Save the selected delivery method and cost in an order.
 *
 * Arrays are updated in the returned value because PHP copies arrays. Objects
 * exposing WooCommerce-like meta methods (`update_meta_data`, `set_shipping_total`)
 * are updated in place. Nothing is written to the database from this stub
 * unless the caller explicitly saves the order afterwards.
 *
 * @param mixed $order  Order data (array or object).
 * @param mixed $method Delivery option identifier.
 * @param mixed $cost   Optional explicit cost override.
 * @return array<string,mixed> Updated order array or selection result.
 */
function velotok_core_save_delivery_selection( $order = array(), $method = '', $cost = null ) {
	if ( is_string( $order ) && ( is_array( $method ) || is_object( $method ) ) ) {
		$tmp    = $order;
		$order  = $method;
		$method = $tmp;
	}

	$contract = velotok_core_delivery_options_contract();
	$fields   = $contract['order_fields'];
	$selection = velotok_core_delivery_selection( $method, is_array( $order ) ? $order : array() );

	if ( null !== $cost && is_numeric( $cost ) && ! empty( $selection['valid'] ) ) {
		$selection['amount'] = round( (float) $cost, 2 );
		$selection['cost']   = $selection['amount'];
		$selection[ $fields['cost'] ]                 = $selection['amount'];
		$selection[ $fields['shipping_cost'] ]        = $selection['amount'];
		$selection['meta'][ $fields['meta']['cost'] ] = $selection['amount'];
		$selection['calculation']['amount']           = $selection['amount'];
		$selection['calculation']['cost']             = $selection['amount'];
	}

	if ( empty( $selection['valid'] ) ) {
		return is_array( $order ) ? array_merge( $order, $selection ) : $selection;
	}

	$selection['saved'] = true;
	$selection['meta'][ $fields['meta']['mocked'] ] = 'yes';

	if ( is_object( $order ) ) {
		foreach ( $selection['meta'] as $meta_key => $meta_value ) {
			if ( method_exists( $order, 'update_meta_data' ) ) {
				$order->update_meta_data( $meta_key, $meta_value );
			}
		}

		if ( method_exists( $order, 'set_shipping_total' ) ) {
			$order->set_shipping_total( $selection['amount'] );
		}

		if ( method_exists( $order, 'set_shipping_method' ) ) {
			$order->set_shipping_method( $selection['method'] );
		}

		return $selection;
	}

	if ( ! is_array( $order ) ) {
		$order = array();
	}

	$order['valid']                      = true;
	$order['saved']                      = true;
	$order['mocked']                     = true;
	$order[ $fields['method'] ]          = $selection['method'];
	$order[ $fields['method_label'] ]    = $selection['method_label'];
	$order[ $fields['provider'] ]        = $selection['provider'];
	$order[ $fields['cost'] ]            = $selection['amount'];
	$order[ $fields['currency'] ]        = $selection['currency'];
	$order[ $fields['shipping_method'] ] = $selection['method'];
	$order[ $fields['shipping_cost'] ]   = $selection['amount'];
	$order[ $fields['mocked'] ]          = true;
	$order['delivery_method']            = $selection['method'];
	$order['delivery_method_label']      = $selection['method_label'];
	$order['delivery_cost']              = $selection['amount'];
	$order['delivery_currency']          = $selection['currency'];
	$order['delivery']                   = $selection;
	$order['delivery_selection']         = $selection;
	$order['delivery_meta']              = $selection['meta'];

	return $order;
}

/**
 * Alias for callers that use an order-prefixed save name.
 *
 * @param mixed $order  Order data.
 * @param mixed $method Delivery option identifier.
 * @param mixed $cost   Optional explicit cost override.
 * @return array<string,mixed>
 */
function velotok_core_save_order_delivery( $order = array(), $method = '', $cost = null ) {
	return velotok_core_save_delivery_selection( $order, $method, $cost );
}

/**
 * Apply the delivery selection to an order by reference.
 *
 * @param mixed $order  Order data.
 * @param mixed $method Delivery option identifier.
 * @param mixed $cost   Optional explicit cost override.
 * @return array<string,mixed> Selection result.
 */
function velotok_core_apply_delivery_selection( &$order, $method = '', $cost = null ) {
	$result = velotok_core_save_delivery_selection( $order, $method, $cost );

	if ( is_array( $order ) ) {
		$order = $result;
	}

	return $result;
}

/**
 * Alias for callers that use an apply-to-order name.
 *
 * @param mixed $order  Order data.
 * @param mixed $method Delivery option identifier.
 * @param mixed $cost   Optional explicit cost override.
 * @return array<string,mixed>
 */
function velotok_core_apply_delivery_to_order( &$order, $method = '', $cost = null ) {
	return velotok_core_apply_delivery_selection( $order, $method, $cost );
}

/**
 * Alias for callers that use a select-delivery name.
 *
 * @param mixed $order  Order data.
 * @param mixed $method Delivery option identifier.
 * @param mixed $cost   Optional explicit cost override.
 * @return array<string,mixed>
 */
function velotok_core_select_delivery_option( $order = array(), $method = '', $cost = null ) {
	return velotok_core_save_delivery_selection( $order, $method, $cost );
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
 * Alias for callers that use a shorter open questions name.
 *
 * @return array<string,string>
 */
function velotok_core_delivery_open_questions() {
	return velotok_core_delivery_options_open_questions();
}
