<?php
/**
 * Accounts stub contract: email/password registration, confirmation and login.
 *
 * This file is the single access point for customer accounts and keeps an
 * in-memory implementation so the contract can be exercised without a
 * WordPress installation. On a live site the helpers delegate to
 * accounts-wordpress.php, which persists customers through
 * wc_create_new_customer()/wp_create_user(), requires email confirmation,
 * logs users in with wp_signon(), blocks unconfirmed access to the personal
 * area and ends sessions with wp_logout(). Password recovery remains out of
 * scope for this iteration.
 *
 * Spec: accounts (requirement TESTEB-1).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

if ( is_readable( __DIR__ . '/cart.php' ) ) {
	require_once __DIR__ . '/cart.php';
}

/**
 * Return the accounts contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_accounts_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$contract = array(
		'spec'         => 'accounts',
		'requirement'  => 'TESTEB-1',
		'requirements' => array(
			'register'          => 'Система SHALL регистрировать пользователя по email и паролю',
			'confirm_email'     => 'Регистрация SHALL требовать подтверждения email',
			'existing_email'    => 'Существующий email SHALL вести ко входу, а не к дублю',
			'transfer_cart'     => 'Регистрация SHALL завершаться переносом корзины и переходом к оформлению',
			'logout'            => 'Сессия SHALL завершаться выходом',
			'account_access'    => 'Неподтверждённый пользователь SHALL не иметь доступа к личному кабинету',
		),
		'source'         => 'wordpress',
		'provider'       => 'woocommerce',
		'mocked'         => false,
		'network'        => false,
		'persistent'     => true,
		'runtime'        => 'wordpress',
		'in_memory_fallback' => true,
		'integration'    => array(
			'file'        => 'includes/accounts-wordpress.php',
			'create_user' => 'wc_create_new_customer',
			'login'       => 'wp_signon',
			'logout'      => 'wp_logout',
			'confirmation' => 'email_token',
		),
		'operation'      => 'account_register_confirm_login',
		'operations'     => array(
			'register'             => 'velotok_core_account_register',
			'confirm_email'        => 'velotok_core_account_confirm_email',
			'login'                => 'velotok_core_account_login',
			'logout'               => 'velotok_core_account_logout',
			'current_user'         => 'velotok_core_account_current_user',
			'can_access_account'   => 'velotok_core_account_can_access',
			'transfer_guest_cart'  => 'velotok_core_account_transfer_guest_cart',
			'checkout_redirect'    => 'velotok_core_account_checkout_redirect',
		),
		'registration'   => array(
			'enabled'                     => true,
			'creates_account'             => true,
			'required_fields'             => array( 'email', 'password' ),
			'requires_email'              => true,
			'requires_password'           => true,
			'email_confirmation_required' => true,
			'min_password_length'         => 8,
			'password_storage'            => 'password_hash',
			'duplicate_account'           => false,
			'existing_email_strategy'     => 'login',
			'existing_email_next_step'    => 'login',
			'existing_email_creates_account' => false,
			'auto_login'                  => false,
			'persistent'                  => true,
			'mocked'                      => false,
			'source'                      => 'wordpress',
		),
		'confirmation'   => array(
			'required'       => true,
			'pending_status' => 'pending',
			'active_status'  => 'active',
			'token_field'    => 'confirmation_token',
			'auto_login'     => true,
			'creates_session' => true,
			'mocked'         => false,
			'source'         => 'wordpress',
		),
		'login'          => array(
			'enabled'                       => true,
			'requires_confirmation'         => true,
			'creates_session'               => true,
			'unconfirmed_access'            => false,
			'invalid_credentials_code'      => 'ACCOUNT_INVALID_CREDENTIALS',
			'reveals_account_existence'     => false,
			'target_url'                    => '/account/login/',
			'mocked'                        => false,
			'source'                        => 'wordpress',
		),
		'logout'         => array(
			'enabled'      => true,
			'ends_session' => true,
			'mocked'       => false,
			'source'       => 'wordpress',
		),
		'account'        => array(
			'personal_area'        => true,
			'requires_login'       => true,
			'requires_confirmation' => true,
			'guests_allowed'       => false,
			'unconfirmed_allowed'  => false,
			'access_denied_code'   => 'ACCOUNT_ACCESS_DENIED',
			'mocked'               => false,
			'source'               => 'wordpress',
		),
		'cart'           => array(
			'transfer_on_registration' => true,
			'transfer_on_login'        => true,
			'merge_strategy'           => 'merge_quantities',
			'clear_guest_after_transfer' => true,
			'mocked'                   => false,
			'source'                   => 'wordpress',
		),
		'checkout'       => array(
			'after_transfer' => true,
			'target'         => 'checkout',
			'target_url'     => '/checkout/',
			'requires_items' => true,
			'empty_error'    => 'CART_EMPTY',
			'mocked'         => false,
			'source'         => 'wordpress',
		),
		'privacy'        => array(
			'reveal_existing_account'    => false,
			'generic_invalid_credentials' => true,
			'generic_registration_message' => 'Если аккаунт с таким email уже существует, используйте вход или ссылку для входа.',
		),
		'error_codes'    => array(
			'email_required'      => 'ACCOUNT_EMAIL_REQUIRED',
			'email_invalid'       => 'ACCOUNT_EMAIL_INVALID',
			'password_required'   => 'ACCOUNT_PASSWORD_REQUIRED',
			'password_too_short'  => 'ACCOUNT_PASSWORD_TOO_SHORT',
			'email_not_confirmed' => 'ACCOUNT_EMAIL_NOT_CONFIRMED',
			'invalid_credentials' => 'ACCOUNT_INVALID_CREDENTIALS',
			'confirmation_invalid' => 'ACCOUNT_CONFIRMATION_INVALID',
			'access_denied'       => 'ACCOUNT_ACCESS_DENIED',
			'cart_transfer_failed' => 'ACCOUNT_CART_TRANSFER_FAILED',
			'cart_empty'          => 'CART_EMPTY',
		),
		'open_questions' => array(
			'password_recovery' => 'Восстановление забытого пароля вне объёма текущей итерации.',
			'email_delivery'    => 'Письмо подтверждения отправляется через wp_mail(); настройка реального SMTP-провайдера остаётся задачей окружения.',
			'authentication'    => 'Вход выполняется только по email и паролю; социальные сети вне объёма.',
			'customer_storage'  => 'Пользователи сохраняются в WordPress/WooCommerce через wc_create_new_customer(); in-memory реализация используется только изолированными тестами.',
		),
	);

	return $contract;
}

/**
 * Alias for callers that prefer an explicit stub contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_accounts_stub_contract() {
	return velotok_core_accounts_contract();
}

/**
 * Alias for callers that use the data contract wording.
 *
 * @return array<string,mixed>
 */
