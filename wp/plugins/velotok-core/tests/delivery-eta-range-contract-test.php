<?php
/**
 * Contract test for the delivery ETA range.
 *
 * Requirement TESTEB-31 / spec delivery:
 * «Срок получения заказа SHALL отображаться и храниться диапазоном».
 *
 * The test proves that the ETA is normalized to min/max boundaries, is shown
 * as a range at checkout and on the order details screen, and is persisted on
 * the order as a range.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-eta-range-contract-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_eta_actions'] = array();
$GLOBALS['velotok_eta_filters'] = array();
$GLOBALS['velotok_eta_meta']    = array();
$GLOBALS['velotok_eta_orders']  = array();
$GLOBALS['velotok_eta_notices'] = array();

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

	$GLOBALS['velotok_eta_actions'][ $hook ][] = $callback;

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

	$GLOBALS['velotok_eta_filters'][ $hook ][] = $callback;

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

	if ( empty( $GLOBALS['velotok_eta_filters'][ $hook ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['velotok_eta_filters'][ $hook ] as $callback ) {
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

	if ( isset( $GLOBALS['velotok_eta_meta'][ $post_id ][ $key ] ) ) {
		return $GLOBALS['velotok_eta_meta'][ $post_id ][ $key ];
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
	$GLOBALS['velotok_eta_meta'][ $post_id ][ $key ] = $value;

	return true;
}

/**
 * Load an order for the persistence test.
 *
 * @param int $order_id Order id.
 * @return mixed
 */
function wc_get_order( $order_id ) {
	return isset( $GLOBALS['velotok_eta_orders'][ $order_id ] ) ? $GLOBALS['velotok_eta_orders'][ $order_id ] : null;
}

/**
 * Record a WooCommerce notice.
 *
 * @param string $message Message.
 * @param string $type    Type.
 * @return void
 */
function wc_add_notice( $message, $type = 'success' ) {
	$GLOBALS['velotok_eta_notices'][] = array(
		'message' => (string) $message,
		'type'    => (string) $type,
	);
}

/**
 * Minimal order stub.
 */
class Velotok_Eta_Test_Order {
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
	 * Constructor.
	 *
	 * @param int   $id       Order id.
	 * @param array $shipping Shipping lines.
	 */
	public function __construct( $id, $shipping ) {
		$this->id       = (int) $id;
		$this->shipping = $shipping;
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
	 * Return the shipping lines.
	 *
	 * @return array
	 */
	public function get_shipping_methods() {
		return $this->shipping;
	}
}

/**
 * Minimal shipping line stub.
 */
class Velotok_Eta_Test_Shipping {
	/**
	 * Method id.
	 *
	 * @var string
	 */
	private $method_id;

	/**
	 * Cost.
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
	 * Constructor.
	 *
	 * @param string $method_id Method id.
	 * @param float  $total     Cost.
	 * @param array  $meta      Meta data.
	 */
	public function __construct( $method_id, $total, $meta ) {
		$this->method_id = (string) $method_id;
		$this->total     = (float) $total;
		$this->meta      = (array) $meta;
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
	 * Return the cost.
	 *
	 * @return float
	 */
	public function get_total() {
		return $this->total;
	}

	/**
	 * Return the meta data.
	 *
	 * @return array
	 */
	public function get_meta_data() {
		return $this->meta;
	}
}

$module_path = getenv( 'VELOTOK_DELIVERY_ETA_STUB' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__ ) . '/includes/delivery-zones.php';
}

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль сроков доставки.' . PHP_EOL );
	exit( 1 );
}

require_once $module_path;

$woocommerce_path = getenv( 'VELOTOK_DELIVERY_ETA_WOOCOMMERCE' );

if ( ! is_string( $woocommerce_path ) || '' === $woocommerce_path ) {
	$sibling = dirname( $module_path ) . '/delivery-woocommerce.php';

	$woocommerce_path = is_readable( $sibling )
		? $sibling
		: dirname( __DIR__ ) . '/includes/delivery-woocommerce.php';
}

