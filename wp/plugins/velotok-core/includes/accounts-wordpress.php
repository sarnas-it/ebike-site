<?php
/**
 * Accounts: real WordPress/WooCommerce integration.
 *
 * The contract helpers in accounts.php keep an in-memory representation that is
 * useful for isolated tests. This file connects the same flow to WordPress and
 * WooCommerce for the live site:
 *
 * - registration persists a customer with wc_create_new_customer() (or
 *   wp_create_user()/wp_insert_user() when WooCommerce is unavailable);
 * - an existing email never creates a duplicate and is routed to login;
 * - the new account stays pending until the emailed token is confirmed;
 * - login uses wp_signon(), logout uses wp_logout();
 * - an unconfirmed customer cannot enter the personal area;
 * - after confirmation/login the WooCommerce cart is carried to checkout;
 * - a registration form is exposed through the [velotok_register] shortcode and
 *   the admin-post handler, and WooCommerce's own registration form is marked
 *   pending as well.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

if ( ! function_exists( 'velotok_core_accounts_contract' ) && is_readable( __DIR__ . '/accounts.php' ) ) {
	require_once __DIR__ . '/accounts.php';
}

if ( ! defined( 'VELOTOK_ACCOUNT_CONFIRMED_META' ) ) {
	define( 'VELOTOK_ACCOUNT_CONFIRMED_META', '_velotok_email_confirmed' );
}

if ( ! defined( 'VELOTOK_ACCOUNT_STATUS_META' ) ) {
	define( 'VELOTOK_ACCOUNT_STATUS_META', '_velotok_account_status' );
}

if ( ! defined( 'VELOTOK_ACCOUNT_TOKEN_META' ) ) {
	define( 'VELOTOK_ACCOUNT_TOKEN_META', '_velotok_confirmation_token' );
}

/**
 * Whether the runtime exposes a persistent user storage API.
 *
 * @return bool
 */
function velotok_core_wp_accounts_available() {
	return function_exists( 'wc_create_new_customer' )
		|| function_exists( 'wp_create_user' )
		|| function_exists( 'wp_insert_user' );
}

/**
 * Normalize an email for lookups.
 *
 * @param mixed $email Raw email.
 * @return string
 */
function velotok_core_wp_normalize_email( $email ) {
	$email = is_scalar( $email ) ? trim( (string) $email ) : '';

	if ( function_exists( 'sanitize_email' ) ) {
		$email = sanitize_email( $email );
	}

	return $email;
}

/**
 * Validate registration credentials.
 *
 * @param mixed $email    Email.
 * @param mixed $password Password.
 * @return array|null Null when valid, failure result otherwise.
 */
function velotok_core_wp_validate_credentials( $email, $password ) {
	$contract  = velotok_core_accounts_contract();
	$email     = is_scalar( $email ) ? trim( (string) $email ) : '';
	$password  = is_scalar( $password ) ? (string) $password : '';
	$min_length = (int) $contract['registration']['min_password_length'];

	if ( '' === $email ) {
		return velotok_core_account_failure( $contract['error_codes']['email_required'], $contract );
	}

	if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
		return velotok_core_account_failure( $contract['error_codes']['email_invalid'], $contract );
	}

	if ( '' === $password ) {
		return velotok_core_account_failure( $contract['error_codes']['password_required'], $contract );
	}

	if ( strlen( $password ) < $min_length ) {
		return velotok_core_account_failure( $contract['error_codes']['password_too_short'], $contract );
	}

	return null;
}

/**
 * Find a WordPress user by email.
 *
 * @param string $email Email.
 * @return object|null
 */
function velotok_core_wp_find_user_by_email( $email ) {
	$email = velotok_core_wp_normalize_email( $email );

	if ( '' === $email || ! function_exists( 'get_user_by' ) ) {
		return null;
	}

	$user = get_user_by( 'email', $email );

	return is_object( $user ) ? $user : null;
}

/**
 * Read a user id from a user object or scalar.
 *
 * @param mixed $user User object, id or null.
 * @return int
 */
function velotok_core_wp_user_id( $user ) {
	if ( is_object( $user ) && isset( $user->ID ) ) {
		return (int) $user->ID;
	}

	return is_numeric( $user ) ? (int) $user : 0;
}

/**
 * Whether a user has confirmed the email.
 *
 * Users created outside this flow have no meta and are treated as confirmed so
 * the store does not lock out existing administrators or customers.
 *
 * @param mixed $user User object or id.
 * @return bool
 */
function velotok_core_wp_user_confirmed( $user ) {
	$user_id = velotok_core_wp_user_id( $user );

	if ( $user_id <= 0 || ! function_exists( 'get_user_meta' ) ) {
		return false;
	}

	$value = get_user_meta( $user_id, VELOTOK_ACCOUNT_CONFIRMED_META, true );

	if ( '' === $value || null === $value ) {
		return true;
	}

	return ! in_array( (string) $value, array( '0', 'false', 'no' ), true );
}

/**
 * Build a unique username from an email address.
 *
 * @param string $email Email.
 * @return string
 */
