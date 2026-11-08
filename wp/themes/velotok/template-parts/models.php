<?php
/**
 * Блок популярных моделей.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

$velotok_models = velotok_get_home_models();
?>
<section class="section section--models" id="models" aria-labelledby="models-title">
	<div class="wrap">
		<div class="section-head">
			<div>
				<p class="label">02 — <?php esc_html_e( 'Популярное', 'velotok' ); ?></p>
				<h2 id="models-title"><?php esc_html_e( 'Модели, которые чаще всего уезжают из шоурума', 'velotok' ); ?></h2>
			</div>
			<a class="text-link" href="<?php echo esc_url( home_url( '/#lead' ) ); ?>"><?php esc_html_e( 'Нужна консультация', 'velotok' ); ?>
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"/></svg>
			</a>
		</div>

		<div class="model-grid">
			<?php foreach ( $velotok_models as $velotok_model ) : ?>
				<?php get_template_part( 'template-parts/model-card', null, $velotok_model ); ?>
			<?php endforeach; ?>

			<div class="model-cta">
				<p class="label"><?php esc_html_e( 'Не определились?', 'velotok' ); ?></p>
				<h3><?php esc_html_e( 'Три вопроса — и мы покажем вашу модель', 'velotok' ); ?></h3>
				<p><?php esc_html_e( 'Ответьте, где и как часто будете ездить. Подборщик учтёт бюджет и тип маршрута.', 'velotok' ); ?></p>
				<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/#picker' ) ); ?>">
					<?php esc_html_e( 'Подобрать модель', 'velotok' ); ?>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
				</a>
			</div>
		</div>
	</div>
</section>
