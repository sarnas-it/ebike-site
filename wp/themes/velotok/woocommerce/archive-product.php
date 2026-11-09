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
$velotok_sorting  = function_exists( 'velotok_catalog_sorting' ) ? velotok_catalog_sorting() : array(
	'menu_order' => __( 'По умолчанию', 'velotok' ),
	'popularity' => __( 'По популярности', 'velotok' ),
	'rating'     => __( 'По рейтингу', 'velotok' ),
	'date'       => __( 'По новинкам', 'velotok' ),
	'price'      => __( 'Цена: по возрастанию', 'velotok' ),
	'price-desc' => __( 'Цена: по убыванию', 'velotok' ),
);
$velotok_orderby  = function_exists( 'velotok_catalog_request_orderby' ) ? velotok_catalog_request_orderby() : 'menu_order';
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
$velotok_search            = function_exists( 'velotok_catalog_request_search' ) ? velotok_catalog_request_search() : '';
$velotok_filter_values     = function_exists( 'velotok_catalog_request_filters' ) ? velotok_catalog_request_filters() : array();
$velotok_filter_attributes = function_exists( 'velotok_catalog_filter_contract_attributes' ) ? velotok_catalog_filter_contract_attributes() : array();
?>
<main id="main" class="page-main" tabindex="-1">
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

			<form class="catalog-filter-form" method="get" action="<?php echo esc_url( $velotok_sort_action ); ?>" role="search">
				<div class="catalog-filter-search">
					<label for="velotok-catalog-search"><?php esc_html_e( 'Поиск по каталогу', 'velotok' ); ?></label>
					<input type="search" id="velotok-catalog-search" name="s" value="<?php echo esc_attr( $velotok_search ); ?>" placeholder="<?php esc_attr_e( 'Название, характеристика, бейдж…', 'velotok' ); ?>">
				</div>
				<?php foreach ( $velotok_filter_attributes as $velotok_filter_key => $velotok_filter_attribute ) : ?>
					<?php
					if ( empty( $velotok_filter_attribute['filter'] ) || 'category' === $velotok_filter_key ) {
						continue;
					}

					$velotok_filter_value = isset( $velotok_filter_values[ $velotok_filter_key ] ) ? $velotok_filter_values[ $velotok_filter_key ] : '';
					$velotok_filter_label = isset( $velotok_filter_attribute['label'] ) ? $velotok_filter_attribute['label'] : $velotok_filter_key;
					?>
					<?php if ( 'number' === $velotok_filter_attribute['type'] && ! empty( $velotok_filter_attribute['range'] ) ) : ?>
						<fieldset class="catalog-filter-range">
							<legend><?php echo esc_html( $velotok_filter_label ); ?></legend>
							<label>
								<span><?php esc_html_e( 'от', 'velotok' ); ?></span>
								<input type="number" step="any" name="velotok_filter[<?php echo esc_attr( $velotok_filter_key ); ?>_min]" value="<?php echo esc_attr( is_array( $velotok_filter_value ) && isset( $velotok_filter_value['min'] ) ? $velotok_filter_value['min'] : '' ); ?>">
							</label>
							<label>
								<span><?php esc_html_e( 'до', 'velotok' ); ?></span>
								<input type="number" step="any" name="velotok_filter[<?php echo esc_attr( $velotok_filter_key ); ?>_max]" value="<?php echo esc_attr( is_array( $velotok_filter_value ) && isset( $velotok_filter_value['max'] ) ? $velotok_filter_value['max'] : '' ); ?>">
							</label>
						</fieldset>
					<?php else : ?>
						<label class="catalog-filter-field">
							<span><?php echo esc_html( $velotok_filter_label ); ?></span>
							<input type="text" name="velotok_filter[<?php echo esc_attr( $velotok_filter_key ); ?>]" value="<?php echo esc_attr( is_array( $velotok_filter_value ) ? implode( ', ', $velotok_filter_value ) : $velotok_filter_value ); ?>">
						</label>
					<?php endif; ?>
				<?php endforeach; ?>
				<input type="hidden" name="orderby" value="<?php echo esc_attr( $velotok_orderby ); ?>">
				<div class="catalog-filter-actions">
					<button type="submit" class="button"><?php esc_html_e( 'Применить', 'velotok' ); ?></button>
					<a class="text-link" href="<?php echo esc_url( $velotok_shop_url ); ?>"><?php esc_html_e( 'Сбросить', 'velotok' ); ?></a>
				</div>
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
						$velotok_loop_product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
						if ( is_object( $velotok_loop_product ) && ! velotok_is_valid_product( $velotok_loop_product ) ) {
							continue;
						}
						wc_get_template_part( 'content', 'product' );
					endwhile;
					?>
				</div>
				<?php
				if ( function_exists( 'velotok_catalog_pagination' ) ) {
					velotok_catalog_pagination( array( 'base' => $velotok_sort_action ) );
				} else {
					woocommerce_pagination();
				}
				?>
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
