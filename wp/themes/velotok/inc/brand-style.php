<?php
/**
 * Контракт фирменного стиля темы «Велоток».
 *
 * Фирменный стиль описан машинно-читаемым манифестом brand-style.json:
 * палитра, типографика, форма, сетка и логотип. Тема использует этот
 * контракт в шапке, подвале и метаданных документа, а базовые CSS-переменные
 * assets/css/main.css должны совпадать со значениями манифеста.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

/**
 * Возвращает путь к манифесту фирменного стиля.
 *
 * @return string
 */
function velotok_brand_style_manifest_path() {
	$path = get_template_directory() . '/brand-style.json';

	if ( function_exists( 'apply_filters' ) ) {
		$path = (string) apply_filters( 'velotok_brand_style_manifest_path', $path );
	}

	return $path;
}

/**
 * Встроенный контракт на случай отсутствующего или повреждённого манифеста.
 *
 * @return array<string,mixed>
 */
function velotok_brand_style_fallback() {
	return array(
		'version'     => 1,
		'spec'        => 'theme',
		'name'        => 'Велоток',
		'tagline'     => 'электровелосипеды',
		'theme_color' => 'ink',
		'palette'     => array(
			'ink'       => '#12211a',
			'ink-2'     => '#1d3127',
			'paper'     => '#f3f0e7',
			'card'      => '#fbf9f3',
			'muted'     => '#58655d',
			'line'      => '#d9d3c4',
			'lime'      => '#d8f26a',
			'lime-soft' => '#e9f1c9',
			'orange'    => '#d9531f',
		),
		'typography'  => array(
			'sans'        => 'ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif',
			'serif'       => 'Georgia,"Times New Roman",serif',
			'mono'        => 'ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace',
			'base_size'   => '16px',
			'line_height' => '1.5',
		),
		'shape'       => array(
			'radius' => '24px',
			'pill'   => '999px',
		),
		'layout'      => array(
			'content_width' => '1280px',
			'padding'       => 'clamp(20px,4.5vw,56px)',
		),
		'logo'        => array(
			'view_box'       => '0 0 40 40',
			'circle_radius'  => '19',
			'bolt_path'      => 'M22.6 8.5 13 22.1h6.2l-1.8 9.4L27.2 17h-6.3l1.7-8.5Z',
			'header'         => array(
				'background' => 'ink',
				'foreground' => 'lime',
			),
			'footer'         => array(
				'background' => 'lime',
				'foreground' => 'ink',
			),
		),
	);
}

/**
 * Проверяет цвет фирменной палитры.
 *
 * @param mixed  $value    Значение из манифеста.
 * @param string $fallback Значение по умолчанию.
 * @return string
 */
function velotok_brand_style_sanitize_color( $value, $fallback = '' ) {
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';

	if ( preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ) {
		return strtolower( $value );
	}

	return (string) $fallback;
}

/**
 * Проверяет строковое значение контракта.
 *
 * @param mixed  $value    Значение из манифеста.
 * @param string $fallback Значение по умолчанию.
 * @return string
 */
function velotok_brand_style_sanitize_text( $value, $fallback = '' ) {
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';

	return '' !== $value ? $value : (string) $fallback;
}

/**
 * Проверяет CSS-значение без внешних ссылок и выражений.
 *
 * @param mixed  $value    Значение из манифеста.
 * @param string $fallback Значение по умолчанию.
 * @return string
 */
function velotok_brand_style_sanitize_css_value( $value, $fallback = '' ) {
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';

	if ( '' === $value || false !== strpos( $value, '://' ) || false !== strpos( $value, 'url(' ) || false !== strpos( $value, 'expression(' ) ) {
		return (string) $fallback;
	}

	return $value;
}

/**
 * Приводит манифест к предсказуемой структуре.
 *
 * @param mixed $data Данные манифеста.
 * @return array<string,mixed>
 */
