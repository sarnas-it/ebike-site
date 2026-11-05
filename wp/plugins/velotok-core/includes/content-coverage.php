<?php
/**
 * Content thematic coverage stub.
 *
 * This is the contract for checking whether a material belongs to one of the
 * three required green-energy topics. The current iteration is intentionally a
 * fixed in-memory stub: it does not read files, environment variables, the
 * database or remote services.
 *
 * Spec: clarify-a2fd2f66.
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the thematic coverage contract.
 *
 * The contract fixes:
 * - the spec identifier and the explicit `mocked` marker;
 * - the three required topics and their matching rules;
 * - the hybrid classification decision;
 * - the quality and verification criteria for each topic.
 *
 * @return array<string,mixed>
 */
function velotok_core_content_coverage_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$contract = array(
		'spec'              => 'clarify-a2fd2f66',
		'parent_spec'       => 'ecommerce-store',
		'source'            => 'stub',
		'mocked'            => true,
		'classification'    => 'hybrid',
		'taxonomy'          => 'velotok_content_topic',
		'missing_category'  => 'not_compliant',
		'checks'            => array(
			'positive' => 'Материал, удовлетворяющий всем группам условий категории, признаётся соответствующим этой категории.',
			'negative' => 'Материал, не удовлетворяющий хотя бы одной группе условий категории, не признаётся соответствующим этой категории.',
		),
		'decisions'         => array(
			'classification'          => 'hybrid: автоматическая проверка по условиям плюс ручное подтверждение спорных материалов.',
			'model_review_quality'   => 'полнота обзора и отсутствие продвижения конкретного продукта.',
			'legislation_actuality'  => 'актуальность ссылок и юридическая достоверность формулировок.',
			'news_sources'           => 'проверка источника и подтверждение события.',
			'parent_spec'            => 'родительская спецификация ecommerce-store задаёт границы магазина; тематический охват не меняет её контракты.',
		),
		'categories'        => array(
			'model_review'      => array(
				'slug'        => 'model-review',
				'label'       => 'Обзор моделей в зелёной энергетике',
				'description' => 'Сравнение и разбор моделей оборудования и транспорта для зелёной энергетики.',
				'all_of'      => array(
					array( 'обзор', 'сравнен', 'тест', 'разбор', 'рейтинг', 'анализ' ),
					array( 'модел', 'характеристик', 'электромобил', 'велосипед', 'накопител', 'солнечн', 'ветров' ),
				),
				'criteria'    => array( 'completeness', 'no_product_promotion' ),
			),
			'legislation'       => array(
				'slug'        => 'legislation',
				'label'       => 'Требования законодательства',
				'description' => 'Нормы, требования и ограничения, применимые к зелёной энергетике.',
				'all_of'      => array(
					array( 'закон', 'законодательств', 'норматив', 'регулирован', 'требован', 'сертификац', 'гост' ),
				),
				'criteria'    => array( 'actuality', 'legal_reliability' ),
			),
			'green_energy_news' => array(
				'slug'        => 'green-energy-news',
				'label'       => 'Новости о зелёной энергетике',
				'description' => 'Новости и события, связанные с зелёной энергетикой.',
				'all_of'      => array(
					array( 'новост', 'событи', 'анонс', 'сообщ' ),
					array( 'зелён', 'зелен', 'возобновля', 'солнечн', 'ветров', 'энергетик', 'электромобил' ),
				),
				'criteria'    => array( 'source_verification', 'event_verification' ),
			),
		),
	);

	return $contract;
}

/**
 * Return the required thematic categories keyed by contract key.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_content_coverage_categories() {
	$contract = velotok_core_content_coverage_contract();

	return $contract['categories'];
}

/**
 * Check that the contract covers all three required topics.
 *
 * @return bool
 */
