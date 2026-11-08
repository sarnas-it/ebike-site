<?php
/**
 * Shopping cart: real WooCommerce integration.
 *
 * The cart helpers in cart.php keep an in-memory representation that is useful
 * for isolated tests. This file connects the same operations to the live
 * WooCommerce cart (WC()->cart):
 *
 * - adding, updating and removing positions happens through WC_Cart;
 * - totals include WooCommerce discounts, taxes and shipping;
 * - the quantity per position is capped by the cart contract and by the
 *   product's own purchase limit;
 * - removing the last position leaves an empty WooCommerce cart;
 * - a guest cart stored in the WooCommerce session is carried into the
 *   customer cart on login/registration without duplicating positions;
 * - the customer cart is persisted by WooCommerce user meta between sessions
 *   and devices;
 * - checkout is only available to authenticated customers.
 *
 * Spec: cart (requirement TESTEB-7).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

if ( ! defined( 'VELOTOK_CART_WC_INTEGRATION_LOADING' ) ) {
	define( 'VELOTOK_CART_WC_INTEGRATION_LOADING', true );
}

if ( ! function_exists( 'velotok_core_cart_contract' ) && is_readable( __DIR__ . '/cart.php' ) ) {
	require_once __DIR__ . '/cart.php';
}

/**
 * Return the WooCommerce cart contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_cart_woocommerce_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$contract = array(
		'spec'             => 'cart',
		'parent_spec'      => 'ecommerce-store',
		'requirement'      => 'TESTEB-7',
		'task'             => 'TESTEB-7',
		'requirements'     => array(
			'woocommerce_cart'  => 'Система SHALL вести корзину на WooCommerce',
			'guest_transfer'    => 'Гостевая корзина SHALL переноситься при входе',
			'guest_persistence' => 'Гостевая корзина SHALL сохраняться между сессиями',
			'user_persistence'  => 'Корзина авторизованного пользователя SHALL сохраняться между сессиями и устройствами',
			'totals'            => 'Итоги корзины SHALL учитывать скидки и доставку',
			'quantity_limit'    => 'Количество на позицию SHALL ограничиваться',
			'checkout_auth'     => 'Покупка SHALL требовать авторизации',
			'empty_on_remove'   => 'Удаление последней позиции SHALL переводить корзину в пустое состояние',
		),
		'source'           => 'woocommerce',
		'provider'         => 'woocommerce',
		'provider_id'      => 'woocommerce',
		'provider_label'   => 'WooCommerce cart',
		'cart_class'       => 'WC_Cart',
		'cart_accessor'    => 'WC()->cart',
		'uses_wc_cart'     => true,
		'mocked'           => false,
		'network'          => false,
		'persistent'       => true,
		'runtime'          => 'wordpress',
		'in_memory_fallback' => true,
		'integration'      => array(
			'file'     => 'includes/cart-woocommerce.php',
			'cart'     => 'WC()->cart',
			'session'  => 'WC()->session',
			'customer' => 'wc_get_current_user_id',
		),
		'operation'        => 'woocommerce_cart_add_update_remove_transfer',
		'operations'       => array(
			'add_item'            => 'velotok_core_wc_cart_add_item',
			'update_item'         => 'velotok_core_wc_cart_update_item',
			'remove_item'         => 'velotok_core_wc_cart_remove_item',
			'get_items'           => 'velotok_core_wc_cart_items',
			'calculate_totals'    => 'velotok_core_wc_cart_totals',
			'clear'               => 'velotok_core_wc_cart_clear',
			'transfer_guest_cart' => 'velotok_core_wc_cart_transfer_guest_cart',
			'persist_guest_cart'  => 'velotok_core_wc_cart_store_guest_snapshot',
			'restore_guest_cart'  => 'velotok_core_wc_cart_restore_guest_snapshot',
			'checkout_auth'       => 'velotok_core_wc_cart_checkout_auth_status',
		),
		'currency'         => 'RUB',
		'default_quantity' => 1,
		'min_quantity'     => 1,
		'max_quantity'     => 99,
		'quantity_step'    => 1,
		'storage'          => array(
			'type'            => 'woocommerce_session',
			'source'          => 'woocommerce',
			'mocked'          => false,
			'persistent'      => true,
			'guest_storage'   => 'woocommerce_session',
			'user_storage'    => 'woocommerce_persistent_cart',
			'guest_key'       => 'velotok_guest_cart',
			'guest_persistent' => true,
			'user_persistent' => true,
			'merge_by'        => 'product_id',
			'clear_after_checkout' => true,
		),
		'guest_cart'       => array(
			'enabled'                  => true,
			'source'                   => 'woocommerce',
			'mocked'                   => false,
			'storage'                  => 'woocommerce_session',
			'persistent'               => true,
			'identifier'               => 'woocommerce_session_cookie',
			'transfer_on_registration' => true,
			'transfer_on_login'        => true,
			'merge_strategy'           => 'merge_quantities',
			'merge_by'                 => 'product_id',
			'clear_guest_after_transfer' => true,
		),
		'totals'           => array(
			'fields'          => array(
				'item_count',
				'unique_item_count',
				'subtotal',
				'discount_total',
				'shipping_total',
				'total_tax',
				'total',
				'currency',
			),
			'strategy'        => 'woocommerce_cart_totals',
			'rounding'        => 2,
			'currency'        => 'RUB',
			'include_discounts' => true,
			'include_delivery'  => true,
			'include_taxes'     => true,
			'delivery_source'   => 'woocommerce_shipping',
			'mocked'            => false,
			'source'            => 'woocommerce',
		),
		'checkout'         => array(
			'enabled'             => true,
			'requires_auth'       => true,
			'guest_checkout'      => false,
			'requires_items'      => true,
			'empty_error'         => 'CART_EMPTY',
			'auth_error'          => 'CHECKOUT_AUTH_REQUIRED',
			'auth_message'        => 'Оформление заказа доступно только авторизованным покупателям.',
			'mocked'              => false,
			'source'              => 'woocommerce',
		),
		'error_codes'      => array(
			'empty'             => 'CART_EMPTY',
			'product_not_found' => 'CART_PRODUCT_NOT_FOUND',
			'item_not_found'    => 'CART_ITEM_NOT_FOUND',
			'quantity_invalid'  => 'CART_QUANTITY_INVALID',
			'quantity_limit'    => 'CART_QUANTITY_LIMIT',
			'add_failed'        => 'CART_ADD_FAILED',
			'auth_required'     => 'CHECKOUT_AUTH_REQUIRED',
		),
		'open_questions'   => array(
			'reserved_stock' => 'Резервирование остатков на время оформления остаётся настройкой WooCommerce.',
		),
	);

	return $contract;
}

/**
 * Alias for the WooCommerce cart contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_cart_wc_contract() {
	return velotok_core_cart_woocommerce_contract();
}

/**
 * Alias for the WooCommerce cart contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_wc_cart_contract() {
	return velotok_core_cart_woocommerce_contract();
}

/**
 * Return the live WooCommerce cart object.
 *
 * @return object|null Null when WooCommerce or its cart is not available.
 */
