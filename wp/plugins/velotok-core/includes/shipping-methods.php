<?php
/**
 * Delivery method stubs.
 *
 * Each WooCommerce shipping method mirrors one option from the checkout
 * contract. The rates are zero-cost stubs: no real logistics API is called and
 * every method is explicitly marked as mocked.
 *
 * Spec: clarify-096bb945.
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Shipping_Method' ) ) {
	return;
}

/**
 * Base class for a mocked delivery method.
 */
class Velotok_Stub_Shipping_Method extends WC_Shipping_Method {

	/**
	 * Explicit mocked marker.
	 *
	 * @var bool
	 */
	public $mocked = true;

	/**
	 * WooCommerce method identifier.
	 *
	 * @var string
	 */
	protected $method_id = 'velotok_stub';

	/**
	 * Human-readable method label.
	 *
	 * @var string
	 */
	protected $method_label = 'Иной логист';

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Shipping zone instance identifier.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = $this->method_id;
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = $this->method_label . ' (заглушка)';
		$this->method_description = __( 'Заглушка службы доставки. Реальная интеграция не подключена, стоимость не рассчитывается. mocked: true.', 'velotok-core' );
		$this->supports           = array( 'shipping-zones' );

		$this->init();
	}

	/**
	 * Initialise settings.
	 *
	 * @return void
	 */
	public function init() {
		$this->enabled = $this->get_option( 'enabled', 'yes' );
		$this->title   = $this->get_option( 'title', $this->method_label . ' (заглушка)' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Add a zero-cost mocked rate.
	 *
	 * @param array $package Shipping package.
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) {
		$this->add_rate(
			array(
				'id'        => $this->id,
				'label'     => $this->title,
				'cost'      => 0,
				'package'   => $package,
				'meta_data' => array(
					'velotok_mocked' => 'yes',
				),
			)
		);
	}
}

/**
 * Russian Post delivery stub.
 */
class Velotok_Postal_RF_Shipping_Method extends Velotok_Stub_Shipping_Method {

	/**
	 * WooCommerce method identifier.
	 *
	 * @var string
	 */
	protected $method_id = 'velotok_postal_rf';

	/**
	 * Human-readable method label.
	 *
	 * @var string
	 */
	protected $method_label = 'Почта РФ';
}

/**
 * CDEK delivery stub.
 */
class Velotok_CDEK_Shipping_Method extends Velotok_Stub_Shipping_Method {

	/**
	 * WooCommerce method identifier.
	 *
	 * @var string
	 */
	protected $method_id = 'velotok_cdek';

	/**
	 * Human-readable method label.
	 *
	 * @var string
	 */
	protected $method_label = 'СДЭК';
}

/**
 * Other logistics provider delivery stub.
 */
class Velotok_Other_Logist_Shipping_Method extends Velotok_Stub_Shipping_Method {

	/**
	 * WooCommerce method identifier.
	 *
	 * @var string
	 */
	protected $method_id = 'velotok_other_logist';

	/**
	 * Human-readable method label.
	 *
	 * @var string
	 */
	protected $method_label = 'Иной логист';
}

/**
 * Register all mocked shipping methods.
 *
 * @param array<string,string> $methods Shipping method class map.
 * @return array<string,string>
 */
function velotok_core_register_shipping_methods( $methods ) {
	$methods['velotok_postal_rf']    = 'Velotok_Postal_RF_Shipping_Method';
	$methods['velotok_cdek']         = 'Velotok_CDEK_Shipping_Method';
	$methods['velotok_other_logist'] = 'Velotok_Other_Logist_Shipping_Method';

	return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'velotok_core_register_shipping_methods' );