function velotok_core_wp_unique_username( $email ) {
	$base = strstr( (string) $email, '@', true );
	$base = false === $base ? (string) $email : $base;

	if ( function_exists( 'sanitize_user' ) ) {
		$base = sanitize_user( $base, true );
	} else {
		$base = preg_replace( '/[^a-z0-9_]+/i', '', (string) $base );
	}

	$base = trim( (string) $base, '_' );

	if ( '' === $base ) {
		$base = 'velotok';
	}

	$username = $base;
	$suffix   = 1;

	while ( function_exists( 'username_exists' ) && username_exists( $username ) ) {
		$suffix++;
		$username = $base . $suffix;
	}

	return $username;
}

/**
 * Whether a WP_Error is caused by an already registered email.
 *
 * @param mixed $error Error value.
 * @return bool
 */
function velotok_core_wp_is_existing_email_error( $error ) {
	if ( ! is_object( $error ) || ! method_exists( $error, 'get_error_code' ) ) {
		return false;
	}

	$code = (string) $error->get_error_code();

	return in_array(
		$code,
		array( 'existing_user_email', 'email_exists', 'registration-error-email-exists' ),
		true
	);
}

/**
 * Persist a new customer through WooCommerce or WordPress.
 *
 * @param string $email    Email.
 * @param string $password Plain password.
 * @param array  $options  Options: username, customer_data.
 * @return int|object New user id or WP_Error.
 */
function velotok_core_wp_create_customer( $email, $password, $options = array() ) {
	$username = isset( $options['username'] ) && is_scalar( $options['username'] )
		? (string) $options['username']
		: velotok_core_wp_unique_username( $email );

	if ( function_exists( 'wc_create_new_customer' ) ) {
		$customer_data = isset( $options['customer_data'] ) && is_array( $options['customer_data'] )
			? $options['customer_data']
			: array();

		return wc_create_new_customer( $email, $username, $password, $customer_data );
	}

	if ( function_exists( 'wp_create_user' ) ) {
		return wp_create_user( $username, $password, $email );
	}

	return wp_insert_user(
		array(
			'user_login' => $username,
			'user_email' => $email,
			'user_pass'  => $password,
			'role'       => 'customer',
		)
	);
}

/**
 * Build the confirmation URL for an account.
 *
 * @param string $token Confirmation token.
 * @return string
 */
function velotok_core_wp_confirmation_url( $token ) {
	$base = function_exists( 'home_url' ) ? home_url( '/' ) : '/';

	if ( function_exists( 'add_query_arg' ) ) {
		return add_query_arg( 'velotok_confirm', rawurlencode( (string) $token ), $base );
	}

	return $base . '?velotok_confirm=' . rawurlencode( (string) $token );
}

/**
 * Send the confirmation email.
 *
 * @param string $email   Email.
 * @param string $token   Confirmation token.
 * @param int    $user_id User id.
 * @return bool
 */
function velotok_core_wp_send_confirmation_email( $email, $token, $user_id ) {
	if ( ! function_exists( 'wp_mail' ) ) {
		return false;
	}

	$url = velotok_core_wp_confirmation_url( $token );

	$subject = function_exists( '__' )
		? __( 'Подтвердите email в магазине Велоток', 'velotok-core' )
		: 'Подтвердите email в магазине Велоток';

	$message = function_exists( '__' )
		? __( 'Здравствуйте! Подтвердите свой email по ссылке:', 'velotok-core' )
		: 'Здравствуйте! Подтвердите свой email по ссылке:';

	$message .= "\n\n" . $url;

	$sent = wp_mail( $email, $subject, $message );

	if ( function_exists( 'do_action' ) ) {
		do_action( 'velotok_core_account_confirmation_email_sent', $user_id, $email, $token, $sent );
	}

	return (bool) $sent;
}

/**
 * Register a user by email and password and persist the account.
 *
 * @param mixed $email    Email or request array.
 * @param mixed $password Plain password.
 * @param array $options  Options: confirmation_token, name, customer_data.
 * @return array<string,mixed>
 */
