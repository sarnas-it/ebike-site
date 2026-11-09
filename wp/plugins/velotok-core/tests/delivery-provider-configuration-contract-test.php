<?php
/**
 * Contract test for the administrator-visible provider configuration error.
 *
 * Requirement TESTEB-43 / spec delivery:
 * «Администратор SHALL видеть ошибку конфигурации провайдера».
 *
 * The test proves that a broken provider configuration is never silent and
 * never replaced with a fake tariff: it is returned as an explicit, visible
 * error carrying the DELIVERY_PROVIDER_CONFIGURATION_ERROR code and rendered
 * as a WooCommerce/WordPress admin notice for users allowed to manage the
 * store.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/delivery-provider-configuration-contract-test.php
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['velotok_provider_configuration_actions'] = array();
$GLOBALS['velotok_provider_configuration_admin']   = true;

/**
 * Record an action registration.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted args.
 * @return bool
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	unset( $priority, $args );

	$GLOBALS['velotok_provider_configuration_actions'][ $hook ][] = $callback;

	return true;
}

/**
 * Resolve an administrator capability in the test.
 *
 * @param string $capability Capability.
 * @return bool
 */
function current_user_can( $capability ) {
	unset( $capability );

	return ! empty( $GLOBALS['velotok_provider_configuration_admin'] );
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error implementation for the standalone test.
	 */
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		private $code;

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
			$this->code    = (string) $code;
			$this->message = (string) $message;
		}

		/**
		 * Return the error code.
		 *
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
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

$module_path = getenv( 'VELOTOK_DELIVERY_PROVIDER_CONFIGURATION_STUB' );

if ( ! is_string( $module_path ) || '' === $module_path ) {
	$module_path = dirname( __DIR__ ) . '/includes/delivery-zones.php';
}

if ( ! is_readable( $module_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль ошибок конфигурации доставки.' . PHP_EOL );
	exit( 1 );
}

require_once $module_path;

$failures = array();

/**
 * Assert an administrator-visible provider configuration condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_provider_configuration_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Build a WooCommerce zone with the supplied shipping methods.
 *
 * @param array $methods Shipping methods.
 * @return array
 */
function velotok_provider_configuration_zone( $methods ) {
	return array(
		array(
			'zone_id'          => 10,
			'zone_name'        => 'Москва',
			'zone_order'       => 1,
			'zone_locations'   => array(
				array( 'type' => 'postcode', 'code' => '101*' ),
			),
			'shipping_methods' => $methods,
		),
	);
}

/**
 * Build one delivery method.
 *
 * @param array $extra Extra method data.
 * @param array $settings Method settings.
 * @return array
 */
function velotok_provider_configuration_method( $extra = array(), $settings = array() ) {
	return array_merge(
		array(
			'instance_id' => 101,
			'method_id'   => 'cdek',
			'title'       => 'СДЭК',
			'enabled'     => true,
			'settings'    => array_merge( array( 'base' => 500, 'per_kg' => 100 ), $settings ),
		),
		$extra
	);
}

$contract = velotok_core_delivery_zones_contract();
$section  = isset( $contract['provider_configuration'] ) ? $contract['provider_configuration'] : array();

velotok_provider_configuration_assert(
	isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-43', $contract['requirement_ids'], true ),
	'Контракт доставки должен ссылаться на TESTEB-43.'
);
velotok_provider_configuration_assert(
	isset( $section['requirement'] ) && 'TESTEB-43' === $section['requirement'],
	'Раздел конфигурации провайдера должен ссылаться на TESTEB-43.'
);
velotok_provider_configuration_assert(
	isset( $section['error_code'] ) && 'DELIVERY_PROVIDER_CONFIGURATION_ERROR' === $section['error_code'],
	'Ошибка конфигурации провайдера должна иметь стабильный код.'
);
velotok_provider_configuration_assert(
	isset( $section['admin_visible'] ) && true === $section['admin_visible'],
	'Ошибка конфигурации провайдера должна быть видна администратору.'
);
velotok_provider_configuration_assert(
	isset( $section['visible'] ) && true === $section['visible'] && isset( $section['explicit'] ) && true === $section['explicit'],
	'Ошибка конфигурации провайдера должна быть явной и видимой.'
);
velotok_provider_configuration_assert(
	isset( $section['customer_visible'] ) && false === $section['customer_visible'],
	'Ошибка конфигурации провайдера предназначена администратору, а не покупателю.'
);
velotok_provider_configuration_assert(
	isset( $section['fallback_tariff'] ) && false === $section['fallback_tariff'],
	'Ошибка конфигурации не должна подменяться фиктивным тарифом.'
);
velotok_provider_configuration_assert(
	isset( $section['providers'] ) && array( 'cdek', 'russian_post' ) === $section['providers'],
	'Ошибка конфигурации должна охватывать оба провайдера.'
);
velotok_provider_configuration_assert(
	isset( $section['notice_hook'] ) && 'admin_notices' === $section['notice_hook'],
	'Ошибка конфигурации должна выводиться через административные уведомления.'
);

