<?php
/**
 * Idempotent store provisioning for «Велоток».
 *
 * Run from the WordPress root:
 *   wp eval-file wp/cli/provision.php
 *
 * Creates the Russian shipping zone with СДЭК, Почта РФ and showroom pickup,
 * enables the payment gateways and installs the Russian language pack. The
 * result is reported through WP-CLI; the command can be run repeatedly.
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

$provisioning_module = dirname( __DIR__ ) . '/plugins/velotok-core/includes/provisioning.php';

if ( ! function_exists( 'velotok_core_provision_store' ) && is_readable( $provisioning_module ) ) {
	require_once $provisioning_module;
}

if ( ! function_exists( 'velotok_core_provision_store' ) ) {
	$message = 'Модуль провижининга магазина недоступен.';

	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::error( $message );
	}

	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
}

$result = velotok_core_provision_store();

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::log(
		sprintf(
			'Зона доставки «%s» (ID %d), способов: %d.',
			$result['zone']['name'],
			(int) $result['zone']['id'],
			count( $result['zone']['methods'] )
		)
	);

	foreach ( $result['payment_gateways'] as $gateway_id => $gateway ) {
		WP_CLI::log(
			sprintf(
				'Шлюз «%s»: %s.',
				$gateway_id,
				! empty( $gateway['enabled'] ) ? 'включён' : 'выключен'
			)
		);
	}

	if ( ! empty( $result['language']['applied'] ) ) {
		WP_CLI::log( 'Языковой пакет ru_RU проверен.' );
	}

	foreach ( $result['warnings'] as $warning ) {
		WP_CLI::warning( $warning );
	}

	if ( ! empty( $result['checkout_ready'] ) ) {
		WP_CLI::success( 'Провижининг завершён: checkout готов создавать заказы.' );
	} else {
		WP_CLI::warning( 'Провижининг завершён, но checkout не готов: нет шлюза или способа доставки.' );
	}
} else {
	echo esc_html(
		sprintf(
			'Провижининг завершён: зона «%s», способов %d, checkout_ready=%s.',
			$result['zone']['name'],
			count( $result['zone']['methods'] ),
			! empty( $result['checkout_ready'] ) ? 'yes' : 'no'
		)
	) . PHP_EOL;
}
