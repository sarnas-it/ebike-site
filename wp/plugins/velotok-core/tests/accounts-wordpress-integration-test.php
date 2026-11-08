<?php
/**
 * Integration test for the real WordPress/WooCommerce accounts bridge.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/accounts-wordpress-integration-test.php
 *
 * The test stubs the small WordPress/WooCommerce surface used by
 * accounts-wordpress.php and proves that registration is persisted through
 * wc_create_new_customer(), the account stays pending until the emailed token
 * is confirmed, duplicate emails never create a duplicate user, login goes
 * through wp_signon() and logout goes through wp_logout().
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_test_users']        = array();
$GLOBALS['velotok_test_meta']         = array();
$GLOBALS['velotok_test_next_id']      = 1;
$GLOBALS['velotok_test_created_via']  = array();
$GLOBALS['velotok_test_mail']         = array();
$GLOBALS['velotok_test_auth_cookie']  = array();
$GLOBALS['velotok_test_current_user'] = 0;
$GLOBALS['velotok_test_signon_calls'] = 0;
$GLOBALS['velotok_test_logout_calls'] = 0;
$GLOBALS['velotok_test_redirects']    = array();
$GLOBALS['velotok_test_hooks']         = array();
$GLOBALS['velotok_test_nonces']       = array();
$GLOBALS['velotok_test_cart_items']   = 0;

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error replacement for the standalone test.
	 */
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		private $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		private $message;

		/**
		 * Constructor.
		 *
		 * @param string $code    Code.
		 * @param string $message Message.
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
		}

		/**
		 * Return the error code.
		 *
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * Return the error message.
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}
}

/**
 * Whether a value is a WP_Error.
 *
 * @param mixed $value Value.
 * @return bool
 */
function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

/**
 * Test hook recorder.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted args.
 * @return bool
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['velotok_test_hooks'][ $hook ][] = $callback;

	return true;
}

/**
 * Test filter recorder.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted args.
 * @return bool
 */
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['velotok_test_hooks'][ $hook ][] = $callback;

	return true;
}

/**
 * Test shortcode recorder.
 *
 * @param string   $tag      Shortcode tag.
 * @param callable $callback Callback.
 * @return bool
 */
function add_shortcode( $tag, $callback ) {
	$GLOBALS['velotok_test_hooks'][ 'shortcode:' . $tag ][] = $callback;

	return true;
}

/**
 * Test action runner.
 *
 * @param string $hook Hook name.
 * @return void
 */
function do_action( $hook ) {
	unset( $hook );
}

/**
 * Test translations.
 *
 * @param string $text Text.
 * @return string
 */
function __( $text ) {
	return $text;
}

/**
 * Escape HTML.
 *
 * @param string $text Text.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Escape URL.
 *
 * @param string $url URL.
 * @return string
 */
function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}

/**
 * Normalize an email.
 *
 * @param string $email Email.
 * @return string
 */
function sanitize_email( $email ) {
	$email = trim( (string) $email );

	return false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ? '' : $email;
}

/**
 * Sanitize a username.
 *
 * @param string $value Value.
 * @return string
 */
function sanitize_user( $value ) {
	return preg_replace( '/[^a-z0-9_\-@.]+/i', '', (string) $value );
}

/**
 * Sanitize a text field.
 *
 * @param string $value Value.
 * @return string
 */
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

/**
 * Unslash input.
 *
 * @param mixed $value Value.
 * @return mixed
 */
function wp_unslash( $value ) {
	return $value;
}

/**
 * Home URL.
 *
 * @param string $path Path.
 * @return string
 */
function home_url( $path = '/' ) {
	return 'https://example.test' . $path;
}

/**
 * Admin URL.
 *
 * @param string $path Path.
 * @return string
 */
function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
}

/**
 * WooCommerce checkout URL.
 *
 * @return string
 */
function wc_get_checkout_url() {
	return 'https://example.test/checkout/';
}

/**
 * WooCommerce page permalink.
 *
 * @param string $page Page.
 * @return string
 */
function wc_get_page_permalink( $page ) {
	unset( $page );

	return 'https://example.test/account/';
}

