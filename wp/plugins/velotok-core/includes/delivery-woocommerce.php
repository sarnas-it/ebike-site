<?php
/**
 * WooCommerce shipping methods backed by shipping zones.
 *
 * Registers the СДЭК and Почта РФ shipping methods with WooCommerce so the
 * storefront offers them wherever the administrator added them to a shipping
 * zone. Costs are calculated by the configured tariff/provider, delivery time
 * is shown as a range, and provider errors stay visible instead of being
 * replaced with a fake tariff.
 *
 * Spec: delivery (requirement TESTEB-28).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once __DIR__ . '/delivery-zones.php';

if ( ! function_exists( 'velotok_core_delivery_woocommerce_absint' ) ) {
	/**
	 * Convert a value to a non-negative integer.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	function velotok_core_delivery_woocommerce_absint( $value ) {
		return function_exists( 'absint' ) ? absint( $value ) : abs( (int) $value );
	}
}

if ( ! function_exists( 'velotok_core_delivery_woocommerce_translate' ) ) {
	/**
	 * Translate a string when WordPress is available.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function velotok_core_delivery_woocommerce_translate( $text ) {
		return function_exists( '__' ) ? __( $text, 'velotok-core' ) : $text;
	}
}

if ( ! function_exists( 'velotok_core_delivery_woocommerce_escape' ) ) {
	/**
	 * Escape text for HTML output.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function velotok_core_delivery_woocommerce_escape( $text ) {
		return function_exists( 'esc_html' ) ? esc_html( $text ) : htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'velotok_core_delivery_register_shipping_methods' ) ) {
	/**
	 * Register the delivery methods with WooCommerce.
	 *
	 * WooCommerce calls this filter to discover shipping method classes that
	 * the administrator can add to a shipping zone. The methods are only
	 * offered at checkout when a zone containing them matches the address.
	 *
	 * @param array<string,string> $methods Method id => class name.
	 * @return array<string,string>
	 */
	function velotok_core_delivery_register_shipping_methods( $methods ) {
		$methods = is_array( $methods ) ? $methods : array();

		if ( class_exists( 'Velotok_Shipping_Method_CDEK' ) ) {
			$methods['cdek'] = 'Velotok_Shipping_Method_CDEK';
		}

		if ( class_exists( 'Velotok_Shipping_Method_Russian_Post' ) ) {
			$methods['russian_post'] = 'Velotok_Shipping_Method_Russian_Post';
		}

		return $methods;
	}
}
if ( function_exists( 'add_filter' ) ) {
	add_filter( 'woocommerce_shipping_methods', 'velotok_core_delivery_register_shipping_methods' );
}

if ( ! function_exists( 'velotok_core_delivery_rate_method_id' ) ) {
	/**
	 * Resolve the shipping method identifier from a WooCommerce rate.
	 *
	 * @param mixed $rate Rate object or array.
	 * @return string
	 */
	function velotok_core_delivery_rate_method_id( $rate ) {
		if ( is_object( $rate ) ) {
			if ( method_exists( $rate, 'get_method_id' ) ) {
				return (string) $rate->get_method_id();
			}

			return (string) velotok_core_delivery_zones_value( $rate, array( 'method_id', 'id' ), '' );
		}

		if ( is_array( $rate ) ) {
			return (string) velotok_core_delivery_zones_value( $rate, array( 'method_id', 'id' ), '' );
		}

		return '';
	}
}