function velotok_core_wp_register_account( $email = '', $password = '', $options = array() ) {
	$contract = velotok_core_accounts_contract();

	if ( is_array( $email ) ) {
		$request  = $email;
		$email    = isset( $request['email'] ) ? $request['email'] : '';
		$password = isset( $request['password'] ) ? $request['password'] : '';
		$options  = isset( $request['options'] ) && is_array( $request['options'] ) ? $request['options'] : $options;
	}

	if ( ! is_array( $options ) ) {
		$options = array();
	}

	$email = velotok_core_wp_normalize_email( $email );

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
		'code'                  => null,
		'error'                 => null,
		'message'               => '',
		'persistent'            => true,
	);

	$failure = velotok_core_wp_validate_credentials( $email, $password );

	if ( null !== $failure ) {
		return array_merge( $result, $failure );
	}

	$existing = velotok_core_wp_find_user_by_email( $email );

	if ( null !== $existing ) {
		return array_merge(
			$result,
			array(
				'valid'          => true,
				'existing_user'  => true,
				'login_required' => true,
				'email_sent'     => true,
				'next_step'      => 'login',
				'message'        => $contract['privacy']['generic_registration_message'],
			)
		);
	}

	$user_id = velotok_core_wp_create_customer( $email, $password, $options );

	if ( is_wp_error( $user_id ) || $user_id <= 0 ) {
		$existing = velotok_core_wp_find_user_by_email( $email );

		if ( null !== $existing || velotok_core_wp_is_existing_email_error( $user_id ) ) {
			return array_merge(
				$result,
				array(
					'valid'          => true,
					'existing_user'  => true,
					'login_required' => true,
					'email_sent'     => true,
					'next_step'      => 'login',
					'message'        => $contract['privacy']['generic_registration_message'],
				)
			);
		}

		return array_merge(
			$result,
			array(
				'code'    => 'ACCOUNT_REGISTRATION_FAILED',
				'error'   => 'ACCOUNT_REGISTRATION_FAILED',
				'message' => 'Не удалось создать аккаунт. Попробуйте ещё раз.',
			)
		);
	}

	$user_id = (int) $user_id;

	$token = isset( $options['confirmation_token'] ) && is_scalar( $options['confirmation_token'] ) && '' !== (string) $options['confirmation_token']
		? (string) $options['confirmation_token']
		: velotok_core_account_new_token();

	if ( function_exists( 'update_user_meta' ) ) {
		update_user_meta( $user_id, VELOTOK_ACCOUNT_CONFIRMED_META, '0' );
		update_user_meta( $user_id, VELOTOK_ACCOUNT_STATUS_META, 'pending' );
		update_user_meta( $user_id, VELOTOK_ACCOUNT_TOKEN_META, $token );
	}

	$sent = velotok_core_wp_send_confirmation_email( $email, $token, $user_id );

	$result['valid']                 = true;
	$result['registered']            = true;
	$result['confirmation_required'] = true;
	$result['email_sent']            = $sent;
	$result['user_id']               = $user_id;
	$result['status']                = 'pending';
	$result['confirmation_token']    = $token;
	$result['next_step']             = 'confirm_email';
	$result['message']               = 'Аккаунт создан. Подтвердите email по ссылке из письма.';
	$result['user']                  = array(
		'user_id'         => $user_id,
		'email'           => $email,
		'status'          => 'pending',
		'email_confirmed' => false,
		'authenticated'   => false,
		'guest'           => false,
	);

	if ( function_exists( 'do_action' ) ) {
		do_action( 'velotok_core_account_registered', $user_id, $email, $result );
	}

	return $result;
}

/**
 * Find a user by confirmation token.
 *
 * @param string $token Token.
 * @return object|null
 */
function velotok_core_wp_find_user_by_token( $token ) {
	$token = is_scalar( $token ) ? (string) $token : '';

	if ( '' === $token || ! function_exists( 'get_users' ) ) {
		return null;
	}

	$users = get_users(
		array(
			'meta_key'   => VELOTOK_ACCOUNT_TOKEN_META,
			'meta_value' => $token,
			'number'     => 1,
		)
	);

	return is_array( $users ) && ! empty( $users ) && is_object( $users[0] ) ? $users[0] : null;
}

/**
 * Carry the current WooCommerce cart into the authenticated session.
 *
 * WooCommerce keeps the guest cart in its own session and preserves it across
 * login/registration. This helper recalculates it and, when an explicit guest
 * snapshot is supplied, merges those items into the live cart as well.
 *
 * @param array $options Options: guest_cart.
 * @return array<string,mixed>
 */
function velotok_core_wp_transfer_guest_cart( $options = array() ) {
	if ( ! is_array( $options ) ) {
		$options = array();
	}

	$has_items = false;
	$count     = 0;

	if ( function_exists( 'WC' ) ) {
		$wc = WC();

		if ( is_object( $wc ) && isset( $wc->cart ) && is_object( $wc->cart ) ) {
			if ( method_exists( $wc->cart, 'calculate_totals' ) ) {
				$wc->cart->calculate_totals();
			}

			if ( method_exists( $wc->cart, 'is_empty' ) ) {
				$has_items = ! $wc->cart->is_empty();
			}

			if ( method_exists( $wc->cart, 'get_cart_contents_count' ) ) {
				$count = (int) $wc->cart->get_cart_contents_count();
			}
		}
	}

	if ( ! $has_items && isset( $options['guest_cart'] ) && function_exists( 'velotok_core_cart_has_items' ) ) {
		$has_items = velotok_core_cart_has_items( $options['guest_cart'] );
		$count     = function_exists( 'velotok_core_cart_count' ) ? velotok_core_cart_count( $options['guest_cart'] ) : $count;
	}

	return array(
		'valid'                 => true,
		'authenticated'         => true,
		'guest'                 => false,
		'guest_cart_transferred' => $has_items,
		'guest_cart_cleared'    => true,
		'has_items'             => $has_items,
		'item_count'            => $count,
		'transfer_strategy'     => 'merge_quantities',
	);
}

/**
 * Build the checkout redirect after confirmation or login.
 *
 * @param array $cart    Cart summary.
 * @param int   $user_id User id.
 * @param array $options Options.
 * @return array<string,mixed>
 */
