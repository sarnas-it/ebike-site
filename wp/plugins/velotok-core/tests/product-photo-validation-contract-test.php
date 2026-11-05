<?php
/**
 * Contract test for the mocked product photo validation rule.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/product-photo-validation-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/product-photo-validation-contract-test.php
 *
 * The test intentionally fails when the photo stub is removed, stops being
 * marked as mocked or no longer enforces PHOTO_REQUIRED and
 * PHOTO_INVALID_FORMAT. It is the red-green counterpart of the photo rule in
 * includes/product-validation.php.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$velotok_photo_stub = dirname( __DIR__ ) . '/includes/product-validation.php';

if ( ! file_exists( $velotok_photo_stub ) ) {
	fwrite( STDERR, 'FAIL: заглушка контракта валидации фото отсутствует.' . PHP_EOL );
	exit( 1 );
}

require_once $velotok_photo_stub;

$failures = array();

/**
 * Assert a photo contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_product_photo_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Assert that a validation result carries the expected error code.
 *
 * @param array<string,mixed> $result Validation result.
 * @param string              $code   Expected code.
 * @param string              $label  Human-readable check label.
 * @return void
 */
function velotok_product_photo_expect_code( $result, $code, $label ) {
	velotok_product_photo_assert(
		is_array( $result ) && isset( $result['errors'] ) && in_array( $code, $result['errors'], true ),
		sprintf( '%s: ожидался код %s.', $label, $code )
	);
}

/**
 * Assert that a validation result is valid.
 *
 * @param array<string,mixed> $result Validation result.
 * @param string              $label  Human-readable check label.
 * @return void
 */
function velotok_product_photo_expect_valid( $result, $label ) {
	velotok_product_photo_assert(
		is_array( $result ) && isset( $result['valid'] ) && true === $result['valid'],
		sprintf( '%s: фото должно быть валидным.', $label )
	);
	velotok_product_photo_assert(
		is_array( $result ) && isset( $result['errors'] ) && array() === $result['errors'],
		sprintf( '%s: у валидного фото не должно быть ошибок.', $label )
	);
}

/**
 * Build a valid product for the photo checks.
 *
 * @return array<string,mixed>
 */
function velotok_product_photo_valid_product() {
	return array(
		'photo'       => 123,
		'photo_mime'  => 'image/jpeg',
		'title'       => 'Городской велосипед «Ривьера»',
		'description' => 'Лёгкая низкая рама для ежедневных поездок по городу.',
		'price'       => 62900,
		'category'    => 'city',
	);
}

$contract = velotok_core_product_validation_contract();

velotok_product_photo_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
velotok_product_photo_assert(
	isset( $contract['mocked'] ) && true === $contract['mocked'],
	'Контракт не помечен mocked: true.'
);
velotok_product_photo_assert(
	isset( $contract['spec'] ) && 'clarify-41b1da6d' === $contract['spec'],
	'Контракт ссылается на неверную спеку.'
);
velotok_product_photo_assert(
	isset( $contract['source'] ) && 'stub' === $contract['source'],
	'Источником должна быть заглушка.'
);
velotok_product_photo_assert(
	isset( $contract['attributes']['photo'] ),
	'В контракте отсутствует правило фото.'
);
velotok_product_photo_assert(
	isset( $contract['required_attributes'] ) && in_array( 'photo', $contract['required_attributes'], true ),
	'Фото должно входить в обязательные атрибуты карточки.'
);

$rule = velotok_core_photo_validation_contract();

