<?php
/**
 * Regression test for TESTEB-11 / spec cart: cart totals include discounts and
 * shipping.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/cart-totals-discounts-delivery-test.php
 *
 * The test stubs the WooCommerce surface used by the cart integration and
 * proves that:
 * - totals are read from the live WC()->cart, not calculated in isolation;
 * - a WooCommerce discount reduces the cart total and is exposed as
 *   discount_total;
 * - a WooCommerce shipping cost is added to the cart total and is exposed as
 *   shipping_total;
 * - discounts and shipping are applied together on a multi-item cart;
 * - the generic cart helpers return the same WooCommerce totals;
 * - an empty cart stays empty with zero totals.
 *
 * The test fails when the WooCommerce totals stop taking discounts or shipping
 * into account.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_totals_test_logged_in'] = false;
$GLOBALS['velotok_totals_test_user_id']   = 0;
$GLOBALS['velotok_totals_test_actions']   = array();

/**
 * Minimal WooCommerce product double.
 */
class Velotok_Totals_Test_Product {
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
class Velotok_Totals_Test_Session {
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
 *
 * Discounts, taxes and shipping are configured directly so the test can prove
 * that the integration reads the WooCommerce totals instead of recomputing the
 * cart from item prices only.
 */
class Velotok_Totals_Test_WC_Cart {
	/**
	 * Cart contents.
	 *
	 * @var array
	 */
	public $contents = array();

	/**
	 * Discount total without tax.
	 *
	 * @var float
	 */
	public $discount = 0.0;

	/**
	 * Discount tax total.
	 *
	 * @var float
	 */
	public $discount_tax = 0.0;

	/**
	 * Shipping total without tax.
	 *
	 * @var float
	 */
	public $shipping = 0.0;

	/**
	 * Shipping tax total.
	 *
	 * @var float
	 */
	public $shipping_tax = 0.0;

	/**
	 * Cart tax total.
	 *
	 * @var float
	 */
	public $tax = 0.0;

	/**
	 * How many times totals were recalculated.
	 *
	 * @var int
	 */
	public $calculate_calls = 0;

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
		$this->calculate_calls++;
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
		return (float) $this->discount;
	}

	/**
	 * Discount tax.
	 *
	 * @return float
	 */
	public function get_discount_tax() {
		return (float) $this->discount_tax;
	}

	/**
	 * Shipping total.
	 *
	 * @return float
	 */
	public function get_shipping_total() {
		return (float) $this->shipping;
	}

	/**
	 * Shipping tax.
	 *
	 * @return float
	 */
	public function get_shipping_tax() {
		return (float) $this->shipping_tax;
	}

	/**
	 * Tax total.
	 *
	 * @return float
	 */
	public function get_total_tax() {
		return (float) $this->tax;
	}

	/**
	 * Cart grand total.
	 *
	 * @return float
	 */
	public function get_total() {
		return $this->get_subtotal()
			- $this->get_discount_total()
			- $this->get_discount_tax()
			+ $this->get_shipping_total()
			+ $this->get_shipping_tax()
			+ $this->get_total_tax();
	}
}

/**
 * Minimal WooCommerce runtime double.
 */
class Velotok_Totals_Test_WooCommerce {
	/**
	 * Cart.
	 *
	 * @var Velotok_Totals_Test_WC_Cart
	 */
	public $cart;

	/**
	 * Session.
	 *
	 * @var Velotok_Totals_Test_Session
	 */
	public $session;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cart    = new Velotok_Totals_Test_WC_Cart();
		$this->session = new Velotok_Totals_Test_Session();
	}
}

/**
 * Product fixtures.
 *
 * @return array<int,Velotok_Totals_Test_Product>
 */
function velotok_totals_test_products() {
	static $products = null;

	if ( null === $products ) {
		$products = array(
			101 => new Velotok_Totals_Test_Product( 101, 'Riviera', 1000.0, 99 ),
			102 => new Velotok_Totals_Test_Product( 102, 'Street 7', 500.0, 5 ),
		);
	}

	return $products;
}

/**
 * WooCommerce product accessor.
 *
 * @param int $product_id Product id.
 * @return Velotok_Totals_Test_Product|false
 */
