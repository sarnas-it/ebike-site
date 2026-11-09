<?php
/**
 * Product card attribute validation stub.
 *
 * The card contract requires four attributes: photo, title, description and
 * price. This file is the single mocked source for the required attribute list
 * and the validation rules used by the shop.
 *
 * The current iteration is deliberately a fixed in-memory stub: it does not
 * read the database, files, remote services or image storage. Real image
 * handling and the external category directory are out of scope for this
 * contract.
 *
 * Spec: clarify-41b1da6d.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

/**
 * Return the product validation contract.
 *
 * The contract documents the spec identifier, the explicit `mocked` marker,
 * the required attributes, their error codes, limits, supported image MIME
 * types and the fixed category attribute directory.
 *
 * @return array<string,mixed>
 */
function velotok_core_product_validation_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$required = array( 'photo', 'title', 'description', 'price' );

	$attributes = array(
		'photo'       => array(
			'required'    => true,
			'type'        => 'attachment_id',
			'mocked'      => true,
			'spec'        => 'clarify-9e1a1acd',
			'error_codes' => array(
				'required' => 'PHOTO_REQUIRED',
				'format'   => 'PHOTO_INVALID_FORMAT',
			),
		),
		'title'       => array(
			'required'    => true,
			'type'        => 'string',
			'mocked'      => true,
			'spec'        => 'clarify-9e1a1acd',
			'trim'        => true,
			'error_codes' => array(
				'required' => 'TITLE_REQUIRED',
				'too_long' => 'TITLE_TOO_LONG',
			),
		),
		'description' => array(
			'required'         => true,
			'type'             => 'string',
			'mocked'           => true,
			'spec'             => 'clarify-9e1a1acd',
			'trim'             => true,
			'blank_is_missing' => true,
			'error_codes'      => array(
				'required' => 'DESCRIPTION_REQUIRED',
			),
		),
		'price'       => array(
			'required'         => true,
			'type'             => 'number',
			'mocked'           => true,
			'spec'             => 'clarify-9e1a1acd',
			'trim'             => true,
			'blank_is_missing' => true,
			'allow_zero'       => true,
			'allow_negative'   => false,
			'error_codes'      => array(
				'required' => 'PRICE_REQUIRED',
				'format'   => 'PRICE_INVALID_FORMAT',
				'negative' => 'PRICE_NEGATIVE',
			),
		),
	);

	$category_attributes = array(
		'city'     => $required,
		'mountain' => $required,
		'cargo'    => $required,
		'folding'  => $required,
		'default'  => $required,
	);

	$contract = array(
		'spec'                         => 'clarify-41b1da6d',
		'requirement'                  => 'TESTEB-61',
		'price_requirement'            => 'TESTEB-62',
		'server_side'                  => true,
		'validated_on_server'          => true,
		'source'                       => 'stub',
		'mocked'                       => true,
		'required_attributes'          => $required,
		'attributes'                   => $attributes,
		'limits'                       => array(
			'title_max_length'       => 200,
			'description_max_length' => null,
		),
		'supported_image_mime_types'   => array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/avif',
		),
		'category_required_attributes' => $category_attributes,
		'category_source'              => 'local_stub',
		'unknown_category'             => 'default_attributes',
		'future_source'                => 'woocommerce',
		'missing_attribute'            => 'error_code',
		'open_questions'               => array(
			'Какие именно форматы изображений считаются поддерживаемыми и есть ли ограничения по размеру файла и разрешению?',
			'Требуется ли минимальное количество фото и отдельное обязательное главное фото?',
			'Допускается ли нулевая цена как валидное значение во всех категориях или только в отдельных (например, бесплатные позиции)?',
			'Каковы предельные длины названия и описания, и различаются ли они по категориям?',
			'Источник справочника категорий и их обязательных атрибутов: локальная конфигурация, внешняя система или комбинация с приоритетами?',
			'Как должны формулироваться сообщения об ошибках валидации и требуется ли их локализация?',
		),
	);

	return $contract;
}

