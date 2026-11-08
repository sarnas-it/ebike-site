<?php

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

if ( ! function_exists( 'velotok_core_checkout_order_contract' ) ) {
	function velotok_core_checkout_order_contract() {
		static $contract = null;

		if ( null !== $contract ) {
			return $contract;
		}

		$contract = array(
			'spec'            => 'checkout',
			'parent_spec'     => 'ecommerce-store',
			'requirement'     => 'TESTEB-23',
			'task'            => 'TESTEB-23',
			'requirement_ids' => array( 'TESTEB-23', 'TESTEB-24' ),
			'provider'        => 'woocommerce',
			'provider_label'  => 'WooCommerce',
			'checkout_class'  => 'WC_Checkout',
			'uses_wc_checkout'=> true,
			'creates_order'   => true,
			'network'         => false,
			'mocked'          => false,
			'nonce'           => array(
				'required'     => true,
				'server_side'  => true,
				'field'        => 'woocommerce-process-checkout-nonce',
				'action'       => 'woocommerce-process-checkout',
				'invalid_code' => 'CHECKOUT_INVALID_NONCE',
				'message'      => 'Срок действия формы истёк. Обновите страницу и повторите оформление.',
			),
			'auth'            => array(
				'requirement'    => 'TESTEB-24',
				'required'       => true,
				'guest_checkout' => false,
				'code'           => 'CHECKOUT_AUTH_REQUIRED',
				'message'        => 'Оформление заказа доступно только авторизованным покупателям.',
			),
			'cart'            => array(
				'required'   => true,
				'empty_code' => 'CHECKOUT_EMPTY_CART',
				'message'    => 'Нельзя оформить заказ с пустой корзиной.',
			),
			'validation'      => array(
				'server_side'    => true,
				'invalid_code'   => 'CHECKOUT_INVALID_FORM',
				'message'        => 'Проверьте правильность заполнения формы.',
				'required_fields'=> array(
					'billing_first_name',
					'billing_last_name',
					'billing_email',
					'billing_address_1',
					'billing_city',
					'billing_postcode',
					'payment_method',
					'delivery_method',
				),
				'optional_fields'=> array(
					'customer_note',
					'delivery_tracking_number',
				),
				'comment_field'  => 'customer_note',
			),
			'order'           => array(
				'composition_field' => 'line_items',
				'delivery_field'    => 'delivery_method',
				'payment_field'     => 'payment_method',
				'comment_field'     => 'customer_note',
				'tracking_field'    => 'delivery_tracking_number',
				'delivery_meta'     => '_velotok_delivery_method',
				'payment_meta'      => '_payment_method',
				'comment_meta'      => '_customer_note',
				'tracking_meta'     => '_velotok_delivery_tracking_number',
				'persisted'         => true,
				'visible_to'        => array( 'customer', 'owner' ),
			),
			'idempotency'     => array(
				'field'             => 'velotok_checkout_key',
				'meta'              => '_velotok_checkout_key',
				'prevents_duplicate'=> true,
				'reuse_existing'    => true,
				'duplicate_code'    => 'CHECKOUT_DUPLICATE',
				'message'           => 'Этот заказ уже оформлен.',
			),
			'result_codes'    => array(
				'created'   => 'CHECKOUT_CREATED',
				'available' => 'CHECKOUT_AVAILABLE',
				'duplicate' => 'CHECKOUT_DUPLICATE',
				'error'     => 'CHECKOUT_ERROR',
			),
		);

		return $contract;
	}
}

if ( ! function_exists( 'velotok_core_checkout_contract' ) ) {
	function velotok_core_checkout_contract() {
		return velotok_core_checkout_order_contract();
	}
}

if ( ! function_exists( 'velotok_core_checkout_value' ) ) {
	function velotok_core_checkout_value( $source, $keys, $default = null ) {
		foreach ( (array) $keys as $key ) {
			if ( is_array( $source ) && array_key_exists( $key, $source ) ) {
				return $source[ $key ];
			}

			if ( is_object( $source ) ) {
				if ( isset( $source->$key ) ) {
					return $source->$key;
				}

				foreach ( array( 'get_' . $key, 'get' . str_replace( ' ', '', ucwords( str_replace( '_', ' ', $key ) ) ) ) as $method ) {
					if ( method_exists( $source, $method ) ) {
						return $source->$method();
					}
				}
			}
		}

		return $default;
	}
}

