<?php
/**
 * Карточка модели.
 *
 * Карточка всегда выводит обязательные атрибуты товара: фото, название,
 * описание и цену. Значения берутся из нормализованных данных карточки, а
 * вспомогательная функция velotok_product_card_attributes() гарантирует, что
 * каждый обязательный атрибут присутствует в разметке.
 *
 * @package Velotok
 *
 * @var array $args Данные карточки.
 */

defined( 'ABSPATH' ) || exit;

$velotok_model = is_array( $args ) ? $args : array();

if ( function_exists( 'velotok_product_card_attributes' ) ) {
	$velotok_card = velotok_product_card_attributes( $velotok_model );
} else {
	$velotok_card = array(
		'photo'       => isset( $velotok_model['image_html'] ) ? (string) $velotok_model['image_html'] : '',
		'title'       => isset( $velotok_model['name'] ) ? (string) $velotok_model['name'] : '',
		'description' => isset( $velotok_model['description'] ) ? (string) $velotok_model['description'] : '',
		'price'       => isset( $velotok_model['price_html'] ) ? (string) $velotok_model['price_html'] : '',
	);
}

if ( '' === trim( (string) $velotok_card['title'] ) ) {
	return;
}

$velotok_required_attributes = function_exists( 'velotok_product_card_required_attributes' )
	? velotok_product_card_required_attributes()
	: array( 'photo', 'title', 'description', 'price' );

$velotok_card_id = isset( $velotok_model['card_id'] ) ? $velotok_model['card_id'] : '';
$velotok_url     = isset( $velotok_model['url'] ) ? $velotok_model['url'] : '';
?>
<article class="model-card" id="<?php echo esc_attr( $velotok_card_id ); ?>" data-product-card="true" data-required-attributes="<?php echo esc_attr( implode( ' ', $velotok_required_attributes ) ); ?>">
	<div class="model-media" data-card-attribute="photo">
		<?php if ( ! empty( $velotok_model['badge'] ) ) : ?>
			<span class="badge<?php echo ! empty( $velotok_model['badge_lime'] ) ? ' badge--lime' : ''; ?>"><?php echo esc_html( $velotok_model['badge'] ); ?></span>
		<?php endif; ?>
		<?php echo $velotok_card['photo']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- разметка собрана и экранирована в inc/woocommerce.php. ?>
	</div>
	<div class="model-body">
		<div class="model-top">
			<span><?php echo esc_html( isset( $velotok_model['category_label'] ) ? $velotok_model['category_label'] : '' ); ?></span>
			<span><?php echo esc_html( isset( $velotok_model['number'] ) ? $velotok_model['number'] : '' ); ?></span>
		</div>
		<h3 class="model-title" data-card-attribute="title"><a href="<?php echo esc_url( $velotok_url ); ?>"><?php echo esc_html( $velotok_card['title'] ); ?></a></h3>
		<p class="model-description" data-card-attribute="description"><?php echo esc_html( $velotok_card['description'] ); ?></p>
		<ul class="specs">
			<?php foreach ( (array) ( isset( $velotok_model['specs'] ) ? $velotok_model['specs'] : array() ) as $velotok_spec ) : ?>
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
			<div class="price model-price" data-card-attribute="price">
				<?php echo wp_kses_post( $velotok_card['price'] ); ?>
				<?php if ( ! empty( $velotok_model['price_note'] ) ) : ?>
					<small><?php echo esc_html( $velotok_model['price_note'] ); ?></small>
				<?php endif; ?>
			</div>
			<?php echo isset( $velotok_model['add_html'] ) ? $velotok_model['add_html'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- разметка собрана и экранирована в inc/woocommerce.php. ?>
		</div>
	</div>
</article>
