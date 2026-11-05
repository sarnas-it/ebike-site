<?php
/**
 * Contract test for the Velotok WordPress storefront theme.
 *
 * Standalone:
 *   php wp/themes/velotok/tests/theme-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/themes/velotok/tests/theme-contract-test.php
 *
 * The test intentionally fails when the theme is removed, loses its style.css
 * header, required templates or the WooCommerce storefront integration.
 *
 * @package Velotok
 */

$GLOBALS['velotok_theme_contract_failures'] = array();
$velotok_theme_dir                         = dirname( __DIR__ );

if ( ! function_exists( 'velotok_theme_contract_assert' ) ) {
	/**
	 * Assert a contract condition.
	 *
	 * @param bool   $condition Condition to check.
	 * @param string $message   Failure message.
	 * @return void
	 */
	function velotok_theme_contract_assert( $condition, $message ) {
		if ( ! $condition ) {
			$GLOBALS['velotok_theme_contract_failures'][] = $message;
		}
	}
}

if ( ! function_exists( 'velotok_theme_contract_read_header' ) ) {
	/**
	 * Read the WordPress stylesheet header fields from style.css.
	 *
	 * @param string $file Absolute path to style.css.
	 * @return array<string,string>
	 */
	function velotok_theme_contract_read_header( $file ) {
		$headers = array();
		$source  = (string) file_get_contents( $file );

		if ( preg_match_all( '/^\s*([A-Za-z][A-Za-z0-9 _-]+):\s*(.+)$/m', $source, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$headers[ trim( $match[1] ) ] = trim( $match[2] );
			}
		}

		return $headers;
	}
}

if ( ! function_exists( 'velotok_theme_contract_source' ) ) {
	/**
	 * Read a theme source file.
	 *
	 * @param string $path Absolute path.
	 * @return string
	 */
	function velotok_theme_contract_source( $path ) {
		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}
}

/**
 * Check that the theme root is available.
 */
velotok_theme_contract_assert( is_dir( $velotok_theme_dir ), 'Не найден каталог темы velotok.' );

/**
 * Required theme files: WordPress templates, theme modules, storefront parts
 * and WooCommerce template overrides.
 */
$velotok_required_files = array(
	'style.css',
	'functions.php',
	'index.php',
	'header.php',
	'footer.php',
	'front-page.php',
	'page.php',
	'single.php',
	'404.php',
	'inc/setup.php',
	'inc/enqueue.php',
	'inc/customizer.php',
	'inc/security.php',
	'inc/woocommerce.php',
	'template-parts/header-bar.php',
	'template-parts/hero.php',
	'template-parts/categories.php',
	'template-parts/models.php',
	'template-parts/model-card.php',
	'template-parts/advantages.php',
	'template-parts/picker.php',
	'template-parts/reviews.php',
	'template-parts/lead-form.php',
	'template-parts/footer-content.php',
	'woocommerce/archive-product.php',
	'woocommerce/content-product.php',
	'woocommerce/single-product.php',
	'woocommerce/cart/cart.php',
	'woocommerce/checkout/form-checkout.php',
	'woocommerce/checkout/thankyou.php',
	'assets/css/main.css',
	'assets/css/wp.css',
	'assets/js/main.js',
	'assets/img/hero-city-1-web.jpg',
	'assets/img/model-city-classic-1-web.jpg',
	'assets/img/model-city-sport-1-web.jpg',
	'assets/img/model-mountain-pro-1-web.jpg',
	'assets/img/model-mountain-hardtail-1-web.jpg',
	'assets/img/model-cargo-family-1-web.jpg',
	'assets/img/model-cargo-courier-1-web.jpg',
	'assets/img/model-folding-compact-1-web.jpg',
);

foreach ( $velotok_required_files as $velotok_required_file ) {
	$velotok_path = $velotok_theme_dir . '/' . $velotok_required_file;

	velotok_theme_contract_assert( is_readable( $velotok_path ), sprintf( 'Отсутствует обязательный файл темы: %s', $velotok_required_file ) );
}

/**
 * Valid WordPress theme header in style.css.
 */
$velotok_style_path = $velotok_theme_dir . '/style.css';

