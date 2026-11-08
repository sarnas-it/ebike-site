<?php
/**
 * Тема «Велоток»: подключение модулей.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'VELOTOK_VERSION' ) ) {
	define( 'VELOTOK_VERSION', '1.0.0' );
}

$velotok_includes = array(
	'inc/setup.php',
	'inc/customizer.php',
	'inc/enqueue.php',
	'inc/security.php',
	'inc/woocommerce.php',
);

foreach ( $velotok_includes as $velotok_file ) {
	require_once get_template_directory() . '/' . $velotok_file;
}
