<?php
/**
 * Provisioning stub contract.
 *
 * This is the single access point for the provisioning operation. The current
 * iteration deliberately uses a deterministic in-memory stub: it does not read
 * environment variables, files, the database or remote services.
 *
 * A real provisioning provider is out of scope until the external provider,
 * protocol, error semantics and persistence requirements are agreed. Consumers
 * must call velotok_core_provision() so the mocked implementation can later be
 * replaced by a contract-compatible provider without touching caller code.
 *
 * Spec: clarify-497c0475.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

/**
 * Return the provisioning contract.
 *
 * The contract documents:
 * - the spec identifier and the explicit `mocked` marker;
 * - the provisioning operation, its input fields and response shape;
 * - the deterministic, stateless and offline behaviour of the stub;
 * - the errors and the future environment variable names;
 * - the open questions that block a real integration.
 *
 * @return array<string,mixed>
 */
function velotok_core_provisioning_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$fields = array(
		'resource_type'   => array(
			'type'     => 'string',
			'required' => true,
			'aliases'  => array( 'type', 'kind', 'resource', 'service', 'target' ),
		),
		'resource_id'     => array(
			'type'     => 'string',
			'required' => false,
			'aliases'  => array( 'id', 'key' ),
		),
		'attributes'      => array(
			'type'     => 'array',
			'required' => false,
			'aliases'  => array( 'params', 'parameters', 'payload', 'data' ),
		),
		'idempotency_key' => array(
			'type'     => 'string',
			'required' => false,
			'aliases'  => array( 'idempotency', 'request_id' ),
		),
	);

	$contract = array(
		'spec'              => 'clarify-497c0475',
		'parent_spec'       => 'clarify-096bb945',
		'source'            => 'stub',
		'mocked'            => true,
		'operation'         => 'provision',
		'provider'          => 'mock',
		'provider_label'    => 'Mock provisioning provider',
		'protocol'          => 'in_process',
		'network'           => false,
		'stateful'          => false,
		'persistence'       => false,
		'success_status'    => 'provisioned',
		'failure_status'    => 'failed',
		'supported_modes'   => array( 'mock' ),
		'default_mode'      => 'mock',
		'idempotency'       => array(
			'enabled'    => true,
			'strategy'   => 'deterministic_input_hash',
			'persisted'  => false,
			'retry_safe' => true,
		),
		'retry'             => array(
			'retryable' => false,
			'attempts'  => 1,
			'backoff'   => null,
		),
		'request'           => array(
			'required' => array( 'resource_type' ),
			'optional' => array( 'resource_id', 'attributes', 'idempotency_key' ),
			'fields'   => $fields,
		),
		'response'          => array(
			'required_keys' => array(
				'ok',
				'valid',
				'spec',
				'operation',
				'provider',
				'mocked',
				'status',
				'request_id',
				'resource_id',
				'idempotency_key',
				'idempotent',
				'retryable',
				'persisted',
				'network',
				'request',
				'error',
				'errors',
				'details',
			),
			'types'         => array(
				'ok'              => 'bool',
				'valid'           => 'bool',
				'spec'            => 'string',
				'operation'       => 'string',
				'provider'        => 'string',
				'mocked'          => 'bool',
				'status'          => 'string',
				'request_id'      => 'string',
				'resource_id'     => 'string|null',
				'idempotency_key' => 'string|null',
				'idempotent'      => 'bool',
				'retryable'       => 'bool',
				'persisted'       => 'bool',
				'network'         => 'bool',
				'request'         => 'array',
				'error'           => 'array|null',
				'errors'          => 'array',
				'details'         => 'array',
			),
		),
		'error_codes'       => array(
			'invalid_input'       => 'PROVISIONING_INVALID_INPUT',
			'missing_parameter'   => 'PROVISIONING_MISSING_PARAMETER',
			'provider_unavailable' => 'PROVISIONING_PROVIDER_UNAVAILABLE',
			'provider_invalid_response' => 'PROVISIONING_PROVIDER_INVALID_RESPONSE',
		),
		'future_source'     => 'provider_api',
		'env_prefix'        => 'VELOTOK_PROVISIONING_',
		'env'               => array(
			'provider' => 'VELOTOK_PROVISIONING_PROVIDER',
			'endpoint' => 'VELOTOK_PROVISIONING_ENDPOINT',
			'token'    => 'VELOTOK_PROVISIONING_TOKEN',
			'timeout'  => 'VELOTOK_PROVISIONING_TIMEOUT',
		),
		'open_questions'    => array(
			'provider_protocol' => 'Какой внешний провайдер и протокол являются целевыми для реальной интеграции — без этого невозможно зафиксировать финальный контракт ответа.',
			'error_semantics'   => 'Какие семантики ошибок (коды, ретраи, идемпотентность) должен поддерживать реальный провайдер — влияет на расширение контракта.',
			'state'             => 'Требуется ли сохранять состояние провижининга между запусками и в какой форме — определяет границы mocked-реализации.',
			'non_functional'    => 'Какие нефункциональные требования (латентность, лимиты) применяются к реальной интеграции — не определено.',
			'parent_spec'       => 'Каково отношение к родительской спеке clarify-096bb945: какие её требования наследуются, а какие переопределяются в этой спеке.',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer a full explicit contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_provisioning_operation_contract() {
	return velotok_core_provisioning_contract();
}

/**
 * Return the open questions that block a real integration.
 *
 * @return array<string,string>
 */
function velotok_core_provisioning_open_questions() {
	$contract = velotok_core_provisioning_contract();

	return $contract['open_questions'];
}

/**
 * Return the future environment variable name for a provisioning setting.
 *
 * The stub only computes the name; it never reads environment variables.
 *
 * @param mixed $key Setting key.
 * @return string Empty string for unknown keys.
 */
function velotok_core_provisioning_env_name( $key ) {
	if ( ! is_string( $key ) ) {
		return '';
	}

	$contract = velotok_core_provisioning_contract();

	if ( ! isset( $contract['env'][ $key ] ) ) {
		return '';
	}

	return $contract['env'][ $key ];
}

/**
 * Internal provider registry.
 *
 * @return array<string,mixed>
 */
function &velotok_core_provisioning_state() {
	static $state = array(
		'provider' => null,
		'mocked'   => true,
	);

	return $state;
}

/**
 * Register a contract-compatible provisioning provider.
 *
 * The provider callable receives the raw input, options and contract. It must
 * return an array matching the response shape declared by the contract. When
 * no provider is registered, the deterministic mock is used.
 *
 * @param callable|null $provider Provider callable or null to reset.
 * @return bool
 */
function velotok_core_provisioning_register_provider( $provider ) {
	$state = &velotok_core_provisioning_state();

	if ( null === $provider ) {
		$state['provider'] = null;
		$state['mocked']   = true;

		return true;
	}

	if ( ! is_callable( $provider ) ) {
		return false;
	}

	$state['provider'] = $provider;
	$state['mocked']   = false;

	return true;
}

/**
 * Alias for callers that use an explicit registration name.
 *
 * @param callable|null $provider Provider callable or null to reset.
 * @return bool
 */
function velotok_core_register_provisioning_provider( $provider ) {
	return velotok_core_provisioning_register_provider( $provider );
}

/**
 * Reset the provisioning provider to the built-in mock.
 *
 * @return void
 */
function velotok_core_provisioning_reset_provider() {
	velotok_core_provisioning_register_provider( null );
}

/**
 * Return the active provider identifier.
 *
 * @return string
 */
function velotok_core_provisioning_provider() {
	$state = &velotok_core_provisioning_state();

	return true === $state['mocked'] ? 'mock' : 'custom';
}

/**
 * Check whether the active provider is the built-in mock.
 *
 * @return bool
 */
function velotok_core_provisioning_is_mocked() {
	$state = &velotok_core_provisioning_state();

	return true === $state['mocked'];
}

/**
 * Canonicalize a value for deterministic hashing.
 *
 * Associative array keys are sorted recursively; list order is preserved.
 *
 * @param mixed $value Raw value.
 * @return mixed
 */
function velotok_core_provisioning_canonicalize( $value ) {
	if ( is_object( $value ) ) {
		$value = (array) $value;
	}

	if ( ! is_array( $value ) ) {
		return $value;
	}

	$keys   = array_keys( $value );
	$is_list = $keys === range( 0, count( $value ) - 1 );

	if ( ! $is_list ) {
		ksort( $value, SORT_STRING );
	}

	foreach ( $value as $key => $item ) {
		$value[ $key ] = velotok_core_provisioning_canonicalize( $item );
	}

	return $value;
}

/**
 * Build a deterministic hash for any input.
 *
 * @param mixed $value Input value.
 * @return string
 */
function velotok_core_provisioning_hash( $value ) {
	$canonical = velotok_core_provisioning_canonicalize( $value );
	$payload   = json_encode( $canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	if ( false === $payload ) {
		$payload = serialize( $canonical );
	}

	return hash( 'sha256', (string) $payload );
}

/**
 * Normalize and validate provisioning input.
 *
 * @param mixed $input Raw input.
 * @return array<string,mixed>
 */
function velotok_core_provisioning_prepare_input( $input ) {
	$contract = velotok_core_provisioning_contract();
	$raw      = $input;

	if ( is_object( $raw ) ) {
		$raw = (array) $raw;
	}

	if ( is_string( $raw ) || is_int( $raw ) || is_float( $raw ) ) {
		$raw = array( 'resource_type' => $raw );
	}

	if ( ! is_array( $raw ) ) {
		return array(
			'valid'  => false,
			'code'   => $contract['error_codes']['invalid_input'],
			'field'  => null,
			'values' => array(),
		);
	}

	$values = array();

	foreach ( $contract['request']['fields'] as $canonical => $field ) {
		$names = array_merge( array( $canonical ), $field['aliases'] );

		foreach ( $names as $name ) {
			if ( array_key_exists( $name, $raw ) ) {
				$values[ $canonical ] = $raw[ $name ];
				break;
			}
		}
	}

	$type = isset( $values['resource_type'] ) ? $values['resource_type'] : null;

	if ( is_array( $type ) || is_object( $type ) || is_bool( $type ) || null === $type ) {
		return array(
			'valid'  => false,
			'code'   => $contract['error_codes']['missing_parameter'],
			'field'  => 'resource_type',
			'values' => $values,
		);
	}

	$type = is_scalar( $type ) ? trim( (string) $type ) : '';

	if ( '' === $type ) {
		return array(
			'valid'  => false,
			'code'   => $contract['error_codes']['missing_parameter'],
			'field'  => 'resource_type',
			'values' => $values,
		);
	}

	$values['resource_type'] = $type;

	if ( array_key_exists( 'resource_id', $values ) ) {
		$resource_id = $values['resource_id'];

		if ( is_array( $resource_id ) || is_object( $resource_id ) || is_bool( $resource_id ) ) {
			return array(
				'valid'  => false,
				'code'   => $contract['error_codes']['invalid_input'],
				'field'  => 'resource_id',
				'values' => $values,
			);
		}

		$values['resource_id'] = is_scalar( $resource_id ) ? trim( (string) $resource_id ) : '';
	} else {
		$values['resource_id'] = '';
	}

	if ( array_key_exists( 'attributes', $values ) ) {
		$attributes = $values['attributes'];

		if ( is_object( $attributes ) ) {
			$attributes = (array) $attributes;
		}

		if ( ! is_array( $attributes ) ) {
			return array(
				'valid'  => false,
				'code'   => $contract['error_codes']['invalid_input'],
				'field'  => 'attributes',
				'values' => $values,
			);
		}

		$values['attributes'] = $attributes;
	} else {
		$values['attributes'] = array();
	}

	if ( array_key_exists( 'idempotency_key', $values ) ) {
		$idempotency_key = $values['idempotency_key'];

		if ( is_array( $idempotency_key ) || is_object( $idempotency_key ) || is_bool( $idempotency_key ) ) {
			return array(
				'valid'  => false,
				'code'   => $contract['error_codes']['invalid_input'],
				'field'  => 'idempotency_key',
				'values' => $values,
			);
		}

		$values['idempotency_key'] = is_scalar( $idempotency_key ) ? trim( (string) $idempotency_key ) : '';
	} else {
		$values['idempotency_key'] = '';
	}

	return array(
		'valid'  => true,
		'code'   => null,
		'field'  => null,
		'values' => $values,
	);
}

/**
 * Build an error response that matches the contract.
 *
 * @param string      $code    Error code.
 * @param string|null $field   Field name.
 * @param mixed       $input   Raw input.
 * @param mixed       $options Optional options.
 * @return array<string,mixed>
 */
function velotok_core_provisioning_error_response( $code, $field, $input, $options = array() ) {
	$contract = velotok_core_provisioning_contract();
	$hash     = velotok_core_provisioning_hash( $input );
	$message  = $contract['error_codes']['missing_parameter'] === $code
		? 'Required provisioning parameter is missing.'
		: 'Provisioning input is invalid.';

	return array(
		'ok'              => false,
		'valid'           => false,
		'spec'            => $contract['spec'],
		'operation'       => $contract['operation'],
		'provider'        => 'mock',
		'mocked'          => true,
		'status'          => $contract['failure_status'],
		'request_id'      => 'mock-provision-' . substr( $hash, 0, 16 ),
		'resource_id'     => null,
		'idempotency_key' => null,
		'idempotent'      => true,
		'retryable'       => false,
		'persisted'       => false,
		'network'         => false,
		'request'         => is_array( $input ) ? velotok_core_provisioning_canonicalize( $input ) : array(),
		'error'           => array(
			'code'    => $code,
			'message' => $message,
		),
		'errors'          => array(
			array(
				'code'    => $code,
				'field'   => $field,
				'message' => $message,
			),
		),
		'details'         => array(
			'source'  => 'stub',
			'mocked'  => true,
			'network' => false,
			'options' => is_array( $options ) ? $options : array(),
		),
	);
}

/**
 * Perform the deterministic mocked provisioning operation.
 *
 * The same input always produces the same identifiers. No network request is
 * performed, no state is persisted and no environment variable is read.
 *
 * @param mixed               $input   Provisioning input.
 * @param array<string,mixed> $options Optional options.
 * @return array<string,mixed>
 */
function velotok_core_provisioning_mock( $input = array(), $options = array() ) {
	$contract = velotok_core_provisioning_contract();
	$prepared = velotok_core_provisioning_prepare_input( $input );

	if ( ! $prepared['valid'] ) {
		return velotok_core_provisioning_error_response( $prepared['code'], $prepared['field'], $input, $options );
	}

	$values = $prepared['values'];
	$hash   = velotok_core_provisioning_hash( $values );
	$token  = substr( $hash, 0, 16 );
	$idem   = '' === $values['idempotency_key'] ? 'mock-idem-' . $token : $values['idempotency_key'];

	return array(
		'ok'              => true,
		'valid'           => true,
		'spec'            => $contract['spec'],
		'operation'       => $contract['operation'],
		'provider'        => 'mock',
		'mocked'          => true,
		'status'          => $contract['success_status'],
		'request_id'      => 'mock-provision-' . $token,
		'resource_type'   => $values['resource_type'],
		'resource_id'     => 'mock-resource-' . $token,
		'idempotency_key' => $idem,
		'idempotent'      => true,
		'retryable'       => false,
		'persisted'       => false,
		'network'         => false,
		'request'         => velotok_core_provisioning_canonicalize( $values ),
		'error'           => null,
		'errors'          => array(),
		'details'         => array(
			'source'    => 'stub',
			'mocked'    => true,
			'network'   => false,
			'persisted' => false,
			'options'   => is_array( $options ) ? $options : array(),
		),
	);
}

/**
 * Execute the provisioning operation through the active provider.
 *
 * Callers must use this function instead of a concrete provider. The default
 * implementation is the mocked stub; a contract-compatible provider can be
 * registered without changing caller code.
 *
 * @param mixed               $input   Provisioning input.
 * @param array<string,mixed> $options Optional options.
 * @return array<string,mixed>
 */
function velotok_core_provision( $input = array(), $options = array() ) {
	$state = &velotok_core_provisioning_state();

	if ( ! is_callable( $state['provider'] ) ) {
		return velotok_core_provisioning_mock( $input, $options );
	}

	$contract = velotok_core_provisioning_contract();
	$response = call_user_func( $state['provider'], $input, $options, $contract );

	if ( ! is_array( $response ) ) {
		return velotok_core_provisioning_error_response(
			$contract['error_codes']['provider_invalid_response'],
			null,
			$input,
			$options
		);
	}

	return $response;
}

/**
 * Alias for callers that prefer the operation name.
 *
 * @param mixed               $input   Provisioning input.
 * @param array<string,mixed> $options Optional options.
 * @return array<string,mixed>
 */
function velotok_core_provisioning_operation( $input = array(), $options = array() ) {
	return velotok_core_provision( $input, $options );
}

/**
 * Alias for callers that expect an explicit resource provisioning helper.
 *
 * @param mixed               $input   Provisioning input.
 * @param array<string,mixed> $options Optional options.
 * @return array<string,mixed>
 */
function velotok_core_provision_resource( $input = array(), $options = array() ) {
	return velotok_core_provision( $input, $options );
}

/**
 * Alias for callers that use the short provisioning name.
 *
 * @param mixed               $input   Provisioning input.
 * @param array<string,mixed> $options Optional options.
 * @return array<string,mixed>
 */
function velotok_core_provisioning( $input = array(), $options = array() ) {
	return velotok_core_provision( $input, $options );
}

/**
 * Alias for callers that explicitly request the mocked implementation.
 *
 * @param mixed               $input   Provisioning input.
 * @param array<string,mixed> $options Optional options.
 * @return array<string,mixed>
 */
function velotok_core_provisioning_stub( $input = array(), $options = array() ) {
	return velotok_core_provisioning_mock( $input, $options );
}