velotok_product_photo_assert( is_array( $rule ), 'Правило фото должно быть массивом.' );
velotok_product_photo_assert(
	isset( $rule['mocked'] ) && true === $rule['mocked'],
	'Правило фото не помечено mocked: true.'
);
velotok_product_photo_assert(
	isset( $rule['spec'] ) && 'clarify-9e1a1acd' === $rule['spec'],
	'Правило фото ссылается на неверную спеку.'
);
velotok_product_photo_assert(
	isset( $rule['required'] ) && true === $rule['required'],
	'Фото должно быть обязательным.'
);
velotok_product_photo_assert(
	isset( $rule['error_codes']['required'] ) && 'PHOTO_REQUIRED' === $rule['error_codes']['required'],
	'Не задан код PHOTO_REQUIRED.'
);
velotok_product_photo_assert(
	isset( $rule['error_codes']['format'] ) && 'PHOTO_INVALID_FORMAT' === $rule['error_codes']['format'],
	'Не задан код PHOTO_INVALID_FORMAT.'
);

$missing_photos = array(
	'null'                => null,
	'ноль'                => 0,
	'пустая строка'       => '',
	'строка из пробелов'  => "   \t\n",
	'строка ноль'         => '0',
	'false'               => false,
	'пустой массив'       => array(),
	'массив без id'       => array( 'mime' => 'image/jpeg' ),
	'массив с пустым id'  => array( 'id' => 0, 'mime' => 'image/jpeg' ),
);

foreach ( $missing_photos as $label => $photo ) {
	$missing_result = velotok_core_validate_product_photo( $photo );
	velotok_product_photo_assert(
		is_array( $missing_result ) && false === $missing_result['valid'],
		sprintf( 'Фото (%s) должно быть невалидным.', $label )
	);
	velotok_product_photo_assert(
		isset( $missing_result['code'] ) && 'PHOTO_REQUIRED' === $missing_result['code'],
		sprintf( 'Фото (%s) должно возвращать PHOTO_REQUIRED.', $label )
	);
	velotok_product_photo_expect_code( $missing_result, 'PHOTO_REQUIRED', sprintf( 'Фото (%s)', $label ) );
}

$valid_photos = array(
	'целый id'                    => array( 123, null ),
	'строковый id'                => array( '123', null ),
	'поддерживаемый jpeg'         => array( 123, 'image/jpeg' ),
	'поддерживаемый png'          => array( 123, 'image/png' ),
	'поддерживаемый gif'          => array( 123, 'image/gif' ),
	'поддерживаемый webp'         => array( 123, 'image/webp' ),
	'поддерживаемый avif'         => array( 123, 'image/avif' ),
	'верхний регистр'             => array( 123, 'IMAGE/JPEG' ),
	'mime с параметрами'          => array( 123, 'image/jpeg; charset=binary' ),
	'массив с id и mime'          => array( array( 'id' => 123, 'mime' => 'image/webp' ), null ),
	'массив с photo_id и mime'    => array( array( 'photo_id' => 123, 'mime_type' => 'image/png' ), null ),
);

foreach ( $valid_photos as $label => $case ) {
	velotok_product_photo_expect_valid(
		velotok_core_validate_product_photo( $case[0], $case[1] ),
		sprintf( 'Фото (%s)', $label )
	);
}

$invalid_mime_photos = array(
	'application/pdf'          => array( 123, 'application/pdf' ),
	'text/plain'               => array( 123, 'text/plain' ),
	'video/mp4'                => array( 123, 'video/mp4' ),
	'image/svg+xml'            => array( 123, 'image/svg+xml' ),
	'пустой mime'              => array( 123, '' ),
	'массив с неверным mime'   => array( array( 'id' => 123, 'mime' => 'application/pdf' ), null ),
);

foreach ( $invalid_mime_photos as $label => $case ) {
	$invalid_result = velotok_core_validate_product_photo( $case[0], $case[1] );

	if ( '' === $case[1] ) {
		// An empty MIME type is treated as "not supplied", so the upload is accepted.
		velotok_product_photo_expect_valid( $invalid_result, sprintf( 'Фото (%s)', $label ) );
		continue;
	}

	velotok_product_photo_assert(
		is_array( $invalid_result ) && false === $invalid_result['valid'],
		sprintf( 'Фото (%s) должно быть отклонено.', $label )
	);
	velotok_product_photo_assert(
		isset( $invalid_result['code'] ) && 'PHOTO_INVALID_FORMAT' === $invalid_result['code'],
		sprintf( 'Фото (%s) должно возвращать PHOTO_INVALID_FORMAT.', $label )
	);
	velotok_product_photo_expect_code( $invalid_result, 'PHOTO_INVALID_FORMAT', sprintf( 'Фото (%s)', $label ) );
}