function wc_get_product( $product_id ) {
	$products = velotok_totals_test_products();

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
 * @return Velotok_Totals_Test_WooCommerce
 */
function WC() {
	if ( ! isset( $GLOBALS['velotok_totals_test_wc'] ) ) {
		$GLOBALS['velotok_totals_test_wc'] = new Velotok_Totals_Test_WooCommerce();
	}

	return $GLOBALS['velotok_totals_test_wc'];
}

/**
 * Reset the WooCommerce runtime (cart and session).
 *
 * @return void
 */
function velotok_totals_test_reset() {
	$GLOBALS['velotok_totals_test_wc'] = new Velotok_Totals_Test_WooCommerce();
}

/**
 * Current login state.
 *
 * @return bool
 */
function is_user_logged_in() {
	return (bool) $GLOBALS['velotok_totals_test_logged_in'];
}

/**
 * Current user id.
 *
 * @return int
 */
function get_current_user_id() {
	return (int) $GLOBALS['velotok_totals_test_user_id'];
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

	$GLOBALS['velotok_totals_test_actions'][ $hook ][] = $callback;

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
 * Assert a cart totals condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_totals_test_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Assert a float total with a small tolerance.
 *
 * @param float  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Failure message.
 * @return void
 */
function velotok_totals_test_money( $expected, $actual, $message ) {
	velotok_totals_test_assert( is_numeric( $actual ) && abs( (float) $expected - (float) $actual ) < 0.005, $message );
}

$contract = velotok_core_cart_woocommerce_contract();

velotok_totals_test_assert( true === $contract['totals']['include_discounts'], 'Итоги WooCommerce должны включать скидки.' );
velotok_totals_test_assert( true === $contract['totals']['include_delivery'], 'Итоги WooCommerce должны включать доставку.' );
velotok_totals_test_assert( 'woocommerce_shipping' === $contract['totals']['delivery_source'], 'Источником доставки должен быть WooCommerce.' );
velotok_totals_test_assert( in_array( 'discount_total', $contract['totals']['fields'], true ), 'Поле discount_total должно быть в итогах.' );
velotok_totals_test_assert( in_array( 'shipping_total', $contract['totals']['fields'], true ), 'Поле shipping_total должно быть в итогах.' );

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

// Scenario 1: a discount and a shipping cost are both applied.
velotok_totals_test_reset();
velotok_core_wc_cart_add_item( $riviera, 2 );
WC()->cart->discount = 200.0;
WC()->cart->shipping = 500.0;

$totals = velotok_core_wc_cart_totals();

velotok_totals_test_money( 2000.0, $totals['subtotal'], 'Подытог должен браться из WooCommerce.' );
velotok_totals_test_money( 200.0, $totals['discount_total'], 'Скидка WooCommerce должна попадать в итоги.' );
velotok_totals_test_money( 500.0, $totals['shipping_total'], 'Доставка WooCommerce должна попадать в итоги.' );
velotok_totals_test_money( 2300.0, $totals['total'], 'Итог должен учитывать скидку и доставку: 2000 - 200 + 500.' );
velotok_totals_test_assert( true === $totals['include_discounts'], 'Итоги должны сообщать, что скидки учтены.' );
velotok_totals_test_assert( true === $totals['include_delivery'], 'Итоги должны сообщать, что доставка учтена.' );
velotok_totals_test_assert( WC()->cart->calculate_calls > 0, 'Перед возвратом итогов WooCommerce должен пересчитать корзину.' );

velotok_totals_test_money( 2000.0, velotok_core_cart_subtotal(), 'Общий помощник подытога должен использовать WooCommerce.' );
velotok_totals_test_money( 2300.0, velotok_core_cart_total(), 'Общий помощник итога должен включать скидку и доставку.' );
velotok_totals_test_money( 2300.0, velotok_core_cart_totals()['total'], 'Общий помощник итогов должен использовать WooCommerce.' );

// Scenario 2: only a discount is applied.
velotok_totals_test_reset();
velotok_core_wc_cart_add_item( $riviera, 1 );
WC()->cart->discount = 250.0;

$discounted = velotok_core_wc_cart_totals();

velotok_totals_test_money( 1000.0, $discounted['subtotal'], 'Подытог со скидкой должен оставаться исходным.' );
velotok_totals_test_money( 250.0, $discounted['discount_total'], 'Скидка должна быть видна в итогах.' );
velotok_totals_test_money( 0.0, $discounted['shipping_total'], 'Без доставки shipping_total должен быть нулевым.' );
velotok_totals_test_money( 750.0, $discounted['total'], 'Итог должен уменьшиться на скидку: 1000 - 250.' );

// Scenario 3: only a shipping cost is applied.
velotok_totals_test_reset();
velotok_core_wc_cart_add_item( $riviera, 1 );
WC()->cart->shipping = 350.0;

$delivered = velotok_core_wc_cart_totals();

velotok_totals_test_money( 1000.0, $delivered['subtotal'], 'Подытог с доставкой должен оставаться исходным.' );
velotok_totals_test_money( 0.0, $delivered['discount_total'], 'Без скидки discount_total должен быть нулевым.' );
velotok_totals_test_money( 350.0, $delivered['shipping_total'], 'Доставка должна быть видна в итогах.' );
velotok_totals_test_money( 1350.0, $delivered['total'], 'Итог должен увеличиться на доставку: 1000 + 350.' );

// Scenario 4: discounts and shipping apply to a multi-item cart.
velotok_totals_test_reset();
velotok_core_wc_cart_add_item( $riviera, 1 );
velotok_core_wc_cart_add_item( $street, 2 );
WC()->cart->discount = 300.0;
WC()->cart->shipping = 600.0;

$multi = velotok_core_wc_cart_totals();

velotok_totals_test_money( 2000.0, $multi['subtotal'], 'Подытог нескольких позиций должен считаться верно.' );
velotok_totals_test_money( 300.0, $multi['discount_total'], 'Скидка должна применяться к корзине из нескольких позиций.' );
velotok_totals_test_money( 600.0, $multi['shipping_total'], 'Доставка должна применяться к корзине из нескольких позиций.' );
velotok_totals_test_money( 2300.0, $multi['total'], 'Итог нескольких позиций: 2000 - 300 + 600.' );
velotok_totals_test_assert( 2 === $multi['unique_item_count'], 'В корзине должно оставаться две уникальные позиции.' );

// Scenario 5: an empty cart keeps zero totals.
velotok_totals_test_reset();

$empty = velotok_core_wc_cart_totals();

velotok_totals_test_assert( true === $empty['valid'], 'Пустая корзина должна возвращать валидные итоги WooCommerce.' );
velotok_totals_test_assert( true === $empty['empty'], 'Пустая корзина должна помечаться пустой.' );
velotok_totals_test_money( 0.0, $empty['discount_total'], 'У пустой корзины не должно быть скидки.' );
velotok_totals_test_money( 0.0, $empty['shipping_total'], 'У пустой корзины не должно быть доставки.' );
velotok_totals_test_money( 0.0, $empty['total'], 'Итог пустой корзины должен быть нулевым.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок итогов корзины не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: итоги корзины учитывают скидки и доставку WooCommerce (TESTEB-11).' . PHP_EOL;
exit( 0 );
