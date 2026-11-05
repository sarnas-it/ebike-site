<?php
/**
 * Contract test for the mocked product card validation stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/product-validation-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/product-validation-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer exposes the required validation rules.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/product-validation.php';

$failures = array();

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_product_validation_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Build a valid product.
 *
 * @return array<string,mixed>
 */
function velotok_product_validation_valid_product() {
	return array(
		'photo'       => 123,
		'photo_mime'  => 'image/jpeg',
		'title'       => 'Городской велосипед «Ривьера»',
		'description' => 'Лёгкая низкая рама для ежедневных поездок по городу.',
		'price'       => 62900,
		'category'    => 'city',
	);
}

/**
 * Assert that a field error contains the expected code.
 *
 * @param array<string,mixed> $result Validation result.
 * @param string              $code   Expected code.
 * @param string              $label  Human-readable check label.
 * @return void
 */
function velotok_product_validation_expect_code( $result, $code, $label ) {
	velotok_product_validation_assert(
		is_array( $result ) && isset( $result['errors'] ) && in_array( $code, $result['errors'], true ),
		sprintf( '%s: ожидался код %s.', $label, $code )
	);
}

$contract = velotok_core_product_validation_contract();

velotok_product_validation_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
velotok_product_validation_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
velotok_product_validation_assert( isset( $contract['spec'] ) && 'clarify-41b1da6d' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
velotok_product_validation_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );

$expected_required = array( 'photo', 'title', 'description', 'price' );
velotok_product_validation_assert(
	isset( $contract['required_attributes'] ) && $expected_required === $contract['required_attributes'],
	'Обязательные атрибуты карточки заданы неверно.'
);

foreach ( $expected_required as $attribute ) {
	velotok_product_validation_assert( isset( $contract['attributes'][ $attribute ] ), sprintf( 'Не описано правило атрибута «%s».', $attribute ) );
}

velotok_product_validation_assert(
	velotok_core_get_required_attributes( 'city' ) === $expected_required,
	'getRequiredAttributes должен возвращать фиксированный перечень для категории.'
);
velotok_product_validation_assert(
	velotok_core_get_required_attributes( 'unknown-category' ) === $expected_required,
	'Неизвестная категория должна получать фиксированный перечень по умолчанию.'
);

$title_empty = velotok_core_validate_product_title( '' );
velotok_product_validation_expect_code( $title_empty, 'TITLE_REQUIRED', 'Пустое название' );

$title_spaces = velotok_core_validate_product_title( "   \t\n" );
velotok_product_validation_expect_code( $title_spaces, 'TITLE_REQUIRED', 'Название из пробелов' );

$title_limit = (int) $contract['limits']['title_max_length'];
$title_in_limit = velotok_core_validate_product_title( str_repeat( 'a', $title_limit ) );
velotok_product_validation_assert( true === $title_in_limit['valid'], 'Название на предельной длине должно быть валидным.' );

$title_too_long = velotok_core_validate_product_title( str_repeat( 'a', $title_limit + 1 ) );
velotok_product_validation_expect_code( $title_too_long, 'TITLE_TOO_LONG', 'Название за предельной длиной' );

$description_empty = velotok_core_validate_product_description( '' );
velotok_product_validation_expect_code( $description_empty, 'DESCRIPTION_REQUIRED', 'Пустое описание' );

$description_spaces = velotok_core_validate_product_description( "  \t " );
velotok_product_validation_expect_code( $description_spaces, 'DESCRIPTION_REQUIRED', 'Описание из пробелов' );

$price_invalid = velotok_core_validate_product_price( 'дорого' );
velotok_product_validation_expect_code( $price_invalid, 'PRICE_INVALID_FORMAT', 'Нечисловая цена' );

$price_negative = velotok_core_validate_product_price( -1 );
velotok_product_validation_expect_code( $price_negative, 'PRICE_NEGATIVE', 'Отрицательная цена' );

$price_zero = velotok_core_validate_product_price( 0 );
velotok_product_validation_assert( true === $price_zero['valid'], 'Нулевая цена должна быть валидной.' );

$price_positive = velotok_core_validate_product_price( 62900.5 );
velotok_product_validation_assert( true === $price_positive['valid'], 'Положительная цена должна быть валидной.' );

$photo_missing = velotok_core_validate_product_photo( 0 );
velotok_product_validation_expect_code( $photo_missing, 'PHOTO_REQUIRED', 'Отсутствие идентификатора фото' );

$photo_bad_mime = velotok_core_validate_product_photo( 123, 'application/pdf' );
velotok_product_validation_expect_code( $photo_bad_mime, 'PHOTO_INVALID_FORMAT', 'MIME-тип вне конфигурации' );

$photo_good_mime = velotok_core_validate_product_photo( 123, 'image/jpeg' );
velotok_product_validation_assert( true === $photo_good_mime['valid'], 'MIME-тип из конфигурации должен быть валидным.' );

$valid_product = velotok_product_validation_valid_product();
$valid_result  = velotok_core_validate_product( $valid_product, 'city' );
velotok_product_validation_assert( true === $valid_result['valid'], 'Карточка со всеми атрибутами должна быть валидной.' );
velotok_product_validation_assert( array() === $valid_result['errors'], 'У валидной карточки не должно быть ошибок.' );

$expected_codes = array(
	'photo'       => 'PHOTO_REQUIRED',
	'title'       => 'TITLE_REQUIRED',
	'description' => 'DESCRIPTION_REQUIRED',
	'price'       => 'PRICE_REQUIRED',
);

foreach ( $expected_codes as $attribute => $code ) {
	$missing_product = $valid_product;
	unset( $missing_product[ $attribute ] );

	if ( 'photo' === $attribute ) {
		unset( $missing_product['photo_mime'] );
	}

	$missing_result = velotok_core_validate_product( $missing_product, 'city' );
	velotok_product_validation_expect_code( $missing_result, $code, sprintf( 'Отсутствует атрибут «%s»', $attribute ) );
	velotok_product_validation_assert(
		in_array( $attribute, $missing_result['missing_attributes'], true ),
		sprintf( 'Атрибут «%s» должен попасть в перечень недостающих.', $attribute )
	);
}

foreach ( velotok_core_get_required_attributes( 'city' ) as $attribute ) {
	$checked = false;

	foreach ( $valid_result['attributes'] as $field => $field_result ) {
		if ( $attribute === $field ) {
			$checked = true;
			break;
		}
	}

	velotok_product_validation_assert( $checked, sprintf( 'Атрибут категории «%s» не проверяется валидатором.', $attribute ) );
}

$publish_missing = $valid_product;
unset( $publish_missing['title'], $publish_missing['description'] );
$publish_result = velotok_core_validate_product_publication( $publish_missing, 'mountain' );
velotok_product_validation_assert( false === $publish_result['can_publish'], 'Публикация без обязательных атрибутов должна блокироваться.' );
velotok_product_validation_assert(
	array( 'title', 'description' ) === $publish_result['missing_attributes'],
	'Публикация должна возвращать перечень недостающих атрибутов.'
);

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт валидации карточки товара на месте.' . PHP_EOL;
