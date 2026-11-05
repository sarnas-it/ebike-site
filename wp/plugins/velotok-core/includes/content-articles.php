<?php
/**
 * Content articles stub contract.
 *
 * This is the single access point for the content articles about products and
 * the store. The current iteration deliberately uses fixed in-memory data: it
 * does not read environment variables, files, the database or remote services.
 *
 * The contract covers two required content areas: articles about products and
 * articles about the store. Classification, validation and coverage checks are
 * mocked contracts; editorial expertise, publication workflows and a real CMS
 * integration are out of scope.
 *
 * Spec: ecommerce-store (requirement TESTEB-149).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

/**
 * Return the content articles contract.
 *
 * The contract documents:
 * - the spec identifier, the explicit `mocked` marker and the stub source;
 * - two required content areas: product articles and store articles;
 * - deterministic matching criteria for both areas;
 * - a fixed set of mocked demo articles used by the contract tests;
 * - error codes for unknown topics, incomplete articles and missing coverage;
 * - the open questions that block a real editorial integration.
 *
 * @return array<string,mixed>
 */
function velotok_core_content_articles_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$topics = array(
		'product_articles' => array(
			'id'             => 'product_articles',
			'slug'           => 'product_articles',
			'code'           => 'product_articles',
			'label'          => 'Статьи о товарах',
			'description'    => 'Обзоры, сравнения и руководства по выбору электровелосипедов и их характеристик.',
			'mocked'         => true,
			'required'       => true,
			'criteria'       => array(
				'topic_terms'   => array( 'товар', 'велосипед', 'модель', 'характеристик', 'обзор', 'выбор', 'сравнен', 'аккумулятор', 'мотор', 'рама', 'запас хода' ),
				'context_terms' => array( 'электровелосипед', 'велоток', 'магазин', 'каталог', 'поездк', 'маршрут' ),
				'match'         => 'product topic term AND electric bike context term',
			),
			'mismatch_code'  => 'PRODUCT_ARTICLES_MISMATCH',
			'missing_code'   => 'PRODUCT_ARTICLE_REQUIRED',
		),
		'store_articles'   => array(
			'id'             => 'store_articles',
			'slug'           => 'store_articles',
			'code'           => 'store_articles',
			'label'          => 'Статьи о магазине',
			'description'    => 'Материалы о магазине, мастерской, доставке, гарантии, сервисе и команде.',
			'mocked'         => true,
			'required'       => true,
			'criteria'       => array(
				'topic_terms'   => array( 'магазин', 'мастерск', 'доставк', 'гаранти', 'сервис', 'команд', 'оплат', 'заказ', 'шоурум', 'велоток' ),
				'context_terms' => array( 'электровелосипед', 'велоток', 'покуп', 'клиент', 'заказ', 'поездк' ),
				'match'         => 'store topic term AND customer context term',
			),
			'mismatch_code'  => 'STORE_ARTICLES_MISMATCH',
			'missing_code'   => 'STORE_ARTICLE_REQUIRED',
		),
	);

	$articles = array(
		array(
			'id'       => 'article-how-to-choose-ebike',
			'slug'     => 'kak-vybrat-elektrovelosiped',
			'topic'    => 'product_articles',
			'title'    => 'Как выбрать электровелосипед: запас хода, мотор и рама',
			'excerpt'  => 'Разбираем характеристики, которые важнее всего при выборе электровелосипеда для города, леса и грузоперевозок.',
			'content'  => 'Перед покупкой электровелосипеда определите маршрут и тип покрытия. Для города обычно достаточно мотора 250–350 Вт и запаса хода 60–90 км, для гор и грунта важнее момент и подвеска. Проверьте тип рамы, размер колёс и возможность установки багажника.',
			'author'   => 'Мастерская «Велоток»',
			'mocked'   => true,
			'featured' => true,
		),
		array(
			'id'       => 'article-battery-care',
			'slug'     => 'uhod-za-akkumulyatorom',
			'topic'    => 'product_articles',
			'title'    => 'Уход за аккумулятором электровелосипеда',
			'excerpt'  => 'Как хранить и заряжать батарею, чтобы она дольше сохраняла запас хода.',
			'content'  => 'Храните аккумулятор при умеренной температуре и заряжайте его до 60–80 процентов, если не планируете поездки. Не оставляйте батарею полностью разряженной на длительное время. Перед зимним хранением зарядите её наполовину и периодически проверяйте напряжение.',
			'author'   => 'Сервис «Велоток»',
			'mocked'   => true,
			'featured' => false,
		),
		array(
			'id'       => 'article-about-store',
			'slug'     => 'o-magazine-velotok',
			'topic'    => 'store_articles',
			'title'    => 'О магазине «Велоток»: шоурум, мастерская и команда',
			'excerpt'  => 'Кто мы, как собираем велосипеды и почему проверяем каждую модель перед выдачей.',
			'content'  => '«Велоток» — это магазин и мастерская электровелосипедов. В шоуруме можно прокатиться на разных моделях, а мастера помогут подобрать раму и настроить посадку. Каждый заказ мы собираем, проверяем тормоза, трансмиссию и электрику перед передачей клиенту.',
			'author'   => 'Команда «Велоток»',
			'mocked'   => true,
			'featured' => true,
		),
		array(
			'id'       => 'article-delivery-warranty',
			'slug'     => 'dostavka-i-garantiya',
			'topic'    => 'store_articles',
			'title'    => 'Доставка, оплата и гарантия в магазине «Велоток»',
			'excerpt'  => 'Как мы доставляем заказы, какие способы оплаты доступны и что покрывает гарантия.',
			'content'  => 'Мы доставляем собранные электровелосипеды в жёсткой коробке по Москве и Санкт-Петербургу на следующий день, в остальные города — курьерской службой. Доступна оплата при получении. Гарантия на раму и мотор составляет два года, а сервисная мастерская помогает с диагностикой аккумулятора.',
			'author'   => 'Команда «Велоток»',
			'mocked'   => true,
			'featured' => false,
		),
	);

	$contract = array(
		'spec'                  => 'ecommerce-store',
		'requirement'           => 'TESTEB-149',
		'source'                => 'stub',
		'mocked'                => true,
		'parent_spec'           => 'ecommerce-store',
		'parent_relation'       => 'Контентные статьи о товарах и магазине реализуют требование родительской спецификации ecommerce-store без публикации во внешней CMS.',
		'required_topics'       => array_keys( $topics ),
		'required_areas'        => array_values( $topics ),
		'topics'                => $topics,
		'articles'              => $articles,
		'article_count'         => count( $articles ),
		'membership'            => 'hybrid',
		'manual_hint_field'     => 'topic',
		'automatic_source'      => 'keywords',
		'classification_source' => 'stub',
		'future_source'         => 'wordpress_posts',
		'required_fields'       => array( 'title', 'content', 'topic' ),
		'optional_fields'       => array( 'slug', 'excerpt', 'author', 'featured', 'product_slug', 'image' ),
		'unknown_topic'         => 'TOPIC_UNKNOWN',
		'article_invalid'       => 'CONTENT_ARTICLE_INVALID',
		'article_incomplete'    => 'CONTENT_ARTICLE_INCOMPLETE',
		'field_required'        => 'CONTENT_ARTICLE_FIELD_REQUIRED',
		'coverage_incomplete'   => 'CONTENT_ARTICLES_INCOMPLETE',
		'open_questions'        => array(
			'themes'          => 'Какие темы и объём контентных статей нужны магазину (clarify-a2fd2f66).',
			'acceptance'      => 'Какие критерии приёмки предъявляются к контенту и как проверяется его качество (clarify-096bb945).',
			'product_links'   => 'Должны ли статьи о товарах быть связаны с конкретными карточками каталога и каким образом.',
			'store_sections'  => 'Какие разделы информации о магазине обязательны: доставка, гарантия, команда, мастерская.',
			'sources'         => 'Какие источники и редакционный процесс используются для реальных статей; текущая реализация — заглушка.',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer a full explicit contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_content_articles_coverage_contract() {
	return velotok_core_content_articles_contract();
}

/**
 * Alias kept for callers that use the singular contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_content_article_contract() {
	return velotok_core_content_articles_contract();
}

/**
 * Return every required content topic keyed by identifier.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_content_article_topics() {
	$contract = velotok_core_content_articles_contract();

	return $contract['topics'];
}

/**
 * Return the required content topic identifiers.
 *
 * @return string[]
 */
function velotok_core_content_article_keys() {
	$contract = velotok_core_content_articles_contract();

	return $contract['required_topics'];
}

/**
 * Normalize text for deterministic keyword matching.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function velotok_core_content_article_normalize_text( $value ) {
	$text = is_scalar( $value ) ? (string) $value : '';

	if ( function_exists( 'mb_strtolower' ) ) {
		$text = mb_strtolower( $text, 'UTF-8' );
	} else {
		$text = strtolower( $text );
	}

	return str_replace( 'ё', 'е', $text );
}

/**
 * Extract searchable text from an article without touching external storage.
 *
 * @param mixed $article Article data.
 * @param int   $depth   Recursion guard.
 * @return string
 */
function velotok_core_content_article_text( $article, $depth = 0 ) {
	if ( $depth > 6 ) {
		return '';
	}

	if ( is_string( $article ) || is_numeric( $article ) ) {
		return (string) $article;
	}

	if ( is_object( $article ) && method_exists( $article, '__toString' ) ) {
		return (string) $article;
	}

	if ( ! is_array( $article ) ) {
		return '';
	}

	$parts = array();

	foreach ( $article as $value ) {
		if ( is_scalar( $value ) ) {
			$parts[] = (string) $value;
		} elseif ( is_array( $value ) || is_object( $value ) ) {
			$nested = velotok_core_content_article_text( $value, $depth + 1 );

			if ( '' !== $nested ) {
				$parts[] = $nested;
			}
		}
	}

	return implode( ' ', $parts );
}

/**
 * Find the matching terms from a list in normalized text.
 *
 * @param string   $text Normalized text.
 * @param string[] $terms Candidate terms.
 * @return string[] Matched terms.
 */
function velotok_core_content_article_matched_terms( $text, $terms ) {
	$matches = array();

	foreach ( $terms as $term ) {
		$needle = velotok_core_content_article_normalize_text( $term );

		if ( '' === $needle ) {
			continue;
		}

		if ( false !== strpos( $text, $needle ) ) {
			$matches[] = $term;
		}
	}

	return array_values( array_unique( $matches ) );
}

/**
 * Normalize a topic identifier, label or alias.
 *
 * @param mixed $topic Topic identifier or label.
 * @return string Empty string for an unknown topic.
 */
function velotok_core_content_article_topic_key( $topic ) {
	if ( is_array( $topic ) ) {
		foreach ( array( 'topic', 'topic_slug', 'slug', 'id', 'code' ) as $key ) {
			if ( isset( $topic[ $key ] ) && is_scalar( $topic[ $key ] ) ) {
				$topic = $topic[ $key ];
				break;
			}
		}
	}

	if ( ! is_string( $topic ) ) {
		return '';
	}

	$key = velotok_core_content_article_normalize_text( trim( $topic ) );
	$key = (string) preg_replace( '/[\s-]+/', '_', $key );

	$aliases = array(
		'product_articles'            => 'product_articles',
		'product_article'             => 'product_articles',
		'products'                    => 'product_articles',
		'product'                     => 'product_articles',
		'products_articles'           => 'product_articles',
		'товары'                      => 'product_articles',
		'о_товарах'                   => 'product_articles',
		'статьи_о_товарах'            => 'product_articles',
		'store_articles'              => 'store_articles',
		'store_article'               => 'store_articles',
		'store'                       => 'store_articles',
		'shop'                        => 'store_articles',
		'about_store'                 => 'store_articles',
		'store_about'                 => 'store_articles',
		'магазин'                     => 'store_articles',
		'о_магазине'                  => 'store_articles',
		'статьи_о_магазине'           => 'store_articles',
	);

	if ( isset( $aliases[ $key ] ) ) {
		return $aliases[ $key ];
	}

	$topics = velotok_core_content_article_topics();

	if ( isset( $topics[ $key ] ) ) {
		return $key;
	}

	return '';
}

/**
 * Resolve a content topic definition.
 *
 * @param mixed $topic Topic identifier or label.
 * @return array<string,mixed>|null Null for an unknown topic.
 */
function velotok_core_content_article_topic( $topic ) {
	$key = velotok_core_content_article_topic_key( $topic );

	if ( '' === $key ) {
		return null;
	}

	$topics = velotok_core_content_article_topics();

	return $topics[ $key ];
}

/**
 * Read an explicit topic hint from an article.
 *
 * @param mixed $article Article data.
 * @return string Normalized topic key or an empty string.
 */
function velotok_core_content_article_manual_topic( $article ) {
	if ( ! is_array( $article ) ) {
		return '';
	}

	foreach ( array( 'topic', 'topic_slug', 'content_topic' ) as $field ) {
		if ( isset( $article[ $field ] ) ) {
			$key = velotok_core_content_article_topic_key( $article[ $field ] );

			if ( '' !== $key ) {
				return $key;
			}
		}
	}

	return '';
}

/**
 * Return the fixed mocked articles.
 *
 * @return array<int,array<string,mixed>>
 */
function velotok_core_content_articles() {
	$contract = velotok_core_content_articles_contract();

	return array_values( $contract['articles'] );
}

/**
 * Alias for callers that prefer an explicit list name.
 *
 * @return array<int,array<string,mixed>>
 */
function velotok_core_content_articles_list() {
	return velotok_core_content_articles();
}

/**
 * Return the fixed mocked articles filtered by topic.
 *
 * @param mixed $topic Optional topic identifier or label.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_get_content_articles( $topic = null ) {
	$articles = velotok_core_content_articles();

	if ( null === $topic || '' === $topic ) {
		return $articles;
	}

	$key = velotok_core_content_article_topic_key( $topic );

	if ( '' === $key ) {
		return array();
	}

	$filtered = array();

	foreach ( $articles as $article ) {
		if ( velotok_core_content_article_manual_topic( $article ) === $key ) {
			$filtered[] = $article;
		}
	}

	return $filtered;
}

/**
 * Find a fixed mocked article by identifier or slug.
 *
 * @param mixed $id Article identifier or slug.
 * @return array<string,mixed>|null Null when the article is not found.
 */
function velotok_core_content_article( $id ) {
	if ( ! is_string( $id ) && ! is_numeric( $id ) ) {
		return null;
	}

	$needle = (string) $id;

	foreach ( velotok_core_content_articles() as $article ) {
		$candidates = array();

		foreach ( array( 'id', 'slug' ) as $field ) {
			if ( isset( $article[ $field ] ) && is_scalar( $article[ $field ] ) ) {
				$candidates[] = (string) $article[ $field ];
			}
		}

		if ( in_array( $needle, $candidates, true ) ) {
			return $article;
		}
	}

	return null;
}

/**
 * Validate an article against the required fields and one content topic.
 *
 * The result is an interface-level mocked contract: an article is valid when
 * it has a resolvable topic, a title and content. When the topic is omitted it
 * is classified by deterministic keyword matching. No editorial expertise and
 * no database access is performed.
 *
 * @param mixed $article Article data.
 * @param mixed $topic   Optional expected topic identifier or label.
 * @return array<string,mixed>
 */
function velotok_core_validate_content_article( $article, $topic = null ) {
	$contract = velotok_core_content_articles_contract();
	$result   = array(
		'valid'          => false,
		'matched'        => false,
		'compliant'      => false,
		'article'        => null,
		'topic'          => '',
		'mocked'         => true,
		'spec'           => $contract['spec'],
		'classification' => 'automatic',
		'code'           => null,
		'error'          => null,
		'errors'         => array(),
		'missing_fields' => array(),
		'matched_terms'  => array(),
	);

	if ( ! is_array( $article ) ) {
		$result['code']   = $contract['article_invalid'];
		$result['error']  = $contract['article_invalid'];
		$result['errors'] = array( $contract['article_invalid'] );

		return $result;
	}

	$manual = velotok_core_content_article_manual_topic( $article );
	$text   = velotok_core_content_article_normalize_text( velotok_core_content_article_text( $article ) );
	$key    = '' !== $manual ? $manual : '';

	if ( '' === $key && null !== $topic && '' !== $topic ) {
		$key = velotok_core_content_article_topic_key( $topic );
	}

	if ( '' === $key ) {
		$best_score = 0;

		foreach ( $contract['required_topics'] as $candidate ) {
			$definition = $contract['topics'][ $candidate ];
			$topic_hits = velotok_core_content_article_matched_terms( $text, $definition['criteria']['topic_terms'] );
			$context    = velotok_core_content_article_matched_terms( $text, $definition['criteria']['context_terms'] );
			$score      = count( $topic_hits ) + count( $context );

			if ( $score > $best_score ) {
				$best_score = $score;
				$key        = $candidate;
				$result['matched_terms'] = array_merge( $topic_hits, $context );
			}
		}
	}

	$result['article'] = $article;
	$result['topic']   = $key;
	$result['classification'] = '' !== $manual ? 'manual' : 'automatic';

	if ( '' === $key ) {
		$result['code']   = $contract['unknown_topic'];
		$result['error']  = $contract['unknown_topic'];
		$result['errors'] = array( $contract['unknown_topic'] );

		return $result;
	}

	if ( null !== $topic && '' !== $topic ) {
		$expected = velotok_core_content_article_topic_key( $topic );

		if ( '' !== $expected && $expected !== $key ) {
			$definition = $contract['topics'][ $expected ];
			$result['topic']  = $expected;
			$result['code']   = $definition['mismatch_code'];
			$result['error']  = $definition['mismatch_code'];
			$result['errors'] = array( $definition['mismatch_code'] );

			return $result;
		}
	}

	$missing = array();

	foreach ( $contract['required_fields'] as $field ) {
		$value = isset( $article[ $field ] ) ? $article[ $field ] : null;

		if ( 'content' === $field && ! isset( $article['content'] ) && isset( $article['body'] ) ) {
			$value = $article['body'];
		}

		if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
			continue;
		}

		if ( 'topic' === $field ) {
			continue;
		}

		$missing[] = $field;
	}

	$result['missing_fields'] = $missing;

	if ( $missing ) {
		$result['code']   = $contract['field_required'];
		$result['error']  = $contract['field_required'];
		$result['errors'] = array( $contract['field_required'] );

		return $result;
	}

	$result['valid']     = true;
	$result['matched']   = true;
	$result['compliant'] = true;

	return $result;
}

/**
 * Check whether an article belongs to a content topic.
 *
 * @param mixed $article Article data.
 * @param mixed $topic   Topic identifier or label.
 * @return bool
 */
function velotok_core_content_article_matches_topic( $article, $topic ) {
	$result = velotok_core_validate_content_article( $article, $topic );

	return true === $result['valid'];
}

/**
 * Normalize a coverage input into a list of articles.
 *
 * @param mixed $articles Articles list, wrapper or a single article.
 * @return array<int,mixed>
 */
function velotok_core_content_article_list( $articles ) {
	if ( is_array( $articles ) ) {
		foreach ( array( 'articles', 'items', 'content' ) as $wrapper ) {
			if ( isset( $articles[ $wrapper ] ) && is_array( $articles[ $wrapper ] ) ) {
				$articles = $articles[ $wrapper ];
				break;
			}
		}
	}

	if ( ! is_array( $articles ) ) {
		return array( $articles );
	}

	$article_fields = array( 'title', 'body', 'content', 'text', 'summary', 'excerpt', 'description', 'tags', 'topic', 'topic_slug', 'content_topic', 'slug', 'id' );
	$string_keys    = array_filter( array_keys( $articles ), 'is_string' );

	if ( array() !== array_intersect( $string_keys, $article_fields ) ) {
		return array( $articles );
	}

	return array_values( $articles );
}

/**
 * Check thematic coverage of an article set.
 *
 * A topic is covered when at least one article is valid for that topic. Both
 * required areas must be covered. When no article set is supplied, the fixed
 * mocked articles from the contract are checked.
 *
 * @param mixed $articles Optional articles list, wrapper or a single article.
 * @return array<string,mixed>
 */
function velotok_core_check_content_articles_coverage( $articles = null ) {
	$contract = velotok_core_content_articles_contract();

	if ( null === $articles ) {
		$articles = $contract['articles'];
	}

	$items    = velotok_core_content_article_list( $articles );
	$coverage = array();
	$covered  = array();
	$missing  = array();

	foreach ( $contract['required_topics'] as $key ) {
		$definition = $contract['topics'][ $key ];
		$found      = array();

		foreach ( $items as $index => $article ) {
			$result = velotok_core_validate_content_article( $article, $key );

			if ( $result['valid'] ) {
				$found[] = $index;
			}
		}

		$is_covered = ! empty( $found );

		$coverage[ $key ] = array(
			'topic'     => $key,
			'label'     => $definition['label'],
			'required'  => true,
			'mocked'    => true,
			'covered'   => $is_covered,
			'articles'  => $found,
			'count'     => count( $found ),
		);

		if ( $is_covered ) {
			$covered[] = $key;
		} else {
			$missing[] = $key;
		}
	}

	$complete = array() === $missing;
	$errors   = $complete ? array() : array( $contract['coverage_incomplete'] );

	return array(
		'valid'           => $complete,
		'complete'        => $complete,
		'spec'            => $contract['spec'],
		'source'          => $contract['source'],
		'mocked'          => true,
		'required_topics' => $contract['required_topics'],
		'coverage'        => $coverage,
		'topics'          => $coverage,
		'covered'         => $covered,
		'covered_topics'  => $covered,
		'missing'         => $missing,
		'missing_topics'  => $missing,
		'covered_count'   => count( $covered ),
		'required_count'  => count( $contract['required_topics'] ),
		'errors'          => $errors,
		'error'           => $errors ? $errors[0] : null,
	);
}

/**
 * Alias for callers that prefer a validation-prefixed helper.
 *
 * @param mixed $articles Optional articles list, wrapper or a single article.
 * @return array<string,mixed>
 */
function velotok_core_validate_content_articles_coverage( $articles = null ) {
	return velotok_core_check_content_articles_coverage( $articles );
}

/**
 * Alias for callers that use a shorter coverage name.
 *
 * @param mixed $articles Optional articles list, wrapper or a single article.
 * @return array<string,mixed>
 */
function velotok_core_content_articles_coverage( $articles = null ) {
	return velotok_core_check_content_articles_coverage( $articles );
}

/**
 * Return the open questions that block real editorial integrations.
 *
 * @return array<string,string>
 */
function velotok_core_content_articles_open_questions() {
	$contract = velotok_core_content_articles_contract();

	return $contract['open_questions'];
}

if ( ! function_exists( 'contentArticlesContract' ) ) {
	/**
	 * Short global alias for the content articles contract.
	 *
	 * @return array<string,mixed>
	 */
	function contentArticlesContract() {
		return velotok_core_content_articles_contract();
	}
}

if ( ! function_exists( 'contentArticles' ) ) {
	/**
	 * Short global alias for the fixed mocked articles.
	 *
	 * @param mixed $topic Optional topic identifier or label.
	 * @return array<int,array<string,mixed>>
	 */
	function contentArticles( $topic = null ) {
		return velotok_core_get_content_articles( $topic );
	}
}

if ( ! function_exists( 'validateContentArticle' ) ) {
	/**
	 * Short global alias for article validation.
	 *
	 * @param mixed $article Article data.
	 * @param mixed $topic   Optional expected topic identifier or label.
	 * @return array<string,mixed>
	 */
	function validateContentArticle( $article, $topic = null ) {
		return velotok_core_validate_content_article( $article, $topic );
	}
}

if ( ! function_exists( 'checkContentArticlesCoverage' ) ) {
	/**
	 * Short global alias for content articles coverage checks.
	 *
	 * @param mixed $articles Optional articles list, wrapper or a single article.
	 * @return array<string,mixed>
	 */
	function checkContentArticlesCoverage( $articles = null ) {
		return velotok_core_check_content_articles_coverage( $articles );
	}
}
