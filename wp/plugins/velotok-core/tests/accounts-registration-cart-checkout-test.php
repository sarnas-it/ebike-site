<?php
/**
 * Focused test for TESTEB-4: registration finishes with cart transfer and
 * checkout redirect.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/accounts-registration-cart-checkout-test.php
 *
 * Overrides used by the red-green test:
 *   VELOTOK_ACCOUNTS_STUB=/tmp/accounts.php
 *   VELOTOK_ACCOUNTS_WP_INTEGRATION=/tmp/accounts-wordpress.php
 *
 * The test proves that a guest cart collected before registration is carried
 * through the mandatory email confirmation step, merged with the user cart,
 * cleared as a guest cart and followed by a redirect to checkout. The cart may
 * be supplied at registration or at confirmation; both shapes must complete
 * the same way. An empty cart must not redirect to checkout.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/accounts.php';
$override  = getenv( 'VELOTOK_ACCOUNTS_STUB' );

if ( is_string( $override ) && '' !== $override ) {
	$stub_path = $override;
}

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл контракта аккаунтов.' . PHP_EOL );
	exit( 1 );
}

require_once $stub_path;

$failures = array();

/**
 * Assert a TESTEB-4 condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_registration_cart_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Read a value from an array result.
 *
 * @param mixed  $result   Result.
 * @param string $key      Key.
 * @param mixed  $fallback Fallback value.
 * @return mixed
 */
function velotok_registration_cart_value( $result, $key, $fallback = null ) {
	if ( is_array( $result ) && array_key_exists( $key, $result ) ) {
		return $result[ $key ];
	}

	return $fallback;
}

$contract = velotok_core_accounts_contract();
$cart     = velotok_registration_cart_value( $contract, 'cart', array() );
$checkout = velotok_registration_cart_value( $contract, 'checkout', array() );

velotok_registration_cart_assert( true === velotok_registration_cart_value( $cart, 'transfer_on_registration' ), 'Контракт должен переносить корзину при регистрации.' );
velotok_registration_cart_assert( 'merge_quantities' === velotok_registration_cart_value( $cart, 'merge_strategy' ), 'Перенос должен объединять количества товаров.' );
velotok_registration_cart_assert( true === velotok_registration_cart_value( $cart, 'clear_guest_after_transfer' ), 'Гостевая корзина должна очищаться после переноса.' );
velotok_registration_cart_assert( true === velotok_registration_cart_value( $checkout, 'after_transfer' ), 'Переход к оформлению должен выполняться после переноса.' );
velotok_registration_cart_assert( 'checkout' === velotok_registration_cart_value( $checkout, 'target' ), 'Целью перехода должно быть оформление.' );
velotok_registration_cart_assert( '/checkout/' === velotok_registration_cart_value( $checkout, 'target_url' ), 'URL оформления должен быть /checkout/.' );

/*
 * In-memory contract.
 */
velotok_core_accounts_reset();

$stub_email_a = 'testeb-4-confirm-cart@example.com';
$stub_token_a = 'testeb-4-stub-confirm-cart-token';

velotok_core_account_register( $stub_email_a, 'SecretPass1', array( 'confirmation_token' => $stub_token_a ) );

$guest_cart_a  = velotok_core_cart_add_item( 'riviera', 2 );
$user_cart_a   = velotok_core_cart_add_item( 'riviera', 1 );
$confirmed_a   = velotok_core_account_confirm_email(
	$stub_token_a,
	'',
	array(
		'guest_cart' => $guest_cart_a,
		'user_cart'  => $user_cart_a,
	)
);
$transfer_a    = velotok_registration_cart_value( $confirmed_a, 'transfer' );

