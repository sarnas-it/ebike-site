<?php
/**
 * Runtime contract test for the mocked specification data stub.
 *
 * The test proves that the stub is the single access point and that a known
 * key keeps returning its predefined value even when matching environment
 * variables are present. This is the behaviour required by spec
 * clarify-a1b91b62 until the future env provider is introduced.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/spec-data-runtime-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/spec-data-runtime-contract-test.php
 *
 * The test fails when the stub is removed, loses the mocked marker, starts
 * reading env, or changes the unknown-key rule.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/spec-data.php';

$failures = array();

/**
 * Assert a runtime contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_spec_runtime_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

function_exists( 'velotok_core_spec_get' ) || velotok_spec_runtime_assert( false, 'Отсутствует точка доступа к данным спецификации.' );
function_exists( 'velotok_core_spec_is_mocked' ) || velotok_spec_runtime_assert( false, 'Отсутствует признак mocked-провайдера.' );

if ( function_exists( 'velotok_core_spec_data' ) ) {
	$contract = velotok_core_spec_data();

	velotok_spec_runtime_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_spec_runtime_assert( isset( $contract['accessor'] ) && 'velotok_core_spec_get' === $contract['accessor'], 'Не зафиксирована единая точка доступа.' );
	velotok_spec_runtime_assert( function_exists( 'velotok_core_spec_is_mocked' ) && true === velotok_core_spec_is_mocked(), 'Провайдер не сообщает о mocked-режиме.' );
}

$env_key = function_exists( 'velotok_core_spec_env_name' ) ? velotok_core_spec_env_name( 'shop_phone' ) : '';
$env_value = 'env-value-must-be-ignored';
$previous_env = array();

if ( '' !== $env_key ) {
	$previous_env = array(
		'putenv'         => false !== getenv( $env_key ),
		'value'          => getenv( $env_key ),
		'_ENV_exists'    => array_key_exists( $env_key, $_ENV ),
		'_ENV_value'     => isset( $_ENV[ $env_key ] ) ? $_ENV[ $env_key ] : null,
		'_SERVER_exists' => array_key_exists( $env_key, $_SERVER ),
		'_SERVER_value'  => isset( $_SERVER[ $env_key ] ) ? $_SERVER[ $env_key ] : null,
	);

	putenv( $env_key . '=' . $env_value );
	$_ENV[ $env_key ] = $env_value;
	$_SERVER[ $env_key ] = $env_value;
}

if ( function_exists( 'velotok_core_spec_get' ) ) {
	velotok_spec_runtime_assert( '+7 (495) 123-45-67' === velotok_core_spec_get( 'shop_phone' ), 'Известный ключ не вернул предопределённое значение.' );
	velotok_spec_runtime_assert( $env_value !== velotok_core_spec_get( 'shop_phone' ), 'Stub прочитал значение из env вместо предопределённого.' );
	velotok_spec_runtime_assert( 340 === velotok_core_spec_get( 'delivery_cities' ), 'Числовое значение не сохраняет объявленный тип.' );
	velotok_spec_runtime_assert( '' === velotok_core_spec_get( 'unknown_key' ), 'Неизвестный ключ должен возвращать пустую строку.' );
	velotok_spec_runtime_assert( '' === velotok_core_spec_get( $env_key ), 'Env-имя не должно становиться ключом спецификации.' );
}

if ( '' !== $env_key ) {
	if ( $previous_env['putenv'] ) {
		putenv( $env_key . '=' . $previous_env['value'] );
	} else {
		putenv( $env_key );
	}

	if ( $previous_env['_ENV_exists'] ) {
		$_ENV[ $env_key ] = $previous_env['_ENV_value'];
	} else {
		unset( $_ENV[ $env_key ] );
	}

	if ( $previous_env['_SERVER_exists'] ) {
		$_SERVER[ $env_key ] = $previous_env['_SERVER_value'];
	} else {
		unset( $_SERVER[ $env_key ] );
	}
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: stub остаётся единственным источником и не читает env.' . PHP_EOL;
