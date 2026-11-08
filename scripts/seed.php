<?php
/**
 * Idempotent demo product seed.
 *
 * Run from the WordPress root: wp eval-file scripts/seed.php
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stop the seed with a clear error message.
 *
 * @param string $message Error message.
 */
function velotok_seed_fail( $message ) {
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::error( $message );
	}

	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
}

/**
 * Read and validate content/products.json.
 *
 * @return array<int, array<string, mixed>>
 */
function velotok_seed_read_products() {
	$path = dirname( __DIR__ ) . '/content/products.json';

	if ( ! is_readable( $path ) ) {
		velotok_seed_fail( sprintf( 'Не найден файл данных: %s', $path ) );
	}

	$raw = file_get_contents( $path );

	if ( false === $raw ) {
		velotok_seed_fail( sprintf( 'Не удалось прочитать файл данных: %s', $path ) );
	}

	$data = json_decode( $raw, true );

	if ( JSON_ERROR_NONE !== json_last_error() ) {
		velotok_seed_fail( sprintf( 'Ошибка JSON в %s: %s', $path, json_last_error_msg() ) );
	}

	if ( ! is_array( $data ) || array() === $data ) {
		velotok_seed_fail( sprintf( 'Файл данных пуст или имеет неверный формат: %s', $path ) );
	}

	return $data;
}

/**
 * Create or update product categories.
 *
 * @return array<string, int>
 */
function velotok_seed_categories() {
	$categories = array(
		'city'     => 'Городские',
		'mountain' => 'Горные',
		'cargo'    => 'Грузовые',
		'folding'  => 'Складные',
	);

	$ids = array();

	foreach ( $categories as $slug => $name ) {
		$existing = term_exists( $slug, 'product_cat' );

		if ( $existing ) {
			$term_id = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
			$result  = wp_update_term(
				$term_id,
				'product_cat',
				array(
					'name' => $name,
					'slug' => $slug,
				)
			);
		} else {
			$result = wp_insert_term(
				$name,
				'product_cat',
				array(
					'slug' => $slug,
				)
			);
		}

		if ( is_wp_error( $result ) ) {
			velotok_seed_fail( sprintf( 'Не удалось создать категорию %s: %s', $slug, $result->get_error_message() ) );
		}

		$ids[ $slug ] = (int) $result['term_id'];
	}

	return $ids;
}

/**
 * Find an existing attachment by file name.
 *
 * @param string $filename File name.
 * @return int
 */
function velotok_seed_find_attachment( $filename ) {
	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 20,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_wp_attached_file',
					'value'   => $filename,
					'compare' => 'LIKE',
				),
			),
		)
	);

	foreach ( $attachments as $attachment_id ) {
		$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );

		if ( $attached_file && $filename === basename( $attached_file ) ) {
			return (int) $attachment_id;
		}
	}

	return 0;
}

/**
 * Upload an image from assets/ into the media library.
 *
 * @param string $filename   File name.
 * @param int    $product_id Parent product ID.
 * @return int
 */
