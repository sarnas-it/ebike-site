<?php
/**
 * Contract test for EBIKE-22: provisioning keeps checkout classic.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/provisioning-checkout-layout-test.php
 *
 * The WooCommerce checkout page must not stay on the JavaScript block. The
 * provisioning migrates it to `[woocommerce_checkout]` idempotently so the
 * theme template `woocommerce/checkout/form-checkout.php` is used.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_checkout_layout_options'] = array( 'woocommerce_checkout_page_id' => 12 );
$GLOBALS['velotok_checkout_layout_page']    = null;
$GLOBALS['velotok_checkout_layout_updates'] = array();

/**
 * Read an option stub.
 *
 * @param string $name    Option name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['velotok_checkout_layout_options'] )
		? $GLOBALS['velotok_checkout_layout_options'][ $name ]
		: $default;
}

/**
 * Update an option stub.
 *
 * @param string $name  Option name.
 * @param mixed  $value Value.
 * @return bool
 */
function update_option( $name, $value ) {
	$GLOBALS['velotok_checkout_layout_options'][ $name ] = $value;

	return true;
}

/**
 * Read a post stub.
 *
 * @param int $post_id Post ID.
 * @return object|null
 */
function get_post( $post_id ) {
	$page = $GLOBALS['velotok_checkout_layout_page'];

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
	$GLOBALS['velotok_checkout_layout_updates'][] = $postarr;

	if ( is_object( $GLOBALS['velotok_checkout_layout_page'] ) ) {
		$GLOBALS['velotok_checkout_layout_page']->post_content = $postarr['post_content'];
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

require_once dirname( __DIR__ ) . '/includes/provisioning.php';

$failures = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_checkout_layout_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = velotok_core_provisioning_contract();

velotok_checkout_layout_assert( isset( $contract['checkout']['layout'] ) && 'classic' === $contract['checkout']['layout'], 'Провижининг должен объявлять классический checkout.' );
velotok_checkout_layout_assert( isset( $contract['checkout']['shortcode'] ) && '[woocommerce_checkout]' === $contract['checkout']['shortcode'], 'Контракт должен содержать шорткод [woocommerce_checkout].' );

$GLOBALS['velotok_checkout_layout_page'] = (object) array(
	'ID'           => 12,
	'post_content' => '<!-- wp:woocommerce/checkout {"align":"wide"} /-->',
);

$result = array( 'warnings' => array() );
velotok_core_provisioning_apply_checkout_layout( $result );

velotok_checkout_layout_assert( true === $result['checkout_layout']['applied'], 'Блочная страница checkout должна мигрировать на шорткод.' );
velotok_checkout_layout_assert( '[woocommerce_checkout]' === $GLOBALS['velotok_checkout_layout_page']->post_content, 'После провижининга checkout должен использовать шорткод.' );
velotok_checkout_layout_assert( 1 === count( $GLOBALS['velotok_checkout_layout_updates'] ), 'Провижининг должен обновить страницу ровно один раз.' );

$result = array( 'warnings' => array() );
velotok_core_provisioning_apply_checkout_layout( $result );

velotok_checkout_layout_assert( false === $result['checkout_layout']['applied'], 'Повторный провижининг не должен менять классическую страницу.' );
velotok_checkout_layout_assert( 1 === count( $GLOBALS['velotok_checkout_layout_updates'] ), 'Повторный провижининг не должен писать в базу.' );

$GLOBALS['velotok_checkout_layout_page']->post_content = '<p>Авторское содержимое</p>';
$result = array( 'warnings' => array() );
velotok_core_provisioning_apply_checkout_layout( $result );

velotok_checkout_layout_assert( false === $result['checkout_layout']['applied'], 'Авторское содержимое checkout не должно перезаписываться.' );
velotok_checkout_layout_assert( '<p>Авторское содержимое</p>' === $GLOBALS['velotok_checkout_layout_page']->post_content, 'Провижининг не должен терять авторское содержимое.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок классического checkout в провижининге не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: провижининг переводит checkout на классический шорткод и шаблон темы (EBIKE-22).' . PHP_EOL;
exit( 0 );
