<?php
/**
 * Legal requirements stub contract.
 *
 * This is the single access point for the «Требования законодательства»
 * content rule. The current iteration deliberately uses fixed in-memory
 * criteria: it does not read environment variables, files, the database or
 * remote services.
 *
 * A material belongs to the category when it contains a description of a
 * normative requirement applicable to green energy and a reference to the
 * source of that requirement. Only the presence of the description and the
 * source reference are checked. The actuality and legal correctness of the
 * requirement are explicitly out of scope.
 *
 * Spec: clarify-a2fd2f66 (requirement TESTEB-121).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

if ( ! defined( 'VELOTOK_CORE_LEGAL_REQUIREMENTS_MOCKED' ) ) {
	define( 'VELOTOK_CORE_LEGAL_REQUIREMENTS_MOCKED', true );
}

/**
 * Return the legal requirements stub contract.
 *
 * The contract documents:
 * - the spec identifier, the explicit `mocked` marker and the stub source;
 * - the checked fields: requirement description and source reference;
 * - the criteria used by the deterministic stub;
 * - error codes for a missing description and a missing source reference;
 * - the limitations and open questions that block a real legal integration.
 *
 * @return array<string,mixed>
 */
function velotok_core_legal_requirements_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$contract = array(
		'spec'               => 'clarify-a2fd2f66',
		'parent_spec'        => 'ecommerce-store',
		'task'               => 'TESTEB-121',
		'requirement'        => 'Требования законодательства',
		'topic'              => 'legal_requirements',
		'slug'               => 'legal_requirements',
		'label'              => 'Требования законодательства',
		'source'             => 'stub',
		'mocked'             => true,
		'mocked_marker'      => 'MOCKED',
		'operation'          => 'check_legal_requirements_material',
		'classification'     => 'deterministic',
		'checks'             => array( 'requirement_description', 'source_link' ),
		'not_checked'        => array( 'legal_actuality', 'legal_correctness', 'requirement_completeness' ),
		'criteria'           => array(
			'requirement_terms' => array( 'закон', 'законодательств', 'норматив', 'регулир', 'требован', 'сертифик', 'лиценз', 'гост', 'стандарт', 'регламент', 'постановлен', 'приказ', 'директив' ),
			'context_terms'     => array( 'зелен', 'зелён', 'солнеч', 'ветр', 'энерг', 'электро', 'эко', 'возобнов', 'гидро', 'био', 'фотовольт', 'аккумулятор' ),
			'match'             => 'requirement term AND green energy context term AND source reference',
		),
		'fields'             => array(
			'requirement' => array( 'requirement', 'requirement_description', 'normative_requirement', 'regulation', 'description', 'summary', 'excerpt', 'content', 'body', 'title' ),
			'source'      => array( 'source', 'source_url', 'source_link', 'legal_source', 'reference', 'references', 'url' ),
		),
		'error_codes'        => array(
			'requirement_required' => 'LEGAL_REQUIREMENT_DESCRIPTION_REQUIRED',
			'source_required'      => 'LEGAL_REQUIREMENT_SOURCE_REQUIRED',
		),
		'open_questions'     => array(
			'legal_accuracy' => 'Какие требования предъявляются к актуальности и юридической достоверности ссылок на законодательство?',
			'source_format'  => 'Каким должен быть формат ссылки на источник требования: URL, реквизиты акта или оба варианта?',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer a full explicit contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_legal_requirements_contract_full() {
	return velotok_core_legal_requirements_contract();
}

/**
 * Normalize text for deterministic keyword matching.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function velotok_core_legal_requirements_normalize( $value ) {
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
function velotok_core_legal_requirements_text( $material, $depth = 0 ) {
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
			$nested = velotok_core_legal_requirements_text( $value, $depth + 1 );

			if ( '' !== $nested ) {
				$parts[] = $nested;
			}
		}
	}

	return implode( ' ', $parts );
}

/**
 * Find matching terms from a list in normalized text.
 *
 * @param string   $text Normalized text.
 * @param string[] $terms Candidate terms.
 * @return string[] Matched terms.
 */
function velotok_core_legal_requirements_matched_terms( $text, $terms ) {
	$matches = array();

	foreach ( $terms as $term ) {
		$needle = velotok_core_legal_requirements_normalize( $term );

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
 * Return the first non-empty value of a material field.
 *
 * @param mixed    $material Material data.
 * @param string[] $fields   Field names in priority order.
 * @return string Empty string when no field is present.
 */
function velotok_core_legal_requirements_field( $material, $fields ) {
	if ( ! is_array( $material ) ) {
		return '';
	}

	foreach ( $fields as $field ) {
		if ( ! isset( $material[ $field ] ) ) {
			continue;
		}

		$value = velotok_core_legal_requirements_text( $material[ $field ] );

		if ( '' !== trim( $value ) ) {
			return trim( $value );
		}
	}

	return '';
}

/**
 * Return the requirement description from a material.
 *
 * @param mixed $material Material data.
 * @return string
 */
function velotok_core_legal_requirements_description( $material ) {
	$contract = velotok_core_legal_requirements_contract();
	$value    = velotok_core_legal_requirements_field( $material, $contract['fields']['requirement'] );

	if ( '' !== $value ) {
		return $value;
	}

	return trim( velotok_core_legal_requirements_text( $material ) );
}

/**
 * Check whether a string is an explicit placeholder instead of a source.
 *
 * @param string $value Candidate value.
 * @return bool
 */
function velotok_core_legal_requirements_is_placeholder( $value ) {
	$normalized = velotok_core_legal_requirements_normalize( trim( $value ) );

	return in_array( $normalized, array( '', 'n/a', 'na', 'none', 'null', 'нет', 'без источника', 'unknown', 'не указан', 'не указано' ), true );
}

/**
 * Extract a source reference from a material.
 *
 * The stub only checks that a source reference is present. It accepts an
 * explicit source field, a URL found anywhere in the material or a legal act
 * citation with requisites. It does not verify that the source exists, is
 * current or is legally correct.
 *
 * @param mixed $material Material data.
 * @return string Empty string when no source reference is found.
 */
function velotok_core_legal_requirements_source( $material ) {
	$contract = velotok_core_legal_requirements_contract();
	$fields   = $contract['fields']['source'];
	$values   = array();

	if ( is_array( $material ) ) {
		foreach ( $fields as $field ) {
			if ( ! isset( $material[ $field ] ) ) {
				continue;
			}

			$value = trim( velotok_core_legal_requirements_text( $material[ $field ] ) );

			if ( ! velotok_core_legal_requirements_is_placeholder( $value ) ) {
				$values[] = $value;
			}
		}
	}

	$full_text = trim( velotok_core_legal_requirements_text( $material ) );

	if ( '' !== $full_text ) {
		$values[] = $full_text;
	}

	foreach ( $values as $value ) {
		if ( preg_match( '#https?://[^\s<>"\']+#i', $value, $url_match ) ) {
			return $url_match[0];
		}
	}

	foreach ( $values as $value ) {
		if ( preg_match( '/(?:№|N|No)\s*\d+/iu', $value, $number_match ) ) {
			return $value;
		}

		if ( preg_match( '/(?:ФЗ|ГОСТ|СНиП|СанПиН|ТР\s*ТС|директив\w*|регламент\w*|постановлен\w*|приказ\w*)[^,.;]*\d/iu', $value ) ) {
			return $value;
		}
	}

	$explicit = velotok_core_legal_requirements_field( $material, $fields );

	if ( '' !== $explicit && ! velotok_core_legal_requirements_is_placeholder( $explicit ) ) {
		return $explicit;
	}

	return '';
}

/**
 * Check a material against the legal requirements category.
 *
 * The result is an interface-level mocked contract. A material is valid when
 * its description contains a normative/legal term together with a green energy
 * context term and the material carries a source reference. The stub never
 * validates the actuality, completeness or legal correctness of the source.
 *
 * @param mixed $material Material data.
 * @return array<string,mixed>
 */
function velotok_core_legal_requirements_check( $material ) {
	$contract = velotok_core_legal_requirements_contract();
	$criteria = $contract['criteria'];

	$full_text        = velotok_core_legal_requirements_normalize( velotok_core_legal_requirements_text( $material ) );
	$description      = velotok_core_legal_requirements_description( $material );
	$description_text = velotok_core_legal_requirements_normalize( $description );

	$requirement_matches = velotok_core_legal_requirements_matched_terms( $description_text, $criteria['requirement_terms'] );
	$context_matches     = velotok_core_legal_requirements_matched_terms( $full_text, $criteria['context_terms'] );
	$source              = velotok_core_legal_requirements_source( $material );

	$has_requirement = ! empty( $requirement_matches ) && ! empty( $context_matches );
	$has_source      = '' !== $source;
	$missing         = array();

	if ( ! $has_requirement ) {
		$missing[] = 'requirement_description';
	}

	if ( ! $has_source ) {
		$missing[] = 'source_link';
	}

	$valid = array() === $missing;

	if ( in_array( 'requirement_description', $missing, true ) ) {
		$code = $contract['error_codes']['requirement_required'];
	} elseif ( in_array( 'source_link', $missing, true ) ) {
		$code = $contract['error_codes']['source_required'];
	} else {
		$code = null;
	}

	return array(
		'valid'             => $valid,
		'compliant'         => $valid,
		'mocked'            => true,
		'mocked_marker'     => $contract['mocked_marker'],
		'spec'              => $contract['spec'],
		'task'              => $contract['task'],
		'topic'             => $contract['topic'],
		'label'             => $contract['label'],
		'source'            => $contract['source'],
		'operation'         => $contract['operation'],
		'has_requirement'   => $has_requirement,
		'has_source'        => $has_source,
		'requirement'       => $description,
		'source_reference'  => $source,
		'matched_terms'     => array_merge( $requirement_matches, $context_matches ),
		'missing'           => $missing,
		'errors'            => array() === $missing ? array() : array( $code ),
		'error'             => $code,
		'code'              => $code,
		'checks'            => $contract['checks'],
		'not_checked'       => $contract['not_checked'],
	);
}

/**
 * Boolean helper for the legal requirements category.
 *
 * @param mixed $material Material data.
 * @return bool
 */
function velotok_core_legal_requirements_matches( $material ) {
	$result = velotok_core_legal_requirements_check( $material );

	return true === $result['valid'];
}

/**
 * Alias for callers that prefer a validation-prefixed helper.
 *
 * @param mixed $material Material data.
 * @return array<string,mixed>
 */
function velotok_core_validate_legal_requirements( $material ) {
	return velotok_core_legal_requirements_check( $material );
}

if ( ! function_exists( 'legalRequirementsContract' ) ) {
	/**
	 * Short global alias for the legal requirements contract.
	 *
	 * @return array<string,mixed>
	 */
	function legalRequirementsContract() {
		return velotok_core_legal_requirements_contract();
	}
}

if ( ! function_exists( 'checkLegalRequirements' ) ) {
	/**
	 * Short global alias for the legal requirements check.
	 *
	 * @param mixed $material Material data.
	 * @return array<string,mixed>
	 */
	function checkLegalRequirements( $material ) {
		return velotok_core_legal_requirements_check( $material );
	}
}
