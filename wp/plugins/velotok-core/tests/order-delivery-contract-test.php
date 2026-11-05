<?php
/**
 * Contract test for the mocked checkout delivery stub contract.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/order-delivery-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/order-delivery-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer exposes delivery selection, delivery cost and order total
 * calculation. No real delivery service is contacted.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/order-delivery.php';

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
function velotok_order_delivery_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_core_order_delivery_contract' ) ) {
	velotok_order_delivery_assert( false, 'Отсутствует контракт оформления доставки заказа.' );
} else {
	$contract = velotok_core_order_delivery_contract();

	velotok_order_delivery_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_order_delivery_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_order_delivery_assert( isset( $contract['spec'] ) && 'clarify-b0e1a6d7' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_order_delivery_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_order_delivery_assert( isset( $contract['provider_contract']['source'] ) && 'stub' === $contract['provider_contract']['source'], 'Stub-провайдер должен быть источником доставки.' );
	velotok_order_delivery_assert( isset( $contract['provider_contract']['mocked'] ) && true === $contract['provider_contract']['mocked'], 'Stub-провайдер не помечен mocked: true.' );
	velotok_order_delivery_assert( isset( $contract['provider_contract']['network'] ) && false === $contract['provider_contract']['network'], 'Stub-провайдер не должен ходить в сеть.' );
	velotok_order_delivery_assert( isset( $contract['totals']['include_delivery'] ) && true === $contract['totals']['include_delivery'], 'Стоимость доставки должна включаться в итоговую сумму.' );
	velotok_order_delivery_assert( isset( $contract['display']['show_delivery_cost'] ) && true === $contract['display']['show_delivery_cost'], 'Стоимость доставки должна отображаться.' );
	velotok_order_delivery_assert( isset( $contract['real_integration']['required'] ) && false === $contract['real_integration']['required'], 'Реальная интеграция не должна требоваться.' );
	velotok_order_delivery_assert( isset( $contract['stub_contract']['mocked'] ) && true === $contract['stub_contract']['mocked'], 'Stub-контракт не помечен mocked: true.' );
	velotok_order_delivery_assert( isset( $contract['stub_contract']['network'] ) && false === $contract['stub_contract']['network'], 'Stub-контракт не должен ходить в сеть.' );
	velotok_order_delivery_assert( ! empty( $contract['open_questions'] ) && is_array( $contract['open_questions'] ), 'Не зафиксированы открытые вопросы.' );
}

if ( function_exists( 'velotok_core_delivery_options_contract' ) ) {
	$delivery_contract = velotok_core_delivery_options_contract();

	velotok_order_delivery_assert( isset( $delivery_contract['mocked'] ) && true === $delivery_contract['mocked'], 'Контракт способов доставки не помечен mocked: true.' );
	velotok_order_delivery_assert( isset( $delivery_contract['source'] ) && 'stub' === $delivery_contract['source'], 'Контракт способов доставки должен ссылаться на stub.' );
	velotok_order_delivery_assert( isset( $delivery_contract['network'] ) && false === $delivery_contract['network'], 'Контракт способов доставки не должен ходить в сеть.' );
	velotok_order_delivery_assert( isset( $delivery_contract['provider_contract']['mocked'] ) && true === $delivery_contract['provider_contract']['mocked'], 'Провайдер способов доставки не помечен mocked: true.' );
	velotok_order_delivery_assert( isset( $delivery_contract['provider_contract']['network'] ) && false === $delivery_contract['provider_contract']['network'], 'Провайдер способов доставки не должен ходить в сеть.' );
	velotok_order_delivery_assert( isset( $delivery_contract['order'] ) && is_array( $delivery_contract['order'] ), 'Не описан формат сохранения доставки в заказе.' );
	velotok_order_delivery_assert( isset( $delivery_contract['order']['mocked'] ) && true === $delivery_contract['order']['mocked'], 'Блок заказа в контракте не помечен mocked: true.' );
	velotok_order_delivery_assert( isset( $delivery_contract['order']['source'] ) && 'stub' === $delivery_contract['order']['source'], 'Блок заказа должен ссылаться на stub.' );
	velotok_order_delivery_assert( isset( $delivery_contract['tariffs'] ) && is_array( $delivery_contract['tariffs'] ) && ! empty( $delivery_contract['tariffs'] ), 'Не описана тарифная сетка заглушки.' );

	foreach ( $delivery_contract['tariffs'] as $tariff_id => $tariff ) {
		velotok_order_delivery_assert( isset( $tariff['mocked'] ) && true === $tariff['mocked'], sprintf( 'Тариф «%s» не помечен mocked: true.', $tariff_id ) );
		velotok_order_delivery_assert( isset( $tariff['source'] ) && 'stub' === $tariff['source'], sprintf( 'Тариф «%s» должен ссылаться на stub.', $tariff_id ) );
		velotok_order_delivery_assert( isset( $tariff['provider'] ) && 'stub' === $tariff['provider'], sprintf( 'Тариф «%s» должен приходить из stub-провайдера.', $tariff_id ) );
	}
} else {
	velotok_order_delivery_assert( false, 'Отсутствует контракт способов доставки, на который опирается оформление заказа.' );
}

if ( function_exists( 'velotok_core_order_delivery_options' ) ) {
	$options = velotok_core_order_delivery_options();
	$ids     = array();
	$labels  = array();

	velotok_order_delivery_assert( is_array( $options ), 'Список способов доставки должен быть массивом.' );
	velotok_order_delivery_assert( 3 === count( $options ), 'На оформлении заказа должно быть три способа доставки.' );

	foreach ( $options as $option ) {
		velotok_order_delivery_assert( isset( $option['id'], $option['label'], $option['tariff'] ), 'У способа доставки должны быть id, label и tariff.' );
		velotok_order_delivery_assert( isset( $option['mocked'] ) && true === $option['mocked'], 'Способ доставки не помечен mocked: true.' );
		velotok_order_delivery_assert( isset( $option['provider'] ) && 'stub' === $option['provider'], 'Способ доставки должен приходить из stub-провайдера.' );
		$ids[]    = $option['id'];
		$labels[] = $option['label'];
	}

	foreach ( array( 'russian_post', 'cdek', 'other' ) as $id ) {
		velotok_order_delivery_assert( in_array( $id, $ids, true ), sprintf( 'Не найден идентификатор способа доставки «%s».', $id ) );
	}

	foreach ( array( 'Почта РФ', 'СДЭК', 'Иной логист' ) as $label ) {
		velotok_order_delivery_assert( in_array( $label, $labels, true ), sprintf( 'Не найдено название способа доставки «%s».', $label ) );
	}
}

if ( function_exists( 'velotok_core_validate_order_delivery_method' ) ) {
	$missing = velotok_core_validate_order_delivery_method( '' );
	velotok_order_delivery_assert( false === $missing['valid'] && 'DELIVERY_METHOD_REQUIRED' === $missing['code'], 'Пустой способ доставки должен давать DELIVERY_METHOD_REQUIRED.' );
	velotok_order_delivery_assert( true === $missing['mocked'], 'Ошибка выбора доставки должна быть помечена mocked: true.' );

	$invalid = velotok_core_validate_order_delivery_method( 'teleport' );
	velotok_order_delivery_assert( false === $invalid['valid'] && 'DELIVERY_METHOD_INVALID' === $invalid['code'], 'Неизвестный способ доставки должен давать DELIVERY_METHOD_INVALID.' );

	$valid = velotok_core_validate_order_delivery_method( 'СДЭК' );
	velotok_order_delivery_assert( true === $valid['valid'] && 'cdek' === $valid['method'], 'Известный способ доставки должен разрешаться по названию.' );
}

if ( function_exists( 'velotok_core_order_delivery_cost' ) ) {
	$cost = velotok_core_order_delivery_cost( 'cdek', array( 'subtotal' => 10000, 'weight' => 2 ) );
	velotok_order_delivery_assert( true === $cost['valid'] && true === $cost['mocked'], 'Стоимость доставки должна быть mocked-результатом.' );
	velotok_order_delivery_assert( 730.0 === (float) $cost['amount'], 'Стоимость доставки должна рассчитываться по тарифу stub-провайдера.' );
	velotok_order_delivery_assert( 'RUB' === $cost['currency'], 'Стоимость доставки должна быть в рублях.' );

	$invalid = velotok_core_order_delivery_cost( 'teleport', array( 'subtotal' => 10000 ) );
	velotok_order_delivery_assert( false === $invalid['valid'] && 'DELIVERY_METHOD_INVALID' === $invalid['code'], 'Неизвестный способ должен давать ошибку расчёта.' );
}

if ( function_exists( 'velotok_core_order_delivery_totals' ) ) {
	$order  = array( 'id' => 128, 'subtotal' => 10000, 'weight' => 2 );
	$totals = velotok_core_order_delivery_totals( $order, 'cdek' );

	velotok_order_delivery_assert( true === $totals['valid'] && true === $totals['mocked'], 'Итоги заказа должны быть mocked-результатом.' );
	velotok_order_delivery_assert( 'cdek' === $totals['delivery_method'], 'В итогах должен быть выбранный способ доставки.' );
	velotok_order_delivery_assert( 10000.0 === (float) $totals['subtotal'], 'Подытог заказа должен сохраняться.' );
	velotok_order_delivery_assert( 730.0 === (float) $totals['delivery_cost'], 'В итогах должна быть стоимость доставки.' );
	velotok_order_delivery_assert( 10730.0 === (float) $totals['order_total'], 'Итоговая сумма заказа должна включать стоимость доставки.' );
	velotok_order_delivery_assert( true === $totals['delivery_in_total'], 'Стоимость доставки должна быть помечена как включённая в итог.' );
	velotok_order_delivery_assert( '' !== $totals['delivery_display'] && '' !== $totals['total_display'], 'Стоимость доставки и итог должны иметь отображаемое представление.' );

	$free = velotok_core_order_delivery_totals( array( 'subtotal' => 70000, 'weight' => 2 ), 'cdek' );
	velotok_order_delivery_assert( true === $free['free_delivery'], 'При достижении порога доставка должна быть бесплатной.' );
	velotok_order_delivery_assert( 0.0 === (float) $free['delivery_cost'], 'Бесплатная доставка должна стоить ноль.' );
	velotok_order_delivery_assert( 70000.0 === (float) $free['order_total'], 'Итог при бесплатной доставке равен подытогу.' );

	$default = velotok_core_order_delivery_totals( array( 'subtotal' => 5000, 'weight' => 1 ) );
	velotok_order_delivery_assert( true === $default['valid'] && 'russian_post' === $default['delivery_method'], 'Без явного выбора должен применяться способ по умолчанию.' );
	velotok_order_delivery_assert( 5450.0 === (float) $default['order_total'], 'Итог по умолчанию должен включать стоимость доставки.' );

	$invalid = velotok_core_order_delivery_totals( $order, 'teleport' );
	velotok_order_delivery_assert( false === $invalid['valid'] && 'DELIVERY_METHOD_INVALID' === $invalid['code'], 'Неизвестный способ не должен давать итоги заказа.' );
}

if ( function_exists( 'velotok_core_save_checkout_delivery' ) ) {
	$order = array( 'id' => 128, 'subtotal' => 10000, 'weight' => 2 );
	$saved = velotok_core_save_checkout_delivery( $order, 'cdek' );

	velotok_order_delivery_assert( true === $saved['saved'], 'Выбранная доставка должна помечаться сохранённой.' );
	velotok_order_delivery_assert( 'cdek' === $saved['delivery_method'], 'В заказе должен сохраняться способ доставки.' );
	velotok_order_delivery_assert( 730.0 === (float) $saved['delivery_cost'], 'В заказе должна сохраняться стоимость доставки.' );
	velotok_order_delivery_assert( 10730.0 === (float) $saved['order_total'], 'Итоговая сумма заказа должна включать доставку.' );
	velotok_order_delivery_assert( 10730.0 === (float) $saved['total'], 'Поле total должно включать доставку.' );
	velotok_order_delivery_assert( 'cdek' === $saved['shipping_method'], 'Способ доставки должен сохраняться в shipping_method.' );
	velotok_order_delivery_assert( 730.0 === (float) $saved['shipping_cost'], 'Стоимость должна сохраняться в shipping_cost.' );
	velotok_order_delivery_assert( 'cdek' === $saved['delivery_meta']['_velotok_delivery_method'], 'Способ доставки должен попадать в meta заказа.' );
	velotok_order_delivery_assert( 730.0 === (float) $saved['delivery_meta']['_velotok_delivery_cost'], 'Стоимость должна попадать в meta заказа.' );
	velotok_order_delivery_assert( 'yes' === $saved['delivery_meta']['_velotok_delivery_in_total'], 'Признак включения в итог должен попадать в meta заказа.' );
	velotok_order_delivery_assert( 128 === $saved['id'], 'Сохранение доставки не должно терять данные заказа.' );

	$invalid = velotok_core_save_checkout_delivery( $order, 'teleport' );
	velotok_order_delivery_assert( false === $invalid['valid'] && false === $invalid['saved'], 'Неизвестный способ доставки не должен сохраняться.' );

	$applied = $order;
	velotok_core_apply_checkout_delivery( $applied, 'russian_post' );
	velotok_order_delivery_assert( 'russian_post' === $applied['delivery_method'], 'Применение выбора должно обновлять массив заказа.' );
	velotok_order_delivery_assert( 10550.0 === (float) $applied['total'], 'Применение выбора должно включать доставку в итог.' );
}

foreach (
	array(
		'velotok_core_order_delivery_contract',
		'velotok_core_order_delivery_options',
		'velotok_core_validate_order_delivery_method',
		'velotok_core_order_delivery_cost',
		'velotok_core_order_delivery_totals',
		'velotok_core_save_checkout_delivery',
		'velotok_core_apply_checkout_delivery',
	) as $helper
) {
	velotok_order_delivery_assert( function_exists( $helper ), sprintf( 'Отсутствует функция контракта оформления доставки: %s.', $helper ) );
}

$source        = (string) file_get_contents( $stub_path );
$dependency    = dirname( __DIR__ ) . '/includes/delivery-options.php';
$dependencies  = array( $source );

if ( is_readable( $dependency ) ) {
	$dependencies[] = (string) file_get_contents( $dependency );
} else {
	velotok_order_delivery_assert( false, 'Отсутствует stub-контракт способов доставки.' );
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

foreach ( $dependencies as $dependency_source ) {
	foreach ( $forbidden as $needle ) {
		velotok_order_delivery_assert( false === strpos( $dependency_source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику или БД: %s', $needle ) );
	}
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт оформления доставки, выбора способа и учёта стоимости в итоге заказа на месте.' . PHP_EOL;