function velotok_brand_style_normalize( $data ) {
	$fallback = velotok_brand_style_fallback();

	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$palette = array();

	foreach ( $fallback['palette'] as $token => $default ) {
		$source = isset( $data['palette'] ) && is_array( $data['palette'] ) ? $data['palette'] : array();
		$palette[ $token ] = velotok_brand_style_sanitize_color( $source[ $token ] ?? '', $default );
	}

	$typography = array();

	foreach ( $fallback['typography'] as $token => $default ) {
		$source = isset( $data['typography'] ) && is_array( $data['typography'] ) ? $data['typography'] : array();
		$typography[ $token ] = velotok_brand_style_sanitize_css_value( $source[ $token ] ?? '', $default );
	}

	$shape = array();

	foreach ( $fallback['shape'] as $token => $default ) {
		$source = isset( $data['shape'] ) && is_array( $data['shape'] ) ? $data['shape'] : array();
		$shape[ $token ] = velotok_brand_style_sanitize_css_value( $source[ $token ] ?? '', $default );
	}

	$layout = array();

	foreach ( $fallback['layout'] as $token => $default ) {
		$source = isset( $data['layout'] ) && is_array( $data['layout'] ) ? $data['layout'] : array();
		$layout[ $token ] = velotok_brand_style_sanitize_css_value( $source[ $token ] ?? '', $default );
	}

	$logo       = isset( $data['logo'] ) && is_array( $data['logo'] ) ? $data['logo'] : array();
	$logo_shape = array(
		'view_box'      => velotok_brand_style_sanitize_text( $logo['view_box'] ?? '', $fallback['logo']['view_box'] ),
		'circle_radius' => velotok_brand_style_sanitize_text( $logo['circle_radius'] ?? '', $fallback['logo']['circle_radius'] ),
		'bolt_path'     => velotok_brand_style_sanitize_text( $logo['bolt_path'] ?? '', $fallback['logo']['bolt_path'] ),
	);

	foreach ( array( 'header', 'footer' ) as $context ) {
		$source = isset( $logo[ $context ] ) && is_array( $logo[ $context ] ) ? $logo[ $context ] : array();
		$logo_shape[ $context ] = array(
			'background' => velotok_brand_style_sanitize_text( $source['background'] ?? '', $fallback['logo'][ $context ]['background'] ),
			'foreground' => velotok_brand_style_sanitize_text( $source['foreground'] ?? '', $fallback['logo'][ $context ]['foreground'] ),
		);

		foreach ( $logo_shape[ $context ] as $role => $token ) {
			if ( ! isset( $palette[ $token ] ) ) {
				$logo_shape[ $context ][ $role ] = $fallback['logo'][ $context ][ $role ];
			}
		}
	}

	$theme_color = velotok_brand_style_sanitize_text( $data['theme_color'] ?? '', $fallback['theme_color'] );

	if ( ! isset( $palette[ $theme_color ] ) ) {
		$theme_color = $fallback['theme_color'];
	}

	return array(
		'version'     => isset( $data['version'] ) && is_scalar( $data['version'] ) ? max( 1, (int) $data['version'] ) : $fallback['version'],
		'spec'        => velotok_brand_style_sanitize_text( $data['spec'] ?? '', $fallback['spec'] ),
		'name'        => velotok_brand_style_sanitize_text( $data['name'] ?? '', $fallback['name'] ),
		'tagline'     => velotok_brand_style_sanitize_text( $data['tagline'] ?? '', $fallback['tagline'] ),
		'theme_color' => $theme_color,
		'palette'     => $palette,
		'typography'  => $typography,
		'shape'       => $shape,
		'layout'      => $layout,
		'logo'        => $logo_shape,
	);
}

/**
 * Читает контракт фирменного стиля.
 *
 * @param bool $reload Перечитать манифест с диска.
 * @return array<string,mixed>
 */
function velotok_brand_style_contract( $reload = false ) {
	static $contract = null;

	if ( $reload ) {
		$contract = null;
	}

	if ( is_array( $contract ) ) {
		return $contract;
	}

	$path = velotok_brand_style_manifest_path();
	$data = array();

	if ( '' !== $path && is_readable( $path ) ) {
		$decoded = json_decode( (string) file_get_contents( $path ), true );

		if ( is_array( $decoded ) ) {
			$data = $decoded;
		}
	}

	$contract = velotok_brand_style_normalize( $data );

	return $contract;
}

/**
 * Псевдоним контракта для шаблонов и интеграций.
 *
 * @param bool $reload Перечитать манифест.
 * @return array<string,mixed>
 */
function velotok_brand_style( $reload = false ) {
	return velotok_brand_style_contract( $reload );
}

/**
 * Псевдоним контракта с явным названием темы.
 *
 * @param bool $reload Перечитать манифест.
 * @return array<string,mixed>
 */
function velotok_theme_brand_style_contract( $reload = false ) {
	return velotok_brand_style_contract( $reload );
}

/**
 * Псевдоним контракта для интеграций.
 *
 * @param bool $reload Перечитать манифест.
 * @return array<string,mixed>
 */
function velotok_get_brand_style( $reload = false ) {
	return velotok_brand_style_contract( $reload );
}

/**
 * Возвращает значение контракта по точечному пути.
 *
 * @param string $path    Путь вида palette.ink.
 * @param mixed  $default Значение по умолчанию.
 * @return mixed
 */
function velotok_brand_style_value( $path, $default = '' ) {
	$contract = velotok_brand_style_contract();
	$path     = is_scalar( $path ) ? (string) $path : '';

	if ( '' === $path ) {
		return $default;
	}

	$value = $contract;

	foreach ( explode( '.', $path ) as $segment ) {
		if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
			return $default;
		}

		$value = $value[ $segment ];
	}

	return $value;
}

/**
 * Возвращает фирменную палитру.
 *
 * @return array<string,string>
 */
function velotok_brand_palette() {
	$palette = velotok_brand_style_value( 'palette', array() );

	return is_array( $palette ) ? $palette : array();
}

