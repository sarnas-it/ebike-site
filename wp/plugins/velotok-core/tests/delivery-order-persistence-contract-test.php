<?php
/**
 * Contract test for saving the selected delivery method and cost in an order.
 *
 * Requirement TESTEB-130 / spec clarify-a8fe76a0:
 * «Система SHALL сохранять выбранный способ и стоимость доставки в заказе».
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-order-persistence-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/delivery-order-persistence-contract-test.php
 *
 * The test fails when the mocked persistence helpers are missing, when the
 * selected method/cost are not written to the order or when the stub stops
 * being marked as mocked. It performs no database, file or network access.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$order_delivery_path = dirname( __DIR__ ) . '/includes/order-delivery.php';
$delivery_options_path = dirname( __DIR__ ) . '/includes/delivery-options.php';

if ( ! is_readable( $order_delivery_path ) || ! is_readable( $delivery_options_path ) ) {
	fwrite( STDERR, 'FAIL: Не найдены файлы заглушки сохранения доставки в заказе.' . PHP_EOL );
	exit( 1 );
}

require_once $order_delivery_path;

$failures = array();

/**
 * Assert a persistence contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_delivery_persistence_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Minimal WooCommerce-like order double used by the persistence contract test.
 */
class Velotok_Delivery_Persistence_Test_Order {
	/** @var array<string,mixed> */
	public $meta = array();

	/** @var float|null */
	public $shipping_total = null;

	/** @var string|null */
	public $shipping_method = null;

	/**
	 * Store order meta like WC_Order::update_meta_data().
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 * @return void
	 */
	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	/**
	 * Read order meta like WC_Order::get_meta().
	 *
	 * @param string $key    Meta key.
	 * @param bool   $single Whether to return a single value.
	 * @return mixed
	 */
	public function get_meta( $key, $single = true ) {
		unset( $single );

		return array_key_exists( $key, $this->meta ) ? $this->meta[ $key ] : '';
	}

	/**
	 * Store the shipping total like WC_Order::set_shipping_total().
	 *
	 * @param float $value Shipping total.
	 * @return void
	 */
	public function set_shipping_total( $value ) {
		$this->shipping_total = $value;
	}

	/**
	 * Store the shipping method.
	 *
	 * @param string $method Shipping method.
	 * @return void
	 */
	public function set_shipping_method( $method ) {
		$this->shipping_method = $method;
	}

	/**
	 * Return the shipping total like WC_Order::get_shipping_total().
	 *
	 * @return float|null
	 */
	public function get_shipping_total() {
		return $this->shipping_total;
	}

	/**
	 * Return the shipping method like WC_Order::get_shipping_method().
	 *
	 * @return string|null
	 */
	public function get_shipping_method() {
		return $this->shipping_method;
	}

	/**
	 * Return the order subtotal.
	 *
	 * @return float
	 */
	public function get_subtotal() {
		return 10000.0;
	}

	/**
	 * Return the order total.
	 *
	 * @return float
	 */
	public function get_total() {
		return 10000.0;
	}

	/**
	 * Return the order weight.
	 *
	 * @return float
	 */
	public function get_weight() {
		return 2.0;
	}
}

$contract = function_exists( 'velotok_core_delivery_options_contract' )
	? velotok_core_delivery_options_contract()
	: array();

velotok_delivery_persistence_assert( is_array( $contract ) && ! empty( $contract ), 'Отсутствует контракт способов доставки.' );
velotok_delivery_persistence_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт способов доставки не помечен mocked: true.' );
velotok_delivery_persistence_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Контракт способов доставки должен ссылаться на stub.' );
velotok_delivery_persistence_assert( isset( $contract['order'] ) && is_array( $contract['order'] ), 'Контракт не описывает формат сохранения доставки в заказе.' );
velotok_delivery_persistence_assert( isset( $contract['order']['mocked'] ) && true === $contract['order']['mocked'], 'Сохранение доставки в заказе не помечено mocked: true.' );
velotok_delivery_persistence_assert( isset( $contract['order_fields']['method'], $contract['order_fields']['cost'] ), 'Контракт не описывает поля способа и стоимости доставки в заказе.' );
velotok_delivery_persistence_assert( isset( $contract['order_fields']['meta']['method'], $contract['order_fields']['meta']['cost'] ), 'Контракт не описывает meta-поля способа и стоимости доставки.' );
velotok_delivery_persistence_assert( isset( $contract['persistence']['mocked'] ) && true === $contract['persistence']['mocked'], 'Контракт не помечает сохранение доставки в заказе как mocked: true.' );
velotok_delivery_persistence_assert( isset( $contract['persistence']['enabled'] ) && true === $contract['persistence']['enabled'], 'Сохранение доставки в заказе должно быть включено.' );
velotok_delivery_persistence_assert( isset( $contract['persistence']['writes_database'] ) && false === $contract['persistence']['writes_database'], 'Заглушка не должна писать в базу данных.' );
velotok_delivery_persistence_assert( isset( $contract['order']['persistence']['mocked'] ) && true === $contract['order']['persistence']['mocked'], 'Persistence-блок заказа не помечен mocked: true.' );
velotok_delivery_persistence_assert( isset( $contract['order']['persistence']['enabled'] ) && true === $contract['order']['persistence']['enabled'], 'Persistence-блок заказа должен быть включён.' );