velotok_provider_configuration_assert(
	isset( $GLOBALS['velotok_provider_configuration_actions']['admin_notices'] ),
	'Должен быть зарегистрирован hook admin_notices для ошибок конфигурации.'
);

$direct = velotok_core_delivery_provider_configuration_error( 'cdek', 'Не задан API-ключ СДЭК' );

velotok_provider_configuration_assert( false === $direct['ok'], 'Ошибка конфигурации не должна выглядеть успешной.' );
velotok_provider_configuration_assert( 'DELIVERY_PROVIDER_CONFIGURATION_ERROR' === $direct['code'], 'Ошибка конфигурации должна иметь стабильный код.' );
velotok_provider_configuration_assert( 'cdek' === $direct['provider'], 'Ошибка конфигурации должна указывать провайдера.' );
velotok_provider_configuration_assert( 'СДЭК' === $direct['provider_label'], 'Ошибка конфигурации должна содержать название провайдера.' );
velotok_provider_configuration_assert( 'Не задан API-ключ СДЭК' === $direct['message'], 'Ошибка конфигурации должна передавать сообщение.' );
velotok_provider_configuration_assert( true === $direct['admin_visible'] && true === $direct['admin'], 'Ошибка конфигурации должна быть административной.' );
velotok_provider_configuration_assert( true === $direct['visible'] && true === $direct['explicit'], 'Ошибка конфигурации должна быть явной.' );
velotok_provider_configuration_assert( false === $direct['fallback_tariff'] && false === $direct['fake_tariff'], 'Ошибка конфигурации не должна подменяться тарифом.' );
velotok_provider_configuration_assert( 'TESTEB-43' === $direct['requirement'], 'Ошибка конфигурации должна ссылаться на TESTEB-43.' );

$explicit_zone = velotok_provider_configuration_zone(
	array(
		velotok_provider_configuration_method(
			array(),
			array( 'configuration_error' => 'Не задан договор СДЭК' )
		),
	)
);
$explicit_errors = velotok_core_delivery_provider_configuration_errors( $explicit_zone );

velotok_provider_configuration_assert( 1 === count( $explicit_errors ), 'Ошибка из настроек способа должна быть найдена.' );
velotok_provider_configuration_assert( 'DELIVERY_PROVIDER_CONFIGURATION_ERROR' === $explicit_errors[0]['code'], 'Ошибка из настроек должна иметь код конфигурации.' );
velotok_provider_configuration_assert( 'cdek' === $explicit_errors[0]['provider'], 'Ошибка из настроек должна указывать СДЭК.' );
velotok_provider_configuration_assert( 'Не задан договор СДЭК' === $explicit_errors[0]['message'], 'Сообщение из настроек должно сохраняться.' );
velotok_provider_configuration_assert( true === $explicit_errors[0]['admin_visible'], 'Ошибка из настроек должна быть видна администратору.' );

$unconfigured_zone = velotok_provider_configuration_zone(
	array(
		velotok_provider_configuration_method(
			array(
				'rate_provider' => function () {
					return array(
						'configured' => false,
						'error'      => 'Не задан API-ключ провайдера',
					);
				},
			)
		),
	)
);
$unconfigured_errors = velotok_core_delivery_provider_configuration_errors( $unconfigured_zone );

velotok_provider_configuration_assert( 1 === count( $unconfigured_errors ), 'Ненастроенный адаптер должен давать ошибку конфигурации.' );
velotok_provider_configuration_assert( 'DELIVERY_PROVIDER_CONFIGURATION_ERROR' === $unconfigured_errors[0]['code'], 'Ненастроенный адаптер должен иметь код конфигурации.' );
velotok_provider_configuration_assert( 'Не задан API-ключ провайдера' === $unconfigured_errors[0]['message'], 'Ненастроенный адаптер должен передавать причину.' );

$wp_error_zone = velotok_provider_configuration_zone(
	array(
		velotok_provider_configuration_method(
			array(
				'rate_provider' => function () {
					return new WP_Error( 'DELIVERY_PROVIDER_CONFIGURATION_ERROR', 'Почтовый индекс склада не задан' );
				},
			)
		),
	)
);
$wp_error_errors = velotok_core_delivery_provider_configuration_errors( $wp_error_zone );

