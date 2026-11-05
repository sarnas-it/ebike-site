<?php
/**
 * Checkout integration for the mocked delivery contract.
 *
 * @package Velotok_Commerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the delivery method select to checkout.
 *
 * @param array<string, array<string, mixed>> $fields Checkout fields.
 * @return array<string, array<string, mixed>>
 */
function velotok_commerce_checkout_delivery_field( $fields ) {
	$options = velotok_commerce_delivery_options();

	if ( empty( $options ) ) {
		return $fields;
	}

	$fields['shipping']['velotok_delivery_method'] = array(
		'type'        => 'select',
		'label'       => __( 'Способ доставки', 'velotok-commerce' ),
		'description' => __( 'Стоимость доставки будет рассчитана после подключения тарифов служб.', 'velotok-commerce' ),
		'required'    => true,
		'options'     => $options,
		'class'       => array( 'form-row-wide', 'velotok-delivery-method' ),
		'priority'    => 90,
	);

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'velotok_commerce_checkout_delivery_field', 20 );

/**
 * Require a valid delivery method during checkout.
 *
 * @param array    $data   Posted checkout data.
 * @param WP_Error $errors Validation errors.
 */
function velotok_commerce_checkout_validate_delivery( $data, $errors ) {
	$method_id = isset( $_POST['velotok_delivery_method'] ) ? sanitize_key( wp_unslash( $_POST['velotok_delivery_method'] ) ) : '';

	if ( ! velotok_commerce_delivery_method( $method_id ) ) {
		$errors->add(
			'velotok_delivery_method',
			__( 'Выберите способ доставки: Почта России, СДЭК или иной логист.', 'velotok-commerce' )
		);
	}
}
add_action( 'woocommerce_after_checkout_validation', 'velotok_commerce_checkout_validate_delivery', 10, 2 );

/**
 * Persist the chosen delivery method on the order.
 *
 * @param WC_Order $order Order object.
 * @param array    $data  Posted checkout data.
 */
function velotok_commerce_checkout_save_delivery( $order, $data ) {
	$method_id = isset( $_POST['velotok_delivery_method'] ) ? sanitize_key( wp_unslash( $_POST['velotok_delivery_method'] ) ) : '';
	$method    = velotok_commerce_delivery_method( $method_id );

	if ( ! $method ) {
		return;
	}

	$order->update_meta_data( '_velotok_delivery_method', $method['id'] );
	$order->update_meta_data( '_velotok_delivery_provider', $method['provider'] );
	$order->update_meta_data( '_velotok_delivery_title', $method['title'] );
	$order->update_meta_data( '_velotok_delivery_mocked', true );
}
add_action( 'woocommerce_checkout_create_order', 'velotok_commerce_checkout_save_delivery', 10, 2 );

/**
 * Show the chosen delivery method on the order screen.
 *
 * @param WC_Order $order Order object.
 */
function velotok_commerce_admin_order_delivery_info( $order ) {
	$method_id = (string) $order->get_meta( '_velotok_delivery_method' );

	if ( '' === $method_id ) {
		return;
	}

	$method = velotok_commerce_delivery_method( $method_id );
	$title  = $method ? $method['title'] : $method_id;

	echo '<p><strong>' . esc_html__( 'Способ доставки:', 'velotok-commerce' ) . '</strong> ' . esc_html( $title );

	if ( $order->get_meta( '_velotok_delivery_mocked' ) ) {
		echo ' <em>' . esc_html__( '(заглушка, тариф не рассчитан)', 'velotok-commerce' ) . '</em>';
	}

	echo '</p>';
}
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'velotok_commerce_admin_order_delivery_info' );

/**
 * Show the mocked payment details on the order screen.
 *
 * @param WC_Order $order Order object.
 */
function velotok_commerce_admin_order_payment_info( $order ) {
	if ( ! $order->get_meta( '_velotok_payment_mocked' ) ) {
		return;
	}

	$provider = (string) $order->get_meta( '_velotok_payment_provider' );
	$mode     = (string) $order->get_meta( '_velotok_payment_mode' );

	echo '<p><strong>' . esc_html__( 'Оплата:', 'velotok-commerce' ) . '</strong> ';
	echo esc_html( 'yookassa' === $provider ? __( 'ЮKassa', 'velotok-commerce' ) : $provider );
	echo ' <em>' . esc_html__( '(заглушка, реальный платёж не выполнялся)', 'velotok-commerce' ) . '</em>';

	if ( '' !== $mode ) {
		echo '<br><span>' . esc_html__( 'Режим:', 'velotok-commerce' ) . ' ' . esc_html( $mode ) . '</span>';
	}

	echo '</p>';
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'velotok_commerce_admin_order_payment_info' );
