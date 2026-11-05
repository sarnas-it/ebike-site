<?php
/**
 * WooCommerce integration for the mocked checkout contract.
 *
 * Everything in this file is a stub: the delivery methods and the YooKassa
 * gateway are explicitly marked as mocked, and no request is ever sent to a
 * real payment or logistics provider.
 *
 * Spec: clarify-096bb945.
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the posted delivery method or the contract default.
 *
 * @return string
 */
function velotok_core_get_selected_delivery_method() {
	$delivery = velotok_core_delivery_contract();
	$field    = isset( $delivery['field_name'] ) ? (string) $delivery['field_name'] : 'velotok_delivery_method';

	if ( isset( $_POST[ $field ] ) ) {
		$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );

		if ( velotok_core_delivery_method( $value ) ) {
			return $value;
		}
	}

	return velotok_core_delivery_default();
}

/**
 * Render the delivery method selector on the classic checkout page.
 *
 * @return void
 */
function velotok_core_render_delivery_methods() {
	$methods  = velotok_core_delivery_methods();
	$field    = velotok_core_delivery_contract()['field_name'];
	$selected = velotok_core_get_selected_delivery_method();

	if ( empty( $methods ) ) {
		return;
	}
	?>
	<fieldset class="velotok-delivery-methods">
		<legend><?php esc_html_e( 'Способ доставки', 'velotok-core' ); ?></legend>
		<?php foreach ( $methods as $method_id => $method ) : ?>
			<label class="velotok-delivery-option">
				<input type="radio" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $method_id ); ?>" <?php checked( $selected, $method_id ); ?> />
				<span><?php echo esc_html( $method['label'] ); ?></span>
				<span class="velotok-delivery-mocked"><?php esc_html_e( 'заглушка (mocked: true)', 'velotok-core' ); ?></span>
			</label>
		<?php endforeach; ?>
		<p class="cart-note"><?php esc_html_e( 'Способ доставки сохраняется в заказе. Реальные API Почты РФ, СДЭК и иного логиста пока не подключены.', 'velotok-core' ); ?></p>
	</fieldset>
	<?php
}
add_action( 'woocommerce_review_order_before_payment', 'velotok_core_render_delivery_methods', 5 );

/**
 * Validate the selected delivery method.
 *
 * @return void
 */
function velotok_core_validate_delivery_method() {
	$delivery = velotok_core_delivery_contract();
	$field    = isset( $delivery['field_name'] ) ? (string) $delivery['field_name'] : 'velotok_delivery_method';

	if ( ! isset( $_POST[ $field ] ) || '' === $_POST[ $field ] ) {
		wc_add_notice( __( 'Выберите способ доставки.', 'velotok-core' ), 'error' );
		return;
	}

	$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );

	if ( ! velotok_core_delivery_method( $value ) ) {
		wc_add_notice( __( 'Выбран неизвестный способ доставки.', 'velotok-core' ), 'error' );
	}
}
add_action( 'woocommerce_checkout_process', 'velotok_core_validate_delivery_method' );

/**
 * Persist the selected delivery method on the order.
 *
 * @param WC_Order $order Order object.
 * @return void
 */
function velotok_core_save_delivery_method( $order ) {
	if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) {
		return;
	}

	$method_id = velotok_core_get_selected_delivery_method();

	$order->update_meta_data( velotok_core_delivery_contract()['meta_key'], $method_id );
	$order->update_meta_data( '_velotok_delivery_method_label', velotok_core_delivery_method_label( $method_id ) );
	$order->update_meta_data( '_velotok_checkout_mocked', 'yes' );
	$order->update_meta_data( '_velotok_payment_provider', velotok_core_payment_contract()['provider'] );
	$order->update_meta_data( '_velotok_requires_acceptance', velotok_core_requires_human_acceptance() ? 'yes' : 'no' );
}
add_action( 'woocommerce_checkout_create_order', 'velotok_core_save_delivery_method', 10, 1 );

