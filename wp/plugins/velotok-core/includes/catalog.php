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

	$product_states = array(
		'publish'    => array(
			'id'      => 'publish',
			'code'    => 'publish',
			'label'   => 'Опубликован',
			'public'  => true,
			'catalog' => true,
		),
		'draft'      => array(
			'id'      => 'draft',
			'code'    => 'draft',
			'label'   => 'Черновик',
			'public'  => false,
			'catalog' => false,
		),
		'pending'    => array(
			'id'      => 'pending',
			'code'    => 'pending',
			'label'   => 'На проверке',
			'public'  => false,
			'catalog' => false,
		),
		'private'    => array(
			'id'      => 'private',
			'code'    => 'private',
			'label'   => 'Приватный',
			'public'  => false,
			'catalog' => false,
		),
		'trash'      => array(
			'id'      => 'trash',
			'code'    => 'trash',
			'label'   => 'Удалён',
			'public'  => false,
			'catalog' => false,
		),
		'future'     => array(
			'id'      => 'future',
			'code'    => 'future',
			'label'   => 'Запланирован',
			'public'  => false,
			'catalog' => false,
		),
		'auto-draft' => array(
			'id'      => 'auto-draft',
			'code'    => 'auto-draft',
			'label'   => 'Авто-черновик',
			'public'  => false,
			'catalog' => false,
		),
	);

	$fields = array(
		'state'             => array(
			'label'    => 'Состояние',
			'type'     => 'string',
			'required' => true,
			'mocked'   => true,
		),
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
			'state'             => 'publish',
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
			'state'             => 'publish',
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
			'state'             => 'publish',
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
			'state'             => 'publish',
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
			'state'             => 'publish',
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
			'state'             => 'publish',
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
			'state'             => 'publish',
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
		'required_fields'     => array( 'state', 'slug', 'name', 'price', 'category' ),
		'optional_fields'     => array( 'old_price', 'short_description', 'description', 'tagline', 'range_km', 'motor_w', 'weight_kg', 'wheel', 'badge', 'image', 'product_type' ),
		'fields'              => $fields,
		'attributes'          => $fields,
		'required_attributes' => array( 'state', 'slug', 'name', 'price', 'category' ),
		'product_states'      => $product_states,
		'product_state_model' => $product_states,
		'product_state_keys'  => array_keys( $product_states ),
		'product_public_states' => array( 'publish' ),
		'default_product_state' => 'publish',
		'product_state_requirement' => 'TESTEB-16',
		'validation_requirement' => 'TESTEB-17',
		'card_requirement'    => 'TESTEB-18',
		'card'                => array(
			'requirement'         => 'TESTEB-18',
			'required_attributes' => array( 'photo', 'name', 'description', 'price' ),
			'fields'              => array( 'name', 'price', 'description', 'category', 'image', 'badge', 'range_km', 'motor_w', 'weight_kg', 'wheel', 'model', 'sku', 'attributes' ),
		),
		'validation'          => array(
			'requirement'            => 'TESTEB-17',
			'server_side'            => true,
			'required_attributes'    => array( 'state', 'slug', 'name', 'price', 'category' ),
			'fields'                 => $fields,
			'checks_state_model'     => true,
			'checks_category_directory' => true,
			'error_codes'            => array(
				'required' => 'CATALOG_ATTRIBUTE_REQUIRED',
				'type'     => 'CATALOG_ATTRIBUTE_INVALID_TYPE',
				'state'    => 'CATALOG_ATTRIBUTE_INVALID_STATE',
				'category' => 'CATALOG_ATTRIBUTE_UNKNOWN_CATEGORY',
				'price'    => 'CATALOG_ATTRIBUTE_INVALID_PRICE',
			),
		),
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
 * Return the fixed catalog product state model.
 *
 * Every catalog product must be in one of these states. Only the public
 * `publish` state may be shown on the storefront.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_catalog_product_state_model() {
	$contract = velotok_core_catalog_contract();

	return $contract['product_states'];
}

/**
 * Return the canonical catalog product state identifiers.
 *
 * @return string[]
 */
function velotok_core_catalog_product_states() {
	$contract = velotok_core_catalog_contract();

	return $contract['product_state_keys'];
}

/**
 * Normalize a catalog product state.
 *
 * Unknown states are returned unchanged so validation can reject them; they are
 * never silently converted to `publish`.
 *
 * @param mixed $state Product state.
 * @return string
 */
function velotok_core_catalog_normalize_product_state( $state ) {
	if ( ! is_scalar( $state ) ) {
		return '';
	}

	$key = trim( (string) $state );
	$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $key, 'UTF-8' ) : strtolower( $key );

	$map = array(
		'publish'     => 'publish',
		'published'   => 'publish',
		'public'      => 'publish',
		'live'        => 'publish',
		'active'      => 'publish',
		'опубликован' => 'publish',
		'draft'       => 'draft',
		'unpublished' => 'draft',
		'черновик'    => 'draft',
		'pending'     => 'pending',
		'review'      => 'pending',
		'на проверке' => 'pending',
		'private'     => 'private',
		'приватный'   => 'private',
		'trash'       => 'trash',
		'deleted'     => 'trash',
		'удалён'      => 'trash',
		'удален'      => 'trash',
		'future'      => 'future',
		'scheduled'   => 'future',
		'запланирован' => 'future',
		'auto-draft'  => 'auto-draft',
		'auto_draft'  => 'auto-draft',
		'авто-черновик' => 'auto-draft',
	);

	return isset( $map[ $key ] ) ? $map[ $key ] : $key;
}

