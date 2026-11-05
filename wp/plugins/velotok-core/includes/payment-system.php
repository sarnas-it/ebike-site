<?php
/**
 * Payment system integration compatibility include.
 *
 * Spec: ecommerce-store. Requirement: TESTEB-153. Open question: clarify-096bb945.
 *
 * The implementation lives in includes/payment-integration.php. This file is a
 * stable compatibility include path for payment system callers.
 *
 * Contract markers:
 * - functions: velotok_core_payment_integration_contract,
 *   velotok_core_payment_integration_stub_contract,
 *   velotok_core_payment_system_contract,
 *   velotok_core_payment_integration_create,
 *   velotok_core_payment_integration_process,
 *   velotok_core_payment_integration_status,
 *   velotok_core_payment_integration_confirm,
 *   velotok_core_payment_integration_is_paid,
 *   velotok_core_payment_integration_validate,
 *   velotok_core_payment_integration_open_questions;
 * - 'mocked' => true, 'source' => 'stub';
 * - provider: 'yookassa' (ЮKassa), 'currency' => 'RUB',
 *   'future_source' => 'env', 'env', 'statuses', 'open_questions';
 * - statuses: 'pending', 'waiting_for_capture', 'succeeded', 'canceled';
 * - errors: 'PAYMENT_PROVIDER_UNSUPPORTED', 'PAYMENT_AMOUNT_INVALID',
 *   'PAYMENT_CURRENCY_UNSUPPORTED', 'PAYMENT_STATUS_INVALID'.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once __DIR__ . '/payment-integration.php';
