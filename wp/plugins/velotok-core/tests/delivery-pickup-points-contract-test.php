<?php
/**
 * Contract test for the radius-based pickup point list.
 *
 * Requirement TESTEB-32 / spec delivery:
 * «Список пунктов выдачи и самовывоза SHALL формироваться по радиусу от
 * адреса».
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-pickup-points-contract-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$module_path = getenv( 'VELOTOK_DELIVERY_PICKUP_POINTS_STUB' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__ ) . '/includes/delivery-zones.php';
}

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль пунктов выдачи доставки.' . PHP_EOL );
	exit( 1 );
}

require_once $module_path;

$failures = array();

/**
 * Assert a pickup point condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_delivery_pickup_points_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = velotok_core_delivery_zones_contract();

velotok_delivery_pickup_points_assert(
	isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-32', $contract['requirement_ids'], true ),
	'Контракт доставки должен ссылаться на TESTEB-32.'
);
velotok_delivery_pickup_points_assert(
	isset( $contract['pickup_points']['requirement'] ) && 'TESTEB-32' === $contract['pickup_points']['requirement'],
	'Раздел пунктов выдачи должен ссылаться на TESTEB-32.'
);
velotok_delivery_pickup_points_assert(
	isset( $contract['pickup']['points_by_radius'] ) && true === $contract['pickup']['points_by_radius'],
	'Самовывоз должен формировать пункты по радиусу.'
);
velotok_delivery_pickup_points_assert(
	isset( $contract['pickup_points']['unit'] ) && 'km' === $contract['pickup_points']['unit'],
	'Радиус пунктов выдачи должен измеряться в километрах.'
);
velotok_delivery_pickup_points_assert(
	isset( $contract['pickup_points']['distance'] ) && 'haversine' === $contract['pickup_points']['distance'],
	'Расстояние до пунктов выдачи должно считаться по координатам.'
);
velotok_delivery_pickup_points_assert(
	isset( $contract['pickup_points']['default_radius_km'] ) && (float) $contract['pickup_points']['default_radius_km'] > 0,
	'У радиуса пунктов выдачи должен быть явный размер по умолчанию.'
);

$address = array(
	'country'     => 'RU',
	'postcode'    => '101000',
	'city'        => 'Москва',
	'coordinates' => array( 'lat' => 55.7558, 'lon' => 37.6173 ),
);

$points = array(
	array(
		'id'       => 'CDEK-2',
		'provider' => 'cdek',
		'name'     => 'СДЭК у метро',
		'address'  => 'Москва, Тверская, 1',
		'country'  => 'RU',
		'lat'      => 55.7600,
		'lon'      => 37.6200,
	),
	array(
		'id'       => 'CDEK-1',
		'provider' => 'cdek',
		'name'     => 'СДЭК в центре',
		'address'  => 'Москва, Арбат, 1',
		'country'  => 'RU',
		'coordinates' => array( 'lat' => 55.7559, 'lon' => 37.6174 ),
	),
	array(
		'id'       => 'POST-1',
		'provider' => 'russian_post',
		'name'     => 'Почта РФ',
		'address'  => 'Москва, окраина',
		'country'  => 'RU',
		'lat'      => 55.8000,
		'lon'      => 37.7000,
	),
	array(
		'id'       => 'CDEK-FAR',
		'provider' => 'cdek',
		'name'     => 'СДЭК далеко',
		'address'  => 'Московская область',
		'country'  => 'RU',
		'lat'      => 55.9000,
		'lon'      => 37.8000,
	),
	array(
		'id'       => 'CDEK-SPB',
		'provider' => 'cdek',
		'name'     => 'СДЭК Санкт-Петербург',
		'address'  => 'Санкт-Петербург',
		'country'  => 'RU',
		'lat'      => 59.9343,
		'lon'      => 30.3351,
	),
	array(
		'id'       => 'POST-KZ',
		'provider' => 'russian_post',
		'name'     => 'Почта Казахстан',
		'address'  => 'Алматы',
		'country'  => 'KZ',
		'lat'      => 55.7601,
		'lon'      => 37.6201,
	),
	array(
		'id'       => 'CDEK-NOCOORDS',
		'provider' => 'cdek',
		'name'     => 'СДЭК без координат',
		'address'  => 'Москва',
		'country'  => 'RU',
	),
);

$default = velotok_core_delivery_pickup_points( $address, $points );

velotok_delivery_pickup_points_assert( true === $default['ok'], 'Список пунктов выдачи должен формироваться.' );
velotok_delivery_pickup_points_assert( 10.0 === (float) $default['radius_km'], 'По умолчанию должен применяться радиус из контракта.' );
velotok_delivery_pickup_points_assert( array( 'CDEK-1', 'CDEK-2', 'POST-1' ) === array_column( $default['points'], 'id' ), 'В радиус должны попасть ближайшие пункты обоих провайдеров.' );
velotok_delivery_pickup_points_assert( 'cdek' === $default['points'][0]['provider'] && 'russian_post' === $default['points'][2]['provider'], 'Адаптеры СДЭК и Почты РФ должны поддерживаться равноправно.' );
velotok_delivery_pickup_points_assert( $default['points'][0]['distance_km'] <= $default['points'][1]['distance_km'], 'Пункты должны сортироваться по расстоянию от адреса.' );
velotok_delivery_pickup_points_assert( $default['points'][1]['distance_km'] <= $default['points'][2]['distance_km'], 'Пункты должны сортироваться по расстоянию от адреса.' );

foreach ( $default['points'] as $point ) {
	velotok_delivery_pickup_points_assert( $point['distance_km'] <= 10.0, 'Пункт за пределами радиуса не должен попадать в список.' );
}

$ids = array_column( $default['points'], 'id' );
velotok_delivery_pickup_points_assert( ! in_array( 'CDEK-FAR', $ids, true ), 'Пункт за пределами радиуса по умолчанию не должен попадать в список.' );
velotok_delivery_pickup_points_assert( ! in_array( 'CDEK-SPB', $ids, true ), 'Пункт в другом городе не должен попадать в список.' );
velotok_delivery_pickup_points_assert( ! in_array( 'POST-KZ', $ids, true ), 'Пункт за пределами России не должен попадать в список.' );
velotok_delivery_pickup_points_assert( ! in_array( 'CDEK-NOCOORDS', $ids, true ), 'Пункт без координат не должен попадать в радиус.' );

$codes = array_column( $default['errors'], 'code' );
velotok_delivery_pickup_points_assert( in_array( 'DELIVERY_PICKUP_POINTS_NO_COORDINATES', $codes, true ), 'Пункт без координат должен давать явную ошибку.' );

$wide = velotok_core_delivery_pickup_points( $address, $points, array( 'radius_km' => 25 ) );
$wide_ids = array_column( $wide['points'], 'id' );
velotok_delivery_pickup_points_assert( in_array( 'CDEK-FAR', $wide_ids, true ), 'Увеличенный радиус должен включать более далёкий пункт.' );
velotok_delivery_pickup_points_assert( ! in_array( 'CDEK-SPB', $wide_ids, true ), 'Даже увеличенный радиус не должен включать другой город.' );
velotok_delivery_pickup_points_assert( 25.0 === (float) $wide['radius_km'], 'Радиус из контекста должен применяться.' );

$narrow = velotok_core_delivery_pickup_points( $address, $points, 0.2 );
velotok_delivery_pickup_points_assert( array( 'CDEK-1' ) === array_column( $narrow['points'], 'id' ), 'Узкий радиус должен оставлять только ближайший пункт.' );

$cdek_only = velotok_core_delivery_pickup_points( $address, $points, array( 'radius_km' => 10, 'provider' => 'cdek' ) );
velotok_delivery_pickup_points_assert( array( 'CDEK-1', 'CDEK-2' ) === array_column( $cdek_only['points'], 'id' ), 'Список пунктов должен фильтроваться по провайдеру СДЭК.' );

$post_only = velotok_core_delivery_pickup_points( $address, $points, array( 'radius_km' => 10, 'provider' => 'russian_post' ) );
velotok_delivery_pickup_points_assert( array( 'POST-1' ) === array_column( $post_only['points'], 'id' ), 'Список пунктов должен фильтроваться по провайдеру Почты РФ.' );

$alias = velotok_core_delivery_pickup_points_by_radius( $address, $points, array( 'radius_km' => 10 ) );
velotok_delivery_pickup_points_assert( array_column( $default['points'], 'id' ) === array_column( $alias['points'], 'id' ), 'Публичный помощник радиуса должен возвращать тот же список.' );

$distance = velotok_core_delivery_pickup_distance_km(
	array( 'lat' => 55.7558, 'lon' => 37.6173 ),
	array( 'lat' => 59.9343, 'lon' => 30.3351 )
);
velotok_delivery_pickup_points_assert( null !== $distance && $distance > 600 && $distance < 700, 'Расстояние между городами должно считаться по координатам.' );

$no_address = velotok_core_delivery_pickup_points( array( 'city' => 'Москва' ), $points );
velotok_delivery_pickup_points_assert( false === $no_address['ok'], 'Без координат адреса список по радиусу не должен строиться.' );
velotok_delivery_pickup_points_assert( 'DELIVERY_PICKUP_POINTS_ADDRESS_AMBIGUOUS' === $no_address['code'], 'Неоднозначный адрес должен давать явную ошибку.' );
velotok_delivery_pickup_points_assert( ! empty( $no_address['errors'] ), 'Неоднозначный адрес должен быть видимой ошибкой.' );

$unavailable = velotok_core_delivery_pickup_points( $address, null );
velotok_delivery_pickup_points_assert( false === $unavailable['ok'], 'Недоступный список пунктов не должен выглядеть успешным.' );
velotok_delivery_pickup_points_assert( 'DELIVERY_PICKUP_POINTS_UNAVAILABLE' === $unavailable['code'], 'Недоступный список пунктов должен иметь явный код.' );
velotok_delivery_pickup_points_assert( ! empty( $unavailable['errors'] ), 'Недоступный список пунктов должен быть видимой ошибкой.' );

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

$options = velotok_core_delivery_zone_methods(
	array(
		'country'     => 'RU',
		'postcode'    => '101000',
		'city'        => 'Москва',
		'coordinates' => array( 'lat' => 55.7558, 'lon' => 37.6173 ),
	),
	$zones,
	array(
		'order'          => array( 'subtotal' => 1000, 'weight' => 1 ),
		'pickup_points'  => $points,
		'radius_km'      => 10,
	)
);

velotok_delivery_pickup_points_assert( array( 'CDEK-1', 'CDEK-2', 'POST-1' ) === array_column( $options['pickup_points'], 'id' ), 'Список пунктов выдачи в способах доставки должен формироваться по радиусу.' );
velotok_delivery_pickup_points_assert( isset( $options['methods']['local_pickup']['pickup_points'] ), 'Самовывоз должен отдавать список пунктов по радиусу.' );
velotok_delivery_pickup_points_assert( array( 'CDEK-1', 'CDEK-2', 'POST-1' ) === array_column( $options['methods']['local_pickup']['pickup_points'], 'id' ), 'Список самовывоза должен совпадать со списком по радиусу.' );
velotok_delivery_pickup_points_assert( array( 'CDEK-1', 'CDEK-2' ) === array_column( $options['methods']['cdek']['pickup_points'], 'id' ), 'Способ СДЭК должен показывать только свои пункты по радиусу.' );
velotok_delivery_pickup_points_assert( array( 'POST-1' ) === array_column( $options['methods']['russian_post']['pickup_points'], 'id' ), 'Способ Почты РФ должен показывать только свои пункты по радиусу.' );
velotok_delivery_pickup_points_assert( true === $options['methods']['local_pickup']['available'], 'Отсутствие пунктов не должно отключать самовывоз.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: список пунктов выдачи и самовывоза формируется по радиусу от адреса.' . PHP_EOL;
