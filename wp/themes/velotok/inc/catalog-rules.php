<?php
/**
 * Правила каталога: товар без обязательного атрибута model находится в
 * состоянии incomplete и не показывается на витрине.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

/**
 * Скрывает товары без model из списков товаров, включая Store API.
 *
 * @param WP_Query $query Запрос.
 */
function velotok_hide_incomplete_products( $query ) {
	if ( is_admin() ) {
		return;
	}
	$is_product_query = 'product' === $query->get( 'post_type' )
		|| $query->is_post_type_archive( 'product' )
		|| $query->is_tax( 'product_cat' )
		|| $query->is_tax( 'product_tag' )
		|| $query->is_tax( 'pa_color' );
	if ( ! $is_product_query ) {
		return;
	}
	$meta = $query->get( 'meta_query' );
	if ( ! is_array( $meta ) ) {
		$meta = array();
	}
	$meta[] = array(
		'key'     => 'model',
		'value'   => '',
		'compare' => '!=',
	);
	$query->set( 'meta_query', $meta );
}
add_action( 'pre_get_posts', 'velotok_hide_incomplete_products' );

/**
 * Карточка incomplete-товара отдаёт 404.
 */
function velotok_incomplete_product_404() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$product_id = get_queried_object_id();
	if ( '' !== (string) get_post_meta( $product_id, 'model', true ) ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	include get_query_template( '404' );
	exit;
}
add_action( 'template_redirect', 'velotok_incomplete_product_404' );

/**
 * Товар без model невидим для WooCommerce.
 *
 * @param bool $visible   Видимость.
 * @param int  $product_id ID товара.
 * @return bool
 */
function velotok_incomplete_product_visible( $visible, $product_id ) {
	if ( ! $visible ) {
		return $visible;
	}
	return '' !== (string) get_post_meta( $product_id, 'model', true );
}
add_filter( 'woocommerce_product_is_visible', 'velotok_incomplete_product_visible', 10, 2 );
