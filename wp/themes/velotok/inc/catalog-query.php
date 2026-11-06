<?php
/**
 * Контракт просмотра каталога: фильтры, сортировка, поиск, пагинация.
 *
 * Параметры URL — часть контракта: filter_color, min_price, max_price,
 * orderby, /page/N/. Недопустимые значения игнорируются, страница не падает.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

/**
 * Является ли запрос витриной товаров.
 *
 * @param WP_Query $query Запрос.
 * @return bool
 */
function velotok_catalog_is_product_query( $query ) {
	if ( $query->is_post_type_archive( 'product' ) || $query->is_tax( array( 'product_cat', 'product_tag', 'pa_color' ) ) ) {
		return true;
	}
	$post_type = $query->get( 'post_type' );
	return $query->is_search() && ( 'product' === $post_type || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) ) );
}

/**
 * Поддерживаемые сортировки каталога.
 *
 * @return array<string,string>
 */
function velotok_catalog_sorting() {
	return array(
		'date'       => __( 'По умолчанию', 'velotok' ),
		'price'      => __( 'Цена: по возрастанию', 'velotok' ),
		'price-desc' => __( 'Цена: по убыванию', 'velotok' ),
	);
}

/**
 * Допустимые слаги цветов из справочника pa_color.
 *
 * @return array<int,string>
 */
function velotok_catalog_color_slugs() {
	if ( ! taxonomy_exists( 'pa_color' ) ) {
		return array();
	}
	$terms = get_terms( array(
		'taxonomy'   => 'pa_color',
		'hide_empty' => false,
		'fields'     => 'slugs',
	) );
	return is_wp_error( $terms ) ? array() : array_values( $terms );
}

/**
 * Термины цветов для формы фильтра.
 *
 * @return array<int,WP_Term>
 */
