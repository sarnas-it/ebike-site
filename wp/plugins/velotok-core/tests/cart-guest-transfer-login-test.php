<?php
/**
 * Regression test for TESTEB-8 / spec cart: the guest cart is transferred on
 * login.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/cart-guest-transfer-login-test.php
 *
 * The test stubs the WooCommerce surface used by the cart integration and
 * proves that:
 * - a guest cart stored in the WooCommerce session is merged into the customer
 *   cart on login without duplicating positions;
 * - existing customer positions are preserved and quantities are summed;
 * - the merge is idempotent and the guest snapshot is cleared;
 * - a guest cart that is still only in WC()->cart also survives the login;
 * - the per-position quantity limit is applied during the merge.
 *
 * The test fails when the login merge stops carrying the guest cart.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_login_test_logged_in'] = false;
$GLOBALS['velotok_login_test_user_id']   = 0;
$GLOBALS['velotok_login_test_actions']   = array();

/**
 * Minimal WooCommerce product double.
 */
class Velotok_Login_Test_Product {
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
class Velotok_Login_Test_Session {
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
class Velotok_Login_Test_WC_Cart {
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
}

/**
 * Minimal WooCommerce runtime double.
 */
class Velotok_Login_Test_WooCommerce {
	/**
	 * Cart.
	 *
	 * @var Velotok_Login_Test_WC_Cart
	 */
	public $cart;