/**
 * Show the delivery method in the WooCommerce admin order screen.
 *
 * @param WC_Order $order Order object.
 * @return void
 */
function velotok_core_admin_order_delivery( $order ) {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
		return;
	}

	$method_id    = (string) $order->get_meta( velotok_core_delivery_contract()['meta_key'] );
	$method_label = $method_id ? velotok_core_delivery_method_label( $method_id ) : '';
	$accepted_by  = (string) $order->get_meta( velotok_core_human_acceptance_contract()['accepted_by'] );

	echo '<p><strong>' . esc_html__( 'Способ доставки:', 'velotok-core' ) . '</strong> ';
	echo esc_html( $method_label ? $method_label : __( 'не выбран', 'velotok-core' ) );
	echo ' <em>(' . esc_html__( 'mocked: true', 'velotok-core' ) . ')</em></p>';

	echo '<p><strong>' . esc_html__( 'Приёмка:', 'velotok-core' ) . '</strong> ';
	echo esc_html( $accepted_by ? __( 'принято', 'velotok-core' ) : __( 'ожидает руководителя проекта', 'velotok-core' ) );
	echo '</p>';
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'velotok_core_admin_order_delivery' );

/**
 * Register the project lead role and acceptance capability.
 *
 * @return void
 */
function velotok_core_register_project_lead_role() {
	$acceptance = velotok_core_human_acceptance_contract();
	$role_slug  = (string) $acceptance['role'];
	$capability = (string) $acceptance['capability'];

	$role = get_role( $role_slug );

	if ( ! $role ) {
		add_role(
			$role_slug,
			(string) $acceptance['role_label'],
			array(
				'read'         => true,
				$capability    => true,
			)
		);
	} else {
		$role->add_cap( $capability );
	}

	$admin = get_role( 'administrator' );

	if ( $admin ) {
		$admin->add_cap( $capability );
	}
}
add_action( 'init', 'velotok_core_register_project_lead_role' );

/**
 * Check whether content or an order has been accepted by a human.
 *
 * @param int $object_id Product or order identifier.
 * @return bool
 */
function velotok_core_is_accepted( $object_id ) {
	$acceptance = velotok_core_human_acceptance_contract();

	return '' !== (string) get_post_meta( (int) $object_id, $acceptance['accepted_by'], true );
}

/**
 * Accept content by a user with the project lead capability.
 *
 * @param int         $object_id Product or order identifier.
 * @param object|null $user      Optional user object.
 * @return bool
 */
function velotok_core_accept_content( $object_id, $user = null ) {
	if ( ! velotok_core_user_can_accept( $user ) ) {
		return false;
	}

	$acceptance = velotok_core_human_acceptance_contract();
	$user_id    = 0;

	if ( is_object( $user ) && isset( $user->ID ) ) {
		$user_id = (int) $user->ID;
	} elseif ( function_exists( 'get_current_user_id' ) ) {
		$user_id = (int) get_current_user_id();
	}

	update_post_meta( (int) $object_id, $acceptance['accepted_by'], $user_id );
	update_post_meta( (int) $object_id, $acceptance['accepted_at'], current_time( 'mysql' ) );

	return true;
}

/**
 * Accept an order by a user with the project lead capability.
 *
 * @param int         $order_id Order identifier.
 * @param object|null $user     Optional user object.
 * @return bool
 */
function velotok_core_accept_order( $order_id, $user = null ) {
	if ( ! velotok_core_user_can_accept( $user ) ) {
		return false;
	}

	$order = wc_get_order( (int) $order_id );

	if ( ! $order ) {
		return false;
	}

	$acceptance = velotok_core_human_acceptance_contract();
	$user_id    = 0;

	if ( is_object( $user ) && isset( $user->ID ) ) {
		$user_id = (int) $user->ID;
	} elseif ( function_exists( 'get_current_user_id' ) ) {
		$user_id = (int) get_current_user_id();
	}

	$order->update_meta_data( $acceptance['accepted_by'], $user_id );
	$order->update_meta_data( $acceptance['accepted_at'], current_time( 'mysql' ) );
	$order->save();

	return true;
}