function velotok_core_accounts_data_contract() {
	return velotok_core_accounts_contract();
}

/**
 * Short alias for the accounts contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_account_contract() {
	return velotok_core_accounts_contract();
}

/**
 * Return the open questions that block a real accounts integration.
 *
 * @return array<string,string>
 */
function velotok_core_accounts_open_questions() {
	$contract = velotok_core_accounts_contract();

	return $contract['open_questions'];
}

/**
 * Reference to the in-memory accounts state.
 *
 * @return array<string,mixed>
 */
function &velotok_core_accounts_state() {
	if ( ! isset( $GLOBALS['velotok_core_accounts_state'] ) || ! is_array( $GLOBALS['velotok_core_accounts_state'] ) ) {
		$GLOBALS['velotok_core_accounts_state'] = array(
			'users'    => array(),
			'sessions' => array(),
			'next_id'  => 1,
		);
	}

	return $GLOBALS['velotok_core_accounts_state'];
}

/**
 * Reset the in-memory accounts state.
 *
 * @return void
 */
function velotok_core_accounts_reset() {
	$GLOBALS['velotok_core_accounts_state'] = array(
		'users'    => array(),
		'sessions' => array(),
		'next_id'  => 1,
	);
}

/**
 * Normalize an email or another lookup key.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function velotok_core_account_normalize_key( $value ) {
	if ( function_exists( 'velotok_core_cart_normalize_key' ) ) {
		return velotok_core_cart_normalize_key( $value );
	}

	return is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
}

/**
 * Hash a password for the in-memory user record.
 *
 * @param string $password Plain password.
 * @return string
 */
function velotok_core_account_hash_password( $password ) {
	if ( function_exists( 'password_hash' ) ) {
		return password_hash( $password, PASSWORD_DEFAULT );
	}

	return hash( 'sha256', $password );
}

/**
 * Verify a password against the stored hash.
 *
 * @param string $password Plain password.
 * @param string $hash     Stored hash.
 * @return bool
 */
function velotok_core_account_verify_password( $password, $hash ) {
	if ( function_exists( 'password_verify' ) ) {
		return password_verify( $password, $hash );
	}

	return hash_equals( (string) $hash, hash( 'sha256', (string) $password ) );
}

/**
 * Generate a confirmation token.
 *
 * @return string
 */
function velotok_core_account_new_token() {
	if ( function_exists( 'random_bytes' ) ) {
		try {
			return bin2hex( random_bytes( 16 ) );
		} catch ( Exception $exception ) {
			unset( $exception );
		}
	}

	return uniqid( 'account_', true );
}

/**
 * Build the public representation of a stored user.
 *
 * @param array $user Stored user.
 * @return array<string,mixed>
 */
function velotok_core_account_public_user( $user ) {
	if ( ! is_array( $user ) ) {
		return array();
	}

	unset( $user['password_hash'], $user['confirmation_token'] );

	$user['authenticated'] = true;
	$user['guest']         = false;
	$user['mocked']        = true;
	$user['source']        = 'stub';
	$user['provider']      = 'stub';

	return $user;
}

/**
 * Find a stored user by email.
 *
 * @param string $email Email address.
 * @return array|null
 */
function velotok_core_account_find_user( $email ) {
	$key   = velotok_core_account_normalize_key( $email );
	$state = &velotok_core_accounts_state();

	if ( '' === $key || ! isset( $state['users'][ $key ] ) ) {
		return null;
	}

	return $state['users'][ $key ];
}

