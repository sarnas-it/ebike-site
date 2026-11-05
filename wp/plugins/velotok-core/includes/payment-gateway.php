<?php
/**
 * YooKassa payment gateway stub.
 *
 * This gateway is intentionally a stub. It is explicitly marked as mocked and
 * never initialises a real YooKassa SDK, never reads credentials and never
 * requests a payment confirmation from the provider.
 *
 * Spec: clarify-096bb945.
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	return;
}

/**
 * Mocked YooKassa payment gateway.
 */
class Velotok_YooKassa_Stub_Gateway extends WC_Payment_Gateway {

	/**
	 * Explicit mocked marker.
	 *
	 * @var bool
	 */
	public $mocked = true;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'velotok_yookassa_stub';
		$this->has_fields         = false;
		$this->method_title       = __( 'ЮKassa (заглушка)', 'velotok-core' );
		$this->method_description = __( 'Заглушка платежного шлюза ЮKassa. Реальная интеграция не подключена; оплата не проводится, а заказу присваивается статус ожидания приёмки. mocked: true.', 'velotok-core' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', $this->method_title );
		$this->description = $this->get_option( 'description', __( 'Тестовый режим ЮKassa: реальные деньги не списываются. Заказ ожидает приёмки руководителем проекта. mocked: true.', 'velotok-core' ) );
		$this->enabled     = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Admin settings.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Включить заглушку', 'velotok-core' ),
				'type'    => 'checkbox',
				'label'   => __( 'Показывать ЮKassa (заглушка) при оформлении заказа', 'velotok-core' ),
				'default' => 'yes',
			),
			'title'       => array(
				'title'   => __( 'Название', 'velotok-core' ),
				'type'    => 'text',
				'default' => __( 'ЮKassa (заглушка)', 'velotok-core' ),
			),
			'description' => array(
				'title'   => __( 'Описание', 'velotok-core' ),
				'type'    => 'textarea',
				'default' => __( 'Тестовый режим ЮKassa: реальные деньги не списываются. Заказ ожидает приёмки руководителем проекта. mocked: true.', 'velotok-core' ),
			),
		);
	}

	/**
	 * Check whether the gateway can be used.
	 *
	 * @return bool
	 */
	public function is_available() {
		return 'yes' === $this->enabled;
	}

	/**
	 * Process a mocked payment.
	 *
	 * @param int $order_id Order identifier.
	 * @return array<string,string>
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array(
				'result'   => 'failure',
				'redirect' => wc_get_checkout_url(),
			);
		}

		$order->update_meta_data( '_velotok_payment_mocked', 'yes' );
		$order->update_meta_data( '_velotok_payment_provider', 'yookassa' );
		$order->update_meta_data( '_velotok_requires_acceptance', velotok_core_requires_human_acceptance() ? 'yes' : 'no' );
		$order->set_transaction_id( 'mock-yookassa-' . $order->get_id() );
		$order->update_status(
			'on-hold',
			__( 'ЮKassa: заглушка (mocked: true). Реальная оплата не проводилась, заказ ожидает приёмки руководителем проекта.', 'velotok-core' )
		);
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_order_received_url(),
		);
	}
}

/**
 * Register the mocked YooKassa gateway.
 *
 * @param string[] $gateways Gateway class names.
 * @return string[]
 */
function velotok_core_register_yookassa_gateway( $gateways ) {
	$gateways[] = 'Velotok_YooKassa_Stub_Gateway';

	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'velotok_core_register_yookassa_gateway' );
