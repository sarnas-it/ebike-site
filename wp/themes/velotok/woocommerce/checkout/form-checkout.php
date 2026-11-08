<?php
/**
 * Оформление заказа.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

$checkout = WC()->checkout();

do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! is_user_logged_in() ) {
	$velotok_checkout_login_url = function_exists( 'wc_get_page_permalink' )
		? wc_get_page_permalink( 'myaccount' )
		: wp_login_url( wc_get_checkout_url() );

	echo '<div class="woocommerce-info">';
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'Оформление заказа доступно только авторизованным покупателям.', 'velotok' ) ) );
	echo ' <a href="' . esc_url( $velotok_checkout_login_url ) . '">' . esc_html__( 'Войти в аккаунт', 'velotok' ) . '</a>';
	echo '</div>';
	return;
}

$velotok_checkout_cart        = null;
$velotok_checkout_cart_empty  = false;

if ( function_exists( 'WC' ) ) {
	$velotok_woocommerce = WC();

	if ( is_object( $velotok_woocommerce ) && isset( $velotok_woocommerce->cart ) ) {
		$velotok_checkout_cart = $velotok_woocommerce->cart;
	}
}

if ( is_object( $velotok_checkout_cart ) ) {
	if ( function_exists( 'velotok_core_checkout_cart_is_empty' ) ) {
		$velotok_checkout_cart_empty = velotok_core_checkout_cart_is_empty( array( 'cart' => $velotok_checkout_cart ) );
	} elseif ( method_exists( $velotok_checkout_cart, 'is_empty' ) ) {
		$velotok_checkout_cart_empty = (bool) $velotok_checkout_cart->is_empty();
	} elseif ( method_exists( $velotok_checkout_cart, 'get_cart_contents_count' ) ) {
		$velotok_checkout_cart_empty = 0 === (int) $velotok_checkout_cart->get_cart_contents_count();
	}
}

if ( $velotok_checkout_cart_empty ) {
	$velotok_checkout_empty_message = 'Нельзя оформить заказ с пустой корзиной.';

	if ( function_exists( 'velotok_core_checkout_order_contract' ) ) {
		$velotok_checkout_empty_contract = velotok_core_checkout_order_contract();
		$velotok_checkout_empty_message  = $velotok_checkout_empty_contract['cart']['message'];
	}

	$velotok_checkout_cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );

	echo '<div class="woocommerce-info">';
	echo esc_html( $velotok_checkout_empty_message );
	echo ' <a href="' . esc_url( $velotok_checkout_cart_url ) . '">' . esc_html__( 'Перейти в корзину', 'velotok' ) . '</a>';
	echo '</div>';
	return;
}
?>
<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">
	<div class="checkout-layout">
		<div class="checkout-fields">
			<?php if ( $checkout->get_checkout_fields() ) : ?>
				<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
				<div class="col2-set" id="customer_details">
					<div class="col-1">
						<?php do_action( 'woocommerce_checkout_billing' ); ?>
					</div>
					<div class="col-2">
						<?php do_action( 'woocommerce_checkout_shipping' ); ?>
					</div>
				</div>
				<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
			<?php endif; ?>
		</div>

		<div class="checkout-review">
			<h2 id="order_review_heading"><?php esc_html_e( 'Ваш заказ', 'velotok' ); ?></h2>
			<p class="cart-note"><?php esc_html_e( 'Выберите удобный способ оплаты: онлайн через ЮKassa или при получении. Менеджер свяжется, чтобы подтвердить заказ и доставку.', 'velotok' ); ?></p>
			<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
			<div id="order_review" class="woocommerce-checkout-review-order">
				<?php do_action( 'woocommerce_checkout_order_review' ); ?>
			</div>
			<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
		</div>
	</div>
</form>
<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
