<?php
/**
 * Behaviour test for TESTEB-52: the theme is responsive and accessible.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/theme-responsive-accessibility-test.php
 *
 * The test inspects the theme templates, styles and navigation script. It
 * fails when the skip link, keyboard menu handling, focusable content target,
 * responsive control-width rules or the accessible WooCommerce gallery trigger
 * are missing.
 *
 * @package Velotok
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, 'This test must be run from the command line.' . PHP_EOL );
	exit( 1 );
}

$velotok_theme_dir = dirname( __DIR__ );
$failures          = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_a11y_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Read a theme file.
 *
 * @param string $relative_path Path relative to the theme root.
 * @return string
 */
function velotok_a11y_read( $relative_path ) {
	global $velotok_theme_dir;

	$file = $velotok_theme_dir . '/' . ltrim( $relative_path, '/' );

	if ( ! is_readable( $file ) ) {
		return '';
	}

	return (string) file_get_contents( $file );
}

$header = velotok_a11y_read( 'header.php' );
$main_css = velotok_a11y_read( 'assets/css/main.css' );
$wp_css   = velotok_a11y_read( 'assets/css/wp.css' );
$script   = velotok_a11y_read( 'assets/js/main.js' );
$style    = velotok_a11y_read( 'style.css' );

/*
 * 1. Document metadata and the keyboard skip link.
 */
velotok_a11y_assert(
	(bool) preg_match( '/<meta\s+name="viewport"\s+content="width=device-width,\s*initial-scale=1"/i', $header ),
	'В шапке должен быть адаптивный meta viewport.'
);
velotok_a11y_assert(
	(bool) preg_match( '/<a\s+class="sr-only"\s+href="#main"/i', $header ),
	'В шапке должна быть ссылка пропуска к основному содержанию.'
);
velotok_a11y_assert(
	false !== strpos( $header, 'Перейти к основному содержанию' ),
	'Ссылка пропуска должна иметь понятную подпись.'
);

/*
 * 2. Every top-level template exposes a focusable main landmark so the skip
 *    link also moves focus, not just the scroll position.
 */
$templates = array(
	'front-page.php',
	'index.php',
	'single.php',
	'page.php',
	'404.php',
	'woocommerce/archive-product.php',
	'woocommerce/single-product.php',
);

foreach ( $templates as $template ) {
	$html = velotok_a11y_read( $template );
	velotok_a11y_assert( '' !== $html, 'Нет шаблона темы: ' . $template );
	velotok_a11y_assert(
		(bool) preg_match( '/<main\b[^>]*\bid="main"[^>]*\btabindex="-1"/i', $html ),
		'Основное содержание должно быть доступно по skip-ссылке с tabindex="-1": ' . $template
	);
}

/*
 * 3. Responsive styles: base accessibility helpers and explicit control-width
 *    breakpoints for the product grid.
 */
velotok_a11y_assert( false !== strpos( $main_css, '.sr-only' ), 'Нет класса sr-only для скрытого, но доступного текста.' );
velotok_a11y_assert( false !== strpos( $main_css, ':focus-visible' ), 'Нет явного видимого фокуса для клавиатуры.' );
velotok_a11y_assert(
	(bool) preg_match( '/@media\s*\(\s*prefers-reduced-motion:\s*reduce\s*\)/', $main_css ),
	'Нет режима prefers-reduced-motion.'
);

$control_widths = array(
	1440 => '/@media\s*\(\s*min-width:\s*1440px\s*\)/',
	1024 => '/@media\s*\(\s*max-width:\s*1024px\s*\)/',
	768  => '/@media\s*\(\s*max-width:\s*768px\s*\)/',
	360  => '/@media\s*\(\s*max-width:\s*360px\s*\)/',
);

foreach ( $control_widths as $width => $pattern ) {
	velotok_a11y_assert(
		(bool) preg_match( $pattern, $main_css ),
		'Нет правила адаптива для контрольной ширины ' . $width . 'px.'
	);
}

velotok_a11y_assert(
	(bool) preg_match( '/@media\s*\(\s*max-width:\s*1024px\s*\)\s*\{[^}]*\.model-grid[^}]*grid-template-columns:\s*repeat\(2,\s*1fr\)/s', $main_css )
		|| (bool) preg_match( '/\.model-grid[^}]*grid-template-columns:\s*repeat\(2,\s*1fr\)[^}]*\}[^@]*@media\s*\(\s*max-width:\s*1024px/s', $main_css ),
	'На ширине 1024px сетка товаров должна перестраиваться в две колонки.'
);
velotok_a11y_assert(
	(bool) preg_match( '/@media\s*\(\s*max-width:\s*768px\s*\)\s*\{[^}]*\.model-grid[^}]*grid-template-columns:\s*1fr/s', $main_css ),
	'На ширине 768px сетка товаров должна перестраиваться в одну колонку.'
);

velotok_a11y_assert(
	(bool) preg_match( '/@media\s*\(\s*max-width:\s*1024px\s*\)\s*\{[^}]*\.catalog-grid[^}]*grid-template-columns:\s*repeat\(2,\s*minmax\(0,\s*1fr\)\)/s', $wp_css ),
	'Каталог WooCommerce должен перестраиваться в две колонки на 1024px.'
);
velotok_a11y_assert(
	(bool) preg_match( '/@media\s*\(\s*max-width:\s*768px\s*\)\s*\{[^}]*\.catalog-grid[^}]*grid-template-columns:\s*1fr/s', $wp_css ),
	'Каталог WooCommerce должен перестраиваться в одну колонку на 768px.'
);

/*
 * 4. Keyboard navigation for the mobile menu.
 */
$header_bar = velotok_a11y_read( 'template-parts/header-bar.php' );
velotok_a11y_assert(
	(bool) preg_match( '/<nav[^>]*id="main-nav"[^>]*aria-label=/i', $header_bar ),
	'Основная навигация должна иметь доступное имя.'
);
velotok_a11y_assert(
	(bool) preg_match( '/<button[^>]*id="menu-toggle"[^>]*aria-expanded="false"[^>]*aria-controls="main-nav"/i', $header_bar ),
	'Кнопка меню должна сообщать состояние через aria-expanded и управлять main-nav.'
);
velotok_a11y_assert(
	(bool) preg_match( '/Escape|Esc/', $script ),
	'Меню должно закрываться клавишей Escape.'
);
velotok_a11y_assert( false !== strpos( $script, '.focus()' ), 'После открытия меню фокус должен переходить внутрь навигации.' );

/*
 * 5. The WooCommerce gallery zoom trigger keeps its accessible name.
 */
velotok_a11y_assert(
	! (bool) preg_match( '/\.woocommerce-product-gallery__trigger\s+\.screen-reader-text\s*\{[^}]*display\s*:\s*none/s', $wp_css ),
	'Подпись кнопки увеличения галереи не должна скрываться через display:none.'
);

velotok_a11y_assert(
	(bool) preg_match( '/Tags:.*accessibility-ready/i', $style ),
	'Тема должна заявлять тег accessibility-ready.'
);

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок адаптивности и доступности не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: тема адаптивна и доступна с клавиатуры (TESTEB-52).' . PHP_EOL;
exit( 0 );
