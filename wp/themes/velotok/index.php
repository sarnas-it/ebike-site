<?php
/**
 * Список записей и архивов.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="page-main">
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
						?>
						<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-card' ); ?>>
							<?php if ( has_post_thumbnail() ) : ?>
								<div class="post-media">
									<a href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
										<?php the_post_thumbnail( 'velotok-card', array( 'loading' => 'lazy' ) ); ?>
									</a>
								</div>
							<?php endif; ?>
							<div class="post-body">
								<p class="post-meta"><?php echo esc_html( get_the_date() ); ?></p>
								<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
								<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 26 ) ); ?></p>
								<a class="text-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Читать', 'velotok' ); ?>
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"/></svg>
								</a>
							</div>
						</article>
					<?php endwhile; ?>
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