/**
 * Возвращает цвет палитры по имени токена.
 *
 * @param string $token    Имя токена.
 * @param string $fallback Значение по умолчанию.
 * @return string
 */
function velotok_brand_color( $token, $fallback = '' ) {
	$palette = velotok_brand_palette();
	$token   = is_scalar( $token ) ? (string) $token : '';

	if ( isset( $palette[ $token ] ) ) {
		return (string) $palette[ $token ];
	}

	return (string) $fallback;
}

/**
 * Возвращает типографику фирменного стиля.
 *
 * @return array<string,string>
 */
function velotok_brand_typography() {
	$typography = velotok_brand_style_value( 'typography', array() );

	return is_array( $typography ) ? $typography : array();
}

/**
 * Возвращает описание логотипа.
 *
 * @return array<string,mixed>
 */
function velotok_brand_logo() {
	$logo = velotok_brand_style_value( 'logo', array() );

	return is_array( $logo ) ? $logo : array();
}

/**
 * Возвращает плоскую карту CSS-переменных фирменного стиля.
 *
 * @return array<string,string>
 */
function velotok_brand_tokens() {
	$contract = velotok_brand_style_contract();
	$tokens   = array();

	foreach ( $contract['palette'] as $name => $value ) {
		$tokens[ '--' . $name ] = $value;
	}

	foreach ( $contract['typography'] as $name => $value ) {
		$tokens[ '--' . $name ] = $value;
	}

	$tokens['--radius'] = $contract['shape']['radius'];
	$tokens['--pad']    = $contract['layout']['padding'];

	return $tokens;
}

/**
 * Псевдоним плоской карты токенов для интеграций.
 *
 * @return array<string,string>
 */
function velotok_brand_style_tokens() {
	return velotok_brand_tokens();
}

/**
 * Собирает CSS-переменные фирменного стиля.
 *
 * @return string
 */
function velotok_brand_style_css_variables() {
	$declarations = array();

	foreach ( velotok_brand_tokens() as $name => $value ) {
		$declarations[] = $name . ':' . $value;
	}

	return implode( ';', $declarations ) . ';';
}

/**
 * Возвращает блок :root с фирменными переменными.
 *
 * @return string
 */
function velotok_brand_style_css() {
	return ':root{' . velotok_brand_style_css_variables() . '}';
}

/**
 * Экранирует атрибут для разметки вне WordPress.
 *
 * @param mixed $value Значение.
 * @return string
 */
function velotok_brand_style_attribute( $value ) {
	$value = is_scalar( $value ) ? (string) $value : '';

	if ( function_exists( 'esc_attr' ) ) {
		return esc_attr( $value );
	}

	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

/**
 * Возвращает SVG-логотип, собранный из контракта фирменного стиля.
 *
 * @param string $context Контекст: header или footer.
 * @return string
 */
function velotok_brand_logo_svg( $context = 'header' ) {
	$context = 'footer' === $context ? 'footer' : 'header';
	$logo    = velotok_brand_logo();
	$config  = isset( $logo[ $context ] ) && is_array( $logo[ $context ] ) ? $logo[ $context ] : array();
	$circle  = velotok_brand_color( $config['background'] ?? 'ink', '#12211a' );
	$bolt    = velotok_brand_color( $config['foreground'] ?? 'lime', '#d8f26a' );
	$view    = (string) ( $logo['view_box'] ?? '0 0 40 40' );
	$radius  = (string) ( $logo['circle_radius'] ?? '19' );
	$path    = (string) ( $logo['bolt_path'] ?? '' );

	return '<svg class="logo-mark" viewBox="' . velotok_brand_style_attribute( $view ) . '" aria-hidden="true">'
		. '<circle cx="20" cy="20" r="' . velotok_brand_style_attribute( $radius ) . '" fill="' . velotok_brand_style_attribute( $circle ) . '"/>'
		. '<path d="' . velotok_brand_style_attribute( $path ) . '" fill="' . velotok_brand_style_attribute( $bolt ) . '"/>'
		. '</svg>';
}

/**
 * Проверяет контракт и возвращает список ошибок.
 *
 * @return string[]
 */
function velotok_brand_style_validate() {
	$contract = velotok_brand_style_contract();
	$errors   = array();

	if ( '' === (string) $contract['name'] ) {
		$errors[] = 'Не задано название фирменного стиля.';
	}

	foreach ( array( 'ink', 'paper', 'card', 'muted', 'line', 'lime', 'orange' ) as $token ) {
		if ( empty( $contract['palette'][ $token ] ) ) {
			$errors[] = 'Не задан цвет палитры: ' . $token . '.';
		}
	}

	foreach ( array( 'sans', 'serif', 'mono' ) as $token ) {
		if ( empty( $contract['typography'][ $token ] ) ) {
			$errors[] = 'Не задан шрифт: ' . $token . '.';
		}
	}

	if ( empty( $contract['shape']['radius'] ) ) {
		$errors[] = 'Не задан радиус карточек.';
	}

	return $errors;
}
