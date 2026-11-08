<?php
/**
 * Regression test for TESTEB-9 / spec cart: a guest cart is persisted between
 * sessions.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/cart-guest-persistence-sessions-test.php
 *
 * The test stubs the WooCommerce surface used by the cart integration and
 * proves that:
 * - a guest cart is stored in the WooCommerce session and mirrored into a
 *   long-lived cookie;
 * - a visitor who returns with a fresh session gets the guest cart back from
 *   that cookie;
 * - the same-session snapshot still restores the cart when the session cart is
 *   empty;
 * - logged-in customers do not get a guest snapshot;
 * - an emptied cart clears the snapshot so it cannot be resurrected;
 * - the transfer on login clears both the session snapshot and the cookie;
 * - the per-position quantity limit is applied when the cart is restored.
 *
 * The test fails when the guest cart stops surviving a new session (for
 * example when the cookie mirror is not written or is not read back).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_persist_test_logged_in'] = false;
$GLOBALS['velotok_persist_test_user_id']   = 0;
$GLOBALS['velotok_persist_test_actions']   = array();

/**
 * Minimal WooCommerce product double.
 */
class Velotok_Persist_Test_Product {
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
class Velotok_Persist_Test_Session {
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
class Velotok_Persist_Test_WC_Cart {
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
class Velotok_Persist_Test_WooCommerce {
	/**
	 * Cart.
	 *
	 * @var Velotok_Persist_Test_WC_Cart
	 */
	public $cart;

