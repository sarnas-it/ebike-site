<?php
/**
 * Contract test for the mocked delivery options stub provider.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-options-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/delivery-options-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer exposes delivery options, tariffs and order persistence.
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

	velotok_delivery_options_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_delivery_options_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_delivery_options_assert( isset( $contract['spec'] ) && 'clarify-a8fe76a0' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_delivery_options_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_delivery_options_assert( isset( $contract['provider_contract']['mocked'] ) && true === $contract['provider_contract']['mocked'], 'Stub-провайдер не помечен mocked: true.' );
	velotok_delivery_options_assert( isset( $contract['provider_contract']['network'] ) && false === $contract['provider_contract']['network'], 'Stub-провайдер не должен ходить в сеть.' );
	velotok_delivery_options_assert( ! empty( $contract['open_questions'] ) && is_array( $contract['open_questions'] ), 'Не зафиксированы открытые вопросы.' );
	velotok_delivery_options_assert( isset( $contract['tariffs'] ) && is_array( $contract['tariffs'] ), 'Не описана тарифная сетка.' );
	velotok_delivery_options_assert( isset( $contract['order_fields']['method'], $contract['order_fields']['cost'] ), 'Не описаны поля заказа для сохранения доставки.' );
}

if ( function_exists( 'velotok_core_delivery_options' ) ) {
	$options = velotok_core_delivery_options();
	$labels  = array();
	$ids     = array();

	velotok_delivery_options_assert( is_array( $options ), 'Список способов доставки должен быть массивом.' );
	velotok_delivery_options_assert( 3 === count( $options ), 'Stub-провайдер должен вернуть три способа доставки.' );

	foreach ( $options as $option ) {
		velotok_delivery_options_assert( isset( $option['id'], $option['label'], $option['tariff'] ), 'У способа доставки должны быть id, label и tariff.' );
		velotok_delivery_options_assert( isset( $option['mocked'] ) && true === $option['mocked'], 'Способ доставки не помечен mocked: true.' );
		velotok_delivery_options_assert( isset( $option['provider'] ) && 'stub' === $option['provider'], 'Способ доставки должен приходить из stub-провайдера.' );
		velotok_delivery_options_assert( isset( $option['available'] ) && true === $option['available'], 'Способ доставки должен быть доступен.' );
		velotok_delivery_options_assert( isset( $option['tariff']['base'], $option['tariff']['per_kg'], $option['tariff']['currency'] ), 'В тарифе должны быть base, per_kg и currency.' );
		$labels[] = $option['label'];
		$ids[]    = $option['id'];
	}

	foreach ( array( 'Почта РФ', 'СДЭК', 'Иной логист' ) as $label ) {
		velotok_delivery_options_assert( in_array( $label, $labels, true ), sprintf( 'Не найден способ доставки «%s».', $label ) );
	}

	foreach ( array( 'russian_post', 'cdek', 'other' ) as $id ) {
		velotok_delivery_options_assert( in_array( $id, $ids, true ), sprintf( 'Не найден идентификатор способа доставки «%s».', $id ) );
	}
}

if ( function_exists( 'velotok_core_delivery_option' ) ) {
	velotok_delivery_options_assert( 'Почта РФ' === velotok_core_delivery_option( 'russian_post' )['label'], 'Способ доставки Почта РФ разрешается неверно.' );
	velotok_delivery_options_assert( 'СДЭК' === velotok_core_delivery_option( 'cdek' )['label'], 'Способ доставки СДЭК разрешается неверно.' );
	velotok_delivery_options_assert( 'Иной логист' === velotok_core_delivery_option( 'other' )['label'], 'Способ доставки Иной логист разрешается неверно.' );
	velotok_delivery_options_assert( 'cdek' === velotok_core_delivery_option( 'СДЭК' )['id'], 'Способ доставки должен разрешаться по названию.' );
	velotok_delivery_options_assert( true === velotok_core_delivery_option_is_valid( 'cdek' ), 'Известный способ доставки должен быть валидным.' );
	velotok_delivery_options_assert( false === velotok_core_delivery_option_is_valid( 'teleport' ), 'Неизвестный способ доставки не должен быть валидным.' );

	$missing = velotok_core_validate_delivery_option( '' );
	velotok_delivery_options_assert( false === $missing['valid'] && 'DELIVERY_METHOD_REQUIRED' === $missing['code'], 'Пустой способ доставки должен давать DELIVERY_METHOD_REQUIRED.' );

	$invalid = velotok_core_validate_delivery_option( 'teleport' );
	velotok_delivery_options_assert( false === $invalid['valid'] && 'DELIVERY_METHOD_INVALID' === $invalid['code'], 'Неизвестный способ доставки должен давать DELIVERY_METHOD_INVALID.' );

	$valid = velotok_core_validate_delivery_option( 'cdek' );
	velotok_delivery_options_assert( true === $valid['valid'] && 'cdek' === $valid['method'], 'Известный способ доставки должен проходить проверку.' );
}

if ( function_exists( 'velotok_core_calculate_delivery_cost' ) ) {
	$tariffs = velotok_core_delivery_tariffs();
	velotok_delivery_options_assert( isset( $tariffs['cdek'] ), 'В тарифной сетке нет СДЭК.' );

	$base = velotok_core_calculate_delivery_cost( 'cdek' );
	velotok_delivery_options_assert( true === $base['valid'] && true === $base['mocked'], 'Расчёт доставки должен быть mocked-результатом.' );
	velotok_delivery_options_assert( 490.0 === (float) $base['amount'], 'Базовая стоимость СДЭК должна браться из тарифной сетки.' );

	$weighted = velotok_core_calculate_delivery_cost( 'cdek', array( 'subtotal' => 10000, 'weight' => 2.1 ) );
	velotok_delivery_options_assert( 850.0 === (float) $weighted['amount'], 'Стоимость должна учитывать base + per_kg и округление веса вверх.' );
	velotok_delivery_options_assert( 3.0 === (float) $weighted['billable_weight'], 'Вес для тарифа должен округляться вверх до целого килограмма.' );

	$free = velotok_core_calculate_delivery_cost( 'cdek', array( 'subtotal' => 70000, 'weight' => 2 ) );
	velotok_delivery_options_assert( true === $free['free'] && 0.0 === (float) $free['amount'], 'При достижении порога доставка должна быть бесплатной.' );

	$manual = velotok_core_calculate_delivery_cost( 'other', array( 'subtotal' => 100000, 'weight' => 1 ) );
	velotok_delivery_options_assert( 740.0 === (float) $manual['amount'], 'Тариф «Иной логист» должен применяться из сетки.' );

	$invalid = velotok_core_calculate_delivery_cost( 'teleport' );
	velotok_delivery_options_assert( false === $invalid['valid'] && 'DELIVERY_METHOD_INVALID' === $invalid['code'], 'Неизвестный способ должен давать ошибку расчёта.' );
}

if ( function_exists( 'velotok_core_save_delivery_selection' ) ) {
	$order = array(
		'id'       => 128,
		'subtotal' => 10000,
		'weight'   => 2,
	);

	$saved = velotok_core_save_delivery_selection( $order, 'cdek' );

	velotok_delivery_options_assert( true === $saved['saved'], 'Выбранная доставка должна помечаться сохранённой.' );
	velotok_delivery_options_assert( 'cdek' === $saved['delivery_method'], 'В заказе должен сохраняться способ доставки.' );
	velotok_delivery_options_assert( 730.0 === (float) $saved['delivery_cost'], 'В заказе должна сохраняться стоимость доставки.' );
	velotok_delivery_options_assert( 'cdek' === $saved['shipping_method'], 'Способ доставки должен сохраняться в shipping_method.' );
	velotok_delivery_options_assert( 730.0 === (float) $saved['shipping_cost'], 'Стоимость должна сохраняться в shipping_cost.' );
	velotok_delivery_options_assert( 'RUB' === $saved['delivery_currency'], 'В заказе должна сохраняться валюта доставки.' );
	velotok_delivery_options_assert( 'cdek' === $saved['delivery_meta']['_velotok_delivery_method'], 'Способ доставки должен попадать в meta заказа.' );
	velotok_delivery_options_assert( 730.0 === (float) $saved['delivery_meta']['_velotok_delivery_cost'], 'Стоимость должна попадать в meta заказа.' );
	velotok_delivery_options_assert( 128 === $saved['id'], 'Сохранение доставки не должно терять данные заказа.' );

	$invalid = velotok_core_save_delivery_selection( $order, 'teleport' );
	velotok_delivery_options_assert( false === $invalid['valid'] && false === $invalid['saved'], 'Неизвестный способ доставки не должен сохраняться.' );

	$explicit = velotok_core_save_delivery_selection( $order, 'russian_post', 123.45 );
	velotok_delivery_options_assert( 123.45 === (float) $explicit['delivery_cost'], 'Явно переданная стоимость должна сохраняться.' );
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
	velotok_delivery_options_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт способов доставки, тарифной сетки и сохранения в заказе на месте.' . PHP_EOL;
