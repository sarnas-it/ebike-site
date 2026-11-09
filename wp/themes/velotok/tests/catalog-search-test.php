<?php
/**
 * Behaviour test for TESTEB-19: catalog search by name, description, model,
 * SKU and category.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/catalog-search-test.php
 *
 * The test covers WooCommerce product objects, normalized catalog arrays and
 * the WordPress archive search SQL. It fails on the previous implementation:
 * array products ignored the model field and the archive SQL never searched
 * product categories.
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

$GLOBALS['velotok_test_meta']       = array();
$GLOBALS['velotok_test_categories'] = array();
$failures                           = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_catalog_search_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/*
 * Minimal WordPress API used by the catalog search path.
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
function wp_trim_words( $text, $num_words = 55, $more = '…' ) { return (string) $text; }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_key( $key ) { return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals, '.', ' ' ); }
function get_template_directory_uri() { return 'http://example.test/wp-content/themes/velotok'; }
function get_template_directory() { return dirname( __DIR__ ); }
function home_url( $path = '' ) { return 'http://example.test' . $path; }
function get_post_meta( $post_id, $key, $single = false ) {
	return isset( $GLOBALS['velotok_test_meta'][ $post_id ][ $key ] ) ? $GLOBALS['velotok_test_meta'][ $post_id ][ $key ] : '';
}
function get_post_thumbnail_id( $post_id ) { return 0; }
function get_the_terms( $post_id, $taxonomy ) {
	return isset( $GLOBALS['velotok_test_categories'][ $post_id ] ) ? $GLOBALS['velotok_test_categories'][ $post_id ] : array();
}
function taxonomy_exists( $taxonomy ) { return false; }
function get_term_by() { return false; }
function is_wp_error( $thing ) { return false; }
function get_term_link() { return ''; }
function wc_get_products( $args = array() ) { return array(); }
function wc_get_product( $id ) { return null; }
function wc_attribute_label( $name, $product = null ) { return (string) $name; }
function wc_get_product_terms() { return array(); }
function velotok_spec_value( $key, $fallback = '' ) { return $fallback; }

if ( ! class_exists( 'WC_Product' ) ) {
	/**
	 * Minimal WooCommerce attribute double.
	 */
	class WC_Product_Attribute {
		private $name;
		private $options;
		private $visible;

		public function __construct( $name, $options, $visible = true ) {
			$this->name    = $name;
			$this->options = $options;
			$this->visible = $visible;
		}

		public function get_name() { return $this->name; }
		public function get_options() { return $this->options; }
		public function get_visible() { return $this->visible; }
		public function is_taxonomy() { return false; }
	}

	/**
	 * Minimal WooCommerce product double.
	 */
	class WC_Product {
		private $data;

		public function __construct( $data = array() ) {
			$this->data = $data;
		}

		public function get_id() { return isset( $this->data['id'] ) ? (int) $this->data['id'] : 0; }
		public function get_name() { return isset( $this->data['name'] ) ? $this->data['name'] : ''; }
		public function get_slug() { return isset( $this->data['slug'] ) ? $this->data['slug'] : ''; }
		public function get_status() { return isset( $this->data['status'] ) ? $this->data['status'] : 'publish'; }
		public function get_catalog_visibility() { return 'visible'; }
		public function is_visible() { return true; }
		public function get_permalink() { return isset( $this->data['permalink'] ) ? $this->data['permalink'] : ''; }
		public function get_price() { return isset( $this->data['price'] ) ? $this->data['price'] : 0; }
		public function get_price_html() { return ''; }
		public function get_sku() { return isset( $this->data['sku'] ) ? $this->data['sku'] : ''; }
		public function get_short_description() { return isset( $this->data['short_description'] ) ? $this->data['short_description'] : ''; }
		public function get_description() { return isset( $this->data['description'] ) ? $this->data['description'] : ''; }
		public function get_attributes() { return isset( $this->data['attributes'] ) ? $this->data['attributes'] : array(); }
		public function is_type( $type ) { return false; }
		public function is_purchasable() { return false; }
		public function is_in_stock() { return false; }
		public function add_to_cart_url() { return $this->get_permalink(); }
	}
}

require_once dirname( __DIR__ ) . '/inc/woocommerce.php';

$product = new WC_Product(
	array(
		'id'                => 1,
		'name'              => 'Ривьера',
		'slug'              => 'riviera',
		'sku'               => 'RIV-1',
		'description'       => 'Городской электровелосипед с кожаным седлом.',
		'short_description' => 'Лёгкая низкая рама для ежедневных поездок.',
		'attributes'        => array(
			new WC_Product_Attribute( 'Модель', array( 'Riviera City' ) ),
		),
	)
);

