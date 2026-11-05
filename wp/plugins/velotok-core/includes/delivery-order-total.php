<?php
/**
 * Delivery cost in the order total stub contract.
 *
 * This is the single access point for the delivery line that is shown in the
 * order summary and for the order total that includes the delivery cost. The
 * current iteration deliberately uses the in-memory delivery options stub: it
 * does not read environment variables, files, the database or remote services.
 *
 * Real delivery providers and real tariff rules are out of scope until the
 * open questions are resolved. Consumers must use the helpers below so the
 * mocked implementation can be replaced without touching templates.
 *
 * Spec: clarify-b0e1a6d7.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

require_once __DIR__ . '/delivery-options.php';

/**
 * Return the delivery order total contract.
 *
 * The contract documents:
 * - the spec identifier and the explicit `mocked` marker;
 * - that the delivery cost is included in the order total;
 * - the display labels and the order of the summary lines;
 * - the calculation strategy based on the delivery options stub;
 * - the open questions that block a real integration.
 *
 * @return array<string,mixed>
 */
function velotok_core_delivery_order_total_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$contract = array(
		'spec'                   => 'clarify-b0e1a6d7',
		'parent_spec'            => 'clarify-a8fe76a0',
		'source'                 => 'stub',
		'mocked'                 => true,
		'provider'               => 'stub',
		'provider_id'            => 'stub',
		'provider_label'         => 'Stub delivery provider',
		'provider_contract'      => array(
			'id'      => 'stub',
			'name'    => 'stub',
			'label'   => 'Stub delivery provider',
			'source'  => 'stub',
			'mocked'  => true,
			'network' => false,
		),
		'currency'               => 'RUB',
		'delivery_source'        => 'stub',
		'includes_delivery'      => true,
		'total_includes_delivery' => true,
		'calculation'            => array(
			'strategy'        => 'subtotal_plus_delivery',
			'delivery_source' => 'velotok_core_calculate_delivery_cost',
			'rounding'        => '2',
			'currency'        => 'RUB',
			'free_delivery'   => 'delivery_stub_free_flag',
			'mocked'          => true,
			'network'         => false,
		),
		'fields'                 => array(
			'subtotal'        => 'subtotal',
			'delivery_method' => 'delivery_method',
			'delivery_label'  => 'delivery_method_label',
			'delivery_cost'   => 'delivery_cost',
			'delivery_free'   => 'delivery_free',
			'total'           => 'total',
			'currency'        => 'delivery_currency',
			'mocked'          => 'delivery_mocked',
		),
		'order_fields'           => array(
			'subtotal'        => 'subtotal',
			'delivery_method' => 'delivery_method',
			'delivery_label'  => 'delivery_method_label',
			'delivery_cost'   => 'delivery_cost',
			'delivery_free'   => 'delivery_free',
			'total'           => 'total',
			'currency'        => 'delivery_currency',
			'mocked'          => 'delivery_mocked',
			'meta'            => array(
				'delivery_method' => '_velotok_delivery_method',
				'delivery_cost'   => '_velotok_delivery_cost',
				'delivery_total'  => '_velotok_order_total',
				'currency'        => '_velotok_delivery_currency',
				'mocked'          => '_velotok_delivery_mocked',
			),
		),
		'display'                => array(
			'enabled'                 => true,
			'position'                => 'before_total',
			'show_delivery_line'      => true,
			'total_includes_delivery' => true,
			'decimals'                => 2,
			'decimal_separator'       => ',',
			'thousands_separator'     => ' ',
			'currency_symbol'         => '₽',
			'labels'                  => array(
				'subtotal'      => 'Товары',
				'delivery'      => 'Доставка',
				'delivery_free' => 'Бесплатно',
				'total'         => 'Итого',
			),
			'mocked'                  => true,
		),
		'error_codes'            => array(
			'missing_method' => 'DELIVERY_METHOD_REQUIRED',
			'invalid_method' => 'DELIVERY_METHOD_INVALID',
		),
		'open_questions'         => array(
			'real_providers'  => 'Каков финальный перечень служб доставки и их отображаемые названия?',
			'real_tariffs'    => 'Какова тарифная сетка и правила расчёта стоимости доставки, включая пороги бесплатной доставки?',
			'real_integration' => 'Какие требования предъявляются к реальной интеграции со службами доставки (протоколы, авторизация, обработка ошибок)?',
			'address_dependency' => 'Как источник реальных данных о способах доставки зависит от адреса и параметров заказа?',
		),
	);

	return $contract;
}

