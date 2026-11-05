<?php
/**
 * Карточка товара.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	global $product;

	if ( ! $product instanceof WC_Product ) {
		continue;
	}

	$velotok_tagline = trim( (string) get_post_meta( $product->get_id(), '_velotok_tagline', true ) );
	?>
	<main id="main" class="page-main">
		<div class="wrap product-breadcrumbs">
			<?php
			woocommerce_breadcrumb( array(
				'wrap_before' => '<nav class="breadcrumbs" aria-label="' . esc_attr__( 'Хлебные крошки', 'velotok' ) . '">',
				'wrap_after'  => '</nav>',
				'delimiter'   => '<span aria-hidden="true">/</span>',
			) );
			?>
		</div>

		<section class="section product-page">
			<div class="wrap">
				<?php do_action( 'woocommerce_before_single_product' ); ?>

				<div class="product-layout">
					<div class="product-gallery">
						<?php woocommerce_show_product_sale_flash(); ?>
						<?php woocommerce_show_product_images(); ?>
					</div>

					<div class="product-summary">
						<p class="label"><?php echo esc_html( velotok_product_category_label( $product ) ); ?></p>
						<?php woocommerce_template_single_title(); ?>

						<?php if ( '' !== $velotok_tagline ) : ?>
							<p class="product-tagline"><?php echo esc_html( $velotok_tagline ); ?></p>
						<?php endif; ?>

						<?php woocommerce_template_single_rating(); ?>
						<div class="product-price"><?php woocommerce_template_single_price(); ?></div>
						<?php woocommerce_template_single_excerpt(); ?>

						<ul class="product-specs">
							<?php foreach ( velotok_product_specs( $product->get_id() ) as $velotok_spec ) : ?>
								<li><span><?php echo esc_html( $velotok_spec[0] ); ?></span><strong><?php echo esc_html( $velotok_spec[1] ); ?></strong></li>
							<?php endforeach; ?>
						</ul>

						<div class="product-buy">
							<?php woocommerce_template_single_add_to_cart(); ?>
							<p class="cart-note"><?php esc_html_e( 'Оплата при получении. Менеджер свяжется для подтверждения заказа и доставки.', 'velotok' ); ?></p>
						</div>

						<?php woocommerce_template_single_meta(); ?>
					</div>
				</div>

				<?php woocommerce_output_product_data_tabs(); ?>

				<?php
				$velotok_related_ids = wc_get_related_products( $product->get_id(), 3 );
				if ( ! empty( $velotok_related_ids ) ) :
					?>
					<section class="related-models" aria-labelledby="related-title">
						<div class="section-head">
							<div>
								<p class="label"><?php esc_html_e( 'Ещё модели', 'velotok' ); ?></p>
								<h2 id="related-title"><?php esc_html_e( 'Посмотрите рядом', 'velotok' ); ?></h2>
							</div>
						</div>
						<div class="model-grid">
							<?php
							foreach ( $velotok_related_ids as $velotok_related_id ) {
								$velotok_related = wc_get_product( $velotok_related_id );
								if ( $velotok_related instanceof WC_Product ) {
									get_template_part( 'template-parts/model-card', null, velotok_normalize_product( $velotok_related ) );
								}
							}
							?>
						</div>
					</section>
				<?php endif; ?>

				<?php do_action( 'woocommerce_after_single_product' ); ?>
			</div>
		</section>
	</main>
	<?php
endwhile;

get_footer();
