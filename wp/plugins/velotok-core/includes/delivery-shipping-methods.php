<?php
/**
 * WooCommerce shipping method classes for СДЭК and Почта РФ.
 *
 * Loaded once the WooCommerce WC_Shipping_Method base class is available.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

if ( ! class_exists( 'WC_Shipping_Method' ) ) {
	return;
}

if ( class_exists( 'Velotok_Shipping_Method' ) ) {
	return;
}

/**
 * Shared base for the delivery provider shipping methods.
 */
abstract class Velotok_Shipping_Method extends WC_Shipping_Method {
	/**
	 * Provider identifier.
	 *
	 * @var string
	 */
	protected $provider_id = '';

	/**
	 * Provider label.
	 *
	 * @var string
	 */
	protected $provider_label = '';

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Shipping method instance id.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->instance_id = velotok_core_delivery_woocommerce_absint( $instance_id );
		$this->supports    = array( 'shipping-zones', 'instance-settings', 'instance-settings-modal' );
		$this->init();
	}

	/**
	 * Initialise the shipping method.
	 *
	 * @return void
	 */
	public function init() {
		$this->id                 = $this->provider_id;
		$this->method_title       = $this->provider_label;
		$this->method_description = velotok_core_delivery_woocommerce_translate( 'Способ доставки из зоны WooCommerce. Стоимость рассчитывается по тарифу провайдера.' );

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled = $this->get_option( 'enabled', 'yes' );
		$this->title   = $this->get_option( 'title', $this->provider_label );

		if ( function_exists( 'add_action' ) ) {
			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		}
	}

	/**
	 * Form fields shown for every zone method instance.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => velotok_core_delivery_woocommerce_translate( 'Включить' ),
				'type'    => 'checkbox',
				'label'   => velotok_core_delivery_woocommerce_translate( 'Предлагать этот способ в зоне' ),
				'default' => 'yes',
			),
			'title'        => array(
				'title'   => velotok_core_delivery_woocommerce_translate( 'Название' ),
				'type'    => 'text',
				'default' => $this->provider_label,
			),
			'base'         => array(
				'title'   => velotok_core_delivery_woocommerce_translate( 'Базовая стоимость' ),
				'type'    => 'number',
				'default' => '0',
			),
			'per_kg'       => array(
				'title'   => velotok_core_delivery_woocommerce_translate( 'Стоимость за кг' ),
				'type'    => 'number',
				'default' => '0',
			),
			'free_from'    => array(
				'title'       => velotok_core_delivery_woocommerce_translate( 'Бесплатная доставка от' ),
				'type'        => 'number',
				'description' => velotok_core_delivery_woocommerce_translate( 'Сумма корзины, начиная с которой доставка бесплатна.' ),
				'default'     => '',
			),
			'eta_min_days' => array(
				'title'   => velotok_core_delivery_woocommerce_translate( 'Срок, от (дней)' ),
				'type'    => 'number',
				'default' => '',
			),
			'eta_max_days' => array(
				'title'   => velotok_core_delivery_woocommerce_translate( 'Срок, до (дней)' ),
				'type'    => 'number',
				'default' => '',
			),
			'test_mode'    => array(
				'title'   => velotok_core_delivery_woocommerce_translate( 'Тестовый режим' ),
				'type'    => 'checkbox',
				'label'   => velotok_core_delivery_woocommerce_translate( 'Показывать покупателю баннер тестового режима' ),
				'default' => 'no',
			),
		);
	}

	/**
	 * Calculate the shipping rate for a package.
	 *
	 * WooCommerce only calls methods from the zone that matched the package,
	 * so every rate added here is offered by a zone.
	 *
	 * @param array $package Shipping package.
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) {
		$order   = velotok_core_delivery_package_order( $package );
		$context = array(
			'order'     => $order,
			'test_mode' => 'yes' === $this->get_option( 'test_mode', 'no' ),
		);

		$cost = velotok_core_delivery_zones_method_cost( $this, $order, $context );

		if ( empty( $cost['ok'] ) ) {
			if ( function_exists( 'wc_add_notice' ) && ! empty( $cost['error'] ) ) {
				wc_add_notice( (string) $cost['error'], 'error' );
			}

			return;
		}

		$eta            = velotok_core_delivery_zones_eta_range( $this );
		$destination    = isset( $package['destination'] ) ? $package['destination'] : array();
		$points_context = array_merge(
			$context,
			array(
				'address'  => $destination,
				'provider' => $this->id,
			)
		);
		$pickup_points  = velotok_core_delivery_pickup_points( $destination, null, $points_context );
		$pickup_failed  = empty( $pickup_points['ok'] );
		$pickup_errors  = isset( $pickup_points['errors'] ) && is_array( $pickup_points['errors'] ) ? $pickup_points['errors'] : array();
		$pickup_notice  = '';

		foreach ( $pickup_errors as $pickup_error ) {
			$pickup_code = is_array( $pickup_error ) && isset( $pickup_error['code'] ) ? (string) $pickup_error['code'] : '';

			if ( 'DELIVERY_PICKUP_POINTS_UNAVAILABLE' === $pickup_code || 'DELIVERY_PICKUP_POINTS_ERROR' === $pickup_code ) {
				$pickup_notice = is_array( $pickup_error ) && ! empty( $pickup_error['message'] )
					? (string) $pickup_error['message']
					: 'Список пунктов выдачи недоступен.';
				break;
			}
		}

		if ( $pickup_failed && '' === $pickup_notice && isset( $pickup_points['code'] ) && in_array( $pickup_points['code'], array( 'DELIVERY_PICKUP_POINTS_UNAVAILABLE', 'DELIVERY_PICKUP_POINTS_ERROR' ), true ) ) {
			$pickup_notice = isset( $pickup_points['message'] ) && '' !== (string) $pickup_points['message']
				? (string) $pickup_points['message']
				: 'Список пунктов выдачи недоступен.';
		}

		if ( '' !== $pickup_notice && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $this->title . ': ' . $pickup_notice, 'error' );
		}

		$rate           = array(
			'id'        => $this->id . ':' . velotok_core_delivery_woocommerce_absint( $this->instance_id ),
			'label'     => $this->title,
			'cost'      => $cost['amount'],
			'calc_tax'  => 'per_order',
			'meta_data' => array(
				'_velotok_delivery_provider'      => $this->id,
				'_velotok_eta_min_days'           => $eta['min_days'],
				'_velotok_eta_max_days'           => $eta['max_days'],
				'_velotok_eta_label'              => $eta['label'],
				'_velotok_eta_range'              => $eta['range'],
				'_velotok_delivery_eta'           => array(
					'min_days' => $eta['min_days'],
					'max_days' => $eta['max_days'],
					'label'    => $eta['label'],
				),
				'_velotok_pickup_points'          => isset( $pickup_points['points'] ) ? $pickup_points['points'] : array(),
				'_velotok_pickup_points_radius_km'=> isset( $pickup_points['radius_km'] ) ? $pickup_points['radius_km'] : null,
				'_velotok_pickup_points_code'     => isset( $pickup_points['code'] ) ? $pickup_points['code'] : '',
				'_velotok_pickup_points_message'  => isset( $pickup_points['message'] ) ? $pickup_points['message'] : '',
				'_velotok_pickup_points_ok'       => ! empty( $pickup_points['ok'] ),
				'_velotok_pickup_points_available'=> ! empty( $pickup_points['available'] ),
				'_velotok_pickup_points_errors'   => $pickup_errors,
				'_velotok_pickup_points_explicit_failure' => true,
				'_velotok_test_mode'              => $context['test_mode'] ? 'yes' : 'no',
			),
		);

		if ( function_exists( 'apply_filters' ) ) {
			$rate = apply_filters( 'velotok_core_delivery_rate', $rate, $this, $order, $context );
		}

		$this->add_rate( $rate );
	}
}

/**
 * СДЭК shipping method.
 */
class Velotok_Shipping_Method_CDEK extends Velotok_Shipping_Method {
	/**
	 * Provider identifier.
	 *
	 * @var string
	 */
	protected $provider_id = 'cdek';

	/**
	 * Provider label.
	 *
	 * @var string
	 */
	protected $provider_label = 'СДЭК';
}

/**
 * Почта РФ shipping method.
 */
class Velotok_Shipping_Method_Russian_Post extends Velotok_Shipping_Method {
	/**
	 * Provider identifier.
	 *
	 * @var string
	 */
	protected $provider_id = 'russian_post';

	/**
	 * Provider label.
	 *
	 * @var string
	 */
	protected $provider_label = 'Почта РФ';
}