/**
 * Return the open questions recorded by the product validation contract.
 *
 * @return string[]
 */
function velotok_core_product_validation_open_questions() {
	$contract = velotok_core_product_validation_contract();

	return $contract['open_questions'];
}

/**
 * Alias kept for callers that prefer an explicit contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_validation_contract() {
	return velotok_core_product_validation_contract();
}

/**
 * Return the mocked rule for a single required product attribute.
 *
 * Every rule is an in-memory stub: it is explicitly marked `mocked => true`
 * and references the spec, so removing the contract makes its callers fail.
 *
 * @param string $attribute Attribute key.
 * @return array<string,mixed>|null
 */
function velotok_core_product_attribute_rule( $attribute ) {
	$contract = velotok_core_product_validation_contract();

	if ( ! is_string( $attribute ) || ! isset( $contract['attributes'][ $attribute ] ) ) {
		return null;
	}

	return $contract['attributes'][ $attribute ];
}

/**
 * Return the mocked category attribute contract.
 *
 * The directory is a fixed local stub. It does not synchronize with an
 * external category system and returns the same required list for every
 * known and unknown category.
 *
 * @return array<string,mixed>
 */
function velotok_core_required_attributes_contract() {
	$contract = velotok_core_product_validation_contract();

	return array(
		'spec'                  => $contract['spec'],
		'source'                => 'stub',
		'mocked'                => true,
		'required_attributes'   => $contract['required_attributes'],
		'categories'            => $contract['category_required_attributes'],
		'category_source'       => $contract['category_source'],
		'unknown_category'      => $contract['unknown_category'],
	);
}

/**
 * Return every validation error code declared by the contract.
 *
 * @return string[]
 */
function velotok_core_validation_error_codes() {
	$contract = velotok_core_product_validation_contract();
	$codes    = array();

	foreach ( $contract['attributes'] as $attribute ) {
		foreach ( $attribute['error_codes'] as $code ) {
			$codes[] = $code;
		}
	}

	return array_values( array_unique( $codes ) );
}

/**
 * Return the supported image MIME types.
 *
 * @return string[]
 */
function velotok_core_supported_image_mime_types() {
	$contract = velotok_core_product_validation_contract();

	return $contract['supported_image_mime_types'];
}

/**
 * Check whether an image MIME type is supported by the contract.
 *
 * @param mixed $mime MIME type.
 * @return bool
 */
function velotok_core_is_supported_image_mime( $mime ) {
	if ( ! is_string( $mime ) ) {
		return false;
	}

	$mime = strtolower( trim( $mime ) );
	$mime = (string) preg_replace( '/\s*;.*$/', '', $mime );

	return in_array( $mime, velotok_core_supported_image_mime_types(), true );
}

/**
 * Return the required attributes for a product category.
 *
 * The stub returns the same fixed list for every known and unknown category.
 * Category synchronization and conflict resolution are out of scope.
 *
 * @param mixed $category Category slug or term.
 * @return string[]
 */
function velotok_core_get_required_attributes( $category = '' ) {
	$contract = velotok_core_required_attributes_contract();
	$key      = is_string( $category ) ? strtolower( trim( $category ) ) : '';

	if ( '' !== $key && isset( $contract['categories'][ $key ] ) ) {
		return $contract['categories'][ $key ];
	}

	return $contract['categories']['default'];
}

/**
 * Alias for callers that use a shorter name.
 *
 * @param mixed $category Category slug or term.
 * @return string[]
 */
function velotok_core_required_attributes( $category = '' ) {
	return velotok_core_get_required_attributes( $category );
}

/**
 * Return a string length in characters, with a byte fallback.
 *
 * @param string $value Value.
 * @return int
 */
function velotok_core_validation_strlen( $value ) {
	if ( function_exists( 'mb_strlen' ) ) {
		return (int) mb_strlen( $value, 'UTF-8' );
	}

	$matched = preg_match_all( '/./us', (string) $value, $characters );

	if ( false !== $matched ) {
		return (int) $matched;
	}

	return (int) strlen( (string) $value );
}

