<?php
/**
 * Базовая настройка темы.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

/**
 * Регистрирует возможности темы и меню.
 *
 * @return void
 */
function velotok_setup() {
	load_theme_textdomain( 'velotok', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 48,
		'width'       => 180,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	) );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );

	add_theme_support( 'woocommerce', array(
		'thumbnail_image_width' => 800,
		'single_image_width'    => 1200,
		'product_grid'          => array(
			'default_rows'    => 3,
			'min_rows'        => 1,
			'default_columns' => 3,
			'min_columns'     => 1,
			'max_columns'     => 4,
		),
	) );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus( array(
		'primary' => __( 'Основное меню', 'velotok' ),
		'footer'  => __( 'Меню в подвале', 'velotok' ),
	) );

	add_image_size( 'velotok-card', 800, 600, true );
	add_image_size( 'velotok-wide', 1536, 864, true );
}
add_action( 'after_setup_theme', 'velotok_setup' );

/**
 * Задаёт ширину контента.
 *
 * @return void
 */
function velotok_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'velotok_content_width', 1280 );
}
add_action( 'after_setup_theme', 'velotok_content_width', 0 );

/**
 * Добавляет понятные классы ссылкам меню.
 *
 * @param array   $classes Классы элемента.
 * @param WP_Post $item    Элемент меню.
 * @return array
 */
function velotok_nav_menu_css_class( $classes, $item ) {
	if ( in_array( 'current-menu-item', $classes, true ) || in_array( 'current_page_item', $classes, true ) ) {
		$classes[] = 'is-current';
	}

	return $classes;
}
add_filter( 'nav_menu_css_class', 'velotok_nav_menu_css_class', 10, 2 );

/**
 * Резервное основное меню, повторяющее якоря главной страницы.
 *
 * @return void
 */
function velotok_primary_menu_fallback() {
	$home = home_url( '/' );
	$items = array(
		'#categories'  => __( 'Каталог', 'velotok' ),
		'#models'      => __( 'Модели', 'velotok' ),
		'#advantages'  => __( 'Сервис', 'velotok' ),
		'#picker'      => __( 'Как выбрать', 'velotok' ),
		'#reviews'     => __( 'Отзывы', 'velotok' ),
		'#lead'        => __( 'Контакты', 'velotok' ),
	);

	foreach ( $items as $anchor => $label ) {
		printf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $home . $anchor ),
			esc_html( $label )
		);
	}
}

/**
 * Резервное меню подвала.
 *
 * @return void
 */
function velotok_footer_menu_fallback() {
	$items = array(
		array( home_url( '/#lead' ), __( 'Доставка и оплата', 'velotok' ), '' ),
		array( home_url( '/#advantages' ), __( 'Гарантия и сервис', 'velotok' ), '' ),
		array( home_url( '/#lead' ), __( 'Тест-драйв', 'velotok' ), '' ),
		array( home_url( '/#reviews' ), __( 'Отзывы', 'velotok' ), '' ),
		array( home_url( '/#privacy' ), __( 'Политика конфиденциальности', 'velotok' ), ' id="privacy"' ),
	);

	foreach ( $items as $item ) {
		printf(
			'<a href="%1$s"%3$s>%2$s</a>',
			esc_url( $item[0] ),
			esc_html( $item[1] ),
			$item[2] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- статичный атрибут id.
		);
	}
}
