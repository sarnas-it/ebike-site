<?php
/**
 * Behaviour test for TESTEB-17: catalog attributes are validated by contract.
 *
 * Run from the project root:
 *   php wp/plugins/velotok-core/tests/catalog-attributes-contract-test.php
 *
 * The catalog contract declares the required product fields, their types, the
 * fixed state model and the category directory. This test checks that the
 * catalog validates each product against that contract: missing required
 * attributes, wrong types, unknown states, unknown categories and invalid
 * prices are rejected instead of being shown on the storefront.
 *
 * @package Velotok_Core
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, 'This test must be run from the command line.' . PHP_EOL );
	exit( 1 );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/catalog.php';

$failures = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_catalog_attributes_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Build a valid catalog product fixture.
 *
 * @param array<string,mixed> $overrides Field overrides.
 * @return array<string,mixed>
 */
function velotok_catalog_attributes_fixture( $overrides = array() ) {
	return array_merge(
		array(
			'state'             => 'publish',
			'slug'              => 'test-model',
			'name'              => 'Тестовая модель',
			'price'             => 62900,
			'category'          => 'city',
			'short_description' => 'Городская модель для ежедневных поездок.',
			'description'       => 'Описание тестовой модели.',
			'range_km'          => 90,
			'motor_w'           => 250,
			'weight_kg'         => 23,
			'wheel'             => '28″',
			'badge'             => 'Хит',
			'image'             => 'model-city-classic-1-web.jpg',
		),
		$overrides
	);
}

$contract = velotok_core_catalog_contract();

/*
 * 1. The contract declares the validation rules for catalog attributes.
 */
velotok_catalog_attributes_assert( function_exists( 'velotok_core_catalog_validate_product' ), 'Не определена валидация атрибутов товара каталога.' );
velotok_catalog_attributes_assert( function_exists( 'velotok_core_catalog_product_is_valid' ), 'Не определена проверка валидности товара каталога.' );
velotok_catalog_attributes_assert( isset( $contract['validation_requirement'] ) && 'TESTEB-17' === $contract['validation_requirement'], 'Контракт должен ссылаться на TESTEB-17.' );
velotok_catalog_attributes_assert( isset( $contract['validation'] ) && is_array( $contract['validation'] ), 'В контракте нет раздела валидации.' );

$validation = isset( $contract['validation'] ) && is_array( $contract['validation'] ) ? $contract['validation'] : array();

velotok_catalog_attributes_assert( isset( $validation['requirement'] ) && 'TESTEB-17' === $validation['requirement'], 'Раздел валидации ссылается на неверное требование.' );
velotok_catalog_attributes_assert( ! empty( $validation['server_side'] ), 'Валидация должна выполняться на сервере.' );
velotok_catalog_attributes_assert( isset( $validation['required_attributes'] ) && $validation['required_attributes'] === $contract['required_fields'], 'Набор обязательных атрибутов не совпадает с контрактом.' );
velotok_catalog_attributes_assert( isset( $validation['fields'] ) && $validation['fields'] === $contract['fields'], 'Валидация должна использовать типы полей контракта.' );
velotok_catalog_attributes_assert( ! empty( $validation['checks_state_model'] ), 'Валидация должна проверять состояние по модели.' );
velotok_catalog_attributes_assert( ! empty( $validation['checks_category_directory'] ), 'Валидация должна проверять категорию по справочнику.' );

$error_codes = isset( $validation['error_codes'] ) ? $validation['error_codes'] : array();

foreach ( array( 'required', 'type', 'state', 'category', 'price' ) as $code_key ) {
	velotok_catalog_attributes_assert( isset( $error_codes[ $code_key ] ) && '' !== $error_codes[ $code_key ], 'В контракте нет кода ошибки: ' . $code_key );
}

velotok_catalog_attributes_assert( 'string' === velotok_core_catalog_field_type( 'name' ), 'Тип названия должен быть string.' );
velotok_catalog_attributes_assert( 'number' === velotok_core_catalog_field_type( 'price' ), 'Тип цены должен быть number.' );
velotok_catalog_attributes_assert( '' === velotok_core_catalog_field_type( 'unknown_field' ), 'Неизвестное поле не должно иметь тип контракта.' );

/*
 * 2. Every mocked catalog product passes contract validation.
 */
$products = velotok_core_catalog_products( array( 'include_invalid' => true ) );

velotok_catalog_attributes_assert( ! empty( $products ), 'Каталог не содержит товаров для проверки.' );

foreach ( $products as $product ) {
	$result = velotok_core_catalog_validate_product( $product );
	$name   = isset( $product['name'] ) ? (string) $product['name'] : '';

	velotok_catalog_attributes_assert( true === $result['valid'], 'Валидный товар каталога отклонён: ' . $name );
	velotok_catalog_attributes_assert( true === $result['is_valid'], 'Результат валидации не согласован: ' . $name );
	velotok_catalog_attributes_assert( true === velotok_core_catalog_product_is_valid( $product ), 'Проверка валидности отклонила товар: ' . $name );
	velotok_catalog_attributes_assert( empty( $result['errors'] ), 'У валидного товара есть ошибки: ' . $name );
}

/*
 * 3. The public catalog accessor returns only products valid by contract.
 */
$public_products = velotok_core_catalog_products();

