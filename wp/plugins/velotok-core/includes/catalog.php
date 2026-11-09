<?php
/**
 * Product catalog stub contract.
 *
 * This is the single access point for the electric bicycle catalog shown by
 * the storefront. The current iteration deliberately uses fixed in-memory
 * data: it does not read environment variables, files, the database or remote
 * services.
 *
 * The catalog provides the four storefront categories and a fixed set of demo
 * electric bicycles with the attributes required by the product card contract
 * (model, price and characteristics). WooCommerce synchronization, inventory
 * and real product feeds are out of scope for this iteration.
 *
 * Spec: ecommerce-store (requirement TESTEB-150).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

/**
 * Return the product catalog contract.
 *
 * The contract documents:
 * - the spec identifier, the explicit `mocked` marker and the stub source;
 * - the catalog categories and their display labels;
 * - the fields every catalog product must expose;
 * - a fixed set of mocked electric bicycles used by the storefront and tests;
 * - error codes for unknown products and categories;
 * - the open questions that block a real WooCommerce catalog integration.
 *
 * @return array<string,mixed>
 */
function velotok_core_catalog_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$categories = array(
		'city'     => array(
			'slug'        => 'city',
			'name'        => 'Городские',
			'label'       => 'Городские',
			'description' => 'Для ежедневных поездок по городу, работы и вечерних маршрутов.',
			'mocked'      => true,
		),
		'mountain' => array(
			'slug'        => 'mountain',
			'name'        => 'Горные',
			'label'       => 'Горные',
			'description' => 'Для грунта, лесных троп и подъёмов.',
			'mocked'      => true,
		),
		'cargo'    => array(
			'slug'        => 'cargo',
			'name'        => 'Грузовые',
			'label'       => 'Грузовые',
			'description' => 'Для детей, доставки и перевозки грузов.',
			'mocked'      => true,
		),
		'folding'  => array(
			'slug'        => 'folding',
			'name'        => 'Складные',
			'label'       => 'Складные',
			'description' => 'Для поездов, офиса, лифта и багажника автомобиля.',
			'mocked'      => true,
		),
	);

	$fields = array(
		'slug'              => array(
			'label'    => 'Слаг',
			'type'     => 'string',
			'required' => true,
			'mocked'   => true,
		),
		'name'              => array(
			'label'    => 'Название',
			'type'     => 'string',
			'required' => true,
			'mocked'   => true,
		),
		'price'             => array(
			'label'    => 'Цена',
			'type'     => 'number',
			'required' => true,
			'mocked'   => true,
		),
		'category'          => array(
			'label'    => 'Категория',
			'type'     => 'string',
			'required' => true,
			'mocked'   => true,
		),
		'short_description' => array(
			'label'    => 'Краткое описание',
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
		'description'       => array(
			'label'    => 'Описание',
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
		'tagline'           => array(
			'label'    => 'Слоган',
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
		'range_km'          => array(
			'label'    => 'Запас хода, км',
			'type'     => 'number',
			'required' => false,
			'mocked'   => true,
		),
		'motor_w'           => array(
			'label'    => 'Мотор, Вт',
			'type'     => 'number',
			'required' => false,
			'mocked'   => true,
		),
		'weight_kg'         => array(
			'label'    => 'Вес, кг',
			'type'     => 'number',
			'required' => false,
			'mocked'   => true,
		),
		'wheel'             => array(
			'label'    => 'Колёса',
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
		'badge'             => array(
			'label'    => 'Бейдж',
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
		'image'             => array(
			'label'    => 'Изображение',
			'type'     => 'string',
			'required' => false,
			'mocked'   => true,
		),
	);

	$products = array(
		array(
			'slug'              => 'riviera',
			'name'              => 'Ривьера',
			'price'             => 62900,
			'old_price'         => null,
			'category'          => 'city',
			'short_description' => 'Лёгкая низкая рама, 90 км запаса хода и спокойный характер — для ежедневных поездок по городу.',
			'description'       => 'Городской электровелосипед «Ривьера» кремово-оливкового цвета с низкой рамой и кожаным седлом.',
			'tagline'           => 'Лёгкая низкая рама для ежедневных поездок по городу',
			'range_km'          => 90,
			'motor_w'           => 250,
			'weight_kg'         => 23,
			'wheel'             => '28″',
			'badge'             => 'Хит',
			'image'             => 'model-city-classic-1-web.jpg',
			'product_type'      => 'electric_bicycle',
			'mocked'            => true,
		),
		array(
			'slug'              => 'street-7',
			'name'              => 'Стрит 7',
			'price'             => 74900,
			'old_price'         => null,
			'category'          => 'city',
			'short_description' => 'Более быстрый и жёсткий вариант для асфальта: 110 км хода и посадка для активной езды.',
			'description'       => 'Городской электровелосипед «Стрит 7» тёмно-бирюзового цвета с узкими шинами и багажником.',
			'tagline'           => 'Быстрый и жёсткий вариант для асфальта',
			'range_km'          => 110,
			'motor_w'           => 350,
			'weight_kg'         => 21,
			'wheel'             => '700C',
			'badge'             => 'Новинка',
			'image'             => 'model-city-sport-1-web.jpg',
			'product_type'      => 'electric_bicycle',
			'mocked'            => true,
		),
		array(
			'slug'              => 'hrebet-pro',
			'name'              => 'Хребет Pro',
			'price'             => 149900,
			'old_price'         => null,
			'category'          => 'mountain',
			'short_description' => 'Полная подвеска и мотор 750 Вт — если в маршруте есть серьёзные подъёмы и спуски.',
			'description'       => 'Горный электровелосипед «Хребет Pro» с полной подвеской, чёрной рамой и оранжевыми акцентами.',
			'tagline'           => 'Полная подвеска для серьёзных подъёмов и спусков',
			'range_km'          => 120,
			'motor_w'           => 750,
			'weight_kg'         => null,
			'wheel'             => '29″',
			'badge'             => 'Для гор',
			'image'             => 'model-mountain-pro-1-web.jpg',
			'product_type'      => 'electric_bicycle',
			'mocked'            => true,
		),
		array(
			'slug'              => 'hrebet-trail',
			'name'              => 'Хребет Trail',
			'price'             => 98900,
			'old_price'         => null,
			'category'          => 'mountain',
			'short_description' => 'Хардтейл для леса и грунта: меньше веса, простая обслуживаемость и уверенный ход.',
			'description'       => 'Горный электровелосипед «Хребет Trail» с зелёной рамой и широкими грунтовыми шинами.',
			'tagline'           => 'Хардтейл для леса и грунта',
			'range_km'          => 95,
			'motor_w'           => 500,
			'weight_kg'         => null,
			'wheel'             => '27,5″',
			'badge'             => 'Выбор механиков',
			'image'             => 'model-mountain-hardtail-1-web.jpg',
			'product_type'      => 'electric_bicycle',
			'mocked'            => true,
		),
		array(
			'slug'              => 'family',
			'name'              => 'Семейный',
			'price'             => 132900,
			'old_price'         => null,
			'category'          => 'cargo',
			'short_description' => 'Удлинённая рама и бокс на 80 кг: дети, продукты и всё, что нужно на даче.',
			'description'       => 'Грузовой электровелосипед «Семейный» с деревянным боксом на удлинённой задней раме.',
			'tagline'           => 'Удлинённая рама и бокс на 80 кг',
			'range_km'          => 80,
			'motor_w'           => 750,
			'weight_kg'         => null,
			'wheel'             => '20″ / 26″',
			'badge'             => 'Для семьи',
			'image'             => 'model-cargo-family-1-web.jpg',
			'product_type'      => 'electric_bicycle',
			'mocked'            => true,
		),
		array(
			'slug'              => 'courier',
			'name'              => 'Курьер',
			'price'             => 119900,
			'old_price'         => null,
			'category'          => 'cargo',
			'short_description' => 'Рама под ежедневные нагрузки: передний и задний багажник, груз до 120 кг.',
			'description'       => 'Грузовой электровелосипед «Курьер» с передним багажником и широкими шинами.',
			'tagline'           => 'Рама под ежедневные нагрузки',
			'range_km'          => 100,
			'motor_w'           => 500,
			'weight_kg'         => null,
			'wheel'             => '20″',
			'badge'             => 'Для работы',
			'image'             => 'model-cargo-courier-1-web.jpg',
			'product_type'      => 'electric_bicycle',
			'mocked'            => true,
		),
		array(
			'slug'              => 'sklad-20',
			'name'              => 'Складень 20',
			'price'             => 68900,
			'old_price'         => null,
			'category'          => 'folding',
			'short_description' => 'Складывается за полминуты и помещается в багажник — для поездов, офиса и лифта.',
			'description'       => 'Складной электровелосипед «Складень 20» с маленькими колёсами и графитовой рамой.',
			'tagline'           => 'Складывается за полминуты и помещается в багажник',
			'range_km'          => 65,
			'motor_w'           => 250,
			'weight_kg'         => 18,
			'wheel'             => '20″',
			'badge'             => 'В багажник',
			'image'             => 'model-folding-compact-1-web.jpg',
			'product_type'      => 'electric_bicycle',
			'mocked'            => true,
		),
	);

	$contract = array(
		'spec'                => 'ecommerce-store',
		'requirement'         => 'TESTEB-150',
		'source'              => 'stub',
		'mocked'              => true,
		'parent_spec'         => 'ecommerce-store',
		'parent_relation'     => 'Каталог электровелосипедов реализует требование родительской спецификации ecommerce-store без реальной синхронизации с WooCommerce.',
		'catalog_type'        => 'product_catalog',
		'product_type'        => 'electric_bicycle',
		'currency'            => 'RUB',
		'required_fields'     => array( 'slug', 'name', 'price', 'category' ),
		'optional_fields'     => array( 'old_price', 'short_description', 'description', 'tagline', 'range_km', 'motor_w', 'weight_kg', 'wheel', 'badge', 'image', 'product_type' ),
		'fields'              => $fields,
		'attributes'          => $fields,
		'required_attributes' => array( 'slug', 'name', 'price', 'category' ),
		'categories'          => $categories,
		'required_categories' => array_keys( $categories ),
		'products'            => $products,
		'product_count'       => count( $products ),
		'default_sort'        => 'menu_order',
		'product_not_found'   => 'CATALOG_PRODUCT_NOT_FOUND',
		'category_unknown'    => 'CATALOG_CATEGORY_UNKNOWN',
		'empty_catalog'       => 'CATALOG_EMPTY',
		'future_source'       => 'woocommerce',
		'open_questions'      => array(
			'attributes'    => 'Какие атрибуты товаров каталога (модель, цена, характеристики) обязательны и как они связаны с карточкой товара (clarify-2c698d44).',
			'content'       => 'Какие контентные статьи сопровождают каталог и должны ли они связываться с конкретными моделями (clarify-a2fd2f66).',
			'filters'       => 'Какие фильтры, сортировка и пагинация нужны каталогу после появления реального ассортимента.',
			'inventory'     => 'Как учитывать наличие, складские остатки и актуальность цен при переходе на реальный источник.',
			'source'        => 'Каким образом каталог синхронизируется с WooCommerce или внешней товарной системой.',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer an explicit product catalog name.
 *
 * @return array<string,mixed>
 */
function velotok_core_product_catalog_contract() {
	return velotok_core_catalog_contract();
}

/**
 * Alias kept for callers that use the data contract wording.
 *
 * @return array<string,mixed>
 */
function velotok_core_catalog_data_contract() {
	return velotok_core_catalog_contract();
}

/**
 * Return the catalog categories keyed by slug.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_catalog_categories() {
	$contract = velotok_core_catalog_contract();

	return $contract['categories'];
}

/**
 * Return the required catalog category slugs.
 *
 * @return string[]
 */
function velotok_core_catalog_category_keys() {
	$contract = velotok_core_catalog_contract();

	return $contract['required_categories'];
}

/**
 * Normalize a category slug.
 *
 * @param mixed $category Category slug or name.
 * @return string
 */
function velotok_core_catalog_normalize_key( $category ) {
	if ( ! is_scalar( $category ) ) {
		return '';
	}

	$key = trim( (string) $category );

	if ( function_exists( 'mb_strtolower' ) ) {
		return mb_strtolower( $key, 'UTF-8' );
	}

	return strtolower( $key );
}

/**
 * Return a catalog category definition.
 *
 * @param mixed $category Category slug or name.
 * @return array<string,mixed>|null Null for an unknown category.
 */
function velotok_core_catalog_category( $category ) {
	$key        = velotok_core_catalog_normalize_key( $category );
	$categories = velotok_core_catalog_categories();

	if ( '' !== $key && isset( $categories[ $key ] ) ) {
		return $categories[ $key ];
	}

	foreach ( $categories as $definition ) {
		if ( isset( $definition['name'] ) && velotok_core_catalog_normalize_key( $definition['name'] ) === $key ) {
			return $definition;
		}
	}

	return null;
}

/**
 * Check whether a catalog category exists.
 *
 * @param mixed $category Category slug or name.
 * @return bool
 */
function velotok_core_catalog_has_category( $category ) {
	return null !== velotok_core_catalog_category( $category );
}

/**
 * Return a human readable label for a catalog category.
 *
 * @param mixed $category Category slug or name.
 * @return string Empty string for an unknown category.
 */
function velotok_core_catalog_category_label( $category ) {
	$definition = velotok_core_catalog_category( $category );

	if ( null === $definition ) {
		return '';
	}

	return isset( $definition['label'] ) ? (string) $definition['label'] : (string) $definition['name'];
}

/**
 * Normalize catalog text for case-insensitive search.
 *
 * @param mixed $value Source value.
 * @return string
 */
function velotok_core_catalog_normalize_search_text( $value ) {
	if ( is_array( $value ) || is_object( $value ) ) {
		return '';
	}

	$value = trim( strip_tags( (string) $value ) );
	$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	$value = str_replace( 'ё', 'е', $value );
	$value = preg_replace( '/\s+/u', ' ', $value );

	return trim( (string) $value );
}

/**
 * Return every searchable value of a catalog product.
 *
 * Search reads the product name, descriptions, model, SKU, category and all
 * other scalar attributes, including optional fields added by a future
 * WooCommerce source.
 *
 * @param mixed $product Catalog product.
 * @return string[]
 */
function velotok_core_catalog_product_search_values( $product ) {
	if ( ! is_array( $product ) ) {
		return array();
	}

	$values = array();

	foreach ( $product as $value ) {
		if ( is_scalar( $value ) ) {
			$values[] = (string) $value;
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$values[] = (string) $item;
				} elseif ( is_array( $item ) ) {
					foreach ( $item as $part ) {
						if ( is_scalar( $part ) ) {
							$values[] = (string) $part;
						}
					}
				}
			}
		}
	}

	if ( isset( $product['category'] ) ) {
		$definition = velotok_core_catalog_category( $product['category'] );

		if ( is_array( $definition ) ) {
			foreach ( array( 'slug', 'name', 'label', 'description' ) as $field ) {
				if ( isset( $definition[ $field ] ) && is_scalar( $definition[ $field ] ) ) {
					$values[] = (string) $definition[ $field ];
				}
			}
		}
	}

	return array_values( array_unique( array_filter( $values, 'is_string' ) ) );
}

/**
 * Check whether a catalog product matches a search query.
 *
 * The whole query is matched first, then every whitespace-separated token.
 * Matching is case-insensitive and covers name, description, model, SKU and
 * category.
 *
 * @param mixed  $product Catalog product.
 * @param string $query   Search query.
 * @return bool
 */
function velotok_core_catalog_product_matches_search( $product, $query ) {
	$query = velotok_core_catalog_normalize_search_text( $query );

	if ( '' === $query ) {
		return true;
	}

	$values = velotok_core_catalog_product_search_values( $product );
	$haystack = velotok_core_catalog_normalize_search_text( implode( ' ', $values ) );

	if ( '' === $haystack ) {
		return false;
	}

	if ( false !== strpos( $haystack, $query ) ) {
		return true;
	}

	$tokens = preg_split( '/\s+/u', $query );
	$tokens = is_array( $tokens ) ? array_filter( $tokens ) : array();

	foreach ( $tokens as $token ) {
		if ( false === strpos( $haystack, $token ) ) {
			return false;
		}
	}

	return ! empty( $tokens );
}

/**
 * Return the fixed mocked catalog products.
 *
 * Supports filtering by category plus optional limit, offset and ordering. Only
 * in-memory data is used; WooCommerce, files and remote services are not read.
 *
 * @param string|array $category Category slug or an arguments array.
 * @param array        $args     Optional arguments: category, search, limit, offset, orderby, order.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_catalog_products( $category = '', $args = array() ) {
	$contract = velotok_core_catalog_contract();

	if ( is_array( $category ) ) {
		$args     = $category;
		$category = '';
	}

	if ( ! is_array( $args ) ) {
		$args = array();
	}

	if ( '' === $category && isset( $args['category'] ) ) {
		$category = $args['category'];
	}

	$key      = velotok_core_catalog_normalize_key( $category );
	$products = array();

	if ( '' !== $key && ! velotok_core_catalog_has_category( $key ) ) {
		return array();
	}

	$search = '';

	foreach ( array( 'search', 's', 'query' ) as $search_key ) {
		if ( isset( $args[ $search_key ] ) && is_scalar( $args[ $search_key ] ) ) {
			$search = (string) $args[ $search_key ];
			break;
		}
	}

	foreach ( $contract['products'] as $product ) {
		if ( '' !== $key && ( ! isset( $product['category'] ) || $product['category'] !== $key ) ) {
			continue;
		}

		if ( '' !== trim( $search ) && ! velotok_core_catalog_product_matches_search( $product, $search ) ) {
			continue;
		}

		$products[] = $product;
	}

	$orderby = isset( $args['orderby'] ) ? (string) $args['orderby'] : '';
	$order   = isset( $args['order'] ) && 'desc' === strtolower( (string) $args['order'] ) ? 'desc' : 'asc';

	if ( 'price' === $orderby ) {
		usort(
			$products,
			static function ( $left, $right ) use ( $order ) {
				$result = (float) $left['price'] <=> (float) $right['price'];

				return 'desc' === $order ? -$result : $result;
			}
		);
	} elseif ( 'name' === $orderby ) {
		usort(
			$products,
			static function ( $left, $right ) use ( $order ) {
				$result = strcmp( (string) $left['name'], (string) $right['name'] );

				return 'desc' === $order ? -$result : $result;
			}
		);
	}

	$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
	$limit  = isset( $args['limit'] ) ? max( 0, (int) $args['limit'] ) : 0;

	if ( $offset > 0 || $limit > 0 ) {
		$products = array_slice( $products, $offset, $limit > 0 ? $limit : null );
	}

	return array_values( $products );
}

/**
 * Alias for callers that prefer the get-prefixed name.
 *
 * @param string|array $category Category slug or an arguments array.
 * @param array        $args     Optional arguments.
 * @return array<int,array<string,mixed>>
 */
function velotok_core_get_catalog_products( $category = '', $args = array() ) {
	return velotok_core_catalog_products( $category, $args );
}

/**
 * Find a single catalog product by slug or name.
 *
 * @param mixed $slug Product slug or name.
 * @return array<string,mixed>|null Null when the product is not found.
 */
function velotok_core_catalog_product( $slug ) {
	$needle = velotok_core_catalog_normalize_key( $slug );

	if ( '' === $needle ) {
		return null;
	}

	foreach ( velotok_core_catalog_products() as $product ) {
		$candidates = array();

		foreach ( array( 'slug', 'name' ) as $field ) {
			if ( isset( $product[ $field ] ) && is_scalar( $product[ $field ] ) ) {
				$candidates[] = velotok_core_catalog_normalize_key( $product[ $field ] );
			}
		}

		if ( in_array( $needle, $candidates, true ) ) {
			return $product;
		}
	}

	return null;
}

/**
 * Alias for the single product accessor.
 *
 * @param mixed $slug Product slug or name.
 * @return array<string,mixed>|null
 */
function velotok_core_get_catalog_product( $slug ) {
	return velotok_core_catalog_product( $slug );
}

/**
 * Check whether a product exists in the catalog.
 *
 * @param mixed $slug Product slug or name.
 * @return bool
 */
function velotok_core_catalog_has_product( $slug ) {
	return null !== velotok_core_catalog_product( $slug );
}

/**
 * Count catalog products, optionally filtered by category.
 *
 * @param string|array $category Category slug or an arguments array.
 * @return int
 */
function velotok_core_catalog_count( $category = '' ) {
	return count( velotok_core_catalog_products( $category ) );
}

/**
 * Return the open questions that block a real catalog integration.
 *
 * @return array<string,string>
 */
function velotok_core_catalog_open_questions() {
	$contract = velotok_core_catalog_contract();

	return $contract['open_questions'];
}

if ( ! function_exists( 'catalogContract' ) ) {
	/**
	 * Short global alias for the catalog contract.
	 *
	 * @return array<string,mixed>
	 */
	function catalogContract() {
		return velotok_core_catalog_contract();
	}
}

if ( ! function_exists( 'catalogCategories' ) ) {
	/**
	 * Short global alias for the catalog categories.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function catalogCategories() {
		return velotok_core_catalog_categories();
	}
}

if ( ! function_exists( 'catalogProducts' ) ) {
	/**
	 * Short global alias for the catalog products.
	 *
	 * @param string|array $category Category slug or an arguments array.
	 * @param array        $args     Optional arguments.
	 * @return array<int,array<string,mixed>>
	 */
	function catalogProducts( $category = '', $args = array() ) {
		return velotok_core_catalog_products( $category, $args );
	}
}

if ( ! function_exists( 'catalogProduct' ) ) {
	/**
	 * Short global alias for a single catalog product.
	 *
	 * @param mixed $slug Product slug or name.
	 * @return array<string,mixed>|null
	 */
	function catalogProduct( $slug ) {
		return velotok_core_catalog_product( $slug );
	}
}
