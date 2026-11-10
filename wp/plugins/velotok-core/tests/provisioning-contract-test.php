<?php
/**
 * Contract test for store provisioning (EBIKE-23).
 *
 * A fresh shop must already have a delivery zone with methods, enabled payment
 * gateways and the Russian language pack, otherwise checkout answers
 * «No payment method provided». The test stubs the small WordPress/WooCommerce
 * surface and proves that provisioning is complete and idempotent.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/provisioning-contract-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_test_options']       = array();
$GLOBALS['velotok_test_zones']         = array();
$GLOBALS['velotok_test_next_zone']     = 0;
$GLOBALS['velotok_test_next_instance'] = 0;
$GLOBALS['velotok_test_added_methods'] = array();

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['velotok_test_options'] ) ? $GLOBALS['velotok_test_options'][ $name ] : $default;
}

function update_option( $name, $value, $autoload = null ) {
	unset( $autoload );

	$GLOBALS['velotok_test_options'][ $name ] = $value;

	return true;
}

function sanitize_text_field( $value ) {
	return trim( (string) $value );
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

class WC_Shipping_Zone {
	public $id;
	public $name      = '';
	public $locations = array();
	public $methods   = array();
	public $saves     = 0;

	public function __construct( $zone_id = 0 ) {
		$this->id = (int) $zone_id;

		if ( $this->id > 0 && isset( $GLOBALS['velotok_test_zones'][ $this->id ] ) ) {
			$source          = $GLOBALS['velotok_test_zones'][ $this->id ];
			$this->name      = $source->name;
			$this->locations = $source->locations;
			$this->methods   = $source->methods;
		}
	}

	public function get_id() {
		return $this->id;
	}

	public function set_zone_name( $name ) {
		$this->name = (string) $name;
	}

	public function get_zone_name() {
		return $this->name;
	}

	public function set_locations( $locations ) {
		$this->locations = array();

		foreach ( (array) $locations as $location ) {
			$this->locations[] = array(
				'type' => isset( $location['type'] ) ? (string) $location['type'] : '',
				'code' => isset( $location['code'] ) ? (string) $location['code'] : '',
			);
		}
	}

	public function get_zone_locations() {
		return array_map(
			function ( $location ) {
				return (object) $location;
			},
			$this->locations
		);
	}

	public function get_shipping_methods( $enabled_only = false ) {
		unset( $enabled_only );

		return $this->methods;
	}

	public function add_shipping_method( $method_id ) {
		$instance_id = ++$GLOBALS['velotok_test_next_instance'];

		$this->methods[ $instance_id ] = (object) array(
			'id'          => (string) $method_id,
			'instance_id' => $instance_id,
		);

		$GLOBALS['velotok_test_added_methods'][] = (string) $method_id;

		return $instance_id;
	}

	public function save() {
		if ( ! $this->id ) {
			$this->id = ++$GLOBALS['velotok_test_next_zone'];
		}

		++$this->saves;
		$GLOBALS['velotok_test_zones'][ $this->id ] = $this;

		return $this->id;
	}
}

class WC_Shipping_Zones {
	public static function get_zones() {
		$zones = array();

		foreach ( $GLOBALS['velotok_test_zones'] as $zone ) {
			$zones[] = array(
				'zone_id'   => $zone->id,
				'zone_name' => $zone->name,
			);
		}

		return $zones;
	}
}

require_once dirname( __DIR__ ) . '/includes/provisioning.php';

$failures = array();

function velotok_provisioning_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = velotok_core_provisioning_contract();

velotok_provisioning_assert( isset( $contract['task'] ) && 'EBIKE-23' === $contract['task'], 'Контракт должен ссылаться на EBIKE-23.' );
velotok_provisioning_assert( isset( $contract['requirement'] ) && 'EBIKE-23' === $contract['requirement'], 'Требование контракта должно быть EBIKE-23.' );
velotok_provisioning_assert( isset( $contract['checkout_requirement'] ) && 'TESTEB-23' === $contract['checkout_requirement'], 'Критерий checkout должен ссылаться на TESTEB-23.' );
velotok_provisioning_assert( isset( $contract['idempotent'] ) && true === $contract['idempotent'], 'Провижининг должен быть идемпотентным.' );
velotok_provisioning_assert( isset( $contract['checkout']['creates_order'] ) && true === $contract['checkout']['creates_order'], 'Критерий «checkout создаёт заказ» должен быть объявлен.' );

velotok_provisioning_assert( isset( $contract['zone']['name'] ) && 'Россия' === $contract['zone']['name'], 'Должна провижиниться зона «Россия».' );
velotok_provisioning_assert(
	isset( $contract['zone']['locations'][0] ) && 'RU' === $contract['zone']['locations'][0]['code'] && 'country' === $contract['zone']['locations'][0]['type'],
	'Зона «Россия» должна содержать локацию RU.'
);

foreach ( array( 'cdek', 'russian_post', 'local_pickup' ) as $method_id ) {
	velotok_provisioning_assert( isset( $contract['zone']['methods'][ $method_id ] ), sprintf( 'Способ доставки «%s» должен быть в плане провижининга.', $method_id ) );
}

velotok_provisioning_assert(
	isset( $contract['zone']['methods']['local_pickup']['title'] ) && 'Самовывоз из шоурума' === $contract['zone']['methods']['local_pickup']['title'],
	'Самовывоз должен называться «Самовывоз из шоурума».'
);

velotok_provisioning_assert( isset( $contract['payment_gateways']['cod'] ), 'Шлюз COD должен быть в плане провижининга.' );
velotok_provisioning_assert( isset( $contract['payment_gateways']['velotok_yookassa'] ), 'Шлюз ЮKassa должен быть зарегистрирован в плане провижининга.' );
velotok_provisioning_assert(
	isset( $contract['payment_gateways']['cod']['settings']['title'] ) && 'Наличными или картой при получении' === $contract['payment_gateways']['cod']['settings']['title'],
	'COD должен иметь понятное название для checkout.'
);
velotok_provisioning_assert( isset( $contract['locale']['language'] ) && 'ru_RU' === $contract['locale']['language'], 'Языковой пакет должен быть ru_RU.' );
velotok_provisioning_assert( isset( $contract['locale']['plugins']['woocommerce'] ) && 'ru_RU' === $contract['locale']['plugins']['woocommerce'], 'Для WooCommerce должен ставиться ru_RU.' );

$first = velotok_core_provision_store( array( 'skip_language' => true ) );

velotok_provisioning_assert( true === $first['zone']['created'], 'Первый запуск должен создать зону доставки.' );
velotok_provisioning_assert( $first['zone']['id'] > 0, 'Созданная зона должна иметь идентификатор.' );
velotok_provisioning_assert( 3 === count( $first['zone']['methods'] ), 'В зоне должно быть три способа доставки.' );
velotok_provisioning_assert( true === $first['checkout_ready'], 'После провижининга checkout должен быть готов.' );
velotok_provisioning_assert( true === velotok_core_provisioning_checkout_ready( $first ), 'Помощник готовности checkout должен подтверждать провижининг.' );

$cod = get_option( 'woocommerce_cod_settings', array() );
velotok_provisioning_assert( is_array( $cod ) && isset( $cod['enabled'] ) && 'yes' === $cod['enabled'], 'COD должен быть включён.' );
velotok_provisioning_assert( isset( $cod['title'] ) && 'Наличными или картой при получении' === $cod['title'], 'Название COD должно быть сохранено.' );

$yookassa = get_option( 'woocommerce_velotok_yookassa_settings', array() );
velotok_provisioning_assert( is_array( $yookassa ) && isset( $yookassa['enabled'] ), 'Настройки ЮKassa должны быть созданы.' );

$cdek = get_option( 'woocommerce_cdek_1_settings', array() );
velotok_provisioning_assert( is_array( $cdek ) && isset( $cdek['enabled'] ) && 'yes' === $cdek['enabled'], 'СДЭК должен быть включён в зоне.' );
velotok_provisioning_assert( isset( $cdek['title'] ) && 'СДЭК' === $cdek['title'], 'Название СДЭК должно быть сохранено.' );
velotok_provisioning_assert( isset( $cdek['free_from'] ) && '5000' === (string) $cdek['free_from'], 'Порог бесплатной доставки СДЭК должен быть сохранён.' );

$pickup = get_option( 'woocommerce_local_pickup_3_settings', array() );
velotok_provisioning_assert( is_array( $pickup ) && isset( $pickup['enabled'] ) && 'yes' === $pickup['enabled'], 'Самовывоз должен быть включён.' );
velotok_provisioning_assert( isset( $pickup['title'] ) && 'Самовывоз из шоурума' === $pickup['title'], 'Название самовывоза должно быть сохранено.' );
velotok_provisioning_assert( isset( $pickup['cost'] ) && '0' === (string) $pickup['cost'], 'Самовывоз должен быть бесплатным.' );

$second = velotok_core_provision_store( array( 'skip_language' => true ) );

velotok_provisioning_assert( false === $second['zone']['created'], 'Повторный запуск не должен создавать вторую зону.' );
velotok_provisioning_assert( $first['zone']['id'] === $second['zone']['id'], 'Повторный запуск должен использовать существующую зону.' );
velotok_provisioning_assert( 3 === count( $second['zone']['methods'] ), 'Повторный запуск не должен дублировать способы доставки.' );
velotok_provisioning_assert( 3 === count( $GLOBALS['velotok_test_added_methods'] ), 'Способы доставки должны добавляться ровно один раз.' );
velotok_provisioning_assert( 1 === count( $GLOBALS['velotok_test_zones'] ), 'Должна существовать ровно одна зона доставки.' );
velotok_provisioning_assert( isset( $second['language']['skipped'] ) && true === $second['language']['skipped'], 'Языковой пакет должен корректно пропускаться без WP-CLI.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: провижининг магазина создаёт зону, способы доставки, шлюзы и ru_RU идемпотентно.' . PHP_EOL;
