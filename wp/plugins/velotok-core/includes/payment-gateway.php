<?php
/**
 * WooCommerce payment gateway: ЮKassa API v3.
 *
 * The module registers a WC_Payment_Gateway implementation, creates payments
 * through the ЮKassa v3 API, verifies and de-duplicates webhooks, keeps the
 * WooCommerce order status in sync with the payment status model and turns a
 * full refund into the `refunded` payment status.
 *
 * Secrets are never stored in the code: shop id, secret key and webhook secret
 * are read from WordPress options or from VELOTOK_YOOKASSA_* environment
 * variables.
 *
 * Spec: payment (requirement TESTEB-44).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

/**
 * Apply a filter when WordPress is available.
 *
 * @param string $tag   Filter name.
 * @param mixed  $value Filter value.
 * @param mixed  ...$args Additional filter arguments.
 * @return mixed
 */
function velotok_core_payment_apply_filters( $tag, $value, ...$args ) {
	if ( function_exists( 'apply_filters' ) ) {
		return apply_filters( $tag, $value, ...$args );
	}

	return $value;
}

/**
 * Fire an action when WordPress is available.
 *
 * @param string $tag  Action name.
 * @param mixed  ...$args Action arguments.
 * @return void
 */
function velotok_core_payment_do_action( $tag, ...$args ) {
	if ( function_exists( 'do_action' ) ) {
		do_action( $tag, ...$args );
	}
}

/**
 * Read a payment setting from options or from the matching environment name.
 *
 * @param string $name    Option name; the environment name is its upper-case form.
 * @param mixed  $default Default value.
 * @return mixed
 */
function velotok_core_payment_option( $name, $default = '' ) {
	$value = null;

	if ( function_exists( 'get_option' ) ) {
		$value = get_option( $name, null );
	}

	$env_name = strtoupper( $name );

	if ( null === $value || '' === $value || false === $value ) {
		$env = getenv( $env_name );
		if ( false !== $env && '' !== $env ) {
			$value = $env;
		} elseif ( isset( $_ENV[ $env_name ] ) && '' !== $_ENV[ $env_name ] ) {
			$value = $_ENV[ $env_name ];
		}
	}

	if ( null === $value || '' === $value || false === $value ) {
		return $default;
	}

	return $value;
}

/**
 * ЮKassa shop id. Loaded from options or VELOTOK_YOOKASSA_SHOP_ID.
 *
 * @return string
 */
function velotok_core_yookassa_shop_id() {
	$value = velotok_core_payment_option( 'velotok_yookassa_shop_id', '' );

	if ( '' === $value ) {
		$value = velotok_core_payment_option( 'yookassa_shop_id', '' );
	}

	if ( '' === $value ) {
		$value = velotok_core_payment_gateway_setting( 'shop_id', '' );
	}

	return (string) velotok_core_payment_apply_filters( 'velotok_core_yookassa_shop_id', $value );
}

/**
 * ЮKassa secret key. Loaded from options or VELOTOK_YOOKASSA_SECRET_KEY.
 *
 * The value is only used to build the Basic authorization header and is never
 * exposed by the contract or order notes.
 *
 * @return string
 */
function velotok_core_yookassa_secret_key() {
	$value = velotok_core_payment_option( 'velotok_yookassa_secret_key', '' );

	if ( '' === $value ) {
		$value = velotok_core_payment_option( 'yookassa_secret_key', '' );
	}

	if ( '' === $value ) {
		$value = velotok_core_payment_gateway_setting( 'secret_key', '' );
	}

	return (string) velotok_core_payment_apply_filters( 'velotok_core_yookassa_secret_key', $value );
}

/**
 * Webhook signing secret. Loaded from options or
 * VELOTOK_YOOKASSA_WEBHOOK_SECRET.
 *
 * @return string
 */
function velotok_core_yookassa_webhook_secret() {
	$value = velotok_core_payment_option( 'velotok_yookassa_webhook_secret', '' );

	if ( '' === $value ) {
		$value = velotok_core_payment_option( 'yookassa_webhook_secret', '' );
	}

	if ( '' === $value ) {
		$value = velotok_core_payment_gateway_setting( 'webhook_secret', '' );
	}

	return (string) velotok_core_payment_apply_filters( 'velotok_core_yookassa_webhook_secret', $value );
}

/**
 * WooCommerce gateway settings array.
 *
 * @return array<string,mixed>
 */
function velotok_core_payment_gateway_settings() {
	$settings = function_exists( 'get_option' ) ? get_option( 'woocommerce_velotok_yookassa_settings', array() ) : array();

	return is_array( $settings ) ? $settings : array();
}

/**
 * Read a single WooCommerce gateway setting.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function velotok_core_payment_gateway_setting( $key, $default = '' ) {
	$settings = velotok_core_payment_gateway_settings();

	return isset( $settings[ $key ] ) && '' !== $settings[ $key ] ? $settings[ $key ] : $default;
}

/**
 * Whether the gateway has the credentials required for real requests.
 *
 * @return bool
 */
function velotok_core_yookassa_is_configured() {
	return '' !== velotok_core_yookassa_shop_id() && '' !== velotok_core_yookassa_secret_key();
}

/**
 * ЮKassa API base URL.
 *
 * @return string
 */
function velotok_core_yookassa_api_base_url() {
	$url = velotok_core_payment_apply_filters( 'velotok_core_yookassa_api_base_url', 'https://api.yookassa.ru/v3' );

	return rtrim( (string) $url, '/' );
}

/**
 * Return URL after payment confirmation.
 *
 * @param mixed $order WooCommerce order or order array.
 * @return string
 */
function velotok_core_yookassa_return_url( $order = null ) {
	$url = velotok_core_payment_option( 'velotok_yookassa_return_url', '' );

	if ( '' === $url && function_exists( 'wc_get_checkout_url' ) ) {
		$url = wc_get_checkout_url();
	}

	if ( '' === $url && function_exists( 'home_url' ) ) {
		$url = home_url( '/' );
	}

	return (string) velotok_core_payment_apply_filters( 'velotok_core_yookassa_return_url', $url, $order );
}

/**
 * Fixed payment status model.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_payment_status_model() {
	static $model = null;

	if ( null !== $model ) {
		return $model;
	}

	$transitions = array(
		'pending'             => array( 'waiting_for_capture', 'succeeded', 'canceled' ),
		'waiting_for_capture' => array( 'succeeded', 'canceled' ),
		'succeeded'           => array( 'refunded' ),
		'canceled'            => array(),
		'refunded'            => array(),
	);

	$order_status = array(
		'pending'             => 'pending',
		'waiting_for_capture' => 'on-hold',
		'succeeded'           => 'processing',
		'canceled'            => 'cancelled',
		'refunded'            => 'refunded',
	);

	$labels = array(
		'pending'             => 'Ожидает оплаты',
		'waiting_for_capture' => 'Средства захвачены',
		'succeeded'           => 'Оплачен',
		'canceled'            => 'Отменён',
		'refunded'            => 'Возврат',
	);

	$model = array();

	foreach ( $labels as $id => $label ) {
		$model[ $id ] = array(
			'id'          => $id,
			'code'        => $id,
			'label'       => $label,
			'paid'        => in_array( $id, array( 'succeeded', 'refunded' ), true ),
			'final'       => in_array( $id, array( 'canceled', 'refunded' ), true ),
			'order_status' => $order_status[ $id ],
			'transitions' => $transitions[ $id ],
		);
	}

	return $model;
}

/**
 * Fixed list of payment status identifiers.
 *
 * @return array<int,string>
 */
function velotok_core_payment_statuses() {
	return array_keys( velotok_core_payment_status_model() );
}

/**
 * Normalize a status coming from the API, webhook, order meta or a human.
 *
 * @param mixed $status Raw status.
 * @return string
 */
