<?php
/**
 * Focused TESTEB-2 test against the real WordPress/WooCommerce accounts bridge.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/accounts-email-confirmation-wp-test.php
 *
 * The test loads includes/accounts.php and includes/accounts-wordpress.php with
 * an in-memory WordPress/WooCommerce harness. It proves that registration
 * persists a pending, unconfirmed customer, sends the confirmation email, blocks
 * login and personal-area access until the emailed token is used, and only then
 * activates the account.
 *
 * The integration file can be overridden for the PHP red-green proof:
 *   VELOTOK_ACCOUNTS_WP_INTEGRATION=/tmp/accounts-wordpress.php php ...
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/accounts.php';
require_once __DIR__ . '/helpers/accounts-wordpress-harness.php';

velotok_test_load_accounts_integration( dirname( __DIR__ ) . '/includes/accounts-wordpress.php' );

$failures = array();

/**
 * Assert a TESTEB-2 WordPress-path condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_email_confirmation_wp_assert( $condition, $message ) {
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
function velotok_email_confirmation_wp_value( $result, $key, $fallback = null ) {
	if ( is_array( $result ) && array_key_exists( $key, $result ) ) {
		return $result[ $key ];
	}

	return $fallback;
}

velotok_test_reset_store();

$email    = 'confirm-me@example.com';
$password = 'SecretPass1';
$token    = 'testeb-2-wp-confirmation-token';

$registration = velotok_core_account_register(
	$email,
	$password,
	array(
		'confirmation_token' => $token,
		'name'               => 'Покупатель',
		// A caller must not be able to skip the mandatory confirmation step.
		'email_confirmed'    => true,
		'status'             => 'active',
		'authenticated'      => true,
	)
);

velotok_email_confirmation_wp_assert( true === velotok_email_confirmation_wp_value( $registration, 'valid' ), 'Регистрация через WordPress должна быть успешной.' );
velotok_email_confirmation_wp_assert( true === velotok_email_confirmation_wp_value( $registration, 'registered' ), 'Регистрация должна создавать аккаунт.' );
velotok_email_confirmation_wp_assert( true === velotok_email_confirmation_wp_value( $registration, 'confirmation_required' ), 'Регистрация должна требовать подтверждения email.' );
velotok_email_confirmation_wp_assert( false === velotok_email_confirmation_wp_value( $registration, 'authenticated' ), 'Сразу после регистрации вход должен быть закрыт.' );
velotok_email_confirmation_wp_assert( 'pending' === velotok_email_confirmation_wp_value( $registration, 'status' ), 'Новый аккаунт должен быть в статусе pending.' );
velotok_email_confirmation_wp_assert( 'confirm_email' === velotok_email_confirmation_wp_value( $registration, 'next_step' ), 'После регистрации следующий шаг — подтверждение email.' );
velotok_email_confirmation_wp_assert( $token === velotok_email_confirmation_wp_value( $registration, 'confirmation_token' ), 'Регистрация должна выдать токен подтверждения.' );
velotok_email_confirmation_wp_assert( true === velotok_email_confirmation_wp_value( $registration, 'email_sent' ), 'Регистрация должна отправить письмо подтверждения.' );
velotok_email_confirmation_wp_assert( (int) velotok_email_confirmation_wp_value( $registration, 'user_id', 0 ) > 0, 'Аккаунт должен получить id.' );

$registered_user = velotok_email_confirmation_wp_value( $registration, 'user', array() );
velotok_email_confirmation_wp_assert( false === velotok_email_confirmation_wp_value( $registered_user, 'authenticated' ), 'Публичное состояние пользователя не должно быть authenticated.' );
velotok_email_confirmation_wp_assert( false === velotok_email_confirmation_wp_value( $registered_user, 'email_confirmed' ), 'Публичное состояние пользователя не должно быть подтверждено.' );

velotok_email_confirmation_wp_assert( 1 === count( $GLOBALS['velotok_test_users'] ), 'Пользователь должен сохраняться в WordPress.' );
velotok_email_confirmation_wp_assert( in_array( 'wc_create_new_customer', $GLOBALS['velotok_test_created_via'], true ), 'Аккаунт должен создаваться через wc_create_new_customer().' );

$user_id = (int) velotok_email_confirmation_wp_value( $registration, 'user_id', 0 );

velotok_email_confirmation_wp_assert( '0' === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_CONFIRMED_META, true ), 'До подтверждения email мета должна быть unconfirmed.' );
velotok_email_confirmation_wp_assert( 'pending' === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_STATUS_META, true ), 'До подтверждения статус должен быть pending.' );
velotok_email_confirmation_wp_assert( $token === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_TOKEN_META, true ), 'Токен подтверждения должен сохраняться в user meta.' );

velotok_email_confirmation_wp_assert( 1 === count( $GLOBALS['velotok_test_mail'] ), 'Должно отправляться ровно одно письмо подтверждения.' );
velotok_email_confirmation_wp_assert( false !== strpos( $GLOBALS['velotok_test_mail'][0]['message'], $token ), 'В письме должна быть ссылка с токеном подтверждения.' );

$pending_login = velotok_core_account_login( $email, $password );

velotok_email_confirmation_wp_assert( false === velotok_email_confirmation_wp_value( $pending_login, 'authenticated' ), 'Неподтверждённый пользователь не должен входить.' );
velotok_email_confirmation_wp_assert( false === velotok_email_confirmation_wp_value( $pending_login, 'logged_in' ), 'Неподтверждённый пользователь не должен получать сессию.' );
velotok_email_confirmation_wp_assert( 'ACCOUNT_EMAIL_NOT_CONFIRMED' === velotok_email_confirmation_wp_value( $pending_login, 'code' ), 'Вход без подтверждения должен возвращать ACCOUNT_EMAIL_NOT_CONFIRMED.' );
velotok_email_confirmation_wp_assert( 0 === (int) $GLOBALS['velotok_test_signon_calls'], 'wp_signon() не должен вызываться до подтверждения email.' );
velotok_email_confirmation_wp_assert( array() === $GLOBALS['velotok_test_auth_cookie'], 'До подтверждения auth cookie не должна ставиться.' );

$pending_access = velotok_core_account_can_access( $user_id );

velotok_email_confirmation_wp_assert( false === velotok_email_confirmation_wp_value( $pending_access, 'allowed' ), 'Неподтверждённый пользователь не должен иметь доступ к кабинету.' );
velotok_email_confirmation_wp_assert( 'ACCOUNT_ACCESS_DENIED' === velotok_email_confirmation_wp_value( $pending_access, 'code' ), 'Доступ без подтверждения должен запрещаться.' );

$invalid = velotok_core_account_confirm_email( 'wrong-token' );

velotok_email_confirmation_wp_assert( false === velotok_email_confirmation_wp_value( $invalid, 'confirmed' ), 'Неверный токен не должен подтверждать email.' );
velotok_email_confirmation_wp_assert( 'ACCOUNT_CONFIRMATION_INVALID' === velotok_email_confirmation_wp_value( $invalid, 'code' ), 'Неверный токен должен возвращать ACCOUNT_CONFIRMATION_INVALID.' );

$still_pending = velotok_core_account_login( $email, $password );
velotok_email_confirmation_wp_assert( 'ACCOUNT_EMAIL_NOT_CONFIRMED' === velotok_email_confirmation_wp_value( $still_pending, 'code' ), 'После неверного токена вход должен оставаться заблокированным.' );

$confirmed = velotok_core_account_confirm_email( $token );

velotok_email_confirmation_wp_assert( true === velotok_email_confirmation_wp_value( $confirmed, 'valid' ), 'Подтверждение email должно быть успешным.' );
velotok_email_confirmation_wp_assert( true === velotok_email_confirmation_wp_value( $confirmed, 'confirmed' ), 'Аккаунт должен быть подтверждён.' );
velotok_email_confirmation_wp_assert( true === velotok_email_confirmation_wp_value( $confirmed, 'authenticated' ), 'После подтверждения должна создаваться сессия.' );
velotok_email_confirmation_wp_assert( 'active' === velotok_email_confirmation_wp_value( $confirmed, 'status' ), 'После подтверждения статус должен быть active.' );
velotok_email_confirmation_wp_assert( '1' === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_CONFIRMED_META, true ), 'После подтверждения мета должна стать confirmed.' );
velotok_email_confirmation_wp_assert( 'active' === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_STATUS_META, true ), 'После подтверждения статус должен стать active.' );
velotok_email_confirmation_wp_assert( '' === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_TOKEN_META, true ), 'После подтверждения токен должен удаляться.' );
velotok_email_confirmation_wp_assert( in_array( $user_id, $GLOBALS['velotok_test_auth_cookie'], true ), 'После подтверждения должна ставиться auth cookie.' );

$confirmed_access = velotok_core_account_can_access( $user_id );
velotok_email_confirmation_wp_assert( true === velotok_email_confirmation_wp_value( $confirmed_access, 'allowed' ), 'Подтверждённый пользователь должен иметь доступ к кабинету.' );

$GLOBALS['velotok_test_current_user'] = 0;
$login                                = velotok_core_account_login( $email, $password );

velotok_email_confirmation_wp_assert( true === velotok_email_confirmation_wp_value( $login, 'authenticated' ), 'После подтверждения вход должен работать.' );
velotok_email_confirmation_wp_assert( 1 === (int) $GLOBALS['velotok_test_signon_calls'], 'Подтверждённый вход должен выполняться через wp_signon().' );

if ( array() !== $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

fwrite( STDOUT, 'PASS: WordPress-регистрация требует подтверждения email (TESTEB-2).' . PHP_EOL );
exit( 0 );
