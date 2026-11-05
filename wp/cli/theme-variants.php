<?php
/**
 * Выбор варианта дизайна темы магазина.
 *
 * Список вариантов и их статусы хранятся в wp/themes/design-variants.json.
 * При выборе одного варианта все остальные автоматически помечаются
 * отклонёнными, а выбранный вариант становится единственным selected.
 *
 * Использование из командной строки:
 *   php wp/cli/theme-variants.php list
 *   php wp/cli/theme-variants.php select velotok-bold
 *
 * @package Velotok
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

/**
 * Путь к манифесту вариантов дизайна.
 *
 * @return string
 */
function velotok_theme_variants_manifest_path() {
	if ( getenv( 'VELOTOK_DESIGN_VARIANTS' ) ) {
		return (string) getenv( 'VELOTOK_DESIGN_VARIANTS' );
	}

	return dirname( __DIR__ ) . '/themes/design-variants.json';
}

/**
 * Загружает манифест вариантов дизайна.
 *
 * @param string|null $path Путь к манифесту.
 * @return array
 *
 * @throws RuntimeException Если манифест отсутствует или содержит ошибку JSON.
 */
function velotok_theme_variants_load( $path = null ) {
	$path = $path ? $path : velotok_theme_variants_manifest_path();

	if ( ! is_readable( $path ) ) {
		throw new RuntimeException( sprintf( 'Манифест вариантов не найден: %s', $path ) );
	}

	$json = file_get_contents( $path );

	if ( false === $json ) {
		throw new RuntimeException( sprintf( 'Не удалось прочитать манифест вариантов: %s', $path ) );
	}

	$manifest = json_decode( $json, true );

	if ( ! is_array( $manifest ) ) {
		throw new RuntimeException( sprintf( 'Некорректный JSON в манифесте вариантов: %s', $path ) );
	}

	return $manifest;
}

/**
 * Сохраняет манифест вариантов дизайна.
 *
 * @param array       $manifest Манифест.
 * @param string|null $path     Путь к манифесту.
 * @return void
 *
 * @throws RuntimeException Если записать манифест не удалось.
 */
function velotok_theme_variants_save( $manifest, $path = null ) {
	$path = $path ? $path : velotok_theme_variants_manifest_path();
	$json = json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	if ( false === $json ) {
		throw new RuntimeException( 'Не удалось сериализовать манифест вариантов.' );
	}

	$json .= PHP_EOL;

	if ( false === file_put_contents( $path, $json, LOCK_EX ) ) {
		throw new RuntimeException( sprintf( 'Не удалось записать манифест вариантов: %s', $path ) );
	}
}

/**
 * Возвращает статус варианта из манифеста.
 *
 * @param array  $manifest Манифест.
 * @param string $slug     Слаг темы.
 * @return string selected|rejected|unknown
 */
function velotok_theme_variants_status( $manifest, $slug ) {
	if ( empty( $manifest['variants'] ) || ! is_array( $manifest['variants'] ) ) {
		return 'unknown';
	}

	foreach ( $manifest['variants'] as $variant ) {
		if ( isset( $variant['slug'], $variant['status'] ) && $slug === $variant['slug'] ) {
			return (string) $variant['status'];
		}
	}

	return 'unknown';
}

/**
 * Выбирает один вариант дизайна и помечает остальные отклонёнными.
 *
 * @param string      $slug Слаг выбранной темы.
 * @param string|null $path Путь к манифесту.
 * @return array Обновлённый манифест.
 *
 * @throws InvalidArgumentException Если варианта нет в манифесте.
 * @throws RuntimeException         Если манифест нельзя прочитать или записать.
 */
function velotok_theme_variants_select( $slug, $path = null ) {
	$path     = $path ? $path : velotok_theme_variants_manifest_path();
	$manifest = velotok_theme_variants_load( $path );
	$slugs    = array();

	if ( ! empty( $manifest['variants'] ) && is_array( $manifest['variants'] ) ) {
		foreach ( $manifest['variants'] as $variant ) {
			if ( isset( $variant['slug'] ) ) {
				$slugs[] = (string) $variant['slug'];
			}
		}
	}

	if ( ! in_array( $slug, $slugs, true ) ) {
		throw new InvalidArgumentException( sprintf( 'Неизвестный вариант дизайна: %s', $slug ) );
	}

	foreach ( $manifest['variants'] as $index => $variant ) {
		$manifest['variants'][ $index ]['status'] = ( $slug === $variant['slug'] ) ? 'selected' : 'rejected';
	}

	$manifest['selected'] = $slug;

	velotok_theme_variants_save( $manifest, $path );

	return $manifest;
}

/**
 * Проверяет манифест и наличие всех тем-вариантов.
 *
 * @param array $manifest Манифест.
 * @return string[] Список ошибок; пустой массив означает успех.
 */
