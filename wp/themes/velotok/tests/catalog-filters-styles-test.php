<?php
/**
 * Behaviour test for EBIKE-19: catalog filters have theme styles.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/catalog-filters-styles-test.php
 *
 * The catalog archive gained the catalog-filter* markup in TESTEB-20, but the
 * stylesheets had no rules for it, so browsers rendered raw fieldsets, inputs
 * and buttons. This test checks that every filter part has a rule in
 * assets/css/main.css, that the fieldset chrome is reset, and that the form
 * still fits the 768 and 360 px control widths.
 *
 * @package Velotok
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, 'This test must be run from the command line.' . PHP_EOL );
	exit( 1 );
}

$velotok_filter_style_dir = dirname( __DIR__ );
$failures                 = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_filter_style_assert( $condition, $message ) {
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
function velotok_filter_style_read( $relative_path ) {
	global $velotok_filter_style_dir;

	$file = $velotok_filter_style_dir . '/' . ltrim( $relative_path, '/' );

	if ( ! is_readable( $file ) ) {
		return '';
	}

	return (string) file_get_contents( $file );
}

/**
 * Normalise whitespace for selector/declaration matching.
 *
 * @param string $css Stylesheet fragment.
 * @return string
 */
function velotok_filter_style_compact( $css ) {
	return trim( preg_replace( '/\s+/', ' ', (string) $css ) );
}

$main_css = velotok_filter_style_read( 'assets/css/main.css' );
$archive  = velotok_filter_style_read( 'woocommerce/archive-product.php' );

$marker = '/* Фильтры каталога (EBIKE-19) */';
$start  = strpos( $main_css, $marker );

velotok_filter_style_assert( '' !== $main_css, 'Не найден assets/css/main.css.' );
velotok_filter_style_assert( false !== $start, 'В main.css нет блока стилей фильтров каталога.' );

$filters_css = false === $start ? '' : substr( $main_css, $start );
$compact     = velotok_filter_style_compact( $filters_css );

/*
 * 1. Every part of the archive markup has a matching stylesheet rule.
 */
$selectors = array(
	'.catalog-filter-form'                                   => 'формы фильтров',
	'.catalog-filter-search'                                 => 'поисковой строки',
	'.catalog-filter-field'                                  => 'текстового поля',
	'.catalog-filter-range'                                  => 'поля диапазона',
	'.catalog-filter-actions'                                => 'блока действий',
	'.catalog-filter-form fieldset'                          => 'сброса рамки fieldset',
	'.catalog-filter-range legend'                           => 'подписи диапазона',
	'.catalog-filter-range label'                            => 'подписей «от» и «до»',
	'.catalog-filter-search input'                           => 'поля поиска',
	'.catalog-filter-field input'                            => 'текстового ввода',
	'.catalog-filter-range input'                            => 'ввода диапазона',
	'.catalog-filter-form .catalog-filter-actions .button'   => 'кнопки «Применить»',
	'.catalog-filter-form .catalog-filter-actions .text-link' => 'ссылки «Сбросить»',
);

foreach ( $selectors as $selector => $label ) {
	velotok_filter_style_assert(
		false !== strpos( $compact, $selector ),
		'Нет стилей для ' . $label . ' (' . $selector . ').'
	);
}

/*
 * 2. The markup in the archive uses the classes that are styled.
 */
foreach ( array( 'catalog-filter-form', 'catalog-filter-search', 'catalog-filter-field', 'catalog-filter-range', 'catalog-filter-actions' ) as $class_name ) {
	velotok_filter_style_assert(
		false !== strpos( $archive, $class_name ),
		'В archive-product.php нет класса ' . $class_name . '.'
	);
}

/*
 * 3. Layout: a responsive form grid, full-width search, reset fieldset chrome.
 */
velotok_filter_style_assert(
	(bool) preg_match( '/\.catalog-filter-form\s*\{[^}]*display:\s*grid/', $compact ),
	'Форма фильтров должна раскладываться сеткой.'
);
velotok_filter_style_assert(
	(bool) preg_match( '/\.catalog-filter-form\s*\{[^}]*grid-template-columns:\s*repeat\(auto-fit,\s*minmax\(190px,\s*1fr\)\)/', $compact ),
	'У формы фильтров должна быть адаптивная сетка колонок.'
);
velotok_filter_style_assert(
	(bool) preg_match( '/\.catalog-filter-form fieldset,\s*\.catalog-filter-range\s*\{[^}]*border:\s*0/', $compact ),
	'У fieldset фильтров должна сбрасываться браузерная рамка.'
);
velotok_filter_style_assert(
	(bool) preg_match( '/\.catalog-filter-search\s*\{[^}]*grid-column:\s*1\s*\/\s*-1/', $compact ),
	'Строка поиска должна занимать всю ширину формы.'
);
velotok_filter_style_assert(
	(bool) preg_match( '/\.catalog-filter-range\s*\{[^}]*grid-template-columns:\s*repeat\(2,\s*minmax\(0,\s*1fr\)\)/', $compact ),
	'Поля «от» и «до» должны стоять в две колонки.'
);
velotok_filter_style_assert(
	(bool) preg_match( '/\.catalog-filter-form input\s*\{[^}]*width:\s*100%/', $compact ),
	'Поля фильтров должны занимать всю ширину ячейки.'
);
velotok_filter_style_assert(
	(bool) preg_match( '/\.catalog-filter-form input:focus\s*\{[^}]*outline:\s*none/', $compact ),
	'У полей фильтров должен быть видимый стиль фокуса вместо браузерного.'
);
velotok_filter_style_assert(
	(bool) preg_match( '/\.catalog-filter-form \.catalog-filter-actions \.button\s*\{[^}]*border-radius:\s*999px/', $compact ),
	'Кнопка «Применить» должна быть стилизована как кнопка темы.'
);
velotok_filter_style_assert(
	(bool) preg_match( '/\.catalog-filter-form \.catalog-filter-actions \.text-link\s*\{[^}]*text-decoration:\s*none/', $compact ),
	'Ссылка «Сбросить» должна быть стилизована как кнопка, а не браузерная ссылка.'
);

/*
 * 4. The filter form reflows at the narrow control widths.
 */
velotok_filter_style_assert(
	(bool) preg_match( '/@media\s*\(max-width:\s*768px\)\s*\{\s*\.catalog-filter-form\s*\{\s*grid-template-columns:\s*1fr/', $compact ),
	'На 768px форма фильтров должна перестраиваться в одну колонку.'
);
velotok_filter_style_assert(
	(bool) preg_match( '/@media\s*\(max-width:\s*360px\)\s*\{\s*\.catalog-filter-form\s*\{\s*padding:\s*16px;?\s*\}\s*\.catalog-filter-range\s*\{\s*grid-template-columns:\s*1fr/', $compact ),
	'На 360px поля «от» и «до» должны перестраиваться в одну колонку.'
);

/*
 * 5. The filter block must be valid CSS.
 */
velotok_filter_style_assert(
	substr_count( $filters_css, '{' ) === substr_count( $filters_css, '}' ),
	'В блоке стилей фильтров несбалансированные фигурные скобки.'
);

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок стилей фильтров каталога не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: фильтры каталога получают стили темы на десктопе и узких экранах (EBIKE-19).' . PHP_EOL;
exit( 0 );