if ( is_readable( $velotok_style_path ) ) {
	$velotok_header = velotok_theme_contract_read_header( $velotok_style_path );

	foreach ( array( 'Theme Name', 'Description', 'Version', 'Requires at least', 'Requires PHP', 'License', 'Text Domain' ) as $velotok_header_key ) {
		velotok_theme_contract_assert(
			isset( $velotok_header[ $velotok_header_key ] ) && '' !== $velotok_header[ $velotok_header_key ],
			sprintf( 'В style.css не задано поле шапки «%s».', $velotok_header_key )
		);
	}

	velotok_theme_contract_assert(
		isset( $velotok_header['Theme Name'] ) && 'Велоток' === $velotok_header['Theme Name'],
		'Шапка style.css должна объявлять тему «Велоток».'
	);
	velotok_theme_contract_assert(
		isset( $velotok_header['Text Domain'] ) && 'velotok' === $velotok_header['Text Domain'],
		'Text Domain темы должен быть velotok.'
	);
	velotok_theme_contract_assert(
		isset( $velotok_header['Description'] ) && false !== stripos( $velotok_header['Description'], 'WooCommerce' ),
		'Описание темы должно указывать на витрину WooCommerce.'
	);
}

/**
 * The theme must load its modules and declare storefront support.
 */
$velotok_functions = velotok_theme_contract_source( $velotok_theme_dir . '/functions.php' );

foreach ( array( 'inc/setup.php', 'inc/customizer.php', 'inc/enqueue.php', 'inc/security.php', 'inc/woocommerce.php' ) as $velotok_include ) {
	velotok_theme_contract_assert(
		false !== strpos( $velotok_functions, $velotok_include ),
		sprintf( 'functions.php не подключает модуль %s.', $velotok_include )
	);
}

$velotok_setup = velotok_theme_contract_source( $velotok_theme_dir . '/inc/setup.php' );

foreach ( array( 'woocommerce', 'post-thumbnails', 'title-tag', 'custom-logo' ) as $velotok_feature ) {
	velotok_theme_contract_assert(
		1 === preg_match( "/add_theme_support\\(\\s*'" . preg_quote( $velotok_feature, '/' ) . "'/", $velotok_setup ),
		sprintf( 'Тема не объявляет поддержку %s.', $velotok_feature )
	);
}

velotok_theme_contract_assert(
	1 === preg_match( '/register_nav_menus\\s*\\(/', $velotok_setup ),
	'Тема не регистрирует навигационные меню витрины.'
);
velotok_theme_contract_assert(
	false !== strpos( $velotok_setup, "'primary'" ) && false !== strpos( $velotok_setup, "'footer'" ),
	'Тема должна регистрировать основное меню и меню подвала.'
);

$velotok_enqueue = velotok_theme_contract_source( $velotok_theme_dir . '/inc/enqueue.php' );

foreach ( array( 'wp_enqueue_style', 'wp_enqueue_script', 'get_stylesheet_uri', 'wp_localize_script' ) as $velotok_enqueue_call ) {
	velotok_theme_contract_assert(
		false !== strpos( $velotok_enqueue, $velotok_enqueue_call ),
		sprintf( 'inc/enqueue.php не вызывает %s.', $velotok_enqueue_call )
	);
}

$velotok_woocommerce = velotok_theme_contract_source( $velotok_theme_dir . '/inc/woocommerce.php' );

foreach ( array( 'woocommerce_enqueue_styles', 'woocommerce_add_to_cart_fragments', 'function velotok_get_home_models', 'function velotok_normalize_product', 'function velotok_fallback_models' ) as $velotok_woo_hook ) {
	velotok_theme_contract_assert(
		false !== strpos( $velotok_woocommerce, $velotok_woo_hook ),
		sprintf( 'inc/woocommerce.php не содержит %s.', $velotok_woo_hook )
	);
}

$velotok_security = velotok_theme_contract_source( $velotok_theme_dir . '/inc/security.php' );

foreach ( array( 'X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy' ) as $velotok_header_name ) {
	velotok_theme_contract_assert(
		false !== strpos( $velotok_security, $velotok_header_name ),
		sprintf( 'inc/security.php не выставляет заголовок %s.', $velotok_header_name )
	);
}

$velotok_front_page = velotok_theme_contract_source( $velotok_theme_dir . '/front-page.php' );

foreach ( array( 'hero', 'categories', 'models', 'advantages', 'picker', 'reviews', 'lead-form' ) as $velotok_part ) {
	velotok_theme_contract_assert(
		false !== strpos( $velotok_front_page, 'template-parts/' . $velotok_part ),
		sprintf( 'front-page.php не выводит блок витрины %s.', $velotok_part )
	);
}

foreach ( array( 'header.php', 'footer.php' ) as $velotok_document_part ) {
	$velotok_document_source = velotok_theme_contract_source( $velotok_theme_dir . '/' . $velotok_document_part );

	velotok_theme_contract_assert(
		false !== strpos( $velotok_document_source, 'wp_' . ( 'header.php' === $velotok_document_part ? 'head' : 'footer' ) . '()' ),
		sprintf( '%s не вызывает обязательный хук WordPress.', $velotok_document_part )
	);
}

