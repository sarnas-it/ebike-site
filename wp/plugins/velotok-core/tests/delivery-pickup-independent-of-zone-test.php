<?php
/**
 * Integration test for pickup being available independently of shipping zones.
 *
 * Requirement TESTEB-34 / spec delivery:
 * «Самовывоз SHALL быть доступен независимо от зоны доставки».
 *
 * The test stubs the small WordPress/WooCommerce surface used by the delivery
 * integration and proves that:
 * - pickup is added to checkout rates when no zone matched the address;
 * - an ambiguous address also keeps pickup available;
 * - pickup is not shown outside Russia;
 * - an already offered pickup rate is not duplicated;
 * - the zone helper keeps pickup for out-of-zone and ambiguous addresses.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-pickup-independent-of-zone-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_delivery_independent_filters'] = array();
$GLOBALS['velotok_delivery_independent_actions'] = array();

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

	$GLOBALS['velotok_delivery_independent_actions'][ $hook ][] = $callback;

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

	$GLOBALS['velotok_delivery_independent_filters'][ $hook ][] = $callback;

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

	if ( empty( $GLOBALS['velotok_delivery_independent_filters'][ $hook ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['velotok_delivery_independent_filters'][ $hook ] as $callback ) {
		$args[1] = $value;
		$value   = call_user_func_array( $callback, array_slice( $args, 1 ) );
	}

	return $value;
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

if ( ! class_exists( 'WC_Shipping_Rate' ) ) {
	/**
	 * Minimal WooCommerce shipping rate stub.
	 */
	class WC_Shipping_Rate {
		/**
		 * Rate id.
		 *
		 * @var string
		 */
		public $id;

		/**
		 * Rate label.
		 *
		 * @var string
		 */
		public $label;

		/**
		 * Rate cost.
		 *
		 * @var float
		 */
		public $cost;

		/**
		 * Rate metadata.
		 *
		 * @var array
		 */
		public $meta_data;

		/**
		 * Method id.
		 *
		 * @var string
		 */
		public $method_id;

		/**
		 * Constructor.
		 *
		 * @param string $id        Rate id.
		 * @param string $label     Label.
		 * @param float  $cost      Cost.
		 * @param array  $meta_data Metadata.
		 * @param string $method_id Method id.
		 */
		public function __construct( $id, $label = '', $cost = 0, $meta_data = array(), $method_id = '' ) {
			$this->id        = (string) $id;
			$this->label     = (string) $label;
			$this->cost      = (float) $cost;
			$this->meta_data = is_array( $meta_data ) ? $meta_data : array();
			$this->method_id = (string) $method_id;
		}

		/**
		 * Return the rate id.
		 *
		 * @return string
		 */
		public function get_id() {
			return $this->id;
		}

		/**
		 * Return the label.
		 *
		 * @return string
		 */
		public function get_label() {
			return $this->label;
		}

		/**
		 * Return the cost.
		 *
		 * @return float
		 */
		public function get_cost() {
			return $this->cost;
		}

		/**
		 * Return metadata.
		 *
		 * @return array
		 */
		public function get_meta_data() {
			return $this->meta_data;
		}

		/**
		 * Return the method id.
		 *
		 * @return string
		 */
		public function get_method_id() {
			return $this->method_id;
		}
	}
}

$integration_path = dirname( __DIR__ ) . '/includes/delivery-woocommerce.php';
$override         = getenv( 'VELOTOK_DELIVERY_PICKUP_INDEPENDENT_OF_ZONE_STUB' );

if ( is_string( $override ) && '' !== $override ) {
	$integration_path = $override;
}

