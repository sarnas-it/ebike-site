<?php
/**
 * Contract test for explicit pickup point unavailability and failure handling.
 *
 * Requirement TESTEB-33 / spec delivery:
 * «Поведение при недоступности или сбое списка пунктов SHALL быть явным».
 *
 * The point list must never turn an unavailable or broken provider response
 * into a silent successful empty list. Every failure is returned with a stable
 * code, a message and a visible flag so the customer and the administrator can
 * see what happened.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-pickup-points-failure-contract-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$module_path = getenv( 'VELOTOK_DELIVERY_PICKUP_POINTS_FAILURE_STUB' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__ ) . '/includes/delivery-zones.php';
}

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль пунктов выдачи доставки.' . PHP_EOL );
	exit( 1 );
}

require_once $module_path;

$failures = array();

/**
 * Assert a pickup point failure condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_delivery_pickup_failure_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/**
		 * Error message.
		 *
		 * @var string
		 */
		private $message;

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( $code = '', $message = '' ) {
			unset( $code );

			$this->message = (string) $message;
		}

		/**
		 * Return the error message.
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}
}

$contract = velotok_core_delivery_zones_contract();

velotok_delivery_pickup_failure_assert(
	isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-33', $contract['requirement_ids'], true ),
	'Контракт доставки должен ссылаться на TESTEB-33.'
);
velotok_delivery_pickup_failure_assert(
	isset( $contract['pickup_points']['failure_requirement'] ) && 'TESTEB-33' === $contract['pickup_points']['failure_requirement'],
	'Раздел пунктов выдачи должен объявлять требование TESTEB-33 для сбоев.'
);
velotok_delivery_pickup_failure_assert(
	isset( $contract['pickup_points']['failure_explicit'] ) && true === $contract['pickup_points']['failure_explicit'],
	'Сбой списка пунктов выдачи должен быть явным.'
);
velotok_delivery_pickup_failure_assert(
	isset( $contract['pickup_points']['failure_visible'] ) && true === $contract['pickup_points']['failure_visible'],
	'Сбой списка пунктов выдачи должен быть видимым.'
);
velotok_delivery_pickup_failure_assert(
	isset( $contract['pickup_points_failure'] ) && 'TESTEB-33' === $contract['pickup_points_failure']['requirement'],
	'Контракт должен содержать раздел поведения при сбое пунктов выдачи.'
);
velotok_delivery_pickup_failure_assert(
	isset( $contract['pickup_points_unavailable'] ) && 'TESTEB-33' === $contract['pickup_points_unavailable']['requirement'],
	'Недоступность пунктов выдачи должна иметь раздел контракта TESTEB-33.'
);
velotok_delivery_pickup_failure_assert(
	isset( $contract['pickup_points_error'] ) && 'TESTEB-33' === $contract['pickup_points_error']['requirement'],
	'Ошибка провайдера пунктов выдачи должна иметь раздел контракта TESTEB-33.'
);

foreach ( array( 'pickup_points_failure', 'pickup_points_unavailable', 'pickup_points_error' ) as $section ) {
	velotok_delivery_pickup_failure_assert(
		isset( $contract[ $section ]['explicit'] ) && true === $contract[ $section ]['explicit'],
		sprintf( 'Раздел «%s» должен объявлять явное поведение.', $section )
	);
	velotok_delivery_pickup_failure_assert(
		isset( $contract[ $section ]['visible'] ) && true === $contract[ $section ]['visible'],
		sprintf( 'Раздел «%s» должен объявлять видимость ошибки.', $section )
	);
	velotok_delivery_pickup_failure_assert(
		isset( $contract[ $section ]['fallback_to_empty_list'] ) && false === $contract[ $section ]['fallback_to_empty_list'],
		sprintf( 'Раздел «%s» не должен подменять сбой пустым успешным списком.', $section )
	);
	velotok_delivery_pickup_failure_assert(
		isset( $contract[ $section ]['fallback_to_fake_points'] ) && false === $contract[ $section ]['fallback_to_fake_points'],
		sprintf( 'Раздел «%s» не должен подменять сбой фиктивными пунктами.', $section )
	);
}

$address = array(
	'country'     => 'RU',
	'postcode'    => '101000',
	'city'        => 'Москва',
	'coordinates' => array( 'lat' => 55.7558, 'lon' => 37.6173 ),
);

$point = array(
	'id'       => 'CDEK-1',
	'provider' => 'cdek',
	'name'     => 'СДЭК в центре',
	'address'  => 'Москва, Арбат, 1',
	'country'  => 'RU',
	'lat'      => 55.7559,
	'lon'      => 37.6174,
);

$unavailable = velotok_core_delivery_pickup_points( $address, null );

velotok_delivery_pickup_failure_assert( false === $unavailable['ok'], 'Недоступный список пунктов не должен выглядеть успешным.' );
velotok_delivery_pickup_failure_assert( false === $unavailable['available'], 'Недоступный список пунктов не должен быть доступным.' );
velotok_delivery_pickup_failure_assert( 'DELIVERY_PICKUP_POINTS_UNAVAILABLE' === $unavailable['code'], 'Недоступный список должен иметь явный код UNAVAILABLE.' );
velotok_delivery_pickup_failure_assert( ! empty( $unavailable['message'] ), 'Недоступный список должен иметь сообщение.' );
velotok_delivery_pickup_failure_assert( ! empty( $unavailable['errors'] ), 'Недоступный список должен возвращать видимую ошибку.' );
velotok_delivery_pickup_failure_assert(
	isset( $unavailable['failure']['code'] ) && 'DELIVERY_PICKUP_POINTS_UNAVAILABLE' === $unavailable['failure']['code'],
	'Недоступный список должен сохранять ошибку в явном поле failure.'
);
velotok_delivery_pickup_failure_assert( empty( $unavailable['points'] ), 'Недоступный список не должен придумывать пункты.' );
velotok_delivery_pickup_failure_assert( ! empty( $unavailable['errors'][0]['visible'] ), 'Ошибка недоступности должна быть видимой.' );

$malformed = velotok_core_delivery_pickup_points( $address, 'broken' );
velotok_delivery_pickup_failure_assert( false === $malformed['ok'], 'Некорректный список не должен выглядеть успешным.' );
velotok_delivery_pickup_failure_assert( 'DELIVERY_PICKUP_POINTS_ERROR' === $malformed['code'], 'Некорректный список должен иметь явный код ERROR.' );
velotok_delivery_pickup_failure_assert( ! empty( $malformed['errors'][0]['visible'] ), 'Некорректный список должен быть видимой ошибкой.' );

$throwing = velotok_core_delivery_pickup_points(
	$address,
	null,
	array(
		'pickup_providers' => array(
			'cdek' => function () {
				throw new RuntimeException( 'СДЭК временно недоступен' );
			},
		),
	)
);
velotok_delivery_pickup_failure_assert( false === $throwing['ok'], 'Исключение провайдера не должно выглядеть успешным.' );
velotok_delivery_pickup_failure_assert( 'DELIVERY_PICKUP_POINTS_ERROR' === $throwing['code'], 'Исключение провайдера должно давать код ERROR.' );
velotok_delivery_pickup_failure_assert( ! empty( $throwing['errors'][0]['message'] ), 'Исключение провайдера должно иметь видимое сообщение.' );
velotok_delivery_pickup_failure_assert( 'cdek' === $throwing['errors'][0]['provider'], 'Ошибка должна указывать провайдера.' );

$empty_provider = velotok_core_delivery_pickup_points(
	$address,
	null,
	array(
		'pickup_providers' => array(
			'cdek' => function () {
				return null;
			},
			'russian_post' => function () {
				return false;
			},
		),
	)
);
velotok_delivery_pickup_failure_assert( false === $empty_provider['ok'], 'Провайдер без данных не должен давать успешный список.' );
velotok_delivery_pickup_failure_assert( 'DELIVERY_PICKUP_POINTS_ERROR' === $empty_provider['code'], 'Провайдер без данных должен давать явный код ERROR.' );
velotok_delivery_pickup_failure_assert( 2 === count( $empty_provider['errors'] ), 'Каждый недоступный провайдер должен давать отдельную видимую ошибку.' );

$wp_error = velotok_core_delivery_pickup_points(
	$address,
	null,
	array(
		'pickup_providers' => array(
			'cdek' => function () {
				return new WP_Error( 'cdek_down', 'СДЭК вернул ошибку' );
			},
		),
	)
);
velotok_delivery_pickup_failure_assert( false === $wp_error['ok'], 'WP_Error провайдера не должен выглядеть успешным.' );
velotok_delivery_pickup_failure_assert( 'DELIVERY_PICKUP_POINTS_ERROR' === $wp_error['code'], 'WP_Error провайдера должен давать код ERROR.' );
velotok_delivery_pickup_failure_assert( 'СДЭК вернул ошибку' === $wp_error['errors'][0]['message'], 'WP_Error должен передавать сообщение провайдера.' );

$not_callable = velotok_core_delivery_pickup_points(
	$address,
	null,
	array(
		'pickup_providers' => array(
			'cdek' => null,
		),
	)
);
velotok_delivery_pickup_failure_assert( false === $not_callable['ok'], 'Недоступный адаптер не должен выглядеть успешным.' );
velotok_delivery_pickup_failure_assert( 'DELIVERY_PICKUP_POINTS_ERROR' === $not_callable['code'], 'Недоступный адаптер должен давать явный код ERROR.' );

$partial = velotok_core_delivery_pickup_points(
	$address,
	null,
	array(
		'pickup_providers' => array(
			'cdek' => function () {
				throw new RuntimeException( 'СДЭК временно недоступен' );
			},
			'russian_post' => function () use ( $point ) {
				return array( 'points' => array( $point ) );
			},
		),
	)
);
velotok_delivery_pickup_failure_assert( true === $partial['ok'], 'Рабочий адаптер должен сохранять доступный список.' );
velotok_delivery_pickup_failure_assert( array( 'CDEK-1' ) === array_column( $partial['points'], 'id' ), 'Рабочий адаптер должен отдавать свои пункты.' );
velotok_delivery_pickup_failure_assert( true === $partial['degraded'], 'Частичный сбой должен быть отмечен как degraded.' );
velotok_delivery_pickup_failure_assert( 'DELIVERY_PICKUP_POINTS_ERROR' === $partial['code'], 'Частичный сбой должен иметь явный код ERROR.' );
velotok_delivery_pickup_failure_assert( ! empty( $partial['errors'] ), 'Частичный сбой должен возвращать видимую ошибку.' );

$zones = array(
	array(
		'zone_id'          => 10,
		'zone_name'        => 'Москва',
		'zone_order'       => 1,
		'zone_locations'   => array(
			array( 'type' => 'postcode', 'code' => '101*' ),
		),
		'shipping_methods' => array(
			array(
				'instance_id' => 101,
				'method_id'   => 'cdek',
				'title'       => 'СДЭК',
				'enabled'     => true,
				'settings'    => array( 'base' => 500, 'per_kg' => 100 ),
			),
		),
	),
);

$unavailable_options = velotok_core_delivery_zone_methods(
	$address,
	$zones,
	array( 'order' => array( 'subtotal' => 1000, 'weight' => 1 ) )
);
$unavailable_codes = array_column( $unavailable_options['errors'], 'code' );
velotok_delivery_pickup_failure_assert(
	in_array( 'DELIVERY_PICKUP_POINTS_UNAVAILABLE', $unavailable_codes, true ),
	'Недоступность пунктов должна попадать в видимые ошибки способов доставки.'
);
velotok_delivery_pickup_failure_assert(
	isset( $unavailable_options['methods']['local_pickup']['pickup_points_code'] ) && 'DELIVERY_PICKUP_POINTS_UNAVAILABLE' === $unavailable_options['methods']['local_pickup']['pickup_points_code'],
	'Самовывоз должен явно показывать код недоступности пунктов.'
);
velotok_delivery_pickup_failure_assert(
	! empty( $unavailable_options['methods']['local_pickup']['points_visible_error'] ),
	'Недоступность пунктов самовывоза должна быть видимой ошибкой.'
);
velotok_delivery_pickup_failure_assert(
	isset( $unavailable_options['methods']['local_pickup']['available'] ) && true === $unavailable_options['methods']['local_pickup']['available'],
	'Недоступность списка не должна отключать самовывоз.'
);
velotok_delivery_pickup_failure_assert(
	isset( $unavailable_options['methods']['cdek']['pickup_points_code'] ) && 'DELIVERY_PICKUP_POINTS_UNAVAILABLE' === $unavailable_options['methods']['cdek']['pickup_points_code'],
	'Способ СДЭК должен явно показывать недоступность списка пунктов.'
);
velotok_delivery_pickup_failure_assert(
	empty( $unavailable_options['methods']['cdek']['pickup_points'] ),
	'Способ СДЭК не должен подменять недоступный список фиктивными пунктами.'
);

$failed_zones = velotok_core_delivery_zone_methods(
	$address,
	$zones,
	array(
		'order'            => array( 'subtotal' => 1000, 'weight' => 1 ),
		'pickup_providers' => array(
			'cdek' => function () {
				return new WP_Error( 'cdek_down', 'СДЭК временно недоступен' );
			},
		),
	)
);
$failed_codes = array_column( $failed_zones['errors'], 'code' );
velotok_delivery_pickup_failure_assert(
	in_array( 'DELIVERY_PICKUP_POINTS_ERROR', $failed_codes, true ),
	'Сбой провайдера пунктов должен попадать в видимые ошибки способов доставки.'
);
velotok_delivery_pickup_failure_assert(
	isset( $failed_zones['pickup_points_result']['failure']['code'] ) && 'DELIVERY_PICKUP_POINTS_ERROR' === $failed_zones['pickup_points_result']['failure']['code'],
	'Сбой провайдера пунктов должен явно сохраняться в результате.'
);
velotok_delivery_pickup_failure_assert(
	empty( $failed_zones['pickup_points'] ),
	'Сбой провайдера не должен подменяться фиктивным списком пунктов.'
);

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: недоступность и сбой списка пунктов выдачи обрабатываются явно.' . PHP_EOL;
