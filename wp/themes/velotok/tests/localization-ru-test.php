<?php
/**
 * Behaviour test for EBIKE-20: the storefront is localised to Russian.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/localization-ru-test.php
 *
 * The test boots a minimal WordPress option/post stub, loads the theme
 * localisation layer and checks the Russian locale, the WooCommerce page and
 * UI strings, the price/date formats and the idempotent store localisation
 * routine. It fails when the storefront keeps the en_US labels or the US price
 * format (119,900 ₽) instead of 119 900 ₽.
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

$GLOBALS['velotok_l10n_options']        = array();
$GLOBALS['velotok_l10n_posts']          = array();
$GLOBALS['velotok_l10n_updated_posts']  = array();
$GLOBALS['velotok_l10n_filters']        = array();
$GLOBALS['velotok_l10n_is_admin']       = false;
$GLOBALS['velotok_l10n_switched_locale'] = null;
$failures                               = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_l10n_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * WordPress filter registration stub.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $accepted Accepted args.
 * @return bool
 */
function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) {
	$GLOBALS['velotok_l10n_filters'][ $hook ][] = $callback;

	return true;
}

/**
 * Whether the current request is an admin request.
 *
 * @return bool
 */
function is_admin() {
	return ! empty( $GLOBALS['velotok_l10n_is_admin'] );
}

/**
 * Get an option from the stub store.
 *
 * @param string $name    Option name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['velotok_l10n_options'] )
		? $GLOBALS['velotok_l10n_options'][ $name ]
		: $default;
}

/**
 * Update an option in the stub store.
 *
 * @param string $name  Option name.
 * @param mixed  $value Option value.
 * @return bool
 */
function update_option( $name, $value ) {
	$GLOBALS['velotok_l10n_options'][ $name ] = $value;

	return true;
}

/**
 * Get a post from the stub store.
 *
 * @param int $post_id Post ID.
 * @return object|null
 */
function get_post( $post_id ) {
	return isset( $GLOBALS['velotok_l10n_posts'][ $post_id ] )
		? $GLOBALS['velotok_l10n_posts'][ $post_id ]
		: null;
}

/**
 * Update a post in the stub store.
 *
 * @param array $data Post data.
 * @return int
 */
function wp_update_post( $data ) {
	$GLOBALS['velotok_l10n_updated_posts'][] = $data;

	$post_id = isset( $data['ID'] ) ? (int) $data['ID'] : 0;

	if ( $post_id > 0 && isset( $GLOBALS['velotok_l10n_posts'][ $post_id ] ) ) {
		$GLOBALS['velotok_l10n_posts'][ $post_id ]->post_title = $data['post_title'];
	}

	return $post_id;
}

/**
 * Switch the locale in the stub store.
 *
 * @param string $locale Locale code.
 * @return bool
 */
function switch_to_locale( $locale ) {
	$GLOBALS['velotok_l10n_switched_locale'] = $locale;

	return true;
}

require_once dirname( __DIR__ ) . '/inc/localization.php';

/*
 * 1. The localisation layer exposes every function the setup relies on.
 */
$required_functions = array(
	'velotok_localization_translations',
	'velotok_localization_translate',
	'velotok_localization_filter_gettext',
	'velotok_localization_locale',
	'velotok_localization_price_format',
	'velotok_localization_page_titles',
	'velotok_localization_language_packs',
	'velotok_localization_install_language_packs',
	'velotok_localize_store',
);

foreach ( $required_functions as $function_name ) {
	velotok_l10n_assert( function_exists( $function_name ), 'Не определена функция локализации: ' . $function_name . '.' );
}

/*
 * 2. The storefront locale is forced to ru_RU, while the admin keeps its own.
 */
velotok_l10n_assert( 'ru_RU' === velotok_localization_locale( 'en_US' ), 'Витрина должна принудительно открываться на ru_RU.' );

