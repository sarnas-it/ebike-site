<?php
/**
 * Delivery method stub contract.
 *
 * Delivery choices (Russian Post, CDEK and another logistics provider) live in
 * the mocked checkout contract. This file keeps a stable include path for
 * consumers that treat delivery separately.
 *
 * Spec: clarify-096bb945.
 *
 * @package Velotok_Core
 */

require_once __DIR__ . '/checkout-contracts.php';
