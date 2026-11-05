<?php
/**
 * Contract test for the mocked delivery cost in the order total.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-order-total-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/delivery-order-total-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer includes the delivery cost in the order total.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/delivery-order-total.php';

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки стоимости доставки в итоговой сумме заказа.' . PHP_EOL );
	exit( 1 );
}

require_once dirname( __DIR__ ) . '/includes/delivery-options.php';
require_once $stub_path;

$failures = array();

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_delivery_order_total_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_core_delivery_order_total_contract' ) ) {
	velotok_delivery_order_total_assert( false, 'Отсутствует контракт стоимости доставки в итоговой сумме заказа.' );
} else {
	$contract = velotok_core_delivery_order_total_contract();

	velotok_delivery_order_total_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_delivery_order_total_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_delivery_order_total_assert( isset( $contract['spec'] ) && 'clarify-b0e1a6d7' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_delivery_order_total_assert( isset( $contract['parent_spec'] ) && 'clarify-a8fe76a0' === $contract['parent_spec'], 'Контракт ссылается на неверную родительскую спеку.' );
	velotok_delivery_order_total_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_delivery_order_total_assert( isset( $contract['provider_contract']['mocked'] ) && true === $contract['provider_contract']['mocked'], 'Stub-провайдер не помечен mocked: true.' );
	velotok_delivery_order_total_assert( isset( $contract['provider_contract']['network'] ) && false === $contract['provider_contract']['network'], 'Stub-провайдер не должен ходить в сеть.' );
	velotok_delivery_order_total_assert( isset( $contract['includes_delivery'] ) && true === $contract['includes_delivery'], 'Итоговая сумма должна включать стоимость доставки.' );
	velotok_delivery_order_total_assert( isset( $contract['total_includes_delivery'] ) && true === $contract['total_includes_delivery'], 'Контракт не подтверждает включение доставки в итог.' );
	velotok_delivery_order_total_assert( isset( $contract['calculation']['strategy'] ) && 'subtotal_plus_delivery' === $contract['calculation']['strategy'], 'Неверная стратегия расчёта итоговой суммы.' );
	velotok_delivery_order_total_assert( isset( $contract['display']['enabled'] ) && true === $contract['display']['enabled'], 'Отображение стоимости доставки должно быть включено.' );
	velotok_delivery_order_total_assert( isset( $contract['display']['show_delivery_line'] ) && true === $contract['display']['show_delivery_line'], 'Строка доставки должна отображаться.' );
	velotok_delivery_order_total_assert( isset( $contract['display']['total_includes_delivery'] ) && true === $contract['display']['total_includes_delivery'], 'Строка итога должна включать доставку.' );
	velotok_delivery_order_total_assert( isset( $contract['display']['labels']['delivery'] ) && 'Доставка' === $contract['display']['labels']['delivery'], 'Не задана подпись строки доставки.' );
	velotok_delivery_order_total_assert( isset( $contract['display']['labels']['total'] ) && 'Итого' === $contract['display']['labels']['total'], 'Не задана подпись строки итога.' );
	velotok_delivery_order_total_assert( isset( $contract['display']['mocked'] ) && true === $contract['display']['mocked'], 'Отображение не помечено mocked: true.' );
	velotok_delivery_order_total_assert( ! empty( $contract['open_questions'] ) && is_array( $contract['open_questions'] ), 'Не зафиксированы открытые вопросы.' );
	velotok_delivery_order_total_assert( isset( $contract['open_questions']['real_tariffs'] ), 'Не зафиксирован вопрос о тарифной сетке.' );
	velotok_delivery_order_total_assert( isset( $contract['open_questions']['real_integration'] ), 'Не зафиксирован вопрос об интеграции.' );
	velotok_delivery_order_total_assert( isset( $contract['open_questions']['address_dependency'] ), 'Не зафиксирована зависимость от адреса заказа.' );
}

if ( function_exists( 'velotok_core_delivery_order_total' ) ) {
	$summary = velotok_core_delivery_order_total( 'cdek', array( 'subtotal' => 10000, 'weight' => 2 ) );

	velotok_delivery_order_total_assert( true === $summary['valid'], 'Итоговая сумма с доставкой должна быть валидной.' );
	velotok_delivery_order_total_assert( true === $summary['mocked'], 'Итоговая сумма должна быть mocked-результатом.' );
	velotok_delivery_order_total_assert( 'stub' === $summary['source'], 'Источником должна быть заглушка.' );
	velotok_delivery_order_total_assert( 'cdek' === $summary['method'], 'Способ доставки должен сохраняться в итоговой сумме.' );
	velotok_delivery_order_total_assert( 10000.0 === (float) $summary['subtotal'], 'Подытог должен браться из заказа.' );
	velotok_delivery_order_total_assert( 730.0 === (float) $summary['delivery_cost'], 'Стоимость доставки должна браться из тарифной сетки заглушки.' );
	velotok_delivery_order_total_assert( 10730.0 === (float) $summary['total'], 'Итоговая сумма должна включать стоимость доставки.' );
	velotok_delivery_order_total_assert( true === $summary['includes_delivery'], 'Результат должен подтверждать включение доставки.' );
	velotok_delivery_order_total_assert( true === $summary['total_includes_delivery'], 'Итог должен включать доставку.' );
	velotok_delivery_order_total_assert( 'RUB' === $summary['currency'], 'Валюта итоговой суммы должна быть RUB.' );

	velotok_delivery_order_total_assert( isset( $summary['lines']['subtotal'], $summary['lines']['delivery'], $summary['lines']['total'] ), 'В сводке должны быть строки подытога, доставки и итога.' );
	velotok_delivery_order_total_assert( 'Доставка' === $summary['lines']['delivery']['label'], 'Строка доставки должна иметь подпись «Доставка».' );
	velotok_delivery_order_total_assert( 730.0 === (float) $summary['lines']['delivery']['amount'], 'В строке доставки должна быть её стоимость.' );
	velotok_delivery_order_total_assert( false !== strpos( $summary['lines']['delivery']['formatted'], '730' ), 'Стоимость доставки должна быть отформатирована для вывода.' );
	velotok_delivery_order_total_assert( isset( $summary['lines']['total']['includes_delivery'] ) && true === $summary['lines']['total']['includes_delivery'], 'Строка итога должна сообщать, что включает доставку.' );
	velotok_delivery_order_total_assert( 10730.0 === (float) $summary['lines']['total']['amount'], 'Строка итога должна содержать сумму с доставкой.' );

	$free = velotok_core_delivery_order_total( 'cdek', array( 'subtotal' => 70000, 'weight' => 2 ) );
	velotok_delivery_order_total_assert( true === $free['valid'] && true === $free['delivery_free'], 'При достижении порога доставка должна быть бесплатной.' );
	velotok_delivery_order_total_assert( 0.0 === (float) $free['delivery_cost'], 'Бесплатная доставка должна стоить ноль.' );
	velotok_delivery_order_total_assert( 70000.0 === (float) $free['total'], 'Бесплатная доставка не должна увеличивать итог.' );
	velotok_delivery_order_total_assert( true === $free['lines']['delivery']['free'], 'Строка доставки должна быть помечена как бесплатная.' );
	velotok_delivery_order_total_assert( 'Бесплатно' === $free['lines']['delivery']['formatted'], 'Для бесплатной доставки должна выводиться подпись «Бесплатно».' );

	$manual = velotok_core_delivery_order_total( 'other', array( 'subtotal' => 100000, 'weight' => 1 ) );
	velotok_delivery_order_total_assert( 740.0 === (float) $manual['delivery_cost'], 'Стоимость «Иного логиста» должна браться из тарифной сетки.' );
	velotok_delivery_order_total_assert( 100740.0 === (float) $manual['total'], 'Итог для «Иного логиста» должен включать доставку.' );

	$override = velotok_core_delivery_order_total( 'cdek', array( 'subtotal' => 10000, 'weight' => 2 ), array( 'delivery_cost' => 123.45 ) );
	velotok_delivery_order_total_assert( 123.45 === (float) $override['delivery_cost'], 'Явно переданная стоимость доставки должна использоваться.' );
	velotok_delivery_order_total_assert( 10123.45 === (float) $override['total'], 'Итог должен учитывать явно переданную стоимость доставки.' );

	$invalid = velotok_core_delivery_order_total( 'teleport', array( 'subtotal' => 10000, 'weight' => 1 ) );
	velotok_delivery_order_total_assert( false === $invalid['valid'] && 'DELIVERY_METHOD_INVALID' === $invalid['code'], 'Неизвестный способ доставки должен давать DELIVERY_METHOD_INVALID.' );
	velotok_delivery_order_total_assert( null === $invalid['total'], 'Для неизвестного способа не должно быть итоговой суммы.' );
}

if ( function_exists( 'velotok_core_delivery_order_total_lines' ) ) {
	$lines = velotok_core_delivery_order_total_lines( 'russian_post', array( 'subtotal' => 5000, 'weight' => 1 ) );

	velotok_delivery_order_total_assert( isset( $lines['delivery'] ), 'Хелпер строк не возвращает строку доставки.' );
	velotok_delivery_order_total_assert( 450.0 === (float) $lines['delivery']['amount'], 'Строка доставки Почты РФ должна считаться по тарифу.' );
	velotok_delivery_order_total_assert( 5450.0 === (float) $lines['total']['amount'], 'Строка итога должна включать доставку.' );
}

if ( function_exists( 'velotok_core_render_delivery_order_total' ) ) {
	$html = velotok_core_render_delivery_order_total( 'cdek', array( 'subtotal' => 10000, 'weight' => 2 ) );

	velotok_delivery_order_total_assert( is_string( $html ) && '' !== $html, 'Разметка итоговой суммы не должна быть пустой.' );
	velotok_delivery_order_total_assert( false !== strpos( $html, 'data-mocked="true"' ), 'Разметка должна быть помечена как mocked.' );
	velotok_delivery_order_total_assert( false !== strpos( $html, 'Доставка' ), 'Разметка должна содержать строку доставки.' );
	velotok_delivery_order_total_assert( false !== strpos( $html, 'Итого' ), 'Разметка должна содержать строку итога.' );
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
	velotok_delivery_order_total_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт стоимости доставки в итоговой сумме заказа на месте.' . PHP_EOL;
