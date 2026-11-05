<?php
/**
 * Guest cart and registration stub contract.
 *
 * This is the single access point for the guest cart scenario: an
 * unauthenticated visitor collects products, presses «Купить», the store
 * registers the visitor, transfers the guest cart into the new account and
 * sends the visitor to checkout.
 *
 * The current iteration deliberately uses fixed in-memory data: it does not
 * read environment variables, files, the database or remote services and it
 * does not persist the cart or the account between requests. Real
 * authentication, WooCommerce customer creation and checkout routing are out
 * of scope for this iteration.
 *
 * Spec: clarify-30af92ca (requirement TESTEB-78).
 * Parent spec: ecommerce-store.
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
 * Return the guest cart registration contract.
 *
 * The contract documents:
 * - the spec identifier, the explicit `mocked` marker and the stub source;
 * - the guest cart that an unauthenticated visitor can fill;
 * - the «Купить» action that starts registration;
 * - the transfer and merge rules applied after registration;
 * - the redirect to checkout after the cart has been transferred;
 * - the open questions that block a real registration and checkout flow.
 *
 * @return array<string,mixed>
 */
function velotok_core_guest_cart_registration_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$open_questions = array(
		'authentication'  => 'Каким способом аутентифицируется пользователь при регистрации (email/пароль, соцсети) и является ли подтверждение email обязательным?',
		'existing_user'   => 'Как система должна обрабатывать нажатие «Купить», если пользователь с введённым email уже существует (вход, ошибка, отдельный сценарий)?',
		'merge_rules'     => 'Каковы правила слияния дублирующихся позиций при объединении гостевой корзины с существующей корзиной пользователя (сложение количеств, приоритет одной из корзин, ограничение по максимуму)?',
		'checkout_target' => 'Какая страница является целевой при переходе к оформлению заказа и каково поведение пользователя при неуспешной регистрации за пределами сохранения гостевой корзины?',
		'local_run'       => 'Какие команды используются для локального запуска проекта для проверки (например, `docker compose up`)?',
	);

	$contract = array(
		'spec'                    => 'clarify-30af92ca',
		'parent_spec'             => 'ecommerce-store',
		'requirement'             => 'TESTEB-78',
		'cart_spec'               => 'clarify-30af92ca',
		'checkout_spec'           => 'clarify-096bb945',
		'parent_relation'         => 'Сценарий гостевой корзины реализует требование родительской спецификации ecommerce-store без реальной интеграции с WooCommerce.',
		'requirements'            => array(
			'guest_cart'             => 'Система SHALL позволять неавторизованному пользователю собирать корзину',
			'register_on_buy'        => 'Система SHALL при нажатии «Купить» регистрировать пользователя',
			'transfer_after_signup'  => 'Система SHALL переносить гостевую корзину в аккаунт пользователя после регистрации',
			'redirect_to_checkout'   => 'Система SHALL переводить пользователя к оформлению заказа после переноса корзины',
		),
		'guest_cart_allowed'      => true,
		'registers_on_buy'        => true,
		'transfers_after_registration' => true,
		'redirects_to_checkout'   => true,
		'source'                  => 'stub',
		'provider'                => 'stub',
		'provider_id'             => 'stub',
		'provider_label'          => 'Stub guest cart registration provider',
		'mocked'                  => true,
		'network'                 => false,
		'persistent'              => false,
		'operation'               => 'guest_cart_register_transfer_checkout',
		'operations'              => array(
			'create_guest_cart'      => 'velotok_core_guest_cart_create',
			'add_item'               => 'velotok_core_guest_cart_add',
			'view_guest_cart'        => 'velotok_core_cart_items',
			'register'               => 'velotok_core_guest_cart_register',
			'transfer_cart'          => 'velotok_core_guest_cart_transfer',
			'buy'                    => 'velotok_core_guest_cart_buy',
			'redirect_to_checkout'   => 'velotok_core_guest_cart_checkout_redirect',
			'transfer_guest_cart'    => 'velotok_core_cart_transfer_guest_cart',
		),
		'guest_cart'              => array(
			'enabled'                 => true,
			'allow_unauthenticated'   => true,
			'authentication_required' => false,
			'accumulate_items'        => true,
			'storage'                 => 'session',
			'identifier'              => 'session_token',
			'persistent'              => false,
			'mocked'                  => true,
			'source'                  => 'stub',
			'supports'                => array( 'add', 'update', 'remove', 'view' ),
			'empty_allowed'           => true,
			'merge_strategy'          => 'merge_quantities',
		),
		'buy_action'              => array(
			'trigger'                 => 'buy',
			'label'                   => 'Купить',
			'registers_user'          => true,
			'requires_authentication' => false,
			'registration_first'      => true,
			'transfer_after_registration' => true,
			'redirect_after_transfer' => true,
			'mocked'                  => true,
			'source'                  => 'stub',
		),
		'registration'            => array(
			'enabled'                 => true,
			'triggered_by'            => 'buy',
			'creates_account'         => true,
			'required_fields'         => array( 'email' ),
			'optional_fields'         => array( 'password', 'name' ),
			'password_source'         => 'open_question',
			'email_confirmation'      => 'open_question',
			'email_confirmation_required' => null,
			'existing_user_strategy'  => 'open_question',
			'session_created'         => true,
			'persistent'              => false,
			'mocked'                  => true,
			'source'                  => 'stub',
		),
		'transfer'                => array(
			'enabled'                 => true,
			'on_registration'         => true,
			'on_login'                => true,
			'source'                  => 'guest_cart',
			'target'                  => 'user_cart',
			'merge_strategy'          => 'merge_quantities',
			'merge_by'                => 'product_id',
			'preserve_user_cart'      => true,
			'clear_guest_after_transfer' => true,
			'max_quantity'            => 99,
			'persistent'              => false,
			'mocked'                  => true,
		),
		'redirect'                => array(
			'enabled'                 => true,
			'after_transfer'          => true,
			'target'                  => 'checkout',
			'target_page'             => 'checkout',
			'target_url'              => '/checkout/',
			'requires_items'          => true,
			'empty_error'             => 'CART_EMPTY',
			'mocked'                  => true,
			'source'                  => 'stub',
		),
		'checkout_redirect'       => array(
			'enabled'                 => true,
			'after_transfer'          => true,
			'target'                  => 'checkout',
			'target_page'             => 'checkout',
			'target_url'              => '/checkout/',
			'requires_items'          => true,
			'empty_error'             => 'CART_EMPTY',
			'mocked'                  => true,
			'source'                  => 'stub',
		),
		'error_codes'             => array(
			'empty_cart'                 => 'CART_EMPTY',
			'guest_cart_empty'           => 'GUEST_CART_EMPTY',
			'registration_email_required' => 'REGISTRATION_EMAIL_REQUIRED',
			'registration_email_invalid' => 'REGISTRATION_EMAIL_INVALID',
			'registration_email_exists'  => 'REGISTRATION_EMAIL_EXISTS',
			'transfer_failed'            => 'GUEST_CART_TRANSFER_FAILED',
			'checkout_unavailable'       => 'CHECKOUT_UNAVAILABLE',
		),
		'empty_error'             => 'CART_EMPTY',
		'open_questions'          => $open_questions,
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer an explicit stub contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_guest_cart_registration_stub_contract() {
	return velotok_core_guest_cart_registration_contract();
}

/**
 * Alias kept for callers that use the data contract wording.
 *
 * @return array<string,mixed>
 */
function velotok_core_guest_cart_registration_data_contract() {
	return velotok_core_guest_cart_registration_contract();
}

/**
 * Short alias for the guest cart registration contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_guest_cart_contract() {
	return velotok_core_guest_cart_registration_contract();
}

/**
 * Return the open questions that block a real guest cart flow.
 *
 * @return array<string,string>
 */
function velotok_core_guest_cart_open_questions() {
	$contract = velotok_core_guest_cart_registration_contract();

	return $contract['open_questions'];
}

/**
 * Create an empty guest cart.
 *
 * @param array<int,array<string,mixed>> $items Optional initial items.
 * @return array<string,mixed>
 */
function velotok_core_guest_cart_create( $items = array() ) {
	if ( function_exists( 'velotok_core_cart_guest' ) ) {
		$cart = velotok_core_cart_guest( $items );
	} else {
		$cart = array( 'items' => array() );
	}

	$cart['guest']         = true;
	$cart['authenticated'] = false;
	$cart['user_id']       = 0;
	$cart['mocked']        = true;
	$cart['source']        = 'stub';
	$cart['provider']      = 'stub';

	return $cart;
}

/**
 * Add a product to a guest cart.
 *
 * Accepts either a cart followed by a product or a product directly. The short
 * form starts a new guest cart, mirroring the cart helper.
 *
 * @param mixed $guest_cart Guest cart array or product data.
 * @param mixed $product    Product slug, identifier, name or data.
 * @param mixed $quantity   Quantity.
 * @return array<string,mixed>
 */
function velotok_core_guest_cart_add( $guest_cart = array(), $product = null, $quantity = 1 ) {
	if ( ! function_exists( 'velotok_core_cart_is_cart' ) || ! velotok_core_cart_is_cart( $guest_cart ) ) {
		if ( is_numeric( $product ) ) {
			$quantity = $product;
		} elseif ( is_array( $product ) && isset( $product['quantity'] ) && is_numeric( $product['quantity'] ) ) {
			$quantity = $product['quantity'];
		} else {
			$quantity = 1;
		}

		$product    = $guest_cart;
		$guest_cart = velotok_core_guest_cart_create();
	}

	$cart = velotok_core_cart_add_item( $guest_cart, $product, $quantity );

	$cart['guest']         = true;
	$cart['authenticated'] = false;
	$cart['user_id']       = 0;
	$cart['mocked']        = true;
	$cart['source']        = 'stub';
	$cart['provider']      = 'stub';

	return $cart;
}

/**
 * Normalize a guest registration request.
 *
 * @param mixed $request Request array or email string.
 * @return array<string,mixed>
 */
function velotok_core_guest_cart_registration_request( $request ) {
	if ( is_string( $request ) ) {
		return array( 'email' => $request );
	}

	return is_array( $request ) ? $request : array();
}

/**
 * Register a user from the guest state using mocked in-memory data.
 *
 * A new account is created for a valid email. When the email is already in the
 * mocked `existing_users` option, the strategy stays an open question: the
 * caller may pass `existing_user_strategy => 'error'` to get
 * REGISTRATION_EMAIL_EXISTS, otherwise the existing account is used for the
 * continuation of the checkout flow.
 *
 * @param mixed $request    Registration request or email string.
 * @param mixed $guest_cart Guest cart to carry through registration.
 * @param array $options    Mocked options: existing_users, existing_user_strategy, user_id.
 * @return array<string,mixed>
 */
function velotok_core_guest_cart_register( $request = array(), $guest_cart = array(), $options = array() ) {
	$contract = velotok_core_guest_cart_registration_contract();
	$request  = velotok_core_guest_cart_registration_request( $request );

	if ( ! is_array( $options ) ) {
		$options = array();
	}

	$email = isset( $request['email'] ) && is_scalar( $request['email'] ) ? trim( (string) $request['email'] ) : '';
	$name  = isset( $request['name'] ) && is_scalar( $request['name'] ) ? trim( (string) $request['name'] ) : '';

	$result = array(
		'valid'               => false,
		'registered'          => false,
		'registered_user'     => false,
		'login'               => false,
		'authenticated'       => false,
		'existing_user'       => false,
		'user'                => null,
		'user_id'             => 0,
		'email'               => $email,
		'guest_cart_preserved' => true,
		'guest_cart'          => $guest_cart,
		'mocked'              => true,
		'source'              => 'stub',
		'provider'            => 'stub',
		'code'                => null,
		'error'               => null,
	);

	if ( '' === $email ) {
		$result['code']  = $contract['error_codes']['registration_email_required'];
		$result['error'] = $result['code'];

		return $result;
	}

	if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
		$result['code']  = $contract['error_codes']['registration_email_invalid'];
		$result['error'] = $result['code'];

		return $result;
	}

	$existing_users = isset( $options['existing_users'] ) && is_array( $options['existing_users'] )
		? $options['existing_users']
		: array();
	$existing       = null;

	foreach ( $existing_users as $candidate ) {
		if ( is_array( $candidate ) && isset( $candidate['email'] ) && velotok_core_cart_normalize_key( $candidate['email'] ) === velotok_core_cart_normalize_key( $email ) ) {
			$existing = $candidate;
			break;
		}

		if ( is_string( $candidate ) && velotok_core_cart_normalize_key( $candidate ) === velotok_core_cart_normalize_key( $email ) ) {
			$existing = array( 'email' => $candidate );
			break;
		}
	}

	if ( null !== $existing ) {
		$strategy = isset( $options['existing_user_strategy'] ) && is_scalar( $options['existing_user_strategy'] )
			? (string) $options['existing_user_strategy']
			: 'open_question';

		if ( 'error' === $strategy ) {
			$result['code']  = $contract['error_codes']['registration_email_exists'];
			$result['error'] = $result['code'];

			return $result;
		}

		$user = array_merge(
			$existing,
			array(
				'email'    => $email,
				'mocked'   => true,
				'source'   => 'stub',
				'guest'    => false,
				'registered' => false,
			)
		);

		$result['valid']           = true;
		$result['login']           = true;
		$result['authenticated']   = true;
		$result['existing_user']   = true;
		$result['user']            = $user;
		$result['user_id']         = isset( $user['user_id'] ) && is_numeric( $user['user_id'] ) ? (int) $user['user_id'] : 0;

		return $result;
	}

	$user_id = isset( $options['user_id'] ) && is_numeric( $options['user_id'] )
		? max( 1, (int) $options['user_id'] )
		: count( $existing_users ) + 1;

	$user = array(
		'user_id'        => $user_id,
		'email'          => $email,
		'name'           => $name,
		'registered'     => true,
		'authenticated'  => true,
		'guest'          => false,
		'mocked'         => true,
		'source'         => 'stub',
		'provider'       => 'stub',
	);

	$result['valid']           = true;
	$result['registered']      = true;
	$result['registered_user'] = true;
	$result['authenticated']   = true;
	$result['user']            = $user;
	$result['user_id']         = $user_id;

	return $result;
}

