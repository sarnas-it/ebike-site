<?php
/**
 * Behaviour test for TESTEB-56: the theme reflows at the control widths.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/theme-control-widths-test.php
 *
 * The test reads the responsive stylesheets and checks the explicit grid
 * transitions at 1440, 1024, 768 and 360 px. It fails when a control width is
 * missing or when the product, category and article grids keep the wrong
 * column count at that width.
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
function velotok_control_assert( $condition, $message ) {
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
function velotok_control_read( $relative_path ) {
	global $velotok_theme_dir;

	$file = $velotok_theme_dir . '/' . ltrim( $relative_path, '/' );

	if ( ! is_readable( $file ) ) {
		return '';
	}

	return (string) file_get_contents( $file );
}

/**
 * Extract the body of a single media query block.
 *
 * @param string $css   Stylesheet contents.
 * @param string $query Media condition, e.g. "max-width:768px".
 * @return string
 */
function velotok_control_media_block( $css, $query ) {
	if ( ! preg_match( '/^(min|max)-width\s*:\s*(\d+)px$/', trim( $query ), $parts ) ) {
		return '';
	}

	$pattern = '/@media\s*\(\s*' . preg_quote( $parts[1] . '-width', '/' ) . '\s*:\s*' . preg_quote( $parts[2] . 'px', '/' ) . '\s*\)\s*\{/';

	if ( ! preg_match( $pattern, $css, $match, PREG_OFFSET_CAPTURE ) ) {
		return '';
	}

	$start = $match[0][1] + strlen( $match[0][0] );
	$depth = 1;
	$ends  = strlen( $css );

	for ( $index = $start; $index < $ends; $index++ ) {
		if ( '{' === $css[ $index ] ) {
			$depth++;
		} elseif ( '}' === $css[ $index ] ) {
			$depth--;

			if ( 0 === $depth ) {
				return substr( $css, $start, $index - $start );
			}
		}
	}

	return '';
}

/**
 * Remove insignificant whitespace so selector/declaration pairs are easy to
 * match across compact and tab-indented stylesheets.
 *
 * @param string $css Stylesheet fragment.
 * @return string
 */
function velotok_control_compact( $css ) {
	return preg_replace( '/\s+/', '', (string) $css );
}

/**
 * Assert that a media block contains a selector with the expected grid columns.
 *
 * @param string $block    Media query body.
 * @param string $selector CSS selector.
 * @param string $columns  Expected grid-template-columns value.
 * @param string $message  Failure message.
 * @return void
 */
function velotok_control_grid( $block, $selector, $columns, $message ) {
	$compact = velotok_control_compact( $block );
	$needle  = $selector . '{grid-template-columns:' . $columns;

	velotok_control_assert( false !== strpos( $compact, $needle ), $message );
}

$main_css = velotok_control_read( 'assets/css/main.css' );
$wp_css   = velotok_control_read( 'assets/css/wp.css' );

/*
 * 1. Both stylesheets define the four control widths with real rules.
 */
$queries = array(
	1440 => 'min-width:1440px',
	1024 => 'max-width:1024px',
	768  => 'max-width:768px',
	360  => 'max-width:360px',
);

foreach ( $queries as $width => $query ) {
	$main_block = velotok_control_media_block( $main_css, $query );
	$wp_block   = velotok_control_media_block( $wp_css, $query );

	velotok_control_assert( '' !== $main_block, 'В main.css нет правил для контрольной ширины ' . $width . 'px.' );
	velotok_control_assert( '' !== $wp_block, 'В wp.css нет правил для контрольной ширины ' . $width . 'px.' );
}

/*
 * 2. The home product and category grids reflow: 3/4 -> 2 -> 1 -> 1 columns.
 */
$main_1440 = velotok_control_media_block( $main_css, $queries[1440] );
$main_1024 = velotok_control_media_block( $main_css, $queries[1024] );
$main_768  = velotok_control_media_block( $main_css, $queries[768] );
$main_360  = velotok_control_media_block( $main_css, $queries[360] );

velotok_control_grid( $main_1440, '.model-grid', 'repeat(3,1fr)', 'На 1440px сетка моделей должна оставаться в три колонки.' );
velotok_control_grid( $main_1440, '.cat-grid', 'repeat(4,1fr)', 'На 1440px сетка категорий должна оставаться в четыре колонки.' );
velotok_control_grid( $main_1024, '.model-grid', 'repeat(2,1fr)', 'На 1024px сетка моделей должна перестраиваться в две колонки.' );
velotok_control_grid( $main_1024, '.cat-grid', 'repeat(2,1fr)', 'На 1024px сетка категорий должна перестраиваться в две колонки.' );
velotok_control_grid( $main_768, '.model-grid', '1fr', 'На 768px сетка моделей должна перестраиваться в одну колонку.' );
velotok_control_grid( $main_768, '.cat-grid', '1fr', 'На 768px сетка категорий должна перестраиваться в одну колонку.' );
velotok_control_grid( $main_360, '.model-grid,.cat-grid', '1fr', 'На 360px сетки моделей и категорий должны оставаться в одну колонку.' );

/*
 * 3. The WooCommerce catalog and the article/category listing reflow too.
 */
$wp_1440 = velotok_control_media_block( $wp_css, $queries[1440] );
$wp_1024 = velotok_control_media_block( $wp_css, $queries[1024] );
$wp_768  = velotok_control_media_block( $wp_css, $queries[768] );
$wp_360  = velotok_control_media_block( $wp_css, $queries[360] );

velotok_control_grid( $wp_1440, '.catalog-grid', 'repeat(3,minmax(0,1fr))', 'На 1440px каталог WooCommerce должен оставаться в три колонки.' );
velotok_control_grid( $wp_1440, '.post-grid', 'repeat(3,minmax(0,1fr))', 'На 1440px список статей должен оставаться в три колонки.' );
velotok_control_grid( $wp_1024, '.catalog-grid', 'repeat(2,minmax(0,1fr))', 'На 1024px каталог WooCommerce должен перестраиваться в две колонки.' );
velotok_control_grid( $wp_1024, '.post-grid', 'repeat(2,minmax(0,1fr))', 'На 1024px список статей должен перестраиваться в две колонки.' );
velotok_control_grid( $wp_768, '.catalog-grid', '1fr', 'На 768px каталог WooCommerce должен перестраиваться в одну колонку.' );
velotok_control_grid( $wp_768, '.post-grid', '1fr', 'На 768px список статей должен перестраиваться в одну колонку.' );
velotok_control_grid( $wp_360, '.catalog-grid,.post-grid', '1fr', 'На 360px каталог и список статей должны оставаться в одну колонку.' );

/*
 * 4. Small screens must not introduce a fixed-width layout or hide overflow.
 */
$main_compact = velotok_control_compact( $main_css );
velotok_control_assert(
	false !== strpos( $main_compact, 'body{margin:0;font-family:var(--sans);background:var(--paper);color:var(--ink);font-size:16px;line-height:1.5;overflow-x:clip' ),
	'На узких ширинах страница должна обрезать горизонтальный выход, а не требовать фиксированной ширины.'
);
velotok_control_assert(
	false === strpos( $main_compact, '.model-grid{grid-template-columns:repeat(3,1fr);min-width' ),
	'Сетка моделей не должна задавать фиксированную минимальную ширину.'
);

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок контрольных ширин не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: тема перестраивается по контрольным ширинам 1440, 1024, 768 и 360 px (TESTEB-56).' . PHP_EOL;
exit( 0 );
