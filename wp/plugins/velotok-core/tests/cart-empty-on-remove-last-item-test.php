<?php
/**
 * Regression test for TESTEB-14 / spec cart: removing the last position turns
 * the cart into an empty state.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/cart-empty-on-remove-last-item-test.php
 *
 * The test stubs the WooCommerce surface used by the cart integration and
 * proves that:
 * - the cart contract references the requirement and TESTEB-14;
 * - removing the last position empties WC()->cart and all cart totals;
 * - removing one of several positions keeps the remaining positions;
 * - setting the last position quantity to zero removes it and empties the cart;
 * - the guest session snapshot and cookie are cleared so the removed cart is
 *   not resurrected in a new session;
 * - the persisted customer cart snapshot is cleared for an authenticated user.
 *
 * The test fails when removing the last position no longer leaves the cart in
 * an empty state.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_remove_last_test_logged_in'] = false;
$GLOBALS['velotok_remove_last_test_user_id']   = 0;
$GLOBALS['velotok_remove_last_test_notices']   = array();
$GLOBALS['velotok_remove_last_test_actions']   = array();
$GLOBALS['velotok_remove_last_test_filters']   = array();
$GLOBALS['velotok_remove_last_test_meta']      = array();

$_COOKIE = array();

/**
 * Minimal WooCommerce product double.
 */
class Velotok_Remove_Last_Test_Product {
	/**
	 * Product id.
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Product name.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Product price.
	 *
	 * @var float
	 */
	public $price;

	/**
	 * Max purchase quantity.
	 *
	 * @var int
	 */
	public $max;

	/**
	 * Constructor.
	 *
	 * @param int    $id    Id.
	 * @param string $name  Name.
	 * @param float  $price Price.
	 * @param int    $max   Max quantity.
	 */
	public function __construct( $id, $name, $price, $max = 99 ) {
		$this->id    = (int) $id;
		$this->name  = (string) $name;
		$this->price = (float) $price;
		$this->max   = (int) $max;
	}

	/**
	 * Product id.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Product name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Product price.
	 *
	 * @return float
	 */
	public function get_price() {
		return $this->price;
	}

	/**
	 * Product slug.
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'product-' . $this->id;
	}

	/**
	 * Max purchase quantity.
	 *
	 * @return int
	 */
	public function get_max_purchase_quantity() {
		return $this->max;
	}
}

/**
 * Minimal WooCommerce session double.
 */
class Velotok_Remove_Last_Test_Session {
	/**
	 * Session data.
	 *
	 * @var array
	 */
	public $data = array();

	/**
	 * Read a session value.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default;
	}

	/**
	 * Store a session value.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function set( $key, $value ) {
		$this->data[ $key ] = $value;
	}

	/**
	 * Delete a session value.
	 *
	 * @param string $key Key.
	 * @return void
	 */
	public function delete( $key ) {
		unset( $this->data[ $key ] );
	}
}

/**
 * Minimal WooCommerce cart double backed by an in-memory array.
 */
class Velotok_Remove_Last_Test_WC_Cart {
	/**
	 * Cart contents.
	 *
	 * @var array
	 */
	public $contents = array();

	/**
	 * Cart item sequence.
	 *
	 * @var int
	 */
	private $sequence = 0;

	/**
	 * Cart contents.
	 *
	 * @return array
	 */
	public function get_cart() {
		return $this->contents;
	}

	/**
	 * Whether the cart is empty.
	 *
	 * @return bool
	 */
	public function is_empty() {
		return empty( $this->contents );
	}

	/**
	 * Total quantity in the cart.
	 *
	 * @return int
	 */
	public function get_cart_contents_count() {
		$count = 0;

		foreach ( $this->contents as $line ) {
			$count += isset( $line['quantity'] ) ? (int) $line['quantity'] : 0;
		}

		return $count;
	}

