<?php
/**
 * Behaviour test for TESTEB-50: the theme overrides WooCommerce templates.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/woocommerce-templates-test.php
 *
 * WooCommerce must look for its templates inside the theme and take the
 * overrides from wp/themes/velotok/woocommerce/. This test loads the theme
 * integration in isolation, checks that both hooks are registered and that the
 * locate filter resolves existing theme templates while leaving unknown
 * templates untouched.
 *
 * @package Velotok
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, 'This test must be run from the command line.' . PHP_EOL );
	exit( 1 );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_woocommerce_test_filters'] = array();
$failures                                    = array();

/**
 * Record a registered filter so the test can inspect the integration.
 *
 * @param string   $hook          Hook name.
 * @param callable $callback      Callback.
 * @param int      $priority      Priority.
 * @param int      $accepted_args Accepted arguments.
 * @return bool
 */
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['velotok_woocommerce_test_filters'][] = array(
		'hook'          => $hook,
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);

	return true;
}

/**
 * WordPress action stub.
 *
 * @return void
 */
function add_action() {}

/**
 * Theme directory stub.
 *
 * @return string
 */
function get_template_directory() {
	return dirname( __DIR__ );
}

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_woocommerce_template_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Find a recorded filter registration.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @return array|null
 */
function velotok_woocommerce_test_filter( $hook, $callback ) {
	foreach ( $GLOBALS['velotok_woocommerce_test_filters'] as $filter ) {
		if ( $hook === $filter['hook'] && $callback === $filter['callback'] ) {
			return $filter;
		}
	}

	return null;
}

require_once dirname( __DIR__ ) . '/inc/woocommerce.php';

/*
 * 1. The theme points WooCommerce at its own template directory.
 */
$path_filter = velotok_woocommerce_test_filter( 'woocommerce_template_path', 'velotok_woocommerce_template_path' );
velotok_woocommerce_template_assert( null !== $path_filter, 'Тема должна подключать шаблоны через woocommerce_template_path.' );
velotok_woocommerce_template_assert( function_exists( 'velotok_woocommerce_template_path' ), 'Фильтр woocommerce_template_path не определён.' );
velotok_woocommerce_template_assert( 'woocommerce/' === velotok_woocommerce_template_path( 'templates/' ), 'Каталог шаблонов WooCommerce должен указывать на woocommerce/.' );

/*
 * 2. The locate filter resolves overrides from the theme.
 */
$locate_filter = velotok_woocommerce_test_filter( 'woocommerce_locate_template', 'velotok_locate_woocommerce_template' );
velotok_woocommerce_template_assert( null !== $locate_filter, 'Тема должна подключать шаблоны через woocommerce_locate_template.' );
velotok_woocommerce_template_assert(
	null !== $locate_filter && (int) $locate_filter['accepted_args'] >= 3,
	'Фильтр woocommerce_locate_template должен получать имя и каталог шаблона.'
);

$theme_file = dirname( __DIR__ ) . '/woocommerce/archive-product.php';
$located    = velotok_locate_woocommerce_template( '/woocommerce/templates/archive-product.php', 'archive-product.php', 'woocommerce/' );
velotok_woocommerce_template_assert( $theme_file === $located, 'Архив товаров должен подключаться из темы.' );

$missing_template = '/woocommerce/templates/emails/does-not-exist.php';
$located          = velotok_locate_woocommerce_template( $missing_template, 'emails/does-not-exist.php', 'woocommerce/' );
velotok_woocommerce_template_assert( $missing_template === $located, 'Без переопределения должен возвращаться исходный шаблон.' );

/*
 * 3. The storefront templates required by the theme are present.
 */
$templates = array(
	'archive-product.php',
	'single-product.php',
	'content-product.php',
	'cart/cart.php',
	'checkout/form-checkout.php',
	'checkout/thankyou.php',
);

foreach ( $templates as $template ) {
	$file = dirname( __DIR__ ) . '/woocommerce/' . $template;
	velotok_woocommerce_template_assert( is_readable( $file ), 'Нет переопределённого шаблона WooCommerce: ' . $template );
}

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок шаблонов WooCommerce не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: тема переопределяет шаблоны WooCommerce и подключает их через woocommerce_template_path/woocommerce_locate_template (TESTEB-50).' . PHP_EOL;
exit( 0 );