velotok_registration_cart_assert( true === velotok_registration_cart_value( $confirmed_a, 'confirmed' ), 'Подтверждение с корзиной должно завершать регистрацию.' );
velotok_registration_cart_assert( is_array( $transfer_a ), 'Подтверждение с корзиной должно выполнять перенос.' );
velotok_registration_cart_assert( 3 === velotok_core_cart_count( $transfer_a ), 'Количества гостевой и пользовательской корзины должны суммироваться.' );
velotok_registration_cart_assert( 1 === velotok_core_cart_unique_count( $transfer_a ), 'Одинаковые товары должны объединяться в одну позицию.' );
velotok_registration_cart_assert( false === velotok_registration_cart_value( $transfer_a, 'guest' ), 'После переноса корзина не должна быть гостевой.' );
velotok_registration_cart_assert( true === velotok_registration_cart_value( $transfer_a, 'authenticated' ), 'После переноса корзина должна принадлежать аккаунту.' );
velotok_registration_cart_assert( true === velotok_registration_cart_value( $transfer_a, 'guest_cart_cleared' ), 'Гостевая корзина должна очищаться после переноса.' );
velotok_registration_cart_assert( 'merge_quantities' === velotok_registration_cart_value( $transfer_a, 'transfer_strategy' ), 'Стратегия переноса должна браться из контракта.' );
velotok_registration_cart_assert( true === velotok_registration_cart_value( $confirmed_a, 'redirected' ), 'После переноса должен выполняться переход к оформлению.' );
velotok_registration_cart_assert( '/checkout/' === velotok_registration_cart_value( $confirmed_a, 'redirect_url' ), 'Переход должен вести на /checkout/.' );
velotok_registration_cart_assert( '/checkout/' === velotok_registration_cart_value( $confirmed_a, 'checkout_url' ), 'Результат должен содержать URL оформления.' );
velotok_registration_cart_assert( 'checkout' === velotok_registration_cart_value( $confirmed_a, 'next_step' ), 'Следующим шагом должно быть оформление.' );

$stub_email_b = 'testeb-4-register-cart@example.com';
$stub_token_b = 'testeb-4-stub-register-cart-token';
$guest_cart_b = velotok_core_cart_add_item( 'street-7', 2 );

$registration_b = velotok_core_account_register(
	$stub_email_b,
	'SecretPass1',
	array(
		'confirmation_token' => $stub_token_b,
		'guest_cart'         => $guest_cart_b,
	)
);

velotok_registration_cart_assert( true === velotok_registration_cart_value( $registration_b, 'registered' ), 'Регистрация с корзиной должна создавать аккаунт.' );
velotok_registration_cart_assert( false === velotok_registration_cart_value( $registration_b, 'authenticated' ), 'До подтверждения email аккаунт не должен быть авторизован.' );
velotok_registration_cart_assert( 'confirm_email' === velotok_registration_cart_value( $registration_b, 'next_step' ), 'Регистрация с корзиной должна ждать подтверждения email.' );

$registered_user_b = velotok_registration_cart_value( $registration_b, 'user', array() );
velotok_registration_cart_assert( ! isset( $registered_user_b['pending_guest_cart'] ), 'Снимок корзины не должен попадать в публичное состояние пользователя.' );

$confirmed_b = velotok_core_account_confirm_email( $stub_token_b );
$transfer_b  = velotok_registration_cart_value( $confirmed_b, 'transfer' );

velotok_registration_cart_assert( is_array( $transfer_b ), 'Корзина, переданная при регистрации, должна переноситься после подтверждения.' );
velotok_registration_cart_assert( 2 === velotok_core_cart_count( $transfer_b ), 'Товары из корзины регистрации должны сохраниться.' );
velotok_registration_cart_assert( true === velotok_registration_cart_value( $transfer_b, 'guest_cart_cleared' ), 'Корзина регистрации должна очищаться после переноса.' );
velotok_registration_cart_assert( '/checkout/' === velotok_registration_cart_value( $confirmed_b, 'redirect_url' ), 'Подтверждение после регистрации с корзиной должно вести на /checkout/.' );
velotok_registration_cart_assert( 'checkout' === velotok_registration_cart_value( $confirmed_b, 'next_step' ), 'После подтверждения с корзиной следующий шаг — checkout.' );

