<?php
/**
 * Contract test for accounts: email/password registration, confirmation and login.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/accounts-contract-test.php
 *
 * With an alternate stub (used by the red-green test):
 *   VELOTOK_ACCOUNTS_STUB=/tmp/accounts.php php wp/plugins/velotok-core/tests/accounts-contract-test.php
 *
 * The test fails when registration stops requiring email confirmation, creates
 * a duplicate for an existing email, lets an unconfirmed user into the personal
 * area, loses the guest cart on registration/login or does not end the session
 * on logout.
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
 * Assert an accounts contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_accounts_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Read a value from an array result.
 *
 * @param mixed  $result Result array.
 * @param string $key    Key.
 * @param mixed  $fallback Fallback value.
 * @return mixed
 */
function velotok_accounts_value( $result, $key, $fallback = null ) {
	if ( is_array( $result ) && array_key_exists( $key, $result ) ) {
		return $result[ $key ];
	}

	return $fallback;
}

if ( ! function_exists( 'velotok_core_accounts_contract' ) ) {
	fwrite( STDERR, 'FAIL: Не объявлена функция velotok_core_accounts_contract().' . PHP_EOL );
	exit( 1 );
}

$contract = velotok_core_accounts_contract();

velotok_accounts_assert( is_array( $contract ), 'Контракт аккаунтов должен быть массивом.' );
velotok_accounts_assert( 'accounts' === velotok_accounts_value( $contract, 'spec' ), 'Контракт должен ссылаться на спеку accounts.' );
velotok_accounts_assert( 'TESTEB-1' === velotok_accounts_value( $contract, 'requirement' ), 'Контракт должен ссылаться на требование TESTEB-1.' );
velotok_accounts_assert( false === velotok_accounts_value( $contract, 'mocked' ), 'Контракт должен быть помечен mocked => false: аккаунты сохраняются в WordPress.' );
velotok_accounts_assert( true === velotok_accounts_value( $contract, 'persistent' ), 'Контракт должен объявлять постоянное хранение пользователей.' );
velotok_accounts_assert( true === velotok_accounts_value( velotok_accounts_value( $contract, 'registration', array() ), 'email_confirmation_required' ), 'Регистрация должна требовать подтверждения email.' );
velotok_accounts_assert( true === velotok_accounts_value( velotok_accounts_value( $contract, 'registration', array() ), 'requires_password' ), 'Регистрация должна требовать пароль.' );
velotok_accounts_assert( 'login' === velotok_accounts_value( velotok_accounts_value( $contract, 'registration', array() ), 'existing_email_strategy' ), 'Существующий email должен вести ко входу.' );
velotok_accounts_assert( true === velotok_accounts_value( velotok_accounts_value( $contract, 'login', array() ), 'requires_confirmation' ), 'Вход должен требовать подтверждения email.' );
velotok_accounts_assert( false === velotok_accounts_value( velotok_accounts_value( $contract, 'account', array() ), 'unconfirmed_allowed' ), 'Неподтверждённый пользователь не должен иметь доступа к кабинету.' );
velotok_accounts_assert( true === velotok_accounts_value( velotok_accounts_value( $contract, 'logout', array() ), 'ends_session' ), 'Выход должен завершать сессию.' );
velotok_accounts_assert( true === velotok_accounts_value( velotok_accounts_value( $contract, 'cart', array() ), 'transfer_on_registration' ), 'Регистрация должна переносить гостевую корзину.' );
velotok_accounts_assert( '/checkout/' === velotok_accounts_value( velotok_accounts_value( $contract, 'checkout', array() ), 'target_url' ), 'После переноса корзины должен быть переход к оформлению.' );

$operations = velotok_accounts_value( $contract, 'operations', array() );
foreach ( array( 'register', 'confirm_email', 'login', 'logout', 'current_user', 'can_access_account', 'transfer_guest_cart', 'checkout_redirect' ) as $operation ) {
	velotok_accounts_assert( isset( $operations[ $operation ] ) && '' !== $operations[ $operation ], 'В контракте отсутствует операция ' . $operation . '.' );
}