/**
 * Add query arguments.
 *
 * @param array  $args Arguments.
 * @param string $url  URL.
 * @return string
 */
function add_query_arg( $args, $url = '', $maybe_url = '' ) {
	if ( '' !== (string) $maybe_url ) {
		$args = array( $args => $url );
		$url  = (string) $maybe_url;
	}

	if ( ! is_array( $args ) ) {
		$args = array( $args => $url );
		$url  = '';
	}

	return $url . ( false === strpos( (string) $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}

/**
 * Whether the request is SSL.
 *
 * @return bool
 */
function is_ssl() {
	return true;
}

/**
 * Record a redirect instead of exiting.
 *
 * @param string $url URL.
 * @return bool
 */
function wp_safe_redirect( $url ) {
	$GLOBALS['velotok_test_redirects'][] = $url;

	return true;
}

/**
 * Record a redirect instead of exiting.
 *
 * @param string $url URL.
 * @return bool
 */
function wp_redirect( $url ) {
	$GLOBALS['velotok_test_redirects'][] = $url;

	return true;
}

/**
 * Generate a nonce field.
 *
 * @param string $action Action.
 * @param string $name   Field name.
 * @return void
 */
function wp_nonce_field( $action, $name = '_wpnonce' ) {
	echo '<input type="hidden" name="' . esc_html( $name ) . '" value="nonce-' . esc_html( $action ) . '" />';
}

/**
 * Verify a nonce.
 *
 * @param string $nonce  Nonce.
 * @param string $action Action.
 * @return bool
 */
function wp_verify_nonce( $nonce, $action ) {
	return ( 'nonce-' . $action ) === (string) $nonce;
}

/**
 * Find a user.
 *
 * @param string $field Field.
 * @param mixed  $value Value.
 * @return object|null
 */
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['velotok_test_users'] as $user ) {
		if ( 'id' === $field && (int) $user->ID === (int) $value ) {
			return $user;
		}

		if ( 'email' === $field && 0 === strcasecmp( $user->user_email, (string) $value ) ) {
			return $user;
		}

		if ( 'login' === $field && $user->user_login === (string) $value ) {
			return $user;
		}
	}

	return null;
}

/**
 * Whether a username exists.
 *
 * @param string $username Username.
 * @return bool
 */
function username_exists( $username ) {
	return null !== get_user_by( 'login', $username );
}

/**
 * Add a user to the test store.
 *
 * @param string $email    Email.
 * @param string $username Username.
 * @param string $password Password.
 * @return object
 */
function velotok_test_add_user( $email, $username, $password ) {
	$id = (int) $GLOBALS['velotok_test_next_id'];
	$GLOBALS['velotok_test_next_id']++;

	$user = (object) array(
		'ID'         => $id,
		'user_email' => (string) $email,
		'user_login' => (string) $username,
		'user_pass'  => password_hash( (string) $password, PASSWORD_DEFAULT ),
	);

	$GLOBALS['velotok_test_users'][ $id ] = $user;
	$GLOBALS['velotok_test_meta'][ $id ]  = array();

	return $user;
}

/**
 * WooCommerce customer creation stub.
 *
 * @param string $email    Email.
 * @param string $username Username.
 * @param string $password Password.
 * @param array  $args     Customer data.
 * @return int|WP_Error
 */
function wc_create_new_customer( $email, $username = '', $password = '', $args = array() ) {
	unset( $args );

	$GLOBALS['velotok_test_created_via'][] = 'wc_create_new_customer';

	if ( null !== get_user_by( 'email', $email ) ) {
		return new WP_Error( 'registration-error-email-exists', 'An account is already registered with your email address.' );
	}

	$user = velotok_test_add_user( $email, $username, $password );

	return (int) $user->ID;
}

/**
 * WordPress user creation stub.
 *
 * @param string $username Username.
 * @param string $password Password.
 * @param string $email    Email.
 * @return int|WP_Error
 */
function wp_create_user( $username, $password, $email = '' ) {
	$GLOBALS['velotok_test_created_via'][] = 'wp_create_user';

	if ( null !== get_user_by( 'email', $email ) ) {
		return new WP_Error( 'existing_user_email', 'Email exists.' );
	}

	$user = velotok_test_add_user( $email, $username, $password );

	return (int) $user->ID;
}

