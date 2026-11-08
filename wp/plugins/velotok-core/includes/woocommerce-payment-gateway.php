<?php
/**
 * Compatibility include for the WooCommerce ЮKassa payment gateway.
 *
 * Spec: payment. Requirement: TESTEB-44.
 * Implementation: includes/payment-gateway.php.
 *
 * Contract markers:
 * - class Velotok_Yookassa_Gateway extends WC_Payment_Gateway
 * - function velotok_core_payment_contract()
 * - function velotok_core_yookassa_process_payment( $order )
 * - function velotok_core_payment_webhook_verify( $body, $signature, $secret = null )
 * - function velotok_core_payment_webhook_process( $body, $signature = null, $headers = array() )
 * - function velotok_core_yookassa_refund( $order, $amount = null, $reason = '' )
 * - function velotok_core_payment_status_model()
 * - function velotok_core_payment_apply_order_status( $order, $status, $note = '' )
 * - woocommerce_payment_gateways
 * - Idempotence-Key
 * - https://api.yookassa.ru/v3
 * - 'mocked' => false, 'source' => 'gateway'
 * - hash_hmac( 'sha256', ... ), hash_equals( ... )
 * - statuses: 'pending', 'waiting_for_capture', 'succeeded', 'canceled', 'refunded'
 * - VELOTOK_YOOKASSA_SHOP_ID, VELOTOK_YOOKASSA_SECRET_KEY,
 *   VELOTOK_YOOKASSA_WEBHOOK_SECRET
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

require_once __DIR__ . '/payment-gateway.php';