	/**
	 * Add a product to the cart.
	 *
	 * @param int   $product_id   Product id.
	 * @param int   $quantity     Quantity.
	 * @param int   $variation_id Variation id.
	 * @param array $variation    Variation data.
	 * @param array $extra        Extra data.
	 * @return string|false
	 */
	public function add_to_cart( $product_id, $quantity = 1, $variation_id = 0, $variation = array(), $extra = array() ) {
		unset( $variation, $extra );

		$product = wc_get_product( $product_id );

		if ( ! is_object( $product ) ) {
			return false;
		}

		foreach ( $this->contents as $key => $line ) {
			if ( (int) $line['product_id'] === (int) $product_id && (int) $line['variation_id'] === (int) $variation_id ) {
				$this->contents[ $key ]['quantity']  = (int) $line['quantity'] + (int) $quantity;
				$this->contents[ $key ]['line_total'] = round( $product->get_price() * $this->contents[ $key ]['quantity'], 2 );

				return $key;
			}
		}

		$this->sequence++;
		$key = 'cart_' . $this->sequence;

		$this->contents[ $key ] = array(
			'key'          => $key,
			'product_id'   => (int) $product_id,
			'variation_id' => (int) $variation_id,
			'quantity'     => (int) $quantity,
			'data'         => $product,
			'line_total'   => round( $product->get_price() * (int) $quantity, 2 ),
		);

		return $key;
	}

	/**
	 * Update a cart position quantity.
	 *
	 * @param string $key      Cart item key.
	 * @param int    $quantity Quantity.
	 * @param bool   $refresh  Refresh totals.
	 * @return bool
	 */
	public function set_quantity( $key, $quantity = 1, $refresh = true ) {
		unset( $refresh );

		if ( ! isset( $this->contents[ $key ] ) ) {
			return false;
		}

		$quantity = (int) $quantity;

		if ( $quantity < 1 ) {
			return $this->remove_cart_item( $key );
		}

		$product = $this->contents[ $key ]['data'];
		$this->contents[ $key ]['quantity']  = $quantity;
		$this->contents[ $key ]['line_total'] = round( $product->get_price() * $quantity, 2 );

		return true;
	}

	/**
	 * Remove a cart position.
	 *
	 * @param string $key Cart item key.
	 * @return bool
	 */
	public function remove_cart_item( $key ) {
		if ( ! isset( $this->contents[ $key ] ) ) {
			return false;
		}

		unset( $this->contents[ $key ] );

		return true;
	}

	/**
	 * Empty the cart.
	 *
	 * @return void
	 */
	public function empty_cart() {
		$this->contents = array();
	}

	/**
	 * Read a single cart item.
	 *
	 * @param string $key Cart item key.
	 * @return array|false
	 */
	public function get_cart_item( $key ) {
		return isset( $this->contents[ $key ] ) ? $this->contents[ $key ] : false;
	}

	/**
	 * Recalculate totals.
	 *
	 * @return void
	 */
	public function calculate_totals() {
	}

	/**
	 * Cart subtotal.
	 *
	 * @return float
	 */
	public function get_subtotal() {
		$subtotal = 0.0;

		foreach ( $this->contents as $line ) {
			$subtotal += (float) $line['line_total'];
		}

		return $subtotal;
	}

	/**
	 * Discount total.
	 *
	 * @return float
	 */
	public function get_discount_total() {
		return 0.0;
	}

	/**
	 * Discount tax.
	 *
	 * @return float
	 */
	public function get_discount_tax() {
		return 0.0;
	}

	/**
	 * Shipping total.
	 *
	 * @return float
	 */
	public function get_shipping_total() {
		return 0.0;
	}

	/**
	 * Shipping tax.
	 *
	 * @return float
	 */
	public function get_shipping_tax() {
		return 0.0;
	}

	/**
	 * Tax total.
	 *
	 * @return float
	 */
	public function get_total_tax() {
		return 0.0;
	}

	/**
	 * Cart grand total.
	 *
	 * @return float
	 */
	public function get_total() {
		return $this->get_subtotal();
	}
}

/**
 * Minimal WooCommerce runtime double.
 */