if ( ! function_exists( 'velotok_core_account_register' ) ) {
	fwrite( STDERR, 'FAIL: Не объявлена функция velotok_core_account_register().' . PHP_EOL );
	exit( 1 );
}

velotok_core_accounts_reset();

$email    = 'Buyer@Example.com';
$password = 'SecretPass1';
$token    = 'confirmation-token-1';

$registered = velotok_core_account_register(
	$email,
	$password,
	array(
		'confirmation_token' => $token,
		'name'               => 'Покупатель',
	)
);

velotok_accounts_assert( true === velotok_accounts_value( $registered, 'valid' ), 'Регистрация нового пользователя должна быть успешной.' );
velotok_accounts_assert( true === velotok_accounts_value( $registered, 'registered' ), 'Новый пользователь должен быть зарегистрирован.' );
velotok_accounts_assert( true === velotok_accounts_value( $registered, 'confirmation_required' ), 'Регистрация должна требовать подтверждения email.' );
velotok_accounts_assert( false === velotok_accounts_value( $registered, 'authenticated' ), 'До подтверждения email пользователь не должен быть авторизован.' );
velotok_accounts_assert( $token === velotok_accounts_value( $registered, 'confirmation_token' ), 'Регистрация должна выдать токен подтверждения.' );
velotok_accounts_assert( 'pending' === velotok_accounts_value( $registered, 'status' ), 'Новый пользователь должен быть в статусе pending.' );
velotok_accounts_assert( 'confirm_email' === velotok_accounts_value( $registered, 'next_step' ), 'После регистрации нужно подтвердить email.' );
velotok_accounts_assert( (int) velotok_accounts_value( $registered, 'user_id', 0 ) > 0, 'Зарегистрированному пользователю должен быть выдан id.' );

$pending_login = velotok_core_account_login( $email, $password );

velotok_accounts_assert( false === velotok_accounts_value( $pending_login, 'authenticated' ), 'Неподтверждённый пользователь не должен входить.' );
velotok_accounts_assert( false === velotok_accounts_value( $pending_login, 'logged_in' ), 'Неподтверждённый пользователь не должен получать сессию.' );
velotok_accounts_assert( 'ACCOUNT_EMAIL_NOT_CONFIRMED' === velotok_accounts_value( $pending_login, 'code' ), 'Вход без подтверждения должен возвращать ACCOUNT_EMAIL_NOT_CONFIRMED.' );
velotok_accounts_assert( null === velotok_accounts_value( $pending_login, 'session_token' ), 'Неподтверждённый пользователь не должен получать токен сессии.' );

$pending_access = velotok_core_account_can_access( $email );

velotok_accounts_assert( false === velotok_accounts_value( $pending_access, 'allowed' ), 'Неподтверждённый пользователь не должен иметь доступа к личному кабинету.' );
velotok_accounts_assert( 'ACCOUNT_ACCESS_DENIED' === velotok_accounts_value( $pending_access, 'code' ), 'Доступ к кабинету без подтверждения должен быть запрещён.' );

$confirmed = velotok_core_account_confirm_email( $token );

velotok_accounts_assert( true === velotok_accounts_value( $confirmed, 'valid' ), 'Подтверждение email должно быть успешным.' );
velotok_accounts_assert( true === velotok_accounts_value( $confirmed, 'confirmed' ), 'Аккаунт должен быть подтверждён.' );
velotok_accounts_assert( true === velotok_accounts_value( $confirmed, 'authenticated' ), 'После подтверждения должна создаваться сессия.' );
velotok_accounts_assert( 'active' === velotok_accounts_value( $confirmed, 'status' ), 'После подтверждения аккаунт должен быть active.' );
velotok_accounts_assert( '' !== (string) velotok_accounts_value( $confirmed, 'session_token' ), 'После подтверждения должен быть выдан токен сессии.' );

