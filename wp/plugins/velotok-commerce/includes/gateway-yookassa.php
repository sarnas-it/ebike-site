<?php
/**
 * YuKassa WooCommerce gateway stub.
 *
 * @package Velotok_Commerce
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Velotok_Commerce_Gateway_Yookassa' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
	return;
}

/**
 * Mocked YuKassa payment gateway.
 *
 * The gateway is visible in checkout, but process_payment() never contacts
 * YuKassa. It records the mocked contract metadata and leaves the order
 * pending for manual confirmation.
 */
class Velotok_Commerce_Gateway_Yookassa extends WC_Payment_Gateway {

	/**
	 * Whether this gateway is a stub.
	 *
	 * @var bool
	 */
	public $mocked = true;

	/**
	 * Set up the gateway.
	 */
	public function __construct() {
		$this->id                 = 'velotok_yookassa';
		$this->has_fields         = false;
		$this->method_title       = __( 'ЮKassa (заглушка)', 'velotok-commerce' );
		$this->method_description = __( 'Заглушка платёжного контракта ЮKassa. Реальная оплата не выполняется, заказ остаётся в ожидании подтверждения.', 'velotok-commerce' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'ЮKassa (заглушка)', 'velotok-commerce' ) );
		$this->description = $this->get_option( 'description', __( 'Тестовый режим. Платёж не будет списан, менеджер подтвердит заказ вручную.', 'velotok-commerce' ) );
		$this->enabled     = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Define gateway settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Включить', 'velotok-commerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Показывать ЮKassa в оформлении заказа', 'velotok-commerce' ),
				'default' => 'yes',
			),
			'title'       => array(
				'title'   => __( 'Название', 'velotok-commerce' ),
				'type'    => 'text',
				'default' => __( 'ЮKassa (заглушка)', 'velotok-commerce' ),
			),
			'description' => array(
				'title'   => __( 'Описание', 'velotok-commerce' ),
				'type'    => 'textarea',
				'default' => __( 'Тестовый режим. Платёж не будет списан, менеджер подтвердит заказ вручную.', 'velotok-commerce' ),
			),
			'mode'        => array(
				'title'   => __( 'Режим', 'velotok-commerce' ),
				'type'    => 'select',
				'options' => array(
					'test' => __( 'Тестовый', 'velotok-commerce' ),
					'prod' => __( 'Боевой (недоступен в заглушке)', 'velotok-commerce' ),
				),
				'default' => 'test',
			),
			'mocked_notice' => array(
				'title'       => __( 'Статус интеграции', 'velotok-commerce' ),
				'type'        => 'title',
				'description' => __( 'mocked: true — заглушка активна. Учётные данные магазина будут браться из переменных окружения VELOTOK_YOOKASSA_SHOP_ID и VELOTOK_YOOKASSA_SECRET_KEY после подключения реальной интеграции.', 'velotok-commerce' ),
			),
		);
	}

	/**
	 * Process the payment without contacting YuKassa.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array<string, string>
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}

		$contract = velotok_commerce_payment_contract();
		$mode     = $this->get_option( 'mode', $contract['default_mode'] );

		if ( ! isset( $contract['modes'][ $mode ] ) ) {
			$mode = $contract['default_mode'];
		}

		$order->update_meta_data( '_velotok_payment_provider', $contract['provider'] );
		$order->update_meta_data( '_velotok_payment_gateway', $contract['gateway_id'] );
		$order->update_meta_data( '_velotok_payment_mode', $mode );
		$order->update_meta_data( '_velotok_payment_mocked', true );
		$order->update_meta_data( '_velotok_payment_status', 'mocked_pending' );
		$order->set_status( 'pending', __( 'Заглушка ЮKassa: реальная оплата не выполнялась, заказ ожидает подтверждения.', 'velotok-commerce' ) );
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_order_received_url(),
		);
	}
}
