<?php
/**
 * Behaviour test for TESTEB-62: price validation returns a concrete message.
 *
 * Run from the project root:
 *   php wp/plugins/velotok-core/tests/product-price-validation-message-test.php
 *
 * The core validator must reject missing, non-numeric and negative prices with
 * a human-readable message, not only an error code. Zero stays valid.
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

require_once dirname( __DIR__ ) . '/includes/product-validation.php';

$GLOBALS['velotok_price_message_failures'] = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_price_message_assert( $condition, $message ) {
	if ( ! $condition ) {
		$GLOBALS['velotok_price_message_failures'][] = $message;
	}
}

$contract = velotok_core_product_validation_contract();
$rule     = velotok_core_price_validation_contract();

velotok_price_message_assert( 'TESTEB-62' === $contract['price_requirement'], 'Контракт не ссылается на TESTEB-62.' );
velotok_price_message_assert( isset( $contract['messages']['invalid_price'] ) && '' !== $contract['messages']['invalid_price'], 'В контракте нет конкретного сообщения о невалидной цене.' );
velotok_price_message_assert( isset( $rule['error_messages']['required'], $rule['error_messages']['format'], $rule['error_messages']['negative'] ), 'Правило цены не задаёт сообщения для всех ошибок.' );

$cases = array(
	'negative' => array( -100, 'PRICE_NEGATIVE', 'отрицательн' ),
	'non-numeric' => array( 'дорого', 'PRICE_INVALID_FORMAT', 'числ' ),
	'missing' => array( '', 'PRICE_REQUIRED', 'цен' ),
);

foreach ( $cases as $label => $case ) {
	list( $price, $code, $needle ) = $case;

	$result = velotok_core_validate_product_price( $price );

	velotok_price_message_assert( false === $result['valid'], 'Невалидная цена принята: ' . $label );
	velotok_price_message_assert( $code === $result['code'], 'Неверный код цены: ' . $label );
	velotok_price_message_assert( ! empty( $result['message'] ), 'Нет конкретного сообщения для цены: ' . $label );
	velotok_price_message_assert( false !== mb_stripos( $result['message'], $needle ), 'Сообщение не содержит ожидаемого пояснения для цены: ' . $label );
	velotok_price_message_assert( 'invalid' === $result['status'], 'Неверный статус невалидной цены: ' . $label );
}

$zero = velotok_core_validate_product_price( 0 );

velotok_price_message_assert( true === $zero['valid'], 'Нулевая цена должна быть валидной.' );
velotok_price_message_assert( '' === $zero['message'], 'У валидной цены не должно быть сообщения.' );

$product = velotok_core_validate_product(
	array(
		'photo'       => 101,
		'title'       => 'Ривьера',
		'description' => 'Городской электровелосипед.',
		'price'       => '-100',
	)
);

velotok_price_message_assert( false === $product['valid'], 'Товар с отрицательной ценой прошёл валидацию.' );
velotok_price_message_assert( 'PRICE_NEGATIVE' === $product['error'], 'Товар с отрицательной ценой не вернул PRICE_NEGATIVE.' );
velotok_price_message_assert( ! empty( $product['message'] ), 'Товар с отрицательной ценой не вернул сообщение.' );
velotok_price_message_assert( false !== mb_stripos( $product['message'], 'отрицательн' ), 'Сообщение товара не объясняет отрицательную цену.' );
velotok_price_message_assert( isset( $product['messages']['price'] ) && $product['messages']['price'] === $product['message'], 'Сообщение цены не согласовано с результатом атрибута.' );

if ( $GLOBALS['velotok_price_message_failures'] ) {
	fwrite( STDERR, 'FAIL: ' . count( $GLOBALS['velotok_price_message_failures'] ) . ' проверок сообщений о цене не прошли:' . PHP_EOL );

	foreach ( $GLOBALS['velotok_price_message_failures'] as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: невалидная цена отклоняется с конкретным сообщением (TESTEB-62, core).' . PHP_EOL;
exit( 0 );