function velotok_core_content_coverage_has_full_coverage() {
	$required = array( 'model_review', 'legislation', 'green_energy_news' );
	$categories = velotok_core_content_coverage_categories();

	foreach ( $required as $category ) {
		if ( ! isset( $categories[ $category ] ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Normalize a material for matching.
 *
 * @param mixed $material Material data.
 * @return array<string,string>
 */
function velotok_core_content_coverage_normalize_material( $material ) {
	$material = is_array( $material ) ? $material : array();

	$normalized = array(
		'title'   => '',
		'excerpt' => '',
		'content' => '',
	);

	foreach ( array_keys( $normalized ) as $field ) {
		if ( isset( $material[ $field ] ) && is_scalar( $material[ $field ] ) ) {
			$normalized[ $field ] = (string) $material[ $field ];
		}
	}

	return $normalized;
}

/**
 * Lowercase a string for case-insensitive matching.
 *
 * @param string $text Source text.
 * @return string
 */
function velotok_core_content_coverage_lower( $text ) {
	if ( function_exists( 'mb_strtolower' ) ) {
		return mb_strtolower( (string) $text, 'UTF-8' );
	}

	return strtolower( (string) $text );
}

/**
 * Check whether a text contains a term.
 *
 * @param string $text Haystack.
 * @param string $term Needle.
 * @return bool
 */
function velotok_core_content_coverage_contains( $text, $term ) {
	$text = velotok_core_content_coverage_lower( $text );
	$term = velotok_core_content_coverage_lower( $term );

	if ( '' === $term ) {
		return false;
	}

	if ( function_exists( 'mb_strpos' ) ) {
		return false !== mb_strpos( $text, $term, 0, 'UTF-8' );
	}

	return false !== strpos( $text, $term );
}

/**
 * Collect terms from one condition group that are present in the text.
 *
 * @param string   $text  Material text.
 * @param string[] $terms Condition terms.
 * @return string[]
 */
function velotok_core_content_coverage_group_matches( $text, $terms ) {
	$matches = array();

	foreach ( $terms as $term ) {
		if ( velotok_core_content_coverage_contains( $text, $term ) ) {
			$matches[] = $term;
		}
	}

	return $matches;
}

/**
 * Evaluate a material against every thematic category.
 *
 * @param mixed $material Material data with title, excerpt and content.
 * @return array<string,mixed>
 */
function velotok_core_content_coverage_evaluate( $material ) {
	$normalized  = velotok_core_content_coverage_normalize_material( $material );
	$text        = implode( ' ', $normalized );
	$categories  = velotok_core_content_coverage_categories();
	$results     = array();
	$matched     = array();
	$scores      = array();
	$primary     = null;
	$best_score  = -1;

	foreach ( $categories as $key => $category ) {
		$compliant     = true;
		$matched_terms = array();

		foreach ( $category['all_of'] as $group ) {
			$group_matches = velotok_core_content_coverage_group_matches( $text, $group );

			if ( empty( $group_matches ) ) {
				$compliant = false;
				break;
			}

			$matched_terms = array_merge( $matched_terms, $group_matches );
		}

		$results[ $key ] = array(
			'compliant' => $compliant,
			'slug'      => $category['slug'],
			'label'     => $category['label'],
			'matches'   => $compliant ? array_values( array_unique( $matched_terms ) ) : array(),
		);

		if ( $compliant ) {
			$matched[]         = $key;
			$scores[ $key ]    = count( array_unique( $matched_terms ) );
		}
	}

	foreach ( $scores as $key => $score ) {
		if ( $score > $best_score ) {
			$best_score = $score;
			$primary    = $key;
		}
	}

	return array(
		'spec'              => 'clarify-a2fd2f66',
		'mocked'            => true,
		'material'          => $normalized,
		'categories'        => $results,
		'matched_categories'=> $matched,
		'primary_category'  => $primary,
	);
}

/**
 * Check whether a material belongs to the requested thematic category.
 *
 * @param mixed  $material Material data.
 * @param string $category Category key or slug.
 * @return bool
 */
function velotok_core_content_coverage_is_compliant( $material, $category ) {
	if ( ! is_string( $category ) ) {
		return false;
	}

	$categories = velotok_core_content_coverage_categories();

	if ( ! isset( $categories[ $category ] ) ) {
		foreach ( $categories as $key => $definition ) {
			if ( $definition['slug'] === $category ) {
				$category = $key;
				break;
			}
		}
	}

	if ( ! isset( $categories[ $category ] ) ) {
		return false;
	}

	$evaluation = velotok_core_content_coverage_evaluate( $material );

	return isset( $evaluation['categories'][ $category ] ) && true === $evaluation['categories'][ $category ]['compliant'];
}
