<?php
/**
 * Contract test for the mocked server-side product card save service.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/product-card-required-attributes-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/product-card-required-attributes-contract-test.php
 *
 * The product card must contain the mandatory photo, title, description and
 * price attributes. Saving is validated by the product card service on the
 * server, not by client-side checks. The test intentionally fails when the
 * stub is removed or stops enforcing any of the required attributes.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$velotok_product_card_stub = dirname( __DIR__ ) . '/includes/product-validation.php';

if ( ! file_exists( $velotok_product_card_stub ) ) {
	fwrite( STDERR, 'FAIL: заглушка сервиса карточки товара отсутствует.' . PHP_EOL );
	exit( 1 );
}

require_once $velotok_product_card_stub;

$failures = array();

/**
 * Assert a product card service condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_product_card_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Build a product card with all required attributes.
 *
 * @return array<string,mixed>
 */
function velotok_product_card_valid_product() {
	return array(
		'photo'       => 123,
		'photo_mime'  => 'image/jpeg',
		'title'       => 'Городской велосипед «Ривьера»',
		'description' => 'Лёгкая рама и запас хода до 70 км для ежедневных поездок.',
		'price'       => 62900,
		'category'    => 'city',
	);
}

$service = velotok_core_product_card_service_contract();

velotok_product_card_assert( is_array( $service ), 'Контракт сервиса должен быть массивом.' );
velotok_product_card_assert(
	isset( $service['mocked'] ) && true === $service['mocked'],
	'Сервис карточки должен быть помечен mocked: true.'
);
velotok_product_card_assert(
	isset( $service['source'] ) && 'stub' === $service['source'],
	'Источником сервиса должна быть заглушка.'
);
velotok_product_card_assert(
	isset( $service['validate_on_save'] ) && true === $service['validate_on_save'],
	'Сервис должен валидировать карточку при сохранении.'
);
velotok_product_card_assert(
	isset( $service['server_side'] ) && true === $service['server_side'],
	'Валидация должна выполняться на стороне сервиса.'
);
velotok_product_card_assert(
	isset( $service['client_validation_only'] ) && false === $service['client_validation_only'],
	'Валидация не должна опираться только на клиентскую проверку.'
);
velotok_product_card_assert(
	isset( $service['required_attributes'] )
		&& array( 'photo', 'title', 'description', 'price' ) === $service['required_attributes'],
	'Обязательные атрибуты карточки заданы неверно.'
);

$valid_product = velotok_product_card_valid_product();
$valid_result  = velotok_core_save_product_card( $valid_product, 'city' );

velotok_product_card_assert(
	isset( $valid_result['saved'] ) && true === $valid_result['saved'],
	'Карточка со всеми обязательными атрибутами должна сохраняться.'
);
velotok_product_card_assert(
	isset( $valid_result['blocked'] ) && false === $valid_result['blocked'],
	'Валидная карточка не должна блокироваться.'
);
velotok_product_card_assert(
	isset( $valid_result['errors'] ) && array() === $valid_result['errors'],
	'У валидной карточки не должно быть ошибок.'
);
velotok_product_card_assert(
	isset( $valid_result['missing_attributes'] ) && array() === $valid_result['missing_attributes'],
	'У валидной карточки не должно быть недостающих атрибутов.'
);
velotok_product_card_assert(
	isset( $valid_result['invalid_attributes'] ) && array() === $valid_result['invalid_attributes'],
	'У валидной карточки не должно быть невалидных атрибутов.'
);
velotok_product_card_assert(
	saveProductCard( $valid_product, 'city' ) === velotok_core_save_product_card( $valid_product, 'city' ),
	'Глобальный алиас saveProductCard должен возвращать тот же результат.'
);

$missing_codes = array(
	'photo'       => 'PHOTO_REQUIRED',
	'title'       => 'TITLE_REQUIRED',
	'description' => 'DESCRIPTION_REQUIRED',
	'price'       => 'PRICE_REQUIRED',
);