function velotok_core_wc_cart_runtime() {
	if ( ! function_exists( 'WC' ) ) {
		return null;
	}

	$wc = WC();

	if ( ! is_object( $wc ) || ! isset( $wc->cart ) || ! is_object( $wc->cart ) ) {
		return null;
	}

	return $wc->cart;
}

/**
 * Whether the live WooCommerce cart is available.
 *
 * @return bool
 */
function velotok_core_wc_cart_available() {
	return null !== velotok_core_wc_cart_runtime();
}

/**
 * Whether WooCommerce cart integration is available.
 *
 * @return bool
 */
function velotok_core_cart_woocommerce_available() {
	return velotok_core_wc_cart_available();
}

/**
 * Whether WooCommerce cart integration is available.
 *
 * @return bool
 */
function velotok_core_cart_wc_available() {
	return velotok_core_wc_cart_available();
}

/**
 * Return the WooCommerce session object.
 *
 * @return object|null
 */
function velotok_core_wc_cart_session() {
	if ( ! function_exists( 'WC' ) ) {
		return null;
	}

	$wc = WC();

	if ( ! is_object( $wc ) || ! isset( $wc->session ) || ! is_object( $wc->session ) ) {
		return null;
	}

	return $wc->session;
}

/**
 * Read a value from an object or array with several candidate keys.
 *
 * @param mixed $source  Source object or array.
 * @param array $keys    Candidate keys.
 * @param mixed $default Default value.
 * @return mixed
 */
function velotok_core_wc_cart_value( $source, $keys, $default = null ) {
	foreach ( (array) $keys as $key ) {
		if ( is_array( $source ) && array_key_exists( $key, $source ) ) {
			return $source[ $key ];
		}

		if ( is_object( $source ) ) {
			if ( isset( $source->$key ) ) {
				return $source->$key;
			}

			$getter = 'get_' . $key;

			if ( method_exists( $source, $getter ) ) {
				return $source->$getter();
			}
		}
	}

	return $default;
}

/**
 * Resolve a WooCommerce product id from a product id, slug, SKU or array.
 *
 * @param mixed $product Product identifier or data.
 * @return int Product id or zero when it cannot be resolved.
 */
function velotok_core_wc_cart_find_product_id( $product ) {
	if ( is_array( $product ) ) {
		foreach ( array( 'product_id', 'id' ) as $field ) {
			if ( isset( $product[ $field ] ) && is_numeric( $product[ $field ] ) && (int) $product[ $field ] > 0 ) {
				return (int) $product[ $field ];
			}
		}

		foreach ( array( 'slug', 'product_slug', 'sku' ) as $field ) {
			if ( isset( $product[ $field ] ) && is_scalar( $product[ $field ] ) && '' !== trim( (string) $product[ $field ] ) ) {
				$product = trim( (string) $product[ $field ] );
				break;
			}
		}
	}

	if ( is_numeric( $product ) && (int) $product > 0 ) {
		return (int) $product;
	}

	if ( ! is_scalar( $product ) ) {
		return 0;
	}

	$value = trim( (string) $product );

	if ( '' === $value ) {
		return 0;
	}

	if ( ctype_digit( $value ) ) {
		return (int) $value;
	}

	if ( function_exists( 'wc_get_product_id_by_sku' ) ) {
		$product_id = (int) wc_get_product_id_by_sku( $value );

		if ( $product_id > 0 ) {
			return $product_id;
		}
	}

	if ( function_exists( 'get_page_by_path' ) ) {
		$page = get_page_by_path( $value, OBJECT, 'product' );

		if ( is_object( $page ) && isset( $page->ID ) ) {
			return (int) $page->ID;
		}
	}

	if ( function_exists( 'wc_get_products' ) ) {
		$products = wc_get_products(
			array(
				'slug'   => $value,
				'limit'  => 1,
				'status' => 'publish',
			)
		);

		if ( is_array( $products ) && ! empty( $products ) ) {
			$found = $products[0];

			if ( is_object( $found ) && method_exists( $found, 'get_id' ) ) {
				return (int) $found->get_id();
			}

			if ( is_object( $found ) && isset( $found->ID ) ) {
				return (int) $found->ID;
			}

			if ( is_numeric( $found ) ) {
				return (int) $found;
			}
		}
	}

	return 0;
}

/**
 * Resolve a product reference into the pieces WooCommerce needs.
 *
 * @param mixed $product Product id, slug, SKU or data.
 * @return array<string,mixed>
 */