$GLOBALS['velotok_l10n_is_admin'] = true;
velotok_l10n_assert( 'en_US' === velotok_localization_locale( 'en_US' ), 'Локаль админки не должна переопределяться.' );
$GLOBALS['velotok_l10n_is_admin'] = false;

/*
 * 3. WooCommerce and WordPress strings are Russian, including the ones from
 *    the reported symptom (Shop, Cart, Checkout, My account, Add to cart, SKU,
 *    Category, Description).
 */
$expected_strings = array(
	'Shop'         => 'Каталог',
	'Cart'         => 'Корзина',
	'Checkout'     => 'Оформление заказа',
	'My account'   => 'Мой аккаунт',
	'Add to cart'  => 'В корзину',
	'SKU:'         => 'Артикул:',
	'SKU'          => 'Артикул',
	'Category:'    => 'Категория:',
	'Category'     => 'Категория',
	'Description'  => 'Описание',
	'Place order'  => 'Оформить заказ',
	'Your cart is currently empty.' => 'Ваша корзина пока пуста.',
);

foreach ( $expected_strings as $source => $expected ) {
	$translated = velotok_localization_filter_gettext( '', $source, 'woocommerce' );
	velotok_l10n_assert( $expected === $translated, 'Строка «' . $source . '» должна переводиться как «' . $expected . '», получено «' . $translated . '».' );
}

velotok_l10n_assert( 'Готовый перевод' === velotok_localization_filter_gettext( 'Готовый перевод', 'Shop', 'woocommerce' ), 'Уже готовый перевод языкового пакета нельзя перетирать.' );
velotok_l10n_assert( 'Категории:' === velotok_localization_filter_ngettext( '', 'Category:', 'Categories:', 2, 'woocommerce' ), 'Множественное число категорий должно быть русским.' );

/*
 * 4. Prices use the Russian format: no decimals, space thousands separator and
 *    the ruble sign after the amount.
 */
velotok_l10n_assert( 0 === velotok_localization_price_decimals(), 'В ценах не должно быть копеек.' );
velotok_l10n_assert( ' ' === velotok_localization_price_thousand_separator(), 'Разделителем тысяч должен быть пробел.' );
velotok_l10n_assert( ',' === velotok_localization_price_decimal_separator(), 'Десятичным разделителем должна быть запятая.' );

$price_format = velotok_localization_price_format();
$price_html   = sprintf(
	$price_format,
	velotok_localization_currency_symbol( '$', 'RUB' ),
	number_format( 119900, velotok_localization_price_decimals(), velotok_localization_price_decimal_separator(), velotok_localization_price_thousand_separator() )
);

velotok_l10n_assert( '119 900 ₽' === $price_html, 'Цена должна выводиться как «119 900 ₽», получено «' . $price_html . '».' );
velotok_l10n_assert( false === strpos( $price_html, ',' ), 'В российской цене не должно быть US-разделителя тысяч.' );
velotok_l10n_assert( '$' === velotok_localization_currency_symbol( '$', 'USD' ), 'Символ чужой валюты не должен подменяться рублём.' );

/*
 * 5. Service page titles are Russian both through the_title and through the
 *    idempotent store localisation routine.
 */
$GLOBALS['velotok_l10n_options'] = array(
	'woocommerce_shop_page_id'      => 10,
	'woocommerce_cart_page_id'      => 11,
	'woocommerce_checkout_page_id'  => 12,
	'woocommerce_myaccount_page_id' => 13,
	'woocommerce_terms_page_id'     => 14,
	'wp_page_for_privacy_policy'    => 15,
	'WPLANG'                        => 'en_US',
	'date_format'                   => 'F j, Y',
	'time_format'                   => 'g:i a',
	'timezone_string'               => 'UTC',
	'woocommerce_price_format'      => '%1$s%2$s',
);

