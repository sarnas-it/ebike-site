<?php
/**
 * Contract test for velotok-commerce stubs.
 *
 * Run from the WordPress root:
 * wp eval-file wp-content/plugins/velotok-commerce/tests/contract-test.php
 *
 * The test fails when a mocked stub or its explicit mocked marker is missing.
 *
 * @package Velotok_Commerce
 */

defined( 'ABSPATH' ) || exit;

$velotok_contract_failures = array();

/**
 * Record a contract assertion.
 *
 * @param bool   $condition Assertion condition.
 * @param string $message   Failure message.
 */
function velotok_commerce_contract_assert( $condition, $message ) {
	global $velotok_contract_failures;

	if ( ! $condition ) {
		$velotok_contract_failures[] = $message;
	}
}

/**
 * Finish the contract test.
 */
function velotok_commerce_contract_finish() {
	global $velotok_contract_failures;

	if ( ! empty( $velotok_contract_failures ) ) {
		$message = "Контрактные проверки не пройдены:\n- " . implode( "\n- ", $velotok_contract_failures );

		if ( class_exists( 'WP_CLI' ) ) {
			WP_CLI::error( $message );
		}

		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}

	$message = 'Контрактные проверки пройдены: оплата ЮKassa, доставка и приёмка контента.';

	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::success( $message );
	} else {
		echo $message . PHP_EOL;
	}
}

$velotok_plugin_file = dirname( __DIR__ ) . '/velotok-commerce.php';

if ( ! defined( 'VELOTOK_COMMERCE_VERSION' ) ) {
	require_once $velotok_plugin_file;
}

velotok_commerce_contract_assert(
	defined( 'VELOTOK_COMMERCE_MOCKED' ) && true === VELOTOK_COMMERCE_MOCKED,
	'Не найден обязательный маркер заглушки VELOTOK_COMMERCE_MOCKED = true.'
);

velotok_commerce_contract_assert(
	function_exists( 'velotok_commerce_payment_contract' ),
	'Отсутствует контракт оплаты velotok_commerce_payment_contract().'
);

if ( function_exists( 'velotok_commerce_payment_contract' ) ) {
	$payment = velotok_commerce_payment_contract();

	velotok_commerce_contract_assert( is_array( $payment ), 'Контракт оплаты должен возвращать массив.' );
	velotok_commerce_contract_assert( 'yookassa' === ( $payment['provider'] ?? '' ), 'Платёжный провайдер должен быть yookassa.' );
	velotok_commerce_contract_assert( 'velotok_yookassa' === ( $payment['gateway_id'] ?? '' ), 'ID платёжного шлюза должен быть velotok_yookassa.' );
	velotok_commerce_contract_assert( true === ( $payment['mocked'] ?? false ), 'Контракт оплаты должен быть помечен mocked: true.' );
	velotok_commerce_contract_assert( isset( $payment['modes']['test'], $payment['modes']['prod'] ), 'Контракт оплаты должен описывать тестовый и боевой режимы.' );
	velotok_commerce_contract_assert( true === ( $payment['modes']['test']['mocked'] ?? false ), 'Тестовый режим ЮKassa должен быть помечен mocked: true.' );
	velotok_commerce_contract_assert( true === ( $payment['modes']['prod']['mocked'] ?? false ), 'Боевой режим ЮKassa должен быть помечен mocked: true.' );
	velotok_commerce_contract_assert( 'RUB' === ( $payment['currency'] ?? '' ), 'Валюта контракта оплаты должна быть RUB.' );
	velotok_commerce_contract_assert( true === velotok_commerce_payment_is_mocked(), 'velotok_commerce_payment_is_mocked() должен возвращать true.' );
	velotok_commerce_contract_assert( empty( $payment['credentials'] ), 'Контракт оплаты не должен содержать реальные учётные данные.' );
}

if ( class_exists( 'WC_Payment_Gateway' ) ) {
	velotok_commerce_contract_assert(
		class_exists( 'Velotok_Commerce_Gateway_Yookassa' ),
		'Платёжный шлюз-заглушка Velotok_Commerce_Gateway_Yookassa не зарегистрирован.'
	);
}

velotok_commerce_contract_assert(
	function_exists( 'velotok_commerce_delivery_contract' ),
	'Отсутствует контракт доставки velotok_commerce_delivery_contract().'
);

