<?php
/**
 * Regression test for TESTEB-27: the checkout page is unavailable with an empty cart.
 *
 * Standalone:
 *   php wp/themes/velotok/tests/checkout-empty-cart-test.php
 *
 * The template is rendered for a logged-in customer. With an empty WooCommerce
 * cart it must not render the checkout form and must point back to the cart.
 * A non-empty cart still renders the checkout form.
 *
 * @package Velotok
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

class Velotok_Checkout_Empty_Template_Cart {
	private $empty;

	public function __construct( $empty ) {
		$this->empty = (bool) $empty;
	}

	public function is_empty() {
		return $this->empty;
	}

	public function get_cart_contents_count() {
		return $this->empty ? 0 : 1;
	}

	public function get_cart() {
		if ( $this->empty ) {
			return array();
		}

		return array(
			array(
				'product_id' => 10,
				'quantity'   => 1,
			),
		);
	}
}

class Velotok_Checkout_Empty_Template_Checkout {
	public function get_checkout_fields() {
		return array();
	}
}

class Velotok_Checkout_Empty_Template_WC {
	public $cart;

	public function __construct( $empty ) {
		$this->cart = new Velotok_Checkout_Empty_Template_Cart( $empty );
	}

	public function checkout() {
		return new Velotok_Checkout_Empty_Template_Checkout();
	}
}

$GLOBALS['velotok_checkout_empty_template_wc'] = new Velotok_Checkout_Empty_Template_WC( true );

function WC() {
	return $GLOBALS['velotok_checkout_empty_template_wc'];
}

function is_user_logged_in() {
	return true;
}

function do_action( $hook ) {
}

function apply_filters( $hook, $value ) {
	return $value;
}

function esc_html( $value ) {
	return (string) $value;
}

function esc_html__( $value, $domain = null ) {
	return (string) $value;
}

function esc_html_e( $value, $domain = null ) {
	echo (string) $value;
}

function esc_url( $value ) {
	return (string) $value;
}

function __( $value, $domain = null ) {
	return (string) $value;
}

function wc_get_checkout_url() {
	return 'https://example.test/checkout/';
}

function wc_get_cart_url() {
	return 'https://example.test/cart/';
}

function wc_get_page_permalink( $page ) {
	return 'myaccount' === $page ? 'https://example.test/my-account/' : 'https://example.test/';
}

function wp_login_url( $redirect = '' ) {
	return 'https://example.test/wp-login.php';
}

$template = dirname( __DIR__ ) . '/woocommerce/checkout/form-checkout.php';

ob_start();
require $template;
$empty_html = ob_get_clean();

$GLOBALS['velotok_checkout_empty_template_wc'] = new Velotok_Checkout_Empty_Template_WC( false );

ob_start();
require $template;
$filled_html = ob_get_clean();

$failures = array();

function velotok_checkout_empty_template_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

velotok_checkout_empty_template_assert( false === strpos( $empty_html, '<form' ), 'Пустая корзина не должна показывать форму оформления заказа.' );
velotok_checkout_empty_template_assert( false !== strpos( $empty_html, 'Нельзя оформить заказ с пустой корзиной.' ), 'Для пустой корзины должно показываться объяснение.' );
velotok_checkout_empty_template_assert( false !== strpos( $empty_html, 'https://example.test/cart/' ), 'Для пустой корзины должна быть ссылка на корзину.' );
velotok_checkout_empty_template_assert( false !== strpos( $empty_html, 'Перейти в корзину' ), 'Для пустой корзины должна быть ссылка перехода в корзину.' );
velotok_checkout_empty_template_assert( false !== strpos( $filled_html, '<form' ), 'С товарами в корзине форма оформления должна показываться.' );
velotok_checkout_empty_template_assert( false === strpos( $filled_html, 'Нельзя оформить заказ с пустой корзиной.' ), 'При непустой корзине не должно быть сообщения о пустой корзине.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок шаблона оформления при пустой корзине не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: оформление заказа недоступно при пустой корзине (TESTEB-27).' . PHP_EOL;
exit( 0 );
