<?php
/**
 * Checkout, delivery and payment stub contracts.
 *
 * The contract fixes the checkout rules for the current iteration:
 * - delivery can be one of Russian Post, CDEK or another logistics provider;
 * - payment is performed through YooKassa;
 * - content acceptance requires a human with the project manager role.
 *
 * Payment and delivery integrations are deliberately mocked. This file is the
 * single in-memory source for the contract and never reads environment
 * variables, files, the database or remote services.
 *
 * Spec: clarify-096bb945.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

/**
 * Lowercase a UTF-8 string with a byte fallback.
 *
 * @param string $value Value.
 * @return string
 */
function velotok_core_checkout_lowercase( $value ) {
	if ( function_exists( 'mb_strtolower' ) ) {
		return (string) mb_strtolower( $value, 'UTF-8' );
	}

	$map = array(
		'А' => 'а', 'Б' => 'б', 'В' => 'в', 'Г' => 'г', 'Д' => 'д', 'Е' => 'е', 'Ё' => 'ё',
		'Ж' => 'ж', 'З' => 'з', 'И' => 'и', 'Й' => 'й', 'К' => 'к', 'Л' => 'л', 'М' => 'м',
		'Н' => 'н', 'О' => 'о', 'П' => 'п', 'Р' => 'р', 'С' => 'с', 'Т' => 'т', 'У' => 'у',
		'Ф' => 'ф', 'Х' => 'х', 'Ц' => 'ц', 'Ч' => 'ч', 'Ш' => 'ш', 'Щ' => 'щ', 'Ъ' => 'ъ',
		'Ы' => 'ы', 'Ь' => 'ь', 'Э' => 'э', 'Ю' => 'ю', 'Я' => 'я',
	);

	return strtr( strtolower( $value ), $map );
}

/**
 * Return the checkout contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_checkout_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$delivery_methods = array(
		'postal_rf' => array(
			'id'              => 'postal_rf',
			'label'           => 'Почта РФ',
			'provider'        => 'russian_post',
			'provider_label'  => 'Почта России',
			'mocked'          => true,
			'enabled'         => true,
			'requires_custom' => false,
			'cost'            => null,
			'cost_source'     => 'open_question',
			'aliases'         => array( 'post', 'post_rf', 'pochta', 'pochta_rf', 'russian_post', 'почта рф', 'почта россии' ),
		),
		'cdek'      => array(
			'id'              => 'cdek',
			'label'           => 'СДЭК',
			'provider'        => 'cdek',
			'provider_label'  => 'СДЭК',
			'mocked'          => true,
			'enabled'         => true,
			'requires_custom' => false,
			'cost'            => null,
			'cost_source'     => 'open_question',
			'aliases'         => array( 'sdek', 'сдэк' ),
		),
		'other'     => array(
			'id'              => 'other',
			'label'           => 'Иной логист',
			'provider'        => 'other',
			'provider_label'  => 'Иной логист',
			'mocked'          => true,
			'enabled'         => true,
			'requires_custom' => true,
			'cost'            => null,
			'cost_source'     => 'open_question',
			'aliases'         => array( 'other_logistics', 'custom', 'иной логист' ),
		),
	);

	$payment_methods = array(
		'yookassa' => array(
			'id'             => 'yookassa',
			'label'          => 'ЮKassa',
			'provider'       => 'yookassa',
			'provider_label' => 'ЮKassa',
			'mocked'         => true,
			'enabled'        => true,
			'modes'          => array( 'test', 'prod' ),
			'default_mode'   => 'test',
			'mode'           => 'test',
			'credentials'    => 'env',
			'env'            => array(
				'shop_id'    => 'VELOTOK_YOOKASSA_SHOP_ID',
				'secret_key' => 'VELOTOK_YOOKASSA_SECRET_KEY',
				'mode'       => 'VELOTOK_YOOKASSA_MODE',
			),
			'aliases'        => array( 'yoo_kassa', 'yookassa_ru', 'юkassa', 'юкасса' ),
		),
	);

	$content_acceptance = array(
		'mocked'          => true,
		'required'        => true,
		'human_required'  => true,
		'role'            => 'project_manager',
		'role_label'      => 'руководитель проекта',
		'capability'      => 'velotok_accept_content',
		'auto_approve'    => false,
		'initial_status'  => 'pending',
		'approved_status' => 'approved',
		'rejected_status' => 'rejected',
	);

	$contract = array(
		'spec'                    => 'clarify-096bb945',
		'source'                  => 'stub',
		'mocked'                  => true,
		'future_source'           => 'woocommerce',
		'delivery_methods'        => $delivery_methods,
		'delivery_options'        => array_values( $delivery_methods ),
		'delivery_method_ids'     => array_keys( $delivery_methods ),
		'delivery_method_aliases' => array(
			'post'         => 'postal_rf',
			'post_rf'      => 'postal_rf',
			'pochta'       => 'postal_rf',
			'pochta_rf'    => 'postal_rf',
			'russian_post' => 'postal_rf',
			'sdek'         => 'cdek',
			'other_logistics' => 'other',
			'custom'       => 'other',
		),
		'default_delivery_method' => '',
		'delivery'                => array(
			'required'      => true,
			'allow_custom'  => true,
			'methods'       => $delivery_methods,
			'missing_error' => 'DELIVERY_METHOD_REQUIRED',
			'invalid_error' => 'DELIVERY_METHOD_UNSUPPORTED',
			'cost_source'   => 'open_question',
			'cost_display'  => 'after_manager_confirmation',
		),
		'payment_methods'         => $payment_methods,
		'payment_options'         => array_values( $payment_methods ),
		'payment_method_ids'      => array_keys( $payment_methods ),
		'payment_method_aliases'  => array(
			'yoo_kassa'    => 'yookassa',
			'yookassa_ru'  => 'yookassa',
		),
		'default_payment_method'  => 'yookassa',
		'payment'                 => array(
			'required'      => true,
			'methods'       => $payment_methods,
			'missing_error' => 'PAYMENT_METHOD_REQUIRED',
			'invalid_error' => 'PAYMENT_METHOD_UNSUPPORTED',
			'provider'      => 'yookassa',
		),
		'content_acceptance'      => $content_acceptance,
		'open_questions'          => array(
			'yookassa_modes' => 'Тестовый и боевой режимы ЮKassa: разделение test/prod, источник и формат учётных данных магазина, процедура подстановки секретов из env в runtime.',
			'delivery_apis'  => 'Интеграция со службами доставки: API Почты РФ и СДЭК, объём передаваемых данных и обработка варианта «иной логист».',
			'delivery_cost'  => 'Стоимость доставки: источники тарифов, правила расчёта и отображение стоимости до подключения реальных интеграций.',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer an explicit contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_checkout_delivery_payment_contract() {
	return velotok_core_checkout_contract();
}

/**
 * Return the delivery and payment contract data.
 *
 * @return array<string,mixed>
 */