$GLOBALS['velotok_l10n_posts'] = array(
	10 => (object) array( 'post_title' => 'Shop' ),
	11 => (object) array( 'post_title' => 'Cart' ),
	12 => (object) array( 'post_title' => 'Checkout' ),
	13 => (object) array( 'post_title' => 'My account' ),
	14 => (object) array( 'post_title' => 'Terms and conditions' ),
	15 => (object) array( 'post_title' => 'Privacy Policy' ),
);

velotok_l10n_assert( 'Каталог' === velotok_localization_filter_title( 'Shop', 10 ), 'Заголовок страницы магазина должен быть «Каталог».' );
velotok_l10n_assert( 'Корзина' === velotok_localization_filter_title( 'Cart', 11 ), 'Заголовок корзины должен быть «Корзина».' );
velotok_l10n_assert( 'Оформление заказа' === velotok_localization_filter_title( 'Checkout', 12 ), 'Заголовок оформления заказа должен быть русским.' );
velotok_l10n_assert( 'Мой аккаунт' === velotok_localization_filter_title( 'My account', 13 ), 'Заголовок личного кабинета должен быть русским.' );

velotok_localize_store();

velotok_l10n_assert( 'ru_RU' === get_option( 'WPLANG' ), 'WPLANG должен переключаться на ru_RU.' );
velotok_l10n_assert( 'Europe/Moscow' === get_option( 'timezone_string' ), 'Часовой пояс должен быть Europe/Moscow.' );
velotok_l10n_assert( 'd.m.Y' === get_option( 'date_format' ), 'Дата должна выводиться как дд.мм.гггг.' );
velotok_l10n_assert( 'H:i' === get_option( 'time_format' ), 'Время должно выводиться как чч:мм.' );
velotok_l10n_assert( 'RUB' === get_option( 'woocommerce_currency' ), 'Валюта магазина должна быть RUB.' );
velotok_l10n_assert( '₽' === get_option( 'woocommerce_currency_symbol' ), 'Символ валюты должен быть ₽.' );
velotok_l10n_assert( 0 === get_option( 'woocommerce_price_num_decimals' ), 'Количество знаков после запятой должно быть 0.' );
velotok_l10n_assert( '%2$s %1$s' === get_option( 'woocommerce_price_format' ), 'Формат цены должен ставить символ валюты после суммы.' );
velotok_l10n_assert( 'ru_RU' === $GLOBALS['velotok_l10n_switched_locale'], 'Локаль должна переключаться на ru_RU.' );

$localized_titles = array();
foreach ( $GLOBALS['velotok_l10n_updated_posts'] as $updated ) {
	$localized_titles[ (int) $updated['ID'] ] = $updated['post_title'];
}

velotok_l10n_assert( 'Каталог' === ( isset( $localized_titles[10] ) ? $localized_titles[10] : '' ), 'Страница Shop должна стать «Каталог».' );
velotok_l10n_assert( 'Корзина' === ( isset( $localized_titles[11] ) ? $localized_titles[11] : '' ), 'Страница Cart должна стать «Корзина».' );
velotok_l10n_assert( 'Оформление заказа' === ( isset( $localized_titles[12] ) ? $localized_titles[12] : '' ), 'Страница Checkout должна стать «Оформление заказа».' );
velotok_l10n_assert( 'Мой аккаунт' === ( isset( $localized_titles[13] ) ? $localized_titles[13] : '' ), 'Страница My account должна стать «Мой аккаунт».' );

/*
 * 6. The filters are wired into WordPress and WooCommerce.
 */
foreach ( array( 'gettext', 'ngettext', 'locale', 'the_title', 'woocommerce_price_format', 'woocommerce_currency_symbol', 'woocommerce_product_add_to_cart_text' ) as $hook ) {
	velotok_l10n_assert( ! empty( $GLOBALS['velotok_l10n_filters'][ $hook ] ), 'Не зарегистрирован фильтр локализации: ' . $hook . '.' );
}

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок локализации не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: витрина и служебные страницы WooCommerce локализованы на русский (EBIKE-20).' . PHP_EOL;
exit( 0 );