velotok_provider_configuration_assert( 1 === count( $wp_error_errors ), 'WP_Error конфигурации должен давать ошибку конфигурации.' );
velotok_provider_configuration_assert( 'DELIVERY_PROVIDER_CONFIGURATION_ERROR' === $wp_error_errors[0]['code'], 'WP_Error конфигурации должен иметь код конфигурации.' );
velotok_provider_configuration_assert( 'Почтовый индекс склада не задан' === $wp_error_errors[0]['message'], 'WP_Error конфигурации должен передавать сообщение.' );

$outage_zone = velotok_provider_configuration_zone(
	array(
		velotok_provider_configuration_method(
			array(
				'rate_provider' => function () {
					throw new RuntimeException( 'СДЭК временно недоступен' );
				},
			)
		),
	)
);
$outage_errors = velotok_core_delivery_provider_configuration_errors( $outage_zone );

velotok_provider_configuration_assert( empty( $outage_errors ), 'Сбой провайдера не является ошибкой конфигурации.' );

$working_zone = velotok_provider_configuration_zone(
	array(
		velotok_provider_configuration_method(
			array(
				'rate_provider' => function () {
					return array( 'amount' => 600, 'currency' => 'RUB' );
				},
			)
		),
	)
);
$working_errors = velotok_core_delivery_provider_configuration_errors( $working_zone );

velotok_provider_configuration_assert( empty( $working_errors ), 'Рабочая конфигурация не должна давать ошибку.' );

$disabled_zone = velotok_provider_configuration_zone(
	array(
		velotok_provider_configuration_method(
			array( 'enabled' => false ),
			array( 'configuration_error' => 'Не задан договор СДЭК' )
		),
	)
);
$disabled_errors = velotok_core_delivery_provider_configuration_errors( $disabled_zone );

velotok_provider_configuration_assert( empty( $disabled_errors ), 'Отключённый способ не должен показывать ошибку конфигурации.' );

$GLOBALS['velotok_provider_configuration_admin'] = true;

ob_start();
$notice_html = velotok_core_delivery_provider_configuration_admin_notice( $explicit_zone );
$rendered    = ob_get_clean();

velotok_provider_configuration_assert( '' !== $rendered, 'Администратор должен увидеть уведомление об ошибке конфигурации.' );
velotok_provider_configuration_assert( $notice_html === $rendered, 'Уведомление должно возвращаться и выводиться одинаково.' );
velotok_provider_configuration_assert( false !== strpos( $rendered, 'notice-error' ), 'Уведомление должно быть ошибкой WordPress.' );
velotok_provider_configuration_assert( false !== strpos( $rendered, 'velotok-delivery-provider-configuration' ), 'Уведомление должно иметь явный класс.' );
velotok_provider_configuration_assert( false !== strpos( $rendered, 'data-provider="cdek"' ), 'Уведомление должно указывать провайдера.' );
velotok_provider_configuration_assert( false !== strpos( $rendered, 'data-code="DELIVERY_PROVIDER_CONFIGURATION_ERROR"' ), 'Уведомление должно указывать код ошибки.' );
velotok_provider_configuration_assert( false !== strpos( $rendered, 'data-admin-visible="true"' ), 'Уведомление должно быть помечено как административное.' );
velotok_provider_configuration_assert( false !== strpos( $rendered, 'СДЭК' ), 'Администратор должен увидеть название провайдера.' );
velotok_provider_configuration_assert( false !== strpos( $rendered, 'Не задан договор СДЭК' ), 'Администратор должен увидеть причину ошибки.' );

$escaped = velotok_core_delivery_provider_configuration_notice_html(
	array(
		'provider'       => 'russian_post',
		'provider_label' => 'Почта РФ',
		'code'           => 'DELIVERY_PROVIDER_CONFIGURATION_ERROR',
		'message'        => '<script>alert(1)</script>',
	)
);

velotok_provider_configuration_assert( false === strpos( $escaped, '<script>' ), 'Сообщение об ошибке должно экранироваться.' );
velotok_provider_configuration_assert( false !== strpos( $escaped, '&lt;script&gt;' ), 'Экранированное сообщение должно оставаться читаемым.' );

$GLOBALS['velotok_provider_configuration_admin'] = false;
ob_start();
$hidden_notice = velotok_core_delivery_provider_configuration_admin_notice( $explicit_zone );
$hidden_output = ob_get_clean();

velotok_provider_configuration_assert( '' === $hidden_notice && '' === $hidden_output, 'Не-администратор не должен видеть ошибку конфигурации.' );

$GLOBALS['velotok_provider_configuration_admin'] = true;
ob_start();
$clean_notice = velotok_core_delivery_provider_configuration_admin_notice( $working_zone );
$clean_output = ob_get_clean();

velotok_provider_configuration_assert( '' === $clean_notice && '' === $clean_output, 'Без ошибки конфигурации уведомление не выводится.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: администратор видит ошибку конфигурации провайдера доставки (TESTEB-43).' . PHP_EOL;