foreach ( $missing_codes as $attribute => $code ) {
	$missing_product = $valid_product;
	unset( $missing_product[ $attribute ] );

	if ( 'photo' === $attribute ) {
		unset( $missing_product['photo_mime'] );
	}

	$missing_result = velotok_core_save_product_card( $missing_product, 'city' );

	velotok_product_card_assert(
		isset( $missing_result['saved'] ) && false === $missing_result['saved'],
		sprintf( 'Карточка без «%s» не должна сохраняться.', $attribute )
	);
	velotok_product_card_assert(
		isset( $missing_result['blocked'] ) && true === $missing_result['blocked'],
		sprintf( 'Сохранение без «%s» должно блокироваться.', $attribute )
	);
	velotok_product_card_assert(
		isset( $missing_result['missing_attributes'] )
			&& in_array( $attribute, $missing_result['missing_attributes'], true ),
		sprintf( 'Отсутствующий атрибут «%s» должен попасть в перечень.', $attribute )
	);
	velotok_product_card_assert(
		isset( $missing_result['errors'] ) && in_array( $code, $missing_result['errors'], true ),
		sprintf( 'Для отсутствующего «%s» ожидался код %s.', $attribute, $code )
	);
	velotok_product_card_assert(
		isset( $missing_result['errors_by_attribute'][ $attribute ] )
			&& $code === $missing_result['errors_by_attribute'][ $attribute ],
		sprintf( 'Код %s должен быть привязан к атрибуту «%s».', $code, $attribute )
	);
}

$invalid_cases = array(
	'title' => array(
		'value' => str_repeat( 'a', 201 ),
		'code'  => 'TITLE_TOO_LONG',
	),
	'price' => array(
		'value' => -10,
		'code'  => 'PRICE_NEGATIVE',
	),
);

foreach ( $invalid_cases as $attribute => $case ) {
	$invalid_product               = $valid_product;
	$invalid_product[ $attribute ] = $case['value'];
	$invalid_result                = velotok_core_save_product_card( $invalid_product, 'city' );

	velotok_product_card_assert(
		isset( $invalid_result['saved'] ) && false === $invalid_result['saved'],
		sprintf( 'Карточка с невалидным «%s» не должна сохраняться.', $attribute )
	);
	velotok_product_card_assert(
		isset( $invalid_result['blocked'] ) && true === $invalid_result['blocked'],
		sprintf( 'Сохранение с невалидным «%s» должно блокироваться.', $attribute )
	);
	velotok_product_card_assert(
		isset( $invalid_result['errors'] ) && in_array( $case['code'], $invalid_result['errors'], true ),
		sprintf( 'Для невалидного «%s» ожидался код %s.', $attribute, $case['code'] )
	);
	velotok_product_card_assert(
		isset( $invalid_result['errors_by_attribute'][ $attribute ] )
			&& $case['code'] === $invalid_result['errors_by_attribute'][ $attribute ],
		sprintf( 'Ошибка невалидного «%s» должна быть привязана к атрибуту.', $attribute )
	);
	velotok_product_card_assert(
		isset( $invalid_result['invalid_attributes'] )
			&& in_array( $attribute, $invalid_result['invalid_attributes'], true ),
		sprintf( 'Невалидный атрибут «%s» должен попасть в invalid_attributes.', $attribute )
	);
}

$invalid_photo            = $valid_product;
$invalid_photo['photo_mime'] = 'application/pdf';
$invalid_photo_result     = velotok_core_save_product_card( $invalid_photo, 'city' );

velotok_product_card_assert( false === $invalid_photo_result['saved'], 'Карточка с невалидным фото не должна сохраняться.' );
velotok_product_card_assert(
	in_array( 'PHOTO_INVALID_FORMAT', $invalid_photo_result['errors'], true ),
	'Невалидное фото должно возвращать PHOTO_INVALID_FORMAT.'
);
velotok_product_card_assert(
	in_array( 'photo', $invalid_photo_result['invalid_attributes'], true ),
	'Невалидное фото должно попасть в invalid_attributes.'
);

$empty_result = velotok_core_save_product_card( array(), 'city' );

velotok_product_card_assert( false === $empty_result['saved'], 'Пустая карточка не должна сохраняться.' );
velotok_product_card_assert(
	array( 'photo', 'title', 'description', 'price' ) === $empty_result['missing_attributes'],
	'Пустая карточка должна вернуть все четыре недостающих атрибута.'
);

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-сервис карточки товара проверяет обязательные атрибуты при сохранении.' . PHP_EOL;
