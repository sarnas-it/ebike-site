<?php
/**
 * Contract test for the shared behaviour of an out-of-zone and an ambiguous address.
 *
 * Requirement TESTEB-35 / spec delivery:
 * «Адрес вне зон и неоднозначный адрес SHALL давать одинаковое поведение».
 *
 * Both cases must fall back to the same zone-independent pickup behaviour:
 * no provider rates, no fake tariffs, pickup still offered in Russia and the
 * same explicit pickup-point failure information.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-out-of-zone-ambiguous-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$module_path = getenv( 'VELOTOK_DELIVERY_OUT_OF_ZONE_AMBIGUOUS_STUB' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__ ) . '/includes/delivery-zones.php';
}

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль способов доставки из зон WooCommerce.' . PHP_EOL );
	exit( 1 );
}

require_once $module_path;

$failures = array();

/**
 * Assert a no-zone behaviour condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_delivery_out_of_zone_ambiguous_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = velotok_core_delivery_zones_contract();

velotok_delivery_out_of_zone_ambiguous_assert(
	isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-35', $contract['requirement_ids'], true ),
	'Контракт доставки должен ссылаться на TESTEB-35.'
);
velotok_delivery_out_of_zone_ambiguous_assert(
	isset( $contract['out_of_zone']['requirement'] ) && 'TESTEB-35' === $contract['out_of_zone']['requirement'],
	'Раздел адреса вне зон должен ссылаться на TESTEB-35.'
);
velotok_delivery_out_of_zone_ambiguous_assert(
	isset( $contract['ambiguous_address']['requirement'] ) && 'TESTEB-35' === $contract['ambiguous_address']['requirement'],
	'Раздел неоднозначного адреса должен ссылаться на TESTEB-35.'
);
velotok_delivery_out_of_zone_ambiguous_assert(
	isset( $contract['no_zone']['same_behavior'] ) && true === $contract['no_zone']['same_behavior'],
	'Адрес вне зон и неоднозначный адрес должны объявлять одинаковое поведение.'
);
velotok_delivery_out_of_zone_ambiguous_assert(
	isset( $contract['out_of_zone']['same_behavior_as_ambiguous'] ) && true === $contract['out_of_zone']['same_behavior_as_ambiguous'],
	'Поведение вне зон должно совпадать с поведением неоднозначного адреса.'
);
velotok_delivery_out_of_zone_ambiguous_assert(
	isset( $contract['ambiguous_address']['same_as_out_of_zone'] ) && true === $contract['ambiguous_address']['same_as_out_of_zone'],
	'Поведение неоднозначного адреса должно совпадать с поведением вне зон.'
);
velotok_delivery_out_of_zone_ambiguous_assert(
	isset( $contract['no_zone']['fallback'] ) && 'pickup' === $contract['no_zone']['fallback'],
	'Общим поведением должен быть переход к самовывозу.'
);
velotok_delivery_out_of_zone_ambiguous_assert(
	isset( $contract['no_zone']['fallback_method'] ) && 'local_pickup' === $contract['no_zone']['fallback_method'],
	'Резервным способом должен быть local_pickup.'
);
velotok_delivery_out_of_zone_ambiguous_assert(
	isset( $contract['no_zone']['provider_methods_hidden'] ) && true === $contract['no_zone']['provider_methods_hidden'],
	'Способы провайдеров вне зон не должны предлагаться.'
);
velotok_delivery_out_of_zone_ambiguous_assert(
	isset( $contract['no_zone']['fake_tariff'] ) && false === $contract['no_zone']['fake_tariff'],
	'Вне зон не должно быть фиктивного тарифа.'
);
velotok_delivery_out_of_zone_ambiguous_assert(
	isset( $contract['no_zone']['codes']['out_of_zone'] ) && 'DELIVERY_ZONE_NOT_FOUND' === $contract['no_zone']['codes']['out_of_zone'],
	'Вне зон должно сохранять явный код причины.'
);
velotok_delivery_out_of_zone_ambiguous_assert(
	isset( $contract['no_zone']['codes']['ambiguous_address'] ) && 'DELIVERY_ADDRESS_AMBIGUOUS' === $contract['no_zone']['codes']['ambiguous_address'],
	'Неоднозначный адрес должен сохранять явный код причины.'
);

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
				'settings'    => array( 'base' => 500, 'per_kg' => 100 ),
			),
			array(
				'instance_id' => 102,
				'method_id'   => 'russian_post',
				'title'       => 'Почта РФ',
				'enabled'     => true,
				'settings'    => array( 'base' => 350, 'per_kg' => 80 ),
			),
		),
	),
);

$context = array( 'order' => array( 'subtotal' => 1000, 'weight' => 1 ) );

$out_of_zone = velotok_core_delivery_zone_methods(
	array( 'country' => 'RU', 'postcode' => '999999', 'city' => 'Неизвестск' ),
	$zones,
	$context
);
$ambiguous = velotok_core_delivery_zone_methods( array(), $zones, $context );

velotok_delivery_out_of_zone_ambiguous_assert( 'DELIVERY_ZONE_NOT_FOUND' === $out_of_zone['code'], 'Адрес вне зон должен давать DELIVERY_ZONE_NOT_FOUND.' );
velotok_delivery_out_of_zone_ambiguous_assert( 'DELIVERY_ADDRESS_AMBIGUOUS' === $ambiguous['code'], 'Неоднозначный адрес должен давать DELIVERY_ADDRESS_AMBIGUOUS.' );
velotok_delivery_out_of_zone_ambiguous_assert( false === $out_of_zone['zone_matched'], 'Адрес вне зон не должен попадать в зону.' );
velotok_delivery_out_of_zone_ambiguous_assert( false === $ambiguous['zone_matched'], 'Неоднозначный адрес не должен попадать в зону.' );

velotok_delivery_out_of_zone_ambiguous_assert(
	array_keys( $out_of_zone['methods'] ) === array_keys( $ambiguous['methods'] ),
	'Адрес вне зон и неоднозначный адрес должны давать одинаковые способы доставки.'
);
velotok_delivery_out_of_zone_ambiguous_assert(
	array( 'local_pickup' ) === array_keys( $out_of_zone['methods'] ),
	'Вне зон должен оставаться только самовывоз.'
);
velotok_delivery_out_of_zone_ambiguous_assert(
	array( 'local_pickup' ) === array_keys( $ambiguous['methods'] ),
	'Для неоднозначного адреса должен оставаться только самовывоз.'
);
velotok_delivery_out_of_zone_ambiguous_assert( ! isset( $out_of_zone['methods']['cdek'] ), 'СДЭК не должен предлагаться вне зон.' );
velotok_delivery_out_of_zone_ambiguous_assert( ! isset( $out_of_zone['methods']['russian_post'] ), 'Почта РФ не должна предлагаться вне зон.' );
velotok_delivery_out_of_zone_ambiguous_assert( ! isset( $ambiguous['methods']['cdek'] ), 'СДЭК не должен предлагаться при неоднозначном адресе.' );
velotok_delivery_out_of_zone_ambiguous_assert( ! isset( $ambiguous['methods']['russian_post'] ), 'Почта РФ не должна предлагаться при неоднозначном адресе.' );

velotok_delivery_out_of_zone_ambiguous_assert( true === $out_of_zone['available'], 'Вне зон должен оставаться доступный самовывоз.' );
velotok_delivery_out_of_zone_ambiguous_assert( true === $ambiguous['available'], 'При неоднозначном адресе должен оставаться доступный самовывоз.' );
velotok_delivery_out_of_zone_ambiguous_assert( true === $out_of_zone['pickup'], 'Вне зон самовывоз должен быть включён.' );
velotok_delivery_out_of_zone_ambiguous_assert( true === $ambiguous['pickup'], 'При неоднозначном адресе самовывоз должен быть включён.' );
velotok_delivery_out_of_zone_ambiguous_assert( 0.0 === (float) $out_of_zone['methods']['local_pickup']['cost'], 'Самовывоз вне зон должен быть бесплатным.' );
velotok_delivery_out_of_zone_ambiguous_assert( 0.0 === (float) $ambiguous['methods']['local_pickup']['cost'], 'Самовывоз при неоднозначном адресе должен быть бесплатным.' );

velotok_delivery_out_of_zone_ambiguous_assert( true === $out_of_zone['no_zone'], 'Адрес вне зон должен отмечаться как адрес без зоны.' );
velotok_delivery_out_of_zone_ambiguous_assert( true === $ambiguous['no_zone'], 'Неоднозначный адрес должен отмечаться как адрес без зоны.' );
velotok_delivery_out_of_zone_ambiguous_assert( $out_of_zone['behavior'] === $ambiguous['behavior'], 'Поведение вне зон и неоднозначного адреса должно совпадать.' );
velotok_delivery_out_of_zone_ambiguous_assert( 'pickup_fallback' === $out_of_zone['behavior'], 'Вне зон должно использоваться поведение pickup_fallback.' );
velotok_delivery_out_of_zone_ambiguous_assert( 'pickup_fallback' === $ambiguous['behavior'], 'Неоднозначный адрес должен использовать поведение pickup_fallback.' );
velotok_delivery_out_of_zone_ambiguous_assert( $out_of_zone['fallback'] === $ambiguous['fallback'], 'Резервное поведение должно совпадать.' );
velotok_delivery_out_of_zone_ambiguous_assert( 'pickup' === $out_of_zone['fallback'] && 'pickup' === $ambiguous['fallback'], 'Резервным поведением должен быть самовывоз.' );
velotok_delivery_out_of_zone_ambiguous_assert( true === $out_of_zone['provider_methods_hidden'], 'Вне зон способы провайдеров должны быть скрыты.' );
velotok_delivery_out_of_zone_ambiguous_assert( true === $ambiguous['provider_methods_hidden'], 'При неоднозначном адресе способы провайдеров должны быть скрыты.' );
velotok_delivery_out_of_zone_ambiguous_assert( true === $out_of_zone['same_behavior'], 'Вне зон должно объявлять общее поведение.' );
velotok_delivery_out_of_zone_ambiguous_assert( true === $ambiguous['same_behavior'], 'Неоднозначный адрес должен объявлять общее поведение.' );

velotok_delivery_out_of_zone_ambiguous_assert(
	$out_of_zone['pickup_points_code'] === $ambiguous['pickup_points_code'],
	'Информация о недоступности пунктов должна быть одинаковой для обоих случаев.'
);
velotok_delivery_out_of_zone_ambiguous_assert(
	isset( $out_of_zone['pickup_points_result']['failure']['visible'] ) && true === $out_of_zone['pickup_points_result']['failure']['visible'],
	'Недоступность пунктов вне зон должна быть явной.'
);
velotok_delivery_out_of_zone_ambiguous_assert(
	isset( $ambiguous['pickup_points_result']['failure']['visible'] ) && true === $ambiguous['pickup_points_result']['failure']['visible'],
	'Недоступность пунктов при неоднозначном адресе должна быть явной.'
);

$out_match = velotok_core_delivery_zone_for_address(
	array( 'country' => 'RU', 'postcode' => '999999', 'city' => 'Неизвестск' ),
	$zones
);
$ambiguous_match = velotok_core_delivery_zone_for_address( array(), $zones );

velotok_delivery_out_of_zone_ambiguous_assert( $out_match['behavior'] === $ambiguous_match['behavior'], 'Помощник зон должен давать одинаковое поведение для обоих адресов.' );
velotok_delivery_out_of_zone_ambiguous_assert( true === $out_match['same_behavior'] && true === $ambiguous_match['same_behavior'], 'Помощник зон должен явно отмечать общее поведение.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: адрес вне зон и неоднозначный адрес дают одинаковое поведение.' . PHP_EOL;