if ( ! is_readable( $woocommerce_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль доставки WooCommerce.' . PHP_EOL );
	exit( 1 );
}

require_once $woocommerce_path;

$failures = array();

/**
 * Assert an ETA range condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_delivery_eta_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = velotok_core_delivery_zones_contract();

velotok_delivery_eta_assert(
	isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-31', $contract['requirement_ids'], true ),
	'Контракт доставки должен ссылаться на TESTEB-31.'
);
velotok_delivery_eta_assert(
	isset( $contract['eta']['requirement'] ) && 'TESTEB-31' === $contract['eta']['requirement'],
	'Раздел срока доставки должен ссылаться на TESTEB-31.'
);
velotok_delivery_eta_assert(
	isset( $contract['eta']['format'] ) && 'range' === $contract['eta']['format'],
	'Срок доставки должен храниться диапазоном.'
);
velotok_delivery_eta_assert(
	isset( $contract['eta']['order_meta']['range'] ) && '_velotok_delivery_eta' === $contract['eta']['order_meta']['range'],
	'Диапазон срока доставки должен иметь явный ключ меты заказа.'
);

$range = velotok_core_delivery_zones_eta_range(
	array(
		'settings' => array(
			'eta_min_days' => 1,
			'eta_max_days' => 3,
		),
	)
);
velotok_delivery_eta_assert( 1 === $range['min_days'] && 3 === $range['max_days'], 'Срок доставки должен возвращаться двумя границами.' );
velotok_delivery_eta_assert( array( 'min' => 1, 'max' => 3 ) === $range['range'], 'Диапазон срока должен быть представлен min/max.' );
velotok_delivery_eta_assert( '1–3 дн.' === $range['label'], 'Срок доставки должен отображаться диапазоном.' );

$reversed = velotok_core_delivery_zones_eta_range(
	array(
		'eta' => array(
			'min_days' => 5,
			'max_days' => 2,
		),
	)
);
velotok_delivery_eta_assert( 2 === $reversed['min_days'] && 5 === $reversed['max_days'], 'Границы срока должны нормализоваться по возрастанию.' );

$text_range = velotok_core_delivery_zones_eta_range( array( 'eta' => '2–6 дн.' ) );
velotok_delivery_eta_assert( 2 === $text_range['min_days'] && 6 === $text_range['max_days'], 'Текстовый диапазон срока должен разбираться в две границы.' );
velotok_delivery_eta_assert( '2–6 дн.' === $text_range['label'], 'Текстовый диапазон срока должен отображаться диапазоном.' );

$equal = velotok_core_delivery_zones_eta_range(
	array(
		'settings' => array(
			'eta_min_days' => 4,
			'eta_max_days' => 4,
		),
	)
);
velotok_delivery_eta_assert( 4 === $equal['min_days'] && 4 === $equal['max_days'], 'Равные границы должны храниться диапазоном.' );
velotok_delivery_eta_assert( '4 дн.' === $equal['label'], 'Равные границы должны отображаться одним сроком.' );

$blank_primary = velotok_core_delivery_zones_eta_range(
	array(
		'eta_min_days' => '',
		'settings'     => array(
			'eta_min_days' => 2,
			'eta_max_days' => 8,
		),
	)
);
velotok_delivery_eta_assert( 2 === $blank_primary['min_days'] && 8 === $blank_primary['max_days'], 'Пустая основная граница не должна скрывать настроенный диапазон.' );

$zones = array(
	array(
		'zone_id'          => 10,
		'zone_name'        => 'Москва',
		'zone_order'       => 1,
		'zone_locations'   => array(
			array( 'type' => 'postcode', 'code' => '101*' ),
		),
		'shipping_methods' => array(
			array(
				'instance_id' => 101,
				'method_id'   => 'cdek',
				'title'       => 'СДЭК',
				'enabled'     => true,
				'settings'    => array(
					'base'         => 500,
					'per_kg'       => 100,
					'eta_min_days' => 1,
					'eta_max_days' => 3,
				),
			),
		),
	),
);

$options = velotok_core_delivery_zone_methods(
	array(
		'country'  => 'RU',
		'postcode' => '101000',
		'city'     => 'Москва',
	),
	$zones,
	array( 'order' => array( 'subtotal' => 1000, 'weight' => 1 ) )
);
$cdek = $options['methods']['cdek'];

velotok_delivery_eta_assert( 1 === $cdek['eta']['min_days'] && 3 === $cdek['eta']['max_days'], 'Способ доставки из зоны должен отдавать срок диапазоном.' );
velotok_delivery_eta_assert( '1–3 дн.' === $cdek['eta_label'], 'Способ доставки должен отдавать подпись срока диапазоном.' );
velotok_delivery_eta_assert( '1–3 дн.' === $cdek['delivery_time'], 'Срок получения должен отображаться диапазоном в способе доставки.' );
velotok_delivery_eta_assert( array( 'min' => 1, 'max' => 3 ) === $cdek['eta_range'], 'Способ доставки должен отдавать диапазон срока.' );

$GLOBALS['velotok_eta_orders'][42] = new Velotok_Eta_Test_Order(
	42,
	array(
		new Velotok_Eta_Test_Shipping(
			'cdek',
			700.0,
			array(
				'_velotok_eta_min_days' => 1,
				'_velotok_eta_max_days' => 3,
				'_velotok_eta_label'    => '1–3 дн.',
			)
		),
	)
);

velotok_core_delivery_persist_order_meta( 42 );

$stored = get_post_meta( 42, '_velotok_delivery_eta', true );
velotok_delivery_eta_assert( is_array( $stored ), 'Диапазон срока должен сохраняться в заказе единым значением.' );
velotok_delivery_eta_assert( 1 === $stored['min_days'] && 3 === $stored['max_days'], 'В заказе должны сохраняться обе границы срока.' );
velotok_delivery_eta_assert( '1–3 дн.' === $stored['label'], 'В заказе должна сохраняться подпись срока диапазоном.' );
velotok_delivery_eta_assert( 1 === (int) get_post_meta( 42, '_velotok_delivery_eta_min_days', true ), 'Минимальный срок должен сохраняться отдельной метой.' );
velotok_delivery_eta_assert( 3 === (int) get_post_meta( 42, '_velotok_delivery_eta_max_days', true ), 'Максимальный срок должен сохраняться отдельной метой.' );
velotok_delivery_eta_assert( '1–3 дн.' === get_post_meta( 42, '_velotok_delivery_eta_label', true ), 'Подпись срока должна сохраняться в заказе.' );

$order_eta = velotok_core_delivery_order_eta_range( 42 );
velotok_delivery_eta_assert( 1 === $order_eta['min_days'] && 3 === $order_eta['max_days'], 'Срок из заказа должен читаться диапазоном.' );
velotok_delivery_eta_assert( '1–3 дн.' === $order_eta['label'], 'Срок из заказа должен отображаться диапазоном.' );

$html = velotok_core_delivery_order_eta_html( 42 );
velotok_delivery_eta_assert( false !== strpos( $html, '1–3 дн.' ), 'Владелец заказа должен видеть срок получения диапазоном.' );
velotok_delivery_eta_assert( isset( $GLOBALS['velotok_eta_actions']['woocommerce_after_shipping_rate'] ), 'Срок должен выводиться рядом со способом доставки в checkout.' );
velotok_delivery_eta_assert( isset( $GLOBALS['velotok_eta_actions']['woocommerce_order_details_after_order_table'] ), 'Срок должен выводиться в деталях заказа.' );

$GLOBALS['velotok_eta_meta'][43] = array(
	'_velotok_delivery_eta_min_days' => 3,
	'_velotok_delivery_eta_max_days' => 7,
	'_velotok_delivery_eta_label'    => '3–7 дн.',
);
$legacy = velotok_core_delivery_order_eta_range( 43 );
velotok_delivery_eta_assert( 3 === $legacy['min_days'] && 7 === $legacy['max_days'], 'Старые отдельные меты срока должны читаться диапазоном.' );
velotok_delivery_eta_assert( '3–7 дн.' === $legacy['label'], 'Старая подпись срока должна сохраняться.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: срок получения отображается и хранится диапазоном.' . PHP_EOL;
