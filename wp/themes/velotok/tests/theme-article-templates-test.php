<?php
/**
 * Behaviour test for TESTEB-57: the theme contains article templates.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/theme-article-templates-test.php
 *
 * The test checks that the article template hierarchy is present: the single
 * article, the archive fallback, the category (rubric), tag, author and search
 * listings, the comments template and a shared article card partial. It also
 * verifies that the templates render posts dynamically instead of hard-coding
 * content. Without the article templates the test fails.
 *
 * @package Velotok
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, 'This test must be run from the command line.' . PHP_EOL );
	exit( 1 );
}

$velotok_article_theme_dir = dirname( __DIR__ );
$failures                  = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_article_assert( $condition, $message ) {
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
function velotok_article_read( $relative_path ) {
	global $velotok_article_theme_dir;

	$file = $velotok_article_theme_dir . '/' . ltrim( $relative_path, '/' );

	if ( ! is_readable( $file ) ) {
		return '';
	}

	return (string) file_get_contents( $file );
}

/*
 * 1. The article template hierarchy exists inside the theme.
 */
$templates = array(
	'index.php'                              => 'запасной список статей',
	'archive.php'                            => 'архив статей',
	'category.php'                           => 'рубрика статей',
	'tag.php'                                => 'архив меток',
	'author.php'                             => 'архив автора',
	'search.php'                             => 'результаты поиска',
	'single.php'                             => 'отдельная статья',
	'comments.php'                           => 'комментарии к статье',
	'template-parts/content-post-card.php'  => 'общая карточка статьи',
);

foreach ( $templates as $template => $label ) {
	$file = $velotok_article_theme_dir . '/' . $template;
	velotok_article_assert( is_readable( $file ), 'Нет шаблона статьи: ' . $template . ' (' . $label . ').' );
}

/*
 * 2. Every listing template renders the real post loop through the shared
 *    article card, keeps the focusable main landmark and pagination.
 */
$listing_templates = array(
	'index.php',
	'archive.php',
	'category.php',
	'tag.php',
	'author.php',
	'search.php',
);

foreach ( $listing_templates as $template ) {
	$source = velotok_article_read( $template );

	velotok_article_assert( false !== strpos( $source, 'get_header' ), 'Шаблон статьи должен подключать шапку: ' . $template );
	velotok_article_assert( false !== strpos( $source, 'get_footer' ), 'Шаблон статьи должен подключать подвал: ' . $template );
	velotok_article_assert(
		(bool) preg_match( '/<main\b[^>]*\bid="main"[^>]*\btabindex="-1"/i', $source ),
		'Список статей должен сохранять доступный landmark main: ' . $template
	);
	velotok_article_assert( false !== strpos( $source, 'have_posts' ), 'Список статей должен проверять наличие записей: ' . $template );
	velotok_article_assert( false !== strpos( $source, 'the_post' ), 'Список статей должен выводить записи через цикл: ' . $template );
	velotok_article_assert(
		(bool) preg_match( "/get_template_part\(\s*'template-parts\/content-post-card'\s*\)/", $source ),
		'Список статей должен переиспользовать общую карточку статьи: ' . $template
	);
	velotok_article_assert(
		false !== strpos( $source, 'the_posts_pagination' ),
		'Список статей должен выводить пагинацию: ' . $template
	);
	velotok_article_assert(
		false === strpos( $source, '12 990' ) && false === strpos( $source, 'Велоток X' ),
		'Шаблон статьи не должен содержать захардкоженных записей: ' . $template
	);
}

/*
 * 3. The shared article card renders post data dynamically.
 */
$card = velotok_article_read( 'template-parts/content-post-card.php' );

velotok_article_assert( (bool) preg_match( '/<article\b/i', $card ), 'Карточка статьи должна быть размечена элементом article.' );
velotok_article_assert( false !== strpos( $card, 'post_class' ), 'Карточка статьи должна использовать post_class.' );
velotok_article_assert( false !== strpos( $card, 'the_title' ), 'Карточка статьи должна выводить заголовок записи.' );
velotok_article_assert( false !== strpos( $card, 'the_permalink' ), 'Карточка статьи должна ссылаться на постоянную ссылку.' );
velotok_article_assert( false !== strpos( $card, 'has_post_thumbnail' ), 'Карточка статьи должна учитывать миниатюру.' );
velotok_article_assert( false !== strpos( $card, 'the_post_thumbnail' ), 'Карточка статьи должна выводить миниатюру.' );
velotok_article_assert( false !== strpos( $card, 'get_the_excerpt' ), 'Карточка статьи должна выводить краткое описание.' );
velotok_article_assert( false !== strpos( $card, 'get_the_date' ), 'Карточка статьи должна выводить дату публикации.' );

/*
 * 4. The single article renders its content and the comments template.
 */
$single = velotok_article_read( 'single.php' );

velotok_article_assert( (bool) preg_match( '/<article\b/i', $single ), 'Отдельная статья должна быть размечена элементом article.' );
velotok_article_assert( false !== strpos( $single, 'post_class' ), 'Отдельная статья должна использовать post_class.' );
velotok_article_assert( false !== strpos( $single, 'the_title' ), 'Отдельная статья должна выводить заголовок.' );
velotok_article_assert( false !== strpos( $single, 'the_content' ), 'Отдельная статья должна выводить полный текст.' );
velotok_article_assert( false !== strpos( $single, 'comments_template' ), 'Отдельная статья должна подключать комментарии.' );

$comments = velotok_article_read( 'comments.php' );
velotok_article_assert( false !== strpos( $comments, 'wp_list_comments' ), 'Шаблон комментариев должен выводить список комментариев.' );
velotok_article_assert( false !== strpos( $comments, 'comment_form' ), 'Шаблон комментариев должен выводить форму.' );

/*
 * 5. Rubrics and search expose their own contextual headings and queries.
 */
$category = velotok_article_read( 'category.php' );
velotok_article_assert( false !== strpos( $category, 'single_cat_title' ), 'Шаблон рубрики должен выводить название рубрики.' );
velotok_article_assert( false !== strpos( $category, 'category_description' ), 'Шаблон рубрики должен выводить описание рубрики.' );

$search = velotok_article_read( 'search.php' );
velotok_article_assert( false !== strpos( $search, 'get_search_query' ), 'Шаблон поиска должен выводить поисковый запрос.' );
velotok_article_assert( false !== strpos( $search, 'get_search_form' ), 'Шаблон поиска должен содержать форму поиска.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок шаблонов статей не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: тема содержит шаблоны статей, рубрик и общую карточку записи (TESTEB-57).' . PHP_EOL;
exit( 0 );
