<?php
/**
 * Contract test for the buyer-visible delivery test-mode banner.
 *
 * Requirement TESTEB-40 / spec delivery:
 * «Баннер тестового режима SHALL быть виден покупателю».
 *
 * The test proves that the banner is part of the delivery contract, is
 * rendered for the buyer at cart and checkout, and appears whenever an offered
 * or selected WooCommerce shipping rate belongs to a provider in test mode,
 * even when the shop-wide option is disabled.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-buyer-test-mode-banner-contract-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_banner_actions'] = array();
$GLOBALS['velotok_banner_filters'] = array();
$GLOBALS['velotok_banner_option']  = 'no';
$GLOBALS['velotok_banner_wc']      = null;

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

	$GLOBALS['velotok_banner_actions'][ $hook ][] = $callback;

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

	$GLOBALS['velotok_banner_filters'][ $hook ][] = $callback;

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

	if ( empty( $GLOBALS['velotok_banner_filters'][ $hook ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['velotok_banner_filters'][ $hook ] as $callback ) {
		$args[1] = $value;
		$value   = call_user_func_array( $callback, array_slice( $args, 1 ) );
	}

	return $value;
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
 * Read an option.
 *
 * @param string $key     Option key.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_option( $key, $default = false ) {
	if ( 'velotok_core_delivery_test_mode' === $key ) {
		return $GLOBALS['velotok_banner_option'];
	}

	return $default;
}

/**
 * Return the WooCommerce stub.
 *
 * @return mixed
 */
function WC() {
	return $GLOBALS['velotok_banner_wc'];
}

/**
 * Minimal WooCommerce session stub.
 */
class Velotok_Banner_Session {
	/**
	 * Session data.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param array $data Session data.
	 */
	public function __construct( $data = array() ) {
		$this->data = $data;
	}

	/**
	 * Read a session value.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public function get( $key, $default = false ) {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default;
	}
}

/**
 * Minimal WooCommerce shipping stub.
 */
class Velotok_Banner_Shipping {
	/**
	 * Shipping packages.
	 *
	 * @var array
	 */
	private $packages;

	/**
	 * Constructor.
	 *
	 * @param array $packages Packages.
	 */
	public function __construct( $packages = array() ) {
		$this->packages = $packages;
	}

	/**
	 * Return the packages.
	 *
	 * @return array
	 */
	public function get_packages() {
		return $this->packages;
	}
}

/**
 * Minimal WooCommerce stub.
 */
class Velotok_Banner_WC {
	/**
	 * Session.
	 *
	 * @var mixed
	 */
	public $session;

	/**
	 * Shipping.
	 *
	 * @var mixed
	 */
	public $shipping;

