<?php
/**
 * Contract test for equal support of the СДЭК and Почта РФ adapters.
 *
 * Requirement TESTEB-42 / spec delivery:
 * «Адаптеры СДЭК и Почты РФ SHALL поддерживаться равноправно».
 *
 * The test proves both providers share the same interface and capabilities:
 * the same methods, rates, pickup points, ETA, test mode, tracking, error
 * handling and store configuration. Neither provider may be preferred or
 * silently replaced with a fake tariff when it fails.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-provider-parity-contract-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$module_path = getenv( 'VELOTOK_DELIVERY_PROVIDER_PARITY_STUB' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__ ) . '/includes/delivery-zones.php';
}

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль адаптеров доставки.' . PHP_EOL );
	exit( 1 );
}

require_once $module_path;

$failures = array();

/**
 * Assert a provider parity condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_delivery_provider_parity_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = velotok_core_delivery_zones_contract();

velotok_delivery_provider_parity_assert(
	isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-42', $contract['requirement_ids'], true ),
	'Контракт доставки должен ссылаться на TESTEB-42.'
);
velotok_delivery_provider_parity_assert(
	isset( $contract['provider_parity']['requirement'] ) && 'TESTEB-42' === $contract['provider_parity']['requirement'],
	'Раздел равноправия провайдеров должен ссылаться на TESTEB-42.'
);
velotok_delivery_provider_parity_assert(
	isset( $contract['provider_parity']['equal'] ) && true === $contract['provider_parity']['equal'],
	'Адаптеры СДЭК и Почты РФ должны быть объявлены равноправными.'
);
velotok_delivery_provider_parity_assert(
	isset( $contract['provider_parity']['providers'] ) && array( 'cdek', 'russian_post' ) === $contract['provider_parity']['providers'],
	'Равноправие должно охватывать ровно адаптеры СДЭК и Почты РФ.'
);
velotok_delivery_provider_parity_assert(
	isset( $contract['provider_parity']['same_interface'] ) && true === $contract['provider_parity']['same_interface'],
	'Адаптеры должны использовать один интерфейс.'
);
velotok_delivery_provider_parity_assert(
	isset( $contract['provider_parity']['same_features'] ) && true === $contract['provider_parity']['same_features'],
	'Адаптеры должны поддерживать одинаковый набор возможностей.'
);
velotok_delivery_provider_parity_assert(
	isset( $contract['provider_parity']['same_error_handling'] ) && true === $contract['provider_parity']['same_error_handling'],
	'Ошибки обоих провайдеров должны обрабатываться одинаково.'
);
velotok_delivery_provider_parity_assert(
	isset( $contract['provider_parity']['fallback_tariff'] ) && false === $contract['provider_parity']['fallback_tariff'],
	'Сбой провайдера не должен подменяться фиктивным тарифом.'
);
velotok_delivery_provider_parity_assert(
	isset( $contract['provider_parity']['error_visible'] ) && true === $contract['provider_parity']['error_visible'],
	'Ошибка провайдера должна быть видимой.'
);
velotok_delivery_provider_parity_assert(
	isset( $contract['supported_providers'] ) && array( 'cdek', 'russian_post' ) === $contract['supported_providers'],
	'Оба провайдера должны быть в списке поддерживаемых.'
);
velotok_delivery_provider_parity_assert(
	isset( $contract['pickup_points']['providers'] ) && array( 'cdek', 'russian_post' ) === $contract['pickup_points']['providers'],
	'Пункты выдачи должны поддерживаться обоими провайдерами.'
);
velotok_delivery_provider_parity_assert(
	isset( $contract['test_mode']['providers'] ) && array( 'cdek', 'russian_post' ) === $contract['test_mode']['providers'],
	'Тестовый режим должен поддерживаться обоими провайдерами.'
);

$adapters = velotok_core_delivery_provider_adapters();

velotok_delivery_provider_parity_assert(
	array( 'cdek', 'russian_post' ) === array_keys( $adapters ),
	'Контракт должен описывать адаптеры СДЭК и Почты РФ.'
);

$baseline          = null;
$adapter_labels    = array();
$adapter_methods   = array();

foreach ( $adapters as $adapter_id => $adapter ) {
	$features = isset( $adapter['features'] ) && is_array( $adapter['features'] ) ? $adapter['features'] : array();
	$features = array_values( array_keys( $features ) );
	sort( $features );

	if ( null === $baseline ) {
		$baseline = $features;
	} else {
		velotok_delivery_provider_parity_assert( $baseline === $features, 'У адаптеров должен быть одинаковый набор возможностей.' );
	}

	velotok_delivery_provider_parity_assert( ! empty( $adapter['shipping_method'] ), 'У адаптера должно быть объявлено Shipping Method.' );
	$adapter_labels[ $adapter_id ]  = isset( $adapter['label'] ) ? $adapter['label'] : '';
	$adapter_methods[ $adapter_id ] = isset( $adapter['method_id'] ) ? $adapter['method_id'] : '';
}

velotok_delivery_provider_parity_assert( 'СДЭК' === $adapter_labels['cdek'], 'Адаптер СДЭК должен иметь своё название.' );
velotok_delivery_provider_parity_assert( 'Почта РФ' === $adapter_labels['russian_post'], 'Адаптер Почты РФ должен иметь своё название.' );
velotok_delivery_provider_parity_assert( 'cdek' === $adapter_methods['cdek'], 'Адаптер СДЭК должен иметь идентификатор способа.' );
velotok_delivery_provider_parity_assert( 'russian_post' === $adapter_methods['russian_post'], 'Адаптер Почты РФ должен иметь идентификатор способа.' );

$parity = velotok_core_delivery_provider_parity();

velotok_delivery_provider_parity_assert( true === $parity['equal'], 'Вычисленное равноправие провайдеров должно подтверждаться.' );
velotok_delivery_provider_parity_assert( array( 'cdek', 'russian_post' ) === $parity['providers'], 'Вычисленное равноправие должно охватывать оба провайдера.' );

velotok_delivery_provider_parity_assert(
	'cdek' === velotok_core_delivery_provider_contract( 'СДЭК' )['id'],
	'Поиск адаптера по названию СДЭК должен возвращать адаптер СДЭК.'
);
velotok_delivery_provider_parity_assert(
	'russian_post' === velotok_core_delivery_provider_contract( 'Почта РФ' )['id'],
	'Поиск адаптера по названию Почты РФ должен возвращать адаптер Почты РФ.'
);

$order = array( 'subtotal' => 1000, 'weight' => 2 );
$rate_context = array(
	'order'     => $order,
	'providers' => array(
		'cdek'         => function () {
			return array( 'amount' => 450, 'currency' => 'RUB' );
		},
		'russian_post' => function () {
			return array( 'amount' => 450, 'currency' => 'RUB' );
		},
	),
);

$cdek_method = array(
	'method_id' => 'cdek',
	'title'     => 'СДЭК',
	'settings'  => array( 'base' => 350, 'per_kg' => 50 ),
);
$post_method = array(
	'method_id' => 'russian_post',
	'title'     => 'Почта РФ',
	'settings'  => array( 'base' => 350, 'per_kg' => 50 ),
);

$cdek_rate = velotok_core_delivery_zones_provider_rate( $cdek_method, $order, $rate_context );
$post_rate = velotok_core_delivery_zones_provider_rate( $post_method, $order, $rate_context );

velotok_delivery_provider_parity_assert( true === $cdek_rate['ok'] && true === $post_rate['ok'], 'Оба адаптера должны рассчитывать тариф.' );
velotok_delivery_provider_parity_assert( (float) $cdek_rate['amount'] === (float) $post_rate['amount'], 'Одинаковые тарифы провайдеров должны давать одинаковую стоимость.' );
velotok_delivery_provider_parity_assert( 'provider' === $cdek_rate['source'] && 'provider' === $post_rate['source'], 'Тариф обоих провайдеров должен считаться провайдером.' );
velotok_delivery_provider_parity_assert( true === $cdek_rate['test_mode_explicit'] && true === $post_rate['test_mode_explicit'], 'Тестовый режим обоих адаптеров должен быть явным.' );

$failing_context = array(
	'order'     => $order,
	'providers' => array(
		'cdek'         => function () {
			throw new \RuntimeException( 'СДЭК недоступен' );
		},
		'russian_post' => function () {
			throw new \RuntimeException( 'Почта недоступна' );
		},
	),
);

$cdek_error = velotok_core_delivery_zones_provider_rate( $cdek_method, $order, $failing_context );
$post_error = velotok_core_delivery_zones_provider_rate( $post_method, $order, $failing_context );

foreach ( array( 'cdek' => $cdek_error, 'russian_post' => $post_error ) as $provider => $error ) {
	velotok_delivery_provider_parity_assert( false === $error['ok'], 'Сбой ' . $provider . ' должен быть ошибкой, а не тарифом.' );
	velotok_delivery_provider_parity_assert( 'DELIVERY_PROVIDER_ERROR' === $error['code'], 'Сбой ' . $provider . ' должен иметь явный код ошибки.' );
	velotok_delivery_provider_parity_assert( true === $error['visible'], 'Сбой ' . $provider . ' должен быть видимым.' );
	velotok_delivery_provider_parity_assert( ! isset( $error['amount'] ), 'Сбой ' . $provider . ' не должен подменяться фиктивной суммой.' );
}

$address = array(
	'country'     => 'RU',
	'postcode'    => '101000',
	'city'        => 'Москва',
	'coordinates' => array( 'lat' => 55.7558, 'lon' => 37.6173 ),
);

$pickup_providers = array(
	'cdek'         => function () {
		return array(
			'points' => array(
				array(
					'id'       => 'CDEK-1',
					'provider' => 'cdek',
					'name'     => 'СДЭК в центре',
					'country'  => 'RU',
					'lat'      => 55.7559,
					'lon'      => 37.6174,
				),
			),
		);
	},
	'russian_post' => function () {
		return array(
			'points' => array(
				array(
					'id'       => 'POST-1',
					'provider' => 'russian_post',
					'name'     => 'Почта РФ',
					'country'  => 'RU',
					'lat'      => 55.7560,
					'lon'      => 37.6175,
				),
			),
		);
	},
);

$pickup = velotok_core_delivery_pickup_points(
	$address,
	null,
	array(
		'radius_km'               => 10,
		'pickup_point_providers'  => $pickup_providers,
	)
);

velotok_delivery_provider_parity_assert( true === $pickup['ok'], 'Список пунктов выдачи должен собираться от обоих провайдеров.' );
velotok_delivery_provider_parity_assert( array( 'cdek', 'russian_post' ) === array_column( $pickup['points'], 'provider' ), 'Пункты выдачи должны содержать оба провайдера.' );

$failing_pickup_providers = array(
	'cdek'         => function () {
		return array( 'ok' => false, 'error' => 'СДЭК недоступен' );
	},
	'russian_post' => function () {
		return array( 'ok' => false, 'error' => 'Почта недоступна' );
	},
);

$failing_pickup = velotok_core_delivery_pickup_points(
	$address,
	null,
	array(
		'radius_km'               => 10,
		'pickup_point_providers'  => $failing_pickup_providers,
	)
);

velotok_delivery_provider_parity_assert( false === $failing_pickup['ok'], 'Сбой списка пунктов не должен выглядеть успешным.' );
velotok_delivery_provider_parity_assert( 'DELIVERY_PICKUP_POINTS_ERROR' === $failing_pickup['code'], 'Сбой списка пунктов должен иметь явный код.' );
velotok_delivery_provider_parity_assert( array( 'cdek', 'russian_post' ) === array_column( $failing_pickup['errors'], 'provider' ), 'Ошибки обоих провайдеров должны быть видимы одинаково.' );

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
				'settings'    => array( 'base' => 500, 'per_kg' => 100, 'eta_min_days' => 2, 'eta_max_days' => 4 ),
			),
			array(
				'instance_id' => 102,
				'method_id'   => 'russian_post',
				'title'       => 'Почта РФ',
				'enabled'     => true,
				'settings'    => array( 'base' => 500, 'per_kg' => 100, 'eta_min_days' => 2, 'eta_max_days' => 4 ),
			),
		),
	),
);

$result = velotok_core_delivery_zone_methods( $address, $zones, array( 'order' => array( 'subtotal' => 1000, 'weight' => 2 ) ) );

velotok_delivery_provider_parity_assert( isset( $result['methods']['cdek'] ), 'Способ СДЭК должен предлагаться из зоны.' );
velotok_delivery_provider_parity_assert( isset( $result['methods']['russian_post'] ), 'Способ Почты РФ должен предлагаться из зоны.' );
velotok_delivery_provider_parity_assert( true === $result['methods']['cdek']['available'], 'Способ СДЭК должен быть доступен как и Почта РФ.' );
velotok_delivery_provider_parity_assert( true === $result['methods']['russian_post']['available'], 'Способ Почты РФ должен быть доступен как и СДЭК.' );
velotok_delivery_provider_parity_assert( (float) $result['methods']['cdek']['cost'] === (float) $result['methods']['russian_post']['cost'], 'Одинаковые тарифы обоих способов должны давать одинаковую стоимость.' );
velotok_delivery_provider_parity_assert( '2–4 дн.' === $result['methods']['cdek']['eta_label'] && '2–4 дн.' === $result['methods']['russian_post']['eta_label'], 'Срок обоих провайдеров должен задаваться одинаково.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: адаптеры СДЭК и Почты РФ поддерживаются равноправно.' . PHP_EOL;