function velotok_core_wc_cart_resolve_product( $product ) {
	$resolved = array(
		'product_id'   => velotok_core_wc_cart_find_product_id( $product ),
		'variation_id' => 0,
		'variation'    => array(),
		'quantity'     => 1,
		'name'         => '',
		'price'        => null,
	);

	if ( is_array( $product ) ) {
		foreach ( array( 'variation_id' ) as $field ) {
			if ( isset( $product[ $field ] ) && is_numeric( $product[ $field ] ) ) {
				$resolved['variation_id'] = (int) $product[ $field ];
			}
		}

		if ( isset( $product['variation'] ) && is_array( $product['variation'] ) ) {
			$resolved['variation'] = $product['variation'];
		}

		if ( isset( $product['quantity'] ) && is_numeric( $product['quantity'] ) ) {
			$resolved['quantity'] = (int) $product['quantity'];
		}

		foreach ( array( 'name', 'title' ) as $field ) {
			if ( isset( $product[ $field ] ) && is_scalar( $product[ $field ] ) ) {
				$resolved['name'] = (string) $product[ $field ];
				break;
			}
		}

		foreach ( array( 'price', 'regular_price', 'amount' ) as $field ) {
			if ( isset( $product[ $field ] ) && is_numeric( $product[ $field ] ) ) {
				$resolved['price'] = (float) $product[ $field ];
				break;
			}
		}
	}

	return $resolved;
}

/**
 * Load a WooCommerce product object when possible.
 *
 * @param int $product_id Product id.
 * @return object|null
 */
function velotok_core_wc_cart_product( $product_id ) {
	if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
		return null;
	}

	$product = wc_get_product( $product_id );

	return is_object( $product ) ? $product : null;
}

/**
 * Return the maximum quantity allowed for one cart position.
 *
 * @param int $product_id Product id.
 * @return int
 */
function velotok_core_wc_cart_max_quantity( $product_id = 0 ) {
	$contract = velotok_core_cart_woocommerce_contract();
	$max      = max( 1, (int) $contract['max_quantity'] );
	$product  = velotok_core_wc_cart_product( (int) $product_id );

	if ( null !== $product && method_exists( $product, 'get_max_purchase_quantity' ) ) {
		$product_max = (int) $product->get_max_purchase_quantity();

		if ( $product_max > 0 ) {
			$max = min( $max, $product_max );
		}
	}

	if ( function_exists( 'apply_filters' ) ) {
		$max = (int) apply_filters( 'velotok_core_cart_max_quantity', $max, (int) $product_id );
	}

	return max( 1, $max );
}

/**
 * Read the raw WooCommerce cart contents.
 *
 * @param object $cart Cart object.
 * @return array<string,array<string,mixed>>
 */
function velotok_core_wc_cart_contents( $cart ) {
	if ( ! is_object( $cart ) ) {
		return array();
	}

	if ( method_exists( $cart, 'get_cart' ) ) {
		$contents = $cart->get_cart();
	} elseif ( isset( $cart->cart_contents ) ) {
		$contents = $cart->cart_contents;
	} else {
		$contents = array();
	}

	return is_array( $contents ) ? $contents : array();
}

/**
 * Normalize WooCommerce cart contents into the shared cart item shape.
 *
 * @return array<int,array<string,mixed>>
 */
function velotok_core_wc_cart_items() {
	$cart  = velotok_core_wc_cart_runtime();
	$items = array();

	foreach ( velotok_core_wc_cart_contents( $cart ) as $key => $line ) {
		$line       = is_array( $line ) ? $line : array();
		$data       = isset( $line['data'] ) ? $line['data'] : null;
		$quantity   = (int) velotok_core_wc_cart_value( $line, array( 'quantity', 'qty' ), 0 );
		$product_id = (int) velotok_core_wc_cart_value( $line, array( 'product_id' ), 0 );

		if ( $product_id <= 0 && is_object( $data ) && method_exists( $data, 'get_id' ) ) {
			$product_id = (int) $data->get_id();
		}

		$price = velotok_core_wc_cart_value( $line, array( 'price' ), null );

		if ( ! is_numeric( $price ) && is_object( $data ) && method_exists( $data, 'get_price' ) ) {
			$price = $data->get_price();
		}

		$price      = is_numeric( $price ) ? (float) $price : 0.0;
		$line_total = velotok_core_wc_cart_value( $line, array( 'line_total' ), null );

		if ( ! is_numeric( $line_total ) ) {
			$line_total = $price * $quantity;
		}

		$name = velotok_core_wc_cart_value( $line, array( 'name' ), '' );

		if ( '' === $name && is_object( $data ) && method_exists( $data, 'get_name' ) ) {
			$name = $data->get_name();
		}

		$slug = velotok_core_wc_cart_value( $line, array( 'slug' ), '' );

		if ( '' === $slug && is_object( $data ) && method_exists( $data, 'get_slug' ) ) {
			$slug = $data->get_slug();
		}

		$items[] = array(
			'key'          => (string) $key,
			'product_id'   => $product_id,
			'variation_id' => (int) velotok_core_wc_cart_value( $line, array( 'variation_id' ), 0 ),
			'slug'         => (string) $slug,
			'name'         => (string) $name,
			'price'        => round( $price, 2 ),
			'quantity'     => $quantity,
			'line_total'   => round( (float) $line_total, 2 ),
			'currency'     => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'RUB',
			'mocked'       => false,
			'source'       => 'woocommerce',
		);
	}

	return $items;
}

/**
 * Sum the quantity of a product already in the live cart.
 *
 * @param int $product_id   Product id.
 * @param int $variation_id Variation id.
 * @return int
 */
function velotok_core_wc_cart_quantity_for_product( $product_id, $variation_id = 0 ) {
	$quantity = 0;

	foreach ( velotok_core_wc_cart_items() as $item ) {
		if ( (int) $item['product_id'] !== (int) $product_id ) {
			continue;
		}

		if ( (int) $variation_id > 0 && (int) $item['variation_id'] !== (int) $variation_id ) {
			continue;
		}

		$quantity += (int) $item['quantity'];
	}

	return $quantity;
}

/**
 * Return a normalized cart item by WooCommerce cart item key.
 *
 * @param string $key Cart item key.
 * @return array<string,mixed>|null
 */
function velotok_core_wc_cart_item( $key ) {
	foreach ( velotok_core_wc_cart_items() as $item ) {
		if ( (string) $item['key'] === (string) $key ) {
			return $item;
		}
	}

	return null;
}

