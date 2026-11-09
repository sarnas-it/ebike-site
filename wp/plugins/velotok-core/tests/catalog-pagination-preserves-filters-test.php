<?php
/**
 * Behaviour test for TESTEB-22: catalog pagination keeps filters and sorting.
 *
 * Run from the project root:
 *   php wp/plugins/velotok-core/tests/catalog-pagination-preserves-filters-test.php
 *
 * The test checks that page links carry the search query, contract attribute
 * filters and the normalized orderby value, while never leaking the previous
 * page number. It also checks that the product accessor can slice a filtered
 * and sorted result by page.
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

$GLOBALS['velotok_core_pagination_failures'] = array();
$GLOBALS['velotok_core_pagination_links_args'] = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_core_pagination_assert( $condition, $message ) {
	if ( ! $condition ) {
		$GLOBALS['velotok_core_pagination_failures'][] = $message;
	}
}

/*
 * Minimal WordPress API used by the catalog path.
 */
function add_filter() { return true; }
function add_action() {}
function sanitize_key( $key ) { return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function home_url( $path = '' ) { return 'http://example.test' . $path; }
function paginate_links( $args = array() ) {
	$GLOBALS['velotok_core_pagination_links_args'] = $args;

	return '<ul class="page-numbers"><li><span class="page-numbers current">' . (int) $args['current'] . '</span></li></ul>';
}

require_once dirname( __DIR__ ) . '/includes/catalog.php';

/**
 * Parse query arguments from a URL.
 *
 * @param string $url URL.
 * @return array<string,mixed>
 */
function velotok_core_pagination_url_query( $url ) {
	$query = (string) parse_url( $url, PHP_URL_QUERY );
	$args  = array();

	if ( '' !== $query ) {
		parse_str( $query, $args );
	}

	return $args;
}

$source = array(
	's'              => 'Хребет',
	'orderby'        => 'price',
	'paged'          => 2,
	'velotok_filter' => array(
		'badge'        => 'Для гор',
		'range_km_min' => '100',
		'range_km_max' => '130',
		'motor_w'      => '750',
		'unknown'      => 'ignore-me',
	),
	'unknown'        => 'ignore-me',
);

/*
 * 1. The contract exposes the pagination parameter and requirement.
 */
$contract = velotok_core_catalog_contract();

velotok_core_pagination_assert( function_exists( 'velotok_core_catalog_pagination' ), 'Не определена пагинация каталога.' );
velotok_core_pagination_assert( function_exists( 'velotok_core_catalog_pagination_url' ), 'Не определена ссылка пагинации каталога.' );
velotok_core_pagination_assert( function_exists( 'velotok_core_catalog_pagination_query_args' ), 'Не определены параметры пагинации каталога.' );
velotok_core_pagination_assert( isset( $contract['pagination_parameter'] ) && 'paged' === $contract['pagination_parameter'], 'Параметр пагинации должен называться paged.' );
velotok_core_pagination_assert( isset( $contract['pagination_requirement'] ) && 'TESTEB-22' === $contract['pagination_requirement'], 'Контракт должен ссылаться на TESTEB-22.' );

/*
 * 2. Query arguments keep search, filters and sorting and drop the old page.
 */
$args = velotok_core_catalog_pagination_query_args( $source );

velotok_core_pagination_assert( isset( $args['s'] ) && 'Хребет' === $args['s'], 'Пагинация не сохраняет поисковый запрос.' );
velotok_core_pagination_assert( isset( $args['orderby'] ) && 'price' === $args['orderby'], 'Пагинация не сохраняет сортировку.' );
velotok_core_pagination_assert( isset( $args['velotok_filter'] ) && is_array( $args['velotok_filter'] ), 'Пагинация не сохраняет фильтры контракта.' );
velotok_core_pagination_assert( isset( $args['velotok_filter']['badge'] ) && 'Для гор' === $args['velotok_filter']['badge'], 'Пагинация потеряла фильтр по бейджу.' );
velotok_core_pagination_assert( isset( $args['velotok_filter']['range_km']['min'] ) && 100.0 === (float) $args['velotok_filter']['range_km']['min'], 'Пагинация потеряла нижнюю границу диапазона.' );
velotok_core_pagination_assert( isset( $args['velotok_filter']['range_km']['max'] ) && 130.0 === (float) $args['velotok_filter']['range_km']['max'], 'Пагинация потеряла верхнюю границу диапазона.' );
velotok_core_pagination_assert( isset( $args['velotok_filter']['motor_w'] ) && 750.0 === (float) $args['velotok_filter']['motor_w'], 'Пагинация потеряла числовой фильтр мотора.' );
velotok_core_pagination_assert( ! isset( $args['paged'] ), 'Старый номер страницы не должен попадать в параметры следующей страницы.' );
velotok_core_pagination_assert( ! isset( $args['unknown'], $args['velotok_filter']['unknown'] ), 'Неизвестные параметры не должны попадать в пагинацию.' );

$invalid = velotok_core_catalog_pagination_query_args( array( 'orderby' => 'hacked', 'velotok_filter' => array( 'badge' => 'Хит' ) ) );
velotok_core_pagination_assert( isset( $invalid['orderby'] ) && 'menu_order' === $invalid['orderby'], 'Недопустимая сортировка должна заменяться значением по умолчанию.' );

/*
 * 3. Page URLs carry every preserved argument and set only the target page.
 */
$page_three = velotok_core_catalog_pagination_url( 3, 'http://example.test/catalog/', $source );
$query      = velotok_core_pagination_url_query( $page_three );

velotok_core_pagination_assert( 3 === (int) $query['paged'], 'Ссылка страницы не содержит номер целевой страницы.' );
velotok_core_pagination_assert( isset( $query['s'] ) && 'Хребет' === $query['s'], 'Ссылка страницы потеряла поисковый запрос.' );
velotok_core_pagination_assert( isset( $query['orderby'] ) && 'price' === $query['orderby'], 'Ссылка страницы потеряла сортировку.' );
velotok_core_pagination_assert( isset( $query['velotok_filter']['badge'] ) && 'Для гор' === $query['velotok_filter']['badge'], 'Ссылка страницы потеряла фильтр по бейджу.' );
velotok_core_pagination_assert( isset( $query['velotok_filter']['range_km']['min'] ) && 100.0 === (float) $query['velotok_filter']['range_km']['min'], 'Ссылка страницы потеряла диапазон фильтра.' );
velotok_core_pagination_assert( isset( $query['velotok_filter']['motor_w'] ) && 750.0 === (float) $query['velotok_filter']['motor_w'], 'Ссылка страницы потеряла числовой фильтр.' );

$page_one = velotok_core_catalog_pagination_url( 1, 'http://example.test/catalog/', $source );
$query    = velotok_core_pagination_url_query( $page_one );

velotok_core_pagination_assert( ! isset( $query['paged'] ), 'Первая страница не должна содержать paged.' );
velotok_core_pagination_assert( isset( $query['orderby'] ) && 'price' === $query['orderby'], 'Первая страница потеряла сортировку.' );

$stale = velotok_core_catalog_pagination_url( 1, 'http://example.test/catalog/?paged=2&product_cat=city', $source );
$query = velotok_core_pagination_url_query( $stale );

velotok_core_pagination_assert( ! isset( $query['paged'] ), 'Старый номер страницы не должен оставаться в базовом URL первой страницы.' );
velotok_core_pagination_assert( isset( $query['product_cat'] ) && 'city' === $query['product_cat'], 'Пагинация не должна терять посторонние параметры базового URL.' );
velotok_core_pagination_assert( isset( $query['orderby'] ) && 'price' === $query['orderby'], 'Базовый URL со старой страницей не должен терять сортировку.' );

$stale_three = velotok_core_catalog_pagination_url( 3, 'http://example.test/catalog/?paged=2&page=2', $source );
$query       = velotok_core_pagination_url_query( $stale_three );

velotok_core_pagination_assert( 3 === (int) $query['paged'], 'Целевая страница должна заменять старый номер страницы.' );
velotok_core_pagination_assert( ! isset( $query['page'] ), 'Старый параметр page не должен сохраняться в ссылке пагинации.' );

/*
 * 4. The generated pagination markup uses the same URL builder.
 */
$links     = velotok_core_catalog_pagination_links( array(
	'base'    => 'http://example.test/catalog/',
	'current' => 2,
	'total'   => 4,
	'source'  => $source,
) );
$link_args = $GLOBALS['velotok_core_pagination_links_args'];
$link_base = isset( $link_args['base'] ) ? (string) $link_args['base'] : '';

velotok_core_pagination_assert( '' !== $links, 'Разметка пагинации не сформирована.' );
velotok_core_pagination_assert( false !== strpos( $link_base, 's=%D0%A5%D1%80%D0%B5%D0%B1%D0%B5%D1%82' ), 'Шаблон пагинации не передаёт поиск в ссылки.' );
velotok_core_pagination_assert( false !== strpos( $link_base, 'orderby=price' ), 'Шаблон пагинации не передаёт сортировку в ссылки.' );
velotok_core_pagination_assert( false !== strpos( $link_base, 'velotok_filter' ), 'Шаблон пагинации не передаёт фильтры в ссылки.' );
velotok_core_pagination_assert( false !== strpos( $link_base, 'paged=%#%' ), 'Шаблон пагинации не подставляет номер страницы.' );
velotok_core_pagination_assert( false === strpos( $link_base, 'paged=2' ), 'Шаблон пагинации не должен сохранять старый номер страницы.' );

velotok_core_catalog_pagination_links( array(
	'base'    => 'http://example.test/catalog/?paged=2&product_cat=city',
	'current' => 2,
	'total'   => 4,
	'source'  => $source,
) );
$stale_link_args = $GLOBALS['velotok_core_pagination_links_args'];
$stale_link_base = isset( $stale_link_args['base'] ) ? (string) $stale_link_args['base'] : '';

velotok_core_pagination_assert( false === strpos( $stale_link_base, 'paged=2' ), 'Шаблон пагинации не должен оставлять старую страницу базового URL.' );
velotok_core_pagination_assert( false !== strpos( $stale_link_base, 'product_cat=city' ), 'Шаблон пагинации не должен терять посторонние параметры базового URL.' );
velotok_core_pagination_assert( false !== strpos( $stale_link_base, 'paged=%#%' ), 'Шаблон пагинации должен подставлять только целевой номер страницы.' );

ob_start();
velotok_core_catalog_pagination( array(
	'base'    => 'http://example.test/catalog/',
	'current' => 2,
	'total'   => 4,
	'source'  => $source,
) );
$markup = (string) ob_get_clean();

velotok_core_pagination_assert( false !== strpos( $markup, 'woocommerce-pagination' ), 'Пагинация не размечена как woocommerce-pagination.' );
velotok_core_pagination_assert( false !== strpos( $markup, 'aria-label=' ), 'Пагинация должна иметь доступную подпись.' );

/*
 * 5. The product accessor slices a filtered and sorted list by page without
 *    losing the filters or sorting.
 */
$filtered = velotok_core_catalog_products(
	array(
		'velotok_filter' => array( 'category' => 'city' ),
		'orderby'        => 'price-desc',
		'paged'          => 1,
		'per_page'       => 1,
	)
);
$next = velotok_core_catalog_products(
	array(
		'velotok_filter' => array( 'category' => 'city' ),
		'orderby'        => 'price-desc',
		'paged'          => 2,
		'per_page'       => 1,
	)
);

velotok_core_pagination_assert( 1 === count( $filtered ), 'Пагинация не ограничивает выдачу размером страницы.' );
velotok_core_pagination_assert( 1 === count( $next ), 'Вторая страница не содержит товар.' );
velotok_core_pagination_assert( isset( $filtered[0]['category'], $next[0]['category'] ) && 'city' === $filtered[0]['category'] && 'city' === $next[0]['category'], 'Пагинация потеряла фильтр категории.' );
velotok_core_pagination_assert( $filtered[0]['slug'] !== $next[0]['slug'], 'Страницы не различаются.' );
velotok_core_pagination_assert( (float) $filtered[0]['price'] >= (float) $next[0]['price'], 'Пагинация потеряла сортировку по убыванию цены.' );

if ( $GLOBALS['velotok_core_pagination_failures'] ) {
	fwrite( STDERR, 'FAIL: ' . count( $GLOBALS['velotok_core_pagination_failures'] ) . ' проверок пагинации каталога не прошли:' . PHP_EOL );

	foreach ( $GLOBALS['velotok_core_pagination_failures'] as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: пагинация каталога сохраняет фильтры и сортировку (TESTEB-22).' . PHP_EOL;
exit( 0 );
