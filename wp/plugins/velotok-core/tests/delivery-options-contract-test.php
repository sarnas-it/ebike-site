<?php
/**
 * Contract test for the mocked delivery options stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-options-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/delivery-options-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer supplies the delivery methods, tariff grid and order
 * persistence shape required by the checkout flow.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/delivery-options.php';

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки способов доставки.' . PHP_EOL );
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
function velotok_delivery_options_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_core_delivery_options_contract' ) ) {
	velotok_delivery_options_assert( false, 'Отсутствует контракт способов доставки.' );
} else {
	$contract = velotok_core_delivery_options_contract();

	velotok_delivery_options_assert( is_array( $contract ), 'Контракт способов доставки должен быть массивом.' );
	velotok_delivery_options_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_delivery_options_assert( isset( $contract['spec'] ) && 'clarify-a8fe76a0' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_delivery_options_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_delivery_options_assert( isset( $contract['provider'] ) && 'stub' === $contract['provider'], 'Провайдером заглушки должен быть stub.' );
	velotok_delivery_options_assert( isset( $contract['network'] ) && false === $contract['network'], 'Заглушка не должна выполнять сетевые вызовы.' );
	velotok_delivery_options_assert( isset( $contract['methods'] ) && is_array( $contract['methods'] ), 'Не предоставлен список способов доставки.' );
	velotok_delivery_options_assert( 3 === count( $contract['methods'] ), 'В заглушке должно быть три способа доставки.' );
	velotok_delivery_options_assert( isset( $contract['tariffs'] ) && is_array( $contract['tariffs'] ), 'Не задана тарифная сетка заглушки.' );
	velotok_delivery_options_assert( isset( $contract['tariff_grid'] ) && is_array( $contract['tariff_grid'] ), 'Не задана тарифная сетка (tariff_grid).' );
	velotok_delivery_options_assert( isset( $contract['order'] ) && is_array( $contract['order'] ), 'Не описан формат сохранения доставки в заказе.' );
	velotok_delivery_options_assert( isset( $contract['order']['mocked'] ) && true === $contract['order']['mocked'], 'Сохранение доставки в заказе не помечено mocked.' );
	velotok_delivery_options_assert( ! empty( $contract['open_questions'] ) && is_array( $contract['open_questions'] ), 'Не зафиксированы открытые вопросы.' );

	$labels = array();

	foreach ( $contract['methods'] as $method ) {
		velotok_delivery_options_assert( isset( $method['id'], $method['label'] ), 'У способа доставки должны быть id и label.' );
		velotok_delivery_options_assert( isset( $method['mocked'] ) && true === $method['mocked'], 'Способ доставки не помечен mocked: true.' );
		$labels[] = $method['label'];
	}

	foreach ( array( 'Почта РФ', 'СДЭК', 'Иной логист' ) as $label ) {
		velotok_delivery_options_assert( in_array( $label, $labels, true ), sprintf( 'Не найден способ доставки «%s».', $label ) );
	}

	foreach ( $contract['tariffs'] as $id => $tariff ) {
		velotok_delivery_options_assert( isset( $tariff['mocked'] ) && true === $tariff['mocked'], sprintf( 'Тариф «%s» не помечен mocked: true.', $id ) );
		velotok_delivery_options_assert( ! empty( $tariff['tiers'] ) && is_array( $tariff['tiers'] ), sprintf( 'Для тарифа «%s» не заданы уровни.', $id ) );

		foreach ( $tariff['tiers'] as $tier ) {
			velotok_delivery_options_assert( isset( $tier['min_total'], $tier['amount'] ), sprintf( 'Уровень тарифа «%s» описан неверно.', $id ) );
		}
	}
}

if ( function_exists( 'velotok_core_delivery_options' ) ) {
	$options = velotok_core_delivery_options( array( 'total' => 10000 ) );

	velotok_delivery_options_assert( is_array( $options ) && 3 === count( $options ), 'Заглушка должна возвращать три способа доставки.' );

	foreach ( $options as $option ) {
		velotok_delivery_options_assert( isset( $option['cost'] ) && is_numeric( $option['cost'] ), 'В способе доставки не рассчитана стоимость.' );
		velotok_delivery_options_assert( isset( $option['cost_mocked'] ) && true === $option['cost_mocked'], 'Стоимость способа доставки не помечена mocked.' );
	}
}

if ( function_exists( 'velotok_core_delivery_options_cost' ) ) {
	$tariff = velotok_core_delivery_options_tariff( 'cdek' );
	$first_tier = isset( $tariff['tiers'][0] ) ? $tariff['tiers'][0] : array();

	$cost = velotok_core_delivery_options_cost( 'cdek', array( 'total' => 10000 ) );
	velotok_delivery_options_assert( true === $cost['valid'], 'Известный способ доставки должен рассчитываться.' );
	velotok_delivery_options_assert( true === $cost['mocked'] && true === $cost['calculated'], 'Расчёт должен быть mocked и calculated.' );
	velotok_delivery_options_assert( 'stub' === $cost['source'] && 'stub' === $cost['provider'], 'Расчёт должен использовать тарифную сетку заглушки.' );
	velotok_delivery_options_assert( (float) $first_tier['amount'] === (float) $cost['amount'], 'Стоимость должна браться из тарифной сетки заглушки.' );

	$with_label = velotok_core_delivery_options_cost( 'СДЭК', array( 'total' => 10000 ) );
	velotok_delivery_options_assert( true === $with_label['valid'] && 'cdek' === $with_label['method'], 'Способ доставки по названию должен рассчитываться.' );

	$free = velotok_core_delivery_options_cost( 'cdek', array( 'total' => 60000 ) );
	velotok_delivery_options_assert( 0.0 === (float) $free['amount'] && true === $free['free'], 'Выше порога должна применяться бесплатная доставка.' );

	$other = velotok_core_delivery_options_cost( 'other', array( 'total' => 60000 ) );
	velotok_delivery_options_assert( (float) $other['amount'] > 0.0, 'Для «Иного логиста» должна действовать отдельная тарифная сетка.' );

	$invalid = velotok_core_delivery_options_cost( 'teleport', array( 'total' => 10000 ) );
	velotok_delivery_options_assert( false === $invalid['valid'] && null === $invalid['amount'], 'Неизвестный способ не должен рассчитываться.' );
	velotok_delivery_options_assert( 'DELIVERY_METHOD_INVALID' === $invalid['code'], 'Для неизвестного способа нужен DELIVERY_METHOD_INVALID.' );
}

if ( function_exists( 'velotok_core_delivery_options_apply_to_order' ) ) {
	$order = velotok_core_delivery_options_apply_to_order( array( 'id' => 42, 'total' => 10000 ), 'cdek' );

	velotok_delivery_options_assert( 'cdek' === $order['delivery_method'], 'Выбранный способ доставки не сохраняется в заказе.' );
	velotok_delivery_options_assert( 'СДЭК' === $order['delivery_method_label'], 'Название способа доставки не сохраняется в заказе.' );
	velotok_delivery_options_assert( isset( $order['delivery_cost'] ) && is_numeric( $order['delivery_cost'] ), 'Стоимость доставки не сохраняется в заказе.' );
	velotok_delivery_options_assert( 'RUB' === $order['delivery_currency'], 'Валюта доставки не сохраняется в заказе.' );
	velotok_delivery_options_assert( true === $order['delivery_mocked'] && true === $order['delivery_options_saved'], 'Сохранение доставки должно быть помечено mocked.' );
	velotok_delivery_options_assert( isset( $order['delivery']['method'] ) && 'cdek' === $order['delivery']['method'], 'Выбранный способ не сохраняется во вложенной структуре заказа.' );
}

foreach ( array( 'velotok_core_delivery_options_tariff_grid', 'velotok_core_delivery_options_open_questions', 'velotok_core_delivery_cost', 'velotok_core_calculate_delivery_cost', 'velotok_core_save_delivery_option' ) as $helper ) {
	velotok_delivery_options_assert( function_exists( $helper ), sprintf( 'Отсутствует функция контракта: %s.', $helper ) );
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
	'$wpdb',
);

foreach ( $forbidden as $needle ) {
	velotok_delivery_options_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт способов доставки и тарифной сетки на месте.' . PHP_EOL;