/**
 * Read a numeric total from the WooCommerce cart.
 *
 * @param object $cart Cart object.
 * @param array  $methods Candidate getter methods.
 * @param float  $default Default value.
 * @return float
 */
function velotok_core_wc_cart_number( $cart, $methods, $default = 0.0 ) {
	foreach ( (array) $methods as $method ) {
		if ( is_object( $cart ) && method_exists( $cart, $method ) ) {
			$value = $cart->$method();

			if ( is_numeric( $value ) ) {
				return (float) $value;
			}
		}
	}

	return (float) $default;
}

/**
 * Build the cart totals from WooCommerce, including discounts and shipping.
 *
 * @return array<string,mixed>
 */
function velotok_core_wc_cart_totals() {
	$contract = velotok_core_cart_woocommerce_contract();
	$cart     = velotok_core_wc_cart_runtime();

	if ( null === $cart ) {
		return array(
			'valid'          => false,
			'items'          => array(),
			'item_count'     => 0,
			'quantity_count' => 0,
			'unique_item_count' => 0,
			'subtotal'       => 0.0,
			'discount_total' => 0.0,
			'shipping_total' => 0.0,
			'total_tax'      => 0.0,
			'total'          => 0.0,
			'currency'       => $contract['currency'],
			'empty'          => true,
			'include_delivery' => true,
			'mocked'         => false,
			'source'         => 'woocommerce',
			'provider'       => 'woocommerce',
			'code'           => $contract['error_codes']['empty'],
		);
	}

	if ( method_exists( $cart, 'calculate_totals' ) ) {
		$cart->calculate_totals();
	}

	$items      = velotok_core_wc_cart_items();
	$item_count = 0;

	foreach ( $items as $item ) {
		$item_count += (int) $item['quantity'];
	}

	$subtotal       = velotok_core_wc_cart_number( $cart, array( 'get_subtotal' ), 0.0 );
	$discount_total = velotok_core_wc_cart_number( $cart, array( 'get_discount_total' ), 0.0 )
		+ velotok_core_wc_cart_number( $cart, array( 'get_discount_tax' ), 0.0 );
	$shipping_total = velotok_core_wc_cart_number( $cart, array( 'get_shipping_total' ), 0.0 )
		+ velotok_core_wc_cart_number( $cart, array( 'get_shipping_tax' ), 0.0 );
	$total_tax      = velotok_core_wc_cart_number( $cart, array( 'get_total_tax' ), 0.0 );
	$total          = velotok_core_wc_cart_number( $cart, array( 'get_total' ), $subtotal - $discount_total + $shipping_total );

	if ( 0.0 === $subtotal && ! empty( $items ) ) {
		foreach ( $items as $item ) {
			$subtotal += (float) $item['line_total'];
		}
	}

	$empty = 0 === $item_count;

	if ( method_exists( $cart, 'is_empty' ) ) {
		$empty = (bool) $cart->is_empty();
	}

	$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : $contract['currency'];

	return array(
		'valid'             => true,
		'items'             => $items,
		'cart_items'        => $items,
		'item_count'        => $item_count,
		'quantity_count'    => $item_count,
		'unique_item_count' => count( $items ),
		'items_count'       => count( $items ),
		'subtotal'          => round( $subtotal, 2 ),
		'discount_total'    => round( $discount_total, 2 ),
		'shipping_total'    => round( $shipping_total, 2 ),
		'total_tax'         => round( $total_tax, 2 ),
		'total'             => round( $total, 2 ),
		'currency'          => $currency,
		'empty'             => $empty,
		'is_empty'          => $empty,
		'include_discounts' => true,
		'include_delivery'  => true,
		'mocked'            => false,
		'source'            => 'woocommerce',
		'provider'          => 'woocommerce',
		'uses_wc_cart'      => true,
	);
}

/**
 * Build a complete cart summary from the live WooCommerce cart.
 *
 * @param array<string,mixed> $extra Extra fields merged into the result.
 * @return array<string,mixed>
 */
function velotok_core_wc_cart_summary( $extra = array() ) {
	$totals = velotok_core_wc_cart_totals();

	if ( ! is_array( $extra ) ) {
		$extra = array();
	}

	return array_merge( $totals, $extra );
}

/**
 * Add a product to the WooCommerce cart.
 *
 * Returns null when WooCommerce cannot handle the request, so callers can fall
 * back to the in-memory cart used by isolated tests.
 *
 * @param mixed $product  Product id, slug, SKU or data.
 * @param mixed $quantity Quantity.
 * @param array $extra    Extra cart item data.
 * @return array<string,mixed>|null
 */
function velotok_core_wc_cart_add_item( $product, $quantity = 1, $extra = array() ) {
	$cart = velotok_core_wc_cart_runtime();

	if ( null === $cart || ! method_exists( $cart, 'add_to_cart' ) ) {
		return null;
	}

	$contract = velotok_core_cart_woocommerce_contract();
	$resolved = velotok_core_wc_cart_resolve_product( $product );

	if ( $resolved['product_id'] <= 0 ) {
		return null;
	}

	if ( ! is_numeric( $quantity ) ) {
		$quantity = 1;
	}

	$quantity = (int) $quantity;

	if ( $quantity < 1 ) {
		return velotok_core_wc_cart_summary(
			array(
				'valid'  => false,
				'success' => false,
				'added'  => false,
				'code'   => $contract['error_codes']['quantity_invalid'],
				'error'  => $contract['error_codes']['quantity_invalid'],
				'item'   => null,
			)
		);
	}

	$max       = velotok_core_wc_cart_max_quantity( $resolved['product_id'] );
	$existing  = velotok_core_wc_cart_quantity_for_product( $resolved['product_id'], $resolved['variation_id'] );
	$requested = min( $max, $existing + $quantity );
	$delta     = max( 0, $requested - $existing );

	if ( $delta < 1 ) {
		return velotok_core_wc_cart_summary(
			array(
				'valid'    => true,
				'success'  => true,
				'added'    => false,
				'code'     => $contract['error_codes']['quantity_limit'],
				'error'    => $contract['error_codes']['quantity_limit'],
				'item'     => null,
				'quantity' => $existing,
			)
		);
	}

	$extra = is_array( $extra ) ? $extra : array();
	$key   = $cart->add_to_cart(
		$resolved['product_id'],
		$delta,
		$resolved['variation_id'],
		$resolved['variation'],
		$extra
	);

	if ( ! $key ) {
		return velotok_core_wc_cart_summary(
			array(
				'valid'   => false,
				'success' => false,
				'added'   => false,
				'code'    => $contract['error_codes']['add_failed'],
				'error'   => $contract['error_codes']['add_failed'],
				'item'    => null,
			)
		);
	}

	$item = velotok_core_wc_cart_item( $key );

	return velotok_core_wc_cart_summary(
		array(
			'valid'         => true,
			'success'       => true,
			'added'         => true,
			'code'          => null,
			'error'         => null,
			'item'          => $item,
			'last_item'     => $item,
			'cart_item_key' => (string) $key,
		)
	);
}