function velotok_core_payment_normalize_status( $status ) {
	$key = trim( (string) $status );
	$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $key, 'UTF-8' ) : strtolower( $key );

	$map = array(
		'new'                 => 'pending',
		'created'             => 'pending',
		'pending'             => 'pending',
		'ожидает'             => 'pending',
		'ожидает оплаты'      => 'pending',
		'waiting'             => 'waiting_for_capture',
		'authorized'          => 'waiting_for_capture',
		'waiting_for_capture' => 'waiting_for_capture',
		'захвачен'            => 'waiting_for_capture',
		'paid'                => 'succeeded',
		'success'             => 'succeeded',
		'succeeded'           => 'succeeded',
		'оплачен'             => 'succeeded',
		'успешно'             => 'succeeded',
		'cancelled'           => 'canceled',
		'canceled'            => 'canceled',
		'cancel'              => 'canceled',
		'отменён'             => 'canceled',
		'отменен'             => 'canceled',
		'refund'              => 'refunded',
		'refunded'            => 'refunded',
		'returned'            => 'refunded',
		'возврат'             => 'refunded',
		'возвращён'           => 'refunded',
		'возвращен'           => 'refunded',
	);

	if ( isset( $map[ $key ] ) ) {
		return $map[ $key ];
	}

	return $key;
}

/**
 * Whether a status belongs to the fixed payment status model.
 *
 * @param mixed $status Status.
 * @return bool
 */
function velotok_core_payment_status_is_valid( $status ) {
	$model = velotok_core_payment_status_model();

	return isset( $model[ velotok_core_payment_normalize_status( $status ) ] );
}

/**
 * Human readable status label.
 *
 * @param mixed $status Status.
 * @return string
 */
function velotok_core_payment_status_label( $status ) {
	$key   = velotok_core_payment_normalize_status( $status );
	$model = velotok_core_payment_status_model();

	return isset( $model[ $key ] ) ? $model[ $key ]['label'] : (string) $status;
}

/**
 * Whether a transition between two statuses is allowed.
 *
 * @param mixed $from Current status.
 * @param mixed $to   Target status.
 * @return bool
 */
function velotok_core_payment_can_transition( $from, $to ) {
	$model = velotok_core_payment_status_model();
	$from  = velotok_core_payment_normalize_status( $from );
	$to    = velotok_core_payment_normalize_status( $to );

	if ( ! isset( $model[ $from ], $model[ $to ] ) ) {
		return false;
	}

	if ( $from === $to ) {
		return true;
	}

	return in_array( $to, $model[ $from ]['transitions'], true );
}

/**
 * WooCommerce order status matching a payment status.
 *
 * @param mixed $status Payment status.
 * @return string
 */
function velotok_core_payment_order_status( $status ) {
	$key   = velotok_core_payment_normalize_status( $status );
	$model = velotok_core_payment_status_model();

	return isset( $model[ $key ] ) ? $model[ $key ]['order_status'] : '';
}

/**
 * Read an order property safely.
 *
 * @param mixed  $order  Order object or array.
 * @param string $method Getter name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function velotok_core_payment_order_value( $order, $method, $default = '' ) {
	if ( is_object( $order ) && method_exists( $order, $method ) ) {
		return $order->{$method}();
	}

	if ( is_array( $order ) ) {
		$key = strtolower( preg_replace( '/^(get_|set_)/', '', (string) $method ) );
		if ( isset( $order[ $key ] ) ) {
			return $order[ $key ];
		}
	}

	return $default;
}

/**
 * Order total.
 *
 * @param mixed $order Order.
 * @return float
 */
function velotok_core_payment_order_total( $order ) {
	if ( is_array( $order ) ) {
		foreach ( array( 'total', 'amount', 'order_total' ) as $key ) {
			if ( isset( $order[ $key ] ) ) {
				return (float) $order[ $key ];
			}
		}
	}

	return (float) velotok_core_payment_order_value( $order, 'get_total', 0 );
}

/**
 * Order currency.
 *
 * @param mixed $order Order.
 * @return string
 */
function velotok_core_payment_order_currency( $order ) {
	$currency = (string) velotok_core_payment_order_value( $order, 'get_currency', 'RUB' );

	return '' !== $currency ? strtoupper( $currency ) : 'RUB';
}

/**
 * Read order meta safely.
 *
 * @param mixed  $order Order.
 * @param string $key   Meta key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function velotok_core_payment_order_meta( $order, $key, $default = '' ) {
	if ( is_object( $order ) && method_exists( $order, 'get_meta' ) ) {
		$value = $order->get_meta( $key, true );
		return ( '' === $value || null === $value ) ? $default : $value;
	}

	if ( is_object( $order ) && method_exists( $order, 'get_id' ) && function_exists( 'get_post_meta' ) ) {
		$value = get_post_meta( $order->get_id(), $key, true );
		return ( '' === $value || null === $value ) ? $default : $value;
	}

	if ( is_array( $order ) ) {
		$short = ltrim( (string) $key, '_' );
		if ( isset( $order[ $short ] ) ) {
			return $order[ $short ];
		}
		if ( isset( $order[ $key ] ) ) {
			return $order[ $key ];
		}
	}

	return $default;
}

/**
 * Read the first non-empty meta value from a list of compatible keys.
 *
 * @param mixed $order   Order.
 * @param array $keys    Meta keys.
 * @param mixed $default Default value.
 * @return mixed
 */
function velotok_core_payment_order_meta_first( $order, $keys, $default = '' ) {
	foreach ( (array) $keys as $key ) {
		$value = velotok_core_payment_order_meta( $order, $key, '' );
		if ( '' !== $value && null !== $value ) {
			return $value;
		}
	}

	return $default;
}

/**
 * Write order meta safely.
 *
 * @param mixed  $order Order.
 * @param string $key   Meta key.
 * @param mixed  $value Value.
 * @return void
 */
function velotok_core_payment_update_order_meta( $order, $key, $value ) {
	if ( is_object( $order ) && method_exists( $order, 'update_meta_data' ) ) {
		$order->update_meta_data( $key, $value );
		return;
	}

	if ( is_object( $order ) && method_exists( $order, 'get_id' ) && function_exists( 'update_post_meta' ) ) {
		update_post_meta( $order->get_id(), $key, $value );
		return;
	}

	if ( is_array( $order ) ) {
		$order[ ltrim( (string) $key, '_' ) ] = $value;
	}
}

/**
 * Persist an order after meta changes when the API is available.
 *
 * @param mixed $order Order.
 * @return void
 */
function velotok_core_payment_save_order( $order ) {
	if ( is_object( $order ) && method_exists( $order, 'save' ) ) {
		$order->save();
	}
}

/**
 * Add an order note when the order supports notes.
 *
 * @param mixed  $order Order.
 * @param string $note  Note text.
 * @return void
 */
function velotok_core_payment_add_order_note( $order, $note ) {
	if ( is_object( $order ) && method_exists( $order, 'add_order_note' ) ) {
		$order->add_order_note( $note );
	}
}

/**
 * Apply a payment status to an order and to its WooCommerce status.
 *
 * @param mixed  $order  Order.
 * @param mixed  $status Payment status.
 * @param string $note   Optional order note.
 * @return bool
 */
