<?php
/**
 * Focused contract test for TESTEB-3: an existing email leads to login.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/accounts-existing-email-login-test.php
 *
 * Overrides used by the red-green test:
 *   VELOTOK_ACCOUNTS_STUB=/tmp/accounts.php
 *   VELOTOK_ACCOUNTS_WP_INTEGRATION=/tmp/accounts-wordpress.php
 *
 * The test proves that registering with an email that is already known never
 * creates a second account, never overwrites the original password, token or
 * status, and routes the visitor to the login step instead of confirmation.
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
 * Assert a TESTEB-3 condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_existing_email_assert( $condition, $message ) {
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
function velotok_existing_email_value( $result, $key, $fallback = null ) {
	if ( is_array( $result ) && array_key_exists( $key, $result ) ) {
		return $result[ $key ];
	}

	return $fallback;
}

if ( ! function_exists( 'velotok_core_account_register' ) ) {
	fwrite( STDERR, 'FAIL: Не объявлена функция velotok_core_account_register().' . PHP_EOL );
	exit( 1 );
}

/*
 * In-memory contract.
 */
velotok_core_accounts_reset();

$email    = 'Existing@Example.com';
$password = 'OriginalPass1';
$token    = 'testeb-3-stub-confirmation-token';

$first = velotok_core_account_register( $email, $password, array( 'confirmation_token' => $token ) );

velotok_existing_email_assert( true === velotok_existing_email_value( $first, 'registered' ), 'Первый аккаунт должен регистрироваться.' );

$key          = velotok_core_account_normalize_key( $email );
$state_before = velotok_core_accounts_state();
$count_before = count( $state_before['users'] );
$user_before  = $state_before['users'][ $key ];

$duplicate = velotok_core_account_register(
	'existing@example.com',
	'DifferentPass2',
	array( 'confirmation_token' => 'testeb-3-stub-duplicate-token' )
);

velotok_existing_email_assert( true === velotok_existing_email_value( $duplicate, 'valid' ), 'Существующий email не должен быть ошибкой регистрации.' );
velotok_existing_email_assert( false === velotok_existing_email_value( $duplicate, 'registered' ), 'Существующий email не должен создавать новый аккаунт.' );
velotok_existing_email_assert( true === velotok_existing_email_value( $duplicate, 'existing_user' ), 'Существующий email должен распознаваться.' );
velotok_existing_email_assert( true === velotok_existing_email_value( $duplicate, 'login_required' ), 'Существующий email должен вести ко входу.' );
velotok_existing_email_assert( 'login' === velotok_existing_email_value( $duplicate, 'next_step' ), 'Следующий шаг для существующего email — login.' );
velotok_existing_email_assert( false === velotok_existing_email_value( $duplicate, 'confirmation_required' ), 'Существующий email не должен отправлять на подтверждение нового аккаунта.' );
velotok_existing_email_assert( '' !== (string) velotok_existing_email_value( $duplicate, 'login_url' ), 'Результат должен содержать ссылку на вход.' );
velotok_existing_email_assert( null === velotok_existing_email_value( $duplicate, 'confirmation_token' ), 'Повторная регистрация не должна выдавать новый токен подтверждения.' );

$state_after = velotok_core_accounts_state();
$user_after  = $state_after['users'][ $key ];

velotok_existing_email_assert( $count_before === count( $state_after['users'] ), 'Повторная регистрация не должна создавать дубль.' );
velotok_existing_email_assert( (int) $user_before['user_id'] === (int) $user_after['user_id'], 'Идентификатор исходного пользователя не должен меняться.' );
velotok_existing_email_assert( $user_before['password_hash'] === $user_after['password_hash'], 'Пароль исходного пользователя не должен перезаписываться.' );
velotok_existing_email_assert( $user_before['confirmation_token'] === $user_after['confirmation_token'], 'Токен исходного пользователя не должен перезаписываться.' );
velotok_existing_email_assert( $user_before['status'] === $user_after['status'], 'Статус исходного пользователя не должен меняться.' );

$confirmed = velotok_core_account_confirm_email( $token );

