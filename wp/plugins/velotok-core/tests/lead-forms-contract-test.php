<?php
/**
 * Contract test for EBIKE-21: Contact Form 7 lead forms and Flamingo storage.
 *
 * Run from the project root:
 *   php wp/plugins/velotok-core/tests/lead-forms-contract-test.php
 *
 * The test proves that the setup installs and activates Contact Form 7 and
 * Flamingo, creates the exact «test-drive» and «consultation» forms used by
 * the theme, fills their mail recipient and keeps the forms idempotent.
 *
 * @package Velotok_Core
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, 'This test must be run from the command line.' . PHP_EOL );
	exit( 1 );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_lead_test_theme_mod'] = 'shop@example.test';
$GLOBALS['velotok_lead_test_posts']     = array();
$GLOBALS['velotok_lead_test_saved']     = array();
$failures                                = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_lead_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

function get_theme_mod( $name, $default = '' ) {
	unset( $name );

	return $GLOBALS['velotok_lead_test_theme_mod'];
}

function get_option( $name ) {
	unset( $name );

	return 'admin@example.test';
}

function is_email( $email ) {
	return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
}

function get_posts( $args ) {
	$title = isset( $args['title'] ) ? $args['title'] : '';

	return isset( $GLOBALS['velotok_lead_test_posts'][ $title ] )
		? array( $GLOBALS['velotok_lead_test_posts'][ $title ] )
		: array();
}

/**
 * Minimal WP_Post double.
 */
class WP_Post {
	public $ID;

	public function __construct( $id ) {
		$this->ID = (int) $id;
	}
}

/**
 * Minimal Contact Form 7 form double.
 */
class WPCF7_ContactForm {
	public $title = '';

	public $properties = array(
		'form'                => '',
		'mail'                => array(),
		'mail_2'              => array(),
		'messages'            => array(),
		'additional_settings' => '',
	);

	private $id = 0;

	public static function get_template( $args ) {
		$form        = new self();
		$form->title = $args['title'];

		return $form;
	}

	public static function get_instance( $post ) {
		unset( $post );

		return new self();
	}

	public function set_title( $title ) {
		$this->title = $title;
	}

	public function set_properties( $properties ) {
		$this->properties = array_intersect_key(
			array_merge( $this->properties, $properties ),
			$this->properties
		);
	}

	public function save() {
		$this->id = isset( $GLOBALS['velotok_lead_test_saved'][ $this->title ] )
			? $GLOBALS['velotok_lead_test_saved'][ $this->title ]['id'] + 1
			: 1;

		$GLOBALS['velotok_lead_test_saved'][ $this->title ] = array(
			'id'         => $this->id,
			'properties' => $this->properties,
		);

		if ( ! isset( $GLOBALS['velotok_lead_test_posts'][ $this->title ] ) ) {
			$GLOBALS['velotok_lead_test_posts'][ $this->title ] = new WP_Post( $this->id );
		}

		return $this->id;
	}
}

/**
 * Minimal Flamingo marker used by the provisioning script.
 */
class Flamingo_Inbound_Message {}

$root             = dirname( __DIR__, 4 );
$setup_script     = $root . '/wp/cli/setup.sh';
$lead_forms_script = $root . '/wp/cli/lead-forms.php';
$template         = $root . '/wp/themes/velotok/template-parts/lead-form.php';

velotok_lead_assert( is_readable( $setup_script ), 'Не найден скрипт настройки wp/cli/setup.sh.' );
velotok_lead_assert( is_readable( $lead_forms_script ), 'Не найден скрипт форм wp/cli/lead-forms.php.' );
velotok_lead_assert( is_readable( $template ), 'Не найден шаблон блока заявки.' );

$setup = (string) file_get_contents( $setup_script );
velotok_lead_assert( false !== strpos( $setup, 'contact-form-7' ), 'Настройка не устанавливает Contact Form 7.' );
velotok_lead_assert( false !== strpos( $setup, 'flamingo' ), 'Настройка не устанавливает Flamingo.' );
velotok_lead_assert( false !== strpos( $setup, 'lead-forms.php' ), 'Настройка не создаёт формы заявок.' );

$template_source = (string) file_get_contents( $template );
velotok_lead_assert( false !== strpos( $template_source, '[contact-form-7 title="test-drive"]' ), 'Шаблон не выводит форму test-drive.' );
velotok_lead_assert( false !== strpos( $template_source, '[contact-form-7 title="consultation"]' ), 'Шаблон не выводит форму consultation.' );

if ( ! defined( 'VELOTOK_LEAD_FORMS_LIBRARY_ONLY' ) ) {
	define( 'VELOTOK_LEAD_FORMS_LIBRARY_ONLY', true );
}

require $lead_forms_script;

velotok_lead_assert( function_exists( 'velotok_lead_form_definitions' ), 'Скрипт форм не определяет velotok_lead_form_definitions().' );

$definitions = velotok_lead_form_definitions( 'shop@example.test' );

velotok_lead_assert( isset( $definitions['test-drive'] ), 'Нет определения формы test-drive.' );
velotok_lead_assert( isset( $definitions['consultation'] ), 'Нет определения формы consultation.' );

foreach ( array( 'test-drive', 'consultation' ) as $title ) {
	$definition = isset( $definitions[ $title ] ) ? $definitions[ $title ] : array();
	$form       = isset( $definition['form'] ) ? $definition['form'] : '';
	$mail       = isset( $definition['mail'] ) ? $definition['mail'] : array();
	$settings   = isset( $definition['additional_settings'] ) ? $definition['additional_settings'] : '';

	velotok_lead_assert( false !== strpos( $form, '[text* your-name' ), 'В форме ' . $title . ' нет обязательного имени.' );
	velotok_lead_assert( false !== strpos( $form, '[email your-email' ), 'В форме ' . $title . ' нет поля email.' );
	velotok_lead_assert( false !== strpos( $form, '[tel your-phone' ), 'В форме ' . $title . ' нет поля телефона.' );
	velotok_lead_assert( isset( $mail['recipient'] ) && 'shop@example.test' === $mail['recipient'], 'У формы ' . $title . ' не задан получатель.' );
	velotok_lead_assert( isset( $mail['body'] ) && false !== strpos( $mail['body'], '[your-name]' ), 'Письмо формы ' . $title . ' не содержит данных заявки.' );
	velotok_lead_assert( false !== strpos( $settings, 'flamingo_subject:' ), 'У формы ' . $title . ' не задан канал Flamingo.' );
}

velotok_lead_forms_save( 'test-drive', $definitions['test-drive'] );
velotok_lead_forms_save( 'test-drive', $definitions['test-drive'] );
velotok_lead_forms_save( 'consultation', $definitions['consultation'] );

velotok_lead_assert( isset( $GLOBALS['velotok_lead_test_saved']['test-drive'] ), 'Форма test-drive не сохраняется.' );
velotok_lead_assert( isset( $GLOBALS['velotok_lead_test_saved']['consultation'] ), 'Форма consultation не сохраняется.' );
velotok_lead_assert( 2 === $GLOBALS['velotok_lead_test_saved']['test-drive']['id'], 'Повторная настройка должна обновлять форму, а не создавать дубль.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}
	exit( 1 );
}

echo 'OK: формы test-drive и consultation создаются, получатель задан, заявки сохраняются во Flamingo.' . PHP_EOL;
