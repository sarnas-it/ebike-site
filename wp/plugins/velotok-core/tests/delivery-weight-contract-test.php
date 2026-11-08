<?php
/**
 * Contract test for the order weight used by delivery tariffs.
 *
 * Requirement TESTEB-30 / spec delivery:
 * «Вес заказа для тарифа SHALL рассчитываться однозначно».
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-weight-contract-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$module_path = getenv( 'VELOTOK_DELIVERY_WEIGHT_STUB' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__ ) . '/includes/delivery-zones.php';
}

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль расчёта веса доставки.' . PHP_EOL );
	exit( 1 );
}

require_once $module_path;

$failures = array();

/**
 * Assert an order weight condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_delivery_weight_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Build a zone with one СДЭК method.
 *
 * @param array $settings Method settings.
 * @return array
 */
function velotok_delivery_weight_zone( $settings ) {
	return array(
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
					'settings'    => $settings,
				),
			),
		),
	);
}

/**
 * Minimal product stub with a WooCommerce-like weight getter.
 */
class Velotok_Delivery_Weight_Product {
	/**
	 * Product weight.
	 *
	 * @var mixed
	 */
	private $weight;

	/**
	 * Constructor.
	 *
	 * @param mixed $weight Weight.
	 */
	public function __construct( $weight ) {
		$this->weight = $weight;
	}

	/**
	 * Return the product weight.
	 *
	 * @return mixed
	 */
	public function get_weight() {
		return $this->weight;
	}
}

$contract = velotok_core_delivery_zones_contract();

velotok_delivery_weight_assert(
	isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-30', $contract['requirement_ids'], true ),
	'Контракт доставки должен ссылаться на TESTEB-30.'
);
velotok_delivery_weight_assert(
	isset( $contract['weight']['requirement'] ) && 'TESTEB-30' === $contract['weight']['requirement'],
	'Раздел веса должен ссылаться на TESTEB-30.'
);
velotok_delivery_weight_assert( 'kg' === $contract['weight']['unit'], 'Вес заказа должен считаться в килограммах.' );
velotok_delivery_weight_assert( 'ceil' === $contract['weight']['rounding'], 'Вес для тарифа должен округляться вверх.' );
velotok_delivery_weight_assert( 'DELIVERY_WEIGHT_AMBIGUOUS' === $contract['weight']['ambiguous_code'], 'Неоднозначный вес должен иметь явный код ошибки.' );

$direct = velotok_core_delivery_zones_order_weight( array( 'weight' => 2.5 ) );
velotok_delivery_weight_assert( true === $direct['ok'] && 2.5 === (float) $direct['weight'], 'Вес заказа должен браться из order.weight.' );
velotok_delivery_weight_assert( 'order.weight' === $direct['source'], 'Источником веса должен быть order.weight.' );

$string_weight = velotok_core_delivery_zones_order_weight( array( 'weight' => '2.4' ) );
velotok_delivery_weight_assert( true === $string_weight['ok'] && 2.4 === (float) $string_weight['weight'], 'Числовой строковый вес должен приниматься.' );

$comma_weight = velotok_core_delivery_zones_order_weight( array( 'weight' => '1,5' ) );
velotok_delivery_weight_assert( true === $comma_weight['ok'] && 1.5 === (float) $comma_weight['weight'], 'Десятичная запятая не должна делать вес неоднозначным.' );

$total_weight = velotok_core_delivery_zones_order_weight( array( 'total_weight' => 3 ) );
velotok_delivery_weight_assert( true === $total_weight['ok'] && 3.0 === (float) $total_weight['weight'], 'Альтернативное поле total_weight должно читаться однозначно.' );

$empty_primary_weight = velotok_core_delivery_zones_order_weight(
	array(
		'weight'       => '',
		'total_weight' => 2.7,
	)
);
velotok_delivery_weight_assert( true === $empty_primary_weight['ok'] && 2.7 === (float) $empty_primary_weight['weight'], 'Пустое поле weight не должно скрывать заполненное total_weight.' );