/**
 * Build a per-attribute validation result.
 *
 * @param bool        $valid   Whether the value is valid.
 * @param string|null $code    First error code, if any.
 * @param mixed       $value   Original value.
 * @param string[]    $errors  Error codes.
 * @return array<string,mixed>
 */
function velotok_core_validation_result( $valid, $code, $value, $errors = array() ) {
	if ( null !== $code && ! $errors ) {
		$errors = array( $code );
	}

	return array(
		'valid'  => (bool) $valid,
		'code'   => $code,
		'error'  => $code,
		'errors' => array_values( $errors ),
		'value'  => $value,
	);
}

/**
 * Validate a product title.
 *
 * Empty strings, whitespace-only strings and non-scalar values produce
 * TITLE_REQUIRED. Values above the configured limit produce TITLE_TOO_LONG.
 *
 * @param mixed $title Product title.
 * @return array<string,mixed>
 */
function velotok_core_validate_product_title( $title ) {
	$contract = velotok_core_product_validation_contract();
	$rule     = velotok_core_product_attribute_rule( 'title' );
	$max      = (int) $contract['limits']['title_max_length'];
	$value    = is_scalar( $title ) ? (string) $title : '';

	if ( ! empty( $rule['trim'] ) ) {
		$value = trim( $value );
	}

	if ( '' === $value ) {
		return velotok_core_validation_result( false, $rule['error_codes']['required'], $title );
	}

	if ( velotok_core_validation_strlen( $value ) > $max ) {
		return velotok_core_validation_result( false, $rule['error_codes']['too_long'], $title );
	}

	return velotok_core_validation_result( true, null, $title );
}

/**
 * Alias for title validation.
 *
 * @param mixed $title Product title.
 * @return array<string,mixed>
 */
function velotok_core_validate_title( $title ) {
	return velotok_core_validate_product_title( $title );
}

/**
 * Return the mocked description validation rule.
 *
 * The rule is a fixed in-memory stub for spec clarify-9e1a1acd and is the
 * single source for description validation. It never reads the database,
 * files, media library or remote services.
 *
 * @return array<string,mixed>
 */
function velotok_core_description_validation_contract() {
	$contract = velotok_core_product_validation_contract();

	return $contract['attributes']['description'];
}

/**
 * Validate a product description.
 *
 * Empty strings, whitespace-only strings and non-scalar values produce
 * DESCRIPTION_REQUIRED. The rule is taken from the mocked description
 * validation contract.
 *
 * @param mixed $description Product description.
 * @return array<string,mixed>
 */
function velotok_core_validate_product_description( $description ) {
	$rule  = velotok_core_description_validation_contract();
	$value = is_scalar( $description ) ? (string) $description : '';

	if ( ! empty( $rule['trim'] ) ) {
		$value = trim( $value );
	}

	if ( '' === $value ) {
		return velotok_core_validation_result( false, $rule['error_codes']['required'], $description );
	}

	return velotok_core_validation_result( true, null, $description );
}

/**
 * Alias for description validation.
 *
 * @param mixed $description Product description.
 * @return array<string,mixed>
 */
function velotok_core_validate_description( $description ) {
	return velotok_core_validate_product_description( $description );
}

/**
 * Return the mocked price validation rule.
 *
 * The rule is a fixed in-memory stub for spec clarify-9e1a1acd and is the
 * single source for price validation. It never reads the database, files,
 * currency services or remote price feeds.
 *
 * @return array<string,mixed>
 */
function velotok_core_price_validation_contract() {
	$contract = velotok_core_product_validation_contract();

	return $contract['attributes']['price'];
}

/**
 * Validate a product price.
 *
 * Numeric values are accepted, including zero. A non-numeric value produces
 * PRICE_INVALID_FORMAT, a negative value produces PRICE_NEGATIVE and a missing
 * value produces PRICE_REQUIRED. The rule is taken from the mocked price
 * validation contract.
 *
 * @param mixed $price Product price.
 * @return array<string,mixed>
 */
