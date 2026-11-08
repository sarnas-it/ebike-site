<?php
/**
 * Pickup point (ПВЗ) admin helpers.
 *
 * Spec: delivery. Requirements TESTEB-32 and TESTEB-33: pickup points are
 * selected by radius and a broken point list is explicit and visible to the
 * administrator.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once __DIR__ . '/class-pvs-contract.php';

if ( ! class_exists( 'Velotok_PVS_Admin' ) ) {
	/**
	 * Pickup point admin facade.
	 */
	class Velotok_PVS_Admin {
		/**
		 * Return an admin-visible point list error.
		 *
		 * @param array<string,mixed> $result Point scan result.
		 * @return array<string,mixed>
		 */
		public static function error( $result = array() ) {
			$message = is_array( $result ) && isset( $result['message'] ) ? $result['message'] : 'Список пунктов выдачи недоступен.';

			return array(
				'ok'          => false,
				'code'        => is_array( $result ) && ! empty( $result['code'] ) ? $result['code'] : 'DELIVERY_PICKUP_POINTS_UNAVAILABLE',
				'message'     => $message,
				'errors'      => is_array( $result ) && isset( $result['errors'] ) && is_array( $result['errors'] ) ? $result['errors'] : array(),
				'visible'     => true,
				'explicit'    => true,
				'requirement' => 'TESTEB-33',
				'admin'       => true,
			);
		}

		/**
		 * Return the contract used by the admin screen.
		 *
		 * @return array<string,mixed>
		 */
		public static function contract() {
			return velotok_core_delivery_pickup_points_contract();
		}
	}
}
