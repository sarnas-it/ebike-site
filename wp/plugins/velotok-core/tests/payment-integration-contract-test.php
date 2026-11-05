<?php
/**
 * Contract test for the mocked payment system integration stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/payment-integration-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/payment-integration-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer exposes the payment provider, statuses, payment creation
 * and validation rules required to pay for an order.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/payment-integration.php';

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки интеграции с платёжной системой.' . PHP_EOL );
	exit( 1 );
}

require_once $stub_path;

$failures = array();

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_payment_integration_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$expected_statuses = array( 'pending', 'waiting_for_capture', 'succeeded', 'canceled' );

if ( ! function_exists( 'velotok_core_payment_integration_contract' ) ) {
	velotok_payment_integration_assert( false, 'Отсутствует контракт интеграции с платёжной системой.' );
} else {
	$contract = velotok_core_payment_integration_contract();

	velotok_payment_integration_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_payment_integration_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_payment_integration_assert( isset( $contract['spec'] ) && 'ecommerce-store' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_payment_integration_assert( isset( $contract['requirement'] ) && 'TESTEB-153' === $contract['requirement'], 'Контракт ссылается на неверное требование.' );
	velotok_payment_integration_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_payment_integration_assert( isset( $contract['parent_spec'] ) && 'ecommerce-store' === $contract['parent_spec'], 'Не зафиксирована родительская спецификация.' );
	velotok_payment_integration_assert( isset( $contract['checkout_spec'] ) && 'clarify-096bb945' === $contract['checkout_spec'], 'Не зафиксирована спецификация оформления заказа.' );
	velotok_payment_integration_assert( isset( $contract['provider'] ) && 'yookassa' === $contract['provider'], 'Платёжной системой должна быть ЮKassa.' );
	velotok_payment_integration_assert( isset( $contract['provider_name'] ) && 'ЮKassa' === $contract['provider_name'], 'Не зафиксировано название платёжной системы ЮKassa.' );
	velotok_payment_integration_assert( isset( $contract['network'] ) && false === $contract['network'], 'Заглушка не должна ходить в сеть.' );
	velotok_payment_integration_assert( isset( $contract['real_integration']['required'] ) && false === $contract['real_integration']['required'], 'Реальная интеграция не требуется.' );
	velotok_payment_integration_assert( isset( $contract['currency'] ) && 'RUB' === $contract['currency'], 'Валютой платежа должна быть RUB.' );
	velotok_payment_integration_assert( isset( $contract['future_source'] ) && 'env' === $contract['future_source'], 'Не зафиксировано направление на env для ЮKassa.' );
	velotok_payment_integration_assert( isset( $contract['env']['shop_id'] ) && 'VELOTOK_YOOKASSA_SHOP_ID' === $contract['env']['shop_id'], 'Неверное имя env-переменной shop_id.' );
	velotok_payment_integration_assert( isset( $contract['env']['secret_key'] ) && 'VELOTOK_YOOKASSA_SECRET_KEY' === $contract['env']['secret_key'], 'Неверное имя env-переменной secret_key.' );
	velotok_payment_integration_assert( isset( $contract['env']['mode'] ) && 'VELOTOK_YOOKASSA_MODE' === $contract['env']['mode'], 'Неверное имя env-переменной mode.' );
	velotok_payment_integration_assert( in_array( 'test', $contract['supported_modes'], true ) && in_array( 'prod', $contract['supported_modes'], true ), 'Не зафиксировано разделение test/prod для ЮKassa.' );
	velotok_payment_integration_assert( isset( $contract['open_questions'] ) && is_array( $contract['open_questions'] ) && ! empty( $contract['open_questions'] ), 'Не зафиксированы открытые вопросы по интеграции.' );

	velotok_payment_integration_assert( isset( $contract['statuses'] ) && is_array( $contract['statuses'] ), 'Не описан перечень статусов оплаты.' );
	velotok_payment_integration_assert( isset( $contract['status_ids'] ) && $expected_statuses === $contract['status_ids'], 'Перечень статусов оплаты не совпадает с контрактом.' );

	foreach ( $expected_statuses as $status_id ) {
		velotok_payment_integration_assert( isset( $contract['statuses'][ $status_id ] ), sprintf( 'Не описан статус оплаты «%s».', $status_id ) );
		velotok_payment_integration_assert( isset( $contract['statuses'][ $status_id ]['mocked'] ) && true === $contract['statuses'][ $status_id ]['mocked'], sprintf( 'Статус «%s» не помечен mocked: true.', $status_id ) );
	}
}

if ( function_exists( 'velotok_core_payment_integration_statuses' ) ) {
	$statuses = velotok_core_payment_integration_statuses();

	velotok_payment_integration_assert( is_array( $statuses ) && 4 === count( $statuses ), 'Должно быть описано четыре статуса оплаты.' );
	velotok_payment_integration_assert( true === velotok_core_payment_integration_status_is_valid( 'succeeded' ), 'Статус succeeded должен быть валидным.' );
	velotok_payment_integration_assert( true === velotok_core_payment_integration_status_is_valid( 'Оплачен' ), 'Русское название статуса должно разрешаться.' );
	velotok_payment_integration_assert( false === velotok_core_payment_integration_status_is_valid( 'refunded' ), 'Неизвестный статус не должен быть валидным.' );
	velotok_payment_integration_assert( 'Оплачен' === velotok_core_payment_integration_status_label( 'succeeded' ), 'Неверная подпись статуса succeeded.' );
}

if ( function_exists( 'velotok_core_payment_integration_create' ) ) {
	$payment = velotok_core_payment_integration_create( array( 'id' => 42, 'total' => 62900.5 ) );

	velotok_payment_integration_assert( is_array( $payment ), 'Платёж должен быть массивом.' );
	velotok_payment_integration_assert( isset( $payment['mocked'] ) && true === $payment['mocked'], 'Платёж не помечен mocked: true.' );
	velotok_payment_integration_assert( isset( $payment['provider'] ) && 'yookassa' === $payment['provider'], 'Платёж должен создаваться для ЮKassa.' );
	velotok_payment_integration_assert( isset( $payment['status'] ) && 'pending' === $payment['status'], 'Заглушка должна возвращать статус pending.' );
	velotok_payment_integration_assert( isset( $payment['paid'] ) && false === $payment['paid'], 'Заглушка не должна считать заказ оплаченным без реального провайдера.' );
	velotok_payment_integration_assert( isset( $payment['amount']['value'] ) && 62900.5 === (float) $payment['amount']['value'], 'Сумма платежа должна браться из заказа.' );
	velotok_payment_integration_assert( isset( $payment['amount']['currency'] ) && 'RUB' === $payment['amount']['currency'], 'Валюта платежа должна быть RUB.' );
	velotok_payment_integration_assert( isset( $payment['confirmation_url'] ) && 0 === strpos( (string) $payment['confirmation_url'], 'mock://yookassa/' ), 'Подтверждение платежа должно быть mocked-ссылкой.' );
	velotok_payment_integration_assert( false === velotok_core_payment_integration_is_paid( $payment ), 'Созданный платёж не должен считаться оплаченным.' );

	$succeeded = velotok_core_payment_integration_confirm( $payment, 'succeeded' );
	velotok_payment_integration_assert( 'succeeded' === $succeeded['status'], 'Подтверждение должно переводить платёж в succeeded.' );
	velotok_payment_integration_assert( true === $succeeded['paid'], 'Подтверждённый платёж должен быть оплачен.' );
	velotok_payment_integration_assert( true === velotok_core_payment_integration_is_paid( $succeeded ), 'Помощник is_paid должен распознавать оплаченный платёж.' );
	velotok_payment_integration_assert( true === velotok_core_payment_integration_status_result( 'succeeded' )['final'], 'Статус succeeded должен быть финальным.' );
}

if ( function_exists( 'velotok_core_payment_integration_validate' ) ) {
	$valid = velotok_core_payment_integration_validate(
		array(
			'order_id' => 42,
			'amount'   => 62900.5,
			'currency' => 'RUB',
			'status'   => 'pending',
		)
	);

	velotok_payment_integration_assert( true === $valid['valid'], 'Корректные данные платежа должны проходить проверку.' );
	velotok_payment_integration_assert( true === $valid['mocked'], 'Результат проверки должен быть помечен mocked.' );
	velotok_payment_integration_assert( 'yookassa' === $valid['payment_provider'], 'Проверка должна использовать ЮKassa.' );
	velotok_payment_integration_assert( array() === $valid['errors'], 'У корректного платежа не должно быть ошибок.' );

	$unsupported = velotok_core_payment_integration_validate(
		array(
			'amount'           => 1000,
			'currency'         => 'RUB',
			'payment_provider' => 'stripe',
		)
	);
	velotok_payment_integration_assert( false === $unsupported['valid'], 'Неподдерживаемая платёжная система должна блокироваться.' );
	velotok_payment_integration_assert( 'PAYMENT_PROVIDER_UNSUPPORTED' === $unsupported['error'], 'Должна быть ошибка PAYMENT_PROVIDER_UNSUPPORTED.' );

	$bad_currency = velotok_core_payment_integration_validate(
		array(
			'amount'   => 1000,
			'currency' => 'USD',
		)
	);
	velotok_payment_integration_assert( false === $bad_currency['valid'], 'Неподдерживаемая валюта должна блокироваться.' );
	velotok_payment_integration_assert( in_array( 'PAYMENT_CURRENCY_UNSUPPORTED', $bad_currency['errors'], true ), 'Должна быть ошибка PAYMENT_CURRENCY_UNSUPPORTED.' );

	$bad_status = velotok_core_payment_integration_validate(
		array(
			'amount'   => 1000,
			'currency' => 'RUB',
			'status'   => 'refunded',
		)
	);
	velotok_payment_integration_assert( false === $bad_status['valid'], 'Неизвестный статус должен блокироваться.' );
	velotok_payment_integration_assert( in_array( 'PAYMENT_STATUS_INVALID', $bad_status['errors'], true ), 'Должна быть ошибка PAYMENT_STATUS_INVALID.' );

	$bad_amount = velotok_core_payment_integration_validate(
		array(
			'amount'   => 'not-a-number',
			'currency' => 'RUB',
		)
	);
	velotok_payment_integration_assert( false === $bad_amount['valid'], 'Нечисловая сумма должна блокироваться.' );
	velotok_payment_integration_assert( in_array( 'PAYMENT_AMOUNT_INVALID', $bad_amount['errors'], true ), 'Должна быть ошибка PAYMENT_AMOUNT_INVALID.' );
}

$source = (string) file_get_contents( $stub_path );

$forbidden = array(
	'get' . 'env(',
	'apache_get' . 'env(',
	'$_' . 'ENV',
	'$_' . 'SERVER',
	'file_get' . '_contents(',
	'fopen(',
	'curl_' . 'init(',
	'wp_remote_' . 'get(',
	'wp_remote_' . 'post(',
);

foreach ( $forbidden as $needle ) {
	velotok_payment_integration_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт интеграции с платёжной системой на месте.' . PHP_EOL;
