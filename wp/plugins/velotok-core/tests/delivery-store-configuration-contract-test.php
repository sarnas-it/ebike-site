<?php
/**
 * Contract test for store-configured delivery options, ETA and recalculation.
 *
 * Requirement TESTEB-41 / spec delivery:
 * «Варианты доставки, сроки и пересчёт SHALL быть заданы конфигурацией
 * магазина».
 *
 * The test proves that the offered delivery options, the delivery ETA range
 * and the cost recalculation are taken from the store configuration instead
 * of being hard-coded.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-store-configuration-contract-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$module_path = getenv( 'VELOTOK_DELIVERY_CONFIG_STUB' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__ ) . '/includes/delivery-zones.php';
}

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль конфигурации доставки.' . PHP_EOL );
	exit( 1 );
}

require_once $module_path;

$failures = array();

/**
 * Assert a store delivery configuration condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_delivery_config_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Build a store configuration as WooCommerce keeps it in a shipping zone.
 *
 * @param array $cdek_settings          СДЭК settings.
 * @param array $russian_post_settings  Почта РФ settings.
 * @param bool  $cdek_enabled           Whether СДЭК is offered.
 * @return array
 */
function velotok_delivery_config_zones( $cdek_settings, $russian_post_settings, $cdek_enabled = true ) {
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
					'title'       => isset( $cdek_settings['title'] ) ? $cdek_settings['title'] : 'СДЭК',
					'enabled'     => $cdek_enabled,
					'settings'    => $cdek_settings,
				),
				array(
					'instance_id' => 102,
					'method_id'   => 'russian_post',
					'title'       => isset( $russian_post_settings['title'] ) ? $russian_post_settings['title'] : 'Почта РФ',
					'enabled'     => true,
					'settings'    => $russian_post_settings,
				),
			),
		),
	);
}

$contract = velotok_core_delivery_zones_contract();

velotok_delivery_config_assert(
	isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-41', $contract['requirement_ids'], true ),
	'Контракт доставки должен ссылаться на TESTEB-41.'
);
velotok_delivery_config_assert(
	isset( $contract['configuration']['requirement'] ) && 'TESTEB-41' === $contract['configuration']['requirement'],
	'Раздел конфигурации доставки должен ссылаться на TESTEB-41.'
);
velotok_delivery_config_assert(
	isset( $contract['configuration']['source'] ) && 'woocommerce_store_configuration' === $contract['configuration']['source'],
	'Источником вариантов, сроков и пересчёта должна быть конфигурация магазина.'
);
velotok_delivery_config_assert(
	isset( $contract['configuration']['reads_store_configuration'] ) && true === $contract['configuration']['reads_store_configuration'],
	'Контракт должен явно читать конфигурацию магазина.'
);
velotok_delivery_config_assert(
	isset( $contract['configuration']['options_source'] ) && 'woocommerce_shipping_zone_methods' === $contract['configuration']['options_source'],
	'Варианты доставки должны задаваться способами зон WooCommerce.'
);
velotok_delivery_config_assert(
	isset( $contract['configuration']['eta_source'] ) && 'shipping_method_settings' === $contract['configuration']['eta_source'],
	'Сроки доставки должны задаваться настройками способа доставки.'
);
velotok_delivery_config_assert(
	isset( $contract['configuration']['recalculation_source'] ) && 'shipping_method_settings' === $contract['configuration']['recalculation_source'],
	'Пересчёт стоимости должен задаваться настройками способа доставки.'
);
velotok_delivery_config_assert(
	isset( $contract['configuration']['hardcoded_tariffs'] ) && false === $contract['configuration']['hardcoded_tariffs'],
	'Тарифы не должны быть захардкожены.'
);
velotok_delivery_config_assert(
	isset( $contract['configuration']['mocked'] ) && false === $contract['configuration']['mocked'],
	'Конфигурация не должна подменяться моком.'
);

$address = array(
	'country'  => 'RU',
	'postcode' => '101000',
	'city'     => 'Москва',
);

