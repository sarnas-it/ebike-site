<?php
/**
 * Contract test for the mocked checkout, delivery and payments stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/checkout-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/checkout-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked, loses one of the three required delivery options, or stops requiring
 * human acceptance by the project lead.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$failures     = array();
$contract_file = dirname( __DIR__ ) . '/includes/checkout-contracts.php';

if ( is_readable( $contract_file ) ) {
	require_once $contract_file;
} else {
	$failures[] = 'Не найден файл заглушки checkout-контракта.';
}

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_checkout_contract_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_core_checkout_contract' ) ) {
	velotok_checkout_contract_assert( false, 'Отсутствует контракт checkout, доставки и оплаты.' );
} else {
	$contract = velotok_core_checkout_contract();

	velotok_checkout_contract_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_checkout_contract_assert( isset( $contract['spec'] ) && 'clarify-096bb945' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_checkout_contract_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_checkout_contract_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );

	velotok_checkout_contract_assert( isset( $contract['payment'] ) && is_array( $contract['payment'] ), 'Не задан платёжный контракт.' );
	velotok_checkout_contract_assert( isset( $contract['payment']['provider'] ) && 'yookassa' === $contract['payment']['provider'], 'Платёжным провайдером должна быть ЮKassa.' );
	velotok_checkout_contract_assert( isset( $contract['payment']['provider_label'] ) && 'ЮKassa' === $contract['payment']['provider_label'], 'Неверная подпись платёжного провайдера.' );
	velotok_checkout_contract_assert( isset( $contract['payment']['mocked'] ) && true === $contract['payment']['mocked'], 'Платёжный контракт не помечен mocked: true.' );
	velotok_checkout_contract_assert( ! empty( $contract['payment']['gateway_id'] ), 'Не задан идентификатор платёжного шлюза.' );

	velotok_checkout_contract_assert( isset( $contract['delivery'] ) && is_array( $contract['delivery'] ), 'Не задан контракт доставки.' );
	velotok_checkout_contract_assert( isset( $contract['delivery']['mocked'] ) && true === $contract['delivery']['mocked'], 'Контракт доставки не помечен mocked: true.' );

	$expected_methods = array(
		'postal_rf'    => 'Почта РФ',
		'cdek'         => 'СДЭК',
		'other_logist' => 'Иной логист',
	);

	velotok_checkout_contract_assert( isset( $contract['delivery']['options'] ) && is_array( $contract['delivery']['options'] ), 'Не заданы способы доставки.' );
	velotok_checkout_contract_assert( count( $expected_methods ) === count( $contract['delivery']['options'] ), 'Контракт должен описывать ровно три способа доставки.' );

	foreach ( $expected_methods as $method_id => $label ) {
		velotok_checkout_contract_assert( isset( $contract['delivery']['options'][ $method_id ] ), sprintf( 'Не задан способ доставки «%s».', $label ) );
		velotok_checkout_contract_assert( isset( $contract['delivery']['options'][ $method_id ]['label'] ) && $label === $contract['delivery']['options'][ $method_id ]['label'], sprintf( 'Неверная подпись способа доставки «%s».', $method_id ) );
		velotok_checkout_contract_assert( isset( $contract['delivery']['options'][ $method_id ]['mocked'] ) && true === $contract['delivery']['options'][ $method_id ]['mocked'], sprintf( 'Способ доставки «%s» не помечен mocked: true.', $method_id ) );
	}

	velotok_checkout_contract_assert( isset( $contract['delivery']['default'] ) && 'postal_rf' === $contract['delivery']['default'], 'Неверный способ доставки по умолчанию.' );

	velotok_checkout_contract_assert( isset( $contract['human_acceptance'] ) && is_array( $contract['human_acceptance'] ), 'Не задано правило приёмки человеком.' );
	velotok_checkout_contract_assert( isset( $contract['human_acceptance']['required'] ) && true === $contract['human_acceptance']['required'], 'Приёмка контента человеком должна быть обязательной.' );
	velotok_checkout_contract_assert( isset( $contract['human_acceptance']['role'] ) && 'project_lead' === $contract['human_acceptance']['role'], 'Приёмку должен выполнять руководитель проекта.' );
	velotok_checkout_contract_assert( isset( $contract['human_acceptance']['capability'] ) && '' !== $contract['human_acceptance']['capability'], 'Не задана возможность приёмки.' );
}

if ( function_exists( 'velotok_core_delivery_method_ids' ) ) {
	$ids = velotok_core_delivery_method_ids();

	velotok_checkout_contract_assert( array( 'postal_rf', 'cdek', 'other_logist' ) === $ids, 'Порядок или состав способов доставки изменён.' );
	velotok_checkout_contract_assert( 'Почта РФ' === velotok_core_delivery_method_label( 'postal_rf' ), 'Не работает подпись «Почта РФ».' );
	velotok_checkout_contract_assert( 'СДЭК' === velotok_core_delivery_method_label( 'cdek' ), 'Не работает подпись «СДЭК».' );
	velotok_checkout_contract_assert( 'Иной логист' === velotok_core_delivery_method_label( 'other_logist' ), 'Не работает подпись «Иной логист».' );
	velotok_checkout_contract_assert( '' === velotok_core_delivery_method_label( 'unknown' ), 'Неизвестный способ доставки должен возвращать пустую подпись.' );
	velotok_checkout_contract_assert( null === velotok_core_delivery_method( 'unknown' ), 'Неизвестный способ доставки не должен считаться допустимым.' );
	velotok_checkout_contract_assert( 'postal_rf' === velotok_core_delivery_default(), 'Неверный способ доставки по умолчанию.' );
}

if ( function_exists( 'velotok_core_payment_is_mocked' ) ) {
	velotok_checkout_contract_assert( true === velotok_core_payment_is_mocked(), 'Платёжный контракт должен быть помечен mocked.' );
}

if ( function_exists( 'velotok_core_requires_human_acceptance' ) ) {
	velotok_checkout_contract_assert( true === velotok_core_requires_human_acceptance(), 'Приёмка человеком должна быть обязательной.' );
}

if ( function_exists( 'velotok_core_user_can_accept' ) ) {
	$lead = new class() {
		/**
		 * Report the project lead capability.
		 *
		 * @param string $capability Capability name.
		 * @return bool
		 */
		public function has_cap( $capability ) {
			return 'velotok_accept_order' === $capability;
		}
	};

	$visitor = new class() {
		/**
		 * Report no capabilities.
		 *
		 * @param string $capability Capability name.
		 * @return bool
		 */
		public function has_cap( $capability ) {
			return false;
		}
	};

	velotok_checkout_contract_assert( true === velotok_core_user_can_accept( $lead ), 'Руководитель проекта должен иметь возможность приёмки.' );
	velotok_checkout_contract_assert( false === velotok_core_user_can_accept( $visitor ), 'Пользователь без роли не должен иметь возможность приёмки.' );
}

if ( is_readable( $contract_file ) ) {
	$source = (string) file_get_contents( $contract_file );

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
		velotok_checkout_contract_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен содержать обращение к внешнему источнику: %s', $needle ) );
	}
}

if ( $failures ) {
	$error_stream = defined( 'STDERR' ) ? STDERR : fopen( 'php://stderr', 'w' );

	foreach ( $failures as $failure ) {
		fwrite( $error_stream, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт checkout, доставки и оплаты на месте.' . PHP_EOL;