if ( ! function_exists( 'velotok_core_checkout_scalar' ) ) {
	function velotok_core_checkout_scalar( $value, $default = '' ) {
		return is_scalar( $value ) ? (string) $value : $default;
	}
}

if ( ! function_exists( 'velotok_core_checkout_bool' ) ) {
	function velotok_core_checkout_bool( $value, $default = false ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( null === $value ) {
			return (bool) $default;
		}

		$normalized = strtolower( trim( velotok_core_checkout_scalar( $value, '' ) ) );

		if ( in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true ) ) {
			return true;
		}

		if ( in_array( $normalized, array( '0', 'false', 'no', 'off', '' ), true ) ) {
			return false;
		}

		return (bool) $value;
	}
}

if ( ! function_exists( 'velotok_core_checkout_posted_data' ) ) {
	function velotok_core_checkout_posted_data( $context = array() ) {
		$data = velotok_core_checkout_value( $context, array( 'data', 'posted_data', 'fields' ), array() );

		if ( is_object( $data ) ) {
			$data = get_object_vars( $data );
		}

		return is_array( $data ) ? $data : array();
	}
}

if ( ! function_exists( 'velotok_core_checkout_is_authenticated' ) ) {
	function velotok_core_checkout_is_authenticated( $context = array() ) {
		if ( array_key_exists( 'is_user_logged_in', $context ) ) {
			return velotok_core_checkout_bool( $context['is_user_logged_in'] );
		}

		if ( function_exists( 'is_user_logged_in' ) ) {
			return (bool) is_user_logged_in();
		}

		return false;
	}
}

if ( ! function_exists( 'velotok_core_checkout_auth_result' ) ) {
	function velotok_core_checkout_auth_result( $context = array() ) {
		$contract = velotok_core_checkout_order_contract();

		if ( velotok_core_checkout_is_authenticated( $context ) ) {
			return array(
				'required'      => true,
				'authenticated' => true,
				'available'     => true,
				'code'          => 'CHECKOUT_AUTH_OK',
				'message'       => '',
			);
		}

		return array(
			'required'      => true,
			'authenticated' => false,
			'available'     => false,
			'code'          => $contract['auth']['code'],
			'message'       => $contract['auth']['message'],
		);
	}
}

if ( ! function_exists( 'velotok_core_checkout_verify_nonce' ) ) {
	function velotok_core_checkout_verify_nonce( $context = array() ) {
		if ( array_key_exists( 'nonce_valid', $context ) ) {
			return velotok_core_checkout_bool( $context['nonce_valid'] );
		}

		$contract = velotok_core_checkout_order_contract();
		$nonce     = velotok_core_checkout_scalar( velotok_core_checkout_value( $context, array( 'nonce', 'nonce_value' ), '' ) );
		$action    = velotok_core_checkout_scalar( velotok_core_checkout_value( $context, array( 'nonce_action' ), $contract['nonce']['action'] ) );

		if ( '' === $nonce ) {
			return false;
		}

		if ( function_exists( 'wp_verify_nonce' ) ) {
			return false !== wp_verify_nonce( $nonce, $action );
		}

		return false;
	}
}

if ( ! function_exists( 'velotok_core_checkout_cart_items' ) ) {
	function velotok_core_checkout_cart_items( $context = array() ) {
		$cart = velotok_core_checkout_value( $context, array( 'cart_items', 'line_items', 'cart' ), null );

		if ( null === $cart && function_exists( 'WC' ) ) {
			$woocommerce = WC();

			if ( is_object( $woocommerce ) && isset( $woocommerce->cart ) ) {
				$cart = $woocommerce->cart;
			}
		}

		if ( is_object( $cart ) ) {
			foreach ( array( 'get_cart', 'get_cart_contents' ) as $method ) {
				if ( method_exists( $cart, $method ) ) {
					$cart = $cart->$method();
					break;
				}
			}
		}

		if ( is_array( $cart ) && isset( $cart['items'] ) && is_array( $cart['items'] ) ) {
			$cart = $cart['items'];
		}

		return is_array( $cart ) ? $cart : array();
	}
}