if ( ! function_exists( 'velotok_core_delivery_rate_is_pickup' ) ) {
	/**
	 * Whether a WooCommerce rate represents pickup.
	 *
	 * @param mixed  $rate      Rate object or array.
	 * @param string $pickup_id Configured pickup method id.
	 * @return bool
	 */
	function velotok_core_delivery_rate_is_pickup( $rate, $pickup_id = 'local_pickup' ) {
		$method_id = strtolower( trim( velotok_core_delivery_rate_method_id( $rate ) ) );
		$candidates = array_filter( array_unique( array( strtolower( (string) $pickup_id ), 'local_pickup', 'pickup' ) ) );

		foreach ( $candidates as $candidate ) {
			if ( $method_id === $candidate || 0 === strpos( $method_id, $candidate . ':' ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'velotok_core_delivery_package_country' ) ) {
	/**
	 * Resolve the destination country from a WooCommerce package.
	 *
	 * @param array $package Shipping package.
	 * @return string
	 */
	function velotok_core_delivery_package_country( $package = array() ) {
		$package     = is_array( $package ) ? $package : array();
		$destination = velotok_core_delivery_zones_value( $package, array( 'destination' ), $package );

		return strtoupper(
			trim(
				velotok_core_delivery_zones_scalar(
					velotok_core_delivery_zones_value( $destination, array( 'country', 'country_code' ), '' )
				)
			)
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_add_pickup_rate' ) ) {
	/**
	 * Add the pickup rate to a package independently of shipping zones.
	 *
	 * Pickup is offered for Russia even when no WooCommerce zone matched the
	 * address (or the address is ambiguous). Outside Russia it is not shown, and
	 * any pickup rate already added by a zone is removed from the package.
	 * Inside Russia an existing pickup rate is left untouched, so the
	 * zone-configured method and the zone-independent fallback never duplicate
	 * each other.
	 *
	 * @param mixed $rates   Shipping rates keyed by rate id.
	 * @param array $package Shipping package.
	 * @return mixed
	 */
	function velotok_core_delivery_add_pickup_rate( $rates, $package = array() ) {
		$contract    = velotok_core_delivery_zones_contract();
		$package     = is_array( $package ) ? $package : array();
		$destination = velotok_core_delivery_zones_value( $package, array( 'destination' ), $package );
		$destination = ( is_array( $destination ) || is_object( $destination ) ) ? $destination : array();
		$country     = velotok_core_delivery_package_country( $package );
		$pickup_id   = (string) $contract['pickup']['method_id'];
		$rates       = is_array( $rates ) ? $rates : array();

		if ( '' !== $country && $contract['pickup']['country'] !== $country ) {
			foreach ( $rates as $rate_key => $rate ) {
				if ( velotok_core_delivery_rate_is_pickup( $rate, $pickup_id ) ) {
					unset( $rates[ $rate_key ] );
				}
			}

			return $rates;
		}

		if ( empty( $contract['pickup']['enabled'] ) ) {
			return $rates;
		}

		foreach ( $rates as $rate ) {
			if ( velotok_core_delivery_rate_is_pickup( $rate, $pickup_id ) ) {
				return $rates;
			}
		}

		$order   = velotok_core_delivery_package_order( $package );
		$context = array(
			'order'     => $order,
			'address'   => $destination,
			'test_mode' => velotok_core_delivery_test_mode_enabled(),
		);
		$option  = velotok_core_delivery_zones_pickup_option( $context );

		if ( empty( $option['available'] ) ) {
			return $rates;
		}

		$meta = array(
			'_velotok_delivery_provider'       => 'pickup',
			'_velotok_delivery_method'         => $pickup_id,
			'_velotok_delivery_independent_of_zone' => 'yes',
			'_velotok_delivery_zone_required'  => 'no',
			'_velotok_eta_min_days'            => $option['eta_min_days'],
			'_velotok_eta_max_days'            => $option['eta_max_days'],
			'_velotok_eta_label'               => $option['eta_label'],
			'_velotok_eta_range'               => $option['eta'],
			'_velotok_pickup_points'           => isset( $option['pickup_points'] ) ? $option['pickup_points'] : array(),
			'_velotok_pickup_points_code'      => isset( $option['pickup_points_code'] ) ? $option['pickup_points_code'] : '',
			'_velotok_pickup_points_message'   => isset( $option['pickup_points_message'] ) ? $option['pickup_points_message'] : '',
			'_velotok_pickup_points_ok'        => ! empty( $option['pickup_points_ok'] ),
			'_velotok_pickup_points_available' => ! empty( $option['pickup_points_available'] ),
			'_velotok_pickup_points_errors'    => isset( $option['pickup_points_errors'] ) ? $option['pickup_points_errors'] : array(),
			'_velotok_pickup_points_explicit_failure' => true,
			'_velotok_test_mode'               => $context['test_mode'] ? 'yes' : 'no',
		);

		$rate = class_exists( 'WC_Shipping_Rate' )
			? new WC_Shipping_Rate( $pickup_id, $option['label'], (float) $option['cost'], $meta, $pickup_id )
			: array(
				'id'        => $pickup_id,
				'label'     => $option['label'],
				'cost'      => (float) $option['cost'],
				'method_id' => $pickup_id,
				'meta_data' => $meta,
			);

		$rates[ $pickup_id ] = $rate;

		return $rates;
	}
}
if ( function_exists( 'add_filter' ) ) {
	add_filter( 'woocommerce_package_rates', 'velotok_core_delivery_add_pickup_rate', 10, 2 );
}

if ( ! function_exists( 'velotok_core_delivery_provider_rate_adapter' ) ) {
	/**
	 * Resolve the provider adapter behind the shipping method.
	 *
	 * The concrete СДЭК / Почта РФ HTTP adapters are supplied through the
	 * documented filter, so the storefront never invents a tariff when a
	 * provider is not configured.
	 *
	 * @param mixed $callback Current callback.
	 * @param mixed $method   Shipping method.
	 * @param mixed $order    Order data.
	 * @param array $context  Context.
	 * @return mixed
	 */
	function velotok_core_delivery_provider_rate_adapter( $callback, $method, $order, $context = array() ) {
		if ( is_callable( $callback ) ) {
			return $callback;
		}

		$provider = velotok_core_delivery_zones_method_id( $method );
		$adapters = array();

		if ( function_exists( 'apply_filters' ) ) {
			$adapters = apply_filters( 'velotok_core_delivery_provider_adapters', $adapters, $provider, $method, $order, $context );
		}

		if ( is_array( $adapters ) && isset( $adapters[ $provider ] ) && is_callable( $adapters[ $provider ] ) ) {
			return $adapters[ $provider ];
		}

		return $callback;
	}
}
if ( function_exists( 'add_filter' ) ) {
	add_filter( 'velotok_core_delivery_provider_rate', 'velotok_core_delivery_provider_rate_adapter', 10, 4 );
}

if ( ! function_exists( 'velotok_core_delivery_package_order' ) ) {
	/**
	 * Build the order data used for tariff calculation from a WC package.
	 *
	 * @param array $package WooCommerce shipping package.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_package_order( $package = array() ) {
		$package = is_array( $package ) ? $package : array();
		$items   = array();
		$weight  = 0.0;
		$complete = true;

		foreach ( (array) velotok_core_delivery_zones_value( $package, array( 'contents' ), array() ) as $line ) {
			$product  = velotok_core_delivery_zones_value( $line, array( 'data', 'product' ), null );
			$quantity = max(
				0,
				velotok_core_delivery_zones_number( velotok_core_delivery_zones_value( $line, array( 'quantity', 'qty' ), 1 ), 1 )
			);
			$item     = array(
				'quantity' => $quantity,
				'product'  => $product,
				'weight'   => velotok_core_delivery_zones_value( $line, array( 'weight', 'product_weight' ), null ),
			);
			$item_weight = velotok_core_delivery_zones_item_weight( $item );

			if ( null !== $item_weight ) {
				$item['weight'] = $item_weight;
				$weight        += $item_weight * $quantity;
			} else {
				unset( $item['weight'] );
				$complete = false;
			}

			$items[] = $item;
		}

		$subtotal = velotok_core_delivery_zones_value( $package, array( 'contents_cost', 'subtotal' ), null );

		if ( ! is_numeric( $subtotal ) ) {
			$subtotal = 0.0;

			foreach ( (array) velotok_core_delivery_zones_value( $package, array( 'contents' ), array() ) as $line ) {
				$subtotal += velotok_core_delivery_zones_number( velotok_core_delivery_zones_value( $line, array( 'line_total', 'total' ), 0 ) );
			}
		}

		$order = array(
			'subtotal' => (float) $subtotal,
			'items'    => $items,
		);

		if ( $complete ) {
			$order['weight'] = $weight;
		}

		return $order;
	}
}

if ( ! function_exists( 'velotok_core_delivery_load_shipping_methods' ) ) {
	/**
	 * Load the shipping method classes once WooCommerce is ready.
	 *
	 * The plugin may be loaded before WooCommerce, so the classes are loaded
	 * again on plugins_loaded when the WC_Shipping_Method base class exists.
	 *
	 * @return void
	 */
	function velotok_core_delivery_load_shipping_methods() {
		if ( class_exists( 'Velotok_Shipping_Method' ) || ! class_exists( 'WC_Shipping_Method' ) ) {
			return;
		}

		require_once __DIR__ . '/delivery-shipping-methods.php';
	}
}
velotok_core_delivery_load_shipping_methods();
if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', 'velotok_core_delivery_load_shipping_methods', 20 );
}

if ( ! function_exists( 'velotok_core_delivery_rate_eta_html' ) ) {
	/**
	 * Render the delivery ETA range next to a shipping rate.
	 *
	 * @param mixed $method Shipping rate object.
	 * @param int   $index  Rate index.
	 * @return void
	 */
	function velotok_core_delivery_rate_eta_html( $method, $index = 0 ) {
		unset( $index );

		$meta = is_object( $method ) && method_exists( $method, 'get_meta_data' ) ? $method->get_meta_data() : array();

		if ( ! is_array( $meta ) || empty( $meta['_velotok_eta_label'] ) ) {
			return;
		}

		echo '<span class="velotok-delivery-eta">' . velotok_core_delivery_woocommerce_escape( $meta['_velotok_eta_label'] ) . '</span>';
	}
}
if ( function_exists( 'add_action' ) ) {
	add_action( 'woocommerce_after_shipping_rate', 'velotok_core_delivery_rate_eta_html', 10, 2 );
}

if ( ! function_exists( 'velotok_core_delivery_test_mode_enabled' ) ) {
	/**
	 * Whether the delivery test mode is enabled.
	 *
	 * @return bool
	 */
	function velotok_core_delivery_test_mode_enabled() {
		$enabled = function_exists( 'get_option' ) ? get_option( 'velotok_core_delivery_test_mode', 'no' ) : 'no';
		$enabled = in_array( strtolower( (string) $enabled ), array( 'yes', '1', 'true', 'on' ), true );

		if ( function_exists( 'apply_filters' ) ) {
			$enabled = (bool) apply_filters( 'velotok_core_delivery_test_mode', $enabled );
		}

		return $enabled;
	}
}

if ( ! function_exists( 'velotok_core_delivery_checkout_test_mode_banner' ) ) {
	/**
	 * Render the explicit test-mode banner at checkout.
	 *
	 * @return void
	 */
	function velotok_core_delivery_checkout_test_mode_banner() {
		if ( ! velotok_core_delivery_test_mode_enabled() ) {
			return;
		}

		echo velotok_core_delivery_test_mode_banner( true );
	}
}
if ( function_exists( 'add_action' ) ) {
	add_action( 'woocommerce_review_order_after_shipping', 'velotok_core_delivery_checkout_test_mode_banner' );
}

if ( ! function_exists( 'velotok_core_delivery_order_eta_range' ) ) {
	/**
	 * Read the persisted ETA range from an order.
	 *
	 * The ETA is stored as a range (min/max plus a human-readable label). The
	 * helper also understands the older separate meta keys, so existing orders
	 * keep their delivery time.
	 *
	 * @param int|object $order Order identifier or order object.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_order_eta_range( $order = 0 ) {
		$empty = array(
			'min_days' => null,
			'max_days' => null,
			'min'      => null,
			'max'      => null,
			'range'    => array( 'min' => null, 'max' => null ),
			'label'    => '',
			'text'     => '',
		);

		if ( ! function_exists( 'get_post_meta' ) ) {
			return $empty;
		}

		if ( is_object( $order ) ) {
			$order_id = method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0;
		} else {
			$order_id = (int) $order;
		}

		if ( $order_id <= 0 ) {
			return $empty;
		}

		$stored = get_post_meta( $order_id, '_velotok_delivery_eta', true );
		$min    = null;
		$max    = null;
		$label  = '';

		if ( is_array( $stored ) ) {
			$min_value = velotok_core_delivery_zones_value( $stored, array( 'min_days', 'min' ), null );
			$max_value = velotok_core_delivery_zones_value( $stored, array( 'max_days', 'max' ), null );
			$min       = is_numeric( $min_value ) ? (int) ceil( (float) $min_value ) : null;
			$max       = is_numeric( $max_value ) ? (int) ceil( (float) $max_value ) : null;
			$label      = (string) velotok_core_delivery_zones_value( $stored, array( 'label', 'text' ), '' );
		} elseif ( is_string( $stored ) && '' !== trim( $stored ) ) {
			$label = trim( $stored );
		}

		if ( null === $min ) {
			$value = get_post_meta( $order_id, '_velotok_delivery_eta_min_days', true );
			$min   = is_numeric( $value ) ? (int) ceil( (float) $value ) : null;
		}

		if ( null === $max ) {
			$value = get_post_meta( $order_id, '_velotok_delivery_eta_max_days', true );
			$max   = is_numeric( $value ) ? (int) ceil( (float) $value ) : null;
		}

		if ( '' === $label ) {
			$value = get_post_meta( $order_id, '_velotok_delivery_eta_label', true );
			$label = is_scalar( $value ) ? trim( (string) $value ) : '';
		}

		if ( '' !== $label && ( null === $min || null === $max ) ) {
			$parsed = velotok_core_delivery_zones_eta_parse( $label );

			if ( null === $min && null !== $parsed['min'] ) {
				$min = $parsed['min'];
			}

			if ( null === $max && null !== $parsed['max'] ) {
				$max = $parsed['max'];
			}
		}

		if ( null !== $min && null !== $max && $min > $max ) {
			$swap = $min;
			$min  = $max;
			$max  = $swap;
		}

		if ( null !== $min || null !== $max ) {
			$formatted = velotok_core_delivery_zones_eta_label( $min, $max );

			if ( '' !== $formatted ) {
				$label = $formatted;
			}
		}

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

if ( ! function_exists( 'velotok_core_delivery_order_eta_html' ) ) {
	/**
	 * Render the persisted delivery ETA range for the order owner.
	 *
	 * @param int|object $order Order identifier or order object.
	 * @return string
	 */
	function velotok_core_delivery_order_eta_html( $order = 0 ) {
		$eta = velotok_core_delivery_order_eta_range( $order );

		if ( '' === $eta['label'] ) {
			return '';
		}

		$min = null === $eta['min_days'] ? '' : (string) $eta['min_days'];
		$max = null === $eta['max_days'] ? '' : (string) $eta['max_days'];

		return '<p class="velotok-delivery-eta velotok-delivery-order-eta" data-eta-min="' . velotok_core_delivery_woocommerce_escape( $min ) . '" data-eta-max="' . velotok_core_delivery_woocommerce_escape( $max ) . '">'
			. velotok_core_delivery_woocommerce_escape( $eta['label'] )
			. '</p>';
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_eta' ) ) {
	/**
	 * Echo the order ETA range on the order details screen.
	 *
	 * @param int|object $order Order identifier or order object.
	 * @return void
	 */
	function velotok_core_delivery_order_eta( $order = 0 ) {
		echo velotok_core_delivery_order_eta_html( $order );
	}
}
if ( function_exists( 'add_action' ) ) {
	add_action( 'woocommerce_order_details_after_order_table', 'velotok_core_delivery_order_eta', 10, 1 );
}

if ( ! function_exists( 'velotok_core_delivery_persist_order_meta' ) ) {
	/**
	 * Persist the selected delivery method, cost and ETA range on the order.
	 *
	 * The ETA is stored as a range: separate min/max meta keys plus a combined
	 * range array and its label. This keeps the order display and any later
	 * re-read of the delivery time unambiguous.
	 *
	 * @param int $order_id Order identifier.
	 * @return void
	 */
	function velotok_core_delivery_persist_order_meta( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) || ! function_exists( 'update_post_meta' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! is_object( $order ) || ! method_exists( $order, 'get_shipping_methods' ) ) {
			return;
		}

		foreach ( (array) $order->get_shipping_methods() as $shipping ) {
			$method_id = method_exists( $shipping, 'get_method_id' ) ? (string) $shipping->get_method_id() : '';
			$meta      = method_exists( $shipping, 'get_meta_data' ) ? (array) $shipping->get_meta_data() : array();
			$range     = null;

			foreach ( array( '_velotok_eta_range', '_velotok_delivery_eta' ) as $range_key ) {
				if ( isset( $meta[ $range_key ] ) && is_array( $meta[ $range_key ] ) ) {
					$range = $meta[ $range_key ];
					break;
				}
			}

			$min = isset( $meta['_velotok_eta_min_days'] )
				? $meta['_velotok_eta_min_days']
				: velotok_core_delivery_zones_value( $range, array( 'min_days', 'min' ), null );
			$max = isset( $meta['_velotok_eta_max_days'] )
				? $meta['_velotok_eta_max_days']
				: velotok_core_delivery_zones_value( $range, array( 'max_days', 'max' ), null );
			$label = isset( $meta['_velotok_eta_label'] )
				? $meta['_velotok_eta_label']
				: velotok_core_delivery_zones_value( $range, array( 'label', 'text' ), '' );

			$eta = velotok_core_delivery_zones_eta_range(
				array(
					'eta'           => array(
						'min_days' => $min,
						'max_days' => $max,
					),
					'delivery_time' => $label,
				)
			);
			$has_eta   = null !== $eta['min_days'] || null !== $eta['max_days'] || '' !== $eta['label'];
			$recognized = in_array( $method_id, array( 'cdek', 'russian_post' ), true );

			if ( ! $recognized && ! $has_eta ) {
				continue;
			}

			update_post_meta( $order_id, '_velotok_delivery_method', $method_id );
			update_post_meta( $order_id, '_velotok_delivery_cost', method_exists( $shipping, 'get_total' ) ? (float) $shipping->get_total() : 0.0 );

			if ( null !== $eta['min_days'] ) {
				update_post_meta( $order_id, '_velotok_delivery_eta_min_days', (int) $eta['min_days'] );
			}

			if ( null !== $eta['max_days'] ) {
				update_post_meta( $order_id, '_velotok_delivery_eta_max_days', (int) $eta['max_days'] );
			}

			if ( '' !== $eta['label'] ) {
				update_post_meta( $order_id, '_velotok_delivery_eta_label', $eta['label'] );
			}

			if ( $has_eta ) {
				$range = array(
					'min_days' => $eta['min_days'],
					'max_days' => $eta['max_days'],
					'min'      => $eta['min_days'],
					'max'      => $eta['max_days'],
					'range'    => $eta['range'],
					'label'    => $eta['label'],
				);

				update_post_meta( $order_id, '_velotok_delivery_eta', $range );
				update_post_meta( $order_id, '_velotok_delivery_eta_range', $range );
			}

			break;
		}
	}
}
if ( function_exists( 'add_action' ) ) {
	add_action( 'woocommerce_checkout_update_order_meta', 'velotok_core_delivery_persist_order_meta' );
}
