<?php
/**
 * Подключение стилей и скриптов.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

/**
 * Версия файла для сброса кеша.
 *
 * @param string $relative_path Путь относительно темы.
 * @return string
 */
function velotok_asset_version( $relative_path ) {
	$file = get_template_directory() . '/' . ltrim( $relative_path, '/' );

	if ( file_exists( $file ) ) {
		return (string) filemtime( $file );
	}

	return VELOTOK_VERSION;
}

/**
 * Модели подборщика из актуального каталога товаров.
 *
 * Цены и состав моделей берутся из товаров WooCommerce (или из демо-каталога,
 * если WooCommerce не установлен), а не из отдельной таблицы в теме.
 *
 * @return array<string,array>
 */
function velotok_picker_models() {
	$models = array();

	if ( ! function_exists( 'velotok_get_home_models' ) ) {
		return $models;
	}

	$index = 0;

	foreach ( velotok_get_home_models() as $model ) {
		if ( ! is_array( $model ) ) {
			continue;
		}

		$index++;
		$slug = isset( $model['slug'] ) ? trim( (string) $model['slug'] ) : '';

		if ( '' === $slug ) {
			$slug = 'model-' . $index;
		}

		$price   = isset( $model['price'] ) ? (float) $model['price'] : 0;
		$card_id = isset( $model['card_id'] ) ? trim( (string) $model['card_id'] ) : '';

		if ( '' === $card_id ) {
			$card_id = 'model-' . $slug;
		}

		$note = isset( $model['tagline'] ) ? trim( (string) $model['tagline'] ) : '';

		if ( '' === $note ) {
			$note = isset( $model['description'] ) ? (string) $model['description'] : '';
		}

		$models[ $slug ] = array(
			'slug'        => $slug,
			'name'        => isset( $model['name'] ) ? (string) $model['name'] : '',
			'price'       => number_format_i18n( $price, 0 ) . ' ₽',
			'price_value' => $price,
			'href'        => '#' . $card_id,
			'note'        => $note,
			'category'    => isset( $model['category'] ) ? (string) $model['category'] : '',
		);
	}

	return $models;
}

/**
 * Строки, которые использует фронтенд-скрипт.
 *
 * @return array
 */
function velotok_js_strings() {
	return array(
		'menuOpen'        => __( 'Открыть меню', 'velotok' ),
		'menuClose'       => __( 'Закрыть меню', 'velotok' ),
		'emptyResult'     => __( 'Ответьте на три вопроса — покажем модель, которая подходит под ваш маршрут и бюджет.', 'velotok' ),
		'noModel'         => __( 'В выбранном бюджете пока нет подходящей модели — измените бюджет или свяжитесь с нами.', 'velotok' ),
		'resultKicker'    => __( 'Ваш вариант', 'velotok' ),
		'viewModel'       => __( 'Смотреть модель', 'velotok' ),
		'addedToCart'     => __( 'Товар добавлен в корзину', 'velotok' ),
		'removedFromCart' => __( 'Товар удалён из корзины', 'velotok' ),
		'models'          => velotok_picker_models(),
	);
}

/**
 * Подключает ресурсы фронтенда.
 *
 * @return void
 */
function velotok_enqueue_assets() {
	wp_enqueue_style(
		'velotok-main',
		get_template_directory_uri() . '/assets/css/main.css',
		array(),
		velotok_asset_version( 'assets/css/main.css' )
	);

	wp_enqueue_style(
		'velotok-wp',
		get_template_directory_uri() . '/assets/css/wp.css',
		array( 'velotok-main' ),
		velotok_asset_version( 'assets/css/wp.css' )
	);

	wp_enqueue_style(
		'velotok-style',
		get_stylesheet_uri(),
		array( 'velotok-wp' ),
		velotok_asset_version( 'style.css' )
	);

	wp_enqueue_script(
		'velotok-picker',
		get_template_directory_uri() . '/assets/js/picker.js',
		array(),
		velotok_asset_version( 'assets/js/picker.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	wp_enqueue_script(
		'velotok-main',
		get_template_directory_uri() . '/assets/js/main.js',
		array( 'velotok-picker' ),
		velotok_asset_version( 'assets/js/main.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	wp_localize_script( 'velotok-main', 'velotokL10n', velotok_js_strings() );

	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	if ( is_front_page() || is_shop() || is_product() || is_product_category() || is_product_tag() ) {
		if ( wp_script_is( 'wc-add-to-cart', 'registered' ) ) {
			wp_enqueue_script( 'wc-add-to-cart' );
		}
		if ( wp_script_is( 'wc-cart-fragments', 'registered' ) ) {
			wp_enqueue_script( 'wc-cart-fragments' );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'velotok_enqueue_assets', 20 );

/**
 * Предзагружает изображение первого экрана на главной.
 *
 * @return void
 */
function velotok_preload_hero() {
	if ( ! is_front_page() ) {
		return;
	}

	printf(
		'<link rel="preload" as="image" href="%1$s" fetchpriority="high" />' . "\n",
		esc_url( get_template_directory_uri() . '/assets/img/hero-city-1-web.jpg' )
	);
}
add_action( 'wp_head', 'velotok_preload_hero', 2 );
