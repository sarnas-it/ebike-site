<?php
/**
 * Contract test for the mocked checkout, delivery and payment stubs.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/checkout-contracts-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/checkout-contracts-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer exposes the delivery and payment rules.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/checkout-contracts.php';

$failures = array();

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_checkout_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = velotok_core_checkout_contract();

velotok_checkout_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
velotok_checkout_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
velotok_checkout_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
velotok_checkout_assert( isset( $contract['spec'] ) && 'clarify-096bb945' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );

$delivery_methods = velotok_core_delivery_methods();
velotok_checkout_assert( is_array( $delivery_methods ) && 3 === count( $delivery_methods ), 'Контракт должен объявлять ровно три способа доставки.' );

$labels = array();
foreach ( $delivery_methods as $method ) {
	$labels[] = isset( $method['label'] ) ? $method['label'] : '';
	velotok_checkout_assert( isset( $method['mocked'] ) && true === $method['mocked'], 'Способ доставки не помечен mocked: true.' );
}

foreach ( array( 'Почта РФ', 'СДЭК', 'Иной логист' ) as $expected_label ) {
	velotok_checkout_assert( in_array( $expected_label, $labels, true ), sprintf( 'Не объявлен способ доставки «%s».', $expected_label ) );
}

foreach ( array( 'postal_rf', 'cdek', 'other' ) as $expected_id ) {
	velotok_checkout_assert( isset( $delivery_methods[ $expected_id ] ), sprintf( 'Не объявлен способ доставки «%s».', $expected_id ) );
	velotok_checkout_assert( true === velotok_core_is_valid_delivery_method( $expected_id ), sprintf( 'Способ доставки «%s» не проходит валидацию.', $expected_id ) );
}

velotok_checkout_assert( true === velotok_core_is_valid_delivery_method( 'post' ), 'Псевдоним Почты РФ должен приниматься.' );
velotok_checkout_assert( true === velotok_core_is_valid_delivery_method( 'СДЭК' ), 'Название СДЭК должно приниматься.' );
velotok_checkout_assert( false === velotok_core_is_valid_delivery_method( 'teleport' ), 'Неизвестный способ доставки не должен приниматься.' );

$missing_delivery = velotok_core_validate_delivery_method( '' );
velotok_checkout_assert(
	isset( $missing_delivery['code'] ) && 'DELIVERY_METHOD_REQUIRED' === $missing_delivery['code'],
	'Пустой способ доставки должен давать DELIVERY_METHOD_REQUIRED.'
);

$invalid_delivery = velotok_core_validate_delivery_method( 'teleport' );
velotok_checkout_assert(
	isset( $invalid_delivery['code'] ) && 'DELIVERY_METHOD_UNSUPPORTED' === $invalid_delivery['code'],
	'Неизвестный способ доставки должен давать DELIVERY_METHOD_UNSUPPORTED.'
);

$valid_delivery = velotok_core_validate_delivery_method( 'cdek' );
velotok_checkout_assert( true === $valid_delivery['valid'], 'Известный способ доставки должен быть валидным.' );

$payment_methods = velotok_core_payment_methods();
velotok_checkout_assert( isset( $payment_methods['yookassa'] ), 'Не объявлена оплата через ЮKassa.' );
velotok_checkout_assert( 'ЮKassa' === $payment_methods['yookassa']['label'], 'Неверное название платёжной системы.' );
velotok_checkout_assert( isset( $payment_methods['yookassa']['mocked'] ) && true === $payment_methods['yookassa']['mocked'], 'Оплата не помечена mocked: true.' );

foreach ( array( 'test', 'prod' ) as $mode ) {
	velotok_checkout_assert( in_array( $mode, $payment_methods['yookassa']['modes'], true ), sprintf( 'Не объявлен режим ЮKassa «%s».', $mode ) );
}

foreach ( array( 'VELOTOK_YOOKASSA_SHOP_ID', 'VELOTOK_YOOKASSA_SECRET_KEY', 'VELOTOK_YOOKASSA_MODE' ) as $env_name ) {
	velotok_checkout_assert( in_array( $env_name, $payment_methods['yookassa']['env'], true ), sprintf( 'Не объявлена переменная окружения «%s».', $env_name ) );
}

velotok_checkout_assert( true === velotok_core_is_valid_payment_method( 'yookassa' ), 'ЮKassa должна проходить валидацию.' );
velotok_checkout_assert( true === velotok_core_is_valid_payment_method( 'юкасса' ), 'Псевдоним ЮKassa должен приниматься.' );
velotok_checkout_assert( false === velotok_core_is_valid_payment_method( 'stripe' ), 'Неизвестная платёжная система не должна приниматься.' );

$missing_payment = velotok_core_validate_payment_method( '' );
velotok_checkout_assert(
	isset( $missing_payment['code'] ) && 'PAYMENT_METHOD_REQUIRED' === $missing_payment['code'],
	'Пустая платёжная система должна давать PAYMENT_METHOD_REQUIRED.'
);

$invalid_payment = velotok_core_validate_payment_method( 'stripe' );
velotok_checkout_assert(
	isset( $invalid_payment['code'] ) && 'PAYMENT_METHOD_UNSUPPORTED' === $invalid_payment['code'],
	'Неизвестная платёжная система должна давать PAYMENT_METHOD_UNSUPPORTED.'
);

$acceptance = velotok_core_content_acceptance_contract();
velotok_checkout_assert( true === velotok_core_content_requires_human_acceptance(), 'Приёмка контента должна требовать человека.' );
velotok_checkout_assert( isset( $acceptance['role'] ) && 'project_manager' === $acceptance['role'], 'Приёмка контента должна требовать роль руководителя проекта.' );
velotok_checkout_assert( true === velotok_core_can_accept_content( 'project_manager', true ), 'Руководитель проекта должен принимать контент.' );
velotok_checkout_assert( false === velotok_core_can_accept_content( 'project_manager', false ), 'Автоматическая приёмка контента недопустима.' );

$source_path = dirname( __DIR__ ) . '/includes/checkout-contracts.php';

if ( is_readable( $source_path ) ) {
	$source = (string) file_get_contents( $source_path );

	$forbidden = array(
		'get' . 'env(',
		'apache_get' . 'env(',
		'$_' . 'ENV',
		'$_' . 'SERVER',
		'file_get' . '_contents(',
		'fopen(',
		'curl_' . 'init(',
		'wp_remote_' . 'get(',
	);

	foreach ( $forbidden as $needle ) {
		velotok_checkout_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен содержать обращение к внешнему источнику: %s', $needle ) );
	}
} else {
	velotok_checkout_assert( false, 'Не найден файл заглушки checkout-контракта.' );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт checkout, доставки и оплаты на месте.' . PHP_EOL;
