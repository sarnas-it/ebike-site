<?php
/**
 * Checkout delivery form stub contract.
 *
 * This is the single access point for rendering the delivery selection on the
 * checkout page and for handling its submission. The current iteration uses
 * fixed in-memory data supplied by the delivery stub provider: it does not
 * read environment variables, files, the database or remote services.
 *
 * A real integration with delivery services (provider APIs, authorization,
 * shipment creation, tracking and error handling) is out of scope until the
 * open questions are resolved. Consumers must keep using the accessors below
 * so the mocked implementation can be replaced without touching checkout code.
 *
 * Spec: TESTEB-152 (clarify-b0e1a6d7).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once __DIR__ . '/order-delivery.php';

/**
 * Return the checkout delivery form contract.
 *
 * The contract documents:
 * - the spec identifier and the explicit `mocked` marker;
 * - the form identifier and the submitted delivery method field;
 * - the selectable delivery methods supplied by the stub provider;
 * - the order fields that receive the selected method and delivery cost;
 * - the open questions that block a real delivery integration.
 *
 * @return array<string,mixed>
 */
function velotok_core_checkout_delivery_form_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$order_delivery = velotok_core_order_delivery_contract();
	$methods        = $order_delivery['methods'];
	$options        = array_values( $methods );
	$ids            = array_keys( $methods );
	$default        = isset( $methods[ $order_delivery['default'] ] ) ? $order_delivery['default'] : ( $ids ? $ids[0] : '' );

	$contract = array(
		'spec'              => 'TESTEB-152',
		'spec_ref'          => 'clarify-b0e1a6d7',
		'checkout_spec'     => 'clarify-096bb945',
		'requirement'       => 'Система SHALL поддерживать оформление доставки заказа',
		'operation'         => 'render_and_submit_checkout_delivery_form',
		'source'            => 'stub',
		'mocked'            => true,
		'network'           => false,
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
		'form'              => array(
			'id'            => 'velotok-checkout-delivery',
			'action'        => 'woocommerce_checkout',
			'method'        => 'post',
			'field'         => 'velotok_delivery_method',
			'submit_field'  => 'velotok_delivery_submit',
			'nonce_field'   => 'velotok_delivery_nonce',
			'required'      => true,
			'source'        => 'stub',
			'mocked'        => true,
		),
		'field'             => 'velotok_delivery_method',
		'field_required'    => true,
		'fields'            => array(
			'delivery_method' => array(
				'name'     => 'velotok_delivery_method',
				'type'     => 'radio',
				'label'    => 'Способ доставки',
				'required' => true,
				'source'   => 'stub',
				'mocked'   => true,
				'options'  => $options,
			),
		),
		'labels'            => array(
			'russian_post' => 'Почта РФ',
			'cdek'         => 'СДЭК',
			'other'        => 'Иной логист',
		),
		'methods'           => $methods,
		'options'           => $options,
		'method_ids'        => $ids,
		'delivery_methods'  => $methods,
		'delivery_options'  => $options,
		'delivery_method_ids' => $ids,
		'default'           => $default,
		'delivery_default'  => $default,
		'currency'          => 'RUB',
		'validation'        => array(
			'required' => true,
			'mocked'   => true,
			'source'   => 'stub',
			'missing'  => 'DELIVERY_METHOD_REQUIRED',
			'invalid'  => 'DELIVERY_METHOD_INVALID',
		),
		'missing_method'    => 'DELIVERY_METHOD_REQUIRED',
		'invalid_method'    => 'DELIVERY_METHOD_INVALID',
		'order_fields'      => $order_delivery['order_fields'],
		'totals'            => array(
			'include_delivery' => true,
			'in_total'         => true,
			'delivery_cost'    => 'delivery_cost',
			'order_total'      => 'order_total',
			'currency'         => 'RUB',
			'mocked'           => true,
			'source'           => 'stub',
		),
		'display'           => array(
			'show_form'            => true,
			'show_delivery_method' => true,
			'show_delivery_cost'   => true,
			'show_in_order_total'  => true,
			'source'               => 'stub',
			'mocked'               => true,
		),
		'stub_contract'     => array(
			'id'       => 'stub',
			'source'   => 'stub',
			'mocked'   => true,
			'network'  => false,
			'provider' => 'stub',
		),
		'real_integration'  => array(
			'required' => false,
			'status'   => 'not_implemented',
			'mocked'   => true,
			'network'  => false,
			'reason'   => 'open_questions_unresolved',
		),
		'open_questions'    => array(
			'providers'         => 'Каков финальный перечень служб доставки и их отображаемые названия?',
			'tariffs'           => 'Какова тарифная сетка и правила расчёта стоимости доставки, включая пороги бесплатной доставки?',
			'integration'       => 'Какие требования предъявляются к реальной интеграции со службами доставки (протоколы, авторизация, обработка ошибок)?',
			'address_dependent' => 'Как источник реальных данных о способах доставки зависит от адреса и параметров заказа?',
		),
	);

	return $contract;
}

