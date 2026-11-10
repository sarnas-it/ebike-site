<?php
/**
 * Behaviour test for EBIKE-22: checkout uses the classic theme template.
 *
 * Standalone:
 *   php wp/themes/velotok/tests/checkout-classic-test.php
 *
 * The checkout page must not render the JavaScript `wp:woocommerce/checkout`
 * block. It is created, migrated and rendered as the `[woocommerce_checkout]`
 * shortcode, so `woocommerce/checkout/form-checkout.php` from the theme is used.
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

$GLOBALS['velotok_checkout_classic_test_filters'] = array();
$GLOBALS['velotok_checkout_classic_test_actions'] = array();
$GLOBALS['velotok_checkout_classic_test_page']    = null;
$GLOBALS['velotok_checkout_classic_test_updates'] = array();
$GLOBALS['velotok_checkout_classic_test_option']  = 12;

/**
 * Record a filter registration.
 *
 * @param string   $hook          Hook name.
 * @param callable $callback      Callback.
 * @param int      $priority      Priority.
 * @param int      $accepted_args Accepted arguments.
 * @return bool
 */
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['velotok_checkout_classic_test_filters'][] = array(
		'hook'          => $hook,
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);

	return true;
}

/**
 * Record an action registration.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @return bool
 */
function add_action( $hook, $callback, $priority = 10 ) {
	$GLOBALS['velotok_checkout_classic_test_actions'][] = array(
		'hook'     => $hook,
		'callback' => $callback,
		'priority' => $priority,
	);

	return true;
}

/**
 * Theme directory stub.
 *
 * @return string
 */
function get_template_directory() {
	return dirname( __DIR__ );
}

/**
 * Read a WordPress option stub.
 *
 * @param string $name    Option name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	if ( 'woocommerce_checkout_page_id' === $name ) {
		return $GLOBALS['velotok_checkout_classic_test_option'];
	}

	return $default;
}

/**
 * Read a post stub.
 *
 * @param int $post_id Post ID.
 * @return object|null
 */
function get_post( $post_id ) {
	$page = $GLOBALS['velotok_checkout_classic_test_page'];

	if ( ! is_object( $page ) || (int) $page->ID !== (int) $post_id ) {
		return null;
	}

	return $page;
}

/**
 * Update a post stub.
 *
 * @param array $postarr Post data.
 * @param bool  $wp_error Whether to return an error.
 * @return int
 */
function wp_update_post( $postarr, $wp_error = false ) {
	$GLOBALS['velotok_checkout_classic_test_updates'][] = $postarr;

	if ( is_object( $GLOBALS['velotok_checkout_classic_test_page'] ) ) {
		$GLOBALS['velotok_checkout_classic_test_page']->post_content = $postarr['post_content'];
	}

	return isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
}

/**
 * WordPress error stub.
 *
 * @param mixed $thing Value.
 * @return bool
 */
function is_wp_error( $thing ) {
	return false;
}

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_checkout_classic_assert( $condition, $message ) {
	if ( ! $condition ) {
		$GLOBALS['velotok_checkout_classic_test_failures'][] = $message;
	}
}

/**
 * Create a checkout page stub.
 *
 * @param string $content Page content.
 * @return object
 */
function velotok_checkout_classic_page( $content ) {
	return (object) array(
		'ID'           => (int) $GLOBALS['velotok_checkout_classic_test_option'],
		'post_content' => $content,
	);
}

require_once dirname( __DIR__ ) . '/inc/woocommerce.php';

$GLOBALS['velotok_checkout_classic_test_failures'] = array();

/*
 * 1. The classic shortcode and page content are declared.
 */
velotok_checkout_classic_assert( '[woocommerce_checkout]' === velotok_checkout_classic_shortcode(), 'Checkout должен использовать шорткод [woocommerce_checkout].' );
velotok_checkout_classic_assert( '[woocommerce_checkout]' === velotok_checkout_page_content(), 'Содержимое страницы checkout должно быть классическим шорткодом.' );

/*
 * 2. Page creation keeps classic checkout for fresh installs.
 */
$pages = velotok_checkout_classic_create_pages(
	array(
		'shop'     => array( 'content' => '' ),
		'checkout' => array( 'content' => '<!-- wp:woocommerce/checkout /-->' ),
	)
);
velotok_checkout_classic_assert( '[woocommerce_checkout]' === $pages['checkout']['content'], 'Новая страница checkout должна создаваться с классическим шорткодом.' );
velotok_checkout_classic_assert( array_key_exists( 'shop', $pages ), 'Фильтр создания страниц не должен терять другие страницы WooCommerce.' );

$create_filter = null;

