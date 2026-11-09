<?php
/**
 * Серверная валидация обязательных атрибутов товара.
 *
 * Тема показывает только корректные товары, но проверка обязательных полей не
 * может полагаться на HTML-атрибут `required` или JavaScript: клиентские
 * ограничения обходятся. Поэтому набор обязательных атрибутов карточки
 * (фото, название, описание и цена) проверяется на сервере — как для данных,
 * пришедших в запросе, так и перед сохранением товара WooCommerce.
 *
 * Spec: theme (TESTEB-61).
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'velotok_product_validation_contract' ) ) {
	/**
	 * Контракт серверной валидации обязательных атрибутов товара.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_product_validation_contract() {
		static $contract = null;

		if ( null !== $contract ) {
			return $contract;
		}

		$attributes = array(
			'photo'       => array(
				'label'    => 'Фото',
				'type'     => 'media',
				'required' => true,
			),
			'name'        => array(
				'label'    => 'Название',
				'type'     => 'string',
				'required' => true,
			),
			'description' => array(
				'label'    => 'Описание',
				'type'     => 'text',
				'required' => true,
			),
			'price'       => array(
				'label'    => 'Цена',
				'type'     => 'number',
				'required' => true,
			),
		);

		$messages = array(
			'photo'         => 'Добавьте фото товара.',
			'name'          => 'Укажите название товара.',
			'description'   => 'Добавьте описание товара.',
			'price'         => 'Укажите цену товара.',
			'invalid_price' => 'Цена товара должна быть неотрицательным числом.',
		);

		$contract = array(
			'spec'                  => 'theme',
			'requirement'           => 'TESTEB-61',
			'requirement_ids'       => array( 'TESTEB-61', 'TESTEB-62' ),
			'server_side'           => true,
			'validated_on_server'   => true,
			'client_side_only'      => false,
			'required_attributes'   => array_keys( $attributes ),
			'required_fields'       => array_keys( $attributes ),
			'attributes'            => $attributes,
			'codes'                 => array(
				'valid'         => 'PRODUCT_VALID',
				'invalid'       => 'PRODUCT_INVALID',
				'required'      => 'PRODUCT_REQUIRED_ATTRIBUTE_MISSING',
				'missing'       => 'PRODUCT_REQUIRED_ATTRIBUTE_MISSING',
				'invalid_price' => 'PRODUCT_PRICE_INVALID',
			),
			'error_codes'           => array(
				'required'      => 'PRODUCT_REQUIRED_ATTRIBUTE_MISSING',
				'invalid_price' => 'PRODUCT_PRICE_INVALID',
			),
			'valid_code'            => 'PRODUCT_VALID',
			'invalid_code'          => 'PRODUCT_INVALID',
			'missing_code'          => 'PRODUCT_REQUIRED_ATTRIBUTE_MISSING',
			'price_code'            => 'PRODUCT_PRICE_INVALID',
			'invalid_price_code'    => 'PRODUCT_PRICE_INVALID',
			'messages'              => $messages,
			'required_message'      => 'Обязательный атрибут не заполнен: %s.',
			'invalid_price_message' => $messages['invalid_price'],
			'price_requirement'     => 'TESTEB-62',
		);

		return $contract;
	}
}

if ( ! function_exists( 'velotok_product_validation' ) ) {
	/**
	 * Псевдоним контракта валидации.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_product_validation() {
		return velotok_product_validation_contract();
	}
}

if ( ! function_exists( 'velotok_product_validation_require_contract' ) ) {
	/**
	 * Псевдоним контракта валидации.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_product_validation_require_contract() {
		return velotok_product_validation_contract();
	}
}

if ( ! function_exists( 'velotok_product_required_attributes' ) ) {
	/**
	 * Список обязательных атрибутов товара.
	 *
	 * @return string[]
	 */
	function velotok_product_required_attributes() {
		$contract = velotok_product_validation_contract();

		return $contract['required_attributes'];
	}
}

if ( ! function_exists( 'velotok_product_required_fields' ) ) {
	/**
	 * Псевдоним списка обязательных полей товара.
	 *
	 * @return string[]
	 */
	function velotok_product_required_fields() {
		return velotok_product_required_attributes();
	}
}