/**
 * WordPress insert user stub.
 *
 * @param array $data User data.
 * @return int|WP_Error
 */
function wp_insert_user( $data ) {
	$GLOBALS['velotok_test_created_via'][] = 'wp_insert_user';

	$user = velotok_test_add_user( $data['user_email'], $data['user_login'], $data['user_pass'] );

	return (int) $user->ID;
}

/**
 * Read user meta.
 *
 * @param int    $user_id User id.
 * @param string $key     Meta key.
 * @param bool   $single  Single value.
 * @return mixed
 */
function get_user_meta( $user_id, $key = '', $single = false ) {
	unset( $single );

	if ( ! isset( $GLOBALS['velotok_test_meta'][ $user_id ][ $key ] ) ) {
		return '';
	}

	return $GLOBALS['velotok_test_meta'][ $user_id ][ $key ];
}

/**
 * Update user meta.
 *
 * @param int    $user_id User id.
 * @param string $key     Meta key.
 * @param mixed  $value   Value.
 * @return bool
 */
function update_user_meta( $user_id, $key, $value ) {
	if ( ! isset( $GLOBALS['velotok_test_meta'][ $user_id ] ) ) {
		$GLOBALS['velotok_test_meta'][ $user_id ] = array();
	}

	$GLOBALS['velotok_test_meta'][ $user_id ][ $key ] = $value;

	return true;
}

/**
 * Delete user meta.
 *
 * @param int    $user_id User id.
 * @param string $key     Meta key.
 * @return bool
 */
function delete_user_meta( $user_id, $key ) {
	unset( $GLOBALS['velotok_test_meta'][ $user_id ][ $key ] );

	return true;
}

/**
 * Query users by meta.
 *
 * @param array $args Query args.
 * @return array
 */
function get_users( $args ) {
	$found = array();

	foreach ( $GLOBALS['velotok_test_meta'] as $user_id => $meta ) {
		if ( isset( $args['meta_key'], $args['meta_value'] ) && isset( $meta[ $args['meta_key'] ] ) && (string) $meta[ $args['meta_key'] ] === (string) $args['meta_value'] ) {
			$user = get_user_by( 'id', $user_id );

			if ( null !== $user ) {
				$found[] = $user;
			}
		}
	}

	return $found;
}

/**
 * Send mail stub.
 *
 * @param string $email   Email.
 * @param string $subject Subject.
 * @param string $message Message.
 * @return bool
 */
function wp_mail( $email, $subject, $message ) {
	$GLOBALS['velotok_test_mail'][] = array(
		'email'   => $email,
		'subject' => $subject,
		'message' => $message,
	);

	return true;
}

/**
 * Log a user in.
 *
 * @param array  $credentials Credentials.
 * @param string $secure      Secure cookie.
 * @return object|WP_Error
 */
function wp_signon( $credentials, $secure = '' ) {
	unset( $secure );

	$GLOBALS['velotok_test_signon_calls']++;

	$user = get_user_by( 'email', $credentials['user_login'] );

	if ( null === $user ) {
		$user = get_user_by( 'login', $credentials['user_login'] );
	}

	if ( null === $user || ! password_verify( (string) $credentials['user_password'], $user->user_pass ) ) {
		return new WP_Error( 'invalid_username', 'Invalid credentials.' );
	}

	$GLOBALS['velotok_test_current_user']       = (int) $user->ID;
	$GLOBALS['velotok_test_auth_cookie'][]      = (int) $user->ID;

	return $user;
}

/**
 * Set current user.
 *
 * @param int $user_id User id.
 * @return void
 */
function wp_set_current_user( $user_id ) {
	$GLOBALS['velotok_test_current_user'] = (int) $user_id;
}

/**
 * Set auth cookie.
 *
 * @param int   $user_id User id.
 * @param bool  $remember Remember.
 * @param mixed $secure   Secure.
 * @return void
 */
function wp_set_auth_cookie( $user_id, $remember = false, $secure = false ) {
	unset( $remember, $secure );

	$GLOBALS['velotok_test_auth_cookie'][] = (int) $user_id;
}

/**
 * Log the current user out.
 *
 * @return void
 */
