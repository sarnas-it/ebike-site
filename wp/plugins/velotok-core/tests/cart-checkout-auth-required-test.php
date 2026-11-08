<?php
/**
 * Regression test for TESTEB-13 / spec cart: a purchase requires authorization.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/cart-checkout-auth-required-test.php
 *
 * The test stubs the WooCommerce surface used by the cart integration and
 * proves that:
 * - the cart contract declares that checkout requires an authenticated buyer
 *   and that guest checkout is disabled;
 * - a guest is rejected with CHECKOUT_AUTH_REQUIRED and an explanation;
 * - the WooCommerce checkout process hook blocks the guest and shows a notice;
 * - an authenticated buyer passes the same checks.
 *
 * The test fails when the cart integration stops requiring authorization for a
 * purchase.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_cart_auth_test_logged_in'] = false;
$GLOBALS['velotok_cart_auth_test_notices']   = array();
$GLOBALS['velotok_cart_auth_test_actions']   = array();
$GLOBALS['velotok_cart_auth_test_filters']   = array();

/**
 * Current login state.
 *
 * @return bool
 */
function is_user_logged_in() {
	return (bool) $GLOBALS['velotok_cart_auth_test_logged_in'];
}

/**
 * Current user id.
 *
 * @return int
 */
function get_current_user_id() {
	return $GLOBALS['velotok_cart_auth_test_logged_in'] ? 7 : 0;
}

/**
 * Record an action.
 *
 * @param string $hook     Hook.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $args     Arguments.
 * @return bool
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	unset( $priority, $args );

	$GLOBALS['velotok_cart_auth_test_actions'][ $hook ][] = $callback;

	return true;
}

/**
 * Record a filter.
 *
 * @param string $hook     Hook.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $args     Arguments.
 * @return bool
 */
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	unset( $priority, $args );

	$GLOBALS['velotok_cart_auth_test_filters'][ $hook ][] = $callback;

	return true;
}

/**
 * Apply the stubbed filters registered for a hook.
 *
 * @param string $hook  Hook.
 * @param mixed  $value Value.
 * @param mixed  ...$args Extra arguments.
 * @return mixed
 */
function apply_filters( $hook, $value, ...$args ) {
	unset( $args );

	if ( empty( $GLOBALS['velotok_cart_auth_test_filters'][ $hook ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['velotok_cart_auth_test_filters'][ $hook ] as $callback ) {
		if ( is_callable( $callback ) ) {
			$value = call_user_func( $callback, $value );
		}
	}

	return $value;
}

/**
 * Record a WooCommerce notice.
 *
 * @param string $message Message.
 * @param string $type    Type.
 * @return void
 */
function wc_add_notice( $message, $type = 'success' ) {
	$GLOBALS['velotok_cart_auth_test_notices'][] = array(
		'message' => (string) $message,
		'type'    => (string) $type,
	);
}

$module_path = getenv( 'VELOTOK_CART_WC_INTEGRATION' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__ ) . '/includes/cart-woocommerce.php';
}

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найдена интеграция корзины с WooCommerce.' . PHP_EOL );
	exit( 1 );
}

define( 'VELOTOK_CART_SKIP_WC_INTEGRATION', true );

require_once dirname( __DIR__ ) . '/includes/cart.php';
require_once $module_path;

$failures = array();

