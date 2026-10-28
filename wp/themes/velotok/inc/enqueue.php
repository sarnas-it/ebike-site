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
 * Строки, которые использует фронтенд-скрипт.
 *
 * @return array
 */
function velotok_js_strings() {
	$models = array(
		'riviera'      => array(
			'name'  => __( 'Ривьера', 'velotok' ),
			'price' => number_format_i18n( 62900, 0 ) . ' ₽',
			'href'  => '#model-riviera',
			'note'  => __( 'Лёгкая низкая рама, 90 км запаса хода и спокойный характер — для ежедневных поездок по городу.', 'velotok' ),
		),
		'street'       => array(
			'name'  => __( 'Стрит 7', 'velotok' ),
			'price' => number_format_i18n( 74900, 0 ) . ' ₽',
			'href'  => '#model-street',
			'note'  => __( 'Более быстрый и жёсткий вариант для асфальта: 110 км хода и посадка для активной езды.', 'velotok' ),
		),
		'hrebet-pro'   => array(
			'name'  => __( 'Хребет Pro', 'velotok' ),
			'price' => number_format_i18n( 149900, 0 ) . ' ₽',
			'href'  => '#model-hrebet-pro',
			'note'  => __( 'Полная подвеска и мотор 750 Вт — если в маршруте есть серьёзные подъёмы и спуски.', 'velotok' ),
		),
		'hrebet-trail' => array(
			'name'  => __( 'Хребет Trail', 'velotok' ),
			'price' => number_format_i18n( 98900, 0 ) . ' ₽',
			'href'  => '#model-hrebet-trail',
			'note'  => __( 'Хардтейл для леса и грунта: меньше веса, простая обслуживаемость и уверенный ход.', 'velotok' ),
		),
		'family'       => array(
			'name'  => __( 'Семейный', 'velotok' ),
			'price' => number_format_i18n( 132900, 0 ) . ' ₽',
			'href'  => '#model-family',
			'note'  => __( 'Удлинённая рама и бокс на 80 кг: дети, продукты и всё, что нужно на даче.', 'velotok' ),
		),
		'courier'      => array(
			'name'  => __( 'Курьер', 'velotok' ),
			'price' => number_format_i18n( 119900, 0 ) . ' ₽',
			'href'  => '#model-courier',
			'note'  => __( 'Рама под ежедневные нагрузки: передний и задний багажник, груз до 120 кг.', 'velotok' ),
		),
		'sklad'        => array(
			'name'  => __( 'Складень 20', 'velotok' ),
			'price' => number_format_i18n( 68900, 0 ) . ' ₽',
			'href'  => '#model-sklad',
			'note'  => __( 'Складывается за полминуты и помещается в багажник — для поездов, офиса и лифта.', 'velotok' ),
		),
	);

	return array(
		'menuOpen'       => __( 'Открыть меню', 'velotok' ),
		'menuClose'      => __( 'Закрыть меню', 'velotok' ),
		'emptyResult'    => __( 'Ответьте на три вопроса — покажем модель, которая подходит под ваш маршрут и бюджет.', 'velotok' ),
		'resultKicker'   => __( 'Ваш вариант', 'velotok' ),
		'viewModel'      => __( 'Смотреть модель', 'velotok' ),
		'addedToCart'    => __( 'Товар добавлен в корзину', 'velotok' ),
		'removedFromCart' => __( 'Товар удалён из корзины', 'velotok' ),
		'models'         => $models,
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
		'velotok-main',
		get_template_directory_uri() . '/assets/js/main.js',
		array(),
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
