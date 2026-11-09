<?php
/**
 * Behaviour test for TESTEB-58: search and filters use contract attributes.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/catalog-search-filters-contract-test.php
 *
 * The test builds products with the card contract attributes (name,
 * description, category, tagline, range, motor, weight, wheel, badge, price and
 * seller attributes) and checks that search and filters read all of them. It
 * also checks that only contract attributes from the request are accepted and
 * that the catalog templates expose the search/filter controls.
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
$GLOBALS['velotok_test_products']   = array();
$GLOBALS['velotok_test_categories'] = array();
$failures                           = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_search_filter_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/*
 * Minimal WordPress API used by the catalog path.
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
function wc_get_products( $args = array() ) { return $GLOBALS['velotok_test_products']; }
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
		public function get_catalog_visibility() { return isset( $this->data['catalog_visibility'] ) ? $this->data['catalog_visibility'] : 'visible'; }
		public function is_visible() { return true; }
		public function get_permalink() { return isset( $this->data['permalink'] ) ? $this->data['permalink'] : ''; }
		public function get_price() { return isset( $this->data['price'] ) ? $this->data['price'] : 0; }
		public function get_price_html() { return isset( $this->data['price_html'] ) ? $this->data['price_html'] : ''; }
		public function get_sku() { return isset( $this->data['sku'] ) ? $this->data['sku'] : ''; }
		public function get_short_description() { return isset( $this->data['short_description'] ) ? $this->data['short_description'] : ''; }
		public function get_description() { return isset( $this->data['description'] ) ? $this->data['description'] : ''; }
		public function get_attributes() { return isset( $this->data['attributes'] ) ? $this->data['attributes'] : array(); }
		public function is_type( $type ) { return isset( $this->data['type'] ) && $type === $this->data['type']; }
		public function is_purchasable() { return ! empty( $this->data['purchasable'] ); }
		public function is_in_stock() { return ! empty( $this->data['in_stock'] ); }
		public function add_to_cart_url() { return $this->get_permalink(); }
	}
}

require_once dirname( __DIR__ ) . '/inc/woocommerce.php';

/**
 * Build a product with contract attributes.
 *
 * @param array $overrides Field overrides.
 * @return WC_Product
 */
function velotok_search_filter_product( $overrides = array() ) {
	$defaults = array(
		'id'                 => 1,
		'name'               => 'Ривьера',
		'slug'               => 'riviera',
		'status'             => 'publish',
		'catalog_visibility' => 'visible',
		'permalink'          => 'http://example.test/product/riviera/',
		'price'              => 62900,
		'price_html'         => '62 900 ₽',
		'sku'                => 'RIV-1',
		'short_description'  => 'Лёгкая низкая рама для ежедневных поездок по городу.',
		'description'        => 'Городской электровелосипед с кожаным седлом.',
		'attributes'         => array(),
		'type'               => 'simple',
		'purchasable'        => true,
		'in_stock'           => true,
	);

	return new WC_Product( array_merge( $defaults, $overrides ) );
}

$riviera = velotok_search_filter_product(
	array(
		'id'         => 1,
		'name'       => 'Ривьера',
		'sku'        => 'RIV-1',
		'attributes' => array(
			new WC_Product_Attribute( 'Модель', array( 'Riviera City' ) ),
			new WC_Product_Attribute( 'Рама', array( 'Алюминий 6061' ) ),
			new WC_Product_Attribute( 'Гарантия', array( '2 года' ) ),
		),
	)
);

$hrebet = velotok_search_filter_product(
	array(
		'id'                => 2,
		'name'              => 'Хребет Pro',
		'slug'              => 'hrebet-pro',
		'permalink'         => 'http://example.test/product/hrebet-pro/',
		'price'             => 149900,
		'price_html'        => '149 900 ₽',
		'sku'               => 'HRB-1',
		'short_description' => '',
		'description'       => 'Горная модель с полной подвеской и мотором 750 Вт.',
		'attributes'        => array(
			new WC_Product_Attribute( 'Рама', array( 'Алюминий 6061' ) ),
		),
	)
);

