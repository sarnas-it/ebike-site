<?php
/**
 * Theme design variants stub contract.
 *
 * The project leader selects exactly one design direction for the storefront.
 * The current iteration deliberately uses fixed in-memory variants: it does not
 * read environment variables, files, the database or remote services.
 *
 * A real design pipeline (separate WordPress themes, review labels or a parent
 * spec manifest) is out of scope until the open questions are answered.
 * Consumers must use velotok_theme_select_design_variant() so the mocked
 * implementation can later be replaced without touching caller code.
 *
 * Spec: clarify-c1924ac5.
 *
 * @package Velotok
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

/**
 * Return the theme design variants contract.
 *
 * The contract documents the available design directions, the single-choice
 * selection operation, the selected/rejected statuses and the open questions
 * that block a real integration.
 *
 * @return array<string,mixed>
 */
function velotok_theme_design_variants_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$variants = array(
		'urban'  => array(
			'id'          => 'urban',
			'slug'        => 'urban',
			'code'        => 'urban',
			'label'       => 'Городской минимализм',
			'description' => 'Спокойная светлая витрина с акцентом на городские модели и ежедневные поездки.',
			'palette'     => array( 'primary' => '#0f766e', 'accent' => '#f59e0b' ),
			'mocked'      => true,
			'status'      => 'pending',
			'selected'    => false,
			'rejected'    => false,
		),
		'trail'  => array(
			'id'          => 'trail',
			'slug'        => 'trail',
			'code'        => 'trail',
			'label'       => 'Спортивный азарт',
			'description' => 'Контрастная динамичная витрина для горных и спортивных электровелосипедов.',
			'palette'     => array( 'primary' => '#1d4ed8', 'accent' => '#f97316' ),
			'mocked'      => true,
			'status'      => 'pending',
			'selected'    => false,
			'rejected'    => false,
		),
		'family' => array(
			'id'          => 'family',
			'slug'        => 'family',
			'code'        => 'family',
			'label'       => 'Семейный комфорт',
			'description' => 'Тёплая дружелюбная витрина для грузовых и семейных моделей.',
			'palette'     => array( 'primary' => '#9a3412', 'accent' => '#16a34a' ),
			'mocked'      => true,
			'status'      => 'pending',
			'selected'    => false,
			'rejected'    => false,
		),
	);

	$contract = array(
		'spec'               => 'clarify-c1924ac5',
		'source'             => 'stub',
		'mocked'             => true,
		'operation'          => 'select_design_variant',
		'provider'           => 'mock',
		'artifact'           => 'in_repository_mockup',
		'single_choice'      => true,
		'reject_unselected'  => true,
		'unselected_status'  => 'rejected',
		'required_variants'  => array_keys( $variants ),
		'variants'           => $variants,
		'default_variant'    => 'urban',
		'selection_field'    => 'variant',
		'selection_aliases'  => array( 'variant', 'variant_id', 'design', 'design_id', 'theme', 'theme_variant', 'slug', 'id' ),
		'request'            => array(
			'required' => array( 'variant' ),
			'optional' => array(),
			'fields'   => array(
				'variant' => array(
					'type'    => 'string',
					'aliases' => array( 'variant_id', 'design', 'design_id', 'theme', 'theme_variant', 'slug', 'id' ),
				),
			),
		),
		'response'           => array(
			'required_keys' => array(
				'ok',
				'valid',
				'spec',
				'operation',
				'mocked',
				'status',
				'selected',
				'selected_variant',
				'variants',
				'rejected',
				'error',
				'errors',
			),
		),
		'statuses'           => array(
			'pending',
			'selected',
			'rejected',
		),
		'pending_status'     => 'pending',
		'selected_status'    => 'selected',
		'rejected_status'    => 'rejected',
		'status_labels'      => array(
			'pending'  => 'ожидает выбора',
			'selected' => 'выбран',
			'rejected' => 'отклонён',
		),
		'network'            => false,
		'stateful'           => false,
		'persistence'        => false,
		'real_integration'   => false,
		'future_source'      => 'design_review',
		'error_codes'        => array(
			'missing_parameter' => 'DESIGN_VARIANT_REQUIRED',
			'unknown_variant'   => 'DESIGN_VARIANT_UNKNOWN',
			'invalid_input'     => 'DESIGN_VARIANT_INVALID',
		),
		'open_questions'     => array(
			'status_artifact' => 'Каким артефактом фиксируется статус «выбран / отклонён» для каждого варианта после апрува PR: манифест в репозитории, метки PR или запись в родительской спеке?',
			'rejected_storage' => 'Должны ли отклонённые варианты сохраняться в wp-content/themes/ или удаляться после выбора?',
			'variant_artifact' => 'Требуется ли для каждого варианта отдельная тема WordPress в wp-content/themes/, или достаточно HTML-макетов в одном PR?',
			'parent_spec'      => 'Какой родительской спекой фиксируется выбор направления фирменного стиля и какие её требования наследуются?',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer a shorter contract name.
 *
 * @return array<string,mixed>
 */
function velotok_theme_design_contract() {
	return velotok_theme_design_variants_contract();
}

/**
 * Alias kept for callers that use the core-style naming convention.
 *
 * @return array<string,mixed>
 */
function velotok_core_theme_design_variants_contract() {
	return velotok_theme_design_variants_contract();
}

/**
 * Return the open questions that block a real design integration.
 *
 * @return array<string,string>
 */
function velotok_theme_design_variants_open_questions() {
	$contract = velotok_theme_design_variants_contract();

	return $contract['open_questions'];
}

/**
 * Return every design variant keyed by identifier.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_theme_design_variants() {
	$contract = velotok_theme_design_variants_contract();

	return $contract['variants'];
}

/**
 * Return the required design variant identifiers.
 *
 * @return string[]
 */
function velotok_theme_design_variant_keys() {
	$contract = velotok_theme_design_variants_contract();

	return $contract['required_variants'];
}

/**
 * Return the supported selection statuses.
 *
 * @return string[]
 */
function velotok_theme_design_variant_statuses() {
	$contract = velotok_theme_design_variants_contract();

	return $contract['statuses'];
}

/**
 * Normalize a variant identifier, label or alias.
 *
 * @param mixed $variant Variant identifier or label.
 * @return string Empty string for an unknown variant.
 */
function velotok_theme_design_variant_key( $variant ) {
	if ( is_array( $variant ) ) {
		foreach ( array( 'variant', 'variant_id', 'design', 'design_id', 'theme', 'theme_variant', 'slug', 'id', 'code' ) as $key ) {
			if ( isset( $variant[ $key ] ) && is_scalar( $variant[ $key ] ) ) {
				$variant = $variant[ $key ];
				break;
			}
		}
	}

	if ( ! is_string( $variant ) ) {
		return '';
	}

	$key = trim( $variant );

	if ( function_exists( 'mb_strtolower' ) ) {
		$key = mb_strtolower( $key, 'UTF-8' );
	} else {
		$key = strtolower( $key );
	}

	$key = str_replace( 'ё', 'е', $key );
	$key = (string) preg_replace( '/[\s-]+/', '_', $key );

	$aliases = array(
		'urban'       => 'urban',
		'city'        => 'urban',
		'city_minimal'=> 'urban',
		'городской_минимализм' => 'urban',
		'городской'   => 'urban',
		'минимализм'  => 'urban',
		'trail'       => 'trail',
		'sport'       => 'trail',
		'sporty'      => 'trail',
		'спортивный_азарт' => 'trail',
		'спортивный'  => 'trail',
		'family'      => 'family',
		'cargo'       => 'family',
		'семейный_комфорт' => 'family',
		'семейный'    => 'family',
	);

	if ( isset( $aliases[ $key ] ) ) {
		return $aliases[ $key ];
	}

	$variants = velotok_theme_design_variants();

	if ( isset( $variants[ $key ] ) ) {
		return $key;
	}

	return '';
}

/**
 * Resolve a design variant definition.
 *
 * @param mixed $variant Variant identifier or label.
 * @return array<string,mixed>|null Null for an unknown variant.
 */
function velotok_theme_design_variant( $variant ) {
	$key = velotok_theme_design_variant_key( $variant );

	if ( '' === $key ) {
		return null;
	}

	$variants = velotok_theme_design_variants();

	return $variants[ $key ];
}

/**
 * Extract a raw variant selection from the operation input.
 *
 * @param mixed $input Raw input.
 * @return mixed
 */
function velotok_theme_design_variant_input( $input ) {
	if ( is_object( $input ) ) {
		$input = (array) $input;
	}

	if ( is_string( $input ) || is_int( $input ) || is_float( $input ) ) {
		return $input;
	}

	if ( ! is_array( $input ) ) {
		return null;
	}

	$contract = velotok_theme_design_variants_contract();

	foreach ( $contract['selection_aliases'] as $alias ) {
		if ( array_key_exists( $alias, $input ) ) {
			return $input[ $alias ];
		}
	}

	return null;
}

/**
 * Return the selection status for a variant.
 *
 * Exactly one variant can be selected. Every other variant is rejected; the
 * pending status is reserved for failed requests where no choice was made.
 *
 * @param string      $variant  Variant key.
 * @param string|null $selected Selected variant key or null.
 * @return string
 */
function velotok_theme_design_variant_status( $variant, $selected = null ) {
	$contract = velotok_theme_design_variants_contract();

	return ( $selected === $variant )
		? $contract['selected_status']
		: $contract['rejected_status'];
}

/**
 * Return the identifiers of every variant rejected by a selection.
 *
 * @param string|null $selected Selected variant key or null.
 * @return string[]
 */
function velotok_theme_design_rejected_variants( $selected = null ) {
	$contract = velotok_theme_design_variants_contract();
	$rejected = array();

	foreach ( array_keys( $contract['variants'] ) as $key ) {
		if ( $contract['rejected_status'] === velotok_theme_design_variant_status( $key, $selected ) ) {
			$rejected[] = $key;
		}
	}

	return $rejected;
}

/**
 * Build a response with every variant marked by its selection status.
 *
 * @param string|null $selected Selected variant key or null.
 * @return array<int,array<string,mixed>>
 */
function velotok_theme_design_variant_states( $selected = null ) {
	$contract = velotok_theme_design_variants_contract();
	$states   = array();

	foreach ( $contract['variants'] as $key => $definition ) {
		$status      = velotok_theme_design_variant_status( $key, $selected );
		$is_selected = ( $contract['selected_status'] === $status );
		$is_rejected = ( $contract['rejected_status'] === $status );

		$states[] = array_merge(
			$definition,
			array(
				'status'   => $status,
				'selected' => $is_selected,
				'rejected' => $is_rejected,
			)
		);
	}

	return $states;
}

/**
 * Build an error response that matches the contract.
 *
 * @param string    $code  Error code.
 * @param string    $field Field name.
 * @param mixed     $input Raw input.
 * @return array<string,mixed>
 */
function velotok_theme_design_error_response( $code, $field, $input ) {
	$contract = velotok_theme_design_variants_contract();
	$message  = $contract['error_codes']['missing_parameter'] === $code
		? 'Design variant is required.'
		: ( $contract['error_codes']['unknown_variant'] === $code
			? 'Design variant is unknown.'
			: 'Design variant input is invalid.' );

	$states = array();

	foreach ( $contract['variants'] as $key => $definition ) {
		$states[] = array_merge(
			$definition,
			array(
				'status'   => $contract['pending_status'],
				'selected' => false,
				'rejected' => false,
			)
		);
	}

	return array(
		'ok'               => false,
		'valid'            => false,
		'spec'             => $contract['spec'],
		'operation'        => $contract['operation'],
		'provider'         => 'mock',
		'mocked'           => true,
		'status'           => 'failed',
		'selected'         => null,
		'selected_variant' => null,
		'variants'         => $states,
		'rejected'         => array(),
		'rejected_variants'=> array(),
		'network'          => false,
		'persisted'        => false,
		'request'          => is_array( $input ) ? $input : array(),
		'error'            => array(
			'code'    => $code,
			'message' => $message,
		),
		'errors'           => array(
			array(
				'code'    => $code,
				'field'   => $field,
				'message' => $message,
			),
		),
	);
}

/**
 * Select exactly one theme design variant.
 *
 * The unselected variants are marked as rejected. The same input always
 * produces the same result. No network request is performed, no state is
 * persisted and no environment variable is read.
 *
 * @param mixed               $input   Variant input.
 * @param array<string,mixed> $options Optional options.
 * @return array<string,mixed>
 */
function velotok_theme_select_design_variant( $input = array(), $options = array() ) {
	$contract = velotok_theme_design_variants_contract();
	$raw      = velotok_theme_design_variant_input( $input );

	if ( null === $raw || '' === $raw ) {
		return velotok_theme_design_error_response( $contract['error_codes']['missing_parameter'], 'variant', $input );
	}

	if ( is_array( $raw ) || is_object( $raw ) || is_bool( $raw ) ) {
		return velotok_theme_design_error_response( $contract['error_codes']['invalid_input'], 'variant', $input );
	}

	$selected = velotok_theme_design_variant_key( $raw );

	if ( '' === $selected ) {
		return velotok_theme_design_error_response( $contract['error_codes']['unknown_variant'], 'variant', $input );
	}

	$states     = velotok_theme_design_variant_states( $selected );
	$rejected   = velotok_theme_design_rejected_variants( $selected );
	$definition = $contract['variants'][ $selected ];

	return array(
		'ok'                => true,
		'valid'             => true,
		'spec'              => $contract['spec'],
		'operation'         => $contract['operation'],
		'provider'          => 'mock',
		'mocked'            => true,
		'status'            => $contract['selected_status'],
		'selected'          => $selected,
		'selected_variant'  => array_merge(
			$definition,
			array(
				'status'   => $contract['selected_status'],
				'selected' => true,
				'rejected' => false,
			)
		),
		'variants'          => $states,
		'rejected'          => $rejected,
		'rejected_variants' => $rejected,
		'network'           => false,
		'persisted'         => false,
		'request'           => array( 'variant' => $selected ),
		'options'           => is_array( $options ) ? $options : array(),
		'error'             => null,
		'errors'            => array(),
	);
}

/**
 * Alias for callers that prefer the design-prefixed name.
 *
 * @param mixed               $input   Variant input.
 * @param array<string,mixed> $options Optional options.
 * @return array<string,mixed>
 */
function velotok_theme_design_select_variant( $input = array(), $options = array() ) {
	return velotok_theme_select_design_variant( $input, $options );
}

/**
 * Alias for callers that use the short theme prefix.
 *
 * @param mixed               $input   Variant input.
 * @param array<string,mixed> $options Optional options.
 * @return array<string,mixed>
 */
function velotok_select_theme_design_variant( $input = array(), $options = array() ) {
	return velotok_theme_select_design_variant( $input, $options );
}

/**
 * Alias kept for callers that use the core-style naming convention.
 *
 * @param mixed               $input   Variant input.
 * @param array<string,mixed> $options Optional options.
 * @return array<string,mixed>
 */
function velotok_core_select_theme_design_variant( $input = array(), $options = array() ) {
	return velotok_theme_select_design_variant( $input, $options );
}

/**
 * Alias for callers that use a short selection name.
 *
 * @param mixed               $input   Variant input.
 * @param array<string,mixed> $options Optional options.
 * @return array<string,mixed>
 */
function velotok_select_design_variant( $input = array(), $options = array() ) {
	return velotok_theme_select_design_variant( $input, $options );
}

/**
 * Alias for callers that use a variants-prefixed selection name.
 *
 * @param mixed               $input   Variant input.
 * @param array<string,mixed> $options Optional options.
 * @return array<string,mixed>
 */
function velotok_theme_design_variants_select( $input = array(), $options = array() ) {
	return velotok_theme_select_design_variant( $input, $options );
}