if ( ! function_exists( 'velotok_core_checkout_cart_is_empty' ) ) {
	function velotok_core_checkout_cart_is_empty( $context = array() ) {
		if ( array_key_exists( 'cart_empty', $context ) ) {
			return velotok_core_checkout_bool( $context['cart_empty'], true );
		}

		return empty( velotok_core_checkout_cart_items( $context ) );
	}
}

if ( ! function_exists( 'velotok_core_checkout_validate_form' ) ) {
	function velotok_core_checkout_validate_form( $data, $context = array() ) {
		$contract = velotok_core_checkout_order_contract();
		$required = velotok_core_checkout_value( $context, array( 'required_fields' ), $contract['validation']['required_fields'] );
		$errors   = array();

		foreach ( (array) $required as $field ) {
			$field = velotok_core_checkout_scalar( $field );

			if ( '' === $field ) {
				continue;
			}

			$value = velotok_core_checkout_value( $data, array( $field ), '' );

			if ( is_array( $value ) || is_object( $value ) ) {
				$value = '';
			}

			if ( '' === trim( velotok_core_checkout_scalar( $value ) ) ) {
				$errors[ $field ] = 'Обязательное поле не заполнено: ' . $field;
			}
		}

		$email = trim( velotok_core_checkout_scalar( velotok_core_checkout_value( $data, array( 'billing_email' ), '' ) ) );

		if ( '' !== $email && ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$errors['billing_email'] = 'Указан некорректный адрес электронной почты.';
		}

		return array(
			'valid'  => empty( $errors ),
			'code'   => empty( $errors ) ? 'CHECKOUT_VALID' : $contract['validation']['invalid_code'],
			'message'=> empty( $errors ) ? '' : $contract['validation']['message'],
			'errors' => $errors,
		);
	}
}

if ( ! function_exists( 'velotok_core_checkout_available' ) ) {
	function velotok_core_checkout_available( $context = array() ) {
		$contract = velotok_core_checkout_order_contract();
		$auth     = velotok_core_checkout_auth_result( $context );

		if ( empty( $auth['authenticated'] ) ) {
			return array(
				'available' => false,
				'code'      => $auth['code'],
				'message'   => $auth['message'],
			);
		}

		if ( velotok_core_checkout_cart_is_empty( $context ) ) {
			return array(
				'available' => false,
				'code'      => $contract['cart']['empty_code'],
				'message'   => $contract['cart']['message'],
			);
		}

		return array(
			'available' => true,
			'code'      => $contract['result_codes']['available'],
			'message'   => '',
		);
	}
}

if ( ! function_exists( 'velotok_core_checkout_validate' ) ) {
	function velotok_core_checkout_validate( $context = array() ) {
		$contract = velotok_core_checkout_order_contract();
		$available = velotok_core_checkout_available( $context );

		if ( empty( $available['available'] ) ) {
			return array_merge(
				array(
					'valid'  => false,
					'errors' => array(),
					'data'   => velotok_core_checkout_posted_data( $context ),
				),
				$available
			);
		}

		if ( ! velotok_core_checkout_verify_nonce( $context ) ) {
			return array(
				'valid'   => false,
				'available'=> false,
				'code'    => $contract['nonce']['invalid_code'],
				'message' => $contract['nonce']['message'],
				'errors'  => array(),
				'data'    => velotok_core_checkout_posted_data( $context ),
			);
		}

		$data     = velotok_core_checkout_posted_data( $context );
		$validation = velotok_core_checkout_validate_form( $data, $context );

		$validation['available'] = $validation['valid'];
		$validation['data']      = $data;

		return $validation;
	}
}

