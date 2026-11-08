<?php
/**
 * Варианты дизайна темы.
 *
 * Набор вариантов описывается манифестом design-manifest.json. Манифест
 * задаёт вариант по умолчанию, человекочитаемые подписи и необязательный
 * файл стилей для каждого варианта. Активный вариант можно выбрать в
 * «Настройщике», параметром запроса или фильтром, но только если он объявлен
 * в манифесте: произвольное имя класса или путь к CSS не принимается.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

/**
 * Возвращает путь к манифесту вариантов дизайна.
 *
 * @return string
 */
function velotok_design_manifest_path() {
	$path = get_template_directory() . '/design-manifest.json';

	if ( function_exists( 'apply_filters' ) ) {
		$path = (string) apply_filters( 'velotok_design_manifest_path', $path );
	}

	return $path;
}

/**
 * Приводит имя варианта к безопасному ключу.
 *
 * @param mixed $slug Имя варианта.
 * @return string
 */
function velotok_design_sanitize_slug( $slug ) {
	$slug = is_scalar( $slug ) ? (string) $slug : '';

	if ( function_exists( 'sanitize_key' ) ) {
		return (string) sanitize_key( $slug );
	}

	return (string) preg_replace( '/[^a-z0-9_-]/i', '', strtolower( $slug ) );
}

/**
 * Проверяет относительный путь к CSS варианта.
 *
 * @param mixed $stylesheet Путь из манифеста.
 * @return string Пустой путь, если значение небезопасно.
 */
function velotok_design_sanitize_stylesheet( $stylesheet ) {
	$stylesheet = is_scalar( $stylesheet ) ? trim( (string) $stylesheet ) : '';

	if ( '' === $stylesheet ) {
		return '';
	}

	if ( false !== strpos( $stylesheet, '..' ) || false !== strpos( $stylesheet, '://' ) ) {
		return '';
	}

	if ( '/' === $stylesheet[0] || '\\' === $stylesheet[0] ) {
		return '';
	}

	if ( ! preg_match( '#^[a-z0-9][a-z0-9_./ -]*\.css$#i', $stylesheet ) ) {
		return '';
	}

	return $stylesheet;
}

/**
 * Нормализует один вариант из манифеста.
 *
 * @param string $slug    Ключ варианта.
 * @param mixed  $variant Данные варианта.
 * @return array<string,string>|null
 */
function velotok_design_normalize_variant( $slug, $variant ) {
	$slug = velotok_design_sanitize_slug( $slug );

	if ( '' === $slug ) {
		return null;
	}

	if ( ! is_array( $variant ) ) {
		$variant = array();
	}

	$label = isset( $variant['label'] ) && is_scalar( $variant['label'] )
		? trim( (string) $variant['label'] )
		: '';

	if ( '' === $label ) {
		$label = ucfirst( $slug );
	}

	$description = isset( $variant['description'] ) && is_scalar( $variant['description'] )
		? trim( (string) $variant['description'] )
		: '';

	$stylesheet = '';

	foreach ( array( 'stylesheet', 'style', 'css' ) as $key ) {
		if ( isset( $variant[ $key ] ) ) {
			$stylesheet = velotok_design_sanitize_stylesheet( $variant[ $key ] );
			break;
		}
	}

	return array(
		'slug'        => $slug,
		'label'       => $label,
		'description' => $description,
		'stylesheet'  => $stylesheet,
		'body_class'  => 'design-variant-' . $slug,
	);
}

/**
 * Встроенный набор вариантов на случай повреждённого манифеста.
 *
 * @return array<string,mixed>
 */
function velotok_design_fallback_manifest() {
	return array(
		'version'  => 1,
		'default'  => 'classic',
		'variants' => array(
			'classic'  => array(
				'slug'        => 'classic',
				'label'       => 'Классический',
				'description' => 'Базовая палитра «Велоток».',
				'stylesheet'  => '',
				'body_class'  => 'design-variant-classic',
			),
			'contrast' => array(
				'slug'        => 'contrast',
				'label'       => 'Высокий контраст',
				'description' => 'Усиленный контраст для доступности.',
				'stylesheet'  => 'assets/css/variants/contrast.css',
				'body_class'  => 'design-variant-contrast',
			),
		),
	);
}

/**
 * Читает и проверяет манифест вариантов дизайна.
 *
 * @param bool $reload Перечитать манифест с диска.
 * @return array<string,mixed>
 */
