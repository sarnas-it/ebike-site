<?php
/**
 * Интеграция с WooCommerce.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

/**
 * Отключает стандартные стили WooCommerce: оформление задаёт тема.
 *
 * @return array
 */
function velotok_disable_woocommerce_styles() {
	return array();
}
add_filter( 'woocommerce_enqueue_styles', 'velotok_disable_woocommerce_styles', 999 );

/**
 * Количество товаров в корзине.
 *
 * @return int
 */
function velotok_cart_count() {
	if ( function_exists( 'WC' ) && WC()->cart ) {
		return (int) WC()->cart->get_cart_contents_count();
	}

	return 0;
}

/**
 * Разметка счётчика для AJAX-обновления фрагментов корзины.
 *
 * @return string
 */
function velotok_cart_count_html() {
	return '<span class="cart-count" id="velotok-cart-count">' . esc_html( (string) velotok_cart_count() ) . '</span>';
}

/**
 * Обновляет счётчик корзины через фрагменты WooCommerce.
 *
 * @param array $fragments Фрагменты.
 * @return array
 */
function velotok_cart_fragments( $fragments ) {
	$fragments['#velotok-cart-count'] = velotok_cart_count_html();

	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'velotok_cart_fragments' );

/**
 * Ссылка на корзину.
 *
 * @return string
 */
function velotok_cart_url() {
	if ( function_exists( 'wc_get_cart_url' ) ) {
		return wc_get_cart_url();
	}

	return home_url( '/' );
}

/**
 * Человекочитаемое количество моделей у категории.
 *
 * @param string $slug Слаг категории.
 * @return string
 */
function velotok_category_count_label( $slug ) {
	$fallbacks = array(
		'city'     => '14',
		'mountain' => '9',
		'cargo'    => '7',
		'folding'  => '12',
	);

	if ( taxonomy_exists( 'product_cat' ) ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			/* translators: %s: количество моделей. */
			return sprintf( _n( '%s модель', '%s моделей', (int) $term->count, 'velotok' ), number_format_i18n( (int) $term->count ) );
		}
	}

	$count = isset( $fallbacks[ $slug ] ) ? $fallbacks[ $slug ] : '0';

	/* translators: %s: количество моделей. */
	return sprintf( _n( '%s модель', '%s моделей', (int) $count, 'velotok' ), $count );
}

/**
 * Ссылка на категорию товаров.
 *
 * @param string $slug Слаг категории.
 * @return string
 */
function velotok_category_url( $slug ) {
	if ( taxonomy_exists( 'product_cat' ) ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				return $link;
			}
		}
	}

	return home_url( '/#models' );
}

/**
 * Шаблон картинки для товара без миниатюры.
 *
 * @param WC_Product $product Товар.
 * @param string     $alt     Альтернативный текст.
 * @return string
 */
function velotok_product_placeholder_image( $product, $alt ) {
	$file  = 'model-city-classic-1-web.jpg';
	$terms = get_the_terms( $product->get_id(), 'product_cat' );

	if ( $terms && ! is_wp_error( $terms ) ) {
		$map = array(
			'city'     => 'model-city-classic-1-web.jpg',
			'mountain' => 'model-mountain-pro-1-web.jpg',
			'cargo'    => 'model-cargo-family-1-web.jpg',
			'folding'  => 'model-folding-compact-1-web.jpg',
		);
		foreach ( $terms as $term ) {
			if ( isset( $map[ $term->slug ] ) ) {
				$file = $map[ $term->slug ];
				break;
			}
		}
	}

	return sprintf(
		'<img src="%1$s" width="800" height="600" alt="%2$s" loading="lazy" decoding="async" class="model-photo">',
		esc_url( get_template_directory_uri() . '/assets/img/' . $file ),
		esc_attr( $alt )
	);
}

/**
 * Разметка изображения товара.
 *
 * @param WC_Product $product Товар.
 * @param string     $alt     Альтернативный текст.
 * @return string
 */
function velotok_product_image_html( $product, $alt ) {
	$thumbnail_id = (int) get_post_thumbnail_id( $product->get_id() );

	if ( $thumbnail_id ) {
		$image = wp_get_attachment_image( $thumbnail_id, 'velotok-card', false, array(
			'alt'      => $alt,
			'class'    => 'model-photo',
			'loading'  => 'lazy',
			'decoding' => 'async',
		) );
		if ( $image ) {
			return $image;
		}
	}

	return velotok_product_placeholder_image( $product, $alt );
}

/**
 * Собирает характеристики товара из post meta.
 *
 * @param int $product_id ID товара.
 * @return array<int,array{0:string,1:string}>
 */
