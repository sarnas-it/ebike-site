<?php
/**
 * Проверка того, что данные подборщика берутся из каталога товаров.
 *
 * Запуск: php wp/themes/velotok/tests/picker-models-test.php
 *
 * @package Velotok
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

function add_action() {}
function add_filter() {}

function __( $text, $domain = null ) {
	return $text;
}

function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, (int) $decimals, '.', ' ' );
}

$GLOBALS['velotok_picker_source'] = array(
	array(
		'slug'        => 'alpha',
		'name'        => 'Альфа',
		'price'       => 12345,
		'card_id'     => 'model-alpha',
		'description' => 'Первая модель.',
		'tagline'     => 'Короткая подпись первой модели.',
		'category'    => 'city',
	),
	array(
		'slug'        => 'beta',
		'name'        => 'Бета',
		'price'       => 99999,
		'card_id'     => '',
		'description' => 'Вторая модель.',
		'tagline'     => '',
		'category'    => 'mountain',
	),
);

function velotok_get_home_models() {
	return $GLOBALS['velotok_picker_source'];
}

/**
 * Проверить условие и завершиться с ошибкой.
 *
 * @param bool   $condition Условие.
 * @param string $message   Сообщение.
 */
function velotok_picker_check( $condition, $message ) {
	if ( ! $condition ) {
		file_put_contents( 'php://stderr', 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}

	echo 'ok - ' . $message . PHP_EOL;
}

require dirname( __DIR__ ) . '/inc/enqueue.php';

$models = velotok_picker_models();

velotok_picker_check( 2 === count( $models ), 'в подборщик попадают все модели каталога' );
velotok_picker_check( isset( $models['alpha'], $models['beta'] ), 'модели индексируются по slug товара' );
velotok_picker_check( 12345.0 === $models['alpha']['price_value'], 'цена берётся из товара, а не из таблицы темы' );
velotok_picker_check( '12 345 ₽' === $models['alpha']['price'], 'цена для вывода форматируется из цены товара' );
velotok_picker_check( '#model-alpha' === $models['alpha']['href'], 'ссылка ведёт на карточку товара' );
velotok_picker_check( 'Короткая подпись первой модели.' === $models['alpha']['note'], 'для подписи используется данные товара' );
velotok_picker_check( '#model-beta' === $models['beta']['href'], 'без card_id ссылка собирается из slug' );
velotok_picker_check( 'mountain' === $models['beta']['category'], 'категория товара передаётся в подборщик' );

$GLOBALS['velotok_picker_source'][0]['price'] = 777;

$updated = velotok_picker_models();

velotok_picker_check( 777.0 === $updated['alpha']['price_value'], 'изменение цены товара сразу меняет данные подборщика' );

$strings = velotok_js_strings();

velotok_picker_check( $updated === $strings['models'], 'подборщик получает те же данные из каталога товаров' );
velotok_picker_check( isset( $strings['noModel'] ) && '' !== $strings['noModel'], 'есть сообщение для бюджета без подходящих моделей' );

echo 'PASS: подборщик получает модели и цены из товаров.' . PHP_EOL;
