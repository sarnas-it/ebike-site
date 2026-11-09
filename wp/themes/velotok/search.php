<?php
/**
 * Результаты поиска по статьям.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="page-main" tabindex="-1">
	<section class="section">
		<div class="wrap">
			<header class="page-header">
				<p class="label"><?php esc_html_e( 'Поиск', 'velotok' ); ?></p>
				<h1>
					<?php
					/* translators: %s: поисковый запрос. */
					printf( esc_html__( 'Результаты поиска: %s', 'velotok' ), esc_html( get_search_query() ) );
					?>
				</h1>
				<?php get_search_form(); ?>
			</header>

			<?php if ( have_posts() ) : ?>
				<div class="post-grid">
					<?php
					while ( have_posts() ) :
						the_post();
						get_template_part( 'template-parts/content-post-card' );
					endwhile;
					?>
				</div>
				<?php
				the_posts_pagination( array(
					'mid_size'  => 1,
					'prev_text' => esc_html__( 'Назад', 'velotok' ),
					'next_text' => esc_html__( 'Далее', 'velotok' ),
				) );
				?>
			<?php else : ?>
				<p class="result-empty"><?php esc_html_e( 'По этому запросу ничего не найдено.', 'velotok' ); ?></p>
				<?php get_search_form(); ?>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
get_footer();