function velotok_core_validate_product_price( $price ) {
	$rule = velotok_core_price_validation_contract();

	if ( null === $price || ( is_string( $price ) && '' === trim( $price ) ) ) {
		return velotok_core_validation_result( false, $rule['error_codes']['required'], $price );
	}

	if ( is_string( $price ) ) {
		$normalized = trim( $price );

		if ( ! preg_match( '/^[+-]?(?:\d+(?:[.,]\d+)?|[.,]\d+)$/', $normalized ) ) {
			return velotok_core_validation_result( false, $rule['error_codes']['format'], $price );
		}

		$number = (float) str_replace( ',', '.', $normalized );
	} elseif ( is_int( $price ) || ( is_float( $price ) && is_finite( $price ) ) ) {
		$number = (float) $price;
	} else {
		return velotok_core_validation_result( false, $rule['error_codes']['format'], $price );
	}

	if ( $number < 0 ) {
		return velotok_core_validation_result( false, $rule['error_codes']['negative'], $price );
	}

	return velotok_core_validation_result( true, null, $price );
}

/**
 * Alias for price validation.
 *
 * @param mixed $price Product price.
 * @return array<string,mixed>
 */
function velotok_core_validate_price( $price ) {
	return velotok_core_validate_product_price( $price );
}

/**
 * Extract the attachment id and MIME type from a photo value.
 *
 * The stub accepts a scalar id as well as an array with `id`/`photo_id` and
 * `mime`/`mime_type` keys. It never touches the media library.
 *
 * @param mixed $photo Photo value.
 * @return array{id:mixed,mime:mixed}
 */
function velotok_core_photo_parts( $photo ) {
	$id   = $photo;
	$mime = null;

	if ( is_array( $photo ) ) {
		$id   = null;
		$mime = null;

		foreach ( array( 'id', 'photo_id', 'image_id', 'attachment_id' ) as $key ) {
			if ( array_key_exists( $key, $photo ) ) {
				$id = $photo[ $key ];
				break;
			}
		}

		foreach ( array( 'mime', 'mime_type', 'type' ) as $key ) {
			if ( array_key_exists( $key, $photo ) ) {
				$mime = $photo[ $key ];
				break;
			}
		}
	}

	return array(
		'id'   => $id,
		'mime' => $mime,
	);
}

/**
 * Return the mocked photo validation rule.
 *
 * The rule is a fixed in-memory stub for spec clarify-9e1a1acd and is the
 * single source for photo validation. It never reads the database, files,
 * media library or remote image storage.
 *
 * @return array<string,mixed>
 */
function velotok_core_photo_validation_contract() {
	$contract = velotok_core_product_validation_contract();

	return $contract['attributes']['photo'];
}

/**
 * Check whether a photo id is present.
 *
 * @param mixed $id Attachment id.
 * @return bool
 */
function velotok_core_photo_id_is_present( $id ) {
	if ( null === $id || false === $id || array() === $id ) {
		return false;
	}

	if ( is_int( $id ) || is_float( $id ) ) {
		return $id > 0;
	}

	if ( is_string( $id ) ) {
		return '' !== trim( $id ) && '0' !== trim( $id );
	}

	return false;
}

/**
 * Validate a product photo.
 *
 * A missing attachment id produces PHOTO_REQUIRED. When a MIME type is
 * supplied, a type outside the contract produces PHOTO_INVALID_FORMAT.
 *
 * @param mixed       $photo Photo value.
 * @param string|null $mime  Optional MIME type.
 * @return array<string,mixed>
 */
function velotok_core_validate_product_photo( $photo, $mime = null ) {
	$rule  = velotok_core_photo_validation_contract();
	$parts = velotok_core_photo_parts( $photo );

	if ( null === $mime && null !== $parts['mime'] ) {
		$mime = $parts['mime'];
	}

	if ( ! velotok_core_photo_id_is_present( $parts['id'] ) ) {
		return velotok_core_validation_result( false, $rule['error_codes']['required'], $photo );
	}

	if ( null !== $mime && '' !== $mime && ! velotok_core_is_supported_image_mime( $mime ) ) {
		return velotok_core_validation_result( false, $rule['error_codes']['format'], $photo );
	}

	return velotok_core_validation_result( true, null, $photo );
}

