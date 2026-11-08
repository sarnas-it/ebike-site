<?php
/**
 * Integration test for WooCommerce delivery methods sourced from zones.
 *
 * Requirement TESTEB-28 / spec delivery:
 * «Система SHALL предлагать способы доставки из зон WooCommerce».
 *
 * The test stubs the small WordPress/WooCommerce surface used by the
 * integration and proves that:
 * - the СДЭК and Почта РФ methods are registered with WooCommerce;
 * - a method offered by a zone calculates its rate from the tariff;
 * - the delivery time is carried as a range;
 * - the free-shipping threshold is honoured;
 * - a provider error stays visible and is never replaced with a fake rate.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-woocommerce-integration-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_delivery_actions'] = array();
$GLOBALS['velotok_delivery_filters'] = array();
$GLOBALS['velotok_delivery_notices'] = array();
$GLOBALS['velotok_delivery_meta']    = array();

/**
 * Record an action.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted args.
 * @return bool
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	unset( $priority, $args );

	$GLOBALS['velotok_delivery_actions'][ $hook ][] = $callback;

	return true;
}

/**
 * Record a filter.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted args.
 * @return bool
 */
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	unset( $priority, $args );

	$GLOBALS['velotok_delivery_filters'][ $hook ][] = $callback;

	return true;
}

/**
 * Apply registered filters.
 *
 * @param string $hook  Hook name.
 * @param mixed  $value Value.
 * @return mixed
 */
