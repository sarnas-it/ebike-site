<?php
/**
 * Оформление заказа.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

$checkout = WC()->checkout();

do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'Для оформления заказа нужно войти в аккаунт.', 'velotok' ) ) );
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
			<p class="cart-note"><?php esc_html_e( 'Онлайн-оплаты нет: оплата при получении. Менеджер свяжется, чтобы подтвердить заказ и доставку.', 'velotok' ); ?></p>
			<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
			<div id="order_review" class="woocommerce-checkout-review-order">
				<?php do_action( 'woocommerce_checkout_order_review' ); ?>
			</div>
			<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
		</div>
	</div>
</form>
<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