if ( ! is_readable( $integration_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль интеграции доставки с WooCommerce.' . PHP_EOL );
	exit( 1 );
}

require_once $integration_path;

$failures = array();

/**
 * Assert a pickup independence condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_delivery_pickup_independent_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Find a rate by shipping method id.
 *
 * @param mixed  $rates     Rates.
 * @param string $method_id Method id.
 * @return mixed|null
 */
function velotok_delivery_pickup_independent_find_rate( $rates, $method_id ) {
	if ( ! is_array( $rates ) ) {
		return null;
	}

	foreach ( $rates as $rate ) {
		if ( velotok_core_delivery_rate_method_id( $rate ) === $method_id ) {
			return $rate;
		}
	}

	return null;
}

$contract = velotok_core_delivery_zones_contract();

velotok_delivery_pickup_independent_assert(
	isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-34', $contract['requirement_ids'], true ),
	'Контракт доставки должен ссылаться на TESTEB-34.'
);
velotok_delivery_pickup_independent_assert(
	isset( $contract['pickup']['requirement'] ) && 'TESTEB-34' === $contract['pickup']['requirement'],
	'Раздел самовывоза должен ссылаться на TESTEB-34.'
);
velotok_delivery_pickup_independent_assert(
	isset( $contract['pickup']['independent_of_zone'] ) && true === $contract['pickup']['independent_of_zone'],
	'Самовывоз должен быть объявлен независимым от зоны.'
);
velotok_delivery_pickup_independent_assert(
	isset( $contract['pickup']['zone_required'] ) && false === $contract['pickup']['zone_required'],
	'Для самовывоза зона доставки не должна быть обязательной.'
);
velotok_delivery_pickup_independent_assert(
	isset( $contract['out_of_zone']['pickup_available'] ) && true === $contract['out_of_zone']['pickup_available'],
	'Вне зон самовывоз должен оставаться доступным.'
);
velotok_delivery_pickup_independent_assert(
	isset( $contract['ambiguous_address']['pickup_available'] ) && true === $contract['ambiguous_address']['pickup_available'],
	'При неоднозначном адресе самовывоз должен оставаться доступным.'
);

velotok_delivery_pickup_independent_assert(
	function_exists( 'velotok_core_delivery_add_pickup_rate' ),
	'Должен существовать помощник добавления самовывоза независимо от зоны.'
);
velotok_delivery_pickup_independent_assert(
	isset( $GLOBALS['velotok_delivery_independent_filters']['woocommerce_package_rates'] ),
	'Самовывоз должен добавляться через фильтр способов доставки WooCommerce.'
);

$ru_package = array(
	'destination'  => array(
		'country'  => 'RU',
		'postcode' => '999999',
		'city'     => 'Неизвестск',
	),
	'contents_cost' => 1000,
	'contents'      => array(),
);

$ru_rates = apply_filters( 'woocommerce_package_rates', array(), $ru_package );
$ru_pickup = velotok_delivery_pickup_independent_find_rate( $ru_rates, 'local_pickup' );

velotok_delivery_pickup_independent_assert( null !== $ru_pickup, 'Вне зон самовывоз должен добавляться в checkout.' );

if ( null !== $ru_pickup ) {
	$pickup_meta = is_object( $ru_pickup ) && method_exists( $ru_pickup, 'get_meta_data' ) ? $ru_pickup->get_meta_data() : array();

	velotok_delivery_pickup_independent_assert( 0.0 === (float) $ru_pickup->get_cost(), 'Самовывоз должен быть бесплатным.' );
	velotok_delivery_pickup_independent_assert( 'Самовывоз' === (string) $ru_pickup->get_label(), 'Самовывоз должен иметь понятную подпись.' );
	velotok_delivery_pickup_independent_assert(
		isset( $pickup_meta['_velotok_delivery_independent_of_zone'] ) && 'yes' === $pickup_meta['_velotok_delivery_independent_of_zone'],
		'Тариф самовывоза должен быть помечен независимым от зоны.'
	);
}

$ambiguous_rates = apply_filters( 'woocommerce_package_rates', array(), array( 'destination' => array() ) );
velotok_delivery_pickup_independent_assert(
	null !== velotok_delivery_pickup_independent_find_rate( $ambiguous_rates, 'local_pickup' ),
	'При неоднозначном адресе самовывоз должен добавляться в checkout.'
);

$foreign_package = array(
	'destination'  => array(
		'country'  => 'KZ',
		'postcode' => '050000',
		'city'     => 'Алматы',
	),
	'contents_cost' => 1000,
	'contents'      => array(),
);
$foreign_rates = apply_filters( 'woocommerce_package_rates', array(), $foreign_package );

velotok_delivery_pickup_independent_assert(
	null === velotok_delivery_pickup_independent_find_rate( $foreign_rates, 'local_pickup' ),
	'За пределами России самовывоз не должен добавляться.'
);

$existing_pickup = new WC_Shipping_Rate( 'local_pickup:1', 'Самовывоз из зоны', 0, array(), 'local_pickup' );
$existing_rates  = apply_filters( 'woocommerce_package_rates', array( 'local_pickup:1' => $existing_pickup ), $ru_package );

velotok_delivery_pickup_independent_assert( 1 === count( $existing_rates ), 'Существующий самовывоз не должен дублироваться.' );
velotok_delivery_pickup_independent_assert(
	isset( $existing_rates['local_pickup:1'] ) && $existing_rates['local_pickup:1'] === $existing_pickup,
	'Существующий самовывоз из зоны должен сохраняться без подмены.'
);

$zones = array(
	array(
		'zone_id'          => 20,
		'zone_name'        => 'Казахстан',
		'zone_order'       => 1,
		'zone_locations'   => array(
			array( 'type' => 'country', 'code' => 'KZ' ),
		),
		'shipping_methods' => array(
			array(
				'method_id' => 'cdek',
				'title'     => 'СДЭК',
				'enabled'   => true,
				'settings'  => array( 'base' => 900, 'per_kg' => 150 ),
			),
		),
	),
);

$out_of_zone = velotok_core_delivery_zone_methods(
	array( 'country' => 'RU', 'postcode' => '999999', 'city' => 'Неизвестск' ),
	$zones,
	array( 'order' => array( 'subtotal' => 1000, 'weight' => 1 ) )
);
velotok_delivery_pickup_independent_assert( false === $out_of_zone['zone_matched'], 'Адрес вне зон не должен попадать в зону.' );
velotok_delivery_pickup_independent_assert( isset( $out_of_zone['methods']['local_pickup'] ), 'Вне зон helper должен предлагать самовывоз.' );
velotok_delivery_pickup_independent_assert( true === $out_of_zone['pickup'], 'Вне зон флаг самовывоза должен быть включён.' );

$ambiguous_result = velotok_core_delivery_zone_methods( array(), $zones, array( 'order' => array( 'subtotal' => 1000, 'weight' => 1 ) ) );
velotok_delivery_pickup_independent_assert(
	array_keys( $out_of_zone['methods'] ) === array_keys( $ambiguous_result['methods'] ),
	'Адрес вне зон и неоднозначный адрес должны давать одинаковые способы доставки.'
);
velotok_delivery_pickup_independent_assert( isset( $ambiguous_result['methods']['local_pickup'] ), 'При неоднозначном адресе helper должен предлагать самовывоз.' );

$foreign_result = velotok_core_delivery_zone_methods(
	array( 'country' => 'KZ', 'postcode' => '050000', 'city' => 'Алматы' ),
	$zones,
	array( 'order' => array( 'subtotal' => 1000, 'weight' => 1 ) )
);
velotok_delivery_pickup_independent_assert( ! isset( $foreign_result['methods']['local_pickup'] ), 'За пределами России helper не должен предлагать самовывоз.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: самовывоз доступен независимо от зоны доставки и не показывается за пределами России.' . PHP_EOL;