	/**
	 * Constructor.
	 *
	 * @param mixed $session  Session.
	 * @param mixed $shipping Shipping.
	 */
	public function __construct( $session = null, $shipping = null ) {
		$this->session  = $session;
		$this->shipping = $shipping;
	}
}

require_once dirname( __DIR__ ) . '/includes/delivery-woocommerce.php';

$failures = array();

/**
 * Assert a buyer banner condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_banner_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = velotok_core_delivery_zones_contract();

velotok_banner_assert( in_array( 'TESTEB-40', $contract['requirement_ids'], true ), 'Контракт доставки должен включать TESTEB-40.' );
velotok_banner_assert( isset( $contract['test_mode_banner'] ) && is_array( $contract['test_mode_banner'] ), 'Контракт должен содержать раздел баннера тестового режима.' );
velotok_banner_assert( 'TESTEB-40' === $contract['test_mode_banner']['requirement'], 'Раздел баннера должен ссылаться на TESTEB-40.' );
velotok_banner_assert( true === $contract['test_mode_banner']['buyer_visible'], 'Баннер должен быть виден покупателю.' );
velotok_banner_assert( true === $contract['test_mode_banner']['only_when_test_mode'], 'Баннер должен показываться только в тестовом режиме.' );
velotok_banner_assert( isset( $contract['test_mode']['banner_info'] ) && 'TESTEB-40' === $contract['test_mode']['banner_info']['requirement'], 'Тестовый режим должен ссылаться на требование о баннере.' );

$banner = velotok_core_delivery_test_mode_banner( true );

velotok_banner_assert( false !== strpos( $banner, 'Тестовый режим доставки' ), 'Баннер должен содержать понятный покупателю текст.' );
velotok_banner_assert( false !== strpos( $banner, 'data-test-mode="true"' ), 'Баннер должен явно помечать тестовый режим.' );
velotok_banner_assert( false !== strpos( $banner, 'data-buyer-visible="true"' ), 'Баннер должен быть помечен как видимый покупателю.' );
velotok_banner_assert( '' === velotok_core_delivery_test_mode_banner( false ), 'Без тестового режима баннер не выводится.' );
velotok_banner_assert( false === velotok_core_delivery_test_mode_banner_visible(), 'Без тестового режима баннер не виден.' );
velotok_banner_assert( true === velotok_core_delivery_test_mode_banner_visible( array( 'test_mode' => 'yes' ) ), 'Контекст тестового режима делает баннер видимым.' );

$test_rate = array(
	'id'        => 'cdek:5',
	'meta_data' => array(
		array( 'key' => '_velotok_test_mode', 'value' => 'yes' ),
	),
);
$live_rate = array(
	'id'        => 'cdek:5',
	'meta_data' => array(
		array( 'key' => '_velotok_test_mode', 'value' => 'no' ),
	),
);

$GLOBALS['velotok_banner_wc'] = new Velotok_Banner_WC(
	new Velotok_Banner_Session( array( 'chosen_shipping_methods' => array( 'cdek:5' ) ) ),
	new Velotok_Banner_Shipping(
		array(
			array(
				'rates' => array(
					'cdek:5' => $test_rate,
					'russian_post:7' => array( 'id' => 'russian_post:7', 'meta_data' => array() ),
				),
			),
		)
	)
);

velotok_banner_assert( true === velotok_core_delivery_test_mode_banner_visible(), 'Выбранный тестовый тариф должен делать баннер видимым.' );

ob_start();
velotok_core_delivery_checkout_test_mode_banner();
$checkout_banner = ob_get_clean();

velotok_banner_assert( false !== strpos( $checkout_banner, 'Тестовый режим доставки' ), 'В checkout покупатель должен видеть баннер тестового режима.' );
velotok_banner_assert( isset( $GLOBALS['velotok_banner_actions']['woocommerce_review_order_after_shipping'] ), 'Баннер должен выводиться в области доставки checkout.' );
velotok_banner_assert( isset( $GLOBALS['velotok_banner_actions']['woocommerce_before_cart'] ), 'Баннер должен выводиться в корзине.' );

ob_start();
velotok_core_delivery_cart_test_mode_banner();
$cart_banner = ob_get_clean();

velotok_banner_assert( false !== strpos( $cart_banner, 'Тестовый режим доставки' ), 'В корзине покупатель должен видеть баннер тестового режима.' );

$GLOBALS['velotok_banner_wc'] = new Velotok_Banner_WC(
	new Velotok_Banner_Session( array( 'chosen_shipping_methods' => array( 'cdek:5' ) ) ),
	new Velotok_Banner_Shipping(
		array(
			array(
				'rates' => array(
					'cdek:5' => $live_rate,
				),
			),
		)
	)
);

velotok_banner_assert( false === velotok_core_delivery_test_mode_banner_visible(), 'Рабочий выбранный тариф не должен показывать баннер.' );

ob_start();
velotok_core_delivery_checkout_test_mode_banner();
$live_banner = ob_get_clean();

velotok_banner_assert( '' === $live_banner, 'Без тестового режима checkout не выводит баннер.' );

$GLOBALS['velotok_banner_option'] = 'yes';
$GLOBALS['velotok_banner_wc']     = null;

velotok_banner_assert( true === velotok_core_delivery_test_mode_banner_visible(), 'Включённый тестовый режим магазина делает баннер видимым.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: баннер тестового режима доставки виден покупателю в корзине и в checkout.' . PHP_EOL;