function velotok_product_specs( $product_id ) {
	$range  = trim( (string) get_post_meta( $product_id, '_velotok_range_km', true ) );
	$motor  = trim( (string) get_post_meta( $product_id, '_velotok_motor_w', true ) );
	$weight = trim( (string) get_post_meta( $product_id, '_velotok_weight_kg', true ) );
	$wheel  = trim( (string) get_post_meta( $product_id, '_velotok_wheel', true ) );

	$specs = array();

	if ( '' !== $range ) {
		$specs[] = array(
			__( 'Запас хода', 'velotok' ),
			/* translators: %s: запас хода в километрах. */
			sprintf( __( 'до %s км', 'velotok' ), $range ),
		);
	}
	if ( '' !== $motor ) {
		$specs[] = array(
			__( 'Мотор', 'velotok' ),
			/* translators: %s: мощность мотора в ваттах. */
			sprintf( __( '%s Вт', 'velotok' ), $motor ),
		);
	}
	if ( '' !== $wheel ) {
		$specs[] = array( __( 'Колёса', 'velotok' ), $wheel );
	}
	if ( '' !== $weight ) {
		$specs[] = array(
			__( 'Вес', 'velotok' ),
			/* translators: %s: вес в килограммах. */
			sprintf( __( '%s кг', 'velotok' ), $weight ),
		);
	}

	if ( empty( $specs ) ) {
		$specs[] = array( __( 'Характеристики', 'velotok' ), __( 'Уточните у менеджера', 'velotok' ) );
	}

	return $specs;
}

/**
 * Краткое описание товара для карточки каталога.
 *
 * @param WC_Product $product Товар.
 * @return string
 */
function velotok_product_description( $product ) {
	if ( ! is_object( $product ) ) {
		return '';
	}

	$description = method_exists( $product, 'get_short_description' ) ? (string) $product->get_short_description() : '';

	if ( '' === trim( wp_strip_all_tags( $description ) ) && method_exists( $product, 'get_description' ) ) {
		$description = (string) $product->get_description();
	}

	if ( '' === trim( wp_strip_all_tags( $description ) ) && method_exists( $product, 'get_id' ) ) {
		$description = (string) get_post_meta( $product->get_id(), '_velotok_tagline', true );
	}

	$description = wp_strip_all_tags( $description );

	if ( '' === $description ) {
		return __( 'Описание уточните у менеджера', 'velotok' );
	}

	if ( function_exists( 'wp_trim_words' ) ) {
		return wp_trim_words( $description, 30, '…' );
	}

	return $description;
}

/**
 * Произвольные дополнительные атрибуты товара.
 *
 * Продавцы задают их средствами WooCommerce: состав атрибутов не фиксирован,
 * поэтому карточка показывает все видимые атрибуты с непустыми значениями.
 *
 * @param WC_Product $product Товар.
 * @return array<int,array{0:string,1:string}>
 */
function velotok_product_extra_attributes( $product ) {
	$extra = array();

	if ( ! is_object( $product ) || ! method_exists( $product, 'get_attributes' ) ) {
		return $extra;
	}

	$attributes = $product->get_attributes();

	if ( ! is_array( $attributes ) ) {
		return $extra;
	}

	$seen = array();

	foreach ( $attributes as $attribute ) {
		if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_visible' ) || ! $attribute->get_visible() ) {
			continue;
		}

		$name = method_exists( $attribute, 'get_name' ) ? (string) $attribute->get_name() : '';

		if ( '' === trim( $name ) ) {
			continue;
		}

		$label = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( $name, $product ) : $name;
		$label = trim( wp_strip_all_tags( $label ) );
		$key   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label ) : strtolower( $label );

		if ( '' === $label || isset( $seen[ $key ] ) ) {
			continue;
		}

		$values = array();

		if ( method_exists( $attribute, 'is_taxonomy' ) && $attribute->is_taxonomy() && function_exists( 'wc_get_product_terms' ) ) {
			$terms = wc_get_product_terms( $product->get_id(), $name, array( 'fields' => 'names' ) );

			if ( is_array( $terms ) ) {
				$values = $terms;
			}
		} elseif ( method_exists( $attribute, 'get_options' ) ) {
			$values = (array) $attribute->get_options();
		}

		$clean = array();

		foreach ( $values as $value ) {
			$value = trim( wp_strip_all_tags( (string) $value ) );

			if ( '' !== $value ) {
				$clean[] = $value;
			}
		}

		if ( empty( $clean ) ) {
			continue;
		}

		$seen[ $key ] = true;
		$extra[]      = array( $label, implode( ', ', $clean ) );
	}

	return $extra;
}

