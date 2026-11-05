<?php
/**
 * Plugin Name: Велоток: оформление заказа
 * Description: Заглушки оплаты через ЮKassa и выбора доставки, а также приёмка контента руководителем проекта.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Велоток
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: velotok-commerce
 * Domain Path: /languages
 *
 * @package Velotok_Commerce
 */

defined( 'ABSPATH' ) || exit;

define( 'VELOTOK_COMMERCE_VERSION', '1.0.0' );
define( 'VELOTOK_COMMERCE_FILE', __FILE__ );
define( 'VELOTOK_COMMERCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'VELOTOK_COMMERCE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Explicit marker: all external integrations in this plugin are stubs.
 */
define( 'VELOTOK_COMMERCE_MOCKED', true );

require_once VELOTOK_COMMERCE_PATH . 'includes/payment.php';
require_once VELOTOK_COMMERCE_PATH . 'includes/delivery.php';
require_once VELOTOK_COMMERCE_PATH . 'includes/content-acceptance.php';
require_once VELOTOK_COMMERCE_PATH . 'includes/checkout.php';

register_activation_hook( VELOTOK_COMMERCE_FILE, 'velotok_commerce_register_content_roles' );

/**
 * Load plugin translations.
 */
function velotok_commerce_load_textdomain() {
	load_plugin_textdomain( 'velotok-commerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'velotok_commerce_load_textdomain' );

/**
 * Load the YuKassa gateway class once WooCommerce is available.
 */
function velotok_commerce_load_payment_gateway() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once VELOTOK_COMMERCE_PATH . 'includes/gateway-yookassa.php';
}

if ( did_action( 'plugins_loaded' ) ) {
	velotok_commerce_load_payment_gateway();
} else {
	add_action( 'plugins_loaded', 'velotok_commerce_load_payment_gateway', 20 );
}

/**
 * Declare compatibility with WooCommerce custom order tables.
 */
function velotok_commerce_declare_woocommerce_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', VELOTOK_COMMERCE_FILE, true );
	}
}
add_action( 'before_woocommerce_init', 'velotok_commerce_declare_woocommerce_compatibility' );

/**
 * Warn administrators when WooCommerce is not active.
 */
function velotok_commerce_woocommerce_notice() {
	if ( class_exists( 'WooCommerce' ) ) {
		return;
	}

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>' . esc_html__( 'Плагин «Велоток: оформление заказа» требует активного WooCommerce. Заглушки оплаты и доставки не будут доступны в оформлении заказа, пока WooCommerce не активирован.', 'velotok-commerce' ) . '</p></div>';
}
add_action( 'admin_notices', 'velotok_commerce_woocommerce_notice' );
