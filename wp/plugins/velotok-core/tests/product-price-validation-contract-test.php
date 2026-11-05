<?php
/**
 * Contract test for the mocked product price validation rule.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/product-price-validation-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/product-price-validation-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer enforces the price validation codes. It is the
 * red-green counterpart of the price rule in includes/product-validation.php.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$velotok_price_stub = dirname( __DIR__ ) . '/includes/product-validation.php';

if ( ! file_exists( $velotok_price_stub ) ) {
	fwrite( STDERR, 'FAIL: заглушка контракта валидации цены отсутствует.' . PHP_EOL );
	exit( 1 );
}

require_once $velotok_price_stub;

$failures = array();

/**
 * Assert a price contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_product_price_assert( $condition, $message ) {
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
function velotok_product_price_expect_code( $result, $code, $label ) {
	velotok_product_price_assert(
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
function velotok_product_price_expect_valid( $result, $label ) {
	velotok_product_price_assert(
		is_array( $result ) && isset( $result['valid'] ) && true === $result['valid'],
		sprintf( '%s: цена должна быть валидной.', $label )
	);
	velotok_product_price_assert(
		is_array( $result ) && isset( $result['errors'] ) && array() === $result['errors'],
		sprintf( '%s: у валидной цены не должно быть ошибок.', $label )
	);
}

$contract = velotok_core_product_validation_contract();

velotok_product_price_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
velotok_product_price_assert(
	isset( $contract['mocked'] ) && true === $contract['mocked'],
	'Контракт не помечен mocked: true.'
);
velotok_product_price_assert(
	isset( $contract['spec'] ) && 'clarify-9e1a1acd' === $contract['spec'],
	'Контракт ссылается на неверную спеку.'
);
velotok_product_price_assert(
	isset( $contract['source'] ) && 'stub' === $contract['source'],
	'Источником должна быть заглушка.'
);
velotok_product_price_assert(
	isset( $contract['attributes']['price'] ),
	'В контракте отсутствует правило цены.'
);
velotok_product_price_assert(
	isset( $contract['required_attributes'] ) && in_array( 'price', $contract['required_attributes'], true ),
	'Цена должна входить в обязательные атрибуты карточки.'
);

$rule = velotok_core_price_validation_contract();

velotok_product_price_assert( is_array( $rule ), 'Правило цены должно быть массивом.' );
velotok_product_price_assert(
	isset( $rule['mocked'] ) && true === $rule['mocked'],
	'Правило цены не помечено mocked: true.'
);
velotok_product_price_assert(
	isset( $rule['required'] ) && true === $rule['required'],
	'Цена должна быть обязательной.'
);
velotok_product_price_assert(
	isset( $rule['allow_zero'] ) && true === $rule['allow_zero'],
	'Нулевая цена должна быть разрешена.'
);
velotok_product_price_assert(
	isset( $rule['allow_negative'] ) && false === $rule['allow_negative'],
	'Отрицательная цена должна быть запрещена.'
);
velotok_product_price_assert(
	isset( $rule['error_codes']['required'] ) && 'PRICE_REQUIRED' === $rule['error_codes']['required'],
	'Не задан код PRICE_REQUIRED.'
);
velotok_product_price_assert(
	isset( $rule['error_codes']['format'] ) && 'PRICE_INVALID_FORMAT' === $rule['error_codes']['format'],
	'Не задан код PRICE_INVALID_FORMAT.'
);
velotok_product_price_assert(
	isset( $rule['error_codes']['negative'] ) && 'PRICE_NEGATIVE' === $rule['error_codes']['negative'],
	'Не задан код PRICE_NEGATIVE.'
);

$missing_prices = array(
	'null'               => null,
	'пустая строка'      => '',
	'строка из пробелов' => "   \t\n",
);

foreach ( $missing_prices as $label => $price ) {
	$missing_result = velotok_core_validate_product_price( $price );
	velotok_product_price_assert(
		is_array( $missing_result ) && false === $missing_result['valid'],
		sprintf( 'Цена (%s) должна быть невалидной.', $label )
	);
	velotok_product_price_assert(
		isset( $missing_result['code'] ) && 'PRICE_REQUIRED' === $missing_result['code'],
		sprintf( 'Цена (%s) должна возвращать PRICE_REQUIRED.', $label )
	);
	velotok_product_price_expect_code( $missing_result, 'PRICE_REQUIRED', sprintf( 'Цена (%s)', $label ) );
}

$invalid_prices = array(
	'нечисловая строка' => 'дорого',
	'строка с буквами'  => '12abc',
	'булево значение'   => true,
	'массив'            => array( 100 ),
);

foreach ( $invalid_prices as $label => $price ) {
	$invalid_result = velotok_core_validate_product_price( $price );
	velotok_product_price_assert(
		is_array( $invalid_result ) && false === $invalid_result['valid'],
		sprintf( 'Цена (%s) должна быть невалидной.', $label )
	);
	velotok_product_price_assert(
		isset( $invalid_result['code'] ) && 'PRICE_INVALID_FORMAT' === $invalid_result['code'],
		sprintf( 'Цена (%s) должна возвращать PRICE_INVALID_FORMAT.', $label )
	);
	velotok_product_price_expect_code( $invalid_result, 'PRICE_INVALID_FORMAT', sprintf( 'Цена (%s)', $label ) );
}

$negative_prices = array(
	'целое'          => -1,
	'дробное'        => -0.5,
	'строка'         => '-100',
	'строка с запятой' => '-100,50',
);

foreach ( $negative_prices as $label => $price ) {
	$negative_result = velotok_core_validate_product_price( $price );
	velotok_product_price_assert(
		is_array( $negative_result ) && false === $negative_result['valid'],
		sprintf( 'Отрицательная цена (%s) должна быть невалидной.', $label )
	);
	velotok_product_price_assert(
		isset( $negative_result['code'] ) && 'PRICE_NEGATIVE' === $negative_result['code'],
		sprintf( 'Отрицательная цена (%s) должна возвращать PRICE_NEGATIVE.', $label )
	);
	velotok_product_price_expect_code( $negative_result, 'PRICE_NEGATIVE', sprintf( 'Отрицательная цена (%s)', $label ) );
}

$zero_prices = array(
	'целый ноль'         => 0,
	'дробный ноль'       => 0.0,
	'строка ноль'        => '0',
	'строка ноль запятая' => '0,00',
);

foreach ( $zero_prices as $label => $price ) {
	velotok_product_price_expect_valid( velotok_core_validate_product_price( $price ), sprintf( 'Нулевая цена (%s)', $label ) );
}

$positive_prices = array(
	'целое'            => 62900,
	'дробное'          => 62900.5,
	'строка'           => '62900',
	'строка с запятой' => '62900,50',
);

foreach ( $positive_prices as $label => $price ) {
	velotok_product_price_expect_valid( velotok_core_validate_product_price( $price ), sprintf( 'Положительная цена (%s)', $label ) );
}

$valid_product = array(
	'photo'       => 123,
	'photo_mime'  => 'image/jpeg',
	'title'       => 'Городской велосипед «Ривьера»',
	'description' => 'Лёгкая рама для ежедневных поездок по городу.',
	'price'       => 62900,
	'category'    => 'city',
);

$valid_product_result = velotok_core_validate_product( $valid_product, 'city' );

velotok_product_price_assert(
	isset( $valid_product_result['valid'] ) && true === $valid_product_result['valid'],
	'Карточка с корректной ценой должна быть валидной.'
);

$missing_price = $valid_product;
unset( $missing_price['price'] );

$missing_result = velotok_core_validate_product( $missing_price, 'city' );

velotok_product_price_assert(
	isset( $missing_result['valid'] ) && false === $missing_result['valid'],
	'Карточка без цены должна быть невалидной.'
);
velotok_product_price_expect_code( $missing_result, 'PRICE_REQUIRED', 'Отсутствующая цена' );
velotok_product_price_assert(
	isset( $missing_result['missing_attributes'] ) && in_array( 'price', $missing_result['missing_attributes'], true ),
	'Цена должна попасть в перечень недостающих атрибутов.'
);
velotok_product_price_assert(
	isset( $missing_result['errors_by_attribute']['price'] )
		&& 'PRICE_REQUIRED' === $missing_result['errors_by_attribute']['price'],
	'Ошибка цены должна быть привязана к атрибуту price.'
);

$publish_result = velotok_core_validate_product_publication( $missing_price, 'city' );

velotok_product_price_assert(
	isset( $publish_result['blocked'] ) && true === $publish_result['blocked'],
	'Публикация без цены должна блокироваться.'
);
velotok_product_price_assert(
	isset( $publish_result['can_publish'] ) && false === $publish_result['can_publish'],
	'Карточку без цены нельзя публиковать.'
);
velotok_product_price_assert(
	isset( $publish_result['missing_attributes'] ) && in_array( 'price', $publish_result['missing_attributes'], true ),
	'Публикация должна сообщать об отсутствующей цене.'
);

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт валидации цены товара на месте.' . PHP_EOL;
