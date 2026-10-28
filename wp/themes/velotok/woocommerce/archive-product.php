<?php
/**
 * Архив товаров WooCommerce.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

get_header();

$velotok_shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/#models' );
$velotok_terms    = taxonomy_exists( 'product_cat' ) ? get_terms( array(
	'taxonomy'   => 'product_cat',
	'hide_empty' => true,
	'orderby'    => 'name',
	'order'      => 'ASC',
) ) : array();
$velotok_title    = is_product_category() ? single_term_title( '', false ) : __( 'Каталог моделей', 'velotok' );
$velotok_orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'menu_order'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- публичная сортировка каталога.
$velotok_sorting  = array(
	'menu_order' => __( 'По умолчанию', 'velotok' ),
	'popularity' => __( 'По популярности', 'velotok' ),
	'rating'     => __( 'По рейтингу', 'velotok' ),
	'date'       => __( 'По новинкам', 'velotok' ),
	'price'      => __( 'Цена: по возрастанию', 'velotok' ),
	'price-desc' => __( 'Цена: по убыванию', 'velotok' ),
);
if ( ! isset( $velotok_sorting[ $velotok_orderby ] ) ) {
	$velotok_orderby = 'menu_order';
}
$velotok_sort_action = $velotok_shop_url;
if ( is_product_category() ) {
	$velotok_current_term = get_queried_object();
	$velotok_term_link    = $velotok_current_term ? get_term_link( $velotok_current_term ) : '';
	if ( $velotok_term_link && ! is_wp_error( $velotok_term_link ) ) {
		$velotok_sort_action = $velotok_term_link;
	}
}
?>
<main id="main" class="page-main">
	<section class="catalog-head">
		<div class="wrap">
			<p class="label"><?php esc_html_e( 'Каталог', 'velotok' ); ?></p>
			<h1><?php echo esc_html( $velotok_title ); ?></h1>
			<p class="catalog-lead"><?php esc_html_e( 'Городские, горные, грузовые и складные электровелосипеды. Все модели собраны, проверены и готовы к тест-драйву.', 'velotok' ); ?></p>
			<?php if ( is_product_category() && term_description() ) : ?>
				<div class="catalog-description"><?php echo wp_kses_post( term_description() ); ?></div>
			<?php endif; ?>
		</div>
	</section>

	<section class="section catalog-section">
		<div class="wrap">
			<div class="catalog-toolbar">
				<nav class="catalog-filters" aria-label="<?php esc_attr_e( 'Фильтр по категории', 'velotok' ); ?>">
					<a class="chip<?php echo is_shop() ? ' is-active' : ''; ?>" href="<?php echo esc_url( $velotok_shop_url ); ?>"><?php esc_html_e( 'Все', 'velotok' ); ?></a>
					<?php if ( ! is_wp_error( $velotok_terms ) ) : ?>
						<?php foreach ( $velotok_terms as $velotok_term ) : ?>
							<a class="chip<?php echo is_product_category( $velotok_term->slug ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $velotok_term ) ); ?>"><?php echo esc_html( $velotok_term->name ); ?> <span><?php echo esc_html( number_format_i18n( $velotok_term->count ) ); ?></span></a>
						<?php endforeach; ?>
					<?php endif; ?>
				</nav>
				<div class="catalog-sort">
					<form class="velotok-ordering" method="get" action="<?php echo esc_url( $velotok_sort_action ); ?>">
						<label class="screen-reader-text" for="velotok-orderby"><?php esc_html_e( 'Сортировка товаров', 'velotok' ); ?></label>
						<select name="orderby" id="velotok-orderby">
							<?php foreach ( $velotok_sorting as $velotok_value => $velotok_label ) : ?>
								<option value="<?php echo esc_attr( $velotok_value ); ?>"<?php selected( $velotok_orderby, $velotok_value ); ?>><?php echo esc_html( $velotok_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<noscript><button type="submit" class="button"><?php esc_html_e( 'Применить', 'velotok' ); ?></button></noscript>
					</form>
				</div>
			</div>

			<?php
			if ( function_exists( 'wc_setup_loop' ) ) {
				wc_setup_loop();
			}
			?>

			<?php if ( have_posts() ) : ?>
				<div class="model-grid catalog-grid">
					<?php
					while ( have_posts() ) :
						the_post();
						wc_get_template_part( 'content', 'product' );
					endwhile;
					?>
				</div>
				<?php woocommerce_pagination(); ?>
			<?php else : ?>
				<div class="catalog-empty">
					<p class="result-empty"><?php esc_html_e( 'В этой категории пока нет моделей.', 'velotok' ); ?></p>
					<a class="btn btn-dark" href="<?php echo esc_url( $velotok_shop_url ); ?>"><?php esc_html_e( 'Смотреть весь каталог', 'velotok' ); ?></a>
				</div>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
get_footer();