function velotok_core_payment_apply_order_status( $order, $status, $note = '' ) {
	$key = velotok_core_payment_normalize_status( $status );

	if ( ! velotok_core_payment_status_is_valid( $key ) ) {
		return false;
	}

	$order_status = velotok_core_payment_order_status( $key );

	if ( 'processing' === $order_status && is_object( $order ) && method_exists( $order, 'needs_processing' ) && ! $order->needs_processing() ) {
		$order_status = 'completed';
	}

	velotok_core_payment_update_order_meta( $order, '_velotok_payment_status', $key );
	velotok_core_payment_update_order_meta( $order, '_yookassa_payment_status', $key );
	velotok_core_payment_update_order_meta( $order, '_payment_status', $key );
	velotok_core_payment_update_order_meta( $order, '_velotok_payment_order_status', $order_status );
	velotok_core_payment_update_order_meta( $order, '_velotok_payment_updated_at', gmdate( 'c' ) );

	$status_updated = false;

	if ( '' !== $order_status && is_object( $order ) && method_exists( $order, 'update_status' ) ) {
		$order->update_status( $order_status, $note );
		$status_updated = true;
	}

	if ( '' !== $note && ! $status_updated ) {
		velotok_core_payment_add_order_note( $order, $note );
	}

	velotok_core_payment_save_order( $order );
	velotok_core_payment_do_action( 'velotok_core_payment_order_status_applied', $key, $order_status, $order );

	return true;
}

/**
 * Deterministic idempotency key for an order operation.
 *
 * @param mixed  $order Order.
 * @param string $scope Operation scope.
 * @return string
 */
function velotok_core_payment_idempotency_key( $order, $scope = 'payment', $extra = '' ) {
	if ( '' === (string) $extra && is_object( $order ) && method_exists( $order, 'get_meta' ) ) {
		$stored = (string) $order->get_meta( '_velotok_' . $scope . '_idempotency_key', true );
		if ( '' !== $stored ) {
			return $stored;
		}
	}

	$order_id  = (string) velotok_core_payment_order_value( $order, 'get_id', '' );
	$order_key = (string) velotok_core_payment_order_value( $order, 'get_order_key', '' );
	$seed      = $scope . '|' . $order_id . '|' . $order_key . '|' . number_format( velotok_core_payment_order_total( $order ), 2, '.', '' ) . '|' . (string) $extra;
	$hash      = hash( 'sha256', $seed );
	$key       = 'velotok-' . $scope . '-' . ( '' !== $order_id ? $order_id . '-' : '' ) . substr( $hash, 0, 24 );

	return (string) velotok_core_payment_apply_filters( 'velotok_core_payment_idempotency_key', $key, $order, $scope, $extra );
}

/**
 * Build the HMAC-SHA256 webhook signature.
 *
 * @param string|array $body   Raw body or decoded payload.
 * @param string|null  $secret Signing secret.
 * @return string
 */
function velotok_core_payment_webhook_signature( $body, $secret = null ) {
	if ( null === $secret ) {
		$secret = velotok_core_yookassa_webhook_secret();
	}

	if ( ! is_string( $body ) ) {
		$body = function_exists( 'wp_json_encode' ) ? wp_json_encode( $body ) : json_encode( $body );
	}

	return hash_hmac( 'sha256', (string) $body, (string) $secret );
}

/**
 * Verify a webhook signature in constant time.
 *
 * @param string|array $body      Raw body or decoded payload.
 * @param string       $signature Provided signature.
 * @param string|null  $secret    Signing secret.
 * @return bool
 */
function velotok_core_payment_webhook_verify( $body, $signature, $secret = null ) {
	if ( null === $secret ) {
		$secret = velotok_core_yookassa_webhook_secret();
	}

	$secret    = (string) $secret;
	$signature = trim( (string) $signature );

	if ( '' === $secret || '' === $signature ) {
		return false;
	}

	if ( 0 === strpos( $signature, 'sha256=' ) ) {
		$signature = substr( $signature, 7 );
	}

	$expected = velotok_core_payment_webhook_signature( $body, $secret );

	if ( ! hash_equals( $expected, strtolower( $signature ) ) && ! hash_equals( $expected, $signature ) ) {
		return false;
	}

	$verified = true;

	return (bool) velotok_core_payment_apply_filters( 'velotok_core_payment_webhook_verified', $verified, $body, $signature );
}

/**
 * Normalize a header name for case-insensitive, transport-agnostic lookup.
 *
 * Accepts both `X-Velotok-Signature` and `HTTP_X_VELOTOK_SIGNATURE` forms.
 *
 * @param string $name Header name.
 * @return string
 */
function velotok_core_payment_normalize_header_name( $name ) {
	$name = strtolower( trim( (string) $name ) );
	$name = str_replace( '_', '-', $name );

	if ( 0 === strpos( $name, 'http-' ) ) {
		$name = substr( $name, 5 );
	}

	return $name;
}

/**
 * Case-insensitive header lookup.
 *
 * @param array  $headers Headers.
 * @param string $name    Header name.
 * @return string
 */
function velotok_core_payment_header( $headers, $name ) {
	if ( ! is_array( $headers ) ) {
		return '';
	}

	$needle = velotok_core_payment_normalize_header_name( $name );

	foreach ( $headers as $key => $value ) {
		if ( velotok_core_payment_normalize_header_name( $key ) === $needle ) {
			return is_array( $value ) ? (string) reset( $value ) : (string) $value;
		}
	}

	return '';
}

/**
 * Extract a webhook signature from request headers.
 *
 * @param array $headers Headers.
 * @return string
 */
function velotok_core_payment_webhook_signature_from_headers( $headers ) {
	foreach ( array( 'X-Velotok-Signature', 'X-Yookassa-Signature', 'X-Webhook-Signature', 'X-Signature' ) as $name ) {
		$value = velotok_core_payment_header( $headers, $name );
		if ( '' !== $value ) {
			return $value;
		}
	}

	return '';
}

/**
 * Stable webhook event key used for idempotency.
 *
 * @param string|array $payload Payload.
 * @return string
 */
function velotok_core_payment_webhook_event_id( $payload ) {
	if ( ! is_array( $payload ) ) {
		$payload = json_decode( (string) $payload, true );
	}

	if ( ! is_array( $payload ) ) {
		return '';
	}

	$event_id = '';

	if ( isset( $payload['event']['id'] ) ) {
		$event_id = (string) $payload['event']['id'];
	} elseif ( isset( $payload['id'] ) && isset( $payload['event'] ) && is_string( $payload['event'] ) ) {
		$event_id = (string) $payload['id'];
	}

	if ( '' !== $event_id ) {
		return $event_id;
	}

	$event_name = '';
	if ( isset( $payload['event']['event'] ) ) {
		$event_name = (string) $payload['event']['event'];
	} elseif ( isset( $payload['event'] ) && is_string( $payload['event'] ) ) {
		$event_name = (string) $payload['event'];
	} elseif ( isset( $payload['type'] ) ) {
		$event_name = (string) $payload['type'];
	}

	$object_id = isset( $payload['object']['id'] ) ? (string) $payload['object']['id'] : '';
	$status    = isset( $payload['object']['status'] ) ? (string) $payload['object']['status'] : '';

	return hash( 'sha256', $event_name . '|' . $object_id . '|' . $status );
}

/**
 * Processed webhook event store.
 *
 * @return array<string,array<string,mixed>>
 */
function &velotok_core_payment_processed_events() {
	static $events = null;

	if ( null === $events ) {
		$stored = function_exists( 'get_option' ) ? get_option( 'velotok_payment_processed_events', array() ) : array();
		$events = is_array( $stored ) ? $stored : array();
	}

	return $events;
}

/**
 * Whether a webhook event was already processed.
 *
 * @param string $event_id Event key.
 * @return bool
 */
function velotok_core_payment_webhook_is_duplicate( $event_id ) {
	$events =& velotok_core_payment_processed_events();

	return '' !== (string) $event_id && isset( $events[ (string) $event_id ] );
}

/**
 * Remember a processed webhook event.
 *
 * @param string $event_id Event key.
 * @param array  $meta     Event metadata.
 * @return void
 */