foreach (
	array(
		'velotok_core_delivery_options_apply_to_order',
		'velotok_core_save_delivery_selection',
		'velotok_core_delivery_read_from_order',
		'velotok_core_delivery_order_has_selection',
		'velotok_core_save_checkout_delivery',
		'velotok_core_apply_checkout_delivery',
		'velotok_core_get_checkout_delivery',
		'velotok_core_checkout_delivery_is_saved',
	) as $helper
) {
	velotok_delivery_persistence_assert( function_exists( $helper ), sprintf( 'Отсутствует функция сохранения доставки в заказе: %s.', $helper ) );
}

if ( function_exists( 'velotok_core_save_checkout_delivery' ) ) {
	$order = array( 'id' => 500, 'subtotal' => 10000, 'weight' => 2 );
	$saved = velotok_core_save_checkout_delivery( $order, 'cdek' );

	velotok_delivery_persistence_assert( true === $saved['saved'], 'Выбранная доставка должна помечаться сохранённой в заказе.' );
	velotok_delivery_persistence_assert( true === $saved['mocked'], 'Сохранённая доставка должна быть помечена mocked: true.' );
	velotok_delivery_persistence_assert( 'cdek' === $saved['delivery_method'], 'Способ доставки не сохраняется в заказе.' );
	velotok_delivery_persistence_assert( 'СДЭК' === $saved['delivery_method_label'], 'Название способа доставки не сохраняется в заказе.' );
	velotok_delivery_persistence_assert( 730.0 === (float) $saved['delivery_cost'], 'Стоимость доставки не сохраняется в заказе.' );
	velotok_delivery_persistence_assert( 'RUB' === $saved['delivery_currency'], 'Валюта доставки не сохраняется в заказе.' );
	velotok_delivery_persistence_assert( 'cdek' === $saved['shipping_method'], 'Способ доставки не сохраняется в shipping_method.' );
	velotok_delivery_persistence_assert( 730.0 === (float) $saved['shipping_cost'], 'Стоимость доставки не сохраняется в shipping_cost.' );
	velotok_delivery_persistence_assert( 10730.0 === (float) $saved['order_total'], 'Итог заказа не включает сохранённую стоимость доставки.' );
	velotok_delivery_persistence_assert( 'cdek' === $saved['delivery_meta']['_velotok_delivery_method'], 'Способ доставки не попадает в meta заказа.' );
	velotok_delivery_persistence_assert( 730.0 === (float) $saved['delivery_meta']['_velotok_delivery_cost'], 'Стоимость доставки не попадает в meta заказа.' );
	velotok_delivery_persistence_assert( 500 === $saved['id'], 'Сохранение доставки не должно терять данные заказа.' );

	if ( function_exists( 'velotok_core_get_checkout_delivery' ) ) {
		$stored = velotok_core_get_checkout_delivery( $saved );

		velotok_delivery_persistence_assert( true === $stored['valid'] && true === $stored['saved'], 'Сохранённая доставка должна читаться обратно из заказа.' );
		velotok_delivery_persistence_assert( true === $stored['mocked'], 'Прочитанная доставка должна быть помечена mocked: true.' );
		velotok_delivery_persistence_assert( 'cdek' === $stored['delivery_method'], 'Прочитанный способ доставки не совпадает с сохранённым.' );
		velotok_delivery_persistence_assert( 'СДЭК' === $stored['delivery_method_label'], 'Прочитанное название способа доставки не совпадает с сохранённым.' );
		velotok_delivery_persistence_assert( 730.0 === (float) $stored['delivery_cost'], 'Прочитанная стоимость доставки не совпадает с сохранённой.' );
		velotok_delivery_persistence_assert( 'RUB' === $stored['delivery_currency'], 'Прочитанная валюта доставки не совпадает с сохранённой.' );
		velotok_delivery_persistence_assert( 'cdek' === $stored['meta']['_velotok_delivery_method'], 'Meta способа доставки не читается из заказа.' );
		velotok_delivery_persistence_assert( 730.0 === (float) $stored['meta']['_velotok_delivery_cost'], 'Meta стоимости доставки не читается из заказа.' );
		velotok_delivery_persistence_assert( true === velotok_core_checkout_delivery_is_saved( $saved ), 'Заказ с сохранённой доставкой должен определяться как имеющий выбор.' );

		$empty = velotok_core_get_checkout_delivery( array( 'id' => 503 ) );
		velotok_delivery_persistence_assert( false === $empty['valid'] && false === $empty['saved'], 'Заказ без доставки не должен возвращать сохранённый выбор.' );
		velotok_delivery_persistence_assert( 'DELIVERY_METHOD_REQUIRED' === $empty['code'], 'Пустой заказ должен давать DELIVERY_METHOD_REQUIRED.' );
		velotok_delivery_persistence_assert( false === velotok_core_checkout_delivery_is_saved( array( 'id' => 503 ) ), 'Заказ без доставки не должен помечаться как сохранённый.' );
	}

	$invalid = velotok_core_save_checkout_delivery( $order, 'teleport' );
	velotok_delivery_persistence_assert( false === $invalid['valid'] && false === $invalid['saved'], 'Неизвестный способ доставки не должен сохраняться в заказе.' );
}

