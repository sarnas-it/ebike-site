<?php
/**
 * Behaviour test for EBIKE-19: rendered catalog filters get theme styles.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/catalog-filters-rendered-styles-test.php
 *
 * The earlier regression only checked that the archive markup and stylesheet
 * existed. This test renders the real archive filter form through the template,
 * parses the resulting DOM and applies the matching rules from main.css to the
 * rendered elements. It fails when the fieldset/legend/input/button/reset
 * controls would fall back to raw browser chrome.
 *
 * @package Velotok
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, 'This test must be run from the command line.' . PHP_EOL );
	exit( 1 );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_rendered_style_failures'] = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_rendered_style_assert( $condition, $message ) {
	if ( ! $condition ) {
		$GLOBALS['velotok_rendered_style_failures'][] = $message;
	}
}

/*
 * Minimal WordPress/WooCommerce API used by archive-product.php.
 */
function add_filter() { return true; }
function add_action() {}
function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_html_e( $text, $domain = null ) { echo esc_html( $text ); }
function esc_attr_e( $text, $domain = null ) { echo esc_attr( $text ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return (string) $url; }
function wp_kses_post( $text ) { return (string) $text; }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_key( $key ) { return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals, '.', ' ' ); }
function get_template_directory_uri() { return 'http://example.test/wp-content/themes/velotok'; }
function get_template_directory() { return dirname( __DIR__ ); }
function home_url( $path = '' ) { return 'http://example.test' . $path; }
function wc_get_page_permalink( $page = 'shop' ) { return home_url( '/shop/' ); }
function taxonomy_exists( $taxonomy ) { return false; }
function is_wp_error( $thing ) { return false; }
function is_shop() { return true; }
function is_product_category( $category = null ) { return false; }
function selected( $selected, $current = true, $echo = true ) {
	$result = (string) $selected === (string) $current ? ' selected="selected"' : '';
	if ( $echo ) {
		echo $result;
	}
	return $result;
}
function get_header() { echo '<header class="site-header"><a href="/">Velotok</a></header>'; }
function get_footer() { echo '<footer class="site-footer">Velotok</footer>'; }
function have_posts() { return false; }
function the_post() {}
function get_queried_object() { return null; }
function term_description() { return ''; }
function get_terms( $args = array() ) { return array(); }

require_once dirname( __DIR__ ) . '/inc/catalog-filters.php';

$template = dirname( __DIR__ ) . '/woocommerce/archive-product.php';

ob_start();
include $template;
$rendered = (string) ob_get_clean();

$document = new DOMDocument();
libxml_use_internal_errors( true );
$document->loadHTML( '<?xml encoding="UTF-8">' . $rendered );
libxml_clear_errors();
$xpath = new DOMXPath( $document );

/**
 * Read the themed stylesheet.
 *
 * @return string
 */
function velotok_rendered_style_css() {
	$file = dirname( __DIR__ ) . '/assets/css/main.css';
	return is_readable( $file ) ? (string) file_get_contents( $file ) : '';
}

/**
 * Split a declaration list into property/value pairs.
 *
 * @param string $body Declarations between braces.
 * @return array<string,string>
 */
function velotok_rendered_style_declarations( $body ) {
	$declarations = array();

	foreach ( explode( ';', (string) $body ) as $declaration ) {
		$colon = strpos( $declaration, ':' );
		if ( false === $colon ) {
			continue;
		}

		$property = strtolower( trim( substr( $declaration, 0, $colon ) ) );
		$value    = trim( preg_replace( '/\s+/', ' ', substr( $declaration, $colon + 1 ) ) );

		if ( '' !== $property && '' !== $value ) {
			$declarations[ $property ] = $value;
		}
	}

	return $declarations;
}

/**
 * Extract top-level style rules and expand comma-separated selectors.
 *
 * Media rules are checked separately because this test applies the desktop
 * cascade to the rendered form.
 *
 * @param string $css Stylesheet.
 * @return array<int,array{selector:string,declarations:array<string,string>,order:int}>
 */