function apply_filters( $hook, $value ) {
	$args = func_get_args();

	if ( empty( $GLOBALS['velotok_delivery_filters'][ $hook ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['velotok_delivery_filters'][ $hook ] as $callback ) {
		$args[1] = $value;
		$value   = call_user_func_array( $callback, array_slice( $args, 1 ) );
	}

	return $value;
}

/**
 * Translate a string.
 *
 * @param string $text Text.
 * @return string
 */
function __( $text ) {
	return $text;
}

/**
 * Escape text.
 *
 * @param string $text Text.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Convert to non-negative integer.
 *
 * @param mixed $value Value.
 * @return int
 */
function absint( $value ) {
	return abs( (int) $value );
}

/**
 * Get an option.
 *
 * @param string $key     Option key.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_option( $key, $default = false ) {
	unset( $key );

	return $default;
}

/**
 * Read post meta.
 *
 * @param int    $post_id Post id.
 * @param string $key     Meta key.
 * @param bool   $single  Single.
 * @return mixed
 */
function get_post_meta( $post_id, $key = '', $single = false ) {
	unset( $single );

	if ( isset( $GLOBALS['velotok_delivery_meta'][ $post_id ][ $key ] ) ) {
		return $GLOBALS['velotok_delivery_meta'][ $post_id ][ $key ];
	}

	return '';
}

/**
 * Record a WooCommerce notice.
 *
 * @param string $message Message.
 * @param string $type    Type.
 * @return void
 */
function wc_add_notice( $message, $type = 'success' ) {
	$GLOBALS['velotok_delivery_notices'][] = array(
		'message' => (string) $message,
		'type'    => (string) $type,
	);
}

/**
 * Minimal WooCommerce shipping method base class.
 */
class WC_Shipping_Method {
	/**
	 * Method id.
	 *
	 * @var string
	 */
	public $id = '';

	/**
	 * Instance id.
	 *
	 * @var int
	 */
	public $instance_id = 0;

	/**
	 * Method title.
	 *
	 * @var string
	 */
	public $method_title = '';

	/**
	 * Method description.
	 *
	 * @var string
	 */
	public $method_description = '';

	/**
	 * Supports.
	 *
	 * @var array
	 */
	public $supports = array();

	/**
	 * Enabled.
	 *
	 * @var string
	 */
	public $enabled = 'yes';

	/**
	 * Title.
	 *
	 * @var string
	 */
	public $title = '';

	/**
	 * Form fields.
	 *
	 * @var array
	 */
	public $form_fields = array();

	/**
	 * Instance settings.
	 *
	 * @var array
	 */
	public $instance_settings = array();

	/**
	 * Settings.
	 *
	 * @var array
	 */
	public $settings = array();

	/**
	 * Added rates.
	 *
	 * @var array
	 */
	public $rates = array();

	/**
	 * Load instance settings.
	 *
	 * @return void
	 */
	public function init_settings() {
		$this->instance_settings = $this->instance_settings ? $this->instance_settings : $this->settings;
	}

	/**
	 * Read an option.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public function get_option( $key, $default = '' ) {
		if ( isset( $this->instance_settings[ $key ] ) && '' !== (string) $this->instance_settings[ $key ] ) {
			return $this->instance_settings[ $key ];
		}

		if ( isset( $this->settings[ $key ] ) && '' !== (string) $this->settings[ $key ] ) {
			return $this->settings[ $key ];
		}

		if ( isset( $this->form_fields[ $key ]['default'] ) && '' !== (string) $this->form_fields[ $key ]['default'] ) {
			return $this->form_fields[ $key ]['default'];
		}

		return $default;
	}

	/**
	 * Save admin options.
	 *
	 * @return bool
	 */
	public function process_admin_options() {
		return true;
	}

	/**
	 * Add a rate.
	 *
	 * @param array $rate Rate.
	 * @return void
	 */
	public function add_rate( $rate ) {
		$this->rates[ $rate['id'] ] = $rate;
	}
}

/**
 * Minimal product stub.
 */
class Velotok_Test_Product {
	/**
	 * Product weight.
	 *
	 * @var mixed
	 */
	private $weight;

	/**
	 * Product id.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Constructor.
	 *
	 * @param mixed $weight Weight.
	 * @param int   $id     Id.
	 */
	public function __construct( $weight, $id = 1 ) {
		$this->weight = $weight;
		$this->id     = (int) $id;
	}

	/**
	 * Return the weight.
	 *
	 * @return mixed
	 */
	public function get_weight() {
		return $this->weight;
	}

	/**
	 * Return the id.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}
}

$integration_path = dirname( __DIR__ ) . '/includes/delivery-woocommerce.php';
$override         = getenv( 'VELOTOK_DELIVERY_WOOCOMMERCE_INTEGRATION' );

if ( is_string( $override ) && '' !== $override ) {
	$integration_path = $override;
}

if ( ! is_readable( $integration_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль интеграции доставки с WooCommerce.' . PHP_EOL );
	exit( 1 );
}

require_once $integration_path;

$failures = array();

/**
 * Assert a delivery integration condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_delivery_woocommerce_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$registered = apply_filters( 'woocommerce_shipping_methods', array() );

velotok_delivery_woocommerce_assert( isset( $GLOBALS['velotok_delivery_filters']['woocommerce_shipping_methods'] ), 'Способы доставки должны регистрироваться в WooCommerce через woocommerce_shipping_methods.' );
velotok_delivery_woocommerce_assert( isset( $registered['cdek'] ) && 'Velotok_Shipping_Method_CDEK' === $registered['cdek'], 'СДЭК должен регистрироваться как способ доставки WooCommerce.' );
velotok_delivery_woocommerce_assert( isset( $registered['russian_post'] ) && 'Velotok_Shipping_Method_Russian_Post' === $registered['russian_post'], 'Почта РФ должна регистрироваться как способ доставки WooCommerce.' );
velotok_delivery_woocommerce_assert( isset( $GLOBALS['velotok_delivery_actions']['woocommerce_after_shipping_rate'] ), 'Срок доставки должен выводиться рядом со способом в checkout.' );

$cdek = new Velotok_Shipping_Method_CDEK( 5 );
$cdek->instance_settings = array(
	'enabled'      => 'yes',
	'title'        => 'СДЭК',
	'base'         => '500',
	'per_kg'       => '100',
	'free_from'    => '5000',
	'eta_min_days' => '1',
	'eta_max_days' => '3',
	'test_mode'    => 'no',
);
$cdek->title = 'СДЭК';

$package = array(
	'destination'  => array(
		'country'  => 'RU',
		'state'    => 'MOW',
		'postcode' => '101000',
		'city'     => 'Москва',
	),
	'contents_cost' => 1000,
	'contents'      => array(
		array(
			'data'     => new Velotok_Test_Product( 1.1, 11 ),
			'quantity' => 2,
		),
	),
);

$cdek->calculate_shipping( $package );

velotok_delivery_woocommerce_assert( isset( $cdek->rates['cdek:5'] ), 'Способ СДЭК из зоны должен добавлять тариф в checkout.' );

if ( isset( $cdek->rates['cdek:5'] ) ) {
	$rate = $cdek->rates['cdek:5'];

	velotok_delivery_woocommerce_assert( 'СДЭК' === $rate['label'], 'Название способа должно браться из настроек зоны.' );
	velotok_delivery_woocommerce_assert( 800.0 === (float) $rate['cost'], 'Стоимость должна считаться по тарифу и весу заказа.' );
	velotok_delivery_woocommerce_assert( 1 === (int) $rate['meta_data']['_velotok_eta_min_days'] && 3 === (int) $rate['meta_data']['_velotok_eta_max_days'], 'Срок доставки должен храниться диапазоном.' );
	velotok_delivery_woocommerce_assert( '1–3 дн.' === $rate['meta_data']['_velotok_eta_label'], 'Срок доставки должен отображаться диапазоном.' );
}

$free_cdek = new Velotok_Shipping_Method_CDEK( 6 );
$free_cdek->instance_settings = array(
	'enabled'      => 'yes',
	'title'        => 'СДЭК',
	'base'         => '500',
	'per_kg'       => '100',
	'free_from'    => '5000',
	'eta_min_days' => '1',
	'eta_max_days' => '3',
);
$free_cdek->title = 'СДЭК';
$free_package    = $package;
$free_package['contents_cost'] = 6000;
$free_cdek->calculate_shipping( $free_package );

velotok_delivery_woocommerce_assert( isset( $free_cdek->rates['cdek:6'] ), 'Бесплатная доставка должна оставаться доступной.' );

if ( isset( $free_cdek->rates['cdek:6'] ) ) {
	velotok_delivery_woocommerce_assert( 0.0 === (float) $free_cdek->rates['cdek:6']['cost'], 'Порог бесплатной доставки должен обнулять стоимость.' );
}

$post = new Velotok_Shipping_Method_Russian_Post( 7 );
$post->instance_settings = array(
	'enabled'      => 'yes',
	'title'        => 'Почта РФ',
	'base'         => '350',
	'per_kg'       => '80',
	'free_from'    => '7000',
	'eta_min_days' => '3',
	'eta_max_days' => '7',
);
$post->title = 'Почта РФ';
$post->calculate_shipping( $package );

velotok_delivery_woocommerce_assert( isset( $post->rates['russian_post:7'] ), 'Способ Почты РФ из зоны должен добавлять тариф в checkout.' );

if ( isset( $post->rates['russian_post:7'] ) ) {
	velotok_delivery_woocommerce_assert( 590.0 === (float) $post->rates['russian_post:7']['cost'], 'Тариф Почты РФ должен считаться независимо от СДЭК.' );
	velotok_delivery_woocommerce_assert( '3–7 дн.' === $post->rates['russian_post:7']['meta_data']['_velotok_eta_label'], 'Срок Почты РФ должен отображаться диапазоном.' );
}

add_filter(
	'velotok_core_delivery_provider_adapters',
	function ( $adapters, $provider ) {
		if ( 'cdek' === $provider ) {
			$adapters['cdek'] = function () {
				return array( 'error' => 'СДЭК временно недоступен' );
			};
		}

		return $adapters;
	},
	10,
	2
);

$failing = new Velotok_Shipping_Method_CDEK( 8 );
$failing->instance_settings = array(
	'enabled'      => 'yes',
	'title'        => 'СДЭК',
	'base'         => '500',
	'per_kg'       => '100',
	'eta_min_days' => '1',
	'eta_max_days' => '3',
);
$failing->title = 'СДЭК';
$failing->calculate_shipping( $package );

velotok_delivery_woocommerce_assert( ! isset( $failing->rates['cdek:8'] ), 'Ошибка провайдера не должна подменяться фиктивным тарифом.' );
velotok_delivery_woocommerce_assert( ! empty( $GLOBALS['velotok_delivery_notices'] ), 'Ошибка провайдера должна быть видна покупателю.' );

if ( ! empty( $GLOBALS['velotok_delivery_notices'] ) ) {
	velotok_delivery_woocommerce_assert( false !== strpos( $GLOBALS['velotok_delivery_notices'][0]['message'], 'СДЭК' ), 'Покупатель должен увидеть сообщение об ошибке провайдера.' );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: WooCommerce предлагает СДЭК и Почту РФ из зон с тарифом, сроком и видимой ошибкой.' . PHP_EOL;
