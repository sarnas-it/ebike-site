<?php
/**
 * Блок отзывов.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

$velotok_reviews = array(
	array(
		'quote'    => __( '«Брала Ривьеру, чтобы ездить на работу вместо метро. Дорога занимает те же 25 минут, но я приезжаю без толпы и с ровным дыханием. Батареи хватает на три дня с запасом».', 'velotok' ),
		'initials' => 'АК',
		'name'     => __( 'Анна К.', 'velotok' ),
		'meta'     => __( 'Москва · Ривьера', 'velotok' ),
		'dark'     => false,
	),
	array(
		'quote'    => __( '«Взял Хребет Trail для леса за городом. На подъёмах мотор работает плавно, без рывка, а по грунту велосипед ведёт себя предсказуемо. На первой неделе приехал на настройку подвески — сделали за час».', 'velotok' ),
		'initials' => 'ДС',
		'name'     => __( 'Дмитрий С.', 'velotok' ),
		'meta'     => __( 'Казань · Хребет Trail', 'velotok' ),
		'dark'     => true,
	),
	array(
		'quote'    => __( '«Семейный заменил нам вторую машину для поездок в садик и магазин. Двое детей сзади, рюкзаки в боксе, до горки доезжаем быстрее, чем на автобусе. Главное — поставили удобное детское кресло».', 'velotok' ),
		'initials' => 'МП',
		'name'     => __( 'Марина и Павел', 'velotok' ),
		'meta'     => __( 'Санкт-Петербург · Семейный', 'velotok' ),
		'dark'     => false,
	),
);
?>
<section class="section" id="reviews" aria-labelledby="reviews-title">
	<div class="wrap">
		<div class="section-head">
			<div>
				<p class="label">05 — <?php esc_html_e( 'Отзывы', 'velotok' ); ?></p>
				<h2 id="reviews-title"><?php esc_html_e( 'Что говорят после', 'velotok' ); ?> <em class="serif"><?php esc_html_e( 'первой тысячи', 'velotok' ); ?></em> <?php esc_html_e( 'километров', 'velotok' ); ?></h2>
			</div>
			<div class="rating">
				<strong>4,9</strong>
				<div>
					<span class="stars" aria-hidden="true">★★★★★</span>
					<p><?php esc_html_e( '218 отзывов на картах', 'velotok' ); ?></p>
				</div>
			</div>
		</div>
		<div class="review-grid">
			<?php foreach ( $velotok_reviews as $velotok_review ) : ?>
				<article class="review-card">
					<div class="review-top">
						<span class="stars" aria-label="<?php esc_attr_e( 'Оценка 5 из 5', 'velotok' ); ?>">★★★★★</span>
						<span class="verified">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 12 5 5L20 6"/></svg>
							<?php esc_html_e( 'Проверен', 'velotok' ); ?>
						</span>
					</div>
					<blockquote><?php echo esc_html( $velotok_review['quote'] ); ?></blockquote>
					<div class="review-author">
						<span class="avatar<?php echo $velotok_review['dark'] ? ' avatar--dark' : ''; ?>" aria-hidden="true"><?php echo esc_html( $velotok_review['initials'] ); ?></span>
						<div><b><?php echo esc_html( $velotok_review['name'] ); ?></b><span><?php echo esc_html( $velotok_review['meta'] ); ?></span></div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