function velotok_core_payment_webhook_mark_processed( $event_id, $meta = array() ) {
	$event_id = (string) $event_id;

	if ( '' === $event_id ) {
		return;
	}

	$events =& velotok_core_payment_processed_events();
	$events[ $event_id ] = array_merge(
		array( 'processed_at' => gmdate( 'c' ) ),
		is_array( $meta ) ? $meta : array()
	);

	if ( count( $events ) > 1000 ) {
		$events = array_slice( $events, -1000, null, true );
	}

	if ( function_exists( 'update_option' ) ) {
		update_option( 'velotok_payment_processed_events', $events, false );
	}
}

/**
 * Payment status implied by a webhook payload.
 *
 * @param array $payload Decoded payload.
 * @return string
 */
function velotok_core_payment_webhook_status( $payload ) {
	if ( ! is_array( $payload ) ) {
		return '';
	}

	$event_name = '';
	if ( isset( $payload['event']['event'] ) ) {
		$event_name = (string) $payload['event']['event'];
	} elseif ( isset( $payload['event'] ) && is_string( $payload['event'] ) ) {
		$event_name = (string) $payload['event'];
	} elseif ( isset( $payload['type'] ) ) {
		$event_name = (string) $payload['type'];
	}

	$object = isset( $payload['object'] ) && is_array( $payload['object'] ) ? $payload['object'] : array();
	$status = isset( $object['status'] ) ? velotok_core_payment_normalize_status( $object['status'] ) : '';

	if ( 0 === strpos( $event_name, 'refund.' ) ) {
		return 'succeeded' === $status ? 'refunded' : '';
	}

	if ( velotok_core_payment_status_is_valid( $status ) ) {
		return $status;
	}

	$map = array(
		'payment.succeeded'           => 'succeeded',
		'payment.canceled'            => 'canceled',
		'payment.cancelled'           => 'canceled',
		'payment.waiting_for_capture' => 'waiting_for_capture',
		'payment.pending'             => 'pending',
	);

	return isset( $map[ $event_name ] ) ? $map[ $event_name ] : '';
}

/**
 * Find the WooCommerce order id inside a webhook payload.
 *
 * @param array $payload Decoded payload.
 * @return string
 */
function velotok_core_payment_webhook_order_id( $payload ) {
	if ( ! is_array( $payload ) ) {
		return '';
	}

	$object = isset( $payload['object'] ) && is_array( $payload['object'] ) ? $payload['object'] : array();

	if ( isset( $object['metadata']['order_id'] ) ) {
		return (string) $object['metadata']['order_id'];
	}
	if ( isset( $object['order_id'] ) ) {
		return (string) $object['order_id'];
	}
	if ( isset( $payload['order_id'] ) ) {
		return (string) $payload['order_id'];
	}

	return '';
}

/**
 * Find an order id by a stored ЮKassa payment id.
 *
 * @param string $payment_id Payment id.
 * @return string
 */
function velotok_core_payment_find_order_id_by_payment( $payment_id ) {
	$payment_id = (string) $payment_id;

	if ( '' === $payment_id || ! function_exists( 'wc_get_orders' ) ) {
		return '';
	}

	foreach ( array( '_velotok_payment_id', '_yookassa_payment_id', '_payment_id' ) as $meta_key ) {
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_key'   => $meta_key,
				'meta_value' => $payment_id,
				'return'     => 'ids',
			)
		);

		if ( ! empty( $orders ) ) {
			return (string) reset( $orders );
		}
	}

	return '';
}

/**
 * Verify, de-duplicate and apply a webhook.
 *
 * @param string|array $body      Raw body or decoded payload.
 * @param string|null  $signature Provided signature.
 * @param array        $headers   Request headers.
 * @return array<string,mixed>
 */
function velotok_core_payment_webhook_process( $body, $signature = null, $headers = array() ) {
	$payload = is_array( $body ) ? $body : json_decode( (string) $body, true );

	if ( ! is_array( $payload ) ) {
		return array(
			'ok'         => false,
			'code'       => 'PAYMENT_WEBHOOK_INVALID',
			'verified'   => false,
			'idempotent' => false,
		);
	}

	if ( '' === (string) $signature ) {
		$signature = velotok_core_payment_webhook_signature_from_headers( $headers );
	}

	if ( ! velotok_core_payment_webhook_verify( $body, $signature ) ) {
		return array(
			'ok'         => false,
			'code'       => 'PAYMENT_WEBHOOK_SIGNATURE_INVALID',
			'verified'   => false,
			'idempotent' => false,
		);
	}

	$event_id = velotok_core_payment_webhook_event_id( $payload );

	if ( velotok_core_payment_webhook_is_duplicate( $event_id ) ) {
		$events =& velotok_core_payment_processed_events();

		$status = isset( $events[ $event_id ]['status'] ) ? $events[ $event_id ]['status'] : '';

		return array(
			'ok'           => true,
			'code'         => 'PAYMENT_WEBHOOK_DUPLICATE',
			'duplicate'    => true,
			'verified'     => true,
			'idempotent'   => true,
			'event_id'     => $event_id,
			'status'       => $status,
			'order_id'     => isset( $events[ $event_id ]['order_id'] ) ? $events[ $event_id ]['order_id'] : '',
			'order_status' => '' !== $status ? velotok_core_payment_order_status( $status ) : '',
		);
	}

	$status = velotok_core_payment_webhook_status( $payload );

	if ( ! velotok_core_payment_status_is_valid( $status ) ) {
		return array(
			'ok'         => false,
			'code'       => 'PAYMENT_STATUS_INVALID',
			'verified'   => true,
			'idempotent' => true,
			'event_id'   => $event_id,
		);
	}

	$order_id = velotok_core_payment_webhook_order_id( $payload );

	if ( '' === $order_id && isset( $payload['object']['payment_id'] ) ) {
		$order_id = velotok_core_payment_find_order_id_by_payment( (string) $payload['object']['payment_id'] );
	}

	$order = velotok_core_payment_apply_filters( 'velotok_core_payment_webhook_order', null, $order_id, $payload );

	if ( null === $order && '' !== $order_id && function_exists( 'wc_get_order' ) ) {
		$order = wc_get_order( (int) $order_id );
	}

	if ( $order ) {
		velotok_core_payment_apply_order_status( $order, $status, 'ЮKassa: ' . $status . ' (' . $event_id . ')' );
	}

	velotok_core_payment_webhook_mark_processed(
		$event_id,
		array(
			'status'   => $status,
			'order_id' => $order_id,
		)
	);

	velotok_core_payment_do_action( 'velotok_core_payment_webhook_processed', $status, $order, $payload, $event_id );

	return array(
		'ok'           => true,
		'code'         => 'PAYMENT_WEBHOOK_PROCESSED',
		'duplicate'    => false,
		'verified'     => true,
		'idempotent'   => true,
		'event_id'     => $event_id,
		'status'       => $status,
		'order_id'     => $order_id,
		'order_status' => velotok_core_payment_order_status( $status ),
	);
}

/**
 * Low-level ЮKassa API request.
 *
 * A filter can supply the decoded response, which keeps unit tests offline:
 * `velotok_core_yookassa_api_response`.
 *
 * @param string     $method          HTTP method.
 * @param string     $path            API path.
 * @param array|null $body            Request body.
 * @param string     $idempotency_key Idempotency key.
 * @return array<string,mixed>
 */
