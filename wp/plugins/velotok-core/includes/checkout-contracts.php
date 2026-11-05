<?php
/**
 * Checkout, delivery and payments stub contract.
 *
 * This is the single access point for the checkout contract used by the site.
 * The current iteration is deliberately a fixed in-memory stub: it does not
 * read environment variables, files, the database or remote services, and it
 * never contacts YooKassa, Russian Post, CDEK or any other logistics provider.
 *
 * Future direction: real providers will be connected behind the same
 * accessors, and credentials will be read from environment variables by the
 * integration layer rather than by templates.
 *
 * Spec: clarify-096bb945.
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the checkout, delivery and payments contract.
 *
 * The contract documents:
 * - the spec identifier and the explicit `mocked` marker;
 * - the fixed YooKassa payment stub;
 * - the three available delivery methods and their fixed labels;
 * - the human content acceptance rule for the project lead role;
 * - the open integration questions that are intentionally not resolved here.
 *
 * @return array<string,mixed>
 */
function velotok_core_checkout_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$contract = array(
		'spec'     => 'clarify-096bb945',
		'source'   => 'stub',
		'mocked'   => true,
		'payment'  => array(
			'provider'       => 'yookassa',
			'provider_label' => 'ЮKassa',
			'mode'           => 'stub',
			'mocked'         => true,
			'gateway_id'     => 'velotok_yookassa_stub',
			'gateway_class'  => 'Velotok_YooKassa_Stub_Gateway',
			'open_questions' => array(
				'test_prod'   => 'Разделение тестового и боевого режимов ЮKassa.',
				'credentials' => 'Источник и формат учётных данных магазина.',
				'secrets'     => 'Процедура подстановки секретов из env в runtime.',
			),
		),
		'delivery' => array(
			'mocked'     => true,
			'field_name' => 'velotok_delivery_method',
			'meta_key'   => '_velotok_delivery_method',
			'default'    => 'postal_rf',
			'options'    => array(
				'postal_rf'    => array(
					'id'     => 'postal_rf',
					'label'  => 'Почта РФ',
					'mocked' => true,
				),
				'cdek'         => array(
					'id'     => 'cdek',
					'label'  => 'СДЭК',
					'mocked' => true,
				),
				'other_logist' => array(
					'id'     => 'other_logist',
					'label'  => 'Иной логист',
					'mocked' => true,
				),
			),
			'open_questions' => array(
				'apis'        => 'Какие API Почты РФ и СДЭК подключаются.',
				'data'        => 'Какие данные и в каком объёме передаются логистам.',
				'other'       => 'Как обрабатывается вариант «иной логист».',
				'pricing'     => 'Источники тарифов, правила расчёта и отображение стоимости доставки.',
			),
		),
		'human_acceptance' => array(
			'required'    => true,
			'mocked'      => false,
			'role'        => 'project_lead',
			'role_label'  => 'Руководитель проекта',
			'capability'  => 'velotok_accept_order',
			'accepted_by' => '_velotok_accepted_by',
			'accepted_at' => '_velotok_accepted_at',
			'rule'        => 'Контент и заказ считаются принятыми только после явного подтверждения пользователем с ролью «Руководитель проекта».',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer an explicit contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_checkout_stub_contract() {
	return velotok_core_checkout_contract();
}

/**
 * Return the payment part of the checkout contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_payment_contract() {
	$contract = velotok_core_checkout_contract();

	return $contract['payment'];
}

/**
 * Check whether the payment contract is a mock.
 *
 * @return bool
 */
function velotok_core_payment_is_mocked() {
	$payment = velotok_core_payment_contract();

	return isset( $payment['mocked'] ) && true === $payment['mocked'];
}

/**
 * Return the delivery part of the checkout contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_delivery_contract() {
	$contract = velotok_core_checkout_contract();

	return $contract['delivery'];
}

/**
 * Return all available delivery methods keyed by identifier.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_delivery_methods() {
	$delivery = velotok_core_delivery_contract();

	return $delivery['options'];
}

/**
 * Return the delivery method identifiers in contract order.
 *
 * @return string[]
 */
function velotok_core_delivery_method_ids() {
	return array_keys( velotok_core_delivery_methods() );
}

/**
 * Return one delivery method by identifier.
 *
 * @param mixed $method_id Delivery method identifier.
 * @return array<string,mixed>|null Null when the identifier is unknown.
 */
function velotok_core_delivery_method( $method_id ) {
	if ( ! is_string( $method_id ) ) {
		return null;
	}

	$methods = velotok_core_delivery_methods();

	return isset( $methods[ $method_id ] ) ? $methods[ $method_id ] : null;
}

/**
 * Return the human-readable label for a delivery method.
 *
 * @param mixed $method_id Delivery method identifier.
 * @return string Empty string when the identifier is unknown.
 */
function velotok_core_delivery_method_label( $method_id ) {
	$method = velotok_core_delivery_method( $method_id );

	return $method ? (string) $method['label'] : '';
}

/**
 * Return the default delivery method identifier.
 *
 * @return string
 */
function velotok_core_delivery_default() {
	$delivery = velotok_core_delivery_contract();

	return (string) $delivery['default'];
}

/**
 * Return the human acceptance part of the checkout contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_human_acceptance_contract() {
	$contract = velotok_core_checkout_contract();

	return $contract['human_acceptance'];
}

/**
 * Whether the site must require human content acceptance.
 *
 * @return bool
 */
function velotok_core_requires_human_acceptance() {
	$acceptance = velotok_core_human_acceptance_contract();

	return isset( $acceptance['required'] ) && true === $acceptance['required'];
}

/**
 * Whether the given user may accept content or an order.
 *
 * The check relies on a capability instead of a role name so administrators
 * and other explicitly granted users can perform the same action.
 *
 * @param object|null $user Optional user object with a has_cap() method.
 * @return bool
 */
function velotok_core_user_can_accept( $user = null ) {
	$acceptance = velotok_core_human_acceptance_contract();
	$capability = isset( $acceptance['capability'] ) ? (string) $acceptance['capability'] : '';

	if ( '' === $capability ) {
		return false;
	}

	if ( null === $user && function_exists( 'wp_get_current_user' ) ) {
		$user = wp_get_current_user();
	}

	if ( is_object( $user ) && method_exists( $user, 'has_cap' ) ) {
		return (bool) $user->has_cap( $capability );
	}

	return false;
}