$confirmed_session = velotok_accounts_value( $confirmed, 'session_token' );
$confirmed_access  = velotok_core_account_can_access( $confirmed_session );

velotok_accounts_assert( true === velotok_accounts_value( $confirmed_access, 'allowed' ), 'Подтверждённый пользователь должен иметь доступ к кабинету.' );
velotok_accounts_assert( true === velotok_accounts_value( $confirmed_access, 'confirmed' ), 'Доступ к кабинету должен требовать подтверждения.' );

$login = velotok_core_account_login( $email, $password );

velotok_accounts_assert( true === velotok_accounts_value( $login, 'valid' ), 'Подтверждённый пользователь должен входить по email и паролю.' );
velotok_accounts_assert( true === velotok_accounts_value( $login, 'authenticated' ), 'Успешный вход должен авторизовать пользователя.' );
velotok_accounts_assert( true === velotok_accounts_value( $login, 'account_access' ), 'После входа должен быть доступ к кабинету.' );
velotok_accounts_assert( '' !== (string) velotok_accounts_value( $login, 'session_token' ), 'Успешный вход должен выдавать токен сессии.' );

$login_session = velotok_accounts_value( $login, 'session_token' );

$logout = velotok_core_account_logout( $login_session );

velotok_accounts_assert( true === velotok_accounts_value( $logout, 'logged_out' ), 'Выход должен завершать сессию.' );
velotok_accounts_assert( true === velotok_accounts_value( $logout, 'session_ended' ), 'Выход должен помечать сессию завершённой.' );
velotok_accounts_assert( false === velotok_accounts_value( $logout, 'authenticated' ), 'После выхода пользователь не должен быть авторизован.' );
velotok_accounts_assert( null === velotok_core_account_current_user( $login_session ), 'После выхода сессия не должна возвращать пользователя.' );

$after_logout_access = velotok_core_account_can_access( $login_session );
velotok_accounts_assert( false === velotok_accounts_value( $after_logout_access, 'allowed' ), 'После выхода доступ к кабинету должен быть закрыт.' );

$state_before = velotok_core_accounts_state();
$users_before = count( $state_before['users'] );

$duplicate = velotok_core_account_register(
	'buyer@example.com',
	'AnotherPass2',
	array( 'confirmation_token' => 'confirmation-token-2' )
);

$state_after = velotok_core_accounts_state();
$users_after = count( $state_after['users'] );

velotok_accounts_assert( true === velotok_accounts_value( $duplicate, 'valid' ), 'Повторная регистрация существующего email не должна быть ошибкой.' );
velotok_accounts_assert( false === velotok_accounts_value( $duplicate, 'registered' ), 'Существующий email не должен создавать новый аккаунт.' );
velotok_accounts_assert( true === velotok_accounts_value( $duplicate, 'existing_user' ), 'Существующий email должен распознаваться.' );
velotok_accounts_assert( true === velotok_accounts_value( $duplicate, 'login_required' ), 'Существующий email должен вести ко входу.' );
velotok_accounts_assert( 'login' === velotok_accounts_value( $duplicate, 'next_step' ), 'Существующий email должен вести на шаг login.' );
velotok_accounts_assert( $users_before === $users_after, 'Повторная регистрация не должна создавать дубль.' );

$invalid_email = velotok_core_account_register( 'not-an-email', $password );
velotok_accounts_assert( 'ACCOUNT_EMAIL_INVALID' === velotok_accounts_value( $invalid_email, 'code' ), 'Некорректный email должен отклоняться.' );

$missing_password = velotok_core_account_register( 'new@example.com', '' );
velotok_accounts_assert( 'ACCOUNT_PASSWORD_REQUIRED' === velotok_accounts_value( $missing_password, 'code' ), 'Пустой пароль должен отклоняться.' );

