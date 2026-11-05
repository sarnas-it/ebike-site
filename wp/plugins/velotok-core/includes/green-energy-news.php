<?php
/**
 * Green energy news stub contract.
 *
 * This is the single access point for the «Новости о зелёной энергетике»
 * content rule. The current iteration deliberately uses fixed in-memory
 * criteria: it does not read environment variables, files, the database or
 * remote services.
 *
 * A material belongs to the category when it describes an event or a change in
 * green energy and carries the event date. Only the presence of the event
 * description and the date is checked. The authenticity of the event, the
 * credibility of the source and the accuracy of the date are explicitly out of
 * scope.
 *
 * Spec: clarify-a2fd2f66 (requirement TESTEB-122).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

if ( ! defined( 'VELOTOK_CORE_GREEN_ENERGY_NEWS_MOCKED' ) ) {
	define( 'VELOTOK_CORE_GREEN_ENERGY_NEWS_MOCKED', true );
}

/**
 * Return the green energy news stub contract.
 *
 * The contract documents:
 * - the spec identifier, the explicit `mocked` marker and the stub source;
 * - the checked fields: event description and event date;
 * - deterministic criteria for the event description and date parsing;
 * - error codes for a missing event, a missing date and an invalid date;
 * - the limitations and open questions that block a real news integration.
 *
 * @return array<string,mixed>
 */