if ( function_exists( 'velotok_core_save_delivery_selection' ) ) {
	$order = array( 'id' => 501, 'subtotal' => 10000, 'weight' => 2 );
	$saved = velotok_core_save_delivery_selection( $order, 'russian_post' );

	velotok_delivery_persistence_assert( true === $saved['saved'], 'Способ и стоимость доставки должны помечаться сохранёнными.' );
	velotok_delivery_persistence_assert( 'russian_post' === $saved['delivery_method'], 'Способ доставки не сохраняется через save_delivery_selection.' );
	velotok_delivery_persistence_assert( 550.0 === (float) $saved['delivery_cost'], 'Стоимость доставки не сохраняется через save_delivery_selection.' );
	velotok_delivery_persistence_assert( 'russian_post' === $saved['delivery_meta']['_velotok_delivery_method'], 'Способ доставки не попадает в meta при save_delivery_selection.' );
	velotok_delivery_persistence_assert( 550.0 === (float) $saved['delivery_meta']['_velotok_delivery_cost'], 'Стоимость не попадает в meta при save_delivery_selection.' );

	$override = velotok_core_save_delivery_selection( $order, 'cdek', 123.45 );
	velotok_delivery_persistence_assert( 123.45 === (float) $override['delivery_cost'], 'Явно переданная стоимость доставки должна сохраняться.' );
	velotok_delivery_persistence_assert( 123.45 === (float) $override['delivery_meta']['_velotok_delivery_cost'], 'Явно переданная стоимость должна попадать в meta.' );
}

if ( function_exists( 'velotok_core_save_checkout_delivery' ) ) {
	$order = new Velotok_Delivery_Persistence_Test_Order();
	$saved = velotok_core_save_checkout_delivery( $order, 'cdek' );

	velotok_delivery_persistence_assert( true === $saved['saved'], 'Сохранение доставки в объект заказа должно быть успешным.' );
	velotok_delivery_persistence_assert( isset( $order->meta['_velotok_delivery_method'] ) && 'cdek' === $order->meta['_velotok_delivery_method'], 'Способ доставки не записывается в meta объекта заказа.' );
	velotok_delivery_persistence_assert( isset( $order->meta['_velotok_delivery_cost'] ) && 730.0 === (float) $order->meta['_velotok_delivery_cost'], 'Стоимость доставки не записывается в meta объекта заказа.' );
	velotok_delivery_persistence_assert( 730.0 === (float) $order->shipping_total, 'Стоимость доставки не записывается в shipping_total объекта заказа.' );
	velotok_delivery_persistence_assert( 'cdek' === $order->shipping_method, 'Способ доставки не записывается в shipping_method объекта заказа.' );

	if ( function_exists( 'velotok_core_get_checkout_delivery' ) ) {
		$stored = velotok_core_get_checkout_delivery( $order );

		velotok_delivery_persistence_assert( true === $stored['valid'] && true === $stored['saved'], 'Сохранённая доставка должна читаться из объекта заказа.' );
		velotok_delivery_persistence_assert( 'cdek' === $stored['delivery_method'], 'Способ доставки не читается из объекта заказа.' );
		velotok_delivery_persistence_assert( 730.0 === (float) $stored['delivery_cost'], 'Стоимость доставки не читается из объекта заказа.' );
		velotok_delivery_persistence_assert( 'RUB' === $stored['delivery_currency'], 'Валюта доставки не читается из объекта заказа.' );
		velotok_delivery_persistence_assert( true === velotok_core_checkout_delivery_is_saved( $order ), 'Объект заказа должен определяться как имеющий сохранённую доставку.' );
	}
}

if ( function_exists( 'velotok_core_apply_checkout_delivery' ) ) {
	$order = array( 'id' => 502, 'subtotal' => 10000, 'weight' => 2 );
	velotok_core_apply_checkout_delivery( $order, 'cdek' );

	velotok_delivery_persistence_assert( 'cdek' === $order['delivery_method'], 'Применение выбора должно обновлять способ доставки в массиве заказа.' );
	velotok_delivery_persistence_assert( 730.0 === (float) $order['delivery_cost'], 'Применение выбора должно обновлять стоимость доставки в массиве заказа.' );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: выбранный способ и стоимость доставки сохраняются в заказе (mocked, stub).' . PHP_EOL;