if ( function_exists( 'velotok_commerce_delivery_contract' ) ) {
	$delivery = velotok_commerce_delivery_contract();
	$methods  = velotok_commerce_delivery_methods();

	velotok_commerce_contract_assert( true === ( $delivery['mocked'] ?? false ), 'Контракт доставки должен быть помечен mocked: true.' );
	velotok_commerce_contract_assert( true === velotok_commerce_delivery_is_mocked(), 'Все способы доставки должны быть помечены mocked: true.' );

	foreach ( array( 'pochta', 'cdek', 'other' ) as $method_id ) {
		velotok_commerce_contract_assert( isset( $methods[ $method_id ] ), sprintf( 'Отсутствует способ доставки «%s».', $method_id ) );

		if ( isset( $methods[ $method_id ] ) ) {
			velotok_commerce_contract_assert( true === ( $methods[ $method_id ]['mocked'] ?? false ), sprintf( 'Способ доставки «%s» должен быть помечен mocked: true.', $method_id ) );
		}
	}

	velotok_commerce_contract_assert( null === velotok_commerce_delivery_method( 'unknown' ), 'Неизвестный способ доставки должен возвращать null.' );
}

velotok_commerce_contract_assert(
	function_exists( 'velotok_commerce_content_acceptance_contract' ),
	'Отсутствует контракт приёмки контента velotok_commerce_content_acceptance_contract().'
);

if ( function_exists( 'velotok_commerce_content_acceptance_contract' ) ) {
	$acceptance = velotok_commerce_content_acceptance_contract();

	velotok_commerce_contract_assert( true === ( $acceptance['required'] ?? false ), 'Приёмка контента должна быть обязательной.' );
	velotok_commerce_contract_assert( true === ( $acceptance['human_only'] ?? false ), 'Приёмка контента должна требовать человека.' );
	velotok_commerce_contract_assert( false === ( $acceptance['automation_allowed'] ?? true ), 'Автоматическая приёмка контента должна быть запрещена.' );
	velotok_commerce_contract_assert( 'velotok_project_manager' === ( $acceptance['role'] ?? '' ), 'Роль приёмки контента должна быть velotok_project_manager.' );
	velotok_commerce_contract_assert( 'velotok_accept_content' === ( $acceptance['capability'] ?? '' ), 'Возможность приёмки контента должна быть velotok_accept_content.' );

	velotok_commerce_register_content_roles();

	$role = get_role( 'velotok_project_manager' );

	velotok_commerce_contract_assert( null !== $role, 'Роль «Руководитель проекта» не зарегистрирована.' );

	if ( $role ) {
		velotok_commerce_contract_assert( $role->has_cap( 'velotok_accept_content' ), 'Роль «Руководитель проекта» не имеет возможности velotok_accept_content.' );
	}

	velotok_commerce_contract_assert( false === velotok_commerce_user_can_accept_content( 0 ), 'Неавторизованный пользователь не должен принимать контент.' );

	if ( $role ) {
		$suffix = strtolower( wp_generate_password( 8, false, false ) );

		$manager_id = wp_insert_user(
			array(
				'user_login' => 'velotok_contract_manager_' . $suffix,
				'user_pass'  => wp_generate_password( 20 ),
				'user_email' => 'velotok-manager-' . $suffix . '@example.test',
				'role'       => 'velotok_project_manager',
			)
		);

		$subscriber_id = wp_insert_user(
			array(
				'user_login' => 'velotok_contract_subscriber_' . $suffix,
				'user_pass'  => wp_generate_password( 20 ),
				'user_email' => 'velotok-subscriber-' . $suffix . '@example.test',
				'role'       => 'subscriber',
			)
		);

		if ( is_wp_error( $manager_id ) ) {
			velotok_commerce_contract_assert( false, 'Не удалось создать тестового руководителя проекта: ' . $manager_id->get_error_message() );
		} else {
			velotok_commerce_contract_assert( true === velotok_commerce_user_can_accept_content( $manager_id ), 'Руководитель проекта должен иметь право приёмки контента.' );
		}

		if ( is_wp_error( $subscriber_id ) ) {
			velotok_commerce_contract_assert( false, 'Не удалось создать тестового подписчика: ' . $subscriber_id->get_error_message() );
		} else {
			velotok_commerce_contract_assert( false === velotok_commerce_user_can_accept_content( $subscriber_id ), 'Подписчик не должен иметь права приёмки контента.' );
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		if ( ! is_wp_error( $manager_id ) && $manager_id ) {
			wp_delete_user( $manager_id );
		}

		if ( ! is_wp_error( $subscriber_id ) && $subscriber_id ) {
			wp_delete_user( $subscriber_id );
		}
	}
}

velotok_commerce_contract_finish();
