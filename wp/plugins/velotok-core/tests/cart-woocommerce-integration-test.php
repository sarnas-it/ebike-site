<?php
/**
 * Integration test for the WooCommerce cart (TESTEB-7 / spec cart).
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/cart-woocommerce-integration-test.php
 *
 * The test stubs the small WordPress/WooCommerce surface used by the cart
 * integration and proves that the system keeps its cart in WC()->cart:
 * - add, merge, update and remove positions;
 * - the quantity per position is limited;
 * - totals include WooCommerce discounts and shipping;
 * - removing the last position leaves an empty cart;
 * - a guest cart is persisted in the WooCommerce session and transferred on
 *   login without duplicating positions;
 * - checkout requires an authenticated customer.
 *
 * The test fails when the WooCommerce integration is missing because the live
 * cart is never used and the in-memory fallback is returned instead.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_cart_test_logged_in'] = false;
$GLOBALS['velotok_cart_test_user_id']   = 0;
$GLOBALS['velotok_cart_test_notices']   = array();
$GLOBALS['velotok_cart_test_actions']   = array();
$GLOBALS['velotok_cart_test_filters']   = array();

/**
 * A minimal WooCommerce product double.
 */
class Velotok_Cart_Test_Product {
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
class Velotok_Cart_Test_Session {
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
class Velotok_Cart_Test_WC_Cart {
	/**
	 * Cart contents.
	 *
	 * @var array
	 */
	public $contents = array();

	/**
	 * Discount total.
	 *
	 * @var float
	 */
	public $discount = 0.0;

	/**
	 * Shipping total.
	 *
	 * @var float
	 */
	public $shipping = 0.0;

	/**
	 * Tax total.
	 *
	 * @var float
	 */
	public $tax = 0.0;

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
		return (float) $this->discount;
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
		return (float) $this->shipping;
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
		return (float) $this->tax;
	}

	/**
	 * Cart grand total.
	 *
	 * @return float
	 */
	public function get_total() {
		return $this->get_subtotal() - $this->get_discount_total() + $this->get_shipping_total() + $this->get_total_tax();
	}
}

/**
 * Minimal WooCommerce runtime double.
 */
class Velotok_Cart_Test_WooCommerce {
	/**
	 * Cart.
	 *
	 * @var Velotok_Cart_Test_WC_Cart
	 */
	public $cart;

	/**
	 * Session.
	 *
	 * @var Velotok_Cart_Test_Session
	 */
	public $session;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cart    = new Velotok_Cart_Test_WC_Cart();
		$this->session = new Velotok_Cart_Test_Session();
	}
}

/**
 * Product fixtures.
 *
 * @return array<int,Velotok_Cart_Test_Product>
 */
function velotok_cart_test_products() {
	static $products = null;

	if ( null === $products ) {
		$products = array(
			101 => new Velotok_Cart_Test_Product( 101, 'Riviera', 1000.0, 99 ),
			102 => new Velotok_Cart_Test_Product( 102, 'Street 7', 500.0, 5 ),
		);
	}

	return $products;
}

/**
 * WooCommerce product accessor.
 *
 * @param int $product_id Product id.
 * @return Velotok_Cart_Test_Product|false
 */
function wc_get_product( $product_id ) {
	$products = velotok_cart_test_products();

	return isset( $products[ (int) $product_id ] ) ? $products[ (int) $product_id ] : false;
}

/**
 * WooCommerce SKU accessor.
 *
 * @param string $sku SKU.
 * @return int
 */
