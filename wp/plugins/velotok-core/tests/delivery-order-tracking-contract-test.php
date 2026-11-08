<?php
/**
 * Contract test for the delivery tracking number stored on an order.
 *
 * Requirement TESTEB-38 / spec delivery:
 * «Трек-номер SHALL сохраняться в заказе и быть виден владельцу».
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-order-tracking-contract-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_tracking_actions'] = array();
$GLOBALS['velotok_tracking_filters'] = array();
$GLOBALS['velotok_tracking_meta']    = array();
$GLOBALS['velotok_tracking_orders']  = array();

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	unset( $priority, $args );

	$GLOBALS['velotok_tracking_actions'][ $hook ][] = $callback;

	return true;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	unset( $priority, $args );

	$GLOBALS['velotok_tracking_filters'][ $hook ][] = $callback;

	return true;
}

function apply_filters( $hook, $value ) {
	$args = func_get_args();

	if ( empty( $GLOBALS['velotok_tracking_filters'][ $hook ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['velotok_tracking_filters'][ $hook ] as $callback ) {
		$args[1] = $value;
		$value   = call_user_func_array( $callback, array_slice( $args, 1 ) );
	}

	return $value;
}

function __( $text ) {
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function absint( $value ) {
	return abs( (int) $value );
}

function get_option( $key, $default = false ) {
	unset( $key );

	return $default;
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	unset( $single );

	if ( isset( $GLOBALS['velotok_tracking_meta'][ $post_id ][ $key ] ) ) {
		return $GLOBALS['velotok_tracking_meta'][ $post_id ][ $key ];
	}

	return '';
}

function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['velotok_tracking_meta'][ $post_id ][ $key ] = $value;

	return true;
}

function wc_get_order( $order_id ) {
	return isset( $GLOBALS['velotok_tracking_orders'][ $order_id ] )
		? $GLOBALS['velotok_tracking_orders'][ $order_id ]
		: null;
}

class Velotok_Tracking_Shipping {
	private $method_id;
	private $total;
	private $meta;
	private $name;

	public function __construct( $method_id, $total = 0.0, $meta = array(), $name = '' ) {
		$this->method_id = (string) $method_id;
		$this->total     = (float) $total;
		$this->meta      = (array) $meta;
		$this->name      = (string) $name;
	}

	public function get_method_id() {
		return $this->method_id;
	}

	public function get_total() {
		return $this->total;
	}

	public function get_meta_data() {
		return $this->meta;
	}

	public function get_name() {
		return $this->name;
	}

	public function get_meta( $key, $single = true ) {
		unset( $single );

		return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : '';
	}
}

class Velotok_Tracking_Order {
	private $id;
	private $shipping;
	private $meta;

	public function __construct( $id, $shipping = array(), $meta = array() ) {
		$this->id       = (int) $id;
		$this->shipping = (array) $shipping;
		$this->meta     = (array) $meta;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_shipping_methods() {
		return $this->shipping;
	}

	public function get_meta( $key, $single = true ) {
		unset( $single );

		return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : '';
	}
}

$module_path = getenv( 'VELOTOK_DELIVERY_ORDER_TRACKING_STUB' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__ ) . '/includes/order-delivery.php';
}

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль фиксации доставки и трек-номера в заказе.' . PHP_EOL );
	exit( 1 );
}

require_once $module_path;

$failures = array();

function velotok_tracking_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = velotok_core_delivery_zones_contract();

velotok_tracking_assert(
	isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-38', $contract['requirement_ids'], true ),
	'Контракт доставки должен ссылаться на TESTEB-38.'
);
velotok_tracking_assert(
	isset( $contract['order']['tracking_requirement'] ) && 'TESTEB-38' === $contract['order']['tracking_requirement'],
	'Раздел заказа должен ссылаться на требование о трек-номере.'
);
velotok_tracking_assert(
	isset( $contract['order']['meta']['tracking'] ) && '_velotok_delivery_tracking_number' === $contract['order']['meta']['tracking'],
	'Трек-номер должен иметь явный ключ меты заказа.'
);
velotok_tracking_assert(
	isset( $contract['order']['tracking_visible_to_owner'] ) && true === $contract['order']['tracking_visible_to_owner'],
	'Трек-номер должен быть виден владельцу заказа.'
);
velotok_tracking_assert(
	isset( $contract['tracking']['requirement'] ) && 'TESTEB-38' === $contract['tracking']['requirement'],
	'Контракт должен содержать раздел трек-номера.'
);

$order_contract = velotok_core_delivery_order_contract();

velotok_tracking_assert(
	isset( $order_contract['requirement_ids'] ) && in_array( 'TESTEB-38', $order_contract['requirement_ids'], true ),
	'Контракт выбора доставки должен ссылаться на TESTEB-38.'
);
velotok_tracking_assert(
	isset( $order_contract['tracking_requirement'] ) && 'TESTEB-38' === $order_contract['tracking_requirement'],
	'Контракт заказа должен объявлять требование о трек-номере.'
);
velotok_tracking_assert(
	isset( $order_contract['meta']['tracking'] ) && '_velotok_delivery_tracking_number' === $order_contract['meta']['tracking'],
	'Контракт заказа должен объявлять ключ меты трек-номера.'
);
velotok_tracking_assert(
	isset( $order_contract['tracking_visible_to_owner'] ) && true === $order_contract['tracking_visible_to_owner'],
	'Контракт заказа должен объявлять трек-номер видимым владельцу.'
);

$GLOBALS['velotok_tracking_orders'][201] = new Velotok_Tracking_Order(
	201,
	array(
		new Velotok_Tracking_Shipping(
			'cdek',
			700.0,
			array(
				'_velotok_delivery_provider'          => 'cdek',
				'_velotok_delivery_tracking_number'   => 'CDEK-TRACK-201',
			),
			'СДЭК'
		),
	)
);

velotok_core_delivery_persist_order_meta( 201 );

velotok_tracking_assert(
	'CDEK-TRACK-201' === get_post_meta( 201, '_velotok_delivery_tracking_number', true ),
	'Трек-номер из строки доставки должен сохраняться в заказе.'
);
velotok_tracking_assert(
	'CDEK-TRACK-201' === velotok_core_delivery_order_tracking_number( 201 ),
	'Сохранённый трек-номер должен читаться из заказа.'
);
velotok_tracking_assert(
	true === velotok_core_delivery_order_tracking_visible_to_owner( 201 ),
	'Трек-номер сохранённого заказа должен быть виден владельцу.'
);

$selection = velotok_core_delivery_order_selection( 201 );
velotok_tracking_assert(
	isset( $selection['tracking_number'] ) && 'CDEK-TRACK-201' === $selection['tracking_number'],
	'Выбор доставки в заказе должен содержать трек-номер.'
);
velotok_tracking_assert(
	isset( $selection['tracking']['visible_to_owner'] ) && true === $selection['tracking']['visible_to_owner'],
	'Выбор доставки должен помечать трек-номер видимым владельцу.'
);

$GLOBALS['velotok_tracking_orders'][202] = new Velotok_Tracking_Order(
	202,
	array(
		new Velotok_Tracking_Shipping(
			'russian_post',
			380.0,
			array(
				'tracking_number' => 'RP-TRACK-202',
			),
			'Почта РФ'
		),
	)
);

velotok_core_delivery_persist_order_meta( 202 );

velotok_tracking_assert(
	'RP-TRACK-202' === velotok_core_delivery_order_tracking_number( 202 ),
	'Старый ключ трек-номера в строке доставки должен читаться.'
);
velotok_tracking_assert(
	'RP-TRACK-202' === get_post_meta( 202, '_velotok_delivery_tracking_number', true ),
	'Старый трек-номер должен переноситься в явную мету заказа.'
);

$GLOBALS['velotok_tracking_orders'][203] = new Velotok_Tracking_Order( 203 );

velotok_core_delivery_order_tracking_number_set( 203, 'MANUAL-TRACK-203' );

velotok_tracking_assert(
	'MANUAL-TRACK-203' === velotok_core_delivery_order_tracking_number( 203 ),
	'Установленный трек-номер должен сохраняться в заказе.'
);
velotok_tracking_assert(
	'MANUAL-TRACK-203' === velotok_core_delivery_order_tracking_data( 203 )['tracking_number'],
	'Данные трек-номера должны отдавать сохранённое значение.'
);

$GLOBALS['velotok_tracking_orders'][204] = new Velotok_Tracking_Order(
	204,
	array(
		new Velotok_Tracking_Shipping(
			'cdek',
			700.0,
			array(
				'_tracking_number' => 'LEGACY-TRACK-204',
			),
			'СДЭК'
		),
	)
);

velotok_tracking_assert(
	'LEGACY-TRACK-204' === velotok_core_delivery_order_tracking_number( 204 ),
	'Трек-номер должен читаться из legacy-меты строки доставки без перезаписи заказа.'
);

$GLOBALS['velotok_tracking_orders'][205] = new Velotok_Tracking_Order( 205 );
$empty_tracking = velotok_core_delivery_order_tracking_data( 205 );

velotok_tracking_assert( '' === $empty_tracking['tracking_number'], 'Без трек-номера заказ не должен выдавать фиктивное значение.' );
velotok_tracking_assert( false === $empty_tracking['has_tracking'], 'Отсутствие трек-номера должно быть явным.' );
velotok_tracking_assert( false === velotok_core_delivery_order_tracking_visible_to_owner( 205 ), 'Пустой трек-номер не должен считаться видимым.' );
velotok_tracking_assert( '' === velotok_core_delivery_order_tracking_html( 205 ), 'Пустой трек-номер не должен выводиться.' );

$html = velotok_core_delivery_order_tracking_html( 201 );
velotok_tracking_assert( false !== strpos( $html, 'CDEK-TRACK-201' ), 'Владелец должен видеть трек-номер в деталях заказа.' );
velotok_tracking_assert( false !== strpos( $html, 'data-visible-to-owner="true"' ), 'Вывод трек-номера должен явно помечать видимость владельцу.' );

ob_start();
velotok_core_delivery_order_tracking( 201 );
$rendered = ob_get_clean();
velotok_tracking_assert( false !== strpos( $rendered, 'CDEK-TRACK-201' ), 'Трек-номер должен выводиться в заказе.' );

velotok_tracking_assert(
	isset( $GLOBALS['velotok_tracking_actions']['woocommerce_order_details_after_order_table'] ),
	'Трек-номер должен выводиться в деталях заказа WooCommerce.'
);
velotok_tracking_assert(
	isset( $GLOBALS['velotok_tracking_actions']['woocommerce_admin_order_data_after_shipping_address'] ),
	'Владелец магазина должен видеть трек-номер в админке заказа.'
);

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок трек-номера в заказе не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: трек-номер сохраняется в заказе и виден владельцу (TESTEB-38).' . PHP_EOL;
