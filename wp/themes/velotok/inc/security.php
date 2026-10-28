<?php
/**
 * Базовая гигиена безопасности.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );

/**
 * Убирает версию WordPress из генератора.
 *
 * @return string
 */
function velotok_remove_generator() {
	return '';
}
add_filter( 'the_generator', 'velotok_remove_generator' );

/**
 * Добавляет заголовки безопасности для публичных страниц.
 *
 * @return void
 */
function velotok_security_headers() {
	if ( is_admin() ) {
		return;
	}

	if ( ! headers_sent() ) {
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
	}
}
add_action( 'send_headers', 'velotok_security_headers' );