function wp_logout() {
	$GLOBALS['velotok_test_logout_calls']++;
	$GLOBALS['velotok_test_current_user'] = 0;
}

/**
 * Whether a user is logged in.
 *
 * @return bool
 */
function is_user_logged_in() {
	return (int) $GLOBALS['velotok_test_current_user'] > 0;
}

/**
 * Current user id.
 *
 * @return int
 */
function get_current_user_id() {
	return (int) $GLOBALS['velotok_test_current_user'];
}

/**
 * Minimal WooCommerce runtime stub.
 */
class Velotok_Test_Cart {
	/**
	 * Whether the cart is empty.
	 *
	 * @return bool
	 */
	public function is_empty() {
		return 0 === (int) $GLOBALS['velotok_test_cart_items'];
	}

	/**
	 * Cart contents count.
	 *
	 * @return int
	 */
	public function get_cart_contents_count() {
		return (int) $GLOBALS['velotok_test_cart_items'];
	}

	/**
	 * Recalculate totals.
	 *
	 * @return void
	 */
	public function calculate_totals() {
	}
}

/**
 * Minimal WooCommerce runtime stub.
 */
class Velotok_Test_WooCommerce {
	/**
	 * Cart.
	 *
	 * @var Velotok_Test_Cart
	 */
	public $cart;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cart = new Velotok_Test_Cart();
	}
}

/**
 * WooCommerce runtime factory.
 *
 * @return Velotok_Test_WooCommerce
 */
function WC() {
	static $wc = null;

	if ( null === $wc ) {
		$wc = new Velotok_Test_WooCommerce();
	}

	return $wc;
}

$integration_path = dirname( __DIR__ ) . '/includes/accounts-wordpress.php';
$override         = getenv( 'VELOTOK_ACCOUNTS_WP_INTEGRATION' );

if ( is_string( $override ) && '' !== $override ) {
	$integration_path = $override;
	define( 'VELOTOK_ACCOUNTS_SKIP_WP_INTEGRATION', true );
}

require_once dirname( __DIR__ ) . '/includes/accounts.php';

if ( ! is_readable( $integration_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл WordPress-интеграции аккаунтов.' . PHP_EOL );
	exit( 1 );
}

require_once $integration_path;

$failures = array();

/**
 * Assert an integration condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_wp_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Reset the test store between scenarios.
 *
 * @return void
 */
function velotok_wp_reset_store() {
	$GLOBALS['velotok_test_users']        = array();
	$GLOBALS['velotok_test_meta']         = array();
	$GLOBALS['velotok_test_next_id']      = 1;
	$GLOBALS['velotok_test_created_via']  = array();
	$GLOBALS['velotok_test_mail']         = array();
	$GLOBALS['velotok_test_auth_cookie']  = array();
	$GLOBALS['velotok_test_current_user'] = 0;
	$GLOBALS['velotok_test_signon_calls'] = 0;
	$GLOBALS['velotok_test_logout_calls'] = 0;
	$GLOBALS['velotok_test_redirects']    = array();
	$GLOBALS['velotok_test_cart_items']   = 0;
}

velotok_wp_reset_store();

$email    = 'Buyer@Example.com';
$password = 'SecretPass1';
$token    = 'confirmation-token-wp-1';

$registration = velotok_core_account_register(
	$email,
	$password,
	array( 'confirmation_token' => $token )
);

velotok_wp_assert( true === $registration['valid'], 'Регистрация через WordPress должна быть успешной.' );
velotok_wp_assert( true === $registration['registered'], 'Регистрация должна создавать аккаунт.' );
velotok_wp_assert( true === $registration['confirmation_required'], 'Регистрация должна требовать подтверждения email.' );
velotok_wp_assert( false === $registration['authenticated'], 'Сразу после регистрации вход запрещён.' );
velotok_wp_assert( 'pending' === $registration['status'], 'Новый аккаунт должен быть pending.' );
velotok_wp_assert( $token === $registration['confirmation_token'], 'Регистрация должна выдать токен подтверждения.' );
velotok_wp_assert( 1 === count( $GLOBALS['velotok_test_users'] ), 'Пользователь должен быть сохранён в хранилище WordPress.' );
velotok_wp_assert( in_array( 'wc_create_new_customer', $GLOBALS['velotok_test_created_via'], true ), 'Аккаунт должен создаваться через wc_create_new_customer().' );

$user_id = (int) $registration['user_id'];

velotok_wp_assert( '0' === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_CONFIRMED_META, true ), 'До подтверждения email мета должна быть unconfirmed.' );
velotok_wp_assert( 'pending' === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_STATUS_META, true ), 'До подтверждения статус должен быть pending.' );
velotok_wp_assert( $token === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_TOKEN_META, true ), 'Токен должен сохраняться в user meta.' );
velotok_wp_assert( 1 === count( $GLOBALS['velotok_test_mail'] ), 'Должно отправляться письмо подтверждения.' );
velotok_wp_assert( false !== strpos( $GLOBALS['velotok_test_mail'][0]['message'], $token ), 'В письме должна быть ссылка с токеном.' );

