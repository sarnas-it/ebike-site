<?php
/**
 * Specification data stub.
 *
 * This is the single access point for specification data used by the site.
 * The current iteration deliberately uses a fixed in-memory stub: it does not
 * read environment variables, files, the database or remote services.
 *
 * Future direction: the values provider will read the same canonical keys from
 * environment variables. Consumers must keep using the accessors below so the
 * provider can be replaced without touching templates.
 *
 * Spec: clarify-a1b91b62.
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the specification data contract.
 *
 * The contract documents:
 * - the spec identifier and the explicit `mocked` marker;
 * - canonical specification keys, their values and scalar types;
 * - the fixed behaviour for unknown keys (an empty string);
 * - the future mapping of keys to environment variable names.
 *
 * @return array<string,mixed>
 */
function velotok_core_spec_data() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$values = array(
		'shop_phone'           => '+7 (495) 123-45-67',
		'shop_email'           => 'hello@velotok.ru',
		'shop_address'         => 'Москва, Нижняя Сыромятническая, 10, стр. 2',
		'shop_hours'           => 'Ежедневно, 10:00–21:00',
		'catalog_model_types'  => 4,
		'catalog_range'        => '20–120 км',
		'warranty_years'       => 2,
		'delivery_cities'      => 340,
		'installment_months'   => 24,
	);

	$types = array(
		'shop_phone'           => 'string',
		'shop_email'           => 'string',
		'shop_address'         => 'string',
		'shop_hours'           => 'string',
		'catalog_model_types'  => 'int',
		'catalog_range'        => 'string',
		'warranty_years'       => 'int',
		'delivery_cities'      => 'int',
		'installment_months'   => 'int',
	);

	$env_map = array();

	foreach ( array_keys( $values ) as $key ) {
		$env_map[ $key ] = 'VELOTOK_SPEC_' . strtoupper( (string) preg_replace( '/[^a-z0-9]+/i', '_', $key ) );
	}

	$contract = array(
		'spec'          => 'clarify-a1b91b62',
		'source'        => 'stub',
		'mocked'        => true,
		'missing_key'   => 'empty_string',
		'future_source' => 'env',
		'env_prefix'    => 'VELOTOK_SPEC_',
		'values'        => $values,
		'types'         => $types,
		'env_map'       => $env_map,
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer an explicit contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_spec_data_contract() {
	return velotok_core_spec_data();
}

/**
 * Return the canonical specification keys.
 *
 * @return string[]
 */
function velotok_core_spec_keys() {
	$contract = velotok_core_spec_data();

	return array_keys( $contract['values'] );
}

/**
 * Check whether a specification key exists in the stub.
 *
 * @param mixed $key Specification key.
 * @return bool
 */
function velotok_core_spec_has( $key ) {
	if ( ! is_string( $key ) ) {
		return false;
	}

	$contract = velotok_core_spec_data();

	return array_key_exists( $key, $contract['values'] );
}

/**
 * Read a specification value by key.
 *
 * Unknown keys always return an empty string. This behaviour is part of the
 * contract (`missing_key => empty_string`) and must not change without a spec
 * update.
 *
 * @param mixed $key Specification key.
 * @return mixed
 */
function velotok_core_spec_get( $key ) {
	if ( ! velotok_core_spec_has( $key ) ) {
		return '';
	}

	$contract = velotok_core_spec_data();

	return $contract['values'][ $key ];
}

/**
 * Return the declared scalar type for a specification key.
 *
 * @param mixed $key Specification key.
 * @return string Empty string for unknown keys.
 */
function velotok_core_spec_type( $key ) {
	if ( ! velotok_core_spec_has( $key ) ) {
		return '';
	}

	$contract = velotok_core_spec_data();

	return $contract['types'][ $key ];
}

/**
 * Return the future environment variable name for a specification key.
 *
 * The stub only computes the name; it never reads environment variables.
 *
 * @param mixed $key Specification key.
 * @return string Empty string for unknown keys.
 */
function velotok_core_spec_env_name( $key ) {
	if ( ! velotok_core_spec_has( $key ) ) {
		return '';
	}

	$contract = velotok_core_spec_data();

	return $contract['env_map'][ $key ];
}