/**
 * Alias kept for callers that prefer an explicit stub contract name.
 *
 * @return array<string,mixed>
 */
function velotok_core_delivery_order_total_stub_contract() {
	return velotok_core_delivery_order_total_contract();
}

/**
 * Alias kept for callers that use a shorter delivery total name.
 *
 * @return array<string,mixed>
 */
function velotok_core_delivery_total_contract() {
	return velotok_core_delivery_order_total_contract();
}

/**
 * Format a monetary amount using the display settings of the contract.
 *
 * @param mixed $amount Amount to format.
 * @param bool  $free   Whether to use the free delivery label.
 * @return string
 */
function velotok_core_delivery_order_total_format_amount( $amount, $free = false ) {
	$contract = velotok_core_delivery_order_total_contract();
	$display  = $contract['display'];

	if ( $free ) {
		return $display['labels']['delivery_free'];
	}

	$value = is_numeric( $amount ) ? (float) $amount : 0.0;
	$text  = number_format(
		$value,
		(int) $display['decimals'],
		(string) $display['decimal_separator'],
		(string) $display['thousands_separator']
	);

	return trim( $text . ' ' . $display['currency_symbol'] );
}

/**
 * Alias for callers that use a shorter formatting name.
 *
 * @param mixed $amount Amount to format.
 * @param bool  $free   Whether to use the free delivery label.
 * @return string
 */
function velotok_core_format_delivery_amount( $amount, $free = false ) {
	return velotok_core_delivery_order_total_format_amount( $amount, $free );
}

/**
 * Build the order total summary with the delivery cost included.
 *
 * The delivery cost is calculated by the delivery options stub. A caller may
 * pass an explicit `delivery_cost` option (for example the shipping total that
 * WooCommerce already stored in an order) without any external request.
 *
 * @param mixed               $method  Delivery option identifier.
 * @param array<string,mixed> $order   Order data.
 * @param array<string,mixed> $options Optional overrides: subtotal, weight, delivery_cost.
 * @return array<string,mixed>
 */
function velotok_core_delivery_order_total( $method, $order = array(), $options = array() ) {
	$contract = velotok_core_delivery_order_total_contract();

	if ( ! is_array( $options ) ) {
		$options = array();
	}

	if ( ! function_exists( 'velotok_core_calculate_delivery_cost' ) ) {
		return array(
			'valid'    => false,
			'code'     => $contract['error_codes']['invalid_method'],
			'error'    => $contract['error_codes']['invalid_method'],
			'method'   => '',
			'subtotal' => null,
			'delivery_cost' => null,
			'total'    => null,
			'currency' => $contract['currency'],
			'source'   => $contract['source'],
			'mocked'   => true,
			'provider' => $contract['provider_id'],
		);
	}

	$delivery = velotok_core_calculate_delivery_cost( $method, $order, $options );

	if ( empty( $delivery['valid'] ) ) {
		return array(
			'valid'    => false,
			'code'     => $delivery['code'],
			'error'    => $delivery['error'],
			'method'   => '',
			'subtotal' => null,
			'delivery_cost' => null,
			'total'    => null,
			'currency' => $contract['currency'],
			'source'   => $contract['source'],
			'mocked'   => true,
			'provider' => $contract['provider_id'],
		);
	}

	$subtotal      = isset( $options['subtotal'] ) && is_numeric( $options['subtotal'] )
		? (float) $options['subtotal']
		: ( isset( $delivery['subtotal'] ) ? (float) $delivery['subtotal'] : 0.0 );
	$delivery_cost = isset( $options['delivery_cost'] ) && is_numeric( $options['delivery_cost'] )
		? (float) $options['delivery_cost']
		: (float) $delivery['amount'];

	$subtotal      = round( $subtotal, 2 );
	$delivery_cost = round( $delivery_cost, 2 );
	$total         = round( $subtotal + $delivery_cost, 2 );
	$free          = 0.0 === $delivery_cost;
	$display       = $contract['display'];
	$labels        = $display['labels'];

	$lines = array(
		'subtotal' => array(
			'key'       => 'subtotal',
			'label'     => $labels['subtotal'],
			'amount'    => $subtotal,
			'formatted' => velotok_core_delivery_order_total_format_amount( $subtotal ),
			'free'      => false,
			'mocked'    => true,
		),
		'delivery' => array(
			'key'       => 'delivery',
			'label'     => $labels['delivery'],
			'amount'    => $delivery_cost,
			'formatted' => velotok_core_delivery_order_total_format_amount( $delivery_cost, $free ),
			'free'      => $free,
			'method'    => $delivery['method'],
			'mocked'    => true,
		),
		'total'    => array(
			'key'       => 'total',
			'label'     => $labels['total'],
			'amount'    => $total,
			'formatted' => velotok_core_delivery_order_total_format_amount( $total ),
			'free'      => false,
			'includes_delivery' => true,
			'mocked'    => true,
		),
	);

	return array(
		'valid'             => true,
		'code'              => null,
		'error'             => null,
		'spec'              => $contract['spec'],
		'mocked'            => true,
		'source'            => $contract['source'],
		'provider'          => $contract['provider_id'],
		'method'            => $delivery['method'],
		'method_label'      => $delivery['method_label'],
		'delivery_method'   => $delivery['method'],
		'delivery_label'    => $delivery['method_label'],
		'subtotal'          => $subtotal,
		'delivery_cost'     => $delivery_cost,
		'delivery_free'     => $free,
		'total'             => $total,
		'currency'          => $contract['currency'],
		'includes_delivery' => true,
		'total_includes_delivery' => true,
		'display'           => $display,
		'lines'             => $lines,
		'breakdown'         => array(
			'subtotal'      => $subtotal,
			'delivery'      => $delivery_cost,
			'total'         => $total,
			'delivery_free' => $free,
			'mocked'        => true,
		),
		'delivery'          => $delivery,
	);
}

