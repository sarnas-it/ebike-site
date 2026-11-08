<?php
/**
 * Contract test for the explicit provider test mode.
 *
 * Requirement TESTEB-39 / spec delivery:
 * «Тестовый режим провайдера SHALL быть явным».
 *
 * The test proves that a test-mode provider is never silently presented as a
 * live one: the flag is read from the shipping method settings and from the
 * provider response, is returned on every delivery option, and the customer
 * banner is rendered only while test mode is enabled.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-test-mode-contract-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$module_path = getenv( 'VELOTOK_DELIVERY_TEST_MODE_STUB' );

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
 * Assert a delivery test-mode condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_delivery_test_mode_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Build a Moscow zone with the supplied shipping methods.
 *
 * @param array $methods Shipping methods.
 * @return array
 */
function velotok_delivery_test_mode_zone( $methods ) {
	return array(
		array(
			'zone_id'          => 10,
			'zone_name'        => 'Москва',
			'zone_order'       => 1,
			'zone_locations'   => array(
				array( 'type' => 'postcode', 'code' => '101*' ),
			),
			'shipping_methods' => $methods,
		),
	);
}

$address = array(
	'country'  => 'RU',
	'postcode' => '101000',
	'city'     => 'Москва',
);
$order   = array( 'subtotal' => 1000, 'weight' => 2 );

$contract = velotok_core_delivery_zones_contract();

velotok_delivery_test_mode_assert( is_array( $contract ), 'Контракт доставки должен быть массивом.' );
velotok_delivery_test_mode_assert( in_array( 'TESTEB-39', $contract['requirement_ids'], true ), 'Контракт должен включать требование TESTEB-39.' );
velotok_delivery_test_mode_assert( 'TESTEB-39' === $contract['test_mode']['requirement'], 'Раздел test_mode должен ссылаться на TESTEB-39.' );
velotok_delivery_test_mode_assert( true === $contract['test_mode']['explicit'], 'Тестовый режим должен быть явным.' );
velotok_delivery_test_mode_assert( true === $contract['test_mode']['visible'], 'Тестовый режим должен быть видимым.' );
velotok_delivery_test_mode_assert( true === $contract['test_mode']['buyer_visible'], 'Тестовый режим должен быть виден покупателю.' );

velotok_delivery_test_mode_assert( true === velotok_core_delivery_test_mode_normalize( 'yes' ), 'yes должен означать тестовый режим.' );
velotok_delivery_test_mode_assert( true === velotok_core_delivery_test_mode_normalize( 'sandbox' ), 'sandbox должен означать тестовый режим.' );
velotok_delivery_test_mode_assert( false === velotok_core_delivery_test_mode_normalize( 'live' ), 'live не должен означать тестовый режим.' );

$configured = velotok_core_delivery_zone_methods(
	$address,
	velotok_delivery_test_mode_zone(
		array(
			array(
				'instance_id' => 101,
				'method_id'   => 'cdek',
				'title'       => 'СДЭК',
				'enabled'     => true,
				'settings'    => array( 'base' => 500, 'per_kg' => 100, 'test_mode' => 'yes' ),
			),
			array(
				'instance_id' => 102,
				'method_id'   => 'russian_post',
				'title'       => 'Почта РФ',
				'enabled'     => true,
				'settings'    => array( 'base' => 350, 'per_kg' => 80, 'test_mode' => 'no' ),
			),
		)
	),
	array( 'order' => $order )
);

