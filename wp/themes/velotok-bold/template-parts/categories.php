<?php
/**
 * Блок категорий.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

$velotok_categories = array(
	array(
		'slug'  => 'city',
		'title' => __( 'Городские', 'velotok' ),
		'text'  => __( 'Для работы, магазина и вечерних поездок без пота на спине.', 'velotok' ),
		'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 21V8l4-3v16M8 21V5l6 4v12M14 21V11h6v10M2 21h20M11 10h1M11 13h1M17 14h1M17 17h1"/></svg>',
	),
	array(
		'slug'  => 'mountain',
		'title' => __( 'Горные', 'velotok' ),
		'text'  => __( 'Грунт, лесные тропы и подъёмы, где обычный велосипед сдаётся.', 'velotok' ),
		'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 19 5.5-9 3.5 5.5L15 10l6 9H3ZM14 6.5l1.6-2.5 1.6 2.5"/></svg>',
	),
	array(
		'slug'  => 'cargo',
		'title' => __( 'Грузовые', 'velotok' ),
		'text'  => __( 'Дети, доставка, дачный урожай — до 120 кг на борту.', 'velotok' ),
		'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h10v10H3zM13 12h5l3 3v3h-8M6 18a2 2 0 1 0 0 4 2 2 0 0 0 0-4ZM18 18a2 2 0 1 0 0 4 2 2 0 0 0 0-4ZM8 8V5h5"/></svg>',
	),
	array(
		'slug'  => 'folding',
		'title' => __( 'Складные', 'velotok' ),
		'text'  => __( 'В багажник, в лифт, в офис. Складываются за полминуты.', 'velotok' ),
		'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="17" r="3.2"/><circle cx="18" cy="17" r="3.2"/><path d="M6 17 9.5 9h4L12 13l6-1M15.5 9H18M12 9l-2.5 8M15.5 9 18 17M4 7h3"/></svg>',
	),
);
?>
<section class="section" id="categories" aria-labelledby="cat-title">
	<div class="wrap">
		<div class="section-head">
			<div>
				<p class="label">01 — <?php esc_html_e( 'Каталог', 'velotok' ); ?></p>
				<h2 id="cat-title"><?php esc_html_e( 'Один магазин —', 'velotok' ); ?> <em class="serif"><?php esc_html_e( 'четыре', 'velotok' ); ?></em> <?php esc_html_e( 'сценария', 'velotok' ); ?></h2>
			</div>
			<a class="text-link" href="<?php echo esc_url( home_url( '/#models' ) ); ?>"><?php esc_html_e( 'Все модели', 'velotok' ); ?>
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"/></svg>
			</a>
		</div>
		<div class="cat-grid">
			<?php foreach ( $velotok_categories as $velotok_category ) : ?>
				<a class="cat-card" href="<?php echo esc_url( velotok_category_url( $velotok_category['slug'] ) ); ?>">
					<span class="cat-icon">
						<?php echo $velotok_category['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- статичная SVG-иконка темы. ?>
					</span>
					<h3><?php echo esc_html( $velotok_category['title'] ); ?></h3>
					<p><?php echo esc_html( $velotok_category['text'] ); ?></p>
					<span class="cat-meta"><?php echo esc_html( velotok_category_count_label( $velotok_category['slug'] ) ); ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"/></svg>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