if ( ! function_exists( 'velotok_core_checkout_idempotency_key' ) ) {
	function velotok_core_checkout_idempotency_key( $data, $context = array() ) {
		$contract = velotok_core_checkout_order_contract();
		$explicit = velotok_core_checkout_scalar(
			velotok_core_checkout_value(
				$data,
				array( $contract['idempotency']['field'], 'checkout_key' ),
				velotok_core_checkout_value( $context, array( 'idempotency_key', 'checkout_key' ), '' )
			)
		);

		if ( '' !== trim( $explicit ) ) {
			return function_exists( 'sanitize_key' ) ? sanitize_key( $explicit ) : trim( $explicit );
		}

		$items = array();

		foreach ( velotok_core_checkout_cart_items( $context ) as $item ) {
			$items[] = array(
				'product_id' => velotok_core_checkout_scalar( velotok_core_checkout_value( $item, array( 'product_id', 'id' ), '' ) ),
				'quantity'   => velotok_core_checkout_scalar( velotok_core_checkout_value( $item, array( 'quantity', 'qty' ), 1 ) ),
			);
		}

		$seed = array(
			'user_id'         => velotok_core_checkout_scalar( velotok_core_checkout_value( $context, array( 'user_id', 'customer_id' ), '0' ) ),
			'email'           => velotok_core_checkout_scalar( velotok_core_checkout_value( $data, array( 'billing_email' ), '' ) ),
			'payment_method'  => velotok_core_checkout_scalar( velotok_core_checkout_value( $data, array( 'payment_method' ), '' ) ),
			'delivery_method' => velotok_core_checkout_scalar( velotok_core_checkout_value( $data, array( 'delivery_method', 'shipping_method' ), '' ) ),
			'items'           => $items,
		);

		return 'velotok-' . md5( json_encode( $seed ) );
	}
}

if ( ! function_exists( 'velotok_core_checkout_request_key' ) ) {
	function velotok_core_checkout_request_key( $key = null, $set = false ) {
		static $current = '';

		if ( $set && '' !== (string) $key ) {
			$current = (string) $key;
		}

		return $current;
	}
}

if ( ! function_exists( 'velotok_core_checkout_registry' ) ) {
	function velotok_core_checkout_registry( $key = null, $order = null, $store = false ) {
		static $orders = array();

		if ( $store && '' !== (string) $key ) {
			$orders[ (string) $key ] = $order;
			return $order;
		}

		if ( null !== $key && isset( $orders[ (string) $key ] ) ) {
			return $orders[ (string) $key ];
		}

		return null;
	}
}

if ( ! function_exists( 'velotok_core_checkout_find_order' ) ) {
	function velotok_core_checkout_find_order( $key, $context = array() ) {
		$key = trim( velotok_core_checkout_scalar( $key ) );

		if ( '' === $key ) {
			return null;
		}

		$known = velotok_core_checkout_registry( $key );

		if ( null !== $known ) {
			return $known;
		}

		$map = velotok_core_checkout_value( $context, array( 'orders_by_key', 'existing_orders' ), null );

		if ( is_array( $map ) && isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}

		$finder = velotok_core_checkout_value( $context, array( 'find_order_by_key', 'order_lookup' ), null );

		if ( is_callable( $finder ) ) {
			$found = call_user_func( $finder, $key, $context );

			if ( null !== $found && false !== $found ) {
				return $found;
			}
		}

		if ( function_exists( 'wc_get_orders' ) ) {
			$contract = velotok_core_checkout_order_contract();
			$orders   = wc_get_orders(
				array(
					'limit'      => 1,
					'meta_key'   => $contract['idempotency']['meta'],
					'meta_value' => $key,
					'return'     => 'objects',
				)
			);

			if ( ! empty( $orders ) ) {
				return reset( $orders );
			}
		}

		return null;
	}
}

if ( ! function_exists( 'velotok_core_checkout_order_id' ) ) {
	function velotok_core_checkout_order_id( $order ) {
		if ( is_numeric( $order ) ) {
			return (int) $order;
		}

		if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
			return (int) $order->get_id();
		}

		$id = velotok_core_checkout_value( $order, array( 'id', 'order_id' ), 0 );

		return is_numeric( $id ) ? (int) $id : 0;
	}
}