velotok_catalog_attributes_assert( count( $public_products ) === count( $products ), 'Все товары контракта должны проходить валидацию.' );

foreach ( $public_products as $product ) {
	velotok_catalog_attributes_assert( true === velotok_core_catalog_product_is_valid( $product ), 'Витрина вернула товар, не прошедший валидацию.' );
}

/*
 * 4. Missing required attributes are rejected with the required error code.
 */
$required_fields = $contract['required_fields'];

foreach ( $required_fields as $field ) {
	$product = velotok_catalog_attributes_fixture();
	unset( $product[ $field ] );

	$result = velotok_core_catalog_validate_product( $product );

	velotok_catalog_attributes_assert( false === $result['valid'], 'Принят товар без обязательного атрибута: ' . $field );
	velotok_catalog_attributes_assert( isset( $result['errors_by_attribute'][ $field ] ) && $error_codes['required'] === $result['errors_by_attribute'][ $field ], 'Неверный код ошибки для отсутствующего атрибута: ' . $field );
	velotok_catalog_attributes_assert( in_array( $field, $result['missing_attributes'], true ), 'Отсутствующий атрибут не попал в missing_attributes: ' . $field );
	velotok_catalog_attributes_assert( false === velotok_core_catalog_product_is_valid( $product ), 'Проверка валидности пропустила товар без атрибута: ' . $field );
}

/*
 * 5. Wrong types are rejected by the declared field types.
 */
$type_cases = array(
	'name'  => array( 'name' => array( 'Массив' ) ),
	'slug'  => array( 'slug' => array( 'slug' ) ),
	'price' => array( 'price' => 'дорого' ),
	'range_km' => array( 'range_km' => 'много' ),
);

foreach ( $type_cases as $field => $overrides ) {
	$result = velotok_core_catalog_validate_product( velotok_catalog_attributes_fixture( $overrides ) );

	velotok_catalog_attributes_assert( false === $result['valid'], 'Товар с неверным типом атрибута принят: ' . $field );
	velotok_catalog_attributes_assert( isset( $result['errors_by_attribute'][ $field ] ) && $error_codes['type'] === $result['errors_by_attribute'][ $field ], 'Неверный код ошибки типа для атрибута: ' . $field );
	velotok_catalog_attributes_assert( in_array( $field, $result['invalid_attributes'], true ), 'Атрибут с неверным типом не попал в invalid_attributes: ' . $field );
}

/*
 * 6. State, category and price are validated by their contract rules.
 */
$result = velotok_core_catalog_validate_product( velotok_catalog_attributes_fixture( array( 'state' => 'archived' ) ) );
velotok_catalog_attributes_assert( false === $result['valid'], 'Товар с состоянием вне модели принят.' );
velotok_catalog_attributes_assert( isset( $result['errors_by_attribute']['state'] ) && $error_codes['state'] === $result['errors_by_attribute']['state'], 'Для состояния вне модели нужен код INVALID_STATE.' );

$result = velotok_core_catalog_validate_product( velotok_catalog_attributes_fixture( array( 'category' => 'submarine' ) ) );
velotok_catalog_attributes_assert( false === $result['valid'], 'Товар с неизвестной категорией принят.' );
velotok_catalog_attributes_assert( isset( $result['errors_by_attribute']['category'] ) && $error_codes['category'] === $result['errors_by_attribute']['category'], 'Для неизвестной категории нужен код UNKNOWN_CATEGORY.' );

$result = velotok_core_catalog_validate_product( velotok_catalog_attributes_fixture( array( 'price' => -100 ) ) );
velotok_catalog_attributes_assert( false === $result['valid'], 'Товар с отрицательной ценой принят.' );
velotok_catalog_attributes_assert( isset( $result['errors_by_attribute']['price'] ) && $error_codes['price'] === $result['errors_by_attribute']['price'], 'Для отрицательной цены нужен код INVALID_PRICE.' );

$result = velotok_core_catalog_validate_product( velotok_catalog_attributes_fixture( array( 'price' => '0' ) ) );
velotok_catalog_attributes_assert( true === $result['valid'], 'Нулевая цена должна считаться валидной.' );

/*
 * 7. Optional attributes may be absent or empty, the validator still passes.
 */
$optional = velotok_catalog_attributes_fixture();
unset( $optional['short_description'], $optional['description'], $optional['weight_kg'] );
$optional['wheel'] = '';

$result = velotok_core_catalog_validate_product( $optional );

velotok_catalog_attributes_assert( true === $result['valid'], 'Отсутствующие необязательные атрибуты не должны блокировать товар.' );
velotok_catalog_attributes_assert( true === velotok_core_catalog_product_is_valid( $optional ), 'Проверка валидности отклонила товар без необязательных атрибутов.' );

/*
 * 8. Short aliases expose the same contract validation.
 */
$alias_result = function_exists( 'catalogValidateProduct' ) ? catalogValidateProduct( velotok_catalog_attributes_fixture() ) : array();
velotok_catalog_attributes_assert( true === $alias_result['valid'], 'Глобальный псевдоним валидации вернул неверный результат.' );
velotok_catalog_attributes_assert( function_exists( 'catalogProductIsValid' ) && true === catalogProductIsValid( velotok_catalog_attributes_fixture() ), 'Глобальный псевдоним проверки валидности не работает.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: атрибуты товаров каталога валидируются по контракту (TESTEB-17).' . PHP_EOL;
