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
$velotok_orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'date'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- публичная сортировка каталога.
$velotok_sorting  = function_exists( 'velotok_catalog_sorting' ) ? velotok_catalog_sorting() : array( 'date' => __( 'По умолчанию', 'velotok' ) );
if ( ! isset( $velotok_sorting[ $velotok_orderby ] ) ) {
	$velotok_orderby = 'date';
}
$velotok_archive_url = function_exists( 'velotok_catalog_archive_url' ) ? velotok_catalog_archive_url() : $velotok_shop_url;
$velotok_sort_action = $velotok_archive_url ? $velotok_archive_url : $velotok_shop_url;
$velotok_filters     = function_exists( 'velotok_catalog_active_filters' ) ? velotok_catalog_active_filters() : array();
$velotok_colors      = function_exists( 'velotok_catalog_color_terms' ) ? velotok_catalog_color_terms() : array();
$velotok_active      = isset( $velotok_filters['filter_color'] ) ? $velotok_filters['filter_color'] : array();
$velotok_reset_url   = function_exists( 'velotok_catalog_reset_url' ) ? velotok_catalog_reset_url() : $velotok_shop_url;
$velotok_has_filters = ! empty( $velotok_filters );
$velotok_empty_text  = is_search() ? __( 'Ничего не найдено', 'velotok' ) : __( 'Товаров не найдено', 'velotok' );
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
						<?php foreach ( $velotok_active as $velotok_color_slug ) : ?>
							<input type="hidden" name="filter_color[]" value="<?php echo esc_attr( $velotok_color_slug ); ?>" />
						<?php endforeach; ?>
						<?php foreach ( array( 'min_price', 'max_price' ) as $velotok_price_key ) : ?>
							<?php if ( isset( $velotok_filters[ $velotok_price_key ] ) ) : ?>
								<input type="hidden" name="<?php echo esc_attr( $velotok_price_key ); ?>" value="<?php echo esc_attr( $velotok_filters[ $velotok_price_key ] ); ?>" />
							<?php endif; ?>
						<?php endforeach; ?>
						<select name="orderby" id="velotok-orderby">
							<?php foreach ( $velotok_sorting as $velotok_value => $velotok_label ) : ?>
								<option value="<?php echo esc_attr( $velotok_value ); ?>"<?php selected( $velotok_orderby, $velotok_value ); ?>><?php echo esc_html( $velotok_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<noscript><button type="submit" class="button"><?php esc_html_e( 'Применить', 'velotok' ); ?></button></noscript>
					</form>
				</div>
			</div>

			<form class="catalog-filter-form" method="get" action="<?php echo esc_url( $velotok_sort_action ); ?>" data-catalog-filters>
				<?php if ( ! empty( $velotok_colors ) ) : ?>
					<fieldset class="catalog-filter-group">
						<legend><?php esc_html_e( 'Цвет', 'velotok' ); ?></legend>
						<?php foreach ( $velotok_colors as $velotok_color ) : ?>
							<label>
								<input type="checkbox" name="filter_color[]" value="<?php echo esc_attr( $velotok_color->slug ); ?>"<?php checked( in_array( $velotok_color->slug, $velotok_active, true ) ); ?> />
								<?php echo esc_html( $velotok_color->name ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php endif; ?>
				<fieldset class="catalog-filter-group">
					<legend><?php esc_html_e( 'Цена, ₽', 'velotok' ); ?></legend>
					<label>
						<?php esc_html_e( 'от', 'velotok' ); ?>
						<input type="number" min="0" name="min_price" value="<?php echo isset( $velotok_filters['min_price'] ) ? esc_attr( $velotok_filters['min_price'] ) : ''; ?>" />
					</label>
					<label>
						<?php esc_html_e( 'до', 'velotok' ); ?>
						<input type="number" min="0" name="max_price" value="<?php echo isset( $velotok_filters['max_price'] ) ? esc_attr( $velotok_filters['max_price'] ) : ''; ?>" />
					</label>
				</fieldset>
				<button type="submit" class="button"><?php esc_html_e( 'Применить', 'velotok' ); ?></button>
				<?php if ( $velotok_has_filters ) : ?>
					<a data-reset-filters class="catalog-reset" href="<?php echo esc_url( $velotok_reset_url ); ?>"><?php esc_html_e( 'Сбросить фильтры', 'velotok' ); ?></a>
				<?php endif; ?>
			</form>

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
				<div class="catalog-empty" data-catalog-empty>
					<p class="result-empty"><?php echo esc_html( $velotok_empty_text ); ?></p>
					<?php if ( $velotok_has_filters || is_search() ) : ?>
						<a data-reset-filters class="catalog-reset" href="<?php echo esc_url( $velotok_reset_url ); ?>"><?php esc_html_e( 'Сбросить фильтры', 'velotok' ); ?></a>
					<?php endif; ?>
					<a class="btn btn-dark" href="<?php echo esc_url( $velotok_shop_url ); ?>"><?php esc_html_e( 'Смотреть весь каталог', 'velotok' ); ?></a>
				</div>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
get_footer();
