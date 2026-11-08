<?php
/**
 * Focused contract test for TESTEB-47.
 *
 * Spec: payment. Requirement: secrets must be stored outside the code.
 *
 * The test verifies that shop id, secret key and webhook secret are read from
 * the environment, WordPress options or WooCommerce gateway settings, that no
 * secret value is baked into the implementation and that the public contract
 * only exposes environment variable names.
 *
 * The test can be pointed at another implementation through
 * VELOTOK_PAYMENT_GATEWAY_FILE; the red-green proof uses this to run against
 * deliberately broken copies of includes/payment-gateway.php.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'STDERR' ) ) {
	define( 'STDERR', fopen( 'php://stderr', 'w' ) );
}

$GLOBALS['velotok_test_filters'] = array();
$GLOBALS['velotok_test_actions'] = array();
$GLOBALS['velotok_test_options'] = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['velotok_test_filters'][ $tag ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		if ( ! empty( $GLOBALS['velotok_test_filters'][ $tag ] ) ) {
			foreach ( $GLOBALS['velotok_test_filters'][ $tag ] as $callback ) {
				$value = call_user_func( $callback, $value, ...$args );
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['velotok_test_actions'][ $tag ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $tag, ...$args ) {
		if ( ! empty( $GLOBALS['velotok_test_actions'][ $tag ] ) ) {
			foreach ( $GLOBALS['velotok_test_actions'][ $tag ] as $callback ) {
				call_user_func( $callback, ...$args );
			}
		}
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['velotok_test_options'] ) ? $GLOBALS['velotok_test_options'][ $name ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['velotok_test_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '/' ) {
		return 'https://shop.test' . $path;
	}
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	class WC_Payment_Gateway {
		public $id = '';
		public $method_title = '';
		public $method_description = '';
		public $has_fields = false;
		public $supports = array();
		public $title = '';
		public $description = '';
		public $enabled = 'yes';
		public $settings = array();

		public function __construct() {}

		public function init_settings() {
			$this->settings = array();
		}

		public function get_option( $key, $default = '' ) {
			return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
		}

		public function process_admin_options() {
			return true;
		}
	}
}

$implementation = getenv( 'VELOTOK_PAYMENT_GATEWAY_FILE' );

if ( false === $implementation || '' === $implementation ) {
	$implementation = dirname( __DIR__ ) . '/includes/payment-gateway.php';
}

if ( ! is_readable( $implementation ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл реализации платёжного шлюза: ' . $implementation . PHP_EOL );
	exit( 1 );
}

require_once $implementation;

$GLOBALS['velotok_payment_secrets_test_passed'] = 0;

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_payment_secrets_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}

	$GLOBALS['velotok_payment_secrets_test_passed']++;
}

/**
 * Environment variables used by the gateway.
 *
 * @return array<int,string>
 */
function velotok_payment_secrets_test_env_names() {
	return array(
		'VELOTOK_YOOKASSA_SHOP_ID',
		'VELOTOK_YOOKASSA_SECRET_KEY',
		'VELOTOK_YOOKASSA_WEBHOOK_SECRET',
	);
}

/**
 * Remove stored secrets from the process environment.
 *
 * @return void
 */
function velotok_payment_secrets_test_clear_env() {
	foreach ( velotok_payment_secrets_test_env_names() as $name ) {
		putenv( $name );
		unset( $_ENV[ $name ] );
	}
}

/**
 * Set the gateway secrets in the process environment.
 *
 * @param string $shop_id        Shop id.
 * @param string $secret_key     Secret key.
 * @param string $webhook_secret Webhook secret.
 * @return void
 */
function velotok_payment_secrets_test_set_env( $shop_id, $secret_key, $webhook_secret ) {
	putenv( 'VELOTOK_YOOKASSA_SHOP_ID=' . $shop_id );
	putenv( 'VELOTOK_YOOKASSA_SECRET_KEY=' . $secret_key );
	putenv( 'VELOTOK_YOOKASSA_WEBHOOK_SECRET=' . $webhook_secret );
}

velotok_payment_secrets_test_clear_env();
$GLOBALS['velotok_test_options'] = array();

velotok_payment_secrets_test_assert(
	'' === velotok_core_yookassa_shop_id(),
	'Без окружения и настроек shop id не должен быть зашит в код.'
);
velotok_payment_secrets_test_assert(
	'' === velotok_core_yookassa_secret_key(),
	'Без окружения и настроек secret key не должен быть зашит в код.'
);
velotok_payment_secrets_test_assert(
	'' === velotok_core_yookassa_webhook_secret(),
	'Без окружения и настроек webhook secret не должен быть зашит в код.'
);
velotok_payment_secrets_test_assert(
	! velotok_core_yookassa_is_configured(),
	'Шлюз без секретов не должен считаться настроенным.'
);

velotok_payment_secrets_test_set_env( 'env-shop-47', 'env-secret-47', 'env-webhook-47' );

velotok_payment_secrets_test_assert(
	'env-shop-47' === velotok_core_yookassa_shop_id(),
	'Shop id должен читаться из VELOTOK_YOOKASSA_SHOP_ID.'
);
velotok_payment_secrets_test_assert(
	'env-secret-47' === velotok_core_yookassa_secret_key(),
	'Secret key должен читаться из VELOTOK_YOOKASSA_SECRET_KEY.'
);
velotok_payment_secrets_test_assert(
	'env-webhook-47' === velotok_core_yookassa_webhook_secret(),
	'Webhook secret должен читаться из VELOTOK_YOOKASSA_WEBHOOK_SECRET.'
);
velotok_payment_secrets_test_assert(
	velotok_core_yookassa_is_configured(),
	'Шлюз с секретами из окружения должен считаться настроенным.'
);