function velotok_catalog_color_terms() {
	if ( ! taxonomy_exists( 'pa_color' ) ) {
		return array();
	}
	$terms = get_terms( array(
		'taxonomy'   => 'pa_color',
		'hide_empty' => false,
		'orderby'    => 'name',
	) );
	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Активные фильтры из URL (только допустимые значения).
 *
 * @return array<string,mixed>
 */
function velotok_catalog_active_filters() {
	$filters = array();

	$colors = array();
	if ( isset( $_GET['filter_color'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- публичный фильтр.
		$raw    = (array) wp_unslash( $_GET['filter_color'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$colors = array_values( array_intersect( array_map( 'sanitize_title', $raw ), velotok_catalog_color_slugs() ) );
	}
	if ( ! empty( $colors ) ) {
		$filters['filter_color'] = $colors;
	}

	foreach ( array( 'min_price', 'max_price' ) as $key ) {
		if ( ! isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			continue;
		}
		$value = wp_unslash( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_numeric( $value ) && (float) $value >= 0 ) {
			$filters[ $key ] = (float) $value;
		}
	}

	return $filters;
}

/**
 * Убирает недопустимые значения фильтра из запроса до разбора WooCommerce:
 * иначе ядро применит несуществующий термин и обнулит выдачу.
 */
function velotok_catalog_sanitize_request() {
	if ( is_admin() || ! isset( $_GET['filter_color'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$raw   = (array) wp_unslash( $_GET['filter_color'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$valid = array_values( array_intersect( array_map( 'sanitize_title', $raw ), velotok_catalog_color_slugs() ) );
	if ( empty( $valid ) ) {
		unset( $_GET['filter_color'] );
	} else {
		$_GET['filter_color'] = $valid;
	}
}
add_action( 'init', 'velotok_catalog_sanitize_request', 99 );

/**
 * Применяет фильтры и сортировку к запросу витрины.
 *
 * @param WP_Query $query Запрос.
 */
function velotok_catalog_apply_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! velotok_catalog_is_product_query( $query ) ) {
		return;
	}

	$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	switch ( $orderby ) {
		case 'price':
			$query->set( 'meta_key', '_price' );
			$query->set( 'orderby', array( 'meta_value_num' => 'ASC', 'ID' => 'DESC' ) );
			$query->set( 'order', 'ASC' );
			break;
		case 'price-desc':
			$query->set( 'meta_key', '_price' );
			$query->set( 'orderby', array( 'meta_value_num' => 'DESC', 'ID' => 'DESC' ) );
			$query->set( 'order', 'DESC' );
			break;
		default:
			$query->set( 'orderby', array( 'date' => 'DESC', 'ID' => 'DESC' ) );
			$query->set( 'order', 'DESC' );
			break;
	}

	// Недопустимые значения не должны доходить до price-фильтра WooCommerce:
	// отрицательная цена иначе обнуляет выдачу.
	foreach ( array( 'min_price', 'max_price' ) as $price_key ) {
		if ( isset( $_GET[ $price_key ] ) && ! isset( velotok_catalog_active_filters()[ $price_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$query->set( $price_key, '' );
			unset( $query->query_vars[ $price_key ] );
		}
	}

	$filters = velotok_catalog_active_filters();

	if ( isset( $filters['filter_color'] ) ) {
		$tax_query   = (array) $query->get( 'tax_query' );
		$tax_query[] = array(
			'taxonomy' => 'pa_color',
			'field'    => 'slug',
			'terms'    => $filters['filter_color'],
			'operator' => 'IN',
		);
		$query->set( 'tax_query', $tax_query );
	}

	if ( isset( $filters['min_price'] ) || isset( $filters['max_price'] ) ) {
		$meta_query = (array) $query->get( 'meta_query' );
		if ( isset( $filters['min_price'] ) ) {
			$meta_query[] = array(
				'key'     => '_price',
				'value'   => (float) $filters['min_price'],
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}
		if ( isset( $filters['max_price'] ) ) {
			$meta_query[] = array(
				'key'     => '_price',
				'value'   => (float) $filters['max_price'],
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}
		$query->set( 'meta_query', $meta_query );
	}
}
add_action( 'pre_get_posts', 'velotok_catalog_apply_query', 20 );

/**
 * Добавляет поля модели, SKU и категории в поиск по товарам.
 *
 * @param string   $join  SQL JOIN.
 * @param WP_Query $query Запрос.
 * @return string
 */
function velotok_catalog_search_join( $join, $query ) {
	global $wpdb;
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return $join;
	}
	$post_type = $query->get( 'post_type' );
	if ( ! ( 'product' === $post_type || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) ) ) ) {
		return $join;
	}
	$join .= " LEFT JOIN {$wpdb->postmeta} AS vtk_model ON ({$wpdb->posts}.ID = vtk_model.post_id AND vtk_model.meta_key = 'model')";
	$join .= " LEFT JOIN {$wpdb->postmeta} AS vtk_sku ON ({$wpdb->posts}.ID = vtk_sku.post_id AND vtk_sku.meta_key = '_sku')";
	$join .= " LEFT JOIN {$wpdb->term_relationships} AS vtk_tr ON ({$wpdb->posts}.ID = vtk_tr.object_id)";
	$join .= " LEFT JOIN {$wpdb->term_taxonomy} AS vtk_tt ON (vtk_tr.term_taxonomy_id = vtk_tt.term_taxonomy_id AND vtk_tt.taxonomy = 'product_cat')";
	$join .= " LEFT JOIN {$wpdb->terms} AS vtk_term ON (vtk_tt.term_id = vtk_term.term_id)";
	return $join;
}
add_filter( 'posts_join', 'velotok_catalog_search_join', 10, 2 );

/**
 * Ищет по названию, описанию, модели, SKU и категории.
 *
 * @param string   $search SQL WHERE.
 * @param WP_Query $query  Запрос.
 * @return string
 */
function velotok_catalog_search_where( $search, $query ) {
	global $wpdb;
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return $search;
	}
	$post_type = $query->get( 'post_type' );
	if ( ! ( 'product' === $post_type || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) ) ) ) {
		return $search;
	}
	$term = (string) $query->get( 's' );
	if ( '' === $term ) {
		return $search;
	}
	$like = '%' . $wpdb->esc_like( $term ) . '%';
	return $wpdb->prepare(
		" AND ( ({$wpdb->posts}.post_title LIKE %s) OR ({$wpdb->posts}.post_content LIKE %s) OR (vtk_model.meta_value LIKE %s) OR (vtk_sku.meta_value LIKE %s) OR (vtk_term.name LIKE %s) ) ",
		$like,
		$like,
		$like,
		$like,
		$like
	);
}
add_filter( 'posts_search', 'velotok_catalog_search_where', 10, 2 );

/**
 * Убирает дубли товаров при поиске по категориям.
 *
 * @param string   $distinct DISTINCT-часть.
 * @param WP_Query $query    Запрос.
 * @return string
 */
function velotok_catalog_search_distinct( $distinct, $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return $distinct;
	}
	$post_type = $query->get( 'post_type' );
	if ( 'product' === $post_type || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) ) ) {
		return 'DISTINCT';
	}
	return $distinct;
}
add_filter( 'posts_distinct', 'velotok_catalog_search_distinct', 10, 2 );

/**
 * Ссылка сброса: текущий архив без параметров фильтра.
 *
 * @return string
 */
function velotok_catalog_reset_url() {
	$base = velotok_catalog_archive_url();
	return $base ? $base : home_url( '/' );
}

/**
 * Базовый URL текущего архива без query-параметров.
 *
 * @return string
 */
function velotok_catalog_archive_url() {
	if ( is_product_category() ) {
		$term = get_queried_object();
		$link = $term ? get_term_link( $term ) : '';
		return ( $link && ! is_wp_error( $link ) ) ? $link : '';
	}
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		return wc_get_page_permalink( 'shop' );
	}
	return home_url( '/' );
}

/**
 * Сохраняет фильтры и сортировку в ссылках пагинации.
 *
 * @param string $link Ссылка страницы.
 * @return string
 */
function velotok_catalog_pagination_link( $link ) {
	if ( is_admin() ) {
		return $link;
	}
	$args = array();
	$filters = velotok_catalog_active_filters();
	if ( isset( $filters['filter_color'] ) ) {
		$args['filter_color'] = $filters['filter_color'];
	}
	foreach ( array( 'min_price', 'max_price' ) as $key ) {
		if ( isset( $filters[ $key ] ) ) {
			$args[ $key ] = $filters[ $key ];
		}
	}
	if ( isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = sanitize_key( wp_unslash( $_GET['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( velotok_catalog_sorting()[ $orderby ] ) ) {
			$args['orderby'] = $orderby;
		}
	}
	return empty( $args ) ? $link : add_query_arg( $args, $link );
}
add_filter( 'paginate_links', 'velotok_catalog_pagination_link' );

/**
 * Товаров на странице каталога — параметр контракта.
 *
 * @return int
 */
function velotok_catalog_per_page() {
	return (int) apply_filters( 'velotok_catalog_per_page', 12 );
}
add_filter( 'loop_shop_per_page', 'velotok_catalog_per_page', 20 );