function velotok_rendered_style_rules( $css ) {
	$css   = preg_replace( '!/\*.*?\*/!s', '', (string) $css );
	$rules = array();
	$length = strlen( $css );
	$offset = 0;
	$order  = 0;

	while ( $offset < $length ) {
		$open = strpos( $css, '{', $offset );
		if ( false === $open ) {
			break;
		}

		$selector = trim( substr( $css, $offset, $open - $offset ) );
		$depth    = 0;
		$close    = false;

		for ( $index = $open; $index < $length; $index++ ) {
			if ( '{' === $css[ $index ] ) {
				$depth++;
			} elseif ( '}' === $css[ $index ] ) {
				$depth--;
				if ( 0 === $depth ) {
					$close = $index;
					break;
				}
			}
		}

		if ( false === $close ) {
			break;
		}

		$body = substr( $css, $open + 1, $close - $open - 1 );

		if ( '' !== $selector && '@' !== $selector[0] ) {
			$declarations = velotok_rendered_style_declarations( $body );

			foreach ( explode( ',', $selector ) as $part ) {
				$part = trim( $part );
				if ( '' !== $part ) {
					$rules[] = array(
						'selector'     => $part,
						'declarations' => $declarations,
						'order'        => $order++,
					);
				}
			}
		}

		$offset = $close + 1;
	}

	return $rules;
}

/**
 * Does an element match a simple compound selector?
 *
 * @param DOMElement $element  Rendered element.
 * @param string     $compound Selector without combinators.
 * @return bool
 */
function velotok_rendered_style_compound( $element, $compound ) {
	if ( ! $element instanceof DOMElement ) {
		return false;
	}

	$compound = trim( $compound );
	if ( '' === $compound || false !== strpos( $compound, ':' ) ) {
		return false;
	}

	if ( preg_match( '/^([a-zA-Z][a-zA-Z0-9-]*|\*)/', $compound, $tag_match ) ) {
		if ( '*' !== $tag_match[1] && strtolower( $element->nodeName ) !== strtolower( $tag_match[1] ) ) {
			return false;
		}
	}

	if ( preg_match_all( '/\.([a-zA-Z0-9_-]+)/', $compound, $class_matches ) ) {
		$classes = preg_split( '/\s+/', trim( $element->getAttribute( 'class' ) ) );
		foreach ( $class_matches[1] as $class_name ) {
			if ( ! in_array( $class_name, $classes, true ) ) {
				return false;
			}
		}
	}

	if ( preg_match( '/#([a-zA-Z0-9_-]+)/', $compound, $id_match ) && $element->getAttribute( 'id' ) !== $id_match[1] ) {
		return false;
	}

	if ( preg_match_all( '/\[([a-zA-Z0-9_-]+)(?:\s*=\s*["\']?([^"\'\]]*)["\']?)?\]/', $compound, $attribute_matches, PREG_SET_ORDER ) ) {
		foreach ( $attribute_matches as $attribute_match ) {
			$name = $attribute_match[1];

			if ( ! $element->hasAttribute( $name ) ) {
				return false;
			}

			if ( isset( $attribute_match[2] ) && '' !== $attribute_match[2] && $element->getAttribute( $name ) !== $attribute_match[2] ) {
				return false;
			}
		}
	}

	return true;
}

/**
 * Match a DOM element against a selector with descendant combinators.
 *
 * @param DOMElement $element  Rendered element.
 * @param string     $selector Selector.
 * @return bool
 */
function velotok_rendered_style_matches( $element, $selector ) {
	$selector = preg_replace( '/\s*>\s*/', ' ', trim( $selector ) );
	$parts    = preg_split( '/\s+/', $selector );
	if ( ! $parts ) {
		return false;
	}

	if ( ! velotok_rendered_style_compound( $element, end( $parts ) ) ) {
		return false;
	}

	array_pop( $parts );

	return velotok_rendered_style_ancestors( $element, $parts );
}