function velotok_core_yookassa_request( $method, $path, $body = null, $idempotency_key = '' ) {
	$method = strtoupper( (string) $method );
	$url    = velotok_core_yookassa_api_base_url() . '/' . ltrim( (string) $path, '/' );
	$args   = array(
		'method'  => $method,
		'timeout' => 20,
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( velotok_core_yookassa_shop_id() . ':' . velotok_core_yookassa_secret_key() ),
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		),
	);

	if ( '' !== (string) $idempotency_key ) {
		$args['headers']['Idempotence-Key'] = (string) $idempotency_key;
	}

	if ( null !== $body ) {
		$args['body'] = function_exists( 'wp_json_encode' ) ? wp_json_encode( $body ) : json_encode( $body );
	}

	$mocked = velotok_core_payment_apply_filters( 'velotok_core_yookassa_api_response', null, $method, $path, $body, $idempotency_key, $url, $args );

	if ( null !== $mocked ) {
		return is_array( $mocked ) ? $mocked : array();
	}

	if ( ! function_exists( 'wp_remote_request' ) ) {
		return array(
			'error' => array(
				'code'    => 'YOO_TRANSPORT_UNAVAILABLE',
				'message' => 'HTTP transport is not available.',
			),
		);
	}

	$response = wp_remote_request( $url, $args );

	if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
		return array(
			'error' => array(
				'code'    => 'YOO_TRANSPORT_ERROR',
				'message' => $response->get_error_message(),
			),
		);
	}

	$code = function_exists( 'wp_remote_retrieve_response_code' ) ? (int) wp_remote_retrieve_response_code( $response ) : 0;
	$raw  = function_exists( 'wp_remote_retrieve_body' ) ? (string) wp_remote_retrieve_body( $response ) : '';
	$data = json_decode( $raw, true );

	if ( $code < 200 || $code >= 300 ) {
		return array(
			'error' => array(
				'code'    => 'YOO_HTTP_' . $code,
				'message' => is_array( $data ) && isset( $data['description'] ) ? (string) $data['description'] : $raw,
			),
			'status' => $code,
		);
	}

	return is_array( $data ) ? $data : array();
}

/**
 * Create a payment in ЮKassa.
 *
 * @param mixed $order Order or order array.
 * @param array $args  Overrides.
 * @return array<string,mixed>
 */
function velotok_core_yookassa_create_payment( $order, $args = array() ) {
	$amount   = isset( $args['amount'] ) ? (float) $args['amount'] : velotok_core_payment_order_total( $order );
	$currency = isset( $args['currency'] ) ? strtoupper( (string) $args['currency'] ) : velotok_core_payment_order_currency( $order );
	$order_id = (string) velotok_core_payment_order_value( $order, 'get_id', '' );

	if ( '' === $order_id && isset( $args['order_id'] ) ) {
		$order_id = (string) $args['order_id'];
	}

	$body = array(
		'amount'       => array(
			'value'    => number_format( $amount, 2, '.', '' ),
			'currency' => $currency,
		),
		'capture'      => isset( $args['capture'] ) ? (bool) $args['capture'] : true,
		'confirmation' => array(
			'type'       => 'redirect',
			'return_url' => isset( $args['return_url'] ) ? (string) $args['return_url'] : velotok_core_yookassa_return_url( $order ),
		),
		'description'  => isset( $args['description'] ) ? (string) $args['description'] : sprintf( 'Оплата заказа №%s', '' !== $order_id ? $order_id : '' ),
		'metadata'     => array(
			'order_id' => $order_id,
		),
	);

	if ( isset( $args['receipt'] ) && is_array( $args['receipt'] ) ) {
		$body['receipt'] = $args['receipt'];
	}

	$idempotency_key = isset( $args['idempotency_key'] ) ? (string) $args['idempotency_key'] : velotok_core_payment_idempotency_key( $order, 'payment' );
	$response        = velotok_core_yookassa_request( 'POST', 'payments', $body, $idempotency_key );

	if ( isset( $response['error'] ) ) {
		return $response;
	}

	if ( empty( $response['id'] ) ) {
		return array(
			'error' => array(
				'code'    => 'YOO_PAYMENT_ID_MISSING',
				'message' => 'ЮKassa response does not contain a payment id.',
			),
		);
	}

	$response['_idempotency_key'] = $idempotency_key;
	$response['_amount']          = $amount;
	$response['_currency']        = $currency;
	$response['_order_id']        = $order_id;

	return $response;
}

/**
 * Process payment for an order and return a WooCommerce redirect result.
 *
 * @param mixed $order_id Order id, order object or order array.
 * @return array<string,mixed>
 */
function velotok_core_yookassa_process_payment( $order_id ) {
	$order = is_object( $order_id ) ? $order_id : null;

	if ( null === $order && is_array( $order_id ) ) {
		$order = $order_id;
	} elseif ( null === $order && function_exists( 'wc_get_order' ) ) {
		$order = wc_get_order( (int) $order_id );
	}

	if ( ! $order ) {
		return array(
			'result'   => 'failure',
			'messages' => 'Заказ не найден.',
		);
	}

	$amount = velotok_core_payment_order_total( $order );

	if ( $amount <= 0 ) {
		return array(
			'result'   => 'failure',
			'messages' => 'Сумма заказа должна быть больше нуля.',
		);
	}

	$currency = velotok_core_payment_order_currency( $order );

	if ( 'RUB' !== $currency ) {
		return array(
			'result'   => 'failure',
			'messages' => 'ЮKassa поддерживает оплату в рублях.',
		);
	}

	$existing_id     = (string) velotok_core_payment_order_meta_first( $order, array( '_velotok_payment_id', '_yookassa_payment_id', '_payment_id' ) );
	$existing_status = velotok_core_payment_normalize_status( velotok_core_payment_order_meta_first( $order, array( '_velotok_payment_status', '_yookassa_payment_status', '_payment_status' ) ) );
	$existing_url    = (string) velotok_core_payment_order_meta_first( $order, array( '_velotok_payment_confirmation_url', '_yookassa_confirmation_url', '_payment_confirmation_url' ) );

	if ( '' !== $existing_id && '' !== $existing_url && in_array( $existing_status, array( 'pending', 'waiting_for_capture' ), true ) ) {
		return array(
			'result'     => 'success',
			'redirect'   => $existing_url,
			'payment_id' => $existing_id,
			'status'     => $existing_status,
			'idempotent' => true,
		);
	}

	$response = velotok_core_yookassa_create_payment( $order );

	if ( isset( $response['error'] ) ) {
		$message = isset( $response['error']['message'] ) ? (string) $response['error']['message'] : 'Не удалось создать платёж.';
		velotok_core_payment_add_order_note( $order, 'ЮKassa: ' . $message );

		return array(
			'result'   => 'failure',
			'messages' => $message,
		);
	}

	$payment_id = (string) $response['id'];
	$status     = velotok_core_payment_normalize_status( isset( $response['status'] ) ? $response['status'] : 'pending' );
	$url        = isset( $response['confirmation']['url'] ) ? (string) $response['confirmation']['url'] : '';

	$idempotency_key = isset( $response['_idempotency_key'] ) ? $response['_idempotency_key'] : velotok_core_payment_idempotency_key( $order, 'payment' );

	velotok_core_payment_update_order_meta( $order, '_velotok_payment_id', $payment_id );
	velotok_core_payment_update_order_meta( $order, '_yookassa_payment_id', $payment_id );
	velotok_core_payment_update_order_meta( $order, '_velotok_payment_idempotency_key', $idempotency_key );
	velotok_core_payment_update_order_meta( $order, '_yookassa_payment_idempotency_key', $idempotency_key );
	velotok_core_payment_update_order_meta( $order, '_velotok_payment_amount', $amount );
	velotok_core_payment_update_order_meta( $order, '_velotok_payment_currency', $currency );
	velotok_core_payment_update_order_meta( $order, '_velotok_payment_confirmation_url', $url );
	velotok_core_payment_update_order_meta( $order, '_yookassa_confirmation_url', $url );

	velotok_core_payment_apply_order_status( $order, $status, 'ЮKassa: платёж ' . $payment_id . ' создан (' . $status . ').' );
	velotok_core_payment_save_order( $order );

	if ( function_exists( 'WC' ) ) {
		$wc = WC();
		if ( $wc && isset( $wc->cart ) && is_object( $wc->cart ) && method_exists( $wc->cart, 'empty_cart' ) ) {
			$wc->cart->empty_cart();
		}
	}

	if ( '' === $url && 'succeeded' !== $status ) {
		return array(
			'result'   => 'failure',
			'messages' => 'ЮKassa не вернула ссылку на подтверждение оплаты.',
		);
	}

	return array(
		'result'     => 'success',
		'redirect'   => '' !== $url ? $url : velotok_core_yookassa_return_url( $order ),
		'payment_id' => $payment_id,
		'status'     => $status,
		'idempotent' => false,
	);
}

