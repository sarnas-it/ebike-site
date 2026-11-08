<?php
/**
 * Contract test for delivery cost and the free-shipping threshold.
 *
 * Requirement TESTEB-29 / spec delivery:
 * «Стоимость доставки SHALL рассчитываться по тарифу и порогу бесплатной
 * доставки».
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-cost-contract-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$module_path = getenv( 'VELOTOK_DELIVERY_COST_STUB' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__ ) . '/includes/delivery-zones.php';
}

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль расчёта стоимости доставки.' . PHP_EOL );
	exit( 1 );
}

require_once $module_path;

$failures = array();

/**
 * Assert a delivery cost condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_delivery_cost_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Build a Moscow delivery zone with one СДЭК method.
 *
 * @param array $settings Method settings.
 * @param mixed $provider Optional provider callback.
 * @return array
 */
function velotok_delivery_cost_zone( $settings, $provider = null ) {
	$method = array(
		'instance_id' => 101,
		'method_id'   => 'cdek',
		'title'       => 'СДЭК',
		'enabled'     => true,
		'settings'    => $settings,
	);

	if ( null !== $provider ) {
		$method['rate_provider'] = $provider;
	}

	return array(
		array(
			'zone_id'          => 10,
			'zone_name'        => 'Москва',
			'zone_order'       => 1,
			'zone_locations'   => array(
				array( 'type' => 'postcode', 'code' => '101*' ),
			),
			'shipping_methods' => array( $method ),
		),
	);
}

$address = array(
	'country'  => 'RU',
	'postcode' => '101000',
	'city'     => 'Москва',
);

$settings = array(
	'base'         => 500,
	'per_kg'       => 100,
	'free_from'    => 5000,
	'eta_min_days' => 1,
	'eta_max_days' => 3,
);

$tariff = velotok_core_delivery_zone_methods(
	$address,
	velotok_delivery_cost_zone( $settings ),
	array( 'order' => array( 'subtotal' => 1000, 'weight' => 2 ) )
);

velotok_delivery_cost_assert( isset( $tariff['methods']['cdek'] ), 'Способ СДЭК должен рассчитываться.' );
velotok_delivery_cost_assert( 700.0 === (float) $tariff['methods']['cdek']['cost'], 'Тариф должен считаться как base + per_kg * вес.' );
velotok_delivery_cost_assert( false === $tariff['methods']['cdek']['free'], 'Ниже порога доставка не должна быть бесплатной.' );
velotok_delivery_cost_assert( 5000.0 === (float) $tariff['methods']['cdek']['tariff']['free_from'], 'Порог бесплатной доставки должен попадать в результат.' );

$at_threshold = velotok_core_delivery_zone_methods(
	$address,
	velotok_delivery_cost_zone( $settings ),
	array( 'order' => array( 'subtotal' => 5000, 'weight' => 2 ) )
);
velotok_delivery_cost_assert( true === $at_threshold['methods']['cdek']['free'], 'Порог должен срабатывать при равенстве сумме.' );
velotok_delivery_cost_assert( 0.0 === (float) $at_threshold['methods']['cdek']['cost'], 'На пороге бесплатной доставки стоимость должна обнуляться.' );

$below_threshold = velotok_core_delivery_zone_methods(
	$address,
	velotok_delivery_cost_zone( $settings ),
	array( 'order' => array( 'subtotal' => 4999, 'weight' => 2 ) )
);
velotok_delivery_cost_assert( false === $below_threshold['methods']['cdek']['free'], 'Чуть ниже порога доставка должна оставаться платной.' );
velotok_delivery_cost_assert( 700.0 === (float) $below_threshold['methods']['cdek']['cost'], 'Ниже порога должен применяться обычный тариф.' );

$min_amount = $settings;
unset( $min_amount['free_from'] );
$min_amount['min_amount'] = 5000;

$min_amount_result = velotok_core_delivery_zone_methods(
	$address,
	velotok_delivery_cost_zone( $min_amount ),
	array( 'order' => array( 'subtotal' => 6000, 'weight' => 2 ) )
);
velotok_delivery_cost_assert( true === $min_amount_result['methods']['cdek']['free'], 'Порог должен читаться из настроек зоны (min_amount).' );
velotok_delivery_cost_assert( 0.0 === (float) $min_amount_result['methods']['cdek']['cost'], 'Порог из настроек зоны должен обнулять стоимость.' );