/**
 * Transfer a guest cart into a user cart after registration.
 *
 * @param mixed $guest_cart Guest cart array.
 * @param mixed $user_cart  Existing user cart array.
 * @param array $user       Registered user data.
 * @return array<string,mixed>
 */
function velotok_core_guest_cart_transfer( $guest_cart, $user_cart = array(), $user = array() ) {
	$contract = velotok_core_guest_cart_registration_contract();

	if ( ! function_exists( 'velotok_core_cart_transfer_guest_cart' ) ) {
		return array(
			'valid'     => false,
			'code'      => $contract['error_codes']['transfer_failed'],
			'error'     => $contract['error_codes']['transfer_failed'],
			'mocked'    => true,
			'source'    => 'stub',
			'provider'  => 'stub',
		);
	}

	$cart = velotok_core_cart_transfer_guest_cart( $guest_cart, $user_cart );

	$cart['authenticated']        = true;
	$cart['guest']                = false;
	$cart['user_id']              = isset( $user['user_id'] ) && is_numeric( $user['user_id'] ) ? (int) $user['user_id'] : 0;
	$cart['transfer_mocked']      = true;
	$cart['transfer_strategy']    = $contract['transfer']['merge_strategy'];
	$cart['mocked']               = true;
	$cart['source']               = 'stub';
	$cart['provider']             = 'stub';
	$cart['code']                 = null;
	$cart['error']                = null;

	return $cart;
}

