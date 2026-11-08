<?php
/**
 * Focused contract test for TESTEB-2: registration must require email confirmation.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/accounts-email-confirmation-test.php
 *
 * With an alternate stub (used by the red-green test):
 *   VELOTOK_ACCOUNTS_STUB=/tmp/accounts.php php wp/plugins/velotok-core/tests/accounts-email-confirmation-test.php
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
 * Assert a TESTEB-2 contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_accounts_email_confirmation_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Read a value from an array result.
 *
 * @param mixed  $result   Result array.
 * @param string $key      Key.
 * @param mixed  $fallback Fallback value.
 * @return mixed
 */
function velotok_accounts_email_confirmation_value( $result, $key, $fallback = null ) {
	if ( is_array( $result ) && array_key_exists( $key, $result ) ) {
		return $result[ $key ];
	}

	return $fallback;
}

if ( ! function_exists( 'velotok_core_accounts_contract' ) ) {
	fwrite( STDERR, 'FAIL: Не объявлена функция velotok_core_accounts_contract().' . PHP_EOL );
	exit( 1 );
}

$contract     = velotok_core_accounts_contract();
$registration = velotok_accounts_email_confirmation_value( $contract, 'registration', array() );
$confirmation = velotok_accounts_email_confirmation_value( $contract, 'confirmation', array() );
$login_config = velotok_accounts_email_confirmation_value( $contract, 'login', array() );
$account      = velotok_accounts_email_confirmation_value( $contract, 'account', array() );
$requirements = velotok_accounts_email_confirmation_value( $contract, 'requirements', array() );

velotok_accounts_email_confirmation_assert(
	true === velotok_accounts_email_confirmation_value( $registration, 'email_confirmation_required' ),
	'Контракт должен требовать подтверждения email при регистрации.'
);
velotok_accounts_email_confirmation_assert(
	true === velotok_accounts_email_confirmation_value( $confirmation, 'required' ),
	'Подтверждение email должно быть обязательным.'
);
velotok_accounts_email_confirmation_assert(
	true === velotok_accounts_email_confirmation_value( $login_config, 'requires_confirmation' ),
	'Вход должен требовать подтверждения email.'
);
velotok_accounts_email_confirmation_assert(
	false === velotok_accounts_email_confirmation_value( $account, 'unconfirmed_allowed' ),
	'Неподтверждённый пользователь не должен получать доступ к кабинету.'
);
velotok_accounts_email_confirmation_assert(
	isset( $requirements['confirm_email'] ) && false !== mb_strpos( (string) $requirements['confirm_email'], 'подтверждения email' ),
	'В требованиях должна быть зафиксирована обязательность подтверждения email.'
);

if ( ! function_exists( 'velotok_core_account_register' ) ) {
	fwrite( STDERR, 'FAIL: Не объявлена функция velotok_core_account_register().' . PHP_EOL );
	exit( 1 );
}

velotok_core_accounts_reset();

$email    = 'confirm-me@example.com';
$password = 'SecretPass1';
$token    = 'testeb-2-confirmation-token';

$registered = velotok_core_account_register(
	$email,
	$password,
	array(
		'confirmation_token' => $token,
		'name'               => 'Покупатель',
	)
);

velotok_accounts_email_confirmation_assert(
	true === velotok_accounts_email_confirmation_value( $registered, 'registered' ),
	'Новый пользователь должен быть зарегистрирован.'
);
velotok_accounts_email_confirmation_assert(
	true === velotok_accounts_email_confirmation_value( $registered, 'confirmation_required' ),
	'Регистрация должна сообщать об обязательном подтверждении email.'
);
velotok_accounts_email_confirmation_assert(
	false === velotok_accounts_email_confirmation_value( $registered, 'authenticated' ),
	'До подтверждения email пользователь не должен быть авторизован.'
);
velotok_accounts_email_confirmation_assert(
	'pending' === velotok_accounts_email_confirmation_value( $registered, 'status' ),
	'До подтверждения email аккаунт должен быть в статусе pending.'
);
velotok_accounts_email_confirmation_assert(
	'confirm_email' === velotok_accounts_email_confirmation_value( $registered, 'next_step' ),
	'После регистрации следующий шаг — подтверждение email.'
);
velotok_accounts_email_confirmation_assert(
	$token === velotok_accounts_email_confirmation_value( $registered, 'confirmation_token' ),
	'Регистрация должна выдать токен подтверждения email.'
);

$state = velotok_core_accounts_state();
$key   = velotok_core_account_normalize_key( $email );

velotok_accounts_email_confirmation_assert(
	isset( $state['users'][ $key ] ) && false === $state['users'][ $key ]['email_confirmed'],
	'Сохранённый пользователь не должен быть подтверждён до перехода по ссылке.'
);

$pending_login = velotok_core_account_login( $email, $password );

velotok_accounts_email_confirmation_assert(
	false === velotok_accounts_email_confirmation_value( $pending_login, 'authenticated' ),
	'Неподтверждённый пользователь не должен входить.'
);
velotok_accounts_email_confirmation_assert(
	'ACCOUNT_EMAIL_NOT_CONFIRMED' === velotok_accounts_email_confirmation_value( $pending_login, 'code' ),
	'Вход без подтверждения должен возвращать ACCOUNT_EMAIL_NOT_CONFIRMED.'
);
velotok_accounts_email_confirmation_assert(
	null === velotok_accounts_email_confirmation_value( $pending_login, 'session_token' ),
	'Без подтверждения сессия не должна создаваться.'
);

