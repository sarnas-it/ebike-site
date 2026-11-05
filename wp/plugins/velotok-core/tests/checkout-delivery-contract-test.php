<?php
/**
 * Contract test for the mocked checkout delivery form stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/checkout-delivery-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/checkout-delivery-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer renders and submits the delivery selection with its cost
 * included in the order total. No real delivery service is contacted.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path     = dirname( __DIR__ ) . '/includes/checkout-delivery.php';
$dependency    = dirname( __DIR__ ) . '/includes/order-delivery.php';

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки оформления доставки заказа.' . PHP_EOL );
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
function velotok_checkout_delivery_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_core_checkout_delivery_form_contract' ) ) {
	velotok_checkout_delivery_assert( false, 'Отсутствует контракт формы оформления доставки заказа.' );
} else {
	$contract = velotok_core_checkout_delivery_form_contract();

	velotok_checkout_delivery_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_checkout_delivery_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_checkout_delivery_assert( isset( $contract['spec'] ) && 'TESTEB-152' === $contract['spec'], 'Контракт ссылается на неверную задачу.' );
	velotok_checkout_delivery_assert( isset( $contract['spec_ref'] ) && 'clarify-b0e1a6d7' === $contract['spec_ref'], 'Контракт ссылается на неверную спеку.' );
	velotok_checkout_delivery_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_checkout_delivery_assert( isset( $contract['network'] ) && false === $contract['network'], 'Заглушка не должна ходить в сеть.' );
	velotok_checkout_delivery_assert( isset( $contract['provider_contract']['mocked'] ) && true === $contract['provider_contract']['mocked'], 'Stub-провайдер не помечен mocked: true.' );
	velotok_checkout_delivery_assert( isset( $contract['provider_contract']['network'] ) && false === $contract['provider_contract']['network'], 'Stub-провайдер не должен ходить в сеть.' );
	velotok_checkout_delivery_assert( isset( $contract['stub_contract']['mocked'] ) && true === $contract['stub_contract']['mocked'], 'Stub-контракт не помечен mocked: true.' );
	velotok_checkout_delivery_assert( isset( $contract['form']['id'] ) && 'velotok-checkout-delivery' === $contract['form']['id'], 'Не описан идентификатор формы доставки.' );
	velotok_checkout_delivery_assert( isset( $contract['form']['field'] ) && 'velotok_delivery_method' === $contract['form']['field'], 'Не описано поле выбора доставки.' );
	velotok_checkout_delivery_assert( isset( $contract['form']['mocked'] ) && true === $contract['form']['mocked'], 'Форма доставки не помечена mocked: true.' );
	velotok_checkout_delivery_assert( isset( $contract['form']['required'] ) && true === $contract['form']['required'], 'Выбор доставки должен быть обязательным.' );
	velotok_checkout_delivery_assert( isset( $contract['validation']['missing'] ) && 'DELIVERY_METHOD_REQUIRED' === $contract['validation']['missing'], 'Не описан код ошибки пустого выбора.' );
	velotok_checkout_delivery_assert( isset( $contract['validation']['invalid'] ) && 'DELIVERY_METHOD_INVALID' === $contract['validation']['invalid'], 'Не описан код ошибки неизвестного способа.' );
	velotok_checkout_delivery_assert( isset( $contract['totals']['include_delivery'] ) && true === $contract['totals']['include_delivery'], 'Стоимость доставки должна включаться в итог заказа.' );
	velotok_checkout_delivery_assert( isset( $contract['real_integration']['required'] ) && false === $contract['real_integration']['required'], 'Реальная интеграция не должна требоваться.' );
	velotok_checkout_delivery_assert( ! empty( $contract['open_questions'] ) && is_array( $contract['open_questions'] ), 'Не зафиксированы открытые вопросы.' );
}

if ( function_exists( 'velotok_core_checkout_delivery_form_options' ) ) {
	$options = velotok_core_checkout_delivery_form_options( 'cdek' );
	$ids     = array();
	$labels  = array();
	$selected = array();

	velotok_checkout_delivery_assert( is_array( $options ), 'Список способов доставки должен быть массивом.' );
	velotok_checkout_delivery_assert( 3 === count( $options ), 'На форме оформления должно быть три способа доставки.' );

	foreach ( $options as $option ) {
		velotok_checkout_delivery_assert( isset( $option['id'], $option['label'], $option['selected'] ), 'У способа доставки должны быть id, label и selected.' );
		velotok_checkout_delivery_assert( isset( $option['mocked'] ) && true === $option['mocked'], 'Способ доставки не помечен mocked: true.' );
		velotok_checkout_delivery_assert( isset( $option['source'] ) && 'stub' === $option['source'], 'Способ доставки должен приходить из stub.' );
		velotok_checkout_delivery_assert( isset( $option['field'] ) && 'velotok_delivery_method' === $option['field'], 'Способ доставки должен быть привязан к полю формы.' );

		$ids[]    = $option['id'];
		$labels[] = $option['label'];

		if ( ! empty( $option['selected'] ) ) {
			$selected[] = $option['id'];
		}
	}

	foreach ( array( 'russian_post', 'cdek', 'other' ) as $id ) {
		velotok_checkout_delivery_assert( in_array( $id, $ids, true ), sprintf( 'Не найден идентификатор способа доставки «%s».', $id ) );
	}

	foreach ( array( 'Почта РФ', 'СДЭК', 'Иной логист' ) as $label ) {
		velotok_checkout_delivery_assert( in_array( $label, $labels, true ), sprintf( 'Не найдено название способа доставки «%s».', $label ) );
	}

	velotok_checkout_delivery_assert( array( 'cdek' ) === $selected, 'Выбранным должен быть ровно один способ доставки.' );

	$default = velotok_core_checkout_delivery_form_options();
	$selected = array();

	foreach ( $default as $option ) {
		if ( ! empty( $option['selected'] ) ) {
			$selected[] = $option['id'];
		}
	}

	velotok_checkout_delivery_assert( array( 'russian_post' ) === $selected, 'Без явного выбора должен выделяться способ по умолчанию.' );
}

if ( function_exists( 'velotok_core_checkout_delivery_form_submit' ) ) {
	$missing = velotok_core_checkout_delivery_form_submit( array(), array( 'id' => 128, 'subtotal' => 10000, 'weight' => 2 ) );
	velotok_checkout_delivery_assert( false === $missing['valid'] && false === $missing['saved'], 'Пустой выбор доставки не должен сохраняться.' );
	velotok_checkout_delivery_assert( 'DELIVERY_METHOD_REQUIRED' === $missing['code'], 'Пустой выбор должен давать DELIVERY_METHOD_REQUIRED.' );
	velotok_checkout_delivery_assert( isset( $missing['mocked'] ) && true === $missing['mocked'], 'Ошибка формы должна быть помечена mocked: true.' );
	velotok_checkout_delivery_assert( isset( $missing['submitted'] ) && true === $missing['submitted'], 'Ответ должен подтверждать отправку формы.' );
	velotok_checkout_delivery_assert( isset( $missing['errors'][0] ) && 'DELIVERY_METHOD_REQUIRED' === $missing['errors'][0], 'Ошибка формы должна попадать в errors.' );

	$invalid = velotok_core_checkout_delivery_form_submit( array( 'velotok_delivery_method' => 'teleport' ), array( 'id' => 128, 'subtotal' => 10000, 'weight' => 2 ) );
	velotok_checkout_delivery_assert( false === $invalid['valid'] && false === $invalid['saved'], 'Неизвестный способ доставки не должен сохраняться.' );
	velotok_checkout_delivery_assert( 'DELIVERY_METHOD_INVALID' === $invalid['code'], 'Неизвестный способ должен давать DELIVERY_METHOD_INVALID.' );

	$order = array( 'id' => 128, 'subtotal' => 10000, 'weight' => 2 );
	$valid = velotok_core_checkout_delivery_form_submit( array( 'velotok_delivery_method' => 'cdek' ), $order );

	velotok_checkout_delivery_assert( true === $valid['valid'] && true === $valid['saved'], 'Выбранная доставка должна сохраняться в заказе.' );
	velotok_checkout_delivery_assert( isset( $valid['mocked'] ) && true === $valid['mocked'], 'Результат формы должен быть помечен mocked: true.' );
	velotok_checkout_delivery_assert( isset( $valid['network'] ) && false === $valid['network'], 'Результат формы не должен требовать сети.' );
	velotok_checkout_delivery_assert( 'cdek' === $valid['delivery_method'], 'В заказе должен сохраняться выбранный способ доставки.' );
	velotok_checkout_delivery_assert( 730.0 === (float) $valid['delivery_cost'], 'В заказе должна сохраняться стоимость доставки.' );
	velotok_checkout_delivery_assert( 10730.0 === (float) $valid['order_total'], 'Итоговая сумма заказа должна включать доставку.' );
	velotok_checkout_delivery_assert( true === $valid['delivery_in_total'], 'Стоимость доставки должна быть помечена как включённая в итог.' );
	velotok_checkout_delivery_assert( 'velotok-checkout-delivery' === $valid['form'], 'Результат должен ссылаться на форму доставки.' );
	velotok_checkout_delivery_assert( 'velotok_delivery_method' === $valid['field'], 'Результат должен ссылаться на поле формы.' );
	velotok_checkout_delivery_assert( array() === $valid['errors'], 'Успешная отправка не должна содержать ошибок.' );
	velotok_checkout_delivery_assert( isset( $valid['delivery_meta']['_velotok_delivery_method'] ) && 'cdek' === $valid['delivery_meta']['_velotok_delivery_method'], 'Способ доставки должен попадать в meta заказа.' );
	velotok_checkout_delivery_assert( 128 === $valid['id'], 'Сохранение доставки не должно терять данные заказа.' );
}

foreach (
	array(
		'velotok_core_checkout_delivery_form_contract',
		'velotok_core_order_delivery_form_contract',
		'velotok_core_checkout_delivery_form_is_mocked',
		'velotok_core_checkout_delivery_form_field',
		'velotok_core_checkout_delivery_form_options',
		'velotok_core_checkout_delivery_form_submit',
		'velotok_core_submit_checkout_delivery',
		'velotok_core_checkout_delivery_form_open_questions',
	) as $helper
) {
	velotok_checkout_delivery_assert( function_exists( $helper ), sprintf( 'Отсутствует функция контракта формы доставки: %s.', $helper ) );
}

$sources = array( (string) file_get_contents( $stub_path ) );

if ( is_readable( $dependency ) ) {
	$sources[] = (string) file_get_contents( $dependency );
} else {
	velotok_checkout_delivery_assert( false, 'Отсутствует stub-контракт оформления доставки, на который опирается форма.' );
}

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
	'fsockopen(',
	'stream_socket_' . 'client(',
	'socket_' . 'create(',
	'$wp' . 'db',
	'get_option(',
	'update_option(',
	'wp_insert_post(',
	'get_post_meta(',
	'update_post_meta(',
);

foreach ( $sources as $source ) {
	foreach ( $forbidden as $needle ) {
		velotok_checkout_delivery_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику или БД: %s', $needle ) );
	}
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт формы оформления доставки, выбора способа, стоимости и итога заказа на месте.' . PHP_EOL;