class Velotok_Remove_Last_Test_WooCommerce {
	/**
	 * Cart.
	 *
	 * @var Velotok_Remove_Last_Test_WC_Cart
	 */
	public $cart;

	/**
	 * Session.
	 *
	 * @var Velotok_Remove_Last_Test_Session
	 */
	public $session;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cart    = new Velotok_Remove_Last_Test_WC_Cart();
		$this->session = new Velotok_Remove_Last_Test_Session();
	}
}

/**
 * Product fixtures.
 *
 * @return array<int,Velotok_Remove_Last_Test_Product>
 */
function velotok_remove_last_test_products() {
	static $products = null;

	if ( null === $products ) {
		$products = array(
			101 => new Velotok_Remove_Last_Test_Product( 101, 'Riviera', 1000.0, 99 ),
			102 => new Velotok_Remove_Last_Test_Product( 102, 'Street 7', 500.0, 5 ),
		);
	}

	return $products;
}

/**
 * WooCommerce product accessor.
 *
 * @param int $product_id Product id.
 * @return Velotok_Remove_Last_Test_Product|false
 */
function wc_get_product( $product_id ) {
	$products = velotok_remove_last_test_products();

	return isset( $products[ (int) $product_id ] ) ? $products[ (int) $product_id ] : false;
}

/**
 * WooCommerce SKU accessor.
 *
 * @param string $sku SKU.
 * @return int
 */
function wc_get_product_id_by_sku( $sku ) {
	unset( $sku );

	return 0;
}

/**
 * WooCommerce currency accessor.
 *
 * @return string
 */
function get_woocommerce_currency() {
	return 'RUB';
}

/**
 * Current blog id.
 *
 * @return int
 */
function get_current_blog_id() {
	return 1;
}

/**
 * WooCommerce runtime factory.
 *
 * @return Velotok_Remove_Last_Test_WooCommerce
 */
function WC() {
	if ( ! isset( $GLOBALS['velotok_remove_last_test_wc'] ) ) {
		$GLOBALS['velotok_remove_last_test_wc'] = new Velotok_Remove_Last_Test_WooCommerce();
	}

	return $GLOBALS['velotok_remove_last_test_wc'];
}

/**
 * Reset the WooCommerce runtime, customer and guest persistence.
 *
 * @return void
 */
function velotok_remove_last_test_reset() {
	$GLOBALS['velotok_remove_last_test_wc']        = new Velotok_Remove_Last_Test_WooCommerce();
	$GLOBALS['velotok_remove_last_test_logged_in'] = false;
	$GLOBALS['velotok_remove_last_test_user_id']   = 0;
	$GLOBALS['velotok_remove_last_test_meta']      = array();
	$GLOBALS['velotok_remove_last_test_notices']   = array();

	$_COOKIE = array();
}

/**
 * Start a new WooCommerce session without touching the long-lived cookie.
 *
 * @return void
 */
function velotok_remove_last_test_new_session() {
	$GLOBALS['velotok_remove_last_test_wc'] = new Velotok_Remove_Last_Test_WooCommerce();
}

/**
 * Current login state.
 *
 * @return bool
 */
function is_user_logged_in() {
	return (bool) $GLOBALS['velotok_remove_last_test_logged_in'];
}

/**
 * Current user id.
 *
 * @return int
 */
function get_current_user_id() {
	return (int) $GLOBALS['velotok_remove_last_test_user_id'];
}

/**
 * Read user meta.
 *
 * @param int    $user_id User id.
 * @param string $key     Meta key.
 * @param bool   $single  Single value.
 * @return mixed
 */
function get_user_meta( $user_id, $key, $single = false ) {
	unset( $single );

	$user_id = (int) $user_id;

	return isset( $GLOBALS['velotok_remove_last_test_meta'][ $user_id ][ $key ] )
		? $GLOBALS['velotok_remove_last_test_meta'][ $user_id ][ $key ]
		: '';
}