/**
 * Update the quantity of a WooCommerce cart position.
 *
 * @param mixed $key      Cart item key.
 * @param mixed $quantity New quantity.
 * @return array<string,mixed>|null
 */
function velotok_core_wc_cart_update_item( $key, $quantity = 1 ) {
	$cart = velotok_core_wc_cart_runtime();

	if ( null === $cart || ! method_exists( $cart, 'set_quantity' ) ) {
		return null;
	}

	$key = is_scalar( $key ) ? (string) $key : '';

	if ( '' === $key ) {
		return null;
	}

	$contract = velotok_core_cart_woocommerce_contract();
	$item     = velotok_core_wc_cart_item( $key );

	if ( null === $item ) {
		return velotok_core_wc_cart_summary(
			array(
				'valid'   => false,
				'success' => false,
				'updated' => false,
				'code'    => $contract['error_codes']['item_not_found'],
				'error'   => $contract['error_codes']['item_not_found'],
				'item'    => null,
			)
		);
	}

	$quantity = is_numeric( $quantity ) ? (int) $quantity : 0;

	if ( $quantity < 1 ) {
		return velotok_core_wc_cart_remove_item( $key );
	}

	$max      = velotok_core_wc_cart_max_quantity( (int) $item['product_id'] );
	$quantity = min( $max, $quantity );

	$cart->set_quantity( $key, $quantity, true );
	$updated = velotok_core_wc_cart_item( $key );

	return velotok_core_wc_cart_summary(
		array(
			'valid'         => true,
			'success'       => true,
			'updated'       => true,
			'code'          => null,
			'error'         => null,
			'item'          => $updated,
			'cart_item_key' => $key,
		)
	);
}

/**
 * Remove a position from the WooCommerce cart.
 *
 * @param mixed $key Cart item key.
 * @return array<string,mixed>|null
 */
function velotok_core_wc_cart_remove_item( $key ) {
	$cart = velotok_core_wc_cart_runtime();

	if ( null === $cart || ! method_exists( $cart, 'remove_cart_item' ) ) {
		return null;
	}

	$key = is_scalar( $key ) ? (string) $key : '';

	if ( '' === $key ) {
		return null;
	}

	$contract = velotok_core_cart_woocommerce_contract();
	$item     = velotok_core_wc_cart_item( $key );

	if ( null === $item ) {
		return velotok_core_wc_cart_summary(
			array(
				'valid'   => false,
				'success' => false,
				'removed' => false,
				'code'    => $contract['error_codes']['item_not_found'],
				'error'   => $contract['error_codes']['item_not_found'],
				'item'    => null,
			)
		);
	}

	$cart->remove_cart_item( $key );

	return velotok_core_wc_cart_summary(
		array(
			'valid'   => true,
			'success' => true,
			'removed' => true,
			'code'    => null,
			'error'   => null,
			'item'    => $item,
		)
	);
}

/**
 * Empty the WooCommerce cart.
 *
 * @return array<string,mixed>|null
 */
function velotok_core_wc_cart_clear() {
	$cart = velotok_core_wc_cart_runtime();

	if ( null === $cart ) {
		return null;
	}

	if ( method_exists( $cart, 'empty_cart' ) ) {
		$cart->empty_cart();
	} else {
		foreach ( array_keys( velotok_core_wc_cart_contents( $cart ) ) as $key ) {
			if ( method_exists( $cart, 'remove_cart_item' ) ) {
				$cart->remove_cart_item( $key );
			}
		}
	}

	return velotok_core_wc_cart_summary(
		array(
			'valid'   => true,
			'success' => true,
			'cleared' => true,
		)
	);
}

/**
 * Return the guest cart snapshot stored in the WooCommerce session.
 *
 * @return array<int,array<string,mixed>>
 */
function velotok_core_wc_cart_guest_snapshot() {
	$session = velotok_core_wc_cart_session();

	if ( null === $session || ! method_exists( $session, 'get' ) ) {
		return array();
	}

	$snapshot = $session->get( 'velotok_guest_cart' );

	if ( is_array( $snapshot ) && isset( $snapshot['items'] ) && is_array( $snapshot['items'] ) ) {
		return array_values( array_filter( $snapshot['items'], 'is_array' ) );
	}

	return is_array( $snapshot ) ? array_values( array_filter( $snapshot, 'is_array' ) ) : array();
}

/**
 * Persist the guest cart in the WooCommerce session between requests.
 *
 * @return bool Whether a snapshot was stored.
 */
function velotok_core_wc_cart_store_guest_snapshot() {
	if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
		return false;
	}

	$session = velotok_core_wc_cart_session();

	if ( null === $session || ! method_exists( $session, 'set' ) ) {
		return false;
	}

	$session->set(
		'velotok_guest_cart',
		array(
			'items'      => velotok_core_wc_cart_items(),
			'updated_at' => time(),
		)
	);

	return true;
}

/**
 * Restore a stored guest cart when a new session starts empty.
 *
 * @return bool Whether items were restored.
 */