$supported = velotok_core_supported_image_mime_types();

velotok_product_photo_assert( is_array( $supported ) && array() !== $supported, 'Список поддерживаемых MIME-типов пуст.' );

foreach ( $supported as $mime ) {
	velotok_product_photo_assert(
		velotok_core_is_supported_image_mime( $mime ),
		sprintf( 'MIME-тип «%s» из конфигурации должен поддерживаться.', $mime )
	);
	velotok_product_photo_expect_valid(
		velotok_core_validate_product_photo( 123, $mime ),
		sprintf( 'MIME-тип из конфигурации «%s»', $mime )
	);
}

$valid_product = velotok_product_photo_valid_product();
$valid_result  = velotok_core_validate_product( $valid_product, 'city' );

velotok_product_photo_assert( true === $valid_result['valid'], 'Карточка с фото должна быть валидной.' );

$missing_photo = $valid_product;
unset( $missing_photo['photo'], $missing_photo['photo_mime'] );

$missing_result = velotok_core_validate_product( $missing_photo, 'city' );

velotok_product_photo_assert( false === $missing_result['valid'], 'Карточка без фото должна быть невалидной.' );
velotok_product_photo_expect_code( $missing_result, 'PHOTO_REQUIRED', 'Отсутствующее фото' );
velotok_product_photo_assert(
	isset( $missing_result['missing_attributes'] ) && in_array( 'photo', $missing_result['missing_attributes'], true ),
	'Фото должно попасть в перечень недостающих атрибутов.'
);
velotok_product_photo_assert(
	isset( $missing_result['errors_by_attribute']['photo'] )
		&& 'PHOTO_REQUIRED' === $missing_result['errors_by_attribute']['photo'],
	'Ошибка фото должна быть привязана к атрибуту photo.'
);

$invalid_photo = $valid_product;
$invalid_photo['photo_mime'] = 'application/pdf';

$invalid_result = velotok_core_validate_product( $invalid_photo, 'city' );

velotok_product_photo_assert( false === $invalid_result['valid'], 'Карточка с неподдерживаемым фото должна быть невалидной.' );
velotok_product_photo_expect_code( $invalid_result, 'PHOTO_INVALID_FORMAT', 'MIME-тип фото вне конфигурации' );

$publish_result = velotok_core_validate_product_publication( $missing_photo, 'city' );

velotok_product_photo_assert( true === $publish_result['blocked'], 'Публикация без фото должна блокироваться.' );
velotok_product_photo_assert( false === $publish_result['can_publish'], 'Карточку без фото нельзя публиковать.' );
velotok_product_photo_assert(
	isset( $publish_result['missing_attributes'] ) && in_array( 'photo', $publish_result['missing_attributes'], true ),
	'Публикация должна сообщать об отсутствующем фото.'
);

$category_attributes = velotok_core_get_required_attributes( 'mountain' );

velotok_product_photo_assert(
	is_array( $category_attributes ) && in_array( 'photo', $category_attributes, true ),
	'Перечень обязательных атрибутов категории должен содержать фото.'
);
velotok_product_photo_assert(
	isset( $valid_result['required_attributes'] ) && in_array( 'photo', $valid_result['required_attributes'], true ),
	'Валидатор должен проверять фото из перечня атрибутов категории.'
);
velotok_product_photo_assert(
	isset( $valid_result['attributes']['photo'] ) && is_array( $valid_result['attributes']['photo'] ),
	'Валидатор должен возвращать результат проверки фото.'
);

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт валидации фото товара на месте.' . PHP_EOL;