$family = velotok_search_filter_product(
	array(
		'id'                => 3,
		'name'              => 'Семейный',
		'slug'              => 'family',
		'permalink'         => 'http://example.test/product/family/',
		'price'             => 132900,
		'price_html'        => '132 900 ₽',
		'sku'               => 'FAM-1',
		'short_description' => 'Удлинённая рама и бокс на 80 кг.',
		'description'       => 'Грузовой электровелосипед для детей и дачи.',
		'attributes'        => array(
			new WC_Product_Attribute( 'Рама', array( 'Сталь' ) ),
			new WC_Product_Attribute( 'Максимальная нагрузка', array( '80 кг' ) ),
		),
	)
);

$courier = velotok_search_filter_product(
	array(
		'id'                => 4,
		'name'              => 'Курьер',
		'slug'              => 'courier',
		'permalink'         => 'http://example.test/product/courier/',
		'price'             => 119900,
		'price_html'        => '119 900 ₽',
		'sku'               => 'CRR-1',
		'short_description' => 'Рама под ежедневные нагрузки.',
		'description'       => 'Грузовой электровелосипед с багажником.',
		'attributes'        => array(
			new WC_Product_Attribute( 'Рама', array( 'Сталь' ) ),
			new WC_Product_Attribute( 'Максимальная нагрузка', array( '120 кг' ) ),
		),
	)
);

$GLOBALS['velotok_test_categories'] = array(
	1 => array( (object) array( 'slug' => 'city', 'name' => 'Городские' ) ),
	2 => array( (object) array( 'slug' => 'mountain', 'name' => 'Горные' ) ),
	3 => array( (object) array( 'slug' => 'cargo', 'name' => 'Грузовые' ) ),
	4 => array( (object) array( 'slug' => 'cargo', 'name' => 'Грузовые' ) ),
);

$GLOBALS['velotok_test_meta'] = array(
	1 => array(
		'_velotok_tagline'   => 'Лёгкая низкая рама для города',
		'_velotok_range_km'  => '90',
		'_velotok_motor_w'   => '250',
		'_velotok_weight_kg' => '23',
		'_velotok_wheel'     => '28″',
		'_velotok_badge'     => 'Хит',
	),
	2 => array(
		'_velotok_tagline'   => 'Полная подвеска для гор',
		'_velotok_range_km'  => '120',
		'_velotok_motor_w'   => '750',
		'_velotok_weight_kg' => '',
		'_velotok_wheel'     => '29″',
		'_velotok_badge'     => 'Для гор',
	),
	3 => array(
		'_velotok_tagline'   => 'Бокс на 80 кг',
		'_velotok_range_km'  => '80',
		'_velotok_motor_w'   => '750',
		'_velotok_weight_kg' => '32',
		'_velotok_wheel'     => '20″ / 26″',
		'_velotok_badge'     => 'Для семьи',
	),
	4 => array(
		'_velotok_tagline'   => 'Для ежедневной работы',
		'_velotok_range_km'  => '100',
		'_velotok_motor_w'   => '500',
		'_velotok_weight_kg' => '29',
		'_velotok_wheel'     => '20″',
		'_velotok_badge'     => 'Для работы',
	),
);

$GLOBALS['velotok_test_products'] = array( $riviera, $hrebet, $family, $courier );

/*
 * 1. The contract lists every searchable and filterable card attribute.
 */
velotok_search_filter_assert( function_exists( 'velotok_catalog_filter_contract' ), 'Не определён контракт поиска и фильтров.' );
$contract = velotok_catalog_filter_contract();

velotok_search_filter_assert( isset( $contract['requirement'] ) && 'TESTEB-58' === $contract['requirement'], 'Контракт должен ссылаться на TESTEB-58.' );
velotok_search_filter_assert( isset( $contract['attributes'] ) && is_array( $contract['attributes'] ), 'Контракт не содержит атрибутов.' );