if ( ! function_exists( 'velotok_core_checkout_error' ) ) {
	function velotok_core_checkout_error( $value ) {
		if ( is_array( $value ) && ! empty( $value['__velotok_error'] ) ) {
			return $value;
		}

		if ( is_object( $value ) && method_exists( $value, 'get_error_message' ) ) {
			return array(
				'__velotok_error' => true,
				'code'            => method_exists( $value, 'get_error_code' ) ? $value->get_error_code() : 'CHECKOUT_ERROR',
				'message'         => $value->get_error_message(),
			);
		}

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $value ) ) {
			return array(
				'__velotok_error' => true,
				'code'            => $value->get_error_code(),
				'message'         => $value->get_error_message(),
			);
		}

		return null;
	}
}

if ( ! function_exists( 'velotok_core_checkout_checkout_instance' ) ) {
	function velotok_core_checkout_checkout_instance( $context = array() ) {
		$checkout = velotok_core_checkout_value( $context, array( 'checkout', 'wc_checkout' ), null );

		if ( is_object( $checkout ) ) {
			return $checkout;
		}

		$factory = velotok_core_checkout_value( $context, array( 'checkout_factory' ), null );

		if ( is_callable( $factory ) ) {
			return call_user_func( $factory, $context );
		}

		if ( function_exists( 'WC' ) ) {
			$woocommerce = WC();

			if ( is_object( $woocommerce ) && method_exists( $woocommerce, 'checkout' ) ) {
				return $woocommerce->checkout();
			}
		}

		if ( class_exists( 'WC_Checkout' ) ) {
			return new WC_Checkout();
		}

		return null;
	}
}

if ( ! function_exists( 'velotok_core_checkout_create_order' ) ) {
	function velotok_core_checkout_create_order( $data, $context = array() ) {
		$creator = velotok_core_checkout_value( $context, array( 'create_order', 'order_factory' ), null );

		if ( is_callable( $creator ) ) {
			return call_user_func( $creator, $data, $context );
		}

		$checkout = velotok_core_checkout_checkout_instance( $context );

		if ( ! is_object( $checkout ) || ! method_exists( $checkout, 'create_order' ) ) {
			return array(
				'__velotok_error' => true,
				'code'            => 'CHECKOUT_UNAVAILABLE',
				'message'         => 'WooCommerce Checkout недоступен.',
			);
		}

		try {
			return $checkout->create_order( $data );
		} catch ( Exception $exception ) {
			return array(
				'__velotok_error' => true,
				'code'            => 'CHECKOUT_ERROR',
				'message'         => $exception->getMessage(),
			);
		} catch ( Throwable $throwable ) {
			return array(
				'__velotok_error' => true,
				'code'            => 'CHECKOUT_ERROR',
				'message'         => $throwable->getMessage(),
			);
		}
	}
}

if ( ! function_exists( 'velotok_core_checkout_build_order_data' ) ) {
	function velotok_core_checkout_build_order_data( $data, $context = array() ) {
		$contract   = velotok_core_checkout_order_contract();
		$order_data = is_array( $data ) ? $data : array();
		$items      = velotok_core_checkout_cart_items( $context );

		if ( ! empty( $items ) ) {
			$order_data[ $contract['order']['composition_field'] ] = $items;
		}

		$delivery = velotok_core_checkout_value( $data, array( 'delivery_method', 'shipping_method', 'velotok_delivery_method' ), velotok_core_checkout_value( $context, array( 'delivery_method' ), '' ) );
		$payment  = velotok_core_checkout_value( $data, array( 'payment_method' ), velotok_core_checkout_value( $context, array( 'payment_method' ), '' ) );
		$comment  = velotok_core_checkout_value( $data, array( 'customer_note', 'order_comments' ), velotok_core_checkout_value( $context, array( 'customer_note', 'comment' ), '' ) );
		$tracking = velotok_core_checkout_value( $data, array( 'delivery_tracking_number', 'tracking_number' ), velotok_core_checkout_value( $context, array( 'delivery_tracking_number', 'tracking_number' ), '' ) );

		$order_data[ $contract['order']['delivery_field'] ] = velotok_core_checkout_scalar( $delivery );
		$order_data[ $contract['order']['payment_field'] ]  = velotok_core_checkout_scalar( $payment );
		$order_data[ $contract['order']['comment_field'] ]  = velotok_core_checkout_scalar( $comment );
		$order_data[ $contract['order']['tracking_field'] ] = velotok_core_checkout_scalar( $tracking );

		if ( ! isset( $order_data['order_comments'] ) ) {
			$order_data['order_comments'] = velotok_core_checkout_scalar( $comment );
		}

		$user_id = velotok_core_checkout_value( $data, array( 'user_id', 'customer_id' ), velotok_core_checkout_value( $context, array( 'user_id', 'customer_id' ), 0 ) );

		if ( '' !== velotok_core_checkout_scalar( $user_id ) ) {
			$order_data['user_id']     = $user_id;
			$order_data['customer_id'] = $user_id;
		}

		$order_data[ $contract['idempotency']['field'] ] = velotok_core_checkout_idempotency_key( $data, $context );

		return $order_data;
	}
}

