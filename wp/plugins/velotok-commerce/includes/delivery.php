<?php
/**
 * Delivery contract (mocked stub).
 *
 * @package Velotok_Commerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Describe the delivery contract.
 *
 * @return array<string, mixed>
 */
function velotok_commerce_delivery_contract() {
	return array(
		'mocked'         => true,
		'methods'        => velotok_commerce_delivery_methods(),
		'cost'           => array(
			'calculated' => false,
			'mocked'     => true,
			'source'     => null,
		),
		'open_questions' => array(
			__( 'Какие API Почты России и СДЭК подключаются и какие данные передаются.', 'velotok-commerce' ),
			__( 'Как обрабатывается вариант «иной логист» и кто его согласует.', 'velotok-commerce' ),
			__( 'Источники тарифов, правила расчёта и отображение стоимости доставки до подключения интеграций.', 'velotok-commerce' ),
		),
	);
}

/**
 * Available delivery methods.
 *
 * @return array<string, array<string, mixed>>
 */
function velotok_commerce_delivery_methods() {
	return array(
		'pochta' => array(
			'id'          => 'pochta',
			'provider'    => 'russian_post',
			'title'       => __( 'Почта России', 'velotok-commerce' ),
			'description' => __( 'Доставка Почтой России. Стоимость будет рассчитана после подключения тарифов.', 'velotok-commerce' ),
			'enabled'     => true,
			'mocked'      => true,
		),
		'cdek'   => array(
			'id'          => 'cdek',
			'provider'    => 'cdek',
			'title'       => __( 'СДЭК', 'velotok-commerce' ),
			'description' => __( 'Доставка СДЭК. Стоимость будет рассчитана после подключения тарифов.', 'velotok-commerce' ),
			'enabled'     => true,
			'mocked'      => true,
		),
		'other'  => array(
			'id'          => 'other',
			'provider'    => 'custom',
			'title'       => __( 'Иной логист', 'velotok-commerce' ),
			'description' => __( 'Логиста и стоимость согласует менеджер после оформления заказа.', 'velotok-commerce' ),
			'enabled'     => true,
			'mocked'      => true,
		),
	);
}

/**
 * Find a delivery method by ID.
 *
 * @param string $method_id Delivery method ID.
 * @return array<string, mixed>|null
 */
function velotok_commerce_delivery_method( $method_id ) {
	$methods   = velotok_commerce_delivery_methods();
	$method_id = sanitize_key( (string) $method_id );

	if ( '' === $method_id || ! isset( $methods[ $method_id ] ) ) {
		return null;
	}

	return $methods[ $method_id ];
}

/**
 * Delivery options for a checkout select.
 *
 * @return array<string, string>
 */
function velotok_commerce_delivery_options() {
	$options = array();

	foreach ( velotok_commerce_delivery_methods() as $method ) {
		if ( ! empty( $method['enabled'] ) ) {
			$options[ $method['id'] ] = $method['title'];
		}
	}

	return $options;
}

/**
 * Whether every delivery method is mocked.
 *
 * @return bool
 */
function velotok_commerce_delivery_is_mocked() {
	foreach ( velotok_commerce_delivery_methods() as $method ) {
		if ( empty( $method['mocked'] ) ) {
			return false;
		}
	}

	return true;
}
