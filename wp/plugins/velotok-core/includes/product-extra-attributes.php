<?php
/**
 * Product extra attributes stub contract.
 *
 * This is the single access point for the additional (non-required) product
 * attributes shown on catalog cards and product pages. The current iteration
 * deliberately uses a fixed in-memory stub: it does not read the database,
 * files, remote services or a seller API.
 *
 * The card contract requires four attributes: photo, title, description and
 * price. Everything else is an extra attribute and may be supplied by sellers
 * in an arbitrary shape once real sellers appear. Until then the contract and
 * the demo value set are mocked.
 *
 * Spec: clarify-2c698d44.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

/**
 * Return the product extra attributes contract.
 *
 * The contract documents:
 * - the spec identifier and the explicit `mocked` marker;
 * - the four required card attributes that stay separate from extras;
 * - the known extra attribute directory and its display order;
 * - the arbitrary-key rules (unknown keys are rendered, empty values hidden);
 * - the mocked value set used while there are no real sellers;
 * - the open questions that block a real seller integration.
 *
 * @return array<string,mixed>
 */
function velotok_core_product_extra_attributes_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$required = array( 'photo', 'title', 'description', 'price' );

	$definitions = array(
		'model'       => array(
			'label'    => 'Модель',
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
		'battery'     => array(
			'label'    => 'Аккумулятор',
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
		'brakes'      => array(
			'label'    => 'Тормоза',
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
		'frame'       => array(
			'label'    => 'Рама',
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
		'max_load_kg' => array(
			'label'    => 'Макс. нагрузка, кг',
			'type'     => 'number',
			'required' => false,
			'mocked'   => true,
		),
	);

	$default_values = array(
		'model'       => 'Velotok',
		'battery'     => '48 В · 15 Ач',
		'brakes'      => 'Гидравлические дисковые',
		'frame'       => 'Алюминий 6061',
		'max_load_kg' => '120',
	);

	$stub_values = array(
		'default'   => $default_values,
		'riviera'   => array(
			'model'       => 'Ривьера',
			'battery'     => '48 В · 15 Ач',
			'brakes'      => 'V-brake',
			'frame'       => 'Алюминий 6061',
			'max_load_kg' => '120',
		),
		'street-7'  => array(
			'model'       => 'Стрит 7',
			'battery'     => '48 В · 17 Ач',
			'brakes'      => 'Гидравлические дисковые',
			'frame'       => 'Алюминий 6061',
			'max_load_kg' => '110',
		),
		'street'    => array(
			'model'       => 'Стрит 7',
			'battery'     => '48 В · 17 Ач',
			'brakes'      => 'Гидравлические дисковые',
			'frame'       => 'Алюминий 6061',
			'max_load_kg' => '110',
		),
		'hrebet-pro' => array(
			'model'       => 'Хребет Pro',
			'battery'     => '48 В · 20 Ач',
			'brakes'      => 'Гидравлические дисковые',
			'frame'       => 'Алюминий 6061, полная подвеска',
			'max_load_kg' => '130',
		),
		'hrebet-trail' => array(
			'model'       => 'Хребет Trail',
			'battery'     => '48 В · 17 Ач',
			'brakes'      => 'Дисковые гидравлические',
			'frame'       => 'Алюминий 6061, хардтейл',
			'max_load_kg' => '120',
		),
		'family'    => array(
			'model'       => 'Семейный',
			'battery'     => '48 В · 20 Ач',
			'brakes'      => 'Гидравлические дисковые',
			'frame'       => 'Алюминий 6061, удлинённая',
			'max_load_kg' => '180',
		),
		'courier'   => array(
			'model'       => 'Курьер',
			'battery'     => '48 В · 17 Ач',
			'brakes'      => 'Гидравлические дисковые',
			'frame'       => 'Алюминий 6061, усиленная',
			'max_load_kg' => '160',
		),
		'sklad'     => array(
			'model'       => 'Складень 20',
			'battery'     => '36 В · 10 Ач',
			'brakes'      => 'Дисковые механические',
			'frame'       => 'Алюминий 6061, складная',
			'max_load_kg' => '100',
		),
		'sklad-20'  => array(
			'model'       => 'Складень 20',
			'battery'     => '36 В · 10 Ач',
			'brakes'      => 'Дисковые механические',
			'frame'       => 'Алюминий 6061, складная',
			'max_load_kg' => '100',
		),
	);

	$contract = array(
		'spec'                   => 'clarify-2c698d44',
		'source'                 => 'stub',
		'mocked'                 => true,
		'parent_spec'            => 'ecommerce-store',
		'required_attributes'    => $required,
		'additional_attributes'  => $definitions,
		'extra_attributes'       => $definitions,
		'attributes'             => $definitions,
		'arbitrary'              => true,
		'allow_arbitrary'        => true,
		'key_format'             => 'slug',
		'value_types'            => array( 'string', 'number', 'bool' ),
		'unknown_attribute'      => 'rendered_humanized',
		'empty_value'            => 'hidden',
		'order'                  => 'contract_then_input',
		'missing_attribute'      => 'hidden',
		'source_contract'        => 'seller_attributes',
		'future_source'          => 'seller',
		'stub_values'            => $stub_values,
		'default_values'         => $default_values,
		'open_questions'         => array(
			'required_set' => 'Какие дополнительные атрибуты станут обязательными при появлении реальных продавцов и как они соотносятся с уже заданными произвольно?',
			'source'       => 'Каков источник данных дополнительных атрибутов (внешняя система продавцов, импорт, ручной ввод) и требуется ли валидация по схеме?',
			'filtering'    => 'Нужна ли фильтрация и сортировка каталога по дополнительным атрибутам после фиксации их набора?',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer a shorter contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_extra_attributes_contract() {
	return velotok_core_product_extra_attributes_contract();
}

/**
 * Alias kept for callers that use the "additional" wording.
 *
 * @return array<string,mixed>
 */
function velotok_core_additional_attributes_contract() {
	return velotok_core_product_extra_attributes_contract();
}

/**
 * Return the known extra attribute definitions.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_extra_attribute_definitions() {
	$contract = velotok_core_product_extra_attributes_contract();

	return $contract['additional_attributes'];
}

/**
 * Return the declared scalar type for an extra attribute key.
 *
 * @param mixed $key Attribute key.
 * @return string Empty string for an unknown key.
 */
function velotok_core_extra_attribute_type( $key ) {
	$definitions = velotok_core_extra_attribute_definitions();

	if ( is_string( $key ) && isset( $definitions[ $key ]['type'] ) ) {
		return (string) $definitions[ $key ]['type'];
	}

	return '';
}

/**
 * Return a human readable label for an extra attribute key.
 *
 * Known attributes use the contract label. Arbitrary keys are humanized so a
 * seller can add a new attribute without changing the template.
 *
 * @param mixed $key Attribute key.
 * @return string
 */
function velotok_core_extra_attribute_label( $key ) {
	$definitions = velotok_core_extra_attribute_definitions();

	if ( is_string( $key ) && isset( $definitions[ $key ]['label'] ) ) {
		return (string) $definitions[ $key ]['label'];
	}

	$label = is_scalar( $key ) ? trim( (string) $key ) : '';
	$label = (string) preg_replace( '/[\s_-]+/', ' ', $label );

	if ( '' === $label ) {
		return 'Атрибут';
	}

	if ( function_exists( 'mb_strtoupper' ) && function_exists( 'mb_substr' ) ) {
		return mb_strtoupper( mb_substr( $label, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $label, 1, null, 'UTF-8' );
	}

	return ucfirst( $label );
}

/**
 * Return the mocked extra attribute values for a product slug.
 *
 * The stub never touches the database. Unknown slugs fall back to the default
 * demo set so every catalog card still demonstrates the contract.
 *
 * @param mixed $slug Product slug.
 * @return array<string,mixed>
 */
function velotok_core_extra_attribute_stub_values( $slug = '' ) {
	$contract = velotok_core_product_extra_attributes_contract();
	$key      = is_scalar( $slug ) ? strtolower( trim( (string) $slug ) ) : '';

	if ( '' !== $key && isset( $contract['stub_values'][ $key ] ) ) {
		return $contract['stub_values'][ $key ];
	}

	return $contract['stub_values']['default'];
}

/**
 * Convert an arbitrary extra attribute value to a display string.
 *
 * Arrays may use a `value` key. Booleans become yes/no. Empty and non-scalar
 * values are hidden by the contract.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function velotok_core_extra_attribute_value( $value ) {
	if ( is_array( $value ) ) {
		foreach ( array( 'value', 'val', 'text' ) as $key ) {
			if ( array_key_exists( $key, $value ) ) {
				$value = $value[ $key ];
				break;
			}
		}
	}

	if ( is_bool( $value ) ) {
		return $value ? 'Да' : 'Нет';
	}

	if ( is_scalar( $value ) ) {
		return trim( (string) $value );
	}

	if ( is_object( $value ) && method_exists( $value, '__toString' ) ) {
		return trim( (string) $value );
	}

	return '';
}

/**
 * Infer the scalar type of a raw value.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function velotok_core_extra_attribute_inferred_type( $value ) {
	if ( is_bool( $value ) ) {
		return 'bool';
	}

	if ( is_int( $value ) || is_float( $value ) ) {
		return 'number';
	}

	return 'string';
}

/**
 * Normalize one arbitrary extra attribute into the display contract.
 *
 * @param mixed $key Raw key or numeric index.
 * @param mixed $raw Raw value, or an array with label/value keys.
 * @return array<string,mixed>|null Null when the value must be hidden.
 */
function velotok_core_normalize_extra_attribute( $key, $raw ) {
	$label = '';
	$type  = '';
	$value = $raw;

	if ( is_array( $raw ) ) {
		if ( isset( $raw['key'] ) && is_scalar( $raw['key'] ) ) {
			$key = $raw['key'];
		}

		if ( isset( $raw['label'] ) && is_scalar( $raw['label'] ) ) {
			$label = (string) $raw['label'];
		}

		if ( isset( $raw['type'] ) && is_scalar( $raw['type'] ) ) {
			$type = (string) $raw['type'];
		}

		if ( array_key_exists( 'value', $raw ) ) {
			$value = $raw['value'];
		} elseif ( array_key_exists( 'val', $raw ) ) {
			$value = $raw['val'];
		} elseif ( array_key_exists( 1, $raw ) ) {
			$value = $raw[1];

			if ( '' === $label && isset( $raw[0] ) && is_scalar( $raw[0] ) ) {
				$label = (string) $raw[0];
			}
		} else {
			return null;
		}
	}

	$display = velotok_core_extra_attribute_value( $value );

	if ( '' === $display ) {
		return null;
	}

	if ( '' === $label ) {
		$label = velotok_core_extra_attribute_label( $key );
	}

	if ( '' === $type ) {
		$type = velotok_core_extra_attribute_type( $key );

		if ( '' === $type ) {
			$type = velotok_core_extra_attribute_inferred_type( $value );
		}
	}

	return array(
		'key'    => is_scalar( $key ) ? (string) $key : '',
		'label'  => $label,
		'value'  => $display,
		'type'   => $type,
		'mocked' => true,
	);
}

/**
 * Normalize an arbitrary extra attribute map or list.
 *
 * Known attributes keep the contract order. Unknown (arbitrary) attributes are
 * appended in the order supplied by the caller. Empty values are hidden.
 *
 * @param mixed $values Attribute map or list.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_normalize_extra_attributes( $values ) {
	if ( ! is_array( $values ) ) {
		return array();
	}

	$definitions = velotok_core_extra_attribute_definitions();
	$normalized  = array();
	$seen        = array();

	foreach ( $definitions as $key => $definition ) {
		if ( ! array_key_exists( $key, $values ) ) {
			continue;
		}

		$item = velotok_core_normalize_extra_attribute( $key, $values[ $key ] );

		if ( null !== $item ) {
			$normalized[]    = $item;
			$seen[ $key ]    = true;
		}
	}

	foreach ( $values as $key => $raw ) {
		if ( is_string( $key ) && isset( $seen[ $key ] ) ) {
			continue;
		}

		$item = velotok_core_normalize_extra_attribute( $key, $raw );

		if ( null !== $item ) {
			$normalized[] = $item;

			if ( is_string( $key ) ) {
				$seen[ $key ] = true;
			}
		}
	}

	return $normalized;
}

/**
 * Return display-ready extra attributes for a product.
 *
 * The first argument accepts a product array (with `extra_attributes` or
 * `additional_attributes`), a product object with `get_slug()`, a slug string
 * or a product id. A product id and unknown slugs resolve to the mocked demo
 * values because the stub never reads the database. An explicit values array
 * always wins and may use arbitrary keys.
 *
 * @param mixed $product Product data, slug, object or id.
 * @param mixed $values  Optional explicit attribute map or list.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_get_product_extra_attributes( $product = 0, $values = null ) {
	$contract = velotok_core_product_extra_attributes_contract();
	$slug     = '';
	$input    = null;

	if ( is_object( $product ) ) {
		if ( method_exists( $product, 'get_slug' ) ) {
			$slug = (string) $product->get_slug();
		}
	} elseif ( is_string( $product ) ) {
		$slug = $product;
	} elseif ( is_array( $product ) ) {
		$is_product_data = false;

		foreach ( array( 'slug', 'name', 'title', 'id', 'product_id', 'extra_attributes', 'additional_attributes' ) as $field ) {
			if ( array_key_exists( $field, $product ) ) {
				$is_product_data = true;
				break;
			}
		}

		if ( $is_product_data ) {
			if ( isset( $product['slug'] ) && is_scalar( $product['slug'] ) ) {
				$slug = (string) $product['slug'];
			}

			if ( isset( $product['extra_attributes'] ) && is_array( $product['extra_attributes'] ) ) {
				$input = $product['extra_attributes'];
			} elseif ( isset( $product['additional_attributes'] ) && is_array( $product['additional_attributes'] ) ) {
				$input = $product['additional_attributes'];
			}
		} else {
			$input = $product;
		}
	}

	if ( null !== $values && is_array( $values ) ) {
		$input = $values;
	}

	if ( null === $input ) {
		$input = velotok_core_extra_attribute_stub_values( $slug );
	}

	return velotok_core_normalize_extra_attributes( $input );
}

/**
 * Alias kept for callers that prefer the product-prefixed name.
 *
 * @param mixed $product Product data, slug, object or id.
 * @param mixed $values  Optional explicit attribute map or list.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_product_extra_attributes( $product = 0, $values = null ) {
	return velotok_core_get_product_extra_attributes( $product, $values );
}

/**
 * Short alias for the extra attributes accessor.
 *
 * @param mixed $product Product data, slug, object or id.
 * @param mixed $values  Optional explicit attribute map or list.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_extra_attributes( $product = 0, $values = null ) {
	return velotok_core_get_product_extra_attributes( $product, $values );
}

/**
 * Normalize arbitrary values without product resolution.
 *
 * @param mixed $values Attribute map or list.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_extra_attributes_from_values( $values ) {
	return velotok_core_normalize_extra_attributes( $values );
}

/**
 * Return the open questions that block a real seller integration.
 *
 * @return array<string,string>
 */
function velotok_core_extra_attributes_open_questions() {
	$contract = velotok_core_product_extra_attributes_contract();

	return $contract['open_questions'];
}

if ( ! function_exists( 'getProductExtraAttributes' ) ) {
	/**
	 * Short global alias for the extra attributes accessor.
	 *
	 * @param mixed $product Product data, slug, object or id.
	 * @param mixed $values  Optional explicit attribute map or list.
	 * @return array<int,array<string,mixed>>
	 */
	function getProductExtraAttributes( $product = 0, $values = null ) {
		return velotok_core_get_product_extra_attributes( $product, $values );
	}
}
