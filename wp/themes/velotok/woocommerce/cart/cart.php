<?php
/**
 * Корзина.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );

if ( ! WC()->cart || WC()->cart->is_empty() ) {
	do_action( 'woocommerce_cart_is_empty' );
	?>
	<div class="cart-empty-state">
		<span class="success-icon">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h2l2.2 10.3a2 2 0 0 0 2 1.7h7.7a2 2 0 0 0 2-1.6L20.5 8H6.1"/><circle cx="10" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/></svg>
		</span>
		<h2><?php esc_html_e( 'Корзина пока пуста', 'velotok' ); ?></h2>
		<p><?php esc_html_e( 'Добавьте модель из каталога — и она появится здесь. Поможем выбрать, если сомневаетесь.', 'velotok' ); ?></p>
		<a class="btn btn-dark" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Перейти в каталог', 'velotok' ); ?></a>
	</div>
	<?php
	return;
}
?>
<form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
	<?php do_action( 'woocommerce_before_cart_table' ); ?>

	<table class="velotok-cart-table" cellspacing="0">
		<thead>
			<tr>
				<th class="product-remove"><span class="screen-reader-text"><?php esc_html_e( 'Удалить', 'velotok' ); ?></span></th>
				<th class="product-thumbnail"><span class="screen-reader-text"><?php esc_html_e( 'Фото', 'velotok' ); ?></span></th>
				<th class="product-name"><?php esc_html_e( 'Модель', 'velotok' ); ?></th>
				<th class="product-price"><?php esc_html_e( 'Цена', 'velotok' ); ?></th>
				<th class="product-quantity"><?php esc_html_e( 'Количество', 'velotok' ); ?></th>
				<th class="product-subtotal"><?php esc_html_e( 'Итого', 'velotok' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php do_action( 'woocommerce_before_cart_contents' ); ?>

			<?php
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
				$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

				if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 ) {
					continue;
				}

				$product_permalink = $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '';
				$product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
				$thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy', 'decoding' => 'async' ) ), $cart_item, $cart_item_key );
				?>
				<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
					<td class="product-remove">
						<?php
						echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							'woocommerce_cart_item_remove_link',
							sprintf(
								'<a href="%1$s" class="remove" aria-label="%2$s" data-product_id="%3$d" data-product_sku="%4$s">&times;</a>',
								esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
								/* translators: %s: название товара. */
								esc_attr( sprintf( __( 'Удалить %s из корзины', 'velotok' ), $product_name ) ),
								(int) $product_id,
								esc_attr( $_product->get_sku() )
							),
							$cart_item_key
						);
						?>
					</td>
					<td class="product-thumbnail">
						<?php if ( $product_permalink ) : ?>
							<a href="<?php echo esc_url( $product_permalink ); ?>"><?php echo wp_kses_post( $thumbnail ); ?></a>
						<?php else : ?>
							<?php echo wp_kses_post( $thumbnail ); ?>
						<?php endif; ?>
					</td>
					<td class="product-name">
						<?php
						if ( $product_permalink ) {
							printf( '<a href="%1$s">%2$s</a>', esc_url( $product_permalink ), wp_kses_post( $product_name ) );
						} else {
							echo wp_kses_post( $product_name );
						}
						echo wp_kses_post( wc_get_formatted_cart_item_data( $cart_item ) );
						?>
					</td>
					<td class="product-price" data-title="<?php esc_attr_e( 'Цена', 'velotok' ); ?>">
						<?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ) ); ?>
					</td>
					<td class="product-quantity" data-title="<?php esc_attr_e( 'Количество', 'velotok' ); ?>">
						<?php
						if ( $_product->is_sold_individually() ) {
							printf(
								'<input type="hidden" name="cart[%1$s][qty]" value="%2$s" />',
								esc_attr( $cart_item_key ),
								esc_attr( (string) $cart_item['quantity'] )
							);
						} else {
							woocommerce_quantity_input( array(
								'input_name'   => 'cart[' . $cart_item_key . '][qty]',
								'input_value'  => $cart_item['quantity'],
								'max_value'    => $_product->get_max_purchase_quantity(),
								'min_value'    => '0',
								'product_name' => $_product->get_name(),
							), $_product, true );
						}
						?>
					</td>
					<td class="product-subtotal" data-title="<?php esc_attr_e( 'Итого', 'velotok' ); ?>">
						<?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ) ); ?>
					</td>
				</tr>
				<?php
			}
			?>

			<?php do_action( 'woocommerce_cart_contents' ); ?>

			<tr>
				<td colspan="6" class="actions">
					<?php if ( wc_coupons_enabled() ) : ?>
						<div class="coupon">
							<label for="coupon_code" class="screen-reader-text"><?php esc_html_e( 'Код купона', 'velotok' ); ?></label>
							<input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Код купона', 'velotok' ); ?>" />
							<button type="submit" class="button" name="apply_coupon" value="<?php esc_attr_e( 'Применить купон', 'velotok' ); ?>"><?php esc_html_e( 'Применить купон', 'velotok' ); ?></button>
							<?php do_action( 'woocommerce_cart_coupon' ); ?>
						</div>
					<?php endif; ?>

					<button type="submit" class="button" name="update_cart" value="<?php esc_attr_e( 'Обновить корзину', 'velotok' ); ?>"><?php esc_html_e( 'Обновить корзину', 'velotok' ); ?></button>

					<?php do_action( 'woocommerce_cart_actions' ); ?>
					<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
				</td>
			</tr>

			<?php do_action( 'woocommerce_after_cart_contents' ); ?>
		</tbody>
	</table>

	<?php do_action( 'woocommerce_after_cart_table' ); ?>
</form>

<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>

<div class="cart-collaterals">
	<?php do_action( 'woocommerce_cart_collaterals' ); ?>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>
