<?php
/**
 * Contract test for delivery methods sourced from WooCommerce shipping zones.
 *
 * Requirement TESTEB-28 / spec delivery:
 * «Система SHALL предлагать способы доставки из зон WooCommerce».
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-zones-contract-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$module_path = getenv( 'VELOTOK_DELIVERY_ZONES_STUB' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__ ) . '/includes/delivery-zones.php';
}

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль способов доставки из зон WooCommerce.' . PHP_EOL );
	exit( 1 );
}

require_once $module_path;

$failures = array();

function velotok_delivery_zones_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = velotok_core_delivery_zones_contract();

velotok_delivery_zones_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
velotok_delivery_zones_assert( isset( $contract['requirement'] ) && 'TESTEB-28' === $contract['requirement'], 'Контракт должен ссылаться на TESTEB-28.' );
velotok_delivery_zones_assert( isset( $contract['source'] ) && 'woocommerce_zones' === $contract['source'], 'Источник способов доставки должен быть woocommerce_zones.' );
velotok_delivery_zones_assert( isset( $contract['reads_woocommerce_zones'] ) && true === $contract['reads_woocommerce_zones'], 'Контракт должен читать зоны WooCommerce.' );
velotok_delivery_zones_assert( isset( $contract['supported_providers'] ) && in_array( 'cdek', $contract['supported_providers'], true ), 'СДЭК должен поддерживаться.' );
velotok_delivery_zones_assert( isset( $contract['supported_providers'] ) && in_array( 'russian_post', $contract['supported_providers'], true ), 'Почта РФ должна поддерживаться.' );
velotok_delivery_zones_assert( isset( $contract['pickup']['independent_of_zone'] ) && true === $contract['pickup']['independent_of_zone'], 'Самовывоз должен быть независим от зоны.' );
velotok_delivery_zones_assert( isset( $contract['pickup']['only_russia'] ) && true === $contract['pickup']['only_russia'], 'Самовывоз должен ограничиваться Россией.' );
velotok_delivery_zones_assert( isset( $contract['provider_error']['fallback_tariff'] ) && false === $contract['provider_error']['fallback_tariff'], 'Ошибка провайдера не должна подменяться фиктивным тарифом.' );
velotok_delivery_zones_assert( isset( $contract['eta']['format'] ) && 'range' === $contract['eta']['format'], 'Срок доставки должен задаваться диапазоном.' );

$zones = array(
	array(
		'zone_id'       => 10,
		'zone_name'     => 'Москва',
		'zone_order'    => 1,
		'zone_locations'=> array(
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
					'free_from'    => 5000,
					'eta_min_days' => 1,
					'eta_max_days' => 3,
				),
			),
			array(
				'instance_id' => 102,
				'method_id'   => 'russian_post',
				'title'       => 'Почта РФ',
				'enabled'     => true,
				'settings'    => array(
					'base'         => 350,
					'per_kg'       => 80,
					'free_from'    => 7000,
					'eta_min_days' => 3,
					'eta_max_days' => 7,
				),
			),
		),
	),
	array(
		'zone_id'       => 20,
		'zone_name'     => 'Казахстан',
		'zone_order'    => 2,
		'zone_locations'=> array(
			array( 'type' => 'country', 'code' => 'KZ' ),
		),
		'shipping_methods' => array(
			array(
				'instance_id' => 201,
				'method_id'   => 'cdek',
				'title'       => 'СДЭК',
				'enabled'     => true,
				'settings'    => array(
					'base'         => 900,
					'per_kg'       => 150,
					'free_from'    => null,
					'eta_min_days' => 2,
					'eta_max_days' => 5,
				),
			),
		),
	),
);

$address = array(
	'country'  => 'RU',
	'postcode' => '101000',
	'city'     => 'Москва',
);

$result = velotok_core_delivery_zone_methods(
	$address,
	$zones,
	array( 'order' => array( 'subtotal' => 1000, 'weight' => 2 ) )
);

velotok_delivery_zones_assert( true === $result['zone_matched'], 'Адрес должен попадать в зону Москвы.' );
velotok_delivery_zones_assert( 10 === $result['zone_id'], 'Должна выбираться зона Москвы.' );
velotok_delivery_zones_assert( isset( $result['methods']['cdek'] ), 'Способ СДЭК должен предлагаться из зоны.' );
velotok_delivery_zones_assert( isset( $result['methods']['russian_post'] ), 'Способ Почты РФ должен предлагаться из зоны.' );
velotok_delivery_zones_assert( isset( $result['methods']['local_pickup'] ), 'Самовывоз должен предлагаться.' );
velotok_delivery_zones_assert( true === $result['available'], 'Должны быть доступные способы доставки.' );

$cdek = $result['methods']['cdek'];
velotok_delivery_zones_assert( 'СДЭК' === $cdek['label'], 'Подпись СДЭК должна браться из зоны.' );
velotok_delivery_zones_assert( 1 === $cdek['eta']['min_days'] && 3 === $cdek['eta']['max_days'], 'Срок СДЭК должен храниться диапазоном.' );
velotok_delivery_zones_assert( '1–3 дн.' === $cdek['eta_label'], 'Срок СДЭК должен отображаться диапазоном.' );
velotok_delivery_zones_assert( 700.0 === (float) $cdek['cost'], 'Стоимость СДЭК должна считаться по base + per_kg с округлением веса вверх.' );

$free_result = velotok_core_delivery_zone_methods(
	$address,
	$zones,
	array( 'order' => array( 'subtotal' => 6000, 'weight' => 2 ) )
);
velotok_delivery_zones_assert( 0.0 === (float) $free_result['methods']['cdek']['cost'], 'Порог бесплатной доставки должен обнулять стоимость.' );
velotok_delivery_zones_assert( true === $free_result['methods']['cdek']['free'], 'Бесплатная доставка должна быть отмечена.' );

$weight_result = velotok_core_delivery_zone_methods(
	$address,
	$zones,
	array(
		'order' => array(
			'subtotal' => 1000,
			'items'    => array(
				array( 'weight' => 1.1, 'quantity' => 2 ),
			),
		),
	)
);
velotok_delivery_zones_assert( 800.0 === (float) $weight_result['methods']['cdek']['cost'], 'Вес заказа должен однозначно суммироваться по товарам и округляться вверх.' );

$ambiguous_weight = velotok_core_delivery_zone_methods(
	$address,
	$zones,
	array(
		'order' => array(
			'subtotal' => 1000,
			'items'    => array(
				array( 'quantity' => 1 ),
			),
		),
	)
);
velotok_delivery_zones_assert( false === $ambiguous_weight['methods']['cdek']['available'], 'Неоднозначный вес не должен давать тариф.' );
velotok_delivery_zones_assert( 'DELIVERY_WEIGHT_AMBIGUOUS' === $ambiguous_weight['methods']['cdek']['error_code'], 'Неоднозначный вес должен давать явную ошибку.' );

$out_of_zone = velotok_core_delivery_zone_methods(
	array( 'country' => 'RU', 'postcode' => '999999', 'city' => 'Неизвестск' ),
	$zones,
	array( 'order' => array( 'subtotal' => 1000, 'weight' => 1 ) )
);
velotok_delivery_zones_assert( false === $out_of_zone['zone_matched'], 'Адрес вне зон не должен попадать в зону.' );
velotok_delivery_zones_assert( 'DELIVERY_ZONE_NOT_FOUND' === $out_of_zone['code'], 'Адрес вне зон должен давать DELIVERY_ZONE_NOT_FOUND.' );
velotok_delivery_zones_assert( array( 'local_pickup' ) === array_keys( $out_of_zone['methods'] ), 'Вне зон должен оставаться только самовывоз.' );

$ambiguous = velotok_core_delivery_zone_methods(
	array(),
	$zones,
	array( 'order' => array( 'subtotal' => 1000, 'weight' => 1 ) )
);
velotok_delivery_zones_assert( 'DELIVERY_ADDRESS_AMBIGUOUS' === $ambiguous['code'], 'Неоднозначный адрес должен давать DELIVERY_ADDRESS_AMBIGUOUS.' );
velotok_delivery_zones_assert( array_keys( $out_of_zone['methods'] ) === array_keys( $ambiguous['methods'] ), 'Адрес вне зон и неоднозначный адрес должны давать одинаковые способы.' );
velotok_delivery_zones_assert( array( 'local_pickup' ) === array_keys( $ambiguous['methods'] ), 'Для неоднозначного адреса должен оставаться самовывоз.' );

$foreign = velotok_core_delivery_zone_methods(
	array( 'country' => 'KZ', 'city' => 'Алматы' ),
	$zones,
	array( 'order' => array( 'subtotal' => 1000, 'weight' => 1 ) )
);
velotok_delivery_zones_assert( true === $foreign['zone_matched'], 'Адрес Казахстана должен попадать в зону.' );
velotok_delivery_zones_assert( ! isset( $foreign['methods']['local_pickup'] ), 'За пределами России самовывоз не показывается.' );
velotok_delivery_zones_assert( false === $foreign['pickup'], 'Флаг самовывоза должен быть выключен за пределами России.' );

$error_zones = $zones;
$error_zones[0]['shipping_methods'][0]['rate_provider'] = function () {
	return array( 'error' => 'СДЭК временно недоступен' );
};

$provider_error = velotok_core_delivery_zone_methods(
	$address,
	$error_zones,
	array( 'order' => array( 'subtotal' => 1000, 'weight' => 2 ) )
);
velotok_delivery_zones_assert( false === $provider_error['methods']['cdek']['available'], 'Ошибка провайдера должна делать способ недоступным.' );
velotok_delivery_zones_assert( null === $provider_error['methods']['cdek']['cost'], 'Ошибка провайдера не должна подменяться фиктивным тарифом.' );
velotok_delivery_zones_assert( 'DELIVERY_PROVIDER_ERROR' === $provider_error['methods']['cdek']['error_code'], 'Ошибка провайдера должна быть видимой.' );
velotok_delivery_zones_assert( 510.0 === (float) $provider_error['methods']['russian_post']['cost'], 'Рабочий адаптер должен продолжать считать свой тариф.' );

$numeric_zones = $zones;
$numeric_zones[0]['shipping_methods'][0]['rate_provider'] = function () {
	return 777;
};

$numeric_provider = velotok_core_delivery_zone_methods(
	$address,
	$numeric_zones,
	array( 'order' => array( 'subtotal' => 1000, 'weight' => 2 ) )
);
velotok_delivery_zones_assert( true === $numeric_provider['methods']['cdek']['available'], 'Числовой тариф провайдера должен приниматься.' );
velotok_delivery_zones_assert( 777.0 === (float) $numeric_provider['methods']['cdek']['cost'], 'Числовой тариф провайдера должен попадать в стоимость.' );

$min_amount_zones = $zones;
$min_amount_zones[0]['shipping_methods'][0]['settings']['min_amount'] = 5000;
unset( $min_amount_zones[0]['shipping_methods'][0]['settings']['free_from'] );

$min_amount_free = velotok_core_delivery_zone_methods(
	$address,
	$min_amount_zones,
	array( 'order' => array( 'subtotal' => 6000, 'weight' => 2 ) )
);
velotok_delivery_zones_assert( true === $min_amount_free['methods']['cdek']['free'], 'Порог бесплатной доставки должен читаться из настроек зоны.' );
velotok_delivery_zones_assert( 0.0 === (float) $min_amount_free['methods']['cdek']['cost'], 'Порог бесплатной доставки должен обнулять тариф зоны.' );

$single = velotok_core_delivery_zone_option( 'cdek', $address, $zones, array( 'order' => array( 'subtotal' => 1000, 'weight' => 1 ) ) );
velotok_delivery_zones_assert( is_array( $single ) && 'cdek' === $single['method_id'], 'Должен возвращаться отдельный способ по идентификатору.' );

$fake_wc_zones = ! class_exists( 'WC_Shipping_Zones' );

if ( ! class_exists( 'WC_Shipping_Zone' ) ) {
	class WC_Shipping_Zone {
		public $zone_id;

		public function __construct( $zone_id = 0 ) {
			$this->zone_id = (int) $zone_id;
		}

		public function get_id() {
			return $this->zone_id;
		}

		public function get_zone_name() {
			return 0 === $this->zone_id ? 'Rest of the world' : 'Зона WooCommerce';
		}

		public function get_zone_locations() {
			return array();
		}

		public function get_shipping_methods() {
			return array();
		}
	}
}

if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
	class WC_Shipping_Zones {
		public static function get_zones() {
			return array(
				array(
					'zone_id'          => 30,
					'zone_name'        => 'WooCommerce Москва',
					'zone_order'       => 1,
					'zone_locations'   => array(
						array( 'type' => 'postcode', 'code' => '101000' ),
					),
					'shipping_methods' => array(
						array(
							'instance_id' => 301,
							'method_id'   => 'cdek',
							'title'       => 'СДЭК',
							'enabled'     => true,
							'settings'    => array( 'base' => 500, 'per_kg' => 100, 'eta_min_days' => 1, 'eta_max_days' => 3 ),
						),
					),
				),
			);
		}
	}
}

if ( $fake_wc_zones ) {
	$wc_result = velotok_core_delivery_zone_methods( $address, null, array( 'order' => array( 'subtotal' => 1000, 'weight' => 1 ) ) );
	velotok_delivery_zones_assert( true === $wc_result['zone_matched'] && 30 === $wc_result['zone_id'], 'Способы должны читаться напрямую из зон WooCommerce.' );
	velotok_delivery_zones_assert( isset( $wc_result['methods']['cdek'] ), 'Способ из зоны WooCommerce должен предлагаться.' );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: способы доставки из зон WooCommerce, тарифы, сроки и самовывоз на месте.' . PHP_EOL;