if ( ! function_exists( 'velotok_core_checkout_order_meta' ) ) {
	function velotok_core_checkout_order_meta( $order, $key, $default = '' ) {
		if ( is_object( $order ) && method_exists( $order, 'get_meta' ) ) {
			$value = $order->get_meta( $key, true );

			if ( '' !== $value && null !== $value ) {
				return $value;
			}
		}

		$value = velotok_core_checkout_value( $order, array( $key, ltrim( $key, '_' ) ), null );

		if ( null !== $value ) {
			return $value;
		}

		if ( is_object( $order ) ) {
			$id = velotok_core_checkout_order_id( $order );

			if ( $id && function_exists( 'get_post_meta' ) ) {
				return get_post_meta( $id, $key, true );
			}
		}

		return $default;
	}
}

if ( ! function_exists( 'velotok_core_checkout_set_order_meta' ) ) {
	function velotok_core_checkout_set_order_meta( $order, $key, $value ) {
		if ( is_object( $order ) ) {
			if ( method_exists( $order, 'update_meta_data' ) ) {
				$order->update_meta_data( $key, $value );
				return true;
			}

			if ( method_exists( $order, 'set_meta_data' ) ) {
				$order->set_meta_data( $key, $value );
				return true;
			}
		}

		$id = velotok_core_checkout_order_id( $order );

		if ( $id && function_exists( 'update_post_meta' ) ) {
			return (bool) update_post_meta( $id, $key, $value );
		}

		return false;
	}
}

if ( ! function_exists( 'velotok_core_checkout_persist_order' ) ) {
	function velotok_core_checkout_persist_order( $order, $data, $context = array() ) {
		$contract = velotok_core_checkout_order_contract();
		$data     = is_array( $data ) ? $data : array();
		$key      = velotok_core_checkout_request_key();

		if ( '' === $key ) {
			$key = velotok_core_checkout_idempotency_key( $data, $context );
		}

		velotok_core_checkout_request_key( $key, true );

		$meta = array(
			$contract['order']['delivery_meta'] => velotok_core_checkout_value( $data, array( 'delivery_method', 'shipping_method' ), '' ),
			$contract['order']['payment_meta']  => velotok_core_checkout_value( $data, array( 'payment_method' ), '' ),
			$contract['order']['comment_meta']  => velotok_core_checkout_value( $data, array( 'customer_note', 'order_comments' ), '' ),
			$contract['order']['tracking_meta'] => velotok_core_checkout_value( $data, array( 'delivery_tracking_number', 'tracking_number' ), '' ),
			$contract['idempotency']['meta']    => $key,
		);

		foreach ( $meta as $meta_key => $meta_value ) {
			velotok_core_checkout_set_order_meta( $order, $meta_key, velotok_core_checkout_scalar( $meta_value ) );
		}

		if ( is_object( $order ) && method_exists( $order, 'save' ) ) {
			$order->save();
		}

		velotok_core_checkout_registry( $key, $order, true );

		return true;
	}
}