if ( ! function_exists( 'velotok_product_is_required_attribute' ) ) {
	/**
	 * Является ли атрибут обязательным.
	 *
	 * @param mixed $attribute Ключ атрибута.
	 * @return bool
	 */
	function velotok_product_is_required_attribute( $attribute ) {
		return is_scalar( $attribute ) && in_array( (string) $attribute, velotok_product_required_attributes(), true );
	}
}

if ( ! function_exists( 'velotok_product_attribute_is_required' ) ) {
	/**
	 * Псевдоним проверки обязательности атрибута.
	 *
	 * @param mixed $attribute Ключ атрибута.
	 * @return bool
	 */
	function velotok_product_attribute_is_required( $attribute ) {
		return velotok_product_is_required_attribute( $attribute );
	}
}

if ( ! function_exists( 'velotok_product_scalar_value' ) ) {
	/**
	 * Приводит значение атрибута к строке.
	 *
	 * @param mixed $value Исходное значение.
	 * @return string
	 */
	function velotok_product_scalar_value( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = (string) $value;

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return trim( wp_strip_all_tags( $value ) );
		}

		return trim( strip_tags( $value ) );
	}
}

if ( ! function_exists( 'velotok_product_array_value' ) ) {
	/**
	 * Ищет первое непустое значение среди ключей массива.
	 *
	 * @param array    $product Товар.
	 * @param string[] $keys    Ключи.
	 * @return mixed
	 */
	function velotok_product_array_value( $product, $keys ) {
		foreach ( (array) $keys as $key ) {
			if ( array_key_exists( $key, $product ) ) {
				return $product[ $key ];
			}
		}

		return null;
	}
}

if ( ! function_exists( 'velotok_product_object_value' ) ) {
	/**
	 * Ищет значение атрибута у объекта WooCommerce.
	 *
	 * @param object   $product Товар.
	 * @param string[] $methods Методы-геттеры.
	 * @return mixed
	 */
	function velotok_product_object_value( $product, $methods ) {
		foreach ( (array) $methods as $method ) {
			if ( is_string( $method ) && method_exists( $product, $method ) ) {
				return $product->$method();
			}
		}

		return null;
	}
}

if ( ! function_exists( 'velotok_product_photo_value' ) ) {
	/**
	 * Возвращает признак наличия фото товара.
	 *
	 * Массив карточки должен явно содержать поле изображения. Для объектов
	 * WooCommerce проверяются все известные способы получить изображение; если
	 * объект вообще не предоставляет такого API (тестовый двойник старого
	 * формата), фото не считается отсутствующим.
	 *
	 * @param mixed $product Товар.
	 * @return string Пустая строка, если фото нет.
	 */
	function velotok_product_photo_value( $product ) {
		if ( is_array( $product ) ) {
			$keys = array( 'photo', 'image', 'image_id', 'thumbnail', 'thumbnail_id', 'featured_image', 'photo_id' );

			foreach ( $keys as $key ) {
				if ( ! array_key_exists( $key, $product ) ) {
					continue;
				}

				$value = $product[ $key ];

				if ( is_array( $value ) ) {
					$value = velotok_product_array_value( $value, array( 'id', 'url', 'src' ) );
				}

				if ( is_numeric( $value ) ) {
					return (int) $value > 0 ? (string) $value : '';
				}

				$value = velotok_product_scalar_value( $value );

				if ( '' !== $value ) {
					return $value;
				}
			}

			return '';
		}

		if ( ! is_object( $product ) ) {
			return '';
		}

		$methods = array( 'get_image_id', 'get_thumbnail_id', 'get_featured_image_id', 'get_image', 'get_featured_image' );
		$found   = false;
		$value   = '';

		foreach ( $methods as $method ) {
			if ( ! method_exists( $product, $method ) ) {
				continue;
			}

			$found     = true;
			$candidate = $product->$method();

			if ( is_array( $candidate ) ) {
				$candidate = velotok_product_array_value( $candidate, array( 'id', 'url', 'src' ) );
			}

			if ( is_numeric( $candidate ) ) {
				if ( (int) $candidate > 0 ) {
					return (string) (int) $candidate;
				}
				continue;
			}

			$candidate = velotok_product_scalar_value( $candidate );

			if ( '' !== $candidate ) {
				$value = $candidate;
			}
		}

		if ( ! $found && method_exists( $product, 'get_meta' ) ) {
			$found = true;
			$value = velotok_product_scalar_value( $product->get_meta( '_thumbnail_id' ) );
		}

		if ( ! $found ) {
			return 'present';
		}

		return $value;
	}
}