/**
 * Alias for callers that use an order-delivery form name.
 *
 * @return array<string,mixed>
 */
function velotok_core_order_delivery_form_contract() {
	return velotok_core_checkout_delivery_form_contract();
}

/**
 * Check whether the checkout delivery form is currently mocked.
 *
 * @return bool
 */
function velotok_core_checkout_delivery_form_is_mocked() {
	$contract = velotok_core_checkout_delivery_form_contract();

	return true === $contract['mocked'];
}

/**
 * Return the delivery field definition rendered on checkout.
 *
 * @return array<string,mixed>
 */
function velotok_core_checkout_delivery_form_field() {
	$contract = velotok_core_checkout_delivery_form_contract();

	return $contract['fields']['delivery_method'];
}

/**
 * Return the delivery options rendered on checkout with the selected flag.
 *
 * @param mixed $selected Selected delivery method identifier or label.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_checkout_delivery_form_options( $selected = '' ) {
	$contract = velotok_core_checkout_delivery_form_contract();
	$resolved = '';

	if ( is_string( $selected ) && '' !== trim( $selected ) ) {
		$option = velotok_core_order_delivery_option( $selected );

		if ( is_array( $option ) ) {
			$resolved = (string) $option['id'];
		}
	}

	if ( '' === $resolved ) {
		$resolved = (string) $contract['default'];
	}

	$options = array();

	foreach ( $contract['options'] as $option ) {
		$id = isset( $option['id'] ) ? (string) $option['id'] : '';

		$option['selected'] = ( $id === $resolved );
		$option['field']    = $contract['form']['field'];
		$option['mocked']   = true;
		$option['source']   = 'stub';

		$options[] = $option;
	}

	return $options;
}

/**
 * Handle the checkout delivery form submission.
 *
 * The submitted delivery method is validated and, when valid, applied to the
 * order with its cost included in the order total. Nothing is written to the
 * database unless the caller explicitly saves the returned order afterwards.
 * The result is always marked as mocked and never contacts a provider.
 *
 * @param array<string,mixed> $request Submitted form data.
 * @param mixed               $order   Order data (array or object).
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_checkout_delivery_form_submit( $request = array(), $order = array(), $options = array() ) {
	$contract = velotok_core_checkout_delivery_form_contract();
	$field    = $contract['form']['field'];

	if ( ! is_array( $request ) ) {
		$request = array();
	}

	$method = '';

	if ( isset( $request[ $field ] ) && is_scalar( $request[ $field ] ) ) {
		$method = (string) $request[ $field ];
	} elseif ( isset( $request['delivery_method'] ) && is_scalar( $request['delivery_method'] ) ) {
		$method = (string) $request['delivery_method'];
	}

	$validation = velotok_core_validate_order_delivery_method( $method, true );

	if ( empty( $validation['valid'] ) ) {
		return array(
			'valid'        => false,
			'ok'           => false,
			'saved'        => false,
			'submitted'    => true,
			'spec'         => $contract['spec'],
			'spec_ref'     => $contract['spec_ref'],
			'source'       => $contract['source'],
			'mocked'       => true,
			'network'      => false,
			'provider'     => $contract['provider_id'],
			'form'         => $contract['form']['id'],
			'field'        => $field,
			'method'       => '',
			'delivery_method' => '',
			'code'         => isset( $validation['code'] ) ? $validation['code'] : $contract['validation']['invalid'],
			'error'        => isset( $validation['error'] ) ? $validation['error'] : null,
			'errors'       => array( isset( $validation['code'] ) ? $validation['code'] : $contract['validation']['invalid'] ),
			'options'      => velotok_core_checkout_delivery_form_options( $method ),
			'calculation'  => $validation,
		);
	}

	$selection = velotok_core_save_checkout_delivery( $order, $validation['method'], $options );

	$selection['submitted']       = true;
	$selection['form']            = $contract['form']['id'];
	$selection['field']           = $field;
	$selection['spec']            = $contract['spec'];
	$selection['spec_ref']        = $contract['spec_ref'];
	$selection['source']          = $contract['source'];
	$selection['mocked']          = true;
	$selection['network']         = false;
	$selection['provider']        = $contract['provider_id'];
	$selection['form_mocked']     = true;
	$selection['errors']          = array();
	$selection['options']         = velotok_core_checkout_delivery_form_options( $validation['method'] );

	return $selection;
}

/**
 * Alias for callers that use a submit-checkout-delivery name.
 *
 * @param array<string,mixed> $request Submitted form data.
 * @param mixed               $order   Order data (array or object).
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_submit_checkout_delivery( $request = array(), $order = array(), $options = array() ) {
	return velotok_core_checkout_delivery_form_submit( $request, $order, $options );
}

/**
 * Return the open questions that block a real checkout delivery form.
 *
 * @return array<string,string>
 */
function velotok_core_checkout_delivery_form_open_questions() {
	$contract = velotok_core_checkout_delivery_form_contract();

	return $contract['open_questions'];
}
