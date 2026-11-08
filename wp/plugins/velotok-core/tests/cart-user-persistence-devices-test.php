<?php
/**
 * Regression test for TESTEB-10 / spec cart: the customer cart is persisted
 * between sessions and devices.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/cart-user-persistence-devices-test.php
 *
 * The test stubs the WooCommerce surface used by the cart integration and
 * proves that:
 * - the cart of an authenticated customer is stored in WooCommerce persistent
 *   cart user meta;
 * - a completely new session (another device) restores the same positions;
 * - the restored cart keeps quantities and does not duplicate products;
 * - the per-position quantity limit is applied when the cart is restored;
 * - carts of different customers stay isolated;
 * - an emptied cart removes the persisted snapshot so it cannot be resurrected;
 * - guests never persist a customer cart.
 *
 * The test fails when the customer cart stops surviving a new session/device.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_user_test_logged_in'] = false;
$GLOBALS['velotok_user_test_user_id']   = 0;
$GLOBALS['velotok_user_test_actions']   = array();
$GLOBALS['velotok_user_test_meta']      = array();

/**
 * Minimal WooCommerce product double.
 */
class Velotok_User_Test_Product {
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
class Velotok_User_Test_Session {
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
class Velotok_User_Test_WC_Cart {
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
class Velotok_User_Test_WooCommerce {
	/**
	 * Cart.
	 *
	 * @var Velotok_User_Test_WC_Cart
	 */
	public $cart;