function velotok_core_wc_cart_restore_guest_snapshot() {
	if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
		return false;
	}

	$cart = velotok_core_wc_cart_runtime();

	if ( null === $cart || ! method_exists( $cart, 'add_to_cart' ) ) {
		return false;
	}

	if ( method_exists( $cart, 'is_empty' ) && ! $cart->is_empty() ) {
		return false;
	}

	$items = velotok_core_wc_cart_guest_snapshot();

	if ( empty( $items ) ) {
		return false;
	}

	$restored = false;

	foreach ( $items as $item ) {
		$result = velotok_core_wc_cart_add_item(
			$item,
			isset( $item['quantity'] ) ? $item['quantity'] : 1
		);

		if ( is_array( $result ) && ! empty( $result['added'] ) ) {
			$restored = true;
		}
	}

	return $restored;
}

/**
 * Clear the stored guest cart snapshot after it has been transferred.
 *
 * @return void
 */
function velotok_core_wc_cart_clear_guest_snapshot() {
	$session = velotok_core_wc_cart_session();

	if ( null === $session ) {
		return;
	}

	if ( method_exists( $session, 'delete' ) ) {
		$session->delete( 'velotok_guest_cart' );
	} elseif ( method_exists( $session, 'set' ) ) {
		$session->set( 'velotok_guest_cart', array() );
	}
}

/**
 * Transfer a guest cart into the live customer cart without duplicates.
 *
 * Quantities of the same product are merged and the result is capped by the
 * per-position limit. The stored guest snapshot is cleared afterwards.
 *
 * @param mixed $guest_cart Guest cart array.
 * @param int   $user_id    Customer id.
 * @return array<string,mixed>|null Null when WooCommerce cannot handle it.
 */
function velotok_core_wc_cart_transfer_guest_cart( $guest_cart = array(), $user_id = 0 ) {
	$cart = velotok_core_wc_cart_runtime();

	if ( null === $cart || ! method_exists( $cart, 'add_to_cart' ) ) {
		return null;
	}

	$items = array();

	if ( is_array( $guest_cart ) ) {
		if ( isset( $guest_cart['items'] ) && is_array( $guest_cart['items'] ) ) {
			$items = array_values( array_filter( $guest_cart['items'], 'is_array' ) );
		} elseif ( isset( $guest_cart['cart_items'] ) && is_array( $guest_cart['cart_items'] ) ) {
			$items = array_values( array_filter( $guest_cart['cart_items'], 'is_array' ) );
		} else {
			$items = array_values( array_filter( $guest_cart, 'is_array' ) );
		}
	}

	$transferred = 0;
	$skipped     = 0;

	foreach ( $items as $item ) {
		$resolved = velotok_core_wc_cart_resolve_product( $item );

		if ( $resolved['product_id'] <= 0 ) {
			$skipped++;
			continue;
		}

		$quantity = isset( $item['quantity'] ) && is_numeric( $item['quantity'] ) ? (int) $item['quantity'] : 1;

		if ( $quantity < 1 ) {
			$skipped++;
			continue;
		}

		$max      = velotok_core_wc_cart_max_quantity( $resolved['product_id'] );
		$existing = velotok_core_wc_cart_quantity_for_product( $resolved['product_id'], $resolved['variation_id'] );
		$target   = min( $max, $existing + $quantity );
		$delta    = max( 0, $target - $existing );

		if ( $delta > 0 ) {
			$key = $cart->add_to_cart(
				$resolved['product_id'],
				$delta,
				$resolved['variation_id'],
				$resolved['variation'],
				array()
			);

			if ( ! $key ) {
				$skipped++;
				continue;
			}
		}

		$transferred++;
	}

	if ( method_exists( $cart, 'calculate_totals' ) ) {
		$cart->calculate_totals();
	}

	velotok_core_wc_cart_clear_guest_snapshot();

	return velotok_core_wc_cart_summary(
		array(
			'valid'               => true,
			'guest'               => false,
			'authenticated'       => true,
			'user_id'             => (int) $user_id,
			'transferred'         => $transferred,
			'transferred_count'   => $transferred,
			'transferred_items'   => $transferred,
			'skipped'             => $skipped,
			'guest_cleared'       => true,
			'guest_cart_cleared'  => true,
			'guest_cart_transferred' => $transferred > 0,
			'transfer_strategy'   => 'merge_quantities',
			'transfer_mocked'     => false,
			'mocked'              => false,
			'source'              => 'woocommerce',
			'provider'            => 'woocommerce',
			'code'                => null,
			'error'               => null,
		)
	);
}

/**
 * Whether the live cart already contains every item of a snapshot.
 *
 * This makes the login merge idempotent when WooCommerce has already carried
 * the guest session cart into the customer cart.
 *
 * @param array<int,array<string,mixed>> $items Snapshot items.
 * @return bool
 */
function velotok_core_wc_cart_contains_snapshot( $items ) {
	if ( ! is_array( $items ) || empty( $items ) ) {
		return true;
	}

	foreach ( $items as $item ) {
		$resolved = velotok_core_wc_cart_resolve_product( $item );

		if ( $resolved['product_id'] <= 0 ) {
			continue;
		}

		$required = isset( $item['quantity'] ) && is_numeric( $item['quantity'] ) ? (int) $item['quantity'] : 1;
		$current  = velotok_core_wc_cart_quantity_for_product( $resolved['product_id'], $resolved['variation_id'] );

		if ( $current < $required ) {
			return false;
		}
	}

	return true;
}

/**
 * Merge the stored guest cart on login.
 *
 * The guest cart normally lives in the WooCommerce session snapshot. When the
 * snapshot has not been stored yet (for example the visitor logs in right after
 * adding a product), the items already present in the live guest cart are used
 * instead, so the cart still survives the login. Existing customer positions
 * are preserved and duplicate products are merged by quantity.
 *
 * @param string $user_login User login.
 * @param mixed  $user       User object.
 * @return array<string,mixed>|null
 */
