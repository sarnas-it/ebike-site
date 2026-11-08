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
			'requirement_ids'         => array( 'TESTEB-28', 'TESTEB-29' ),
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
				'format'    => 'range',
				'min_key'   => 'min_days',
				'max_key'   => 'max_days',
				'label'     => '{min}–{max} дн.',
				'min_label' => '{min} дн.',
				'max_label' => '{max} дн.',
			),
			'weight'                  => array(
				'unit'           => 'kg',
				'source'         => 'order.weight',
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

if ( ! function_exists( 'velotok_core_delivery_zones_eta_range' ) ) {
	/**
	 * Return a delivery ETA range.
	 *
	 * @param mixed $method Method data.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_zones_eta_range( $method ) {
		$contract = velotok_core_delivery_zones_contract();
		$settings = velotok_core_delivery_zones_settings( $method );
		$eta      = velotok_core_delivery_zones_value( $method, array( 'eta', 'eta_range', 'delivery_time' ), array() );

		$min = velotok_core_delivery_zones_value(
			$eta,
			array( 'min_days', 'min', 'from' ),
			velotok_core_delivery_zones_value(
				$method,
				array( 'eta_min_days', 'min_days' ),
				isset( $settings['eta_min_days'] ) ? $settings['eta_min_days'] : null
			)
		);
		$max = velotok_core_delivery_zones_value(
			$eta,
			array( 'max_days', 'max', 'to' ),
			velotok_core_delivery_zones_value(
				$method,
				array( 'eta_max_days', 'max_days' ),
				isset( $settings['eta_max_days'] ) ? $settings['eta_max_days'] : null
			)
		);

		$min = is_numeric( $min ) ? (int) ceil( (float) $min ) : null;
		$max = is_numeric( $max ) ? (int) ceil( (float) $max ) : null;

		if ( null !== $min && null !== $max && $min > $max ) {
			$swap = $min;
			$min  = $max;
			$max  = $swap;
		}

		$label = '';

		if ( null !== $min && null !== $max ) {
			$label = $min === $max
				? str_replace( '{min}', (string) $min, $contract['eta']['min_label'] )
				: str_replace( array( '{min}', '{max}' ), array( (string) $min, (string) $max ), $contract['eta']['label'] );
		} elseif ( null !== $min ) {
			$label = str_replace( '{min}', (string) $min, $contract['eta']['min_label'] );
		} elseif ( null !== $max ) {
			$label = str_replace( '{max}', (string) $max, $contract['eta']['max_label'] );
		}

		return array(
			'min_days' => $min,
			'max_days' => $max,
			'min'      => $min,
			'max'      => $max,
			'range'    => array( 'min' => $min, 'max' => $max ),
			'label'    => $label,
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_zones_order_weight' ) ) {
	/**
	 * Resolve order weight without ambiguity.
	 *
	 * @param mixed $order Order data.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_zones_order_weight( $order = array() ) {
		$contract = velotok_core_delivery_zones_contract();
		$weight   = velotok_core_delivery_zones_value( $order, array( 'weight', 'order_weight' ), null );

		if ( is_numeric( $weight ) ) {
			return array(
				'ok'     => true,
				'weight' => (float) $weight,
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
			$item_weight = velotok_core_delivery_zones_value( $item, array( 'weight', 'product_weight' ), null );

			if ( ! is_numeric( $item_weight ) ) {
				return array(
					'ok'     => false,
					'weight' => null,
					'source' => 'items',
					'unit'   => $contract['weight']['unit'],
					'code'   => $contract['weight']['ambiguous_code'],
					'error'  => 'Вес хотя бы одного товара не задан.',
				);
			}

			$quantity = velotok_core_delivery_zones_value( $item, array( 'quantity', 'qty' ), 1 );
			$quantity = is_numeric( $quantity ) ? max( 0, (float) $quantity ) : 1.0;

			$total += (float) $item_weight * $quantity;
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
		$weight_info = array(
			'weight'                  => $weight['weight'],
			'weight_kg'               => $weight['weight'],
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

		$provider = velotok_core_delivery_zones_provider_rate( $method, $order, $context );

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
		$round_weight = 'ceil' === $contract['weight']['rounding'] ? ceil( $weight['weight'] ) : $weight['weight'];
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
			'settings'       => $settings,
		);

		if ( $enabled ) {
			$cost = velotok_core_delivery_zones_method_cost( $method, $order, $context );

			if ( ! empty( $cost['ok'] ) ) {
				$option['cost']     = $cost['amount'];
				$option['amount']   = $cost['amount'];
				$option['currency'] = $cost['currency'];
				$option['free']     = $cost['free'];
				$option['weight']   = $cost['weight'];
				$option['tariff']   = $cost;
			} else {
				$option['available'] = false;
				$option['error']     = $cost['error'];
				$option['error_code']= $cost['code'];
				$option['visible']   = true;
			}
		}

		return array( $key, $option );
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
		$contract = velotok_core_delivery_zones_contract();
		$pickup   = $contract['pickup'];

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
					$context
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

		if ( $pickup_allowed ) {
			$methods['local_pickup'] = velotok_core_delivery_zones_pickup_option( $context );
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
