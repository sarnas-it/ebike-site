<?php
/**
 * Acceptance test for the EBIKE-23 criterion «checkout создаёт заказ».
 *
 * The test provisions a fresh store (stubbed WooCommerce surface) and then
 * places an order through the real checkout-order module using the provisioned
 * COD gateway and showroom pickup method.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/provisioning-checkout-test.php
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

class WC_Shipping_Zone {
	public $id;
	public $name      = '';
	public $locations = array();
	public $methods   = array();

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

class Velotok_Provisioning_Test_Order {
	public $id;
	public $data;
	public $meta = array();
	public $saves = 0;

	public function __construct( $id, $data ) {
		$this->id   = (int) $id;
		$this->data = $data;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_data() {
		return $this->data;
	}

	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	public function get_meta( $key, $single = true ) {
		unset( $single );

		return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : '';
	}

	public function save() {
		++$this->saves;

		return true;
	}
}

require_once dirname( __DIR__ ) . '/includes/provisioning.php';
require_once dirname( __DIR__ ) . '/includes/checkout-order.php';

$failures = array();

function velotok_provisioning_checkout_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$provisioned = velotok_core_provision_store( array( 'skip_language' => true ) );

velotok_provisioning_checkout_assert( true === $provisioned['checkout_ready'], 'Провижининг должен сообщить о готовности checkout.' );
velotok_provisioning_checkout_assert( isset( $provisioned['zone']['methods']['local_pickup'] ), 'Провижининг должен создать самовывоз.' );

$cod = get_option( 'woocommerce_cod_settings', array() );
velotok_provisioning_checkout_assert( is_array( $cod ) && isset( $cod['enabled'] ) && 'yes' === $cod['enabled'], 'COD должен быть включён до оформления заказа.' );

$created_order = null;
$context       = array(
	'is_user_logged_in' => true,
	'nonce_valid'       => true,
	'user_id'           => 42,
	'cart_items'        => array(
		array(
			'product_id' => 10,
			'quantity'   => 1,
			'name'       => 'Электровелосипед',
			'total'      => 119900,
		),
	),
	'data'              => array(
		'billing_first_name'       => 'Иван',
		'billing_last_name'        => 'Петров',
		'billing_email'            => 'ivan@example.com',
		'billing_address_1'        => 'ул. Ленина, 1',
		'billing_city'             => 'Москва',
		'billing_postcode'         => '101000',
		'payment_method'           => 'cod',
		'delivery_method'          => 'local_pickup',
		'velotok_checkout_key'     => 'provisioning-checkout-1',
	),
	'create_order'      => function ( $data ) use ( &$created_order ) {
		$created_order = new Velotok_Provisioning_Test_Order( 9001, $data );

		return $created_order;
	},
);

$result = velotok_core_checkout_place_order( $context );

velotok_provisioning_checkout_assert( true === $result['created'], 'Checkout должен создать заказ на провижиненном магазине.' );
velotok_provisioning_checkout_assert( 'CHECKOUT_CREATED' === $result['code'], 'Успешное оформление должно возвращать CHECKOUT_CREATED.' );
velotok_provisioning_checkout_assert( 9001 === $result['order_id'], 'Созданный заказ должен иметь идентификатор.' );
velotok_provisioning_checkout_assert( $created_order instanceof Velotok_Provisioning_Test_Order, 'Заказ должен создаваться через переданный WC_Checkout-совместимый объект.' );

$order_data = $created_order->get_data();
velotok_provisioning_checkout_assert( 'cod' === $order_data['payment_method'], 'Заказ должен сохранять провижиненный способ оплаты.' );
velotok_provisioning_checkout_assert( 'local_pickup' === $order_data['delivery_method'], 'Заказ должен сохранять провижиненный способ доставки.' );
velotok_provisioning_checkout_assert( 'cod' === $created_order->get_meta( '_payment_method' ), 'Способ оплаты должен сохраняться в метаданных заказа.' );
velotok_provisioning_checkout_assert( 'local_pickup' === $created_order->get_meta( '_velotok_delivery_method' ), 'Способ доставки должен сохраняться в метаданных заказа.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: после провижининга checkout создаёт заказ с оплатой и доставкой.' . PHP_EOL;
