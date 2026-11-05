<?php
/**
 * Contract test for the human content approval guard.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/content-approval-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/content-approval-contract-test.php
 *
 * The test intentionally fails when the approval guard is removed, allows a
 * non-project-manager to approve content, stops storing the approver ID in
 * `_content_approved_by`, or lets an unapproved article be published.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$approval_path = dirname( __DIR__ ) . '/includes/content-approval.php';
$override      = getenv( 'VELOTOK_CONTENT_APPROVAL_STUB' );

if ( is_string( $override ) && '' !== $override ) {
	$approval_path = $override;
}

if ( ! is_readable( $approval_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден модуль приёмки контента человеком.' . PHP_EOL );
	exit( 1 );
}

require_once $approval_path;

$failures = array();

/**
 * Assert a content approval contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_content_approval_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$authorized = array(
	'ID'    => 42,
	'roles' => array( 'project_manager' ),
);
$authorized_label = array(
	'ID'   => 43,
	'role' => 'Руководитель проекта',
);
$authorized_cap = array(
	'ID'      => 44,
	'allcaps' => array( 'approve_content' => true ),
);
$forbidden = array(
	'ID'    => 7,
	'roles' => array( 'editor' ),
);
$forbidden_label = array(
	'ID'   => 8,
	'role' => 'Главный редактор',
);

if ( ! function_exists( 'velotok_core_content_approval_contract' ) ) {
	velotok_content_approval_assert( false, 'Отсутствует контракт приёмки контента человеком.' );
} else {
	$contract = velotok_core_content_approval_contract();

	velotok_content_approval_assert( is_array( $contract ), 'Контракт приёмки должен быть массивом.' );
	velotok_content_approval_assert( isset( $contract['spec'] ) && 'clarify-096bb945' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_content_approval_assert( isset( $contract['requirement'] ) && 'TESTEB-68' === $contract['requirement'], 'Контракт ссылается на неверное требование.' );
	velotok_content_approval_assert( isset( $contract['enforce'] ) && true === $contract['enforce'], 'Правило приёмки не включено.' );
	velotok_content_approval_assert( isset( $contract['role'] ) && 'project_manager' === $contract['role'], 'Приёмку должен выполнять руководитель проекта.' );
	velotok_content_approval_assert( isset( $contract['role_label'] ) && 'руководитель проекта' === $contract['role_label'], 'Не зафиксировано название роли руководителя проекта.' );
	velotok_content_approval_assert( isset( $contract['meta']['approved_by'] ) && '_content_approved_by' === $contract['meta']['approved_by'], 'Не задана мета _content_approved_by.' );
	velotok_content_approval_assert( isset( $contract['error']['required'] ) && 'CONTENT_APPROVAL_REQUIRED' === $contract['error']['required'], 'Не задан код CONTENT_APPROVAL_REQUIRED.' );
	velotok_content_approval_assert( isset( $contract['error']['role_forbidden'] ) && 'CONTENT_APPROVAL_ROLE_FORBIDDEN' === $contract['error']['role_forbidden'], 'Не задан код CONTENT_APPROVAL_ROLE_FORBIDDEN.' );
}

velotok_content_approval_assert( true === velotok_core_content_approval_role_is_valid( 'project_manager' ), 'Слаг project_manager должен быть валидной ролью.' );
velotok_content_approval_assert( true === velotok_core_content_approval_role_is_valid( 'Руководитель проекта' ), 'Название роли «Руководитель проекта» должно быть валидным.' );
velotok_content_approval_assert( false === velotok_core_content_approval_role_is_valid( 'editor' ), 'Роль editor не должна считаться руководителем проекта.' );

velotok_content_approval_assert( true === velotok_core_content_approval_user_is_authorized( $authorized ), 'Пользователь с ролью project_manager должен иметь право приёмки.' );
velotok_content_approval_assert( true === velotok_core_content_approval_user_is_authorized( $authorized_label ), 'Пользователь с названием роли «Руководитель проекта» должен иметь право приёмки.' );
velotok_content_approval_assert( true === velotok_core_content_approval_user_is_authorized( $authorized_cap ), 'Пользователь с правом approve_content должен иметь право приёмки.' );
velotok_content_approval_assert( false === velotok_core_content_approval_user_is_authorized( $forbidden ), 'Редактор без роли руководителя проекта не должен подтверждать контент.' );
velotok_content_approval_assert( false === velotok_core_content_approval_user_is_authorized( $forbidden_label ), 'Посторонняя роль не должна подтверждать контент.' );

$draft = array(
	'ID'          => 101,
	'post_type'   => 'post',
	'post_status' => 'draft',
);
$approved = velotok_core_content_approval_apply( $draft, $authorized, true );

velotok_content_approval_assert( true === $approved['valid'], 'Руководитель проекта должен успешно подтверждать контент.' );
velotok_content_approval_assert( true === $approved['accepted'], 'Подтверждённая приёмка должна иметь accepted = true.' );
velotok_content_approval_assert( 42 === $approved['approved_by'], 'ID подтвердившего должен сохраняться.' );
velotok_content_approval_assert( 42 === (int) $approved['post']['_content_approved_by'], 'ID подтвердившего должен сохраняться в _content_approved_by.' );
velotok_content_approval_assert( 'approved' === $approved['post']['_content_approval_status'], 'Статус приёмки должен стать approved.' );
velotok_content_approval_assert( 'publish' === $approved['post']['post_status'], 'После подтверждения статья должна публиковаться.' );

$denied = velotok_core_content_approval_apply( $draft, $forbidden, true );

velotok_content_approval_assert( false === $denied['valid'], 'Редактор не должен подтверждать контент.' );
velotok_content_approval_assert( 'CONTENT_APPROVAL_ROLE_FORBIDDEN' === $denied['code'], 'Для чужой роли нужен CONTENT_APPROVAL_ROLE_FORBIDDEN.' );
velotok_content_approval_assert( ! isset( $denied['post']['_content_approved_by'] ), 'При отказе ID подтвердившего не должен записываться.' );
velotok_content_approval_assert( 'publish' !== $denied['post_status'], 'При отказе статья не должна публиковаться.' );

$unapproved_publish = array(
	'ID'          => 102,
	'post_type'   => 'post',
	'post_status' => 'publish',
);
$guard = velotok_core_content_approval_guard_transition( $unapproved_publish );

velotok_content_approval_assert( false === $guard['allowed'], 'Публикация без приёмки должна блокироваться.' );
velotok_content_approval_assert( 'pending' === $guard['post_status'], 'Публикация без приёмки должна откатываться в pending.' );
velotok_content_approval_assert( 'CONTENT_APPROVAL_REQUIRED' === $guard['code'], 'Без приёмки должен возвращаться CONTENT_APPROVAL_REQUIRED.' );

$api_bypass = array(
	'ID'          => 103,
	'post_type'   => 'post',
	'post_status' => 'publish',
	'meta'        => array(
		'_content_approved_by'     => '',
		'_content_approval_status' => '',
	),
);
$api_guard = velotok_core_content_approval_guard_transition( $api_bypass );

velotok_content_approval_assert( false === $api_guard['allowed'], 'Обход через update_post/API без приёмки должен блокироваться.' );
velotok_content_approval_assert( 'pending' === $api_guard['post_status'], 'Обход через update_post/API должен откатываться в pending.' );

$guard_approved = velotok_core_content_approval_guard_transition( $approved['post'] );

velotok_content_approval_assert( true === $guard_approved['allowed'], 'Ранее подтверждённая статья должна публиковаться.' );
velotok_content_approval_assert( 'publish' === $guard_approved['post_status'], 'Ранее подтверждённая статья должна сохранять publish.' );

$revoked = velotok_core_content_approval_apply( $approved['post'], $authorized, false );

velotok_content_approval_assert( true === $revoked['valid'], 'Руководитель проекта должен уметь отзывать приёмку.' );
velotok_content_approval_assert( false === $revoked['accepted'], 'Отозванная приёмка должна иметь accepted = false.' );
velotok_content_approval_assert( 'pending' === $revoked['post_status'], 'После отзыва статья должна возвращаться в pending.' );
velotok_content_approval_assert( ! isset( $revoked['post']['_content_approved_by'] ), 'После отзыва ID подтвердившего должен удаляться.' );

$draft_guard = velotok_core_content_approval_guard_transition( $draft );

velotok_content_approval_assert( true === $draft_guard['allowed'], 'Черновик не должен принудительно переводиться в pending.' );
velotok_content_approval_assert( 'draft' === $draft_guard['post_status'], 'Черновик должен сохранять свой статус.' );

$product_guard = velotok_core_content_approval_guard_transition(
	array(
		'ID'          => 104,
		'post_type'   => 'product',
		'post_status' => 'publish',
	)
);

velotok_content_approval_assert( true === $product_guard['allowed'], 'Товары WooCommerce не должны попадать под правило контентных статей.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: приёмка контента человеком с ролью руководителя проекта обязательна.' . PHP_EOL;
