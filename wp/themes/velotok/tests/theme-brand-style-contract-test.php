<?php
/**
 * Behaviour test for TESTEB-55: the theme implements the brand style contract.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/theme-brand-style-contract-test.php
 *
 * The test treats brand-style.json as the contract source of truth. It checks
 * that the theme exposes the contract, that the base CSS variables use the
 * contract palette, typography and geometry, and that the header, footer and
 * document metadata render brand values from the contract instead of copied
 * literals. Without inc/brand-style.php or brand-style.json it fails.
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

$GLOBALS['velotok_brand_test_filters'] = array();
$failures                              = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_brand_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * WordPress filter stub.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @return bool
 */
function add_filter( $hook, $callback ) {
	$GLOBALS['velotok_brand_test_filters'][ $hook ][] = $callback;

	return true;
}

/**
 * WordPress filter application stub.
 *
 * @param string $hook  Hook name.
 * @param mixed  $value Initial value.
 * @return mixed
 */
function apply_filters( $hook, $value ) {
	if ( empty( $GLOBALS['velotok_brand_test_filters'][ $hook ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['velotok_brand_test_filters'][ $hook ] as $callback ) {
		$value = call_user_func( $callback, $value );
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
 * Attribute escaping stub.
 *
 * @param mixed $value Value.
 * @return string
 */
function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

/**
 * Read a theme file.
 *
 * @param string $relative_path Path relative to the theme root.
 * @return string
 */
function velotok_brand_read( $relative_path ) {
	$file = dirname( __DIR__ ) . '/' . ltrim( $relative_path, '/' );

	return is_readable( $file ) ? (string) file_get_contents( $file ) : '';
}

/**
 * Extract :root CSS custom properties.
 *
 * @param string $css Stylesheet source.
 * @return array<string,string>
 */
function velotok_brand_css_root_variables( $css ) {
	$variables = array();

	if ( ! preg_match( '/:root\s*\{(.*?)\}/s', $css, $root ) ) {
		return $variables;
	}

	if ( preg_match_all( '/--([a-z0-9-]+)\s*:\s*([^;]+);/i', $root[1], $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $match ) {
			$variables[ '--' . $match[1] ] = trim( $match[2] );
		}
	}

	return $variables;
}

$theme_dir     = dirname( __DIR__ );
$manifest_path = $theme_dir . '/brand-style.json';

require_once $theme_dir . '/inc/brand-style.php';

/*
 * 1. The manifest is the source of truth and is valid JSON.
 */
velotok_brand_assert( is_readable( $manifest_path ), 'Нет манифеста фирменного стиля brand-style.json.' );

$manifest = is_readable( $manifest_path ) ? json_decode( (string) file_get_contents( $manifest_path ), true ) : null;
velotok_brand_assert( is_array( $manifest ), 'Манифест фирменного стиля должен быть валидным JSON.' );
velotok_brand_assert( isset( $manifest['spec'] ) && 'theme' === $manifest['spec'], 'Манифест должен ссылаться на спеку theme.' );
velotok_brand_assert( isset( $manifest['name'] ) && 'Велоток' === $manifest['name'], 'Манифест должен объявлять бренд «Велоток».' );
velotok_brand_assert( isset( $manifest['palette'] ) && is_array( $manifest['palette'] ), 'Манифест должен содержать палитру.' );
velotok_brand_assert( isset( $manifest['typography'] ) && is_array( $manifest['typography'] ), 'Манифест должен содержать типографику.' );
velotok_brand_assert( isset( $manifest['logo'] ) && is_array( $manifest['logo'] ), 'Манифест должен содержать описание логотипа.' );

/*
 * 2. The theme exposes the contract and resolves it from the manifest.
 */
velotok_brand_assert( function_exists( 'velotok_brand_style_contract' ), 'Не определён контракт фирменного стиля.' );
velotok_brand_assert( function_exists( 'velotok_brand_color' ), 'Не определён доступ к цветам фирменного стиля.' );
velotok_brand_assert( function_exists( 'velotok_brand_logo_svg' ), 'Не определён логотип из контракта фирменного стиля.' );
velotok_brand_assert( function_exists( 'velotok_brand_tokens' ), 'Не определена карта токенов фирменного стиля.' );

$contract = velotok_brand_style_contract( true );

velotok_brand_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
velotok_brand_assert( isset( $contract['version'] ) && 1 === $contract['version'], 'Контракт должен объявлять версию 1.' );
velotok_brand_assert( isset( $contract['spec'] ) && 'theme' === $contract['spec'], 'Контракт должен ссылаться на спеку theme.' );
velotok_brand_assert( isset( $contract['name'] ) && 'Велоток' === $contract['name'], 'Контракт должен возвращать название бренда.' );

$required_colors = array( 'ink', 'ink-2', 'paper', 'card', 'muted', 'line', 'lime', 'lime-soft', 'orange' );

foreach ( $required_colors as $token ) {
	velotok_brand_assert(
		isset( $contract['palette'][ $token ] ) && preg_match( '/^#[0-9a-f]{6}$/i', $contract['palette'][ $token ] ),
		'В контракте нет корректного цвета палитры: ' . $token . '.'
	);

	if ( isset( $manifest['palette'][ $token ] ) ) {
		velotok_brand_assert(
			$contract['palette'][ $token ] === strtolower( $manifest['palette'][ $token ] ),
			'Цвет «' . $token . '» должен браться из манифеста.'
		);
	}
}

velotok_brand_assert( '#12211a' === velotok_brand_color( 'ink' ), 'Цвет ink должен читаться из контракта.' );
velotok_brand_assert( 'fallback' === velotok_brand_color( 'unknown', 'fallback' ), 'Неизвестный цвет должен возвращать fallback.' );
velotok_brand_assert( '#12211a' === velotok_brand_style_value( 'palette.ink' ), 'Точечный путь должен возвращать значение контракта.' );
velotok_brand_assert( 'fallback' === velotok_brand_style_value( 'palette.unknown', 'fallback' ), 'Неизвестный путь должен возвращать fallback.' );
velotok_brand_assert( array() === velotok_brand_style_validate(), 'Валидный контракт не должен содержать ошибок.' );

/*
 * 3. Base CSS variables implement the contract palette, type and geometry.
 */
$css         = velotok_brand_read( 'assets/css/main.css' );
$css_root    = velotok_brand_css_root_variables( $css );
$css_checks  = array(
	'--ink'       => $contract['palette']['ink'],
	'--ink-2'     => $contract['palette']['ink-2'],
	'--paper'     => $contract['palette']['paper'],
	'--card'      => $contract['palette']['card'],
	'--muted'     => $contract['palette']['muted'],
	'--line'      => $contract['palette']['line'],
	'--lime'      => $contract['palette']['lime'],
	'--lime-soft' => $contract['palette']['lime-soft'],
	'--orange'    => $contract['palette']['orange'],
	'--sans'      => $contract['typography']['sans'],
	'--serif'     => $contract['typography']['serif'],
	'--mono'      => $contract['typography']['mono'],
	'--radius'    => $contract['shape']['radius'],
	'--pad'       => $contract['layout']['padding'],
);

foreach ( $css_checks as $variable => $expected ) {
	velotok_brand_assert(
		isset( $css_root[ $variable ] ) && $expected === $css_root[ $variable ],
		'CSS-переменная ' . $variable . ' должна реализовывать значение контракта.'
	);
}

$tokens = velotok_brand_tokens();

foreach ( $contract['palette'] as $token => $value ) {
	velotok_brand_assert( isset( $tokens[ '--' . $token ] ) && $value === $tokens[ '--' . $token ], 'Карта токенов должна содержать цвет ' . $token . '.' );
}

$variables_css = velotok_brand_style_css_variables();
velotok_brand_assert( false !== strpos( $variables_css, '--ink:' . $contract['palette']['ink'] ), 'CSS-переменные должны собираться из контракта.' );

/*
 * 4. The header, footer and document metadata render the contract.
 */
$header = velotok_brand_read( 'header.php' );
velotok_brand_assert( false !== strpos( $header, 'velotok_brand_color' ), 'Метаданные темы должны брать theme-color из контракта.' );
velotok_brand_assert( false === strpos( $header, 'content="#12211a"' ), 'theme-color не должен быть захардкожен в шапке документа.' );

$header_bar     = velotok_brand_read( 'template-parts/header-bar.php' );
$footer_content = velotok_brand_read( 'template-parts/footer-content.php' );

foreach ( array( 'template-parts/header-bar.php' => $header_bar, 'template-parts/footer-content.php' => $footer_content ) as $label => $source ) {
	velotok_brand_assert( false !== strpos( $source, 'velotok_brand_logo_svg' ), 'Логотип в ' . $label . ' должен собираться из контракта.' );
	velotok_brand_assert( false === strpos( $source, '#12211a' ), 'Цвета логотипа в ' . $label . ' не должны быть захардкожены.' );
	velotok_brand_assert( false === strpos( $source, '#d8f26a' ), 'Акцент логотипа в ' . $label . ' не должен быть захардкожен.' );
}

$header_logo = velotok_brand_logo_svg( 'header' );
$footer_logo = velotok_brand_logo_svg( 'footer' );

velotok_brand_assert( false !== strpos( $header_logo, 'class="logo-mark"' ) && false !== strpos( $header_logo, 'aria-hidden="true"' ), 'Логотип шапки должен быть декоративным SVG с классом logo-mark.' );
velotok_brand_assert( false !== strpos( $header_logo, 'fill="' . $contract['palette']['ink'] . '"' ), 'Фон логотипа шапки должен брать цвет ink из контракта.' );
velotok_brand_assert( false !== strpos( $header_logo, 'fill="' . $contract['palette']['lime'] . '"' ), 'Молния логотипа шапки должна брать цвет lime из контракта.' );
velotok_brand_assert( false !== strpos( $footer_logo, 'fill="' . $contract['palette']['lime'] . '"' ), 'Фон логотипа подвала должен брать цвет lime из контракта.' );
velotok_brand_assert( false !== strpos( $footer_logo, 'fill="' . $contract['palette']['ink'] . '"' ), 'Молния логотипа подвала должна брать цвет ink из контракта.' );

/*
 * 5. A missing or corrupted manifest falls back to the same contract instead
 *    of breaking the storefront.
 */
add_filter(
	'velotok_brand_style_manifest_path',
	static function () {
		return '/path/that/does/not/exist/brand-style.json';
	}
);

$fallback = velotok_brand_style_contract( true );
velotok_brand_assert( 'Велоток' === $fallback['name'], 'При отсутствии манифеста должен применяться встроенный контракт.' );
velotok_brand_assert( '#12211a' === $fallback['palette']['ink'], 'Встроенный контракт должен сохранять фирменный цвет ink.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок контракта фирменного стиля не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: тема реализует контракт фирменного стиля из brand-style.json (TESTEB-55).' . PHP_EOL;
exit( 0 );