/**
 * Build the mocked redirect to checkout after the guest cart transfer.
 *
 * @param mixed $cart    Cart after transfer.
 * @param array $user    Registered user data.
 * @param array $options Reserved options.
 * @return array<string,mixed>
 */
function velotok_core_guest_cart_checkout_redirect( $cart = array(), $user = array(), $options = array() ) {
	$contract = velotok_core_guest_cart_registration_contract();
	$redirect = $contract['redirect'];
	$has_items = function_exists( 'velotok_core_cart_has_items' ) ? velotok_core_cart_has_items( $cart ) : false;
	$allowed   = true === $redirect['requires_items'] ? $has_items : true;

	return array(
		'valid'          => $allowed,
		'redirect'       => $allowed,
		'redirected'     => $allowed,
		'target'         => $redirect['target'],
		'target_page'    => $redirect['target_page'],
		'redirect_url'   => $allowed ? $redirect['target_url'] : '',
		'checkout_url'   => $allowed ? $redirect['target_url'] : '',
		'url'            => $allowed ? $redirect['target_url'] : '',
		'after_transfer' => true,
		'requires_items' => true,
		'has_items'      => $has_items,
		'user_id'        => isset( $user['user_id'] ) && is_numeric( $user['user_id'] ) ? (int) $user['user_id'] : 0,
		'code'           => $allowed ? null : $redirect['empty_error'],
		'error'          => $allowed ? null : $redirect['empty_error'],
		'mocked'         => true,
		'source'         => 'stub',
		'provider'       => 'stub',
	);
}