foreach ( $GLOBALS['velotok_checkout_classic_test_filters'] as $filter ) {
	if ( 'woocommerce_create_pages' === $filter['hook'] && 'velotok_checkout_classic_create_pages' === $filter['callback'] ) {
		$create_filter = $filter;
	}
}
velotok_checkout_classic_assert( null !== $create_filter, 'Тема должна подключать классический checkout через woocommerce_create_pages.' );

/*
 * 3. Block markup is detected and replaced, including surrounding content.
 */
$block = '<!-- wp:woocommerce/checkout {"align":"wide"} /-->';
velotok_checkout_classic_assert( true === velotok_checkout_has_block( $block ), 'Блочный checkout должен распознаваться.' );
velotok_checkout_classic_assert( false === velotok_checkout_has_block( '[woocommerce_checkout]' ), 'Классический шорткод не должен считаться блоком.' );
velotok_checkout_classic_assert( '[woocommerce_checkout]' === velotok_checkout_classic_replace_blocks( $block ), 'Одиночный блок checkout должен заменяться шорткодом.' );

$paired = '<!-- wp:woocommerce/checkout --><div>inner</div><!-- /wp:woocommerce/checkout -->';
velotok_checkout_classic_assert( '[woocommerce_checkout]' === velotok_checkout_classic_replace_blocks( $paired ), 'Парный блок checkout должен заменяться шорткодом.' );

$surrounded = '<p>До блока</p>' . $block . '<p>После блока</p>';
$replaced   = velotok_checkout_classic_replace_blocks( $surrounded );
velotok_checkout_classic_assert( false !== strpos( $replaced, 'До блока' ) && false !== strpos( $replaced, 'После блока' ), 'Замена блока не должна удалять остальное содержимое страницы.' );
velotok_checkout_classic_assert( false === strpos( $replaced, 'wp:woocommerce/checkout' ), 'После замены блочный checkout не должен оставаться.' );

/*
 * 4. An existing block checkout page is migrated idempotently.
 */
$GLOBALS['velotok_checkout_classic_test_page']    = velotok_checkout_classic_page( $block );
$GLOBALS['velotok_checkout_classic_test_updates'] = array();

velotok_checkout_classic_assert( true === velotok_checkout_classic_migrate_page(), 'Страница с блочным checkout должна обновляться.' );
velotok_checkout_classic_assert( 1 === count( $GLOBALS['velotok_checkout_classic_test_updates'] ), 'Миграция должна обновить страницу ровно один раз.' );
velotok_checkout_classic_assert( '[woocommerce_checkout]' === $GLOBALS['velotok_checkout_classic_test_page']->post_content, 'После миграции в базе должен остаться только классический шорткод.' );

velotok_checkout_classic_assert( false === velotok_checkout_classic_migrate_page(), 'Повторная миграция не должна обновлять уже классическую страницу.' );
velotok_checkout_classic_assert( 1 === count( $GLOBALS['velotok_checkout_classic_test_updates'] ), 'Повторная миграция не должна писать в базу.' );

$GLOBALS['velotok_checkout_classic_test_page'] = velotok_checkout_classic_page( '<p>Авторский текст</p>' );
velotok_checkout_classic_assert( false === velotok_checkout_classic_migrate_page(), 'Страница с авторским содержимым не должна перезаписываться.' );

/*
 * 5. Rendering falls back to the classic template before blocks are parsed.
 */
$content_filter = null;

foreach ( $GLOBALS['velotok_checkout_classic_test_filters'] as $filter ) {
	if ( 'the_content' === $filter['hook'] && 'velotok_checkout_classic_the_content' === $filter['callback'] ) {
		$content_filter = $filter;
	}
}
velotok_checkout_classic_assert( null !== $content_filter, 'Тема должна подменять блочный checkout на the_content.' );
velotok_checkout_classic_assert( null !== $content_filter && (int) $content_filter['priority'] < 9, 'Подмена checkout должна выполняться до do_blocks.' );
velotok_checkout_classic_assert( '[woocommerce_checkout]' === velotok_checkout_classic_the_content( $block ), 'Рендер блочного checkout должен подменяться шорткодом.' );

/*
 * 6. The classic theme template still exists for the shortcode to render.
 */
velotok_checkout_classic_assert(
	is_readable( dirname( __DIR__ ) . '/woocommerce/checkout/form-checkout.php' ),
	'Шаблон темы woocommerce/checkout/form-checkout.php должен существовать.'
);

if ( $GLOBALS['velotok_checkout_classic_test_failures'] ) {
	fwrite( STDERR, 'FAIL: ' . count( $GLOBALS['velotok_checkout_classic_test_failures'] ) . ' проверок классического checkout не прошли:' . PHP_EOL );

	foreach ( $GLOBALS['velotok_checkout_classic_test_failures'] as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: checkout использует классический шорткод и шаблон темы вместо блока (EBIKE-22).' . PHP_EOL;
exit( 0 );
