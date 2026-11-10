<?php
/**
 * Локализация витрины: русский язык, тексты WooCommerce и форматы.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

/**
 * Русские переводы строк, которые выводит WooCommerce и WordPress.
 *
 * Словарь работает как резервный слой: если языковой пакет ru_RU уже
 * установлен, gettext вернёт готовый перевод и словарь не вмешивается.
 *
 * @return array<string,string>
 */
function velotok_localization_translations() {
	static $translations = null;

	if ( null !== $translations ) {
		return $translations;
	}

	$translations = array(
		'Shop'                                  => 'Каталог',
		'Home'                                  => 'Главная',
		'Cart'                                  => 'Корзина',
		'Checkout'                              => 'Оформление заказа',
		'My account'                            => 'Мой аккаунт',
		'My Account'                            => 'Мой аккаунт',
		'Product'                               => 'Товар',
		'Products'                              => 'Товары',
		'Price'                                 => 'Цена',
		'Quantity'                              => 'Количество',
		'Subtotal'                              => 'Промежуточный итог',
		'Cart subtotal'                         => 'Сумма корзины',
		'Cart totals'                           => 'Итоги корзины',
		'Total'                                 => 'Итого',
		'Order total'                           => 'Итого заказа',
		'Tax'                                   => 'Налог',
		'Discount'                              => 'Скидка',
		'Shipping'                              => 'Доставка',
		'Free shipping'                         => 'Бесплатная доставка',
		'Flat rate'                             => 'Фиксированная стоимость',
		'Local pickup'                          => 'Самовывоз',
		'Coupon code'                           => 'Код купона',
		'Apply coupon'                          => 'Применить купон',
		'Update cart'                           => 'Обновить корзину',
		'Proceed to checkout'                   => 'Перейти к оформлению',
		'Proceed to Checkout'                   => 'Перейти к оформлению',
		'Continue to checkout'                  => 'Перейти к оформлению',
		'Your cart is currently empty.'         => 'Ваша корзина пока пуста.',
		'Your cart is currently empty!'         => 'Ваша корзина пока пуста.',
		'Return to shop'                        => 'Вернуться в каталог',
		'Continue shopping'                     => 'Продолжить покупки',
		'Remove'                                => 'Удалить',
		'Remove this item'                      => 'Удалить этот товар',
		'Available on backorder'                => 'Доступно под заказ',
		'Out of stock'                          => 'Нет в наличии',
		'In stock'                              => 'В наличии',
		'Sale!'                                 => 'Скидка!',
		'Add to cart'                           => 'В корзину',
		'Add to basket'                         => 'В корзину',
		'Select options'                        => 'Выбрать параметры',
		'Read more'                             => 'Подробнее',
		'View cart'                             => 'Посмотреть корзину',
		'View products'                         => 'Посмотреть товары',
		'Description'                           => 'Описание',
		'Additional information'                => 'Дополнительная информация',
		'Reviews'                               => 'Отзывы',
		'Reviews (%d)'                          => 'Отзывы (%d)',
		'Related products'                      => 'Похожие товары',
		'Related Products'                      => 'Похожие товары',
		'You may also like&hellip;'             => 'Вам также может понравиться…',
		'You may also like…'                    => 'Вам также может понравиться…',
		'You may also like'                     => 'Вам также может понравиться',
		'No products were found matching your selection.' => 'Товары не найдены.',
		'No products found'                     => 'Товары не найдены',
		'SKU:'                                  => 'Артикул:',
		'SKU'                                   => 'Артикул',
		'Category:'                             => 'Категория:',
		'Categories:'                           => 'Категории:',
		'Category'                              => 'Категория',
		'Categories'                            => 'Категории',
		'Tag:'                                  => 'Метка:',
		'Tags:'                                 => 'Метки:',
		'Tag'                                   => 'Метка',
		'Tags'                                  => 'Метки',
		'Billing details'                       => 'Данные для оплаты',
		'Shipping details'                      => 'Данные для доставки',
		'Billing address'                       => 'Платёжный адрес',
		'Shipping address'                      => 'Адрес доставки',
		'First name'                            => 'Имя',
		'Last name'                             => 'Фамилия',
		'Company name'                          => 'Название компании',
		'Country / Region'                      => 'Страна / Регион',
		'Street address'                        => 'Адрес улицы',
		'Apartment, suite, etc.'                => 'Квартира, офис и т. д.',
		'Town / City'                           => 'Город',
		'State / County'                        => 'Область / район',
		'Postcode / ZIP'                        => 'Почтовый индекс',
		'Phone'                                 => 'Телефон',
		'Email address'                         => 'Адрес email',
		'Email'                                 => 'Email',
		'Order notes'                           => 'Примечание к заказу',
		'Order notes (optional)'                => 'Примечание к заказу (необязательно)',
		'Add a note to your order'              => 'Добавить примечание к заказу',
		'Your order'                            => 'Ваш заказ',
		'Order review'                          => 'Проверка заказа',
		'Place order'                           => 'Оформить заказ',
		'Payment method'                        => 'Способ оплаты',
		'Create an account?'                    => 'Создать аккаунт?',
		'Ship to a different address?'          => 'Доставить по другому адресу?',
		'Required'                              => 'Обязательно',
		'Optional'                              => 'Необязательно',
		'Terms and conditions'                  => 'Условия использования',
		'I have read and agree to the website terms and conditions' => 'Я прочитал(а) и согласен(на) с условиями сайта',
		'Dashboard'                             => 'Главная',
		'Orders'                                => 'Заказы',
		'Downloads'                             => 'Загрузки',
		'Addresses'                             => 'Адреса',
		'Account details'                       => 'Данные аккаунта',
		'Account Details'                       => 'Данные аккаунта',
		'Log out'                               => 'Выйти',
		'Logout'                                => 'Выйти',
		'Login'                                 => 'Войти',
		'Register'                              => 'Регистрация',
		'Username or email address'             => 'Имя пользователя или email',
		'Password'                              => 'Пароль',
		'Remember me'                           => 'Запомнить меня',
		'Lost your password?'                   => 'Забыли пароль?',
		'Lost password'                         => 'Забыли пароль',
		'Display name'                          => 'Отображаемое имя',
		'Change password'                       => 'Сменить пароль',
		'Current password (leave blank to leave unchanged)' => 'Текущий пароль (оставьте пустым, чтобы не менять)',
		'New password (leave blank to leave unchanged)' => 'Новый пароль (оставьте пустым, чтобы не менять)',
		'Confirm new password'                  => 'Подтвердите новый пароль',
		'Save changes'                          => 'Сохранить изменения',
		'No order has been made yet.'           => 'Заказов пока нет.',
		'No downloads available yet.'           => 'Загрузок пока нет.',
		'View'                                  => 'Просмотр',
		'Order'                                 => 'Заказ',
		'Order #%s'                             => 'Заказ №%s',
		'Date'                                  => 'Дата',
		'Status'                                => 'Статус',
		'Actions'                               => 'Действия',
		'Order details'                         => 'Детали заказа',
		'Customer details'                      => 'Данные покупателя',
		'Note'                                  => 'Примечание',
		'Notes'                                 => 'Примечания',
	);

	return $translations;
}

