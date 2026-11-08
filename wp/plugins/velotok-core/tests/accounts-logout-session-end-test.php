<?php
/**
 * Focused test for TESTEB-5: the session ends on logout.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/accounts-logout-session-end-test.php
 *
 * Overrides used by the red-green test:
 *   VELOTOK_ACCOUNTS_STUB=/tmp/accounts.php
 *   VELOTOK_ACCOUNTS_WP_INTEGRATION=/tmp/accounts-wordpress.php
 *
 * The test proves that after a confirmed login the active session is usable,
 * and that logout ends it: the logged-in user disappears, the personal area is
 * denied again and the session token cannot be reused. It covers both the
 * in-memory contract and the WordPress/WooCommerce integration where logout
 * must go through wp_logout().
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
 * Assert a TESTEB-5 logout condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_logout_session_assert( $condition, $message ) {
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
function velotok_logout_session_value( $result, $key, $fallback = null ) {
	if ( is_array( $result ) && array_key_exists( $key, $result ) ) {
		return $result[ $key ];
	}

	return $fallback;
}

$contract = velotok_core_accounts_contract();
$logout_contract = velotok_logout_session_value( $contract, 'logout', array() );

velotok_logout_session_assert( true === velotok_logout_session_value( $logout_contract, 'enabled' ), 'Выход должен быть включён в контракте.' );
velotok_logout_session_assert( true === velotok_logout_session_value( $logout_contract, 'ends_session' ), 'Контракт должен объявлять завершение сессии при выходе.' );

/*
 * In-memory contract.
 */
velotok_core_accounts_reset();

$email    = 'testeb-5-logout@example.com';
$password = 'SecretPass1';
$token    = 'testeb-5-logout-token';

$registration = velotok_core_account_register(
	$email,
	$password,
	array( 'confirmation_token' => $token )
);

velotok_logout_session_assert( true === velotok_logout_session_value( $registration, 'registered' ), 'Подготовка: аккаунт должен регистрироваться.' );

$confirmed        = velotok_core_account_confirm_email( $token );
$confirmed_session = (string) velotok_logout_session_value( $confirmed, 'session_token', '' );

velotok_logout_session_assert( true === velotok_logout_session_value( $confirmed, 'authenticated' ), 'Подготовка: подтверждение должно создавать сессию.' );
velotok_logout_session_assert( '' !== $confirmed_session, 'Подготовка: подтверждение должно выдавать токен сессии.' );
velotok_logout_session_assert( null !== velotok_core_account_current_user( $confirmed_session ), 'Подготовка: активная сессия должна возвращать пользователя.' );
velotok_logout_session_assert( true === velotok_logout_session_value( velotok_core_account_can_access( $confirmed_session ), 'allowed' ), 'Подготовка: активная сессия должна открывать кабинет.' );

$array_logout = velotok_core_account_logout( array( 'session_token' => $confirmed_session ) );

velotok_logout_session_assert( true === velotok_logout_session_value( $array_logout, 'valid' ), 'Выход по токену в запросе должен быть успешным.' );
velotok_logout_session_assert( true === velotok_logout_session_value( $array_logout, 'logged_out' ), 'Выход должен помечать пользователя вышедшим.' );
velotok_logout_session_assert( true === velotok_logout_session_value( $array_logout, 'session_ended' ), 'Выход должен завершать сессию.' );
velotok_logout_session_assert( false === velotok_logout_session_value( $array_logout, 'authenticated' ), 'После выхода пользователь не должен быть авторизован.' );
velotok_logout_session_assert( null === velotok_logout_session_value( $array_logout, 'session_token' ), 'После выхода токен сессии не должен возвращаться.' );
velotok_logout_session_assert( null === velotok_core_account_current_user( $confirmed_session ), 'Завершённая сессия не должна возвращать пользователя.' );
velotok_logout_session_assert( false === velotok_logout_session_value( velotok_core_account_can_access( $confirmed_session ), 'allowed' ), 'После выхода кабинет должен быть закрыт.' );
velotok_logout_session_assert( false === velotok_core_account_is_logged_in( $confirmed_session ), 'После выхода is_logged_in должен быть false.' );

$second_login   = velotok_core_account_login( $email, $password );
$second_session = (string) velotok_logout_session_value( $second_login, 'session_token', '' );

velotok_logout_session_assert( true === velotok_logout_session_value( $second_login, 'authenticated' ), 'После выхода можно снова войти.' );
velotok_logout_session_assert( '' !== $second_session, 'Повторный вход должен выдавать новую сессию.' );
velotok_logout_session_assert( null !== velotok_core_account_current_user( $second_session ), 'Новая сессия должна быть активной.' );

$direct_logout = velotok_core_account_logout( $second_session );

