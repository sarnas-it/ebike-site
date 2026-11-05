<?php
/**
 * Plugin Name: Велоток: проверка плагинов на уязвимости
 * Description: Проверяет все установленные плагины на известные уязвимости в mocked-режиме.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Велоток
 * License: GPL-2.0-or-later
 * Text Domain: pvs
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );
}

if ( ! defined( 'PVS_VERSION' ) ) {
	define( 'PVS_VERSION', '1.0.0' );
}

if ( ! defined( 'PVS_PLUGIN_FILE' ) ) {
	define( 'PVS_PLUGIN_FILE', __FILE__ );
}

require_once __DIR__ . '/includes/class-pvs-contract.php';
require_once __DIR__ . '/includes/class-pvs-scanner.php';
require_once __DIR__ . '/includes/class-pvs-admin.php';
