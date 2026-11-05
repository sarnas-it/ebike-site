<?php
/**
 * Plugin uninstall routine.
 *
 * Order meta is intentionally preserved: it is customer data.
 *
 * @package Velotok_Commerce
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$velotok_commerce_capability = 'velotok_accept_content';
$velotok_commerce_role       = 'velotok_project_manager';

$velotok_commerce_administrator = get_role( 'administrator' );

if ( $velotok_commerce_administrator ) {
	$velotok_commerce_administrator->remove_cap( $velotok_commerce_capability );
}

remove_role( $velotok_commerce_role );
