<?php
/**
 * Lead form response and mail delivery checks shared by E2E and unit tests.
 *
 * The E2E script must not treat a Contact Form 7 submission as successful when
 * wp_mail() failed. A submission counts only when CF7 reports `mail_sent` and
 * the same message can be found in the local Mailpit catcher with the configured
 * recipient and the submitted marker in its body.
 *
 * CLI usage:
 *   php wp/cli/lead-forms-mail-check.php response <cf7-response.json>
 *   php wp/cli/lead-forms-mail-check.php delivery <mailpit-message.json> <recipient> <marker>
 *
 * @package Velotok_Core
 */

/**
 * A Contact Form 7 REST response is successful only for `mail_sent`.
 *
 * @param mixed $status CF7 response status.
 * @return bool
 */
function velotok_lead_mail_status_ok( $status ) {
	return is_string( $status ) && 'mail_sent' === $status;
}

/**
 * Check the complete CF7 feedback response.
 *
 * @param mixed $response Decoded CF7 response.
 * @return bool
 */
function velotok_lead_mail_response_valid( $response ) {
	if ( ! is_array( $response ) ) {
		return false;
	}

	return velotok_lead_mail_status_ok( isset( $response['status'] ) ? $response['status'] : null )
		&& isset( $response['invalid_fields'] )
		&& array() === $response['invalid_fields'];
}

/**
 * Normalise the recipient list from a form setting.
 *
 * @param string $recipient Recipient setting, possibly comma separated.
 * @return string[]
 */
function velotok_lead_mail_recipients( $recipient ) {
	$parts = preg_split( '/\s*,\s*/', (string) $recipient );
	$parts = is_array( $parts ) ? $parts : array();

	return array_values( array_filter( array_map( 'trim', $parts ), 'strlen' ) );
}

/**
 * Check that a Mailpit message was delivered to the form recipient and contains
 * the unique marker submitted through the form.
 *
 * @param mixed  $message   Decoded Mailpit message.
 * @param string $recipient Configured form recipient(s).
 * @param string $marker    Unique value submitted in the form body.
 * @return bool
 */
function velotok_lead_mail_delivery_matches( $message, $recipient, $marker ) {
	if ( ! is_array( $message ) || '' === (string) $marker ) {
		return false;
	}

	$to = isset( $message['To'] ) && is_array( $message['To'] ) ? $message['To'] : array();
	$addresses = array();

	foreach ( $to as $address ) {
		if ( is_array( $address ) && isset( $address['Address'] ) ) {
			$addresses[] = strtolower( (string) $address['Address'] );
		} elseif ( is_string( $address ) ) {
			$addresses[] = strtolower( $address );
		}
	}

	$expected = array_map( 'strtolower', velotok_lead_mail_recipients( $recipient ) );
	$matched  = (bool) array_intersect( $expected, $addresses );

	if ( ! $matched ) {
		return false;
	}

	$body = ( isset( $message['Text'] ) ? (string) $message['Text'] : '' )
		. "\n"
		. ( isset( $message['HTML'] ) ? (string) $message['HTML'] : '' );

	return false !== strpos( $body, (string) $marker );
}

if ( ! defined( 'VELOTOK_LEAD_MAIL_CHECK_LIBRARY_ONLY' ) ) {
	$command = isset( $argv[1] ) ? $argv[1] : '';

	if ( 'response' === $command && isset( $argv[2] ) ) {
		$response = json_decode( (string) file_get_contents( $argv[2] ), true );

		if ( ! velotok_lead_mail_response_valid( $response ) ) {
			fwrite( STDERR, 'FAIL: CF7 response is not mail_sent with empty invalid_fields' . PHP_EOL );
			exit( 1 );
		}

		echo $response['status'];
		exit( 0 );
	}

	if ( 'delivery' === $command && isset( $argv[2], $argv[3], $argv[4] ) ) {
		$message = json_decode( (string) file_get_contents( $argv[2] ), true );

		if ( ! velotok_lead_mail_delivery_matches( $message, $argv[3], $argv[4] ) ) {
			fwrite( STDERR, 'FAIL: delivered message does not match recipient and marker' . PHP_EOL );
			exit( 1 );
		}

		exit( 0 );
	}

	fwrite( STDERR, 'Usage: lead-forms-mail-check.php response <json> | delivery <json> <recipient> <marker>' . PHP_EOL );
	exit( 2 );
}
