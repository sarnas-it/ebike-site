<?php
/**
 * Plugin uninstall routine.
 *
 * Product meta is intentionally preserved: it is customer data.
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
