<?php
/**
 * Pickup point (ПВЗ) contract.
 *
 * Spec: delivery. Requirements TESTEB-32 and TESTEB-33: pickup points are
 * selected by the radius from the delivery address and a broken list fails
 * explicitly.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once __DIR__ . '/delivery-zones.php';

if ( ! class_exists( 'Velotok_PVS_Contract' ) ) {
	/**
	 * Pickup point contract facade.
	 */
	class Velotok_PVS_Contract {
		/**
		 * Requirement identifier.
		 */
		const REQUIREMENT = 'TESTEB-32';

		/**
		 * Default search radius in kilometres.
		 */
		const DEFAULT_RADIUS_KM = 10.0;

		/**
		 * Distance unit.
		 */
		const UNIT = 'km';

		/**
		 * Return the pickup points contract section.
		 *
		 * @return array<string,mixed>
		 */
		public static function contract() {
			return velotok_core_delivery_pickup_points_contract();
		}

		/**
		 * Return the contract for an instance.
		 *
		 * @return array<string,mixed>
		 */
		public function get_contract() {
			return self::contract();
		}

		/**
		 * Return the requirement identifier.
		 *
		 * @return string
		 */
		public static function requirement() {
			$contract = self::contract();

			return isset( $contract['requirement'] ) ? $contract['requirement'] : self::REQUIREMENT;
		}

		/**
		 * Return the requirement identifier for pickup point failures.
		 *
		 * @return string
		 */
		public static function failure_requirement() {
			$contract = self::contract();

			return isset( $contract['failure_requirement'] ) ? $contract['failure_requirement'] : 'TESTEB-33';
		}

		/**
		 * Return the supported providers.
		 *
		 * @return array<int,string>
		 */
		public static function providers() {
			$contract = self::contract();

			return isset( $contract['providers'] ) ? (array) $contract['providers'] : array( 'cdek', 'russian_post' );
		}

		/**
		 * Return the default radius in kilometres.
		 *
		 * @return float
		 */
		public static function default_radius_km() {
			$contract = self::contract();

			return isset( $contract['default_radius_km'] ) ? (float) $contract['default_radius_km'] : self::DEFAULT_RADIUS_KM;
		}

		/**
		 * Resolve a radius from the context.
		 *
		 * @param mixed $context Context.
		 * @param mixed $method  Optional method data.
		 * @return float
		 */
		public static function radius( $context = array(), $method = null ) {
			return velotok_core_delivery_pickup_radius_km( $context, $method );
		}

		/**
		 * Return the error codes used by the point list.
		 *
		 * @return array<string,string>
		 */
		public static function codes() {
			$contract = self::contract();

			return array(
				'unavailable'    => isset( $contract['unavailable_code'] ) ? $contract['unavailable_code'] : 'DELIVERY_PICKUP_POINTS_UNAVAILABLE',
				'error'          => isset( $contract['error_code'] ) ? $contract['error_code'] : 'DELIVERY_PICKUP_POINTS_ERROR',
				'no_address'     => isset( $contract['no_address_code'] ) ? $contract['no_address_code'] : 'DELIVERY_PICKUP_POINTS_ADDRESS_AMBIGUOUS',
				'no_coordinates' => isset( $contract['no_coordinates_code'] ) ? $contract['no_coordinates_code'] : 'DELIVERY_PICKUP_POINTS_NO_COORDINATES',
				'empty'          => isset( $contract['empty_code'] ) ? $contract['empty_code'] : 'DELIVERY_PICKUP_POINTS_EMPTY',
				'outside_radius' => isset( $contract['outside_radius_code'] ) ? $contract['outside_radius_code'] : 'DELIVERY_PICKUP_POINTS_OUTSIDE_RADIUS',
			);
		}
	}
}

if ( ! function_exists( 'velotok_core_pvs_contract' ) ) {
	/**
	 * Return the pickup point contract.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_core_pvs_contract() {
		return velotok_core_delivery_pickup_points_contract();
	}
}
