<?php
/**
 * Contract test for the mocked provisioning stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/provisioning-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/provisioning-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked, loses determinism, starts making network calls or stops exposing the
 * stable facade that callers use.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/provisioning.php';

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки провижининга.' . PHP_EOL );
	exit( 1 );
}

require_once $stub_path;

$failures = array();

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_provisioning_contract_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_core_provisioning_contract' ) ) {
	velotok_provisioning_contract_assert( false, 'Отсутствует контракт провижининга.' );
} else {
	$contract = velotok_core_provisioning_contract();

	velotok_provisioning_contract_assert( is_array( $contract ), 'Контракт провижининга должен быть массивом.' );
	velotok_provisioning_contract_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт провижининга не помечен mocked: true.' );
	velotok_provisioning_contract_assert( isset( $contract['spec'] ) && 'clarify-497c0475' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_provisioning_contract_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_provisioning_contract_assert( isset( $contract['operation'] ) && 'provision' === $contract['operation'], 'Не зафиксирована операция провижининга.' );
	velotok_provisioning_contract_assert( isset( $contract['provider'] ) && 'mock' === $contract['provider'], 'Провайдером заглушки должен быть mock.' );
	velotok_provisioning_contract_assert( isset( $contract['network'] ) && false === $contract['network'], 'Заглушка не должна выполнять сетевые вызовы.' );
	velotok_provisioning_contract_assert( isset( $contract['persistence'] ) && false === $contract['persistence'], 'Заглушка не должна сохранять состояние между запусками.' );
	velotok_provisioning_contract_assert( isset( $contract['request']['required'] ) && in_array( 'resource_type', $contract['request']['required'], true ), 'Не зафиксировано обязательное поле resource_type.' );
	velotok_provisioning_contract_assert( ! empty( $contract['request']['fields'] ) && is_array( $contract['request']['fields'] ), 'Не описаны поля запроса провижининга.' );
	velotok_provisioning_contract_assert( ! empty( $contract['response']['required_keys'] ) && is_array( $contract['response']['required_keys'] ), 'Не описан формат ответа провижининга.' );
	velotok_provisioning_contract_assert( ! empty( $contract['error_codes'] ) && is_array( $contract['error_codes'] ), 'Не описаны коды ошибок провижининга.' );
	velotok_provisioning_contract_assert( ! empty( $contract['open_questions'] ) && is_array( $contract['open_questions'] ), 'Не зафиксированы открытые вопросы по реальной интеграции.' );
	velotok_provisioning_contract_assert( 'PROVISIONING_MISSING_PARAMETER' === $contract['error_codes']['missing_parameter'], 'Неверный код отсутствующего параметра.' );
	velotok_provisioning_contract_assert( 'PROVISIONING_INVALID_INPUT' === $contract['error_codes']['invalid_input'], 'Неверный код невалидного ввода.' );
}

if ( function_exists( 'velotok_core_provisioning_env_name' ) ) {
	velotok_provisioning_contract_assert( 'VELOTOK_PROVISIONING_PROVIDER' === velotok_core_provisioning_env_name( 'provider' ), 'Неверное имя env-переменной provider.' );
	velotok_provisioning_contract_assert( 'VELOTOK_PROVISIONING_ENDPOINT' === velotok_core_provisioning_env_name( 'endpoint' ), 'Неверное имя env-переменной endpoint.' );
	velotok_provisioning_contract_assert( 'VELOTOK_PROVISIONING_TOKEN' === velotok_core_provisioning_env_name( 'token' ), 'Неверное имя env-переменной token.' );
	velotok_provisioning_contract_assert( 'VELOTOK_PROVISIONING_TIMEOUT' === velotok_core_provisioning_env_name( 'timeout' ), 'Неверное имя env-переменной timeout.' );
	velotok_provisioning_contract_assert( '' === velotok_core_provisioning_env_name( 'unknown' ), 'Неизвестный ключ не должен получать env-имя.' );
}

if ( function_exists( 'velotok_core_provision' ) ) {
	$valid_input = array(
		'resource_type' => 'license',
		'resource_id'   => 'order-42',
		'attributes'    => array(
			'plan'  => 'pro',
			'seats' => 3,
		),
	);

	$first  = velotok_core_provision( $valid_input );
	$second = velotok_core_provision( $valid_input );
	$other  = velotok_core_provision( array( 'resource_type' => 'license', 'resource_id' => 'order-43' ) );

	velotok_provisioning_contract_assert( is_array( $first ), 'Ответ провижининга должен быть массивом.' );
	velotok_provisioning_contract_assert( true === $first['ok'], 'Валидный запрос должен завершаться успешно.' );
	velotok_provisioning_contract_assert( true === $first['valid'], 'Валидный запрос должен быть помечен valid.' );
	velotok_provisioning_contract_assert( true === $first['mocked'], 'Ответ провижининга должен быть помечен mocked: true.' );
	velotok_provisioning_contract_assert( 'mock' === $first['provider'], 'Ответ должен быть получен от mock-провайдера.' );
	velotok_provisioning_contract_assert( 'clarify-497c0475' === $first['spec'], 'Ответ должен ссылаться на спеку clarify-497c0475.' );
	velotok_provisioning_contract_assert( 'provisioned' === $first['status'], 'Успешный провижининг должен возвращать статус provisioned.' );
	velotok_provisioning_contract_assert( '' !== (string) $first['request_id'], 'Ответ должен содержать request_id.' );
	velotok_provisioning_contract_assert( '' !== (string) $first['resource_id'], 'Ответ должен содержать resource_id.' );
	velotok_provisioning_contract_assert( '' !== (string) $first['idempotency_key'], 'Ответ должен содержать idempotency_key.' );
	velotok_provisioning_contract_assert( true === $first['idempotent'], 'Провижининг должен быть идемпотентным.' );
	velotok_provisioning_contract_assert( false === $first['retryable'], 'Успешный провижининг не должен требовать ретрая.' );
	velotok_provisioning_contract_assert( false === $first['network'], 'Заглушка не должна выполнять сетевые вызовы.' );
	velotok_provisioning_contract_assert( false === $first['persisted'], 'Заглушка не должна сохранять состояние.' );
	velotok_provisioning_contract_assert( null === $first['error'], 'Успешный ответ не должен содержать ошибку.' );
	velotok_provisioning_contract_assert( array() === $first['errors'], 'Успешный ответ не должен содержать ошибок.' );
	velotok_provisioning_contract_assert( 'license' === $first['request']['resource_type'], 'Запрос должен сохранять resource_type.' );
	velotok_provisioning_contract_assert( $first === $second, 'Заглушка должна быть детерминированной при одинаковом вводе.' );
	velotok_provisioning_contract_assert( $first['resource_id'] !== $other['resource_id'], 'Разный ввод должен давать разные идентификаторы.' );

	$missing = velotok_core_provision( array() );
	velotok_provisioning_contract_assert( false === $missing['ok'], 'Пустой ввод не должен приводить к успеху.' );
	velotok_provisioning_contract_assert( false === $missing['valid'], 'Пустой ввод должен быть невалидным.' );
	velotok_provisioning_contract_assert( 'failed' === $missing['status'], 'Ошибочный провижининг должен возвращать статус failed.' );
	velotok_provisioning_contract_assert( true === $missing['mocked'], 'Ошибочный ответ заглушки должен быть помечен mocked.' );
	velotok_provisioning_contract_assert( 'PROVISIONING_MISSING_PARAMETER' === $missing['error']['code'], 'Для пустого ввода нужен PROVISIONING_MISSING_PARAMETER.' );
	velotok_provisioning_contract_assert( false === $missing['network'], 'Ошибочный ответ не должен выполнять сетевые вызовы.' );

	$invalid = velotok_core_provision( array( 'resource_type' => 'license', 'attributes' => 'not-an-array' ) );
	velotok_provisioning_contract_assert( false === $invalid['ok'], 'Неверный тип attributes должен отклоняться.' );
	velotok_provisioning_contract_assert( 'PROVISIONING_INVALID_INPUT' === $invalid['error']['code'], 'Для неверного типа attributes нужен PROVISIONING_INVALID_INPUT.' );

	$alias = velotok_core_provision( array( 'type' => 'license', 'id' => 'order-42' ) );
	velotok_provisioning_contract_assert( true === $alias['ok'], 'Псевдонимы полей должны приниматься.' );
	velotok_provisioning_contract_assert( 'license' === $alias['request']['resource_type'], 'Псевдоним type должен разрешаться в resource_type.' );
}

if ( function_exists( 'velotok_core_provisioning_register_provider' ) && function_exists( 'velotok_core_provisioning_reset_provider' ) ) {
	$custom = function ( $input, $options, $contract ) {
		return array(
			'ok'              => true,
			'valid'           => true,
			'spec'            => $contract['spec'],
			'operation'       => $contract['operation'],
			'provider'        => 'custom',
			'mocked'          => false,
			'status'          => $contract['success_status'],
			'request_id'      => 'custom-request-1',
			'resource_id'     => 'custom-resource-1',
			'idempotency_key' => 'custom-idem-1',
			'idempotent'      => true,
			'retryable'       => false,
			'persisted'       => false,
			'network'         => false,
			'request'         => is_array( $input ) ? $input : array(),
			'error'           => null,
			'errors'          => array(),
			'details'         => array(),
		);
	};

	$registered = velotok_core_provisioning_register_provider( $custom );
	velotok_provisioning_contract_assert( true === $registered, 'Совместимый провайдер должен регистрироваться.' );
	velotok_provisioning_contract_assert( false === velotok_core_provisioning_is_mocked(), 'После регистрации провайдера заглушка не должна быть активной.' );

	$replaced = velotok_core_provision( array( 'resource_type' => 'license' ) );
	velotok_provisioning_contract_assert( 'custom-resource-1' === $replaced['resource_id'], 'Вызывающий код должен получать ответ провайдера через тот же фасад.' );
	velotok_provisioning_contract_assert( false === $replaced['mocked'], 'Ответ реального провайдера не должен быть помечен mocked.' );

	velotok_core_provisioning_reset_provider();

	$restored = velotok_core_provision( array( 'resource_type' => 'license' ) );
	velotok_provisioning_contract_assert( true === $restored['mocked'], 'После сброса должен снова использоваться mock-провайдер.' );
	velotok_provisioning_contract_assert( true === velotok_core_provisioning_is_mocked(), 'После сброса заглушка должна быть активной.' );
}

$source = (string) file_get_contents( $stub_path );

$forbidden = array(
	'get' . 'env(',
	'apache_get' . 'env(',
	'$_' . 'ENV',
	'$_' . 'SERVER',
	'file_get' . '_contents(',
	'fopen(',
	'curl_' . 'init(',
	'wp_remote_' . 'get(',
	'wp_remote_' . 'post(',
	'fsockopen(',
	'stream_socket_' . 'client(',
	'socket_' . 'create(',
);

foreach ( $forbidden as $needle ) {
	velotok_provisioning_contract_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт провижининга на месте.' . PHP_EOL;