function velotok_design_manifest( $reload = false ) {
	static $manifest = null;

	if ( $reload ) {
		$manifest = null;
	}

	if ( is_array( $manifest ) ) {
		return $manifest;
	}

	$fallback = velotok_design_fallback_manifest();
	$path     = velotok_design_manifest_path();

	if ( '' === $path || ! is_readable( $path ) ) {
		$manifest = $fallback;

		return $manifest;
	}

	$raw = file_get_contents( $path );
	$data = json_decode( (string) $raw, true );

	if ( ! is_array( $data ) || ! isset( $data['variants'] ) || ! is_array( $data['variants'] ) ) {
		$manifest = $fallback;

		return $manifest;
	}

	$variants = array();

	foreach ( $data['variants'] as $slug => $variant ) {
		$normalized = velotok_design_normalize_variant( $slug, $variant );

		if ( null !== $normalized ) {
			$variants[ $normalized['slug'] ] = $normalized;
		}
	}

	if ( empty( $variants ) ) {
		$manifest = $fallback;

		return $manifest;
	}

	$default = isset( $data['default'] ) ? velotok_design_sanitize_slug( $data['default'] ) : '';

	if ( '' === $default || ! isset( $variants[ $default ] ) ) {
		$keys    = array_keys( $variants );
		$default = (string) $keys[0];
	}

	$version = isset( $data['version'] ) && is_scalar( $data['version'] )
		? (int) $data['version']
		: 1;

	$manifest = array(
		'version'  => max( 1, $version ),
		'default'  => $default,
		'variants' => $variants,
	);

	return $manifest;
}

/**
 * Возвращает объявленные в манифесте варианты дизайна.
 *
 * @param bool $reload Перечитать манифест.
 * @return array<string,array<string,string>>
 */
function velotok_design_variants( $reload = false ) {
	$manifest = velotok_design_manifest( $reload );

	return isset( $manifest['variants'] ) && is_array( $manifest['variants'] )
		? $manifest['variants']
		: array();
}

/**
 * Проверяет, объявлен ли вариант в манифесте.
 *
 * @param mixed $slug Имя варианта.
 * @return bool
 */
function velotok_is_design_variant( $slug ) {
	$slug = velotok_design_sanitize_slug( $slug );

	return '' !== $slug && array_key_exists( $slug, velotok_design_variants() );
}

/**
 * Возвращает активный вариант дизайна.
 *
 * Приоритет: параметр запроса, настройка темы, фильтр. Любое неизвестное
 * значение откатывается к варианту по умолчанию из манифеста.
 *
 * @return string
 */
function velotok_get_active_design_variant() {
	$manifest = velotok_design_manifest();
	$variants = velotok_design_variants();
	$default  = isset( $manifest['default'] ) ? (string) $manifest['default'] : 'classic';
	$slug     = '';

	foreach ( array( 'velotok_variant', 'design_variant', 'variant' ) as $key ) {
		if ( isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ) {
			$requested = function_exists( 'wp_unslash' ) ? wp_unslash( $_GET[ $key ] ) : $_GET[ $key ];
			$candidate = velotok_design_sanitize_slug( $requested );

			if ( isset( $variants[ $candidate ] ) ) {
				$slug = $candidate;
				break;
			}
		}
	}

	if ( '' === $slug && function_exists( 'get_theme_mod' ) ) {
		$candidate = velotok_design_sanitize_slug( get_theme_mod( 'velotok_design_variant', '' ) );

		if ( isset( $variants[ $candidate ] ) ) {
			$slug = $candidate;
		}
	}

	if ( function_exists( 'apply_filters' ) ) {
		$candidate = velotok_design_sanitize_slug( apply_filters( 'velotok_active_design_variant', $slug, $variants ) );

		if ( isset( $variants[ $candidate ] ) ) {
			$slug = $candidate;
		}
	}

	return isset( $variants[ $slug ] ) ? $slug : $default;
}

/**
 * Псевдонимы для шаблонов темы.
 *
 * @return string
 */
function velotok_design_variant() {
	return velotok_get_active_design_variant();
}

/**
 * Псевдоним для интеграций.
 *
 * @return string
 */
function velotok_get_design_variant() {
	return velotok_get_active_design_variant();
}

