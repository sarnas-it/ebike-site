<?php
/**
 * Focused test for TESTEB-6: an unconfirmed user cannot enter the personal area.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/accounts-unconfirmed-account-access-test.php
 *
 * Overrides used by the red-green test:
 *   VELOTOK_ACCOUNTS_STUB=/tmp/accounts.php
 *   VELOTOK_ACCOUNTS_WP_INTEGRATION=/tmp/accounts-wordpress.php
 *
 * The test proves that a pending, unconfirmed account is denied the personal
 * area even when a stale authenticated session exists, and that the WordPress
 * account page guard logs the visitor out and sends them to the confirmation
 * step. A confirmed account then regains access. It covers both the in-memory
 * contract and the WordPress/WooCommerce integration.
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
 * Assert a TESTEB-6 personal-area access condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_unconfirmed_access_assert( $condition, $message ) {
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
function velotok_unconfirmed_access_value( $result, $key, $fallback = null ) {
	if ( is_array( $result ) && array_key_exists( $key, $result ) ) {
		return $result[ $key ];
	}

	return $fallback;
}

$contract         = velotok_core_accounts_contract();
$account_contract = velotok_unconfirmed_access_value( $contract, 'account', array() );
$error_codes      = velotok_unconfirmed_access_value( $contract, 'error_codes', array() );

velotok_unconfirmed_access_assert( false === velotok_unconfirmed_access_value( $account_contract, 'unconfirmed_allowed' ), 'Контракт должен запрещать неподтверждённому пользователю доступ к кабинету.' );
velotok_unconfirmed_access_assert( 'ACCOUNT_ACCESS_DENIED' === velotok_unconfirmed_access_value( $error_codes, 'access_denied' ), 'Доступ без подтверждения должен возвращать ACCOUNT_ACCESS_DENIED.' );

/*
 * In-memory contract.
 */
velotok_core_accounts_reset();

$email    = 'testeb-6-access@example.com';
$password = 'SecretPass1';
$token    = 'testeb-6-access-token';

$registration = velotok_core_account_register(
	$email,
	$password,
	array( 'confirmation_token' => $token )
);

$user_id = (int) velotok_unconfirmed_access_value( $registration, 'user_id', 0 );

velotok_unconfirmed_access_assert( true === velotok_unconfirmed_access_value( $registration, 'registered' ), 'Подготовка: аккаунт должен регистрироваться.' );
velotok_unconfirmed_access_assert( $user_id > 0, 'Подготовка: аккаунт должен получить id.' );
velotok_unconfirmed_access_assert( false === velotok_unconfirmed_access_value( $registration, 'authenticated' ), 'Подготовка: новый аккаунт не должен быть авторизован.' );

$pending_login = velotok_core_account_login( $email, $password );

velotok_unconfirmed_access_assert( false === velotok_unconfirmed_access_value( $pending_login, 'authenticated' ), 'Неподтверждённый пользователь не должен входить.' );
velotok_unconfirmed_access_assert( 'ACCOUNT_EMAIL_NOT_CONFIRMED' === velotok_unconfirmed_access_value( $pending_login, 'code' ), 'Вход без подтверждения должен блокироваться.' );

$pending_by_email = velotok_core_account_can_access( $email );

velotok_unconfirmed_access_assert( false === velotok_unconfirmed_access_value( $pending_by_email, 'allowed' ), 'Неподтверждённый пользователь не должен иметь доступа к кабинету.' );
velotok_unconfirmed_access_assert( false === velotok_unconfirmed_access_value( $pending_by_email, 'account_access' ), 'account_access неподтверждённого пользователя должен быть закрыт.' );
velotok_unconfirmed_access_assert( false === velotok_unconfirmed_access_value( $pending_by_email, 'confirmed' ), 'Неподтверждённый пользователь не должен считаться подтверждённым.' );
velotok_unconfirmed_access_assert( 'ACCOUNT_ACCESS_DENIED' === velotok_unconfirmed_access_value( $pending_by_email, 'code' ), 'Доступ без подтверждения должен запрещаться.' );
velotok_unconfirmed_access_assert( '/account/login/' === velotok_unconfirmed_access_value( $pending_by_email, 'redirect_url' ), 'Неподтверждённого пользователя нужно отправлять на вход.' );
velotok_unconfirmed_access_assert( 'login' === velotok_unconfirmed_access_value( $pending_by_email, 'next_step' ), 'Следующим шагом должен быть вход.' );
velotok_unconfirmed_access_assert( null === velotok_unconfirmed_access_value( $pending_by_email, 'user' ), 'Запрещённый доступ не должен раскрывать пользователя.' );

