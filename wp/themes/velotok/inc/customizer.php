<?php
/**
 * Настройки темы в «Настройщике».
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

/**
 * Значения по умолчанию для редактируемых текстов.
 *
 * @return array<string,string>
 */
function velotok_customizer_defaults() {
	return array(
		'topbar_text'         => __( 'Бесплатная доставка при заказе от 50 000 ₽', 'velotok' ),
		'topbar_note'         => __( 'Москва, ежедневно 10:00–21:00', 'velotok' ),
		'hero_eyebrow'        => __( 'Электровелосипеды с 2018 года', 'velotok' ),
		'hero_title'          => __( 'Едете вы.', 'velotok' ),
		'hero_title_accent'   => __( 'Работает мотор.', 'velotok' ),
		'hero_lead'           => __( 'Семь проверенных моделей для города, гор и дачи. Привозим собранными, настраиваем посадку и объясняем, как обращаться с батареей.', 'velotok' ),
		'hero_primary_label'  => __( 'Выбрать велосипед', 'velotok' ),
		'hero_secondary_label' => __( 'Записаться на тест-драйв', 'velotok' ),
		'phone'               => '+7 (495) 123-45-67',
		'email'               => 'hello@velotok.ru',
		'address'             => __( 'Москва, Нижняя Сыромятническая, 10, стр. 2', 'velotok' ),
		'hours'               => __( 'Ежедневно, 10:00–21:00', 'velotok' ),
		'legal'               => __( '© 2026 «ВЕЛОТОК». ИП Соколов А. В., ИНН 770123456789, ОГРНИП 320770000123456. Цены на сайте не являются публичной офертой. <a href="#privacy">Политика конфиденциальности</a> · <a href="#lead">Публичная оферта</a>', 'velotok' ),
		'footer_note'         => __( 'Сделано для тех, кто ездит', 'velotok' ),
	);
}

/**
 * Возвращает значение настройки темы.
 *
 * @param string $key Ключ настройки.
 * @return string
 */
function velotok_get_option( $key ) {
	$defaults = velotok_customizer_defaults();
	$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';

	return (string) get_theme_mod( 'velotok_' . $key, $default );
}

/**
 * Телефон для ссылки tel:.
 *
 * @return string
 */
function velotok_phone_href() {
	$phone = preg_replace( '/[^0-9+]/', '', velotok_get_option( 'phone' ) );

	return 'tel:' . ltrim( (string) $phone, '+' );
}

/**
 * Очищает телефон.
 *
 * @param string $value Значение.
 * @return string
 */
function velotok_sanitize_phone( $value ) {
	return sanitize_text_field( $value );
}

/**
 * Регистрирует настройки.
 *
 * @param WP_Customize_Manager $wp_customize Настройщик.
 * @return void
 */
function velotok_customize_register( $wp_customize ) {
	$wp_customize->add_section( 'velotok_content', array(
		'title'       => __( 'Велоток: тексты и контакты', 'velotok' ),
		'priority'    => 30,
		'description' => __( 'Тексты первого экрана, контакты и юридическая строка. Изменения применяются без правки кода.', 'velotok' ),
	) );

	$fields = array(
		'topbar_text'          => array( 'label' => __( 'Верхняя полоса: текст', 'velotok' ), 'type' => 'text', 'sanitize' => 'sanitize_text_field' ),
		'topbar_note'          => array( 'label' => __( 'Верхняя полоса: город и график', 'velotok' ), 'type' => 'text', 'sanitize' => 'sanitize_text_field' ),
		'hero_eyebrow'         => array( 'label' => __( 'Первый экран: надзаголовок', 'velotok' ), 'type' => 'text', 'sanitize' => 'sanitize_text_field' ),
		'hero_title'           => array( 'label' => __( 'Первый экран: заголовок', 'velotok' ), 'type' => 'text', 'sanitize' => 'sanitize_text_field' ),
		'hero_title_accent'    => array( 'label' => __( 'Первый экран: акцентная часть заголовка', 'velotok' ), 'type' => 'text', 'sanitize' => 'sanitize_text_field' ),
		'hero_lead'            => array( 'label' => __( 'Первый экран: описание', 'velotok' ), 'type' => 'textarea', 'sanitize' => 'sanitize_textarea_field' ),
		'hero_primary_label'   => array( 'label' => __( 'Первый экран: основная кнопка', 'velotok' ), 'type' => 'text', 'sanitize' => 'sanitize_text_field' ),
		'hero_secondary_label' => array( 'label' => __( 'Первый экран: вторая кнопка', 'velotok' ), 'type' => 'text', 'sanitize' => 'sanitize_text_field' ),
		'phone'                => array( 'label' => __( 'Телефон', 'velotok' ), 'type' => 'text', 'sanitize' => 'velotok_sanitize_phone' ),
		'email'                => array( 'label' => __( 'Электронная почта', 'velotok' ), 'type' => 'email', 'sanitize' => 'sanitize_email' ),
		'address'              => array( 'label' => __( 'Адрес шоурума', 'velotok' ), 'type' => 'text', 'sanitize' => 'sanitize_text_field' ),
		'hours'                => array( 'label' => __( 'График работы', 'velotok' ), 'type' => 'text', 'sanitize' => 'sanitize_text_field' ),
		'legal'                => array( 'label' => __( 'Юридическая строка подвала', 'velotok' ), 'type' => 'textarea', 'sanitize' => 'wp_kses_post' ),
		'footer_note'          => array( 'label' => __( 'Подпись в подвале', 'velotok' ), 'type' => 'text', 'sanitize' => 'sanitize_text_field' ),
	);

	foreach ( $fields as $key => $field ) {
		$wp_customize->add_setting( 'velotok_' . $key, array(
			'default'           => velotok_customizer_defaults()[ $key ],
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'sanitize_callback' => $field['sanitize'],
		) );

		$wp_customize->add_control( 'velotok_' . $key, array(
			'label'   => $field['label'],
			'section' => 'velotok_content',
			'type'    => $field['type'],
		) );
	}
}
add_action( 'customize_register', 'velotok_customize_register' );
