<?php
/**
 * Contract test for the mocked checkout redirect after the guest cart transfer.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/guest-cart-checkout-redirect-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/guest-cart-checkout-redirect-contract-test.php
 *
 * The test intentionally fails when the redirect stub is removed, stops being
 * marked as mocked or no longer sends the visitor to checkout after the guest
 * cart has been transferred into the registered user's account (TESTEB-81).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/guest-cart-checkout-redirect.php';
$override  = getenv( 'VELOTOK_GUEST_CART_REDIRECT_STUB' );

if ( ! is_string( $override ) || '' === $override ) {
	$override = getenv( 'VELOTOK_GUEST_CART_STUB' );
}

if ( is_string( $override ) && '' !== $override ) {
	$stub_path = $override;
}

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки перехода к оформлению.' . PHP_EOL );
	exit( 1 );
}

require_once $stub_path;

$failures = array();

/**
 * Assert a checkout redirect contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_guest_cart_redirect_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_core_guest_cart_checkout_redirect_contract' ) ) {
	velotok_guest_cart_redirect_assert( false, 'Отсутствует контракт перехода к оформлению.' );
} else {
	$contract = velotok_core_guest_cart_checkout_redirect_contract();
	$redirect = isset( $contract['redirect'] ) ? $contract['redirect'] : array();

	velotok_guest_cart_redirect_assert( true === $contract['mocked'], 'Контракт перехода не помечен mocked: true.' );
	velotok_guest_cart_redirect_assert( 'stub' === $contract['source'], 'Источником контракта перехода должна быть заглушка.' );
	velotok_guest_cart_redirect_assert( 'clarify-30af92ca' === $contract['spec'], 'Контракт перехода ссылается на неверную спеку.' );
	velotok_guest_cart_redirect_assert( 'TESTEB-81' === $contract['current_task'], 'Текущая задача контракта должна быть TESTEB-81.' );
	velotok_guest_cart_redirect_assert( 'TESTEB-81' === $contract['requirement_id'], 'Требование контракта должно быть TESTEB-81.' );
	velotok_guest_cart_redirect_assert( 'TESTEB-81' === $contract['redirect_requirement'], 'Не зафиксировано требование перехода TESTEB-81.' );
	velotok_guest_cart_redirect_assert( 'TESTEB-81' === $contract['requirement_tickets']['redirect_to_checkout'], 'Переход не привязан к задаче TESTEB-81.' );
	velotok_guest_cart_redirect_assert( true === $contract['redirects_to_checkout'], 'Не включён переход к оформлению.' );
	velotok_guest_cart_redirect_assert( true === $redirect['enabled'], 'Переход к оформлению должен быть включён.' );
	velotok_guest_cart_redirect_assert( true === $redirect['after_transfer'], 'Переход должен выполняться после переноса.' );
	velotok_guest_cart_redirect_assert( true === $redirect['requires_transfer'], 'Переход должен требовать переноса корзины.' );
	velotok_guest_cart_redirect_assert( 'TESTEB-81' === $redirect['requirement'], 'Секция перехода не привязана к TESTEB-81.' );
	velotok_guest_cart_redirect_assert( 'redirect_to_checkout' === $redirect['operation'], 'Неверная операция перехода.' );
	velotok_guest_cart_redirect_assert( 'checkout' === $redirect['target'], 'Целью перехода должно быть оформление заказа.' );
	velotok_guest_cart_redirect_assert( '/checkout/' === $redirect['target_url'], 'URL оформления заказа должен быть /checkout/.' );
	velotok_guest_cart_redirect_assert( 'checkout' === $redirect['next_step'], 'Следующим шагом должно быть оформление заказа.' );
	velotok_guest_cart_redirect_assert( true === $redirect['mocked'], 'Секция перехода не помечена mocked: true.' );
	velotok_guest_cart_redirect_assert( 'stub' === $redirect['source'], 'Секция перехода должна быть из заглушки.' );
}

if ( function_exists( 'velotok_core_guest_cart_redirect_to_checkout' ) ) {
	$guest = velotok_core_guest_cart_add( velotok_core_guest_cart_create(), 'riviera', 1 );

	$before_transfer = velotok_core_guest_cart_redirect_to_checkout( $guest );

	velotok_guest_cart_redirect_assert( false === $before_transfer['valid'], 'До переноса гостевая корзина не должна переводить к оформлению.' );
	velotok_guest_cart_redirect_assert( 'GUEST_CART_TRANSFER_REQUIRED' === $before_transfer['code'], 'До переноса ожидается GUEST_CART_TRANSFER_REQUIRED.' );

	$user  = velotok_core_guest_cart_register( array( 'email' => 'redirect@example.com' ), $guest );
	$cart  = velotok_core_guest_cart_transfer( $guest, array(), $user['user'] );

	$redirect = velotok_core_guest_cart_redirect_to_checkout( $cart, $user['user'] );

	velotok_guest_cart_redirect_assert( true === $redirect['valid'], 'После переноса переход должен быть разрешён.' );
	velotok_guest_cart_redirect_assert( true === $redirect['redirected'], 'После переноса переход должен выполняться.' );
	velotok_guest_cart_redirect_assert( '/checkout/' === $redirect['redirect_url'], 'Переход должен вести на /checkout/.' );
	velotok_guest_cart_redirect_assert( 'checkout' === $redirect['next_step'], 'Следующим шагом должно быть оформление заказа.' );
	velotok_guest_cart_redirect_assert( true === $redirect['after_transfer'], 'Переход должен быть помечен как выполняемый после переноса.' );
	velotok_guest_cart_redirect_assert( false === $redirect['guest'], 'После переноса корзина не должна быть гостевой.' );
	velotok_guest_cart_redirect_assert( true === $redirect['mocked'] && 'stub' === $redirect['source'], 'Переход должен быть mocked и stub.' );

	$empty = velotok_core_guest_cart_redirect_to_checkout( velotok_core_guest_cart_create(), $user['user'] );

	velotok_guest_cart_redirect_assert( false === $empty['valid'], 'Пустая корзина не должна переводить к оформлению.' );
	velotok_guest_cart_redirect_assert( 'CART_EMPTY' === $empty['code'], 'Пустая корзина должна давать CART_EMPTY.' );
}

$source   = (string) file_get_contents( $stub_path );
$forbidden = array(
	'get' . 'env(',
	'apache_get' . 'env(',
	'$_' . 'ENV',
	'$_' . 'SERVER',
	'file_get' . '_contents(',
	'fopen(',
	'curl_' . 'init(',
	'wp_remote_' . 'get(',
	'wp_remote_' . 'post(',
	'$' . 'wpdb',
);

foreach ( $forbidden as $needle ) {
	velotok_guest_cart_redirect_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт перехода к оформлению после переноса корзины на месте.' . PHP_EOL;