velotok_existing_email_assert( true === velotok_existing_email_value( $confirmed, 'confirmed' ), 'Исходный токен должен продолжать подтверждать email.' );

$original_login = velotok_core_account_login( $email, $password );

velotok_existing_email_assert( true === velotok_existing_email_value( $original_login, 'authenticated' ), 'Вход с исходным паролем должен работать.' );

$duplicate_login = velotok_core_account_login( $email, 'DifferentPass2' );

velotok_existing_email_assert( 'ACCOUNT_INVALID_CREDENTIALS' === velotok_existing_email_value( $duplicate_login, 'code' ), 'Пароль из повторной регистрации не должен заменять исходный.' );

$duplicate_after_confirmation = velotok_core_account_register( 'EXISTING@example.com', 'AnotherPass3' );

velotok_existing_email_assert( false === velotok_existing_email_value( $duplicate_after_confirmation, 'registered' ), 'Подтверждённый существующий email тоже не должен создавать дубль.' );
velotok_existing_email_assert( true === velotok_existing_email_value( $duplicate_after_confirmation, 'login_required' ), 'Подтверждённый существующий email должен вести ко входу.' );
velotok_existing_email_assert( 'login' === velotok_existing_email_value( $duplicate_after_confirmation, 'next_step' ), 'Подтверждённый существующий email должен вести на login.' );
velotok_existing_email_assert( $count_before === count( velotok_core_accounts_state()['users'] ), 'Подтверждённый пользователь не должен дублироваться.' );

/*
 * Real WordPress/WooCommerce path.
 */
require_once __DIR__ . '/helpers/accounts-wordpress-harness.php';

velotok_test_load_accounts_integration( dirname( __DIR__ ) . '/includes/accounts-wordpress.php' );
velotok_test_reset_store();

$wp_email    = 'Wp-Existing@Example.com';
$wp_password = 'OriginalPass1';
$wp_token    = 'testeb-3-wp-confirmation-token';

$wp_first = velotok_core_account_register( $wp_email, $wp_password, array( 'confirmation_token' => $wp_token ) );

velotok_existing_email_assert( true === velotok_existing_email_value( $wp_first, 'registered' ), 'WP-аккаунт должен регистрироваться.' );
velotok_existing_email_assert( 1 === count( $GLOBALS['velotok_test_users'] ), 'WP-аккаунт должен сохраняться один раз.' );
velotok_existing_email_assert( 1 === count( $GLOBALS['velotok_test_mail'] ), 'Для нового аккаунта должно отправляться письмо подтверждения.' );

$wp_user         = get_user_by( 'email', $wp_email );
$wp_user_id      = null !== $wp_user ? (int) $wp_user->ID : 0;
$wp_hash_before  = null !== $wp_user ? (string) $wp_user->user_pass : '';
$wp_mail_before  = count( $GLOBALS['velotok_test_mail'] );
$wp_create_before = count( $GLOBALS['velotok_test_created_via'] );

$wp_duplicate = velotok_core_account_register(
	'wp-existing@example.com',
	'DifferentPass2',
	array( 'confirmation_token' => 'testeb-3-wp-duplicate-token' )
);

velotok_existing_email_assert( true === velotok_existing_email_value( $wp_duplicate, 'valid' ), 'WP: существующий email не должен быть ошибкой регистрации.' );
velotok_existing_email_assert( false === velotok_existing_email_value( $wp_duplicate, 'registered' ), 'WP: существующий email не должен создавать новый аккаунт.' );
velotok_existing_email_assert( true === velotok_existing_email_value( $wp_duplicate, 'existing_user' ), 'WP: существующий email должен распознаваться.' );
velotok_existing_email_assert( true === velotok_existing_email_value( $wp_duplicate, 'login_required' ), 'WP: существующий email должен вести ко входу.' );
velotok_existing_email_assert( 'login' === velotok_existing_email_value( $wp_duplicate, 'next_step' ), 'WP: следующий шаг для существующего email — login.' );
velotok_existing_email_assert( false === velotok_existing_email_value( $wp_duplicate, 'confirmation_required' ), 'WP: существующий email не должен отправлять на подтверждение нового аккаунта.' );
velotok_existing_email_assert( '' !== (string) velotok_existing_email_value( $wp_duplicate, 'login_url' ), 'WP: результат должен содержать ссылку на вход.' );
velotok_existing_email_assert( $wp_user_id === (int) velotok_existing_email_value( $wp_duplicate, 'user_id', 0 ), 'WP: повторная регистрация должна ссылаться на исходного пользователя.' );
velotok_existing_email_assert( null === velotok_existing_email_value( $wp_duplicate, 'confirmation_token' ), 'WP: повторная регистрация не должна выдавать новый токен.' );