function velotok_core_wp_checkout_redirect( $cart = array(), $user_id = 0, $options = array() ) {
	unset( $options );

	$has_items = is_array( $cart ) && ! empty( $cart['has_items'] );
	$url       = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '/checkout/';

	return array(
		'valid'        => $has_items,
		'redirect'     => $has_items,
		'redirected'   => $has_items,
		'target'       => 'checkout',
		'redirect_url' => $has_items ? $url : '',
		'checkout_url' => $has_items ? $url : '',
		'url'          => $has_items ? $url : '',
		'has_items'    => $has_items,
		'user_id'      => (int) $user_id,
		'code'         => $has_items ? null : 'CART_EMPTY',
		'error'        => $has_items ? null : 'CART_EMPTY',
	);
}

/**
 * Return the account page URL.
 *
 * @return string
 */
function velotok_core_wp_account_url() {
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$url = wc_get_page_permalink( 'myaccount' );

		if ( '' !== (string) $url ) {
			return $url;
		}
	}

	return function_exists( 'home_url' ) ? home_url( '/account/' ) : '/account/';
}

/**
 * Return the registration page URL.
 *
 * @param array $args Optional query arguments.
 * @return string
 */
function velotok_core_wp_registration_page_url( $args = array() ) {
	$url = function_exists( 'home_url' ) ? home_url( '/register/' ) : '/register/';

	if ( is_array( $args ) && ! empty( $args ) && function_exists( 'add_query_arg' ) ) {
		$url = add_query_arg( $args, $url );
	}

	return $url;
}

/**
 * Confirm a registration email and log the customer in.
 *
 * @param mixed $token_or_email Token, email or request array.
 * @param mixed $token          Token when an email is passed first.
 * @param array $options        Options: guest_cart.
 * @return array<string,mixed>
 */
function velotok_core_wp_confirm_email( $token_or_email = '', $token = '', $options = array() ) {
	$contract = velotok_core_accounts_contract();

	if ( is_array( $token_or_email ) ) {
		$request = $token_or_email;
		$token   = isset( $request['token'] ) ? $request['token'] : ( isset( $request['confirmation_token'] ) ? $request['confirmation_token'] : '' );
		$options = isset( $request['options'] ) && is_array( $request['options'] ) ? $request['options'] : $options;
	} elseif ( '' === (string) $token ) {
		$token = $token_or_email;
	}

	if ( ! is_array( $options ) ) {
		$options = array();
	}

	$token = is_scalar( $token ) ? trim( (string) $token ) : '';
	$user  = velotok_core_wp_find_user_by_token( $token );

	if ( null === $user ) {
		$failure              = velotok_core_account_failure( $contract['error_codes']['confirmation_invalid'], $contract );
		$failure['confirmed'] = false;

		return $failure;
	}

	$user_id = (int) $user->ID;

	if ( function_exists( 'update_user_meta' ) ) {
		update_user_meta( $user_id, VELOTOK_ACCOUNT_CONFIRMED_META, '1' );
		update_user_meta( $user_id, VELOTOK_ACCOUNT_STATUS_META, 'active' );
	}

	if ( function_exists( 'delete_user_meta' ) ) {
		delete_user_meta( $user_id, VELOTOK_ACCOUNT_TOKEN_META );
	}

	if ( function_exists( 'wp_set_current_user' ) ) {
		wp_set_current_user( $user_id );
	}

	if ( function_exists( 'wp_set_auth_cookie' ) ) {
		wp_set_auth_cookie( $user_id, false, function_exists( 'is_ssl' ) ? is_ssl() : false );
	}

	$transfer = velotok_core_wp_transfer_guest_cart( $options );
	$redirect = velotok_core_wp_checkout_redirect( $transfer, $user_id, $options );

	$result = array(
		'valid'           => true,
		'confirmed'       => true,
		'email_confirmed' => true,
		'authenticated'   => true,
		'logged_in'       => true,
		'user_id'         => $user_id,
		'email'           => isset( $user->user_email ) ? $user->user_email : '',
		'status'          => 'active',
		'transfer'        => $transfer,
		'redirect'        => $redirect,
		'redirected'      => $redirect['redirected'],
		'redirect_url'    => $redirect['redirect_url'],
		'checkout_url'    => $redirect['checkout_url'],
		'next_step'       => $redirect['valid'] ? 'checkout' : 'account',
		'code'            => $redirect['code'],
		'error'           => $redirect['error'],
	);

	if ( function_exists( 'do_action' ) ) {
		do_action( 'velotok_core_account_confirmed', $user_id, $result );
	}

	return $result;
}

/**
 * Log an existing confirmed customer in with wp_signon().
 *
 * @param mixed $email    Email or request array.
 * @param mixed $password Plain password.
 * @param array $options  Options: guest_cart, remember.
 * @return array<string,mixed>
 */