function velotok_core_delivery_contract() {
	return velotok_core_checkout_contract();
}

/**
 * Return the payment contract data.
 *
 * @return array<string,mixed>
 */
function velotok_core_payment_contract() {
	return velotok_core_checkout_contract();
}

/**
 * Return the content acceptance contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_content_acceptance_contract() {
	$contract = velotok_core_checkout_contract();

	return $contract['content_acceptance'];
}

/**
 * Return all delivery methods declared by the contract.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_delivery_methods() {
	$contract = velotok_core_checkout_contract();

	return $contract['delivery_methods'];
}

/**
 * Alias for callers that use a shorter name.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_get_delivery_methods() {
	return velotok_core_delivery_methods();
}

/**
 * Return the delivery options as a list.
 *
 * @return array<int,array<string,mixed>>
 */
function velotok_core_delivery_options() {
	return array_values( velotok_core_delivery_methods() );
}

/**
 * Alias for callers that use the option terminology.
 *
 * @return array<int,array<string,mixed>>
 */
function velotok_core_get_delivery_options() {
	return velotok_core_delivery_options();
}

/**
 * Return the canonical delivery method ids.
 *
 * @return string[]
 */
function velotok_core_delivery_method_ids() {
	return array_keys( velotok_core_delivery_methods() );
}

/**
 * Normalize a delivery method id or alias.
 *
 * @param mixed $method Delivery method id or alias.
 * @return string Canonical id or an empty string.
 */
function velotok_core_normalize_delivery_method( $method ) {
	if ( ! is_string( $method ) ) {
		return '';
	}

	$method = velotok_core_checkout_lowercase( trim( $method ) );

	foreach ( velotok_core_delivery_methods() as $id => $data ) {
		if ( $method === $id || ( isset( $data['provider'] ) && $method === $data['provider'] ) ) {
			return $id;
		}

		if ( isset( $data['aliases'] ) && in_array( $method, $data['aliases'], true ) ) {
			return $id;
		}
	}

	return '';
}

/**
 * Return one delivery method by id or alias.
 *
 * @param mixed $method Delivery method id or alias.
 * @return array<string,mixed>|null
 */
function velotok_core_delivery_method( $method ) {
	$id = velotok_core_normalize_delivery_method( $method );

	if ( '' === $id ) {
		return null;
	}

	$methods = velotok_core_delivery_methods();

	return $methods[ $id ];
}

/**
 * Check whether a delivery method is supported by the contract.
 *
 * @param mixed $method Delivery method id or alias.
 * @return bool
 */
function velotok_core_is_valid_delivery_method( $method ) {
	return null !== velotok_core_delivery_method( $method );
}

/**
 * Validate a delivery method selection.
 *
 * @param mixed $method Delivery method id or alias.
 * @return array<string,mixed>
 */
function velotok_core_validate_delivery_method( $method ) {
	$id = velotok_core_normalize_delivery_method( $method );

	if ( null === $method || ( is_string( $method ) && '' === trim( $method ) ) ) {
		return array(
			'valid'  => false,
			'code'   => 'DELIVERY_METHOD_REQUIRED',
			'error'  => 'DELIVERY_METHOD_REQUIRED',
			'method' => $method,
			'mocked' => true,
		);
	}

	if ( '' === $id ) {
		return array(
			'valid'  => false,
			'code'   => 'DELIVERY_METHOD_UNSUPPORTED',
			'error'  => 'DELIVERY_METHOD_UNSUPPORTED',
			'method' => $method,
			'mocked' => true,
		);
	}

	return array(
		'valid'      => true,
		'code'       => null,
		'error'      => null,
		'method'     => $id,
		'definition' => velotok_core_delivery_method( $id ),
		'mocked'     => true,
	);
}