$malformed_weight = velotok_core_delivery_zones_order_weight( array( 'weight' => 'тяжёлый' ) );
velotok_delivery_weight_assert( false === $malformed_weight['ok'], 'Нечисловой вес заказа не должен молча становиться нулём.' );
velotok_delivery_weight_assert( 'DELIVERY_WEIGHT_AMBIGUOUS' === $malformed_weight['code'], 'Нечисловой вес заказа должен давать явную ошибку.' );

$negative_order = velotok_core_delivery_zones_order_weight( array( 'weight' => -1 ) );
velotok_delivery_weight_assert( false === $negative_order['ok'], 'Отрицательный вес заказа не должен давать тариф.' );
velotok_delivery_weight_assert( isset( $negative_order['code'] ) && 'DELIVERY_WEIGHT_AMBIGUOUS' === $negative_order['code'], 'Отрицательный вес заказа должен давать явную ошибку.' );

$items = velotok_core_delivery_zones_order_weight(
	array(
		'items' => array(
			array( 'weight' => 1.1, 'quantity' => 2 ),
			array( 'weight' => '2', 'quantity' => '1' ),
			array( 'weight' => 5, 'quantity' => -3 ),
		),
	)
);
velotok_delivery_weight_assert( true === $items['ok'] && 4.2 === (float) $items['weight'], 'Вес заказа должен однозначно суммироваться по товарам и количеству.' );
velotok_delivery_weight_assert( 'items' === $items['source'], 'При отсутствии order.weight источником должны быть товары.' );

$default_quantity = velotok_core_delivery_zones_order_weight(
	array(
		'items' => array(
			array( 'weight' => 1.2 ),
		),
	)
);
velotok_delivery_weight_assert( true === $default_quantity['ok'] && 1.2 === (float) $default_quantity['weight'], 'Без количества должна использоваться одна единица товара.' );

$comma_item = velotok_core_delivery_zones_order_weight(
	array(
		'items' => array(
			array( 'weight' => '1,5', 'quantity' => 2 ),
		),
	)
);
velotok_delivery_weight_assert( true === $comma_item['ok'] && 3.0 === (float) $comma_item['weight'], 'Десятичная запятая в весе товара должна нормализоваться.' );

$nested_array = velotok_core_delivery_zones_order_weight(
	array(
		'items' => array(
			array(
				'product'  => array( 'weight' => 2.5 ),
				'quantity' => 2,
			),
		),
	)
);
velotok_delivery_weight_assert( true === $nested_array['ok'] && 5.0 === (float) $nested_array['weight'], 'Вес товара должен читаться из вложенного product.' );

$nested_object = velotok_core_delivery_zones_order_weight(
	array(
		'items' => array(
			array(
				'product'  => new Velotok_Delivery_Weight_Product( 1.5 ),
				'quantity' => 2,
			),
		),
	)
);
velotok_delivery_weight_assert( true === $nested_object['ok'] && 3.0 === (float) $nested_object['weight'], 'Вес товара должен читаться из объекта product в строке заказа.' );

$missing = velotok_core_delivery_zones_order_weight(
	array(
		'items' => array(
			array( 'quantity' => 1 ),
		),
	)
);
velotok_delivery_weight_assert( false === $missing['ok'], 'Без веса товара расчёт должен быть недоступен.' );
velotok_delivery_weight_assert( 'DELIVERY_WEIGHT_AMBIGUOUS' === $missing['code'], 'Отсутствующий вес товара должен давать явную ошибку.' );

$negative_item = velotok_core_delivery_zones_order_weight(
	array(
		'items' => array(
			array( 'weight' => -2, 'quantity' => 1 ),
		),
	)
);
velotok_delivery_weight_assert( false === $negative_item['ok'], 'Отрицательный вес товара не должен давать тариф.' );
velotok_delivery_weight_assert( isset( $negative_item['code'] ) && 'DELIVERY_WEIGHT_AMBIGUOUS' === $negative_item['code'], 'Отрицательный вес товара должен давать явную ошибку.' );

$empty = velotok_core_delivery_zones_order_weight( array() );
velotok_delivery_weight_assert( true === $empty['ok'] && 0.0 === (float) $empty['weight'], 'Пустой заказ должен иметь однозначный нулевой вес.' );