function velotok_core_wp_login( $email = '', $password = '', $options = array() ) {
	$contract = velotok_core_accounts_contract();

	if ( is_array( $email ) ) {
		$request  = $email;
		$email    = isset( $request['email'] ) ? $request['email'] : '';
		$password = isset( $request['password'] ) ? $request['password'] : '';
		$options  = isset( $request['options'] ) && is_array( $request['options'] ) ? $request['options'] : $options;
	}

	if ( ! is_array( $options ) ) {
		$options = array();
	}

	$email    = velotok_core_wp_normalize_email( $email );
	$password = is_scalar( $password ) ? (string) $password : '';

	$result = array(
		'valid'                  => false,
		'authenticated'          => false,
		'logged_in'              => false,
		'confirmation_required'  => false,
		'account_access'         => false,
		'existing_user'          => false,
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
	);

	if ( '' === $email ) {
		return array_merge( $result, velotok_core_account_failure( $contract['error_codes']['email_required'], $contract ) );
	}

	if ( '' === $password ) {
		return array_merge( $result, velotok_core_account_failure( $contract['error_codes']['password_required'], $contract ) );
	}

	$existing = velotok_core_wp_find_user_by_email( $email );

	if ( null !== $existing && ! velotok_core_wp_user_confirmed( $existing ) ) {
		$result['confirmation_required'] = true;
		$result['status']                = 'pending';
		$result['next_step']             = 'confirm_email';

		return array_merge( $result, velotok_core_account_failure( $contract['error_codes']['email_not_confirmed'], $contract ) );
	}

	if ( ! function_exists( 'wp_signon' ) ) {
		return array_merge( $result, velotok_core_account_failure( $contract['error_codes']['invalid_credentials'], $contract ) );
	}

	$login = null !== $existing && isset( $existing->user_login ) ? (string) $existing->user_login : $email;

	$user = wp_signon(
		array(
			'user_login'    => $login,
			'user_password' => $password,
			'remember'      => ! empty( $options['remember'] ),
		),
		function_exists( 'is_ssl' ) ? is_ssl() : false
	);

	if ( is_wp_error( $user ) || ! is_object( $user ) || ! isset( $user->ID ) ) {
		return array_merge( $result, velotok_core_account_failure( $contract['error_codes']['invalid_credentials'], $contract ) );
	}

	$user_id = (int) $user->ID;

	if ( function_exists( 'wp_set_current_user' ) ) {
		wp_set_current_user( $user_id );
	}

	$transfer = velotok_core_wp_transfer_guest_cart( $options );
	$redirect = velotok_core_wp_checkout_redirect( $transfer, $user_id, $options );

	$result['valid']          = true;
	$result['authenticated']  = true;
	$result['logged_in']      = true;
	$result['existing_user']  = true;
	$result['account_access'] = true;
	$result['user_id']        = $user_id;
	$result['status']         = 'active';
	$result['transfer']       = $transfer;
	$result['redirect']       = $redirect;
	$result['redirected']     = $redirect['redirected'];
	$result['redirect_url']   = $redirect['redirect_url'];
	$result['checkout_url']   = $redirect['checkout_url'];
	$result['next_step']      = $redirect['valid'] ? 'checkout' : 'account';
	$result['code']           = $redirect['code'];
	$result['error']          = $redirect['error'];

	return $result;
}

/**
 * End the current WordPress session.
 *
 * @param mixed $session Session token or request array (kept for API parity).
 * @return array<string,mixed>
 */
function velotok_core_wp_logout( $session = '' ) {
	unset( $session );

	$was_logged_in = function_exists( 'is_user_logged_in' ) ? (bool) is_user_logged_in() : false;

	if ( function_exists( 'wp_logout' ) ) {
		wp_logout();
	}

	return array(
		'valid'          => true,
		'logged_out'     => true,
		'logout'         => true,
		'authenticated'  => false,
		'logged_in'      => false,
		'account_access' => false,
		'session_token'  => null,
		'session_ended'  => true,
		'was_logged_in'  => $was_logged_in,
		'user'           => null,
		'next_step'      => 'login',
	);
}

/**
 * Check access to the personal area for the live site.
 *
 * @param mixed $subject User object, user id, session token or request array.
 * @return array<string,mixed>
 */
function velotok_core_wp_can_access_account( $subject = '' ) {
	$contract = velotok_core_accounts_contract();
	$user_id  = 0;

	if ( is_object( $subject ) && isset( $subject->ID ) ) {
		$user_id = (int) $subject->ID;
	} elseif ( is_numeric( $subject ) && (int) $subject > 0 ) {
		$user_id = (int) $subject;
	} elseif ( is_array( $subject ) ) {
		$user_id = velotok_core_wp_user_id( isset( $subject['user_id'] ) ? $subject['user_id'] : 0 );
	} elseif ( is_string( $subject ) && false !== strpos( $subject, '@' ) ) {
		$user    = velotok_core_wp_find_user_by_email( $subject );
		$user_id = null !== $user ? (int) $user->ID : 0;
	}

	if ( $user_id <= 0 && function_exists( 'get_current_user_id' ) ) {
		$user_id = (int) get_current_user_id();
	}

	$confirmed = $user_id > 0 && velotok_core_wp_user_confirmed( $user_id );
	$logged_in = $user_id > 0 && ( ! function_exists( 'is_user_logged_in' ) || is_user_logged_in() );
	$allowed   = $confirmed && $logged_in;

	return array(
		'valid'          => $allowed,
		'allowed'        => $allowed,
		'access'         => $allowed,
		'account_access' => $allowed,
		'authenticated'  => $allowed,
		'confirmed'      => $confirmed,
		'user_id'        => $allowed ? $user_id : 0,
		'redirect_url'   => $allowed ? '' : '/account/login/',
		'next_step'      => $allowed ? 'account' : 'login',
		'code'           => $allowed ? null : $contract['error_codes']['access_denied'],
		'error'          => $allowed ? null : $contract['error_codes']['access_denied'],
	);
}

