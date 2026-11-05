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
	velotok_guest_cart_contract_assert( isset( $contract['task'] ) && 'TESTEB-81' === $contract['task'], 'Контракт не помечен задачей TESTEB-81.' );
	velotok_guest_cart_contract_assert( isset( $contract['previous_task'] ) && 'TESTEB-80' === $contract['previous_task'], 'Контракт не фиксирует предыдущую задачу TESTEB-80.' );
	velotok_guest_cart_contract_assert( isset( $contract['transfer_requirement'] ) && 'TESTEB-80' === $contract['transfer_requirement'], 'Контракт не фиксирует требование переноса TESTEB-80.' );
	velotok_guest_cart_contract_assert( isset( $contract['redirect_requirement'] ) && 'TESTEB-81' === $contract['redirect_requirement'], 'Контракт не фиксирует требование перехода TESTEB-81.' );
	velotok_guest_cart_contract_assert( isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-79', $contract['requirement_ids'], true ), 'Контракт не содержит требование TESTEB-79.' );
	velotok_guest_cart_contract_assert( isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-80', $contract['requirement_ids'], true ), 'Контракт не содержит требование TESTEB-80.' );
	velotok_guest_cart_contract_assert( isset( $contract['requirement_ids'] ) && in_array( 'TESTEB-81', $contract['requirement_ids'], true ), 'Контракт не содержит требование TESTEB-81.' );
	velotok_guest_cart_contract_assert( isset( $contract['requirement_tickets']['transfer_after_signup'] ) && 'TESTEB-80' === $contract['requirement_tickets']['transfer_after_signup'], 'Перенос корзины не привязан к задаче TESTEB-80.' );
	velotok_guest_cart_contract_assert( isset( $contract['requirement_tickets']['redirect_to_checkout'] ) && 'TESTEB-81' === $contract['requirement_tickets']['redirect_to_checkout'], 'Переход к оформлению не привязан к задаче TESTEB-81.' );
	velotok_guest_cart_contract_assert( isset( $contract['current_task'] ) && 'TESTEB-81' === $contract['current_task'], 'Контракт не помечен текущей задачей TESTEB-81.' );
	velotok_guest_cart_contract_assert( isset( $contract['requirement_id'] ) && 'TESTEB-81' === $contract['requirement_id'], 'Контракт не содержит идентификатор требования TESTEB-81.' );
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

	velotok_guest_cart_contract_assert( isset( $contract['transfer']['enabled'] ) && true === $contract['transfer']['enabled'], 'Перенос гостевой корзины должен быть включён.' );
	velotok_guest_cart_contract_assert( isset( $contract['transfer']['requirement'] ) && 'TESTEB-80' === $contract['transfer']['requirement'], 'Перенос гостевой корзины не привязан к требованию TESTEB-80.' );
	velotok_guest_cart_contract_assert( isset( $contract['transfer']['on_registration'] ) && true === $contract['transfer']['on_registration'], 'Не описан перенос гостевой корзины при регистрации.' );
	velotok_guest_cart_contract_assert( isset( $contract['transfer']['source'] ) && 'guest_cart' === $contract['transfer']['source'], 'Источником переноса должна быть гостевая корзина.' );
	velotok_guest_cart_contract_assert( isset( $contract['transfer']['target'] ) && 'user_cart' === $contract['transfer']['target'], 'Целью переноса должна быть корзина пользователя.' );
	velotok_guest_cart_contract_assert( isset( $contract['transfer']['merge_strategy'] ) && 'merge_quantities' === $contract['transfer']['merge_strategy'], 'Неверная стратегия слияния гостевой корзины.' );
	velotok_guest_cart_contract_assert( isset( $contract['transfer']['merge_by'] ) && 'product_id' === $contract['transfer']['merge_by'], 'Слияние должно выполняться по товару.' );
	velotok_guest_cart_contract_assert( isset( $contract['transfer']['preserve_user_cart'] ) && true === $contract['transfer']['preserve_user_cart'], 'Существующая корзина пользователя должна сохраняться.' );
	velotok_guest_cart_contract_assert( isset( $contract['transfer']['clear_guest_after_transfer'] ) && true === $contract['transfer']['clear_guest_after_transfer'], 'Гостевая корзина должна очищаться после переноса.' );
	velotok_guest_cart_contract_assert( isset( $contract['transfer']['attaches_to_user_account'] ) && true === $contract['transfer']['attaches_to_user_account'], 'Корзина должна привязываться к аккаунту пользователя.' );
	velotok_guest_cart_contract_assert( isset( $contract['transfer']['mocked'] ) && true === $contract['transfer']['mocked'], 'Перенос не помечен mocked: true.' );

	velotok_guest_cart_contract_assert( isset( $contract['redirect']['enabled'] ) && true === $contract['redirect']['enabled'], 'Переход к оформлению должен быть включён.' );
	velotok_guest_cart_contract_assert( isset( $contract['redirect']['requirement'] ) && 'TESTEB-81' === $contract['redirect']['requirement'], 'Переход не привязан к требованию TESTEB-81.' );
	velotok_guest_cart_contract_assert( isset( $contract['redirect']['operation'] ) && 'redirect_to_checkout' === $contract['redirect']['operation'], 'Неверная операция перехода к оформлению.' );
	velotok_guest_cart_contract_assert( isset( $contract['redirect']['after_transfer'] ) && true === $contract['redirect']['after_transfer'], 'Переход должен выполняться после переноса.' );
	velotok_guest_cart_contract_assert( isset( $contract['redirect']['requires_transfer'] ) && true === $contract['redirect']['requires_transfer'], 'Переход должен требовать переноса корзины.' );
	velotok_guest_cart_contract_assert( isset( $contract['redirect']['target'] ) && 'checkout' === $contract['redirect']['target'], 'Целью перехода должно быть оформление заказа.' );
	velotok_guest_cart_contract_assert( isset( $contract['redirect']['target_url'] ) && '/checkout/' === $contract['redirect']['target_url'], 'URL оформления заказа должен быть /checkout/.' );
	velotok_guest_cart_contract_assert( isset( $contract['redirect']['next_step'] ) && 'checkout' === $contract['redirect']['next_step'], 'Следующим шагом должно быть оформление заказа.' );
	velotok_guest_cart_contract_assert( isset( $contract['redirect']['mocked'] ) && true === $contract['redirect']['mocked'], 'Переход не помечен mocked: true.' );
	velotok_guest_cart_contract_assert( isset( $contract['checkout_redirect']['requirement'] ) && 'TESTEB-81' === $contract['checkout_redirect']['requirement'], 'Секция checkout_redirect не привязана к требованию TESTEB-81.' );

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

velotok_guest_cart_contract_assert( function_exists( 'velotok_core_guest_cart_transfer_contract' ), 'Отсутствует контракт переноса гостевой корзины.' );
velotok_guest_cart_contract_assert( function_exists( 'velotok_core_guest_cart_transfer_after_registration' ), 'Отсутствует перенос корзины после регистрации.' );
velotok_guest_cart_contract_assert( function_exists( 'velotok_core_guest_cart_checkout_redirect_contract' ), 'Отсутствует контракт перехода к оформлению.' );
velotok_guest_cart_contract_assert( function_exists( 'velotok_core_guest_cart_redirect_contract' ), 'Отсутствует короткий контракт перехода к оформлению.' );
velotok_guest_cart_contract_assert( function_exists( 'velotok_core_guest_cart_redirect_to_checkout' ), 'Отсутствует помощник перехода к оформлению.' );

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
	$transfer_contract = velotok_core_guest_cart_transfer_contract();
	velotok_guest_cart_contract_assert( true === $transfer_contract['mocked'] && true === $transfer_contract['transfer']['mocked'], 'Контракт переноса должен быть mocked.' );
	velotok_guest_cart_contract_assert( true === $merged['transfer_mocked'], 'Перенос после регистрации должен быть помечен mocked.' );
	velotok_guest_cart_contract_assert( false === $merged['guest'], 'После переноса корзина не должна оставаться гостевой.' );
	velotok_guest_cart_contract_assert( true === $merged['authenticated'], 'После переноса корзина должна принадлежать аккаунту.' );

	$registered = velotok_core_guest_cart_register( array( 'email' => 'transfer@example.com' ), $guest );
	$after_registration = velotok_core_guest_cart_transfer( $guest, array(), $registered['user'] );

	velotok_guest_cart_contract_assert( true === $after_registration['guest_cart_transferred'], 'После регистрации корзина должна переноситься в аккаунт.' );
	velotok_guest_cart_contract_assert( (int) $registered['user_id'] === (int) $after_registration['user_id'], 'Перенесённая корзина должна ссылаться на зарегистрированного пользователя.' );
	velotok_guest_cart_contract_assert( 1 === velotok_core_cart_count( $after_registration ), 'Перенос после регистрации должен сохранять товары.' );
}

if ( function_exists( 'velotok_core_guest_cart_checkout_redirect' ) ) {
	$empty = velotok_core_guest_cart_checkout_redirect( velotok_core_guest_cart_create() );

	velotok_guest_cart_contract_assert( false === $empty['valid'], 'Пустая корзина не должна переводить к оформлению.' );
	velotok_guest_cart_contract_assert( 'CART_EMPTY' === $empty['code'], 'Пустая корзина должна давать CART_EMPTY.' );
	velotok_guest_cart_contract_assert( '' === $empty['redirect_url'], 'Без товаров URL оформления должен быть пустым.' );
	velotok_guest_cart_contract_assert( null === $empty['next_step'], 'Без товаров следующий шаг не должен быть оформлением.' );

	$ready_guest = velotok_core_guest_cart_add( velotok_core_guest_cart_create(), 'riviera', 1 );
	$not_transferred = velotok_core_guest_cart_checkout_redirect( $ready_guest );

	velotok_guest_cart_contract_assert( false === $not_transferred['valid'], 'До переноса гостевая корзина не должна переводить к оформлению.' );
	velotok_guest_cart_contract_assert( 'GUEST_CART_TRANSFER_REQUIRED' === $not_transferred['code'], 'До переноса ожидается GUEST_CART_TRANSFER_REQUIRED.' );
	velotok_guest_cart_contract_assert( false === $not_transferred['transferred'], 'До переноса флаг transferred должен быть false.' );

	$ready_user  = velotok_core_guest_cart_register( array( 'email' => 'redirect@example.com' ), $ready_guest );
	$ready_cart  = velotok_core_guest_cart_transfer( $ready_guest, array(), $ready_user['user'] );
	$redirect    = velotok_core_guest_cart_checkout_redirect( $ready_cart, $ready_user['user'] );

	velotok_guest_cart_contract_assert( true === $redirect['valid'], 'После переноса непустая корзина должна переводить к оформлению.' );
	velotok_guest_cart_contract_assert( true === $redirect['redirected'], 'После переноса переход должен выполняться.' );
	velotok_guest_cart_contract_assert( 'TESTEB-81' === $redirect['requirement'], 'Переход должен ссылаться на требование TESTEB-81.' );
	velotok_guest_cart_contract_assert( 'redirect_to_checkout' === $redirect['operation'], 'Неверная операция перехода.' );
	velotok_guest_cart_contract_assert( '/checkout/' === $redirect['redirect_url'], 'Переход должен вести на /checkout/.' );
	velotok_guest_cart_contract_assert( 'checkout' === $redirect['next_step'], 'Следующим шагом должно быть оформление заказа.' );
	velotok_guest_cart_contract_assert( true === $redirect['mocked'] && 'stub' === $redirect['source'], 'Переход должен быть mocked и stub.' );
	velotok_guest_cart_contract_assert( false === $redirect['guest'], 'После переноса корзина не должна быть гостевой.' );
	velotok_guest_cart_contract_assert( (int) $ready_user['user_id'] === (int) $redirect['user_id'], 'Переход должен ссылаться на зарегистрированного пользователя.' );

	$redirect_contract = velotok_core_guest_cart_checkout_redirect_contract();
	velotok_guest_cart_contract_assert( true === $redirect_contract['mocked'] && true === $redirect_contract['redirect']['mocked'], 'Контракт перехода должен быть mocked.' );
	velotok_guest_cart_contract_assert( 'TESTEB-81' === $redirect_contract['redirect_requirement'], 'Контракт перехода должен ссылаться на TESTEB-81.' );

	$alias_redirect = velotok_core_guest_cart_redirect_to_checkout( $ready_cart, $ready_user['user'] );
	velotok_guest_cart_contract_assert( $redirect['redirect_url'] === $alias_redirect['redirect_url'], 'Помощник перехода должен возвращать тот же URL.' );
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