/**
 * Переводит одну строку из резервного словаря.
 *
 * @param string $text Исходная строка.
 * @return string
 */
function velotok_localization_translate( $text ) {
	$translations = velotok_localization_translations();

	if ( isset( $translations[ $text ] ) ) {
		return $translations[ $text ];
	}

	return $text;
}

/**
 * Резервный перевод gettext для WooCommerce и ядра WordPress.
 *
 * @param string $translation Текущий перевод.
 * @param string $text        Исходная строка.
 * @param string $domain      Текстовый домен.
 * @return string
 */
function velotok_localization_filter_gettext( $translation, $text, $domain = 'default' ) {
	if ( '' !== $translation && $translation !== $text ) {
		return $translation;
	}

	return velotok_localization_translate( $text );
}

/**
 * Резервный перевод множественного числа.
 *
 * @param string $translation Текущий перевод.
 * @param string $single      Форма единственного числа.
 * @param string $plural      Форма множественного числа.
 * @param int    $number      Число.
 * @param string $domain      Текстовый домен.
 * @return string
 */
function velotok_localization_filter_ngettext( $translation, $single, $plural, $number, $domain = 'default' ) {
	$plural_map = array(
		'Category:' => array( 'Категория:', 'Категории:' ),
		'Tag:'      => array( 'Метка:', 'Метки:' ),
	);

	if ( isset( $plural_map[ $single ] ) ) {
		return 1 === (int) $number ? $plural_map[ $single ][0] : $plural_map[ $single ][1];
	}

	if ( '' !== $translation && $translation !== $single && $translation !== $plural ) {
		return $translation;
	}

	return velotok_localization_translate( $translation );
}

/**
 * Принудительно включает русскую локаль на витрине.
 *
 * @param string $locale Текущая локаль.
 * @return string
 */
function velotok_localization_locale( $locale ) {
	if ( function_exists( 'is_admin' ) && is_admin() ) {
		return $locale;
	}

	return 'ru_RU';
}