$GLOBALS['velotok_test_categories'] = array(
	1 => array( (object) array( 'slug' => 'city', 'name' => 'Городские' ) ),
);

/*
 * 1. WooCommerce product objects are found by every required field.
 */
$product_search_cases = array(
	array( 'Ривьера', 'название' ),
	array( 'кожаным седлом', 'описание' ),
	array( 'Riviera City', 'модель' ),
	array( 'RIV-1', 'SKU' ),
	array( 'Городские', 'название категории' ),
	array( 'city', 'слаг категории' ),
);

foreach ( $product_search_cases as $case ) {
	velotok_catalog_search_assert(
		velotok_catalog_product_matches_search( $product, $case[0] ),
		'Товар не найден по полю «' . $case[1] . '»: ' . $case[0]
	);
}

velotok_catalog_search_assert(
	false === velotok_catalog_product_matches_search( $product, 'Горные' ),
	'Товар не должен находиться по чужой категории.'
);
velotok_catalog_search_assert(
	velotok_catalog_product_matches_search( $product, 'ривьера городской' ),
	'Поиск должен находить товар по нескольким словам из разных полей.'
);

/*
 * 2. Normalized card arrays expose the same five searchable fields, including
 *    model and SKU aliases used by seller data.
 */
$card = array(
	'name'           => 'Ривьера',
	'description'    => 'Городской электровелосипед с кожаным седлом.',
	'model'          => 'Riviera City',
	'_sku'           => 'RIV-1',
	'categories'     => array(
		(object) array( 'slug' => 'city', 'name' => 'Городские' ),
	),
	'attributes'     => array(
		array( 'label' => 'Модель', 'value' => 'Riviera City' ),
	),
);

foreach ( array( 'Ривьера', 'кожаным седлом', 'Riviera City', 'RIV-1', 'Городские', 'city' ) as $query ) {
	velotok_catalog_search_assert(
		velotok_catalog_product_matches_search( $card, $query ),
		'Карточка каталога не найдена по запросу: ' . $query
	);
}

$filtered = velotok_catalog_filter_products(
	array( $product, $card ),
	array( 'search' => 'Riviera City' )
);
velotok_catalog_search_assert( 2 === count( $filtered ), 'Поиск по модели должен находить объект и карточку.' );

/*
 * 3. The archive search SQL also searches product categories, because a
 *    WooCommerce category term is not part of the post title or content.
 */
class Velotok_Search_WPDB {
	public $posts            = 'wp_posts';
	public $postmeta         = 'wp_postmeta';
	public $term_relationships = 'wp_term_relationships';
	public $term_taxonomy    = 'wp_term_taxonomy';
	public $terms            = 'wp_terms';

	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	public function prepare( $query, $args ) {
		$args = is_array( $args ) ? array_values( $args ) : array();
		$index = 0;

		return (string) preg_replace_callback(
			'/%s/',
			static function ( $matches ) use ( &$index, $args ) {
				$value = isset( $args[ $index ] ) ? $args[ $index ] : '';
				$index++;
				return "'" . str_replace( "'", "''", (string) $value ) . "'";
			},
			(string) $query
		);
	}
}

class Velotok_Search_Query {
	private $vars;

	public function __construct( $vars ) {
		$this->vars = $vars;
	}

	public function get( $key, $default = false ) {
		return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : $default;
	}

	public function is_main_query() { return true; }
	public function is_search() { return true; }
	public function is_post_type_archive( $type = '' ) { return 'product' === $type; }
	public function is_tax( $type = '' ) { return false; }
}

$wpdb   = new Velotok_Search_WPDB();
$query  = new Velotok_Search_Query(
	array(
		'post_type' => 'product',
		's'         => 'Городские',
	)
);
$search = velotok_catalog_extend_product_search(
	" AND ((wp_posts.post_title LIKE '%Городские%'))",
	$query
);

velotok_catalog_search_assert( false !== strpos( $search, 'product_cat' ), 'SQL поиска не учитывает таксономию product_cat.' );
velotok_catalog_search_assert( false !== strpos( $search, 'velotok_t.name' ), 'SQL поиска не учитывает название категории.' );
velotok_catalog_search_assert( false !== strpos( $search, 'velotok_t.slug' ), 'SQL поиска не учитывает слаг категории.' );
velotok_catalog_search_assert( false !== strpos( $search, 'velotok_pm.meta_value' ), 'SQL поиска перестал учитывать SKU и атрибуты.' );
velotok_catalog_search_assert( false !== strpos( $search, 'Городские' ), 'Поисковая строка не попадает в SQL.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок поиска каталога не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: поиск каталога находит товары по названию, описанию, модели, SKU и категории (TESTEB-19).' . PHP_EOL;
exit( 0 );