	/**
	 * Session.
	 *
	 * @var Velotok_User_Test_Session
	 */
	public $session;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cart    = new Velotok_User_Test_WC_Cart();
		$this->session = new Velotok_User_Test_Session();
	}
}

/**
 * Product fixtures.
 *
 * @return array<int,Velotok_User_Test_Product>
 */
function velotok_user_test_products() {
	static $products = null;

	if ( null === $products ) {
		$products = array(
			101 => new Velotok_User_Test_Product( 101, 'Riviera', 1000.0, 99 ),
			102 => new Velotok_User_Test_Product( 102, 'Street 7', 500.0, 5 ),
		);
	}

	return $products;
}

/**
 * WooCommerce product accessor.
 *
 * @param int $product_id Product id.
 * @return Velotok_User_Test_Product|false
 */
function wc_get_product( $product_id ) {
	$products = velotok_user_test_products();

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
 * WooCommerce runtime factory.
 *
 * @return Velotok_User_Test_WooCommerce
 */
function WC() {
	if ( ! isset( $GLOBALS['velotok_user_test_wc'] ) ) {
		$GLOBALS['velotok_user_test_wc'] = new Velotok_User_Test_WooCommerce();
	}

	return $GLOBALS['velotok_user_test_wc'];
}

/**
 * Start a completely new device: new cart, new session, but keep user meta.
 *
 * @return void
 */
function velotok_user_test_new_device() {
	$GLOBALS['velotok_user_test_wc'] = new Velotok_User_Test_WooCommerce();
}

/**
 * Reset the customer cart meta and start a new device.
 *
 * @return void
 */
function velotok_user_test_reset_all() {
	$GLOBALS['velotok_user_test_meta'] = array();
	velotok_user_test_new_device();
}

/**
 * Current login state.
 *
 * @return bool
 */
function is_user_logged_in() {
	return (bool) $GLOBALS['velotok_user_test_logged_in'];
}

/**
 * Current user id.
 *
 * @return int
 */
function get_current_user_id() {
	return (int) $GLOBALS['velotok_user_test_user_id'];
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

	return isset( $GLOBALS['velotok_user_test_meta'][ $user_id ][ $key ] )
		? $GLOBALS['velotok_user_test_meta'][ $user_id ][ $key ]
		: '';
}

/**
 * Write user meta.
 *
 * @param int    $user_id User id.
 * @param string $key     Meta key.
 * @param mixed  $value   Value.
 * @return bool
 */
function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['velotok_user_test_meta'][ (int) $user_id ][ $key ] = $value;

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

	if ( ! isset( $GLOBALS['velotok_user_test_meta'][ $user_id ][ $key ] ) ) {
		return false;
	}

	unset( $GLOBALS['velotok_user_test_meta'][ $user_id ][ $key ] );

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

	$GLOBALS['velotok_user_test_actions'][ $hook ][] = $callback;

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
	unset( $hook, $callback, $priority, $args );

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
	unset( $message, $type );
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
 * Assert a customer persistence condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_user_test_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Assert that a hook is registered for customer cart persistence.
 *
 * @param string $hook     Hook.
 * @param string $callback Callback.
 * @param string $message  Failure message.
 * @return void
 */
function velotok_user_test_hooked( $hook, $callback, $message ) {
	velotok_user_test_assert(
		isset( $GLOBALS['velotok_user_test_actions'][ $hook ] )
			&& in_array( $callback, $GLOBALS['velotok_user_test_actions'][ $hook ], true ),
		$message
	);
}

/**
 * Find a normalized cart item by product id.
 *
 * @param int $product_id Product id.
 * @return array<string,mixed>|null
 */
function velotok_user_test_item( $product_id ) {
	foreach ( velotok_core_wc_cart_items() as $item ) {
		if ( (int) $item['product_id'] === (int) $product_id ) {
			return $item;
		}
	}

	return null;
}

$contract = velotok_core_cart_woocommerce_contract();

velotok_user_test_assert( true === $contract['storage']['user_persistent'], 'Корзина пользователя должна сохраняться между устройствами.' );
velotok_user_test_assert( 'woocommerce_persistent_cart' === $contract['storage']['user_storage'], 'Корзина пользователя должна храниться в persistent cart WooCommerce.' );

$operations = $contract['operations'];

velotok_user_test_assert( isset( $operations['persist_user_cart'] ) && 'velotok_core_wc_cart_store_user_snapshot' === $operations['persist_user_cart'], 'В контракте должна быть операция сохранения корзины пользователя.' );
velotok_user_test_assert( isset( $operations['restore_user_cart'] ) && 'velotok_core_wc_cart_restore_user_snapshot' === $operations['restore_user_cart'], 'В контракте должна быть операция восстановления корзины пользователя.' );

velotok_user_test_hooked( 'woocommerce_cart_updated', 'velotok_core_wc_cart_store_user_on_cart_update', 'Изменение корзины должно сохранять корзину пользователя.' );
velotok_user_test_hooked( 'woocommerce_cart_loaded_from_session', 'velotok_core_wc_cart_restore_user_on_session', 'Загрузка сессии должна восстанавливать корзину пользователя.' );
velotok_user_test_hooked( 'wp_login', 'velotok_core_wc_cart_restore_user_on_login', 'Вход должен восстанавливать корзину пользователя.' );

$meta_key = velotok_core_wc_cart_user_meta_key( 7 );

// Scenario 1: the customer cart is written to persistent cart user meta.
velotok_user_test_reset_all();
$GLOBALS['velotok_user_test_logged_in'] = true;
$GLOBALS['velotok_user_test_user_id']   = 7;

velotok_core_cart_add_item( 101, 2 );
velotok_core_cart_add_item( 102, 1 );

velotok_user_test_assert( true === velotok_core_wc_cart_store_user_snapshot(), 'Корзина авторизованного пользователя должна сохраняться.' );
velotok_user_test_assert( isset( $GLOBALS['velotok_user_test_meta'][7][ $meta_key ] ), 'Корзина должна лежать в persistent cart user meta.' );

$stored = velotok_core_wc_cart_user_snapshot( 7 );

$stored_quantity = 0;

foreach ( $stored as $stored_item ) {
	$stored_quantity += (int) $stored_item['quantity'];
}

velotok_user_test_assert( 2 === count( $stored ), 'В сохранённой корзине должно быть две позиции.' );
velotok_user_test_assert( 3 === $stored_quantity, 'В сохранённой корзине должно быть три единицы.' );

// Scenario 2: a new device restores the same cart from user meta.
velotok_user_test_new_device();

velotok_user_test_assert( WC()->cart->is_empty(), 'Новое устройство должно начинать с пустой корзины.' );
velotok_user_test_assert( true === velotok_core_wc_cart_restore_user_snapshot(), 'Корзина должна восстанавливаться на новом устройстве.' );
velotok_user_test_assert( 3 === WC()->cart->get_cart_contents_count(), 'На новом устройстве должны появиться три единицы.' );
velotok_user_test_assert( 2 === count( velotok_core_wc_cart_items() ), 'Одинаковые товары не должны дублироваться.' );

$riviera = velotok_user_test_item( 101 );
$street  = velotok_user_test_item( 102 );

velotok_user_test_assert( is_array( $riviera ) && 2 === (int) $riviera['quantity'], 'Riviera должна восстановиться с количеством 2.' );
velotok_user_test_assert( is_array( $street ) && 1 === (int) $street['quantity'], 'Street 7 должна восстановиться с количеством 1.' );

// Scenario 3: a changed customer cart is visible from the next device.
velotok_core_cart_update_item( $riviera['key'], 4 );
velotok_core_wc_cart_store_user_snapshot();

velotok_user_test_new_device();

velotok_user_test_assert( true === velotok_core_wc_cart_restore_user_snapshot(), 'Изменённая корзина должна восстанавливаться на следующем устройстве.' );

$riviera = velotok_user_test_item( 101 );

velotok_user_test_assert( is_array( $riviera ) && 4 === (int) $riviera['quantity'], 'Изменённое количество должно сохраняться между устройствами.' );
velotok_user_test_assert( 5 === WC()->cart->get_cart_contents_count(), 'Изменённая корзина должна содержать пять единиц.' );

// Scenario 4: carts of different customers stay isolated.
velotok_user_test_new_device();
$GLOBALS['velotok_user_test_user_id'] = 8;

velotok_user_test_assert( false === velotok_core_wc_cart_restore_user_snapshot(), 'Другой пользователь не должен получать чужую корзину.' );
velotok_user_test_assert( WC()->cart->is_empty(), 'Корзина другого пользователя должна оставаться пустой.' );

$GLOBALS['velotok_user_test_user_id'] = 7;

// Scenario 5: the per-position quantity limit is applied on restore.
velotok_user_test_reset_all();
velotok_core_wc_cart_store_user_snapshot();

$GLOBALS['velotok_user_test_meta'][7][ $meta_key ] = velotok_core_wc_cart_user_payload(
	array(
		array(
			'product_id' => 102,
			'quantity'   => 10,
			'line_total' => 5000.0,
		),
	)
);

velotok_user_test_new_device();

velotok_user_test_assert( true === velotok_core_wc_cart_restore_user_snapshot(), 'Корзина должна восстанавливаться из снимка с большим количеством.' );

$street = velotok_user_test_item( 102 );

velotok_user_test_assert( is_array( $street ) && 5 === (int) $street['quantity'], 'При восстановлении количество должно ограничиваться максимумом позиции.' );
velotok_user_test_assert( 5 === WC()->cart->get_cart_contents_count(), 'Ограничение должно применяться в WC()->cart.' );

// Scenario 6: an emptied cart removes the persisted customer cart.
velotok_user_test_new_device();
velotok_core_wc_cart_add_item( 101, 2 );
velotok_core_wc_cart_store_user_snapshot();
velotok_user_test_assert( isset( $GLOBALS['velotok_user_test_meta'][7][ $meta_key ] ), 'Перед очисткой снимок должен существовать.' );

velotok_core_wc_cart_clear();
velotok_core_wc_cart_store_user_snapshot();

velotok_user_test_assert( ! isset( $GLOBALS['velotok_user_test_meta'][7][ $meta_key ] ), 'Пустая корзина должна удалять снимок пользователя.' );

velotok_user_test_new_device();

velotok_user_test_assert( false === velotok_core_wc_cart_restore_user_snapshot(), 'Удалённая корзина не должна восстанавливаться.' );
velotok_user_test_assert( WC()->cart->is_empty(), 'Новое устройство должно начинаться с пустой корзины.' );

// Scenario 7: guests never persist a customer cart automatically.
velotok_user_test_reset_all();
$GLOBALS['velotok_user_test_logged_in'] = false;
$GLOBALS['velotok_user_test_user_id']   = 0;

velotok_core_cart_add_item( 101, 2 );

velotok_user_test_assert( false === velotok_core_wc_cart_store_user_on_cart_update(), 'Гость не должен сохранять корзину пользователя.' );
velotok_user_test_assert( empty( $GLOBALS['velotok_user_test_meta'] ), 'Для гостя user meta не должна создаваться.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок сохранения корзины пользователя между сессиями и устройствами не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: корзина авторизованного пользователя сохраняется между сессиями и устройствами (TESTEB-10).' . PHP_EOL;
exit( 0 );
