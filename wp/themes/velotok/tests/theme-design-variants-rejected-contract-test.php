<?php
/**
 * Contract test: every non-selected theme design variant is rejected.
 *
 * Standalone:
 *   php wp/themes/velotok/tests/theme-design-variants-rejected-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/themes/velotok/tests/theme-design-variants-rejected-contract-test.php
 *
 * The test fails when the stub is removed, stops marking unselected variants as
 * rejected, rejects the selected variant, or allows more than one selected
 * variant. It intentionally exercises only the public contract facade.
 *
 * @package Velotok
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_override = getenv( 'VELOTOK_THEME_DESIGN_VARIANTS_STUB' );
$stub_path     = false !== $stub_override && '' !== $stub_override
	? $stub_override
	: dirname( __DIR__ ) . '/inc/theme-design-variants.php';

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки вариантов дизайна темы.' . PHP_EOL );
	exit( 1 );
}

require_once $stub_path;

$failures = array();

/**
 * Record a failed contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_theme_design_rejected_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_theme_select_design_variant' ) ) {
	velotok_theme_design_rejected_assert( false, 'Отсутствует операция выбора варианта дизайна.' );
} else {
	$contract = velotok_theme_design_variants_contract();
	$expected = $contract['required_variants'];

	velotok_theme_design_rejected_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_theme_design_rejected_assert( count( $expected ) >= 2, 'Для проверки отклонения нужно не менее двух вариантов.' );
	velotok_theme_design_rejected_assert( isset( $contract['rejected_status'] ) && 'rejected' === $contract['rejected_status'], 'Не зафиксирован статус rejected.' );
	velotok_theme_design_rejected_assert( isset( $contract['reject_unselected'] ) && true === $contract['reject_unselected'], 'Контракт не требует отклонять невыбранные варианты.' );
	velotok_theme_design_rejected_assert( isset( $contract['unselected_status'] ) && 'rejected' === $contract['unselected_status'], 'Статус невыбранного варианта должен быть rejected.' );
	velotok_theme_design_rejected_assert( function_exists( 'velotok_theme_design_variant_status' ), 'Отсутствует функция статуса варианта.' );
	velotok_theme_design_rejected_assert( function_exists( 'velotok_theme_design_rejected_variants' ), 'Отсутствует функция списка отклонённых вариантов.' );

	foreach ( $expected as $variant ) {
		$result = velotok_theme_select_design_variant( array( 'variant' => $variant ) );

		velotok_theme_design_rejected_assert( is_array( $result ), sprintf( 'Результат выбора «%s» должен быть массивом.', $variant ) );
		velotok_theme_design_rejected_assert( isset( $result['ok'] ) && true === $result['ok'], sprintf( 'Выбор «%s» должен завершаться успешно.', $variant ) );
		velotok_theme_design_rejected_assert( isset( $result['selected'] ) && $variant === $result['selected'], sprintf( 'Выбранным должен быть «%s».', $variant ) );

		$selected_ids = array();
		$rejected_ids = array();

		foreach ( $result['variants'] as $state ) {
			if ( true === $state['selected'] ) {
				$selected_ids[] = $state['id'];
				velotok_theme_design_rejected_assert( 'selected' === $state['status'], sprintf( 'У выбранного «%s» должен быть статус selected.', $state['id'] ) );
				velotok_theme_design_rejected_assert( false === $state['rejected'], sprintf( 'Выбранный «%s» не должен быть отклонён.', $state['id'] ) );
				continue;
			}

			$rejected_ids[] = $state['id'];
			velotok_theme_design_rejected_assert( true === $state['rejected'], sprintf( 'Невыбранный вариант «%s» должен быть помечен rejected.', $state['id'] ) );
			velotok_theme_design_rejected_assert( 'rejected' === $state['status'], sprintf( 'У невыбранного «%s» должен быть статус rejected.', $state['id'] ) );
		}

		$expected_rejected = array_values( array_diff( $expected, array( $variant ) ) );

		velotok_theme_design_rejected_assert( array( $variant ) === $selected_ids, sprintf( 'При выборе «%s» выбран должен быть ровно один вариант.', $variant ) );
		velotok_theme_design_rejected_assert( $expected_rejected === $rejected_ids, sprintf( 'При выборе «%s» отклонены должны быть все остальные варианты.', $variant ) );
		velotok_theme_design_rejected_assert( 'selected' === velotok_theme_design_variant_status( $variant, $variant ), 'Функция статуса должна возвращать selected для выбранного варианта.' );

		foreach ( $expected_rejected as $unselected ) {
			velotok_theme_design_rejected_assert( 'rejected' === velotok_theme_design_variant_status( $unselected, $variant ), sprintf( 'Функция статуса должна возвращать rejected для невыбранного «%s».', $unselected ) );
		}

		velotok_theme_design_rejected_assert( $expected_rejected === velotok_theme_design_rejected_variants( $variant ), 'Функция отклонённых вариантов должна возвращать все невыбранные варианты.' );
		velotok_theme_design_rejected_assert( $expected_rejected === $result['rejected'], 'Сводный список rejected должен совпадать со статусами вариантов.' );
		velotok_theme_design_rejected_assert( $expected_rejected === $result['rejected_variants'], 'Список rejected_variants должен совпадать со статусами вариантов.' );
		velotok_theme_design_rejected_assert( false === $result['selected_variant']['rejected'], 'Выбранный вариант в selected_variant не должен быть отклонён.' );
		velotok_theme_design_rejected_assert( 'selected' === $result['selected_variant']['status'], 'У selected_variant должен быть статус selected.' );
	}
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: невыбранные варианты дизайна темы помечены rejected.' . PHP_EOL;