/**
 * Alias for photo validation.
 *
 * @param mixed       $photo Photo value.
 * @param string|null $mime  Optional MIME type.
 * @return array<string,mixed>
 */
function velotok_core_validate_photo( $photo, $mime = null ) {
	return velotok_core_validate_product_photo( $photo, $mime );
}

/**
 * Read the first existing key from a product array.
 *
 * @param array<string,mixed> $product Product data.
 * @param string[]            $keys    Candidate keys.
 * @param mixed               $default Default value.
 * @return mixed
 */
function velotok_core_product_value( $product, $keys, $default = null ) {
	foreach ( $keys as $key ) {
		if ( array_key_exists( $key, $product ) ) {
			return $product[ $key ];
		}
	}

	return $default;
}

/**
 * Validate every required product attribute.
 *
 * The result contains a flat `errors` list of error codes, `missing`
 * attributes, the per-attribute results and the category attribute list.
 *
 * @param array<string,mixed> $product  Product data.
 * @param mixed               $category Category slug or term.
 * @return array<string,mixed>
 */
function velotok_core_validate_product( $product, $category = '' ) {
	if ( ! is_array( $product ) ) {
		$product = array();
	}

	if ( '' === $category || null === $category ) {
		$category = velotok_core_product_value( $product, array( 'category', 'category_slug', 'product_cat' ), '' );
	}

	$required = velotok_core_get_required_attributes( $category );
	$title    = velotok_core_product_value( $product, array( 'title', 'name', 'product_title' ) );
	$desc     = velotok_core_product_value( $product, array( 'description', 'product_description', 'body' ) );
	$price    = velotok_core_product_value( $product, array( 'price', 'regular_price' ) );
	$photo    = velotok_core_product_value( $product, array( 'photo', 'photo_id', 'image', 'image_id', 'thumbnail_id', 'attachment_id' ) );
	$mime     = velotok_core_product_value( $product, array( 'photo_mime', 'image_mime', 'mime', 'mime_type' ) );

	$results = array(
		'photo'       => velotok_core_validate_product_photo( $photo, $mime ),
		'title'       => velotok_core_validate_product_title( $title ),
		'description' => velotok_core_validate_product_description( $desc ),
		'price'       => velotok_core_validate_product_price( $price ),
	);

	$errors_by_attribute = array();
	$errors              = array();
	$missing             = array();

	foreach ( $required as $attribute ) {
		if ( ! isset( $results[ $attribute ] ) || $results[ $attribute ]['valid'] ) {
			continue;
		}

		$code                             = $results[ $attribute ]['code'];
		$errors[]                         = $code;
		$errors_by_attribute[ $attribute ] = $code;
		$missing[]                        = $attribute;
	}

	return array(
		'valid'              => array() === $errors,
		'spec'               => 'clarify-41b1da6d',
		'mocked'             => true,
		'category'           => $category,
		'required_attributes' => $required,
		'attributes'         => $results,
		'errors'             => array_values( $errors ),
		'error_codes'        => array_values( $errors ),
		'codes'              => array_values( $errors ),
		'error'              => $errors ? $errors[0] : null,
		'errors_by_attribute' => $errors_by_attribute,
		'missing'            => array_values( $missing ),
		'missing_attributes' => array_values( $missing ),
	);
}

/**
 * Alias for full product validation.
 *
 * @param array<string,mixed> $product  Product data.
 * @param mixed               $category Category slug or term.
 * @return array<string,mixed>
 */
function velotok_core_validate_product_attributes( $product, $category = '' ) {
	return velotok_core_validate_product( $product, $category );
}

/**
 * Validate a product before publication.
 *
 * Publication is blocked when any required attribute is missing or invalid.
 *
 * @param array<string,mixed> $product  Product data.
 * @param mixed               $category Category slug or term.
 * @return array<string,mixed>
 */
