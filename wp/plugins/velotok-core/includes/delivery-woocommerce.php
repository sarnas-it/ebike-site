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

if ( ! function_exists( 'velotok_core_delivery_persist_order_meta' ) ) {
	/**
	 * Persist the selected delivery method, cost and ETA range on the order.
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

			if ( ! in_array( $method_id, array( 'cdek', 'russian_post' ), true ) ) {
				continue;
			}

			$meta = method_exists( $shipping, 'get_meta_data' ) ? (array) $shipping->get_meta_data() : array();

			update_post_meta( $order_id, '_velotok_delivery_method', $method_id );
			update_post_meta( $order_id, '_velotok_delivery_cost', method_exists( $shipping, 'get_total' ) ? (float) $shipping->get_total() : 0.0 );

			if ( isset( $meta['_velotok_eta_min_days'] ) ) {
				update_post_meta( $order_id, '_velotok_delivery_eta_min_days', (int) $meta['_velotok_eta_min_days'] );
			}

			if ( isset( $meta['_velotok_eta_max_days'] ) ) {
				update_post_meta( $order_id, '_velotok_delivery_eta_max_days', (int) $meta['_velotok_eta_max_days'] );
			}

			break;
		}
	}
}
if ( function_exists( 'add_action' ) ) {
	add_action( 'woocommerce_checkout_update_order_meta', 'velotok_core_delivery_persist_order_meta' );
}
