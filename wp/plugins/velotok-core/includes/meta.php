<?php
/**
 * Product meta fields and admin metabox.
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Describe the product meta fields used by the theme.
 *
 * @return array<string, array<string, mixed>>
 */
function velotok_core_product_meta_fields() {
	return array(
		'_velotok_tagline'   => array(
			'type'              => 'string',
			'input'             => 'text',
			'label'             => __( 'Короткий слоган', 'velotok-core' ),
			'description'       => __( 'Одна фраза о характере модели.', 'velotok-core' ),
			'sanitize_callback' => 'sanitize_text_field',
		),
		'_velotok_range_km'  => array(
			'type'              => 'integer',
			'input'             => 'number',
			'label'             => __( 'Запас хода, км', 'velotok-core' ),
			'description'       => __( 'Целое число от 0 до 500.', 'velotok-core' ),
			'sanitize_callback' => 'velotok_core_sanitize_range_km',
			'min'               => 0,
			'max'               => 500,
			'step'              => 1,
		),
		'_velotok_motor_w'   => array(
			'type'              => 'integer',
			'input'             => 'number',
			'label'             => __( 'Мощность мотора, Вт', 'velotok-core' ),
			'description'       => __( 'Целое число от 0 до 3000.', 'velotok-core' ),
			'sanitize_callback' => 'velotok_core_sanitize_motor_w',
			'min'               => 0,
			'max'               => 3000,
			'step'              => 1,
		),
		'_velotok_weight_kg' => array(
			'type'              => 'number',
			'input'             => 'number',
			'label'             => __( 'Вес, кг', 'velotok-core' ),
			'description'       => __( 'Число с точкой, например 23.5. Можно оставить пустым.', 'velotok-core' ),
			'sanitize_callback' => 'velotok_core_sanitize_weight_kg',
			'min'               => 0,
			'max'               => 500,
			'step'              => 0.1,
		),
		'_velotok_wheel'     => array(
			'type'              => 'string',
			'input'             => 'text',
			'label'             => __( 'Колёса', 'velotok-core' ),
			'description'       => __( 'Например: 28″, 700C или 20″ / 26″.', 'velotok-core' ),
			'sanitize_callback' => 'sanitize_text_field',
		),
		'_velotok_badge'     => array(
			'type'              => 'string',
			'input'             => 'text',
			'label'             => __( 'Бейдж', 'velotok-core' ),
			'description'       => __( 'Например: Хит, Новинка, Для гор.', 'velotok-core' ),
			'sanitize_callback' => 'sanitize_text_field',
		),
	);
}

/**
 * Clamp an integer to the allowed range.
 *
 * @param mixed $value Raw value.
 * @param int   $min   Minimum.
 * @param int   $max   Maximum.
 * @return int
 */
function velotok_core_sanitize_int_range( $value, $min, $max ) {
	$value = (int) $value;

	if ( $value < $min ) {
		$value = $min;
	}

	if ( $value > $max ) {
		$value = $max;
	}

	return $value;
}

/**
 * Sanitize range_km.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function velotok_core_sanitize_range_km( $value ) {
	return velotok_core_sanitize_int_range( $value, 0, 500 );
}

/**
 * Sanitize motor_w.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function velotok_core_sanitize_motor_w( $value ) {
	return velotok_core_sanitize_int_range( $value, 0, 3000 );
}

/**
 * Sanitize weight_kg.
 *
 * @param mixed $value Raw value.
 * @return float
 */
function velotok_core_sanitize_weight_kg( $value ) {
	$value = str_replace( ',', '.', (string) $value );
	$value = (float) $value;

	if ( $value < 0 ) {
		$value = 0;
	}

	if ( $value > 500 ) {
		$value = 500;
	}

	return round( $value, 2 );
}

/**
 * Authorize meta changes against the current user.
 *
 * @param bool   $allowed  Whether the user can edit the meta.
 * @param string $meta_key Meta key.
 * @param int    $post_id  Post ID.
 * @return bool
 */
function velotok_core_meta_auth( $allowed, $meta_key, $post_id ) {
	if ( $post_id ) {
		return current_user_can( 'edit_post', (int) $post_id );
	}

	return current_user_can( 'edit_products' );
}

/**
 * Register the contract meta fields for products.
 */
function velotok_core_register_product_meta() {
	foreach ( velotok_core_product_meta_fields() as $meta_key => $field ) {
		register_post_meta(
			'product',
			$meta_key,
			array(
				'type'              => $field['type'],
				'description'       => $field['description'],
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => $field['sanitize_callback'],
				'auth_callback'     => 'velotok_core_meta_auth',
			)
		);
	}
}
add_action( 'init', 'velotok_core_register_product_meta', 20 );

/**
 * Add the product metabox.
 */
function velotok_core_add_product_metabox() {
	add_meta_box(
		'velotok-core-product-fields',
		__( 'Характеристики велосипеда', 'velotok-core' ),
		'velotok_core_render_product_metabox',
		'product',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_product', 'velotok_core_add_product_metabox' );

/**
 * Render the product metabox.
 *
 * @param WP_Post $post Current post.
 */
function velotok_core_render_product_metabox( $post ) {
	wp_nonce_field( 'velotok_core_save_product_meta', 'velotok_core_nonce' );

	echo '<div class="velotok-core-fields">';

	foreach ( velotok_core_product_meta_fields() as $meta_key => $field ) {
		$value = get_post_meta( $post->ID, $meta_key, true );
		$id    = 'velotok-' . ltrim( $meta_key, '_' );

		echo '<p>';
		echo '<label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $field['label'] ) . '</strong></label><br>';
		echo '<input type="' . esc_attr( $field['input'] ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $value ) . '" class="widefat"';

		if ( 'number' === $field['input'] ) {
			echo ' min="' . esc_attr( $field['min'] ) . '" max="' . esc_attr( $field['max'] ) . '" step="' . esc_attr( $field['step'] ) . '"';
		}

		echo '>';
		echo '<span class="description">' . esc_html( $field['description'] ) . '</span>';
		echo '</p>';
	}

	echo '</div>';
}

/**
 * Save the product metabox values.
 *
 * @param int $post_id Post ID.
 */
function velotok_core_save_product_meta( $post_id ) {
	if ( ! isset( $_POST['velotok_core_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['velotok_core_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'velotok_core_save_product_meta' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( 'product' !== get_post_type( $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( velotok_core_product_meta_fields() as $meta_key => $field ) {
		if ( ! isset( $_POST[ $meta_key ] ) ) {
			delete_post_meta( $post_id, $meta_key );
			continue;
		}

		$raw = wp_unslash( $_POST[ $meta_key ] );

		if ( is_string( $raw ) && '' === trim( $raw ) ) {
			delete_post_meta( $post_id, $meta_key );
			continue;
		}

		$value = call_user_func( $field['sanitize_callback'], $raw );

		if ( '' === $value || null === $value ) {
			delete_post_meta( $post_id, $meta_key );
			continue;
		}

		update_post_meta( $post_id, $meta_key, $value );
	}
}
add_action( 'save_post_product', 'velotok_core_save_product_meta' );
