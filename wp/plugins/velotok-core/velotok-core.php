<?php
/**
 * Plugin Name: Велоток: поля товара
 * Description: Характеристики электровелосипедов для карточек товаров WooCommerce.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: Велоток
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: velotok-core
 * Domain Path: /languages
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

define( 'VELOTOK_CORE_VERSION', '1.0.0' );
define( 'VELOTOK_CORE_FILE', __FILE__ );
define( 'VELOTOK_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'VELOTOK_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once VELOTOK_CORE_PATH . 'includes/meta.php';

/**
 * Load plugin translations.
 */
function velotok_core_load_textdomain() {
	load_plugin_textdomain( 'velotok-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'velotok_core_load_textdomain' );

/**
 * Warn administrators when WooCommerce is not active.
 */
function velotok_core_woocommerce_notice() {
	if ( class_exists( 'WooCommerce' ) ) {
		return;
	}

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>' . esc_html__( 'Плагин «Велоток: поля товара» требует активного WooCommerce. Поля товара не будут отображаться, пока WooCommerce не активирован.', 'velotok-core' ) . '</p></div>';
}
add_action( 'admin_notices', 'velotok_core_woocommerce_notice' );