/**
 * Количество знаков после запятой в ценах.
 *
 * @return int
 */
function velotok_localization_price_decimals() {
	return 0;
}

/**
 * Разделитель тысяч в ценах.
 *
 * @return string
 */
function velotok_localization_price_thousand_separator() {
	return ' ';
}

/**
 * Десятичный разделитель в ценах.
 *
 * @return string
 */
function velotok_localization_price_decimal_separator() {
	return ',';
}

/**
 * Формат цены: сумма, неразрывный пробел, символ валюты.
 *
 * @return string
 */
function velotok_localization_price_format() {
	return '%2$s %1$s';
}

/**
 * Символ валюты для рубля.
 *
 * @param string $symbol   Текущий символ.
 * @param string $currency Код валюты.
 * @return string
 */
function velotok_localization_currency_symbol( $symbol, $currency = '' ) {
	if ( 'RUB' === $currency || '' === $currency ) {
		return '₽';
	}

	return $symbol;
}

/**
 * Текст кнопки добавления товара в корзину.
 *
 * @param string $text Текущий текст.
 * @return string
 */
function velotok_localization_add_to_cart_text( $text ) {
	return velotok_localization_translate( $text );
}

/**
 * Текст кнопки оформления заказа.
 *
 * @param string $text Текущий текст.
 * @return string
 */
function velotok_localization_place_order_text( $text ) {
	return velotok_localization_translate( $text );
}

/**
 * Русские названия служебных страниц WooCommerce.
 *
 * @return array<string,string>
 */
function velotok_localization_page_titles() {
	return array(
		'woocommerce_shop_page_id'      => 'Каталог',
		'woocommerce_cart_page_id'      => 'Корзина',
		'woocommerce_checkout_page_id'  => 'Оформление заказа',
		'woocommerce_myaccount_page_id' => 'Мой аккаунт',
		'woocommerce_terms_page_id'     => 'Условия использования',
		'wp_page_for_privacy_policy'    => 'Политика конфиденциальности',
	);
}

/**
 * Подставляет русские названия служебных страниц.
 *
 * @param string $title   Заголовок записи.
 * @param int    $post_id ID записи.
 * @return string
 */
function velotok_localization_filter_title( $title, $post_id = 0 ) {
	if ( function_exists( 'is_admin' ) && is_admin() ) {
		return $title;
	}

	$post_id = (int) $post_id;

	if ( $post_id <= 0 ) {
		return $title;
	}

	foreach ( velotok_localization_page_titles() as $option => $localized_title ) {
		if ( ! function_exists( 'get_option' ) ) {
			break;
		}

		if ( $post_id === (int) get_option( $option ) ) {
			return $localized_title;
		}
	}

	return $title;
}

/**
 * Языковые пакеты, без которых витрина остаётся на en_US.
 *
 * Ядро WordPress отвечает за даты, меню и общие строки, WooCommerce — за
 * Shop/Cart/Checkout/My account, Add to cart, SKU, Category и Description.
 *
 * @return array<int,array{type:string,slug:string,language:string,activate:bool}>
 */
function velotok_localization_language_packs() {
	return array(
		array(
			'type'     => 'core',
			'slug'     => 'ru_RU',
			'language' => 'ru_RU',
			'activate' => true,
		),
		array(
			'type'     => 'plugin',
			'slug'     => 'woocommerce',
			'language' => 'ru_RU',
			'activate' => false,
		),
	);
}

/**
 * WP-CLI команды установки обязательных языковых пакетов.
 *
 * @return string[]
 */
function velotok_localization_language_pack_commands() {
	$commands = array();

	foreach ( velotok_localization_language_packs() as $pack ) {
		if ( 'core' === $pack['type'] ) {
			$command = sprintf( 'language core install %s', $pack['language'] );
		} else {
			$command = sprintf(
				'language %s install %s %s',
				$pack['type'],
				$pack['slug'],
				$pack['language']
			);
		}

		if ( ! empty( $pack['activate'] ) ) {
			$command .= ' --activate';
		}

		$commands[] = $command;
	}

	return $commands;
}

/**
 * Устанавливает языковые пакеты через переданный исполнитель или WP-CLI.
 *
 * Именно эта функция ставит ru_RU, а не только форсирует локаль фильтром:
 * без языкового пакета gettext-переводы WooCommerce остаются английскими.
 *
 * @param callable|null $runner Получает команду, возвращает успех установки.
 * @return array<string,bool> Результат по каждой команде.
 */
