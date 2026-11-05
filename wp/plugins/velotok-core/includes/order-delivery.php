<?php
/**
 * Order checkout delivery stub contract.
 *
 * This is the single access point for choosing a delivery method during
 * checkout and for adding its cost to the order total. The current iteration
 * deliberately uses fixed in-memory data supplied by the delivery stub
 * provider: it does not read environment variables, files, the database or
 * remote services.
 *
 * A real integration with delivery services (provider APIs, authorization,
 * shipment creation, tracking and error handling) is out of scope until the
 * open questions are resolved. Consumers must keep using the accessors below
 * so the mocked implementation can be replaced without touching checkout code.
 *
 * Spec: clarify-b0e1a6d7.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once __DIR__ . '/delivery-options.php';

/**
 * Return the checkout delivery contract.
 *
 * The contract documents:
 * - the spec identifier and the explicit `mocked` marker;
 * - the selectable delivery methods supplied by the stub provider;
 * - the order fields that store the selected method and delivery cost;
 * - the rule that delivery cost is included in the order total;
 * - the open questions that block a real delivery integration.
 *
 * @return array<string,mixed>
 */
function velotok_core_order_delivery_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$delivery = velotok_core_delivery_options_contract();
	$methods  = $delivery['methods'];
	$options  = array_values( $methods );
	$ids      = array_keys( $methods );
	$default  = isset( $methods[ $delivery['default'] ] ) ? $delivery['default'] : ( $ids ? $ids[0] : '' );

	$contract = array(
		'spec'              => 'clarify-b0e1a6d7',
		'parent_spec'       => 'clarify-a8fe76a0',
		'checkout_spec'     => 'clarify-096bb945',
		'operation'         => 'select_delivery_at_checkout',
		'source'            => 'stub',
		'mocked'            => true,
		'provider'          => 'stub',
		'provider_id'       => 'stub',
		'provider_label'    => 'Stub delivery provider',
		'provider_contract' => array(
			'id'      => 'stub',
			'name'    => 'stub',
			'label'   => 'Stub delivery provider',
			'source'  => 'stub',
			'mocked'  => true,
			'network' => false,
		),
		'network'           => false,
		'currency'          => 'RUB',
		'required'          => true,
		'persistence'       => array(
			'enabled'         => true,
			'mode'            => 'order_object',
			'storage'         => 'order_fields_and_meta',
			'fields'          => array( 'delivery_method', 'delivery_method_label', 'delivery_cost', 'delivery_currency' ),
			'writes_database' => false,
			'mocked'          => true,
			'source'          => 'stub',
		),
		'default'           => $default,
		'labels'            => array(
			'russian_post' => 'Почта РФ',
			'cdek'         => 'СДЭК',
			'other'        => 'Иной логист',
		),
		'delivery_default'  => $default,
		'methods'           => $methods,
		'options'           => $options,
		'method_ids'        => $ids,
		'delivery_methods'  => $methods,
		'delivery_options'  => $options,
		'delivery_method_ids' => $ids,
		'selection'         => array(
			'field'          => 'delivery_method',
			'label_field'    => 'delivery_method_label',
			'cost_field'     => 'delivery_cost',
			'currency_field' => 'delivery_currency',
			'required'       => true,
			'mocked'         => true,
			'source'         => 'stub',
		),
		'order_fields'      => array(
			'method'          => 'delivery_method',
			'method_label'    => 'delivery_method_label',
			'provider'        => 'delivery_provider',
			'cost'            => 'delivery_cost',
			'currency'        => 'delivery_currency',
			'shipping_method' => 'shipping_method',
			'shipping_cost'   => 'shipping_cost',
			'subtotal'        => 'subtotal',
			'total'           => 'order_total',
			'total_alias'     => 'total',
			'in_total'        => 'delivery_in_total',
			'mocked'          => 'delivery_mocked',
			'meta'            => array(
				'method'   => '_velotok_delivery_method',
				'label'    => '_velotok_delivery_method_label',
				'provider' => '_velotok_delivery_provider',
				'cost'     => '_velotok_delivery_cost',
				'currency' => '_velotok_delivery_currency',
				'in_total' => '_velotok_delivery_in_total',
				'mocked'   => '_velotok_delivery_mocked',
			),
		),
		'totals'            => array(
			'include_delivery' => true,
			'in_total'         => true,
			'delivery_cost'    => 'delivery_cost',
			'order_total'      => 'order_total',
			'currency'         => 'RUB',
			'mocked'           => true,
			'strategy'         => 'subtotal_plus_delivery',
		),
		'display'           => array(
			'show_delivery_method' => true,
			'show_delivery_cost'   => true,
			'show_in_order_total'  => true,
			'format'               => '{amount} {currency}',
			'source'               => 'stub',
			'mocked'               => true,
		),
		'stub_contract'     => array(
			'id'      => 'stub',
			'source'  => 'stub',
			'mocked'  => true,
			'network' => false,
			'provider' => 'stub',
		),
		'real_integration'  => array(
			'required' => false,
			'status'   => 'not_implemented',
			'mocked'   => true,
			'network'  => false,
			'reason'   => 'open_questions_unresolved',
		),
		'error_codes'       => array(
			'missing_method'  => 'DELIVERY_METHOD_REQUIRED',
			'invalid_method'  => 'DELIVERY_METHOD_INVALID',
			'option_required' => 'DELIVERY_OPTION_REQUIRED',
			'option_invalid'  => 'DELIVERY_OPTION_INVALID',
		),
		'missing_method'    => 'DELIVERY_METHOD_REQUIRED',
		'invalid_method'    => 'DELIVERY_METHOD_INVALID',
		'open_questions'    => array(
			'providers'        => 'Каков финальный перечень служб доставки и их отображаемые названия?',
			'tariffs'          => 'Какова тарифная сетка и правила расчёта стоимости доставки, включая пороги бесплатной доставки?',
			'integration'      => 'Какие требования предъявляются к реальной интеграции со службами доставки (протоколы, авторизация, обработка ошибок)?',
			'address_dependent' => 'Как источник реальных данных о способах доставки зависит от адреса и параметров заказа?',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that use a checkout-prefixed contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_checkout_delivery_contract() {
	return velotok_core_order_delivery_contract();
}

/**
 * Alias kept for callers that use a delivery-checkout contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_delivery_checkout_contract() {
	return velotok_core_order_delivery_contract();
}

/**
 * Check whether checkout delivery is currently mocked.
 *
 * @return bool
 */
function velotok_core_order_delivery_is_mocked() {
	$contract = velotok_core_order_delivery_contract();

	return true === $contract['mocked'];
}

/**
 * Return every delivery method selectable at checkout.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_order_delivery_methods() {
	$contract = velotok_core_order_delivery_contract();

	return $contract['methods'];
}

/**
 * Return delivery methods as a list.
 *
 * @return array<int,array<string,mixed>>
 */
function velotok_core_order_delivery_options() {
	$contract = velotok_core_order_delivery_contract();

	return $contract['options'];
}

/**
 * Alias for callers that use a get-prefixed options name.
 *
 * @return array<int,array<string,mixed>>
 */
function velotok_core_get_order_delivery_options() {
	return velotok_core_order_delivery_options();
}

/**
 * Alias for callers that use a checkout-prefixed options name.
 *
 * @return array<int,array<string,mixed>>
 */
function velotok_core_checkout_delivery_options() {
	return velotok_core_order_delivery_options();
}

/**
 * Resolve a delivery method declared by the stub provider.
 *
 * @param mixed $method Delivery method identifier or label.
 * @return array<string,mixed>|null Null for an unknown method.
 */
function velotok_core_order_delivery_option( $method ) {
	return velotok_core_delivery_option( $method );
}

/**
 * Validate the delivery method selected at checkout.
 *
 * @param mixed $method   Delivery method identifier.
 * @param bool  $required Whether an empty selection is an error.
 * @return array<string,mixed>
 */
function velotok_core_validate_order_delivery_method( $method, $required = true ) {
	$contract = velotok_core_order_delivery_contract();
	$result   = velotok_core_validate_delivery_option( $method, $required );

	$result['spec']   = $contract['spec'];
	$result['source'] = $contract['source'];
	$result['saved']  = false;

	return $result;
}

/**
 * Alias for callers that use an order-delivery validation name.
 *
 * @param mixed $method   Delivery method identifier.
 * @param bool  $required Whether an empty selection is an error.
 * @return array<string,mixed>
 */
function velotok_core_order_delivery_validate( $method, $required = true ) {
	return velotok_core_validate_order_delivery_method( $method, $required );
}

/**
 * Format an amount using the contract display format.
 *
 * @param float  $amount   Amount.
 * @param string $currency Currency code.
 * @return string
 */
function velotok_core_order_delivery_format_amount( $amount, $currency = 'RUB' ) {
	return number_format( (float) $amount, 2, '.', ' ' ) . ' ' . $currency;
}

/**
 * Calculate the delivery cost for the checkout order.
 *
 * @param mixed               $method  Delivery method identifier.
 * @param array<string,mixed> $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_order_delivery_cost( $method, $order = array(), $options = array() ) {
	$cost = velotok_core_calculate_delivery_cost( $method, $order, $options );

	if ( empty( $cost['valid'] ) ) {
		return $cost;
	}

	$cost['display']      = velotok_core_order_delivery_format_amount( $cost['amount'], $cost['currency'] );
	$cost['cost_display'] = $cost['display'];

	return $cost;
}

/**
 * Alias for callers that use a checkout-prefixed cost name.
 *
 * @param mixed               $method  Delivery method identifier.
 * @param array<string,mixed> $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_checkout_delivery_cost( $method, $order = array(), $options = array() ) {
	return velotok_core_order_delivery_cost( $method, $order, $options );
}

/**
 * Calculate order totals including the delivery cost.
 *
 * The order total always equals the order subtotal plus the delivery cost, so
 * the delivery cost is displayed as part of the final order amount. When the
 * method is omitted the contract default is used.
 *
 * @param array<string,mixed> $order   Order data.
 * @param mixed               $method  Delivery method identifier.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_order_delivery_totals( $order = array(), $method = '', $options = array() ) {
	$contract = velotok_core_order_delivery_contract();

	if ( ! is_array( $options ) ) {
		$options = array();
	}

	if ( ! is_string( $method ) || '' === trim( $method ) ) {
		$method = $contract['default'];
	}

	$cost = velotok_core_order_delivery_cost( $method, $order, $options );

	if ( empty( $cost['valid'] ) ) {
		return array_merge(
			$cost,
			array(
				'spec'    => $contract['spec'],
				'source'  => $contract['source'],
				'mocked'  => true,
				'totals'  => null,
				'saved'   => false,
			)
		);
	}

	$subtotal = isset( $options['subtotal'] ) && is_numeric( $options['subtotal'] )
		? round( (float) $options['subtotal'], 2 )
		: velotok_core_delivery_order_subtotal( $order );
	$delivery = (float) $cost['amount'];
	$total    = round( $subtotal + $delivery, 2 );

	return array(
		'valid'                 => true,
		'code'                  => null,
		'error'                 => null,
		'spec'                  => $contract['spec'],
		'source'                => $contract['source'],
		'mocked'                => true,
		'network'               => false,
		'provider'              => $contract['provider_id'],
		'method'                => $cost['method'],
		'method_label'          => $cost['method_label'],
		'delivery_method'       => $cost['method'],
		'delivery_method_label' => $cost['method_label'],
		'subtotal'              => $subtotal,
		'delivery_cost'         => $delivery,
		'delivery_amount'       => $delivery,
		'shipping_cost'         => $delivery,
		'shipping_method'       => $cost['method'],
		'delivery_currency'     => $cost['currency'],
		'currency'              => $cost['currency'],
		'total'                 => $total,
		'order_total'           => $total,
		'delivery_in_total'     => true,
		'total_includes_delivery' => true,
		'delivery_display'      => velotok_core_order_delivery_format_amount( $delivery, $cost['currency'] ),
		'total_display'         => velotok_core_order_delivery_format_amount( $total, $cost['currency'] ),
		'free_delivery'         => ! empty( $cost['free'] ),
		'cost'                  => $cost,
		'calculation'           => $cost,
		'breakdown'             => array(
			'subtotal'      => $subtotal,
			'delivery_cost' => $delivery,
			'total'         => $total,
		),
	);
}

/**
 * Alias for callers that use a get-prefixed totals name.
 *
 * @param array<string,mixed> $order   Order data.
 * @param mixed               $method  Delivery method identifier.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_get_order_delivery_totals( $order = array(), $method = '', $options = array() ) {
	return velotok_core_order_delivery_totals( $order, $method, $options );
}

/**
 * Alias for callers that use a singular total name.
 *
 * @param array<string,mixed> $order   Order data.
 * @param mixed               $method  Delivery method identifier.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_order_delivery_total( $order = array(), $method = '', $options = array() ) {
	return velotok_core_order_delivery_totals( $order, $method, $options );
}

/**
 * Alias for callers that use a checkout-prefixed totals name.
 *
 * @param array<string,mixed> $order   Order data.
 * @param mixed               $method  Delivery method identifier.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_checkout_delivery_totals( $order = array(), $method = '', $options = array() ) {
	return velotok_core_order_delivery_totals( $order, $method, $options );
}

/**
 * Save the selected delivery method and its cost in an order.
 *
 * Arrays are updated in the returned value because PHP copies arrays. Objects
 * exposing WooCommerce-like meta methods are updated in place. Nothing is saved
 * to the database unless the caller explicitly saves the order afterwards.
 * The returned array includes the delivery cost inside `order_total`/`total`.
 *
 * @param mixed               $order   Order data (array or object).
 * @param mixed               $method  Delivery method identifier.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_save_checkout_delivery( $order = array(), $method = '', $options = array() ) {
	$contract = velotok_core_order_delivery_contract();
	$fields   = $contract['order_fields'];
	$totals   = velotok_core_order_delivery_totals( $order, $method, $options );

	if ( empty( $totals['valid'] ) ) {
		if ( is_array( $order ) ) {
			return array_merge( $order, $totals, array( 'saved' => false ) );
		}

		return array_merge( $totals, array( 'saved' => false ) );
	}

	$meta = array(
		$fields['meta']['method']   => $totals['method'],
		$fields['meta']['label']    => $totals['method_label'],
		$fields['meta']['provider'] => $contract['provider_id'],
		$fields['meta']['cost']     => $totals['delivery_cost'],
		$fields['meta']['currency'] => $totals['currency'],
		$fields['meta']['in_total'] => 'yes',
		$fields['meta']['mocked']   => 'yes',
	);

	$selection = array(
		'valid'                 => true,
		'ok'                    => true,
		'saved'                 => true,
		'spec'                  => $contract['spec'],
		'source'                => $contract['source'],
		'mocked'                => true,
		'provider'              => $contract['provider_id'],
		'method'                => $totals['method'],
		'method_id'             => $totals['method'],
		'method_label'          => $totals['method_label'],
		'delivery_method'       => $totals['method'],
		'delivery_method_label' => $totals['method_label'],
		'delivery_cost'         => $totals['delivery_cost'],
		'delivery_currency'     => $totals['currency'],
		'currency'              => $totals['currency'],
		'shipping_method'       => $totals['method'],
		'shipping_cost'         => $totals['delivery_cost'],
		'subtotal'              => $totals['subtotal'],
		'order_total'           => $totals['order_total'],
		'total'                 => $totals['total'],
		'delivery_in_total'     => true,
		'total_includes_delivery' => true,
		'delivery_display'      => $totals['delivery_display'],
		'total_display'         => $totals['total_display'],
		'meta'                  => $meta,
		'calculation'           => $totals,
	);

	if ( is_object( $order ) ) {
		foreach ( $meta as $meta_key => $meta_value ) {
			if ( method_exists( $order, 'update_meta_data' ) ) {
				$order->update_meta_data( $meta_key, $meta_value );
			}
		}

		if ( method_exists( $order, 'set_shipping_total' ) ) {
			$order->set_shipping_total( $totals['delivery_cost'] );
		}

		if ( method_exists( $order, 'set_shipping_method' ) ) {
			$order->set_shipping_method( $totals['method'] );
		}

		return $selection;
	}

	if ( ! is_array( $order ) ) {
		$order = array();
	}

	$order[ $fields['method'] ]            = $selection['method'];
	$order[ $fields['method_label'] ]      = $selection['method_label'];
	$order[ $fields['provider'] ]          = $selection['provider'];
	$order[ $fields['cost'] ]              = $selection['delivery_cost'];
	$order[ $fields['currency'] ]          = $selection['currency'];
	$order[ $fields['shipping_method'] ]   = $selection['shipping_method'];
	$order[ $fields['shipping_cost'] ]     = $selection['shipping_cost'];
	$order[ $fields['subtotal'] ]          = $selection['subtotal'];
	$order[ $fields['total'] ]             = $selection['order_total'];
	$order[ $fields['total_alias'] ]       = $selection['total'];
	$order[ $fields['in_total'] ]          = true;
	$order[ $fields['mocked'] ]            = true;
	$order['saved']                        = true;
	$order['valid']                        = true;
	$order['spec']                         = $contract['spec'];
	$order['mocked']                       = true;
	$order['delivery']                     = $selection;
	$order['delivery_selection']           = $selection;
	$order['delivery_meta']                = $meta;

	return $order;
}

/**
 * Apply the checkout delivery selection to an order by reference.
 *
 * @param mixed               $order   Order data.
 * @param mixed               $method  Delivery method identifier.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed> Selection result or updated order.
 */
function velotok_core_apply_checkout_delivery( &$order, $method = '', $options = array() ) {
	$result = velotok_core_save_checkout_delivery( $order, $method, $options );

	if ( is_array( $order ) ) {
		$order = $result;
	}

	return $result;
}

/**
 * Alias for callers that use an order-prefixed save name.
 *
 * @param mixed               $order   Order data.
 * @param mixed               $method  Delivery method identifier.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_save_order_checkout_delivery( $order = array(), $method = '', $options = array() ) {
	return velotok_core_save_checkout_delivery( $order, $method, $options );
}

/**
 * Read the delivery selection saved in a checkout order.
 *
 * This is the read counterpart of velotok_core_save_checkout_delivery(). It
 * supports order arrays and WooCommerce-like objects and never writes to the
 * database. The result is marked mocked and reports whether the delivery cost
 * is included in the stored order total.
 *
 * @param mixed $order Order data (array or object).
 * @return array<string,mixed>
 */
function velotok_core_get_checkout_delivery( $order = array() ) {
	$contract = velotok_core_order_delivery_contract();
	$selection = velotok_core_delivery_read_from_order( $order );

	$selection['spec']          = $contract['spec'];
	$selection['checkout_spec'] = $contract['checkout_spec'];
	$selection['provider_label'] = $contract['provider_label'];

	if ( ! empty( $selection['valid'] ) ) {
		$selection['in_total']                 = true;
		$selection['total_includes_delivery']  = true;
		$selection['display']                  = array(
			'delivery' => velotok_core_order_delivery_format_amount( $selection['delivery_cost'], $selection['delivery_currency'] ),
		);
	}

	return $selection;
}

/**
 * Alias for callers that use a read-prefixed checkout delivery name.
 *
 * @param mixed $order Order data (array or object).
 * @return array<string,mixed>
 */
function velotok_core_read_checkout_delivery( $order = array() ) {
	return velotok_core_get_checkout_delivery( $order );
}

/**
 * Check whether a checkout order stores a valid delivery selection.
 *
 * @param mixed $order Order data (array or object).
 * @return bool
 */
function velotok_core_checkout_delivery_is_saved( $order = array() ) {
	$selection = velotok_core_get_checkout_delivery( $order );

	return ! empty( $selection['valid'] ) && ! empty( $selection['saved'] );
}

/**
 * Return the open questions that block a real delivery integration.
 *
 * @return array<string,string>
 */
function velotok_core_order_delivery_open_questions() {
	$contract = velotok_core_order_delivery_contract();

	return $contract['open_questions'];
}

/**
 * Alias for callers that use a checkout-prefixed open questions name.
 *
 * @return array<string,string>
 */
function velotok_core_checkout_delivery_open_questions() {
	return velotok_core_order_delivery_open_questions();
}