/**
 * Store user meta.
 *
 * @param int    $user_id User id.
 * @param string $key     Meta key.
 * @param mixed  $value   Value.
 * @return bool
 */
function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['velotok_remove_last_test_meta'][ (int) $user_id ][ $key ] = $value;

	return true;
}

/**
 * Delete user meta.
 *
 * @param int    $user_id User id.
 * @param string $key     Meta key.
 * @return bool
 */
function delete_user_meta( $user_id, $key ) {
	$user_id = (int) $user_id;

	if ( ! isset( $GLOBALS['velotok_remove_last_test_meta'][ $user_id ][ $key ] ) ) {
		return false;
	}

	unset( $GLOBALS['velotok_remove_last_test_meta'][ $user_id ][ $key ] );

	return true;
}

/**
 * Record an action.
 *
 * @param string $hook     Hook.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $args     Arguments.
 * @return bool
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	unset( $priority, $args );

	$GLOBALS['velotok_remove_last_test_actions'][ $hook ][] = $callback;

	return true;
}

/**
 * Record a filter.
 *
 * @param string $hook     Hook.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $args     Arguments.
 * @return bool
 */
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	unset( $priority, $args );

	$GLOBALS['velotok_remove_last_test_filters'][ $hook ][] = $callback;

	return true;
}

/**
 * Apply filters.
 *
 * @param string $hook  Hook.
 * @param mixed  $value Value.
 * @return mixed
 */
function apply_filters( $hook, $value ) {
	unset( $hook );

	return $value;
}

/**
 * Record a WooCommerce notice.
 *
 * @param string $message Message.
 * @param string $type    Type.
 * @return void
 */
function wc_add_notice( $message, $type = 'success' ) {
	$GLOBALS['velotok_remove_last_test_notices'][] = array(
		'message' => (string) $message,
		'type'    => (string) $type,
	);
}

$module_path = getenv( 'VELOTOK_CART_WC_INTEGRATION' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__ ) . '/includes/cart-woocommerce.php';
}

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найдена интеграция корзины с WooCommerce.' . PHP_EOL );
	exit( 1 );
}

define( 'VELOTOK_CART_SKIP_WC_INTEGRATION', true );

require_once dirname( __DIR__ ) . '/includes/cart.php';
require_once $module_path;

$failures = array();

/**
 * Assert an empty-on-remove condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_remove_last_test_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Find a normalized cart item by product id.
 *
 * @param int $product_id Product id.
 * @return array<string,mixed>|null
 */
function velotok_remove_last_test_item( $product_id ) {
	foreach ( velotok_core_wc_cart_items() as $item ) {
		if ( (int) $item['product_id'] === (int) $product_id ) {
			return $item;
		}
	}

	return null;
}

$riviera = array(
	'product_id' => 101,
	'name'       => 'Riviera',
	'price'      => 1000.0,
);

$street = array(
	'product_id' => 102,
	'name'       => 'Street 7',
	'price'      => 500.0,
);

$contract = velotok_core_cart_woocommerce_contract();

