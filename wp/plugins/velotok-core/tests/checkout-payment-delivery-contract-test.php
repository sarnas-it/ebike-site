<?php
/**
 * Contract test for the mocked checkout, delivery and payment stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/checkout-payment-delivery-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/checkout-payment-delivery-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer exposes the required checkout rules.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/checkout-payment-delivery.php';

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки оплаты, доставки и оформления заказа.' . PHP_EOL );
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
function velotok_checkout_contract_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_core_checkout_contract' ) ) {
	velotok_checkout_contract_assert( false, 'Отсутствует контракт оформления заказа, доставки и оплаты.' );
} else {
	$contract = velotok_core_checkout_contract();

	velotok_checkout_contract_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_checkout_contract_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_checkout_contract_assert( isset( $contract['spec'] ) && 'clarify-096bb945' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_checkout_contract_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_checkout_contract_assert( ! empty( $contract['open_questions'] ) && is_array( $contract['open_questions'] ), 'Не зафиксированы открытые вопросы по интеграциям.' );

	velotok_checkout_contract_assert( isset( $contract['payment'] ) && is_array( $contract['payment'] ), 'Не описан платёжный контракт.' );
	velotok_checkout_contract_assert( isset( $contract['payment']['provider'] ) && 'yookassa' === $contract['payment']['provider'], 'Платёжным провайдером должна быть ЮKassa.' );
	velotok_checkout_contract_assert( isset( $contract['payment']['provider_name'] ) && 'ЮKassa' === $contract['payment']['provider_name'], 'Не зафиксировано название платёжного провайдера ЮKassa.' );
	velotok_checkout_contract_assert( isset( $contract['payment']['mocked'] ) && true === $contract['payment']['mocked'], 'Платёжный контракт не помечен mocked: true.' );
	velotok_checkout_contract_assert( isset( $contract['payment']['future_source'] ) && 'env' === $contract['payment']['future_source'], 'Не зафиксировано направление на env для ЮKassa.' );
	velotok_checkout_contract_assert( ! empty( $contract['payment']['env'] ) && is_array( $contract['payment']['env'] ), 'Не задана карта env-переменных ЮKassa.' );
	velotok_checkout_contract_assert( in_array( 'test', $contract['payment']['supported_modes'], true ) && in_array( 'prod', $contract['payment']['supported_modes'], true ), 'Не зафиксировано разделение test/prod для ЮKassa.' );

	velotok_checkout_contract_assert( isset( $contract['delivery']['methods'] ) && is_array( $contract['delivery']['methods'] ), 'Не описан контракт способов доставки.' );
	velotok_checkout_contract_assert( 3 === count( $contract['delivery']['methods'] ), 'В контракте должно быть три способа доставки.' );

	$labels = array();

	foreach ( $contract['delivery']['methods'] as $method ) {
		velotok_checkout_contract_assert( isset( $method['id'], $method['label'] ), 'У способа доставки должны быть id и label.' );
		velotok_checkout_contract_assert( isset( $method['mocked'] ) && true === $method['mocked'], 'Способ доставки не помечен mocked: true.' );
		velotok_checkout_contract_assert( isset( $method['available'] ) && true === $method['available'], 'Способ доставки должен быть доступен.' );
		$labels[] = $method['label'];
	}

	foreach ( array( 'Почта РФ', 'СДЭК', 'Иной логист' ) as $label ) {
		velotok_checkout_contract_assert( in_array( $label, $labels, true ), sprintf( 'Не найден способ доставки «%s».', $label ) );
	}

	velotok_checkout_contract_assert( isset( $contract['content_acceptance']['required'] ) && true === $contract['content_acceptance']['required'], 'Приёмка контента должна быть обязательной.' );
	velotok_checkout_contract_assert( isset( $contract['content_acceptance']['role'] ) && 'project_manager' === $contract['content_acceptance']['role'], 'Приёмку контента должен выполнять руководитель проекта.' );
	velotok_checkout_contract_assert( isset( $contract['content_acceptance']['role_label'] ) && 'руководитель проекта' === $contract['content_acceptance']['role_label'], 'Не зафиксировано название роли руководителя проекта.' );
	velotok_checkout_contract_assert( isset( $contract['content_acceptance']['enforced_by'] ) && 'human' === $contract['content_acceptance']['enforced_by'], 'Приёмка контента должна выполняться человеком.' );
}

if ( function_exists( 'velotok_core_payment_provider' ) ) {
	velotok_checkout_contract_assert( 'yookassa' === velotok_core_payment_provider(), 'Payment provider accessor вернул неверное значение.' );
	velotok_checkout_contract_assert( 'ЮKassa' === velotok_core_payment_provider_label(), 'Payment provider label вернул неверное значение.' );
	velotok_checkout_contract_assert( true === velotok_core_payment_is_mocked(), 'Оплата должна оставаться mocked.' );
	velotok_checkout_contract_assert( 'test' === velotok_core_payment_mode(), 'Заглушка должна работать в тестовом режиме.' );
	velotok_checkout_contract_assert( 'VELOTOK_YOOKASSA_SHOP_ID' === velotok_core_payment_env_name( 'shop_id' ), 'Неверное имя env-переменной shop_id.' );
	velotok_checkout_contract_assert( 'VELOTOK_YOOKASSA_SECRET_KEY' === velotok_core_payment_env_name( 'secret_key' ), 'Неверное имя env-переменной secret_key.' );
	velotok_checkout_contract_assert( 'VELOTOK_YOOKASSA_MODE' === velotok_core_payment_env_name( 'mode' ), 'Неверное имя env-переменной mode.' );
	velotok_checkout_contract_assert( '' === velotok_core_payment_env_name( 'unknown_key' ), 'Неизвестный ключ не должен получать env-имя.' );

	$intent = velotok_core_create_payment_intent( array( 'id' => 42, 'total' => 62900.5 ) );
	velotok_checkout_contract_assert( is_array( $intent ), 'Платёжное намерение должно быть массивом.' );
	velotok_checkout_contract_assert( isset( $intent['mocked'] ) && true === $intent['mocked'], 'Платёжное намерение не помечено mocked: true.' );
	velotok_checkout_contract_assert( isset( $intent['provider'] ) && 'yookassa' === $intent['provider'], 'Платёжное намерение должно создаваться для ЮKassa.' );
	velotok_checkout_contract_assert( isset( $intent['status'] ) && 'pending' === $intent['status'], 'Заглушка должна возвращать статус pending.' );
	velotok_checkout_contract_assert( isset( $intent['amount']['value'] ) && 62900.5 === (float) $intent['amount']['value'], 'Сумма платежа должна браться из заказа.' );
	velotok_checkout_contract_assert( isset( $intent['amount']['currency'] ) && 'RUB' === $intent['amount']['currency'], 'Валюта платежа должна быть RUB.' );
	velotok_checkout_contract_assert( false === $intent['paid'], 'Заглушка не должна считать заказ оплаченным без реального провайдера.' );
}

if ( function_exists( 'velotok_core_delivery_methods' ) ) {
	$methods = velotok_core_delivery_methods();

	velotok_checkout_contract_assert( isset( $methods['russian_post'] ), 'Не найден способ доставки Почта РФ.' );
	velotok_checkout_contract_assert( isset( $methods['cdek'] ), 'Не найден способ доставки СДЭК.' );
	velotok_checkout_contract_assert( isset( $methods['other'] ), 'Не найден способ доставки «Иной логист».' );

	$tariffs = velotok_core_delivery_tariffs();
	velotok_checkout_contract_assert( isset( $tariffs['russian_post'], $tariffs['cdek'], $tariffs['other'] ), 'Не задана тарифная сетка для всех способов доставки.' );

	foreach ( $tariffs as $tariff ) {
		velotok_checkout_contract_assert( isset( $tariff['mocked'] ) && true === $tariff['mocked'], 'Тариф не помечен mocked: true.' );
		velotok_checkout_contract_assert( ! empty( $tariff['tiers'] ) && is_array( $tariff['tiers'] ), 'У тарифа не заданы уровни.' );
	}

	velotok_checkout_contract_assert( 'Почта РФ' === velotok_core_delivery_method( 'russian_post' )['label'], 'Способ доставки Почта РФ разрешается неверно.' );
	velotok_checkout_contract_assert( 'СДЭК' === velotok_core_delivery_method( 'cdek' )['label'], 'Способ доставки СДЭК разрешается неверно.' );
	velotok_checkout_contract_assert( 'Иной логист' === velotok_core_delivery_method( 'other' )['label'], 'Способ доставки Иной логист разрешается неверно.' );

	velotok_checkout_contract_assert( true === velotok_core_delivery_method_is_valid( 'cdek' ), 'Известный способ доставки должен быть валидным.' );
	velotok_checkout_contract_assert( false === velotok_core_delivery_method_is_valid( 'unknown' ), 'Неизвестный способ доставки не должен быть валидным.' );

	$missing = velotok_core_validate_delivery_method( '' );
	velotok_checkout_contract_assert( false === $missing['valid'] && 'DELIVERY_METHOD_REQUIRED' === $missing['code'], 'Пустой способ доставки должен давать DELIVERY_METHOD_REQUIRED.' );

	$invalid = velotok_core_validate_delivery_method( 'teleport' );
	velotok_checkout_contract_assert( false === $invalid['valid'] && 'DELIVERY_METHOD_INVALID' === $invalid['code'], 'Неизвестный способ доставки должен давать DELIVERY_METHOD_INVALID.' );

	$valid = velotok_core_validate_delivery_method( 'cdek' );
	velotok_checkout_contract_assert( true === $valid['valid'] && 'cdek' === $valid['method'], 'Известный способ доставки должен проходить проверку.' );

	$valid_label = velotok_core_validate_delivery_method( 'СДЭК' );
	velotok_checkout_contract_assert( true === $valid_label['valid'] && 'cdek' === $valid_label['method'], 'Способ доставки по названию «СДЭК» должен проходить проверку.' );

	$valid_lower = velotok_core_validate_delivery_method( 'сдэк' );
	velotok_checkout_contract_assert( true === $valid_lower['valid'] && 'cdek' === $valid_lower['method'], 'Способ доставки в нижнем регистре «сдэк» должен проходить проверку.' );

	$valid_post = velotok_core_validate_delivery_method( 'Почта России' );
	velotok_checkout_contract_assert( true === $valid_post['valid'] && 'russian_post' === $valid_post['method'], 'Способ доставки «Почта России» должен разрешаться в russian_post.' );

	$cost = velotok_core_delivery_cost( 'cdek', array( 'total' => 10000 ) );
	velotok_checkout_contract_assert( true === $cost['valid'] && true === $cost['mocked'], 'Стоимость доставки должна возвращаться как mocked-заглушка.' );
	velotok_checkout_contract_assert( true === $cost['calculated'], 'Стоимость доставки должна рассчитываться по тарифной сетке.' );
	velotok_checkout_contract_assert( 'stub' === $cost['source'] && 'stub' === $cost['provider'], 'Источником стоимости должна быть тарифная сетка заглушки.' );
	velotok_checkout_contract_assert( 490.0 === (float) $cost['amount'], 'Для заказа до 20 000 ₽ тариф СДЭК должен быть 490 ₽.' );

	$free_cost = velotok_core_delivery_cost( 'cdek', array( 'total' => 60000 ) );
	velotok_checkout_contract_assert( 0.0 === (float) $free_cost['amount'] && true === $free_cost['free'], 'От порога бесплатной доставки стоимость должна быть нулевой.' );

	$other_cost = velotok_core_delivery_cost( 'other', array( 'total' => 60000 ) );
	velotok_checkout_contract_assert( 590.0 === (float) $other_cost['amount'], 'Для «Иного логиста» должна применяться своя тарифная сетка.' );

	$invalid_cost = velotok_core_delivery_cost( 'teleport', array( 'total' => 10000 ) );
	velotok_checkout_contract_assert( false === $invalid_cost['valid'] && null === $invalid_cost['amount'], 'Для неизвестного способа стоимость не рассчитывается.' );

	$order = velotok_core_save_delivery_option( array( 'id' => 42, 'total' => 10000 ), 'cdek' );
	velotok_checkout_contract_assert( 'cdek' === $order['delivery_method'], 'Выбранный способ доставки должен сохраняться в заказе.' );
	velotok_checkout_contract_assert( 'СДЭК' === $order['delivery_method_label'], 'Название выбранного способа доставки должно сохраняться в заказе.' );
	velotok_checkout_contract_assert( 490.0 === (float) $order['delivery_cost'], 'Стоимость доставки должна сохраняться в заказе.' );
	velotok_checkout_contract_assert( true === $order['delivery_mocked'], 'Сохранение доставки в заказе должно быть помечено mocked.' );
}

if ( function_exists( 'velotok_core_validate_content_acceptance' ) ) {
	velotok_checkout_contract_assert( true === velotok_core_content_acceptance_required(), 'Приёмка контента должна быть обязательной.' );
	velotok_checkout_contract_assert( false === velotok_core_content_accepted( false ), 'Ложное значение приёмки не должно считаться приёмкой.' );
	velotok_checkout_contract_assert( false === velotok_core_content_accepted( '0' ), 'Строка «0» не должна считаться приёмкой.' );
	velotok_checkout_contract_assert( true === velotok_core_content_accepted( true ), 'Истинное значение приёмки должно считаться приёмкой.' );

	$rejected = velotok_core_validate_content_acceptance( false );
	velotok_checkout_contract_assert( false === $rejected['valid'] && 'CONTENT_ACCEPTANCE_REQUIRED' === $rejected['code'], 'Без приёмки должен возвращаться CONTENT_ACCEPTANCE_REQUIRED.' );
	velotok_checkout_contract_assert( 'human' === $rejected['enforced_by'], 'Приёмка должна выполняться человеком.' );

	$accepted = velotok_core_validate_content_acceptance( array( 'accepted' => true, 'role' => 'project_manager' ) );
	velotok_checkout_contract_assert( true === $accepted['valid'], 'Приёмка руководителем проекта должна быть валидной.' );
	velotok_checkout_contract_assert( 'project_manager' === $accepted['role'], 'Роль принявшего должна сохраняться.' );

	$accepted_label = velotok_core_validate_content_acceptance( array( 'accepted' => true, 'role' => 'руководитель проекта' ) );
	velotok_checkout_contract_assert( true === $accepted_label['valid'], 'Приёмка по названию роли должна быть валидной.' );

	$forbidden_role = velotok_core_validate_content_acceptance( array( 'accepted' => true, 'role' => 'customer' ) );
	velotok_checkout_contract_assert( false === $forbidden_role['valid'], 'Приёмка контента другой ролью должна блокироваться.' );
	velotok_checkout_contract_assert( 'CONTENT_ACCEPTANCE_ROLE_FORBIDDEN' === $forbidden_role['code'], 'Для чужой роли должен возвращаться CONTENT_ACCEPTANCE_ROLE_FORBIDDEN.' );
}

if ( function_exists( 'velotok_core_validate_checkout' ) ) {
	$checkout = velotok_core_validate_checkout( array(
		'velotok_delivery_method'  => 'cdek',
		'velotok_content_accepted' => true,
	) );
	velotok_checkout_contract_assert( true === $checkout['valid'], 'Полный набор данных должен проходить проверку оформления заказа.' );
	velotok_checkout_contract_assert( true === $checkout['mocked'], 'Результат проверки оформления заказа должен быть помечен mocked.' );
	velotok_checkout_contract_assert( 'yookassa' === $checkout['payment_provider'], 'Оформление заказа должно использовать ЮKassa.' );

	$checkout_without_acceptance = velotok_core_validate_checkout( array(
		'velotok_delivery_method' => 'cdek',
	) );
	velotok_checkout_contract_assert( false === $checkout_without_acceptance['valid'], 'Без приёмки контента оформление заказа должно блокироваться.' );
	velotok_checkout_contract_assert( in_array( 'CONTENT_ACCEPTANCE_REQUIRED', $checkout_without_acceptance['errors'], true ), 'Должна быть ошибка об отсутствии приёмки контента.' );

	$checkout_without_delivery = velotok_core_validate_checkout( array(
		'velotok_content_accepted' => true,
	) );
	velotok_checkout_contract_assert( false === $checkout_without_delivery['valid'], 'Без способа доставки оформление заказа должно блокироваться.' );
	velotok_checkout_contract_assert( in_array( 'DELIVERY_METHOD_REQUIRED', $checkout_without_delivery['errors'], true ), 'Должна быть ошибка об отсутствии способа доставки.' );

	$checkout_provider_label = velotok_core_validate_checkout( array(
		'velotok_delivery_method'  => 'СДЭК',
		'velotok_content_accepted' => true,
		'payment_provider'         => 'ЮKassa',
	) );
	velotok_checkout_contract_assert( true === $checkout_provider_label['valid'], 'Название платёжной системы «ЮKassa» должно приниматься.' );

	$checkout_unknown_provider = velotok_core_validate_checkout( array(
		'velotok_delivery_method'  => 'cdek',
		'velotok_content_accepted' => true,
		'payment_provider'         => 'stripe',
	) );
	velotok_checkout_contract_assert( false === $checkout_unknown_provider['valid'], 'Неподдерживаемая платёжная система должна блокироваться.' );
	velotok_checkout_contract_assert( in_array( 'PAYMENT_PROVIDER_UNSUPPORTED', $checkout_unknown_provider['errors'], true ), 'Должна быть ошибка о неподдерживаемой платёжной системе.' );
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
	velotok_checkout_contract_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт оформления заказа, доставки и оплаты на месте.' . PHP_EOL;
