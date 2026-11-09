<?php
/**
 * Архив статей автора.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

get_header();

$velotok_author = get_queried_object();
?>
<main id="main" class="page-main" tabindex="-1">
	<section class="section">
		<div class="wrap">
			<header class="page-header">
				<p class="label"><?php esc_html_e( 'Автор', 'velotok' ); ?></p>
				<h1>
					<?php
					if ( $velotok_author instanceof WP_User ) {
						echo esc_html( $velotok_author->display_name );
					} else {
						the_archive_title();
					}
					?>
				</h1>
				<?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
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
				<p class="result-empty"><?php esc_html_e( 'У этого автора пока нет статей.', 'velotok' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
get_footer();