$duplicate = velotok_core_account_register( 'buyer@example.com', 'AnotherPass2' );

velotok_wp_assert( true === $duplicate['valid'], 'Повторная регистрация не должна быть ошибкой.' );
velotok_wp_assert( false === $duplicate['registered'], 'Существующий email не должен создавать новый аккаунт.' );
velotok_wp_assert( true === $duplicate['existing_user'], 'Существующий email должен распознаваться.' );
velotok_wp_assert( true === $duplicate['login_required'], 'Существующий email должен вести ко входу.' );
velotok_wp_assert( 'login' === $duplicate['next_step'], 'Существующий email должен вести на шаг login.' );
velotok_wp_assert( 1 === count( $GLOBALS['velotok_test_users'] ), 'Повторная регистрация не должна создавать дубль.' );

$pending_login = velotok_core_account_login( $email, $password );

velotok_wp_assert( false === $pending_login['authenticated'], 'Неподтверждённый пользователь не должен входить.' );
velotok_wp_assert( 'ACCOUNT_EMAIL_NOT_CONFIRMED' === $pending_login['code'], 'Вход без подтверждения должен возвращать ACCOUNT_EMAIL_NOT_CONFIRMED.' );
velotok_wp_assert( 0 === (int) $GLOBALS['velotok_test_signon_calls'], 'wp_signon() не должен вызываться для неподтверждённого пользователя.' );

$pending_access = velotok_core_account_can_access( (int) $registration['user_id'] );
velotok_wp_assert( false === $pending_access['allowed'], 'Неподтверждённый пользователь не должен иметь доступ к кабинету.' );
velotok_wp_assert( 'ACCOUNT_ACCESS_DENIED' === $pending_access['code'], 'Доступ без подтверждения должен быть запрещён.' );

$invalid_confirmation = velotok_core_account_confirm_email( 'wrong-token' );
velotok_wp_assert( false === $invalid_confirmation['valid'], 'Неверный токен не должен подтверждать аккаунт.' );
velotok_wp_assert( 'ACCOUNT_CONFIRMATION_INVALID' === $invalid_confirmation['code'], 'Неверный токен должен возвращать ACCOUNT_CONFIRMATION_INVALID.' );

$GLOBALS['velotok_test_cart_items'] = 2;

$confirmed = velotok_core_account_confirm_email( $token );

velotok_wp_assert( true === $confirmed['valid'], 'Подтверждение email должно быть успешным.' );
velotok_wp_assert( true === $confirmed['confirmed'], 'Аккаунт должен стать подтверждённым.' );
velotok_wp_assert( true === $confirmed['authenticated'], 'После подтверждения должна создаваться сессия.' );
velotok_wp_assert( 'active' === $confirmed['status'], 'После подтверждения статус должен быть active.' );
velotok_wp_assert( 'https://example.test/checkout/' === $confirmed['redirect_url'], 'С корзиной должен быть переход на /checkout/.' );
velotok_wp_assert( 'checkout' === $confirmed['next_step'], 'После подтверждения с корзиной следующий шаг — checkout.' );
velotok_wp_assert( '1' === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_CONFIRMED_META, true ), 'Мета подтверждения должна обновиться.' );
velotok_wp_assert( 'active' === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_STATUS_META, true ), 'Статус должен стать active.' );
velotok_wp_assert( '' === (string) get_user_meta( $user_id, VELOTOK_ACCOUNT_TOKEN_META, true ), 'Токен должен удаляться после подтверждения.' );
velotok_wp_assert( in_array( $user_id, $GLOBALS['velotok_test_auth_cookie'], true ), 'После подтверждения должен ставиться auth cookie.' );