/*
 * A stale authenticated session must not bypass the confirmation requirement.
 */
$pending_by_state = velotok_core_account_can_access(
	array(
		'user' => array(
			'user_id'         => $user_id,
			'email'           => $email,
			'status'          => 'pending',
			'email_confirmed' => false,
			'authenticated'   => true,
		),
	)
);

velotok_unconfirmed_access_assert( false === velotok_unconfirmed_access_value( $pending_by_state, 'allowed' ), 'Устаревшая сессия не должна открывать кабинет неподтверждённому пользователю.' );

$guest_access = velotok_core_account_can_access( '' );

velotok_unconfirmed_access_assert( false === velotok_unconfirmed_access_value( $guest_access, 'allowed' ), 'Гость не должен иметь доступа к кабинету.' );

$confirmed        = velotok_core_account_confirm_email( $token );
$confirmed_session = (string) velotok_unconfirmed_access_value( $confirmed, 'session_token', '' );

velotok_unconfirmed_access_assert( true === velotok_unconfirmed_access_value( $confirmed, 'confirmed' ), 'Подготовка: подтверждение должно активировать аккаунт.' );
velotok_unconfirmed_access_assert( '' !== $confirmed_session, 'Подготовка: подтверждение должно выдавать сессию.' );

$confirmed_access = velotok_core_account_can_access( $confirmed_session );

velotok_unconfirmed_access_assert( true === velotok_unconfirmed_access_value( $confirmed_access, 'allowed' ), 'Подтверждённый пользователь должен иметь доступ к кабинету.' );
velotok_unconfirmed_access_assert( true === velotok_unconfirmed_access_value( $confirmed_access, 'confirmed' ), 'Доступ подтверждённого пользователя должен быть подтверждён.' );

/*
 * Real WordPress/WooCommerce path.
 */
require_once __DIR__ . '/helpers/accounts-wordpress-harness.php';

velotok_test_load_accounts_integration( dirname( __DIR__ ) . '/includes/accounts-wordpress.php' );

if ( ! function_exists( 'is_account_page' ) ) {
	/**
	 * Test stub: the request targets the WooCommerce account page.
	 *
	 * @return bool
	 */
	function is_account_page() {
		return true;
	}
}

velotok_test_reset_store();

$wp_email    = 'testeb-6-wp-access@example.com';
$wp_password = 'WpSecretPass1';
$wp_token    = 'testeb-6-wp-access-token';

$wp_registration = velotok_core_account_register(
	$wp_email,
	$wp_password,
	array( 'confirmation_token' => $wp_token )
);

$wp_user_id = (int) velotok_unconfirmed_access_value( $wp_registration, 'user_id', 0 );

velotok_unconfirmed_access_assert( $wp_user_id > 0, 'WP-подготовка: аккаунт должен получить id.' );
velotok_unconfirmed_access_assert( '0' === (string) get_user_meta( $wp_user_id, VELOTOK_ACCOUNT_CONFIRMED_META, true ), 'WP-подготовка: аккаунт должен быть неподтверждённым.' );

$GLOBALS['velotok_test_current_user'] = $wp_user_id;

$wp_pending_access = velotok_core_account_can_access( $wp_user_id );

velotok_unconfirmed_access_assert( false === velotok_unconfirmed_access_value( $wp_pending_access, 'allowed' ), 'WP: неподтверждённый пользователь не должен иметь доступ к кабинету.' );
velotok_unconfirmed_access_assert( false === velotok_unconfirmed_access_value( $wp_pending_access, 'confirmed' ), 'WP: неподтверждённый пользователь не должен считаться подтверждённым.' );
velotok_unconfirmed_access_assert( 'ACCOUNT_ACCESS_DENIED' === velotok_unconfirmed_access_value( $wp_pending_access, 'code' ), 'WP: доступ без подтверждения должен запрещаться.' );
velotok_unconfirmed_access_assert( 0 === (int) velotok_unconfirmed_access_value( $wp_pending_access, 'user_id', 1 ), 'WP: запрещённый доступ не должен возвращать id пользователя.' );

