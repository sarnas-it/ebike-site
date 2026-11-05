<?php
/**
 * Human content acceptance by the project manager role.
 *
 * @package Velotok_Commerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Describe the content acceptance contract.
 *
 * @return array<string, mixed>
 */
function velotok_commerce_content_acceptance_contract() {
	return array(
		'required'           => true,
		'mocked'             => false,
		'human_only'         => true,
		'automation_allowed' => false,
		'role'               => 'velotok_project_manager',
		'role_name'          => __( 'Руководитель проекта', 'velotok-commerce' ),
		'capability'         => 'velotok_accept_content',
		'statuses'           => array( 'pending', 'accepted', 'rejected' ),
		'default_status'     => 'pending',
	);
}

/**
 * Register the project manager role and its capability.
 */
function velotok_commerce_register_content_roles() {
	$contract   = velotok_commerce_content_acceptance_contract();
	$capability = $contract['capability'];
	$role       = get_role( $contract['role'] );

	if ( ! $role ) {
		add_role(
			$contract['role'],
			$contract['role_name'],
			array(
				'read'        => true,
				$capability   => true,
			)
		);
	} else {
		$role->add_cap( $capability );
	}

	$administrator = get_role( 'administrator' );

	if ( $administrator ) {
		$administrator->add_cap( $capability );
	}
}
add_action( 'init', 'velotok_commerce_register_content_roles', 5 );

/**
 * Whether a real user may accept content.
 *
 * @param int $user_id User ID. Defaults to the current user.
 * @return bool
 */
function velotok_commerce_user_can_accept_content( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();

	if ( $user_id <= 0 ) {
		return false;
	}

	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return false;
	}

	$contract = velotok_commerce_content_acceptance_contract();

	return user_can( $user, $contract['capability'] );
}

/**
 * Mark a new order as requiring human content acceptance.
 *
 * @param WC_Order $order Order object.
 */
function velotok_commerce_require_order_content_acceptance( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	if ( '' !== (string) $order->get_meta( '_velotok_content_acceptance_status' ) ) {
		return;
	}

	$contract = velotok_commerce_content_acceptance_contract();

	$order->update_meta_data( '_velotok_content_acceptance_status', $contract['default_status'] );
	$order->update_meta_data( '_velotok_content_acceptance_required', true );
}
add_action( 'woocommerce_checkout_create_order', 'velotok_commerce_require_order_content_acceptance', 20, 1 );

/**
 * Read the content acceptance status of an order.
 *
 * @param int $order_id Order ID.
 * @return string
 */
function velotok_commerce_order_content_acceptance_status( $order_id ) {
	$contract = velotok_commerce_content_acceptance_contract();
	$status   = '';

	if ( function_exists( 'wc_get_order' ) ) {
		$order = wc_get_order( (int) $order_id );

		if ( $order instanceof WC_Order ) {
			$status = (string) $order->get_meta( '_velotok_content_acceptance_status' );
		}
	}

	if ( '' === $status ) {
		$status = (string) get_post_meta( (int) $order_id, '_velotok_content_acceptance_status', true );
	}

	return in_array( $status, $contract['statuses'], true ) ? $status : $contract['default_status'];
}

/**
 * Update the content acceptance status of an order.
 *
 * @param WC_Order $order   Order object.
 * @param string   $status  New status.
 * @param int      $user_id User ID that performed the acceptance.
 * @return true|WP_Error
 */
function velotok_commerce_set_order_content_status( $order, $status, $user_id ) {
	$contract = velotok_commerce_content_acceptance_contract();

	if ( ! $order instanceof WC_Order ) {
		return new WP_Error( 'velotok_content_acceptance_order_not_found', __( 'Заказ не найден.', 'velotok-commerce' ) );
	}

	if ( ! in_array( $status, array( 'accepted', 'rejected' ), true ) ) {
		return new WP_Error( 'velotok_content_acceptance_bad_status', __( 'Недопустимый статус приёмки контента.', 'velotok-commerce' ) );
	}

	if ( ! velotok_commerce_user_can_accept_content( $user_id ) ) {
		return new WP_Error( 'velotok_content_acceptance_forbidden', __( 'Принять контент может только человек с ролью «Руководитель проекта».', 'velotok-commerce' ) );
	}

	$user = get_userdata( (int) $user_id );
	$name = $user ? $user->display_name : sprintf( 'ID %d', (int) $user_id );

	$order->update_meta_data( '_velotok_content_acceptance_status', $status );
	$order->update_meta_data( '_velotok_content_acceptance_required', true );
	$order->update_meta_data( '_velotok_content_acceptance_user_id', (int) $user_id );
	$order->update_meta_data( '_velotok_content_acceptance_at', current_time( 'mysql', true ) );

	if ( 'accepted' === $status ) {
		$order->add_order_note(
			sprintf(
				/* translators: %s: project manager display name. */
				__( 'Приёмка контента подтверждена человеком с ролью «Руководитель проекта»: %s.', 'velotok-commerce' ),
				$name
			)
		);
	} else {
		$order->add_order_note(
			sprintf(
				/* translators: %s: project manager display name. */
				__( 'Приёмка контента отклонена человеком с ролью «Руководитель проекта»: %s.', 'velotok-commerce' ),
				$name
			)
		);
	}

	$order->save();

	return true;
}