/**
 * Find a stored user by confirmation token.
 *
 * @param string $token Confirmation token.
 * @return array|null
 */
function velotok_core_account_find_by_token( $token ) {
	$token = is_scalar( $token ) ? (string) $token : '';

	if ( '' === $token ) {
		return null;
	}

	$state = &velotok_core_accounts_state();

	foreach ( $state['users'] as $user ) {
		if ( isset( $user['confirmation_token'] ) && hash_equals( (string) $user['confirmation_token'], $token ) ) {
			return $user;
		}
	}

	return null;
}

/**
 * Read a user by email without exposing the password hash.
 *
 * @param string $email Email address.
 * @return array|null
 */
function velotok_core_account_user( $email ) {
	$user = velotok_core_account_find_user( $email );

	if ( null === $user ) {
		return null;
	}

	$public = velotok_core_account_public_user( $user );
	$public['authenticated'] = velotok_core_account_has_session_for_user( $user['user_id'] );

	return $public;
}

/**
 * Get or create a session token for a user.
 *
 * @param array  $user  Stored user.
 * @param string $token Optional token.
 * @return string
 */
function velotok_core_account_create_session( $user, $token = '' ) {
	$state = &velotok_core_accounts_state();
	$token = is_scalar( $token ) && '' !== (string) $token ? (string) $token : velotok_core_account_new_token();
	$state['sessions'][ $token ] = array(
		'user_id' => (int) $user['user_id'],
		'email'   => $user['email'],
		'active'  => true,
		'mocked'  => true,
		'source'  => 'stub',
	);

	return $token;
}

/**
 * Remove a session token.
 *
 * @param string $token Session token.
 * @return bool
 */
function velotok_core_account_destroy_session( $token ) {
	$state = &velotok_core_accounts_state();
	$token = is_scalar( $token ) ? (string) $token : '';

	if ( '' === $token || ! isset( $state['sessions'][ $token ] ) ) {
		return false;
	}

	unset( $state['sessions'][ $token ] );

	return true;
}

/**
 * Check whether a user has at least one active session.
 *
 * @param int $user_id User identifier.
 * @return bool
 */