$stub_email_c = 'testeb-4-empty-cart@example.com';
$stub_token_c = 'testeb-4-stub-empty-cart-token';

velotok_core_account_register(
	$stub_email_c,
	'SecretPass1',
	array(
		'confirmation_token' => $stub_token_c,
		'guest_cart'         => velotok_core_cart_guest(),
	)
);

$confirmed_c = velotok_core_account_confirm_email( $stub_token_c );

velotok_registration_cart_assert( true === velotok_registration_cart_value( $confirmed_c, 'confirmed' ), 'Пустая корзина не должна мешать подтверждению.' );
velotok_registration_cart_assert( false === velotok_registration_cart_value( $confirmed_c, 'redirected' ), 'Пустая корзина не должна переводить к оформлению.' );
velotok_registration_cart_assert( 'CART_EMPTY' === velotok_registration_cart_value( $confirmed_c, 'code' ), 'Пустая корзина должна возвращать CART_EMPTY.' );
velotok_registration_cart_assert( '' === velotok_registration_cart_value( $confirmed_c, 'redirect_url' ), 'Без товаров URL оформления должен быть пустым.' );
velotok_registration_cart_assert( 'account' === velotok_registration_cart_value( $confirmed_c, 'next_step' ), 'Без товаров следующим шагом должен быть аккаунт.' );

/*
 * Real WordPress/WooCommerce path.
 */
require_once __DIR__ . '/helpers/accounts-wordpress-harness.php';

velotok_test_load_accounts_integration( dirname( __DIR__ ) . '/includes/accounts-wordpress.php' );

velotok_test_reset_store();

$wp_email_d = 'testeb-4-wp-register-cart@example.com';
$wp_token_d = 'testeb-4-wp-register-cart-token';
$wp_guest_d = velotok_core_cart_add_item( 'riviera', 2 );
$wp_guest_d = velotok_core_cart_add_item( $wp_guest_d, 'street-7', 1 );

$wp_registration_d = velotok_core_account_register(
	$wp_email_d,
	'SecretPass1',
	array(
		'confirmation_token' => $wp_token_d,
		'guest_cart'         => $wp_guest_d,
	)
);

$wp_user_id_d = (int) velotok_registration_cart_value( $wp_registration_d, 'user_id', 0 );

velotok_registration_cart_assert( true === velotok_registration_cart_value( $wp_registration_d, 'registered' ), 'WP: регистрация с корзиной должна создавать аккаунт.' );
velotok_registration_cart_assert( $wp_user_id_d > 0, 'WP: аккаунт должен получить id.' );
velotok_registration_cart_assert( is_array( get_user_meta( $wp_user_id_d, VELOTOK_ACCOUNT_PENDING_CART_META, true ) ), 'WP: корзина регистрации должна сохраняться до подтверждения.' );

$GLOBALS['velotok_test_cart_items'] = 0;
$wp_confirmed_d                     = velotok_core_account_confirm_email( $wp_token_d );
$wp_transfer_d                      = velotok_registration_cart_value( $wp_confirmed_d, 'transfer' );

