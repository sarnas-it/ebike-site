<?php
/**
 * Обычная страница.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

get_header();

$velotok_wide = function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() );
?>
<main id="main" class="page-main">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<section class="section">
			<div class="wrap page-wrap<?php echo $velotok_wide ? ' page-wrap--wide' : ''; ?>">
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?>>
					<header class="page-header">
						<p class="label"><?php esc_html_e( 'Велоток', 'velotok' ); ?></p>
						<h1><?php the_title(); ?></h1>
					</header>
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
			</div>
		</section>
	<?php endwhile; ?>
</main>
<?php
get_footer();