/**
 * Reset content acceptance when a product is edited again.
 *
 * @param int $post_id Product identifier.
 * @return void
 */
function velotok_core_reset_content_acceptance( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( 'product' !== get_post_type( $post_id ) ) {
		return;
	}

	$acceptance = velotok_core_human_acceptance_contract();

	delete_post_meta( (int) $post_id, $acceptance['accepted_by'] );
	delete_post_meta( (int) $post_id, $acceptance['accepted_at'] );
}
add_action( 'save_post_product', 'velotok_core_reset_content_acceptance', 20 );

/**
 * Add the order acceptance metabox.
 *
 * @return void
 */
function velotok_core_add_order_acceptance_metabox() {
	$screens = array( 'shop_order' );

	if ( function_exists( 'wc_get_page_screen_id' ) ) {
		$screens[] = wc_get_page_screen_id( 'shop-order' );
	}

	foreach ( array_unique( array_filter( $screens ) ) as $screen ) {
		add_meta_box(
			'velotok-order-acceptance',
			__( 'Приёмка заказа', 'velotok-core' ),
			'velotok_core_render_order_acceptance_metabox',
			$screen,
			'side',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'velotok_core_add_order_acceptance_metabox' );

/**
 * Render the order acceptance metabox.
 *
 * @param WP_Post|WC_Order $post_or_order Current screen object.
 * @return void
 */
function velotok_core_render_order_acceptance_metabox( $post_or_order ) {
	$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );

	if ( ! $order ) {
		return;
	}

	$acceptance  = velotok_core_human_acceptance_contract();
	$accepted_by = (string) $order->get_meta( $acceptance['accepted_by'] );

	if ( $accepted_by ) {
		echo '<p>' . esc_html__( 'Заказ принят руководителем проекта.', 'velotok-core' ) . '</p>';
		return;
	}

	echo '<p>' . esc_html__( 'Заказ ожидает приёмки руководителем проекта.', 'velotok-core' ) . '</p>';

	if ( ! velotok_core_user_can_accept() ) {
		return;
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="velotok_accept_order" />
		<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
		<?php wp_nonce_field( 'velotok_accept_order_' . $order->get_id() ); ?>
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Принять заказ', 'velotok-core' ); ?></button>
	</form>
	<?php
}

/**
 * Handle the order acceptance form.
 *
 * @return void
 */
function velotok_core_handle_accept_order() {
	$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

	if ( ! $order_id ) {
		wp_die( esc_html__( 'Не найден заказ для приёмки.', 'velotok-core' ) );
	}

	check_admin_referer( 'velotok_accept_order_' . $order_id );

	if ( ! velotok_core_accept_order( $order_id ) ) {
		wp_die( esc_html__( 'Недостаточно прав для приёмки заказа.', 'velotok-core' ) );
	}

	$redirect = wp_get_referer();

	if ( ! $redirect ) {
		$redirect = admin_url( 'edit.php?post_type=shop_order' );
	}

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_velotok_accept_order', 'velotok_core_handle_accept_order' );

/**
 * Load the YooKassa payment gateway stub once WooCommerce is ready.
 *
 * @return void
 */
function velotok_core_load_payment_gateway() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once VELOTOK_CORE_PATH . 'includes/payment-gateway.php';
}
add_action( 'init', 'velotok_core_load_payment_gateway', 0 );

/**
 * Load the shipping method stubs once WooCommerce shipping is ready.
 *
 * @return void
 */
function velotok_core_load_shipping_methods() {
	if ( ! class_exists( 'WC_Shipping_Method' ) ) {
		return;
	}

	require_once VELOTOK_CORE_PATH . 'includes/shipping-methods.php';
}
add_action( 'woocommerce_shipping_init', 'velotok_core_load_shipping_methods' );