/**
 * Характеристики карточки: обязательные поля плюс произвольные атрибуты.
 *
 * @param WC_Product $product Товар.
 * @return array<int,array{0:string,1:string}>
 */
function velotok_product_card_specs( $product ) {
	if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
		return array();
	}

	$specs = velotok_product_specs( $product->get_id() );
	$seen  = array();

	foreach ( $specs as $spec ) {
		$key          = function_exists( 'mb_strtolower' ) ? mb_strtolower( $spec[0] ) : strtolower( $spec[0] );
		$seen[ $key ] = true;
	}

	foreach ( velotok_product_extra_attributes( $product ) as $extra ) {
		$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $extra[0] ) : strtolower( $extra[0] );

		if ( isset( $seen[ $key ] ) ) {
			continue;
		}

		$seen[ $key ] = true;
		$specs[]      = $extra;
	}

	return $specs;
}

/**
 * Кнопка добавления в корзину или перехода к товару.
 *
 * @param WC_Product $product Товар.
 * @return string
 */
function velotok_product_add_button( $product ) {
	$name = $product->get_name();

	if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) {
		return sprintf(
			'<a href="%1$s" class="btn-add button product_type_simple add_to_cart_button ajax_add_to_cart" data-product_id="%2$d" data-product_sku="%3$s" aria-label="%4$s" rel="nofollow">%5$s</a>',
			esc_url( $product->add_to_cart_url() ),
			(int) $product->get_id(),
			esc_attr( $product->get_sku() ),
			/* translators: %s: название товара. */
			esc_attr( sprintf( __( 'Добавить в корзину: %s', 'velotok' ), $name ) ),
			esc_html__( 'В корзину', 'velotok' )
		);
	}

	return sprintf(
		'<a class="btn-add" href="%1$s">%2$s</a>',
		esc_url( $product->get_permalink() ),
		esc_html__( 'Подробнее', 'velotok' )
	);
}

/**
 * Название первой категории товара.
 *
 * @param WC_Product $product Товар.
 * @return string
 */
function velotok_product_category_label( $product ) {
	$terms = get_the_terms( $product->get_id(), 'product_cat' );

	if ( $terms && ! is_wp_error( $terms ) ) {
		return $terms[0]->name;
	}

	return __( 'Электровелосипед', 'velotok' );
}

/**
 * Количество месяцев рассрочки из данных спецификации.
 *
 * Функция не предполагает, что слой спецификации уже подключён: карточка
 * каталога должна рендериться и в изолированном тесте, и без плагина.
 *
 * @return int
 */
function velotok_installment_months() {
	$months = function_exists( 'velotok_spec_value' ) ? (int) velotok_spec_value( 'installment_months', 24 ) : 24;

	return max( 1, $months );
}

/**
 * Приводит товар WooCommerce к структуре карточки темы.
 *
 * @param WC_Product $product Товар.
 * @param int        $index   Порядковый номер.
 * @return array
 */
function velotok_normalize_product( $product, $index = 0 ) {
	$price      = (float) $product->get_price();
	$price_html = $product->get_price_html();
	$note       = '';
	$months     = velotok_installment_months();

	if ( $price > 0 ) {
		$note = sprintf(
			/* translators: %s: сумма ежемесячного платежа. */
			__( 'в рассрочку %s ₽/мес', 'velotok' ),
			number_format_i18n( (int) round( $price / $months ), 0 )
		);
	}

	$badge = trim( (string) get_post_meta( $product->get_id(), '_velotok_badge', true ) );

	return array(
		'id'             => (int) $product->get_id(),
		'card_id'        => 'model-' . $product->get_slug(),
		'slug'           => $product->get_slug(),
		'name'           => $product->get_name(),
		'url'            => $product->get_permalink(),
		'image_html'     => velotok_product_image_html( $product, $product->get_name() ),
		'description'    => velotok_product_description( $product ),
		'category_label' => velotok_product_category_label( $product ),
		'badge'          => $badge,
		'badge_lime'     => in_array( $badge, array( 'Хит', 'Выбор механиков', 'Новинка' ), true ),
		'number'         => $index ? sprintf( '%02d', (int) $index ) : '',
		'specs'          => velotok_product_card_specs( $product ),
		'price_html'     => $price_html ? $price_html : esc_html__( 'Цена по запросу', 'velotok' ),
		'price_note'     => $note,
		'add_html'       => velotok_product_add_button( $product ),
	);
}

