<?php
/**
 * Regression test for TESTEB-2: registration requires email confirmation.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/accounts-registration-confirmation-required-test.php
 *
 * The test focuses only on the requirement's core invariant: no matter which
 * registration shape or options a caller uses, the account starts pending and
 * cannot log in or open the personal area until the emailed token is used.
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
 * Assert a TESTEB-2 registration condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_registration_confirmation_assert( $condition, $message ) {
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
function velotok_registration_confirmation_value( $result, $key, $fallback = null ) {
	if ( is_array( $result ) && array_key_exists( $key, $result ) ) {
		return $result[ $key ];
	}

	return $fallback;
}

$hostile_options = array(
	'email_confirmed' => true,
	'email_verified'  => true,
	'confirmed'       => true,
	'status'          => 'active',
	'account_status'  => 'active',
	'authenticated'   => true,
	'logged_in'       => true,
	'session_token'   => 'attacker-session',
	'name'            => 'Тестовый покупатель',
);

$sanitized = velotok_core_account_registration_options( $hostile_options );

foreach ( array( 'email_confirmed', 'email_verified', 'confirmed', 'status', 'account_status', 'authenticated', 'logged_in', 'session_token' ) as $forbidden ) {
	velotok_registration_confirmation_assert( ! array_key_exists( $forbidden, $sanitized ), 'Опция регистрации «' . $forbidden . '» должна отбрасываться.' );
}

velotok_registration_confirmation_assert( 'Тестовый покупатель' === velotok_registration_confirmation_value( $sanitized, 'name' ), 'Безопасные опции регистрации должны сохраняться.' );

velotok_test_reset_store();

$email    = 'registration-requires-confirmation@example.com';
$password = 'SecretPass1';
$token    = 'registration-confirmation-required-token';

/*
 * Pass the hostile state through the request-array API as well. Top-level
 * request keys and nested options must both be ignored; the only trusted
 * registration state is the pending state written by the code.
 */
$registration = velotok_core_account_register(
	array(
		'email'            => $email,
		'password'         => $password,
		'email_confirmed'  => true,
		'status'           => 'active',
		'authenticated'    => true,
		'options'          => array_merge(
			$hostile_options,
			array( 'confirmation_token' => $token )
		),
	)
);

velotok_registration_confirmation_assert( true === velotok_registration_confirmation_value( $registration, 'valid' ), 'Регистрация должна быть успешной.' );
velotok_registration_confirmation_assert( true === velotok_registration_confirmation_value( $registration, 'registered' ), 'Регистрация должна создавать аккаунт.' );
velotok_registration_confirmation_assert( true === velotok_registration_confirmation_value( $registration, 'confirmation_required' ), 'Регистрация должна требовать подтверждения email.' );
velotok_registration_confirmation_assert( false === velotok_registration_confirmation_value( $registration, 'authenticated' ), 'Регистрация не должна авторизовывать пользователя.' );
velotok_registration_confirmation_assert( 'pending' === velotok_registration_confirmation_value( $registration, 'status' ), 'Аккаунт после регистрации должен быть pending.' );
velotok_registration_confirmation_assert( 'confirm_email' === velotok_registration_confirmation_value( $registration, 'next_step' ), 'Следующий шаг после регистрации — подтверждение email.' );
velotok_registration_confirmation_assert( $token === velotok_registration_confirmation_value( $registration, 'confirmation_token' ), 'Регистрация должна выдать токен подтверждения.' );
velotok_registration_confirmation_assert( true === velotok_registration_confirmation_value( $registration, 'email_sent' ), 'Должно отправляться письмо подтверждения.' );

$user = velotok_registration_confirmation_value( $registration, 'user', array() );
velotok_registration_confirmation_assert( false === velotok_registration_confirmation_value( $user, 'email_confirmed' ), 'Публичное состояние пользователя не должно быть подтверждено.' );
velotok_registration_confirmation_assert( false === velotok_registration_confirmation_value( $user, 'authenticated' ), 'Публичное состояние пользователя не должно быть авторизовано.' );

$user_id = (int) velotok_registration_confirmation_value( $registration, 'user_id', 0 );

velotok_registration_confirmation_assert( $user_id > 0, 'Аккаунт должен получить id.' );
velotok_registration_confirmation_assert( '0' === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_CONFIRMED_META, true ), 'Мета подтверждения должна остаться unconfirmed.' );
velotok_registration_confirmation_assert( 'pending' === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_STATUS_META, true ), 'Статус в мете должен остаться pending.' );
velotok_registration_confirmation_assert( $token === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_TOKEN_META, true ), 'Токен подтверждения должен сохраняться в мете.' );
velotok_registration_confirmation_assert( 1 === count( $GLOBALS['velotok_test_mail'] ), 'Должно отправляться ровно одно письмо подтверждения.' );

$pending_login = velotok_core_account_login( $email, $password );

velotok_registration_confirmation_assert( false === velotok_registration_confirmation_value( $pending_login, 'authenticated' ), 'Без подтверждения вход должен быть заблокирован.' );
velotok_registration_confirmation_assert( 'ACCOUNT_EMAIL_NOT_CONFIRMED' === velotok_registration_confirmation_value( $pending_login, 'code' ), 'Вход без подтверждения должен возвращать ACCOUNT_EMAIL_NOT_CONFIRMED.' );
velotok_registration_confirmation_assert( 0 === (int) $GLOBALS['velotok_test_signon_calls'], 'wp_signon() не должен вызываться до подтверждения.' );

$pending_access = velotok_core_account_can_access( $user_id );

velotok_registration_confirmation_assert( false === velotok_registration_confirmation_value( $pending_access, 'allowed' ), 'Без подтверждения доступ к кабинету должен быть закрыт.' );

$confirmed = velotok_core_account_confirm_email( $token );

velotok_registration_confirmation_assert( true === velotok_registration_confirmation_value( $confirmed, 'confirmed' ), 'Переход по токену должен подтверждать email.' );
velotok_registration_confirmation_assert( '1' === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_CONFIRMED_META, true ), 'После подтверждения мета должна обновиться.' );
velotok_registration_confirmation_assert( 'active' === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_STATUS_META, true ), 'После подтверждения статус должен стать active.' );

$GLOBALS['velotok_test_current_user'] = 0;
$login                                = velotok_core_account_login( $email, $password );

velotok_registration_confirmation_assert( true === velotok_registration_confirmation_value( $login, 'authenticated' ), 'После подтверждения вход должен работать.' );

if ( array() !== $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

fwrite( STDOUT, 'PASS: регистрация требует подтверждения email независимо от переданных опций (TESTEB-2).' . PHP_EOL );
exit( 0 );
