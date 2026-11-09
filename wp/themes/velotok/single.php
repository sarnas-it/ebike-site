<?php
/**
 * Отдельная запись.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<main id="main" class="page-main" tabindex="-1">
		<section class="section">
			<div class="wrap page-wrap">
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?>>
					<header class="page-header">
						<p class="label"><?php echo esc_html( get_the_date() ); ?></p>
						<h1><?php the_title(); ?></h1>
					</header>

					<?php if ( has_post_thumbnail() ) : ?>
						<figure class="entry-media">
							<?php the_post_thumbnail( 'velotok-wide', array( 'loading' => 'lazy' ) ); ?>
						</figure>
					<?php endif; ?>

					<div class="entry-content">
						<?php
						the_content();
						wp_link_pages( array(
							'before' => '<div class="page-links">' . esc_html__( 'Страницы:', 'velotok' ),
							'after'  => '</div>',
						) );
						?>
					</div>

					<?php
					$velotok_categories = get_the_category_list( ', ' );
					$velotok_tags       = get_the_tag_list( '', ', ' );
					?>

					<?php if ( $velotok_categories || $velotok_tags ) : ?>
						<footer class="entry-meta">
							<?php if ( $velotok_categories ) : ?>
								<p class="entry-meta-row">
									<span class="entry-meta-label"><?php esc_html_e( 'Рубрики:', 'velotok' ); ?></span>
									<?php echo wp_kses_post( $velotok_categories ); ?>
								</p>
							<?php endif; ?>
							<?php if ( $velotok_tags ) : ?>
								<p class="entry-meta-row">
									<span class="entry-meta-label"><?php esc_html_e( 'Метки:', 'velotok' ); ?></span>
									<?php echo wp_kses_post( $velotok_tags ); ?>
								</p>
							<?php endif; ?>
						</footer>
					<?php endif; ?>
				</article>

				<nav class="post-navigation" aria-label="<?php esc_attr_e( 'Навигация по записям', 'velotok' ); ?>">
					<div><?php previous_post_link( '%link', '← %title' ); ?></div>
					<div><?php next_post_link( '%link', '%title →' ); ?></div>
				</nav>

				<?php comments_template(); ?>
			</div>
		</section>
	</main>
	<?php
endwhile;

get_footer();