if ( ! function_exists( 'velotok_core_checkout_order_details' ) ) {
	function velotok_core_checkout_order_details( $order ) {
		$contract = velotok_core_checkout_order_contract();
		$data     = is_array( $order ) ? $order : array();

		if ( is_object( $order ) && method_exists( $order, 'get_data' ) ) {
			$order_data = $order->get_data();

			if ( is_array( $order_data ) ) {
				$data = $order_data;
			}
		}

		$items = velotok_core_checkout_value( $order, array( 'line_items', 'items' ), velotok_core_checkout_value( $data, array( 'line_items', 'items' ), array() ) );

		if ( is_object( $items ) && method_exists( $items, 'get_items' ) ) {
			$items = $items->get_items();
		}

		$delivery = velotok_core_checkout_order_meta( $order, $contract['order']['delivery_meta'], velotok_core_checkout_value( $data, array( 'delivery_method', 'shipping_method' ), '' ) );
		$payment  = velotok_core_checkout_order_meta( $order, $contract['order']['payment_meta'], velotok_core_checkout_value( $data, array( 'payment_method' ), '' ) );
		$comment  = velotok_core_checkout_order_meta( $order, $contract['order']['comment_meta'], velotok_core_checkout_value( $data, array( 'customer_note', 'order_comments' ), '' ) );
		$tracking = velotok_core_checkout_order_meta( $order, $contract['order']['tracking_meta'], velotok_core_checkout_value( $data, array( 'delivery_tracking_number', 'tracking_number' ), '' ) );

		return array(
			'order_id'        => velotok_core_checkout_order_id( $order ),
			'items'           => $items,
			'composition'     => $items,
			'delivery_method' => $delivery,
			'payment_method'  => $payment,
			'comment'         => $comment,
			'customer_note'   => $comment,
			'tracking_number' => $tracking,
			'visible_to'      => $contract['order']['visible_to'],
		);
	}
}

if ( ! function_exists( 'velotok_core_checkout_order_visible_to' ) ) {
	function velotok_core_checkout_order_visible_to( $order, $viewer = array(), $context = array() ) {
		$contract    = velotok_core_checkout_order_contract();
		$viewer_id   = velotok_core_checkout_value( $viewer, array( 'id', 'user_id' ), is_numeric( $viewer ) ? $viewer : 0 );
		$viewer_role = strtolower( velotok_core_checkout_scalar( velotok_core_checkout_value( $viewer, array( 'role' ), '' ) ) );
		$customer_id = velotok_core_checkout_value( $order, array( 'customer_id', 'user_id' ), 0 );
		$owner_id    = velotok_core_checkout_value( $context, array( 'owner_id', 'shop_owner_id' ), 0 );

		if ( ! $customer_id && is_object( $order ) && method_exists( $order, 'get_data' ) ) {
			$order_data  = $order->get_data();
			$customer_id = is_array( $order_data ) ? velotok_core_checkout_value( $order_data, array( 'customer_id', 'user_id' ), 0 ) : 0;
		}

		if ( in_array( $viewer_role, array( 'owner', 'administrator', 'shop_manager' ), true ) ) {
			return true;
		}

		if ( $viewer_id && $customer_id && (int) $viewer_id === (int) $customer_id ) {
			return true;
		}

		if ( $viewer_id && $owner_id && (int) $viewer_id === (int) $owner_id ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'velotok_core_checkout_place_order' ) ) {
	function velotok_core_checkout_place_order( $context = array() ) {
		$contract = velotok_core_checkout_order_contract();
		$validation = velotok_core_checkout_validate( $context );

		if ( empty( $validation['valid'] ) ) {
			return array(
				'created'  => false,
				'duplicate'=> false,
				'order'    => null,
				'order_id' => 0,
				'code'     => $validation['code'],
				'message'  => $validation['message'],
				'errors'   => isset( $validation['errors'] ) ? $validation['errors'] : array(),
			);
		}

		$data = velotok_core_checkout_posted_data( $context );
		$key  = velotok_core_checkout_idempotency_key( $data, $context );
		velotok_core_checkout_request_key( $key, true );
		$existing = velotok_core_checkout_find_order( $key, $context );

		if ( null !== $existing ) {
			return array(
				'created'  => false,
				'duplicate'=> true,
				'order'    => $existing,
				'order_id' => velotok_core_checkout_order_id( $existing ),
				'code'     => $contract['idempotency']['duplicate_code'],
				'message'  => $contract['idempotency']['message'],
				'errors'   => array(),
			);
		}

		$order_data = velotok_core_checkout_build_order_data( $data, $context );
		$order      = velotok_core_checkout_create_order( $order_data, $context );
		$error      = velotok_core_checkout_error( $order );

		if ( null !== $error ) {
			return array(
				'created'  => false,
				'duplicate'=> false,
				'order'    => null,
				'order_id' => 0,
				'code'     => $error['code'],
				'message'  => $error['message'],
				'errors'   => array(),
			);
		}

		velotok_core_checkout_persist_order( $order, $order_data, $context );

		return array(
			'created'  => true,
			'duplicate'=> false,
			'order'    => $order,
			'order_id' => velotok_core_checkout_order_id( $order ),
			'code'     => $contract['result_codes']['created'],
			'message'  => '',
			'errors'   => array(),
		);
	}
}

if ( ! function_exists( 'velotok_core_checkout_process' ) ) {
	function velotok_core_checkout_process( $context = array() ) {
		return velotok_core_checkout_place_order( $context );
	}
}

if ( ! function_exists( 'velotok_core_checkout_place' ) ) {
	function velotok_core_checkout_place( $context = array() ) {
		return velotok_core_checkout_place_order( $context );
	}
}

if ( ! function_exists( 'velotok_core_checkout_validate_request' ) ) {
	function velotok_core_checkout_validate_request() {
		$context = array(
			'is_user_logged_in' => function_exists( 'is_user_logged_in' ) ? is_user_logged_in() : false,
			'user_id'           => function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0,
			'nonce'             => isset( $_POST['woocommerce-process-checkout-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce-process-checkout-nonce'] ) ) : '',
			'nonce_action'      => 'woocommerce-process-checkout',
			'data'              => is_array( $_POST ) ? wp_unslash( $_POST ) : array(),
		);

		$validation = velotok_core_checkout_validate( $context );

		if ( empty( $validation['valid'] ) && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $validation['message'], 'error' );
			return;
		}

		$contract = velotok_core_checkout_order_contract();
		$key      = velotok_core_checkout_idempotency_key( $validation['data'], $context );
		velotok_core_checkout_request_key( $key, true );

		if ( null !== velotok_core_checkout_find_order( $key, $context ) && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $contract['idempotency']['message'], 'error' );
		}
	}
}