	/**
	 * Session.
	 *
	 * @var Velotok_Login_Test_Session
	 */
	public $session;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cart    = new Velotok_Login_Test_WC_Cart();
		$this->session = new Velotok_Login_Test_Session();
	}
}

/**
 * Product fixtures.
 *
 * @return array<int,Velotok_Login_Test_Product>
 */
function velotok_login_test_products() {
	static $products = null;

	if ( null === $products ) {
		$products = array(
			101 => new Velotok_Login_Test_Product( 101, 'Riviera', 1000.0, 99 ),
			102 => new Velotok_Login_Test_Product( 102, 'Street 7', 500.0, 5 ),
		);
	}

	return $products;
}

/**
 * WooCommerce product accessor.
 *
 * @param int $product_id Product id.
 * @return Velotok_Login_Test_Product|false
 */
function wc_get_product( $product_id ) {
	$products = velotok_login_test_products();

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
 * @return Velotok_Login_Test_WooCommerce
 */
function WC() {
	if ( ! isset( $GLOBALS['velotok_login_test_wc'] ) ) {
		$GLOBALS['velotok_login_test_wc'] = new Velotok_Login_Test_WooCommerce();
	}

	return $GLOBALS['velotok_login_test_wc'];
}

/**
 * Reset the WooCommerce runtime (cart and session).
 *
 * @return void
 */
function velotok_login_test_reset() {
	$GLOBALS['velotok_login_test_wc'] = new Velotok_Login_Test_WooCommerce();
}

/**
 * Current login state.
 *
 * @return bool
 */
function is_user_logged_in() {
	return (bool) $GLOBALS['velotok_login_test_logged_in'];
}

/**
 * Current user id.
 *
 * @return int
 */
function get_current_user_id() {
	return (int) $GLOBALS['velotok_login_test_user_id'];
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

	$GLOBALS['velotok_login_test_actions'][ $hook ][] = $callback;

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
 * Assert a login transfer condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_login_test_assert( $condition, $message ) {
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
function velotok_login_test_item( $product_id ) {
	foreach ( velotok_core_wc_cart_items() as $item ) {
		if ( (int) $item['product_id'] === (int) $product_id ) {
			return $item;
		}
	}

	return null;
}

velotok_login_test_assert(
	isset( $GLOBALS['velotok_login_test_actions']['wp_login'] )
		&& in_array( 'velotok_core_wc_cart_merge_guest_on_login', $GLOBALS['velotok_login_test_actions']['wp_login'], true ),
	'Перенос гостевой корзины должен быть подключён к событию входа wp_login.'
);

// Scenario 1: the stored guest snapshot merges into an existing customer cart.
velotok_login_test_reset();
WC()->cart->add_to_cart( 101, 2 );
WC()->cart->add_to_cart( 102, 1 );

velotok_login_test_assert( true === velotok_core_wc_cart_store_guest_snapshot(), 'Гостевая корзина должна сохраняться в сессии перед входом.' );

WC()->cart->empty_cart();
WC()->cart->add_to_cart( 101, 1 );

$GLOBALS['velotok_login_test_logged_in'] = true;
$GLOBALS['velotok_login_test_user_id']   = 7;

$merge = velotok_core_wc_cart_merge_guest_on_login( 'buyer', (object) array( 'ID' => 7 ) );

velotok_login_test_assert( is_array( $merge ), 'Вход должен возвращать результат переноса корзины.' );
velotok_login_test_assert( true === $merge['login_merge'], 'Результат должен подтверждать слияние корзины при входе.' );
velotok_login_test_assert( 'session_snapshot' === $merge['guest_source'], 'Источником гостевой корзины должна быть сессия WooCommerce.' );
velotok_login_test_assert( 2 === $merge['transferred'], 'Обе позиции гостевой корзины должны переноситься.' );
velotok_login_test_assert( 4 === $merge['item_count'], 'Количества должны объединяться: 1 + 2 + 1.' );
velotok_login_test_assert( 2 === $merge['unique_item_count'], 'Одинаковые товары не должны создавать дубликат.' );
velotok_login_test_assert( 4 === WC()->cart->get_cart_contents_count(), 'WC()->cart должен содержать объединённую корзину.' );

$riviera = velotok_login_test_item( 101 );
$street  = velotok_login_test_item( 102 );

velotok_login_test_assert( is_array( $riviera ) && 3 === (int) $riviera['quantity'], 'Riviera должна объединиться до 3 единиц.' );
velotok_login_test_assert( is_array( $street ) && 1 === (int) $street['quantity'], 'Street 7 должна сохраниться в корзине пользователя.' );
velotok_login_test_assert( null === WC()->session->get( 'velotok_guest_cart' ), 'После переноса сессионный снимок гостевой корзины должен очищаться.' );

// Scenario 2: a repeated login merge is idempotent.
$repeat = velotok_core_wc_cart_merge_guest_on_login( 'buyer', (object) array( 'ID' => 7 ) );

velotok_login_test_assert( is_array( $repeat ) && 0 === $repeat['transferred'], 'Повторный вход не должен переносить позиции ещё раз.' );
velotok_login_test_assert( 4 === WC()->cart->get_cart_contents_count(), 'Повторный вход не должен дублировать количества.' );

// Scenario 3: a guest cart that is still only in WC()->cart survives login.
velotok_login_test_reset();
WC()->cart->add_to_cart( 101, 2 );

$live_merge = velotok_core_wc_cart_merge_guest_on_login( 'buyer', (object) array( 'ID' => 7 ) );

velotok_login_test_assert( is_array( $live_merge ), 'Вход должен обрабатывать корзину, лежащую только в WC()->cart.' );
velotok_login_test_assert( 'live_cart' === $live_merge['guest_source'], 'Без снимка источником должна быть текущая гостевая корзина.' );
velotok_login_test_assert( 1 === $live_merge['items_preserved'], 'Позиция гостевой корзины должна сохраниться при входе.' );
velotok_login_test_assert( 2 === WC()->cart->get_cart_contents_count(), 'Гостевая корзина не должна теряться при входе.' );

// Scenario 4: the per-position quantity limit is applied during the merge.
velotok_login_test_reset();
$GLOBALS['velotok_login_test_logged_in'] = false;
$GLOBALS['velotok_login_test_user_id']   = 0;
WC()->cart->add_to_cart( 102, 10 );
velotok_core_wc_cart_store_guest_snapshot();
WC()->cart->empty_cart();

$GLOBALS['velotok_login_test_logged_in'] = true;
$GLOBALS['velotok_login_test_user_id']   = 7;

$capped = velotok_core_wc_cart_merge_guest_on_login( 'buyer', (object) array( 'ID' => 7 ) );

velotok_login_test_assert( is_array( $capped ) && 5 === $capped['item_count'], 'При переносе количество должно ограничиваться максимумом позиции.' );
velotok_login_test_assert( 5 === WC()->cart->get_cart_contents_count(), 'Ограничение должно применяться к WC()->cart.' );

// Scenario 5: an empty guest cart keeps the login merge harmless.
velotok_login_test_reset();

$empty = velotok_core_wc_cart_merge_guest_on_login( 'buyer', (object) array( 'ID' => 7 ) );

velotok_login_test_assert( is_array( $empty ) && 0 === $empty['transferred'], 'Пустая корзина не должна ничего переносить.' );
velotok_login_test_assert( 'none' === $empty['guest_source'], 'Для пустой корзины источник должен быть none.' );
velotok_login_test_assert( WC()->cart->is_empty(), 'Корзина должна оставаться пустой.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок переноса гостевой корзины при входе не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: гостевая корзина переносится при входе без дублей (TESTEB-8).' . PHP_EOL;
exit( 0 );