$provider = function () {
	return array( 'amount' => 900, 'currency' => 'RUB' );
};

$provider_free = velotok_core_delivery_zone_methods(
	$address,
	velotok_delivery_cost_zone( $settings, $provider ),
	array( 'order' => array( 'subtotal' => 6000, 'weight' => 2 ) )
);
velotok_delivery_cost_assert( true === $provider_free['methods']['cdek']['free'], 'Порог бесплатной доставки должен действовать и для тарифа провайдера.' );
velotok_delivery_cost_assert( 0.0 === (float) $provider_free['methods']['cdek']['cost'], 'Тариф провайдера выше порога должен обнуляться.' );
velotok_delivery_cost_assert( 900.0 === (float) $provider_free['methods']['cdek']['tariff']['provider_amount'], 'Тариф провайдера должен сохраняться в результате расчёта.' );

$provider_paid = velotok_core_delivery_zone_methods(
	$address,
	velotok_delivery_cost_zone( $settings, $provider ),
	array( 'order' => array( 'subtotal' => 4000, 'weight' => 2 ) )
);
velotok_delivery_cost_assert( false === $provider_paid['methods']['cdek']['free'], 'Ниже порога тариф провайдера должен оставаться платным.' );
velotok_delivery_cost_assert( 900.0 === (float) $provider_paid['methods']['cdek']['cost'], 'Ниже порога должен применяться тариф провайдера.' );

$failing = function () {
	return array( 'error' => 'СДЭК временно недоступен' );
};

$provider_error = velotok_core_delivery_zone_methods(
	$address,
	velotok_delivery_cost_zone( $settings, $failing ),
	array( 'order' => array( 'subtotal' => 1000, 'weight' => 2 ) )
);
velotok_delivery_cost_assert( false === $provider_error['methods']['cdek']['available'], 'Ошибка провайдера должна делать способ недоступным.' );
velotok_delivery_cost_assert( null === $provider_error['methods']['cdek']['cost'], 'Ошибка провайдера не должна подменяться тарифом.' );
velotok_delivery_cost_assert( 'DELIVERY_PROVIDER_ERROR' === $provider_error['methods']['cdek']['error_code'], 'Ошибка провайдера должна быть явной.' );

$unknown_weight = velotok_core_delivery_zone_methods(
	$address,
	velotok_delivery_cost_zone( $settings ),
	array(
		'order' => array(
			'subtotal' => 1000,
			'items'    => array(
				array( 'quantity' => 1 ),
			),
		),
	)
);
velotok_delivery_cost_assert( false === $unknown_weight['methods']['cdek']['available'], 'Без однозначного веса тариф не должен рассчитываться.' );
velotok_delivery_cost_assert( 'DELIVERY_WEIGHT_AMBIGUOUS' === $unknown_weight['methods']['cdek']['error_code'], 'Неоднозначный вес должен давать явную ошибку.' );

$free_shipping = velotok_core_delivery_zone_methods(
	$address,
	array(
		array(
			'zone_id'          => 10,
			'zone_name'        => 'Москва',
			'zone_order'       => 1,
			'zone_locations'   => array(
				array( 'type' => 'postcode', 'code' => '101*' ),
			),
			'shipping_methods' => array(
				array(
					'method_id' => 'free_shipping',
					'title'     => 'Бесплатная доставка',
					'enabled'   => true,
					'settings'  => array( 'base' => 500 ),
				),
			),
		),
	),
	array( 'order' => array( 'subtotal' => 1000, 'weight' => 2 ) )
);
velotok_delivery_cost_assert( 0.0 === (float) $free_shipping['methods']['free_shipping']['cost'], 'Способ бесплатной доставки должен стоить 0.' );
velotok_delivery_cost_assert( true === $free_shipping['methods']['free_shipping']['free'], 'Способ бесплатной доставки должен быть отмечен бесплатным.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: стоимость доставки считается по тарифу и порогу бесплатной доставки.' . PHP_EOL;