function velotok_theme_variants_validate( $manifest ) {
	$errors = array();

	if ( ! is_array( $manifest ) ) {
		return array( 'Манифест должен быть массивом.' );
	}

	if ( ! isset( $manifest['spec'] ) || 'clarify-c1924ac5' !== $manifest['spec'] ) {
		$errors[] = 'Манифест должен ссылаться на спеку clarify-c1924ac5.';
	}

	if ( empty( $manifest['variants'] ) || ! is_array( $manifest['variants'] ) || count( $manifest['variants'] ) < 2 ) {
		$errors[] = 'Для выбора нужно не менее двух вариантов дизайна.';
		return $errors;
	}

	$selected = null;
	$seen     = array();

	foreach ( $manifest['variants'] as $variant ) {
		if ( ! is_array( $variant ) ) {
			$errors[] = 'Каждый вариант должен быть объектом.';
			continue;
		}

		$slug = isset( $variant['slug'] ) ? (string) $variant['slug'] : '';

		if ( '' === $slug || ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
			$errors[] = 'У варианта должен быть корректный slug.';
			continue;
		}

		if ( isset( $seen[ $slug ] ) ) {
			$errors[] = sprintf( 'Вариант «%s» указан несколько раз.', $slug );
		}
		$seen[ $slug ] = true;

		if ( empty( $variant['name'] ) ) {
			$errors[] = sprintf( 'У варианта «%s» нет названия.', $slug );
		}

		$status = isset( $variant['status'] ) ? (string) $variant['status'] : '';

		if ( ! in_array( $status, array( 'selected', 'rejected' ), true ) ) {
			$errors[] = sprintf( 'У варианта «%s» недопустимый статус: %s.', $slug, $status );
		}

		if ( 'selected' === $status ) {
			if ( null !== $selected ) {
				$errors[] = 'Выбранным может быть только один вариант дизайна.';
			}
			$selected = $slug;
		}

		$theme_file = dirname( __DIR__ ) . '/themes/' . $slug . '/style.css';

		if ( ! is_readable( $theme_file ) ) {
			$errors[] = sprintf( 'Не найдена тема варианта «%s».', $slug );
			continue;
		}

		$source = file_get_contents( $theme_file );

		if ( false === $source || ! preg_match( '/^[ \t\/*#@]*Theme Name:\s*(.+)$/mi', $source ) ) {
			$errors[] = sprintf( 'В теме «%s» нет заголовка Theme Name.', $slug );
		}
	}

	if ( null === $selected ) {
		$errors[] = 'Ни один вариант дизайна не выбран.';
	} elseif ( ! isset( $manifest['selected'] ) || $selected !== $manifest['selected'] ) {
		$errors[] = 'Поле selected должно совпадать с выбранным вариантом.';
	}

	foreach ( $manifest['variants'] as $variant ) {
		if ( ! is_array( $variant ) || empty( $variant['slug'] ) ) {
			continue;
		}

		$expected = ( $variant['slug'] === $selected ) ? 'selected' : 'rejected';

		if ( ! isset( $variant['status'] ) || $expected !== $variant['status'] ) {
			$errors[] = sprintf( 'Невыбранный вариант «%s» должен быть помечен rejected.', $variant['slug'] );
		}
	}

	return $errors;
}

if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === realpath( __FILE__ ) ) {
	$command = isset( $argv[1] ) ? $argv[1] : 'list';

	try {
		if ( 'select' === $command ) {
			$slug     = isset( $argv[2] ) ? $argv[2] : '';
			$manifest = velotok_theme_variants_select( $slug );

			printf( 'OK: выбран вариант «%s», остальные помечены rejected.%s', $slug, PHP_EOL );
			printf( 'Манифест: %s%s', velotok_theme_variants_manifest_path(), PHP_EOL );

			if ( function_exists( 'switch_theme' ) ) {
				switch_theme( $slug );
				printf( 'Активная тема WordPress переключена на «%s».%s', $slug, PHP_EOL );
			}

			exit( 0 );
		}

		if ( 'list' === $command ) {
			$manifest = velotok_theme_variants_load();
			$errors   = velotok_theme_variants_validate( $manifest );

			printf( "%-18s %-10s %s%s", 'SLUG', 'STATUS', 'NAME', PHP_EOL );

			foreach ( $manifest['variants'] as $variant ) {
				printf(
					"%-18s %-10s %s%s",
					$variant['slug'],
					isset( $variant['status'] ) ? $variant['status'] : 'unknown',
					isset( $variant['name'] ) ? $variant['name'] : '',
					PHP_EOL
				);
			}

			if ( $errors ) {
				foreach ( $errors as $error ) {
					fwrite( STDERR, 'FAIL: ' . $error . PHP_EOL );
				}

				exit( 1 );
			}

			printf( '%sOK: выбран «%s», остальные варианты отклонены.%s', PHP_EOL, $manifest['selected'], PHP_EOL );
			exit( 0 );
		}

		fwrite( STDERR, 'Неизвестная команда. Используйте list или select <slug>.' . PHP_EOL );
		exit( 1 );
	} catch ( Throwable $exception ) {
		fwrite( STDERR, 'FAIL: ' . $exception->getMessage() . PHP_EOL );
		exit( 1 );
	}
}