/**
 * Check whether a state belongs to the fixed product state model.
 *
 * @param mixed $state Product state.
 * @return bool
 */
function velotok_core_catalog_product_state_is_valid( $state ) {
	$model = velotok_core_catalog_product_state_model();
	$key   = velotok_core_catalog_normalize_product_state( $state );

	return '' !== $key && isset( $model[ $key ] );
}

/**
 * Return a catalog product state, or an empty string when it is outside model.
 *
 * @param mixed $product Catalog product.
 * @return string
 */
function velotok_core_catalog_product_state( $product ) {
	if ( ! is_array( $product ) ) {
		return '';
	}

	$state = '';

	foreach ( array( 'state', 'status' ) as $field ) {
		if ( isset( $product[ $field ] ) && is_scalar( $product[ $field ] ) && '' !== trim( (string) $product[ $field ] ) ) {
			$state = velotok_core_catalog_normalize_product_state( $product[ $field ] );
			break;
		}
	}

	return velotok_core_catalog_product_state_is_valid( $state ) ? $state : '';
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
 * Return the declared type of a catalog contract field.
 *
 * @param mixed $field Field key.
 * @return string Empty string for an unknown field.
 */
function velotok_core_catalog_field_type( $field ) {
	$contract = velotok_core_catalog_contract();
	$key      = velotok_core_catalog_normalize_key( $field );

	if ( '' === $key || ! isset( $contract['fields'][ $key ] ) ) {
		return '';
	}

	return isset( $contract['fields'][ $key ]['type'] ) ? (string) $contract['fields'][ $key ]['type'] : '';
}

/**
 * Check whether a value is a catalog number.
 *
 * Integers, finite floats and numeric strings (with a comma as decimal
 * separator) are accepted; arrays, objects, booleans and empty strings are not.
 *
 * @param mixed $value Value.
 * @return bool
 */
function velotok_core_catalog_number_is_valid( $value ) {
	if ( is_int( $value ) || ( is_float( $value ) && is_finite( $value ) ) ) {
		return true;
	}

	if ( ! is_string( $value ) ) {
		return false;
	}

	$value = trim( str_replace( ',', '.', $value ) );

	return '' !== $value && is_numeric( $value );
}

/**
 * Check whether a value is a catalog string.
 *
 * The contract declares `string` fields, so only actual strings (or integer
 * identifiers) are accepted; arrays, objects and booleans are rejected.
 *
 * @param mixed $value Value.
 * @return bool
 */
function velotok_core_catalog_string_is_valid( $value ) {
	return is_string( $value ) || is_int( $value );
}

/**
 * Build a per-attribute catalog validation result.
 *
 * @param string      $field    Field key.
 * @param string      $type     Declared contract type.
 * @param bool        $required Whether the field is required.
 * @param mixed       $value    Original value.
 * @param bool        $valid    Whether the value is valid.
 * @param string|null $code     Error code, if any.
 * @return array<string,mixed>
 */
function velotok_core_catalog_attribute_result( $field, $type, $required, $value, $valid, $code = null ) {
	return array(
		'field'    => $field,
		'type'     => $type,
		'required' => (bool) $required,
		'value'    => $value,
		'valid'    => (bool) $valid,
		'code'     => $valid ? null : $code,
		'error'    => $valid ? null : $code,
	);
}

/**
 * Validate a single catalog product attribute against the contract.
 *
 * Validation is driven by the contract: required fields, declared field types,
 * the fixed state model and the category directory. Optional fields that are
 * absent or empty are valid; optional numeric fields still have to be numeric
 * when a value is present.
 *
 * @param array<string,mixed> $product Product data.
 * @param mixed               $field   Field key.
 * @return array<string,mixed>
 */
function velotok_core_catalog_validate_attribute( $product, $field ) {
	$contract = velotok_core_catalog_contract();
	$key      = velotok_core_catalog_normalize_key( $field );
	$required = in_array( $key, $contract['required_fields'], true );
	$type     = velotok_core_catalog_field_type( $key );

	if ( ! is_array( $product ) ) {
		$product = array();
	}

	$value      = array_key_exists( $key, $product ) ? $product[ $key ] : null;
	$is_missing = null === $value
		|| ( is_string( $value ) && '' === trim( $value ) )
		|| ( is_array( $value ) && empty( $value ) );

	if ( $is_missing ) {
		if ( $required ) {
			return velotok_core_catalog_attribute_result( $key, $type, true, $value, false, $contract['validation']['error_codes']['required'] );
		}

		return velotok_core_catalog_attribute_result( $key, $type, false, $value, true );
	}

	if ( 'state' === $key && ! velotok_core_catalog_product_state_is_valid( $value ) ) {
		return velotok_core_catalog_attribute_result( $key, $type, $required, $value, false, $contract['validation']['error_codes']['state'] );
	}

	if ( 'category' === $key && ! velotok_core_catalog_has_category( $value ) ) {
		return velotok_core_catalog_attribute_result( $key, $type, $required, $value, false, $contract['validation']['error_codes']['category'] );
	}

	if ( 'number' === $type ) {
		if ( ! velotok_core_catalog_number_is_valid( $value ) ) {
			return velotok_core_catalog_attribute_result( $key, $type, $required, $value, false, $contract['validation']['error_codes']['type'] );
		}

		if ( 'price' === $key && (float) str_replace( ',', '.', (string) $value ) < 0 ) {
			return velotok_core_catalog_attribute_result( $key, $type, $required, $value, false, $contract['validation']['error_codes']['price'] );
		}
	} elseif ( 'string' === $type && ! velotok_core_catalog_string_is_valid( $value ) ) {
		return velotok_core_catalog_attribute_result( $key, $type, $required, $value, false, $contract['validation']['error_codes']['type'] );
	}

	return velotok_core_catalog_attribute_result( $key, $type, $required, $value, true );
}

/**
 * Validate a catalog product against the catalog contract.
 *
 * Every required field from the contract is checked for presence and type, the
 * state must belong to the fixed state model and the category must exist in the
 * category directory. The result exposes per-attribute results, missing fields,
 * invalid fields and error codes.
 *
 * @param array<string,mixed> $product Product data.
 * @return array<string,mixed>
 */
function velotok_core_catalog_validate_product( $product ) {
	$contract = velotok_core_catalog_contract();
	$required = $contract['required_fields'];
	$fields   = array_keys( $contract['fields'] );
	$all      = array_values( array_unique( array_merge( $fields, $required ) ) );

	if ( ! is_array( $product ) ) {
		$product = array();
	}

	$attributes = array();
	$errors     = array();
	$missing    = array();
	$invalid    = array();

	foreach ( $all as $field ) {
		if ( ! in_array( $field, $required, true ) && ! array_key_exists( $field, $product ) ) {
			continue;
		}

		$result                  = velotok_core_catalog_validate_attribute( $product, $field );
		$attributes[ $field ]    = $result;

		if ( ! empty( $result['valid'] ) ) {
			continue;
		}

		$code             = (string) $result['code'];
		$errors[ $field ] = $code;

		if ( $contract['validation']['error_codes']['required'] === $code ) {
			$missing[] = $field;
		} else {
			$invalid[] = $field;
		}
	}

	$error_codes = array_values( $errors );

	return array(
		'valid'              => array() === $errors,
		'is_valid'           => array() === $errors,
		'ok'                 => array() === $errors,
		'spec'               => $contract['spec'],
		'requirement'        => 'TESTEB-17',
		'server_side'        => true,
		'product'            => $product,
		'slug'               => isset( $product['slug'] ) && is_scalar( $product['slug'] ) ? (string) $product['slug'] : '',
		'name'               => isset( $product['name'] ) && is_scalar( $product['name'] ) ? (string) $product['name'] : '',
		'state'              => velotok_core_catalog_product_state( $product ),
		'category'           => isset( $product['category'] ) && is_scalar( $product['category'] ) ? velotok_core_catalog_normalize_key( $product['category'] ) : '',
		'required_attributes' => $required,
		'attributes'         => $attributes,
		'errors'             => $error_codes,
		'error_codes'        => $error_codes,
		'codes'              => $error_codes,
		'error'              => $error_codes ? $error_codes[0] : null,
		'errors_by_attribute' => $errors,
		'missing'            => array_values( $missing ),
		'missing_attributes' => array_values( $missing ),
		'invalid'            => array_values( $invalid ),
		'invalid_attributes' => array_values( $invalid ),
	);
}

/**
 * Alias for full catalog product validation.
 *
 * @param array<string,mixed> $product Product data.
 * @return array<string,mixed>
 */
function velotok_core_catalog_validate_attributes( $product ) {
	return velotok_core_catalog_validate_product( $product );
}

/**
 * Check whether a catalog product is valid by contract.
 *
 * @param array<string,mixed> $product Product data.
 * @return bool
 */
function velotok_core_catalog_product_is_valid( $product ) {
	$result = velotok_core_catalog_validate_product( $product );

	return (bool) $result['valid'];
}

/**
 * Alias for the catalog product validity check.
 *
 * @param array<string,mixed> $product Product data.
 * @return bool
 */
function velotok_core_is_valid_catalog_product( $product ) {
	return velotok_core_catalog_product_is_valid( $product );
}

/**
 * Return the validation contract for catalog product attributes.
 *
 * @return array<string,mixed>
 */
function velotok_core_catalog_validation_contract() {
	$contract = velotok_core_catalog_contract();

	return $contract['validation'];
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

	$validate = empty( $args['include_invalid'] );

	foreach ( $contract['products'] as $product ) {
		if ( '' !== $key && ( ! isset( $product['category'] ) || $product['category'] !== $key ) ) {
			continue;
		}

		if ( '' !== trim( $search ) && ! velotok_core_catalog_product_matches_search( $product, $search ) ) {
			continue;
		}

		if ( $validate && ! velotok_core_catalog_product_is_valid( $product ) ) {
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
 * Escape a value for the catalog card HTML.
 *
 * The helper keeps the card renderer usable in isolated tests where the
 * WordPress escaping functions are not loaded.
 *
 * @param mixed $value Source value.
 * @return string
 */
function velotok_core_catalog_card_escape( $value ) {
	if ( function_exists( 'esc_html' ) ) {
		return (string) esc_html( (string) $value );
	}

	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

/**
 * Read a scalar value from a catalog product by candidate keys.
 *
 * @param array<string,mixed> $product Product data.
 * @param string[]            $keys    Candidate keys.
 * @param mixed               $default Default value.
 * @return mixed
 */
function velotok_core_catalog_card_value( $product, $keys, $default = '' ) {
	if ( ! is_array( $product ) ) {
		return $default;
	}

	foreach ( $keys as $key ) {
		if ( isset( $product[ $key ] ) && is_scalar( $product[ $key ] ) && '' !== trim( (string) $product[ $key ] ) ) {
			return $product[ $key ];
		}
	}

	return $default;
}

/**
 * Format a catalog product price for the card.
 *
 * @param mixed $price Price value.
 * @return string
 */
function velotok_core_catalog_card_price( $price ) {
	if ( ! is_numeric( $price ) ) {
		return '';
	}

	$number = (float) $price;

	if ( function_exists( 'number_format_i18n' ) ) {
		return (string) number_format_i18n( $number, 0 );
	}

	return number_format( $number, 0, '.', ' ' );
}

/**
 * Build the HTML image for a catalog product card.
 *
 * @param array<string,mixed> $product Product data.
 * @param string              $alt     Alternative text.
 * @return string
 */
function velotok_core_catalog_card_image_html( $product, $alt ) {
	$image = velotok_core_catalog_card_value( $product, array( 'image_html', 'photo_html' ), '' );

	if ( '' !== $image ) {
		return (string) $image;
	}

	$image = velotok_core_catalog_card_value( $product, array( 'image', 'photo', 'image_url' ), '' );

	if ( '' === $image ) {
		return '';
	}

	if ( false !== strpos( (string) $image, '<img' ) ) {
		return (string) $image;
	}

	$url = (string) $image;

	if ( function_exists( 'get_template_directory_uri' ) && false === strpos( $url, '://' ) ) {
		$url = get_template_directory_uri() . '/assets/img/' . ltrim( $url, '/' );
	}

	return sprintf(
		'<img src="%1$s" width="800" height="600" alt="%2$s" loading="lazy" decoding="async" class="model-photo">',
		velotok_core_catalog_card_escape( $url ),
		velotok_core_catalog_card_escape( $alt )
	);
}

/**
 * Return the label/value rows that a catalog product card must show.
 *
 * @param array<string,mixed> $product Product data.
 * @return array<int,array{0:string,1:string}>
 */
function velotok_core_catalog_card_specs( $product ) {
	if ( ! is_array( $product ) ) {
		return array();
	}

	$rows = array();
	$seen = array();

	$add = static function ( $label, $value ) use ( &$rows, &$seen ) {
		$label = trim( (string) $label );
		$value = trim( (string) $value );

		if ( '' === $label || '' === $value ) {
			return;
		}

		$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label, 'UTF-8' ) : strtolower( $label );

		if ( isset( $seen[ $key ] ) ) {
			return;
		}

		$seen[ $key ] = true;
		$rows[]       = array( $label, $value );
	};

	$range  = velotok_core_catalog_card_value( $product, array( 'range_km', 'range' ), '' );
	$motor  = velotok_core_catalog_card_value( $product, array( 'motor_w', 'motor' ), '' );
	$weight = velotok_core_catalog_card_value( $product, array( 'weight_kg', 'weight' ), '' );
	$wheel  = velotok_core_catalog_card_value( $product, array( 'wheel' ), '' );
	$model  = velotok_core_catalog_card_value( $product, array( 'model', '_model', '_velotok_model' ), '' );
	$sku    = velotok_core_catalog_card_value( $product, array( 'sku', '_sku' ), '' );

	if ( '' !== $range ) {
		$add( 'Запас хода', 'до ' . $range . ' км' );
	}
	if ( '' !== $motor ) {
		$add( 'Мотор', $motor . ' Вт' );
	}
	if ( '' !== $wheel ) {
		$add( 'Колёса', $wheel );
	}
	if ( '' !== $weight ) {
		$add( 'Вес', $weight . ' кг' );
	}
	if ( '' !== $model ) {
		$add( 'Модель', $model );
	}
	if ( '' !== $sku ) {
		$add( 'Артикул', $sku );
	}

	foreach ( array(
		'frame'    => 'Рама',
		'warranty' => 'Гарантия',
		'max_load' => 'Максимальная нагрузка',
	) as $key => $label ) {
		$value = velotok_core_catalog_card_value( $product, array( $key ), '' );

		if ( '' !== $value ) {
			$add( $label, $value );
		}
	}

	if ( isset( $product['attributes'] ) && is_array( $product['attributes'] ) ) {
		foreach ( $product['attributes'] as $key => $attribute ) {
			if ( is_array( $attribute ) ) {
				$label = isset( $attribute['label'] ) ? $attribute['label'] : ( isset( $attribute[0] ) ? $attribute[0] : $key );
				$value = isset( $attribute['value'] ) ? $attribute['value'] : ( isset( $attribute[1] ) ? $attribute[1] : '' );

				if ( is_array( $value ) ) {
					$value = implode( ', ', array_filter( array_map( 'strval', $value ) ) );
				}

				$add( $label, $value );
			} elseif ( is_scalar( $attribute ) ) {
				$add( $key, $attribute );
			}
		}
	}

	return $rows;
}

/**
 * Return the fixed field list of a catalog product card.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_catalog_card_fields() {
	return array(
		'name'        => array(
			'label'    => 'Название',
			'type'     => 'string',
			'required' => true,
		),
		'price'       => array(
			'label'    => 'Цена',
			'type'     => 'number',
			'required' => true,
		),
		'description' => array(
			'label'    => 'Описание',
			'type'     => 'string',
			'required' => true,
		),
		'category'    => array(
			'label'    => 'Категория',
			'type'     => 'string',
			'required' => false,
		),
		'image'       => array(
			'label'    => 'Фото',
			'type'     => 'string',
			'required' => true,
		),
		'badge'       => array(
			'label'    => 'Бейдж',
			'type'     => 'string',
			'required' => false,
		),
		'range_km'    => array(
			'label'    => 'Запас хода, км',
			'type'     => 'number',
			'required' => false,
		),
		'motor_w'     => array(
			'label'    => 'Мотор, Вт',
			'type'     => 'number',
			'required' => false,
		),
		'weight_kg'   => array(
			'label'    => 'Вес, кг',
			'type'     => 'number',
			'required' => false,
		),
		'wheel'       => array(
			'label'    => 'Колёса',
			'type'     => 'string',
			'required' => false,
		),
		'model'       => array(
			'label'    => 'Модель',
			'type'     => 'string',
			'required' => false,
		),
		'sku'         => array(
			'label'    => 'Артикул',
			'type'     => 'string',
			'required' => false,
		),
		'attributes'  => array(
			'label'    => 'Характеристики',
			'type'     => 'array',
			'required' => false,
		),
	);
}

/**
 * Return the product card contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_catalog_card_contract() {
	$contract = velotok_core_catalog_contract();
	$fields   = velotok_core_catalog_card_fields();

	return array(
		'spec'                      => $contract['spec'],
		'requirement'               => 'TESTEB-18',
		'source'                    => 'stub',
		'mocked'                    => true,
		'fields'                    => $fields,
		'attributes'                => $fields,
		'required_attributes'       => array( 'photo', 'name', 'description', 'price' ),
		'card_mandatory_attributes' => array( 'photo', 'name', 'description', 'price' ),
		'optional_attributes'       => array( 'category', 'badge', 'range_km', 'motor_w', 'weight_kg', 'wheel', 'model', 'sku', 'attributes' ),
		'missing_product'           => 'empty_string',
		'future_source'             => 'woocommerce',
	);
}

/**
 * Alias for the product card field list.
 *
 * @return array<string,array<string,mixed>>
 */
function velotok_core_catalog_product_card_fields() {
	return velotok_core_catalog_card_fields();
}

/**
 * Return the display-ready data of a catalog product card.
 *
 * The card contains every field of the catalog contract, so templates and
 * consumers do not have to read the product stub directly. The accessor
 * accepts a product slug/name or an already resolved catalog product array.
 *
 * @param mixed $product Product slug, name or catalog product data.
 * @return array<string,mixed>|null Null when the product is not found.
 */
function velotok_core_catalog_product_card( $product = null ) {
	if ( is_string( $product ) || is_int( $product ) ) {
		$product = velotok_core_catalog_product( $product );
	}

	if ( ! is_array( $product ) || empty( $product ) ) {
		return null;
	}

	$contract = velotok_core_catalog_contract();

	$slug     = velotok_core_catalog_card_value( $product, array( 'slug' ), '' );
	$name     = velotok_core_catalog_card_value( $product, array( 'name', 'title', 'product_name' ), '' );
	$category = velotok_core_catalog_card_value( $product, array( 'category', 'category_slug' ), '' );
	$category = velotok_core_catalog_normalize_key( $category );
	$label    = '' !== $category ? velotok_core_catalog_category_label( $category ) : '';
	$price    = velotok_core_catalog_card_value( $product, array( 'price', 'regular_price' ), '' );
	$short    = velotok_core_catalog_card_value( $product, array( 'short_description', 'summary' ), '' );
	$long     = velotok_core_catalog_card_value( $product, array( 'description' ), '' );
	$tagline  = velotok_core_catalog_card_value( $product, array( 'tagline' ), '' );
	$image    = velotok_core_catalog_card_value( $product, array( 'image', 'photo', 'image_url' ), '' );
	$state    = velotok_core_catalog_product_state( $product );
	$formatted_price = velotok_core_catalog_card_price( $price );
	$price_html = '' !== $formatted_price ? $formatted_price . ' ₽' : '';
	$description = '' !== $long ? $long : ( '' !== $short ? $short : $tagline );
	$specs       = velotok_core_catalog_card_specs( $product );
	$spec_rows   = array();

	$attribute_values = array();

	foreach ( $specs as $spec ) {
		$attribute_values[ $spec[0] ] = $spec[1];
		$spec_rows[]                  = array(
			'label' => $spec[0],
			'value' => $spec[1],
		);
	}

	return array(
		'requirement'       => 'TESTEB-18',
		'spec'              => $contract['spec'],
		'valid'             => velotok_core_catalog_product_is_valid( $product ),
		'is_valid'          => velotok_core_catalog_product_is_valid( $product ),
		'slug'              => (string) $slug,
		'id'                => (string) $slug,
		'name'              => (string) $name,
		'title'             => (string) $name,
		'price'             => $price,
		'price_html'        => $price_html,
		'currency'          => $contract['currency'],
		'description'       => (string) $description,
		'full_description'  => (string) $long,
		'short_description' => (string) $short,
		'tagline'           => (string) $tagline,
		'category'          => (string) $category,
		'category_slug'     => (string) $category,
		'category_label'    => (string) $label,
		'state'             => (string) $state,
		'image'             => (string) $image,
		'photo'             => (string) $image,
		'image_html'        => velotok_core_catalog_card_image_html( $product, (string) $name ),
		'photo_html'        => velotok_core_catalog_card_image_html( $product, (string) $name ),
		'url'               => (string) velotok_core_catalog_card_value( $product, array( 'url', 'permalink' ), '' ),
		'badge'             => (string) velotok_core_catalog_card_value( $product, array( 'badge' ), '' ),
		'range_km'          => velotok_core_catalog_card_value( $product, array( 'range_km', 'range' ), '' ),
		'motor_w'           => velotok_core_catalog_card_value( $product, array( 'motor_w', 'motor' ), '' ),
		'weight_kg'         => velotok_core_catalog_card_value( $product, array( 'weight_kg', 'weight' ), '' ),
		'wheel'             => velotok_core_catalog_card_value( $product, array( 'wheel' ), '' ),
		'model'             => (string) velotok_core_catalog_card_value( $product, array( 'model', '_model', '_velotok_model' ), '' ),
		'sku'               => (string) velotok_core_catalog_card_value( $product, array( 'sku', '_sku' ), '' ),
		'product_type'      => (string) velotok_core_catalog_card_value( $product, array( 'product_type' ), $contract['product_type'] ),
		'description_html'  => velotok_core_catalog_card_escape( $description ),
		'attributes'        => $specs,
		'specs'             => $specs,
		'spec_rows'         => $spec_rows,
		'attribute_rows'    => $specs,
		'attribute_values'  => $attribute_values,
		'fields'            => $product,
		'product'           => $product,
	);
}

/**
 * Alias for the catalog product card data accessor.
 *
 * @param mixed $product Product slug, name or catalog product data.
 * @return array<string,mixed>|null
 */
function velotok_core_catalog_card( $product = null ) {
	return velotok_core_catalog_product_card( $product );
}

/**
 * Alias for callers that use the card data wording.
 *
 * @param mixed $product Product slug, name or catalog product data.
 * @return array<string,mixed>|null
 */
function velotok_core_catalog_card_data( $product = null ) {
	return velotok_core_catalog_product_card( $product );
}

/**
 * Alias for callers that prefer the get-prefixed name.
 *
 * @param mixed $product Product slug, name or catalog product data.
 * @return array<string,mixed>|null
 */
function velotok_core_get_catalog_product_card( $product = null ) {
	return velotok_core_catalog_product_card( $product );
}

/**
 * Render a catalog product card as escaped HTML.
 *
 * @param mixed $product Product slug, name or catalog product data.
 * @return string Empty string when the product is not found.
 */
function velotok_core_catalog_render_product_card( $product = null ) {
	$card = velotok_core_catalog_product_card( $product );

	if ( null === $card ) {
		return '';
	}

	$html = '<article class="model-card product-card catalog-product-card" data-slug="' . velotok_core_catalog_card_escape( $card['slug'] ) . '"';

	if ( '' !== $card['sku'] ) {
		$html .= ' data-sku="' . velotok_core_catalog_card_escape( $card['sku'] ) . '"';
	}

	$html .= '>';

	if ( '' !== $card['image_html'] ) {
		$html .= '<div class="model-media">' . $card['image_html'] . '</div>';
	}

	$html .= '<div class="model-body">';

	if ( '' !== $card['category_label'] ) {
		$html .= '<span class="model-category">' . velotok_core_catalog_card_escape( $card['category_label'] ) . '</span>';
	}

	$html .= '<h3><a href="' . velotok_core_catalog_card_escape( $card['url'] ) . '">' . velotok_core_catalog_card_escape( $card['name'] ) . '</a></h3>';

	if ( '' !== $card['description'] ) {
		$html .= '<p class="model-description">' . velotok_core_catalog_card_escape( $card['description'] ) . '</p>';
	}

	if ( '' !== $card['sku'] ) {
		$html .= '<p class="model-sku">Артикул: <span>' . velotok_core_catalog_card_escape( $card['sku'] ) . '</span></p>';
	}

	if ( ! empty( $card['specs'] ) ) {
		$html .= '<ul class="specs">';

		foreach ( $card['specs'] as $spec ) {
			$html .= '<li><span>' . velotok_core_catalog_card_escape( $spec[0] ) . '</span><strong>' . velotok_core_catalog_card_escape( $spec[1] ) . '</strong></li>';
		}

		$html .= '</ul>';
	}

	if ( '' !== $card['price_html'] ) {
		$html .= '<div class="price">' . velotok_core_catalog_card_escape( $card['price_html'] ) . '</div>';
	}

	$html .= '</div></article>';

	return $html;
}

/**
 * Alias for callers that use the card HTML wording.
 *
 * @param mixed $product Product slug, name or catalog product data.
 * @return string
 */
function velotok_core_catalog_card_html( $product = null ) {
	return velotok_core_catalog_render_product_card( $product );
}

/**
 * Alias for the catalog card HTML renderer.
 *
 * @param mixed $product Product slug, name or catalog product data.
 * @return string
 */
function velotok_core_catalog_render_card( $product = null ) {
	return velotok_core_catalog_render_product_card( $product );
}

/**
 * Alias for the catalog card HTML renderer.
 *
 * @param mixed $product Product slug, name or catalog product data.
 * @return string
 */
function velotok_core_render_catalog_product_card( $product = null ) {
	return velotok_core_catalog_render_product_card( $product );
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

if ( ! function_exists( 'catalogProductCard' ) ) {
	/**
	 * Short global alias for catalog product card data.
	 *
	 * @param mixed $product Product slug, name or catalog product data.
	 * @return array<string,mixed>|null
	 */
	function catalogProductCard( $product = null ) {
		return velotok_core_catalog_product_card( $product );
	}
}

if ( ! function_exists( 'catalogCard' ) ) {
	/**
	 * Short global alias for catalog product card data.
	 *
	 * @param mixed $product Product slug, name or catalog product data.
	 * @return array<string,mixed>|null
	 */
	function catalogCard( $product = null ) {
		return velotok_core_catalog_product_card( $product );
	}
}

if ( ! function_exists( 'catalogCardData' ) ) {
	/**
	 * Short global alias for catalog product card data.
	 *
	 * @param mixed $product Product slug, name or catalog product data.
	 * @return array<string,mixed>|null
	 */
	function catalogCardData( $product = null ) {
		return velotok_core_catalog_product_card( $product );
	}
}

if ( ! function_exists( 'catalogRenderProductCard' ) ) {
	/**
	 * Short global alias for the catalog card HTML renderer.
	 *
	 * @param mixed $product Product slug, name or catalog product data.
	 * @return string
	 */
	function catalogRenderProductCard( $product = null ) {
		return velotok_core_catalog_render_product_card( $product );
	}
}

if ( ! function_exists( 'catalogProductCardData' ) ) {
	/**
	 * Short global alias for catalog product card data.
	 *
	 * @param mixed $product Product slug, name or catalog product data.
	 * @return array<string,mixed>|null
	 */
	function catalogProductCardData( $product = null ) {
		return velotok_core_catalog_product_card( $product );
	}
}

if ( ! function_exists( 'catalogCardHtml' ) ) {
	/**
	 * Short global alias for the catalog card HTML renderer.
	 *
	 * @param mixed $product Product slug, name or catalog product data.
	 * @return string
	 */
	function catalogCardHtml( $product = null ) {
		return velotok_core_catalog_render_product_card( $product );
	}
}

if ( ! function_exists( 'catalogCardContract' ) ) {
	/**
	 * Short global alias for the catalog card contract.
	 *
	 * @return array<string,mixed>
	 */
	function catalogCardContract() {
		return velotok_core_catalog_card_contract();
	}
}

if ( ! function_exists( 'catalogCardFields' ) ) {
	/**
	 * Short global alias for the catalog card fields.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function catalogCardFields() {
		return velotok_core_catalog_card_fields();
	}
}

if ( ! function_exists( 'catalogValidateProduct' ) ) {
	/**
	 * Short global alias for catalog product validation.
	 *
	 * @param array<string,mixed> $product Product data.
	 * @return array<string,mixed>
	 */
	function catalogValidateProduct( $product ) {
		return velotok_core_catalog_validate_product( $product );
	}
}

if ( ! function_exists( 'validateCatalogProduct' ) ) {
	/**
	 * Alias for catalog product validation.
	 *
	 * @param array<string,mixed> $product Product data.
	 * @return array<string,mixed>
	 */
	function validateCatalogProduct( $product ) {
		return velotok_core_catalog_validate_product( $product );
	}
}

if ( ! function_exists( 'catalogProductIsValid' ) ) {
	/**
	 * Short global alias for the catalog product validity check.
	 *
	 * @param array<string,mixed> $product Product data.
	 * @return bool
	 */
	function catalogProductIsValid( $product ) {
		return velotok_core_catalog_product_is_valid( $product );
	}
}