	/**
	 * Session.
	 *
	 * @var Velotok_Persist_Test_Session
	 */
	public $session;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cart    = new Velotok_Persist_Test_WC_Cart();
		$this->session = new Velotok_Persist_Test_Session();
	}
}

/**
 * Product fixtures.
 *
 * @return array<int,Velotok_Persist_Test_Product>
 */
function velotok_persist_test_products() {
	static $products = null;

	if ( null === $products ) {
		$products = array(
			101 => new Velotok_Persist_Test_Product( 101, 'Riviera', 1000.0, 99 ),
			102 => new Velotok_Persist_Test_Product( 102, 'Street 7', 500.0, 5 ),
		);
	}

	return $products;
}

/**
 * WooCommerce product accessor.
 *
 * @param int $product_id Product id.
 * @return Velotok_Persist_Test_Product|false
 */
function wc_get_product( $product_id ) {
	$products = velotok_persist_test_products();

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
 * @return Velotok_Persist_Test_WooCommerce
 */
function WC() {
	if ( ! isset( $GLOBALS['velotok_persist_test_wc'] ) ) {
		$GLOBALS['velotok_persist_test_wc'] = new Velotok_Persist_Test_WooCommerce();
	}

	return $GLOBALS['velotok_persist_test_wc'];
}

/**
 * Start a completely new session without a guest cart cookie.
 *
 * @return void
 */
function velotok_persist_test_reset_all() {
	$GLOBALS['velotok_persist_test_wc'] = new Velotok_Persist_Test_WooCommerce();
	unset( $_COOKIE['velotok_guest_cart'] );
}

/**
 * Start a new session but keep the long-lived cookie from the previous one.
 *
 * @return void
 */
function velotok_persist_test_reset_session() {
	$GLOBALS['velotok_persist_test_wc'] = new Velotok_Persist_Test_WooCommerce();
}

/**
 * Current login state.
 *
 * @return bool
 */
function is_user_logged_in() {
	return (bool) $GLOBALS['velotok_persist_test_logged_in'];
}

/**
 * Current user id.
 *
 * @return int
 */
function get_current_user_id() {
	return (int) $GLOBALS['velotok_persist_test_user_id'];
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

	$GLOBALS['velotok_persist_test_actions'][ $hook ][] = $callback;

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
 * Assert a guest persistence condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_persist_test_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Assert that a hook is registered for the cart persistence.
 *
 * @param string $hook     Hook.
 * @param string $callback Callback.
 * @param string $message  Failure message.
 * @return void
 */
function velotok_persist_test_hooked( $hook, $callback, $message ) {
	velotok_persist_test_assert(
		isset( $GLOBALS['velotok_persist_test_actions'][ $hook ] )
			&& in_array( $callback, $GLOBALS['velotok_persist_test_actions'][ $hook ], true ),
		$message
	);
}

/**
 * Return the cookie name used by the integration.
 *
 * @return string
 */
function velotok_persist_test_cookie_name() {
	return velotok_core_wc_cart_guest_cookie_name();
}

/**
 * Find a normalized cart item by product id.
 *
 * @param int $product_id Product id.
 * @return array<string,mixed>|null
 */
function velotok_persist_test_item( $product_id ) {
	foreach ( velotok_core_wc_cart_items() as $item ) {
		if ( (int) $item['product_id'] === (int) $product_id ) {
			return $item;
		}
	}

	return null;
}

velotok_persist_test_hooked( 'woocommerce_cart_updated', 'velotok_core_wc_cart_store_guest_snapshot', 'Изменение корзины должно сохранять гостевую корзину.' );
velotok_persist_test_hooked( 'woocommerce_cart_loaded_from_session', 'velotok_core_wc_cart_restore_guest_snapshot', 'Загрузка сессии должна восстанавливать гостевую корзину.' );

$cookie_name = velotok_persist_test_cookie_name();

// Scenario 1: a guest cart is stored in the session and mirrored in a cookie.
velotok_persist_test_reset_all();
velotok_core_cart_add_item( 101, 2 );

velotok_persist_test_assert( true === velotok_core_wc_cart_store_guest_snapshot(), 'Гостевая корзина должна сохраняться.' );

$stored_snapshot = WC()->session->get( 'velotok_guest_cart' );

velotok_persist_test_assert( is_array( $stored_snapshot ) && isset( $stored_snapshot['items'] ) && 1 === count( $stored_snapshot['items'] ), 'Снимок гостевой корзины должен лежать в сессии WooCommerce.' );
velotok_persist_test_assert( isset( $_COOKIE[ $cookie_name ] ) && '' !== $_COOKIE[ $cookie_name ], 'Гостевая корзина должна дублироваться в долгоживущую cookie.' );

$cookie_from_first_session = isset( $_COOKIE[ $cookie_name ] ) ? $_COOKIE[ $cookie_name ] : '';

$snapshot = velotok_core_wc_cart_guest_snapshot();

velotok_persist_test_assert( 1 === count( $snapshot ), 'Снимок гостевой корзины должен читаться из интеграции.' );
velotok_persist_test_assert( 2 === (int) $snapshot[0]['quantity'], 'В снимке должно сохраниться количество 2.' );

// Scenario 2: the guest cart survives a completely new WooCommerce session.
velotok_persist_test_reset_session();

velotok_persist_test_assert( null === WC()->session->get( 'velotok_guest_cart' ), 'Новая сессия не должна содержать снимок.' );
velotok_persist_test_assert( isset( $_COOKIE[ $cookie_name ] ) && $cookie_from_first_session === $_COOKIE[ $cookie_name ], 'Долгоживущая cookie должна пережить смену сессии.' );

$restored = velotok_core_wc_cart_restore_guest_snapshot();

velotok_persist_test_assert( true === $restored, 'Гостевая корзина должна восстанавливаться в новой сессии.' );
velotok_persist_test_assert( 2 === WC()->cart->get_cart_contents_count(), 'После восстановления в корзине должно быть 2 единицы.' );
velotok_persist_test_assert( null !== WC()->session->get( 'velotok_guest_cart' ), 'Восстановленная корзина должна снова сохраняться в сессии.' );

// Scenario 3: the same-session snapshot restores even without a cookie.
velotok_persist_test_reset_all();
velotok_core_cart_add_item( 101, 1 );
velotok_core_cart_add_item( 102, 1 );
velotok_core_wc_cart_store_guest_snapshot();

unset( $_COOKIE[ $cookie_name ] );
WC()->cart = new Velotok_Persist_Test_WC_Cart();

velotok_persist_test_assert( true === velotok_core_wc_cart_restore_guest_snapshot(), 'Снимок сессии должен восстанавливать корзину без cookie.' );
velotok_persist_test_assert( 2 === WC()->cart->get_cart_contents_count(), 'Обе позиции должны вернуться из снимка сессии.' );
velotok_persist_test_assert( 2 === count( velotok_core_wc_cart_items() ), 'Дубликаты не должны появляться при восстановлении.' );

// Scenario 4: logged-in customers never get a guest snapshot.
velotok_persist_test_reset_all();
$GLOBALS['velotok_persist_test_logged_in'] = true;
$GLOBALS['velotok_persist_test_user_id']   = 7;

velotok_core_cart_add_item( 101, 2 );

velotok_persist_test_assert( false === velotok_core_wc_cart_store_guest_snapshot(), 'Корзина пользователя не должна сохраняться как гостевая.' );
velotok_persist_test_assert( null === WC()->session->get( 'velotok_guest_cart' ), 'Для пользователя снимок в сессии не создаётся.' );
velotok_persist_test_assert( ! isset( $_COOKIE[ $cookie_name ] ), 'Для пользователя гостевая cookie не создаётся.' );

$GLOBALS['velotok_persist_test_logged_in'] = false;
$GLOBALS['velotok_persist_test_user_id']   = 0;

// Scenario 5: an emptied cart clears the snapshot and cannot be resurrected.
velotok_persist_test_reset_all();
velotok_core_cart_add_item( 101, 2 );
velotok_core_wc_cart_store_guest_snapshot();
velotok_persist_test_assert( isset( $_COOKIE[ $cookie_name ] ), 'Перед очисткой гостевая cookie должна существовать.' );

velotok_core_wc_cart_clear();
velotok_core_wc_cart_store_guest_snapshot();

$empty_snapshot = WC()->session->get( 'velotok_guest_cart' );

velotok_persist_test_assert( is_array( $empty_snapshot ) && empty( $empty_snapshot['items'] ), 'Пустая корзина должна сохраняться пустой.' );
velotok_persist_test_assert( ! isset( $_COOKIE[ $cookie_name ] ), 'Пустая корзина должна удалять долгоживущую cookie.' );

velotok_persist_test_reset_session();

velotok_persist_test_assert( false === velotok_core_wc_cart_restore_guest_snapshot(), 'Удалённая корзина не должна восстанавливаться в новой сессии.' );
velotok_persist_test_assert( WC()->cart->is_empty(), 'Новая сессия должна начинаться с пустой корзины.' );

// Scenario 6: the login transfer clears the persistence.
velotok_persist_test_reset_all();
velotok_core_cart_add_item( 101, 2 );
velotok_core_wc_cart_store_guest_snapshot();

$guest_items = velotok_core_wc_cart_guest_snapshot();
$transfer    = velotok_core_wc_cart_transfer_guest_cart( array( 'items' => $guest_items ), 7 );

velotok_persist_test_assert( is_array( $transfer ) && 1 === $transfer['transferred'], 'Гостевая корзина должна переноситься при входе.' );
velotok_persist_test_assert( null === WC()->session->get( 'velotok_guest_cart' ), 'После переноса снимок сессии должен очищаться.' );
velotok_persist_test_assert( ! isset( $_COOKIE[ $cookie_name ] ), 'После переноса гостевая cookie должна очищаться.' );

velotok_persist_test_reset_session();
velotok_persist_test_assert( false === velotok_core_wc_cart_restore_guest_snapshot(), 'Перенесённая корзина не должна возвращаться в новую сессию.' );

// Scenario 7: the quantity limit is applied when the cart is restored.
velotok_persist_test_reset_all();
WC()->cart->add_to_cart( 102, 10 );
velotok_core_wc_cart_store_guest_snapshot();

velotok_persist_test_reset_session();

velotok_persist_test_assert( true === velotok_core_wc_cart_restore_guest_snapshot(), 'Корзина должна восстанавливаться из cookie.' );
velotok_persist_test_assert( 5 === WC()->cart->get_cart_contents_count(), 'При восстановлении количество должно ограничиваться максимумом позиции.' );

$street = velotok_persist_test_item( 102 );

velotok_persist_test_assert( is_array( $street ) && 5 === (int) $street['quantity'], 'Street 7 должна восстановиться не более 5 единиц.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок сохранения гостевой корзины между сессиями не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: гостевая корзина сохраняется между сессиями (TESTEB-9).' . PHP_EOL;
exit( 0 );