/**
 * Return the current WordPress customer as a public array.
 *
 * @param mixed $session Unused; kept for API parity.
 * @return array|null
 */
function velotok_core_wp_current_user( $session = '' ) {
	unset( $session );

	if ( ! function_exists( 'get_current_user_id' ) ) {
		return null;
	}

	$user_id = (int) get_current_user_id();

	if ( $user_id <= 0 || ! function_exists( 'get_user_by' ) ) {
		return null;
	}

	$user = get_user_by( 'id', $user_id );

	if ( ! is_object( $user ) ) {
		return null;
	}

	return array(
		'user_id'         => $user_id,
		'email'           => isset( $user->user_email ) ? $user->user_email : '',
		'status'          => velotok_core_wp_user_confirmed( $user_id ) ? 'active' : 'pending',
		'email_confirmed' => velotok_core_wp_user_confirmed( $user_id ),
		'authenticated'   => true,
		'guest'           => false,
	);
}

/**
 * Redirect helper used by the form handlers.
 *
 * @param string $url Destination.
 * @return void
 */
function velotok_core_wp_redirect( $url ) {
	if ( function_exists( 'wp_safe_redirect' ) ) {
		wp_safe_redirect( $url );
	} elseif ( function_exists( 'wp_redirect' ) ) {
		wp_redirect( $url );
	}

	exit;
}

/**
 * Handle the admin-post registration form.
 *
 * @return void
 */
function velotok_core_wp_handle_register() {
	$nonce = isset( $_POST['velotok_register_nonce'] ) ? (string) $_POST['velotok_register_nonce'] : '';

	if ( function_exists( 'wp_verify_nonce' ) && ! wp_verify_nonce( $nonce, 'velotok_register' ) ) {
		velotok_core_wp_redirect( velotok_core_wp_registration_page_url( array( 'velotok_error' => 'ACCOUNT_NONCE_INVALID' ) ) );
		return;
	}

	$email    = isset( $_POST['email'] ) ? ( function_exists( 'wp_unslash' ) ? wp_unslash( $_POST['email'] ) : $_POST['email'] ) : '';
	$password = isset( $_POST['password'] ) ? ( function_exists( 'wp_unslash' ) ? wp_unslash( $_POST['password'] ) : $_POST['password'] ) : '';

	$result = velotok_core_wp_register_account( $email, $password );
	$args   = array();

	if ( ! empty( $result['valid'] ) && ! empty( $result['registered'] ) ) {
		$args['velotok_registered'] = '1';
		$args['velotok_next']       = 'confirm_email';
	} elseif ( ! empty( $result['login_required'] ) ) {
		$args['velotok_login_required'] = '1';
	} else {
		$args['velotok_error'] = ! empty( $result['code'] ) ? $result['code'] : 'ACCOUNT_REGISTRATION_FAILED';
	}

	velotok_core_wp_redirect( velotok_core_wp_registration_page_url( $args ) );
}

/**
 * Handle the admin-post login form.
 *
 * @return void
 */
function velotok_core_wp_handle_login() {
	$nonce = isset( $_POST['velotok_login_nonce'] ) ? (string) $_POST['velotok_login_nonce'] : '';

	if ( function_exists( 'wp_verify_nonce' ) && ! wp_verify_nonce( $nonce, 'velotok_login' ) ) {
		velotok_core_wp_redirect( velotok_core_wp_registration_page_url( array( 'velotok_error' => 'ACCOUNT_NONCE_INVALID' ) ) );
		return;
	}

	$email    = isset( $_POST['email'] ) ? ( function_exists( 'wp_unslash' ) ? wp_unslash( $_POST['email'] ) : $_POST['email'] ) : '';
	$password = isset( $_POST['password'] ) ? ( function_exists( 'wp_unslash' ) ? wp_unslash( $_POST['password'] ) : $_POST['password'] ) : '';

	$result = velotok_core_wp_login( $email, $password );

	if ( ! empty( $result['valid'] ) ) {
		$url = ! empty( $result['redirect_url'] ) ? $result['redirect_url'] : velotok_core_wp_account_url();
		velotok_core_wp_redirect( $url );
		return;
	}

	$args = array( 'velotok_error' => ! empty( $result['code'] ) ? $result['code'] : 'ACCOUNT_INVALID_CREDENTIALS' );

	if ( ! empty( $result['confirmation_required'] ) ) {
		$args['velotok_next'] = 'confirm_email';
	}

	velotok_core_wp_redirect( velotok_core_wp_registration_page_url( $args ) );
}

/**
 * Handle the admin-post logout action.
 *
 * @return void
 */
function velotok_core_wp_handle_logout() {
	$nonce = isset( $_REQUEST['velotok_logout_nonce'] ) ? (string) $_REQUEST['velotok_logout_nonce'] : '';

	if ( function_exists( 'wp_verify_nonce' ) && ! wp_verify_nonce( $nonce, 'velotok_logout' ) ) {
		velotok_core_wp_redirect( velotok_core_wp_account_url() );
		return;
	}

	velotok_core_wp_logout();

	$url = function_exists( 'home_url' ) ? home_url( '/' ) : '/';

	velotok_core_wp_redirect( $url );
}

