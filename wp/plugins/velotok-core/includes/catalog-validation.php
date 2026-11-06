<?php
/**
 * Серверная валидация каталога при сохранении товара через REST API.
 *
 * Правила и тексты — часть контракта каталога: сценарии приёмки проверяют
 * HTTP-ответ и сообщение, а не внутреннюю реализацию. Импорт помечает товары
 * служебным флагом и может оставлять их в состоянии incomplete.
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Тексты ошибок каталога. Продукт может переопределить их фильтром.
 *
 * @return array<string,string>
 */
function velotok_catalog_messages() {
	return apply_filters( 'velotok_catalog_messages', array(
		'model_required'        => 'Укажите модель',
		'name_required'         => 'Укажите название',
		'description_required'  => 'Укажите описание',
		'price_required'        => 'Укажите цену',
		'photo_required'        => 'Добавьте фото',
		'model_duplicate'       => 'Модель уже используется',
		'price_positive'        => 'Цена должна быть больше нуля',
		'price_decimals'        => 'Цена — не более двух знаков после запятой',
		'title_limit'           => 'Название не длиннее 120 символов',
		'description_limit'     => 'Описание не длиннее 5000 символов',
		'attribute_not_allowed' => 'Характеристика вне перечня контракта',
		'photo_too_big'         => 'Фото не больше 5 МБ',
		'photo_format'          => 'Фото должно быть jpg, png или webp',
	) );
}

/**
 * Значение мета-поля из запроса, если оно там есть.
 *
 * @param WP_REST_Request $request Запрос.
 * @param string          $key     Ключ мета.
 * @param bool            $found   Признак, что ключ передан.
 * @return string
 */
function velotok_catalog_meta_from_request( $request, $key, &$found = false ) {
	$found = false;
	$meta  = $request['meta_data'];
	if ( ! is_array( $meta ) ) {
		return '';
	}
	foreach ( $meta as $row ) {
		$row_key = is_array( $row ) ? ( $row['key'] ?? '' ) : ( $row->key ?? '' );
		if ( $row_key !== $key ) {
			continue;
		}
		$found = true;
		return trim( (string) ( is_array( $row ) ? ( $row['value'] ?? '' ) : ( $row->value ?? '' ) ) );
	}
	return '';
}

/**
 * Проверяет товар перед сохранением через WooCommerce REST API.
 *
 * @param WC_Product      $product Товар.
 * @param WP_REST_Request $request Запрос.
 * @return WC_Product|WP_Error
 */
function velotok_catalog_validate_product( $product, $request ) {
	$messages = velotok_catalog_messages();
	$import   = '1' === velotok_catalog_meta_from_request( $request, '_velotok_import', $import_found );
	$model    = velotok_catalog_meta_from_request( $request, 'model', $model_found );
	if ( ! $model_found ) {
		$model = trim( (string) get_post_meta( $product->get_id(), 'model', true ) );
	}

	$name        = isset( $request['name'] ) ? (string) $request['name'] : $product->get_name();
	$description = isset( $request['description'] ) ? (string) $request['description'] : $product->get_description();
	$is_variable = 'variable' === ( isset( $request['type'] ) ? (string) $request['type'] : $product->get_type() );
	$price       = isset( $request['regular_price'] ) ? (string) $request['regular_price'] : (string) $product->get_regular_price();
	$images      = isset( $request['images'] ) ? (array) $request['images'] : ( $product->get_image_id() ? array( $product->get_image_id() ) : array() );

	if ( ! $import ) {
		if ( '' === $model ) {
			return new WP_Error( 'velotok_model_required', $messages['model_required'], array( 'status' => 400 ) );
		}
		if ( '' === trim( $name ) ) {
			return new WP_Error( 'velotok_name_required', $messages['name_required'], array( 'status' => 400 ) );
		}
		if ( '' === trim( wp_strip_all_tags( $description ) ) ) {
			return new WP_Error( 'velotok_description_required', $messages['description_required'], array( 'status' => 400 ) );
		}
		if ( ! $is_variable && '' === trim( $price ) ) {
			return new WP_Error( 'velotok_price_required', $messages['price_required'], array( 'status' => 400 ) );
		}
		if ( empty( $images ) ) {
			return new WP_Error( 'velotok_photo_required', $messages['photo_required'], array( 'status' => 400 ) );
		}
	}

	if ( '' !== $model ) {
		$duplicates = get_posts( array(
			'post_type'   => 'product',
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
			'exclude'     => array( $product->get_id() ),
			'meta_key'    => 'model',
			'meta_value'  => $model,
		) );
		if ( ! empty( $duplicates ) ) {
			return new WP_Error( 'velotok_model_duplicate', $messages['model_duplicate'], array( 'status' => 400 ) );
		}
	}

	if ( ! $is_variable && '' !== trim( $price ) ) {
		if ( ! is_numeric( $price ) || (float) $price <= 0 ) {
			return new WP_Error( 'velotok_price_positive', $messages['price_positive'], array( 'status' => 400 ) );
		}
		if ( ! preg_match( '/^\d+(\.\d{1,2})?$/', $price ) ) {
			return new WP_Error( 'velotok_price_decimals', $messages['price_decimals'], array( 'status' => 400 ) );
		}
	}

	if ( mb_strlen( $name ) > 120 ) {
		return new WP_Error( 'velotok_title_limit', $messages['title_limit'], array( 'status' => 400 ) );
	}
	if ( mb_strlen( $description ) > 5000 ) {
		return new WP_Error( 'velotok_description_limit', $messages['description_limit'], array( 'status' => 400 ) );
	}

	$color_id = 0;
	if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
		foreach ( wc_get_attribute_taxonomies() as $taxonomy ) {
			if ( 'pa_color' === 'pa_' . $taxonomy->attribute_name ) {
				$color_id = (int) $taxonomy->attribute_id;
				break;
			}
		}
	}
	foreach ( (array) $request['attributes'] as $attribute ) {
		$attribute = (array) $attribute;
		$allowed   = ( $color_id && isset( $attribute['id'] ) && (int) $attribute['id'] === $color_id );
		if ( ! $allowed && isset( $attribute['name'] ) ) {
			$allowed = in_array( mb_strtolower( (string) $attribute['name'] ), array( 'цвет', 'color', 'pa_color' ), true );
		}
		if ( ! $allowed ) {
			return new WP_Error( 'velotok_attribute_not_allowed', $messages['attribute_not_allowed'], array( 'status' => 400 ) );
		}
	}

	return $product;
}
add_filter( 'woocommerce_rest_pre_insert_product_object', 'velotok_catalog_validate_product', 10, 2 );

/**
 * Проверяет загружаемое фото до сохранения: формат и размер.
 *
 * @param array $file Описание файла.
 * @return array
 */
function velotok_catalog_validate_upload( $file ) {
	if ( ! isset( $file['name'] ) ) {
		return $file;
	}
	$messages = velotok_catalog_messages();
	$extension = mb_strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
	if ( ! in_array( $extension, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
		$file['error'] = $messages['photo_format'];
		return $file;
	}
	if ( ! empty( $file['tmp_name'] ) && file_exists( $file['tmp_name'] ) && filesize( $file['tmp_name'] ) > 5 * 1024 * 1024 ) {
		$file['error'] = $messages['photo_too_big'];
	}
	return $file;
}
add_filter( 'wp_handle_sideload_prefilter', 'velotok_catalog_validate_upload' );
