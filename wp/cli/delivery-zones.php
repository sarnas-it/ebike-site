<?php
/**
 * Idempotent WooCommerce delivery zone setup.
 *
 * Run from the WordPress root: wp eval-file wp/cli/delivery-zones.php
 *
 * Creates the «Россия» shipping zone and adds the СДЭК, Почта РФ and pickup
 * methods to it, so the storefront offers shipping methods from WooCommerce
 * zones. Existing methods and their settings are preserved.
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Shipping_Zones' ) || ! class_exists( 'WC_Shipping_Zone' ) ) {
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::warning( 'WooCommerce shipping zones API is not available; delivery zones were not created.' );
	}

	return;
}

/**
 * Write a setup log line.
 *
 * @param string $message Message.
 * @return void
 */
function velotok_delivery_zones_log( $message ) {
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::log( $message );
	}
}

$zone_name = 'Россия';
$zone_id   = 0;

foreach ( WC_Shipping_Zones::get_zones() as $zone_data ) {
	if ( isset( $zone_data['zone_name'] ) && $zone_name === $zone_data['zone_name'] ) {
		if ( isset( $zone_data['zone_id'] ) ) {
			$zone_id = (int) $zone_data['zone_id'];
		} elseif ( isset( $zone_data['id'] ) ) {
			$zone_id = (int) $zone_data['id'];
		}
		break;
	}
}

if ( 0 === $zone_id ) {
	$zone = new WC_Shipping_Zone();
	$zone->set_zone_name( $zone_name );
	$zone->set_locations(
		array(
			array(
				'code' => 'RU',
				'type' => 'country',
			),
		)
	);
	$zone->save();
	$zone_id = (int) $zone->get_id();
	velotok_delivery_zones_log( sprintf( 'Создана зона доставки «%s» (ID %d).', $zone_name, $zone_id ) );
}

$zone = new WC_Shipping_Zone( $zone_id );

$defaults = array(
	'cdek'         => array(
		'title'        => 'СДЭК',
		'base'         => '500',
		'per_kg'       => '100',
		'free_from'    => '5000',
		'eta_min_days' => '1',
		'eta_max_days' => '3',
		'test_mode'    => 'no',
	),
	'russian_post' => array(
		'title'        => 'Почта РФ',
		'base'         => '350',
		'per_kg'       => '80',
		'free_from'    => '7000',
		'eta_min_days' => '3',
		'eta_max_days' => '7',
		'test_mode'    => 'no',
	),
	'local_pickup' => array(
		'title' => 'Самовывоз',
		'cost'  => '0',
	),
);

$existing = array();

foreach ( $zone->get_shipping_methods( false ) as $method ) {
	if ( isset( $method->id ) ) {
		$existing[ $method->id ] = isset( $method->instance_id ) ? (int) $method->instance_id : 0;
	}
}

foreach ( $defaults as $method_id => $settings ) {
	if ( isset( $existing[ $method_id ] ) ) {
		$instance_id = (int) $existing[ $method_id ];
	} else {
		$instance_id = $zone->add_shipping_method( $method_id );
	}

	if ( ! $instance_id ) {
		velotok_delivery_zones_log( sprintf( 'Не удалось добавить способ доставки «%s».', $method_id ) );
		continue;
	}

	$option_key = 'woocommerce_' . $method_id . '_' . (int) $instance_id . '_settings';
	$stored     = get_option( $option_key, array() );
	$stored     = is_array( $stored ) ? $stored : array();
	$stored     = array_merge( $settings, $stored );
	$stored['enabled'] = 'yes';

	update_option( $option_key, $stored );
	velotok_delivery_zones_log( sprintf( 'Способ доставки «%s» привязан к зоне «%s».', $method_id, $zone_name ) );
}