$pending_access = velotok_core_account_can_access( $email );

velotok_accounts_email_confirmation_assert(
	false === velotok_accounts_email_confirmation_value( $pending_access, 'allowed' ),
	'Неподтверждённый пользователь не должен иметь доступа к кабинету.'
);

velotok_accounts_email_confirmation_assert(
	null === velotok_core_account_current_user( 'pending-session' ),
	'До подтверждения email не должно существовать активной сессии.'
);

$invalid_confirmation = velotok_core_account_confirm_email( 'not-a-real-token' );

velotok_accounts_email_confirmation_assert(
	false === velotok_accounts_email_confirmation_value( $invalid_confirmation, 'confirmed' ),
	'Неверный токен не должен подтверждать email.'
);
velotok_accounts_email_confirmation_assert(
	'ACCOUNT_CONFIRMATION_INVALID' === velotok_accounts_email_confirmation_value( $invalid_confirmation, 'code' ),
	'Неверный токен должен возвращать ACCOUNT_CONFIRMATION_INVALID.'
);

$still_pending = velotok_core_account_login( $email, $password );

velotok_accounts_email_confirmation_assert(
	false === velotok_accounts_email_confirmation_value( $still_pending, 'authenticated' ),
	'После неудачного подтверждения вход должен оставаться заблокированным.'
);
velotok_accounts_email_confirmation_assert(
	'ACCOUNT_EMAIL_NOT_CONFIRMED' === velotok_accounts_email_confirmation_value( $still_pending, 'code' ),
	'После неудачного подтверждения вход должен возвращать ACCOUNT_EMAIL_NOT_CONFIRMED.'
);

$confirmed = velotok_core_account_confirm_email( $token );

velotok_accounts_email_confirmation_assert(
	true === velotok_accounts_email_confirmation_value( $confirmed, 'confirmed' ),
	'Переход по ссылке должен подтверждать email.'
);
velotok_accounts_email_confirmation_assert(
	'active' === velotok_accounts_email_confirmation_value( $confirmed, 'status' ),
	'После подтверждения аккаунт должен стать active.'
);

$login = velotok_core_account_login( $email, $password );

velotok_accounts_email_confirmation_assert(
	true === velotok_accounts_email_confirmation_value( $login, 'authenticated' ),
	'Подтверждённый пользователь должен входить по email и паролю.'
);

$reused_confirmation = velotok_core_account_confirm_email( $token );

velotok_accounts_email_confirmation_assert(
	false === velotok_accounts_email_confirmation_value( $reused_confirmation, 'confirmed' ),
	'Использованный токен не должен повторно подтверждать email.'
);
velotok_accounts_email_confirmation_assert(
	'ACCOUNT_CONFIRMATION_INVALID' === velotok_accounts_email_confirmation_value( $reused_confirmation, 'code' ),
	'Повторное подтверждение должно отклоняться как недействительное.'
);

velotok_core_accounts_reset();

$bypass_email = 'bypass@example.com';
$bypass_token = 'testeb-2-bypass-token';
$bypass       = velotok_core_account_register(
	$bypass_email,
	'SecretPass2',
	array(
		'confirmation_token' => $bypass_token,
		'email_confirmed'    => true,
		'status'             => 'active',
		'authenticated'      => true,
	)
);

velotok_accounts_email_confirmation_assert(
	true === velotok_accounts_email_confirmation_value( $bypass, 'registered' ),
	'Новый пользователь должен регистрироваться.'
);
velotok_accounts_email_confirmation_assert(
	true === velotok_accounts_email_confirmation_value( $bypass, 'confirmation_required' ),
	'Переданное вызывающим кодом состояние не должно отменять обязательное подтверждение email.'
);
velotok_accounts_email_confirmation_assert(
	false === velotok_accounts_email_confirmation_value( $bypass, 'authenticated' ),
	'Регистрация не должна авторизовывать пользователя до подтверждения email.'
);
velotok_accounts_email_confirmation_assert(
	'pending' === velotok_accounts_email_confirmation_value( $bypass, 'status' ),
	'Пользователь должен оставаться в статусе pending, даже если вызывающий код передал active.'
);

$bypass_login = velotok_core_account_login( $bypass_email, 'SecretPass2' );

velotok_accounts_email_confirmation_assert(
	false === velotok_accounts_email_confirmation_value( $bypass_login, 'authenticated' ),
	'Вход должен быть заблокирован, пока email не подтверждён по токену.'
);
velotok_accounts_email_confirmation_assert(
	'ACCOUNT_EMAIL_NOT_CONFIRMED' === velotok_accounts_email_confirmation_value( $bypass_login, 'code' ),
	'Вход до подтверждения должен возвращать ACCOUNT_EMAIL_NOT_CONFIRMED.'
);

$bypass_confirm = velotok_core_account_confirm_email( $bypass_token );

velotok_accounts_email_confirmation_assert(
	true === velotok_accounts_email_confirmation_value( $bypass_confirm, 'confirmed' ),
	'Только переход по токену должен подтверждать email.'
);

if ( array() !== $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

fwrite( STDOUT, 'PASS: регистрация требует подтверждения email (TESTEB-2).' . PHP_EOL );
exit( 0 );