/**
 * Alias for callers that use a calculate-prefixed name.
 *
 * @param mixed               $method  Delivery option identifier.
 * @param array<string,mixed> $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_calculate_order_total_with_delivery( $method, $order = array(), $options = array() ) {
	return velotok_core_delivery_order_total( $method, $order, $options );
}

/**
 * Alias for callers that use a get-prefixed name.
 *
 * @param mixed               $method  Delivery option identifier.
 * @param array<string,mixed> $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,mixed>
 */
function velotok_core_get_delivery_order_total( $method, $order = array(), $options = array() ) {
	return velotok_core_delivery_order_total( $method, $order, $options );
}

/**
 * Return the display lines of the order total summary.
 *
 * @param mixed               $method  Delivery option identifier.
 * @param array<string,mixed> $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return array<string,array<string,mixed>>
 */
function velotok_core_delivery_order_total_lines( $method, $order = array(), $options = array() ) {
	$summary = velotok_core_delivery_order_total( $method, $order, $options );

	return isset( $summary['lines'] ) && is_array( $summary['lines'] ) ? $summary['lines'] : array();
}

/**
 * Render the order total summary with the delivery line.
 *
 * The markup is escaped and contains `data-mocked="true"` so the storefront
 * makes the mocked origin visible to tests and reviewers.
 *
 * @param mixed               $method  Delivery option identifier.
 * @param array<string,mixed> $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return string Empty string for an invalid delivery selection.
 */
function velotok_core_render_delivery_order_total( $method, $order = array(), $options = array() ) {
	$summary = velotok_core_delivery_order_total( $method, $order, $options );

	if ( empty( $summary['valid'] ) || empty( $summary['lines'] ) ) {
		return '';
	}

	$html = '<dl class="velotok-delivery-total" data-mocked="true" data-source="stub">';

	foreach ( $summary['lines'] as $line ) {
		$class = 'velotok-delivery-total__' . $line['key'];

		if ( 'delivery' === $line['key'] ) {
			$class .= ' velotok-delivery-total__delivery';
		}

		$html .= '<div class="' . htmlspecialchars( $class, ENT_QUOTES, 'UTF-8' ) . '">';
		$html .= '<dt>' . htmlspecialchars( $line['label'], ENT_QUOTES, 'UTF-8' ) . '</dt>';
		$html .= '<dd>' . htmlspecialchars( $line['formatted'], ENT_QUOTES, 'UTF-8' ) . '</dd>';
		$html .= '</div>';
	}

	$html .= '</dl>';

	return $html;
}

/**
 * Alias for callers that use an html-prefixed name.
 *
 * @param mixed               $method  Delivery option identifier.
 * @param array<string,mixed> $order   Order data.
 * @param array<string,mixed> $options Optional overrides.
 * @return string
 */
function velotok_core_delivery_order_total_html( $method, $order = array(), $options = array() ) {
	return velotok_core_render_delivery_order_total( $method, $order, $options );
}

/**
 * Return the open questions that block real delivery integrations.
 *
 * @return array<string,string>
 */
function velotok_core_delivery_order_total_open_questions() {
	$contract = velotok_core_delivery_order_total_contract();

	return $contract['open_questions'];
}