foreach ( array( 'name', 'description', 'sku', 'category', 'tagline', 'range_km', 'motor_w', 'weight_kg', 'wheel', 'badge', 'price', 'model', 'frame', 'warranty', 'max_load' ) as $attribute ) {
	velotok_search_filter_assert( isset( $contract['attributes'][ $attribute ] ), 'В контракте нет атрибута: ' . $attribute );
}

foreach ( array( 'range_km', 'motor_w', 'weight_kg', 'wheel', 'badge', 'frame', 'warranty', 'max_load' ) as $attribute ) {
	velotok_search_filter_assert( in_array( $attribute, velotok_catalog_searchable_attributes(), true ), 'Атрибут не участвует в поиске: ' . $attribute );
	velotok_search_filter_assert( in_array( $attribute, velotok_catalog_filterable_attributes(), true ), 'Атрибут не участвует в фильтрах: ' . $attribute );
}

/*
 * 2. Search reads all contract attributes, not only the product title.
 */
$search_cases = array(
	array( $riviera, 'Ривьера', true, 'название' ),
	array( $riviera, 'кожаным седлом', true, 'описание' ),
	array( $riviera, 'RIV-1', true, 'артикул' ),
	array( $riviera, 'городские', true, 'категория' ),
	array( $riviera, 'лёгкая низкая рама', true, 'слоган' ),
	array( $riviera, '90', true, 'запас хода' ),
	array( $riviera, '250', true, 'мотор' ),
	array( $riviera, '23', true, 'вес' ),
	array( $riviera, '28', true, 'колёса' ),
	array( $riviera, 'Хит', true, 'бейдж' ),
	array( $riviera, 'Riviera City', true, 'атрибут модели' ),
	array( $riviera, 'Алюминий 6061', true, 'атрибут рамы' ),
	array( $riviera, '2 года', true, 'атрибут гарантии' ),
	array( $riviera, '750', false, 'чужой мотор' ),
	array( $hrebet, '750 Вт', true, 'мотор из описания и контракта' ),
);

foreach ( $search_cases as $case ) {
	list( $product, $query, $expected, $label ) = $case;
	velotok_search_filter_assert(
		$expected === velotok_catalog_product_matches_search( $product, $query ),
		'Поиск неверно учитывает ' . $label . ': ' . $query
	);
}

/*
 * 3. Filters read contract attributes, including seller attributes.
 */
$filter_cases = array(
	array( $riviera, array( 'badge' => 'Хит' ), true, 'бейдж' ),
	array( $riviera, array( 'badge' => 'Для гор' ), false, 'чужой бейдж' ),
	array( $riviera, array( 'range_km' => array( 'min' => 80, 'max' => 100 ) ), true, 'диапазон хода' ),
	array( $hrebet, array( 'range_km' => array( 'min' => 80, 'max' => 100 ) ), false, 'ход вне диапазона' ),
	array( $hrebet, array( 'motor_w' => 750 ), true, 'мотор' ),
	array( $riviera, array( 'motor_w' => 750 ), false, 'чужой мотор' ),
	array( $family, array( 'wheel' => '20″' ), true, 'колёса с несколькими значениями' ),
	array( $family, array( 'weight_kg' => array( 'min' => 30, 'max' => 35 ) ), true, 'вес' ),
	array( $riviera, array( 'frame' => 'Алюминий 6061' ), true, 'рама' ),
	array( $family, array( 'frame' => 'Алюминий 6061' ), false, 'чужая рама' ),
	array( $family, array( 'model' => 'Riviera City' ), false, 'чужая модель' ),
	array( $courier, array( 'max_load' => '120 кг' ), true, 'нагрузка' ),
	array( $family, array( 'attributes' => array( 'frame' => 'Сталь' ) ), true, 'дополнительные атрибуты' ),
	array( $riviera, array( 'unknown_attribute' => 'value' ), true, 'неизвестный фильтр игнорируется' ),
);

