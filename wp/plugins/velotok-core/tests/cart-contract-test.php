<?php
/**
 * Contract test for the mocked shopping cart stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/cart-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/cart-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer exposes the cart operations, totals and guest transfer
 * rules required by the checkout flow.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/cart.php';

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки корзины.' . PHP_EOL );
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
function velotok_cart_contract_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_core_cart_contract' ) ) {
	velotok_cart_contract_assert( false, 'Отсутствует контракт корзины.' );
} else {
	$contract = velotok_core_cart_contract();

	velotok_cart_contract_assert( is_array( $contract ), 'Контракт корзины должен быть массивом.' );
	velotok_cart_contract_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_cart_contract_assert( isset( $contract['spec'] ) && 'ecommerce-store' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_cart_contract_assert( isset( $contract['requirement'] ) && 'TESTEB-151' === $contract['requirement'], 'Контракт ссылается на неверное требование.' );
	velotok_cart_contract_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_cart_contract_assert( isset( $contract['parent_spec'] ) && 'ecommerce-store' === $contract['parent_spec'], 'Не зафиксирована родительская спецификация.' );
	velotok_cart_contract_assert( isset( $contract['cart_type'] ) && 'shopping_cart' === $contract['cart_type'], 'Неверный тип корзины.' );
	velotok_cart_contract_assert( isset( $contract['operation'] ) && 'provide_cart_for_checkout' === $contract['operation'], 'Неверная операция корзины.' );
	velotok_cart_contract_assert( isset( $contract['currency'] ) && 'RUB' === $contract['currency'], 'Валюта корзины должна быть RUB.' );
	velotok_cart_contract_assert( isset( $contract['network'] ) && false === $contract['network'], 'Заглушка не должна ходить в сеть.' );
	velotok_cart_contract_assert( isset( $contract['persistent'] ) && false === $contract['persistent'], 'Заглушка не должна сохранять состояние.' );
	velotok_cart_contract_assert( isset( $contract['storage']['mocked'] ) && true === $contract['storage']['mocked'], 'Хранилище корзины не помечено mocked.' );
	velotok_cart_contract_assert( isset( $contract['storage']['persistent'] ) && false === $contract['storage']['persistent'], 'Хранилище корзины не должно быть постоянным.' );

	foreach ( array( 'key', 'product_id', 'name', 'price', 'quantity', 'line_total' ) as $field ) {
		velotok_cart_contract_assert( isset( $contract['item_fields'][ $field ] ), sprintf( 'Не описано поле позиции корзины «%s».', $field ) );
		velotok_cart_contract_assert(
			isset( $contract['item_fields'][ $field ]['mocked'] ) && true === $contract['item_fields'][ $field ]['mocked'],
			sprintf( 'Поле позиции корзины «%s» не помечено mocked: true.', $field )
		);
	}

	foreach ( array( 'product_id', 'name', 'price', 'quantity' ) as $field ) {
		velotok_cart_contract_assert(
			isset( $contract['required_fields'] ) && in_array( $field, $contract['required_fields'], true ),
			sprintf( 'Не задано обязательное поле корзины «%s».', $field )
		);
	}

	foreach ( array( 'add_item', 'update_item', 'remove_item', 'get_items', 'calculate_totals', 'clear', 'transfer_guest_cart' ) as $operation ) {
		velotok_cart_contract_assert( isset( $contract['operations'][ $operation ] ), sprintf( 'Не описана операция корзины «%s».', $operation ) );
	}

	velotok_cart_contract_assert( isset( $contract['guest_cart']['enabled'] ) && true === $contract['guest_cart']['enabled'], 'Гостевая корзина должна быть включена.' );
	velotok_cart_contract_assert( isset( $contract['guest_cart']['mocked'] ) && true === $contract['guest_cart']['mocked'], 'Гостевая корзина не помечена mocked: true.' );
	velotok_cart_contract_assert( isset( $contract['guest_cart']['transfer_on_registration'] ) && true === $contract['guest_cart']['transfer_on_registration'], 'Не описан перенос гостевой корзины при регистрации.' );
	velotok_cart_contract_assert( isset( $contract['guest_cart']['merge_strategy'] ) && 'merge_quantities' === $contract['guest_cart']['merge_strategy'], 'Неверная стратегия слияния гостевой корзины.' );
	velotok_cart_contract_assert( isset( $contract['totals']['strategy'] ) && 'sum_price_times_quantity' === $contract['totals']['strategy'], 'Неверная стратегия расчёта итогов корзины.' );
	velotok_cart_contract_assert( isset( $contract['checkout']['enabled'] ) && true === $contract['checkout']['enabled'], 'Корзина должна поддерживать оформление заказа.' );
	velotok_cart_contract_assert( isset( $contract['open_questions']['guest_cart'] ) && false !== strpos( $contract['open_questions']['guest_cart'], 'clarify-30af92ca' ), 'Не зафиксирован открытый вопрос о гостевой корзине.' );

	foreach ( array( 'CART_EMPTY', 'CART_PRODUCT_NOT_FOUND', 'CART_ITEM_NOT_FOUND', 'CART_QUANTITY_INVALID' ) as $code ) {
		velotok_cart_contract_assert(
			isset( $contract['error_codes'] ) && in_array( $code, $contract['error_codes'], true ),
			sprintf( 'Не задан код ошибки корзины «%s».', $code )
		);
	}
}

if ( function_exists( 'velotok_core_cart_create' ) ) {
	$cart = velotok_core_cart_create();

	velotok_cart_contract_assert( is_array( $cart ) && isset( $cart['items'] ), 'Пустая корзина должна содержать список items.' );
	velotok_cart_contract_assert( true === $cart['mocked'] && 'stub' === $cart['source'], 'Корзина должна быть помечена mocked и stub.' );
	velotok_cart_contract_assert( true === $cart['empty'], 'Новая корзина должна быть пустой.' );
	velotok_cart_contract_assert( 0 === (int) $cart['item_count'], 'Количество товаров в новой корзине должно быть нулевым.' );
	velotok_cart_contract_assert( 0.0 === (float) $cart['subtotal'], 'Подытог новой корзины должен быть нулевым.' );
}

if ( function_exists( 'velotok_core_cart_add_item' ) ) {
	$cart = velotok_core_cart_add_item( 'riviera', 2 );

	velotok_cart_contract_assert( true === $cart['valid'] && true === $cart['added'], 'Товар каталога должен добавляться в корзину.' );
	velotok_cart_contract_assert( 1 === count( velotok_core_cart_items( $cart ) ), 'После добавления в корзине должна быть одна позиция.' );
	velotok_cart_contract_assert( 2 === velotok_core_cart_count( $cart ), 'Количество должно учитывать две единицы товара.' );
	velotok_cart_contract_assert( 125800.0 === (float) velotok_core_cart_subtotal( $cart ), 'Подытог должен считаться как цена × количество.' );
	velotok_cart_contract_assert( 125800.0 === (float) velotok_core_cart_total( $cart ), 'Итог корзины до доставки равен подытогу.' );

	$item = velotok_core_cart_get_item( $cart, 'riviera' );
	velotok_cart_contract_assert( is_array( $item ) && 'Ривьера' === $item['name'], 'Позиция корзины должна содержать название товара.' );
	velotok_cart_contract_assert( true === $item['mocked'], 'Позиция корзины должна быть помечена mocked: true.' );
	velotok_cart_contract_assert( 125800.0 === (float) $item['line_total'], 'Сумма позиции должна считаться как цена × количество.' );

	$cart = velotok_core_cart_add_item( $cart, 'riviera', 1 );
	velotok_cart_contract_assert( 3 === velotok_core_cart_count( $cart ), 'Повторное добавление товара должно суммировать количество.' );
	velotok_cart_contract_assert( 1 === velotok_core_cart_unique_count( $cart ), 'Повторное добавление не должно плодить позиции.' );

	$cart = velotok_core_cart_add_item( $cart, 'street-7', 1 );
	velotok_cart_contract_assert( 2 === velotok_core_cart_unique_count( $cart ), 'Разные товары должны давать разные позиции.' );

	$cart = velotok_core_cart_update_item( $cart, 'riviera', 1 );
	velotok_cart_contract_assert( true === $cart['updated'], 'Количество позиции должно обновляться.' );
	velotok_cart_contract_assert( 2 === velotok_core_cart_count( $cart ), 'Счётчик должен измениться после обновления количества.' );

	$cart = velotok_core_cart_remove_item( $cart, 'street-7' );
	velotok_cart_contract_assert( true === $cart['removed'], 'Позиция должна удаляться из корзины.' );
	velotok_cart_contract_assert( 1 === velotok_core_cart_unique_count( $cart ), 'После удаления должна остаться одна позиция.' );

	$missing = velotok_core_cart_remove_item( $cart, 'unknown-product' );
	velotok_cart_contract_assert( false === $missing['valid'] && 'CART_ITEM_NOT_FOUND' === $missing['code'], 'Удаление неизвестной позиции должно давать CART_ITEM_NOT_FOUND.' );

	$unknown = velotok_core_cart_add_item( 'unknown-product', 1 );
	velotok_cart_contract_assert( false === $unknown['valid'] && 'CART_PRODUCT_NOT_FOUND' === $unknown['code'], 'Неизвестный товар должен давать CART_PRODUCT_NOT_FOUND.' );

	$invalid = velotok_core_cart_add_item( 'riviera', 0 );
	velotok_cart_contract_assert( false === $invalid['valid'] && 'CART_QUANTITY_INVALID' === $invalid['code'], 'Нулевое количество должно давать CART_QUANTITY_INVALID.' );

	$cleared = velotok_core_cart_clear( $cart );
	velotok_cart_contract_assert( true === $cleared['cleared'] && true === velotok_core_cart_is_empty( $cleared ), 'Очищенная корзина должна быть пустой.' );
	velotok_cart_contract_assert( 'CART_EMPTY' === velotok_core_cart_contract()['empty_error'], 'Код пустой корзины должен быть задан в контракте.' );
}

if ( function_exists( 'velotok_core_cart_totals' ) ) {
	$cart   = velotok_core_cart_add_item( 'hrebet-pro', 1 );
	$totals = velotok_core_cart_totals( $cart );

	velotok_cart_contract_assert( true === $totals['valid'] && true === $totals['mocked'], 'Сводка корзины должна быть валидной и mocked.' );
	velotok_cart_contract_assert( 1 === (int) $totals['item_count'], 'Сводка должна содержать количество товаров.' );
	velotok_cart_contract_assert( 149900.0 === (float) $totals['total'], 'Итог сводки должен совпадать с ценой товара.' );
	velotok_cart_contract_assert( 'RUB' === $totals['currency'], 'Валюта сводки должна быть RUB.' );
	velotok_cart_contract_assert( false === $totals['include_delivery'], 'Доставка добавляется на этапе оформления заказа.' );
}

if ( function_exists( 'velotok_core_cart_guest' ) && function_exists( 'velotok_core_cart_transfer_guest_cart' ) ) {
	$guest = velotok_core_cart_guest();
	$guest = velotok_core_cart_add_item( $guest, 'riviera', 1 );

	velotok_cart_contract_assert( true === $guest['guest'], 'Гостевая корзина должна быть помечена guest.' );

	$user = velotok_core_cart_create();
	$user = velotok_core_cart_add_item( $user, 'riviera', 2 );

	$merged = velotok_core_cart_transfer_guest_cart( $guest, $user );

	velotok_cart_contract_assert( true === $merged['guest_cart_transferred'], 'Гостевая корзина должна переноситься.' );
	velotok_cart_contract_assert( 1 === (int) $merged['transferred'], 'Количество перенесённых позиций должно учитываться.' );
	velotok_cart_contract_assert( 3 === velotok_core_cart_count( $merged ), 'При переносе количество одинаковых товаров должно суммироваться.' );
	velotok_cart_contract_assert( 'merge_quantities' === $merged['transfer_strategy'], 'Стратегия переноса должна браться из контракта.' );
}

if ( function_exists( 'velotok_core_render_cart' ) ) {
	$html = velotok_core_render_cart( velotok_core_cart_add_item( 'riviera', 1 ) );

	velotok_cart_contract_assert( is_string( $html ) && '' !== $html, 'Разметка корзины не должна быть пустой.' );
	velotok_cart_contract_assert( false !== strpos( $html, 'data-mocked="true"' ), 'Разметка корзины должна быть помечена как mocked.' );
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
	'$' . 'wpdb',
);

foreach ( $forbidden as $needle ) {
	velotok_cart_contract_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт корзины для оформления заказа на месте.' . PHP_EOL;