$cdek_settings = array(
	'title'        => 'СДЭК из настроек',
	'base'         => 400,
	'per_kg'       => 50,
	'free_from'    => 3000,
	'eta_min_days' => 2,
	'eta_max_days' => 4,
);

$post_settings = array(
	'title'        => 'Почта из настроек',
	'base'         => 300,
	'per_kg'       => 40,
	'free_from'    => 6000,
	'eta_min_days' => 5,
	'eta_max_days' => 9,
);

$zones   = velotok_delivery_config_zones( $cdek_settings, $post_settings );
$context = array( 'order' => array( 'subtotal' => 1000, 'weight' => 2 ) );

$cdek_config = velotok_core_delivery_store_method_configuration( $zones[0]['shipping_methods'][0], $context );

velotok_delivery_config_assert( isset( $cdek_config['requirement'] ) && 'TESTEB-41' === $cdek_config['requirement'], 'Конфигурация способа должна ссылаться на TESTEB-41.' );
velotok_delivery_config_assert( 'cdek' === $cdek_config['method_id'], 'Конфигурация должна содержать способ доставки.' );
velotok_delivery_config_assert( 'СДЭК из настроек' === $cdek_config['label'], 'Название варианта должно браться из настроек магазина.' );
velotok_delivery_config_assert( 2 === $cdek_config['eta']['min_days'] && 4 === $cdek_config['eta']['max_days'], 'Срок варианта должен браться из настроек магазина.' );
velotok_delivery_config_assert( '2–4 дн.' === $cdek_config['eta_label'], 'Срок варианта должен отображаться диапазоном из настроек.' );
velotok_delivery_config_assert( 400.0 === (float) $cdek_config['recalculation']['base'], 'Базовая стоимость должна браться из настроек магазина.' );
velotok_delivery_config_assert( 50.0 === (float) $cdek_config['recalculation']['per_kg'], 'Стоимость за кг должна браться из настроек магазина.' );
velotok_delivery_config_assert( 3000.0 === (float) $cdek_config['recalculation']['free_from'], 'Порог бесплатной доставки должен браться из настроек магазина.' );
velotok_delivery_config_assert( 'ceil' === $cdek_config['recalculation']['weight_rounding'], 'Правило пересчёта веса должно задаваться конфигурацией.' );
velotok_delivery_config_assert( true === $cdek_config['configured'], 'Конфигурация должна быть реальной, а не фиктивной.' );
velotok_delivery_config_assert( false === $cdek_config['mocked'], 'Конфигурация не должна подменяться моком.' );

$store_config = velotok_core_delivery_store_configuration( $zones[0], $context );

velotok_delivery_config_assert( isset( $store_config['requirement'] ) && 'TESTEB-41' === $store_config['requirement'], 'Конфигурация зоны должна ссылаться на TESTEB-41.' );
velotok_delivery_config_assert( 10 === (int) $store_config['zone_id'], 'Конфигурация должна быть привязана к зоне магазина.' );
velotok_delivery_config_assert( array( 'cdek', 'russian_post' ) === $store_config['configured_methods'], 'Варианты доставки должны определяться настройками зоны.' );
velotok_delivery_config_assert( true === $store_config['reads_store_configuration'], 'Конфигурация зоны должна явно читать настройки магазина.' );
velotok_delivery_config_assert( 'СДЭК из настроек' === $store_config['methods']['cdek']['label'], 'Конфигурация зоны должна содержать название СДЭК из настроек.' );
velotok_delivery_config_assert( 'Почта из настроек' === $store_config['methods']['russian_post']['label'], 'Конфигурация зоны должна содержать название Почты РФ из настроек.' );
velotok_delivery_config_assert( 5 === $store_config['methods']['russian_post']['eta']['min_days'] && 9 === $store_config['methods']['russian_post']['eta']['max_days'], 'Срок Почты РФ должен браться из настроек зоны.' );