function wc_get_product_id_by_sku( $sku ) {
	return 'riviera-sku' === $sku ? 101 : 0;
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
 * @return Velotok_Cart_Test_WooCommerce
 */
function WC() {
	if ( ! isset( $GLOBALS['velotok_cart_test_wc'] ) ) {
		$GLOBALS['velotok_cart_test_wc'] = new Velotok_Cart_Test_WooCommerce();
	}

	return $GLOBALS['velotok_cart_test_wc'];
}

/**
 * Reset the WooCommerce runtime.
 *
 * @return void
 */
function velotok_cart_test_reset() {
	$GLOBALS['velotok_cart_test_wc'] = new Velotok_Cart_Test_WooCommerce();
}

/**
 * Current login state.
 *
 * @return bool
 */
function is_user_logged_in() {
	return (bool) $GLOBALS['velotok_cart_test_logged_in'];
}

/**
 * Current user id.
 *
 * @return int
 */
function get_current_user_id() {
	return (int) $GLOBALS['velotok_cart_test_user_id'];
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

	$GLOBALS['velotok_cart_test_actions'][ $hook ][] = $callback;

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

	$GLOBALS['velotok_cart_test_filters'][ $hook ][] = $callback;

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
	$GLOBALS['velotok_cart_test_notices'][] = array(
		'message' => (string) $message,
		'type'    => (string) $type,
	);
}

$integration_path = dirname( __DIR__ ) . '/includes/cart-woocommerce.php';

if ( ! is_readable( $integration_path ) ) {
	fwrite( STDERR, 'FAIL: Не найдена интеграция корзины с WooCommerce.' . PHP_EOL );
	exit( 1 );
}

require_once dirname( __DIR__ ) . '/includes/cart.php';
require_once $integration_path;

$failures = array();

/**
 * Assert a cart condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_cart_wc_assert( $condition, $message ) {
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
function velotok_cart_wc_item_by_product( $product_id ) {
	foreach ( velotok_core_cart_items() as $item ) {
		if ( (int) $item['product_id'] === (int) $product_id ) {
			return $item;
		}
	}

	return null;
}

$contract = velotok_core_cart_woocommerce_contract();

velotok_cart_wc_assert( 'cart' === $contract['spec'], 'Контракт корзины должен ссылаться на спеку cart.' );
velotok_cart_wc_assert( 'TESTEB-7' === $contract['requirement'], 'Контракт корзины должен ссылаться на TESTEB-7.' );
velotok_cart_wc_assert( 'woocommerce' === $contract['source'], 'Источником корзины должен быть WooCommerce.' );
velotok_cart_wc_assert( true === $contract['uses_wc_cart'], 'Корзина должна использоваться через WC()->cart.' );
velotok_cart_wc_assert( false === $contract['mocked'], 'Корзина не должна быть заглушкой.' );
velotok_cart_wc_assert( true === $contract['persistent'], 'Корзина должна сохраняться между запросами.' );
velotok_cart_wc_assert( true === $contract['storage']['guest_persistent'], 'Гостевая корзина должна сохраняться между сессиями.' );
velotok_cart_wc_assert( true === $contract['storage']['user_persistent'], 'Корзина пользователя должна сохраняться между устройствами.' );
velotok_cart_wc_assert( true === $contract['totals']['include_discounts'], 'Итоги должны учитывать скидки.' );
velotok_cart_wc_assert( true === $contract['totals']['include_delivery'], 'Итоги должны учитывать доставку.' );
velotok_cart_wc_assert( true === $contract['checkout']['requires_auth'], 'Покупка должна требовать авторизации.' );

$cart_contract = velotok_core_cart_contract();
velotok_cart_wc_assert( 'woocommerce' === $cart_contract['source'], 'Контракт cart.php должен объявлять WooCommerce.' );
velotok_cart_wc_assert( true === $cart_contract['persistent'], 'Контракт cart.php должен объявлять постоянное хранение.' );
velotok_cart_wc_assert( true === $cart_contract['totals']['include_delivery'], 'Контракт cart.php должен включать доставку в итоги.' );

velotok_cart_test_reset();

$riviera = array(
	'product_id' => 101,
	'name'       => 'Riviera',
	'price'      => 1000.0,
);

$added = velotok_core_cart_add_item( $riviera, 2 );

velotok_cart_wc_assert( is_array( $added ) && ! empty( $added['added'] ), 'Товар должен добавляться в корзину WooCommerce.' );
velotok_cart_wc_assert( 'woocommerce' === $added['source'], 'Результат должен быть получен из WooCommerce.' );
velotok_cart_wc_assert( 2 === $added['item_count'], 'После добавления в корзине должно быть 2 единицы.' );
velotok_cart_wc_assert( 2 === WC()->cart->get_cart_contents_count(), 'Количество должно храниться в WC()->cart.' );

$merged = velotok_core_cart_add_item( $riviera, 1 );

velotok_cart_wc_assert( 3 === $merged['item_count'], 'Повторное добавление должно суммировать количества.' );
velotok_cart_wc_assert( 1 === $merged['unique_item_count'], 'Одинаковые товары не должны создавать дубликат.' );

$capped = velotok_core_cart_add_item( $riviera, 150 );

velotok_cart_wc_assert( 99 === $capped['item_count'], 'Количество на позицию должно ограничиваться максимумом.' );
velotok_cart_wc_assert( 99 === WC()->cart->get_cart_contents_count(), 'Ограничение должно применяться в WC()->cart.' );

$riviera_item = velotok_cart_wc_item_by_product( 101 );

velotok_cart_wc_assert( is_array( $riviera_item ), 'Позиция Riviera должна присутствовать в корзине.' );

$updated = velotok_core_cart_update_item( $riviera_item['key'], 5 );

velotok_cart_wc_assert( is_array( $updated ) && ! empty( $updated['updated'] ), 'Количество позиции должно обновляться.' );
velotok_cart_wc_assert( 5 === $updated['item_count'], 'Обновление должно записать новое количество в WooCommerce.' );

$removed = velotok_core_cart_remove_item( $riviera_item['key'] );

velotok_cart_wc_assert( is_array( $removed ) && ! empty( $removed['removed'] ), 'Позиция должна удаляться из корзины.' );
velotok_cart_wc_assert( true === $removed['empty'], 'После удаления последней позиции корзина должна стать пустой.' );
velotok_cart_wc_assert( 0 === $removed['item_count'], 'В пустой корзине не должно оставаться единиц товара.' );
velotok_cart_wc_assert( WC()->cart->is_empty(), 'WC()->cart должен быть пустым после удаления последней позиции.' );

velotok_cart_test_reset();
velotok_core_cart_add_item( $riviera, 2 );
WC()->cart->discount = 200.0;
WC()->cart->shipping = 500.0;
WC()->cart->tax      = 0.0;

$totals = velotok_core_cart_totals();

velotok_cart_wc_assert( 2000.0 === $totals['subtotal'], 'Подытог должен рассчитываться по WooCommerce.' );
velotok_cart_wc_assert( 200.0 === $totals['discount_total'], 'Итоги должны учитывать скидку WooCommerce.' );
velotok_cart_wc_assert( 500.0 === $totals['shipping_total'], 'Итоги должны учитывать доставку WooCommerce.' );
velotok_cart_wc_assert( 2300.0 === $totals['total'], 'Итог должен включать скидку и доставку.' );
velotok_cart_wc_assert( 2300.0 === velotok_core_cart_total(), 'Общий итог корзины должен браться из WooCommerce.' );

velotok_cart_test_reset();
velotok_core_cart_add_item( $riviera, 1 );

$guest_cart = array(
	'items' => array(
		array(
			'product_id' => 101,
			'name'       => 'Riviera',
			'price'      => 1000.0,
			'quantity'   => 2,
		),
		array(
			'product_id' => 102,
			'name'       => 'Street 7',
			'price'      => 500.0,
			'quantity'   => 1,
		),
	),
);

$transferred = velotok_core_cart_transfer_guest_cart( $guest_cart );

velotok_cart_wc_assert( is_array( $transferred ), 'Перенос гостевой корзины должен выполняться в WooCommerce.' );
velotok_cart_wc_assert( 2 === $transferred['transferred'], 'Обе позиции гостевой корзины должны переноситься.' );
velotok_cart_wc_assert( 4 === $transferred['item_count'], 'Количества должны объединяться: 1 + 2 + 1.' );
velotok_cart_wc_assert( 2 === $transferred['unique_item_count'], 'Дубликаты товаров не должны появляться.' );

$merged_riviera = velotok_cart_wc_item_by_product( 101 );
velotok_cart_wc_assert( is_array( $merged_riviera ) && 3 === (int) $merged_riviera['quantity'], 'Riviera должна объединиться до 3 единиц.' );

$empty_transfer = velotok_core_cart_transfer_guest_cart( array() );

velotok_cart_wc_assert( is_array( $empty_transfer ) && 4 === $empty_transfer['item_count'], 'Пустая гостевая корзина не должна дублировать текущую корзину.' );

velotok_cart_test_reset();
velotok_core_cart_add_item( $riviera, 2 );

velotok_cart_wc_assert( true === velotok_core_wc_cart_store_guest_snapshot(), 'Гостевая корзина должна сохраняться в сессии WooCommerce.' );
velotok_cart_wc_assert( 2 === WC()->cart->get_cart_contents_count(), 'Перед очисткой в корзине две единицы.' );

velotok_core_wc_cart_clear();

velotok_cart_wc_assert( WC()->cart->is_empty(), 'Корзина должна очищаться.' );
velotok_cart_wc_assert( true === velotok_core_wc_cart_restore_guest_snapshot(), 'Гостевая корзина должна восстанавливаться из сессии.' );
velotok_cart_wc_assert( 2 === WC()->cart->get_cart_contents_count(), 'После восстановления должны вернуться две единицы.' );

$GLOBALS['velotok_cart_test_logged_in'] = true;
$GLOBALS['velotok_cart_test_user_id']   = 7;

$login_merge = velotok_core_wc_cart_merge_guest_on_login( 'buyer', (object) array( 'ID' => 7 ) );

velotok_cart_wc_assert( is_array( $login_merge ) && 2 === $login_merge['item_count'], 'Вход не должен дублировать уже перенесённую гостевую корзину.' );
velotok_cart_wc_assert( 2 === WC()->cart->get_cart_contents_count(), 'После входа в корзине должно остаться две единицы.' );

$GLOBALS['velotok_cart_test_logged_in'] = false;
$GLOBALS['velotok_cart_test_user_id']   = 0;
$GLOBALS['velotok_cart_test_user_id']   = 0;
$GLOBALS['velotok_cart_test_notices']   = array();

$guest_status = velotok_core_wc_cart_checkout_auth_status();

velotok_cart_wc_assert( false === $guest_status['allowed'], 'Гость не должен проходить к оформлению.' );
velotok_cart_wc_assert( 'CHECKOUT_AUTH_REQUIRED' === $guest_status['code'], 'Гостю должен возвращаться CHECKOUT_AUTH_REQUIRED.' );
velotok_cart_wc_assert( false === velotok_core_wc_cart_validate_checkout_auth(), 'Проверка оформления должна блокировать гостя.' );
velotok_cart_wc_assert( 1 === count( $GLOBALS['velotok_cart_test_notices'] ), 'Гостю должно показываться сообщение о необходимости входа.' );

$GLOBALS['velotok_cart_test_logged_in'] = true;
$GLOBALS['velotok_cart_test_user_id']   = 7;

$user_status = velotok_core_wc_cart_checkout_auth_status();

velotok_cart_wc_assert( true === $user_status['allowed'], 'Авторизованный покупатель должен проходить к оформлению.' );
velotok_cart_wc_assert( null === $user_status['code'], 'Авторизованному покупателю не нужен код ошибки.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок корзины WooCommerce не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: корзина ведётся на WooCommerce и выполняет перенос гостевой корзины (TESTEB-7).' . PHP_EOL;
exit( 0 );