velotok_delivery_test_mode_assert( true === $configured['test_mode'], 'Тестовый режим способа должен быть виден в результате доставки.' );
velotok_delivery_test_mode_assert( true === $configured['test_mode_explicit'], 'Результат должен явно сообщать тестовый режим.' );
velotok_delivery_test_mode_assert( array( 'cdek' ) === $configured['test_mode_providers'], 'Только СДЭК должен быть в тестовом режиме.' );
velotok_delivery_test_mode_assert( true === $configured['methods']['cdek']['test_mode'], 'Настройка test_mode СДЭК должна попадать в способ доставки.' );
velotok_delivery_test_mode_assert( true === $configured['methods']['cdek']['test_mode_explicit'], 'Способ СДЭК должен явно сообщать тестовый режим.' );
velotok_delivery_test_mode_assert( 'method' === $configured['methods']['cdek']['test_mode_source'], 'Источник тестового режима должен быть явным.' );
velotok_delivery_test_mode_assert( false === $configured['methods']['russian_post']['test_mode'], 'Почта РФ не должна попадать в тестовый режим.' );
velotok_delivery_test_mode_assert( false !== strpos( $configured['test_mode_banner'], 'Тестовый режим доставки' ), 'Баннер тестового режима должен быть сформирован.' );

$live = velotok_core_delivery_zone_methods(
	$address,
	velotok_delivery_test_mode_zone(
		array(
			array(
				'instance_id' => 101,
				'method_id'   => 'cdek',
				'title'       => 'СДЭК',
				'enabled'     => true,
				'settings'    => array( 'base' => 500, 'per_kg' => 100, 'test_mode' => 'no' ),
			),
		)
	),
	array( 'order' => $order )
);

velotok_delivery_test_mode_assert( false === $live['test_mode'], 'Без тестового режима результат не должен его заявлять.' );
velotok_delivery_test_mode_assert( true === $live['test_mode_explicit'], 'Отсутствие тестового режима тоже должно быть явным.' );
velotok_delivery_test_mode_assert( false === $live['methods']['cdek']['test_mode'], 'Способ без test_mode не должен считаться тестовым.' );
velotok_delivery_test_mode_assert( '' === $live['test_mode_banner'], 'Без тестового режима баннер не выводится.' );

$provider_zones = velotok_delivery_test_mode_zone(
	array(
		array(
			'instance_id'  => 103,
			'method_id'    => 'cdek',
			'title'        => 'СДЭК',
			'enabled'      => true,
			'settings'     => array( 'base' => 500, 'per_kg' => 100 ),
			'rate_provider'=> function () {
				return array( 'amount' => 900, 'currency' => 'RUB', 'test_mode' => true );
			},
		),
	)
);

$provider = velotok_core_delivery_zone_methods( $address, $provider_zones, array( 'order' => $order ) );

velotok_delivery_test_mode_assert( true === $provider['methods']['cdek']['test_mode'], 'Тестовый режим, заявленный провайдером, должен быть виден.' );
velotok_delivery_test_mode_assert( 'provider' === $provider['methods']['cdek']['test_mode_source'], 'Источник тестового режима провайдера должен быть явным.' );
velotok_delivery_test_mode_assert( true === $provider['methods']['cdek']['tariff']['test_mode'], 'Тариф провайдера должен сохранять признак тестового режима.' );
velotok_delivery_test_mode_assert( array( 'cdek' ) === $provider['test_mode_providers'], 'Провайдер тестового режима должен попадать в сводку.' );
velotok_delivery_test_mode_assert( true === $provider['test_mode'], 'Сводка доставки должна сообщать тестовый режим провайдера.' );
velotok_delivery_test_mode_assert( true === velotok_core_delivery_method_test_mode( array( 'method_id' => 'cdek' ), array( 'test_mode' => true ) ), 'Контекст тестового режима должен учитываться.' );

velotok_delivery_test_mode_assert( '' === velotok_core_delivery_test_mode_banner( false ), 'Без тестового режима баннер пуст.' );
$banner = velotok_core_delivery_test_mode_banner( true );
velotok_delivery_test_mode_assert( false !== strpos( $banner, 'data-test-mode="true"' ), 'Баннер должен явно помечать тестовый режим.' );
velotok_delivery_test_mode_assert( false !== strpos( $banner, 'Тестовый режим доставки' ), 'Баннер должен содержать понятный покупателю текст.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: тестовый режим провайдера доставки явно сообщается и не подменяется рабочим тарифом.' . PHP_EOL;
