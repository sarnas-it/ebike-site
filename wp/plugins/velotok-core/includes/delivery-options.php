<?php
/**
 * Delivery option helpers.
 *
 * Spec: delivery. Requirements TESTEB-28 … TESTEB-32.
 *
 * The storefront delivery options are implemented in delivery-zones.php so
 * this file only makes the optional module available to the plugin bootstrap.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once __DIR__ . '/delivery-zones.php';

if ( ! function_exists( 'velotok_core_delivery_options' ) ) {
	/**
	 * Return the delivery options for an address.
	 *
	 * @param mixed $address Address data.
	 * @param mixed $zones   Zone list or null.
	 * @param array $context Context.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_options( $address = array(), $zones = null, $context = array() ) {
		return velotok_core_delivery_zone_methods( $address, $zones, $context );
	}
}