if ( ! function_exists( 'velotok_core_checkout_capture_order_meta' ) ) {
	function velotok_core_checkout_capture_order_meta( $order, $data = array() ) {
		$data    = is_array( $data ) ? $data : array();
		$user_id = velotok_core_checkout_value( $data, array( 'user_id', 'customer_id' ), 0 );

		if ( ! $user_id && function_exists( 'get_current_user_id' ) ) {
			$user_id = get_current_user_id();
		}

		$context = array(
			'user_id'    => $user_id,
			'cart_items' => velotok_core_checkout_cart_items( array() ),
		);

		velotok_core_checkout_persist_order( $order, $data, $context );
	}
}

if ( ! function_exists( 'velotok_core_checkout_force_login' ) ) {
	function velotok_core_checkout_force_login( $required = true ) {
		return true;
	}
}

if ( ! function_exists( 'velotok_core_checkout_disable_guest_checkout' ) ) {
	function velotok_core_checkout_disable_guest_checkout( $enabled = false ) {
		return false;
	}
}

if ( ! function_exists( 'velotok_core_checkout_must_be_logged_in_message' ) ) {
	function velotok_core_checkout_must_be_logged_in_message( $message = '' ) {
		$contract = velotok_core_checkout_order_contract();

		return $contract['auth']['message'];
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'woocommerce_checkout_process', 'velotok_core_checkout_validate_request' );
	add_action( 'woocommerce_checkout_create_order', 'velotok_core_checkout_capture_order_meta', 10, 2 );
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'woocommerce_checkout_registration_required', 'velotok_core_checkout_force_login', 999 );
	add_filter( 'woocommerce_checkout_registration_enabled', 'velotok_core_checkout_disable_guest_checkout', 999 );
	add_filter( 'woocommerce_checkout_must_be_logged_in_message', 'velotok_core_checkout_must_be_logged_in_message', 999 );
}