function velotok_seed_upload_image( $filename, $product_id ) {
	$source = dirname( __DIR__ ) . '/assets/' . $filename;

	if ( ! is_readable( $source ) ) {
		velotok_seed_fail( sprintf( 'Не найден файл картинки: %s', $source ) );
	}

	$contents = file_get_contents( $source );

	if ( false === $contents ) {
		velotok_seed_fail( sprintf( 'Не удалось прочитать файл картинки: %s', $source ) );
	}

	$upload = wp_upload_bits( $filename, null, $contents );

	if ( ! empty( $upload['error'] ) ) {
		velotok_seed_fail( sprintf( 'Не удалось загрузить картинку %s: %s', $filename, $upload['error'] ) );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';

	$filetype = wp_check_filetype( $filename );

	if ( empty( $filetype['type'] ) ) {
		velotok_seed_fail( sprintf( 'Неподдерживаемый тип файла: %s', $filename ) );
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$upload['file'],
		$product_id,
		true
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		$message = is_wp_error( $attachment_id ) ? $attachment_id->get_error_message() : 'неизвестная ошибка';
		velotok_seed_fail( sprintf( 'Не удалось создать вложение %s: %s', $filename, $message ) );
	}

	$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
	wp_update_attachment_metadata( $attachment_id, $metadata );

	return (int) $attachment_id;
}

/**
 * Create or update one demo product.
 *
 * @param array<string, mixed> $item         Product data.
 * @param array<string, int>   $category_ids Category term IDs.
 * @param WP_Post|null         $existing     Existing product post.
 * @return int
 */
function velotok_seed_save_product( $item, $category_ids, $existing ) {
	$name = isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : '';
	$slug = isset( $item['slug'] ) ? sanitize_title( $item['slug'] ) : '';
	$cat  = isset( $item['category'] ) ? sanitize_key( $item['category'] ) : '';

	if ( '' === $name || '' === $slug ) {
		velotok_seed_fail( 'У товара отсутствует название или slug.' );
	}

	if ( ! isset( $category_ids[ $cat ] ) ) {
		velotok_seed_fail( sprintf( 'Неизвестная категория «%s» у товара «%s».', $cat, $name ) );
	}

	$product = $existing ? wc_get_product( $existing->ID ) : new WC_Product_Simple();

	if ( ! $product ) {
		velotok_seed_fail( sprintf( 'Не удалось открыть товар «%s».', $name ) );
	}

	$price    = isset( $item['price'] ) ? (float) $item['price'] : 0;
	$old      = ( isset( $item['old_price'] ) && null !== $item['old_price'] && '' !== $item['old_price'] ) ? (float) $item['old_price'] : 0;
	$image    = isset( $item['image'] ) ? sanitize_file_name( $item['image'] ) : '';
	$tagline  = isset( $item['tagline'] ) ? sanitize_text_field( $item['tagline'] ) : '';
	$wheel    = isset( $item['wheel'] ) ? sanitize_text_field( $item['wheel'] ) : '';
	$badge    = isset( $item['badge'] ) ? sanitize_text_field( $item['badge'] ) : '';
	$short    = isset( $item['short_description'] ) ? wp_kses_post( $item['short_description'] ) : '';
	$long     = isset( $item['description'] ) ? wp_kses_post( $item['description'] ) : '';
	$range    = isset( $item['range_km'] ) && '' !== $item['range_km'] ? (int) $item['range_km'] : 0;
	$motor    = isset( $item['motor_w'] ) && '' !== $item['motor_w'] ? (int) $item['motor_w'] : 0;
	$has_weight = isset( $item['weight_kg'] ) && null !== $item['weight_kg'] && '' !== $item['weight_kg'];
	$weight   = $has_weight ? (float) str_replace( ',', '.', (string) $item['weight_kg'] ) : null;

	$product->set_name( $name );
	$product->set_slug( $slug );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_description( $long );
	$product->set_short_description( $short );
	$product->set_category_ids( array( $category_ids[ $cat ] ) );

	if ( $old > $price ) {
		$product->set_regular_price( wc_format_decimal( $old ) );
		$product->set_sale_price( wc_format_decimal( $price ) );
	} else {
		$product->set_regular_price( wc_format_decimal( $price ) );
		$product->set_sale_price( '' );
	}

	$product_id = $product->save();

	if ( ! $product_id ) {
		velotok_seed_fail( sprintf( 'Не удалось сохранить товар «%s».', $name ) );
	}

	if ( '' !== $image ) {
		$attachment_id = velotok_seed_find_attachment( $image );

		if ( ! $attachment_id ) {
			$attachment_id = velotok_seed_upload_image( $image, $product_id );
		}

		if ( $attachment_id ) {
			$product = wc_get_product( $product_id );

			if ( $product ) {
				$product->set_image_id( $attachment_id );
				$product->save();
			}
		}
	}

	$meta = array(
		'_velotok_tagline'   => $tagline,
		'_velotok_range_km'  => $range,
		'_velotok_motor_w'   => $motor,
		'_velotok_weight_kg' => $weight,
		'_velotok_wheel'     => $wheel,
		'_velotok_badge'     => $badge,
	);

	foreach ( $meta as $key => $value ) {
		if ( null === $value || '' === $value ) {
			delete_post_meta( $product_id, $key );
			continue;
		}

		update_post_meta( $product_id, $key, $value );
	}

	return (int) $product_id;
}

if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) || ! class_exists( 'WC_Product_Simple' ) ) {
	velotok_seed_fail( 'Для запуска seed нужен активный WooCommerce.' );
}

$items        = velotok_seed_read_products();
$category_ids = velotok_seed_categories();
$created      = 0;
$updated      = 0;

foreach ( $items as $item ) {
	if ( ! is_array( $item ) ) {
		velotok_seed_fail( 'Каждый элемент content/products.json должен быть объектом.' );
	}

	$slug     = isset( $item['slug'] ) ? sanitize_title( $item['slug'] ) : '';
	$existing = '' !== $slug ? get_page_by_path( $slug, OBJECT, 'product' ) : null;

	velotok_seed_save_product( $item, $category_ids, $existing );

	if ( $existing ) {
		++$updated;
	} else {
		++$created;
	}
}

$summary = sprintf( 'Готово: создано товаров — %d, обновлено — %d.', $created, $updated );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::success( $summary );
} else {
	echo esc_html( $summary ) . PHP_EOL;
}
