<?php
/**
 * Contract test for the mocked product description validation rule.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/product-description-validation-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/product-description-validation-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer enforces DESCRIPTION_REQUIRED. It is the red-green
 * counterpart of the description rule in includes/product-validation.php.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$velotok_description_stub = dirname( __DIR__ ) . '/includes/product-validation.php';

if ( ! file_exists( $velotok_description_stub ) ) {
	fwrite( STDERR, 'FAIL: заглушка контракта валидации описания отсутствует.' . PHP_EOL );
	exit( 1 );
}

require_once $velotok_description_stub;

$failures = array();

/**
 * Assert a description contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_product_description_assert( $condition, $message ) {
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
function velotok_product_description_expect_code( $result, $code, $label ) {
	velotok_product_description_assert(
		is_array( $result ) && isset( $result['errors'] ) && in_array( $code, $result['errors'], true ),
		sprintf( '%s: ожидался код %s.', $label, $code )
	);
}

$contract = velotok_core_product_validation_contract();

velotok_product_description_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
velotok_product_description_assert(
	isset( $contract['mocked'] ) && true === $contract['mocked'],
	'Контракт не помечен mocked: true.'
);
velotok_product_description_assert(
	isset( $contract['spec'] ) && 'clarify-41b1da6d' === $contract['spec'],
	'Контракт ссылается на неверную спеку.'
);
velotok_product_description_assert(
	isset( $contract['source'] ) && 'stub' === $contract['source'],
	'Источником должна быть заглушка.'
);
velotok_product_description_assert(
	isset( $contract['attributes']['description'] ),
	'В контракте отсутствует правило описания.'
);

$rule = velotok_core_description_validation_contract();

velotok_product_description_assert( is_array( $rule ), 'Правило описания должно быть массивом.' );
velotok_product_description_assert(
	isset( $rule['mocked'] ) && true === $rule['mocked'],
	'Правило описания не помечено mocked: true.'
);
velotok_product_description_assert(
	isset( $rule['required'] ) && true === $rule['required'],
	'Описание должно быть обязательным.'
);
velotok_product_description_assert(
	isset( $rule['error_codes']['required'] ) && 'DESCRIPTION_REQUIRED' === $rule['error_codes']['required'],
	'Не задан код DESCRIPTION_REQUIRED.'
);

$blank_descriptions = array(
	'пустая строка'      => '',
	'строка из пробелов' => "   \t\n",
	'null'               => null,
	'нескалярный массив' => array( 'текст' ),
);

foreach ( $blank_descriptions as $label => $description ) {
	$blank_result = velotok_core_validate_product_description( $description );
	velotok_product_description_assert(
		is_array( $blank_result ) && false === $blank_result['valid'],
		sprintf( 'Описание (%s) должно быть невалидным.', $label )
	);
	velotok_product_description_assert(
		isset( $blank_result['code'] ) && 'DESCRIPTION_REQUIRED' === $blank_result['code'],
		sprintf( 'Описание (%s) должно возвращать DESCRIPTION_REQUIRED.', $label )
	);
	velotok_product_description_expect_code( $blank_result, 'DESCRIPTION_REQUIRED', sprintf( 'Описание (%s)', $label ) );
}

$valid_description = 'Лёгкая рама и запас хода до 70 км для ежедневных поездок.';
$valid_result      = velotok_core_validate_product_description( $valid_description );

velotok_product_description_assert(
	is_array( $valid_result ) && true === $valid_result['valid'],
	'Непустое описание должно быть валидным.'
);
velotok_product_description_assert(
	isset( $valid_result['errors'] ) && array() === $valid_result['errors'],
	'У валидного описания не должно быть ошибок.'
);

$valid_product = array(
	'photo'       => 123,
	'photo_mime'  => 'image/jpeg',
	'title'       => 'Городской велосипед «Ривьера»',
	'description' => $valid_description,
	'price'       => 62900,
	'category'    => 'city',
);

$valid_product_result = velotok_core_validate_product( $valid_product, 'city' );

velotok_product_description_assert(
	isset( $valid_product_result['valid'] ) && true === $valid_product_result['valid'],
	'Карточка с корректным описанием должна быть валидной.'
);

$missing_description = $valid_product;
unset( $missing_description['description'] );

$missing_result = velotok_core_validate_product( $missing_description, 'city' );

velotok_product_description_assert(
	isset( $missing_result['valid'] ) && false === $missing_result['valid'],
	'Карточка без описания должна быть невалидной.'
);
velotok_product_description_expect_code( $missing_result, 'DESCRIPTION_REQUIRED', 'Отсутствующее описание' );
velotok_product_description_assert(
	isset( $missing_result['missing_attributes'] ) && in_array( 'description', $missing_result['missing_attributes'], true ),
	'Описание должно попасть в перечень недостающих атрибутов.'
);
velotok_product_description_assert(
	isset( $missing_result['errors_by_attribute']['description'] )
		&& 'DESCRIPTION_REQUIRED' === $missing_result['errors_by_attribute']['description'],
	'Ошибка описания должна быть привязана к атрибуту description.'
);

$publish_result = velotok_core_validate_product_publication( $missing_description, 'city' );

velotok_product_description_assert(
	isset( $publish_result['blocked'] ) && true === $publish_result['blocked'],
	'Публикация без описания должна блокироваться.'
);
velotok_product_description_assert(
	isset( $publish_result['can_publish'] ) && false === $publish_result['can_publish'],
	'Карточку без описания нельзя публиковать.'
);
velotok_product_description_assert(
	isset( $publish_result['missing_attributes'] ) && in_array( 'description', $publish_result['missing_attributes'], true ),
	'Публикация должна сообщать об отсутствующем описании.'
);

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт валидации описания товара на месте.' . PHP_EOL;