$wp_current_access = velotok_core_account_can_access();

velotok_unconfirmed_access_assert( false === velotok_unconfirmed_access_value( $wp_current_access, 'allowed' ), 'WP: текущая сессия неподтверждённого пользователя не должна открывать кабинет.' );

/*
 * The account page guard must evict the unconfirmed session.
 */
$GLOBALS['velotok_test_redirects'] = array();
$logout_before                     = (int) $GLOBALS['velotok_test_logout_calls'];
$GLOBALS['velotok_test_throw_redirect'] = true;
$guard_exception                   = null;

try {
	velotok_core_wp_guard_account_page();
} catch ( RuntimeException $exception ) {
	$guard_exception = $exception;
}

$GLOBALS['velotok_test_throw_redirect'] = false;

$guard_redirect = end( $GLOBALS['velotok_test_redirects'] );

velotok_unconfirmed_access_assert( null !== $guard_exception, 'WP: охранник кабинета должен перенаправлять неподтверждённого пользователя.' );
velotok_unconfirmed_access_assert( (int) $GLOBALS['velotok_test_logout_calls'] === $logout_before + 1, 'WP: охранник кабинета должен завершать сессию неподтверждённого пользователя.' );
velotok_unconfirmed_access_assert( 0 === (int) get_current_user_id(), 'WP: после охранника кабинета пользователь должен быть разлогинен.' );
velotok_unconfirmed_access_assert( false === is_user_logged_in(), 'WP: после охранника кабинета вход должен быть завершён.' );
velotok_unconfirmed_access_assert( is_string( $guard_redirect ) && false !== strpos( $guard_redirect, 'ACCOUNT_EMAIL_NOT_CONFIRMED' ), 'WP: охранник должен отправлять к шагу подтверждения email.' );

$wp_confirmed = velotok_core_account_confirm_email( $wp_token );

velotok_unconfirmed_access_assert( true === velotok_unconfirmed_access_value( $wp_confirmed, 'authenticated' ), 'WP-подготовка: подтверждение должно авторизовать пользователя.' );

$wp_confirmed_access = velotok_core_account_can_access( $wp_user_id );

velotok_unconfirmed_access_assert( true === velotok_unconfirmed_access_value( $wp_confirmed_access, 'allowed' ), 'WP: подтверждённый пользователь должен иметь доступ к кабинету.' );
velotok_unconfirmed_access_assert( true === velotok_unconfirmed_access_value( $wp_confirmed_access, 'confirmed' ), 'WP: подтверждённый доступ должен быть подтверждён.' );

$GLOBALS['velotok_test_redirects']      = array();
$logout_before                          = (int) $GLOBALS['velotok_test_logout_calls'];
$GLOBALS['velotok_test_throw_redirect'] = true;
$confirmed_guard_exception              = null;

try {
	velotok_core_wp_guard_account_page();
} catch ( RuntimeException $exception ) {
	$confirmed_guard_exception = $exception;
}

$GLOBALS['velotok_test_throw_redirect'] = false;

velotok_unconfirmed_access_assert( null === $confirmed_guard_exception, 'WP: подтверждённого пользователя охранник не должен перенаправлять.' );
velotok_unconfirmed_access_assert( (int) $GLOBALS['velotok_test_logout_calls'] === $logout_before, 'WP: подтверждённого пользователя охранник не должен разлогинивать.' );
velotok_unconfirmed_access_assert( true === is_user_logged_in(), 'WP: подтверждённый пользователь должен оставаться в системе.' );

if ( array() !== $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

fwrite( STDOUT, 'PASS: неподтверждённый пользователь не имеет доступа к личному кабинету (TESTEB-6).' . PHP_EOL );
exit( 0 );
