<?php
/**
 * Delivery methods sourced from WooCommerce shipping zones.
 *
 * Spec: delivery (requirement TESTEB-28).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

if ( ! function_exists( 'velotok_core_delivery_zones_contract' ) ) {
	/**
	 * Return the delivery zones contract.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_zones_contract() {
		static $contract = null;

		if ( null !== $contract ) {
			return $contract;
		}

		$contract = array(
			'spec'                    => 'delivery',
			'parent_spec'             => 'ecommerce-store',
			'requirement'             => 'TESTEB-28',
			'task'                    => 'TESTEB-28',
			'requirement_ids'         => array( 'TESTEB-28', 'TESTEB-29', 'TESTEB-30', 'TESTEB-31', 'TESTEB-32', 'TESTEB-33' ),
			'source'                  => 'woocommerce_zones',
			'provider'                => 'woocommerce',
			'provider_label'          => 'WooCommerce',
			'mocked'                  => false,
			'network'                 => false,
			'reads_woocommerce_zones' => true,
			'zone_source'             => 'WC_Shipping_Zones',
			'default_country'         => 'RU',
			'methods'                 => array(
				'cdek'         => 'СДЭК',
				'russian_post' => 'Почта РФ',
				'flat_rate'    => 'Доставка',
				'free_shipping'=> 'Бесплатная доставка',
				'local_pickup' => 'Самовывоз',
			),
			'supported_providers'     => array( 'cdek', 'russian_post' ),
			'eta'                     => array(
				'requirement'=> 'TESTEB-31',
				'format'    => 'range',
				'min_key'   => 'min_days',
				'max_key'   => 'max_days',
				'min_keys'  => array( 'min_days', 'min', 'from', 'delivery_min_days', 'eta_min_days' ),
				'max_keys'  => array( 'max_days', 'max', 'to', 'delivery_max_days', 'eta_max_days' ),
				'label'     => '{min}–{max} дн.',
				'min_label' => '{min} дн.',
				'max_label' => '{max} дн.',
				'order_meta'=> array(
					'range' => '_velotok_delivery_eta',
					'min'   => '_velotok_delivery_eta_min_days',
					'max'   => '_velotok_delivery_eta_max_days',
					'label' => '_velotok_delivery_eta_label',
				),
			),
			'weight'                  => array(
				'requirement'    => 'TESTEB-30',
				'unit'           => 'kg',
				'source'         => 'order.weight',
				'fallback_source'=> 'items',
				'sources'        => array( 'order.weight', 'items', 'empty_order' ),
				'keys'           => array( 'weight', 'order_weight', 'total_weight' ),
				'item_keys'      => array( 'weight', 'product_weight' ),
				'quantity_keys'  => array( 'quantity', 'qty' ),
				'rounding'       => 'ceil',
				'ambiguous_code' => 'DELIVERY_WEIGHT_AMBIGUOUS',
			),
			'free_shipping'           => array(
				'threshold_key' => 'free_from',
				'rule'          => 'subtotal >= free_from',
			),
			'cost'                    => array(
				'requirement'            => 'TESTEB-29',
				'tariff'                 => 'base + per_kg * ceil(weight)',
				'provider_tariff'        => 'provider',
				'threshold_applies_to'   => 'subtotal',
				'threshold_applies_to_provider' => true,
				'fallback_tariff'        => false,
			),
			'pickup'                  => array(
				'enabled'             => true,
				'method_id'           => 'local_pickup',
				'label'               => 'Самовывоз',
				'independent_of_zone' => true,
				'only_russia'         => true,
				'country'             => 'RU',
				'points_by_radius'    => true,
				'points_requirement'  => 'TESTEB-32',
			),
			'pickup_points'           => array(
				'requirement'        => 'TESTEB-32',
				'source'             => 'provider',
				'providers'          => array( 'cdek', 'russian_post' ),
				'unit'               => 'km',
				'distance'           => 'haversine',
				'sort'               => 'distance',
				'sort_by'            => 'distance',
				'radius_key'         => 'radius_km',
				'radius_keys'        => array( 'radius_km', 'pickup_radius_km', 'pickup_points_radius_km', 'pickup_points_radius', 'max_distance_km', 'radius' ),
				'default_radius_km'  => 10.0,
				'default_radius'     => 10.0,
				'radius_km'          => 10.0,
				'radius'             => 10.0,
				'max_radius_km'      => 100.0,
				'max_distance_km'    => 100.0,
				'points_key'         => 'points',
				'coordinate_keys'    => array( 'lat', 'latitude', 'lon', 'lng', 'longitude', 'coordinates', 'coords', 'location', 'geo' ),
				'require_address_coordinates' => true,
				'only_russia'        => true,
				'country'            => 'RU',
				'explicit_failure'   => true,
				'failure_visible'    => true,
				'failure_requirement'=> 'TESTEB-33',
				'failure_explicit'   => true,
				'failure_silent'     => false,
				'failure_fallback_to_empty_list' => false,
				'failure_fallback_to_fake_points' => false,
				'unavailable_code'   => 'DELIVERY_PICKUP_POINTS_UNAVAILABLE',
				'error_code'         => 'DELIVERY_PICKUP_POINTS_ERROR',
				'no_address_code'    => 'DELIVERY_PICKUP_POINTS_ADDRESS_AMBIGUOUS',
				'no_coordinates_code'=> 'DELIVERY_PICKUP_POINTS_NO_COORDINATES',
				'empty_code'         => 'DELIVERY_PICKUP_POINTS_EMPTY',
				'outside_radius_code'=> 'DELIVERY_PICKUP_POINTS_OUTSIDE_RADIUS',
			),
			'out_of_zone'             => array(
				'code'             => 'DELIVERY_ZONE_NOT_FOUND',
				'message'          => 'Адрес не попадает ни в одну зону доставки WooCommerce.',
				'pickup_available' => true,
			),
			'ambiguous_address'       => array(
				'code'                 => 'DELIVERY_ADDRESS_AMBIGUOUS',
				'message'              => 'Адрес доставки неоднозначен.',
				'same_as_out_of_zone'  => true,
				'pickup_available'     => true,
			),
			'zones_unavailable'       => array(
				'code'    => 'DELIVERY_ZONES_UNAVAILABLE',
				'message' => 'Зоны доставки WooCommerce недоступны.',
			),
			'provider_error'          => array(
				'code'             => 'DELIVERY_PROVIDER_ERROR',
				'visible'          => true,
				'fallback_tariff'  => false,
				'replace_with_stub'=> false,
			),
			'test_mode'               => array(
				'explicit'   => true,
				'banner'     => true,
				'banner_text'=> 'Тестовый режим доставки',
			),
			'order'                   => array(
				'method_field'   => 'delivery_method',
				'cost_field'     => 'delivery_cost',
				'tracking_field' => 'delivery_tracking_number',
				'tracking_visible_to_owner' => true,
				'persisted'      => true,
			),
			'provider_configuration'  => array(
				'admin_visible' => true,
				'error_code'    => 'DELIVERY_PROVIDER_CONFIGURATION_ERROR',
			),
		);

		$pickup_failure = array(
			'requirement'          => 'TESTEB-33',
			'explicit'             => true,
			'visible'              => true,
			'silent'               => false,
			'fallback_to_empty_list' => false,
			'fallback_to_fake_points' => false,
			'success_on_failure'   => false,
			'mark_success'         => false,
			'keep_pickup_available'=> true,
			'codes'                => array(
				'unavailable'    => 'DELIVERY_PICKUP_POINTS_UNAVAILABLE',
				'error'          => 'DELIVERY_PICKUP_POINTS_ERROR',
				'no_address'     => 'DELIVERY_PICKUP_POINTS_ADDRESS_AMBIGUOUS',
				'no_coordinates' => 'DELIVERY_PICKUP_POINTS_NO_COORDINATES',
				'empty'          => 'DELIVERY_PICKUP_POINTS_EMPTY',
				'outside_radius' => 'DELIVERY_PICKUP_POINTS_OUTSIDE_RADIUS',
			),
			'unavailable_code'     => 'DELIVERY_PICKUP_POINTS_UNAVAILABLE',
			'error_code'           => 'DELIVERY_PICKUP_POINTS_ERROR',
			'no_address_code'      => 'DELIVERY_PICKUP_POINTS_ADDRESS_AMBIGUOUS',
			'no_coordinates_code'  => 'DELIVERY_PICKUP_POINTS_NO_COORDINATES',
			'empty_code'           => 'DELIVERY_PICKUP_POINTS_EMPTY',
			'outside_radius_code'  => 'DELIVERY_PICKUP_POINTS_OUTSIDE_RADIUS',
		);

		$contract['pickup_points']['failure_behavior'] = $pickup_failure;
		$contract['pickup_points_failure']   = $pickup_failure;
		$contract['pickup_points_unavailable'] = $pickup_failure;
		$contract['pickup_points_error']     = $pickup_failure;
		$contract['pickup_points_availability'] = $pickup_failure;

		return $contract;
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_value' ) ) {
	/**
	 * Read a value from arrays or WooCommerce-like objects.
	 *
	 * @param mixed        $source  Source value.
	 * @param array|string $keys    Candidate keys.
	 * @param mixed        $default Default value.
	 * @return mixed
	 */
	function velotok_core_delivery_zones_value( $source, $keys, $default = null ) {
		foreach ( (array) $keys as $key ) {
			if ( is_array( $source ) && array_key_exists( $key, $source ) ) {
				return $source[ $key ];
			}

			if ( ! is_object( $source ) ) {
				continue;
			}

			if ( isset( $source->$key ) ) {
				return $source->$key;
			}

			$methods = array(
				'get_' . $key,
				'get' . str_replace( ' ', '', ucwords( str_replace( '_', ' ', $key ) ) ),
			);

			foreach ( $methods as $method ) {
				if ( method_exists( $source, $method ) ) {
					return $source->$method();
				}
			}
		}

		return $default;
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_scalar' ) ) {
	/**
	 * Normalize a scalar value.
	 *
	 * @param mixed  $value   Value.
	 * @param string $default Default.
	 * @return string
	 */
	function velotok_core_delivery_zones_scalar( $value, $default = '' ) {
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return $default;
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_number' ) ) {
	/**
	 * Normalize a numeric value.
	 *
	 * @param mixed $value   Value.
	 * @param float $default Default.
	 * @return float
	 */
	function velotok_core_delivery_zones_number( $value, $default = 0.0 ) {
		return is_numeric( $value ) ? (float) $value : (float) $default;
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_settings' ) ) {
	/**
	 * Return method settings as an array.
	 *
	 * @param mixed $method Method data.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_zones_settings( $method ) {
		foreach ( array( 'instance_settings', 'settings', 'options', 'config' ) as $key ) {
			$settings = velotok_core_delivery_zones_value( $method, array( $key ), null );

			if ( is_object( $settings ) ) {
				$settings = get_object_vars( $settings );
			}

			if ( is_array( $settings ) && ! empty( $settings ) ) {
				return $settings;
			}
		}

		return array();
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_method_enabled' ) ) {
	/**
	 * Resolve whether a shipping method is enabled.
	 *
	 * Supports injected arrays, WooCommerce method objects and their settings.
	 *
	 * @param mixed $method Method data.
	 * @return bool
	 */
	function velotok_core_delivery_zones_method_enabled( $method ) {
		$settings = velotok_core_delivery_zones_settings( $method );
		$value    = null;

		if ( is_array( $method ) && array_key_exists( 'enabled', $method ) ) {
			$value = $method['enabled'];
		} elseif ( is_object( $method ) && isset( $method->enabled ) ) {
			$value = $method->enabled;
		} elseif ( array_key_exists( 'enabled', $settings ) ) {
			$value = $settings['enabled'];
		} elseif ( is_object( $method ) ) {
			foreach ( array( 'is_enabled', 'isEnabled' ) as $candidate ) {
				if ( method_exists( $method, $candidate ) ) {
					$value = $method->$candidate();
					break;
				}
			}
		}

		if ( null === $value ) {
			return true;
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		return ! in_array( strtolower( trim( (string) $value ) ), array( 'no', 'false', '0', 'off' ), true );
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_address' ) ) {
	/**
	 * Normalize the destination address.
	 *
	 * @param mixed $address Address data.
	 * @return array<string,string>
	 */
	function velotok_core_delivery_zones_address( $address ) {
		if ( is_string( $address ) ) {
			$address = array( 'city' => $address );
		}

		$normalized = array(
			'country'  => '',
			'state'    => '',
			'postcode' => '',
			'city'     => '',
		);

		foreach ( array_keys( $normalized ) as $key ) {
			$value = velotok_core_delivery_zones_value(
				$address,
				array(
					$key,
					'country' === $key ? 'country_code' : $key,
					'state' === $key ? 'state_code' : $key,
					'postcode' === $key ? 'zip' : $key,
				),
				''
			);

			$normalized[ $key ] = trim( velotok_core_delivery_zones_scalar( $value ) );
		}

		$normalized['country'] = strtoupper( $normalized['country'] );

		$coordinates = velotok_core_delivery_pickup_point_coordinates( $address );

		if ( null !== $coordinates ) {
			$normalized['lat']         = $coordinates['lat'];
			$normalized['lon']         = $coordinates['lon'];
			$normalized['latitude']    = $coordinates['lat'];
			$normalized['longitude']   = $coordinates['lon'];
			$normalized['coordinates'] = array( 'lat' => $coordinates['lat'], 'lon' => $coordinates['lon'] );
		}

		return $normalized;
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_locations' ) ) {
	/**
	 * Normalize zone locations.
	 *
	 * @param mixed $zone Zone data.
	 * @return array<int,array<string,string>>
	 */
	function velotok_core_delivery_zones_locations( $zone ) {
		$locations = velotok_core_delivery_zones_value( $zone, array( 'zone_locations', 'locations' ), array() );

		if ( is_object( $locations ) && method_exists( $locations, 'get_locations' ) ) {
			$locations = $locations->get_locations();
		}

		$normalized = array();

		foreach ( (array) $locations as $location ) {
			$type = velotok_core_delivery_zones_value( $location, array( 'type', 'location_type' ), '' );
			$code = velotok_core_delivery_zones_value( $location, array( 'code', 'location_code', 'value' ), '' );

			if ( is_string( $location ) && false !== strpos( $location, ':' ) ) {
				list( $type, $code ) = explode( ':', $location, 2 );
			}

			$type = strtolower( trim( velotok_core_delivery_zones_scalar( $type ) ) );
			$code = trim( velotok_core_delivery_zones_scalar( $code ) );

			if ( '' === $type || '' === $code ) {
				continue;
			}

			$normalized[] = array(
				'type' => $type,
				'code' => $code,
			);
		}

		return $normalized;
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_zone_methods' ) ) {
	/**
	 * Return raw shipping methods for a zone.
	 *
	 * @param mixed $zone Zone data.
	 * @return array<int,mixed>
	 */
	function velotok_core_delivery_zones_zone_methods( $zone ) {
		$methods = velotok_core_delivery_zones_value( $zone, array( 'shipping_methods', 'methods', 'method_instances' ), array() );

		if ( is_object( $methods ) && method_exists( $methods, 'get_shipping_methods' ) ) {
			$methods = $methods->get_shipping_methods();
		}

		return array_values( (array) $methods );
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_data' ) ) {
	/**
	 * Return WooCommerce shipping zones or an injected zone list.
	 *
	 * @param mixed $zones Zone list or null to read WooCommerce.
	 * @return array<int,mixed>|null
	 */
	function velotok_core_delivery_zones_data( $zones = null ) {
		if ( null !== $zones ) {
			return is_array( $zones ) ? array_values( $zones ) : null;
		}

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'velotok_core_delivery_zones', null );

			if ( is_array( $filtered ) ) {
				return array_values( $filtered );
			}
		}

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return null;
		}

		$zones = WC_Shipping_Zones::get_zones();

		if ( class_exists( 'WC_Shipping_Zone' ) ) {
			$rest_of_world = new WC_Shipping_Zone( 0 );

			if ( is_object( $rest_of_world ) ) {
				$zones[] = $rest_of_world;
			}
		}

		return array_values( (array) $zones );
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_location_matches' ) ) {
	/**
	 * Check one zone location against an address.
	 *
	 * @param array<string,string> $location Location.
	 * @param array<string,string> $address  Address.
	 * @return bool
	 */
	function velotok_core_delivery_zones_location_matches( $location, $address ) {
		$type = isset( $location['type'] ) ? strtolower( (string) $location['type'] ) : '';
		$code = isset( $location['code'] ) ? (string) $location['code'] : '';

		if ( 'country' === $type ) {
			return '' !== $address['country'] && strtoupper( $code ) === $address['country'];
		}

		if ( 'state' === $type ) {
			$state = strtoupper( $address['state'] );

			if ( '' === $state ) {
				return false;
			}

			$state = false !== strpos( $state, ':' ) ? substr( $state, strpos( $state, ':' ) + 1 ) : $state;
			$code  = strtoupper( $code );
			$code  = false !== strpos( $code, ':' ) ? substr( $code, strpos( $code, ':' ) + 1 ) : $code;

			return $code === $state;
		}

		if ( 'postcode' === $type ) {
			$postcode = trim( $address['postcode'] );

			if ( '' === $postcode ) {
				return false;
			}

			$pattern = trim( $code );

			if ( false !== strpos( $pattern, '*' ) ) {
				$regex = '/^' . str_replace( '\\*', '.*', preg_quote( $pattern, '/' ) ) . '$/i';

				return (bool) preg_match( $regex, $postcode );
			}

			if ( false !== strpos( $pattern, '...' ) ) {
				list( $from, $to ) = explode( '...', $pattern, 2 );
				$from = trim( $from );
				$to   = trim( $to );

				if ( '' !== $from && '' !== $to ) {
					if ( ctype_digit( $from ) && ctype_digit( $to ) && ctype_digit( $postcode ) ) {
						return (int) $postcode >= (int) $from && (int) $postcode <= (int) $to;
					}

					return strtoupper( $postcode ) >= strtoupper( $from ) && strtoupper( $postcode ) <= strtoupper( $to );
				}

				$prefix = '' !== $from ? $from : $to;

				return '' !== $prefix && 0 === strpos( strtoupper( $postcode ), strtoupper( $prefix ) );
			}

			return strtoupper( $pattern ) === strtoupper( $postcode );
		}

		if ( 'city' === $type ) {
			return '' !== $address['city'] && strtolower( $code ) === strtolower( $address['city'] );
		}

		if ( 'continent' === $type ) {
			return '' !== $address['country'] && strtoupper( $code ) === $address['country'];
		}

		return false;
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_match' ) ) {
	/**
	 * Match an address to a WooCommerce shipping zone.
	 *
	 * @param mixed $address Address data.
	 * @param mixed $zones   Zone list or null.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_zones_match( $address = array(), $zones = null ) {
		$contract  = velotok_core_delivery_zones_contract();
		$address   = velotok_core_delivery_zones_address( $address );
		$zone_list = velotok_core_delivery_zones_data( $zones );

		$ambiguous = '' === $address['country']
			&& '' === $address['state']
			&& '' === $address['postcode']
			&& '' === $address['city'];

		if ( null === $zone_list ) {
			return array(
				'matched' => false,
				'code'    => $contract['zones_unavailable']['code'],
				'message' => $contract['zones_unavailable']['message'],
				'zone'    => null,
				'zone_id' => 0,
				'address' => $address,
				'errors'  => array( $contract['zones_unavailable'] ),
			);
		}

		if ( $ambiguous ) {
			return array(
				'matched' => false,
				'code'    => $contract['ambiguous_address']['code'],
				'message' => $contract['ambiguous_address']['message'],
				'zone'    => null,
				'zone_id' => 0,
				'address' => $address,
				'errors'  => array( $contract['ambiguous_address'] ),
			);
		}

		usort(
			$zone_list,
			function ( $left, $right ) {
				$left_order  = velotok_core_delivery_zones_number( velotok_core_delivery_zones_value( $left, array( 'zone_order', 'order' ), 0 ) );
				$right_order = velotok_core_delivery_zones_number( velotok_core_delivery_zones_value( $right, array( 'zone_order', 'order' ), 0 ) );

				return $left_order <=> $right_order;
			}
		);

		foreach ( $zone_list as $zone ) {
			$zone_id = (int) velotok_core_delivery_zones_number( velotok_core_delivery_zones_value( $zone, array( 'zone_id', 'id' ), 0 ) );
			$name    = velotok_core_delivery_zones_scalar( velotok_core_delivery_zones_value( $zone, array( 'zone_name', 'name' ), '' ) );
			$is_rest = 0 === $zone_id || false !== stripos( $name, 'rest of the world' ) || false !== stripos( $name, 'остальной мир' );

			if ( $is_rest ) {
				continue;
			}

			foreach ( velotok_core_delivery_zones_locations( $zone ) as $location ) {
				if ( velotok_core_delivery_zones_location_matches( $location, $address ) ) {
					return array(
						'matched' => true,
						'code'    => 'DELIVERY_ZONE_MATCHED',
						'message' => '',
						'zone'    => $zone,
						'zone_id' => $zone_id,
						'address' => $address,
						'errors'  => array(),
					);
				}
			}
		}

		$rest_zone = null;

		foreach ( $zone_list as $zone ) {
			$zone_id = (int) velotok_core_delivery_zones_number( velotok_core_delivery_zones_value( $zone, array( 'zone_id', 'id' ), 0 ) );
			$name    = velotok_core_delivery_zones_scalar( velotok_core_delivery_zones_value( $zone, array( 'zone_name', 'name' ), '' ) );

			if ( 0 === $zone_id || false !== stripos( $name, 'rest of the world' ) || false !== stripos( $name, 'остальной мир' ) ) {
				$rest_zone = $zone;
				break;
			}
		}

		return array(
			'matched' => false,
			'code'    => $contract['out_of_zone']['code'],
			'message' => $contract['out_of_zone']['message'],
			'zone'    => $rest_zone,
			'zone_id' => 0,
			'address' => $address,
			'errors'  => array( $contract['out_of_zone'] ),
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_method_id' ) ) {
	/**
	 * Resolve a provider-neutral method identifier.
	 *
	 * @param mixed $method Method data.
	 * @return string
	 */
	function velotok_core_delivery_zones_method_id( $method ) {
		$settings   = velotok_core_delivery_zones_settings( $method );
		$candidates = array(
			velotok_core_delivery_zones_value( $method, array( 'provider', 'provider_id', 'carrier' ), '' ),
			isset( $settings['provider'] ) ? $settings['provider'] : '',
			isset( $settings['provider_id'] ) ? $settings['provider_id'] : '',
			isset( $settings['method_id'] ) ? $settings['method_id'] : '',
			velotok_core_delivery_zones_value( $method, array( 'method_id', 'id', 'instance_id' ), '' ),
			velotok_core_delivery_zones_value( $method, array( 'title', 'label', 'method_title' ), '' ),
		);

		foreach ( $candidates as $candidate ) {
			$candidate = strtolower( velotok_core_delivery_zones_scalar( $candidate ) );

			if ( '' === $candidate ) {
				continue;
			}

			if ( false !== strpos( $candidate, 'cdek' ) || false !== strpos( $candidate, 'сдэк' ) ) {
				return 'cdek';
			}

			if ( false !== strpos( $candidate, 'russian' ) || false !== strpos( $candidate, 'post' ) || false !== strpos( $candidate, 'pochta' ) || false !== strpos( $candidate, 'почт' ) ) {
				return 'russian_post';
			}

			if ( false !== strpos( $candidate, 'local_pickup' ) || false !== strpos( $candidate, 'pickup' ) || false !== strpos( $candidate, 'самовывоз' ) ) {
				return 'local_pickup';
			}

			if ( false !== strpos( $candidate, 'free_shipping' ) || false !== strpos( $candidate, 'бесплат' ) ) {
				return 'free_shipping';
			}

			if ( false !== strpos( $candidate, 'flat_rate' ) || false !== strpos( $candidate, 'flat' ) ) {
				return 'flat_rate';
			}
		}

		$fallback = velotok_core_delivery_zones_scalar(
			velotok_core_delivery_zones_value( $method, array( 'method_id', 'id', 'instance_id' ), 'shipping' )
		);

		return '' !== $fallback ? strtolower( $fallback ) : 'shipping';
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_eta_parse' ) ) {
	/**
	 * Parse an ETA range written as text.
	 *
	 * Accepts values such as «1–3», «1-3 дн.», «от 2 до 5 дней» or a single
	 * value such as «3 дн.». Numeric scalars are handled by the caller.
	 *
	 * @param mixed $value Raw ETA value.
	 * @return array<string,int|null>
	 */
	function velotok_core_delivery_zones_eta_parse( $value ) {
		$parsed = array(
			'min' => null,
			'max' => null,
		);

		if ( ! is_scalar( $value ) ) {
			return $parsed;
		}

		$text = trim( (string) $value );

		if ( '' === $text ) {
			return $parsed;
		}

		if ( preg_match( '/^(?:\s*от\s*)?(\d+)\s*(?:[-–—]|\.\.|\s+до\s+)\s*(\d+)/iu', $text, $matches ) ) {
			$parsed['min'] = (int) $matches[1];
			$parsed['max'] = (int) $matches[2];

			return $parsed;
		}

		if ( ! is_numeric( $text ) && preg_match( '/(\d+)/u', $text, $matches ) ) {
			$parsed['min'] = (int) $matches[1];
			$parsed['max'] = (int) $matches[1];
		}

		return $parsed;
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_eta_label' ) ) {
	/**
	 * Format an ETA range using the delivery contract labels.
	 *
	 * @param int|null $min Minimum days.
	 * @param int|null $max Maximum days.
	 * @return string
	 */
	function velotok_core_delivery_zones_eta_label( $min = null, $max = null ) {
		$contract = velotok_core_delivery_zones_contract();

		if ( null !== $min && null !== $max ) {
			return $min === $max
				? str_replace( '{min}', (string) $min, $contract['eta']['min_label'] )
				: str_replace( array( '{min}', '{max}' ), array( (string) $min, (string) $max ), $contract['eta']['label'] );
		}

		if ( null !== $min ) {
			return str_replace( '{min}', (string) $min, $contract['eta']['min_label'] );
		}

		if ( null !== $max ) {
			return str_replace( '{max}', (string) $max, $contract['eta']['max_label'] );
		}

		return '';
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_eta_range' ) ) {
	/**
	 * Return a delivery ETA range.
	 *
	 * The range is stored and returned as both boundaries (min/max), never as
	 * an unordered or single-sided value. Configured text ranges such as
	 * «1–3» are parsed as well.
	 *
	 * @param mixed $method Method data.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_zones_eta_range( $method ) {
		$contract = velotok_core_delivery_zones_contract();
		$settings = velotok_core_delivery_zones_settings( $method );
		$sentinel = new \stdClass();

		$min_keys = $contract['eta']['min_keys'];
		$max_keys = $contract['eta']['max_keys'];
		$sources  = array();
		$eta      = velotok_core_delivery_zones_value(
			$method,
			array( 'eta', 'eta_range', 'delivery_eta' ),
			$sentinel
		);

		if ( $eta !== $sentinel && null !== $eta ) {
			$sources[] = $eta;
		}

		$sources[] = $method;
		$sources[] = $settings;

		$pick = function ( $source, $keys ) use ( $sentinel ) {
			foreach ( (array) $keys as $key ) {
				$value = velotok_core_delivery_zones_value( $source, array( $key ), $sentinel );

				if ( $value === $sentinel || null === $value || ! is_scalar( $value ) ) {
					continue;
				}

				if ( '' !== trim( (string) $value ) ) {
					return $value;
				}
			}

			return $sentinel;
		};

		$normalize = function ( $value ) {
			if ( ! is_scalar( $value ) ) {
				return null;
			}

			$value = trim( (string) $value );

			if ( '' === $value ) {
				return null;
			}

			if ( false !== strpos( $value, ',' ) && false === strpos( $value, '.' ) ) {
				$value = str_replace( ',', '.', $value );
			}

			return is_numeric( $value ) ? max( 0, (int) ceil( (float) $value ) ) : null;
		};

		$min = null;
		$max = null;

		foreach ( $sources as $source ) {
			if ( null === $min ) {
				$candidate = $pick( $source, $min_keys );

				if ( $candidate !== $sentinel ) {
					$min = $normalize( $candidate );
				}
			}

			if ( null === $max ) {
				$candidate = $pick( $source, $max_keys );

				if ( $candidate !== $sentinel ) {
					$max = $normalize( $candidate );
				}
			}
		}

		$label_keys = array( 'label', 'delivery_time', 'eta_label', 'text', 'range' );

		foreach ( $sources as $source ) {
			if ( is_scalar( $source ) ) {
				$parsed = velotok_core_delivery_zones_eta_parse( $source );

				if ( null !== $parsed['min'] && null === $min ) {
					$min = $parsed['min'];
				}

				if ( null !== $parsed['max'] && null === $max ) {
					$max = $parsed['max'];
				}

				continue;
			}

			foreach ( $label_keys as $label_key ) {
				$value = velotok_core_delivery_zones_value( $source, array( $label_key ), $sentinel );

				if ( $value === $sentinel || null === $value || ! is_scalar( $value ) ) {
					continue;
				}

				$parsed = velotok_core_delivery_zones_eta_parse( $value );

				if ( null !== $parsed['min'] && null === $min ) {
					$min = $parsed['min'];
				}

				if ( null !== $parsed['max'] && null === $max ) {
					$max = $parsed['max'];
				}
			}
		}

		if ( null !== $min && null !== $max && $min > $max ) {
			$swap = $min;
			$min  = $max;
			$max  = $swap;
		}

		$label = velotok_core_delivery_zones_eta_label( $min, $max );

		return array(
			'min_days' => $min,
			'max_days' => $max,
			'min'      => $min,
			'max'      => $max,
			'range'    => array( 'min' => $min, 'max' => $max ),
			'label'    => $label,
			'text'     => $label,
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_weight_number' ) ) {
	/**
	 * Normalize a numeric weight value.
	 *
	 * Accepts numbers and numeric strings. A decimal comma is normalized so
	 * the same order never yields two different weights depending on how the
	 * value was formatted.
	 *
	 * @param mixed $value Weight value.
	 * @return float|null
	 */
	function velotok_core_delivery_zones_weight_number( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			$number = (float) $value;

			return is_finite( $number ) ? $number : null;
		}

		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		if ( false !== strpos( $value, ',' ) && false === strpos( $value, '.' ) ) {
			$value = str_replace( ',', '.', $value );
		}

		$number = is_numeric( $value ) ? (float) $value : null;

		return null !== $number && is_finite( $number ) ? $number : null;
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_item_weight' ) ) {
	/**
	 * Resolve the weight of one order line without guessing.
	 *
	 * Supports plain item arrays, WooCommerce-like line item objects and the
	 * product object stored inside a line item. The stored product weight is
	 * used as a fallback for the shop's own kilogram meta field.
	 *
	 * @param mixed $item Order line item.
	 * @return float|null
	 */
	function velotok_core_delivery_zones_item_weight( $item ) {
		$contract = velotok_core_delivery_zones_contract();
		$direct   = velotok_core_delivery_zones_weight_number(
			velotok_core_delivery_zones_value( $item, $contract['weight']['item_keys'], null )
		);

		if ( null !== $direct ) {
			return $direct;
		}

		$product = null;

		if ( is_object( $item ) && method_exists( $item, 'get_product' ) ) {
			$product = $item->get_product();
		} else {
			$product = velotok_core_delivery_zones_value( $item, array( 'product', 'data', 'variation' ), null );
		}

		if ( null === $product || $product === $item ) {
			return null;
		}

		$product_weight = velotok_core_delivery_zones_weight_number(
			velotok_core_delivery_zones_value( $product, $contract['weight']['item_keys'], null )
		);

		if ( null !== $product_weight ) {
			return $product_weight;
		}

		if ( function_exists( 'get_post_meta' ) ) {
			$product_id = (int) velotok_core_delivery_zones_number(
				velotok_core_delivery_zones_value( $product, array( 'id', 'product_id' ), 0 )
			);

			if ( $product_id > 0 ) {
				$meta_weight = velotok_core_delivery_zones_weight_number(
					get_post_meta( $product_id, '_velotok_weight_kg', true )
				);

				if ( null !== $meta_weight ) {
					return $meta_weight;
				}
			}
		}

		return null;
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_order_weight' ) ) {
	/**
	 * Resolve order weight without ambiguity.
	 *
	 * The order weight is authoritative when present. If it is absent, the
	 * weight is summed from the order lines; a line without a numeric weight
	 * makes the whole result ambiguous instead of silently becoming zero.
	 *
	 * @param mixed $order Order data.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_zones_order_weight( $order = array() ) {
		$contract         = velotok_core_delivery_zones_contract();
		$weight           = null;
		$invalid_explicit = false;
		$sentinel         = new \stdClass();

		foreach ( (array) $contract['weight']['keys'] as $weight_key ) {
			$raw_weight = velotok_core_delivery_zones_value( $order, array( $weight_key ), $sentinel );

			if ( $raw_weight === $sentinel || null === $raw_weight ) {
				continue;
			}

			if ( is_string( $raw_weight ) && '' === trim( $raw_weight ) ) {
				continue;
			}

			$normalized = velotok_core_delivery_zones_weight_number( $raw_weight );

			if ( null === $normalized ) {
				$invalid_explicit = true;
				continue;
			}

			$weight = $normalized;
			break;
		}

		if ( null === $weight && $invalid_explicit ) {
			return array(
				'ok'     => false,
				'weight' => null,
				'source' => 'order.weight',
				'unit'   => $contract['weight']['unit'],
				'code'   => $contract['weight']['ambiguous_code'],
				'error'  => 'Вес заказа задан некорректно.',
			);
		}

		if ( null !== $weight ) {
			if ( $weight < 0 ) {
				return array(
					'ok'     => false,
					'weight' => null,
					'source' => 'order.weight',
					'unit'   => $contract['weight']['unit'],
					'code'   => $contract['weight']['ambiguous_code'],
					'error'  => 'Вес заказа не может быть отрицательным.',
				);
			}

			return array(
				'ok'     => true,
				'weight' => $weight,
				'source' => 'order.weight',
				'unit'   => $contract['weight']['unit'],
			);
		}

		$items = velotok_core_delivery_zones_value( $order, array( 'items', 'line_items', 'products' ), array() );

		if ( ! is_array( $items ) || empty( $items ) ) {
			return array(
				'ok'     => true,
				'weight' => 0.0,
				'source' => 'empty_order',
				'unit'   => $contract['weight']['unit'],
			);
		}

		$total = 0.0;

		foreach ( $items as $item ) {
			$item_weight = velotok_core_delivery_zones_item_weight( $item );

			if ( null === $item_weight || $item_weight < 0 ) {
				return array(
					'ok'     => false,
					'weight' => null,
					'source' => 'items',
					'unit'   => $contract['weight']['unit'],
					'code'   => $contract['weight']['ambiguous_code'],
					'error'  => null === $item_weight
						? 'Вес хотя бы одного товара не задан.'
						: 'Вес товара не может быть отрицательным.',
				);
			}

			$quantity = velotok_core_delivery_zones_value( $item, $contract['weight']['quantity_keys'], 1 );
			$quantity = is_numeric( $quantity ) ? max( 0, (float) $quantity ) : 1.0;

			$total += $item_weight * $quantity;
		}

		return array(
			'ok'     => true,
			'weight' => $total,
			'source' => 'items',
			'unit'   => $contract['weight']['unit'],
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_provider_rate' ) ) {
	/**
	 * Ask a configured provider for the real tariff.
	 *
	 * @param mixed $method  Method data.
	 * @param mixed $order   Order data.
	 * @param array $context Context.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_zones_provider_rate( $method, $order, $context = array() ) {
		$contract  = velotok_core_delivery_zones_contract();
		$method_id = velotok_core_delivery_zones_method_id( $method );
		$settings  = velotok_core_delivery_zones_settings( $method );
		$callback  = null;

		if ( isset( $context['providers'][ $method_id ] ) && is_callable( $context['providers'][ $method_id ] ) ) {
			$callback = $context['providers'][ $method_id ];
		} elseif ( isset( $context['providers'][ velotok_core_delivery_zones_scalar( velotok_core_delivery_zones_value( $method, array( 'provider', 'provider_id' ), '' ) ) ] ) ) {
			$provider = velotok_core_delivery_zones_scalar( velotok_core_delivery_zones_value( $method, array( 'provider', 'provider_id' ), '' ) );
			$callback = $context['providers'][ $provider ];
			$callback = is_callable( $callback ) ? $callback : null;
		}

		if ( null === $callback ) {
			foreach ( array( 'rate_provider', 'provider_callback', 'cost_provider' ) as $key ) {
				if ( is_array( $method ) && isset( $method[ $key ] ) && is_callable( $method[ $key ] ) ) {
					$callback = $method[ $key ];
					break;
				}

				if ( isset( $settings[ $key ] ) && is_callable( $settings[ $key ] ) ) {
					$callback = $settings[ $key ];
					break;
				}
			}
		}

		if ( null === $callback && function_exists( 'apply_filters' ) ) {
			$candidate = apply_filters( 'velotok_core_delivery_provider_rate', null, $method, $order, $context );

			if ( is_callable( $candidate ) ) {
				$callback = $candidate;
			}
		}

		if ( null === $callback ) {
			return array( 'ok' => false, 'configured' => false );
		}

		try {
			$result = call_user_func( $callback, $method, $order, $context );
		} catch ( \Throwable $exception ) {
			return array(
				'ok'         => false,
				'configured' => true,
				'error'      => $exception->getMessage(),
				'code'       => $contract['provider_error']['code'],
				'visible'    => true,
			);
		}

		$error = null;

		$is_wp_error = is_object( $result ) && (
			'WP_Error' === get_class( $result )
			|| is_a( $result, 'WP_Error' )
			|| ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) )
		);

		if ( $is_wp_error && method_exists( $result, 'get_error_message' ) ) {
			$error = $result->get_error_message();
		} elseif ( is_array( $result ) && isset( $result['error'] ) && is_scalar( $result['error'] ) && '' !== (string) $result['error'] ) {
			$error = (string) $result['error'];
		} elseif ( is_array( $result ) && isset( $result['code'] ) && is_scalar( $result['code'] ) && '' !== (string) $result['code'] && ! isset( $result['amount'] ) && ! isset( $result['cost'] ) ) {
			$error = isset( $result['message'] ) && is_scalar( $result['message'] ) ? (string) $result['message'] : (string) $result['code'];
		}

		if ( null !== $error ) {
			return array(
				'ok'         => false,
				'configured' => true,
				'error'      => $error,
				'code'       => $contract['provider_error']['code'],
				'visible'    => true,
			);
		}

		$amount = is_numeric( $result )
			? $result
			: velotok_core_delivery_zones_value( $result, array( 'amount', 'cost', 'total', 'rate', 'price', 'tariff' ), null );

		if ( ! is_numeric( $amount ) ) {
			return array(
				'ok'         => false,
				'configured' => true,
				'error'      => 'Провайдер доставки вернул некорректный тариф.',
				'code'       => $contract['provider_error']['code'],
				'visible'    => true,
			);
		}

		return array(
			'ok'       => true,
			'amount'   => (float) $amount,
			'currency' => velotok_core_delivery_zones_scalar( velotok_core_delivery_zones_value( $result, array( 'currency' ), 'RUB' ), 'RUB' ),
			'source'   => 'provider',
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_free_shipping_threshold' ) ) {
	/**
	 * Resolve the configured free-shipping threshold for a method.
	 *
	 * @param mixed $method Method data.
	 * @return float|null
	 */
	function velotok_core_delivery_zones_free_shipping_threshold( $method ) {
		$settings = velotok_core_delivery_zones_settings( $method );
		$keys     = array( 'free_from', 'free_shipping_threshold', 'min_amount', 'minimum_amount' );
		$value    = velotok_core_delivery_zones_value( $method, $keys, null );

		if ( ! is_numeric( $value ) ) {
			$value = null;

			foreach ( $keys as $key ) {
				if ( isset( $settings[ $key ] ) && is_numeric( $settings[ $key ] ) ) {
					$value = $settings[ $key ];
					break;
				}
			}
		}

		return is_numeric( $value ) ? (float) $value : null;
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_method_cost' ) ) {
	/**
	 * Calculate a method cost from the provider or the configured tariff.
	 *
	 * The configured free-shipping threshold applies to both the provider
	 * tariff and the fallback tariff, so the customer is never charged above
	 * the store's free-delivery threshold.
	 *
	 * @param mixed $method  Method data.
	 * @param mixed $order   Order data.
	 * @param array $context Context.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_zones_method_cost( $method, $order = array(), $context = array() ) {
		$contract = velotok_core_delivery_zones_contract();
		$settings = velotok_core_delivery_zones_settings( $method );
		$weight   = velotok_core_delivery_zones_order_weight( $order );

		if ( empty( $weight['ok'] ) ) {
			return array(
				'ok'     => false,
				'code'   => $weight['code'],
				'error'  => $weight['error'],
				'amount' => null,
			);
		}

		$subtotal = velotok_core_delivery_zones_number(
			velotok_core_delivery_zones_value( $order, array( 'subtotal', 'cart_subtotal' ), 0 )
		);
		$total    = velotok_core_delivery_zones_number(
			velotok_core_delivery_zones_value( $order, array( 'total', 'cart_total' ), $subtotal )
		);
		$base_total = $subtotal > 0 ? $subtotal : $total;
		$free_from  = velotok_core_delivery_zones_free_shipping_threshold( $method );
		$free       = null !== $free_from && $base_total >= $free_from;
		$round_weight = 'ceil' === $contract['weight']['rounding'] ? ceil( $weight['weight'] ) : $weight['weight'];
		$weight_info = array(
			'weight'                  => $weight['weight'],
			'weight_kg'               => $weight['weight'],
			'billable_weight'         => $round_weight,
			'subtotal'                => $base_total,
			'free_from'               => $free_from,
			'free_shipping_threshold' => $free_from,
		);

		if ( 'free_shipping' === velotok_core_delivery_zones_method_id( $method ) ) {
			return array_merge(
				array(
					'ok'         => true,
					'amount'     => 0.0,
					'cost'       => 0.0,
					'currency'   => 'RUB',
					'free'       => true,
					'source'     => 'free_shipping',
					'calculated' => true,
				),
				$weight_info
			);
		}

		$provider_order = $order;

		if ( is_array( $provider_order ) ) {
			$provider_order['weight'] = $weight['weight'];
		}

		$provider = velotok_core_delivery_zones_provider_rate( $method, $provider_order, $context );

		if ( ! empty( $provider['ok'] ) ) {
			$provider_amount = (float) $provider['amount'];
			$amount          = $free ? 0.0 : $provider_amount;

			return array_merge(
				array(
					'ok'              => true,
					'amount'          => $amount,
					'cost'            => $amount,
					'currency'        => $provider['currency'],
					'free'            => $free || 0.0 === $provider_amount,
					'provider_amount' => $provider_amount,
					'source'          => 'provider',
					'calculated'      => true,
				),
				$weight_info
			);
		}

		if ( true === $provider['configured'] && empty( $provider['ok'] ) ) {
			return array_merge(
				array(
					'ok'         => false,
					'code'       => $provider['code'],
					'error'      => $provider['error'],
					'amount'     => null,
					'visible'    => true,
					'calculated' => false,
				),
				$weight_info
			);
		}

		$base = velotok_core_delivery_zones_number(
			velotok_core_delivery_zones_value(
				$method,
				array( 'base', 'base_amount', 'cost' ),
				isset( $settings['base'] ) ? $settings['base'] : ( isset( $settings['cost'] ) ? $settings['cost'] : 0 )
			)
		);
		$per_kg = velotok_core_delivery_zones_number(
			velotok_core_delivery_zones_value(
				$method,
				array( 'per_kg', 'per_kg_cost' ),
				isset( $settings['per_kg'] ) ? $settings['per_kg'] : 0
			)
		);
		$amount = $base + $per_kg * $round_weight;

		if ( $free ) {
			$amount = 0.0;
		}

		return array_merge(
			array(
				'ok'         => true,
				'amount'     => round( $amount, 2 ),
				'cost'       => round( $amount, 2 ),
				'currency'   => 'RUB',
				'free'       => $free,
				'billable_weight' => $round_weight,
				'base'       => $base,
				'per_kg'     => $per_kg,
				'source'     => 'tariff',
				'calculated' => true,
			),
			$weight_info
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_method_option' ) ) {
	/**
	 * Build a delivery method option.
	 *
	 * @param mixed $method  Raw method.
	 * @param int   $zone_id Zone identifier.
	 * @param mixed $order   Order data.
	 * @param array $context Context.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_zones_method_option( $method, $zone_id = 0, $order = array(), $context = array() ) {
		$contract  = velotok_core_delivery_zones_contract();
		$settings  = velotok_core_delivery_zones_settings( $method );
		$method_id = velotok_core_delivery_zones_method_id( $method );
		$eta       = velotok_core_delivery_zones_eta_range( $method );
		$enabled   = velotok_core_delivery_zones_method_enabled( $method );
		$label     = velotok_core_delivery_zones_scalar(
			velotok_core_delivery_zones_value( $method, array( 'title', 'label', 'method_title', 'name' ), '' )
		);

		if ( '' === $label && isset( $contract['methods'][ $method_id ] ) ) {
			$label = $contract['methods'][ $method_id ];
		}

		$instance_id = velotok_core_delivery_zones_value( $method, array( 'instance_id', 'id' ), '' );
		$key         = $method_id;
		$option      = array(
			'id'             => $method_id,
			'method'         => $method_id,
			'method_id'      => $method_id,
			'slug'           => $method_id,
			'code'           => $method_id,
			'label'          => $label,
			'title'          => $label,
			'provider'       => $method_id,
			'provider_id'    => $method_id,
			'instance_id'    => $instance_id,
			'zone_id'        => (int) $zone_id,
			'source'         => 'woocommerce_zone',
			'available'      => (bool) $enabled,
			'enabled'        => (bool) $enabled,
			'eta'            => array( 'min_days' => $eta['min_days'], 'max_days' => $eta['max_days'] ),
			'eta_min_days'   => $eta['min_days'],
			'eta_max_days'   => $eta['max_days'],
			'eta_label'      => $eta['label'],
			'delivery_time'  => $eta['label'],
			'eta_range'      => $eta['range'],
			'currency'       => 'RUB',
			'cost'           => null,
			'amount'         => null,
			'free'           => false,
			'mocked'         => false,
			'test_mode'      => isset( $context['test_mode'] ) ? (bool) $context['test_mode'] : false,
			'weight'         => null,
			'weight_kg'      => null,
			'billable_weight'=> null,
			'settings'       => $settings,
		);

		if ( $enabled ) {
			$cost = velotok_core_delivery_zones_method_cost( $method, $order, $context );

			if ( ! empty( $cost['ok'] ) ) {
				$option['cost']     = $cost['amount'];
				$option['amount']   = $cost['amount'];
				$option['currency'] = $cost['currency'];
				$option['free']     = $cost['free'];
				$option['weight']          = $cost['weight'];
				$option['weight_kg']       = $cost['weight'];
				$option['billable_weight'] = isset( $cost['billable_weight'] ) ? $cost['billable_weight'] : null;
				$option['tariff']          = $cost;
			} else {
				$option['available'] = false;
				$option['error']     = $cost['error'];
				$option['error_code']= $cost['code'];
				$option['visible']   = true;
			}
		}

		$pickup_providers = isset( $contract['pickup_points']['providers'] ) ? $contract['pickup_points']['providers'] : array();

		if ( in_array( $method_id, $pickup_providers, true ) ) {
			$points_context = $context;
			$points_context['provider'] = $method_id;
			$points_result = velotok_core_delivery_pickup_points(
				isset( $context['address'] ) ? $context['address'] : array(),
				null,
				$points_context
			);
			$option['pickup_points']        = isset( $points_result['points'] ) ? $points_result['points'] : array();
			$option['points']               = isset( $points_result['points'] ) ? $points_result['points'] : array();
			$option['pickup_points_result'] = $points_result;
			$option['pickup_points_radius_km'] = isset( $points_result['radius_km'] ) ? $points_result['radius_km'] : null;
			$option['pickup_points_ok']        = ! empty( $points_result['ok'] );
			$option['pickup_points_available'] = ! empty( $points_result['available'] );
			$option['pickup_points_code']      = isset( $points_result['code'] ) ? $points_result['code'] : '';
			$option['pickup_points_message']   = isset( $points_result['message'] ) ? $points_result['message'] : '';
			$option['pickup_points_errors']    = isset( $points_result['errors'] ) ? $points_result['errors'] : array();
			$option['pickup_points_explicit_failure'] = true;
		}

		return array( $key, $option );
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_points_contract' ) ) {
	/**
	 * Return the pickup points contract section.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_pickup_points_contract() {
		$contract = velotok_core_delivery_zones_contract();

		return isset( $contract['pickup_points'] ) && is_array( $contract['pickup_points'] )
			? $contract['pickup_points']
			: array();
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_coordinates_pair' ) ) {
	/**
	 * Validate and normalize a latitude/longitude pair.
	 *
	 * @param mixed $lat Latitude.
	 * @param mixed $lon Longitude.
	 * @return array<string,float>|null
	 */
	function velotok_core_delivery_pickup_coordinates_pair( $lat, $lon ) {
		if ( ! is_numeric( $lat ) || ! is_numeric( $lon ) ) {
			return null;
		}

		$lat = (float) $lat;
		$lon = (float) $lon;

		if ( $lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0 ) {
			return null;
		}

		return array(
			'lat'       => $lat,
			'lon'       => $lon,
			'latitude'  => $lat,
			'longitude' => $lon,
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_point_coordinates' ) ) {
	/**
	 * Resolve coordinates from an address or a pickup point.
	 *
	 * Plain fields (lat/lon, latitude/longitude), nested coordinate arrays,
	 * GeoJSON points and "lat,lon" strings are accepted so both provider
	 * adapters can supply their point list in their native shape.
	 *
	 * @param mixed $source Address or point data.
	 * @return array<string,float>|null
	 */
	function velotok_core_delivery_pickup_point_coordinates( $source ) {
		$lat_keys = array( 'lat', 'latitude', 'lat_deg', 'geo_lat', 'location_lat' );
		$lon_keys = array( 'lon', 'lng', 'longitude', 'lon_deg', 'geo_lon', 'location_lon' );

		if ( is_string( $source ) ) {
			$text = trim( $source );

			if ( '' === $text ) {
				return null;
			}

			$decoded = json_decode( $text, true );

			if ( is_array( $decoded ) ) {
				return velotok_core_delivery_pickup_point_coordinates( $decoded );
			}

			if ( preg_match( '/^\s*(-?\d+(?:[.,]\d+)?)\s*[,; ]\s*(-?\d+(?:[.,]\d+)?)\s*$/u', $text, $matches ) ) {
				return velotok_core_delivery_pickup_coordinates_pair(
					str_replace( ',', '.', $matches[1] ),
					str_replace( ',', '.', $matches[2] )
				);
			}

			return null;
		}

		if ( ! is_array( $source ) && ! is_object( $source ) ) {
			return null;
		}

		$pair = velotok_core_delivery_pickup_coordinates_pair(
			velotok_core_delivery_zones_value( $source, $lat_keys, null ),
			velotok_core_delivery_zones_value( $source, $lon_keys, null )
		);

		if ( null !== $pair ) {
			return $pair;
		}

		$sentinel    = new \stdClass();
		$source_type = strtolower( velotok_core_delivery_zones_scalar( velotok_core_delivery_zones_value( $source, array( 'type' ), '' ) ) );
		$source_geo  = velotok_core_delivery_zones_value( $source, array( 'coordinates' ), $sentinel );

		if ( 'point' === $source_type && $sentinel !== $source_geo && ( is_array( $source_geo ) || is_object( $source_geo ) ) ) {
			$geo_lon = velotok_core_delivery_zones_value( $source_geo, array( 0 ), $sentinel );
			$geo_lat = velotok_core_delivery_zones_value( $source_geo, array( 1 ), $sentinel );

			if ( $sentinel !== $geo_lon && $sentinel !== $geo_lat ) {
				$pair = velotok_core_delivery_pickup_coordinates_pair( $geo_lat, $geo_lon );

				if ( null !== $pair ) {
					return $pair;
				}
			}
		}

		$first    = velotok_core_delivery_zones_value( $source, array( 0 ), $sentinel );
		$second   = velotok_core_delivery_zones_value( $source, array( 1 ), $sentinel );

		if ( $sentinel !== $first && $sentinel !== $second ) {
			$type = strtolower( velotok_core_delivery_zones_scalar( velotok_core_delivery_zones_value( $source, array( 'type' ), '' ) ) );
			$pair = 'point' === $type
				? velotok_core_delivery_pickup_coordinates_pair( $second, $first )
				: velotok_core_delivery_pickup_coordinates_pair( $first, $second );

			if ( null !== $pair ) {
				return $pair;
			}
		}

		foreach ( array( 'coordinates', 'coords', 'location', 'geo', 'position', 'point' ) as $key ) {
			$nested = velotok_core_delivery_zones_value( $source, array( $key ), $sentinel );

			if ( $nested === $sentinel || null === $nested || $nested === $source ) {
				continue;
			}

			$found = velotok_core_delivery_pickup_point_coordinates( $nested );

			if ( null !== $found ) {
				return $found;
			}

			if ( ! is_array( $nested ) && ! is_object( $nested ) ) {
				continue;
			}

			$nested_lat = velotok_core_delivery_zones_value( $nested, $lat_keys, $sentinel );
			$nested_lon = velotok_core_delivery_zones_value( $nested, $lon_keys, $sentinel );

			if ( $sentinel !== $nested_lat && $sentinel !== $nested_lon ) {
				$pair = velotok_core_delivery_pickup_coordinates_pair( $nested_lat, $nested_lon );

				if ( null !== $pair ) {
					return $pair;
				}
			}

			$first  = velotok_core_delivery_zones_value( $nested, array( 0, 'x', 'lon', 'lng', 'longitude' ), $sentinel );
			$second = velotok_core_delivery_zones_value( $nested, array( 1, 'y', 'lat', 'latitude' ), $sentinel );

			if ( $sentinel === $first || $sentinel === $second ) {
				continue;
			}

			$type = strtolower( velotok_core_delivery_zones_scalar( velotok_core_delivery_zones_value( $nested, array( 'type' ), '' ) ) );
			$pair = 'point' === $type
				? velotok_core_delivery_pickup_coordinates_pair( $second, $first )
				: velotok_core_delivery_pickup_coordinates_pair( $first, $second );

			if ( null !== $pair ) {
				return $pair;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_distance_km' ) ) {
	/**
	 * Calculate the great-circle distance between two coordinates in km.
	 *
	 * @param mixed $from Origin coordinates.
	 * @param mixed $to   Destination coordinates.
	 * @return float|null
	 */
	function velotok_core_delivery_pickup_distance_km( $from, $to ) {
		$origin      = velotok_core_delivery_pickup_point_coordinates( $from );
		$destination = velotok_core_delivery_pickup_point_coordinates( $to );

		if ( null === $origin || null === $destination ) {
			return null;
		}

		$lat1 = deg2rad( $origin['lat'] );
		$lat2 = deg2rad( $destination['lat'] );
		$dlat = $lat2 - $lat1;
		$dlon = deg2rad( $destination['lon'] - $origin['lon'] );

		$haversine = sin( $dlat / 2 ) ** 2 + cos( $lat1 ) * cos( $lat2 ) * sin( $dlon / 2 ) ** 2;
		$haversine = min( 1.0, max( 0.0, $haversine ) );

		return 6371.0088 * 2 * asin( sqrt( $haversine ) );
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_radius_km' ) ) {
	/**
	 * Resolve the pickup search radius from the context or method settings.
	 *
	 * @param mixed $context Radius, context array or method data.
	 * @param mixed $method  Optional method data.
	 * @return float
	 */
	function velotok_core_delivery_pickup_radius_km( $context = array(), $method = null ) {
		$contract = velotok_core_delivery_pickup_points_contract();
		$default  = isset( $contract['default_radius_km'] ) ? (float) $contract['default_radius_km'] : 10.0;
		$radius   = null;

		if ( is_numeric( $context ) ) {
			$radius = (float) $context;
		} elseif ( is_array( $context ) || is_object( $context ) ) {
			foreach ( (array) $contract['radius_keys'] as $key ) {
				$value = velotok_core_delivery_zones_value( $context, array( $key ), null );

				if ( is_numeric( $value ) ) {
					$radius = (float) $value;
					break;
				}
			}

			if ( null === $radius ) {
				$settings = velotok_core_delivery_zones_value( $context, array( 'settings', 'options', 'config' ), null );

				if ( is_array( $settings ) || is_object( $settings ) ) {
					foreach ( (array) $contract['radius_keys'] as $key ) {
						$value = velotok_core_delivery_zones_value( $settings, array( $key ), null );

						if ( is_numeric( $value ) ) {
							$radius = (float) $value;
							break;
						}
					}
				}
			}
		}

		if ( null === $radius && is_array( $context ) ) {
			$context_method = velotok_core_delivery_zones_value( $context, array( 'method', 'shipping_method' ), null );

			if ( null !== $context_method ) {
				$method = $context_method;
			}
		}

		if ( null === $radius && null !== $method ) {
			$method_settings = velotok_core_delivery_zones_settings( $method );

			foreach ( (array) $contract['radius_keys'] as $key ) {
				$value = velotok_core_delivery_zones_value( $method, array( $key ), null );

				if ( ! is_numeric( $value ) && isset( $method_settings[ $key ] ) ) {
					$value = $method_settings[ $key ];
				}

				if ( is_numeric( $value ) ) {
					$radius = (float) $value;
					break;
				}
			}
		}

		if ( null === $radius || ! is_finite( $radius ) ) {
			$radius = $default;
		}

		$radius = max( 0.0, $radius );

		if ( function_exists( 'apply_filters' ) ) {
			$radius = (float) apply_filters( 'velotok_core_delivery_pickup_radius', $radius, $context, $method );
		}

		return $radius;
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_provider_id' ) ) {
	/**
	 * Normalize a pickup point provider identifier.
	 *
	 * @param mixed $value Provider value.
	 * @return string
	 */
	function velotok_core_delivery_pickup_provider_id( $value ) {
		$value = strtolower( trim( velotok_core_delivery_zones_scalar( $value ) ) );

		if ( '' === $value ) {
			return '';
		}

		if ( false !== strpos( $value, 'cdek' ) || false !== strpos( $value, 'сдэк' ) ) {
			return 'cdek';
		}

		if ( false !== strpos( $value, 'russian' ) || false !== strpos( $value, 'post' ) || false !== strpos( $value, 'pochta' ) || false !== strpos( $value, 'почт' ) ) {
			return 'russian_post';
		}

		return $value;
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_points_flatten' ) ) {
	/**
	 * Flatten a provider-keyed point list into one list of points.
	 *
	 * @param mixed $points Points.
	 * @return array<int,mixed>
	 */
	function velotok_core_delivery_pickup_points_flatten( $points ) {
		if ( ! is_array( $points ) ) {
			return array();
		}

		if ( isset( $points['points'] ) && is_array( $points['points'] ) && ! isset( $points['id'] ) && ! isset( $points['lat'] ) ) {
			$points = $points['points'];
		}

		$flat = array();

		foreach ( $points as $key => $point ) {
			$provider_key = ! is_numeric( $key ) ? velotok_core_delivery_pickup_provider_id( $key ) : '';

			if ( ! is_numeric( $key ) && is_array( $point ) && in_array( $provider_key, array( 'cdek', 'russian_post' ), true ) ) {
				if ( ! isset( $point['provider'] ) && ! isset( $point['provider_id'] ) && ! isset( $point['carrier'] ) ) {
					$point['provider'] = $provider_key;
				}

				$flat[] = $point;

				continue;
			}

			if ( is_array( $point ) && ! is_numeric( $key ) && isset( $point[0] ) && ( is_array( $point[0] ) || is_object( $point[0] ) ) ) {
				foreach ( $point as $nested ) {
					if ( is_array( $nested ) ) {
						if ( ! isset( $nested['provider'] ) && ! isset( $nested['provider_id'] ) && ! isset( $nested['carrier'] ) ) {
							$nested['provider'] = velotok_core_delivery_pickup_provider_id( $key );
						}

						$flat[] = $nested;
					} elseif ( is_object( $nested ) ) {
						$flat[] = $nested;
					}
				}

				continue;
			}

			$flat[] = $point;
		}

		return $flat;
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_points_source' ) ) {
	/**
	 * Resolve the raw pickup point list.
	 *
	 * The list can be injected directly, read from the documented filters or
	 * fetched from provider-specific callbacks. A provider failure is returned
	 * explicitly instead of being replaced with an empty successful list.
	 *
	 * @param mixed $points  Direct point list.
	 * @param mixed $address Address data.
	 * @param array $context Context.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_pickup_points_source( $points, $address, $context = array() ) {
		$contract = velotok_core_delivery_pickup_points_contract();

		if ( null !== $points ) {
			if ( ! is_array( $points ) ) {
				return array(
					'ok'     => false,
					'code'   => $contract['error_code'],
					'message'=> 'Список пунктов выдачи имеет некорректный формат.',
					'points' => array(),
					'errors' => array(
						array( 'code' => $contract['error_code'], 'message' => 'Список пунктов выдачи имеет некорректный формат.', 'visible' => true ),
					),
					'source' => 'provided',
				);
			}

			return array(
				'ok'     => true,
				'points' => velotok_core_delivery_pickup_points_flatten( $points ),
				'source' => 'provided',
				'errors' => array(),
			);
		}

		foreach ( array( 'pickup_points', 'pickup_points_list', 'pickup_points_data', 'points' ) as $key ) {
			if ( isset( $context[ $key ] ) && is_array( $context[ $key ] ) ) {
				return array(
					'ok'     => true,
					'points' => velotok_core_delivery_pickup_points_flatten( $context[ $key ] ),
					'source' => 'context',
					'errors' => array(),
				);
			}
		}

		foreach ( array( 'pickup_point_providers', 'pickup_providers' ) as $key ) {
			if ( empty( $context[ $key ] ) || ! is_array( $context[ $key ] ) ) {
				continue;
			}

			$collected = array();
			$errors    = array();

			foreach ( $context[ $key ] as $provider => $callback ) {
				$provider_id = velotok_core_delivery_pickup_provider_id( $provider );

				if ( ! is_callable( $callback ) ) {
					$errors[] = array(
						'code'    => $contract['error_code'],
						'message' => 'Провайдер пунктов выдачи недоступен.',
						'provider'=> $provider_id,
						'visible' => true,
					);
					continue;
				}

				try {
					$result = call_user_func( $callback, $address, $context );
				} catch ( \Throwable $exception ) {
					$errors[] = array(
						'code'    => $contract['error_code'],
						'message' => $exception->getMessage(),
						'provider'=> $provider_id,
						'visible' => true,
					);
					continue;
				}

				$error = null;

				if ( null === $result || false === $result ) {
					$error = 'Провайдер пунктов выдачи не вернул данные.';
				} elseif ( is_object( $result ) && ( 'WP_Error' === get_class( $result ) || is_a( $result, 'WP_Error' ) ) ) {
					$error = method_exists( $result, 'get_error_message' ) ? $result->get_error_message() : 'Провайдер вернул ошибку.';
				} elseif ( is_array( $result ) && isset( $result['ok'] ) && false === $result['ok'] ) {
					$error = isset( $result['error'] ) && is_scalar( $result['error'] ) && '' !== (string) $result['error']
						? (string) $result['error']
						: ( isset( $result['message'] ) && is_scalar( $result['message'] ) && '' !== (string) $result['message'] ? (string) $result['message'] : 'Провайдер вернул ошибку.' );
				} elseif ( is_array( $result ) && isset( $result['error'] ) && is_scalar( $result['error'] ) && '' !== (string) $result['error'] ) {
					$error = (string) $result['error'];
				} elseif ( ! is_array( $result ) && ! is_object( $result ) ) {
					$error = 'Провайдер вернул список пунктов выдачи в некорректном формате.';
				}

				if ( null !== $error ) {
					$errors[] = array(
						'code'    => $contract['error_code'],
						'message' => $error,
						'provider'=> $provider_id,
						'visible' => true,
					);
					continue;
				}

				$list = is_array( $result )
					? velotok_core_delivery_zones_value( $result, array( 'points', 'list', 'items' ), $result )
					: array();

				if ( ! is_array( $list ) ) {
					$errors[] = array(
						'code'    => $contract['error_code'],
						'message' => 'Провайдер вернул список пунктов выдачи в некорректном формате.',
						'provider'=> $provider_id,
						'visible' => true,
					);
					continue;
				}

				foreach ( velotok_core_delivery_pickup_points_flatten( $list ) as $point ) {
					$collected[] = $point;
				}
			}

			if ( empty( $collected ) && ! empty( $errors ) ) {
				return array(
					'ok'       => false,
					'code'     => $contract['error_code'],
					'message'  => $errors[0]['message'],
					'points'   => array(),
					'errors'   => $errors,
					'source'   => 'provider',
					'degraded' => false,
				);
			}

			return array(
				'ok'       => true,
				'points'   => $collected,
				'source'   => 'provider',
				'errors'   => $errors,
				'degraded' => ! empty( $errors ),
			);
		}

		if ( function_exists( 'apply_filters' ) ) {
			foreach ( array( 'velotok_core_delivery_pickup_points', 'velotok_core_delivery_pickup_points_data' ) as $hook ) {
				$filtered = apply_filters( $hook, null, $address, $context );

				if ( is_array( $filtered ) ) {
					return array(
						'ok'     => true,
						'points' => velotok_core_delivery_pickup_points_flatten( $filtered ),
						'source' => 'filter',
						'errors' => array(),
					);
				}
			}
		}

		return array(
			'ok'     => false,
			'code'   => $contract['unavailable_code'],
			'message'=> 'Список пунктов выдачи недоступен.',
			'points' => array(),
			'errors' => array(
				array( 'code' => $contract['unavailable_code'], 'message' => 'Список пунктов выдачи недоступен.', 'visible' => true ),
			),
			'source' => 'unavailable',
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_points' ) ) {
	/**
	 * Build the pickup point list inside a radius of the delivery address.
	 *
	 * Points without coordinates cannot be placed by radius and are reported
	 * explicitly. Points are sorted by distance from the address.
	 *
	 * @param mixed $address Address data.
	 * @param mixed $points  Point list or null to resolve from providers.
	 * @param mixed $context Radius, context array or method data.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_pickup_points( $address = array(), $points = null, $context = array() ) {
		$contract = velotok_core_delivery_pickup_points_contract();

		if ( func_num_args() >= 4 ) {
			$extra = func_get_arg( 3 );

			if ( is_numeric( $extra ) ) {
				$context = is_array( $context ) ? $context : array();
				$context['radius_km'] = (float) $extra;
			} elseif ( null !== $extra ) {
				$context = is_array( $context ) ? $context : array();
				$context['method'] = $extra;
			}
		}

		$address_is_list = is_array( $address )
			&& isset( $address[0] )
			&& ( is_array( $address[0] ) || is_object( $address[0] ) )
			&& ( ! isset( $address['country'] ) && ! isset( $address['city'] ) && ! isset( $address['lat'] ) );
		$points_is_address = is_array( $points )
			&& ( isset( $points['country'] ) || isset( $points['city'] ) || isset( $points['postcode'] ) || isset( $points['lat'] ) || isset( $points['coordinates'] ) );

		if ( $address_is_list && $points_is_address ) {
			$swapped = $address;
			$address = $points;
			$points  = $swapped;
		}

		if ( is_numeric( $context ) ) {
			$context = array( 'radius_km' => (float) $context );
		} elseif ( ! is_array( $context ) ) {
			$context = array();
		}

		if ( isset( $context['address'] ) && ( ! is_array( $address ) || empty( $address ) ) ) {
			$address = $context['address'];
		}

		$normalized_address = velotok_core_delivery_zones_address( $address );
		$radius             = velotok_core_delivery_pickup_radius_km( $context );
		$origin             = velotok_core_delivery_pickup_point_coordinates( $address );

		if ( null === $origin && isset( $context['origin'] ) ) {
			$origin = velotok_core_delivery_pickup_point_coordinates( $context['origin'] );
		}

		if ( null === $origin && isset( $context['coordinates'] ) ) {
			$origin = velotok_core_delivery_pickup_point_coordinates( $context['coordinates'] );
		}

		$source = velotok_core_delivery_pickup_points_source( $points, $address, $context );
		$base   = array(
			'ok'                     => false,
			'available'              => false,
			'points'                 => array(),
			'pickup_points'          => array(),
			'list'                   => array(),
			'items'                  => array(),
			'count'                  => 0,
			'total'                  => 0,
			'found'                  => 0,
			'radius_km'              => $radius,
			'radius'                 => $radius,
			'unit'                   => isset( $contract['unit'] ) ? $contract['unit'] : 'km',
			'distance'               => isset( $contract['distance'] ) ? $contract['distance'] : 'haversine',
			'origin'                 => $origin,
			'address'                => $normalized_address,
			'code'                   => '',
			'message'                => '',
			'errors'                 => array(),
			'source'                 => $source['source'],
			'providers'              => isset( $contract['providers'] ) ? $contract['providers'] : array(),
			'only_russia'            => ! empty( $contract['only_russia'] ),
			'country'                => isset( $contract['country'] ) ? $contract['country'] : 'RU',
			'mocked'                 => false,
			'requirement'            => isset( $contract['requirement'] ) ? $contract['requirement'] : '',
			'failure_requirement'    => isset( $contract['failure_requirement'] ) ? $contract['failure_requirement'] : 'TESTEB-33',
			'explicit_failure'       => true,
			'failure_visible'        => true,
			'failure'                => null,
			'degraded'               => false,
			'pickup_points_radius_km'=> $radius,
		);

		if ( empty( $source['ok'] ) ) {
			$base['code']    = $source['code'];
			$base['message'] = $source['message'];
			$base['errors']  = $source['errors'];
			$base['failure'] = array(
				'code'    => $source['code'],
				'message' => $source['message'],
				'source'  => $source['source'],
				'visible' => true,
			);

			return $base;
		}

		if ( null === $origin ) {
			$message = 'Адрес доставки не позволяет определить координаты для поиска пунктов выдачи.';
			$base['code']    = $contract['no_address_code'];
			$base['message'] = $message;
			$base['errors']  = array(
				array( 'code' => $contract['no_address_code'], 'message' => $message, 'visible' => true ),
			);
			$base['failure'] = array(
				'code'    => $contract['no_address_code'],
				'message' => $message,
				'source'  => $source['source'],
				'visible' => true,
			);

			return $base;
		}

		$errors       = $source['errors'];
		$normalized   = array();
		$provider_hint= velotok_core_delivery_pickup_provider_id(
			velotok_core_delivery_zones_value( $context, array( 'provider', 'provider_id' ), '' )
		);

		foreach ( $source['points'] as $raw_point ) {
			if ( ! is_array( $raw_point ) && ! is_object( $raw_point ) ) {
				continue;
			}

			$country = strtoupper(
				velotok_core_delivery_zones_scalar(
					velotok_core_delivery_zones_value( $raw_point, array( 'country', 'country_code' ), '' )
				)
			);
			$provider = velotok_core_delivery_pickup_provider_id(
				velotok_core_delivery_zones_value( $raw_point, array( 'provider', 'provider_id', 'carrier', 'delivery_provider' ), $provider_hint )
			);

			if ( '' === $provider ) {
				$provider = $provider_hint;
			}

			if ( '' !== $provider_hint && $provider !== $provider_hint ) {
				continue;
			}

			if ( ! empty( $contract['only_russia'] ) && '' !== $country && $contract['country'] !== $country ) {
				continue;
			}

			$coordinates = velotok_core_delivery_pickup_point_coordinates( $raw_point );

			if ( null === $coordinates ) {
				$errors[] = array(
					'code'    => $contract['no_coordinates_code'],
					'message' => 'У пункта выдачи не заданы координаты.',
					'provider'=> $provider,
					'point'   => velotok_core_delivery_zones_scalar(
						velotok_core_delivery_zones_value( $raw_point, array( 'id', 'point_id', 'code' ), '' )
					),
					'visible' => true,
				);
				continue;
			}

			$distance = velotok_core_delivery_pickup_distance_km( $origin, $coordinates );

			if ( null === $distance ) {
				continue;
			}

			if ( $distance > $radius + 0.000001 ) {
				continue;
			}

			$id = velotok_core_delivery_zones_scalar(
				velotok_core_delivery_zones_value( $raw_point, array( 'id', 'point_id', 'code', 'uuid', 'code_pvz' ), '' )
			);
			$name = velotok_core_delivery_zones_scalar(
				velotok_core_delivery_zones_value( $raw_point, array( 'name', 'title', 'label', 'address_name' ), '' )
			);
			$point_address = velotok_core_delivery_zones_scalar(
				velotok_core_delivery_zones_value( $raw_point, array( 'address', 'address_full', 'full_address', 'addr' ), '' )
			);
			$city = velotok_core_delivery_zones_scalar(
				velotok_core_delivery_zones_value( $raw_point, array( 'city', 'settlement' ), '' )
			);
			$postcode = velotok_core_delivery_zones_scalar(
				velotok_core_delivery_zones_value( $raw_point, array( 'postcode', 'zip', 'index' ), '' )
			);

			$normalized[] = array(
				'id'          => $id,
				'point_id'    => $id,
				'code'        => $id,
				'provider'    => $provider,
				'provider_id' => $provider,
				'name'        => $name,
				'title'       => $name,
				'label'       => $name,
				'address'     => $point_address,
				'address_full'=> $point_address,
				'city'        => $city,
				'postcode'    => $postcode,
				'country'     => '' !== $country ? $country : ( isset( $contract['country'] ) ? $contract['country'] : 'RU' ),
				'lat'         => $coordinates['lat'],
				'lon'         => $coordinates['lon'],
				'latitude'    => $coordinates['lat'],
				'longitude'   => $coordinates['lon'],
				'coordinates' => array( 'lat' => $coordinates['lat'], 'lon' => $coordinates['lon'] ),
				'distance'    => round( $distance, 3 ),
				'distance_km' => round( $distance, 3 ),
				'distance_m'  => (int) round( $distance * 1000 ),
				'radius_km'   => $radius,
				'within_radius' => true,
			);
		}

		usort(
			$normalized,
			function ( $left, $right ) {
				if ( $left['distance_km'] === $right['distance_km'] ) {
					return strcmp( (string) $left['id'], (string) $right['id'] );
				}

				return $left['distance_km'] <=> $right['distance_km'];
			}
		);

		$available = ! empty( $normalized );
		$code      = '';
		$message   = '';

		if ( ! $available ) {
			$code    = ! empty( $source['errors'] ) ? $contract['error_code'] : $contract['empty_code'];
			$message = ! empty( $source['errors'] )
				? 'Не удалось получить список пунктов выдачи.'
				: 'В радиусе от адреса нет пунктов выдачи.';
		} elseif ( ! empty( $source['errors'] ) ) {
			$code = $contract['error_code'];
			$message = 'Часть пунктов выдачи недоступна.';
		}

		$degraded = ! empty( $errors ) || ! empty( $source['degraded'] );
		$failure  = null;

		if ( $contract['error_code'] === $code ) {
			$failure = array(
				'code'    => $code,
				'message' => $message,
				'visible' => true,
				'source'  => $source['source'],
			);
		}

		return array_merge(
			$base,
			array(
				'ok'        => true,
				'available' => $available,
				'points'    => array_values( $normalized ),
				'pickup_points' => array_values( $normalized ),
				'list'      => array_values( $normalized ),
				'items'     => array_values( $normalized ),
				'count'     => count( $normalized ),
				'total'     => count( $normalized ),
				'found'     => count( $normalized ),
				'code'      => $code,
				'message'   => $message,
				'errors'    => $errors,
				'degraded'  => $degraded,
				'failure'   => $failure,
			)
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_points_for_address' ) ) {
	/**
	 * Alias for the radius-based pickup point list.
	 *
	 * @param mixed $address Address data.
	 * @param mixed $points  Point list.
	 * @param mixed $context Context.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_pickup_points_for_address( $address = array(), $points = null, $context = array() ) {
		return velotok_core_delivery_pickup_points( $address, $points, $context );
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_points_by_radius' ) ) {
	/**
	 * Alias for the radius-based pickup point list.
	 *
	 * @param mixed $address Address data.
	 * @param mixed $points  Point list.
	 * @param mixed $context Context.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_pickup_points_by_radius( $address = array(), $points = null, $context = array() ) {
		return velotok_core_delivery_pickup_points( $address, $points, $context );
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_points_near_address' ) ) {
	/**
	 * Alias for the radius-based pickup point list.
	 *
	 * @param mixed $address Address data.
	 * @param mixed $points  Point list.
	 * @param mixed $context Context.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_pickup_points_near_address( $address = array(), $points = null, $context = array() ) {
		return velotok_core_delivery_pickup_points( $address, $points, $context );
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_pickup_points' ) ) {
	/**
	 * Alias for the radius-based pickup point list.
	 *
	 * @param mixed $address Address data.
	 * @param mixed $points  Point list.
	 * @param mixed $context Context.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_zones_pickup_points( $address = array(), $points = null, $context = array() ) {
		return velotok_core_delivery_pickup_points( $address, $points, $context );
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_pickup_points_contract' ) ) {
	/**
	 * Alias for the pickup points contract section.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_zones_pickup_points_contract() {
		return velotok_core_delivery_pickup_points_contract();
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_points_nearby' ) ) {
	/**
	 * Alias for the radius-based pickup point list.
	 *
	 * @param mixed $address Address data.
	 * @param mixed $points  Point list.
	 * @param mixed $context Context.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_pickup_points_nearby( $address = array(), $points = null, $context = array() ) {
		return velotok_core_delivery_pickup_points( $address, $points, $context );
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_points_by_address' ) ) {
	/**
	 * Alias for the radius-based pickup point list.
	 *
	 * @param mixed $address Address data.
	 * @param mixed $points  Point list.
	 * @param mixed $context Context.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_pickup_points_by_address( $address = array(), $points = null, $context = array() ) {
		return velotok_core_delivery_pickup_points( $address, $points, $context );
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_points_list' ) ) {
	/**
	 * Alias for the radius-based pickup point list.
	 *
	 * @param mixed $address Address data.
	 * @param mixed $points  Point list.
	 * @param mixed $context Context.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_pickup_points_list( $address = array(), $points = null, $context = array() ) {
		return velotok_core_delivery_pickup_points( $address, $points, $context );
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_radius' ) ) {
	/**
	 * Alias for the pickup search radius helper.
	 *
	 * @param mixed $context Radius or context.
	 * @param mixed $method  Optional method data.
	 * @return float
	 */
	function velotok_core_delivery_pickup_radius( $context = array(), $method = null ) {
		return velotok_core_delivery_pickup_radius_km( $context, $method );
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_points_radius' ) ) {
	/**
	 * Alias for the pickup search radius helper.
	 *
	 * @param mixed $context Radius or context.
	 * @param mixed $method  Optional method data.
	 * @return float
	 */
	function velotok_core_delivery_pickup_points_radius( $context = array(), $method = null ) {
		return velotok_core_delivery_pickup_radius_km( $context, $method );
	}
}

if ( ! function_exists( 'velotok_core_delivery_pickup_points_distance' ) ) {
	/**
	 * Alias for the pickup point distance helper.
	 *
	 * @param mixed $from Origin.
	 * @param mixed $to   Destination.
	 * @return float|null
	 */
	function velotok_core_delivery_pickup_points_distance( $from, $to ) {
		return velotok_core_delivery_pickup_distance_km( $from, $to );
	}
}

if ( ! function_exists( 'velotok_core_delivery_distance_km' ) ) {
	/**
	 * Alias for the pickup point distance helper.
	 *
	 * @param mixed $from Origin.
	 * @param mixed $to   Destination.
	 * @return float|null
	 */
	function velotok_core_delivery_distance_km( $from, $to ) {
		return velotok_core_delivery_pickup_distance_km( $from, $to );
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_distance_km' ) ) {
	/**
	 * Alias for the pickup point distance helper.
	 *
	 * @param mixed $from Origin.
	 * @param mixed $to   Destination.
	 * @return float|null
	 */
	function velotok_core_delivery_zones_distance_km( $from, $to ) {
		return velotok_core_delivery_pickup_distance_km( $from, $to );
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_pickup_option' ) ) {
	/**
	 * Build the pickup option that is independent of shipping zones.
	 *
	 * @param array $context Context.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_zones_pickup_option( $context = array() ) {
		$contract    = velotok_core_delivery_zones_contract();
		$pickup      = $contract['pickup'];
		$point_context = $context;
		$address     = isset( $context['address'] ) ? $context['address'] : array();

		if ( isset( $context['pickup_points_result'] ) && is_array( $context['pickup_points_result'] ) ) {
			$points_result = $context['pickup_points_result'];
		} else {
			$points_result = velotok_core_delivery_pickup_points( $address, null, $point_context );
		}

		return array(
			'id'                  => $pickup['method_id'],
			'method'              => $pickup['method_id'],
			'method_id'           => $pickup['method_id'],
			'slug'                => $pickup['method_id'],
			'code'                => $pickup['method_id'],
			'label'               => $pickup['label'],
			'title'               => $pickup['label'],
			'provider'            => 'pickup',
			'provider_id'         => 'pickup',
			'zone_id'             => 0,
			'source'              => 'pickup',
			'available'           => true,
			'enabled'             => true,
			'independent_of_zone' => true,
			'pickup'              => true,
			'eta'                 => array( 'min_days' => 0, 'max_days' => 0 ),
			'eta_min_days'        => 0,
			'eta_max_days'        => 0,
			'eta_label'           => '0 дн.',
			'currency'            => 'RUB',
			'cost'                => 0.0,
			'amount'              => 0.0,
			'free'                => true,
			'mocked'              => false,
			'test_mode'           => isset( $context['test_mode'] ) ? (bool) $context['test_mode'] : false,
			'points_by_radius'    => true,
			'pickup_points'       => isset( $points_result['points'] ) ? $points_result['points'] : array(),
			'points'              => isset( $points_result['points'] ) ? $points_result['points'] : array(),
			'pickup_points_result'=> $points_result,
			'pickup_points_ok'    => ! empty( $points_result['ok'] ),
			'pickup_points_available' => ! empty( $points_result['available'] ),
			'pickup_points_code'  => isset( $points_result['code'] ) ? $points_result['code'] : '',
			'pickup_points_message' => isset( $points_result['message'] ) ? $points_result['message'] : '',
			'pickup_points_errors' => isset( $points_result['errors'] ) ? $points_result['errors'] : array(),
			'pickup_points_explicit_failure' => true,
			'points_visible_error' => empty( $points_result['ok'] ) || ! empty( $points_result['errors'] ),
			'radius_km'           => isset( $points_result['radius_km'] ) ? $points_result['radius_km'] : null,
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_zone_methods' ) ) {
	/**
	 * Return delivery methods offered by the matching WooCommerce zone.
	 *
	 * @param mixed $address Address data.
	 * @param mixed $zones   Zone list or null to read WooCommerce.
	 * @param array $context Context with order, providers and test_mode.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_zone_methods( $address = array(), $zones = null, $context = array() ) {
		$contract = velotok_core_delivery_zones_contract();
		$match    = velotok_core_delivery_zones_match( $address, $zones );
		$order    = isset( $context['order'] ) ? $context['order'] : array();
		$methods  = array();
		$errors   = isset( $match['errors'] ) ? $match['errors'] : array();
		$matched  = ! empty( $match['matched'] );
		$country  = isset( $match['address']['country'] ) ? $match['address']['country'] : '';
		$method_context = $context;
		$method_context['address'] = isset( $match['address'] ) ? $match['address'] : array();
		$pickup_allowed = true === $contract['pickup']['enabled']
			&& ( '' === $country || $contract['pickup']['country'] === $country );

		if ( $matched && isset( $match['zone'] ) ) {
			foreach ( velotok_core_delivery_zones_zone_methods( $match['zone'] ) as $raw_method ) {
				$method_id = velotok_core_delivery_zones_method_id( $raw_method );

				if ( 'local_pickup' === $method_id ) {
					continue;
				}

				list( $key, $option ) = velotok_core_delivery_zones_method_option(
					$raw_method,
					(int) $match['zone_id'],
					$order,
					$method_context
				);

				if ( isset( $methods[ $key ] ) ) {
					$base_key = $key;
					$index    = 2;

					while ( isset( $methods[ $key ] ) ) {
						$key = $base_key . '-' . $index;
						++$index;
					}
				}

				$methods[ $key ] = $option;

				if ( ! empty( $option['error_code'] ) ) {
					$errors[] = array(
						'code'    => $option['error_code'],
						'message' => $option['error'],
						'method'  => $key,
						'visible' => true,
					);
				}
			}
		}

		$pickup_points = velotok_core_delivery_pickup_points(
			isset( $match['address'] ) ? $match['address'] : array(),
			null,
			$method_context
		);

		if ( empty( $pickup_points['ok'] ) || ! empty( $pickup_points['errors'] ) ) {
			$pickup_errors = ! empty( $pickup_points['errors'] )
				? $pickup_points['errors']
				: array(
					array(
						'code'    => isset( $pickup_points['code'] ) ? $pickup_points['code'] : $contract['pickup_points']['unavailable_code'],
						'message' => isset( $pickup_points['message'] ) ? $pickup_points['message'] : 'Список пунктов выдачи недоступен.',
						'visible' => true,
					),
				);

			foreach ( $pickup_errors as $pickup_error ) {
				if ( ! is_array( $pickup_error ) ) {
					continue;
				}

				$pickup_error['visible'] = true;
				$pickup_error['source']  = 'pickup_points';
				$errors[] = $pickup_error;
			}
		}

		if ( $pickup_allowed ) {
			$pickup_context = $method_context;
			$pickup_context['pickup_points_result'] = $pickup_points;
			$methods['local_pickup'] = velotok_core_delivery_zones_pickup_option( $pickup_context );
		}

		$available = false;

		foreach ( $methods as $option ) {
			if ( ! empty( $option['available'] ) ) {
				$available = true;
				break;
			}
		}

		return array(
			'available'       => $available,
			'zone_matched'    => $matched,
			'zone_id'         => isset( $match['zone_id'] ) ? (int) $match['zone_id'] : 0,
			'zone'            => isset( $match['zone'] ) ? $match['zone'] : null,
			'code'            => isset( $match['code'] ) ? $match['code'] : '',
			'message'         => isset( $match['message'] ) ? $match['message'] : '',
			'address'         => isset( $match['address'] ) ? $match['address'] : array(),
			'methods'         => $methods,
			'list'            => array_values( $methods ),
			'errors'          => $errors,
			'pickup'          => $pickup_allowed,
			'pickup_points'   => isset( $pickup_points['points'] ) ? $pickup_points['points'] : array(),
			'pickup_points_result' => $pickup_points,
			'pickup_points_radius_km' => isset( $pickup_points['radius_km'] ) ? $pickup_points['radius_km'] : null,
			'pickup_points_ok' => ! empty( $pickup_points['ok'] ),
			'pickup_points_code' => isset( $pickup_points['code'] ) ? $pickup_points['code'] : '',
			'pickup_points_message' => isset( $pickup_points['message'] ) ? $pickup_points['message'] : '',
			'pickup_points_errors' => isset( $pickup_points['errors'] ) ? $pickup_points['errors'] : array(),
			'pickup_points_explicit_failure' => true,
			'source'          => $contract['source'],
			'requirement'     => $contract['requirement'],
			'mocked'          => false,
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_zone_option' ) ) {
	/**
	 * Return one offered delivery method by identifier.
	 *
	 * @param string $method_id Method identifier.
	 * @param mixed  $address   Address data.
	 * @param mixed  $zones     Zone list or null.
	 * @param array  $context   Context.
	 * @return array<string,mixed>|null
	 */
	function velotok_core_delivery_zone_option( $method_id, $address = array(), $zones = null, $context = array() ) {
		$result = velotok_core_delivery_zone_methods( $address, $zones, $context );
		$key    = strtolower( trim( (string) $method_id ) );

		foreach ( $result['methods'] as $method_key => $option ) {
			if ( $key === strtolower( (string) $method_key ) || $key === strtolower( (string) $option['method_id'] ) ) {
				return $option;
			}

			if ( isset( $option['id'] ) && $key === strtolower( (string) $option['id'] ) ) {
				return $option;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'velotok_core_delivery_provider_configuration_error' ) ) {
	/**
	 * Return an admin-visible provider configuration error.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $message  Error message.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_provider_configuration_error( $provider = '', $message = '' ) {
		$contract = velotok_core_delivery_zones_contract();

		return array(
			'ok'      => false,
			'code'    => $contract['provider_configuration']['error_code'],
			'provider'=> $provider,
			'message' => '' !== $message ? $message : 'Провайдер доставки настроен некорректно.',
			'visible' => true,
			'admin'   => true,
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_test_mode_banner' ) ) {
	/**
	 * Render the explicit test-mode banner for the customer.
	 *
	 * @param bool $test_mode Test mode flag.
	 * @return string
	 */
	function velotok_core_delivery_test_mode_banner( $test_mode = false ) {
		if ( ! $test_mode ) {
			return '';
		}

		$contract = velotok_core_delivery_zones_contract();
		$text     = $contract['test_mode']['banner_text'];
		$escape   = function ( $value ) {
			return function_exists( 'esc_html' ) ? esc_html( $value ) : htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
		};

		return '<div class="velotok-delivery-test-mode" data-test-mode="true" role="status">' . $escape( $text ) . '</div>';
	}
}

if ( ! function_exists( 'velotok_core_delivery_zone_contract' ) ) {
	function velotok_core_delivery_zone_contract() {
		return velotok_core_delivery_zones_contract();
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones' ) ) {
	function velotok_core_delivery_zones( $zones = null ) {
		return velotok_core_delivery_zones_data( $zones );
	}
}

if ( ! function_exists( 'velotok_core_delivery_zone_for_address' ) ) {
	function velotok_core_delivery_zone_for_address( $address = array(), $zones = null ) {
		return velotok_core_delivery_zones_match( $address, $zones );
	}
}

if ( ! function_exists( 'velotok_core_delivery_zone_options' ) ) {
	function velotok_core_delivery_zone_options( $address = array(), $zones = null, $context = array() ) {
		return velotok_core_delivery_zone_methods( $address, $zones, $context );
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_methods' ) ) {
	function velotok_core_delivery_zones_methods( $address = array(), $zones = null, $context = array() ) {
		return velotok_core_delivery_zone_methods( $address, $zones, $context );
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_options' ) ) {
	function velotok_core_delivery_zones_options( $address = array(), $zones = null, $context = array() ) {
		return velotok_core_delivery_zone_methods( $address, $zones, $context );
	}
}

if ( ! function_exists( 'velotok_core_get_delivery_zone_options' ) ) {
	function velotok_core_get_delivery_zone_options( $address = array(), $zones = null, $context = array() ) {
		return velotok_core_delivery_zone_methods( $address, $zones, $context );
	}
}

if ( ! function_exists( 'velotok_core_delivery_methods_from_zones' ) ) {
	function velotok_core_delivery_methods_from_zones( $address = array(), $zones = null, $context = array() ) {
		return velotok_core_delivery_zone_methods( $address, $zones, $context );
	}
}

if ( ! function_exists( 'velotok_core_woocommerce_delivery_options' ) ) {
	function velotok_core_woocommerce_delivery_options( $address = array(), $zones = null, $context = array() ) {
		return velotok_core_delivery_zone_methods( $address, $zones, $context );
	}
}