$confirmed_access = velotok_core_account_can_access( $user_id );
velotok_wp_assert( true === $confirmed_access['allowed'], 'Подтверждённый пользователь должен иметь доступ к кабинету.' );

$login = velotok_core_account_login( $email, $password );

velotok_wp_assert( true === $login['valid'], 'Подтверждённый пользователь должен входить по email и паролю.' );
velotok_wp_assert( true === $login['authenticated'], 'Успешный вход должен авторизовать пользователя.' );
velotok_wp_assert( 1 === (int) $GLOBALS['velotok_test_signon_calls'], 'Вход должен выполняться через wp_signon().' );
velotok_wp_assert( 'https://example.test/checkout/' === $login['redirect_url'], 'Вход с корзиной должен вести на /checkout/.' );

$wrong_password = velotok_core_account_login( $email, 'WrongPass9' );
velotok_wp_assert( 'ACCOUNT_INVALID_CREDENTIALS' === $wrong_password['code'], 'Неверный пароль должен возвращать общую ошибку.' );
velotok_wp_assert( false === $wrong_password['authenticated'], 'Неверный пароль не должен авторизовать.' );

$unknown = velotok_core_account_login( 'missing@example.com', 'WhateverPass1' );
velotok_wp_assert( 'ACCOUNT_INVALID_CREDENTIALS' === $unknown['code'], 'Неизвестный email должен возвращать ту же ошибку.' );

$logout = velotok_core_account_logout();

velotok_wp_assert( true === $logout['logged_out'], 'Выход должен завершать сессию.' );
velotok_wp_assert( true === $logout['session_ended'], 'Выход должен помечать сессию завершённой.' );
velotok_wp_assert( 1 === (int) $GLOBALS['velotok_test_logout_calls'], 'Выход должен вызывать wp_logout().' );
velotok_wp_assert( false === velotok_core_account_can_access( $user_id )['allowed'], 'После выхода доступ к кабинету должен быть закрыт.' );

$form = velotok_core_wp_registration_form();

velotok_wp_assert( false !== strpos( $form, 'admin-post.php' ), 'Форма регистрации должна отправляться в admin-post.php.' );
velotok_wp_assert( false !== strpos( $form, 'name="action" value="velotok_register"' ), 'Форма должна вызывать действие velotok_register.' );
velotok_wp_assert( false !== strpos( $form, 'type="email"' ), 'Форма должна запрашивать email.' );
velotok_wp_assert( false !== strpos( $form, 'type="password"' ), 'Форма должна запрашивать пароль.' );
velotok_wp_assert( false !== strpos( $form, 'velotok_register_nonce' ), 'Форма должна содержать nonce.' );

velotok_wp_assert( isset( $GLOBALS['velotok_test_hooks']['admin_post_nopriv_velotok_register'] ), 'Должен быть hook admin-post для регистрации.' );
velotok_wp_assert( isset( $GLOBALS['velotok_test_hooks']['woocommerce_created_customer'] ), 'WooCommerce-регистрация должна помечать аккаунт pending.' );
velotok_wp_assert( isset( $GLOBALS['velotok_test_hooks']['woocommerce_after_customer_login_form'] ), 'Форма регистрации должна быть доступна на странице личного кабинета.' );
velotok_wp_assert( isset( $GLOBALS['velotok_test_hooks']['shortcode:velotok_register'] ), 'Должен быть shortcode формы регистрации.' );
velotok_wp_assert( isset( $GLOBALS['velotok_test_hooks']['authenticate'] ), 'Неподтверждённый вход должен блокироваться фильтром authenticate.' );

if ( array() !== $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

fwrite( STDOUT, 'PASS: WordPress-интеграция аккаунтов выполняется.' . PHP_EOL );
exit( 0 );