$short_password = velotok_core_account_register( 'new@example.com', 'short' );
velotok_accounts_assert( 'ACCOUNT_PASSWORD_TOO_SHORT' === velotok_accounts_value( $short_password, 'code' ), 'Короткий пароль должен отклоняться.' );

$wrong_password = velotok_core_account_login( $email, 'WrongPass9' );
velotok_accounts_assert( 'ACCOUNT_INVALID_CREDENTIALS' === velotok_accounts_value( $wrong_password, 'code' ), 'Неверный пароль должен возвращать общую ошибку.' );
velotok_accounts_assert( false === velotok_accounts_value( $wrong_password, 'authenticated' ), 'Неверный пароль не должен авторизовать.' );
velotok_accounts_assert( false === velotok_accounts_value( $wrong_password, 'existing_user' ), 'Ошибка входа не должна раскрывать существование аккаунта.' );

$unknown_user = velotok_core_account_login( 'missing@example.com', 'WhateverPass1' );
velotok_accounts_assert( 'ACCOUNT_INVALID_CREDENTIALS' === velotok_accounts_value( $unknown_user, 'code' ), 'Неизвестный email должен возвращать ту же общую ошибку.' );
velotok_accounts_assert( false === velotok_accounts_value( $unknown_user, 'existing_user' ), 'Неизвестный email не должен раскрываться.' );

velotok_core_accounts_reset();

$cart_token  = 'confirmation-token-cart';
$cart_email  = 'cart@example.com';
$cart_pass   = 'CartPass123';
$registration = velotok_core_account_register( $cart_email, $cart_pass, array( 'confirmation_token' => $cart_token ) );
$guest_cart   = velotok_core_cart_guest();
$guest_cart   = velotok_core_cart_add_item( $guest_cart, 'riviera', 2 );

$with_cart = velotok_core_account_confirm_email(
	$cart_token,
	'',
	array( 'guest_cart' => $guest_cart )
);

velotok_accounts_assert( true === velotok_accounts_value( $with_cart, 'confirmed' ), 'Подтверждение с корзиной должно проходить.' );
velotok_accounts_assert( is_array( velotok_accounts_value( $with_cart, 'transfer' ) ), 'При регистрации должен выполняться перенос корзины.' );
velotok_accounts_assert( is_array( velotok_accounts_value( $with_cart, 'redirect' ) ), 'После переноса корзины должен быть переход к оформлению.' );
velotok_accounts_assert( '/checkout/' === velotok_accounts_value( $with_cart, 'redirect_url' ), 'Переход должен вести на /checkout/.' );
velotok_accounts_assert( 'checkout' === velotok_accounts_value( $with_cart, 'next_step' ), 'После переноса корзины следующий шаг — checkout.' );

$transferred_cart = velotok_accounts_value( $with_cart, 'transfer' );
velotok_accounts_assert( true === velotok_accounts_value( $transferred_cart, 'authenticated' ), 'Перенесённая корзина должна принадлежать авторизованному пользователю.' );
velotok_accounts_assert( false === velotok_accounts_value( $transferred_cart, 'guest' ), 'После переноса корзина не должна быть гостевой.' );
velotok_accounts_assert( 1 === velotok_core_cart_unique_count( $transferred_cart ), 'В перенесённой корзине должна быть одна позиция.' );
velotok_accounts_assert( 2 === velotok_core_cart_count( $transferred_cart ), 'Количество товара должно сохраниться при переносе.' );

$cart_session = velotok_accounts_value( $with_cart, 'session_token' );
velotok_core_account_logout( $cart_session );

$second_guest = velotok_core_cart_guest();
$second_guest = velotok_core_cart_add_item( $second_guest, 'street-7', 1 );

$login_with_cart = velotok_core_account_login(
	$cart_email,
	$cart_pass,
	array( 'guest_cart' => $second_guest )
);