/**
 * Payment status after a refund.
 *
 * @param float $amount Refund amount.
 * @param float $total  Order total.
 * @return string
 */
function velotok_core_payment_refund_status( $amount, $total ) {
	$amount = (float) $amount;
	$total  = (float) $total;

	if ( $total <= 0 || $amount <= 0 ) {
		return '';
	}

	return ( $amount + 0.009 >= $total ) ? 'refunded' : 'succeeded';
}

/**
 * Refund a payment through ЮKassa and update the payment status.
 *
 * @param mixed       $order  Order id, order object or order array.
 * @param float|null  $amount Refund amount; null means full refund.
 * @param string      $reason Refund reason.
 * @return array<string,mixed>
 */
function velotok_core_yookassa_refund( $order, $amount = null, $reason = '' ) {
	$order = is_object( $order ) ? $order : null;

	if ( null === $order && is_array( $order ) ) {
		$order = $order;
	} elseif ( null === $order && ( is_int( $order ) || ctype_digit( (string) $order ) ) && function_exists( 'wc_get_order' ) ) {
		$order = wc_get_order( (int) $order );
	}

	if ( ! $order ) {
		return array(
			'ok'      => false,
			'code'    => 'PAYMENT_ORDER_NOT_FOUND',
			'message' => 'Заказ не найден.',
		);
	}

	$total  = velotok_core_payment_order_total( $order );
	$amount = null === $amount ? $total : (float) $amount;
	$amount = round( $amount, 2 );

	if ( $amount <= 0 || $amount > $total + 0.009 ) {
		return array(
			'ok'      => false,
			'code'    => 'PAYMENT_REFUND_AMOUNT_INVALID',
			'message' => 'Сумма возврата должна быть больше нуля и не превышать сумму заказа.',
		);
	}

	$payment_id = (string) velotok_core_payment_order_meta_first( $order, array( '_velotok_payment_id', '_yookassa_payment_id', '_payment_id' ) );

	if ( '' === $payment_id ) {
		return array(
			'ok'      => false,
			'code'    => 'PAYMENT_REFUND_PAYMENT_MISSING',
			'message' => 'У заказа нет платежа ЮKassa для возврата.',
		);
	}

	$body = array(
		'payment_id'  => $payment_id,
		'amount'      => array(
			'value'    => number_format( $amount, 2, '.', '' ),
			'currency' => velotok_core_payment_order_currency( $order ),
		),
		'description' => (string) $reason,
	);

	$idempotency_key = velotok_core_payment_idempotency_key( $order, 'refund', number_format( $amount, 2, '.', '' ) );
	$response        = velotok_core_yookassa_request( 'POST', 'refunds', $body, $idempotency_key );

	if ( isset( $response['error'] ) ) {
		return array(
			'ok'      => false,
			'code'    => isset( $response['error']['code'] ) ? $response['error']['code'] : 'PAYMENT_REFUND_FAILED',
			'message' => isset( $response['error']['message'] ) ? $response['error']['message'] : 'Возврат не выполнен.',
		);
	}

	$refunded  = (float) velotok_core_payment_order_meta( $order, '_velotok_refunded_amount', 0 );
	$refunded += $amount;
	$status    = velotok_core_payment_refund_status( $refunded, $total );

	velotok_core_payment_update_order_meta( $order, '_velotok_refunded_amount', $refunded );

	if ( 'refunded' === $status ) {
		velotok_core_payment_apply_order_status( $order, 'refunded', 'ЮKassa: полный возврат ' . number_format( $amount, 2, '.', '' ) . ' ₽.' );
	} else {
		velotok_core_payment_update_order_meta( $order, '_velotok_payment_status', 'succeeded' );
		velotok_core_payment_add_order_note( $order, 'ЮKassa: частичный возврат ' . number_format( $amount, 2, '.', '' ) . ' ₽.' );
	}

	velotok_core_payment_save_order( $order );

	return array(
		'ok'              => true,
		'status'          => $status,
		'amount'          => $amount,
		'total'           => $total,
		'full'            => 'refunded' === $status,
		'payment_id'      => $payment_id,
		'idempotency_key' => $idempotency_key,
		'refund_id'       => isset( $response['id'] ) ? (string) $response['id'] : '',
	);
}

/**
 * Payment contract for the payment specification.
 *
 * The contract intentionally contains secret names but no secret values.
 *
 * @return array<string,mixed>
 */
function velotok_core_payment_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$model = velotok_core_payment_status_model();

	$contract = array(
		'spec'             => 'payment',
		'requirement'      => 'TESTEB-44',
		'requirement_ids'  => array( 'TESTEB-44' ),
		'gateway'          => array(
			'class'       => 'Velotok_Yookassa_Gateway',
			'id'          => 'velotok_yookassa',
			'provider'    => 'yookassa',
			'provider_name' => 'ЮKassa',
			'api_version' => 'v3',
			'api_base_url' => velotok_core_yookassa_api_base_url(),
			'woocommerce' => 'WC_Payment_Gateway',
		),
		'statuses'         => $model,
		'status_ids'       => array_keys( $model ),
		'order_statuses'   => array(
			'pending'             => 'pending',
			'waiting_for_capture' => 'on-hold',
			'succeeded'           => 'processing',
			'canceled'            => 'cancelled',
			'refunded'            => 'refunded',
		),
		'webhook'          => array(
			'header'      => 'X-Velotok-Signature',
			'algorithm'   => 'sha256',
			'hmac'        => true,
			'secret_env'  => 'VELOTOK_YOOKASSA_WEBHOOK_SECRET',
			'idempotent'  => true,
			'idempotency_key' => 'event_id',
		),
		'secrets'          => array(
			'shop_id_env'      => 'VELOTOK_YOOKASSA_SHOP_ID',
			'secret_key_env'   => 'VELOTOK_YOOKASSA_SECRET_KEY',
			'webhook_env'      => 'VELOTOK_YOOKASSA_WEBHOOK_SECRET',
			'source'           => 'env_or_option',
			'configured'       => velotok_core_yookassa_is_configured(),
		),
		'refund'           => array(
			'full_status'  => 'refunded',
			'partial_status' => 'succeeded',
			'order_status' => 'refunded',
			'api_path'     => 'refunds',
		),
		'currency'         => 'RUB',
		'mocked'           => false,
	);

	return $contract;
}

/**
 * Gateway implementation shared by the WooCommerce and standalone classes.
 */
