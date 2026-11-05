<?php
/**
 * Contract test for the mocked required product card attributes stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/product-required-attributes-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/product-required-attributes-contract-test.php
 *
 * Photo, title, description and price are the mandatory product card
 * attributes. The test intentionally fails when the stub file is removed, is
 * no longer marked as mocked or stops enforcing any of the required fields.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$velotok_required_attributes_stub = dirname( __DIR__ ) . '/includes/product-validation.php';

if ( ! file_exists( $velotok_required_attributes_stub ) ) {
	fwrite( STDERR, 'FAIL: заглушка обязательных атрибутов карточки товара отсутствует.' . PHP_EOL );
	exit( 1 );
}

require_once $velotok_required_attributes_stub;

if ( ! function_exists( 'velotok_core_product_validation_contract' ) ) {
	fwrite( STDERR, 'FAIL: заглушка не объявляет контракт обязательных атрибутов.' . PHP_EOL );
	exit( 1 );
}

$failures = array();

/**
 * Assert a required-attributes contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_required_attributes_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Assert that a validation result contains the expected error code.
 *
 * @param array<string,mixed> $result Validation result.
 * @param string              $code   Expected error code.
 * @param string              $label  Human-readable check label.
 * @return void
 */
function velotok_required_attributes_expect_code( $result, $code, $label ) {
	velotok_required_attributes_assert(
		is_array( $result ) && isset( $result['errors'] ) && in_array( $code, $result['errors'], true ),
		sprintf( '%s: ожидался код %s.', $label, $code )
	);
}

/**
 * Build a product with all four required attributes.
 *
 * @return array<string,mixed>
 */
function velotok_required_attributes_valid_product() {
	return array(
		'photo'       => 123,
		'photo_mime'  => 'image/jpeg',
		'title'       => 'Городской велосипед «Ривьера»',
		'description' => 'Лёгкая рама и запас хода до 70 км для ежедневных поездок.',
		'price'       => 62900,
		'category'    => 'city',
	);
}

$contract = velotok_core_product_validation_contract();
$expected = array( 'photo', 'title', 'description', 'price' );

velotok_required_attributes_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
velotok_required_attributes_assert(
	isset( $contract['spec'] ) && 'clarify-41b1da6d' === $contract['spec'],
	'Контракт ссылается на неверную спеку.'
);
velotok_required_attributes_assert(
	isset( $contract['mocked'] ) && true === $contract['mocked'],
	'Контракт не помечен mocked: true.'
);
velotok_required_attributes_assert(
	isset( $contract['source'] ) && 'stub' === $contract['source'],
	'Источником контракта должна быть заглушка.'
);
velotok_required_attributes_assert(
	isset( $contract['required_attributes'] ) && $expected === $contract['required_attributes'],
	'Обязательные атрибуты должны быть photo, title, description, price.'
);

$category_contract = velotok_core_required_attributes_contract();

velotok_required_attributes_assert( is_array( $category_contract ), 'Контракт категории должен быть массивом.' );
velotok_required_attributes_assert(
	isset( $category_contract['mocked'] ) && true === $category_contract['mocked'],
	'Контракт обязательных атрибутов категории не помечен mocked: true.'
);
velotok_required_attributes_assert(
	isset( $category_contract['source'] ) && 'stub' === $category_contract['source'],
	'Источником обязательных атрибутов категории должна быть заглушка.'
);

foreach ( array( 'city', 'mountain', 'cargo', 'folding', 'unknown-category' ) as $category ) {
	velotok_required_attributes_assert(
		$expected === velotok_core_get_required_attributes( $category ),
		sprintf( 'getRequiredAttributes(«%s») должен возвращать фиксированный перечень.', $category )
	);
	velotok_required_attributes_assert(
		$expected === getRequiredAttributes( $category ),
		sprintf( 'getRequiredAttributes(«%s») должен быть доступен как глобальный алиас.', $category )
	);
}

foreach ( $expected as $attribute ) {
	$rule = velotok_core_product_attribute_rule( $attribute );

	velotok_required_attributes_assert( is_array( $rule ), sprintf( 'Не описана заглушка атрибута «%s».', $attribute ) );
	velotok_required_attributes_assert(
		isset( $rule['mocked'] ) && true === $rule['mocked'],
		sprintf( 'Заглушка атрибута «%s» не помечена mocked: true.', $attribute )
	);
	velotok_required_attributes_assert(
		isset( $rule['spec'] ) && 'clarify-9e1a1acd' === $rule['spec'],
		sprintf( 'Заглушка атрибута «%s» ссылается на неверную спеку.', $attribute )
	);
}

$valid_product = velotok_required_attributes_valid_product();
$valid_result  = velotok_core_validate_product( $valid_product, 'city' );

velotok_required_attributes_assert( true === $valid_result['valid'], 'Карточка со всеми четырьмя атрибутами должна быть валидной.' );
velotok_required_attributes_assert( array() === $valid_result['errors'], 'У валидной карточки не должно быть ошибок.' );

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

	velotok_required_attributes_assert( false === $missing_result['valid'], sprintf( 'Карточка без «%s» должна быть невалидной.', $attribute ) );
	velotok_required_attributes_expect_code( $missing_result, $code, sprintf( 'Отсутствует атрибут «%s»', $attribute ) );
	velotok_required_attributes_assert(
		in_array( $attribute, $missing_result['missing_attributes'], true ),
		sprintf( 'Атрибут «%s» должен попасть в missing_attributes.', $attribute )
	);
	velotok_required_attributes_assert(
		isset( $missing_result['errors_by_attribute'][ $attribute ] )
			&& $code === $missing_result['errors_by_attribute'][ $attribute ],
		sprintf( 'Код ошибки «%s» должен быть привязан к атрибуту.', $attribute )
	);
}

$empty_result = velotok_core_validate_product( array(), 'city' );

velotok_required_attributes_assert( false === $empty_result['valid'], 'Пустая карточка должна быть невалидной.' );
velotok_required_attributes_assert(
	$expected === $empty_result['missing_attributes'],
	'Пустая карточка должна вернуть все четыре недостающих атрибута.'
);
velotok_required_attributes_assert(
	array( 'PHOTO_REQUIRED', 'TITLE_REQUIRED', 'DESCRIPTION_REQUIRED', 'PRICE_REQUIRED' ) === $empty_result['errors'],
	'Пустая карточка должна вернуть все коды обязательных атрибутов.'
);

$publish_product = $valid_product;
unset( $publish_product['photo'], $publish_product['photo_mime'], $publish_product['title'] );
$publish_result = velotok_core_validate_product_publication( $publish_product, 'mountain' );

velotok_required_attributes_assert( false === $publish_result['can_publish'], 'Публикация без обязательных атрибутов должна блокироваться.' );
velotok_required_attributes_assert( true === $publish_result['blocked'], 'Результат публикации должен быть помечен blocked.' );
velotok_required_attributes_assert(
	array( 'photo', 'title' ) === $publish_result['missing_attributes'],
	'Публикация должна возвращать перечень недостающих атрибутов.'
);
velotok_required_attributes_assert(
	false === velotok_core_can_publish_product( $publish_product, 'mountain' ),
	'velotok_core_can_publish_product должен запрещать публикацию.'
);

velotok_required_attributes_assert(
	$valid_result['mocked'] === true,
	'Результат валидации должен сохранять mocked-маркер контракта.'
);

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт обязательных атрибутов карточки товара на месте.' . PHP_EOL;
