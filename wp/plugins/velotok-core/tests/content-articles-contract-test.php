<?php
/**
 * Contract test for the mocked content articles stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/content-articles-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/content-articles-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer exposes the required content areas about products and the
 * store, their validation rules and coverage checks.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/content-articles.php';

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки контентных статей.' . PHP_EOL );
	exit( 1 );
}

require_once $stub_path;

$failures = array();

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_content_articles_contract_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$expected_topics = array( 'product_articles', 'store_articles' );
$expected_labels = array(
	'product_articles' => 'Статьи о товарах',
	'store_articles'   => 'Статьи о магазине',
);

if ( ! function_exists( 'velotok_core_content_articles_contract' ) ) {
	velotok_content_articles_contract_assert( false, 'Отсутствует контракт контентных статей.' );
} else {
	$contract = velotok_core_content_articles_contract();

	velotok_content_articles_contract_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_content_articles_contract_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_content_articles_contract_assert( isset( $contract['spec'] ) && 'ecommerce-store' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_content_articles_contract_assert( isset( $contract['requirement'] ) && 'TESTEB-149' === $contract['requirement'], 'Контракт ссылается на неверное требование.' );
	velotok_content_articles_contract_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_content_articles_contract_assert( isset( $contract['parent_spec'] ) && 'ecommerce-store' === $contract['parent_spec'], 'Не зафиксирована родительская спецификация.' );
	velotok_content_articles_contract_assert( isset( $contract['membership'] ) && 'hybrid' === $contract['membership'], 'Способ определения принадлежности должен быть гибридным.' );
	velotok_content_articles_contract_assert( isset( $contract['required_fields'] ) && in_array( 'title', $contract['required_fields'], true ) && in_array( 'content', $contract['required_fields'], true ), 'Не заданы обязательные поля статьи.' );
	velotok_content_articles_contract_assert( isset( $contract['open_questions'] ) && 5 === count( $contract['open_questions'] ), 'Не зафиксированы пять открытых вопросов.' );

	velotok_content_articles_contract_assert(
		isset( $contract['required_topics'] ) && $expected_topics === $contract['required_topics'],
		'Контент должен включать ровно две обязательные области: товары и магазин.'
	);

	foreach ( $expected_topics as $topic ) {
		velotok_content_articles_contract_assert( isset( $contract['topics'][ $topic ] ), sprintf( 'Не описана тематика «%s».', $topic ) );
		velotok_content_articles_contract_assert(
			isset( $contract['topics'][ $topic ]['label'] ) && $expected_labels[ $topic ] === $contract['topics'][ $topic ]['label'],
			sprintf( 'Неверная подпись тематики «%s».', $topic )
		);
		velotok_content_articles_contract_assert(
			isset( $contract['topics'][ $topic ]['mocked'] ) && true === $contract['topics'][ $topic ]['mocked'],
			sprintf( 'Тематика «%s» не помечена mocked: true.', $topic )
		);
		velotok_content_articles_contract_assert(
			isset( $contract['topics'][ $topic ]['criteria']['topic_terms'] ) && is_array( $contract['topics'][ $topic ]['criteria']['topic_terms'] ),
			sprintf( 'Не заданы критерии тематики «%s».', $topic )
		);
	}

	velotok_content_articles_contract_assert(
		isset( $contract['articles'] ) && is_array( $contract['articles'] ) && count( $contract['articles'] ) >= 2,
		'В заглушке должны быть демонстрационные статьи.'
	);
}

if ( function_exists( 'velotok_core_content_articles' ) ) {
	$articles = velotok_core_content_articles();

	velotok_content_articles_contract_assert( is_array( $articles ) && count( $articles ) >= 2, 'Список статей должен содержать минимум две статьи.' );

	foreach ( $articles as $article ) {
		velotok_content_articles_contract_assert( is_array( $article ), 'Каждая статья должна быть массивом.' );
		velotok_content_articles_contract_assert( isset( $article['mocked'] ) && true === $article['mocked'], 'Статья не помечена mocked: true.' );
		velotok_content_articles_contract_assert( ! empty( $article['title'] ), 'У статьи должен быть заголовок.' );
		velotok_content_articles_contract_assert( ! empty( $article['content'] ), 'У статьи должен быть текст.' );
		velotok_content_articles_contract_assert( ! empty( $article['topic'] ), 'У статьи должна быть тематика.' );
	}

	$product_articles = velotok_core_get_content_articles( 'product_articles' );
	$store_articles   = velotok_core_get_content_articles( 'store_articles' );

	velotok_content_articles_contract_assert( ! empty( $product_articles ), 'Должны быть статьи о товарах.' );
	velotok_content_articles_contract_assert( ! empty( $store_articles ), 'Должны быть статьи о магазине.' );

	$by_id = velotok_core_content_article( 'article-about-store' );
	velotok_content_articles_contract_assert( is_array( $by_id ), 'Статья должна находиться по идентификатору.' );
}

if ( function_exists( 'velotok_core_validate_content_article' ) ) {
	$valid = velotok_core_validate_content_article(
		array(
			'topic'   => 'product_articles',
			'title'   => 'Как выбрать электровелосипед',
			'content' => 'Сравнение моделей и характеристик электровелосипедов.',
		)
	);

	velotok_content_articles_contract_assert( true === $valid['valid'], 'Корректная статья должна признаваться валидной.' );
	velotok_content_articles_contract_assert( true === $valid['matched'], 'Корректная статья должна получать признак совпадения.' );
	velotok_content_articles_contract_assert( true === $valid['mocked'], 'Проверка статьи должна быть помечена mocked.' );
	velotok_content_articles_contract_assert( null === $valid['code'], 'У корректной статьи не должно быть ошибки.' );

	$missing = velotok_core_validate_content_article(
		array(
			'topic' => 'store_articles',
			'title' => 'О магазине',
		)
	);
	velotok_content_articles_contract_assert( false === $missing['valid'], 'Статья без текста не должна быть валидной.' );
	velotok_content_articles_contract_assert( 'CONTENT_ARTICLE_FIELD_REQUIRED' === $missing['code'], 'Для статьи без текста нужен CONTENT_ARTICLE_FIELD_REQUIRED.' );

	$unknown = velotok_core_validate_content_article(
		array(
			'topic'   => 'unknown_topic',
			'title'   => 'Посторонний материал',
			'content' => 'Текст без отношения к теме.',
		),
		'unknown_topic'
	);
	velotok_content_articles_contract_assert( false === $unknown['valid'], 'Неизвестная тематика не должна быть валидной.' );
	velotok_content_articles_contract_assert( 'TOPIC_UNKNOWN' === $unknown['code'], 'Для неизвестной тематики должен возвращаться TOPIC_UNKNOWN.' );

	$mismatch = velotok_core_validate_content_article(
		array(
			'topic'   => 'product_articles',
			'title'   => 'Как выбрать электровелосипед',
			'content' => 'Обзор моделей.',
		),
		'store_articles'
	);
	velotok_content_articles_contract_assert( false === $mismatch['valid'], 'Материал должен отклоняться при несовпадении ожидаемой тематики.' );
	velotok_content_articles_contract_assert( 'STORE_ARTICLES_MISMATCH' === $mismatch['code'], 'Для несовпадения тематики магазина нужен STORE_ARTICLES_MISMATCH.' );
}

if ( function_exists( 'velotok_core_check_content_articles_coverage' ) ) {
	$default = velotok_core_check_content_articles_coverage();

	velotok_content_articles_contract_assert( true === $default['complete'], 'Демонстрационные статьи должны полностью покрывать обе тематики.' );
	velotok_content_articles_contract_assert( true === $default['valid'], 'Полный охват должен быть валидным.' );
	velotok_content_articles_contract_assert( array() === $default['missing'], 'У полного охвата не должно быть недостающих тематик.' );
	velotok_content_articles_contract_assert( true === $default['mocked'], 'Результат проверки охвата должен быть помечен mocked.' );
	velotok_content_articles_contract_assert( 2 === $default['required_count'], 'В охвате должно быть две обязательные области.' );

	$incomplete = velotok_core_check_content_articles_coverage(
		array(
			array(
				'topic'   => 'product_articles',
				'title'   => 'Как выбрать электровелосипед',
				'content' => 'Обзор моделей и характеристик.',
			),
		)
	);

	velotok_content_articles_contract_assert( false === $incomplete['complete'], 'Набор без статей о магазине не должен считаться полным.' );
	velotok_content_articles_contract_assert( array( 'store_articles' ) === $incomplete['missing'], 'Должна быть зафиксирована недостающая тематика магазина.' );
	velotok_content_articles_contract_assert( in_array( 'CONTENT_ARTICLES_INCOMPLETE', $incomplete['errors'], true ), 'Для неполного охвата должен возвращаться CONTENT_ARTICLES_INCOMPLETE.' );

	$empty = velotok_core_check_content_articles_coverage( array() );
	velotok_content_articles_contract_assert( false === $empty['complete'], 'Пустой набор не должен считаться полным.' );
	velotok_content_articles_contract_assert( $expected_topics === $empty['missing'], 'Пустой набор должен не покрывать обе тематики.' );
}

$source = (string) file_get_contents( $stub_path );

$forbidden = array(
	'get' . 'env(',
	'apache_get' . 'env(',
	'$_' . 'ENV',
	'$_' . 'SERVER',
	'file_get' . '_contents(',
	'fopen(',
	'curl_' . 'init(',
	'wp_remote_' . 'get(',
	'wp_remote_' . 'post(',
);

foreach ( $forbidden as $needle ) {
	velotok_content_articles_contract_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт контентных статей о товарах и магазине на месте.' . PHP_EOL;
