<?php
/**
 * Страница «Спасибо» после оформления заказа.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $order ) || ! $order instanceof WC_Order ) {
	?>
	<div class="thankyou-card">
		<span class="success-icon">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 12 5 5L20 6"/></svg>
		</span>
		<h2><?php esc_html_e( 'Заказ не найден', 'velotok' ); ?></h2>
		<p><?php esc_html_e( 'Проверьте ссылку из письма или свяжитесь с нами — поможем восстановить данные заказа.', 'velotok' ); ?></p>
		<a class="btn btn-dark" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'На главную', 'velotok' ); ?></a>
	</div>
	<?php
	return;
}

if ( $order->has_status( 'failed' ) ) {
	?>
	<div class="thankyou-card">
		<h2><?php esc_html_e( 'Оплата не прошла', 'velotok' ); ?></h2>
		<p><?php esc_html_e( 'Попробуйте оформить заказ ещё раз или свяжитесь с менеджером. Если оплата не нужна — выберите оплату при получении.', 'velotok' ); ?></p>
		<a class="btn btn-dark" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Повторить оплату', 'velotok' ); ?></a>
	</div>
	<?php
	return;
}

do_action( 'woocommerce_before_thankyou', $order->get_id() );
?>
<div class="thankyou-card">
	<span class="success-icon">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 12 5 5L20 6"/></svg>
	</span>
	<p class="label"><?php esc_html_e( 'Заказ принят', 'velotok' ); ?></p>
	<h2><?php esc_html_e( 'Спасибо! Мы уже собираем ваш велосипед', 'velotok' ); ?></h2>
	<p>
		<?php
		printf(
			/* translators: %s: номер заказа. */
			esc_html__( 'Номер заказа: %s. Менеджер свяжется в течение рабочего дня, подтвердит состав заказа и согласует доставку.', 'velotok' ),
			'<strong>#' . esc_html( $order->get_order_number() ) . '</strong>'
		);
		?>
	</p>

	<ul class="order-summary">
		<li><span><?php esc_html_e( 'Дата', 'velotok' ); ?></span><strong><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></strong></li>
		<li><span><?php esc_html_e( 'Итого', 'velotok' ); ?></span><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong></li>
		<li><span><?php esc_html_e( 'Оплата', 'velotok' ); ?></span><strong><?php echo esc_html( $order->get_payment_method_title() ? $order->get_payment_method_title() : __( 'При получении', 'velotok' ) ); ?></strong></li>
		<li><span><?php esc_html_e( 'Почта', 'velotok' ); ?></span><strong><?php echo esc_html( $order->get_billing_email() ); ?></strong></li>
	</ul>

	<p class="cart-note"><?php esc_html_e( 'Оплата при получении. Если нужно что-то изменить в заказе, просто скажите менеджеру.', 'velotok' ); ?></p>

	<div class="thankyou-actions">
		<a class="btn btn-primary" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Вернуться в каталог', 'velotok' ); ?></a>
		<a class="text-link" href="<?php echo esc_url( home_url( '/#lead' ) ); ?>"><?php esc_html_e( 'Связаться с нами', 'velotok' ); ?></a>
	</div>
</div>

<?php
wc_get_template( 'order/order-details.php', array(
	'order'         => $order,
	'show_downloads' => false,
) );

do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
do_action( 'woocommerce_thankyou', $order->get_id() );
