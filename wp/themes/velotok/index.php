<?php
/**
 * Список записей и архивов.
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
				<p class="label"><?php esc_html_e( 'Велоток', 'velotok' ); ?></p>
				<h1>
					<?php
					if ( is_home() && ! is_front_page() ) {
						single_post_title();
					} elseif ( is_search() ) {
						/* translators: %s: поисковый запрос. */
						printf( esc_html__( 'Результаты поиска: %s', 'velotok' ), esc_html( get_search_query() ) );
					} else {
						the_archive_title();
					}
					?>
				</h1>
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
				<?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?>
			<?php else : ?>
				<p class="result-empty"><?php esc_html_e( 'Пока ничего не найдено.', 'velotok' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
get_footer();