function velotok_localization_install_language_packs( $runner = null ) {
	$results = array();

	foreach ( velotok_localization_language_pack_commands() as $command ) {
		if ( null !== $runner ) {
			$results[ $command ] = (bool) call_user_func( $runner, $command );
			continue;
		}

		if ( ! class_exists( 'WP_CLI' ) ) {
			$results[ $command ] = false;
			continue;
		}

		try {
			WP_CLI::runcommand( $command, array( 'exit_error' => false ) );
			$results[ $command ] = true;
		} catch ( \Throwable $error ) {
			$results[ $command ] = false;
		}
	}

	return $results;
}

/**
 * Пути установленных файлов переводов ru_RU.
 *
 * @return array<string,string>
 */
function velotok_localization_language_pack_paths() {
	$lang_dir = defined( 'WP_LANG_DIR' ) ? WP_LANG_DIR : '';

	return array(
		'core'        => $lang_dir . '/ru_RU.mo',
		'woocommerce' => $lang_dir . '/plugins/woocommerce-ru_RU.mo',
	);
}

/**
 * Проверяет, что файлы языковых пакетов ru_RU действительно на месте.
 *
 * @return array<string,bool>
 */
function velotok_localization_language_packs_installed() {
	$installed = array();

	foreach ( velotok_localization_language_pack_paths() as $name => $path ) {
		$installed[ $name ] = '' !== $path && file_exists( $path );
	}

	return $installed;
}

/**
 * Применяет русскую локаль, форматы цен и дат, названия страниц.
 *
 * Функция идемпотентна и вызывается из setup/CLI после установки WordPress.
 * В окружении WP-CLI она также устанавливает языковые пакеты ru_RU.
 *
 * @return void
 */
function velotok_localize_store() {
	if ( ! function_exists( 'update_option' ) ) {
		return;
	}

	if ( class_exists( 'WP_CLI' ) ) {
		velotok_localization_install_language_packs();
	}

	$options = array(
		'WPLANG'                        => 'ru_RU',
		'timezone_string'               => 'Europe/Moscow',
		'date_format'                   => 'd.m.Y',
		'time_format'                   => 'H:i',
		'start_of_week'                 => 1,
		'woocommerce_currency'          => 'RUB',
		'woocommerce_currency_symbol'   => '₽',
		'woocommerce_price_num_decimals' => 0,
		'woocommerce_price_decimal_sep' => ',',
		'woocommerce_price_thousand_sep' => ' ',
		'woocommerce_price_format'      => '%2$s %1$s',
		'woocommerce_default_country'   => 'RU:MOW',
	);

	foreach ( $options as $name => $value ) {
		update_option( $name, $value );
	}

	if ( function_exists( 'switch_to_locale' ) ) {
		switch_to_locale( 'ru_RU' );
	}

	if ( ! function_exists( 'get_option' ) || ! function_exists( 'get_post' ) || ! function_exists( 'wp_update_post' ) ) {
		return;
	}

	foreach ( velotok_localization_page_titles() as $option => $title ) {
		$page_id = (int) get_option( $option );

		if ( $page_id <= 0 ) {
			continue;
		}

		$page = get_post( $page_id );

		if ( $page && $page->post_title === $title ) {
			continue;
		}

		wp_update_post(
			array(
				'ID'         => $page_id,
				'post_title' => $title,
			)
		);
	}
}

add_filter( 'gettext', 'velotok_localization_filter_gettext', 20, 3 );
add_filter( 'ngettext', 'velotok_localization_filter_ngettext', 20, 5 );
add_filter( 'locale', 'velotok_localization_locale', 99 );
add_filter( 'the_title', 'velotok_localization_filter_title', 10, 2 );
add_filter( 'wc_get_price_decimals', 'velotok_localization_price_decimals', 99 );
add_filter( 'wc_get_price_thousand_separator', 'velotok_localization_price_thousand_separator', 99 );
add_filter( 'wc_get_price_decimal_separator', 'velotok_localization_price_decimal_separator', 99 );
add_filter( 'wc_get_price_format', 'velotok_localization_price_format', 99 );
add_filter( 'woocommerce_price_format', 'velotok_localization_price_format', 99 );
add_filter( 'woocommerce_currency_symbol', 'velotok_localization_currency_symbol', 99, 2 );
add_filter( 'woocommerce_product_single_add_to_cart_text', 'velotok_localization_add_to_cart_text', 99 );
add_filter( 'woocommerce_product_add_to_cart_text', 'velotok_localization_add_to_cart_text', 99 );
add_filter( 'woocommerce_order_button_text', 'velotok_localization_place_order_text', 99 );