$wp_user_after = get_user_by( 'email', $wp_email );

velotok_existing_email_assert( 1 === count( $GLOBALS['velotok_test_users'] ), 'WP: повторная регистрация не должна создавать дубль.' );
velotok_existing_email_assert( $wp_create_before === count( $GLOBALS['velotok_test_created_via'] ), 'WP: для существующего email не должен вызываться create customer.' );
velotok_existing_email_assert( $wp_mail_before === count( $GLOBALS['velotok_test_mail'] ), 'WP: для существующего email не должно отправляться письмо регистрации.' );
velotok_existing_email_assert( null !== $wp_user_after && $wp_hash_before === (string) $wp_user_after->user_pass, 'WP: пароль исходного пользователя не должен перезаписываться.' );
velotok_existing_email_assert( 'pending' === (string) get_user_meta( $wp_user_id, VELOTOK_ACCOUNT_STATUS_META, true ), 'WP: статус исходного пользователя должен остаться pending.' );
velotok_existing_email_assert( '0' === (string) get_user_meta( $wp_user_id, VELOTOK_ACCOUNT_CONFIRMED_META, true ), 'WP: подтверждение исходного пользователя не должно меняться.' );
velotok_existing_email_assert( $wp_token === (string) get_user_meta( $wp_user_id, VELOTOK_ACCOUNT_TOKEN_META, true ), 'WP: токен исходного пользователя не должен перезаписываться.' );

$wp_confirmed = velotok_core_account_confirm_email( $wp_token );

velotok_existing_email_assert( true === velotok_existing_email_value( $wp_confirmed, 'confirmed' ), 'WP: исходный токен должен продолжать подтверждать email.' );

$GLOBALS['velotok_test_current_user'] = 0;
$wp_original_login                    = velotok_core_account_login( $wp_email, $wp_password );

velotok_existing_email_assert( true === velotok_existing_email_value( $wp_original_login, 'authenticated' ), 'WP: вход с исходным паролем должен работать.' );

$wp_duplicate_login = velotok_core_account_login( $wp_email, 'DifferentPass2' );

velotok_existing_email_assert( 'ACCOUNT_INVALID_CREDENTIALS' === velotok_existing_email_value( $wp_duplicate_login, 'code' ), 'WP: пароль из повторной регистрации не должен заменять исходный.' );

$wp_duplicate_after_confirmation = velotok_core_account_register( 'WP-EXISTING@example.com', 'AnotherPass3' );

velotok_existing_email_assert( false === velotok_existing_email_value( $wp_duplicate_after_confirmation, 'registered' ), 'WP: подтверждённый существующий email не должен создавать дубль.' );
velotok_existing_email_assert( true === velotok_existing_email_value( $wp_duplicate_after_confirmation, 'login_required' ), 'WP: подтверждённый существующий email должен вести ко входу.' );
velotok_existing_email_assert( 'login' === velotok_existing_email_value( $wp_duplicate_after_confirmation, 'next_step' ), 'WP: подтверждённый существующий email должен вести на login.' );
velotok_existing_email_assert( 1 === count( $GLOBALS['velotok_test_users'] ), 'WP: подтверждённый пользователь не должен дублироваться.' );

if ( array() !== $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

fwrite( STDOUT, 'PASS: существующий email ведёт ко входу без дубля (TESTEB-3).' . PHP_EOL );
exit( 0 );