velotok_logout_session_assert( true === velotok_logout_session_value( $direct_logout, 'logged_out' ), 'Выход по токену должен завершать сессию.' );
velotok_logout_session_assert( true === velotok_logout_session_value( $direct_logout, 'session_ended' ), 'Прямой выход должен помечать сессию завершённой.' );
velotok_logout_session_assert( null === velotok_core_account_current_user( $second_session ), 'После прямого выхода сессия не должна возвращать пользователя.' );
velotok_logout_session_assert( false === velotok_logout_session_value( velotok_core_account_can_access( $second_session ), 'allowed' ), 'После прямого выхода кабинет должен быть закрыт.' );

/*
 * Real WordPress/WooCommerce path.
 */
require_once __DIR__ . '/helpers/accounts-wordpress-harness.php';

velotok_test_load_accounts_integration( dirname( __DIR__ ) . '/includes/accounts-wordpress.php' );

velotok_test_reset_store();

$wp_email    = 'testeb-5-wp-logout@example.com';
$wp_password = 'WpSecretPass1';
$wp_token    = 'testeb-5-wp-logout-token';

$wp_registration = velotok_core_account_register(
	$wp_email,
	$wp_password,
	array( 'confirmation_token' => $wp_token )
);

$wp_user_id = (int) velotok_logout_session_value( $wp_registration, 'user_id', 0 );

velotok_logout_session_assert( $wp_user_id > 0, 'WP-подготовка: аккаунт должен получить id.' );

$wp_confirmed = velotok_core_account_confirm_email( $wp_token );

velotok_logout_session_assert( true === velotok_logout_session_value( $wp_confirmed, 'authenticated' ), 'WP-подготовка: подтверждение должно авторизовать.' );
velotok_logout_session_assert( $wp_user_id === (int) get_current_user_id(), 'WP-подготовка: текущий пользователь должен быть установлен.' );
velotok_logout_session_assert( true === is_user_logged_in(), 'WP-подготовка: пользователь должен быть в системе.' );
velotok_logout_session_assert( true === velotok_logout_session_value( velotok_core_account_can_access( $wp_user_id ), 'allowed' ), 'WP-подготовка: кабинет должен быть доступен.' );

$wp_logout = velotok_core_account_logout();

velotok_logout_session_assert( true === velotok_logout_session_value( $wp_logout, 'logged_out' ), 'WP-выход должен помечать пользователя вышедшим.' );
velotok_logout_session_assert( true === velotok_logout_session_value( $wp_logout, 'session_ended' ), 'WP-выход должен завершать сессию.' );
velotok_logout_session_assert( true === velotok_logout_session_value( $wp_logout, 'was_logged_in' ), 'WP-выход должен фиксировать, что сессия была активной.' );
velotok_logout_session_assert( false === velotok_logout_session_value( $wp_logout, 'authenticated' ), 'WP-выход не должен оставлять авторизацию.' );
velotok_logout_session_assert( 1 === (int) $GLOBALS['velotok_test_logout_calls'], 'WP-выход должен вызывать wp_logout().' );
velotok_logout_session_assert( 0 === (int) get_current_user_id(), 'WP-выход должен сбрасывать текущего пользователя.' );
velotok_logout_session_assert( false === is_user_logged_in(), 'WP-выход должен завершать вход.' );
velotok_logout_session_assert( false === velotok_logout_session_value( velotok_core_account_can_access( $wp_user_id ), 'allowed' ), 'WP: после выхода кабинет должен быть закрыт.' );

/*
 * The logout form handler (admin-post) must end the session and send the
 * visitor home.
 */
$GLOBALS['velotok_test_current_user'] = 0;
$wp_login_again                        = velotok_core_account_login( $wp_email, $wp_password );

velotok_logout_session_assert( true === velotok_logout_session_value( $wp_login_again, 'authenticated' ), 'WP-подготовка: повторный вход должен работать.' );
velotok_logout_session_assert( true === is_user_logged_in(), 'WP-подготовка: повторный вход должен установить пользователя.' );

$_REQUEST = array( 'velotok_logout_nonce' => 'nonce-velotok_logout' );

$GLOBALS['velotok_test_throw_redirect'] = true;
$logout_exception                       = null;

try {
	velotok_core_wp_handle_logout();
} catch ( RuntimeException $exception ) {
	$logout_exception = $exception;
}

$GLOBALS['velotok_test_throw_redirect'] = false;

$logout_redirect = end( $GLOBALS['velotok_test_redirects'] );

velotok_logout_session_assert( null !== $logout_exception, 'Обработчик выхода должен завершаться redirect.' );
velotok_logout_session_assert( 2 === (int) $GLOBALS['velotok_test_logout_calls'], 'Обработчик выхода должен вызывать wp_logout().' );
velotok_logout_session_assert( 0 === (int) get_current_user_id(), 'Обработчик выхода должен завершать сессию.' );
velotok_logout_session_assert( false === is_user_logged_in(), 'Обработчик выхода должен завершать вход.' );
velotok_logout_session_assert( 'https://example.test/' === (string) $logout_redirect, 'После выхода должен быть redirect на главную.' );

if ( array() !== $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

fwrite( STDOUT, 'PASS: выход завершает сессию и закрывает личный кабинет (TESTEB-5).' . PHP_EOL );
exit( 0 );