function velotok_core_wc_cart_merge_guest_on_login( $user_login = '', $user = null ) {
	unset( $user_login );

	$user_id = 0;

	if ( is_object( $user ) && isset( $user->ID ) ) {
		$user_id = (int) $user->ID;
	} elseif ( function_exists( 'get_current_user_id' ) ) {
		$user_id = (int) get_current_user_id();
	}

	$source = 'session_snapshot';
	$items  = velotok_core_wc_cart_guest_snapshot();

	if ( empty( $items ) ) {
		$items  = velotok_core_wc_cart_items();
		$source = empty( $items ) ? 'none' : 'live_cart';
	}

	if ( empty( $items ) ) {
		velotok_core_wc_cart_clear_guest_snapshot();

		return velotok_core_wc_cart_summary(
			array(
				'valid'           => true,
				'login_merge'     => true,
				'guest_source'    => 'none',
				'transferred'     => 0,
				'items_preserved' => 0,
				'guest_cleared'   => true,
			)
		);
	}

	if ( velotok_core_wc_cart_contains_snapshot( $items ) ) {
		velotok_core_wc_cart_clear_guest_snapshot();

		return velotok_core_wc_cart_summary(
			array(
				'valid'                      => true,
				'login_merge'                => true,
				'guest_source'               => $source,
				'transferred'                => 0,
				'items_preserved'            => count( $items ),
				'guest_cleared'              => true,
				'guest_cart_already_present' => true,
			)
		);
	}

	$result = velotok_core_wc_cart_transfer_guest_cart( array( 'items' => $items ), $user_id );

	if ( ! is_array( $result ) ) {
		return null;
	}

	$result['login_merge']     = true;
	$result['guest_source']    = $source;
	$result['items_preserved'] = count( $items );

	return $result;
}

/**
 * Whether checkout requires an authenticated customer.
 *
 * @return bool
 */
function velotok_core_wc_cart_requires_authentication_for_checkout() {
	$contract = velotok_core_cart_woocommerce_contract();
	$required = ! empty( $contract['checkout']['requires_auth'] );

	if ( function_exists( 'apply_filters' ) ) {
		$required = (bool) apply_filters( 'velotok_core_cart_requires_authentication_for_checkout', $required );
	}

	return $required;
}

/**
 * Checkout authorisation status.
 *
 * @return array<string,mixed>
 */
function velotok_core_wc_cart_checkout_auth_status() {
	$contract  = velotok_core_cart_woocommerce_contract();
	$required  = velotok_core_wc_cart_requires_authentication_for_checkout();
	$logged_in = function_exists( 'is_user_logged_in' ) ? (bool) is_user_logged_in() : false;
	$allowed   = ! $required || $logged_in;

	return array(
		'valid'          => $allowed,
		'allowed'        => $allowed,
		'requires_auth'  => $required,
		'authenticated'  => $logged_in,
		'guest_checkout' => ! $required,
		'code'           => $allowed ? null : $contract['error_codes']['auth_required'],
		'error'          => $allowed ? null : $contract['error_codes']['auth_required'],
		'message'        => $allowed ? '' : $contract['checkout']['auth_message'],
		'mocked'         => false,
		'source'         => 'woocommerce',
		'provider'       => 'woocommerce',
	);
}

/**
 * Reject checkout for guests during the WooCommerce checkout process.
 *
 * @return bool Whether checkout may continue.
 */
function velotok_core_wc_cart_validate_checkout_auth() {
	$status = velotok_core_wc_cart_checkout_auth_status();

	if ( $status['allowed'] ) {
		return true;
	}

	if ( function_exists( 'wc_add_notice' ) ) {
		wc_add_notice( $status['message'], 'error' );
	}

	return false;
}

/**
 * Clamp a quantity before WooCommerce adds a product to the cart.
 *
 * @param mixed $quantity   Quantity.
 * @param int   $product_id Product id.
 * @return int
 */
function velotok_core_wc_cart_filter_add_quantity( $quantity, $product_id = 0 ) {
	$quantity = is_numeric( $quantity ) ? (int) $quantity : 1;

	return min( $quantity, velotok_core_wc_cart_max_quantity( (int) $product_id ) );
}

/**
 * Validate a quantity before WooCommerce adds a product to the cart.
 *
 * @param bool  $passed        Validation state.
 * @param int   $product_id    Product id.
 * @param mixed $quantity      Quantity.
 * @param int   $variation_id  Variation id.
 * @param array $variations    Variation data.
 * @return bool
 */
function velotok_core_wc_cart_validate_add_quantity( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
	unset( $variation_id, $variations );

	if ( ! is_numeric( $quantity ) || (int) $quantity < 1 ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( 'Укажите корректное количество товара.', 'error' );
		}

		return false;
	}

	if ( (int) $quantity > velotok_core_wc_cart_max_quantity( (int) $product_id ) ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( 'Превышено максимальное количество товара в корзине.', 'error' );
		}

		return false;
	}

	return (bool) $passed;
}

/**
 * Validate a quantity before WooCommerce updates a cart position.
 *
 * @param bool   $passed        Validation state.
 * @param string $cart_item_key Cart item key.
 * @param array  $values        Cart item values.
 * @param mixed  $quantity      New quantity.
 * @return bool
 */
function velotok_core_wc_cart_validate_update_quantity( $passed, $cart_item_key, $values, $quantity ) {
	unset( $cart_item_key );

	if ( ! is_numeric( $quantity ) || (int) $quantity < 1 ) {
		return false;
	}

	$product_id = is_array( $values ) && isset( $values['product_id'] ) ? (int) $values['product_id'] : 0;

	if ( (int) $quantity > velotok_core_wc_cart_max_quantity( $product_id ) ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( 'Превышено максимальное количество товара в корзине.', 'error' );
		}

		return false;
	}

	return (bool) $passed;
}

if ( ! function_exists( 'velotok_core_cart_woocommerce_add_item' ) ) {
	/**
	 * Alias for adding a product to the WooCommerce cart.
	 *
	 * @param mixed $product  Product.
	 * @param mixed $quantity Quantity.
	 * @param array $extra    Extra data.
	 * @return array<string,mixed>|null
	 */
	function velotok_core_cart_woocommerce_add_item( $product, $quantity = 1, $extra = array() ) {
		return velotok_core_wc_cart_add_item( $product, $quantity, $extra );
	}
}

