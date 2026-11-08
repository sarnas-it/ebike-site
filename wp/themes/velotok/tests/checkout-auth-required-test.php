<?php
/**
 * Regression test for TESTEB-24: the checkout page is unavailable to guests.
 *
 * Standalone:
 *   php wp/themes/velotok/tests/checkout-auth-required-test.php
 *
 * The template is rendered in isolation for a logged-out visitor. Regardless of
 * the WooCommerce registration options it must not render the checkout form and
 * must offer a login link instead.
 *
 * @package Velotok
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_checkout_auth_logged_in'] = false;

class Velotok_Checkout_Auth_Template_Checkout {
	public function is_registration_enabled() {
		return true;
	}

	public function is_registration_required() {
		return false;
	}

	public function get_checkout_fields() {
		return array();
	}
}

class Velotok_Checkout_Auth_Template_WC {
	public function checkout() {
		return new Velotok_Checkout_Auth_Template_Checkout();
	}
}

function WC() {
	return new Velotok_Checkout_Auth_Template_WC();
}

function is_user_logged_in() {
	return (bool) $GLOBALS['velotok_checkout_auth_logged_in'];
}

function do_action( $hook ) {
}

function apply_filters( $hook, $value ) {
	return $value;
}

function esc_html( $value ) {
	return (string) $value;
}

function esc_html__( $value, $domain = null ) {
	return (string) $value;
}

function esc_html_e( $value, $domain = null ) {
	echo (string) $value;
}

function esc_url( $value ) {
	return (string) $value;
}

function __( $value, $domain = null ) {
	return (string) $value;
}

function wc_get_checkout_url() {
	return 'https://example.test/checkout/';
}

function wc_get_page_permalink( $page ) {
	return 'myaccount' === $page ? 'https://example.test/my-account/' : 'https://example.test/';
}

function wp_login_url( $redirect = '' ) {
	return 'https://example.test/wp-login.php';
}

ob_start();
require dirname( __DIR__ ) . '/woocommerce/checkout/form-checkout.php';
$html = ob_get_clean();

$failures = array();

function velotok_checkout_auth_template_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

velotok_checkout_auth_template_assert( false === strpos( $html, '<form' ), 'Гость не должен видеть форму оформления заказа.' );
velotok_checkout_auth_template_assert( false !== strpos( $html, 'Оформление заказа доступно только авторизованным покупателям.' ), 'Гостю должно показываться сообщение о необходимости авторизации.' );
velotok_checkout_auth_template_assert( false !== strpos( $html, 'https://example.test/my-account/' ), 'Сообщение должно вести на страницу входа в аккаунт.' );
velotok_checkout_auth_template_assert( false !== strpos( $html, 'Войти в аккаунт' ), 'Гостю должна быть доступна ссылка входа.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок шаблона оформления заказа не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: оформление заказа недоступно гостю и предлагает войти (TESTEB-24).' . PHP_EOL;
exit( 0 );