/**
 * Match the remaining compound selectors against ancestors.
 *
 * @param DOMElement $element Element.
 * @param string[]   $parts   Ancestor selector parts, right to left.
 * @return bool
 */
function velotok_rendered_style_ancestors( $element, $parts ) {
	if ( ! $parts ) {
		return true;
	}

	$expected = array_pop( $parts );
	$parent   = $element->parentNode;

	while ( $parent instanceof DOMElement ) {
		if ( velotok_rendered_style_compound( $parent, $expected ) && velotok_rendered_style_ancestors( $parent, $parts ) ) {
			return true;
		}
		$parent = $parent->parentNode;
	}

	return false;
}

/**
 * Effective declarations for a rendered element, in source order.
 *
 * @param DOMElement $element Rendered element.
 * @param array      $rules   Parsed rules.
 * @return array<string,string>
 */
function velotok_rendered_style_effective( $element, $rules ) {
	$matching = array();

	foreach ( $rules as $rule ) {
		if ( velotok_rendered_style_matches( $element, $rule['selector'] ) ) {
			$matching[] = $rule;
		}
	}

	usort(
		$matching,
		static function ( $left, $right ) {
			return $left['order'] <=> $right['order'];
		}
	);

	$effective = array();
	foreach ( $matching as $rule ) {
		foreach ( $rule['declarations'] as $property => $value ) {
			$effective[ $property ] = $value;
		}
	}

	return $effective;
}

/**
 * Find one rendered element or record a failure.
 *
 * @param DOMXPath      $xpath   Rendered DOM.
 * @param string        $query   XPath query.
 * @param string        $label   Human label.
 * @param DOMNode|null  $context Optional query context node.
 * @return DOMElement|null
 */
function velotok_rendered_style_element( $xpath, $query, $label, $context = null ) {
	$nodes = $context ? $xpath->query( $query, $context ) : $xpath->query( $query );
	if ( ! $nodes || 0 === $nodes->length ) {
		velotok_rendered_style_assert( false, 'В отрендеренном архиве нет элемента: ' . $label . '.' );
		return null;
	}

	return $nodes->item( 0 );
}

$css   = velotok_rendered_style_css();
$rules = velotok_rendered_style_rules( $css );

velotok_rendered_style_assert( '' !== $css, 'Не найден assets/css/main.css.' );
velotok_rendered_style_assert( false !== strpos( $css, '/* Фильтры каталога (EBIKE-19) */' ), 'В main.css нет блока стилей фильтров каталога.' );
velotok_rendered_style_assert( $rendered !== '' && false !== strpos( $rendered, 'catalog-filter-form' ), 'Шаблон архива не отрендерил форму фильтров.' );

$form = velotok_rendered_style_element( $xpath, "//form[contains(concat(' ', normalize-space(@class), ' '), ' catalog-filter-form ')]", 'форма фильтров' );
$search = velotok_rendered_style_element( $xpath, "//*[contains(concat(' ', normalize-space(@class), ' '), ' catalog-filter-search ')]", 'поисковая строка' );
$range = velotok_rendered_style_element( $xpath, "//fieldset[contains(concat(' ', normalize-space(@class), ' '), ' catalog-filter-range ')]", 'fieldset диапазона' );
$legend = $range ? velotok_rendered_style_element( $xpath, './/legend', 'подпись диапазона', $range ) : null;
$range_label = $range ? velotok_rendered_style_element( $xpath, './/label', 'подпись «от»/«до»', $range ) : null;
$range_input = $range ? velotok_rendered_style_element( $xpath, './/input[@type="number"]', 'числовое поле диапазона', $range ) : null;
$search_input = $search ? velotok_rendered_style_element( $xpath, './/input[@type="search"]', 'поле поиска', $search ) : null;
$field = velotok_rendered_style_element( $xpath, "//*[contains(concat(' ', normalize-space(@class), ' '), ' catalog-filter-field ')]", 'текстовое поле фильтра' );
$field_input = $field ? velotok_rendered_style_element( $xpath, './/input', 'текстовый ввод фильтра', $field ) : null;
$actions = velotok_rendered_style_element( $xpath, "//*[contains(concat(' ', normalize-space(@class), ' '), ' catalog-filter-actions ')]", 'блок действий' );
$button = $actions ? velotok_rendered_style_element( $xpath, ".//button[contains(concat(' ', normalize-space(@class), ' '), ' button ')]", 'кнопка «Применить»', $actions ) : null;
$reset = $actions ? velotok_rendered_style_element( $xpath, ".//a[contains(concat(' ', normalize-space(@class), ' '), ' text-link ')]", 'ссылка «Сбросить»', $actions ) : null;

