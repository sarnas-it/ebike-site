<?php
/**
 * Idempotent Contact Form 7 / Flamingo lead form setup.
 *
 * Run from the WordPress root: wp eval-file wp/cli/lead-forms.php
 *
 * Creates (or updates) the «test-drive» and «consultation» forms used by
 * template-parts/lead-form.php. Every form sends a notification to the store
 * e-mail and Flamingo stores the submission in the database.
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Write a setup log line.
 *
 * @param string $message Message.
 * @return void
 */
function velotok_lead_forms_log( $message ) {
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::log( $message );
		return;
	}

	echo $message . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
}

/**
 * Resolve the e-mail address that receives lead notifications.
 *
 * An explicit VELOTOK_LEAD_RECIPIENT wins, then the «Электронная почта»
 * setting from the Customizer, then the site administrator address.
 *
 * @return string
 */
function velotok_lead_forms_recipient() {
	$candidate = getenv( 'VELOTOK_LEAD_RECIPIENT' );

	if ( ! $candidate && function_exists( 'get_theme_mod' ) ) {
		$candidate = (string) get_theme_mod( 'velotok_email', 'hello@velotok.ru' );
	}

	if ( ! $candidate && function_exists( 'get_option' ) ) {
		$candidate = (string) get_option( 'admin_email' );
	}

	$candidate = trim( (string) $candidate );

	if ( function_exists( 'is_email' ) && is_email( $candidate ) ) {
		return $candidate;
	}

	$admin_email = function_exists( 'get_option' ) ? (string) get_option( 'admin_email' ) : '';

	if ( function_exists( 'is_email' ) && is_email( $admin_email ) ) {
		return $admin_email;
	}

	return 'hello@velotok.ru';
}

/**
 * Default Contact Form 7 messages, localised to Russian.
 *
 * @return array<string, string>
 */
function velotok_lead_forms_messages() {
	return array(
		'mail_sent_ok'        => 'Спасибо! Заявка отправлена, мы свяжемся с вами в течение рабочего дня.',
		'mail_sent_ng'        => 'Не удалось отправить заявку. Попробуйте ещё раз или позвоните нам.',
		'validation_error'    => 'Проверьте, пожалуйста, поля формы и попробуйте ещё раз.',
		'spam'                => 'Не удалось отправить заявку: сработала защита от спама.',
		'accept_terms'        => 'Примите условия перед отправкой заявки.',
		'invalid_required'    => 'Заполните это поле.',
		'invalid_too_long'    => 'Это поле слишком длинное.',
		'invalid_too_short'   => 'Это поле слишком короткое.',
		'invalid_date'        => 'Укажите корректную дату.',
		'invalid_email'       => 'Укажите корректный адрес электронной почты.',
		'invalid_url'         => 'Укажите корректный URL.',
		'invalid_tel'         => 'Укажите корректный номер телефона.',
		'invalid_number'      => 'Укажите корректное число.',
		'invalid_date_format' => 'Формат даты неверный.',
		'upload_failed'       => 'Не удалось загрузить файл.',
		'upload_file_type_invalid' => 'Этот тип файла загружать нельзя.',
		'upload_file_too_large'    => 'Файл слишком большой.',
		'upload_failed_php_error'  => 'Ошибка при загрузке файла.',
	);
}

/**
 * Build the mail template for a form.
 *
 * @param string $subject   Notification subject.
 * @param string $recipient Recipient e-mail address.
 * @param string $intro     First line of the message body.
 * @return array<string, mixed>
 */
function velotok_lead_forms_mail( $subject, $recipient, $intro ) {
	return array(
		'subject'            => $subject,
		'sender'             => '[_site_title] <[_site_admin_email]>',
		'body'               => $intro . "\n\n"
			. "Имя: [your-name]\n"
			. "Телефон: [your-phone]\n"
			. "E-mail: [your-email]\n"
			. "Модель: [bike-model]\n"
			. "Желаемая дата: [test-drive-date]\n"
			. "Сообщение:\n[your-message]\n\n"
			. "--\n"
			. "Отправлено с сайта [_site_title] ([_site_url])",
		'recipient'          => $recipient,
		'additional_headers' => 'Reply-To: [your-name] <[your-email]>',
		'attachments'        => '',
		'use_html'           => 0,
		'exclude_blank'      => 1,
	);
}

/**
 * Form definitions used by template-parts/lead-form.php.
 *
 * @param string $recipient Recipient e-mail address.
 * @return array<string, array<string, mixed>>
 */