trait Velotok_Yookassa_Gateway_Behavior {
	/**
	 * Gateway settings exposed in WooCommerce.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => 'Включить/выключить',
				'type'    => 'checkbox',
				'label'   => 'Включить оплату через ЮKassa',
				'default' => 'yes',
			),
			'title'          => array(
				'title'       => 'Название',
				'type'        => 'text',
				'description' => 'Название способа оплаты на странице оформления заказа.',
				'default'     => 'ЮKassa',
			),
			'description'    => array(
				'title'       => 'Описание',
				'type'        => 'textarea',
				'description' => 'Описание способа оплаты на странице оформления заказа.',
				'default'     => 'Оплата банковской картой через ЮKassa.',
			),
			'shop_id'        => array(
				'title'       => 'Shop ID',
				'type'        => 'text',
				'description' => 'Можно оставить пустым, если задана переменная окружения VELOTOK_YOOKASSA_SHOP_ID.',
			),
			'secret_key'     => array(
				'title'       => 'Secret key',
				'type'        => 'password',
				'description' => 'Можно оставить пустым, если задана переменная окружения VELOTOK_YOOKASSA_SECRET_KEY.',
			),
			'webhook_secret' => array(
				'title'       => 'Webhook secret',
				'type'        => 'password',
				'description' => 'Секрет для проверки подписи webhook. Можно задать через VELOTOK_YOOKASSA_WEBHOOK_SECRET.',
			),
		);
	}

	/**
	 * Configure gateway properties.
	 *
	 * @return void
	 */
	public function velotok_setup_gateway() {
		$this->id                 = 'velotok_yookassa';
		$this->method_title       = 'ЮKassa';
		$this->method_description = 'Оплата через ЮKassa API v3.';
		$this->has_fields         = false;
		$this->supports           = array( 'products', 'refunds' );

		if ( method_exists( $this, 'init_settings' ) ) {
			$this->init_settings();
		}

		$this->title       = (string) $this->velotok_gateway_option( 'title', 'ЮKassa' );
		$this->description = (string) $this->velotok_gateway_option( 'description', 'Оплата банковской картой через ЮKassa.' );
		$this->enabled     = (string) $this->velotok_gateway_option( 'enabled', 'yes' );

		if ( function_exists( 'add_action' ) ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
	}

	/**
	 * Read a gateway option.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function velotok_gateway_option( $key, $default = '' ) {
		if ( method_exists( $this, 'get_option' ) ) {
			return $this->get_option( $key, $default );
		}

		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Gateway availability.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== (string) $this->enabled ) {
			return false;
		}

		return velotok_core_yookassa_is_configured();
	}

	/**
	 * Start payment and return a WooCommerce redirect result.
	 *
	 * @param int $order_id Order id.
	 * @return array<string,mixed>
	 */
	public function process_payment( $order_id ) {
		return velotok_core_yookassa_process_payment( $order_id );
	}

	/**
	 * Refund a payment.
	 *
	 * @param int        $order_id Order id.
	 * @param float|null $amount   Amount.
	 * @param string     $reason   Reason.
	 * @return bool|object
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$result = velotok_core_yookassa_refund( $order_id, $amount, $reason );

		if ( ! empty( $result['ok'] ) ) {
			return true;
		}

		if ( class_exists( 'WP_Error' ) ) {
			$code = isset( $result['code'] ) ? $result['code'] : 'PAYMENT_REFUND_FAILED';
			$message = isset( $result['message'] ) ? $result['message'] : 'Возврат не выполнен.';
			return new WP_Error( $code, $message );
		}

		return false;
	}

	/**
	 * Webhook entry point used by the REST callback.
	 *
	 * @param string|array $body      Body.
	 * @param string       $signature Signature.
	 * @param array        $headers   Headers.
	 * @return array<string,mixed>
	 */
	public function handle_webhook( $body, $signature = '', $headers = array() ) {
		return velotok_core_payment_webhook_process( $body, $signature, $headers );
	}
}

/**
 * Define the gateway class once WooCommerce is loaded.
 *
 * velotok-core is included before WooCommerce (plugin order is alphabetical), so
 * WC_Payment_Gateway does not exist yet at include time. Defining the standalone
 * fallback there left WooCommerce without a real gateway: the class was not a
 * WC_Payment_Gateway, was never registered, and PHP 8.2 reported every gateway
 * property as dynamic. When WC_Payment_Gateway is already available (contract
 * tests stub it, or WooCommerce loaded first) the class is defined immediately.
 *
 * @return void
 */
function velotok_core_define_payment_gateway() {
	if ( class_exists( 'Velotok_Yookassa_Gateway' ) ) {
		return;
	}

	if ( class_exists( 'WC_Payment_Gateway' ) ) {
		/**
		 * ЮKassa WooCommerce payment gateway.
		 */
		class Velotok_Yookassa_Gateway extends WC_Payment_Gateway {
			use Velotok_Yookassa_Gateway_Behavior;

			/**
			 * Constructor.
			 */
			public function __construct() {
				$this->id                 = 'velotok_yookassa';
				$this->method_title       = 'ЮKassa';
				$this->method_description = 'Оплата через ЮKassa API v3.';
				$this->has_fields         = false;
				$this->supports           = array( 'products', 'refunds' );

				$this->init_form_fields();
				// WC_Payment_Gateway не объявляет конструктор (WC 11): parent::__construct()
				// даёт фатал «Cannot call constructor». Настройки инициализирует трейт.
				$this->velotok_setup_gateway();
			}
		}
	} else {
		/**
		 * Standalone fallback used by contract tests outside WordPress.
		 */
		class Velotok_Yookassa_Gateway {
			use Velotok_Yookassa_Gateway_Behavior;

			/**
			 * Settings.
			 *
			 * @var array
			 */
			public $settings = array();
			public $form_fields = array();

			/**
			 * Constructor.
			 */
			public function __construct() {
				$this->velotok_setup_gateway();
				$this->init_form_fields();
			}

			/**
			 * Read a setting.
			 *
			 * @param string $key     Key.
			 * @param mixed  $default Default value.
			 * @return mixed
			 */
			public function get_option( $key, $default = '' ) {
				return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
			}
		}
	}

	if ( ! class_exists( 'Velotok_Payment_Gateway' ) ) {
		class_alias( 'Velotok_Yookassa_Gateway', 'Velotok_Payment_Gateway' );
	}
	if ( ! class_exists( 'Velotok_Gateway_Yookassa' ) ) {
		class_alias( 'Velotok_Yookassa_Gateway', 'Velotok_Gateway_Yookassa' );
	}
	if ( ! class_exists( 'Velotok_WC_Yookassa_Gateway' ) ) {
		class_alias( 'Velotok_Yookassa_Gateway', 'Velotok_WC_Yookassa_Gateway' );
	}
}

if ( class_exists( 'WC_Payment_Gateway' ) || ! function_exists( 'add_action' ) ) {
	velotok_core_define_payment_gateway();
} else {
	add_action( 'plugins_loaded', 'velotok_core_define_payment_gateway', 20 );
}

/**
 * Register the gateway with WooCommerce.
 *
 * @param array $gateways Gateways.
 * @return array
 */
function velotok_core_payment_register_gateway( $gateways ) {
	if ( ! is_array( $gateways ) ) {
		$gateways = array();
	}

	$gateways[] = 'Velotok_Yookassa_Gateway';

	return $gateways;
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'woocommerce_payment_gateways', 'velotok_core_payment_register_gateway' );
}

/**
 * REST callback for ЮKassa webhooks.
 *
 * @param mixed $request REST request.
 * @return mixed
 */
function velotok_core_payment_webhook_rest_callback( $request ) {
	$body    = is_object( $request ) && method_exists( $request, 'get_body' ) ? $request->get_body() : '';
	$headers = is_object( $request ) && method_exists( $request, 'get_headers' ) ? $request->get_headers() : array();
	$result  = velotok_core_payment_webhook_process( $body, velotok_core_payment_webhook_signature_from_headers( $headers ), $headers );

	if ( class_exists( 'WP_REST_Response' ) ) {
		return new WP_REST_Response( $result, ! empty( $result['ok'] ) ? 200 : 400 );
	}

	return $result;
}

/**
 * Register the webhook REST route.
 *
 * @return void
 */
function velotok_core_payment_register_rest_routes() {
	if ( function_exists( 'register_rest_route' ) ) {
		register_rest_route(
			'velotok/v1',
			'/yookassa/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => 'velotok_core_payment_webhook_rest_callback',
				'permission_callback' => '__return_true',
			)
		);
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'rest_api_init', 'velotok_core_payment_register_rest_routes' );
}

/**
 * Compatibility aliases for callers that do not use the `core` prefix.
 */
