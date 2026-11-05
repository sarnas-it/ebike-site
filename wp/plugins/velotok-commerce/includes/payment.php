<?php
/**
 * YuKassa payment contract (mocked stub).
 *
 * @package Velotok_Commerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Describe the YuKassa payment contract.
 *
 * The contract is intentionally mocked: no network requests are made and no
 * real payment is captured until the provider integration is implemented.
 *
 * @return array<string, mixed>
 */
function velotok_commerce_payment_contract() {
	return array(
		'provider'          => 'yookassa',
		'title'             => __( 'ЮKassa', 'velotok-commerce' ),
		'gateway_id'        => 'velotok_yookassa',
		'mocked'            => true,
		'default_mode'      => 'test',
		'modes'             => array(
			'test' => array(
				'label'             => __( 'Тестовый режим', 'velotok-commerce' ),
				'enabled'           => true,
				'mocked'            => true,
				'credentials_source' => 'env',
			),
			'prod' => array(
				'label'             => __( 'Боевой режим', 'velotok-commerce' ),
				'enabled'           => false,
				'mocked'            => true,
				'credentials_source' => 'env',
			),
		),
		'credentials_env'   => array(
			'shop_id'    => 'VELOTOK_YOOKASSA_SHOP_ID',
			'secret_key' => 'VELOTOK_YOOKASSA_SECRET_KEY',
		),
		'currency'          => 'RUB',
		'capture'           => true,
		'confirmation'      => 'redirect',
		'webhook'           => array(
			'enabled' => false,
			'mocked'  => true,
			'path'    => '/velotok/yookassa/webhook',
		),
		'open_questions'    => array(
			__( 'Разделение тестового и боевого режимов, источник и формат учётных данных магазина.', 'velotok-commerce' ),
			__( 'Процедура подстановки секретов из env в runtime без попадания в репозиторий.', 'velotok-commerce' ),
			__( 'Формат и проверка подписи вебхуков ЮKassa.', 'velotok-commerce' ),
		),
	);
}

/**
 * Whether the YuKassa integration is currently mocked.
 *
 * @return bool
 */
function velotok_commerce_payment_is_mocked() {
	$contract = velotok_commerce_payment_contract();

	return ! empty( $contract['mocked'] );
}

/**
 * Register the YuKassa gateway stub with WooCommerce.
 *
 * @param array<int, string> $gateways Gateway class names.
 * @return array<int, string>
 */
function velotok_commerce_register_payment_gateway( $gateways ) {
	velotok_commerce_load_payment_gateway();

	if ( class_exists( 'Velotok_Commerce_Gateway_Yookassa' ) && ! in_array( 'Velotok_Commerce_Gateway_Yookassa', $gateways, true ) ) {
		$gateways[] = 'Velotok_Commerce_Gateway_Yookassa';
	}

	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'velotok_commerce_register_payment_gateway' );
