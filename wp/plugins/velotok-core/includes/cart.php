<?php
/**
 * Shopping cart contract.
 *
 * This is the single access point for the cart used to place an order. On a
 * live site the helpers delegate to WooCommerce (WC()->cart) through
 * cart-woocommerce.php: the cart is stored in the WooCommerce session for
 * guests and in customer user meta for authenticated users, totals include
 * discounts and shipping, and a guest cart is merged on login without
 * duplicates. The in-memory array implementation remains as a fallback for
 * isolated tests.
 *
 * Spec: cart (requirement TESTEB-7).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

if ( is_readable( __DIR__ . '/catalog.php' ) ) {
	require_once __DIR__ . '/catalog.php';
}

/**
 * Return the shopping cart contract.
 *
 * The contract documents:
 * - the spec identifier, the explicit `mocked` marker and the stub source;
 * - the cart item fields, operation names and error codes;
 * - the in-memory storage rules and the fact that nothing is persisted;
 * - the guest cart rules and how its items are transferred on registration;
 * - the total calculation rules used before checkout;
 * - the open questions that block a real WooCommerce cart integration.
 *
 * @return array<string,mixed>
 */
function velotok_core_cart_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$item_fields = array(
		'key'         => array(
			'label'    => 'Ключ позиции',
			'type'     => 'string',
			'required' => true,
			'mocked'   => true,
		),
		'product_id'  => array(
			'label'    => 'Идентификатор товара',
			'type'     => 'string',
			'required' => true,
			'mocked'   => true,
		),
		'slug'        => array(
			'label'    => 'Слаг товара',
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
		'name'        => array(
			'label'    => 'Название',
			'type'     => 'string',
			'required' => true,
			'mocked'   => true,
		),
		'price'       => array(
			'label'    => 'Цена',
			'type'     => 'number',
			'required' => true,
			'mocked'   => true,
		),
		'quantity'    => array(
			'label'    => 'Количество',
			'type'     => 'integer',
			'required' => true,
			'mocked'   => true,
		),
		'line_total'  => array(
			'label'    => 'Сумма позиции',
			'type'     => 'number',
			'required' => true,
			'mocked'   => true,
		),
		'currency'    => array(
			'label'    => 'Валюта',
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
		'category'    => array(
			'label'    => 'Категория',
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
		'image'       => array(
			'label'    => 'Изображение',
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
	);

	$contract = array(
		'spec'              => 'cart',
		'requirement'       => 'TESTEB-7',
		'task'              => 'TESTEB-7',
		'requirement_ids'   => array( 'TESTEB-7', 'TESTEB-8', 'TESTEB-9', 'TESTEB-10', 'TESTEB-151' ),
		'parent_spec'       => 'ecommerce-store',
		'catalog_spec'      => 'ecommerce-store',
		'checkout_spec'     => 'clarify-096bb945',
		'delivery_spec'     => 'clarify-b0e1a6d7',
		'cart_spec'         => 'clarify-30af92ca',
		'guest_cart_spec'   => 'clarify-30af92ca',
		'parent_relation'   => 'Корзина ведётся на WooCommerce (WC()->cart); in-memory реализация используется только изолированными тестами.',
		'requirements'      => array(
			'woocommerce_cart'  => 'Система SHALL вести корзину на WooCommerce',
			'guest_transfer'    => 'Гостевая корзина SHALL переноситься при входе',
			'guest_persistence' => 'Гостевая корзина SHALL сохраняться между сессиями',
			'user_persistence'  => 'Корзина авторизованного пользователя SHALL сохраняться между сессиями и устройствами',
			'totals'            => 'Итоги корзины SHALL учитывать скидки и доставку',
			'quantity_limit'    => 'Количество на позицию SHALL ограничиваться',
			'checkout_auth'     => 'Покупка SHALL требовать авторизации',
			'empty_on_remove'   => 'Удаление последней позиции SHALL переводить корзину в пустое состояние',
		),
		'source'            => 'woocommerce',
		'provider'          => 'woocommerce',
		'provider_id'       => 'woocommerce',
		'provider_label'    => 'WooCommerce cart',
		'cart_class'        => 'WC_Cart',
		'cart_accessor'     => 'WC()->cart',
		'uses_wc_cart'      => true,
		'mocked'            => false,
		'network'           => false,
		'persistent'        => true,
		'runtime'           => 'wordpress',
		'in_memory_fallback' => true,
		'integration'       => array(
			'file'        => 'includes/cart-woocommerce.php',
			'cart'        => 'WC()->cart',
			'session'     => 'WC()->session',
			'calculation' => 'WC_Cart::calculate_totals',
		),
		'cart_type'         => 'shopping_cart',
		'operation'         => 'provide_cart_for_checkout',
		'operations'        => array(
			'create'             => 'velotok_core_cart_create',
			'get_items'          => 'velotok_core_cart_items',
			'get_item'           => 'velotok_core_cart_get_item',
			'add_item'           => 'velotok_core_cart_add_item',
			'update_item'        => 'velotok_core_cart_update_item',
			'remove_item'        => 'velotok_core_cart_remove_item',
			'count'              => 'velotok_core_cart_count',
			'subtotal'           => 'velotok_core_cart_subtotal',
			'calculate_totals'   => 'velotok_core_cart_totals',
			'clear'              => 'velotok_core_cart_clear',
			'transfer_guest_cart' => 'velotok_core_cart_transfer_guest_cart',
		),
		'woocommerce_operations' => array(
			'add_item'            => 'velotok_core_wc_cart_add_item',
			'update_item'         => 'velotok_core_wc_cart_update_item',
			'remove_item'         => 'velotok_core_wc_cart_remove_item',
			'calculate_totals'    => 'velotok_core_wc_cart_totals',
			'transfer_guest_cart' => 'velotok_core_wc_cart_transfer_guest_cart',
			'persist_guest_cart'  => 'velotok_core_wc_cart_store_guest_snapshot',
			'restore_guest_cart'  => 'velotok_core_wc_cart_restore_guest_snapshot',
			'persist_user_cart'   => 'velotok_core_wc_cart_store_user_snapshot',
			'restore_user_cart'   => 'velotok_core_wc_cart_restore_user_snapshot',
			'checkout_auth'       => 'velotok_core_wc_cart_checkout_auth_status',
		),
		'currency'          => 'RUB',
		'default_quantity'  => 1,
		'min_quantity'      => 1,
		'max_quantity'      => 99,
		'quantity_step'     => 1,
		'allow_zero_quantity' => false,
		'empty_cart'        => array(
			'items'    => array(),
			'count'    => 0,
			'subtotal' => 0.0,
			'total'    => 0.0,
			'currency' => 'RUB',
			'empty'    => true,
			'mocked'   => true,
			'source'   => 'stub',
		),
		'fields'            => $item_fields,
		'item_fields'       => $item_fields,
		'cart_fields'       => array(
			'items'      => 'array',
			'item_count' => 'integer',
			'subtotal'   => 'number',
			'total'      => 'number',
			'currency'   => 'string',
			'empty'      => 'boolean',
			'mocked'     => 'boolean',
			'source'     => 'string',
		),
		'required_fields'   => array( 'product_id', 'name', 'price', 'quantity' ),
		'required_item_fields' => array( 'product_id', 'name', 'price', 'quantity' ),
		'optional_fields'   => array( 'slug', 'category', 'image', 'currency', 'line_total', 'key' ),
		'storage'           => array(
			'type'        => 'woocommerce_session',
			'source'      => 'woocommerce',
			'mocked'      => false,
			'persistent'  => true,
			'network'     => false,
			'guest'       => true,
			'guest_storage' => 'woocommerce_session',
			'user_storage'  => 'woocommerce_persistent_cart',
			'user_key'      => '_woocommerce_persistent_cart_{blog_id}',
			'user_meta'     => '_woocommerce_persistent_cart_{blog_id}',
			'user_snapshot_meta' => '_velotok_user_cart',
			'guest_persistent' => true,
			'guest_cookie'  => 'velotok_guest_cart',
			'guest_cookie_lifetime' => 2592000,
			'user_persistent'  => true,
			'merge_by'    => 'product_id',
			'clear_after_checkout' => true,
		),
		'guest_cart'        => array(
			'enabled'                 => true,
			'source'                  => 'woocommerce',
			'mocked'                  => false,
			'storage'                 => 'woocommerce_session',
			'persistent'              => true,
			'identifier'              => 'woocommerce_session_cookie',
			'transfer_on_registration' => true,
			'transfer_on_login'       => true,
			'merge_strategy'          => 'merge_quantities',
			'merge_by'                => 'product_id',
			'clear_guest_after_transfer' => true,
			'lost_on_logout'          => false,
		),
		'totals'            => array(
			'fields'          => array( 'item_count', 'unique_item_count', 'subtotal', 'discount_total', 'shipping_total', 'total_tax', 'total', 'currency' ),
			'strategy'        => 'woocommerce_cart_totals',
			'rounding'        => 2,
			'currency'        => 'RUB',
			'include_discounts' => true,
			'include_delivery' => true,
			'include_taxes'    => true,
			'delivery_source' => 'woocommerce_shipping',
			'mocked'          => false,
			'source'          => 'woocommerce',
		),
		'checkout'          => array(
			'enabled'           => true,
			'requires_auth'     => true,
			'requires_items'    => true,
			'guest_checkout'    => false,
			'empty_error'       => 'CART_EMPTY',
			'auth_error'        => 'CHECKOUT_AUTH_REQUIRED',
			'delivery_source'   => 'checkout',
			'payment_source'    => 'checkout',
			'mocked'            => false,
			'source'            => 'woocommerce',
		),
		'error_codes'       => array(
			'empty'            => 'CART_EMPTY',
			'product_not_found' => 'CART_PRODUCT_NOT_FOUND',
			'item_not_found'   => 'CART_ITEM_NOT_FOUND',
			'quantity_invalid' => 'CART_QUANTITY_INVALID',
			'quantity_limit'   => 'CART_QUANTITY_LIMIT',
			'product_required' => 'CART_PRODUCT_REQUIRED',
			'auth_required'    => 'CHECKOUT_AUTH_REQUIRED',
		),
		'empty_error'       => 'CART_EMPTY',
		'allow_guest'       => true,
		'guest'             => true,
		'product_not_found' => 'CART_PRODUCT_NOT_FOUND',
		'item_not_found'    => 'CART_ITEM_NOT_FOUND',
		'quantity_invalid'  => 'CART_QUANTITY_INVALID',
		'open_questions'    => array(
			'guest_cart' => 'Сценарий гостевой корзины и её перенос при регистрации: идентификатор гостя, слияние позиций и очистка гостевой корзины (clarify-30af92ca).',
			'persistence' => 'Где хранить корзину между запросами: сессия WooCommerce, cookie или серверное хранилище.',
			'prices'      => 'Как пересчитывать цены и скидки при изменении каталога и акций.',
			'inventory'   => 'Как проверять наличие, лимиты и частичное отсутствие товаров при оформлении.',
			'integration' => 'Каким образом корзина синхронизируется с WooCommerce и реальной платёжной системой.',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer an explicit stub contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_cart_stub_contract() {
	return velotok_core_cart_contract();
}

/**
 * Alias kept for callers that use the data contract wording.
 *
 * @return array<string,mixed>
 */
function velotok_core_cart_data_contract() {
	return velotok_core_cart_contract();
}

/**
 * Normalize a cart key for comparisons.
 *
 * @param mixed $value Raw key.
 * @return string
 */
function velotok_core_cart_normalize_key( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	if ( function_exists( 'mb_strtolower' ) ) {
		return mb_strtolower( $value, 'UTF-8' );
	}

	return strtolower( $value );
}

/**
 * Build an empty mocked cart.
 *
 * @param array<int,array<string,mixed>> $items Optional initial items.
 * @return array<string,mixed>
 */
function velotok_core_cart_create( $items = array() ) {
	$contract = velotok_core_cart_contract();
	$cart     = array(
		'items'      => array(),
		'currency'   => $contract['currency'],
		'item_count' => 0,
		'subtotal'   => 0.0,
		'total'      => 0.0,
		'empty'      => true,
		'mocked'     => true,
		'source'     => 'stub',
		'provider'   => 'stub',
	);

	if ( is_array( $items ) ) {
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$cart = velotok_core_cart_add_item( $cart, $item, isset( $item['quantity'] ) ? $item['quantity'] : 1 );
		}
	}

	return velotok_core_cart_recalculate( $cart );
}

/**
 * Alias for creating an empty cart.
 *
 * @param array<int,array<string,mixed>> $items Optional initial items.
 * @return array<string,mixed>
 */
function velotok_core_cart_new( $items = array() ) {
	return velotok_core_cart_create( $items );
}

/**
 * Check whether a value looks like a cart array.
 *
 * @param mixed $value Value to check.
 * @return bool
 */
function velotok_core_cart_is_cart( $value ) {
	return is_array( $value ) && ( array_key_exists( 'items', $value ) || array_key_exists( 'cart_items', $value ) );
}

/**
 * Check whether a cart value is a WooCommerce-backed snapshot.
 *
 * @param mixed $cart Cart value.
 * @return bool
 */
function velotok_core_cart_is_wc_cart( $cart ) {
	if ( ! is_array( $cart ) ) {
		return false;
	}

	if ( isset( $cart['uses_wc_cart'] ) && $cart['uses_wc_cart'] ) {
		return true;
	}

	return isset( $cart['source'] ) && 'woocommerce' === $cart['source'];
}

/**
 * Normalize the items collection of a cart.
 *
 * @param mixed $cart Cart array.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_cart_items( $cart = array() ) {
	if ( ! velotok_core_cart_is_cart( $cart ) || velotok_core_cart_is_wc_cart( $cart ) ) {
		if ( function_exists( 'velotok_core_wc_cart_items' ) && function_exists( 'velotok_core_wc_cart_available' ) && velotok_core_wc_cart_available() ) {
			return velotok_core_wc_cart_items();
		}

		if ( ! velotok_core_cart_is_cart( $cart ) ) {
			return array();
		}
	}

	$items = isset( $cart['items'] ) ? $cart['items'] : $cart['cart_items'];

	return is_array( $items ) ? array_values( array_filter( $items, 'is_array' ) ) : array();
}

/**
 * Recalculate cart counters and totals.
 *
 * @param array<string,mixed> $cart Cart array.
 * @return array<string,mixed>
 */
function velotok_core_cart_recalculate( $cart ) {
	$contract       = velotok_core_cart_contract();
	$items          = velotok_core_cart_items( $cart );
	$item_count     = 0;
	$subtotal       = 0.0;

	foreach ( $items as $index => $item ) {
		$quantity            = isset( $item['quantity'] ) && is_numeric( $item['quantity'] ) ? (int) $item['quantity'] : 0;
		$price               = isset( $item['price'] ) && is_numeric( $item['price'] ) ? (float) $item['price'] : 0.0;
		$line_total          = round( $price * $quantity, 2 );
		$item_count         += $quantity;
		$subtotal           += $line_total;
		$items[ $index ]['quantity']   = $quantity;
		$items[ $index ]['price']      = $price;
		$items[ $index ]['line_total'] = $line_total;
		$items[ $index ]['currency']   = isset( $item['currency'] ) ? (string) $item['currency'] : $contract['currency'];
		$items[ $index ]['mocked']     = true;
		$items[ $index ]['source']     = 'stub';
	}

	$subtotal = round( $subtotal, 2 );
	$discount = isset( $cart['discount_total'] ) && is_numeric( $cart['discount_total'] ) ? max( 0.0, (float) $cart['discount_total'] ) : 0.0;
	$shipping = isset( $cart['shipping_total'] ) && is_numeric( $cart['shipping_total'] ) ? max( 0.0, (float) $cart['shipping_total'] ) : 0.0;
	$tax      = isset( $cart['total_tax'] ) && is_numeric( $cart['total_tax'] ) ? max( 0.0, (float) $cart['total_tax'] ) : 0.0;
	$total    = round( $subtotal - $discount + $shipping + $tax, 2 );

	$cart['items']              = array_values( $items );
	$cart['cart_items']         = array_values( $items );
	$cart['currency']           = $contract['currency'];
	$cart['item_count']         = $item_count;
	$cart['quantity_count']     = $item_count;
	$cart['unique_item_count']  = count( $items );
	$cart['items_count']        = count( $items );
	$cart['subtotal']           = $subtotal;
	$cart['discount_total']     = round( $discount, 2 );
	$cart['shipping_total']     = round( $shipping, 2 );
	$cart['total_tax']          = round( $tax, 2 );
	$cart['total']              = $total;
	$cart['empty']              = 0 === $item_count;
	$cart['is_empty']           = 0 === $item_count;
	$cart['mocked']             = true;
	$cart['source']             = 'stub';
	$cart['provider']           = 'stub';

	return $cart;
}

/**
 * Resolve a product definition from the mocked catalog or an explicit array.
 *
 * @param mixed $product Product slug, identifier, name or product data.
 * @return array<string,mixed>|null Null when the product cannot be resolved.
 */
function velotok_core_cart_resolve_product( $product ) {
	if ( is_array( $product ) ) {
		$slug = '';

		foreach ( array( 'slug', 'product_slug', 'key' ) as $field ) {
			if ( isset( $product[ $field ] ) && is_scalar( $product[ $field ] ) && '' !== trim( (string) $product[ $field ] ) ) {
				$slug = (string) $product[ $field ];
				break;
			}
		}

		$catalog_product = '' !== $slug && function_exists( 'velotok_core_catalog_product' )
			? velotok_core_catalog_product( $slug )
			: null;

		return is_array( $catalog_product ) ? array_merge( $catalog_product, $product ) : $product;
	}

	if ( ! is_scalar( $product ) || '' === trim( (string) $product ) ) {
		return null;
	}

	$value = trim( (string) $product );

	if ( function_exists( 'velotok_core_catalog_product' ) ) {
		$found = velotok_core_catalog_product( $value );

		if ( is_array( $found ) ) {
			return $found;
		}
	}

	if ( function_exists( 'velotok_core_catalog_products' ) ) {
		foreach ( velotok_core_catalog_products() as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}

			foreach ( array( 'slug', 'name', 'id', 'product_id' ) as $field ) {
				if ( isset( $candidate[ $field ] ) && velotok_core_cart_normalize_key( $candidate[ $field ] ) === velotok_core_cart_normalize_key( $value ) ) {
					return $candidate;
				}
			}
		}
	}

	return null;
}

/**
 * Build a cart item from product data.
 *
 * @param array<string,mixed> $product  Product data.
 * @param int                 $quantity Quantity.
 * @return array<string,mixed>|null Null for invalid product data.
 */
function velotok_core_cart_make_item( $product, $quantity = 1 ) {
	if ( ! is_array( $product ) ) {
		return null;
	}

	$name = '';

	foreach ( array( 'name', 'title', 'label' ) as $field ) {
		if ( isset( $product[ $field ] ) && is_scalar( $product[ $field ] ) && '' !== trim( (string) $product[ $field ] ) ) {
			$name = trim( (string) $product[ $field ] );
			break;
		}
	}

	$price = null;

	foreach ( array( 'price', 'regular_price', 'amount' ) as $field ) {
		if ( isset( $product[ $field ] ) && is_numeric( $product[ $field ] ) ) {
			$price = (float) $product[ $field ];
			break;
		}
	}

	if ( '' === $name || null === $price ) {
		return null;
	}

	$slug = '';

	foreach ( array( 'slug', 'product_slug' ) as $field ) {
		if ( isset( $product[ $field ] ) && is_scalar( $product[ $field ] ) && '' !== trim( (string) $product[ $field ] ) ) {
			$slug = trim( (string) $product[ $field ] );
			break;
		}
	}

	$product_id = '';

	foreach ( array( 'product_id', 'id' ) as $field ) {
		if ( isset( $product[ $field ] ) && is_scalar( $product[ $field ] ) && '' !== trim( (string) $product[ $field ] ) ) {
			$product_id = trim( (string) $product[ $field ] );
			break;
		}
	}

	if ( '' === $product_id ) {
		$product_id = '' !== $slug ? $slug : velotok_core_cart_normalize_key( $name );
	}

	$key = '' !== $slug ? $slug : $product_id;

	$item = array(
		'key'         => $key,
		'product_id'  => $product_id,
		'slug'        => '' !== $slug ? $slug : $product_id,
		'name'        => $name,
		'price'       => round( max( 0.0, $price ), 2 ),
		'quantity'    => max( 1, (int) $quantity ),
		'currency'    => isset( $product['currency'] ) ? (string) $product['currency'] : velotok_core_cart_contract()['currency'],
		'line_total'  => 0.0,
		'mocked'      => true,
		'source'      => 'stub',
	);

	foreach ( array( 'category', 'image', 'product_type', 'range_km', 'motor_w', 'weight_kg', 'wheel', 'badge' ) as $field ) {
		if ( isset( $product[ $field ] ) && is_scalar( $product[ $field ] ) ) {
			$item[ $field ] = $product[ $field ];
		}
	}

	return $item;
}

/**
 * Add a product to the cart.
 *
 * Accepts either a cart as the first argument or a product directly. In the
 * short form the function starts a new cart:
 *
 *   velotok_core_cart_add_item( 'riviera', 2 );
 *   velotok_core_cart_add_item( $cart, 'riviera', 2 );
 *
 * Adding an existing product merges the quantities. Unknown products produce a
 * mocked error result and never contact WooCommerce or an external service.
 *
 * @param mixed $cart     Cart array or product data.
 * @param mixed $product  Product slug, identifier, name or data.
 * @param mixed $quantity Quantity or options.
 * @param array $options  Optional item overrides.
 * @return array<string,mixed>
 */
function velotok_core_cart_add_item( $cart = array(), $product = null, $quantity = 1, $options = array() ) {
	$contract = velotok_core_cart_contract();

	if ( ! velotok_core_cart_is_cart( $cart ) ) {
		if ( is_array( $quantity ) && ! is_numeric( $quantity ) ) {
			$options  = $quantity;
			$quantity = 1;
		}

		if ( is_numeric( $product ) ) {
			$quantity = $product;
		} elseif ( is_array( $product ) && isset( $product['quantity'] ) && is_numeric( $product['quantity'] ) ) {
			$quantity = $product['quantity'];
		} else {
			$quantity = 1;
		}

		$product = $cart;
		$cart    = velotok_core_cart_create();
	}

	if ( ! is_array( $options ) ) {
		$options = array();
	}

	if ( function_exists( 'velotok_core_wc_cart_add_item' ) && function_exists( 'velotok_core_wc_cart_available' ) && velotok_core_wc_cart_available() ) {
		$wc_cart = velotok_core_wc_cart_add_item( $product, $quantity, $options );

		if ( null !== $wc_cart ) {
			return $wc_cart;
		}
	}

	$resolved = velotok_core_cart_resolve_product( $product );

	if ( null === $resolved ) {
		$cart['valid']  = false;
		$cart['added']  = false;
		$cart['code']   = $contract['product_not_found'];
		$cart['error']  = $contract['product_not_found'];
		$cart['item']   = null;
		$cart['mocked'] = true;

		return $cart;
	}

	$quantity = is_numeric( $quantity ) ? (int) $quantity : 1;

	if ( $quantity < 1 ) {
		$cart['valid']  = false;
		$cart['added']  = false;
		$cart['code']   = $contract['quantity_invalid'];
		$cart['error']  = $contract['quantity_invalid'];
		$cart['item']   = null;
		$cart['mocked'] = true;

		return $cart;
	}

	$quantity = min( $quantity, (int) $contract['max_quantity'] );
	$item     = velotok_core_cart_make_item( $resolved, $quantity );

	if ( null === $item ) {
		$cart['valid']  = false;
		$cart['added']  = false;
		$cart['code']   = $contract['product_not_found'];
		$cart['error']  = $contract['product_not_found'];
		$cart['item']   = null;
		$cart['mocked'] = true;

		return $cart;
	}

	foreach ( $options as $option_key => $option_value ) {
		if ( is_scalar( $option_value ) ) {
			$item[ $option_key ] = $option_value;
		}
	}

	$items    = velotok_core_cart_items( $cart );
	$existing = null;

	foreach ( $items as $index => $candidate ) {
		if ( velotok_core_cart_item_matches( $candidate, $item['key'] ) || velotok_core_cart_item_matches( $candidate, $item['product_id'] ) ) {
			$existing = $index;
			break;
		}
	}

	if ( null !== $existing ) {
		$items[ $existing ]['quantity'] = min(
			(int) $contract['max_quantity'],
			(int) $items[ $existing ]['quantity'] + $quantity
		);
		$item = $items[ $existing ];
	} else {
		$items[] = $item;
	}

	$cart                = velotok_core_cart_recalculate( $cart );
	$cart['items']       = array_values( $items );
	$cart                = velotok_core_cart_recalculate( $cart );
	$cart['valid']       = true;
	$cart['success']     = true;
	$cart['added']       = true;
	$cart['code']        = null;
	$cart['error']       = null;
	$cart['item']        = $item;
	$cart['last_item']   = $item;

	return $cart;
}

/**
 * Alias for adding a product to a cart.
 *
 * @param mixed $cart     Cart array or product data.
 * @param mixed $product  Product slug, identifier, name or data.
 * @param mixed $quantity Quantity or options.
 * @param array $options  Optional item overrides.
 * @return array<string,mixed>
 */
function velotok_core_cart_add( $cart = array(), $product = null, $quantity = 1, $options = array() ) {
	return velotok_core_cart_add_item( $cart, $product, $quantity, $options );
}

/**
 * Check whether a cart item matches a key or product identifier.
 *
 * @param array<string,mixed> $item  Cart item.
 * @param mixed               $value Key or identifier.
 * @return bool
 */
function velotok_core_cart_item_matches( $item, $value ) {
	if ( ! is_array( $item ) ) {
		return false;
	}

	$needle = velotok_core_cart_normalize_key( $value );

	if ( '' === $needle ) {
		return false;
	}

	foreach ( array( 'key', 'product_id', 'slug', 'name', 'id' ) as $field ) {
		if ( isset( $item[ $field ] ) && velotok_core_cart_normalize_key( $item[ $field ] ) === $needle ) {
			return true;
		}
	}

	return false;
}

/**
 * Return a single cart item by key or product identifier.
 *
 * @param mixed $cart  Cart array.
 * @param mixed $value Key or identifier.
 * @return array<string,mixed>|null
 */
function velotok_core_cart_get_item( $cart, $value ) {
	foreach ( velotok_core_cart_items( $cart ) as $item ) {
		if ( velotok_core_cart_item_matches( $item, $value ) ) {
			return $item;
		}
	}

	return null;
}

/**
 * Alias for a single cart item accessor.
 *
 * @param mixed $cart  Cart array.
 * @param mixed $value Key or identifier.
 * @return array<string,mixed>|null
 */
function velotok_core_cart_item( $cart, $value ) {
	return velotok_core_cart_get_item( $cart, $value );
}

/**
 * Check whether a cart contains a product.
 *
 * @param mixed $cart  Cart array.
 * @param mixed $value Key or identifier.
 * @return bool
 */
function velotok_core_cart_has_item( $cart, $value ) {
	return null !== velotok_core_cart_get_item( $cart, $value );
}

/**
 * Update the quantity of an existing cart item.
 *
 * A zero or negative quantity removes the item, matching common cart UX.
 *
 * @param mixed $cart     Cart array or item key in the short form.
 * @param mixed $value    Item key or identifier.
 * @param mixed $quantity New quantity.
 * @return array<string,mixed>
 */
function velotok_core_cart_update_item( $cart, $value = null, $quantity = 1 ) {
	$contract = velotok_core_cart_contract();

	if ( ! velotok_core_cart_is_cart( $cart ) ) {
		$quantity = $value;
		$value    = $cart;
		$cart     = velotok_core_cart_create();
	}

	if ( is_scalar( $value ) && '' !== (string) $value && function_exists( 'velotok_core_wc_cart_update_item' ) && function_exists( 'velotok_core_wc_cart_available' ) && velotok_core_wc_cart_available() ) {
		$wc_cart = velotok_core_wc_cart_update_item( $value, $quantity );

		if ( null !== $wc_cart ) {
			return $wc_cart;
		}
	}

	$item = velotok_core_cart_get_item( $cart, $value );

	if ( null === $item ) {
		$cart['valid'] = false;
		$cart['updated'] = false;
		$cart['code']  = $contract['item_not_found'];
		$cart['error'] = $contract['item_not_found'];

		return $cart;
	}

	$quantity = is_numeric( $quantity ) ? (int) $quantity : 0;

	if ( $quantity < 1 ) {
		return velotok_core_cart_remove_item( $cart, $value );
	}

	$items = velotok_core_cart_items( $cart );

	foreach ( $items as $index => $candidate ) {
		if ( velotok_core_cart_item_matches( $candidate, $value ) ) {
			$items[ $index ]['quantity'] = min( (int) $contract['max_quantity'], $quantity );
			$item                        = $items[ $index ];
			break;
		}
	}

	$cart              = velotok_core_cart_recalculate( $cart );
	$cart['items']     = array_values( $items );
	$cart              = velotok_core_cart_recalculate( $cart );
	$cart['valid']     = true;
	$cart['success']   = true;
	$cart['updated']   = true;
	$cart['code']      = null;
	$cart['error']     = null;
	$cart['item']      = $item;

	return $cart;
}

/**
 * Alias for updating an existing cart item.
 *
 * @param mixed $cart     Cart array.
 * @param mixed $value    Item key or identifier.
 * @param mixed $quantity New quantity.
 * @return array<string,mixed>
 */
function velotok_core_cart_update( $cart, $value = null, $quantity = 1 ) {
	return velotok_core_cart_update_item( $cart, $value, $quantity );
}

/**
 * Remove an item from the cart.
 *
 * @param mixed $cart  Cart array or item key in the short form.
 * @param mixed $value Item key or identifier.
 * @return array<string,mixed>
 */
function velotok_core_cart_remove_item( $cart, $value = null ) {
	$contract = velotok_core_cart_contract();

	if ( ! velotok_core_cart_is_cart( $cart ) ) {
		$value = $cart;
		$cart  = velotok_core_cart_create();
	}

	if ( is_scalar( $value ) && '' !== (string) $value && function_exists( 'velotok_core_wc_cart_remove_item' ) && function_exists( 'velotok_core_wc_cart_available' ) && velotok_core_wc_cart_available() ) {
		$wc_cart = velotok_core_wc_cart_remove_item( $value );

		if ( null !== $wc_cart ) {
			return $wc_cart;
		}
	}

	$items    = velotok_core_cart_items( $cart );
	$filtered = array();
	$removed  = null;

	foreach ( $items as $item ) {
		if ( null === $removed && velotok_core_cart_item_matches( $item, $value ) ) {
			$removed = $item;
			continue;
		}

		$filtered[] = $item;
	}

	$cart            = velotok_core_cart_recalculate( $cart );
	$cart['items']   = $filtered;
	$cart            = velotok_core_cart_recalculate( $cart );
	$cart['valid']   = null !== $removed;
	$cart['success'] = null !== $removed;
	$cart['removed'] = null !== $removed;
	$cart['code']    = null === $removed ? $contract['item_not_found'] : null;
	$cart['error']   = null === $removed ? $contract['item_not_found'] : null;
	$cart['item']    = $removed;

	return $cart;
}

/**
 * Alias for removing an item from the cart.
 *
 * @param mixed $cart  Cart array.
 * @param mixed $value Item key or identifier.
 * @return array<string,mixed>
 */
function velotok_core_cart_remove( $cart, $value = null ) {
	return velotok_core_cart_remove_item( $cart, $value );
}

/**
 * Return the total quantity of items in the cart.
 *
 * @param mixed $cart Cart array.
 * @return int
 */
function velotok_core_cart_count( $cart = array() ) {
	$items = velotok_core_cart_items( $cart );
	$count = 0;

	foreach ( $items as $item ) {
		$count += isset( $item['quantity'] ) && is_numeric( $item['quantity'] ) ? (int) $item['quantity'] : 0;
	}

	return $count;
}

/**
 * Alias for the item quantity count.
 *
 * @param mixed $cart Cart array.
 * @return int
 */
function velotok_core_cart_item_count( $cart = array() ) {
	return velotok_core_cart_count( $cart );
}

/**
 * Return the number of distinct cart positions.
 *
 * @param mixed $cart Cart array.
 * @return int
 */
function velotok_core_cart_unique_count( $cart = array() ) {
	return count( velotok_core_cart_items( $cart ) );
}

/**
 * Return the cart subtotal.
 *
 * @param mixed $cart Cart array.
 * @return float
 */
function velotok_core_cart_subtotal( $cart = array() ) {
	if ( ( ! velotok_core_cart_is_cart( $cart ) || velotok_core_cart_is_wc_cart( $cart ) ) && function_exists( 'velotok_core_wc_cart_totals' ) && function_exists( 'velotok_core_wc_cart_available' ) && velotok_core_wc_cart_available() ) {
		$wc_totals = velotok_core_wc_cart_totals();

		if ( isset( $wc_totals['subtotal'] ) && is_numeric( $wc_totals['subtotal'] ) ) {
			return (float) $wc_totals['subtotal'];
		}
	}

	$subtotal = 0.0;

	foreach ( velotok_core_cart_items( $cart ) as $item ) {
		$price    = isset( $item['price'] ) && is_numeric( $item['price'] ) ? (float) $item['price'] : 0.0;
		$quantity = isset( $item['quantity'] ) && is_numeric( $item['quantity'] ) ? (int) $item['quantity'] : 0;
		$subtotal += $price * $quantity;
	}

	return round( $subtotal, 2 );
}

/**
 * Return the cart total. Delivery is added later during checkout.
 *
 * @param mixed $cart Cart array.
 * @return float
 */
function velotok_core_cart_total( $cart = array() ) {
	if ( ( ! velotok_core_cart_is_cart( $cart ) || velotok_core_cart_is_wc_cart( $cart ) ) && function_exists( 'velotok_core_wc_cart_totals' ) && function_exists( 'velotok_core_wc_cart_available' ) && velotok_core_wc_cart_available() ) {
		$wc_totals = velotok_core_wc_cart_totals();

		if ( isset( $wc_totals['total'] ) && is_numeric( $wc_totals['total'] ) ) {
			return (float) $wc_totals['total'];
		}
	}

	$totals = velotok_core_cart_totals( $cart );

	return isset( $totals['total'] ) && is_numeric( $totals['total'] ) ? (float) $totals['total'] : velotok_core_cart_subtotal( $cart );
}

/**
 * Build the cart totals summary.
 *
 * @param mixed $cart Cart array.
 * @return array<string,mixed>
 */
function velotok_core_cart_totals( $cart = array() ) {
	if ( ( ! velotok_core_cart_is_cart( $cart ) || velotok_core_cart_is_wc_cart( $cart ) ) && function_exists( 'velotok_core_wc_cart_totals' ) && function_exists( 'velotok_core_wc_cart_available' ) && velotok_core_wc_cart_available() ) {
		return velotok_core_wc_cart_totals();
	}

	$contract   = velotok_core_cart_contract();
	$items      = velotok_core_cart_items( $cart );
	$item_count = velotok_core_cart_count( $cart );
	$subtotal   = velotok_core_cart_subtotal( $cart );
	$discount   = is_array( $cart ) && isset( $cart['discount_total'] ) && is_numeric( $cart['discount_total'] ) ? max( 0.0, (float) $cart['discount_total'] ) : 0.0;
	$shipping   = is_array( $cart ) && isset( $cart['shipping_total'] ) && is_numeric( $cart['shipping_total'] ) ? max( 0.0, (float) $cart['shipping_total'] ) : 0.0;
	$tax        = is_array( $cart ) && isset( $cart['total_tax'] ) && is_numeric( $cart['total_tax'] ) ? max( 0.0, (float) $cart['total_tax'] ) : 0.0;
	$total      = round( $subtotal - $discount + $shipping + $tax, 2 );

	return array(
		'valid'             => true,
		'items'             => $items,
		'item_count'        => $item_count,
		'quantity_count'    => $item_count,
		'unique_item_count' => count( $items ),
		'subtotal'          => $subtotal,
		'discount_total'    => round( $discount, 2 ),
		'shipping_total'    => round( $shipping, 2 ),
		'total_tax'         => round( $tax, 2 ),
		'total'             => $total,
		'currency'          => $contract['currency'],
		'empty'             => 0 === $item_count,
		'include_discounts' => true,
		'include_delivery'  => true,
		'mocked'            => true,
		'source'            => 'stub',
		'provider'          => 'stub',
	);
}

/**
 * Check whether the cart has no items.
 *
 * @param mixed $cart Cart array.
 * @return bool
 */
function velotok_core_cart_is_empty( $cart = array() ) {
	return 0 === velotok_core_cart_count( $cart );
}

/**
 * Check whether the cart has at least one item.
 *
 * @param mixed $cart Cart array.
 * @return bool
 */
function velotok_core_cart_has_items( $cart = array() ) {
	return ! velotok_core_cart_is_empty( $cart );
}

/**
 * Clear the cart.
 *
 * @param mixed $cart Cart array.
 * @return array<string,mixed>
 */
function velotok_core_cart_clear( $cart = array() ) {
	if ( ( ! velotok_core_cart_is_cart( $cart ) || velotok_core_cart_is_wc_cart( $cart ) ) && function_exists( 'velotok_core_wc_cart_clear' ) && function_exists( 'velotok_core_wc_cart_available' ) && velotok_core_wc_cart_available() ) {
		$wc_cart = velotok_core_wc_cart_clear();

		if ( null !== $wc_cart ) {
			return $wc_cart;
		}
	}

	$cart             = velotok_core_cart_recalculate( $cart );
	$cart['items']    = array();
	$cart             = velotok_core_cart_recalculate( $cart );
	$cart['cleared']  = true;
	$cart['valid']    = true;

	return $cart;
}

/**
 * Create a guest cart.
 *
 * @param array<int,array<string,mixed>> $items Optional initial items.
 * @return array<string,mixed>
 */
function velotok_core_cart_guest( $items = array() ) {
	$cart            = velotok_core_cart_create( $items );
	$cart['guest']   = true;
	$cart['user_id'] = 0;

	return $cart;
}

/**
 * Transfer a guest cart into a user cart on login or registration.
 *
 * Quantities of the same product are merged. Both snapshots are arrays, so the
 * caller receives the resulting cart without any database write.
 *
 * @param mixed $guest_cart Guest cart array.
 * @param mixed $user_cart  Existing user cart array.
 * @return array<string,mixed>
 */
function velotok_core_cart_transfer_guest_cart( $guest_cart, $user_cart = array() ) {
	if ( function_exists( 'velotok_core_wc_cart_transfer_guest_cart' ) && function_exists( 'velotok_core_wc_cart_available' ) && velotok_core_wc_cart_available() ) {
		$wc_cart = velotok_core_wc_cart_transfer_guest_cart( $guest_cart );

		if ( null !== $wc_cart ) {
			return $wc_cart;
		}
	}

	$contract = velotok_core_cart_contract();
	$result   = velotok_core_cart_is_cart( $user_cart ) ? $user_cart : velotok_core_cart_create();
	$items    = velotok_core_cart_items( $guest_cart );
	$moved    = 0;

	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$result = velotok_core_cart_add_item(
			$result,
			$item,
			isset( $item['quantity'] ) ? $item['quantity'] : 1
		);
		$moved++;
	}

	$result = velotok_core_cart_recalculate( $result );
	$result['valid']               = true;
	$result['transferred']         = $moved;
	$result['transferred_count']   = $moved;
	$result['transferred_items']   = $moved;
	$result['guest_cleared']       = true;
	$result['transfer_mocked']     = true;
	$result['transfer_strategy']   = $contract['guest_cart']['merge_strategy'];
	$result['guest_cart_transferred'] = true;

	return $result;
}

/**
 * Alias for transferring a guest cart on registration.
 *
 * @param mixed $guest_cart Guest cart array.
 * @param mixed $user_cart  Existing user cart array.
 * @return array<string,mixed>
 */
function velotok_core_cart_transfer_on_registration( $guest_cart, $user_cart = array() ) {
	return velotok_core_cart_transfer_guest_cart( $guest_cart, $user_cart );
}

/**
 * Alias for transferring a guest cart on login.
 *
 * @param mixed $guest_cart Guest cart array.
 * @param mixed $user_cart  Existing user cart array.
 * @return array<string,mixed>
 */
function velotok_core_cart_transfer_on_login( $guest_cart, $user_cart = array() ) {
	return velotok_core_cart_transfer_guest_cart( $guest_cart, $user_cart );
}

/**
 * Alias for merging a guest cart.
 *
 * @param mixed $guest_cart Guest cart array.
 * @param mixed $user_cart  Existing user cart array.
 * @return array<string,mixed>
 */
function velotok_core_cart_merge_guest_cart( $guest_cart, $user_cart = array() ) {
	return velotok_core_cart_transfer_guest_cart( $guest_cart, $user_cart );
}

/**
 * Return the open questions that block a real cart integration.
 *
 * @return array<string,string>
 */
function velotok_core_cart_open_questions() {
	$contract = velotok_core_cart_contract();

	return $contract['open_questions'];
}

/**
 * Render a minimal mocked cart summary for diagnostics and tests.
 *
 * @param mixed $cart Cart array.
 * @return string Empty string for an empty cart.
 */
function velotok_core_render_cart( $cart = array() ) {
	if ( velotok_core_cart_is_empty( $cart ) ) {
		return '';
	}

	$html  = '<div class="velotok-cart" data-mocked="true" data-source="stub">';
	$html .= '<ul class="velotok-cart__items">';

	foreach ( velotok_core_cart_items( $cart ) as $item ) {
		$html .= '<li class="velotok-cart__item" data-product="' . htmlspecialchars( (string) $item['product_id'], ENT_QUOTES, 'UTF-8' ) . '">';
		$html .= '<span class="velotok-cart__name">' . htmlspecialchars( (string) $item['name'], ENT_QUOTES, 'UTF-8' ) . '</span>';
		$html .= '<span class="velotok-cart__quantity">' . htmlspecialchars( (string) $item['quantity'], ENT_QUOTES, 'UTF-8' ) . '</span>';
		$html .= '<span class="velotok-cart__line-total">' . htmlspecialchars( (string) $item['line_total'], ENT_QUOTES, 'UTF-8' ) . '</span>';
		$html .= '</li>';
	}

	$html .= '</ul>';
	$html .= '<div class="velotok-cart__total">' . htmlspecialchars( (string) velotok_core_cart_total( $cart ), ENT_QUOTES, 'UTF-8' ) . ' ' . htmlspecialchars( velotok_core_cart_contract()['currency'], ENT_QUOTES, 'UTF-8' ) . '</div>';
	$html .= '</div>';

	return $html;
}

/**
 * Alias for rendering the cart.
 *
 * @param mixed $cart Cart array.
 * @return string
 */
function velotok_core_cart_html( $cart = array() ) {
	return velotok_core_render_cart( $cart );
}

if ( ! function_exists( 'cartContract' ) ) {
	/**
	 * Short global alias for the cart contract.
	 *
	 * @return array<string,mixed>
	 */
	function cartContract() {
		return velotok_core_cart_contract();
	}
}

if ( ! function_exists( 'cartCreate' ) ) {
	/**
	 * Short global alias for creating an empty cart.
	 *
	 * @return array<string,mixed>
	 */
	function cartCreate() {
		return velotok_core_cart_create();
	}
}

if ( ! function_exists( 'cartItems' ) ) {
	/**
	 * Short global alias for cart items.
	 *
	 * @param mixed $cart Cart array.
	 * @return array<int,array<string,mixed>>
	 */
	function cartItems( $cart = array() ) {
		return velotok_core_cart_items( $cart );
	}
}

if ( ! function_exists( 'cartAddItem' ) ) {
	/**
	 * Short global alias for adding a product to the cart.
	 *
	 * @param mixed $cart     Cart array or product data.
	 * @param mixed $product  Product slug, identifier, name or data.
	 * @param mixed $quantity Quantity.
	 * @return array<string,mixed>
	 */
	function cartAddItem( $cart = array(), $product = null, $quantity = 1 ) {
		return velotok_core_cart_add_item( $cart, $product, $quantity );
	}
}

if ( ! function_exists( 'cartTotals' ) ) {
	/**
	 * Short global alias for cart totals.
	 *
	 * @param mixed $cart Cart array.
	 * @return array<string,mixed>
	 */
	function cartTotals( $cart = array() ) {
		return velotok_core_cart_totals( $cart );
	}
}

if ( ! defined( 'VELOTOK_CART_SKIP_WC_INTEGRATION' ) && ! defined( 'VELOTOK_CART_WC_INTEGRATION_LOADING' ) && is_readable( __DIR__ . '/cart-woocommerce.php' ) ) {
	require_once __DIR__ . '/cart-woocommerce.php';
}
