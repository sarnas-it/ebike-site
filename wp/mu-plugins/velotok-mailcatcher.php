<?php
/**
 * Plugin Name: Velotok Mail Catcher Bridge
 * Description: Routes wp_mail() through the local Mailpit SMTP catcher when VELOTOK_SMTP_HOST is set.
 * Version: 1.0.0
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Point PHPMailer at the local mail catcher.
 *
 * The bridge is intentionally opt-in: if VELOTOK_SMTP_HOST is not configured
 * (for example in production), WordPress keeps using its normal mail transport.
 *
 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
 * @return void
 */
function velotok_mailcatcher_configure( $phpmailer ) {
	$host = getenv( 'VELOTOK_SMTP_HOST' );

	if ( ! $host ) {
		return;
	}

	$port = (int) ( getenv( 'VELOTOK_SMTP_PORT' ) ?: 1025 );

	$phpmailer->isSMTP();
	$phpmailer->Host       = $host;
	$phpmailer->Port       = $port;
	$phpmailer->SMTPAuth   = false;
	$phpmailer->SMTPSecure = '';
	$phpmailer->SMTPAutoTLS = false;

	$from = getenv( 'VELOTOK_SMTP_FROM' );
	if ( $from && is_email( $from ) ) {
		$phpmailer->setFrom( $from, get_bloginfo( 'name' ) );
	}
}

add_action( 'phpmailer_init', 'velotok_mailcatcher_configure' );