$checks = array(
	array( $form, 'display', 'grid', 'форма фильтров должна раскладываться сеткой' ),
	array( $form, 'grid-template-columns', 'minmax(190px', 'у формы фильтров должна быть адаптивная сетка' ),
	array( $search, 'grid-column', '1/-1', 'поиск должен занимать всю ширину формы' ),
	array( $range, 'display', 'grid', 'диапазон должен раскладываться сеткой' ),
	array( $range, 'grid-template-columns', 'repeat(2', 'поля «от» и «до» должны стоять в две колонки' ),
	array( $range, 'border', '0', 'у fieldset должна сбрасываться браузерная рамка' ),
	array( $legend, 'float', 'none', 'legend не должен всплывать поверх подписи' ),
	array( $legend, 'padding', '0', 'у legend должен сбрасываться браузерный отступ' ),
	array( $range_label, 'display', 'grid', 'подписи «от»/«до» должны быть сеткой' ),
	array( $range_input, 'width', '100%', 'числовое поле должно занимать всю ячейку' ),
	array( $search_input, 'min-height', '48px', 'поле поиска должно иметь высоту контрола темы' ),
	array( $search_input, 'border-radius', '12px', 'поле поиска должно иметь скругление темы' ),
	array( $field_input, 'border', '1px solid', 'текстовый ввод должен иметь рамку темы' ),
	array( $actions, 'display', 'flex', 'блок действий должен раскладываться флексом' ),
	array( $button, 'border-radius', '999px', 'кнопка «Применить» должна быть кнопкой темы' ),
	array( $button, 'background', 'var(--ink)', 'кнопка «Применить» должна быть залита цветом темы' ),
	array( $reset, 'text-decoration', 'none', 'ссылка «Сбросить» не должна выглядеть браузерной ссылкой' ),
	array( $reset, 'border-radius', '999px', 'ссылка «Сбросить» должна быть кнопкой темы' ),
	array( $reset, 'min-height', '46px', 'ссылка «Сбросить» должна иметь высоту контрола' ),
);

foreach ( $checks as $check ) {
	list( $element, $property, $expected, $label ) = $check;
	if ( ! $element ) {
		continue;
	}

	$effective = velotok_rendered_style_effective( $element, $rules );
	$actual    = isset( $effective[ $property ] ) ? $effective[ $property ] : '';

	velotok_rendered_style_assert(
		false !== strpos( $actual, $expected ),
		'Стиль не применяется к отрендеренному элементу: ' . $label . ' (' . $property . ' = ' . ( '' === $actual ? 'нет правила' : $actual ) . ').'
	);
}

velotok_rendered_style_assert(
	$form && false !== strpos( $css, '/* Фильтры каталога (EBIKE-19) */' ) && false !== strpos( $css, '@media (max-width:768px)' ) && false !== strpos( $css, '@media (max-width:360px)' ),
	'Нет адаптивных правил фильтров для 768px и 360px.'
);

if ( $GLOBALS['velotok_rendered_style_failures'] ) {
	fwrite( STDERR, 'FAIL: ' . count( $GLOBALS['velotok_rendered_style_failures'] ) . ' проверок отрендеренных стилей фильтров не прошли:' . PHP_EOL );

	foreach ( $GLOBALS['velotok_rendered_style_failures'] as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: отрендеренные фильтры каталога получают стили темы на /shop/ и категориях (EBIKE-19).' . PHP_EOL;
exit( 0 );
