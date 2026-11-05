<?php
/**
 * Проверка отображения дополнительных атрибутов в карточке товара.
 *
 * Запуск: php wp/themes/velotok/tests/extra-attributes-card-test.php
 *
 * @package Velotok
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

function __( $text, $domain = null ) {
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
	return (string) $url;
}

function wp_kses_post( $html ) {
	return (string) $html;
}

require_once dirname( __DIR__, 3 ) . '/plugins/velotok-core/includes/product-extra-attributes.php';

/**
 * Проверить условие и завершиться с ошибкой.
 *
 * @param bool   $condition Условие.
 * @param string $message   Сообщение.
 */
function velotok_extra_card_check( $condition, $message ) {
	if ( ! $condition ) {
		file_put_contents( 'php://stderr', 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}

	echo 'ok - ' . $message . PHP_EOL;
}

/**
 * Отрисовать карточку и вернуть HTML.
 *
 * @param array $args Данные карточки.
 * @return string
 */
function velotok_extra_card_render( array $args ) {
	ob_start();
	include dirname( __DIR__ ) . '/template-parts/model-card.php';
	return (string) ob_get_clean();
}

$model = array(
	'card_id'          => 'model-riviera',
	'name'             => 'Ривьера',
	'description'      => 'Лёгкая низкая рама для города.',
	'url'              => 'https://example.test/product/riviera/',
	'image_html'       => '<img src="riviera.jpg" class="model-photo" alt="Ривьера">',
	'category_label'   => 'Городской',
	'badge'            => '',
	'badge_lime'       => false,
	'number'           => '01',
	'specs'            => array( array( 'Запас хода', 'до 90 км' ) ),
	'extra_attributes' => velotok_core_get_product_extra_attributes( 'riviera' ),
	'price_html'       => '62 900 ₽',
	'price_note'       => '',
	'add_html'         => '<a class="btn-add" href="#">В корзину</a>',
);

$html = velotok_extra_card_render( $model );

velotok_extra_card_check( false !== strpos( $html, 'model-extra' ), 'карточка содержит блок дополнительных атрибутов' );
velotok_extra_card_check( false !== strpos( $html, 'Модель' ) && false !== strpos( $html, 'Ривьера' ), 'карточка отображает дополнительный атрибут «Модель»' );
velotok_extra_card_check( false !== strpos( $html, 'Аккумулятор' ) && false !== strpos( $html, '48 В' ), 'карточка отображает дополнительный атрибут «Аккумулятор»' );

$arbitrary = velotok_core_get_product_extra_attributes(
	array(
		array( 'Гарантия', '2 года' ),
		array( 'Пустое значение', '' ),
	)
);

$model['extra_attributes'] = $arbitrary;
$html = velotok_extra_card_render( $model );

velotok_extra_card_check( false !== strpos( $html, 'Гарантия' ) && false !== strpos( $html, '2 года' ), 'произвольный дополнительный атрибут отображается' );
velotok_extra_card_check( false === strpos( $html, 'Пустое значение' ), 'пустой дополнительный атрибут скрывается' );

echo 'PASS: дополнительные атрибуты товара отображаются в карточке.' . PHP_EOL;