/**
 * Псевдоним для интеграций.
 *
 * @param bool $reload Перечитать манифест.
 * @return array<string,array<string,string>>
 */
function velotok_get_design_variants( $reload = false ) {
	return velotok_design_variants( $reload );
}

/**
 * Возвращает относительный путь к CSS активного или указанного варианта.
 *
 * @param string|null $slug Имя варианта или null для активного.
 * @return string
 */
function velotok_design_variant_stylesheet( $slug = null ) {
	$variants = velotok_design_variants();

	if ( null === $slug ) {
		$slug = velotok_get_active_design_variant();
	}

	$slug = velotok_design_sanitize_slug( $slug );

	if ( ! isset( $variants[ $slug ]['stylesheet'] ) ) {
		return '';
	}

	return (string) $variants[ $slug ]['stylesheet'];
}

/**
 * Добавляет класс активного варианта к body.
 *
 * @param array $classes Существующие классы.
 * @return array
 */
function velotok_design_body_class( $classes ) {
	if ( ! is_array( $classes ) ) {
		$classes = array();
	}

	$slug     = velotok_get_active_design_variant();
	$variants = velotok_design_variants();

	if ( isset( $variants[ $slug ] ) ) {
		$class = 'design-variant-' . $slug;

		if ( ! in_array( $class, $classes, true ) ) {
			$classes[] = $class;
		}

		if ( ! in_array( 'has-design-variant', $classes, true ) ) {
			$classes[] = 'has-design-variant';
		}
	}

	return $classes;
}

/**
 * Подключает CSS выбранного варианта дизайна.
 *
 * @return void
 */
function velotok_design_enqueue_variant_assets() {
	$relative = velotok_design_variant_stylesheet();

	if ( '' === $relative ) {
		return;
	}

	$file = get_template_directory() . '/' . ltrim( $relative, '/' );

	if ( ! is_file( $file ) ) {
		return;
	}

	$version = function_exists( 'velotok_asset_version' )
		? velotok_asset_version( $relative )
		: ( defined( 'VELOTOK_VERSION' ) ? VELOTOK_VERSION : '1.0.0' );

	wp_enqueue_style(
		'velotok-design-' . velotok_get_active_design_variant(),
		get_template_directory_uri() . '/' . ltrim( $relative, '/' ),
		array( 'velotok-style' ),
		$version
	);
}

/**
 * Проверяет значение настройки «Настройщика».
 *
 * @param mixed $value Значение.
 * @return string
 */
function velotok_sanitize_design_variant( $value ) {
	$slug = velotok_design_sanitize_slug( $value );

	if ( velotok_is_design_variant( $slug ) ) {
		return $slug;
	}

	$manifest = velotok_design_manifest();

	return isset( $manifest['default'] ) ? (string) $manifest['default'] : 'classic';
}

/**
 * Регистрирует выбор варианта дизайна в «Настройщике».
 *
 * @param WP_Customize_Manager $wp_customize Настройщик.
 * @return void
 */
function velotok_design_customize_register( $wp_customize ) {
	if ( ! is_object( $wp_customize ) || ! method_exists( $wp_customize, 'add_section' ) ) {
		return;
	}

	$manifest = velotok_design_manifest();
	$choices  = array();

	foreach ( velotok_design_variants() as $slug => $variant ) {
		$choices[ $slug ] = $variant['label'];
	}

	$wp_customize->add_section(
		'velotok_design',
		array(
			'title'       => __( 'Велоток: вариант дизайна', 'velotok' ),
			'priority'    => 25,
			'description' => __( 'Варианты перечислены в манифесте темы. Неизвестный вариант не применяется.', 'velotok' ),
		)
	);

	$wp_customize->add_setting(
		'velotok_design_variant',
		array(
			'default'           => isset( $manifest['default'] ) ? $manifest['default'] : 'classic',
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'sanitize_callback' => 'velotok_sanitize_design_variant',
		)
	);

	$wp_customize->add_control(
		'velotok_design_variant',
		array(
			'label'   => __( 'Вариант оформления', 'velotok' ),
			'section' => 'velotok_design',
			'type'    => 'select',
			'choices' => $choices,
		)
	);
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'body_class', 'velotok_design_body_class' );
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'wp_enqueue_scripts', 'velotok_design_enqueue_variant_assets', 25 );
	add_action( 'customize_register', 'velotok_design_customize_register' );
}