/**
 * Return all payment methods declared by the contract.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_payment_methods() {
	$contract = velotok_core_checkout_contract();

	return $contract['payment_methods'];
}

/**
 * Alias for callers that use payment gateway terminology.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_payment_gateways() {
	return velotok_core_payment_methods();
}

/**
 * Return the payment options as a list.
 *
 * @return array<int,array<string,mixed>>
 */
function velotok_core_payment_options() {
	return array_values( velotok_core_payment_methods() );
}

/**
 * Alias for callers that use the option terminology.
 *
 * @return array<int,array<string,mixed>>
 */
function velotok_core_get_payment_options() {
	return velotok_core_payment_options();
}

/**
 * Return the canonical payment method ids.
 *
 * @return string[]
 */
function velotok_core_payment_method_ids() {
	return array_keys( velotok_core_payment_methods() );
}

/**
 * Normalize a payment method id or alias.
 *
 * @param mixed $method Payment method id or alias.
 * @return string Canonical id or an empty string.
 */
function velotok_core_normalize_payment_method( $method ) {
	if ( ! is_string( $method ) ) {
		return '';
	}

	$method = velotok_core_checkout_lowercase( trim( $method ) );

	foreach ( velotok_core_payment_methods() as $id => $data ) {
		if ( $method === $id || ( isset( $data['provider'] ) && $method === $data['provider'] ) ) {
			return $id;
		}

		if ( isset( $data['aliases'] ) && in_array( $method, $data['aliases'], true ) ) {
			return $id;
		}
	}

	return '';
}

/**
 * Return one payment method by id or alias.
 *
 * @param mixed $method Payment method id or alias.
 * @return array<string,mixed>|null
 */
function velotok_core_payment_method( $method ) {
	$id = velotok_core_normalize_payment_method( $method );

	if ( '' === $id ) {
		return null;
	}

	$methods = velotok_core_payment_methods();

	return $methods[ $id ];
}

/**
 * Check whether a payment method is supported by the contract.
 *
 * @param mixed $method Payment method id or alias.
 * @return bool
 */
function velotok_core_is_valid_payment_method( $method ) {
	return null !== velotok_core_payment_method( $method );
}

/**
 * Validate a payment method selection.
 *
 * @param mixed $method Payment method id or alias.
 * @return array<string,mixed>
 */
function velotok_core_validate_payment_method( $method ) {
	$id = velotok_core_normalize_payment_method( $method );

	if ( null === $method || ( is_string( $method ) && '' === trim( $method ) ) ) {
		return array(
			'valid'  => false,
			'code'   => 'PAYMENT_METHOD_REQUIRED',
			'error'  => 'PAYMENT_METHOD_REQUIRED',
			'method' => $method,
			'mocked' => true,
		);
	}

	if ( '' === $id ) {
		return array(
			'valid'  => false,
			'code'   => 'PAYMENT_METHOD_UNSUPPORTED',
			'error'  => 'PAYMENT_METHOD_UNSUPPORTED',
			'method' => $method,
			'mocked' => true,
		);
	}

	return array(
		'valid'      => true,
		'code'       => null,
		'error'      => null,
		'method'     => $id,
		'definition' => velotok_core_payment_method( $id ),
		'mocked'     => true,
	);
}

/**
 * Check whether content acceptance requires a human decision.
 *
 * @return bool
 */
function velotok_core_content_requires_human_acceptance() {
	$contract = velotok_core_content_acceptance_contract();

	return ! empty( $contract['required'] ) && ! empty( $contract['human_required'] ) && empty( $contract['auto_approve'] );
}

/**
 * Check whether a user role can accept content.
 *
 * @param mixed $role      User role.
 * @param bool  $is_human  Whether the actor is a human.
 * @return bool
 */
function velotok_core_can_accept_content( $role, $is_human = true ) {
	$contract = velotok_core_content_acceptance_contract();

	return true === $is_human && is_string( $role ) && $contract['role'] === velotok_core_checkout_lowercase( trim( $role ) );
}

if ( ! function_exists( 'getDeliveryMethods' ) ) {
	/**
	 * Short global alias for the delivery method directory.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function getDeliveryMethods() {
		return velotok_core_delivery_methods();
	}
}

if ( ! function_exists( 'validateDeliveryMethod' ) ) {
	/**
	 * Short global alias for delivery method validation.
	 *
	 * @param mixed $method Delivery method id or alias.
	 * @return array<string,mixed>
	 */
	function validateDeliveryMethod( $method ) {
		return velotok_core_validate_delivery_method( $method );
	}
}

if ( ! function_exists( 'getPaymentMethods' ) ) {
	/**
	 * Short global alias for the payment method directory.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function getPaymentMethods() {
		return velotok_core_payment_methods();
	}
}