/**
 * Демо-модели, если WooCommerce ещё не установлен или товаров нет.
 *
 * @return array<int,array>
 */
function velotok_fallback_models() {
	return array(
		array(
			'slug'           => 'riviera',
			'name'           => __( 'Ривьера', 'velotok' ),
			'description'    => __( 'Лёгкая низкая рама, 90 км запаса хода и спокойный характер — для ежедневных поездок по городу.', 'velotok' ),
			'category_label' => __( 'Городской', 'velotok' ),
			'badge'          => __( 'Хит', 'velotok' ),
			'badge_lime'     => true,
			'range'          => '90',
			'motor'          => '250',
			'wheel'          => '28″',
			'weight'         => '23',
			'price'          => 62900,
			'image'          => 'model-city-classic-1-web.jpg',
		),
		array(
			'slug'           => 'street',
			'name'           => __( 'Стрит 7', 'velotok' ),
			'description'    => __( 'Более быстрый и жёсткий вариант для асфальта: 110 км хода и посадка для активной езды.', 'velotok' ),
			'category_label' => __( 'Городской спорт', 'velotok' ),
			'badge'          => __( 'Новинка', 'velotok' ),
			'badge_lime'     => true,
			'range'          => '110',
			'motor'          => '350',
			'wheel'          => '700C',
			'weight'         => '21',
			'price'          => 74900,
			'image'          => 'model-city-sport-1-web.jpg',
		),
		array(
			'slug'           => 'hrebet-pro',
			'name'           => __( 'Хребет Pro', 'velotok' ),
			'description'    => __( 'Полная подвеска и мотор 750 Вт — если в маршруте есть серьёзные подъёмы и спуски.', 'velotok' ),
			'category_label' => __( 'Горный', 'velotok' ),
			'badge'          => __( 'Для гор', 'velotok' ),
			'badge_lime'     => false,
			'range'          => '120',
			'motor'          => '750',
			'wheel'          => '29″',
			'weight'         => '25',
			'price'          => 149900,
			'image'          => 'model-mountain-pro-1-web.jpg',
		),
		array(
			'slug'           => 'hrebet-trail',
			'name'           => __( 'Хребет Trail', 'velotok' ),
			'description'    => __( 'Хардтейл для леса и грунта: меньше веса, простая обслуживаемость и уверенный ход.', 'velotok' ),
			'category_label' => __( 'Горный', 'velotok' ),
			'badge'          => __( 'Выбор механиков', 'velotok' ),
			'badge_lime'     => true,
			'range'          => '95',
			'motor'          => '500',
			'wheel'          => '27,5″',
			'weight'         => '22',
			'price'          => 98900,
			'image'          => 'model-mountain-hardtail-1-web.jpg',
		),
		array(
			'slug'           => 'family',
			'name'           => __( 'Семейный', 'velotok' ),
			'description'    => __( 'Удлинённая рама и бокс на 80 кг: дети, продукты и всё, что нужно на даче.', 'velotok' ),
			'category_label' => __( 'Грузовой', 'velotok' ),
			'badge'          => __( 'Для семьи', 'velotok' ),
			'badge_lime'     => false,
			'range'          => '80',
			'motor'          => '750',
			'wheel'          => '20″ / 26″',
			'weight'         => '32',
			'price'          => 132900,
			'image'          => 'model-cargo-family-1-web.jpg',
		),
		array(
			'slug'           => 'courier',
			'name'           => __( 'Курьер', 'velotok' ),
			'description'    => __( 'Рама под ежедневные нагрузки: передний и задний багажник, груз до 120 кг.', 'velotok' ),
			'category_label' => __( 'Грузовой', 'velotok' ),
			'badge'          => __( 'Для работы', 'velotok' ),
			'badge_lime'     => false,
			'range'          => '100',
			'motor'          => '500',
			'wheel'          => '20″',
			'weight'         => '29',
			'price'          => 119900,
			'image'          => 'model-cargo-courier-1-web.jpg',
		),
		array(
			'slug'           => 'sklad',
			'name'           => __( 'Складень 20', 'velotok' ),
			'description'    => __( 'Складывается за полминуты и помещается в багажник — для поездов, офиса и лифта.', 'velotok' ),
			'category_label' => __( 'Складной', 'velotok' ),
			'badge'          => __( 'В багажник', 'velotok' ),
			'badge_lime'     => false,
			'range'          => '65',
			'motor'          => '250',
			'wheel'          => '20″',
			'weight'         => '18',
			'price'          => 68900,
			'image'          => 'model-folding-compact-1-web.jpg',
		),
	);
}

