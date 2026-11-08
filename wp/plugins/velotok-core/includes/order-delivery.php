<?php
/**
 * Delivery selection stored on an order.
 *
 * Spec: delivery (requirement TESTEB-37).
 *
 * The delivery method chosen at checkout is written to the WooCommerce order
 * together with its provider, title and cost. The value is read back from the
 * order meta (or, for legacy orders, from the order shipping lines) so the
 * selection is fixed in the order and stays observable for the customer and
 * the shop owner.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once __DIR__ . '/delivery-zones.php';
require_once __DIR__ . '/delivery-woocommerce.php';

if ( ! function_exists( 'velotok_core_delivery_order_contract' ) ) {
	/**
	 * Return the order delivery selection contract.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_order_contract() {
		static $contract = null;

		if ( null !== $contract ) {
			return $contract;
		}

		$contract = array(
			'spec'            => 'delivery',
			'parent_spec'     => 'ecommerce-store',
			'requirement'     => 'TESTEB-37',
			'task'            => 'TESTEB-37',
			'requirement_ids' => array( 'TESTEB-37' ),
			'source'          => 'woocommerce_shipping_method',
			'provider'        => 'woocommerce',
			'provider_label'  => 'WooCommerce',
			'mocked'          => false,
			'network'         => false,
			'method_field'    => 'delivery_method',
			'provider_field'  => 'delivery_provider',
			'label_field'     => 'delivery_method_label',
			'cost_field'      => 'delivery_cost',
			'meta'            => array(
				'method'    => '_velotok_delivery_method',
				'provider'  => '_velotok_delivery_provider',
				'label'     => '_velotok_delivery_method_label',
				'cost'      => '_velotok_delivery_cost',
				'selection' => '_velotok_delivery_selection',
				'eta'       => '_velotok_delivery_eta',
				'tracking'  => '_velotok_delivery_tracking_number',
			),
			'fields'          => array( 'method_id', 'delivery_method', 'provider', 'label', 'cost', 'eta', 'selected' ),
			'persisted'       => true,
			'written_on_checkout' => true,
			'readable_from_order' => true,
			'visible_to'      => array( 'customer', 'owner' ),
			'visible_to_owner'=> true,
			'empty_code'      => 'DELIVERY_METHOD_NOT_SELECTED',
			'empty_message'   => 'Способ доставки не выбран.',
		);

		return $contract;
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_id' ) ) {
	/**
	 * Resolve an order identifier from an object or scalar.
	 *
	 * @param int|object $order Order identifier or object.
	 * @return int
	 */
	function velotok_core_delivery_order_id( $order ) {
		if ( is_object( $order ) ) {
			foreach ( array( 'get_id', 'get_order_id' ) as $method ) {
				if ( method_exists( $order, $method ) ) {
					return (int) $order->$method();
				}
			}

			if ( isset( $order->id ) && is_numeric( $order->id ) ) {
				return (int) $order->id;
			}

			return 0;
		}

		return is_numeric( $order ) ? (int) $order : 0;
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_meta' ) ) {
	/**
	 * Read delivery meta from an order object or its post meta.
	 *
	 * @param int|object $order   Order identifier or object.
	 * @param string     $key     Meta key.
	 * @param mixed      $default Default value.
	 * @return mixed
	 */
	function velotok_core_delivery_order_meta( $order, $key, $default = '' ) {
		if ( is_object( $order ) && method_exists( $order, 'get_meta' ) ) {
			$value = $order->get_meta( $key, true );

			if ( null !== $value && '' !== $value ) {
				return $value;
			}
		}

		$order_id = velotok_core_delivery_order_id( $order );

		if ( $order_id > 0 && function_exists( 'get_post_meta' ) ) {
			$value = get_post_meta( $order_id, $key, true );

			if ( null !== $value && '' !== $value ) {
				return $value;
			}
		}

		return $default;
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_shipping' ) ) {
	/**
	 * Return the selected shipping line of an order, if available.
	 *
	 * @param int|object $order Order identifier or object.
	 * @return mixed
	 */
	function velotok_core_delivery_order_shipping( $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_shipping_methods' ) ) {
			return null;
		}

		foreach ( (array) $order->get_shipping_methods() as $shipping ) {
			if ( is_object( $shipping ) || is_array( $shipping ) ) {
				return $shipping;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_selection' ) ) {
	/**
	 * Read the delivery selection fixed in an order.
	 *
	 * @param int|object $order Order identifier or object.
	 * @return array<string,mixed>
	 */
	function velotok_core_delivery_order_selection( $order = 0 ) {
		$contract  = velotok_core_delivery_order_contract();
		$shipping  = velotok_core_delivery_order_shipping( $order );
		$stored    = velotok_core_delivery_order_meta( $order, $contract['meta']['selection'], array() );
		$stored    = is_array( $stored ) ? $stored : array();
		$method_id = velotok_core_delivery_normalize_method_id(
			velotok_core_delivery_order_meta( $order, $contract['meta']['method'], '' )
		);

		if ( '' === $method_id ) {
			$method_id = velotok_core_delivery_normalize_method_id(
				isset( $stored['method_id'] ) ? $stored['method_id'] : velotok_core_delivery_zones_value( $stored, array( 'delivery_method' ), '' )
			);
		}

		if ( '' === $method_id ) {
			$method_id = velotok_core_delivery_normalize_method_id( $shipping );
		}

		$meta = $shipping && method_exists( $shipping, 'get_meta_data' ) ? (array) $shipping->get_meta_data() : array();
		$provider = velotok_core_delivery_normalize_method_id(
			velotok_core_delivery_order_meta( $order, $contract['meta']['provider'], '' )
		);

		if ( '' === $provider ) {
			$provider = isset( $stored['provider'] ) ? velotok_core_delivery_normalize_method_id( $stored['provider'] ) : '';
		}

		if ( '' === $provider ) {
			$provider = velotok_core_delivery_method_provider( $method_id, $meta );
		}

		$label = velotok_core_delivery_order_meta( $order, $contract['meta']['label'], '' );
		$label = is_scalar( $label ) ? trim( (string) $label ) : '';

		if ( '' === $label && isset( $stored['label'] ) && is_scalar( $stored['label'] ) ) {
			$label = trim( (string) $stored['label'] );
		}

		if ( '' === $label && null !== $shipping ) {
			$label = velotok_core_delivery_method_label( $shipping, $meta, $method_id );
		}

		if ( '' === $label && '' !== $method_id ) {
			$label = velotok_core_delivery_method_label( null, array(), $method_id );
		}

		$cost = velotok_core_delivery_order_meta( $order, $contract['meta']['cost'], null );

		if ( ! is_numeric( $cost ) ) {
			$cost = isset( $stored['cost'] ) && is_numeric( $stored['cost'] )
				? (float) $stored['cost']
				: ( null !== $shipping && method_exists( $shipping, 'get_total' ) ? (float) $shipping->get_total() : 0.0 );
		}

		$cost = (float) $cost;
		$eta  = isset( $stored['eta'] ) && is_array( $stored['eta'] )
			? $stored['eta']
			: velotok_core_delivery_order_eta_range( $order );
		$selected = '' !== $method_id;

		return array(
			'method_id'       => $method_id,
			'delivery_method' => $method_id,
			'provider'        => $provider,
			'label'           => $label,
			'cost'            => $cost,
			'eta'             => $eta,
			'selected'        => $selected,
			'code'            => $selected ? 'DELIVERY_METHOD_SELECTED' : $contract['empty_code'],
			'message'         => $selected ? '' : $contract['empty_message'],
			'visible_to'      => $contract['visible_to'],
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_selected_method' ) ) {
	/**
	 * Return the selected delivery method id fixed in an order.
	 *
	 * @param int|object $order   Order identifier or object.
	 * @param string     $default Default value.
	 * @return string
	 */
	function velotok_core_delivery_order_selected_method( $order = 0, $default = '' ) {
		$selection = velotok_core_delivery_order_selection( $order );

		return '' !== $selection['method_id'] ? $selection['method_id'] : $default;
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_method' ) ) {
	/**
	 * Compatibility alias for the selected delivery method id.
	 *
	 * @param int|object $order   Order identifier or object.
	 * @param string     $default Default value.
	 * @return string
	 */
	function velotok_core_delivery_order_method( $order = 0, $default = '' ) {
		return velotok_core_delivery_order_selected_method( $order, $default );
	}
}
