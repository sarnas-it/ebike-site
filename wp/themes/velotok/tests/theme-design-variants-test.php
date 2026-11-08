<?php
/**
 * Behaviour test for TESTEB-53: the theme supports design variants.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/theme-design-variants-test.php
 *
 * The test treats design-manifest.json as the source of truth, then checks the
 * active-variant selection, body class, stylesheet enqueueing and validation.
 * Without inc/design-variants.php or the manifest the test fails.
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

$GLOBALS['velotok_design_test_filters']    = array();
$GLOBALS['velotok_design_test_theme_mods'] = array();
$GLOBALS['velotok_design_test_styles']     = array();
$failures                                  = array();

$_GET = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_design_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * WordPress filter stub.
 *
 * @param string   $hook          Hook name.
 * @param callable $callback      Callback.
 * @param int      $priority      Priority.
 * @param int      $accepted_args Accepted arguments.
 * @return bool
 */
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['velotok_design_test_filters'][ $hook ][] = array(
		'callback'      => $callback,
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
 * WordPress filter application stub.
 *
 * @param string $hook  Hook name.
 * @param mixed  $value Initial value.
 * @param mixed  ...$args Extra arguments.
 * @return mixed
 */
function apply_filters( $hook, $value, ...$args ) {
	if ( empty( $GLOBALS['velotok_design_test_filters'][ $hook ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['velotok_design_test_filters'][ $hook ] as $filter ) {
		$call_args = array_merge( array( $value ), array_slice( $args, 0, max( 0, $filter['accepted_args'] - 1 ) ) );
		$value     = call_user_func_array( $filter['callback'], $call_args );
	}

	return $value;
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
 * Theme URI stub.
 *
 * @return string
 */
function get_template_directory_uri() {
	return 'https://example.test/wp-content/themes/velotok';
}

/**
 * Theme mod stub.
 *
 * @param string $name    Setting name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function get_theme_mod( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['velotok_design_test_theme_mods'] )
		? $GLOBALS['velotok_design_test_theme_mods'][ $name ]
		: $default;
}

/**
 * Key sanitizer stub.
 *
 * @param string $key Raw key.
 * @return string
 */
function sanitize_key( $key ) {
	return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

/**
 * Unslash stub.
 *
 * @param mixed $value Value.
 * @return mixed
 */
function wp_unslash( $value ) {
	return $value;
}

/**
 * Style enqueue stub.
 *
 * @param string $handle Handle.
 * @param string $src    Source.
 * @param array  $deps   Dependencies.
 * @param mixed  $ver    Version.
 * @return void
 */
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
	$GLOBALS['velotok_design_test_styles'][ $handle ] = array(
		'src'  => $src,
		'deps' => $deps,
		'ver'  => $ver,
	);
}

/**
 * Translation stub.
 *
 * @param string $text Text.
 * @return string
 */
function __( $text, $domain = null ) {
	return $text;
}

$theme_dir = dirname( __DIR__ );

require_once $theme_dir . '/inc/design-variants.php';

/*
 * 1. The manifest is the source of truth and is valid JSON.
 */
$manifest_path = $theme_dir . '/design-manifest.json';
velotok_design_assert( is_readable( $manifest_path ), 'Нет манифеста вариантов дизайна design-manifest.json.' );

$raw_manifest = is_readable( $manifest_path ) ? json_decode( (string) file_get_contents( $manifest_path ), true ) : null;
velotok_design_assert( is_array( $raw_manifest ), 'Манифест вариантов дизайна должен быть валидным JSON.' );
velotok_design_assert( isset( $raw_manifest['default'] ) && 'classic' === $raw_manifest['default'], 'Манифест должен объявлять вариант classic по умолчанию.' );
velotok_design_assert( isset( $raw_manifest['variants'] ) && is_array( $raw_manifest['variants'] ), 'Манифест должен содержать объект variants.' );
velotok_design_assert( isset( $raw_manifest['variants']['contrast'] ), 'Манифест должен объявлять вариант contrast.' );
velotok_design_assert( isset( $raw_manifest['variants']['warm'] ), 'Манифест должен объявлять вариант warm.' );

/*
 * 2. The theme exposes exactly the variants declared by the manifest.
 */
$variants = velotok_design_variants();
velotok_design_assert( is_array( $variants ) && count( $variants ) === count( $raw_manifest['variants'] ), 'Набор вариантов должен совпадать с манифестом.' );
velotok_design_assert( isset( $variants['classic'], $variants['contrast'], $variants['warm'] ), 'Объявленные варианты должны быть доступны.' );
velotok_design_assert( $variants['contrast']['label'] === $raw_manifest['variants']['contrast']['label'], 'Подпись варианта должна браться из манифеста.' );
velotok_design_assert( true === velotok_is_design_variant( 'contrast' ), 'Объявленный вариант должен считаться валидным.' );
velotok_design_assert( false === velotok_is_design_variant( 'not-in-manifest' ), 'Необъявленный вариант не должен считаться валидным.' );

foreach ( $raw_manifest['variants'] as $slug => $variant ) {
	if ( empty( $variant['stylesheet'] ) ) {
		continue;
	}

	$stylesheet = $theme_dir . '/' . ltrim( (string) $variant['stylesheet'], '/' );
	velotok_design_assert( is_readable( $stylesheet ), 'Нет CSS-файла варианта ' . $slug . ': ' . $variant['stylesheet'] );
}

/*
 * 3. The default variant is used with no selection and unknown values fall back.
 */
$GLOBALS['velotok_design_test_theme_mods'] = array();
$_GET                                     = array();
velotok_design_assert( 'classic' === velotok_get_active_design_variant(), 'Без выбора должен применяться вариант по умолчанию.' );

$GLOBALS['velotok_design_test_theme_mods']['velotok_design_variant'] = 'warm';
velotok_design_assert( 'warm' === velotok_get_active_design_variant(), 'Настройка темы должна переключать вариант.' );

$_GET['variant'] = 'contrast';
velotok_design_assert( 'contrast' === velotok_get_active_design_variant(), 'Параметр запроса должен переключать вариант.' );

$_GET['variant'] = 'not-in-manifest';
$GLOBALS['velotok_design_test_theme_mods'] = array();
velotok_design_assert( 'classic' === velotok_get_active_design_variant(), 'Неизвестный вариант должен откатываться к варианту по умолчанию.' );

$_GET['variant'] = '../../etc/passwd';
velotok_design_assert( 'classic' === velotok_get_active_design_variant(), 'Небезопасное значение варианта должно игнорироваться.' );

$_GET = array();
$GLOBALS['velotok_design_test_theme_mods']['velotok_design_variant'] = 'not-in-manifest';
velotok_design_assert( 'classic' === velotok_get_active_design_variant(), 'Неизвестная настройка темы должна откатываться к варианту по умолчанию.' );

/*
 * 4. A filter can select a variant, but cannot introduce a new one.
 */
$GLOBALS['velotok_design_test_theme_mods'] = array();
add_filter(
	'velotok_active_design_variant',
	static function ( $slug ) {
		return 'contrast';
	}
);
velotok_design_assert( 'contrast' === velotok_get_active_design_variant(), 'Фильтр должен переключать объявленный вариант.' );

$GLOBALS['velotok_design_test_filters'] = array();
add_filter(
	'velotok_active_design_variant',
	static function ( $slug ) {
		return 'made-up';
	}
);
velotok_design_assert( 'classic' === velotok_get_active_design_variant(), 'Фильтр не должен включать необъявленный вариант.' );
$GLOBALS['velotok_design_test_filters'] = array();

/*
 * 5. The active variant adds a stable body class and its manifest stylesheet.
 */
$GLOBALS['velotok_design_test_theme_mods']['velotok_design_variant'] = 'contrast';
$classes = velotok_design_body_class( array( 'home' ) );
velotok_design_assert( in_array( 'design-variant-contrast', $classes, true ), 'Активный вариант должен добавлять класс design-variant-contrast.' );
velotok_design_assert( in_array( 'has-design-variant', $classes, true ), 'Тема должна помечать body как использующий вариант дизайна.' );
velotok_design_assert( in_array( 'home', $classes, true ), 'Существующие классы body должны сохраняться.' );

velotok_design_assert( 'assets/css/variants/contrast.css' === velotok_design_variant_stylesheet(), 'CSS активного варианта должен браться из манифеста.' );

velotok_design_enqueue_variant_assets();
velotok_design_assert( isset( $GLOBALS['velotok_design_test_styles']['velotok-design-contrast'] ), 'CSS активного варианта должен подключаться.' );

if ( isset( $GLOBALS['velotok_design_test_styles']['velotok-design-contrast'] ) ) {
	$style = $GLOBALS['velotok_design_test_styles']['velotok-design-contrast'];
	velotok_design_assert( 'https://example.test/wp-content/themes/velotok/assets/css/variants/contrast.css' === $style['src'], 'URL CSS варианта должен указывать на файл темы.' );
	velotok_design_assert( in_array( 'velotok-style', $style['deps'], true ), 'CSS варианта должен подключаться после базовых стилей темы.' );
}

/*
 * 6. The default variant has no extra stylesheet and unsafe paths are rejected.
 */
$GLOBALS['velotok_design_test_theme_mods']['velotok_design_variant'] = 'classic';
velotok_design_assert( '' === velotok_design_variant_stylesheet(), 'Вариант classic не должен подключать отдельный CSS.' );
velotok_design_assert( '' === velotok_design_sanitize_stylesheet( '../secret.css' ), 'CSS-путь с .. должен отклоняться.' );
velotok_design_assert( '' === velotok_design_sanitize_stylesheet( '/absolute.css' ), 'Абсолютный CSS-путь должен отклоняться.' );
velotok_design_assert( '' === velotok_design_sanitize_stylesheet( 'https://example.test/x.css' ), 'Внешний CSS-путь должен отклоняться.' );
velotok_design_assert( 'assets/css/variants/warm.css' === velotok_design_sanitize_stylesheet( 'assets/css/variants/warm.css' ), 'Относительный CSS-путь варианта должен приниматься.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок вариантов дизайна не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: тема поддерживает варианты дизайна из манифеста (TESTEB-53).' . PHP_EOL;
exit( 0 );
