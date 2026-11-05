<?php
/**
 * Checkout redirect after guest cart transfer stub access.
 *
 * This file is the dedicated entry point for the part of the guest cart
 * scenario that sends the visitor to checkout after the guest cart has been
 * transferred into the registered user's account.
 *
 * Spec: clarify-30af92ca (requirement TESTEB-81).
 * Contract markers: mocked => true, source => stub, network => false,
 * persistent => false, redirect target => checkout.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once __DIR__ . '/guest-cart-registration.php';