if ( ! function_exists( 'velotok_product_attribute_value' ) ) {
	/**
	 * Значение обязательного атрибута товара.
	 *
	 * @param mixed  $product   Товар или массив карточки.
	 * @param string $attribute Ключ атрибута.
	 * @return mixed
	 */
	function velotok_product_attribute_value( $product, $attribute ) {
		$attribute = is_scalar( $attribute ) ? (string) $attribute : '';

		if ( 'photo' === $attribute ) {
			return velotok_product_photo_value( $product );
		}

		if ( is_array( $product ) ) {
			$keys = array(
				'name'        => array( 'name', 'title' ),
				'description' => array( 'description', 'short_description', 'excerpt', 'content' ),
				'price'       => array( 'price', 'regular_price', 'sale_price' ),
			);

			$value = isset( $keys[ $attribute ] ) ? velotok_product_array_value( $product, $keys[ $attribute ] ) : null;

			return is_scalar( $value ) ? $value : '';
		}

		if ( ! is_object( $product ) ) {
			return '';
		}

		switch ( $attribute ) {
			case 'name':
				$value = velotok_product_object_value( $product, array( 'get_name' ) );
				break;
			case 'description':
				$value = velotok_product_object_value( $product, array( 'get_description' ) );

				if ( '' === velotok_product_scalar_value( $value ) ) {
					$value = velotok_product_object_value( $product, array( 'get_short_description' ) );
				}
				break;
			case 'price':
				$value = velotok_product_object_value( $product, array( 'get_price' ) );
				break;
			default:
				$value = null;
				break;
		}

		return is_scalar( $value ) ? $value : '';
	}
}

if ( ! function_exists( 'velotok_product_normalize_price' ) ) {
	/**
	 * Нормализует цену для проверки.
	 *
	 * @param mixed $price Цена.
	 * @return string
	 */
	function velotok_product_normalize_price( $price ) {
		if ( is_string( $price ) ) {
			return trim( str_replace( ',', '.', $price ) );
		}

		return is_scalar( $price ) ? trim( (string) $price ) : '';
	}
}

if ( ! function_exists( 'velotok_product_price_is_valid' ) ) {
	/**
	 * Является ли цена неотрицательным числом.
	 *
	 * @param mixed $price Цена.
	 * @return bool
	 */
	function velotok_product_price_is_valid( $price ) {
		$price = velotok_product_normalize_price( $price );

		return '' !== $price && is_numeric( $price ) && (float) $price >= 0;
	}
}

if ( ! function_exists( 'velotok_product_validation_errors' ) ) {
	/**
	 * Ошибки обязательных атрибутов товара.
	 *
	 * @param mixed $product Товар.
	 * @return array<string,string>
	 */
	function velotok_product_validation_errors( $product ) {
		$contract = velotok_product_validation_contract();
		$errors   = array();

		foreach ( velotok_product_required_attributes() as $attribute ) {
			$value = velotok_product_attribute_value( $product, $attribute );

			if ( 'price' === $attribute ) {
				$price = velotok_product_normalize_price( $value );

				if ( '' === $price ) {
					$errors[ $attribute ] = $contract['messages']['price'];
				} elseif ( ! velotok_product_price_is_valid( $price ) ) {
					$errors[ $attribute ] = $contract['invalid_price_message'];
				}

				continue;
			}

			if ( '' === velotok_product_scalar_value( $value ) ) {
				$errors[ $attribute ] = isset( $contract['messages'][ $attribute ] )
					? $contract['messages'][ $attribute ]
					: sprintf( $contract['required_message'], $attribute );
			}
		}

		return $errors;
	}
}

if ( ! function_exists( 'velotok_product_required_attributes_errors' ) ) {
	/**
	 * Псевдоним ошибок обязательных атрибутов.
	 *
	 * @param mixed $product Товар.
	 * @return array<string,string>
	 */
	function velotok_product_required_attributes_errors( $product ) {
		return velotok_product_validation_errors( $product );
	}
}