velotok_remove_last_test_assert( isset( $contract['requirements']['empty_on_remove'] ) && 'Удаление последней позиции SHALL переводить корзину в пустое состояние' === $contract['requirements']['empty_on_remove'], 'Контракт корзины должен объявлять требование пустой корзины после удаления последней позиции.' );
velotok_remove_last_test_assert( isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-14', $contract['requirement_ids'], true ), 'Контракт корзины должен ссылаться на TESTEB-14.' );

$cart_contract = velotok_core_cart_contract();

velotok_remove_last_test_assert( isset( $cart_contract['requirements']['empty_on_remove'] ) && 'Удаление последней позиции SHALL переводить корзину в пустое состояние' === $cart_contract['requirements']['empty_on_remove'], 'Контракт cart.php должен объявлять требование пустой корзины после удаления последней позиции.' );
velotok_remove_last_test_assert( isset( $cart_contract['requirement_ids'] ) && in_array( 'TESTEB-14', $cart_contract['requirement_ids'], true ), 'Контракт cart.php должен ссылаться на TESTEB-14.' );

// Scenario 1: removing the only position empties the WooCommerce cart.
velotok_remove_last_test_reset();
velotok_core_wc_cart_add_item( $riviera, 2 );

$item = velotok_remove_last_test_item( 101 );

velotok_remove_last_test_assert( is_array( $item ), 'Перед удалением позиция Riviera должна быть в корзине.' );
velotok_remove_last_test_assert( 2 === WC()->cart->get_cart_contents_count(), 'Перед удалением в WC()->cart должно быть две единицы.' );

$removed = velotok_core_wc_cart_remove_item( $item['key'] );

velotok_remove_last_test_assert( is_array( $removed ) && ! empty( $removed['removed'] ), 'Последняя позиция должна удаляться.' );
velotok_remove_last_test_assert( is_array( $removed ) && true === $removed['empty'], 'Результат удаления последней позиции должен сообщать пустую корзину.' );
velotok_remove_last_test_assert( is_array( $removed ) && 0 === (int) $removed['item_count'], 'В пустой корзине не должно оставаться единиц товара.' );
velotok_remove_last_test_assert( is_array( $removed ) && 0 === (int) $removed['unique_item_count'], 'В пустой корзине не должно оставаться позиций.' );
velotok_remove_last_test_assert( is_array( $removed ) && 0.0 === (float) $removed['subtotal'], 'Подытог пустой корзины должен быть нулевым.' );
velotok_remove_last_test_assert( is_array( $removed ) && 0.0 === (float) $removed['total'], 'Итог пустой корзины должен быть нулевым.' );
velotok_remove_last_test_assert( WC()->cart->is_empty(), 'WC()->cart должен быть пустым после удаления последней позиции.' );
velotok_remove_last_test_assert( 0 === WC()->cart->get_cart_contents_count(), 'В WC()->cart не должно оставаться единиц товара.' );
velotok_remove_last_test_assert( array() === velotok_core_wc_cart_items(), 'Интеграция не должна возвращать удалённые позиции.' );

$totals = velotok_core_wc_cart_totals();

velotok_remove_last_test_assert( true === $totals['empty'], 'Итоги после удаления последней позиции должны быть пустыми.' );
velotok_remove_last_test_assert( 0 === (int) $totals['item_count'], 'Итоги не должны содержать единиц товара.' );
velotok_remove_last_test_assert( true === velotok_core_cart_is_empty(), 'Общий helper корзины должен сообщать пустую корзину.' );

// Scenario 2: removing one of several positions keeps the other positions.
velotok_remove_last_test_reset();
velotok_core_wc_cart_add_item( $riviera, 1 );
velotok_core_wc_cart_add_item( $street, 2 );

$riviera_item = velotok_remove_last_test_item( 101 );
$removed      = velotok_core_cart_remove_item( $riviera_item['key'] );

velotok_remove_last_test_assert( is_array( $removed ) && true === $removed['removed'], 'Позиция должна удаляться через общий helper корзины.' );
velotok_remove_last_test_assert( false === $removed['empty'], 'Корзина не должна становиться пустой, пока в ней есть другие позиции.' );
velotok_remove_last_test_assert( 2 === (int) $removed['item_count'], 'Оставшаяся позиция должна сохранить своё количество.' );
velotok_remove_last_test_assert( false === WC()->cart->is_empty(), 'WC()->cart не должен становиться пустым при удалении не последней позиции.' );
velotok_remove_last_test_assert( null === velotok_remove_last_test_item( 101 ), 'Удалённая позиция не должна оставаться в корзине.' );
velotok_remove_last_test_assert( is_array( velotok_remove_last_test_item( 102 ) ), 'Оставшаяся позиция должна остаться в корзине.' );

// Scenario 3: setting the last position quantity to zero removes it.
velotok_remove_last_test_reset();
velotok_core_wc_cart_add_item( $riviera, 3 );

$item    = velotok_remove_last_test_item( 101 );
$updated = velotok_core_wc_cart_update_item( $item['key'], 0 );

velotok_remove_last_test_assert( is_array( $updated ) && ! empty( $updated['removed'] ), 'Нулевое количество должно удалять последнюю позицию.' );
velotok_remove_last_test_assert( true === $updated['empty'], 'Удаление последней позиции через нулевое количество должно оставлять пустую корзину.' );
velotok_remove_last_test_assert( WC()->cart->is_empty(), 'WC()->cart должен быть пустым после обнуления последней позиции.' );
velotok_remove_last_test_assert( 0 === (int) $updated['item_count'], 'После обнуления последней позиции не должно оставаться единиц товара.' );

// Scenario 4: removing the last guest position clears the persisted snapshot.
velotok_remove_last_test_reset();
velotok_core_wc_cart_add_item( $riviera, 2 );
velotok_core_wc_cart_store_guest_snapshot();

$cookie_name = velotok_core_wc_cart_guest_cookie_name();
$item        = velotok_remove_last_test_item( 101 );

velotok_remove_last_test_assert( is_array( WC()->session->get( 'velotok_guest_cart' ) ), 'Перед удалением гостевой снимок должен существовать в сессии.' );
velotok_remove_last_test_assert( isset( $_COOKIE[ $cookie_name ] ), 'Перед удалением гостевая cookie должна существовать.' );

velotok_core_wc_cart_remove_item( $item['key'] );

$guest_snapshot = WC()->session->get( 'velotok_guest_cart' );

velotok_remove_last_test_assert( null === $guest_snapshot || ( is_array( $guest_snapshot ) && empty( $guest_snapshot['items'] ) ), 'Удаление последней позиции должно очищать гостевой снимок сессии.' );
velotok_remove_last_test_assert( ! isset( $_COOKIE[ $cookie_name ] ), 'Удаление последней позиции должно удалять долгоживущую гостевую cookie.' );

velotok_core_wc_cart_store_guest_snapshot();

$empty_snapshot = WC()->session->get( 'velotok_guest_cart' );

velotok_remove_last_test_assert( is_array( $empty_snapshot ) && empty( $empty_snapshot['items'] ), 'Пустая гостевая корзина должна сохраняться пустой.' );
velotok_remove_last_test_assert( ! isset( $_COOKIE[ $cookie_name ] ), 'Пустая гостевая корзина не должна воссоздавать cookie.' );

velotok_remove_last_test_new_session();

velotok_remove_last_test_assert( false === velotok_core_wc_cart_restore_guest_snapshot(), 'Удалённая последняя позиция не должна восстанавливаться в новой сессии.' );
velotok_remove_last_test_assert( WC()->cart->is_empty(), 'Новая сессия должна начинать с пустой корзины.' );

// Scenario 5: removing the last customer position clears the user snapshot.
velotok_remove_last_test_reset();

$GLOBALS['velotok_remove_last_test_logged_in'] = true;
$GLOBALS['velotok_remove_last_test_user_id']   = 7;

velotok_core_wc_cart_add_item( $street, 1 );
velotok_core_wc_cart_store_user_snapshot( 7 );

$meta_key = velotok_core_wc_cart_user_meta_key( 7 );

velotok_remove_last_test_assert( ! empty( $GLOBALS['velotok_remove_last_test_meta'][7][ $meta_key ] ), 'Перед удалением корзина пользователя должна быть сохранена.' );

$item = velotok_remove_last_test_item( 102 );

velotok_core_wc_cart_remove_item( $item['key'] );

velotok_remove_last_test_assert( WC()->cart->is_empty(), 'Корзина пользователя должна стать пустой после удаления последней позиции.' );
velotok_remove_last_test_assert( empty( $GLOBALS['velotok_remove_last_test_meta'][7] ), 'Удаление последней позиции должно очищать сохранённую корзину пользователя.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок пустой корзины после удаления последней позиции не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: удаление последней позиции переводит корзину в пустое состояние (TESTEB-14).' . PHP_EOL;
exit( 0 );