function velotok_core_green_energy_news_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$contract = array(
		'spec'               => 'clarify-a2fd2f66',
		'parent_spec'        => 'ecommerce-store',
		'task'               => 'TESTEB-122',
		'requirement'        => 'Новости о зелёной энергетике',
		'topic'              => 'news',
		'slug'               => 'green_energy_news',
		'aliases'            => array( 'news', 'green_energy_news', 'green_news', 'новости', 'новости_о_зеленой_энергетике' ),
		'label'              => 'Новости о зелёной энергетике',
		'source'             => 'stub',
		'mocked'             => true,
		'mocked_marker'      => 'MOCKED',
		'operation'          => 'check_green_energy_news_material',
		'classification'     => 'deterministic',
		'checks'             => array( 'event_description', 'event_date' ),
		'not_checked'        => array( 'event_authenticity', 'source_credibility', 'date_accuracy' ),
		'criteria'           => array(
			'event_terms'   => array( 'новост', 'сообщ', 'объяв', 'анонс', 'произош', 'запущ', 'открыт', 'подписал', 'пресс-релиз', 'инцидент', 'событ', 'изменен', 'введ', 'постро', 'строительств', 'решение', 'договор', 'запуск' ),
			'context_terms' => array( 'зелен', 'зелён', 'солнеч', 'ветр', 'энерг', 'электро', 'эко', 'возобнов', 'гидро', 'био', 'фотовольт', 'аккумулятор' ),
			'match'         => 'event term AND green energy context term AND event date',
		),
		'fields'             => array(
			'event' => array( 'event', 'event_description', 'news', 'description', 'summary', 'excerpt', 'content', 'body', 'title' ),
			'date'  => array( 'event_date', 'eventDate', 'date', 'published_at', 'published_date', 'occurred_at', 'happened_at', 'news_date' ),
		),
		'error_codes'        => array(
			'event_required' => 'GREEN_ENERGY_NEWS_EVENT_REQUIRED',
			'date_required'  => 'GREEN_ENERGY_NEWS_DATE_REQUIRED',
			'date_invalid'   => 'GREEN_ENERGY_NEWS_DATE_INVALID',
		),
		'open_questions'     => array(
			'news_sources'   => 'Какие требования предъявляются к источникам новостей и проверке достоверности событий?',
			'date_format'    => 'Каким должен быть формат даты события и часовой пояс публикации?',
			'authenticity'   => 'Каким образом проверяется достоверность события и отсутствие дублей?',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer a full explicit contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_green_energy_news_contract_full() {
	return velotok_core_green_energy_news_contract();
}

/**
 * Normalize text for deterministic keyword matching.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function velotok_core_green_energy_news_normalize( $value ) {
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
function velotok_core_green_energy_news_text( $material, $depth = 0 ) {
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
			$nested = velotok_core_green_energy_news_text( $value, $depth + 1 );

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
function velotok_core_green_energy_news_matched_terms( $text, $terms ) {
	$matches = array();

	foreach ( $terms as $term ) {
		$needle = velotok_core_green_energy_news_normalize( $term );

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
function velotok_core_green_energy_news_field( $material, $fields ) {
	if ( ! is_array( $material ) ) {
		return '';
	}

	foreach ( $fields as $field ) {
		if ( ! isset( $material[ $field ] ) ) {
			continue;
		}

		$value = velotok_core_green_energy_news_text( $material[ $field ] );

		if ( '' !== trim( $value ) ) {
			return trim( $value );
		}
	}

	return '';
}

/**
 * Check whether a string is an explicit placeholder instead of a date.
 *
 * @param string $value Candidate value.
 * @return bool
 */
function velotok_core_green_energy_news_is_placeholder( $value ) {
	$normalized = velotok_core_green_energy_news_normalize( trim( $value ) );

	return in_array( $normalized, array( '', 'n/a', 'na', 'none', 'null', 'нет', 'без даты', 'не указана', 'не указано', 'unknown', '0', '-' ), true );
}

/**
 * Parse an event date from a string.
 *
 * Supported formats: ISO `YYYY-MM-DD`, `DD.MM.YYYY`, `DD/MM/YYYY` and
 * `D <month name> YYYY`. The stub only validates the calendar date; it does not
 * check the accuracy or timezone of the event.
 *
 * @param mixed $value Candidate value.
 * @return string Normalized `YYYY-MM-DD` date or an empty string.
 */
function velotok_core_green_energy_news_parse_date( $value ) {
	$raw = trim( velotok_core_green_energy_news_text( $value ) );

	if ( velotok_core_green_energy_news_is_placeholder( $raw ) ) {
		return '';
	}

	if ( preg_match( '/(\d{4})-(\d{2})-(\d{2})/u', $raw, $match ) ) {
		$year  = (int) $match[1];
		$month = (int) $match[2];
		$day   = (int) $match[3];

		if ( $year >= 1900 && $year <= 2200 && checkdate( $month, $day, $year ) ) {
			return sprintf( '%04d-%02d-%02d', $year, $month, $day );
		}
	}

	if ( preg_match( '/(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})/u', $raw, $match ) ) {
		$day   = (int) $match[1];
		$month = (int) $match[2];
		$year  = (int) $match[3];

		if ( $year >= 1900 && $year <= 2200 && checkdate( $month, $day, $year ) ) {
			return sprintf( '%04d-%02d-%02d', $year, $month, $day );
		}
	}

	$normalized = velotok_core_green_energy_news_normalize( $raw );

	if ( preg_match( '/(\d{1,2})\s+([а-я]+)\s+(\d{4})/u', $normalized, $match ) ) {
		$day    = (int) $match[1];
		$word   = $match[2];
		$year   = (int) $match[3];
		$months = array(
			'январ'    => 1,
			'феврал'   => 2,
			'март'     => 3,
			'апрел'    => 4,
			'ма'       => 5,
			'июн'      => 6,
			'июл'      => 7,
			'август'   => 8,
			'сентябр'  => 9,
			'октябр'   => 10,
			'ноябр'    => 11,
			'декабр'   => 12,
		);

		foreach ( $months as $stem => $month ) {
			if ( 0 === strpos( $word, $stem ) && $year >= 1900 && $year <= 2200 && checkdate( $month, $day, $year ) ) {
				return sprintf( '%04d-%02d-%02d', $year, $month, $day );
			}
		}
	}

	return '';
}

/**
 * Check whether a material carries a non-empty date field.
 *
 * @param mixed $material Material data.
 * @return bool
 */
function velotok_core_green_energy_news_has_date_field( $material ) {
	$contract = velotok_core_green_energy_news_contract();

	if ( ! is_array( $material ) ) {
		return false;
	}

	foreach ( $contract['fields']['date'] as $field ) {
		if ( ! isset( $material[ $field ] ) ) {
			continue;
		}

		$value = trim( velotok_core_green_energy_news_text( $material[ $field ] ) );

		if ( ! velotok_core_green_energy_news_is_placeholder( $value ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Extract a valid event date from a material.
 *
 * Accepts an explicit date field or a date found in the material text.
 *
 * @param mixed $material Material data.
 * @return string Normalized `YYYY-MM-DD` date or an empty string.
 */
function velotok_core_green_energy_news_date( $material ) {
	$contract = velotok_core_green_energy_news_contract();
	$values   = array();

	if ( is_array( $material ) ) {
		foreach ( $contract['fields']['date'] as $field ) {
			if ( ! isset( $material[ $field ] ) ) {
				continue;
			}

			$value = trim( velotok_core_green_energy_news_text( $material[ $field ] ) );

			if ( ! velotok_core_green_energy_news_is_placeholder( $value ) ) {
				$values[] = $value;
			}
		}
	}

	$full_text = trim( velotok_core_green_energy_news_text( $material ) );

	if ( '' !== $full_text ) {
		$values[] = $full_text;
	}

	foreach ( $values as $value ) {
		$date = velotok_core_green_energy_news_parse_date( $value );

		if ( '' !== $date ) {
			return $date;
		}
	}

	return '';
}

/**
 * Return the event description from a material.
 *
 * @param mixed $material Material data.
 * @return string
 */
function velotok_core_green_energy_news_event( $material ) {
	$contract = velotok_core_green_energy_news_contract();
	$value    = velotok_core_green_energy_news_field( $material, $contract['fields']['event'] );

	if ( '' !== $value ) {
		return $value;
	}

	return trim( velotok_core_green_energy_news_text( $material ) );
}

/**
 * Check a material against the green energy news category.
 *
 * The result is an interface-level mocked contract. A material is valid when
 * it describes an event in green energy and carries a valid event date. The
 * stub never validates the actuality, authenticity or source of the event.
 *
 * @param mixed $material Material data.
 * @return array<string,mixed>
 */
function velotok_core_green_energy_news_check( $material ) {
	$contract = velotok_core_green_energy_news_contract();
	$criteria = $contract['criteria'];

	$full_text         = velotok_core_green_energy_news_normalize( velotok_core_green_energy_news_text( $material ) );
	$event_description = velotok_core_green_energy_news_event( $material );
	$event_text        = velotok_core_green_energy_news_normalize( $event_description );

	$event_matches   = velotok_core_green_energy_news_matched_terms( $event_text, $criteria['event_terms'] );
	$context_matches = velotok_core_green_energy_news_matched_terms( $full_text, $criteria['context_terms'] );
	$date            = velotok_core_green_energy_news_date( $material );

	$has_event = ! empty( $event_matches ) && ! empty( $context_matches );
	$has_date  = '' !== $date;
	$missing   = array();

	if ( ! $has_event ) {
		$missing[] = 'event_description';
	}

	if ( ! $has_date ) {
		$missing[] = 'event_date';
	}

	$valid = array() === $missing;

	if ( in_array( 'event_description', $missing, true ) ) {
		$code = $contract['error_codes']['event_required'];
	} elseif ( in_array( 'event_date', $missing, true ) ) {
		$code = velotok_core_green_energy_news_has_date_field( $material )
			? $contract['error_codes']['date_invalid']
			: $contract['error_codes']['date_required'];
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
		'has_event'         => $has_event,
		'has_date'          => $has_date,
		'event'             => $event_description,
		'event_date'        => $date,
		'matched_terms'     => array_merge( $event_matches, $context_matches ),
		'missing'           => $missing,
		'errors'            => array() === $missing ? array() : array( $code ),
		'error'             => $code,
		'code'              => $code,
		'checks'            => $contract['checks'],
		'not_checked'       => $contract['not_checked'],
	);
}

/**
 * Boolean helper for the green energy news category.
 *
 * @param mixed $material Material data.
 * @return bool
 */
function velotok_core_green_energy_news_matches( $material ) {
	$result = velotok_core_green_energy_news_check( $material );

	return true === $result['valid'];
}

/**
 * Alias for callers that prefer a validation-prefixed helper.
 *
 * @param mixed $material Material data.
 * @return array<string,mixed>
 */
function velotok_core_validate_green_energy_news( $material ) {
	return velotok_core_green_energy_news_check( $material );
}

/**
 * Alias with a shorter content-news name.
 *
 * @return array<string,mixed>
 */
function velotok_core_content_news_contract() {
	return velotok_core_green_energy_news_contract();
}

/**
 * Alias with a shorter news name.
 *
 * @return array<string,mixed>
 */
function velotok_core_news_contract() {
	return velotok_core_green_energy_news_contract();
}

/**
 * Alias with a shorter content-news check name.
 *
 * @param mixed $material Material data.
 * @return array<string,mixed>
 */
function velotok_core_content_news_check( $material ) {
	return velotok_core_green_energy_news_check( $material );
}

/**
 * Alias with a shorter news check name.
 *
 * @param mixed $material Material data.
 * @return array<string,mixed>
 */
function velotok_core_news_check( $material ) {
	return velotok_core_green_energy_news_check( $material );
}

/**
 * Boolean helper with a shorter news name.
 *
 * @param mixed $material Material data.
 * @return bool
 */
function velotok_core_news_matches( $material ) {
	return velotok_core_green_energy_news_matches( $material );
}

if ( ! function_exists( 'greenEnergyNewsContract' ) ) {
	/**
	 * Short global alias for the green energy news contract.
	 *
	 * @return array<string,mixed>
	 */
	function greenEnergyNewsContract() {
		return velotok_core_green_energy_news_contract();
	}
}

if ( ! function_exists( 'checkGreenEnergyNews' ) ) {
	/**
	 * Short global alias for the green energy news check.
	 *
	 * @param mixed $material Material data.
	 * @return array<string,mixed>
	 */
	function checkGreenEnergyNews( $material ) {
		return velotok_core_green_energy_news_check( $material );
	}
}