/**
 * Accept order content.
 *
 * @param int $order_id Order ID.
 * @param int $user_id  User ID. Defaults to the current user.
 * @return true|WP_Error
 */
function velotok_commerce_accept_order_content( $order_id, $user_id = 0 ) {
	$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order_id ) : null;

	return velotok_commerce_set_order_content_status(
		$order,
		'accepted',
		$user_id ? (int) $user_id : get_current_user_id()
	);
}

/**
 * Reject order content.
 *
 * @param int $order_id Order ID.
 * @param int $user_id  User ID. Defaults to the current user.
 * @return true|WP_Error
 */
function velotok_commerce_reject_order_content( $order_id, $user_id = 0 ) {
	$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order_id ) : null;

	return velotok_commerce_set_order_content_status(
		$order,
		'rejected',
		$user_id ? (int) $user_id : get_current_user_id()
	);
}

/**
 * Add content acceptance actions to the WooCommerce order screen.
 *
 * @param array<string, string> $actions Existing actions.
 * @return array<string, string>
 */
function velotok_commerce_order_actions( $actions ) {
	if ( ! velotok_commerce_user_can_accept_content() ) {
		return $actions;
	}

	$actions['velotok_accept_content'] = __( 'Подтвердить приёмку контента', 'velotok-commerce' );
	$actions['velotok_reject_content'] = __( 'Отклонить приёмку контента', 'velotok-commerce' );

	return $actions;
}
add_filter( 'woocommerce_order_actions', 'velotok_commerce_order_actions' );

/**
 * Handle the accept content order action.
 *
 * @param WC_Order $order Order object.
 */
function velotok_commerce_handle_accept_content_action( $order ) {
	$result = velotok_commerce_accept_order_content( $order->get_id(), get_current_user_id() );

	if ( is_wp_error( $result ) ) {
		$order->add_order_note( $result->get_error_message() );
		$order->save();
	}
}
add_action( 'woocommerce_order_action_velotok_accept_content', 'velotok_commerce_handle_accept_content_action' );

/**
 * Handle the reject content order action.
 *
 * @param WC_Order $order Order object.
 */
function velotok_commerce_handle_reject_content_action( $order ) {
	$result = velotok_commerce_reject_order_content( $order->get_id(), get_current_user_id() );

	if ( is_wp_error( $result ) ) {
		$order->add_order_note( $result->get_error_message() );
		$order->save();
	}
}
add_action( 'woocommerce_order_action_velotok_reject_content', 'velotok_commerce_handle_reject_content_action' );

/**
 * Display the content acceptance status on the order screen.
 *
 * @param WC_Order $order Order object.
 */
function velotok_commerce_admin_order_content_status( $order ) {
	$contract = velotok_commerce_content_acceptance_contract();
	$status   = velotok_commerce_order_content_acceptance_status( $order->get_id() );
	$labels   = array(
		'pending'  => __( 'ожидает приёмки', 'velotok-commerce' ),
		'accepted' => __( 'принят', 'velotok-commerce' ),
		'rejected' => __( 'отклонён', 'velotok-commerce' ),
	);

	echo '<p><strong>' . esc_html__( 'Приёмка контента:', 'velotok-commerce' ) . '</strong> ';
	echo esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $status );
	echo '</p>';

	if ( empty( $contract['automation_allowed'] ) ) {
		echo '<p><em>' . esc_html__( 'Подтвердить приёмку может только человек с ролью «Руководитель проекта».', 'velotok-commerce' ) . '</em></p>';
	}
}
add_action( 'woocommerce_admin_order_data_after_order_details', 'velotok_commerce_admin_order_content_status' );