function velotok_core_account_has_session_for_user( $user_id ) {
	$state = &velotok_core_accounts_state();

	foreach ( $state['sessions'] as $session ) {
		if ( (int) $session['user_id'] === (int) $user_id && ! empty( $session['active'] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Build a login failure result.
 *
 * @param string $code    Error code.
 * @param array  $contract Accounts contract.
 * @return array<string,mixed>
 */
function velotok_core_account_failure( $code, $contract ) {
	$messages = array(
		$contract['error_codes']['email_required']       => 'Укажите email.',
		$contract['error_codes']['email_invalid']        => 'Укажите корректный email.',
		$contract['error_codes']['password_required']    => 'Укажите пароль.',
		$contract['error_codes']['password_too_short']   => 'Пароль слишком короткий.',
		$contract['error_codes']['email_not_confirmed']  => 'Подтвердите email, чтобы войти.',
		$contract['error_codes']['invalid_credentials']  => 'Неверный email или пароль.',
		$contract['error_codes']['confirmation_invalid'] => 'Ссылка подтверждения недействительна или устарела.',
		$contract['error_codes']['access_denied']        => 'Доступ к личному кабинету закрыт.',
		$contract['error_codes']['cart_transfer_failed'] => 'Не удалось перенести корзину.',
		$contract['error_codes']['cart_empty']           => 'Корзина пуста.',
	);

	return array(
		'valid'        => false,
		'authenticated' => false,
		'code'         => $code,
		'error'        => $code,
		'message'      => isset( $messages[ $code ] ) ? $messages[ $code ] : '',
		'mocked'       => true,
		'source'       => 'stub',
		'provider'     => 'stub',
	);
}

/**
 * Remove caller-supplied confirmation state from registration options.
 *
 * Registration always starts unconfirmed. Callers may provide a token for
 * deterministic tests, but they must not be able to mark the account as
 * confirmed, active or authenticated while registering.
 *
 * @param mixed $options Raw registration options.
 * @return array<string,mixed>
 */
function velotok_core_account_registration_options( $options ) {
	if ( ! is_array( $options ) ) {
		return array();
	}

	unset(
		$options['email_confirmed'],
		$options['email_verified'],
		$options['confirmed'],
		$options['status'],
		$options['account_status'],
		$options['authenticated'],
		$options['logged_in'],
		$options['session_token']
	);

	return $options;
}

/**
 * Register a user by email and password.
 *
 * Registration creates a pending account that cannot log in until the email is
 * confirmed. If the email already exists no duplicate is created: the result
 * points the visitor to the login flow and keeps the message generic so the
 * registration response does not reveal whether the account exists.
 *
 * @param mixed $email    Email address or request array.
 * @param mixed $password Plain password.
 * @param array $options  Mocked options: confirmation_token, name.
 * @return array<string,mixed>
 */
function velotok_core_account_register( $email = '', $password = '', $options = array() ) {
	if ( function_exists( 'velotok_core_wp_register_account' ) && function_exists( 'velotok_core_wp_accounts_available' ) && velotok_core_wp_accounts_available() ) {
		return velotok_core_wp_register_account( $email, $password, $options );
	}

	$contract = velotok_core_accounts_contract();

	if ( is_array( $email ) ) {
		$request  = $email;
		$email    = isset( $request['email'] ) ? $request['email'] : '';
		$password = isset( $request['password'] ) ? $request['password'] : '';
		$options  = isset( $request['options'] ) && is_array( $request['options'] ) ? $request['options'] : $options;
	}

	$options = velotok_core_account_registration_options( $options );

	$email    = is_scalar( $email ) ? trim( (string) $email ) : '';
	$password = is_scalar( $password ) ? (string) $password : '';
	$key      = velotok_core_account_normalize_key( $email );
	$state    = &velotok_core_accounts_state();

	$result = array(
		'valid'                 => false,
		'registered'            => false,
		'existing_user'         => false,
		'login_required'        => false,
		'confirmation_required' => true,
		'email_sent'            => false,
		'authenticated'         => false,
		'user'                  => null,
		'user_id'               => 0,
		'email'                 => $email,
		'status'                => null,
		'confirmation_token'    => null,
		'next_step'             => null,
		'login_url'             => '',
		'redirect_url'          => '',
		'redirected'            => false,
		'existing_email_strategy' => 'login',
		'code'                  => null,
		'error'                 => null,
		'message'               => '',
		'mocked'                => true,
		'source'                => 'stub',
		'provider'              => 'stub',
	);

	if ( '' === $email ) {
		return array_merge( $result, velotok_core_account_failure( $contract['error_codes']['email_required'], $contract ) );
	}

	if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) || '' === $key ) {
		return array_merge( $result, velotok_core_account_failure( $contract['error_codes']['email_invalid'], $contract ) );
	}

	if ( '' === $password ) {
		return array_merge( $result, velotok_core_account_failure( $contract['error_codes']['password_required'], $contract ) );
	}

	$min_length = (int) $contract['registration']['min_password_length'];

	if ( strlen( $password ) < $min_length ) {
		return array_merge( $result, velotok_core_account_failure( $contract['error_codes']['password_too_short'], $contract ) );
	}

	if ( isset( $state['users'][ $key ] ) ) {
		$login_url = isset( $contract['login']['target_url'] ) ? (string) $contract['login']['target_url'] : '/account/login/';

		$result['valid']                 = true;
		$result['registered']            = false;
		$result['existing_user']         = true;
		$result['login_required']        = true;
		$result['confirmation_required'] = false;
		$result['email_sent']            = true;
		$result['next_step']             = 'login';
		$result['login_url']             = $login_url;
		$result['redirect_url']          = $login_url;
		$result['redirected']            = false;
		$result['existing_email_strategy'] = 'login';
		$result['message']               = $contract['privacy']['generic_registration_message'];
		$result['user_id']               = (int) $state['users'][ $key ]['user_id'];
		$result['status']                = isset( $state['users'][ $key ]['status'] ) ? $state['users'][ $key ]['status'] : null;

		return $result;
	}

	$token = isset( $options['confirmation_token'] ) && is_scalar( $options['confirmation_token'] ) && '' !== (string) $options['confirmation_token']
		? (string) $options['confirmation_token']
		: velotok_core_account_new_token();

	$user_id = isset( $options['user_id'] ) && is_numeric( $options['user_id'] ) ? max( 1, (int) $options['user_id'] ) : (int) $state['next_id'];

	if ( (int) $state['next_id'] <= $user_id ) {
		$state['next_id'] = $user_id + 1;
	}

	$user = array(
		'user_id'            => $user_id,
		'email'              => $email,
		'name'               => isset( $options['name'] ) && is_scalar( $options['name'] ) ? trim( (string) $options['name'] ) : '',
		'status'             => $contract['confirmation']['pending_status'],
		'email_confirmed'    => false,
		'confirmation_token' => $token,
		'password_hash'      => velotok_core_account_hash_password( $password ),
		'registered'         => true,
		'mocked'             => true,
		'source'             => 'stub',
		'provider'           => 'stub',
	);

	$state['users'][ $key ] = $user;

	$result['valid']                 = true;
	$result['registered']            = true;
	$result['confirmation_required'] = true;
	$result['email_sent']            = true;
	$result['user']                  = velotok_core_account_public_user( $user );
	$result['user']['authenticated'] = false;
	$result['user_id']               = $user_id;
	$result['status']                = $user['status'];
	$result['confirmation_token']    = $token;
	$result['next_step']             = 'confirm_email';
	$result['message']               = 'Аккаунт создан. Подтвердите email по ссылке из письма.';

	return $result;
}

/**
 * Confirm a registration email and finish the registration flow.
 *
 * Accepts a token, an email plus token, or a request array. On success the
 * account becomes active, the session is created, the guest cart is
 * transferred when provided and the visitor is sent to checkout when the cart
 * contains items.
 *
 * @param mixed $token_or_email Confirmation token, email or request array.
 * @param mixed $token          Confirmation token when an email is passed first.
 * @param array $options        Mocked options: guest_cart, user_cart, session_token.
 * @return array<string,mixed>
 */
function velotok_core_account_confirm_email( $token_or_email = '', $token = '', $options = array() ) {
	if ( function_exists( 'velotok_core_wp_confirm_email' ) && function_exists( 'velotok_core_wp_accounts_available' ) && velotok_core_wp_accounts_available() ) {
		return velotok_core_wp_confirm_email( $token_or_email, $token, $options );
	}

	$contract = velotok_core_accounts_contract();
	$email    = '';
	$guest_cart = array();
	$user_cart  = array();

	if ( is_array( $token_or_email ) ) {
		$request      = $token_or_email;
		$token        = isset( $request['token'] ) ? $request['token'] : ( isset( $request['confirmation_token'] ) ? $request['confirmation_token'] : '' );
		$email        = isset( $request['email'] ) ? $request['email'] : '';
		$guest_cart   = isset( $request['guest_cart'] ) ? $request['guest_cart'] : array();
		$user_cart    = isset( $request['user_cart'] ) ? $request['user_cart'] : array();
		$options      = isset( $request['options'] ) && is_array( $request['options'] ) ? $request['options'] : $options;
	} else {
		$email = is_scalar( $token_or_email ) ? (string) $token_or_email : '';
	}

	if ( ! is_array( $options ) ) {
		$options = array();
	}

	if ( isset( $options['guest_cart'] ) ) {
		$guest_cart = $options['guest_cart'];
	}

	if ( isset( $options['user_cart'] ) ) {
		$user_cart = $options['user_cart'];
	}

	if ( '' === (string) $token && '' !== $email && false !== strpos( $email, '@' ) ) {
		$user = velotok_core_account_find_user( $email );

		if ( null !== $user && isset( $user['confirmation_token'] ) ) {
			$token = $user['confirmation_token'];
		}
	}

	if ( '' === (string) $token && '' !== $email && false === strpos( $email, '@' ) ) {
		$token = $email;
		$email = '';
	}

	$user = velotok_core_account_find_by_token( $token );

	if ( null === $user ) {
		$failure = velotok_core_account_failure( $contract['error_codes']['confirmation_invalid'], $contract );
		$failure['confirmed'] = false;

		return $failure;
	}

	$state = &velotok_core_accounts_state();
	$key   = velotok_core_account_normalize_key( $user['email'] );

	$state['users'][ $key ]['status']          = $contract['confirmation']['active_status'];
	$state['users'][ $key ]['email_confirmed'] = true;
	unset( $state['users'][ $key ]['confirmation_token'] );

	$user = $state['users'][ $key ];

	$session_token = isset( $options['session_token'] ) && is_scalar( $options['session_token'] ) && '' !== (string) $options['session_token']
		? (string) $options['session_token']
		: velotok_core_account_new_token();

	velotok_core_account_create_session( $user, $session_token );

	$transfer = null;
	$redirect = null;

	if ( ! empty( $guest_cart ) || ! empty( $user_cart ) ) {
		$transfer = velotok_core_account_transfer_guest_cart( $guest_cart, $user_cart, $user );
		$redirect = velotok_core_account_checkout_redirect( $transfer, $user, $options );
	}

	$result = array(
		'valid'              => true,
		'confirmed'          => true,
		'email_confirmed'    => true,
		'authenticated'      => true,
		'logged_in'          => true,
		'user'               => velotok_core_account_public_user( $user ),
		'user_id'            => (int) $user['user_id'],
		'email'              => $user['email'],
		'status'             => $user['status'],
		'session_token'      => $session_token,
		'transfer'           => $transfer,
		'redirect'           => $redirect,
		'redirected'         => null !== $redirect ? $redirect['redirected'] : false,
		'redirect_url'       => null !== $redirect ? $redirect['redirect_url'] : '',
		'checkout_url'       => null !== $redirect ? $redirect['checkout_url'] : '',
		'next_step'          => null !== $redirect && $redirect['valid'] ? 'checkout' : 'account',
		'code'               => null !== $redirect && ! $redirect['valid'] ? $redirect['code'] : null,
		'error'              => null !== $redirect && ! $redirect['valid'] ? $redirect['error'] : null,
		'mocked'             => true,
		'source'             => 'stub',
		'provider'           => 'stub',
	);

	return $result;
}

/**
 * Log in an existing confirmed user by email and password.
 *
 * A pending account cannot log in and cannot access the personal area. Invalid
 * credentials return the same generic answer whether or not the email exists.
 *
 * @param mixed $email    Email address or request array.
 * @param mixed $password Plain password.
 * @param array $options  Mocked options: guest_cart, user_cart, session_token.
 * @return array<string,mixed>
 */
function velotok_core_account_login( $email = '', $password = '', $options = array() ) {
	if ( function_exists( 'velotok_core_wp_login' ) && function_exists( 'velotok_core_wp_accounts_available' ) && velotok_core_wp_accounts_available() ) {
		return velotok_core_wp_login( $email, $password, $options );
	}

	$contract = velotok_core_accounts_contract();
	$guest_cart = array();
	$user_cart  = array();

	if ( is_array( $email ) ) {
		$request  = $email;
		$email    = isset( $request['email'] ) ? $request['email'] : '';
		$password = isset( $request['password'] ) ? $request['password'] : '';
		$options  = isset( $request['options'] ) && is_array( $request['options'] ) ? $request['options'] : $options;

		if ( isset( $request['guest_cart'] ) ) {
			$guest_cart = $request['guest_cart'];
		}

		if ( isset( $request['user_cart'] ) ) {
			$user_cart = $request['user_cart'];
		}
	}

	if ( ! is_array( $options ) ) {
		$options = array();
	}

	if ( isset( $options['guest_cart'] ) ) {
		$guest_cart = $options['guest_cart'];
	}

	if ( isset( $options['user_cart'] ) ) {
		$user_cart = $options['user_cart'];
	}

	$email    = is_scalar( $email ) ? trim( (string) $email ) : '';
	$password = is_scalar( $password ) ? (string) $password : '';
	$key      = velotok_core_account_normalize_key( $email );
	$state    = &velotok_core_accounts_state();

	$result = array(
		'valid'                  => false,
		'authenticated'          => false,
		'logged_in'              => false,
		'confirmation_required'  => false,
		'account_access'         => false,
		'existing_user'          => false,
		'user'                   => null,
		'user_id'                => 0,
		'email'                  => $email,
		'status'                 => null,
		'session_token'          => null,
		'transfer'               => null,
		'redirect'               => null,
		'redirected'             => false,
		'redirect_url'           => '',
		'checkout_url'           => '',
		'next_step'              => null,
		'code'                   => null,
		'error'                  => null,
		'message'                => '',
		'mocked'                 => true,
		'source'                 => 'stub',
		'provider'               => 'stub',
	);

	if ( '' === $email ) {
		return array_merge( $result, velotok_core_account_failure( $contract['error_codes']['email_required'], $contract ) );
	}

	if ( '' === $password ) {
		return array_merge( $result, velotok_core_account_failure( $contract['error_codes']['password_required'], $contract ) );
	}

	if ( ! isset( $state['users'][ $key ] ) ) {
		return array_merge( $result, velotok_core_account_failure( $contract['error_codes']['invalid_credentials'], $contract ) );
	}

	$user = $state['users'][ $key ];

	if ( ! velotok_core_account_verify_password( $password, $user['password_hash'] ) ) {
		return array_merge( $result, velotok_core_account_failure( $contract['error_codes']['invalid_credentials'], $contract ) );
	}

	if ( empty( $user['email_confirmed'] ) || $contract['confirmation']['active_status'] !== $user['status'] ) {
		$result['confirmation_required'] = true;
		$result['status']                = $user['status'];
		$result['next_step']             = 'confirm_email';

		return array_merge( $result, velotok_core_account_failure( $contract['error_codes']['email_not_confirmed'], $contract ) );
	}

	$session_token = isset( $options['session_token'] ) && is_scalar( $options['session_token'] ) && '' !== (string) $options['session_token']
		? (string) $options['session_token']
		: velotok_core_account_new_token();

	velotok_core_account_create_session( $user, $session_token );

	$transfer = null;
	$redirect = null;

	if ( ! empty( $guest_cart ) || ! empty( $user_cart ) ) {
		$transfer = velotok_core_account_transfer_guest_cart( $guest_cart, $user_cart, $user );
		$redirect = velotok_core_account_checkout_redirect( $transfer, $user, $options );
	}

	$result['valid']         = true;
	$result['authenticated'] = true;
	$result['logged_in']     = true;
	$result['existing_user'] = true;
	$result['user']          = velotok_core_account_public_user( $user );
	$result['user_id']       = (int) $user['user_id'];
	$result['status']        = $user['status'];
	$result['session_token'] = $session_token;
	$result['account_access'] = true;
	$result['transfer']      = $transfer;
	$result['redirect']      = $redirect;
	$result['redirected']    = null !== $redirect ? $redirect['redirected'] : false;
	$result['redirect_url']  = null !== $redirect ? $redirect['redirect_url'] : '';
	$result['checkout_url']  = null !== $redirect ? $redirect['checkout_url'] : '';
	$result['next_step']     = null !== $redirect && $redirect['valid'] ? 'checkout' : 'account';

	if ( null !== $redirect && ! $redirect['valid'] ) {
		$result['code']  = $redirect['code'];
		$result['error'] = $redirect['error'];
	}

	return $result;
}

/**
 * Return the logged-in user for a session token.
 *
 * @param mixed $session Session token or request array.
 * @return array|null
 */
function velotok_core_account_current_user( $session = '' ) {
	if ( function_exists( 'velotok_core_wp_current_user' ) && function_exists( 'velotok_core_wp_accounts_available' ) && velotok_core_wp_accounts_available() ) {
		return velotok_core_wp_current_user( $session );
	}

	if ( is_array( $session ) ) {
		$session = isset( $session['session_token'] ) ? $session['session_token'] : '';
	}

	$session = is_scalar( $session ) ? (string) $session : '';

	if ( '' === $session ) {
		return null;
	}

	$state = &velotok_core_accounts_state();

	if ( ! isset( $state['sessions'][ $session ] ) || empty( $state['sessions'][ $session ]['active'] ) ) {
		return null;
	}

	$session_data = $state['sessions'][ $session ];
	$key          = velotok_core_account_normalize_key( $session_data['email'] );

	if ( ! isset( $state['users'][ $key ] ) ) {
		return null;
	}

	$user = $state['users'][ $key ];
	$user['session_token'] = $session;

	return $user;
}

/**
 * Check whether a session belongs to a confirmed and logged-in user.
 *
 * @param mixed $session Session token or request array.
 * @return bool
 */
function velotok_core_account_is_logged_in( $session = '' ) {
	$user = velotok_core_account_current_user( $session );

	return null !== $user && ! empty( $user['email_confirmed'] );
}

/**
 * End a session on logout.
 *
 * @param mixed $session Session token or request array.
 * @return array<string,mixed>
 */
function velotok_core_account_logout( $session = '' ) {
	if ( function_exists( 'velotok_core_wp_logout' ) && function_exists( 'velotok_core_wp_accounts_available' ) && velotok_core_wp_accounts_available() ) {
		return velotok_core_wp_logout( $session );
	}

	if ( is_array( $session ) ) {
		$session = isset( $session['session_token'] ) ? $session['session_token'] : '';
	}

	$session = is_scalar( $session ) ? (string) $session : '';
	$removed = velotok_core_account_destroy_session( $session );
	$user    = '' !== $session ? velotok_core_account_current_user( $session ) : null;

	return array(
		'valid'         => true,
		'logged_out'    => true,
		'logout'        => true,
		'authenticated' => false,
		'logged_in'     => false,
		'account_access' => false,
		'session_token' => null,
		'session_ended' => $removed || null !== $user,
		'user'          => null,
		'next_step'     => 'login',
		'mocked'        => true,
		'source'        => 'stub',
		'provider'      => 'stub',
	);
}

/**
 * Check access to the personal area.
 *
 * Guests, unconfirmed users and logged-out sessions are denied. The result is
 * explicit so templates and tests can observe the blocked personal area.
 *
 * @param mixed $subject Session token, email or request array.
 * @return array<string,mixed>
 */
function velotok_core_account_can_access( $subject = '' ) {
	if ( function_exists( 'velotok_core_wp_can_access_account' ) && function_exists( 'velotok_core_wp_accounts_available' ) && velotok_core_wp_accounts_available() ) {
		return velotok_core_wp_can_access_account( $subject );
	}

	$contract = velotok_core_accounts_contract();
	$session  = '';
	$email    = '';
	$user     = null;

	if ( is_array( $subject ) ) {
		if ( isset( $subject['session_token'] ) ) {
			$session = $subject['session_token'];
		}

		if ( isset( $subject['email'] ) ) {
			$email = $subject['email'];
		}

		if ( isset( $subject['user'] ) && is_array( $subject['user'] ) ) {
			$user = $subject['user'];
		}
	} elseif ( is_string( $subject ) && false !== strpos( $subject, '@' ) ) {
		$email = $subject;
	} elseif ( is_scalar( $subject ) ) {
		$session = (string) $subject;
	}

	if ( null === $user && '' !== $session ) {
		$user = velotok_core_account_current_user( $session );
	}

	if ( null === $user && '' !== $email ) {
		$stored = velotok_core_account_find_user( $email );

		if ( null !== $stored && velotok_core_account_has_session_for_user( $stored['user_id'] ) ) {
			$user = $stored;
		}
	}

	$confirmed = null !== $user && ! empty( $user['email_confirmed'] ) && $contract['confirmation']['active_status'] === $user['status'];
	$allowed   = $confirmed && ( ! isset( $user['authenticated'] ) || ! empty( $user['authenticated'] ) );

	return array(
		'valid'          => $allowed,
		'allowed'        => $allowed,
		'access'         => $allowed,
		'account_access' => $allowed,
		'authenticated'  => $allowed,
		'confirmed'      => $confirmed,
		'user'           => $allowed && null !== $user ? velotok_core_account_public_user( $user ) : null,
		'user_id'        => $allowed && null !== $user ? (int) $user['user_id'] : 0,
		'redirect_url'   => $allowed ? '' : '/account/login/',
		'next_step'      => $allowed ? 'account' : 'login',
		'code'           => $allowed ? null : $contract['error_codes']['access_denied'],
		'error'          => $allowed ? null : $contract['error_codes']['access_denied'],
		'mocked'         => true,
		'source'         => 'stub',
		'provider'       => 'stub',
	);
}

/**
 * Transfer a guest cart into a user cart after registration or login.
 *
 * @param mixed $guest_cart Guest cart.
 * @param mixed $user_cart  Existing user cart.
 * @param array $user       User data.
 * @return array<string,mixed>
 */
function velotok_core_account_transfer_guest_cart( $guest_cart = array(), $user_cart = array(), $user = array() ) {
	$contract = velotok_core_accounts_contract();

	if ( function_exists( 'velotok_core_cart_transfer_guest_cart' ) ) {
		$cart = velotok_core_cart_transfer_guest_cart( $guest_cart, $user_cart );
	} else {
		$cart = array(
			'items' => is_array( $user_cart ) && isset( $user_cart['items'] ) && is_array( $user_cart['items'] )
				? $user_cart['items']
				: array(),
		);
	}

	$cart['authenticated']        = true;
	$cart['guest']                = false;
	$cart['user_id']              = isset( $user['user_id'] ) && is_numeric( $user['user_id'] ) ? (int) $user['user_id'] : 0;
	$cart['transfer_mocked']      = true;
	$cart['transfer_strategy']    = $contract['cart']['merge_strategy'];
	$cart['guest_cart_cleared']   = true;
	$cart['mocked']               = true;
	$cart['source']               = 'stub';
	$cart['provider']             = 'stub';

	return $cart;
}

/**
 * Build the checkout redirect after registration or login.
 *
 * @param mixed $cart    Cart after transfer.
 * @param array $user    User data.
 * @param array $options Reserved options.
 * @return array<string,mixed>
 */
function velotok_core_account_checkout_redirect( $cart = array(), $user = array(), $options = array() ) {
	$contract  = velotok_core_accounts_contract();
	$has_items = function_exists( 'velotok_core_cart_has_items' ) ? velotok_core_cart_has_items( $cart ) : false;
	$allowed   = $contract['checkout']['requires_items'] ? $has_items : true;

	return array(
		'valid'        => $allowed,
		'redirect'     => $allowed,
		'redirected'   => $allowed,
		'target'       => $contract['checkout']['target'],
		'redirect_url' => $allowed ? $contract['checkout']['target_url'] : '',
		'checkout_url' => $allowed ? $contract['checkout']['target_url'] : '',
		'url'          => $allowed ? $contract['checkout']['target_url'] : '',
		'has_items'    => $has_items,
		'user_id'      => isset( $user['user_id'] ) && is_numeric( $user['user_id'] ) ? (int) $user['user_id'] : 0,
		'code'         => $allowed ? null : $contract['checkout']['empty_error'],
		'error'        => $allowed ? null : $contract['checkout']['empty_error'],
		'mocked'       => true,
		'source'       => 'stub',
		'provider'     => 'stub',
	);
}

if ( ! function_exists( 'velotok_core_register_account' ) ) {
	/**
	 * Alias for registering an account.
	 *
	 * @param mixed $email    Email or request array.
	 * @param mixed $password Password.
	 * @param array $options  Options.
	 * @return array<string,mixed>
	 */
	function velotok_core_register_account( $email = '', $password = '', $options = array() ) {
		return velotok_core_account_register( $email, $password, $options );
	}
}

if ( ! function_exists( 'velotok_core_account_signup' ) ) {
	/**
	 * Alias for account registration.
	 *
	 * @param mixed $email    Email or request array.
	 * @param mixed $password Password.
	 * @param array $options  Options.
	 * @return array<string,mixed>
	 */
	function velotok_core_account_signup( $email = '', $password = '', $options = array() ) {
		return velotok_core_account_register( $email, $password, $options );
	}
}

if ( ! function_exists( 'velotok_core_account_confirm' ) ) {
	/**
	 * Alias for email confirmation.
	 *
	 * @param mixed $token_or_email Token, email or request array.
	 * @param mixed $token          Token.
	 * @param array $options        Options.
	 * @return array<string,mixed>
	 */
	function velotok_core_account_confirm( $token_or_email = '', $token = '', $options = array() ) {
		return velotok_core_account_confirm_email( $token_or_email, $token, $options );
	}
}

if ( ! function_exists( 'velotok_core_account_login_user' ) ) {
	/**
	 * Alias for account login.
	 *
	 * @param mixed $email    Email or request array.
	 * @param mixed $password Password.
	 * @param array $options  Options.
	 * @return array<string,mixed>
	 */
	function velotok_core_account_login_user( $email = '', $password = '', $options = array() ) {
		return velotok_core_account_login( $email, $password, $options );
	}
}

if ( ! function_exists( 'velotok_core_logout' ) ) {
	/**
	 * Alias for ending an account session.
	 *
	 * @param mixed $session Session token or request array.
	 * @return array<string,mixed>
	 */
	function velotok_core_logout( $session = '' ) {
		return velotok_core_account_logout( $session );
	}
}

if ( ! function_exists( 'velotok_core_account_has_access' ) ) {
	/**
	 * Alias for the personal area access check.
	 *
	 * @param mixed $subject Session, email or request array.
	 * @return array<string,mixed>
	 */
	function velotok_core_account_has_access( $subject = '' ) {
		return velotok_core_account_can_access( $subject );
	}
}

if ( ! defined( 'VELOTOK_ACCOUNTS_SKIP_WP_INTEGRATION' ) && ( function_exists( 'add_action' ) || function_exists( 'wp_insert_user' ) || function_exists( 'wp_create_user' ) || function_exists( 'wc_create_new_customer' ) ) && is_readable( __DIR__ . '/accounts-wordpress.php' ) ) {
	require_once __DIR__ . '/accounts-wordpress.php';
}
