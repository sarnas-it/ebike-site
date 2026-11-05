<?php
/**
 * Content thematic coverage stub contract.
 *
 * This is the single access point for the thematic coverage rules used by the
 * site. The current iteration deliberately uses fixed in-memory criteria: it
 * does not read environment variables, files, the database or remote services.
 *
 * The contract covers three required content areas: an overview of models in
 * green energy, legal requirements and green energy news. Classification and
 * coverage checks are mocked contracts; editorial expertise and the real
 * publication pipeline are out of scope.
 *
 * Spec: clarify-a2fd2f66.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

/**
 * Return the content thematic coverage contract.
 *
 * The contract documents:
 * - the spec identifier, the explicit `mocked` marker and the parent spec;
 * - three required content areas and their deterministic matching criteria;
 * - the hybrid membership rule (manual topic hint or automatic signals);
 * - error codes for unknown topics, mismatches and promotional model reviews;
 * - the open questions that block real editorial integrations.
 *
 * @return array<string,mixed>
 */
function velotok_core_content_thematic_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$context_terms = array(
		'зелен',
		'зелён',
		'солнеч',
		'ветр',
		'энерг',
		'электро',
		'эко',
		'возобнов',
		'гидро',
		'био',
		'фотовольт',
		'аккумулятор',
	);

	$topics = array(
		'model_overview'      => array(
			'id'                 => 'model_overview',
			'slug'               => 'model_overview',
			'code'               => 'model_overview',
			'label'              => 'Обзор моделей в зелёной энергетике',
			'description'        => 'Обзор, сравнение и характеристики моделей оборудования для зелёной энергетики.',
			'mocked'             => true,
			'criteria'           => array(
				'topic_terms'     => array( 'обзор', 'сравнен', 'рейтинг', 'характеристик', 'модель', 'модели', 'линейк', 'каталог' ),
				'context_terms'   => $context_terms,
				'forbidden_terms' => array( 'купить', 'заказать', 'скидк', 'промокод', 'распродаж', 'акция', 'в корзину', 'оформить заказ' ),
				'match'           => 'topic term AND green energy context term; forbidden terms mark the material as promotion',
			),
			'mismatch_code'      => 'MODEL_OVERVIEW_MISMATCH',
			'promotion_code'     => 'MODEL_PROMOTION_DETECTED',
		),
		'legal_requirements'  => array(
			'id'                 => 'legal_requirements',
			'slug'               => 'legal_requirements',
			'code'               => 'legal_requirements',
			'label'              => 'Требования законодательства',
			'description'        => 'Требования законодательства и нормативного регулирования в области зелёной энергетики.',
			'mocked'             => true,
			'criteria'           => array(
				'topic_terms'     => array( 'закон', 'законодательств', 'норматив', 'регулир', 'требован', 'сертифик', 'лиценз', 'гост', 'стандарт' ),
				'context_terms'   => $context_terms,
				'forbidden_terms' => array(),
				'match'           => 'legal term AND green energy context term',
			),
			'mismatch_code'      => 'LEGAL_REQUIREMENTS_MISMATCH',
			'promotion_code'     => null,
		),
		'green_energy_news'   => array(
			'id'                 => 'green_energy_news',
			'slug'               => 'green_energy_news',
			'code'               => 'green_energy_news',
			'label'              => 'Новости о зелёной энергетике',
			'description'        => 'Новости, сообщения и анонсы событий в области зелёной энергетики.',
			'mocked'             => true,
			'criteria'           => array(
				'topic_terms'     => array( 'новост', 'сообщ', 'объяв', 'анонс', 'произош', 'запустил', 'открыл', 'подписал', 'пресс-релиз', 'инцидент' ),
				'context_terms'   => $context_terms,
				'forbidden_terms' => array(),
				'match'           => 'news term AND green energy context term',
			),
			'mismatch_code'      => 'GREEN_ENERGY_NEWS_MISMATCH',
			'promotion_code'     => null,
		),
	);

	$contract = array(
		'spec'                => 'clarify-a2fd2f66',
		'source'              => 'stub',
		'mocked'              => true,
		'parent_spec'         => 'ecommerce-store',
		'parent_relation'     => 'Тематический охват контента расширяет родительскую спецификацию ecommerce-store и не описывает публикацию или источники данных.',
		'required_topics'     => array_keys( $topics ),
		'required_areas'      => array_values( $topics ),
		'topics'              => $topics,
		'membership'          => 'hybrid',
		'manual_hint_field'   => 'topic',
		'automatic_source'    => 'keywords',
		'classification_source' => 'stub',
		'future_source'       => 'editorial_review',
		'unknown_topic'       => 'TOPIC_UNKNOWN',
		'coverage_incomplete' => 'CONTENT_COVERAGE_INCOMPLETE',
		'missing_topic'       => 'TOPIC_REQUIRED',
		'open_questions'      => array(
			'membership'          => 'Каким образом определяется принадлежность материала к тематике: вручную, автоматически или гибридно?',
			'model_quality'       => 'Какие критерии полноты и качества применяются к обзору моделей и как выявляется продвижение конкретного продукта?',
			'legal_accuracy'      => 'Какие требования предъявляются к актуальности и юридической достоверности ссылок на законодательство?',
			'news_sources'        => 'Какие требования предъявляются к источникам новостей и проверке достоверности событий?',
			'parent_relation'     => 'Каково отношение к родительской спецификации ecommerce-store и какие требования она накладывает на данную тематику?',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer a full explicit contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_content_thematic_coverage_contract() {
	return velotok_core_content_thematic_contract();
}

/**
 * Short alias for the content thematic contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_content_coverage_contract() {
	return velotok_core_content_thematic_contract();
}

/**
 * Return every required content topic keyed by identifier.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_content_topics() {
	$contract = velotok_core_content_thematic_contract();

	return $contract['topics'];
}

/**
 * Return the required content topic identifiers.
 *
 * @return string[]
 */
function velotok_core_content_topic_keys() {
	$contract = velotok_core_content_thematic_contract();

	return $contract['required_topics'];
}

/**
 * Normalize a topic identifier, label or alias.
 *
 * @param mixed $topic Topic identifier or label.
 * @return string Empty string for an unknown topic.
 */
function velotok_core_content_topic_key( $topic ) {
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

	$key = velotok_core_content_normalize_text( trim( $topic ) );
	$key = (string) preg_replace( '/[\s-]+/', '_', $key );

	$aliases = array(
		'model_overview'                 => 'model_overview',
		'models_overview'                => 'model_overview',
		'model_review'                   => 'model_overview',
		'models_review'                  => 'model_overview',
		'models'                         => 'model_overview',
		'обзор_моделей'                  => 'model_overview',
		'обзор_моделей_в_зеленой_энергетике' => 'model_overview',
		'legal_requirements'             => 'legal_requirements',
		'legislation'                    => 'legal_requirements',
		'legal'                          => 'legal_requirements',
		'требования_законодательства'    => 'legal_requirements',
		'законодательство'               => 'legal_requirements',
		'green_energy_news'              => 'green_energy_news',
		'green_news'                     => 'green_energy_news',
		'news'                           => 'green_energy_news',
		'новости'                        => 'green_energy_news',
		'новости_о_зеленой_энергетике'   => 'green_energy_news',
	);

	if ( isset( $aliases[ $key ] ) ) {
		return $aliases[ $key ];
	}

	$topics = velotok_core_content_topics();

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
function velotok_core_content_topic( $topic ) {
	$key = velotok_core_content_topic_key( $topic );

	if ( '' === $key ) {
		return null;
	}

	$topics = velotok_core_content_topics();

	return $topics[ $key ];
}

/**
 * Normalize text for deterministic keyword matching.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function velotok_core_content_normalize_text( $value ) {
	$text = is_scalar( $value ) ? (string) $value : '';

	if ( function_exists( 'mb_strtolower' ) ) {
		$text = mb_strtolower( $text, 'UTF-8' );
	} else {
		$text = strtolower( $text );
	}

	return str_replace( 'ё', 'е', $text );
}

/**
 * Extract searchable text from a material without touching external storage.
 *
 * @param mixed $material Material data.
 * @param int   $depth    Recursion guard.
 * @return string
 */
function velotok_core_content_text( $material, $depth = 0 ) {
	if ( $depth > 6 ) {
		return '';
	}

	if ( is_string( $material ) || is_numeric( $material ) ) {
		return (string) $material;
	}

	if ( is_object( $material ) && method_exists( $material, '__toString' ) ) {
		return (string) $material;
	}

	if ( ! is_array( $material ) ) {
		return '';
	}

	$parts = array();

	foreach ( $material as $value ) {
		if ( is_scalar( $value ) ) {
			$parts[] = (string) $value;
		} elseif ( is_array( $value ) || is_object( $value ) ) {
			$nested = velotok_core_content_text( $value, $depth + 1 );

			if ( '' !== $nested ) {
				$parts[] = $nested;
			}
		}
	}

	return implode( ' ', $parts );
}

/**
 * Find the first matching terms from a list in normalized text.
 *
 * @param string   $text Normalized text.
 * @param string[] $terms Candidate terms.
 * @return string[] Matched terms.
 */
function velotok_core_content_matched_terms( $text, $terms ) {
	$matches = array();

	foreach ( $terms as $term ) {
		$needle = velotok_core_content_normalize_text( $term );

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
 * Read an explicit topic hint from a material.
 *
 * @param mixed $material Material data.
 * @return string Normalized topic key or an empty string.
 */
function velotok_core_content_manual_topic( $material ) {
	if ( ! is_array( $material ) ) {
		return '';
	}

	foreach ( array( 'topic', 'topic_slug', 'content_topic' ) as $field ) {
		if ( isset( $material[ $field ] ) ) {
			$key = velotok_core_content_topic_key( $material[ $field ] );

			if ( '' !== $key ) {
				return $key;
			}
		}
	}

	return '';
}

/**
 * Validate a material against one content topic.
 *
 * The result is an interface-level mocked contract: a material matches a topic
 * when it has a topic signal and a green energy context signal, or when it
 * carries an explicit topic hint. Promotional model reviews are rejected by
 * the model overview criteria. No editorial expertise is performed.
 *
 * @param mixed $material Material data.
 * @param mixed $topic    Topic identifier or label.
 * @return array<string,mixed>
 */
function velotok_core_validate_content_topic( $material, $topic ) {
	$contract = velotok_core_content_thematic_contract();
	$key      = velotok_core_content_topic_key( $topic );
	$result   = array(
		'valid'              => false,
		'matched'            => false,
		'compliant'          => false,
		'topic'              => $key,
		'mocked'             => true,
		'spec'               => $contract['spec'],
		'classification'     => 'automatic',
		'code'               => null,
		'error'              => null,
		'errors'             => array(),
		'promotion'          => false,
		'promotion_detected' => false,
		'matched_terms'      => array(),
		'missing'            => array(),
	);

	if ( '' === $key ) {
		$result['code']   = $contract['unknown_topic'];
		$result['error']  = $contract['unknown_topic'];
		$result['errors'] = array( $contract['unknown_topic'] );

		return $result;
	}

	$definition = velotok_core_content_topics()[ $key ];
	$criteria   = $definition['criteria'];
	$text       = velotok_core_content_normalize_text( velotok_core_content_text( $material ) );
	$manual     = velotok_core_content_manual_topic( $material );

	$topic_matches   = velotok_core_content_matched_terms( $text, $criteria['topic_terms'] );
	$context_matches = velotok_core_content_matched_terms( $text, $criteria['context_terms'] );
	$forbidden       = velotok_core_content_matched_terms( $text, $criteria['forbidden_terms'] );

	$missing = array();

	if ( ! $topic_matches && $manual !== $key ) {
		$missing[] = 'topic_signal';
	}

	if ( ! $context_matches && $manual !== $key ) {
		$missing[] = 'context_signal';
	}

	$matched        = array() === $missing;
	$promotion      = ! empty( $forbidden );
	$classification = $manual === $key ? 'manual' : 'automatic';
	$valid          = $matched && ! $promotion;

	if ( ! $matched ) {
		$code = $definition['mismatch_code'];
	} elseif ( $promotion && null !== $definition['promotion_code'] ) {
		$code = $definition['promotion_code'];
	} else {
		$code = null;
	}

	$result['valid']              = $valid;
	$result['matched']            = $matched;
	$result['compliant']          = $valid;
	$result['classification']     = $classification;
	$result['code']               = $code;
	$result['error']              = $code;
	$result['errors']             = null === $code ? array() : array( $code );
	$result['promotion']          = $promotion;
	$result['promotion_detected'] = $promotion;
	$result['matched_terms']      = array_merge( $topic_matches, $context_matches );
	$result['missing']            = $missing;
	$result['label']              = $definition['label'];

	return $result;
}

/**
 * Check whether a material belongs to a content topic.
 *
 * @param mixed $material Material data.
 * @param mixed $topic    Topic identifier or label.
 * @return bool
 */
function velotok_core_content_matches_topic( $material, $topic ) {
	$result = velotok_core_validate_content_topic( $material, $topic );

	return true === $result['valid'];
}

/**
 * Classify a material against every required content topic.
 *
 * @param mixed $material Material data.
 * @return array<string,mixed>
 */
function velotok_core_classify_content( $material ) {
	$contract = velotok_core_content_thematic_contract();
	$results  = array();
	$matches  = array();

	foreach ( $contract['required_topics'] as $key ) {
		$result            = velotok_core_validate_content_topic( $material, $key );
		$results[ $key ]   = $result;

		if ( $result['valid'] ) {
			$matches[] = $key;
		}
	}

	return array(
		'spec'     => $contract['spec'],
		'mocked'   => true,
		'valid'    => ! empty( $matches ),
		'topics'   => $contract['required_topics'],
		'matches'  => array_values( $matches ),
		'primary'  => $matches ? $matches[0] : '',
		'results'  => $results,
	);
}

/**
 * Normalize a coverage input into a list of materials.
 *
 * @param mixed $materials Materials list, wrapper or a single material.
 * @return array<int,mixed>
 */
function velotok_core_content_material_list( $materials ) {
	if ( is_array( $materials ) ) {
		foreach ( array( 'materials', 'items', 'content' ) as $wrapper ) {
			if ( isset( $materials[ $wrapper ] ) && is_array( $materials[ $wrapper ] ) ) {
				$materials = $materials[ $wrapper ];
				break;
			}
		}
	}

	if ( ! is_array( $materials ) ) {
		return array( $materials );
	}

	$material_fields = array( 'title', 'body', 'content', 'text', 'summary', 'description', 'excerpt', 'tags', 'topic', 'topic_slug', 'content_topic' );
	$string_keys     = array_filter( array_keys( $materials ), 'is_string' );

	if ( array() !== array_intersect( $string_keys, $material_fields ) ) {
		return array( $materials );
	}

	return array_values( $materials );
}

/**
 * Check thematic coverage of a material set.
 *
 * A topic is covered when at least one material is a valid non-promotional
 * match for that topic. All three required areas must be covered.
 *
 * @param mixed $materials Materials list, wrapper or a single material.
 * @return array<string,mixed>
 */
function velotok_core_check_content_coverage( $materials ) {
	$contract = velotok_core_content_thematic_contract();
	$items    = velotok_core_content_material_list( $materials );
	$coverage = array();
	$covered  = array();
	$missing  = array();

	foreach ( $contract['required_topics'] as $key ) {
		$definition = $contract['topics'][ $key ];
		$found      = array();

		foreach ( $items as $index => $material ) {
			$result = velotok_core_validate_content_topic( $material, $key );

			if ( $result['valid'] ) {
				$found[] = $index;
			}
		}

		$is_covered = ! empty( $found );

		$coverage[ $key ] = array(
			'topic'      => $key,
			'label'      => $definition['label'],
			'required'   => true,
			'mocked'     => true,
			'covered'    => $is_covered,
			'materials'  => $found,
			'count'      => count( $found ),
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
 * @param mixed $materials Materials list, wrapper or a single material.
 * @return array<string,mixed>
 */
function velotok_core_validate_content_coverage( $materials ) {
	return velotok_core_check_content_coverage( $materials );
}

/**
 * Alias for callers that use a shorter coverage name.
 *
 * @param mixed $materials Materials list, wrapper or a single material.
 * @return array<string,mixed>
 */
function velotok_core_content_coverage( $materials ) {
	return velotok_core_check_content_coverage( $materials );
}

/**
 * Return the open questions that block real editorial integrations.
 *
 * @return array<string,string>
 */
function velotok_core_content_open_questions() {
	$contract = velotok_core_content_thematic_contract();

	return $contract['open_questions'];
}

if ( ! function_exists( 'contentThematicContract' ) ) {
	/**
	 * Short global alias for the thematic contract.
	 *
	 * @return array<string,mixed>
	 */
	function contentThematicContract() {
		return velotok_core_content_thematic_contract();
	}
}

if ( ! function_exists( 'classifyContent' ) ) {
	/**
	 * Short global alias for content classification.
	 *
	 * @param mixed $material Material data.
	 * @return array<string,mixed>
	 */
	function classifyContent( $material ) {
		return velotok_core_classify_content( $material );
	}
}

if ( ! function_exists( 'checkContentCoverage' ) ) {
	/**
	 * Short global alias for thematic coverage checks.
	 *
	 * @param mixed $materials Materials list, wrapper or a single material.
	 * @return array<string,mixed>
	 */
	function checkContentCoverage( $materials ) {
		return velotok_core_check_content_coverage( $materials );
	}
}