velotok_accounts_assert( true === velotok_accounts_value( $login_with_cart, 'authenticated' ), 'Вход с гостевой корзиной должен авторизовать.' );
velotok_accounts_assert( is_array( velotok_accounts_value( $login_with_cart, 'transfer' ) ), 'При входе должен выполняться перенос корзины.' );
velotok_accounts_assert( '/checkout/' === velotok_accounts_value( $login_with_cart, 'redirect_url' ), 'После входа с корзиной должен быть переход к оформлению.' );
velotok_accounts_assert( 1 === velotok_core_cart_unique_count( velotok_accounts_value( $login_with_cart, 'transfer' ) ), 'При входе в корзину должна перенестись позиция.' );

/*
 * Real WordPress/WooCommerce path.
 *
 * The in-memory contract above is useful, but the requirement must also be
 * observable in the integration that persists customers. The harness stubs the
 * small WordPress surface and loads includes/accounts-wordpress.php, so the
 * mandatory command exercises the same code the live site uses: registration
 * must store a pending customer, send the confirmation email and block login
 * until the emailed token is confirmed.
 */
require_once __DIR__ . '/helpers/accounts-wordpress-harness.php';

velotok_test_load_accounts_integration( dirname( __DIR__ ) . '/includes/accounts-wordpress.php' );
velotok_test_reset_store();

$wp_email    = 'wp-confirm@example.com';
$wp_password = 'WpSecretPass1';
$wp_token    = 'contract-wp-confirmation-token';

$wp_registration = velotok_core_account_register(
	$wp_email,
	$wp_password,
	array(
		'confirmation_token' => $wp_token,
		// Even if a caller passes a confirmed state, registration must keep the
		// account pending until the email token is used.
		'email_confirmed'    => true,
		'status'             => 'active',
		'authenticated'      => true,
	)
);

velotok_accounts_assert( true === velotok_accounts_value( $wp_registration, 'valid' ), 'WP-регистрация должна быть успешной.' );
velotok_accounts_assert( true === velotok_accounts_value( $wp_registration, 'registered' ), 'WP-регистрация должна создавать аккаунт.' );
velotok_accounts_assert( true === velotok_accounts_value( $wp_registration, 'confirmation_required' ), 'WP-регистрация должна требовать подтверждения email.' );
velotok_accounts_assert( false === velotok_accounts_value( $wp_registration, 'authenticated' ), 'WP-регистрация не должна авторизовывать до подтверждения.' );
velotok_accounts_assert( 'pending' === velotok_accounts_value( $wp_registration, 'status' ), 'WP-аккаунт должен быть pending до подтверждения.' );
velotok_accounts_assert( 'confirm_email' === velotok_accounts_value( $wp_registration, 'next_step' ), 'WP-регистрация должна вести на подтверждение email.' );
velotok_accounts_assert( $wp_token === velotok_accounts_value( $wp_registration, 'confirmation_token' ), 'WP-регистрация должна выдать токен подтверждения.' );
velotok_accounts_assert( true === velotok_accounts_value( $wp_registration, 'email_sent' ), 'WP-регистрация должна отправить письмо подтверждения.' );
velotok_accounts_assert( in_array( 'wc_create_new_customer', $GLOBALS['velotok_test_created_via'], true ), 'WP-аккаунт должен создаваться через wc_create_new_customer().' );

$wp_user_id = (int) velotok_accounts_value( $wp_registration, 'user_id', 0 );

velotok_accounts_assert( $wp_user_id > 0, 'WP-пользователь должен получить id.' );
velotok_accounts_assert( '0' === (string) get_user_meta( $wp_user_id, VELOTOK_ACCOUNT_CONFIRMED_META, true ), 'WP-мета подтверждения должна быть unconfirmed после регистрации.' );
velotok_accounts_assert( 'pending' === (string) get_user_meta( $wp_user_id, VELOTOK_ACCOUNT_STATUS_META, true ), 'WP-статус должен быть pending после регистрации.' );
velotok_accounts_assert( $wp_token === (string) get_user_meta( $wp_user_id, VELOTOK_ACCOUNT_TOKEN_META, true ), 'WP-токен подтверждения должен сохраняться в user meta.' );
velotok_accounts_assert( 1 === count( $GLOBALS['velotok_test_mail'] ), 'WP-регистрация должна отправлять одно письмо подтверждения.' );
velotok_accounts_assert( false !== strpos( $GLOBALS['velotok_test_mail'][0]['message'], $wp_token ), 'Письмо должно содержать ссылку с токеном подтверждения.' );

