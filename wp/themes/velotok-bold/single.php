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
	<main id="main" class="page-main">
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
				</article>

				<nav class="post-navigation" aria-label="<?php esc_attr_e( 'Навигация по записям', 'velotok' ); ?>">
					<div><?php previous_post_link( '%link', '← %title' ); ?></div>
					<div><?php next_post_link( '%link', '%title →' ); ?></div>
				</nav>
			</div>
		</section>
	</main>
	<?php
endwhile;

get_footer();
