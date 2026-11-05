<?php
/**
 * Contract test for theme design variants and their selected/rejected statuses.
 *
 * Standalone:
 *   php wp/tests/theme-variants-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/themes/../tests/theme-variants-test.php
 *
 * The test fails when fewer than two complete theme variants exist, when the
 * manifest does not mark exactly one variant as selected, or when selecting a
 * variant stops marking the remaining variants as rejected.
 *
 * @package Velotok
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/cli/theme-variants.php';

$failures  = array();
$themes_dir = dirname( __DIR__ ) . '/themes';

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_theme_variants_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

try {
	$manifest = velotok_theme_variants_load();
} catch ( Throwable $exception ) {
	fwrite( STDERR, 'FAIL: ' . $exception->getMessage() . PHP_EOL );
	exit( 1 );
}

velotok_theme_variants_assert( isset( $manifest['spec'] ) && 'clarify-c1924ac5' === $manifest['spec'], 'Манифест ссылается на неверную спеку.' );
velotok_theme_variants_assert( isset( $manifest['variants'] ) && is_array( $manifest['variants'] ), 'В манифесте нет списка вариантов.' );
velotok_theme_variants_assert( count( $manifest['variants'] ) >= 2, 'Должно быть предложено не менее двух вариантов дизайна.' );

$validation_errors = velotok_theme_variants_validate( $manifest );

foreach ( $validation_errors as $validation_error ) {
	velotok_theme_variants_assert( false, $validation_error );
}

$selected_count = 0;
$rejected_count = 0;
$theme_names    = array();

$required_files = array(
	'style.css',
	'index.php',
	'functions.php',
	'front-page.php',
	'header.php',
	'footer.php',
	'single.php',
	'page.php',
	'404.php',
	'inc/enqueue.php',
	'inc/setup.php',
	'inc/woocommerce.php',
	'template-parts/hero.php',
	'template-parts/models.php',
	'assets/css/main.css',
	'assets/js/main.js',
	'assets/img/hero-city-1-web.jpg',
);

foreach ( $manifest['variants'] as $variant ) {
	$slug = isset( $variant['slug'] ) ? $variant['slug'] : '';
	$dir  = $themes_dir . '/' . $slug;

	velotok_theme_variants_assert( '' !== $slug, 'У варианта отсутствует slug.' );
	velotok_theme_variants_assert( is_dir( $dir ), sprintf( 'Не найдена директория варианта «%s».', $slug ) );

	foreach ( $required_files as $required_file ) {
		velotok_theme_variants_assert(
			is_readable( $dir . '/' . $required_file ),
			sprintf( 'В варианте «%s» отсутствует обязательный файл %s.', $slug, $required_file )
		);
	}

	$style_path = $dir . '/style.css';
	$style      = is_readable( $style_path ) ? (string) file_get_contents( $style_path ) : '';

	velotok_theme_variants_assert( '' !== $style, sprintf( 'Не удалось прочитать style.css варианта «%s».', $slug ) );

	if ( preg_match( '/^[ \t\/*#@]*Theme Name:\s*(.+)$/mi', $style, $matches ) ) {
		$theme_name = trim( $matches[1] );
		velotok_theme_variants_assert( ! isset( $theme_names[ $theme_name ] ), sprintf( 'Название темы «%s» повторяется.', $theme_name ) );
		$theme_names[ $theme_name ] = $slug;
	} else {
		velotok_theme_variants_assert( false, sprintf( 'В style.css варианта «%s» нет Theme Name.', $slug ) );
	}

	$status = isset( $variant['status'] ) ? $variant['status'] : '';

	if ( 'selected' === $status ) {
		$selected_count++;
		velotok_theme_variants_assert( $slug === $manifest['selected'], sprintf( 'Вариант «%s» выбран, но не совпадает с полем selected.', $slug ) );
	} elseif ( 'rejected' === $status ) {
		$rejected_count++;
	} else {
		velotok_theme_variants_assert( false, sprintf( 'У варианта «%s» недопустимый статус.', $slug ) );
	}
}

velotok_theme_variants_assert( 1 === $selected_count, 'Выбранным должен быть ровно один вариант.' );
velotok_theme_variants_assert( count( $manifest['variants'] ) - 1 === $rejected_count, 'Все невыбранные варианты должны быть помечены rejected.' );

$temporary_manifest = tempnam( sys_get_temp_dir(), 'velotok-variants-' );

if ( false === $temporary_manifest ) {
	velotok_theme_variants_assert( false, 'Не удалось создать временный манифест для проверки выбора.' );
} else {
	file_put_contents( $temporary_manifest, (string) file_get_contents( velotok_theme_variants_manifest_path() ) );

	try {
		$updated = velotok_theme_variants_select( 'velotok-bold', $temporary_manifest );

		velotok_theme_variants_assert( 'velotok-bold' === $updated['selected'], 'Выбор не обновил поле selected.' );
		velotok_theme_variants_assert( 'selected' === velotok_theme_variants_status( $updated, 'velotok-bold' ), 'Выбранный вариант не получил статус selected.' );

		foreach ( $updated['variants'] as $variant ) {
			$expected = ( 'velotok-bold' === $variant['slug'] ) ? 'selected' : 'rejected';
			velotok_theme_variants_assert( $expected === $variant['status'], sprintf( 'После выбора у варианта «%s» неверный статус.', $variant['slug'] ) );
		}

		$updated_errors = velotok_theme_variants_validate( velotok_theme_variants_load( $temporary_manifest ) );
		foreach ( $updated_errors as $updated_error ) {
			velotok_theme_variants_assert( false, $updated_error );
		}

		try {
			velotok_theme_variants_select( 'there-is-no-such-variant', $temporary_manifest );
			velotok_theme_variants_assert( false, 'Неизвестный вариант не должен выбираться.' );
		} catch ( InvalidArgumentException $exception ) {
			velotok_theme_variants_assert( true, '' );
		} catch ( Throwable $exception ) {
			velotok_theme_variants_assert( false, 'Неизвестный вариант вызвал неожиданную ошибку: ' . $exception->getMessage() );
		}
	} catch ( Throwable $exception ) {
		velotok_theme_variants_assert( false, 'Не удалось выбрать вариант: ' . $exception->getMessage() );
	}

	@unlink( $temporary_manifest );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: предложено несколько вариантов дизайна, выбран один, остальные отклонены.' . PHP_EOL;