/**
 * Run the complete «Купить» flow: register, transfer and redirect.
 *
 * On registration failure the guest cart is left untouched so the visitor can
 * retry or continue shopping. On an empty cart registration still succeeds but
 * the checkout redirect returns CART_EMPTY.
 *
 * @param mixed $email      Registration request, email string or request array.
 * @param mixed $guest_cart Guest cart array.
 * @param mixed $user_cart  Existing user cart array.
 * @param array $options    Mocked registration options.
 * @return array<string,mixed>
 */
function velotok_core_guest_cart_buy( $email = '', $guest_cart = array(), $user_cart = array(), $options = array() ) {
	$contract = velotok_core_guest_cart_registration_contract();

	if ( is_array( $email ) ) {
		$request = $email;
		$email   = isset( $request['email'] ) ? $request['email'] : '';

		if ( isset( $request['guest_cart'] ) ) {
			$guest_cart = $request['guest_cart'];
		}

		if ( isset( $request['user_cart'] ) ) {
			$user_cart = $request['user_cart'];
		}

		if ( isset( $request['options'] ) ) {
			$options = $request['options'];
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

	$registration = velotok_core_guest_cart_register( $email, $guest_cart, $options );
	$result       = array(
		'valid'          => false,
		'registered'     => $registration['registered'],
		'authenticated'  => $registration['authenticated'],
		'existing_user'  => $registration['existing_user'],
		'user'           => $registration['user'],
		'user_id'        => $registration['user_id'],
		'registration'   => $registration,
		'guest_cart'     => $guest_cart,
		'cart'           => $guest_cart,
		'transfer'       => null,
		'redirect'       => null,
		'redirected'     => false,
		'redirect_url'   => '',
		'checkout_url'   => '',
		'next_step'      => null,
		'code'           => $registration['code'],
		'error'          => $registration['error'],
		'mocked'         => true,
		'source'         => 'stub',
		'provider'       => 'stub',
	);

	if ( ! $registration['valid'] ) {
		$result['guest_cart_preserved'] = true;

		return $result;
	}

	$transfer = velotok_core_guest_cart_transfer( $guest_cart, $user_cart, $registration['user'] );
	$redirect = velotok_core_guest_cart_checkout_redirect( $transfer, $registration['user'], $options );

	$result['transfer']     = $transfer;
	$result['cart']         = $transfer;
	$result['redirect']     = $redirect;
	$result['redirected']   = $redirect['redirected'];
	$result['redirect_url'] = $redirect['redirect_url'];
	$result['checkout_url'] = $redirect['checkout_url'];
	$result['next_step']    = $redirect['valid'] ? 'checkout' : null;
	$result['valid']        = $redirect['valid'];
	$result['code']         = $redirect['code'];
	$result['error']        = $redirect['error'];
	$result['transfer_strategy'] = $contract['transfer']['merge_strategy'];
	$result['guest_cart_transferred'] = true;
	$result['guest_cart_preserved'] = false;

	return $result;
}

/**
 * Alias for the complete buy flow.
 *
 * @param mixed $email      Registration request or email string.
 * @param mixed $guest_cart Guest cart array.
 * @param mixed $user_cart  Existing user cart array.
 * @param array $options    Mocked registration options.
 * @return array<string,mixed>
 */
function velotok_core_cart_buy( $email = '', $guest_cart = array(), $user_cart = array(), $options = array() ) {
	return velotok_core_guest_cart_buy( $email, $guest_cart, $user_cart, $options );
}

/**
 * Alias for callers that describe the flow as registration plus transfer.
 *
 * @param mixed $email      Registration request or email string.
 * @param mixed $guest_cart Guest cart array.
 * @param mixed $user_cart  Existing user cart array.
 * @param array $options    Mocked registration options.
 * @return array<string,mixed>
 */
function velotok_core_cart_register_and_transfer( $email = '', $guest_cart = array(), $user_cart = array(), $options = array() ) {
	return velotok_core_guest_cart_buy( $email, $guest_cart, $user_cart, $options );
}

if ( ! function_exists( 'guestCartRegistrationContract' ) ) {
	/**
	 * Short global alias for the guest cart registration contract.
	 *
	 * @return array<string,mixed>
	 */
	function guestCartRegistrationContract() {
		return velotok_core_guest_cart_registration_contract();
	}
}

if ( ! function_exists( 'guestCartBuy' ) ) {
	/**
	 * Short global alias for the complete guest buy flow.
	 *
	 * @param mixed $email      Registration request or email string.
	 * @param mixed $guest_cart Guest cart array.
	 * @param mixed $user_cart  Existing user cart array.
	 * @param array $options    Mocked registration options.
	 * @return array<string,mixed>
	 */
	function guestCartBuy( $email = '', $guest_cart = array(), $user_cart = array(), $options = array() ) {
		return velotok_core_guest_cart_buy( $email, $guest_cart, $user_cart, $options );
	}
}