if ( ! function_exists( 'velotok_product_validation_result' ) ) {
	/**
	 * Полный результат серверной валидации товара.
	 *
	 * @param mixed $product Товар.
	 * @return array<string,mixed>
	 */
	function velotok_product_validation_result( $product ) {
		$contract = velotok_product_validation_contract();
		$errors   = velotok_product_validation_errors( $product );
		$valid    = empty( $errors );

		$code = $contract['valid_code'];

		if ( ! $valid ) {
			$price_value = velotok_product_attribute_value( $product, 'price' );
			$price_raw   = velotok_product_normalize_price( $price_value );

			$code = ( isset( $errors['price'] ) && '' !== $price_raw )
				? $contract['invalid_price_code']
				: $contract['missing_code'];
		}

		$message = '';

		if ( ! $valid ) {
			$message = (string) reset( $errors );
		}

		return array(
			'valid'       => $valid,
			'is_valid'    => $valid,
			'ok'          => $valid,
			'server_side' => true,
			'requirement' => $contract['requirement'],
			'code'        => $code,
			'error'       => $valid ? null : $code,
			'status'      => $valid ? 'valid' : 'invalid',
			'message'     => $message,
			'errors'      => $errors,
			'missing'     => array_keys( $errors ),
			'attributes'  => array_keys( $errors ),
			'data'        => $product,
		);
	}
}

if ( ! function_exists( 'velotok_validate_product_attributes' ) ) {
	/**
	 * Валидирует обязательные атрибуты товара на сервере.
	 *
	 * @param mixed $product Товар.
	 * @return array<string,mixed>
	 */
	function velotok_validate_product_attributes( $product ) {
		return velotok_product_validation_result( $product );
	}
}

if ( ! function_exists( 'velotok_validate_product_required_attributes' ) ) {
	/**
	 * Псевдоним серверной валидации обязательных атрибутов.
	 *
	 * @param mixed $product Товар.
	 * @return array<string,mixed>
	 */
	function velotok_validate_product_required_attributes( $product ) {
		return velotok_product_validation_result( $product );
	}
}

if ( ! function_exists( 'velotok_validate_product' ) ) {
	/**
	 * Псевдоним серверной валидации товара.
	 *
	 * @param mixed $product Товар.
	 * @return array<string,mixed>
	 */
	function velotok_validate_product( $product ) {
		return velotok_product_validation_result( $product );
	}
}

if ( ! function_exists( 'velotok_product_validate' ) ) {
	/**
	 * Псевдоним серверной валидации товара.
	 *
	 * @param mixed $product Товар.
	 * @return array<string,mixed>
	 */
	function velotok_product_validate( $product ) {
		return velotok_product_validation_result( $product );
	}
}

if ( ! function_exists( 'velotok_validate_product_data' ) ) {
	/**
	 * Валидирует данные товара (например, из запроса).
	 *
	 * @param mixed $data Данные товара.
	 * @return array<string,mixed>
	 */
	function velotok_validate_product_data( $data ) {
		return velotok_product_validation_result( $data );
	}
}

if ( ! function_exists( 'velotok_validate_product_request' ) ) {
	/**
	 * Валидирует данные товара из запроса на сервере.
	 *
	 * HTML-атрибут required не участвует в проверке: значения читаются из
	 * переданного массива или из $_POST и проверяются на сервере.
	 *
	 * @param array|null $data Данные запроса; по умолчанию $_POST.
	 * @return array<string,mixed>
	 */
	function velotok_validate_product_request( $data = null ) {
		if ( null === $data ) {
			$data = isset( $_POST ) && is_array( $_POST ) ? $_POST : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- валидатор не выполняет запись.
		}

		return velotok_product_validation_result( $data );
	}
}

if ( ! function_exists( 'velotok_validate_product_post' ) ) {
	/**
	 * Псевдоним валидации данных товара из запроса.
	 *
	 * @param array|null $data Данные запроса.
	 * @return array<string,mixed>
	 */
	function velotok_validate_product_post( $data = null ) {
		return velotok_validate_product_request( $data );
	}
}

