<?php
/**
 * Поиск и фильтры каталога по атрибутам контракта.
 *
 * Набор атрибутов совпадает с контрактом карточки товара темы: название,
 * описание, артикул, категория, слоган, запас хода, мотор, вес, колёса, бейдж,
 * цена и дополнительные атрибуты продавца. Поиск и фильтры не зависят от
 * плагинов-заглушек и работают как с объектами WooCommerce, так и с массивами
 * карточки.
 *
 * Spec: theme (TESTEB-58).
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'velotok_catalog_filter_contract' ) ) {
	/**
	 * Фиксированный набор сортировок каталога.
	 *
	 * Сортировка принимает только значения из этого набора. Неизвестное
	 * значение не доходит до WooCommerce и заменяется сортировкой по умолчанию.
	 *
	 * @return array<string,string>
	 */
	function velotok_catalog_sorting_options() {
		return array(
			'menu_order' => __( 'По умолчанию', 'velotok' ),
			'popularity' => __( 'По популярности', 'velotok' ),
			'rating'     => __( 'По рейтингу', 'velotok' ),
			'date'       => __( 'По новинкам', 'velotok' ),
			'price'      => __( 'Цена: по возрастанию', 'velotok' ),
			'price-desc' => __( 'Цена: по убыванию', 'velotok' ),
		);
	}

	/**
	 * Псевдоним фиксированного набора сортировок для шаблонов.
	 *
	 * @return array<string,string>
	 */
	function velotok_catalog_sorting() {
		return velotok_catalog_sorting_options();
	}

	/**
	 * Допустимые значения orderby.
	 *
	 * @return string[]
	 */
	function velotok_catalog_sort_values() {
		return array_keys( velotok_catalog_sorting_options() );
	}

	/**
	 * Контракт сортировки каталога.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_catalog_sorting_contract() {
		$options = velotok_catalog_sorting_options();

		return array(
			'requirement' => 'TESTEB-59',
			'parameter'   => 'orderby',
			'options'     => $options,
			'values'      => array_keys( $options ),
			'default'     => velotok_catalog_default_orderby(),
		);
	}

	/**
	 * Псевдоним контракта сортировки.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_catalog_sort_contract() {
		return velotok_catalog_sorting_contract();
	}

	/**
	 * Псевдоним фиксированного набора сортировок.
	 *
	 * @return array<string,string>
	 */
	function velotok_catalog_sort_options() {
		return velotok_catalog_sorting_options();
	}

	/**
	 * Псевдоним фиксированного набора сортировок.
	 *
	 * @return array<string,string>
	 */
	function velotok_catalog_orderby_options() {
		return velotok_catalog_sorting_options();
	}

	/**
	 * Допустимые значения параметра orderby.
	 *
	 * @return string[]
	 */
	function velotok_catalog_sorting_keys() {
		return array_keys( velotok_catalog_sorting_options() );
	}

	/**
	 * Псевдоним допустимых значений orderby.
	 *
	 * @return string[]
	 */
	function velotok_catalog_sorting_values() {
		return velotok_catalog_sorting_keys();
	}

	/**
	 * Псевдоним допустимых значений orderby.
	 *
	 * @return string[]
	 */
	function velotok_catalog_allowed_sorting() {
		return velotok_catalog_sorting_keys();
	}

	/**
	 * Сортировка по умолчанию.
	 *
	 * @return string
	 */
	function velotok_catalog_default_orderby() {
		return 'menu_order';
	}

	/**
	 * Приводит orderby к значению из фиксированного набора.
	 *
	 * @param mixed $orderby Исходное значение.
	 * @return string
	 */
	function velotok_catalog_normalize_orderby( $orderby ) {
		if ( is_array( $orderby ) || is_object( $orderby ) ) {
			return velotok_catalog_default_orderby();
		}

		$orderby = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $orderby ) : strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $orderby ) );

		if ( isset( velotok_catalog_sorting_options()[ $orderby ] ) ) {
			return $orderby;
		}

		return velotok_catalog_default_orderby();
	}

	/**
	 * Псевдоним нормализации orderby.
	 *
	 * @param mixed $orderby Исходное значение.
	 * @return string
	 */
	function velotok_catalog_sanitize_orderby( $orderby ) {
		return velotok_catalog_normalize_orderby( $orderby );
	}

	/**
	 * Сортировка из запроса.
	 *
	 * @param mixed $source Источник (по умолчанию $_GET).
	 * @return string
	 */
	function velotok_catalog_request_orderby( $source = null ) {
		if ( null === $source ) {
			$source = isset( $_GET ) && is_array( $_GET ) ? $_GET : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- публичная сортировка каталога.
		}

		if ( ! is_array( $source ) ) {
			return velotok_catalog_default_orderby();
		}

		$contract = velotok_catalog_filter_contract();
		$key      = isset( $contract['sorting_parameter'] ) ? $contract['sorting_parameter'] : 'orderby';

		if ( ! isset( $source[ $key ] ) ) {
			return velotok_catalog_default_orderby();
		}

		return velotok_catalog_normalize_orderby( $source[ $key ] );
	}

	/**
	 * Контракт поиска и фильтров каталога.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_catalog_filter_contract() {
		static $contract = null;

		if ( null !== $contract ) {
			return $contract;
		}

		$attributes = array(
			'name'        => array(
				'label'  => 'Название',
				'type'   => 'string',
				'search' => true,
				'filter' => false,
			),
			'description' => array(
				'label'  => 'Описание',
				'type'   => 'text',
				'search' => true,
				'filter' => false,
			),
			'sku'         => array(
				'label'  => 'Артикул',
				'type'   => 'string',
				'search' => true,
				'filter' => false,
			),
			'category'    => array(
				'label'    => 'Категория',
				'type'     => 'taxonomy',
				'taxonomy' => 'product_cat',
				'search'   => true,
				'filter'   => true,
			),
			'tagline'     => array(
				'label'  => 'Слоган',
				'type'   => 'string',
				'meta'   => '_velotok_tagline',
				'search' => true,
				'filter' => false,
			),
			'range_km'    => array(
				'label'  => 'Запас хода, км',
				'type'   => 'number',
				'meta'   => '_velotok_range_km',
				'search' => true,
				'filter' => true,
				'range'  => true,
			),
			'motor_w'     => array(
				'label'  => 'Мотор, Вт',
				'type'   => 'number',
				'meta'   => '_velotok_motor_w',
				'search' => true,
				'filter' => true,
			),
			'weight_kg'   => array(
				'label'  => 'Вес, кг',
				'type'   => 'number',
				'meta'   => '_velotok_weight_kg',
				'search' => true,
				'filter' => true,
				'range'  => true,
			),
			'wheel'       => array(
				'label'  => 'Колёса',
				'type'   => 'string',
				'meta'   => '_velotok_wheel',
				'search' => true,
				'filter' => true,
			),
			'badge'       => array(
				'label'  => 'Бейдж',
				'type'   => 'string',
				'meta'   => '_velotok_badge',
				'search' => true,
				'filter' => true,
			),
			'price'       => array(
				'label'  => 'Цена',
				'type'   => 'number',
				'meta'   => '_price',
				'search' => true,
				'filter' => true,
				'range'  => true,
			),
			'model'       => array(
				'label'     => 'Модель',
				'type'      => 'string',
				'attribute' => 'Модель',
				'meta'      => '_velotok_model',
				'search'    => true,
				'filter'    => true,
			),
			'frame'       => array(
				'label'     => 'Рама',
				'type'      => 'string',
				'attribute' => 'Рама',
				'search'    => true,
				'filter'    => true,
			),
			'warranty'    => array(
				'label'     => 'Гарантия',
				'type'      => 'string',
				'attribute' => 'Гарантия',
				'search'    => true,
				'filter'    => true,
			),
			'max_load'    => array(
				'label'     => 'Максимальная нагрузка',
				'type'      => 'string',
				'attribute' => 'Максимальная нагрузка',
				'search'    => true,
				'filter'    => true,
			),
		);

		$searchable = array();
		$filterable = array();

		foreach ( $attributes as $key => $attribute ) {
			if ( ! empty( $attribute['search'] ) ) {
				$searchable[] = $key;
			}

			if ( ! empty( $attribute['filter'] ) ) {
				$filterable[] = $key;
			}
		}

		$sorting = velotok_catalog_sorting_options();
		$filters = array();

		foreach ( $attributes as $key => $attribute ) {
			if ( empty( $attribute['filter'] ) ) {
				continue;
			}

			$control = 'text';

			if ( 'taxonomy' === $attribute['type'] ) {
				$control = 'taxonomy';
			} elseif ( 'number' === $attribute['type'] ) {
				$control = ! empty( $attribute['range'] ) ? 'range' : 'number';
			}

			$definition = array(
				'key'              => $key,
				'name'             => $key,
				'label'            => $attribute['label'],
				'type'             => $attribute['type'],
				'control'          => $control,
				'parameter'        => $key,
				'query_parameter'  => 'velotok_filter[' . $key . ']',
				'range'            => ! empty( $attribute['range'] ),
				'multiple'         => in_array( $key, array( 'category', 'wheel', 'badge' ), true ),
				'requirement'      => 'TESTEB-20',
			);

			foreach ( array( 'meta', 'taxonomy' ) as $field ) {
				if ( isset( $attribute[ $field ] ) ) {
					$definition[ $field ] = $attribute[ $field ];
				}
			}

			if ( isset( $attribute['attribute'] ) ) {
				$definition['source_attribute'] = $attribute['attribute'];
			}

			$filters[ $key ] = $definition;
		}

		$contract = array(
			'spec'                 => 'theme',
			'requirement'          => 'TESTEB-58',
			'source'               => 'woocommerce',
			'attributes'           => $attributes,
			'searchable_attributes' => $searchable,
			'filterable_attributes' => $filterable,
			'filterable'           => $filterable,
			'filters'              => $filters,
			'filter_fields'        => $filters,
			'filter_controls'      => $filters,
			'filter_definitions'   => $filters,
			'filter_requirement'   => 'TESTEB-20',
			'search_parameter'     => 's',
			'filter_parameter'     => 'velotok_filter',
			'sorting_parameter'    => 'orderby',
			'sorting'              => $sorting,
			'sorting_options'      => $sorting,
			'sorting_values'       => array_keys( $sorting ),
			'sorting_keys'         => array_keys( $sorting ),
			'allowed_sorting'      => array_keys( $sorting ),
			'sort_values'          => array_keys( $sorting ),
			'default_sort'         => velotok_catalog_default_orderby(),
			'default_orderby'      => velotok_catalog_default_orderby(),
			'sort_requirement'     => 'TESTEB-59',
			'pagination_parameter' => 'paged',
			'pagination_requirement' => 'TESTEB-60',
			'validation_requirement' => 'TESTEB-61',
			'price_validation_requirement' => 'TESTEB-62',
			'validation'           => array(
				'requirement'         => 'TESTEB-61',
				'server_side'         => true,
				'required_attributes' => array( 'photo', 'name', 'description', 'price' ),
				'invalid_price_code'  => 'PRODUCT_PRICE_INVALID',
			),
			'missing_value'        => '',
		);

		return $contract;
	}

	/**
	 * Псевдоним контракта для шаблонов.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_catalog_contract() {
		return velotok_catalog_filter_contract();
	}

	/**
	 * Атрибуты контракта.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function velotok_catalog_filter_contract_attributes() {
		$contract = velotok_catalog_filter_contract();

		return $contract['attributes'];
	}

	/**
	 * Ключи атрибутов, которые участвуют в поиске.
	 *
	 * @return string[]
	 */
	function velotok_catalog_searchable_attributes() {
		$contract = velotok_catalog_filter_contract();

		return $contract['searchable_attributes'];
	}

	/**
	 * Ключи атрибутов, которые участвуют в фильтрах.
	 *
	 * @return string[]
	 */
	function velotok_catalog_filterable_attributes() {
		$contract = velotok_catalog_filter_contract();

		return $contract['filterable_attributes'];
	}

	/**
	 * Определения фильтров, построенные по контракту каталога.
	 *
	 * Каждый фильтр описывает ключ атрибута, подпись, тип, элемент управления
	 * и параметр запроса. Шаблоны и тесты не должны задавать этот список
	 * вручную: единственный источник — контракт.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function velotok_catalog_filter_contract_filters() {
		$contract = velotok_catalog_filter_contract();

		return $contract['filters'];
	}

	/**
	 * Псевдоним определений фильтров.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function velotok_catalog_filter_definitions() {
		return velotok_catalog_filter_contract_filters();
	}

	/**
	 * Строит элементы управления фильтров по контракту.
	 *
	 * Для каждого фильтруемого атрибута возвращается описание контроля и
	 * доступные значения из переданных товаров. Активные значения берутся из
	 * уже разобранных фильтров, поэтому функция пригодна и для шаблона, и для
	 * тестов.
	 *
	 * @param mixed $products Товары для построения доступных значений.
	 * @param mixed $filters  Активные фильтры (по умолчанию из запроса).
	 * @return array<string,array<string,mixed>>
	 */
	function velotok_catalog_filter_controls( $products = array(), $filters = null ) {
		$products = is_array( $products ) ? $products : array();

		if ( null === $filters && ! empty( $products ) && ! isset( $products[0] ) ) {
			$candidates = array_merge( velotok_catalog_filterable_attributes(), array( 'attributes', 'filters', 'filter' ) );

			if ( array_intersect( array_keys( $products ), $candidates ) ) {
				$filters  = $products;
				$products = array();
			}
		}

		$filters  = null === $filters ? velotok_catalog_request_filters() : $filters;
		$filters  = velotok_catalog_sanitize_filters( $filters );
		$controls = array();

		foreach ( velotok_catalog_filter_contract_filters() as $key => $definition ) {
			$control             = $definition;
			$control['options']  = velotok_catalog_filter_options( $key, $products );
			$control['value']    = isset( $filters[ $key ] ) ? $filters[ $key ] : '';

			if ( ! empty( $definition['range'] ) ) {
				$numbers = array();

				foreach ( $control['options'] as $option ) {
					if ( is_numeric( $option ) ) {
						$numbers[] = (float) $option;
					}
				}

				$control['min'] = $numbers ? min( $numbers ) : null;
				$control['max'] = $numbers ? max( $numbers ) : null;
			}

			$controls[ $key ] = $control;
		}

		return $controls;
	}

	/**
	 * Псевдоним элементов управления фильтров.
	 *
	 * @param mixed $products Товары.
	 * @param mixed $filters  Активные фильтры.
	 * @return array<string,array<string,mixed>>
	 */
	function velotok_catalog_filter_fields( $products = array(), $filters = null ) {
		return velotok_catalog_filter_controls( $products, $filters );
	}

	/**
	 * Псевдоним построения фильтров по контракту.
	 *
	 * @param mixed $products Товары.
	 * @param mixed $filters  Активные фильтры.
	 * @return array<string,array<string,mixed>>
	 */
	function velotok_catalog_build_filters( $products = array(), $filters = null ) {
		return velotok_catalog_filter_controls( $products, $filters );
	}

	/**
	 * Экранирует значение для разметки фильтров.
	 *
	 * @param mixed $value Значение.
	 * @return string
	 */
	function velotok_catalog_escape_filter_value( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		return function_exists( 'esc_attr' ) ? esc_attr( (string) $value ) : htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Разметка элементов управления фильтров по контракту.
	 *
	 * @param mixed $products Товары.
	 * @param mixed $filters  Активные фильтры.
	 * @return string
	 */
	function velotok_catalog_render_filter_controls( $products = array(), $filters = null ) {
		$controls = velotok_catalog_filter_controls( $products, $filters );
		$markup   = '';

		foreach ( $controls as $key => $control ) {
			$label = isset( $control['label'] ) ? (string) $control['label'] : (string) $key;
			$value = isset( $control['value'] ) ? $control['value'] : '';

			if ( 'range' === $control['control'] ) {
				$min = is_array( $value ) && isset( $value['min'] ) ? $value['min'] : '';
				$max = is_array( $value ) && isset( $value['max'] ) ? $value['max'] : '';

				$markup .= '<fieldset class="catalog-filter-range" data-filter="' . velotok_catalog_escape_filter_value( $key ) . '">';
				$markup .= '<legend>' . velotok_catalog_escape_filter_value( $label ) . '</legend>';
				$markup .= '<label><span>' . velotok_catalog_escape_filter_value( 'от' ) . '</span><input type="number" step="any" name="velotok_filter[' . velotok_catalog_escape_filter_value( $key ) . '_min]" value="' . velotok_catalog_escape_filter_value( $min ) . '"></label>';
				$markup .= '<label><span>' . velotok_catalog_escape_filter_value( 'до' ) . '</span><input type="number" step="any" name="velotok_filter[' . velotok_catalog_escape_filter_value( $key ) . '_max]" value="' . velotok_catalog_escape_filter_value( $max ) . '"></label>';
				$markup .= '</fieldset>';
				continue;
			}

			$type  = 'number' === $control['type'] ? 'number' : 'text';
			$value = is_array( $value ) ? implode( ', ', $value ) : $value;

			$markup .= '<label class="catalog-filter-field" data-filter="' . velotok_catalog_escape_filter_value( $key ) . '">';
			$markup .= '<span>' . velotok_catalog_escape_filter_value( $label ) . '</span>';
			$markup .= '<input type="' . $type . '" name="velotok_filter[' . velotok_catalog_escape_filter_value( $key ) . ']" value="' . velotok_catalog_escape_filter_value( $value ) . '">';
			$markup .= '</label>';
		}

		return $markup;
	}

	/**
	 * Псевдоним разметки фильтров.
	 *
	 * @param mixed $products Товары.
	 * @param mixed $filters  Активные фильтры.
	 * @return string
	 */
	function velotok_catalog_render_filters( $products = array(), $filters = null ) {
		return velotok_catalog_render_filter_controls( $products, $filters );
	}

	/**
	 * Нормализует текст для поиска и сравнения.
	 *
	 * @param mixed $value Исходное значение.
	 * @return string
	 */
	function velotok_catalog_normalize_text( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $value ) : trim( strip_tags( (string) $value ) );
		$value = trim( $value );

		if ( function_exists( 'mb_strtolower' ) ) {
			$value = mb_strtolower( $value, 'UTF-8' );
		} else {
			$value = strtolower( $value );
		}

		$value = str_replace( 'ё', 'е', $value );
		$value = preg_replace( '/\s+/u', ' ', $value );

		return trim( (string) $value );
	}

	/**
	 * Нормализует ключ фильтра.
	 *
	 * @param mixed $key Исходный ключ.
	 * @return string
	 */
	function velotok_catalog_normalize_key( $key ) {
		if ( ! is_scalar( $key ) ) {
			return '';
		}

		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( (string) $key );
		}

		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
	}

	/**
	 * Санитизирует строковое значение фильтра.
	 *
	 * @param mixed $value Исходное значение.
	 * @return string
	 */
	function velotok_catalog_sanitize_filter_text( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( (string) $value );
		}

		return trim( strip_tags( (string) $value ) );
	}

	/**
	 * ID товара.
	 *
	 * @param mixed $product Товар.
	 * @return int
	 */
	function velotok_catalog_product_id( $product ) {
		if ( is_array( $product ) ) {
			return isset( $product['id'] ) ? (int) $product['id'] : 0;
		}

		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			return (int) $product->get_id();
		}

		return 0;
	}

	/**
	 * Значение мета-поля товара.
	 *
	 * @param mixed  $product Товар.
	 * @param string $meta_key Ключ мета-поля.
	 * @return string
	 */
	function velotok_catalog_product_meta( $product, $meta_key ) {
		$meta_key = (string) $meta_key;

		if ( '' === $meta_key ) {
			return '';
		}

		if ( is_array( $product ) ) {
			return isset( $product[ $meta_key ] ) && is_scalar( $product[ $meta_key ] ) ? (string) $product[ $meta_key ] : '';
		}

		$id = velotok_catalog_product_id( $product );

		if ( $id > 0 && function_exists( 'get_post_meta' ) ) {
			$value = get_post_meta( $id, $meta_key, true );

			return is_scalar( $value ) ? (string) $value : '';
		}

		return '';
	}

	/**
	 * Дополнительные атрибуты товара в виде строк контракта.
	 *
	 * @param mixed $product Товар.
	 * @return array<int,array{key:string,label:string,value:string}>
	 */
	function velotok_catalog_product_attribute_rows( $product ) {
		$rows = array();

		if ( function_exists( 'velotok_product_extra_attributes' ) ) {
			foreach ( (array) velotok_product_extra_attributes( $product ) as $row ) {
				if ( ! isset( $row[0], $row[1] ) ) {
					continue;
				}

				$label = velotok_catalog_normalize_text( $row[0] );
				$value = velotok_catalog_sanitize_filter_text( $row[1] );

				if ( '' === $label || '' === $value ) {
					continue;
				}

				$rows[] = array(
					'key'   => $label,
					'label' => (string) $row[0],
					'value' => $value,
				);
			}
		}

		return $rows;
	}

	/**
	 * Значение дополнительного атрибута по нормализованной подписи или имени.
	 *
	 * @param mixed  $product  Товар.
	 * @param string $needle   Нормализованный ключ.
	 * @param string $fallback Дополнительная подпись.
	 * @return string
	 */
	function velotok_catalog_product_attribute_row( $product, $needle, $fallback = '' ) {
		$needles = array( velotok_catalog_normalize_text( $needle ), velotok_catalog_normalize_text( $fallback ) );
		$needles = array_values( array_filter( $needles ) );

		if ( is_array( $product ) ) {
			foreach ( array( 'attributes', 'specs', 'extra_attributes' ) as $collection_key ) {
				if ( empty( $product[ $collection_key ] ) || ! is_array( $product[ $collection_key ] ) ) {
					continue;
				}

				foreach ( $product[ $collection_key ] as $row ) {
					$row_label = '';
					$row_value = '';

					if ( is_array( $row ) ) {
						$row_label = isset( $row['label'] ) ? $row['label'] : ( isset( $row[0] ) ? $row[0] : '' );
						$row_value = isset( $row['value'] ) ? $row['value'] : ( isset( $row[1] ) ? $row[1] : '' );
					} elseif ( is_object( $row ) ) {
						$row_label = isset( $row->label ) ? $row->label : ( isset( $row->name ) ? $row->name : '' );
						$row_value = isset( $row->value ) ? $row->value : '';
					} else {
						continue;
					}

					if ( in_array( velotok_catalog_normalize_text( $row_label ), $needles, true ) && is_scalar( $row_value ) && '' !== trim( (string) $row_value ) ) {
						return (string) $row_value;
					}
				}
			}
		}

		foreach ( velotok_catalog_product_attribute_rows( $product ) as $row ) {
			if ( in_array( $row['key'], $needles, true ) ) {
				return $row['value'];
			}
		}

		return '';
	}

	/**
	 * Категории товара (слаги и названия).
	 *
	 * @param mixed $product Товар.
	 * @return string[]
	 */
	function velotok_catalog_product_categories( $product ) {
		$categories = array();

		if ( is_array( $product ) ) {
			foreach ( array( 'category', 'category_label', 'categories' ) as $key ) {
				if ( ! isset( $product[ $key ] ) ) {
					continue;
				}

				$value = $product[ $key ];

				if ( is_array( $value ) ) {
					foreach ( $value as $item ) {
						if ( is_scalar( $item ) ) {
							$categories[] = (string) $item;
						} elseif ( is_array( $item ) ) {
							foreach ( array( 'slug', 'name', 'label' ) as $item_key ) {
								if ( isset( $item[ $item_key ] ) && is_scalar( $item[ $item_key ] ) ) {
									$categories[] = (string) $item[ $item_key ];
								}
							}
						} elseif ( is_object( $item ) ) {
							foreach ( array( 'slug', 'name', 'label' ) as $item_key ) {
								if ( isset( $item->{$item_key} ) && is_scalar( $item->{$item_key} ) ) {
									$categories[] = (string) $item->{$item_key};
								}
							}
						}
					}
				} elseif ( is_scalar( $value ) ) {
					$categories[] = (string) $value;
				}
			}

			return array_values( array_unique( array_filter( $categories ) ) );
		}

		$id = velotok_catalog_product_id( $product );

		if ( $id > 0 && function_exists( 'get_the_terms' ) ) {
			$terms = get_the_terms( $id, 'product_cat' );

			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( is_object( $term ) ) {
						foreach ( array( 'slug', 'name', 'label' ) as $term_key ) {
							if ( isset( $term->{$term_key} ) && is_scalar( $term->{$term_key} ) ) {
								$categories[] = (string) $term->{$term_key};
							}
						}
					} elseif ( is_array( $term ) ) {
						foreach ( array( 'slug', 'name', 'label' ) as $term_key ) {
							if ( isset( $term[ $term_key ] ) && is_scalar( $term[ $term_key ] ) ) {
								$categories[] = (string) $term[ $term_key ];
							}
						}
					} elseif ( is_scalar( $term ) ) {
						$categories[] = (string) $term;
					}
				}
			}
		}

		return array_values( array_unique( array_filter( $categories ) ) );
	}

	/**
	 * Значение одного атрибута контракта у товара.
	 *
	 * @param mixed  $product   Товар.
	 * @param string $attribute Ключ атрибута.
	 * @return string
	 */
	function velotok_catalog_product_attribute( $product, $attribute ) {
		$attributes = velotok_catalog_filter_contract_attributes();
		$key        = velotok_catalog_normalize_key( $attribute );

		if ( ! isset( $attributes[ $key ] ) ) {
			return '';
		}

		$definition = $attributes[ $key ];

		if ( ! empty( $definition['attribute'] ) ) {
			if ( is_array( $product ) ) {
				$direct = array( $key, '_velotok_' . $key, $definition['attribute'] );

				if ( isset( $definition['label'] ) ) {
					$direct[] = $definition['label'];
				}

				foreach ( $direct as $candidate ) {
					if ( isset( $product[ $candidate ] ) && is_scalar( $product[ $candidate ] ) && '' !== trim( (string) $product[ $candidate ] ) ) {
						return (string) $product[ $candidate ];
					}
				}

				$needles = array_map( 'velotok_catalog_normalize_text', $direct );

				foreach ( $product as $product_key => $product_value ) {
					if ( ! is_scalar( $product_value ) || '' === trim( (string) $product_value ) ) {
						continue;
					}

					if ( in_array( velotok_catalog_normalize_text( $product_key ), $needles, true ) ) {
						return (string) $product_value;
					}
				}
			}

			$row_value = velotok_catalog_product_attribute_row( $product, $definition['attribute'], $definition['label'] );

			if ( '' !== $row_value ) {
				return $row_value;
			}

			if ( ! empty( $definition['meta'] ) ) {
				return velotok_catalog_product_meta( $product, $definition['meta'] );
			}

			return '';
		}

		if ( 'category' === $key ) {
			return implode( ', ', velotok_catalog_product_categories( $product ) );
		}

		if ( is_array( $product ) ) {
			$aliases = array(
				'range_km'  => array( 'range', '_velotok_range_km' ),
				'motor_w'   => array( 'motor', '_velotok_motor_w' ),
				'weight_kg' => array( 'weight', '_velotok_weight_kg' ),
				'tagline'   => array( 'tagline' ),
				'wheel'     => array( 'wheel' ),
				'badge'     => array( 'badge' ),
				'price'     => array( 'price' ),
				'name'      => array( 'name' ),
				'description' => array( 'description', 'short_description' ),
				'sku'       => array( 'sku', '_sku' ),
				'model'     => array( 'model', '_model', '_velotok_model' ),
			);

			$candidates = isset( $aliases[ $key ] ) ? $aliases[ $key ] : array();
			$candidates[] = $key;

			if ( isset( $definition['meta'] ) ) {
				$candidates[] = $definition['meta'];
			}

			foreach ( $candidates as $candidate ) {
				if ( isset( $product[ $candidate ] ) && is_scalar( $product[ $candidate ] ) && '' !== trim( (string) $product[ $candidate ] ) ) {
					return (string) $product[ $candidate ];
				}
			}

			return '';
		}

		if ( ! is_object( $product ) ) {
			return '';
		}

		switch ( $key ) {
			case 'name':
				return method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '';
			case 'description':
				$parts = array();
				if ( method_exists( $product, 'get_short_description' ) ) {
					$parts[] = (string) $product->get_short_description();
				}
				if ( method_exists( $product, 'get_description' ) ) {
					$parts[] = (string) $product->get_description();
				}
				return trim( implode( ' ', array_filter( $parts ) ) );
			case 'sku':
				return method_exists( $product, 'get_sku' ) ? (string) $product->get_sku() : '';
			case 'price':
				return method_exists( $product, 'get_price' ) ? (string) $product->get_price() : '';
		}

		if ( isset( $definition['meta'] ) ) {
			return velotok_catalog_product_meta( $product, $definition['meta'] );
		}

		return '';
	}

	/**
	 * Все значения товара, участвующие в поиске.
	 *
	 * @param mixed $product Товар.
	 * @return string[]
	 */
	function velotok_catalog_product_search_values( $product ) {
		$values = array();

		foreach ( velotok_catalog_searchable_attributes() as $attribute ) {
			$value = velotok_catalog_product_attribute( $product, $attribute );

			if ( '' !== $value ) {
				$values[] = $value;
			}
		}

		foreach ( velotok_catalog_product_attribute_rows( $product ) as $row ) {
			$values[] = $row['label'];
			$values[] = $row['value'];
		}

		if ( is_array( $product ) ) {
			foreach ( array( 'specs', 'category_label', 'slug' ) as $key ) {
				if ( ! isset( $product[ $key ] ) ) {
					continue;
				}

				if ( is_array( $product[ $key ] ) ) {
					foreach ( $product[ $key ] as $item ) {
						if ( is_array( $item ) ) {
							foreach ( $item as $part ) {
								if ( is_scalar( $part ) ) {
									$values[] = (string) $part;
								}
							}
						} elseif ( is_scalar( $item ) ) {
							$values[] = (string) $item;
						}
					}
				} elseif ( is_scalar( $product[ $key ] ) ) {
					$values[] = (string) $product[ $key ];
				}
			}
		}

		return array_values( array_unique( array_filter( $values, 'is_string' ) ) );
	}

	/**
	 * Совпадает ли товар с поисковым запросом по атрибутам контракта.
	 *
	 * @param mixed  $product Товар.
	 * @param string $query   Поисковый запрос.
	 * @return bool
	 */
	function velotok_catalog_product_matches_search( $product, $query ) {
		$query = velotok_catalog_normalize_text( $query );

		if ( '' === $query ) {
			return true;
		}

		$haystack = velotok_catalog_normalize_text( implode( ' ', velotok_catalog_product_search_values( $product ) ) );

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
	 * Разбивает строковое значение на части для сравнения.
	 *
	 * @param mixed $value Значение.
	 * @return string[]
	 */
	function velotok_catalog_value_parts( $value ) {
		$value = velotok_catalog_normalize_text( $value );

		if ( '' === $value ) {
			return array();
		}

		$parts = preg_split( '/\s*(?:,|\/|\||;)\s*/u', $value );
		$parts = is_array( $parts ) ? array_filter( array_map( 'trim', $parts ) ) : array();

		$parts[] = $value;

		return array_values( array_unique( $parts ) );
	}

	/**
	 * Совпадает ли фактическое значение атрибута с ожидаемым.
	 *
	 * @param mixed $actual   Фактическое значение.
	 * @param mixed $expected Ожидаемое значение.
	 * @return bool
	 */
	function velotok_catalog_value_matches( $actual, $expected ) {
		$actual_parts = velotok_catalog_value_parts( $actual );

		foreach ( (array) $expected as $candidate ) {
			$candidate = velotok_catalog_normalize_text( $candidate );

			if ( '' === $candidate ) {
				continue;
			}

			if ( in_array( $candidate, $actual_parts, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Санитизирует фильтры и оставляет только атрибуты контракта.
	 *
	 * @param mixed $filters Входные фильтры.
	 * @return array<string,mixed>
	 */
	function velotok_catalog_sanitize_filters( $filters ) {
		if ( ! is_array( $filters ) ) {
			return array();
		}

		$attributes = velotok_catalog_filter_contract_attributes();
		$contract   = velotok_catalog_filter_contract();
		$parameter  = $contract['filter_parameter'];

		if ( isset( $filters[ $parameter ] ) && is_array( $filters[ $parameter ] ) ) {
			$filters = array_merge( $filters, $filters[ $parameter ] );
			unset( $filters[ $parameter ] );
		}

		$clean = array();

		foreach ( $filters as $raw_key => $raw_value ) {
			$key = velotok_catalog_normalize_key( $raw_key );

			if ( in_array( $key, array( 'product_cat', 'product_category', 'cat' ), true ) ) {
				$key = 'category';
			}

			if ( in_array( $key, array( 'min_price', 'price_from' ), true ) ) {
				$key = 'price_min';
			}

			if ( in_array( $key, array( 'max_price', 'price_to' ), true ) ) {
				$key = 'price_max';
			}

			if ( 'attributes' === $key ) {
				if ( ! is_array( $raw_value ) ) {
					continue;
				}

				$attribute_filters = array();

				foreach ( $raw_value as $attribute_key => $attribute_value ) {
					$attribute_key = velotok_catalog_normalize_key( $attribute_key );

					if ( '' === $attribute_key || ! isset( $attributes[ $attribute_key ] ) || empty( $attributes[ $attribute_key ]['filter'] ) ) {
						continue;
					}

					if ( is_array( $attribute_value ) ) {
						$values = array();

						foreach ( $attribute_value as $item ) {
							$item = velotok_catalog_sanitize_filter_text( $item );

							if ( '' !== $item ) {
								$values[] = $item;
							}
						}

						if ( ! empty( $values ) ) {
							$attribute_filters[ $attribute_key ] = $values;
						}
					} else {
						$attribute_value = velotok_catalog_sanitize_filter_text( $attribute_value );

						if ( '' !== $attribute_value ) {
							$attribute_filters[ $attribute_key ] = $attribute_value;
						}
					}
				}

				if ( ! empty( $attribute_filters ) ) {
					$clean['attributes'] = $attribute_filters;
				}

				continue;
			}

			$base  = $key;
			$bound = '';

			if ( preg_match( '/^(.*)_(min|max)$/', $key, $matches ) ) {
				$base  = $matches[1];
				$bound = $matches[2];
			}

			if ( ! isset( $attributes[ $base ] ) || empty( $attributes[ $base ]['filter'] ) ) {
				continue;
			}

			$definition = $attributes[ $base ];

			if ( 'number' === $definition['type'] ) {
				if ( '' !== $bound ) {
					if ( is_numeric( $raw_value ) ) {
						$clean[ $base ][ $bound ] = (float) $raw_value;
					}
					continue;
				}

				if ( is_array( $raw_value ) ) {
					foreach ( array( 'min', 'max', 'value' ) as $nested_key ) {
						if ( isset( $raw_value[ $nested_key ] ) && is_numeric( $raw_value[ $nested_key ] ) ) {
							$clean[ $base ][ 'value' === $nested_key ? 'min' : $nested_key ] = (float) $raw_value[ $nested_key ];
						}
					}
					continue;
				}

				if ( is_numeric( $raw_value ) ) {
					$clean[ $base ] = (float) $raw_value;
				}

				continue;
			}

			if ( '' !== $bound ) {
				continue;
			}

			if ( is_array( $raw_value ) ) {
				$values = array();

				foreach ( $raw_value as $item ) {
					$item = velotok_catalog_sanitize_filter_text( $item );

					if ( '' !== $item ) {
						$values[] = $item;
					}
				}

				if ( ! empty( $values ) ) {
					$clean[ $base ] = $values;
				}
			} else {
				$raw_value = velotok_catalog_sanitize_filter_text( $raw_value );

				if ( '' !== $raw_value ) {
					$clean[ $base ] = $raw_value;
				}
			}
		}

		return $clean;
	}

	/**
	 * Совпадает ли товар с фильтрами контракта.
	 *
	 * @param mixed $product Товар.
	 * @param mixed $filters Фильтры.
	 * @return bool
	 */
	function velotok_catalog_product_matches_filters( $product, $filters ) {
		$filters = velotok_catalog_sanitize_filters( $filters );

		if ( empty( $filters ) ) {
			return true;
		}

		$attributes = velotok_catalog_filter_contract_attributes();

		foreach ( $filters as $key => $expected ) {
			if ( 'attributes' === $key ) {
				foreach ( (array) $expected as $attribute_key => $attribute_value ) {
					if ( isset( $attributes[ $attribute_key ] ) && ! empty( $attributes[ $attribute_key ]['attribute'] ) ) {
						$actual = velotok_catalog_product_attribute( $product, $attribute_key );
					} else {
						$actual = velotok_catalog_product_attribute_row( $product, $attribute_key, $attribute_key );
					}

					if ( '' === $actual || ! velotok_catalog_value_matches( $actual, $attribute_value ) ) {
						return false;
					}
				}
				continue;
			}

			if ( ! isset( $attributes[ $key ] ) ) {
				return false;
			}

			if ( 'category' === $key ) {
				$actual = velotok_catalog_product_categories( $product );

				if ( ! velotok_catalog_value_matches( implode( ', ', $actual ), $expected ) ) {
					return false;
				}

				continue;
			}

			$actual = velotok_catalog_product_attribute( $product, $key );

			if ( 'number' === $attributes[ $key ]['type'] ) {
				if ( '' === trim( (string) $actual ) || ! is_numeric( $actual ) ) {
					return false;
				}

				$actual = (float) $actual;

				if ( is_array( $expected ) ) {
					if ( isset( $expected['min'] ) && $actual < (float) $expected['min'] ) {
						return false;
					}

					if ( isset( $expected['max'] ) && $actual > (float) $expected['max'] ) {
						return false;
					}
				} elseif ( (float) $expected !== $actual ) {
					return false;
				}

				continue;
			}

			if ( ! velotok_catalog_value_matches( $actual, $expected ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Применяет поиск и фильтры к списку товаров.
	 *
	 * @param mixed $products Товары.
	 * @param mixed $args     Аргументы: search, filters, filter, s.
	 * @return array<int,mixed>
	 */
	function velotok_catalog_filter_products( $products, $args = array() ) {
		if ( ! is_array( $products ) ) {
			return array();
		}

		if ( ! is_array( $args ) ) {
			$args = array();
		}

		$search  = '';
		$filters = array();

		if ( isset( $args['search'] ) ) {
			$search = $args['search'];
		} elseif ( isset( $args['s'] ) ) {
			$search = $args['s'];
		}

		if ( isset( $args['filters'] ) ) {
			$filters = $args['filters'];
		} elseif ( isset( $args['filter'] ) ) {
			$filters = $args['filter'];
		} else {
			$filters = $args;
		}

		unset( $filters['search'], $filters['s'], $filters['filters'], $filters['filter'] );

		$filtered = array();

		foreach ( $products as $product ) {
			if ( '' !== trim( (string) $search ) && ! velotok_catalog_product_matches_search( $product, $search ) ) {
				continue;
			}

			if ( ! velotok_catalog_product_matches_filters( $product, $filters ) ) {
				continue;
			}

			$filtered[] = $product;
		}

		return $filtered;
	}

	/**
	 * Проверяет товар по поиску и фильтрам одновременно.
	 *
	 * @param mixed $product Товар.
	 * @param mixed $args    Аргументы.
	 * @return bool
	 */
	function velotok_catalog_product_matches( $product, $args ) {
		if ( ! is_array( $args ) ) {
			return true;
		}

		$matched = velotok_catalog_filter_products( array( $product ), $args );

		return ! empty( $matched );
	}

	/**
	 * Значения фильтра, доступные у товаров.
	 *
	 * @param string $attribute Ключ атрибута.
	 * @param mixed  $products  Товары.
	 * @return string[]
	 */
	function velotok_catalog_filter_options( $attribute, $products = array() ) {
		$key        = velotok_catalog_normalize_key( $attribute );
		$attributes = velotok_catalog_filter_contract_attributes();

		if ( '' === $key || ! isset( $attributes[ $key ] ) || empty( $attributes[ $key ]['filter'] ) ) {
			return array();
		}

		$values = array();

		foreach ( (array) $products as $product ) {
			if ( 'category' === $key ) {
				foreach ( velotok_catalog_product_categories( $product ) as $category ) {
					if ( '' !== trim( (string) $category ) ) {
						$values[] = (string) $category;
					}
				}

				continue;
			}

			$value = velotok_catalog_product_attribute( $product, $key );

			if ( '' !== trim( (string) $value ) ) {
				$values[] = (string) $value;
			}
		}

		$values = array_values( array_unique( $values ) );
		natcasesort( $values );

		return array_values( $values );
	}

	/**
	 * Фильтры из запроса без поисковой строки.
	 *
	 * @param mixed $source Источник (по умолчанию $_GET).
	 * @return array<string,mixed>
	 */
	function velotok_catalog_request_filters( $source = null ) {
		if ( null === $source ) {
			$source = isset( $_GET ) && is_array( $_GET ) ? $_GET : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- публичные фильтры каталога.
		}

		if ( ! is_array( $source ) ) {
			return array();
		}

		$contract = velotok_catalog_filter_contract();
		$input    = array();

		if ( isset( $source[ $contract['filter_parameter'] ] ) && is_array( $source[ $contract['filter_parameter'] ] ) ) {
			$input = array_merge( $input, $source[ $contract['filter_parameter'] ] );
		}

		foreach ( $source as $key => $value ) {
			if ( in_array( $key, array( $contract['filter_parameter'], $contract['search_parameter'], 'orderby', 'paged' ), true ) ) {
				continue;
			}

			$input[ $key ] = $value;
		}

		return velotok_catalog_sanitize_filters( $input );
	}

	/**
	 * Поисковая строка из запроса.
	 *
	 * @param mixed $source Источник (по умолчанию $_GET).
	 * @return string
	 */
	function velotok_catalog_request_search( $source = null ) {
		if ( null === $source ) {
			$source = isset( $_GET ) && is_array( $_GET ) ? $_GET : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- публичный поиск каталога.
		}

		if ( ! is_array( $source ) ) {
			return '';
		}

		$contract = velotok_catalog_filter_contract();

		if ( isset( $source[ $contract['search_parameter'] ] ) ) {
			return velotok_catalog_sanitize_filter_text( $source[ $contract['search_parameter'] ] );
		}

		if ( isset( $source['search'] ) ) {
			return velotok_catalog_sanitize_filter_text( $source['search'] );
		}

		if ( isset( $source[ $contract['filter_parameter'] ] ) && is_array( $source[ $contract['filter_parameter'] ] ) ) {
			foreach ( array( 'search', 's' ) as $search_key ) {
				if ( isset( $source[ $contract['filter_parameter'] ][ $search_key ] ) ) {
					return velotok_catalog_sanitize_filter_text( $source[ $contract['filter_parameter'] ][ $search_key ] );
				}
			}
		}

		return '';
	}

	/**
	 * Поиск и фильтры из запроса.
	 *
	 * @param mixed $source Источник.
	 * @return array{search:string,filters:array<string,mixed>}
	 */
	function velotok_catalog_request_query( $source = null ) {
		return array(
			'search'  => velotok_catalog_request_search( $source ),
			'filters' => velotok_catalog_request_filters( $source ),
			'orderby' => velotok_catalog_request_orderby( $source ),
		);
	}

	/**
	 * Параметры запроса, которые пагинация сохраняет на каждой странице.
	 *
	 * Поиск, фильтры по атрибутам контракта и сортировка переносятся в ссылки
	 * страниц без изменений. Нормализуется только orderby: недопустимое
	 * значение заменяется сортировкой по умолчанию. Параметр paged сюда не
	 * входит, потому что его задаёт сама ссылка страницы.
	 *
	 * @param mixed $source Источник (по умолчанию $_GET).
	 * @return array<string,mixed>
	 */
	function velotok_catalog_pagination_query_args( $source = null ) {
		if ( null === $source ) {
			$source = isset( $_GET ) && is_array( $_GET ) ? $_GET : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- публичная пагинация каталога.
		}

		if ( ! is_array( $source ) ) {
			return array();
		}

		$contract = velotok_catalog_filter_contract();
		$args     = array();

		$search = velotok_catalog_request_search( $source );

		if ( '' !== $search ) {
			$args[ $contract['search_parameter'] ] = $search;
		}

		$filters = velotok_catalog_request_filters( $source );

		if ( ! empty( $filters ) ) {
			$args[ $contract['filter_parameter'] ] = $filters;
		}

		if ( isset( $source[ $contract['sorting_parameter'] ] ) ) {
			$args[ $contract['sorting_parameter'] ] = velotok_catalog_normalize_orderby( $source[ $contract['sorting_parameter'] ] );
		}

		return $args;
	}

	/**
	 * Псевдоним параметров пагинации.
	 *
	 * @param mixed $source Источник.
	 * @return array<string,mixed>
	 */
	function velotok_catalog_pagination_args( $source = null ) {
		return velotok_catalog_pagination_query_args( $source );
	}

	/**
	 * Псевдоним параметров пагинации.
	 *
	 * @param mixed $source Источник.
	 * @return array<string,mixed>
	 */
	function velotok_catalog_pagination_params( $source = null ) {
		return velotok_catalog_pagination_query_args( $source );
	}

	/**
	 * Добавляет параметры запроса к URL без зависимости от WordPress.
	 *
	 * @param string $url  Исходный URL.
	 * @param array  $args Параметры запроса.
	 * @return string
	 */
	function velotok_catalog_add_query_args( $url, $args ) {
		$url      = (string) $url;
		$fragment = '';

		if ( false !== strpos( $url, '#' ) ) {
			list( $url, $fragment ) = explode( '#', $url, 2 );
			$fragment = '#' . $fragment;
		}

		$query = '';

		if ( false !== strpos( $url, '?' ) ) {
			list( $url, $query ) = explode( '?', $url, 2 );
		}

		$existing = array();

		if ( '' !== $query ) {
			parse_str( $query, $existing );
		}

		if ( is_array( $args ) ) {
			$existing = array_merge( $existing, $args );
		}

		$query = http_build_query( $existing, '', '&', PHP_QUERY_RFC3986 );

		return $url . ( '' !== $query ? '?' . $query : '' ) . $fragment;
	}

	/**
	 * Ссылка на страницу каталога с сохранением поиска, фильтров и сортировки.
	 *
	 * @param int    $page   Номер страницы.
	 * @param string $base   Базовый URL архива.
	 * @param mixed  $source Источник параметров (по умолчанию $_GET).
	 * @return string
	 */
	function velotok_catalog_pagination_url( $page, $base = '', $source = null ) {
		$page = max( 1, (int) $page );

		if ( '' === (string) $base ) {
			$base = function_exists( 'home_url' ) ? home_url( '/' ) : '/';
		}

		$args = velotok_catalog_pagination_query_args( $source );

		if ( $page > 1 ) {
			$args['paged'] = $page;
		}

		if ( function_exists( 'add_query_arg' ) ) {
			return add_query_arg( $args, $base );
		}

		return velotok_catalog_add_query_args( $base, $args );
	}

	/**
	 * Псевдоним ссылки на страницу каталога.
	 *
	 * @param int    $page   Номер страницы.
	 * @param string $base   Базовый URL архива.
	 * @param mixed  $source Источник параметров.
	 * @return string
	 */
	function velotok_catalog_paginate_url( $page, $base = '', $source = null ) {
		return velotok_catalog_pagination_url( $page, $base, $source );
	}

	/**
	 * Разметка пагинации каталога с сохранением фильтров и сортировки.
	 *
	 * @param array $args Аргументы: base, current, total, source, mid_size.
	 * @return string
	 */
	function velotok_catalog_pagination_links( $args = array() ) {
		if ( ! is_array( $args ) ) {
			$args = array();
		}

		if ( ! function_exists( 'paginate_links' ) ) {
			return '';
		}

		$total = isset( $args['total'] ) ? (int) $args['total'] : 0;

		if ( $total < 1 ) {
			global $wp_query;

			$total = is_object( $wp_query ) && isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : 0;
		}

		if ( $total < 2 ) {
			return '';
		}

		$current = isset( $args['current'] ) ? max( 1, (int) $args['current'] ) : 1;

		if ( ! isset( $args['current'] ) && function_exists( 'get_query_var' ) ) {
			$paged = (int) get_query_var( 'paged' );

			if ( $paged < 1 ) {
				$paged = (int) get_query_var( 'page' );
			}

			if ( $paged > 0 ) {
				$current = $paged;
			}
		}

		$base = isset( $args['base'] ) ? (string) $args['base'] : '';

		if ( '' === $base ) {
			$base = function_exists( 'home_url' ) ? home_url( '/' ) : '/';
		}

		$source = array_key_exists( 'source', $args ) ? $args['source'] : null;
		$params = velotok_catalog_pagination_query_args( $source );

		if ( function_exists( 'add_query_arg' ) ) {
			$base = add_query_arg( $params, $base );
		} else {
			$base = velotok_catalog_add_query_args( $base, $params );
		}

		$base .= ( false === strpos( $base, '?' ) ? '?' : '&' ) . 'paged=%#%';

		$links = paginate_links( array(
			'base'      => $base,
			'format'    => '',
			'current'   => min( $current, $total ),
			'total'     => $total,
			'mid_size'  => isset( $args['mid_size'] ) ? max( 0, (int) $args['mid_size'] ) : 1,
			'prev_text' => '←',
			'next_text' => '→',
			'type'      => 'list',
		) );

		return is_string( $links ) ? $links : '';
	}

	/**
	 * Выводит пагинацию каталога с сохранением фильтров и сортировки.
	 *
	 * @param array $args Аргументы пагинации.
	 * @return void
	 */
	function velotok_catalog_pagination( $args = array() ) {
		$links = velotok_catalog_pagination_links( $args );

		if ( '' === $links ) {
			if ( function_exists( 'woocommerce_pagination' ) ) {
				woocommerce_pagination();
			}

			return;
		}

		$label = function_exists( 'esc_attr__' ) ? esc_attr__( 'Постраничная навигация', 'velotok' ) : 'Постраничная навигация';

		echo '<nav class="woocommerce-pagination" aria-label="' . $label . '">' . $links . '</nav>';
	}

	/**
	 * Псевдоним активных фильтров каталога.
	 *
	 * @param mixed $source Источник.
	 * @return array<string,mixed>
	 */
	function velotok_catalog_active_filters( $source = null ) {
		return velotok_catalog_request_filters( $source );
	}

	/**
	 * Проверяет, относится ли запрос к каталогу товаров.
	 *
	 * @param mixed $query WP_Query.
	 * @return bool
	 */
	function velotok_catalog_is_product_query( $query ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) ) {
			return false;
		}

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return false;
		}

		if ( method_exists( $query, 'is_main_query' ) && ! $query->is_main_query() ) {
			return false;
		}

		return 'product' === $query->get( 'post_type' )
			|| ( method_exists( $query, 'is_post_type_archive' ) && $query->is_post_type_archive( 'product' ) )
			|| ( method_exists( $query, 'is_tax' ) && $query->is_tax( 'product_cat' ) );
	}

	/**
	 * Расширяет поиск WooCommerce атрибутами контракта.
	 *
	 * @param string $search SQL-условие поиска WordPress.
	 * @param mixed  $query  WP_Query.
	 * @return string
	 */
	function velotok_catalog_extend_product_search( $search, $query ) {
		if ( ! velotok_catalog_is_product_query( $query ) ) {
			return $search;
		}

		if ( ! method_exists( $query, 'is_search' ) || ! $query->is_search() ) {
			return $search;
		}

		$term = trim( (string) $query->get( 's' ) );

		if ( '' === $term ) {
			return $search;
		}

		global $wpdb;

		if ( ! is_object( $wpdb ) || ! isset( $wpdb->posts ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $search;
		}

		$escape_like = static function ( $value ) use ( $wpdb ) {
			if ( method_exists( $wpdb, 'esc_like' ) ) {
				return $wpdb->esc_like( (string) $value );
			}

			return addcslashes( (string) $value, '_%\\' );
		};

		$clauses = array();
		$like    = '%' . $escape_like( $term ) . '%';

		if ( isset( $wpdb->postmeta ) ) {
			$attributes = velotok_catalog_filter_contract_attributes();
			$meta_keys  = array( '_sku', '_product_attributes' );

			foreach ( $attributes as $attribute ) {
				if ( ! empty( $attribute['meta'] ) ) {
					$meta_keys[] = $attribute['meta'];
				}
			}

			$meta_keys = array_values( array_unique( $meta_keys ) );

			if ( ! empty( $meta_keys ) ) {
				$placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
				$clauses[]    = $wpdb->prepare(
					"EXISTS (SELECT 1 FROM {$wpdb->postmeta} velotok_pm WHERE velotok_pm.post_id = {$wpdb->posts}.ID AND velotok_pm.meta_key IN ({$placeholders}) AND velotok_pm.meta_value LIKE %s)",
					array_merge( $meta_keys, array( $like ) )
				);
			}
		}

		if ( isset( $wpdb->term_relationships, $wpdb->term_taxonomy, $wpdb->terms ) ) {
			$clauses[] = $wpdb->prepare(
				"EXISTS (SELECT 1 FROM {$wpdb->term_relationships} velotok_tr INNER JOIN {$wpdb->term_taxonomy} velotok_tt ON velotok_tr.term_taxonomy_id = velotok_tt.term_taxonomy_id INNER JOIN {$wpdb->terms} velotok_t ON velotok_tt.term_id = velotok_t.term_id WHERE velotok_tr.object_id = {$wpdb->posts}.ID AND velotok_tt.taxonomy = %s AND (velotok_t.name LIKE %s OR velotok_t.slug LIKE %s))",
				array( 'product_cat', $like, $like )
			);
		}

		if ( empty( $clauses ) ) {
			return $search;
		}

		$sql = '( ' . implode( ' OR ', $clauses ) . ' )';

		if ( '' === trim( (string) $search ) ) {
			return ' AND ' . $sql . ' ';
		}

		$closing = strrpos( (string) $search, ')' );

		if ( false === $closing ) {
			return $search . ' AND ' . $sql . ' ';
		}

		return substr( (string) $search, 0, $closing ) . ' OR ' . $sql . ' ' . substr( (string) $search, $closing );
	}

	/**
	 * Применяет полный контрактный поиск и фильтры к найденным товарам архива.
	 *
	 * @param mixed $posts Найденные записи.
	 * @param mixed $query WP_Query.
	 * @return array<int,mixed>
	 */
	function velotok_catalog_filter_archive_posts( $posts, $query ) {
		if ( ! is_array( $posts ) || ! velotok_catalog_is_product_query( $query ) ) {
			return $posts;
		}

		$request = velotok_catalog_request_query();

		if ( '' === $request['search'] && empty( $request['filters'] ) ) {
			return $posts;
		}

		$filtered = array();

		foreach ( $posts as $post ) {
			$product_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : 0;
			$product    = $product_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

			if ( ! is_object( $product ) ) {
				$filtered[] = $post;
				continue;
			}

			if ( '' !== $request['search'] && ! velotok_catalog_product_matches_search( $product, $request['search'] ) ) {
				continue;
			}

			if ( ! velotok_catalog_product_matches_filters( $product, $request['filters'] ) ) {
				continue;
			}

			$filtered[] = $post;
		}

		return $filtered;
	}

	/**
	 * Параметры запроса WooCommerce для сортировки из фиксированного набора.
	 *
	 * @param mixed $orderby Значение orderby.
	 * @return array<string,string>
	 */
	function velotok_catalog_orderby_query_vars( $orderby ) {
		switch ( velotok_catalog_normalize_orderby( $orderby ) ) {
			case 'price':
				return array(
					'meta_key' => '_price',
					'orderby'  => 'meta_value_num',
					'order'    => 'ASC',
				);
			case 'price-desc':
				return array(
					'meta_key' => '_price',
					'orderby'  => 'meta_value_num',
					'order'    => 'DESC',
				);
			case 'popularity':
				return array(
					'meta_key' => 'total_sales',
					'orderby'  => 'meta_value_num',
					'order'    => 'DESC',
				);
			case 'rating':
				return array(
					'meta_key' => '_wc_average_rating',
					'orderby'  => 'meta_value_num',
					'order'    => 'DESC',
				);
			case 'date':
				return array(
					'orderby' => 'date',
					'order'   => 'DESC',
				);
			case 'menu_order':
			default:
				return array(
					'orderby' => 'menu_order',
					'order'   => 'ASC',
				);
		}
	}

	/**
	 * Применяет сортировку из фиксированного набора к запросу.
	 *
	 * @param mixed $query   WP_Query.
	 * @param mixed $orderby Значение orderby.
	 * @return void
	 */
	function velotok_catalog_apply_sorting( $query, $orderby = null ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'set' ) ) {
			return;
		}

		if ( null === $orderby ) {
			$orderby = velotok_catalog_request_orderby();
		}

		foreach ( velotok_catalog_orderby_query_vars( $orderby ) as $key => $value ) {
			$query->set( $key, $value );
		}
	}

	/**
	 * Добавляет фильтры контракта к основному запросу архива WooCommerce.
	 *
	 * @param mixed $query WP_Query.
	 * @return void
	 */
	function velotok_catalog_apply_archive_filters( $query ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) ) {
			return;
		}

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return;
		}

		if ( method_exists( $query, 'is_main_query' ) && ! $query->is_main_query() ) {
			return;
		}

		$is_product = 'product' === $query->get( 'post_type' )
			|| ( method_exists( $query, 'is_post_type_archive' ) && $query->is_post_type_archive( 'product' ) )
			|| ( method_exists( $query, 'is_tax' ) && $query->is_tax( 'product_cat' ) );

		if ( ! $is_product ) {
			return;
		}

		$source = isset( $_GET ) && is_array( $_GET ) ? $_GET : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- публичная сортировка каталога.
		if ( isset( $source['orderby'] ) ) {
			velotok_catalog_apply_sorting( $query, velotok_catalog_request_orderby( $source ) );
		}

		$filters = velotok_catalog_request_filters();

		if ( empty( $filters ) ) {
			return;
		}

		$attributes = velotok_catalog_filter_contract_attributes();
		$meta_query = $query->get( 'meta_query' );
		$meta_query = is_array( $meta_query ) ? $meta_query : array();

		foreach ( $filters as $key => $expected ) {
			if ( 'attributes' === $key || ! isset( $attributes[ $key ] ) || empty( $attributes[ $key ]['meta'] ) ) {
				continue;
			}

			$clause = array(
				'key'  => $attributes[ $key ]['meta'],
				'type' => 'number' === $attributes[ $key ]['type'] ? 'NUMERIC' : 'CHAR',
			);

			if ( is_array( $expected ) ) {
				if ( isset( $expected['min'], $expected['max'] ) ) {
					$clause['value']   = array( $expected['min'], $expected['max'] );
					$clause['compare'] = 'BETWEEN';
				} elseif ( isset( $expected['min'] ) ) {
					$clause['value']   = $expected['min'];
					$clause['compare'] = '>=';
				} elseif ( isset( $expected['max'] ) ) {
					$clause['value']   = $expected['max'];
					$clause['compare'] = '<=';
				} else {
					$clause['value']   = array_values( $expected );
					$clause['compare'] = 'IN';
				}
			} else {
				$clause['value']   = $expected;
				$clause['compare'] = '=';
			}

			if ( empty( $meta_query ) ) {
				$meta_query['relation'] = 'AND';
			}

			$meta_query[] = $clause;
		}

		if ( ! empty( $meta_query ) && method_exists( $query, 'set' ) ) {
			$query->set( 'meta_query', $meta_query );
		}

		if ( isset( $filters['category'] ) && method_exists( $query, 'get' ) && method_exists( $query, 'set' ) ) {
			$tax_query = $query->get( 'tax_query' );
			$tax_query = is_array( $tax_query ) ? $tax_query : array();
			$slugs     = array_values( (array) $filters['category'] );

			if ( ! empty( $slugs ) ) {
				if ( empty( $tax_query ) ) {
					$tax_query['relation'] = 'AND';
				}

				$tax_query[] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $slugs,
				);

				$query->set( 'tax_query', $tax_query );
			}
		}
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'pre_get_posts', 'velotok_catalog_apply_archive_filters' );
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'posts_search', 'velotok_catalog_extend_product_search', 10, 2 );
	add_filter( 'the_posts', 'velotok_catalog_filter_archive_posts', 10, 2 );
}