if ( ! function_exists( 'velotok_payment_contract' ) ) {
	function velotok_payment_contract() {
		return velotok_core_payment_contract();
	}
}
if ( ! function_exists( 'velotok_payment_gateway_contract' ) ) {
	function velotok_payment_gateway_contract() {
		return velotok_core_payment_contract();
	}
}
if ( ! function_exists( 'velotok_payment_statuses' ) ) {
	function velotok_payment_statuses() {
		return velotok_core_payment_statuses();
	}
}
if ( ! function_exists( 'velotok_payment_status_is_valid' ) ) {
	function velotok_payment_status_is_valid( $status ) {
		return velotok_core_payment_status_is_valid( $status );
	}
}
if ( ! function_exists( 'velotok_payment_status_model' ) ) {
	function velotok_payment_status_model() {
		return velotok_core_payment_status_model();
	}
}
if ( ! function_exists( 'velotok_core_payment_status_ids' ) ) {
	function velotok_core_payment_status_ids() {
		return velotok_core_payment_statuses();
	}
}
if ( ! function_exists( 'velotok_payment_normalize_status' ) ) {
	function velotok_payment_normalize_status( $status ) {
		return velotok_core_payment_normalize_status( $status );
	}
}
if ( ! function_exists( 'velotok_payment_status_label' ) ) {
	function velotok_payment_status_label( $status ) {
		return velotok_core_payment_status_label( $status );
	}
}
if ( ! function_exists( 'velotok_payment_can_transition' ) ) {
	function velotok_payment_can_transition( $from, $to ) {
		return velotok_core_payment_can_transition( $from, $to );
	}
}
if ( ! function_exists( 'velotok_core_payment_status_transitions' ) ) {
	function velotok_core_payment_status_transitions( $status ) {
		$model = velotok_core_payment_status_model();
		$key   = velotok_core_payment_normalize_status( $status );

		return isset( $model[ $key ] ) ? $model[ $key ]['transitions'] : array();
	}
}
if ( ! function_exists( 'velotok_payment_webhook_signature' ) ) {
	function velotok_payment_webhook_signature( $body, $secret = null ) {
		return velotok_core_payment_webhook_signature( $body, $secret );
	}
}
if ( ! function_exists( 'velotok_payment_webhook_verify' ) ) {
	function velotok_payment_webhook_verify( $body, $signature, $secret = null ) {
		return velotok_core_payment_webhook_verify( $body, $signature, $secret );
	}
}
if ( ! function_exists( 'velotok_payment_webhook_process' ) ) {
	function velotok_payment_webhook_process( $body, $signature = null, $headers = array() ) {
		return velotok_core_payment_webhook_process( $body, $signature, $headers );
	}
}
if ( ! function_exists( 'velotok_payment_refund' ) ) {
	function velotok_payment_refund( $order, $amount = null, $reason = '' ) {
		return velotok_core_yookassa_refund( $order, $amount, $reason );
	}
}
if ( ! function_exists( 'velotok_payment_create' ) ) {
	function velotok_payment_create( $order, $args = array() ) {
		return velotok_core_yookassa_create_payment( $order, $args );
	}
}
if ( ! function_exists( 'velotok_payment_process' ) ) {
	function velotok_payment_process( $order ) {
		return velotok_core_yookassa_process_payment( $order );
	}
}
if ( ! function_exists( 'velotok_core_payment_gateway_contract' ) ) {
	function velotok_core_payment_gateway_contract() {
		return velotok_core_payment_contract();
	}
}
if ( ! function_exists( 'velotok_core_yookassa_contract' ) ) {
	function velotok_core_yookassa_contract() {
		return velotok_core_payment_contract();
	}
}
if ( ! function_exists( 'velotok_core_payment_status_map' ) ) {
	function velotok_core_payment_status_map() {
		return velotok_core_payment_status_model();
	}
}
if ( ! function_exists( 'velotok_core_payment_order_status_map' ) ) {
	function velotok_core_payment_order_status_map() {
		$map = array();
		foreach ( velotok_core_payment_status_model() as $id => $status ) {
			$map[ $id ] = $status['order_status'];
		}
		return $map;
	}
}
if ( ! function_exists( 'velotok_core_yookassa_webhook_signature' ) ) {
	function velotok_core_yookassa_webhook_signature( $body, $secret = null ) {
		return velotok_core_payment_webhook_signature( $body, $secret );
	}
}
if ( ! function_exists( 'velotok_core_yookassa_webhook_verify' ) ) {
	function velotok_core_yookassa_webhook_verify( $body, $signature, $secret = null ) {
		return velotok_core_payment_webhook_verify( $body, $signature, $secret );
	}
}
if ( ! function_exists( 'velotok_core_yookassa_webhook_process' ) ) {
	function velotok_core_yookassa_webhook_process( $body, $signature = null, $headers = array() ) {
		return velotok_core_payment_webhook_process( $body, $signature, $headers );
	}
}
if ( ! function_exists( 'velotok_core_yookassa_handle_webhook' ) ) {
	function velotok_core_yookassa_handle_webhook( $body, $signature = null, $headers = array() ) {
		return velotok_core_payment_webhook_process( $body, $signature, $headers );
	}
}
if ( ! function_exists( 'velotok_core_payment_webhook_idempotency_key' ) ) {
	function velotok_core_payment_webhook_idempotency_key( $payload ) {
		return velotok_core_payment_webhook_event_id( $payload );
	}
}
if ( ! function_exists( 'velotok_core_payment_idempotency' ) ) {
	function velotok_core_payment_idempotency( $order, $scope = 'payment', $extra = '' ) {
		return velotok_core_payment_idempotency_key( $order, $scope, $extra );
	}
}
if ( ! function_exists( 'velotok_core_yookassa_refund_status' ) ) {
	function velotok_core_yookassa_refund_status( $amount, $total ) {
		return velotok_core_payment_refund_status( $amount, $total );
	}
}
if ( ! function_exists( 'velotok_core_yookassa_refund_payment' ) ) {
	function velotok_core_yookassa_refund_payment( $order, $amount = null, $reason = '' ) {
		return velotok_core_yookassa_refund( $order, $amount, $reason );
	}
}
if ( ! function_exists( 'velotok_core_yookassa_process_refund' ) ) {
	function velotok_core_yookassa_process_refund( $order, $amount = null, $reason = '' ) {
		return velotok_core_yookassa_refund( $order, $amount, $reason );
	}
}
if ( ! function_exists( 'velotok_core_yookassa_order_status' ) ) {
	function velotok_core_yookassa_order_status( $status ) {
		return velotok_core_payment_order_status( $status );
	}
}
if ( ! function_exists( 'velotok_payment_order_status' ) ) {
	function velotok_payment_order_status( $status ) {
		return velotok_core_payment_order_status( $status );
	}
}
if ( ! function_exists( 'velotok_payment_apply_order_status' ) ) {
	function velotok_payment_apply_order_status( $order, $status, $note = '' ) {
		return velotok_core_payment_apply_order_status( $order, $status, $note );
	}
}
if ( ! function_exists( 'velotok_core_payment_sync_order_status' ) ) {
	function velotok_core_payment_sync_order_status( $order, $status, $note = '' ) {
		return velotok_core_payment_apply_order_status( $order, $status, $note );
	}
}

function velotok_core_alias_payment_gateway() {
	foreach ( array( 'WC_Gateway_Yookassa', 'Velotok_Yookassa_Payment_Gateway', 'Velotok_WC_Gateway_Yookassa' ) as $velotok_payment_class_alias ) {
		if ( ! class_exists( $velotok_payment_class_alias ) && class_exists( 'Velotok_Yookassa_Gateway' ) ) {
			class_alias( 'Velotok_Yookassa_Gateway', $velotok_payment_class_alias );
		}
	}
}

if ( class_exists( 'Velotok_Yookassa_Gateway' ) ) {
	velotok_core_alias_payment_gateway();
} elseif ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', 'velotok_core_alias_payment_gateway', 21 );
}
