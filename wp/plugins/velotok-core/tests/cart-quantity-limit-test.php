<?php
/**
 * Regression test for TESTEB-12 / spec cart: the quantity per cart position is
 * limited.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/cart-quantity-limit-test.php
 *
 * The test stubs the WooCommerce surface used by the cart integration and
 * proves that:
 * - the cart contract declares a per-position maximum and a limit error code;
 * - adding more than the contract maximum caps the line at the maximum;
 * - adding to a line that already reached the maximum does not grow it;
 * - the product's own purchase limit is respected when it is lower;
 * - updating a line above the maximum caps the stored quantity;
 * - the WooCommerce add-to-cart quantity filter clamps the incoming quantity,
 *   including the quantity already present in the cart;
 * - the WooCommerce add/update validation rejects quantities above the line
 *   maximum.
 *
 * The test fails when the WooCommerce integration stops limiting the quantity
 * per position.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_qty_test_logged_in'] = false;
$GLOBALS['velotok_qty_test_user_id']   = 0;
$GLOBALS['velotok_qty_test_notices']   = array();
$GLOBALS['velotok_qty_test_actions']   = array();

/**
 * Minimal WooCommerce product double.
 */
class Velotok_Quantity_Test_Product {
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
class Velotok_Quantity_Test_Session {
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
class Velotok_Quantity_Test_WC_Cart {
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
		// The quantity test does not need total recalculation.
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
class Velotok_Quantity_Test_WooCommerce {
	/**
	 * Cart.
	 *
	 * @var Velotok_Quantity_Test_WC_Cart
	 */
	public $cart;

