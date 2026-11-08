<?php
/**
 * Contract test for the delivery selection fixed in an order.
 *
 * Requirement TESTEB-37 / spec delivery:
 * «Выбор доставки SHALL фиксироваться в заказе».
 *
 * The test proves that any selected WooCommerce shipping method (provider,
 * flat rate, free shipping or pickup) is written to the order, that the
 * written selection can be read back, and that legacy orders without the
 * dedicated meta still expose their shipping line.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-order-selection-contract-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_order_selection_actions'] = array();
$GLOBALS['velotok_order_selection_filters'] = array();
$GLOBALS['velotok_order_selection_meta']    = array();
$GLOBALS['velotok_order_selection_orders']  = array();

/**
 * Record an action.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted args.
 * @return bool
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	unset( $priority, $args );

	$GLOBALS['velotok_order_selection_actions'][ $hook ][] = $callback;

	return true;
}

/**
 * Record a filter.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted args.
 * @return bool
 */
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	unset( $priority, $args );

	$GLOBALS['velotok_order_selection_filters'][ $hook ][] = $callback;

	return true;
}

/**
 * Apply registered filters.
 *
 * @param string $hook  Hook name.
 * @param mixed  $value Value.
 * @return mixed
 */
function apply_filters( $hook, $value ) {
	$args = func_get_args();

	if ( empty( $GLOBALS['velotok_order_selection_filters'][ $hook ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['velotok_order_selection_filters'][ $hook ] as $callback ) {
		$args[1] = $value;
		$value   = call_user_func_array( $callback, array_slice( $args, 1 ) );
	}

	return $value;
}

/**
 * Translate a string.
 *
 * @param string $text Text.
 * @return string
 */
function __( $text ) {
	return $text;
}

/**
 * Escape text.
 *
 * @param string $text Text.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Convert to non-negative integer.
 *
 * @param mixed $value Value.
 * @return int
 */
function absint( $value ) {
	return abs( (int) $value );
}

/**
 * Get an option.
 *
 * @param string $key     Option key.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_option( $key, $default = false ) {
	unset( $key );

	return $default;
}

/**
 * Read order meta.
 *
 * @param int    $post_id Post id.
 * @param string $key     Meta key.
 * @param bool   $single  Single.
 * @return mixed
 */
function get_post_meta( $post_id, $key = '', $single = false ) {
	unset( $single );

	if ( isset( $GLOBALS['velotok_order_selection_meta'][ $post_id ][ $key ] ) ) {
		return $GLOBALS['velotok_order_selection_meta'][ $post_id ][ $key ];
	}

	return '';
}

/**
 * Write order meta.
 *
 * @param int    $post_id Post id.
 * @param string $key     Meta key.
 * @param mixed  $value   Value.
 * @return bool
 */
function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['velotok_order_selection_meta'][ $post_id ][ $key ] = $value;

	return true;
}

/**
 * Load an order for the persistence test.
 *
 * @param int $order_id Order id.
 * @return mixed
 */
function wc_get_order( $order_id ) {
	return isset( $GLOBALS['velotok_order_selection_orders'][ $order_id ] )
		? $GLOBALS['velotok_order_selection_orders'][ $order_id ]
		: null;
}

/**
 * Minimal WooCommerce shipping line stub.
 */
class Velotok_Order_Selection_Shipping {
	/**
	 * Method id.
	 *
	 * @var string
	 */
	private $method_id;

	/**
	 * Total cost.
	 *
	 * @var float
	 */
	private $total;

	/**
	 * Meta data.
	 *
	 * @var array
	 */
	private $meta;

	/**
	 * Display name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Constructor.
	 *
	 * @param string $method_id Method id.
	 * @param float  $total     Total.
	 * @param array  $meta      Meta.
	 * @param string $name      Name.
	 */
	public function __construct( $method_id, $total = 0.0, $meta = array(), $name = '' ) {
		$this->method_id = (string) $method_id;
		$this->total     = (float) $total;
		$this->meta      = (array) $meta;
		$this->name      = (string) $name;
	}

	/**
	 * Return the method id.
	 *
	 * @return string
	 */
	public function get_method_id() {
		return $this->method_id;
	}

	/**
	 * Return the total.
	 *
	 * @return float
	 */
	public function get_total() {
		return $this->total;
	}

	/**
	 * Return meta data.
	 *
	 * @return array
	 */
	public function get_meta_data() {
		return $this->meta;
	}

	/**
	 * Return the shipping line name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}
}

/**
 * Minimal WooCommerce order stub.
 */
class Velotok_Order_Selection_Order {
	/**
	 * Order id.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Shipping lines.
	 *
	 * @var array
	 */
	private $shipping;

	/**
	 * Order meta.
	 *
	 * @var array
	 */
	private $meta;

	/**
	 * Constructor.
	 *
	 * @param int   $id       Order id.
	 * @param array $shipping Shipping lines.
	 * @param array $meta     Meta.
	 */
	public function __construct( $id, $shipping = array(), $meta = array() ) {
		$this->id       = (int) $id;
		$this->shipping = (array) $shipping;
		$this->meta     = (array) $meta;
	}

	/**
	 * Return the order id.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Return shipping lines.
	 *
	 * @return array
	 */
	public function get_shipping_methods() {
		return $this->shipping;
	}

	/**
	 * Read order meta.
	 *
	 * @param string $key   Meta key.
	 * @param bool   $single Single.
	 * @return mixed
	 */
	public function get_meta( $key, $single = true ) {
		unset( $single );

		return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : '';
	}
}

$module_path = dirname( __DIR__ ) . '/includes/order-delivery.php';

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль фиксации выбора доставки в заказе.' . PHP_EOL );
	exit( 1 );
}

require_once $module_path;

$failures = array();

/**
 * Assert an order delivery selection condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_delivery_order_selection_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = velotok_core_delivery_zones_contract();

velotok_delivery_order_selection_assert(
	isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-37', $contract['requirement_ids'], true ),
	'Контракт доставки должен ссылаться на TESTEB-37.'
);
velotok_delivery_order_selection_assert(
	isset( $contract['order']['requirement'] ) && 'TESTEB-37' === $contract['order']['requirement'],
	'Раздел заказа должен ссылаться на TESTEB-37.'
);
velotok_delivery_order_selection_assert(
	isset( $contract['order']['persisted'] ) && true === $contract['order']['persisted'],
	'Выбор доставки должен сохраняться в заказе.'
);
velotok_delivery_order_selection_assert(
	isset( $contract['order']['meta']['method'] ) && '_velotok_delivery_method' === $contract['order']['meta']['method'],
	'Способ доставки должен иметь явный ключ меты заказа.'
);
velotok_delivery_order_selection_assert(
	isset( $contract['order']['meta']['selection'] ) && '_velotok_delivery_selection' === $contract['order']['meta']['selection'],
	'Выбор доставки должен сохраняться единым значением.'
);
velotok_delivery_order_selection_assert(
	isset( $contract['order']['visible_to_owner'] ) && true === $contract['order']['visible_to_owner'],
	'Выбор доставки должен быть виден владельцу заказа.'
);

$order_contract = velotok_core_delivery_order_contract();

velotok_delivery_order_selection_assert(
	isset( $order_contract['requirement'] ) && 'TESTEB-37' === $order_contract['requirement'],
	'Контракт выбора доставки должен ссылаться на TESTEB-37.'
);
velotok_delivery_order_selection_assert(
	isset( $order_contract['meta']['method'] ) && '_velotok_delivery_method' === $order_contract['meta']['method'],
	'Контракт выбора доставки должен объявлять ключ способа доставки.'
);
velotok_delivery_order_selection_assert(
	isset( $order_contract['visible_to'] ) && in_array( 'customer', $order_contract['visible_to'], true ) && in_array( 'owner', $order_contract['visible_to'], true ),
	'Выбор доставки должен быть виден покупателю и владельцу.'
);
velotok_delivery_order_selection_assert(
	isset( $GLOBALS['velotok_order_selection_actions']['woocommerce_checkout_update_order_meta'] ),
	'Выбор доставки должен фиксироваться при оформлении заказа WooCommerce.'
);

$GLOBALS['velotok_order_selection_orders'][101] = new Velotok_Order_Selection_Order(
	101,
	array(
		new Velotok_Order_Selection_Shipping( 'flat_rate', 450.0, array(), 'Доставка' ),
	)
);

velotok_core_delivery_persist_order_meta( 101 );

velotok_delivery_order_selection_assert(
	'flat_rate' === get_post_meta( 101, '_velotok_delivery_method', true ),
	'Выбранный способ доставки без ETA должен сохраняться в заказе.'
);
velotok_delivery_order_selection_assert(
	'flat_rate' === get_post_meta( 101, '_velotok_delivery_provider', true ),
	'Провайдер выбранного способа доставки должен сохраняться в заказе.'
);
velotok_delivery_order_selection_assert(
	'Доставка' === get_post_meta( 101, '_velotok_delivery_method_label', true ),
	'Название выбранного способа доставки должно сохраняться в заказе.'
);
velotok_delivery_order_selection_assert(
	450.0 === (float) get_post_meta( 101, '_velotok_delivery_cost', true ),
	'Стоимость выбранного способа доставки должна сохраняться в заказе.'
);

$stored_selection = get_post_meta( 101, '_velotok_delivery_selection', true );
velotok_delivery_order_selection_assert( is_array( $stored_selection ), 'Выбор доставки должен храниться единым значением.' );
velotok_delivery_order_selection_assert( 'flat_rate' === $stored_selection['method_id'], 'Выбор доставки должен содержать способ доставки.' );
velotok_delivery_order_selection_assert( 'flat_rate' === $stored_selection['provider'], 'Выбор доставки должен содержать провайдера.' );
velotok_delivery_order_selection_assert( true === $stored_selection['selected'], 'Выбор доставки должен быть отмечен как выбранный.' );

$read_selection = velotok_core_delivery_order_selection( 101 );
velotok_delivery_order_selection_assert( true === $read_selection['selected'], 'Выбор доставки должен читаться из заказа.' );
velotok_delivery_order_selection_assert( 'flat_rate' === $read_selection['method_id'], 'Способ доставки должен читаться из заказа.' );
velotok_delivery_order_selection_assert( 'flat_rate' === velotok_core_delivery_order_selected_method( 101 ), 'Выбранный способ доставки должен быть доступен владельцу.' );

$GLOBALS['velotok_order_selection_orders'][102] = new Velotok_Order_Selection_Order(
	102,
	array(
		new Velotok_Order_Selection_Shipping(
			'cdek',
			700.0,
			array(
				'_velotok_delivery_provider' => 'cdek',
				'_velotok_eta_min_days'      => 1,
				'_velotok_eta_max_days'      => 3,
				'_velotok_eta_label'         => '1–3 дн.',
			),
			'СДЭК'
		),
	)
);

velotok_core_delivery_persist_order_meta( 102 );
$cdek_selection = velotok_core_delivery_order_selection( 102 );
velotok_delivery_order_selection_assert( 'cdek' === $cdek_selection['method_id'], 'Способ доставки СДЭК должен сохраняться в заказе.' );
velotok_delivery_order_selection_assert( 'cdek' === $cdek_selection['provider'], 'Провайдер СДЭК должен сохраняться в заказе.' );
velotok_delivery_order_selection_assert( isset( $cdek_selection['eta']['min_days'] ) && 1 === (int) $cdek_selection['eta']['min_days'], 'Срок выбранной доставки должен сохраняться вместе с выбором.' );

$GLOBALS['velotok_order_selection_orders'][103] = new Velotok_Order_Selection_Order(
	103,
	array(
		new Velotok_Order_Selection_Shipping(
			'local_pickup',
			0.0,
			array(
				'_velotok_delivery_provider' => 'pickup',
				'_velotok_eta_min_days'      => 0,
				'_velotok_eta_max_days'      => 0,
				'_velotok_eta_label'         => '0 дн.',
			),
			'Самовывоз'
		),
	)
);

velotok_core_delivery_persist_order_meta( 103 );
$pickup_selection = velotok_core_delivery_order_selection( 103 );
velotok_delivery_order_selection_assert( 'local_pickup' === $pickup_selection['method_id'], 'Самовывоз должен сохраняться в заказе.' );
velotok_delivery_order_selection_assert( 'pickup' === $pickup_selection['provider'], 'Самовывоз должен сохраняться с провайдером pickup.' );

$legacy_order = new Velotok_Order_Selection_Order(
	104,
	array(
		new Velotok_Order_Selection_Shipping( 'russian_post:7', 380.0, array(), 'Почта РФ' ),
	)
);
$legacy_selection = velotok_core_delivery_order_selection( $legacy_order );
velotok_delivery_order_selection_assert( true === $legacy_selection['selected'], 'Старый заказ без меты должен отдавать выбранную доставку из shipping methods.' );
velotok_delivery_order_selection_assert( 'russian_post' === $legacy_selection['method_id'], 'Идентификатор доставки должен нормализоваться без номера экземпляра.' );
velotok_delivery_order_selection_assert( 380.0 === (float) $legacy_selection['cost'], 'Стоимость старой доставки должна читаться из shipping method.' );

$empty_selection = velotok_core_delivery_order_selection( new Velotok_Order_Selection_Order( 105 ) );
velotok_delivery_order_selection_assert( false === $empty_selection['selected'], 'Без выбранной доставки заказ не должен выдавать фиктивный способ.' );
velotok_delivery_order_selection_assert( 'DELIVERY_METHOD_NOT_SELECTED' === $empty_selection['code'], 'Отсутствие выбора должно иметь явный код.' );

$GLOBALS['velotok_order_selection_orders'][106] = new Velotok_Order_Selection_Order(
	106,
	array(
		new Velotok_Order_Selection_Shipping( 'free_shipping', 0.0, array(), 'Бесплатная доставка' ),
		new Velotok_Order_Selection_Shipping( 'cdek', 700.0, array(), 'СДЭК' ),
	)
);

velotok_core_delivery_persist_order_meta( 106 );
velotok_delivery_order_selection_assert(
	'free_shipping' === get_post_meta( 106, '_velotok_delivery_method', true ),
	'В заказе должен фиксироваться именно первый выбранный способ доставки.'
);
velotok_delivery_order_selection_assert(
	'free_shipping' === velotok_core_delivery_order_selection( 106 )['method_id'],
	'Последующие shipping methods не должны перезаписывать выбор доставки.'
);

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок фиксации доставки в заказе не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: выбор доставки фиксируется в заказе (TESTEB-37).' . PHP_EOL;