$wp_pending_login = velotok_core_account_login( $wp_email, $wp_password );

velotok_accounts_assert( false === velotok_accounts_value( $wp_pending_login, 'authenticated' ), 'WP: неподтверждённый пользователь не должен входить.' );
velotok_accounts_assert( 'ACCOUNT_EMAIL_NOT_CONFIRMED' === velotok_accounts_value( $wp_pending_login, 'code' ), 'WP: вход без подтверждения должен возвращать ACCOUNT_EMAIL_NOT_CONFIRMED.' );
velotok_accounts_assert( 0 === (int) $GLOBALS['velotok_test_signon_calls'], 'WP: wp_signon() не должен вызываться до подтверждения.' );

$wp_pending_access = velotok_core_account_can_access( $wp_user_id );

velotok_accounts_assert( false === velotok_accounts_value( $wp_pending_access, 'allowed' ), 'WP: неподтверждённый пользователь не должен иметь доступ к кабинету.' );
velotok_accounts_assert( 'ACCOUNT_ACCESS_DENIED' === velotok_accounts_value( $wp_pending_access, 'code' ), 'WP: доступ без подтверждения должен запрещаться.' );

$wp_invalid = velotok_core_account_confirm_email( 'wrong-wp-token' );

velotok_accounts_assert( false === velotok_accounts_value( $wp_invalid, 'confirmed' ), 'WP: неверный токен не должен подтверждать email.' );
velotok_accounts_assert( 'ACCOUNT_CONFIRMATION_INVALID' === velotok_accounts_value( $wp_invalid, 'code' ), 'WP: неверный токен должен возвращать ACCOUNT_CONFIRMATION_INVALID.' );

$wp_confirmed = velotok_core_account_confirm_email( $wp_token );

velotok_accounts_assert( true === velotok_accounts_value( $wp_confirmed, 'confirmed' ), 'WP: переход по токену должен подтверждать email.' );
velotok_accounts_assert( 'active' === velotok_accounts_value( $wp_confirmed, 'status' ), 'WP: после подтверждения статус должен быть active.' );
velotok_accounts_assert( '1' === (string) get_user_meta( $wp_user_id, VELOTOK_ACCOUNT_CONFIRMED_META, true ), 'WP: мета подтверждения должна обновиться.' );
velotok_accounts_assert( 'active' === (string) get_user_meta( $wp_user_id, VELOTOK_ACCOUNT_STATUS_META, true ), 'WP: статус должен стать active.' );
velotok_accounts_assert( '' === (string) get_user_meta( $wp_user_id, VELOTOK_ACCOUNT_TOKEN_META, true ), 'WP: токен должен удаляться после подтверждения.' );
velotok_accounts_assert( in_array( $wp_user_id, $GLOBALS['velotok_test_auth_cookie'], true ), 'WP: после подтверждения должна ставиться auth cookie.' );

$GLOBALS['velotok_test_current_user'] = 0;
$wp_login                             = velotok_core_account_login( $wp_email, $wp_password );

velotok_accounts_assert( true === velotok_accounts_value( $wp_login, 'authenticated' ), 'WP: подтверждённый пользователь должен входить.' );
velotok_accounts_assert( 1 === (int) $GLOBALS['velotok_test_signon_calls'], 'WP: подтверждённый вход должен выполняться через wp_signon().' );

if ( array() !== $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

fwrite( STDOUT, 'PASS: контракт аккаунтов выполняется.' . PHP_EOL );
exit( 0 );