	/**
	 * Session.
	 *
	 * @var Velotok_Quantity_Test_Session
	 */
	public $session;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cart    = new Velotok_Quantity_Test_WC_Cart();
		$this->session = new Velotok_Quantity_Test_Session();
	}
}

/**
 * Product fixtures.
 *
 * @return array<int,Velotok_Quantity_Test_Product>
 */
function velotok_qty_test_products() {
	static $products = null;

	if ( null === $products ) {
		$products = array(
			101 => new Velotok_Quantity_Test_Product( 101, 'Riviera', 1000.0, 99 ),
			102 => new Velotok_Quantity_Test_Product( 102, 'Street 7', 500.0, 5 ),
		);
	}

	return $products;
}

/**
 * WooCommerce product accessor.
 *
 * @param int $product_id Product id.
 * @return Velotok_Quantity_Test_Product|false
 */
function wc_get_product( $product_id ) {
	$products = velotok_qty_test_products();

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
 * @return Velotok_Quantity_Test_WooCommerce
 */
function WC() {
	if ( ! isset( $GLOBALS['velotok_qty_test_wc'] ) ) {
		$GLOBALS['velotok_qty_test_wc'] = new Velotok_Quantity_Test_WooCommerce();
	}

	return $GLOBALS['velotok_qty_test_wc'];
}

/**
 * Reset the WooCommerce runtime (cart and session).
 *
 * @return void
 */
function velotok_qty_test_reset() {
	$GLOBALS['velotok_qty_test_wc'] = new Velotok_Quantity_Test_WooCommerce();
}

/**
 * Current login state.
 *
 * @return bool
 */
function is_user_logged_in() {
	return (bool) $GLOBALS['velotok_qty_test_logged_in'];
}

/**
 * Current user id.
 *
 * @return int
 */
function get_current_user_id() {
	return (int) $GLOBALS['velotok_qty_test_user_id'];
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

	$GLOBALS['velotok_qty_test_actions'][ $hook ][] = $callback;

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
	$GLOBALS['velotok_qty_test_notices'][] = array(
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
 * Assert a quantity limit condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_qty_test_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Return the first live cart line for a product.
 *
 * @param int $product_id Product id.
 * @return array<string,mixed>|null
 */
function velotok_qty_test_item( $product_id ) {
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

velotok_qty_test_assert( 99 === (int) $contract['max_quantity'], 'Контракт корзины должен объявлять максимум 99 единиц на позицию.' );
velotok_qty_test_assert( 1 === (int) $contract['min_quantity'], 'Минимальное количество на позицию должно быть равно 1.' );
velotok_qty_test_assert( 'CART_QUANTITY_LIMIT' === $contract['error_codes']['quantity_limit'], 'Должен быть объявлен код превышения количества.' );
velotok_qty_test_assert( 'Количество на позицию SHALL ограничиваться' === $contract['requirements']['quantity_limit'], 'Контракт должен ссылаться на требование ограничения количества.' );

$cart_contract = velotok_core_cart_contract();

velotok_qty_test_assert( 99 === (int) $cart_contract['max_quantity'], 'Общий контракт корзины должен объявлять максимум 99 единиц на позицию.' );
velotok_qty_test_assert( 'CART_QUANTITY_LIMIT' === $cart_contract['error_codes']['quantity_limit'], 'Общий контракт должен объявлять код превышения количества.' );

// Scenario 1: adding more than the contract maximum caps the line.
velotok_qty_test_reset();
$added = velotok_core_wc_cart_add_item( $riviera, 150 );
$item  = velotok_qty_test_item( 101 );

velotok_qty_test_assert( is_array( $added ) && ! empty( $added['added'] ), 'Товар должен добавляться в корзину WooCommerce.' );
velotok_qty_test_assert( is_array( $item ) && 99 === (int) $item['quantity'], 'Количество позиции должно ограничиваться 99 единицами.' );
velotok_qty_test_assert( 99 === (int) $added['item_count'], 'В корзине должно остаться не более 99 единиц.' );
velotok_qty_test_assert( 99 === WC()->cart->get_cart_contents_count(), 'Ограничение должно применяться в WC()->cart.' );

// Scenario 2: adding to a full line does not grow it.
$more = velotok_core_wc_cart_add_item( $riviera, 10 );
$item = velotok_qty_test_item( 101 );

velotok_qty_test_assert( is_array( $more ) && empty( $more['added'] ), 'Добавление сверх лимита не должно увеличивать позицию.' );
velotok_qty_test_assert( 'CART_QUANTITY_LIMIT' === $more['code'], 'Результат сверх лимита должен сообщать CART_QUANTITY_LIMIT.' );
velotok_qty_test_assert( is_array( $item ) && 99 === (int) $item['quantity'], 'Позиция должна оставаться на максимуме.' );
velotok_qty_test_assert( 99 === WC()->cart->get_cart_contents_count(), 'WC()->cart не должен превышать лимит.' );

// Scenario 3: the product's own purchase limit is respected.
velotok_qty_test_reset();
$product_added = velotok_core_wc_cart_add_item( $street, 10 );
$product_item  = velotok_qty_test_item( 102 );

velotok_qty_test_assert( is_array( $product_item ) && 5 === (int) $product_item['quantity'], 'Должен применяться максимум самого товара (5).' );
velotok_qty_test_assert( 5 === (int) $product_added['item_count'], 'Корзина должна ограничиться максимумом товара.' );
velotok_qty_test_assert( 5 === WC()->cart->get_cart_contents_count(), 'WC()->cart должен ограничиться максимумом товара.' );

$product_more = velotok_core_wc_cart_add_item( $street, 1 );

velotok_qty_test_assert( is_array( $product_more ) && empty( $product_more['added'] ), 'Добавление сверх максимума товара должно блокироваться.' );
velotok_qty_test_assert( 'CART_QUANTITY_LIMIT' === $product_more['code'], 'Для максимума товара должен возвращаться CART_QUANTITY_LIMIT.' );
velotok_qty_test_assert( 5 === WC()->cart->get_cart_contents_count(), 'Максимум товара не должен превышаться.' );

// Scenario 4: updating a line above the maximum caps the stored quantity.
velotok_qty_test_reset();
velotok_core_wc_cart_add_item( $riviera, 2 );
$item    = velotok_qty_test_item( 101 );
$updated = velotok_core_wc_cart_update_item( $item['key'], 150 );
$item    = velotok_qty_test_item( 101 );

velotok_qty_test_assert( is_array( $updated ) && ! empty( $updated['updated'] ), 'Обновление количества должно применяться.' );
velotok_qty_test_assert( is_array( $item ) && 99 === (int) $item['quantity'], 'Обновление выше максимума должно ограничиваться 99.' );
velotok_qty_test_assert( 99 === WC()->cart->get_cart_contents_count(), 'Обновление не должно превышать лимит в WC()->cart.' );

velotok_qty_test_reset();
velotok_core_wc_cart_add_item( $street, 1 );
$item    = velotok_qty_test_item( 102 );
$updated = velotok_core_wc_cart_update_item( $item['key'], 10 );
$item    = velotok_qty_test_item( 102 );

velotok_qty_test_assert( is_array( $item ) && 5 === (int) $item['quantity'], 'Обновление должно учитывать максимум товара.' );
velotok_qty_test_assert( 5 === WC()->cart->get_cart_contents_count(), 'Максимум товара не должен превышаться при обновлении.' );

// Scenario 5: the WooCommerce add-to-cart quantity filter clamps the quantity.
velotok_qty_test_reset();

velotok_qty_test_assert( 99 === velotok_core_wc_cart_filter_add_quantity( 150, 101 ), 'Фильтр должен ограничивать количество до 99.' );
velotok_qty_test_assert( 5 === velotok_core_wc_cart_filter_add_quantity( 10, 102 ), 'Фильтр должен учитывать максимум товара.' );

velotok_core_wc_cart_add_item( $riviera, 98 );
velotok_qty_test_assert( 1 === velotok_core_wc_cart_filter_add_quantity( 5, 101 ), 'Фильтр должен учитывать уже лежащее в корзине количество.' );

velotok_core_wc_cart_add_item( $riviera, 1 );
velotok_qty_test_assert( 99 === WC()->cart->get_cart_contents_count(), 'Корзина должна заполниться ровно до 99.' );
velotok_qty_test_assert( 0 === velotok_core_wc_cart_filter_add_quantity( 1, 101 ), 'Для заполненной позиции фильтр должен вернуть 0.' );

$max = velotok_core_wc_cart_max_quantity( 101 );
velotok_qty_test_assert( 99 === $max, 'Максимум позиции должен читаться из контракта.' );
velotok_qty_test_assert( 5 === velotok_core_wc_cart_max_quantity( 102 ), 'Максимум позиции должен учитывать максимум товара.' );

// Scenario 6: WooCommerce add/update validation rejects quantities above the limit.
velotok_qty_test_reset();

velotok_qty_test_assert( true === velotok_core_wc_cart_validate_add_quantity( true, 101, 99 ), 'Количество на уровне максимума должно проходить валидацию.' );
velotok_qty_test_assert( false === velotok_core_wc_cart_validate_add_quantity( true, 101, 100 ), 'Количество выше максимума должно отклоняться.' );
velotok_qty_test_assert( false === velotok_core_wc_cart_validate_add_quantity( true, 101, 0 ), 'Нулевое количество должно отклоняться.' );
velotok_qty_test_assert( false === velotok_core_wc_cart_validate_add_quantity( true, 102, 6 ), 'Количество выше максимума товара должно отклоняться.' );

velotok_core_wc_cart_add_item( $riviera, 98 );

velotok_qty_test_assert( false === velotok_core_wc_cart_validate_add_quantity( true, 101, 2 ), 'Валидация должна учитывать уже лежащее в корзине количество.' );
velotok_qty_test_assert( true === velotok_core_wc_cart_validate_add_quantity( true, 101, 1 ), 'Добавление до максимума должно проходить валидацию.' );

velotok_qty_test_reset();
velotok_core_wc_cart_add_item( $street, 1 );
$item   = velotok_qty_test_item( 102 );
$values = array( 'product_id' => 102 );

velotok_qty_test_assert( false === velotok_core_wc_cart_validate_update_quantity( true, $item['key'], $values, 10 ), 'Обновление выше максимума должно отклоняться.' );
velotok_qty_test_assert( true === velotok_core_wc_cart_validate_update_quantity( true, $item['key'], $values, 5 ), 'Обновление на уровне максимума должно проходить валидацию.' );
velotok_qty_test_assert( false === velotok_core_wc_cart_validate_update_quantity( true, $item['key'], $values, 0 ), 'Нулевое количество при обновлении должно отклоняться.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок ограничения количества не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: количество на позицию ограничивается (TESTEB-12).' . PHP_EOL;
exit( 0 );