$precedence = velotok_core_delivery_zones_order_weight(
	array(
		'weight' => 2,
		'items'  => array(
			array( 'weight' => 5, 'quantity' => 1 ),
		),
	)
);
velotok_delivery_weight_assert( true === $precedence['ok'] && 2.0 === (float) $precedence['weight'], 'Явный вес заказа должен иметь приоритет над товарами.' );

$address = array(
	'country'  => 'RU',
	'postcode' => '101000',
	'city'     => 'Москва',
);
$settings = array(
	'base'   => 500,
	'per_kg' => 100,
);

$item_tariff = velotok_core_delivery_zone_methods(
	$address,
	velotok_delivery_weight_zone( $settings ),
	array(
		'order' => array(
			'subtotal' => 1000,
			'items'    => array(
				array( 'weight' => 1.1, 'quantity' => 2 ),
			),
		),
	)
);
velotok_delivery_weight_assert( true === $item_tariff['methods']['cdek']['available'], 'Вес товаров должен давать доступный тариф.' );
velotok_delivery_weight_assert( 800.0 === (float) $item_tariff['methods']['cdek']['cost'], 'Тариф должен использовать округлённый вверх вес товаров.' );
velotok_delivery_weight_assert( 3.0 === (float) $item_tariff['methods']['cdek']['tariff']['billable_weight'], 'В тарифе должен сохраняться оплачиваемый вес.' );
velotok_delivery_weight_assert( 2.2 === (float) $item_tariff['methods']['cdek']['weight_kg'], 'Способ доставки должен отдавать точный вес заказа.' );
velotok_delivery_weight_assert( 3.0 === (float) $item_tariff['methods']['cdek']['billable_weight'], 'Способ доставки должен отдавать оплачиваемый вес.' );

$order_tariff = velotok_core_delivery_zone_methods(
	$address,
	velotok_delivery_weight_zone( $settings ),
	array(
		'order' => array(
			'subtotal' => 1000,
			'weight'   => 1.2,
		),
	)
);
velotok_delivery_weight_assert( 700.0 === (float) $order_tariff['methods']['cdek']['cost'], 'Тариф должен использовать вес заказа с округлением вверх.' );

$captured_order = null;
$provider       = function ( $method, $provider_order ) use ( &$captured_order ) {
	$captured_order = $provider_order;

	return array( 'amount' => 425 );
};
$provider_zone = velotok_delivery_weight_zone( $settings );

$provider_zone[0]['shipping_methods'][0]['rate_provider'] = $provider;

$provider_tariff = velotok_core_delivery_zone_methods(
	$address,
	$provider_zone,
	array(
		'order' => array(
			'subtotal' => 1000,
			'items'    => array(
				array( 'weight' => 1.2, 'quantity' => 2 ),
			),
		),
	)
);
velotok_delivery_weight_assert( 425.0 === (float) $provider_tariff['methods']['cdek']['cost'], 'Провайдер должен получать тариф по однозначному весу.' );
velotok_delivery_weight_assert( is_array( $captured_order ) && 2.4 === (float) $captured_order['weight'], 'Провайдер должен получать нормализованный вес заказа.' );
velotok_delivery_weight_assert( 3.0 === (float) $provider_tariff['methods']['cdek']['tariff']['billable_weight'], 'Тариф провайдера должен сохранять оплачиваемый вес.' );

$ambiguous_tariff = velotok_core_delivery_zone_methods(
	$address,
	velotok_delivery_weight_zone( $settings ),
	array(
		'order' => array(
			'subtotal' => 1000,
			'items'    => array(
				array( 'quantity' => 1 ),
			),
		),
	)
);
velotok_delivery_weight_assert( false === $ambiguous_tariff['methods']['cdek']['available'], 'Неоднозначный вес не должен давать тариф.' );
velotok_delivery_weight_assert( null === $ambiguous_tariff['methods']['cdek']['cost'], 'Неоднозначный вес не должен подменяться тарифом.' );
velotok_delivery_weight_assert( 'DELIVERY_WEIGHT_AMBIGUOUS' === $ambiguous_tariff['methods']['cdek']['error_code'], 'Неоднозначный вес должен давать явную ошибку в способе доставки.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: вес заказа для тарифа рассчитывается однозначно.' . PHP_EOL;