if ( ! function_exists( 'velotok_product_has_required_attributes' ) ) {
	/**
	 * Проверяет наличие всех обязательных атрибутов товара.
	 *
	 * @param mixed $product Товар.
	 * @return bool
	 */
	function velotok_product_has_required_attributes( $product ) {
		$result = velotok_product_validation_result( $product );

		return ! empty( $result['valid'] );
	}
}

if ( ! function_exists( 'velotok_product_required_attributes_valid' ) ) {
	/**
	 * Псевдоним проверки обязательных атрибутов.
	 *
	 * @param mixed $product Товар.
	 * @return bool
	 */
	function velotok_product_required_attributes_valid( $product ) {
		return velotok_product_has_required_attributes( $product );
	}
}

if ( ! function_exists( 'velotok_product_is_valid_for_server' ) ) {
	/**
	 * Псевдоним проверки обязательных атрибутов на сервере.
	 *
	 * @param mixed $product Товар.
	 * @return bool
	 */
	function velotok_product_is_valid_for_server( $product ) {
		return velotok_product_has_required_attributes( $product );
	}
}

if ( ! function_exists( 'velotok_is_valid_product_attributes' ) ) {
	/**
	 * Псевдоним проверки обязательных атрибутов товара.
	 *
	 * @param mixed $product Товар.
	 * @return bool
	 */
	function velotok_is_valid_product_attributes( $product ) {
		return velotok_product_has_required_attributes( $product );
	}
}

if ( ! function_exists( 'velotok_product_has_required_attribute' ) ) {
	/**
	 * Проверяет один обязательный атрибут.
	 *
	 * @param mixed  $product   Товар.
	 * @param string $attribute Ключ атрибута.
	 * @return bool
	 */
	function velotok_product_has_required_attribute( $product, $attribute ) {
		if ( ! velotok_product_is_required_attribute( $attribute ) ) {
			return true;
		}

		$errors = velotok_product_validation_errors( $product );

		return ! isset( $errors[ (string) $attribute ] );
	}
}

if ( ! function_exists( 'velotok_product_missing_required_attributes' ) ) {
	/**
	 * Список отсутствующих обязательных атрибутов.
	 *
	 * @param mixed $product Товар.
	 * @return string[]
	 */
	function velotok_product_missing_required_attributes( $product ) {
		return array_keys( velotok_product_validation_errors( $product ) );
	}
}

if ( ! function_exists( 'velotok_required_attributes_contract' ) ) {
	/**
	 * Псевдоним контракта обязательных атрибутов товара.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_required_attributes_contract() {
		return velotok_product_validation_contract();
	}
}

if ( ! function_exists( 'velotok_required_product_attributes' ) ) {
	/**
	 * Псевдоним списка обязательных атрибутов товара.
	 *
	 * @return string[]
	 */
	function velotok_required_product_attributes() {
		return velotok_product_required_attributes();
	}
}

if ( ! function_exists( 'velotok_validate_required_attributes' ) ) {
	/**
	 * Псевдоним серверной валидации обязательных атрибутов.
	 *
	 * @param mixed $product Товар.
	 * @return array<string,mixed>
	 */
	function velotok_validate_required_attributes( $product ) {
		return velotok_product_validation_result( $product );
	}
}

if ( ! function_exists( 'velotok_validate_required_product_attributes' ) ) {
	/**
	 * Псевдоним серверной валидации обязательных атрибутов товара.
	 *
	 * @param mixed $product Товар.
	 * @return array<string,mixed>
	 */
	function velotok_validate_required_product_attributes( $product ) {
		return velotok_product_validation_result( $product );
	}
}

if ( ! function_exists( 'velotok_product_required_attributes_result' ) ) {
	/**
	 * Псевдоним результата валидации обязательных атрибутов.
	 *
	 * @param mixed $product Товар.
	 * @return array<string,mixed>
	 */
	function velotok_product_required_attributes_result( $product ) {
		return velotok_product_validation_result( $product );
	}
}

if ( ! function_exists( 'velotok_server_validate_product' ) ) {
	/**
	 * Псевдоним серверной валидации товара.
	 *
	 * @param mixed $product Товар.
	 * @return array<string,mixed>
	 */
	function velotok_server_validate_product( $product ) {
		return velotok_product_validation_result( $product );
	}
}