if ( ! function_exists( 'velotok_core_cart_wc_add_item' ) ) {
	/**
	 * Alias for adding a product to the WooCommerce cart.
	 *
	 * @param mixed $product  Product.
	 * @param mixed $quantity Quantity.
	 * @param array $extra    Extra data.
	 * @return array<string,mixed>|null
	 */
	function velotok_core_cart_wc_add_item( $product, $quantity = 1, $extra = array() ) {
		return velotok_core_wc_cart_add_item( $product, $quantity, $extra );
	}
}

if ( ! function_exists( 'velotok_core_cart_woocommerce_update_item' ) ) {
	/**
	 * Alias for updating a WooCommerce cart position.
	 *
	 * @param mixed $key      Cart item key.
	 * @param mixed $quantity Quantity.
	 * @return array<string,mixed>|null
	 */
	function velotok_core_cart_woocommerce_update_item( $key, $quantity = 1 ) {
		return velotok_core_wc_cart_update_item( $key, $quantity );
	}
}

if ( ! function_exists( 'velotok_core_cart_wc_update_item' ) ) {
	/**
	 * Alias for updating a WooCommerce cart position.
	 *
	 * @param mixed $key      Cart item key.
	 * @param mixed $quantity Quantity.
	 * @return array<string,mixed>|null
	 */
	function velotok_core_cart_wc_update_item( $key, $quantity = 1 ) {
		return velotok_core_wc_cart_update_item( $key, $quantity );
	}
}

if ( ! function_exists( 'velotok_core_cart_woocommerce_remove_item' ) ) {
	/**
	 * Alias for removing a WooCommerce cart position.
	 *
	 * @param mixed $key Cart item key.
	 * @return array<string,mixed>|null
	 */
	function velotok_core_cart_woocommerce_remove_item( $key ) {
		return velotok_core_wc_cart_remove_item( $key );
	}
}

if ( ! function_exists( 'velotok_core_cart_wc_remove_item' ) ) {
	/**
	 * Alias for removing a WooCommerce cart position.
	 *
	 * @param mixed $key Cart item key.
	 * @return array<string,mixed>|null
	 */
	function velotok_core_cart_wc_remove_item( $key ) {
		return velotok_core_wc_cart_remove_item( $key );
	}
}

if ( ! function_exists( 'velotok_core_cart_woocommerce_totals' ) ) {
	/**
	 * Alias for WooCommerce cart totals.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_core_cart_woocommerce_totals() {
		return velotok_core_wc_cart_totals();
	}
}

if ( ! function_exists( 'velotok_core_cart_wc_totals' ) ) {
	/**
	 * Alias for WooCommerce cart totals.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_core_cart_wc_totals() {
		return velotok_core_wc_cart_totals();
	}
}

if ( ! function_exists( 'velotok_core_cart_woocommerce_transfer_guest_cart' ) ) {
	/**
	 * Alias for transferring a guest cart into WooCommerce.
	 *
	 * @param mixed $guest_cart Guest cart.
	 * @param int   $user_id    User id.
	 * @return array<string,mixed>|null
	 */
	function velotok_core_cart_woocommerce_transfer_guest_cart( $guest_cart = array(), $user_id = 0 ) {
		return velotok_core_wc_cart_transfer_guest_cart( $guest_cart, $user_id );
	}
}

if ( ! function_exists( 'velotok_core_cart_wc_transfer_guest_cart' ) ) {
	/**
	 * Alias for transferring a guest cart into WooCommerce.
	 *
	 * @param mixed $guest_cart Guest cart.
	 * @param int   $user_id    User id.
	 * @return array<string,mixed>|null
	 */
	function velotok_core_cart_wc_transfer_guest_cart( $guest_cart = array(), $user_id = 0 ) {
		return velotok_core_wc_cart_transfer_guest_cart( $guest_cart, $user_id );
	}
}

if ( ! function_exists( 'velotok_core_cart_woocommerce_items' ) ) {
	/**
	 * Alias for WooCommerce cart items.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	function velotok_core_cart_woocommerce_items() {
		return velotok_core_wc_cart_items();
	}
}

if ( ! function_exists( 'velotok_core_cart_wc_items' ) ) {
	/**
	 * Alias for WooCommerce cart items.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	function velotok_core_cart_wc_items() {
		return velotok_core_wc_cart_items();
	}
}

if ( ! function_exists( 'velotok_core_cart_woocommerce_clear' ) ) {
	/**
	 * Alias for emptying the WooCommerce cart.
	 *
	 * @return array<string,mixed>|null
	 */
	function velotok_core_cart_woocommerce_clear() {
		return velotok_core_wc_cart_clear();
	}
}

if ( ! function_exists( 'velotok_core_cart_wc_clear' ) ) {
	/**
	 * Alias for emptying the WooCommerce cart.
	 *
	 * @return array<string,mixed>|null
	 */
	function velotok_core_cart_wc_clear() {
		return velotok_core_wc_cart_clear();
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'woocommerce_cart_updated', 'velotok_core_wc_cart_store_guest_snapshot' );
	add_action( 'woocommerce_cart_loaded_from_session', 'velotok_core_wc_cart_restore_guest_snapshot' );
	add_action( 'woocommerce_checkout_process', 'velotok_core_wc_cart_validate_checkout_auth' );
	add_action( 'wp_login', 'velotok_core_wc_cart_merge_guest_on_login', 20, 2 );
	add_action( 'woocommerce_add_to_cart_validation', 'velotok_core_wc_cart_validate_add_quantity', 10, 5 );
	add_action( 'woocommerce_update_cart_validation', 'velotok_core_wc_cart_validate_update_quantity', 10, 4 );
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'woocommerce_add_to_cart_quantity', 'velotok_core_wc_cart_filter_add_quantity', 10, 2 );
}
