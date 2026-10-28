<?php
/**
 * Карточка товара в списке.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product ) {
	$product = wc_get_product( get_the_ID() );
}

if ( ! $product instanceof WC_Product ) {
	return;
}

get_template_part( 'template-parts/model-card', null, velotok_normalize_product( $product ) );