foreach ( array( 'front-page.php', 'index.php', 'page.php', 'single.php', '404.php' ) as $velotok_template ) {
	$velotok_template_source = velotok_theme_contract_source( $velotok_theme_dir . '/' . $velotok_template );

	velotok_theme_contract_assert(
		false !== strpos( $velotok_template_source, 'get_header()' ) && false !== strpos( $velotok_template_source, 'get_footer()' ),
		sprintf( 'Шаблон %s не использует общие шапку и подвал темы.', $velotok_template )
	);
}

/**
 * Runtime checks when the test is executed inside WordPress.
 */
if ( defined( 'ABSPATH' ) && function_exists( 'wp_get_theme' ) ) {
	$velotok_theme = wp_get_theme( 'velotok' );

	velotok_theme_contract_assert( $velotok_theme->exists(), 'WordPress не видит установленную тему velotok.' );
	velotok_theme_contract_assert( 'velotok' === $velotok_theme->get_stylesheet(), 'Тема velotok зарегистрирована под неверным слагом.' );
	velotok_theme_contract_assert( 'velotok' === get_stylesheet(), 'Тема velotok не активирована на сайте.' );
	velotok_theme_contract_assert( current_theme_supports( 'woocommerce' ), 'Активная тема не объявляет поддержку WooCommerce.' );
	velotok_theme_contract_assert( current_theme_supports( 'post-thumbnails' ), 'Активная тема не поддерживает миниатюры товаров.' );

	if ( function_exists( 'get_registered_nav_menus' ) ) {
		$velotok_menus = get_registered_nav_menus();

		velotok_theme_contract_assert( isset( $velotok_menus['primary'] ), 'Не зарегистрировано основное меню витрины.' );
		velotok_theme_contract_assert( isset( $velotok_menus['footer'] ), 'Не зарегистрировано меню подвала.' );
	}

	foreach ( array( 'velotok_setup', 'velotok_enqueue_assets', 'velotok_get_home_models', 'velotok_product_specs', 'velotok_cart_url', 'velotok_order_delivery_total' ) as $velotok_function ) {
		velotok_theme_contract_assert( function_exists( $velotok_function ), sprintf( 'Не загружена функция темы %s().', $velotok_function ) );
	}

	if ( function_exists( 'velotok_get_home_models' ) ) {
		$velotok_models = velotok_get_home_models();

		velotok_theme_contract_assert( is_array( $velotok_models ) && ! empty( $velotok_models ), 'Витрина не отдаёт ни одной модели (ни из WooCommerce, ни из резервного каталога).' );

		if ( ! empty( $velotok_models[0] ) && is_array( $velotok_models[0] ) ) {
			$velotok_model = $velotok_models[0];

			foreach ( array( 'card_id', 'name', 'url', 'image_html', 'specs', 'price_html', 'add_html' ) as $velotok_key ) {
				velotok_theme_contract_assert( isset( $velotok_model[ $velotok_key ] ), sprintf( 'У карточки модели нет поля %s.', $velotok_key ) );
			}

			if ( function_exists( 'get_template_part' ) ) {
				ob_start();
				get_template_part( 'template-parts/model-card', null, $velotok_model );
				$velotok_card = (string) ob_get_clean();

				velotok_theme_contract_assert( false !== strpos( $velotok_card, 'class="model-card"' ), 'Карточка модели не рендерится шаблоном темы.' );
				velotok_theme_contract_assert( false !== strpos( $velotok_card, 'class="specs"' ), 'Карточка модели не выводит характеристики.' );
				velotok_theme_contract_assert( false !== strpos( $velotok_card, 'class="btn-add' ), 'Карточка модели не выводит кнопку покупки.' );
			}
		}
	}

	if ( function_exists( 'velotok_enqueue_assets' ) ) {
		velotok_enqueue_assets();

		velotok_theme_contract_assert( wp_style_is( 'velotok-main', 'enqueued' ), 'Стили витрины velotok-main не подключаются.' );
		velotok_theme_contract_assert( wp_style_is( 'velotok-wp', 'enqueued' ), 'Стили WordPress-шаблонов velotok-wp не подключаются.' );
		velotok_theme_contract_assert( wp_style_is( 'velotok-style', 'enqueued' ), 'Основной style.css темы не подключается.' );
		velotok_theme_contract_assert( wp_script_is( 'velotok-main', 'enqueued' ), 'Скрипт витрины velotok-main не подключается.' );
	}
}

if ( ! empty( $GLOBALS['velotok_theme_contract_failures'] ) ) {
	foreach ( $GLOBALS['velotok_theme_contract_failures'] as $velotok_failure ) {
		fwrite( STDERR, 'FAIL: ' . $velotok_failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: тема velotok оформляет витрину магазина и подключена к WordPress/WooCommerce.' . PHP_EOL;
