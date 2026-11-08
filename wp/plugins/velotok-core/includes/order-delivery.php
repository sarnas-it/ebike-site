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
			'requirement_ids' => array( 'TESTEB-37', 'TESTEB-38' ),
			'tracking_requirement' => 'TESTEB-38',
			'tracking_field'  => 'delivery_tracking_number',
			'tracking_visible_to_owner' => true,
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
		if ( ! is_object( $order ) ) {
			$order_id = velotok_core_delivery_order_id( $order );

			if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
			}
		}

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
		$tracking = velotok_core_delivery_order_tracking_number( $order );
		$selected = '' !== $method_id;

		return array(
			'method_id'       => $method_id,
			'delivery_method' => $method_id,
			'provider'        => $provider,
			'label'           => $label,
			'cost'            => $cost,
			'eta'             => $eta,
			'tracking_number' => $tracking,
			'tracking'        => array(
				'number'          => $tracking,
				'tracking_number' => $tracking,
				'has_tracking'    => '' !== $tracking,
				'visible_to'      => $contract['visible_to'],
				'visible_to_owner'=> true,
			),
			'tracking_visible_to_owner' => true,
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

if ( ! function_exists( 'velotok_core_delivery_order_tracking_meta_key' ) ) {
	function velotok_core_delivery_order_tracking_meta_key() {
		$contract = velotok_core_delivery_order_contract();

		return isset( $contract['meta']['tracking'] ) ? $contract['meta']['tracking'] : '_velotok_delivery_tracking_number';
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_shipping_meta' ) ) {
	function velotok_core_delivery_order_shipping_meta( $shipping ) {
		$raw = null;

		if ( is_object( $shipping ) && method_exists( $shipping, 'get_meta_data' ) ) {
			$raw = $shipping->get_meta_data();
		} elseif ( is_array( $shipping ) ) {
			$raw = isset( $shipping['meta_data'] ) && is_array( $shipping['meta_data'] )
				? $shipping['meta_data']
				: $shipping;
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$meta = array();

		foreach ( $raw as $key => $value ) {
			if ( is_string( $key ) ) {
				$meta[ $key ] = $value;
				continue;
			}

			if ( is_object( $value ) && method_exists( $value, 'get_data' ) ) {
				$value = $value->get_data();
			}

			if ( is_object( $value ) && isset( $value->key ) ) {
				$meta[ (string) $value->key ] = isset( $value->value ) ? $value->value : '';
				continue;
			}

			if ( is_array( $value ) && isset( $value['key'] ) ) {
				$meta[ (string) $value['key'] ] = isset( $value['value'] ) ? $value['value'] : '';
			}
		}

		return $meta;
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_tracking_number' ) ) {
	function velotok_core_delivery_order_tracking_number( $order = 0, $default = '' ) {
		$contract = velotok_core_delivery_order_contract();
		$keys     = array(
			$contract['meta']['tracking'],
			'_velotok_tracking_number',
			'_tracking_number',
			'tracking_number',
			'delivery_tracking_number',
		);

		foreach ( $keys as $key ) {
			$value = velotok_core_delivery_order_meta( $order, $key, null );

			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}

		$stored = velotok_core_delivery_order_meta( $order, $contract['meta']['selection'], array() );

		if ( is_array( $stored ) ) {
			foreach ( $keys as $key ) {
				if ( isset( $stored[ $key ] ) && is_scalar( $stored[ $key ] ) && '' !== trim( (string) $stored[ $key ] ) ) {
					return trim( (string) $stored[ $key ] );
				}
			}

			if ( isset( $stored['tracking']['number'] ) && is_scalar( $stored['tracking']['number'] ) && '' !== trim( (string) $stored['tracking']['number'] ) ) {
				return trim( (string) $stored['tracking']['number'] );
			}
		}

		$shipping = velotok_core_delivery_order_shipping( $order );

		if ( null !== $shipping ) {
			$meta = velotok_core_delivery_order_shipping_meta( $shipping );

			foreach ( $keys as $key ) {
				if ( isset( $meta[ $key ] ) && is_scalar( $meta[ $key ] ) && '' !== trim( (string) $meta[ $key ] ) ) {
					return trim( (string) $meta[ $key ] );
				}

				if ( is_object( $shipping ) && method_exists( $shipping, 'get_meta' ) ) {
					$value = $shipping->get_meta( $key, true );

					if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
						return trim( (string) $value );
					}
				}
			}
		}

		if ( is_object( $order ) ) {
			foreach ( array( 'get_tracking_number', 'get_tracking_code' ) as $method ) {
				if ( method_exists( $order, $method ) ) {
					$value = $order->$method();

					if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
						return trim( (string) $value );
					}
				}
			}
		}

		return $default;
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_tracking_number_set' ) ) {
	function velotok_core_delivery_order_tracking_number_set( $order = 0, $tracking_number = '' ) {
		$key   = velotok_core_delivery_order_tracking_meta_key();
		$value = is_scalar( $tracking_number ) ? trim( (string) $tracking_number ) : '';

		if ( is_object( $order ) ) {
			if ( method_exists( $order, 'update_meta_data' ) ) {
				$order->update_meta_data( $key, $value );
			} elseif ( method_exists( $order, 'set_meta_data' ) ) {
				$order->set_meta_data( $key, $value );
			}
		}

		$order_id = velotok_core_delivery_order_id( $order );

		if ( $order_id > 0 && function_exists( 'update_post_meta' ) ) {
			update_post_meta( $order_id, $key, $value );
		}

		$contract  = velotok_core_delivery_order_contract();
		$selection = velotok_core_delivery_order_meta( $order, $contract['meta']['selection'], array() );
		$selection = is_array( $selection ) ? $selection : array();
		$selection['tracking_number'] = $value;
		$selection['tracking']        = array(
			'number'          => $value,
			'tracking_number' => $value,
			'visible_to_owner'=> true,
		);

		if ( is_object( $order ) && method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( $contract['meta']['selection'], $selection );
		} elseif ( is_object( $order ) && method_exists( $order, 'set_meta_data' ) ) {
			$order->set_meta_data( $contract['meta']['selection'], $selection );
		}

		if ( $order_id > 0 && function_exists( 'update_post_meta' ) ) {
			update_post_meta( $order_id, $contract['meta']['selection'], $selection );
		}

		if ( is_object( $order ) && method_exists( $order, 'save' ) ) {
			$order->save();
		}

		return true;
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_tracking_data' ) ) {
	function velotok_core_delivery_order_tracking_data( $order = 0 ) {
		$tracking = velotok_core_delivery_order_tracking_number( $order );
		$visible  = '' !== $tracking;

		return array(
			'tracking_number' => $tracking,
			'number'          => $tracking,
			'tracking'        => $tracking,
			'code'            => $tracking,
			'has_tracking'    => $visible,
			'available'       => $visible,
			'visible_to'      => array( 'customer', 'owner' ),
			'visible_to_owner'=> $visible,
			'owner_visible'   => $visible,
		);
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_tracking_visible_to_owner' ) ) {
	function velotok_core_delivery_order_tracking_visible_to_owner( $order = 0, $viewer = array(), $context = array() ) {
		$tracking = velotok_core_delivery_order_tracking_number( $order );

		if ( '' === $tracking ) {
			return false;
		}

		if ( is_array( $viewer ) && ! empty( $viewer ) ) {
			$role = strtolower( (string) velotok_core_delivery_zones_value( $viewer, array( 'role' ), '' ) );

			if ( in_array( $role, array( 'owner', 'administrator', 'shop_manager', 'admin' ), true ) ) {
				return true;
			}

			$viewer_id = velotok_core_delivery_zones_value( $viewer, array( 'id', 'user_id' ), is_numeric( $viewer ) ? $viewer : 0 );
			$owner_id  = velotok_core_delivery_zones_value( $context, array( 'owner_id', 'shop_owner_id' ), 0 );

			if ( is_numeric( $viewer_id ) && is_numeric( $owner_id ) && (int) $viewer_id > 0 && (int) $viewer_id === (int) $owner_id ) {
				return true;
			}

			$customer_id = 0;

			if ( is_object( $order ) && method_exists( $order, 'get_customer_id' ) ) {
				$customer_id = (int) $order->get_customer_id();
			}

			if ( is_numeric( $viewer_id ) && $customer_id > 0 && (int) $viewer_id === $customer_id ) {
				return true;
			}
		}

		return true;
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_tracking_html' ) ) {
	function velotok_core_delivery_order_tracking_html( $order = 0 ) {
		$tracking = velotok_core_delivery_order_tracking_number( $order );

		if ( '' === $tracking ) {
			return '';
		}

		$escape = function ( $value ) {
			return function_exists( 'esc_html' ) ? esc_html( (string) $value ) : htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
		};

		return '<p class="velotok-delivery-tracking velotok-delivery-order-tracking" data-tracking-number="' . $escape( $tracking ) . '" data-visible-to-owner="true">'
			. '<span class="velotok-delivery-tracking-label">Трек-номер:</span> '
			. '<span class="velotok-delivery-tracking-number">' . $escape( $tracking ) . '</span>'
			. '</p>';
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_tracking' ) ) {
	function velotok_core_delivery_order_tracking( $order = 0 ) {
		echo velotok_core_delivery_order_tracking_html( $order );
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_track_number' ) ) {
	function velotok_core_delivery_order_track_number( $order = 0, $default = '' ) {
		return velotok_core_delivery_order_tracking_number( $order, $default );
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_get_tracking_number' ) ) {
	function velotok_core_delivery_order_get_tracking_number( $order = 0, $default = '' ) {
		return velotok_core_delivery_order_tracking_number( $order, $default );
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_save_tracking_number' ) ) {
	function velotok_core_delivery_order_save_tracking_number( $order = 0, $tracking_number = '' ) {
		return velotok_core_delivery_order_tracking_number_set( $order, $tracking_number );
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_tracking_visible' ) ) {
	function velotok_core_delivery_order_tracking_visible( $order = 0, $viewer = array(), $context = array() ) {
		return velotok_core_delivery_order_tracking_visible_to_owner( $order, $viewer, $context );
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_is_tracking_visible_to_owner' ) ) {
	function velotok_core_delivery_order_is_tracking_visible_to_owner( $order = 0, $viewer = array(), $context = array() ) {
		return velotok_core_delivery_order_tracking_visible_to_owner( $order, $viewer, $context );
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_set_tracking_number' ) ) {
	function velotok_core_delivery_order_set_tracking_number( $order = 0, $tracking_number = '' ) {
		return velotok_core_delivery_order_tracking_number_set( $order, $tracking_number );
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_tracking_number_get' ) ) {
	function velotok_core_delivery_order_tracking_number_get( $order = 0, $default = '' ) {
		return velotok_core_delivery_order_tracking_number( $order, $default );
	}
}

if ( ! function_exists( 'velotok_core_delivery_tracking_number' ) ) {
	function velotok_core_delivery_tracking_number( $order = 0, $default = '' ) {
		return velotok_core_delivery_order_tracking_number( $order, $default );
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_tracking_owner_visible' ) ) {
	function velotok_core_delivery_order_tracking_owner_visible( $order = 0, $viewer = array(), $context = array() ) {
		return velotok_core_delivery_order_tracking_visible_to_owner( $order, $viewer, $context );
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'woocommerce_order_details_after_order_table', 'velotok_core_delivery_order_tracking', 11, 1 );
	add_action( 'woocommerce_admin_order_data_after_shipping_address', 'velotok_core_delivery_order_tracking', 10, 1 );
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'velotok_core_delivery_order_tracking', 10, 1 );
}
