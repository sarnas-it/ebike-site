<?php
/**
 * Pickup point (ПВЗ) scanner.
 *
 * Spec: delivery. Requirement TESTEB-32: the pickup point list is built from
 * the delivery address within a configurable radius.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once __DIR__ . '/class-pvs-contract.php';

if ( ! class_exists( 'Velotok_PVS_Scanner' ) ) {
	/**
	 * Radius-based pickup point scanner.
	 */
	class Velotok_PVS_Scanner {
		/**
		 * Delivery address.
		 *
		 * @var mixed
		 */
		protected $address;

		/**
		 * Point list.
		 *
		 * @var mixed
		 */
		protected $points;

		/**
		 * Search context.
		 *
		 * @var array
		 */
		protected $context;

		/**
		 * Constructor.
		 *
		 * @param mixed $address Address data.
		 * @param mixed $points  Point list.
		 * @param array $context Context.
		 */
		public function __construct( $address = array(), $points = null, $context = array() ) {
			$this->address = $address;
			$this->points  = $points;
			$this->context = is_array( $context ) ? $context : array();
		}

		/**
		 * Scan the point list by radius.
		 *
		 * @param mixed $address Address data.
		 * @param mixed $points  Point list.
		 * @param array $context Context.
		 * @return array<string,mixed>
		 */
		public function scan( $address = null, $points = null, $context = null ) {
			$address = null === $address ? $this->address : $address;
			$points  = null === $points ? $this->points : $points;
			$context = null === $context ? $this->context : $context;

			return velotok_core_delivery_pickup_points( $address, $points, $context );
		}

		/**
		 * Alias for scan().
		 *
		 * @param mixed $address Address data.
		 * @param mixed $points  Point list.
		 * @param array $context Context.
		 * @return array<string,mixed>
		 */
		public function find( $address = null, $points = null, $context = null ) {
			return $this->scan( $address, $points, $context );
		}

		/**
		 * Alias for scan().
		 *
		 * @param mixed $address Address data.
		 * @param mixed $points  Point list.
		 * @param array $context Context.
		 * @return array<string,mixed>
		 */
		public function nearby( $address = null, $points = null, $context = null ) {
			return $this->scan( $address, $points, $context );
		}

		/**
		 * Alias for scan().
		 *
		 * @param mixed $address Address data.
		 * @param mixed $points  Point list.
		 * @param array $context Context.
		 * @return array<string,mixed>
		 */
		public function by_radius( $address = null, $points = null, $context = null ) {
			return $this->scan( $address, $points, $context );
		}

		/**
		 * Return the scanned point list.
		 *
		 * @param mixed $address Address data.
		 * @param mixed $points  Point list.
		 * @param array $context Context.
		 * @return array<int,mixed>
		 */
		public function points( $address = null, $points = null, $context = null ) {
			$result = $this->scan( $address, $points, $context );

			return isset( $result['points'] ) ? $result['points'] : array();
		}

		/**
		 * Alias for points().
		 *
		 * @param mixed $address Address data.
		 * @param mixed $points  Point list.
		 * @param array $context Context.
		 * @return array<int,mixed>
		 */
		public function get_points( $address = null, $points = null, $context = null ) {
			return $this->points( $address, $points, $context );
		}

		/**
		 * Return the last scan result.
		 *
		 * @param mixed $address Address data.
		 * @param mixed $points  Point list.
		 * @param array $context Context.
		 * @return array<string,mixed>
		 */
		public function result( $address = null, $points = null, $context = null ) {
			return $this->scan( $address, $points, $context );
		}

		/**
		 * Resolve the radius.
		 *
		 * @param mixed $context Context.
		 * @return float
		 */
		public function radius( $context = null ) {
			$context = null === $context ? $this->context : $context;

			return velotok_core_delivery_pickup_radius_km( $context );
		}

		/**
		 * Calculate the distance between two coordinates.
		 *
		 * @param mixed $from Origin.
		 * @param mixed $to   Destination.
		 * @return float|null
		 */
		public function distance( $from, $to ) {
			return velotok_core_delivery_pickup_distance_km( $from, $to );
		}

		/**
		 * Static scan helper.
		 *
		 * @param mixed $address Address data.
		 * @param mixed $points  Point list.
		 * @param mixed $context Context.
		 * @return array<string,mixed>
		 */
		public static function scan_points( $address = array(), $points = null, $context = array() ) {
			return velotok_core_delivery_pickup_points( $address, $points, $context );
		}

		/**
		 * Static nearby helper.
		 *
		 * @param mixed $address Address data.
		 * @param mixed $points  Point list.
		 * @param mixed $context Context.
		 * @return array<string,mixed>
		 */
		public static function nearby_points( $address = array(), $points = null, $context = array() ) {
			return velotok_core_delivery_pickup_points( $address, $points, $context );
		}

		/**
		 * Static distance helper.
		 *
		 * @param mixed $from Origin.
		 * @param mixed $to   Destination.
		 * @return float|null
		 */
		public static function distance_km( $from, $to ) {
			return velotok_core_delivery_pickup_distance_km( $from, $to );
		}
	}
}

if ( ! function_exists( 'velotok_core_pvs_scan' ) ) {
	/**
	 * Scan pickup points by radius.
	 *
	 * @param mixed $address Address data.
	 * @param mixed $points  Point list.
	 * @param mixed $context Context.
	 * @return array<string,mixed>
	 */
	function velotok_core_pvs_scan( $address = array(), $points = null, $context = array() ) {
		return velotok_core_delivery_pickup_points( $address, $points, $context );
	}
}

if ( ! function_exists( 'velotok_core_pvs_pickup_points' ) ) {
	/**
	 * Return pickup points by radius.
	 *
	 * @param mixed $address Address data.
	 * @param mixed $points  Point list.
	 * @param mixed $context Context.
	 * @return array<string,mixed>
	 */
	function velotok_core_pvs_pickup_points( $address = array(), $points = null, $context = array() ) {
		return velotok_core_delivery_pickup_points( $address, $points, $context );
	}
}