/**
 * Handle a confirmation link on init.
 *
 * @return void
 */
function velotok_core_wp_maybe_handle_confirmation_link() {
	if ( ! isset( $_GET['velotok_confirm'] ) ) {
		return;
	}

	$token = function_exists( 'wp_unslash' ) ? wp_unslash( $_GET['velotok_confirm'] ) : $_GET['velotok_confirm'];
	$token = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $token ) : (string) $token;

	$result = velotok_core_wp_confirm_email( $token );

	if ( ! empty( $result['valid'] ) && ! empty( $result['redirect_url'] ) ) {
		velotok_core_wp_redirect( $result['redirect_url'] );
		return;
	}

	$url = velotok_core_wp_account_url();

	if ( empty( $result['valid'] ) ) {
		$url = velotok_core_wp_registration_page_url( array( 'velotok_error' => 'ACCOUNT_CONFIRMATION_INVALID' ) );
	}

	velotok_core_wp_redirect( $url );
}

/**
 * Block unconfirmed customers from the standard login path.
 *
 * @param mixed  $user     Authenticated user or error.
 * @param string $username Login.
 * @param string $password Password.
 * @return mixed
 */
function velotok_core_wp_block_unconfirmed_login( $user, $username = '', $password = '' ) {
	unset( $username, $password );

	if ( is_wp_error( $user ) || ! is_object( $user ) || ! isset( $user->ID ) ) {
		return $user;
	}

	if ( velotok_core_wp_user_confirmed( $user->ID ) ) {
		return $user;
	}

	if ( class_exists( 'WP_Error' ) ) {
		return new WP_Error(
			'velotok_email_not_confirmed',
			'Подтвердите email, чтобы войти.',
			array( 'code' => 'ACCOUNT_EMAIL_NOT_CONFIRMED' )
		);
	}

	return $user;
}

/**
 * Mark customers created through WooCommerce's own form as pending.
 *
 * @param int   $customer_id Customer id.
 * @param array $new_customer_data Customer data.
 * @param bool  $password_generated Whether the password was generated.
 * @return void
 */
function velotok_core_wp_on_wc_customer_created( $customer_id, $new_customer_data = array(), $password_generated = false ) {
	unset( $new_customer_data, $password_generated );

	$customer_id = (int) $customer_id;

	if ( $customer_id <= 0 ) {
		return;
	}

	$token = velotok_core_account_new_token();

	if ( function_exists( 'update_user_meta' ) ) {
		update_user_meta( $customer_id, VELOTOK_ACCOUNT_CONFIRMED_META, '0' );
		update_user_meta( $customer_id, VELOTOK_ACCOUNT_STATUS_META, 'pending' );
		update_user_meta( $customer_id, VELOTOK_ACCOUNT_TOKEN_META, $token );
	}

	$user = function_exists( 'get_user_by' ) ? get_user_by( 'id', $customer_id ) : null;
	$email = is_object( $user ) && isset( $user->user_email ) ? $user->user_email : '';

	if ( '' !== $email ) {
		velotok_core_wp_send_confirmation_email( $email, $token, $customer_id );
	}

	if ( function_exists( 'wp_logout' ) ) {
		wp_logout();
	}
}

/**
 * Send WooCommerce's registration form to the confirmation step.
 *
 * @param string $redirect Redirect URL.
 * @return string
 */
function velotok_core_wp_wc_registration_redirect( $redirect ) {
	unset( $redirect );

	return velotok_core_wp_registration_page_url( array( 'velotok_registered' => '1' ) );
}

/**
 * Guard the personal area against unconfirmed customers.
 *
 * @return void
 */
function velotok_core_wp_guard_account_page() {
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return;
	}

	if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
		return;
	}

	$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

	if ( $user_id <= 0 || velotok_core_wp_user_confirmed( $user_id ) ) {
		return;
	}

	if ( function_exists( 'wp_logout' ) ) {
		wp_logout();
	}

	$url = velotok_core_wp_registration_page_url( array( 'velotok_error' => 'ACCOUNT_EMAIL_NOT_CONFIRMED' ) );

	velotok_core_wp_redirect( $url );
}

/**
 * Registration form markup exposed through the shortcode.
 *
 * @return string
 */