/**
 * Assert a checkout authorization condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_cart_auth_test_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = velotok_core_cart_woocommerce_contract();

velotok_cart_auth_test_assert( isset( $contract['checkout']['requires_auth'] ) && true === $contract['checkout']['requires_auth'], 'Контракт корзины должен требовать авторизацию для покупки.' );
velotok_cart_auth_test_assert( isset( $contract['checkout']['guest_checkout'] ) && false === $contract['checkout']['guest_checkout'], 'Гостевая покупка должна быть запрещена.' );
velotok_cart_auth_test_assert( isset( $contract['checkout']['auth_error'] ) && 'CHECKOUT_AUTH_REQUIRED' === $contract['checkout']['auth_error'], 'Контракт должен возвращать CHECKOUT_AUTH_REQUIRED.' );
velotok_cart_auth_test_assert( isset( $contract['error_codes']['auth_required'] ) && 'CHECKOUT_AUTH_REQUIRED' === $contract['error_codes']['auth_required'], 'Код ошибки авторизации должен быть CHECKOUT_AUTH_REQUIRED.' );
velotok_cart_auth_test_assert( isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-13', $contract['requirement_ids'], true ), 'Контракт корзины должен ссылаться на TESTEB-13.' );

$cart_contract = velotok_core_cart_contract();

velotok_cart_auth_test_assert( isset( $cart_contract['checkout']['requires_auth'] ) && true === $cart_contract['checkout']['requires_auth'], 'Контракт cart.php должен требовать авторизацию для покупки.' );
velotok_cart_auth_test_assert( isset( $cart_contract['requirement_ids'] ) && in_array( 'TESTEB-13', $cart_contract['requirement_ids'], true ), 'Контракт cart.php должен ссылаться на TESTEB-13.' );

velotok_cart_auth_test_assert( true === velotok_core_wc_cart_requires_authentication_for_checkout(), 'Покупка должна требовать авторизации.' );

velotok_cart_auth_test_assert( ! empty( $GLOBALS['velotok_cart_auth_test_actions']['woocommerce_checkout_process'] ) && in_array( 'velotok_core_wc_cart_validate_checkout_auth', $GLOBALS['velotok_cart_auth_test_actions']['woocommerce_checkout_process'], true ), 'Проверка авторизации должна подключаться к оформлению заказа WooCommerce.' );

$GLOBALS['velotok_cart_auth_test_logged_in'] = false;
$GLOBALS['velotok_cart_auth_test_notices']   = array();

$guest = velotok_core_wc_cart_checkout_auth_status();

velotok_cart_auth_test_assert( false === $guest['allowed'], 'Гость не должен проходить к покупке.' );
velotok_cart_auth_test_assert( true === $guest['requires_auth'], 'Проверка покупки должна требовать авторизацию.' );
velotok_cart_auth_test_assert( false === $guest['authenticated'], 'Гость не должен считаться авторизованным.' );
velotok_cart_auth_test_assert( false === $guest['guest_checkout'], 'Гостевая покупка должна быть недоступна.' );
velotok_cart_auth_test_assert( 'CHECKOUT_AUTH_REQUIRED' === $guest['code'], 'Гостю должен возвращаться CHECKOUT_AUTH_REQUIRED.' );
velotok_cart_auth_test_assert( 'CHECKOUT_AUTH_REQUIRED' === $guest['error'], 'Ошибка гостя должна быть CHECKOUT_AUTH_REQUIRED.' );
velotok_cart_auth_test_assert( '' !== $guest['message'], 'Гостю должно показываться сообщение о необходимости входа.' );

velotok_cart_auth_test_assert( false === velotok_core_wc_cart_validate_checkout_auth(), 'Проверка оформления должна блокировать гостя.' );
velotok_cart_auth_test_assert( 1 === count( $GLOBALS['velotok_cart_auth_test_notices'] ), 'Гостю должно показываться одно сообщение о необходимости входа.' );
velotok_cart_auth_test_assert( ! empty( $GLOBALS['velotok_cart_auth_test_notices'][0]['type'] ) && 'error' === $GLOBALS['velotok_cart_auth_test_notices'][0]['type'], 'Сообщение гостю должно быть ошибкой.' );
velotok_cart_auth_test_assert( $guest['message'] === $GLOBALS['velotok_cart_auth_test_notices'][0]['message'], 'Сообщение должно объяснять необходимость входа.' );

$GLOBALS['velotok_cart_auth_test_logged_in'] = true;
$GLOBALS['velotok_cart_auth_test_notices']   = array();

$buyer = velotok_core_wc_cart_checkout_auth_status();

velotok_cart_auth_test_assert( true === $buyer['allowed'], 'Авторизованный покупатель должен проходить к покупке.' );
velotok_cart_auth_test_assert( true === $buyer['authenticated'], 'Авторизованный покупатель должен считаться авторизованным.' );
velotok_cart_auth_test_assert( null === $buyer['code'], 'Авторизованному покупателю не нужен код ошибки.' );
velotok_cart_auth_test_assert( '' === $buyer['message'], 'Авторизованному покупателю не нужно сообщение о входе.' );
velotok_cart_auth_test_assert( true === velotok_core_wc_cart_validate_checkout_auth(), 'Проверка оформления должна пропускать авторизованного покупателя.' );
velotok_cart_auth_test_assert( array() === $GLOBALS['velotok_cart_auth_test_notices'], 'Авторизованному покупателю не должно показываться сообщение об ошибке.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок авторизации покупки не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: покупка требует авторизации (TESTEB-13).' . PHP_EOL;
exit( 0 );