function velotok_lead_form_definitions( $recipient ) {
	$messages = velotok_lead_forms_messages();

	$test_drive_form = <<<'FORM'
<label> Ваше имя
    [text* your-name autocomplete:name] </label>

<label> Телефон
    [tel your-phone autocomplete:tel] </label>

<label> Электронная почта
    [email your-email autocomplete:email] </label>

<label> Удобная дата
    [date test-drive-date min:today] </label>

<label> Модель
    [select bike-model include_blank "Ривьера" "Хребет Pro" "Курьер" "Складной"] </label>

<label> Комментарий
    [textarea your-message] </label>

[submit "Записаться на тест-драйв"]
FORM;

	$consultation_form = <<<'FORM'
<label> Ваше имя
    [text* your-name autocomplete:name] </label>

<label> Телефон
    [tel your-phone autocomplete:tel] </label>

<label> Электронная почта
    [email your-email autocomplete:email] </label>

<label> Вопрос
    [textarea your-message] </label>

[submit "Получить консультацию"]
FORM;

	return array(
		'test-drive'   => array(
			'title'              => 'test-drive',
			'form'               => $test_drive_form,
			'mail'               => velotok_lead_forms_mail(
				'Заявка на тест-драйв — [_site_title]',
				$recipient,
				'Новая заявка на тест-драйв с сайта.'
			),
			'additional_settings' => "flamingo_subject: Заявка на тест-драйв\nflamingo_name: [your-name]\nflamingo_email: [your-email]",
			'messages'           => $messages,
		),
		'consultation' => array(
			'title'              => 'consultation',
			'form'               => $consultation_form,
			'mail'               => velotok_lead_forms_mail(
				'Заявка на консультацию — [_site_title]',
				$recipient,
				'Новая заявка на консультацию с сайта.'
			),
			'additional_settings' => "flamingo_subject: Заявка на консультацию\nflamingo_name: [your-name]\nflamingo_email: [your-email]",
			'messages'           => $messages,
		),
	);
}

/**
 * Find an existing contact form post by its exact title.
 *
 * @param string $title Form title.
 * @return WP_Post|null
 */
function velotok_lead_forms_find( $title ) {
	$posts = get_posts(
		array(
			'post_type'      => 'wpcf7_contact_form',
			'post_status'    => 'any',
			'title'          => $title,
			'numberposts'    => 1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'suppress_filters' => false,
		)
	);

	return $posts ? $posts[0] : null;
}

/**
 * Create or update one contact form.
 *
 * @param string $title      Form title.
 * @param array  $definition Form definition.
 * @return int Form post ID, or 0 on failure.
 */
function velotok_lead_forms_save( $title, $definition ) {
	$existing = velotok_lead_forms_find( $title );
	$form     = $existing
		? WPCF7_ContactForm::get_instance( $existing )
		: WPCF7_ContactForm::get_template( array( 'title' => $title ) );

	if ( ! $form ) {
		velotok_lead_forms_log( sprintf( 'Не удалось открыть форму «%s».', $title ) );
		return 0;
	}

	$mail_2 = array(
		'active'             => false,
		'subject'            => '[_site_title] «[your-subject]»',
		'sender'             => '[_site_title] <[_site_admin_email]>',
		'body'               => 'Ваша заявка получена. Мы свяжемся с вами в течение рабочего дня.',
		'recipient'          => '[your-email]',
		'additional_headers' => 'Reply-To: [_site_admin_email]',
		'attachments'        => '',
		'use_html'           => 0,
		'exclude_blank'      => 0,
	);

	$form->set_title( $title );
	$form->set_properties(
		array(
			'form'                => $definition['form'],
			'mail'                => $definition['mail'],
			'mail_2'              => $mail_2,
			'messages'            => $definition['messages'],
			'additional_settings' => $definition['additional_settings'],
		)
	);

	$form_id = (int) $form->save();

	if ( $form_id > 0 ) {
		velotok_lead_forms_log(
			sprintf(
				'%s форма «%s» (ID %d), получатель %s.',
				$existing ? 'Обновлена' : 'Создана',
				$title,
				$form_id,
				$definition['mail']['recipient']
			)
		);
	}

	return $form_id;
}

if ( defined( 'VELOTOK_LEAD_FORMS_LIBRARY_ONLY' ) ) {
	return;
}

if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
	velotok_lead_forms_log( 'Contact Form 7 не активен: формы заявок не созданы.' );
	return;
}

if ( ! class_exists( 'Flamingo_Inbound_Message' ) ) {
	velotok_lead_forms_log( 'Flamingo не активен: заявки не будут сохраняться в базу.' );
}

$velotok_lead_forms_recipient = velotok_lead_forms_recipient();

foreach ( velotok_lead_form_definitions( $velotok_lead_forms_recipient ) as $velotok_lead_form_title => $velotok_lead_form_definition ) {
	velotok_lead_forms_save( $velotok_lead_form_title, $velotok_lead_form_definition );
}