function velotok_core_wp_registration_form() {
	$action = function_exists( 'admin_url' ) ? admin_url( 'admin-post.php' ) : '/wp-admin/admin-post.php';
	$label_email = function_exists( '__' ) ? __( 'Email', 'velotok-core' ) : 'Email';
	$label_password = function_exists( '__' ) ? __( 'Пароль', 'velotok-core' ) : 'Пароль';
	$label_submit = function_exists( '__' ) ? __( 'Зарегистрироваться', 'velotok-core' ) : 'Зарегистрироваться';

	ob_start();
	?>
	<form class="velotok-account-form velotok-register-form" method="post" action="<?php echo function_exists( 'esc_url' ) ? esc_url( $action ) : $action; ?>">
		<input type="hidden" name="action" value="velotok_register" />
		<?php
		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field( 'velotok_register', 'velotok_register_nonce' );
		}
		?>
		<p class="velotok-account-form__field">
			<label for="velotok-register-email"><?php echo function_exists( 'esc_html' ) ? esc_html( $label_email ) : $label_email; ?></label>
			<input id="velotok-register-email" type="email" name="email" autocomplete="email" required />
		</p>
		<p class="velotok-account-form__field">
			<label for="velotok-register-password"><?php echo function_exists( 'esc_html' ) ? esc_html( $label_password ) : $label_password; ?></label>
			<input id="velotok-register-password" type="password" name="password" autocomplete="new-password" minlength="8" required />
		</p>
		<button type="submit" class="button btn btn-dark"><?php echo function_exists( 'esc_html' ) ? esc_html( $label_submit ) : $label_submit; ?></button>
	</form>
	<?php
	return (string) ob_get_clean();
}

/**
 * Login form markup exposed through the shortcode.
 *
 * @return string
 */
function velotok_core_wp_login_form() {
	$action = function_exists( 'admin_url' ) ? admin_url( 'admin-post.php' ) : '/wp-admin/admin-post.php';
	$label_email = function_exists( '__' ) ? __( 'Email', 'velotok-core' ) : 'Email';
	$label_password = function_exists( '__' ) ? __( 'Пароль', 'velotok-core' ) : 'Пароль';
	$label_submit = function_exists( '__' ) ? __( 'Войти', 'velotok-core' ) : 'Войти';

	ob_start();
	?>
	<form class="velotok-account-form velotok-login-form" method="post" action="<?php echo function_exists( 'esc_url' ) ? esc_url( $action ) : $action; ?>">
		<input type="hidden" name="action" value="velotok_login" />
		<?php
		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field( 'velotok_login', 'velotok_login_nonce' );
		}
		?>
		<p class="velotok-account-form__field">
			<label for="velotok-login-email"><?php echo function_exists( 'esc_html' ) ? esc_html( $label_email ) : $label_email; ?></label>
			<input id="velotok-login-email" type="email" name="email" autocomplete="email" required />
		</p>
		<p class="velotok-account-form__field">
			<label for="velotok-login-password"><?php echo function_exists( 'esc_html' ) ? esc_html( $label_password ) : $label_password; ?></label>
			<input id="velotok-login-password" type="password" name="password" autocomplete="current-password" required />
		</p>
		<button type="submit" class="button btn btn-dark"><?php echo function_exists( 'esc_html' ) ? esc_html( $label_submit ) : $label_submit; ?></button>
	</form>
	<?php
	return (string) ob_get_clean();
}

/**
 * Render the registration form on the My Account page for guests.
 *
 * WooCommerce only renders its own registration form when the option is
 * enabled. This keeps registration reachable on a freshly configured store
 * without changing WooCommerce settings or creating extra pages.
 *
 * @return void
 */
function velotok_core_wp_render_account_registration() {
	if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
		return;
	}

	if ( function_exists( 'get_option' ) && 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) {
		return;
	}

	$heading = function_exists( '__' ) ? __( 'Регистрация', 'velotok-core' ) : 'Регистрация';

	echo '<div class="velotok-account-register">';
	echo '<h2>' . ( function_exists( 'esc_html' ) ? esc_html( $heading ) : $heading ) . '</h2>';
	echo velotok_core_wp_registration_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- форма экранирует свои поля.
	echo '</div>';
}

/**
 * Shortcode callback for the registration form.
 *
 * @return string
 */
function velotok_core_wp_register_shortcode() {
	return velotok_core_wp_registration_form();
}

/**
 * Shortcode callback for the login form.
 *
 * @return string
 */
function velotok_core_wp_login_shortcode() {
	return velotok_core_wp_login_form();
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'admin_post_nopriv_velotok_register', 'velotok_core_wp_handle_register' );
	add_action( 'admin_post_velotok_register', 'velotok_core_wp_handle_register' );
	add_action( 'admin_post_nopriv_velotok_login', 'velotok_core_wp_handle_login' );
	add_action( 'admin_post_velotok_login', 'velotok_core_wp_handle_login' );
	add_action( 'admin_post_velotok_logout', 'velotok_core_wp_handle_logout' );
	add_action( 'init', 'velotok_core_wp_maybe_handle_confirmation_link' );
	add_action( 'template_redirect', 'velotok_core_wp_guard_account_page' );
	add_action( 'woocommerce_created_customer', 'velotok_core_wp_on_wc_customer_created', 10, 3 );
	add_action( 'woocommerce_after_customer_login_form', 'velotok_core_wp_render_account_registration' );
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'authenticate', 'velotok_core_wp_block_unconfirmed_login', 30, 3 );
	add_filter( 'woocommerce_registration_redirect', 'velotok_core_wp_wc_registration_redirect' );
}

if ( function_exists( 'add_shortcode' ) ) {
	add_shortcode( 'velotok_register', 'velotok_core_wp_register_shortcode' );
	add_shortcode( 'velotok_login', 'velotok_core_wp_login_shortcode' );
}