function velotok_core_validate_product_publication( $product, $category = '' ) {
	$result             = velotok_core_validate_product( $product, $category );
	$result['can_publish'] = $result['valid'];
	$result['blocked']     = ! $result['valid'];
	$result['published']   = $result['valid'];

	return $result;
}

/**
 * Check whether a product can be published.
 *
 * @param array<string,mixed> $product  Product data.
 * @param mixed               $category Category slug or term.
 * @return bool
 */
function velotok_core_can_publish_product( $product, $category = '' ) {
	$result = velotok_core_validate_product_publication( $product, $category );

	return (bool) $result['can_publish'];
}

/**
 * Return only the missing product attributes.
 *
 * @param array<string,mixed> $product  Product data.
 * @param mixed               $category Category slug or term.
 * @return string[]
 */
function velotok_core_missing_product_attributes( $product, $category = '' ) {
	$result = velotok_core_validate_product( $product, $category );

	return $result['missing_attributes'];
}

/**
 * Extract the mandatory attributes of a product card.
 *
 * This is the data that is validated and saved by the product card service:
 * photo, title, description and price. The values are returned unchanged so
 * the saved card always contains the mandatory attributes that were checked.
 *
 * @param array<string,mixed> $product Product data.
 * @return array<string,mixed>
 */
function velotok_core_product_card_attributes( $product ) {
	if ( ! is_array( $product ) ) {
		$product = array();
	}

	return array(
		'photo'       => velotok_core_product_value( $product, array( 'photo', 'photo_id', 'image', 'image_id', 'thumbnail_id', 'attachment_id' ) ),
		'photo_mime'  => velotok_core_product_value( $product, array( 'photo_mime', 'image_mime', 'mime', 'mime_type' ) ),
		'title'       => velotok_core_product_value( $product, array( 'title', 'name', 'product_title' ) ),
		'description' => velotok_core_product_value( $product, array( 'description', 'product_description', 'body' ) ),
		'price'       => velotok_core_product_value( $product, array( 'price', 'regular_price' ) ),
	);
}

/**
 * Return the mocked product card save service contract.
 *
 * The service validates the required product card attributes on the server
 * before a card is saved. It is a fixed in-memory stub and never depends on
 * client-side validation, the database or external services.
 *
 * @return array<string,mixed>
 */
function velotok_core_product_card_service_contract() {
	$contract = velotok_core_product_validation_contract();

	return array(
		'spec'                     => 'clarify-41b1da6d',
		'parent_spec'              => $contract['spec'],
		'source'                   => 'stub',
		'mocked'                   => true,
		'service'                  => 'product_card',
		'required_attributes'      => $contract['required_attributes'],
		'validate_on_save'         => true,
		'server_side'              => true,
		'client_validation_only'   => false,
		'saved_status'             => 'saved',
		'blocked_status'           => 'blocked',
		'missing_attributes_key'   => 'missing_attributes',
		'invalid_attributes_key'   => 'invalid_attributes',
		'card_attributes_key'      => 'card_attributes',
		'error_codes'              => velotok_core_validation_error_codes(),
	);
}

/**
 * Validate and save a product card through the mocked server-side service.
 *
 * A card is saved only when every required attribute is present and valid.
 * Missing attributes are reported in `missing_attributes`; attributes that are
 * present but invalid are reported in `invalid_attributes`.
 *
 * @param array<string,mixed> $product  Product data.
 * @param mixed               $category Category slug or term.
 * @return array<string,mixed>
 */
