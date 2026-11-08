<?php
/**
 * Additional product attributes stub.
 *
 * The catalog must show arbitrary seller-defined attributes in addition to the
 * mandatory photo, name, description and price. Real sellers do not exist yet,
 * so the source and the demo attribute set are fixed here as a stub. This file
 * does not read environment variables, files, the database or remote services.
 *
 * Future direction: a seller data source will provide the same rows through the
 * accessors below, so templates will not need to change.
 *
 * Spec: clarify-2c698d44.
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the additional product attributes contract.
 *
 * The contract documents:
 * - the spec identifier and the explicit `mocked` marker;
 * - the mandatory card attributes that are not part of this stub;
 * - the arbitrary additional attribute set and its display labels;
 * - demo values per product slug;
 * - the fixed behaviour for unknown products (an empty list).
 *
 * @return array<string,mixed>
 */
function velotok_core_product_attributes_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$attributes = array(
		'model'    => array(
			'label' => 'Модель',
			'type'  => 'string',
		),
		'frame'    => array(
			'label' => 'Рама',
			'type'  => 'string',
		),
		'warranty' => array(
			'label' => 'Гарантия',
			'type'  => 'string',
		),
		'max_load' => array(
			'label' => 'Максимальная нагрузка',
			'type'  => 'string',
		),
	);

	$values = array(
		'riviera'      => array(
			'model'    => 'Riviera City',
			'frame'    => 'Алюминий 6061',
			'warranty' => '2 года',
		),
		'street-7'     => array(
			'model'    => 'Street 7',
			'frame'    => 'Алюминий 6061',
			'warranty' => '2 года',
		),
		'hrebet-pro'   => array(
			'model'    => 'Hrebet Pro',
			'frame'    => 'Алюминий 6061',
			'warranty' => '2 года',
		),
		'hrebet-trail' => array(
			'model'    => 'Hrebet Trail',
			'frame'    => 'Алюминий 6061',
			'warranty' => '2 года',
		),
		'family'       => array(
			'model'    => 'Family Cargo',
			'frame'    => 'Сталь',
			'warranty' => '2 года',
			'max_load' => '80 кг',
		),
		'courier'      => array(
			'model'    => 'Courier Cargo',
			'frame'    => 'Сталь',
			'warranty' => '2 года',
			'max_load' => '120 кг',
		),
		'sklad-20'     => array(
			'model'    => 'Sklad 20',
			'frame'    => 'Алюминий 6061',
			'warranty' => '2 года',
		),
	);

	$contract = array(
		'spec'                      => 'clarify-2c698d44',
		'source'                    => 'stub',
		'mocked'                    => true,
		'missing_key'               => 'empty_array',
		'future_source'             => 'seller',
		'card_mandatory_attributes' => array( 'photo', 'name', 'description', 'price' ),
		'attributes'                => $attributes,
		'values'                    => $values,
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer an explicit spec name.
 *
 * @return array<string,mixed>
 */
function velotok_core_product_attributes() {
	return velotok_core_product_attributes_contract();
}

/**
 * Check whether the stub has additional attributes for a product.
 *
 * @param mixed $slug Product slug.
 * @return bool
 */
function velotok_core_product_attributes_has( $slug ) {
	if ( ! is_string( $slug ) || '' === $slug ) {
		return false;
	}

	$contract = velotok_core_product_attributes_contract();

	return isset( $contract['values'][ $slug ] ) && is_array( $contract['values'][ $slug ] );
}

/**
 * Return additional attributes for a product as label/value pairs.
 *
 * Unknown products always return an empty list. This behaviour is part of the
 * contract (`missing_key => empty_array`) and must not change without a spec
 * update.
 *
 * @param mixed $slug Product slug.
 * @return array<int,array{label:string,value:string}>
 */
function velotok_core_product_attributes_get( $slug ) {
	if ( ! velotok_core_product_attributes_has( $slug ) ) {
		return array();
	}

	$contract = velotok_core_product_attributes_contract();
	$values   = $contract['values'][ $slug ];
	$rows     = array();

	foreach ( $contract['attributes'] as $key => $attribute ) {
		if ( ! isset( $values[ $key ] ) ) {
			continue;
		}

		$value = trim( (string) $values[ $key ] );

		if ( '' === $value ) {
			continue;
		}

		$rows[] = array(
			'label' => $attribute['label'],
			'value' => $value,
		);
	}

	return $rows;
}

/**
 * Return additional attributes for a product as card-ready tuples.
 *
 * @param mixed $slug Product slug.
 * @return array<int,array{0:string,1:string}>
 */
function velotok_core_product_attributes_rows( $slug ) {
	$rows = array();

	foreach ( velotok_core_product_attributes_get( $slug ) as $row ) {
		$rows[] = array( $row['label'], $row['value'] );
	}

	return $rows;
}