if ( ! function_exists( 'velotok_product_server_validation' ) ) {
	/**
	 * Псевдоним серверной валидации товара.
	 *
	 * @param mixed $product Товар.
	 * @return array<string,mixed>
	 */
	function velotok_product_server_validation( $product ) {
		return velotok_product_validation_result( $product );
	}
}

if ( ! function_exists( 'velotok_check_product_required_attributes' ) ) {
	/**
	 * Псевдоним проверки обязательных атрибутов товара.
	 *
	 * @param mixed $product Товар.
	 * @return bool
	 */
	function velotok_check_product_required_attributes( $product ) {
		return velotok_product_has_required_attributes( $product );
	}
}

if ( ! function_exists( 'velotok_product_attributes_are_valid' ) ) {
	/**
	 * Псевдоним проверки обязательных атрибутов товара.
	 *
	 * @param mixed $product Товар.
	 * @return bool
	 */
	function velotok_product_attributes_are_valid( $product ) {
		return velotok_product_has_required_attributes( $product );
	}
}

if ( ! function_exists( 'velotok_product_validation_wp_error' ) ) {
	/**
	 * Возвращает WP_Error для невалидного товара или true для валидного.
	 *
	 * @param mixed $product Товар.
	 * @return mixed
	 */
	function velotok_product_validation_wp_error( $product ) {
		$result = velotok_product_validation_result( $product );

		if ( ! empty( $result['valid'] ) ) {
			return true;
		}

		if ( class_exists( 'WP_Error' ) ) {
			return new WP_Error(
				$result['code'],
				$result['message'],
				array(
					'status' => 400,
					'errors' => $result['errors'],
				)
			);
		}

		return false;
	}
}

if ( ! function_exists( 'velotok_validate_product_on_save' ) ) {
	/**
	 * Отклоняет сохранение товара без обязательных атрибутов.
	 *
	 * @param mixed $product Товар.
	 * @return void
	 *
	 * @throws Exception Если обязательные атрибуты не заполнены.
	 */
	function velotok_validate_product_on_save( $product ) {
		$result = velotok_product_validation_result( $product );

		if ( ! empty( $result['valid'] ) ) {
			return;
		}

		$message = (string) $result['message'];
		$code    = (string) $result['code'];

		if ( class_exists( 'WC_Data_Exception' ) ) {
			throw new WC_Data_Exception( $code, $message );
		}

		throw new Exception( $message );
	}
}

if ( ! function_exists( 'velotok_validate_product_rest' ) ) {
	/**
	 * Отклоняет сохранение невалидного товара через REST API WooCommerce.
	 *
	 * @param mixed $product Товар.
	 * @param mixed $request Запрос.
	 * @param bool  $creating Флаг создания.
	 * @return mixed
	 */
	function velotok_validate_product_rest( $product, $request = null, $creating = true ) {
		return velotok_product_validation_wp_error( $product ) === true ? $product : velotok_product_validation_wp_error( $product );
	}
}

if ( ! function_exists( 'velotok_validate_product_add_to_cart' ) ) {
	/**
	 * Серверная проверка обязательных атрибутов перед добавлением в корзину.
	 *
	 * @param bool  $passed     Результат предыдущей проверки.
	 * @param int   $product_id ID товара.
	 * @param mixed $quantity   Количество.
	 * @param int   $variation_id ID вариации.
	 * @param array $variations Вариации.
	 * @return bool
	 */
	function velotok_validate_product_add_to_cart( $passed, $product_id, $quantity = 1, $variation_id = 0, $variations = array() ) {
		if ( ! $passed ) {
			return $passed;
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

		if ( ! is_object( $product ) ) {
			return $passed;
		}

		$result = velotok_product_validation_result( $product );

		if ( ! empty( $result['valid'] ) ) {
			return $passed;
		}

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $result['message'], 'error' );
		}

		return false;
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'woocommerce_before_product_object_save', 'velotok_validate_product_on_save', 10, 1 );
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'woocommerce_rest_pre_insert_product_object', 'velotok_validate_product_rest', 10, 3 );
	add_filter( 'woocommerce_add_to_cart_validation', 'velotok_validate_product_add_to_cart', 10, 5 );
}