function velotok_core_save_product_card( $product, $category = '' ) {
	$validation = velotok_core_validate_product( $product, $category );
	$card       = velotok_core_product_card_attributes( $product );
	$missing    = array();
	$invalid    = array();

	foreach ( $validation['attributes'] as $attribute => $result ) {
		if ( ! empty( $result['valid'] ) ) {
			continue;
		}

		$code = isset( $result['code'] ) ? (string) $result['code'] : '';

		if ( '_REQUIRED' === substr( $code, -9 ) ) {
			$missing[] = $attribute;
			continue;
		}

		$invalid[] = $attribute;
	}

	$valid = (bool) $validation['valid'];

	return array(
		'saved'                => $valid,
		'blocked'              => ! $valid,
		'status'               => $valid ? 'saved' : 'blocked',
		'valid'                => $valid,
		'spec'                 => 'clarify-41b1da6d',
		'parent_spec'          => $validation['spec'],
		'source'               => 'stub',
		'mocked'               => true,
		'service'              => 'product_card',
		'server_side'          => true,
		'validated_on_save'    => true,
		'category'             => $validation['category'],
		'required_attributes'  => $validation['required_attributes'],
		'card'                 => $card,
		'card_attributes'      => $card,
		'attributes'           => $validation['attributes'],
		'errors'               => $validation['errors'],
		'error_codes'          => $validation['errors'],
		'errors_by_attribute'  => $validation['errors_by_attribute'],
		'missing_attributes'   => array_values( $missing ),
		'invalid_attributes'   => array_values( $invalid ),
		'missing'              => array_values( $missing ),
		'invalid'              => array_values( $invalid ),
	);
}

if ( ! function_exists( 'getRequiredAttributes' ) ) {
	/**
	 * Short global alias for the category attribute directory.
	 *
	 * @param mixed $category Category slug or term.
	 * @return string[]
	 */
	function getRequiredAttributes( $category = '' ) {
		return velotok_core_get_required_attributes( $category );
	}
}

if ( ! function_exists( 'validateTitle' ) ) {
	/**
	 * Short global alias for title validation.
	 *
	 * @param mixed $title Product title.
	 * @return array<string,mixed>
	 */
	function validateTitle( $title ) {
		return velotok_core_validate_product_title( $title );
	}
}

if ( ! function_exists( 'validateDescription' ) ) {
	/**
	 * Short global alias for description validation.
	 *
	 * @param mixed $description Product description.
	 * @return array<string,mixed>
	 */
	function validateDescription( $description ) {
		return velotok_core_validate_product_description( $description );
	}
}

if ( ! function_exists( 'validatePrice' ) ) {
	/**
	 * Short global alias for price validation.
	 *
	 * @param mixed $price Product price.
	 * @return array<string,mixed>
	 */
	function validatePrice( $price ) {
		return velotok_core_validate_product_price( $price );
	}
}

if ( ! function_exists( 'validatePhoto' ) ) {
	/**
	 * Short global alias for photo validation.
	 *
	 * @param mixed       $photo Photo value.
	 * @param string|null $mime  Optional MIME type.
	 * @return array<string,mixed>
	 */
	function validatePhoto( $photo, $mime = null ) {
		return velotok_core_validate_product_photo( $photo, $mime );
	}
}

if ( ! function_exists( 'validateProduct' ) ) {
	/**
	 * Short global alias for full product validation.
	 *
	 * @param array<string,mixed> $product  Product data.
	 * @param mixed               $category Category slug or term.
	 * @return array<string,mixed>
	 */
	function validateProduct( $product, $category = '' ) {
		return velotok_core_validate_product( $product, $category );
	}
}

if ( ! function_exists( 'validateProductForPublication' ) ) {
	/**
	 * Short global alias for publication validation.
	 *
	 * @param array<string,mixed> $product  Product data.
	 * @param mixed               $category Category slug or term.
	 * @return array<string,mixed>
	 */
	function validateProductForPublication( $product, $category = '' ) {
		return velotok_core_validate_product_publication( $product, $category );
	}
}

if ( ! function_exists( 'saveProductCard' ) ) {
	/**
	 * Short global alias for the mocked product card save service.
	 *
	 * @param array<string,mixed> $product  Product data.
	 * @param mixed               $category Category slug or term.
	 * @return array<string,mixed>
	 */
	function saveProductCard( $product, $category = '' ) {
		return velotok_core_save_product_card( $product, $category );
	}
}