velotok_registration_cart_assert( true === velotok_registration_cart_value( $wp_confirmed_d, 'confirmed' ), 'WP: подтверждение должно завершать регистрацию.' );
velotok_registration_cart_assert( true === velotok_registration_cart_value( $wp_transfer_d, 'has_items' ), 'WP: перенос должен видеть товары корзины регистрации.' );
velotok_registration_cart_assert( 3 === (int) velotok_registration_cart_value( $wp_transfer_d, 'item_count' ), 'WP: все товары корзины регистрации должны переноситься.' );
velotok_registration_cart_assert( true === velotok_registration_cart_value( $wp_transfer_d, 'guest_cart_transferred' ), 'WP: гостевая корзина должна переноситься.' );
velotok_registration_cart_assert( true === velotok_registration_cart_value( $wp_transfer_d, 'guest_cart_cleared' ), 'WP: гостевая корзина должна очищаться.' );
velotok_registration_cart_assert( true === velotok_registration_cart_value( $wp_confirmed_d, 'redirected' ), 'WP: после переноса должен быть переход к оформлению.' );
velotok_registration_cart_assert( 'https://example.test/checkout/' === velotok_registration_cart_value( $wp_confirmed_d, 'redirect_url' ), 'WP: переход должен вести на URL оформления WooCommerce.' );
velotok_registration_cart_assert( 'checkout' === velotok_registration_cart_value( $wp_confirmed_d, 'next_step' ), 'WP: следующим шагом должно быть оформление.' );
velotok_registration_cart_assert( '' === (string) get_user_meta( $wp_user_id_d, VELOTOK_ACCOUNT_PENDING_CART_META, true ), 'WP: снимок корзины должен удаляться после переноса.' );

$wp_email_e = 'testeb-4-wp-confirm-cart@example.com';
$wp_token_e = 'testeb-4-wp-confirm-cart-token';

velotok_core_account_register( $wp_email_e, 'SecretPass1', array( 'confirmation_token' => $wp_token_e ) );

$GLOBALS['velotok_test_cart_items'] = 0;
$wp_confirmed_e                     = velotok_core_account_confirm_email(
	$wp_token_e,
	'',
	array( 'guest_cart' => velotok_core_cart_add_item( 'riviera', 1 ) )
);
$wp_transfer_e = velotok_registration_cart_value( $wp_confirmed_e, 'transfer' );

velotok_registration_cart_assert( true === velotok_registration_cart_value( $wp_transfer_e, 'has_items' ), 'WP: корзина, переданная при подтверждении, должна переноситься.' );
velotok_registration_cart_assert( 1 === (int) velotok_registration_cart_value( $wp_transfer_e, 'item_count' ), 'WP: количество товаров должно сохраниться.' );
velotok_registration_cart_assert( 'https://example.test/checkout/' === velotok_registration_cart_value( $wp_confirmed_e, 'redirect_url' ), 'WP: подтверждение с корзиной должно вести на checkout.' );

$wp_email_f = 'testeb-4-wp-empty-cart@example.com';
$wp_token_f = 'testeb-4-wp-empty-cart-token';

velotok_core_account_register( $wp_email_f, 'SecretPass1', array( 'confirmation_token' => $wp_token_f ) );

$GLOBALS['velotok_test_cart_items'] = 0;
$wp_confirmed_f                     = velotok_core_account_confirm_email( $wp_token_f );

velotok_registration_cart_assert( true === velotok_registration_cart_value( $wp_confirmed_f, 'confirmed' ), 'WP: пустая корзина не должна мешать подтверждению.' );
velotok_registration_cart_assert( false === velotok_registration_cart_value( $wp_confirmed_f, 'redirected' ), 'WP: пустая корзина не должна переводить к оформлению.' );
velotok_registration_cart_assert( 'CART_EMPTY' === velotok_registration_cart_value( $wp_confirmed_f, 'code' ), 'WP: пустая корзина должна возвращать CART_EMPTY.' );
velotok_registration_cart_assert( '' === velotok_registration_cart_value( $wp_confirmed_f, 'redirect_url' ), 'WP: без товаров URL оформления должен оставаться пустым.' );
velotok_registration_cart_assert( 'account' === velotok_registration_cart_value( $wp_confirmed_f, 'next_step' ), 'WP: без товаров следующим шагом должен быть аккаунт.' );

if ( array() !== $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

fwrite( STDOUT, 'PASS: регистрация завершается переносом корзины и переходом к оформлению (TESTEB-4).' . PHP_EOL );
exit( 0 );
