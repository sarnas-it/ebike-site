<?php
/**
 * Карточка модели.
 *
 * @package Velotok
 *
 * @var array $args Данные карточки.
 */

defined( 'ABSPATH' ) || exit;

$velotok_model = is_array( $args ) ? $args : array();

if ( empty( $velotok_model['name'] ) ) {
	return;
}
?>
<article class="model-card" id="<?php echo esc_attr( $velotok_model['card_id'] ); ?>">
	<div class="model-media">
		<?php if ( ! empty( $velotok_model['badge'] ) ) : ?>
			<span class="badge<?php echo ! empty( $velotok_model['badge_lime'] ) ? ' badge--lime' : ''; ?>"><?php echo esc_html( $velotok_model['badge'] ); ?></span>
		<?php endif; ?>
		<?php echo $velotok_model['image_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- разметка собрана и экранирована в inc/woocommerce.php. ?>
	</div>
	<div class="model-body">
		<div class="model-top">
			<span><?php echo esc_html( $velotok_model['category_label'] ); ?></span>
			<span><?php echo esc_html( $velotok_model['number'] ); ?></span>
		</div>
		<h3><a href="<?php echo esc_url( $velotok_model['url'] ); ?>"><?php echo esc_html( $velotok_model['name'] ); ?></a></h3>
		<?php if ( ! empty( $velotok_model['description'] ) ) : ?>
			<p class="model-description"><?php echo esc_html( $velotok_model['description'] ); ?></p>
		<?php endif; ?>
		<ul class="specs">
			<?php foreach ( $velotok_model['specs'] as $velotok_spec ) : ?>
				<li><span><?php echo esc_html( $velotok_spec[0] ); ?></span><strong><?php echo esc_html( $velotok_spec[1] ); ?></strong></li>
			<?php endforeach; ?>
		</ul>
		<?php if ( ! empty( $velotok_model['extra_attributes'] ) && is_array( $velotok_model['extra_attributes'] ) ) : ?>
			<dl class="model-extra">
				<?php foreach ( $velotok_model['extra_attributes'] as $velotok_extra ) : ?>
					<?php
					$velotok_extra_label = isset( $velotok_extra['label'] ) ? $velotok_extra['label'] : ( isset( $velotok_extra[0] ) ? $velotok_extra[0] : '' );
					$velotok_extra_value = isset( $velotok_extra['value'] ) ? $velotok_extra['value'] : ( isset( $velotok_extra[1] ) ? $velotok_extra[1] : '' );

					if ( '' === trim( (string) $velotok_extra_value ) ) {
						continue;
					}
					?>
					<div class="model-extra-row">
						<dt><?php echo esc_html( $velotok_extra_label ); ?></dt>
						<dd><?php echo esc_html( $velotok_extra_value ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
		<?php endif; ?>
		<div class="model-foot">
			<div class="price">
				<?php echo wp_kses_post( $velotok_model['price_html'] ); ?>
				<?php if ( ! empty( $velotok_model['price_note'] ) ) : ?>
					<small><?php echo esc_html( $velotok_model['price_note'] ); ?></small>
				<?php endif; ?>
			</div>
			<?php echo $velotok_model['add_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- разметка собрана и экранирована в inc/woocommerce.php. ?>
		</div>
	</div>
</article>