$result = velotok_core_delivery_zone_methods( $address, $zones, $context );

velotok_delivery_config_assert( isset( $result['configuration'] ) && 'TESTEB-41' === $result['configuration']['requirement'], 'Результат доставки должен содержать конфигурацию магазина.' );
velotok_delivery_config_assert( 'woocommerce_store_configuration' === $result['configuration_source'], 'Источник конфигурации должен быть явным.' );
velotok_delivery_config_assert( 'СДЭК из настроек' === $result['methods']['cdek']['label'], 'Вариант СДЭК должен быть взят из настроек.' );
velotok_delivery_config_assert( 500.0 === (float) $result['methods']['cdek']['cost'], 'Стоимость СДЭК должна пересчитываться по настройкам зоны.' );
velotok_delivery_config_assert( '2–4 дн.' === $result['methods']['cdek']['eta_label'], 'Срок СДЭК должен быть взят из настроек.' );
velotok_delivery_config_assert( 'СДЭК из настроек' === $result['methods']['cdek']['configuration']['label'], 'Способ должен содержать свою конфигурацию.' );
velotok_delivery_config_assert( 400.0 === (float) $result['methods']['cdek']['configuration']['recalculation']['base'], 'Способ должен содержать базовую стоимость из настроек.' );

$changed_settings = array(
	'title'        => 'СДЭК Экспресс',
	'base'         => 900,
	'per_kg'       => 10,
	'free_from'    => 1000,
	'eta_min_days' => 1,
	'eta_max_days' => 2,
);

$changed_zones  = velotok_delivery_config_zones( $changed_settings, $post_settings );
$changed_result = velotok_core_delivery_zone_methods( $address, $changed_zones, $context );

velotok_delivery_config_assert( 'СДЭК Экспресс' === $changed_result['methods']['cdek']['label'], 'Изменение названия в настройках должно менять вариант доставки.' );
velotok_delivery_config_assert( '1–2 дн.' === $changed_result['methods']['cdek']['eta_label'], 'Изменение срока в настройках должно менять срок доставки.' );
velotok_delivery_config_assert( true === $changed_result['methods']['cdek']['free'], 'Изменение порога в настройках должно включать бесплатную доставку.' );
velotok_delivery_config_assert( 0.0 === (float) $changed_result['methods']['cdek']['cost'], 'Пересчёт должен учитывать новый порог бесплатной доставки.' );

$disabled_zones    = velotok_delivery_config_zones( $cdek_settings, $post_settings, false );
$disabled_result   = velotok_core_delivery_zone_methods( $address, $disabled_zones, $context );
$disabled_store    = velotok_core_delivery_store_configuration( $disabled_zones[0], $context );

velotok_delivery_config_assert( false === $disabled_result['methods']['cdek']['available'], 'Отключение способа в настройках должно скрывать вариант.' );
velotok_delivery_config_assert( false === $disabled_store['methods']['cdek']['enabled'], 'Конфигурация должна отражать отключение способа.' );

$default_zones  = velotok_delivery_config_zones( array(), array() );
$default_result = velotok_core_delivery_zone_methods( $address, $default_zones, $context );
$default_config = velotok_core_delivery_store_method_configuration( $default_zones[0]['shipping_methods'][0], $context );

velotok_delivery_config_assert( 0.0 === (float) $default_config['recalculation']['base'], 'Без настройки базовая стоимость должна оставаться нулевой.' );
velotok_delivery_config_assert( 0.0 === (float) $default_config['recalculation']['per_kg'], 'Без настройки стоимость за кг должна оставаться нулевой.' );
velotok_delivery_config_assert( null === $default_config['recalculation']['free_from'], 'Без настройки порог бесплатной доставки должен отсутствовать.' );
velotok_delivery_config_assert( 0.0 === (float) $default_result['methods']['cdek']['cost'], 'Без настроек не должен появляться фиктивный тариф.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: варианты доставки, сроки и пересчёт задаются конфигурацией магазина.' . PHP_EOL;
