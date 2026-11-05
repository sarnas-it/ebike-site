<?php
/**
 * Contract test for the mocked guest cart registration stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/guest-cart-registration-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/guest-cart-registration-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer implements the guest cart collection, registration on
 * «Купить», cart transfer and checkout redirect required by clarify-30af92ca.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/guest-cart-registration.php';
$override  = getenv( 'VELOTOK_GUEST_CART_STUB' );

if ( is_string( $override ) && '' !== $override ) {
	$stub_path = $override;
}

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки гостевой корзины и регистрации.' . PHP_EOL );
	exit( 1 );
}

require_once $stub_path;

$failures = array();

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_guest_cart_contract_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_core_guest_cart_registration_contract' ) ) {
	velotok_guest_cart_contract_assert( false, 'Отсутствует контракт гостевой корзины и регистрации.' );
} else {
	$contract = velotok_core_guest_cart_registration_contract();

	velotok_guest_cart_contract_assert( is_array( $contract ), 'Контракт гостевой корзины должен быть массивом.' );
	velotok_guest_cart_contract_assert( isset( $contract['spec'] ) && 'clarify-30af92ca' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_guest_cart_contract_assert( isset( $contract['parent_spec'] ) && 'ecommerce-store' === $contract['parent_spec'], 'Не зафиксирована родительская спецификация.' );
	velotok_guest_cart_contract_assert( isset( $contract['requirement'] ) && 'TESTEB-78' === $contract['requirement'], 'Контракт ссылается на неверное требование.' );
	velotok_guest_cart_contract_assert( isset( $contract['task'] ) && 'TESTEB-79' === $contract['task'], 'Контракт не помечен задачей TESTEB-79.' );
	velotok_guest_cart_contract_assert( isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-79', $contract['requirement_ids'], true ), 'Контракт не содержит требование TESTEB-79.' );
	velotok_guest_cart_contract_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_guest_cart_contract_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_guest_cart_contract_assert( isset( $contract['network'] ) && false === $contract['network'], 'Заглушка не должна ходить в сеть.' );
	velotok_guest_cart_contract_assert( isset( $contract['persistent'] ) && false === $contract['persistent'], 'Заглушка не должна сохранять состояние.' );
	velotok_guest_cart_contract_assert( isset( $contract['operation'] ) && 'guest_cart_register_transfer_checkout' === $contract['operation'], 'Неверная операция гостевого сценария.' );

	velotok_guest_cart_contract_assert( isset( $contract['guest_cart_allowed'] ) && true === $contract['guest_cart_allowed'], 'Гостевая корзина должна быть разрешена.' );
	velotok_guest_cart_contract_assert( isset( $contract['registers_on_buy'] ) && true === $contract['registers_on_buy'], 'Нажатие «Купить» должно регистрировать пользователя.' );
	velotok_guest_cart_contract_assert( isset( $contract['transfers_after_registration'] ) && true === $contract['transfers_after_registration'], 'Гостевая корзина должна переноситься после регистрации.' );
	velotok_guest_cart_contract_assert( isset( $contract['redirects_to_checkout'] ) && true === $contract['redirects_to_checkout'], 'После переноса пользователь должен переходить к оформлению.' );

	foreach ( array( 'guest_cart', 'register_on_buy', 'transfer_after_signup', 'redirect_to_checkout' ) as $key ) {
		velotok_guest_cart_contract_assert(
			isset( $contract['requirements'][ $key ] ) && false !== strpos( $contract['requirements'][ $key ], 'SHALL' ),
			sprintf( 'Не зафиксировано требование «%s».', $key )
		);
	}

	velotok_guest_cart_contract_assert( isset( $contract['guest_cart']['enabled'] ) && true === $contract['guest_cart']['enabled'], 'Гостевая корзина должна быть включена.' );
	velotok_guest_cart_contract_assert( isset( $contract['guest_cart']['allow_unauthenticated'] ) && true === $contract['guest_cart']['allow_unauthenticated'], 'Неавторизованный пользователь должен собирать корзину.' );
	velotok_guest_cart_contract_assert( isset( $contract['guest_cart']['authentication_required'] ) && false === $contract['guest_cart']['authentication_required'], 'Сбор корзины не должен требовать авторизации.' );
	velotok_guest_cart_contract_assert( isset( $contract['guest_cart']['accumulate_items'] ) && true === $contract['guest_cart']['accumulate_items'], 'Гостевая корзина должна накапливать товары.' );
	velotok_guest_cart_contract_assert( isset( $contract['guest_cart']['mocked'] ) && true === $contract['guest_cart']['mocked'], 'Гостевая корзина не помечена mocked: true.' );
	velotok_guest_cart_contract_assert( isset( $contract['guest_cart']['persistent'] ) && false === $contract['guest_cart']['persistent'], 'Гостевая корзина не должна сохраняться.' );

	velotok_guest_cart_contract_assert( isset( $contract['buy_action']['trigger'] ) && 'buy' === $contract['buy_action']['trigger'], 'Действие «Купить» должно запускать регистрацию.' );
	velotok_guest_cart_contract_assert( isset( $contract['buy_action']['registers_user'] ) && true === $contract['buy_action']['registers_user'], '«Купить» должно регистрировать пользователя.' );
	velotok_guest_cart_contract_assert( isset( $contract['buy_action']['transfer_after_registration'] ) && true === $contract['buy_action']['transfer_after_registration'], '«Купить» должно переносить корзину.' );
	velotok_guest_cart_contract_assert( isset( $contract['buy_action']['redirect_after_transfer'] ) && true === $contract['buy_action']['redirect_after_transfer'], '«Купить» должно переводить к оформлению.' );

	velotok_guest_cart_contract_assert( isset( $contract['registration']['enabled'] ) && true === $contract['registration']['enabled'], 'Регистрация должна быть включена.' );
	velotok_guest_cart_contract_assert( isset( $contract['registration']['creates_account'] ) && true === $contract['registration']['creates_account'], 'Регистрация должна создавать аккаунт.' );
	velotok_guest_cart_contract_assert( isset( $contract['registration']['mocked'] ) && true === $contract['registration']['mocked'], 'Регистрация не помечена mocked: true.' );
	velotok_guest_cart_contract_assert( isset( $contract['registration']['required_fields'] ) && in_array( 'email', $contract['registration']['required_fields'], true ), 'Email должен быть обязательным полем регистрации.' );

	velotok_guest_cart_contract_assert( isset( $contract['transfer']['on_registration'] ) && true === $contract['transfer']['on_registration'], 'Не описан перенос гостевой корзины при регистрации.' );
	velotok_guest_cart_contract_assert( isset( $contract['transfer']['merge_strategy'] ) && 'merge_quantities' === $contract['transfer']['merge_strategy'], 'Неверная стратегия слияния гостевой корзины.' );
	velotok_guest_cart_contract_assert( isset( $contract['transfer']['merge_by'] ) && 'product_id' === $contract['transfer']['merge_by'], 'Слияние должно выполняться по товару.' );
	velotok_guest_cart_contract_assert( isset( $contract['transfer']['clear_guest_after_transfer'] ) && true === $contract['transfer']['clear_guest_after_transfer'], 'Гостевая корзина должна очищаться после переноса.' );

	velotok_guest_cart_contract_assert( isset( $contract['redirect']['enabled'] ) && true === $contract['redirect']['enabled'], 'Переход к оформлению должен быть включён.' );
	velotok_guest_cart_contract_assert( isset( $contract['redirect']['after_transfer'] ) && true === $contract['redirect']['after_transfer'], 'Переход должен выполняться после переноса.' );
	velotok_guest_cart_contract_assert( isset( $contract['redirect']['target'] ) && 'checkout' === $contract['redirect']['target'], 'Целью перехода должно быть оформление заказа.' );
	velotok_guest_cart_contract_assert( isset( $contract['redirect']['mocked'] ) && true === $contract['redirect']['mocked'], 'Переход не помечен mocked: true.' );

	foreach ( array( 'empty_cart', 'registration_email_required', 'registration_email_invalid', 'registration_email_exists', 'transfer_failed' ) as $code ) {
		velotok_guest_cart_contract_assert( isset( $contract['error_codes'][ $code ] ), sprintf( 'Не задан код ошибки «%s».', $code ) );
	}

	foreach ( array( 'authentication', 'existing_user', 'merge_rules', 'checkout_target', 'local_run' ) as $question ) {
		velotok_guest_cart_contract_assert( isset( $contract['open_questions'][ $question ] ), sprintf( 'Не зафиксирован открытый вопрос «%s».', $question ) );
	}
}

if ( function_exists( 'velotok_core_guest_cart_create' ) && function_exists( 'velotok_core_guest_cart_add' ) ) {
	$guest = velotok_core_guest_cart_create();

	velotok_guest_cart_contract_assert( true === $guest['guest'], 'Гостевая корзина должна быть помечена guest.' );
	velotok_guest_cart_contract_assert( false === $guest['authenticated'], 'Гостевая корзина не должна требовать авторизации.' );
	velotok_guest_cart_contract_assert( 0 === (int) $guest['user_id'], 'У гостевой корзины не должно быть пользователя.' );
	velotok_guest_cart_contract_assert( true === $guest['mocked'] && 'stub' === $guest['source'], 'Гостевая корзина должна быть помечена mocked и stub.' );

	$guest = velotok_core_guest_cart_add( $guest, 'riviera', 1 );
	$guest = velotok_core_guest_cart_add( $guest, 'riviera', 2 );

	velotok_guest_cart_contract_assert( true === $guest['valid'], 'Неавторизованный пользователь должен добавлять товары в корзину.' );
	velotok_guest_cart_contract_assert( 3 === velotok_core_cart_count( $guest ), 'Повторное добавление должно накапливать количество.' );
	velotok_guest_cart_contract_assert( 1 === velotok_core_cart_unique_count( $guest ), 'Одинаковые товары не должны дублироваться.' );
	velotok_guest_cart_contract_assert( true === $guest['guest'], 'Корзина должна оставаться гостевая до регистрации.' );

	$registration = velotok_core_guest_cart_register( array( 'email' => 'guest@example.com' ), $guest );

	velotok_guest_cart_contract_assert( true === $registration['valid'], 'Валидный email должен регистрировать пользователя.' );
	velotok_guest_cart_contract_assert( true === $registration['registered'], 'Новый пользователь должен быть зарегистрирован.' );
	velotok_guest_cart_contract_assert( true === $registration['authenticated'], 'После регистрации пользователь должен быть аутентифицирован.' );
	velotok_guest_cart_contract_assert( isset( $registration['user']['email'] ) && 'guest@example.com' === $registration['user']['email'], 'Результат должен содержать пользователя.' );
	velotok_guest_cart_contract_assert( ! isset( $registration['user']['password'] ), 'Заглушка не должна возвращать пароль пользователя.' );
	velotok_guest_cart_contract_assert( true === $registration['mocked'] && 'stub' === $registration['source'], 'Регистрация должна быть mocked и stub.' );

	$buy = velotok_core_guest_cart_buy( 'guest@example.com', $guest );

	velotok_guest_cart_contract_assert( true === $buy['valid'], 'Нажатие «Купить» должно завершать гостевой сценарий.' );
	velotok_guest_cart_contract_assert( true === $buy['registered'], 'Нажатие «Купить» должно регистрировать пользователя.' );
	velotok_guest_cart_contract_assert( true === $buy['authenticated'], 'После «Купить» пользователь должен быть аутентифицирован.' );
	velotok_guest_cart_contract_assert( true === $buy['guest_cart_transferred'], 'Гостевая корзина должна переноситься в аккаунт.' );
	velotok_guest_cart_contract_assert( 3 === velotok_core_cart_count( $buy['cart'] ), 'После переноса товары гостевой корзины должны сохраниться.' );
	velotok_guest_cart_contract_assert( isset( $buy['cart']['guest'] ) && false === $buy['cart']['guest'], 'Корзина после переноса не должна быть гостевой.' );
	velotok_guest_cart_contract_assert( 'merge_quantities' === $buy['transfer_strategy'], 'Стратегия переноса должна браться из контракта.' );
	velotok_guest_cart_contract_assert( true === $buy['redirected'], 'После переноса должен выполняться переход к оформлению.' );
	velotok_guest_cart_contract_assert( '/checkout/' === $buy['redirect_url'], 'Переход должен вести на оформление заказа.' );
	velotok_guest_cart_contract_assert( 'checkout' === $buy['next_step'], 'Следующим шагом должно быть оформление заказа.' );
	velotok_guest_cart_contract_assert( true === $buy['mocked'] && 'stub' === $buy['source'], 'Весь сценарий должен быть mocked и stub.' );

	$failed = velotok_core_guest_cart_buy( '', $guest );

	velotok_guest_cart_contract_assert( false === $failed['valid'], 'Регистрация без email должна завершаться ошибкой.' );
	velotok_guest_cart_contract_assert( 'REGISTRATION_EMAIL_REQUIRED' === $failed['code'], 'Пустой email должен давать REGISTRATION_EMAIL_REQUIRED.' );
	velotok_guest_cart_contract_assert( true === $failed['guest_cart_preserved'], 'При ошибке регистрации гостевая корзина должна сохраняться.' );
	velotok_guest_cart_contract_assert( 3 === velotok_core_cart_count( $failed['guest_cart'] ), 'Гостевая корзина не должна теряться при ошибке регистрации.' );

	$invalid = velotok_core_guest_cart_register( array( 'email' => 'not-an-email' ), $guest );
	velotok_guest_cart_contract_assert( false === $invalid['valid'] && 'REGISTRATION_EMAIL_INVALID' === $invalid['code'], 'Неверный email должен давать REGISTRATION_EMAIL_INVALID.' );

	$existing = velotok_core_guest_cart_register(
		array( 'email' => 'guest@example.com' ),
		$guest,
		array( 'existing_users' => array( array( 'user_id' => 7, 'email' => 'guest@example.com' ) ) )
	);
	velotok_guest_cart_contract_assert( true === $existing['valid'] && true === $existing['existing_user'], 'Существующий email должен обрабатываться по mocked-сценарию.' );
	velotok_guest_cart_contract_assert( 7 === (int) $existing['user_id'], 'Существующий пользователь должен использоваться повторно.' );

	$conflict = velotok_core_guest_cart_register(
		array( 'email' => 'guest@example.com' ),
		$guest,
		array(
			'existing_users'         => array( array( 'user_id' => 7, 'email' => 'guest@example.com' ) ),
			'existing_user_strategy' => 'error',
		)
	);
	velotok_guest_cart_contract_assert( false === $conflict['valid'] && 'REGISTRATION_EMAIL_EXISTS' === $conflict['code'], 'Стратегия error должна давать REGISTRATION_EMAIL_EXISTS.' );
}

if ( function_exists( 'velotok_core_guest_cart_transfer' ) ) {
	$guest = velotok_core_guest_cart_add( velotok_core_guest_cart_create(), 'riviera', 1 );
	$user  = velotok_core_cart_add_item( 'riviera', 2 );

	$merged = velotok_core_guest_cart_transfer( $guest, $user, array( 'user_id' => 5 ) );

	velotok_guest_cart_contract_assert( true === $merged['guest_cart_transferred'], 'Гостевая корзина должна переноситься.' );
	velotok_guest_cart_contract_assert( 3 === velotok_core_cart_count( $merged ), 'Количество одинаковых товаров должно суммироваться.' );
	velotok_guest_cart_contract_assert( 1 === (int) $merged['transferred'], 'Количество перенесённых позиций должно учитываться.' );
	velotok_guest_cart_contract_assert( true === $merged['guest_cleared'], 'Гостевая корзина должна очищаться после переноса.' );
	velotok_guest_cart_contract_assert( 5 === (int) $merged['user_id'], 'Корзина должна привязываться к пользователю.' );
	velotok_guest_cart_contract_assert( true === $merged['mocked'] && 'stub' === $merged['source'], 'Перенос должен быть mocked и stub.' );
}

if ( function_exists( 'velotok_core_guest_cart_checkout_redirect' ) ) {
	$empty = velotok_core_guest_cart_checkout_redirect( velotok_core_guest_cart_create() );

	velotok_guest_cart_contract_assert( false === $empty['valid'], 'Пустая корзина не должна переводить к оформлению.' );
	velotok_guest_cart_contract_assert( 'CART_EMPTY' === $empty['code'], 'Пустая корзина должна давать CART_EMPTY.' );
	velotok_guest_cart_contract_assert( '' === $empty['redirect_url'], 'Без товаров URL оформления должен быть пустым.' );
}

$source = (string) file_get_contents( $stub_path );

$forbidden = array(
	'get' . 'env(',
	'apache_get' . 'env(',
	'$_' . 'ENV',
	'$_' . 'SERVER',
	'file_get' . '_contents(',
	'fopen(',
	'curl_' . 'init(',
	'wp_remote_' . 'get(',
	'wp_remote_' . 'post(',
	'$' . 'wpdb',
);

foreach ( $forbidden as $needle ) {
	velotok_guest_cart_contract_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт гостевой корзины и регистрации на месте.' . PHP_EOL;