$contract = velotok_core_payment_contract();

velotok_payment_secrets_test_assert(
	'env_or_option' === $contract['secrets']['source'],
	'Контракт должен объявлять источник секретов вне кода.'
);
velotok_payment_secrets_test_assert(
	'VELOTOK_YOOKASSA_SHOP_ID' === $contract['secrets']['shop_id_env'],
	'Контракт должен ссылаться на переменную окружения shop id.'
);
velotok_payment_secrets_test_assert(
	'VELOTOK_YOOKASSA_SECRET_KEY' === $contract['secrets']['secret_key_env'],
	'Контракт должен ссылаться на переменную окружения secret key.'
);
velotok_payment_secrets_test_assert(
	'VELOTOK_YOOKASSA_WEBHOOK_SECRET' === $contract['secrets']['webhook_env'],
	'Контракт должен ссылаться на переменную окружения webhook secret.'
);
velotok_payment_secrets_test_assert(
	'VELOTOK_YOOKASSA_WEBHOOK_SECRET' === $contract['webhook']['secret_env'],
	'Webhook-контракт должен ссылаться на переменную окружения секрета.'
);
velotok_payment_secrets_test_assert(
	true === $contract['secrets']['configured'],
	'Контракт должен отражать настроенность шлюза без раскрытия значений.'
);

$contract_json = json_encode( $contract );

foreach ( array( 'env-shop-47', 'env-secret-47', 'env-webhook-47' ) as $secret_value ) {
	velotok_payment_secrets_test_assert(
		false === strpos( (string) $contract_json, $secret_value ),
		'Контракт не должен раскрывать значение секрета.'
	);
}

$payload = array(
	'event'  => 'payment.succeeded',
	'object' => array(
		'id'       => 'pay-secrets-47',
		'status'   => 'succeeded',
		'metadata' => array( 'order_id' => '47' ),
	),
);
$raw       = json_encode( $payload );
$signature = hash_hmac( 'sha256', $raw, 'env-webhook-47' );

velotok_payment_secrets_test_assert(
	$signature === velotok_core_payment_webhook_signature( $raw ),
	'Подпись webhook должна вычисляться секретом из окружения.'
);
velotok_payment_secrets_test_assert(
	velotok_core_payment_webhook_verify( $raw, $signature ),
	'Webhook должен проверяться секретом из окружения.'
);

velotok_payment_secrets_test_clear_env();
$GLOBALS['velotok_test_options'] = array(
	'velotok_yookassa_shop_id'      => 'option-shop-47',
	'velotok_yookassa_secret_key'   => 'option-secret-47',
	'velotok_yookassa_webhook_secret' => 'option-webhook-47',
);

velotok_payment_secrets_test_assert(
	'option-shop-47' === velotok_core_yookassa_shop_id(),
	'Shop id должен читаться из WordPress-настройки.'
);
velotok_payment_secrets_test_assert(
	'option-secret-47' === velotok_core_yookassa_secret_key(),
	'Secret key должен читаться из WordPress-настройки.'
);
velotok_payment_secrets_test_assert(
	'option-webhook-47' === velotok_core_yookassa_webhook_secret(),
	'Webhook secret должен читаться из WordPress-настройки.'
);

$GLOBALS['velotok_test_options'] = array(
	'woocommerce_velotok_yookassa_settings' => array(
		'shop_id'        => 'gateway-shop-47',
		'secret_key'     => 'gateway-secret-47',
		'webhook_secret' => 'gateway-webhook-47',
	),
);

velotok_payment_secrets_test_assert(
	'gateway-shop-47' === velotok_core_yookassa_shop_id(),
	'Shop id должен читаться из настроек платёжного шлюза.'
);
velotok_payment_secrets_test_assert(
	'gateway-secret-47' === velotok_core_yookassa_secret_key(),
	'Secret key должен читаться из настроек платёжного шлюза.'
);
velotok_payment_secrets_test_assert(
	'gateway-webhook-47' === velotok_core_yookassa_webhook_secret(),
	'Webhook secret должен читаться из настроек платёжного шлюза.'
);

$gateway = new Velotok_Yookassa_Gateway();

velotok_payment_secrets_test_assert(
	isset( $gateway->form_fields['shop_id'], $gateway->form_fields['secret_key'], $gateway->form_fields['webhook_secret'] ),
	'Настройки шлюза должны содержать поля секретов.'
);
velotok_payment_secrets_test_assert(
	'password' === $gateway->form_fields['secret_key']['type'],
	'Поле secret key должно маскироваться как password.'
);
velotok_payment_secrets_test_assert(
	'password' === $gateway->form_fields['webhook_secret']['type'],
	'Поле webhook secret должно маскироваться как password.'
);

velotok_payment_secrets_test_clear_env();
$GLOBALS['velotok_test_options'] = array();

velotok_payment_secrets_test_assert(
	'' === velotok_core_yookassa_secret_key(),
	'После удаления окружения и настроек секрет не должен подставляться из кода.'
);

echo 'PASS: секреты хранятся вне кода и читаются из окружения или настроек (' . (int) $GLOBALS['velotok_payment_secrets_test_passed'] . ' проверок, TESTEB-47).' . PHP_EOL;