/**
 * Приводит демо-модель к структуре карточки темы.
 *
 * @param array $model Демо-модель.
 * @param int   $index Порядковый номер.
 * @return array
 */
function velotok_normalize_fallback_model( $model, $index = 0 ) {
	$price  = isset( $model['price'] ) ? (int) $model['price'] : 0;
	$months = velotok_installment_months();
	$specs  = array();

	if ( ! empty( $model['range'] ) ) {
		$specs[] = array(
			__( 'Запас хода', 'velotok' ),
			/* translators: %s: запас хода в километрах. */
			sprintf( __( 'до %s км', 'velotok' ), $model['range'] ),
		);
	}
	if ( ! empty( $model['motor'] ) ) {
		$specs[] = array(
			__( 'Мотор', 'velotok' ),
			/* translators: %s: мощность мотора в ваттах. */
			sprintf( __( '%s Вт', 'velotok' ), $model['motor'] ),
		);
	}
	if ( ! empty( $model['wheel'] ) ) {
		$specs[] = array( __( 'Колёса', 'velotok' ), $model['wheel'] );
	}
	if ( ! empty( $model['weight'] ) ) {
		$specs[] = array(
			__( 'Вес', 'velotok' ),
			/* translators: %s: вес в килограммах. */
			sprintf( __( '%s кг', 'velotok' ), $model['weight'] ),
		);
	}

	$image = sprintf(
		'<img src="%1$s" width="800" height="600" alt="%2$s" loading="lazy" decoding="async" class="model-photo">',
		esc_url( get_template_directory_uri() . '/assets/img/' . $model['image'] ),
		/* translators: %s: название модели. */
		esc_attr( sprintf( __( 'Электровелосипед «%s»', 'velotok' ), $model['name'] ) )
	);

	return array(
		'id'             => 0,
		'card_id'        => 'model-' . $model['slug'],
		'slug'           => $model['slug'],
		'name'           => $model['name'],
		'url'            => home_url( '/#model-' . $model['slug'] ),
		'image_html'     => $image,
		'description'    => isset( $model['description'] ) ? (string) $model['description'] : '',
		'category_label' => $model['category_label'],
		'badge'          => $model['badge'],
		'badge_lime'     => ! empty( $model['badge_lime'] ),
		'number'         => $index ? sprintf( '%02d', (int) $index ) : '',
		'specs'          => $specs,
		'price_html'     => number_format_i18n( $price, 0 ) . ' ₽',
		'price_note'     => $price > 0
			/* translators: %s: сумма ежемесячного платежа. */
			? sprintf( __( 'в рассрочку %s ₽/мес', 'velotok' ), number_format_i18n( (int) round( $price / $months ), 0 ) )
			: '',
		'add_html'       => sprintf(
			'<a class="btn-add" href="%1$s">%2$s</a>',
			esc_url( home_url( '/#lead' ) ),
			esc_html__( 'В корзину', 'velotok' )
		),
	);
}

/**
 * Модели для главной страницы.
 *
 * @return array<int,array>
 */
function velotok_get_home_models() {
	static $models = null;

	if ( null !== $models ) {
		return $models;
	}

	$models = array();

	if ( function_exists( 'wc_get_products' ) ) {
		$products = wc_get_products( array(
			'status'   => 'publish',
			'limit'    => -1,
			'orderby'  => 'menu_order',
			'order'    => 'ASC',
			'return'   => 'objects',
		) );

		if ( ! empty( $products ) ) {
			$index = 0;
			foreach ( $products as $product ) {
				if ( $product instanceof WC_Product ) {
					$index++;
					$models[] = velotok_normalize_product( $product, $index );
				}
			}
		}
	}

	if ( empty( $models ) ) {
		$index = 0;
		foreach ( velotok_fallback_models() as $model ) {
			$index++;
			$models[] = velotok_normalize_fallback_model( $model, $index );
		}
	}

	return $models;
}

/**
 * Товары по категории для блока каталога.
 *
 * @param string $slug Слаг категории.
 * @return array<int,array>
 */
function velotok_get_products_by_category( $slug, $limit = 3 ) {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return array();
	}

	$products = wc_get_products( array(
		'status'   => 'publish',
		'limit'    => (int) $limit,
		'category' => array( $slug ),
		'return'   => 'objects',
	) );

	$models = array();
	$index  = 0;
	foreach ( $products as $product ) {
		if ( $product instanceof WC_Product ) {
			$index++;
			$models[] = velotok_normalize_product( $product, $index );
		}
	}

	return $models;
}