foreach ( $filter_cases as $case ) {
	list( $product, $filters, $expected, $label ) = $case;
	velotok_search_filter_assert(
		$expected === velotok_catalog_product_matches_filters( $product, $filters ),
		'Фильтр неверно учитывает ' . $label . '.'
	);
}

$filtered = velotok_catalog_filter_products(
	$GLOBALS['velotok_test_products'],
	array(
		'search'  => 'горн',
		'filters' => array( 'motor_w' => array( 'min' => 700 ) ),
	)
);

velotok_search_filter_assert( 1 === count( $filtered ) && 'Хребет Pro' === $filtered[0]->get_name(), 'Поиск и фильтры должны применяться вместе.' );

/*
 * 4. Request parsing accepts only contract attributes and sanitises values.
 */
$request = velotok_catalog_request_query(
	array(
		's'              => 'Хребет',
		'orderby'        => 'price',
		'frame'          => 'Алюминий 6061',
		'velotok_filter' => array(
			'badge'        => 'Для гор',
			'range_km_min' => '100',
			'range_km_max' => '130',
			'motor_w'      => '750',
			'unknown'      => 'ignore-me',
		),
	)
);

velotok_search_filter_assert( 'Хребет' === $request['search'], 'Поиск из запроса не читается.' );
velotok_search_filter_assert( isset( $request['filters']['badge'] ) && 'Для гор' === $request['filters']['badge'], 'Бейдж из запроса не читается.' );
velotok_search_filter_assert( isset( $request['filters']['range_km']['min'], $request['filters']['range_km']['max'] ), 'Диапазон хода из запроса не читается.' );
velotok_search_filter_assert( 100.0 === (float) $request['filters']['range_km']['min'] && 130.0 === (float) $request['filters']['range_km']['max'], 'Диапазон хода из запроса приведён к числу неверно.' );
velotok_search_filter_assert( isset( $request['filters']['motor_w'] ) && 750.0 === (float) $request['filters']['motor_w'], 'Мотор из запроса приведён к числу неверно.' );
velotok_search_filter_assert( isset( $request['filters']['frame'] ) && 'Алюминий 6061' === $request['filters']['frame'], 'Атрибут рамы из запроса не читается.' );
velotok_search_filter_assert( ! isset( $request['filters']['unknown'], $request['filters']['orderby'] ), 'В фильтры попал unknown/orderby.' );
velotok_search_filter_assert( isset( $request['filters']['unknown'] ) === false, 'Неизвестный фильтр не должен попадать в контракт.' );

/*
 * 5. The catalog entry point accepts search/filter arguments.
 */
$models = velotok_get_home_models( array( 'filters' => array( 'badge' => 'Хит' ) ) );
velotok_search_filter_assert( 1 === count( $models ) && 'Ривьера' === $models[0]['name'], 'Главный каталог не применяет фильтры контракта.' );

$models = velotok_get_home_models( array( 'search' => 'Хребет' ) );
velotok_search_filter_assert( 1 === count( $models ) && 'Хребет Pro' === $models[0]['name'], 'Главный каталог не применяет поиск по атрибутам.' );

/*
 * 6. The WooCommerce archive exposes search and attribute filter controls.
 */
$archive = (string) file_get_contents( dirname( __DIR__ ) . '/woocommerce/archive-product.php' );

velotok_search_filter_assert( false !== strpos( $archive, 'catalog-filter-form' ), 'В архиве каталога нет формы поиска и фильтров.' );
velotok_search_filter_assert( false !== strpos( $archive, 'name="s"' ), 'В архиве каталога нет поисковой строки.' );
velotok_search_filter_assert( false !== strpos( $archive, 'velotok_filter[' ), 'В архиве каталога нет фильтров по атрибутам контракта.' );
velotok_search_filter_assert( false !== strpos( $archive, 'velotok_catalog_filter_contract_attributes' ), 'Фильтры архива должны строиться из контракта.' );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок поиска и фильтров по атрибутам контракта не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: поиск и фильтры каталога учитывают атрибуты контракта (TESTEB-58).' . PHP_EOL;
exit( 0 );
