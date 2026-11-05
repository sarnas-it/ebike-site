<?php
/**
 * WordPress integration test for the human content approval guard.
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/content-approval-wordpress-test.php
 *
 * The test creates a pending article, verifies that a direct update_post /
 * REST-style publish attempt is reverted to pending, then verifies that a
 * project manager can approve it, that the approver ID is stored in
 * `_content_approved_by` and that the article becomes published. It also
 * verifies that an unauthorized user cannot approve content and that approval
 * can be revoked.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$approval_path = dirname( __DIR__ ) . '/includes/content-approval.php';

if ( is_readable( $approval_path ) ) {
	require_once $approval_path;
}

if ( ! function_exists( 'wp_insert_post' ) || ! function_exists( 'velotok_core_content_approval_approve' ) ) {
	echo 'SKIP: WordPress is not loaded.' . PHP_EOL;
	exit( 0 );
}

$failures = array();

/**
 * Assert a WordPress content approval condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_content_approval_wp_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$contract = velotok_core_content_approval_contract();
$role     = get_role( $contract['role'] );

if ( null === $role ) {
	add_role(
		$contract['role'],
		$contract['role_display'],
		array(
			'read'                  => true,
			'edit_posts'            => true,
			'edit_published_posts'  => true,
			'publish_posts'         => true,
			$contract['capability'] => true,
		)
	);

	$role = get_role( $contract['role'] );
}

if ( null !== $role ) {
	$role->add_cap( $contract['capability'] );
	$role->add_cap( 'edit_posts' );
}

$suffix = function_exists( 'wp_generate_password' ) ? wp_generate_password( 6, false, false ) : (string) time();
$time   = time();

$manager_id = wp_insert_user(
	array(
		'user_login' => 'velotok_pm_' . $time,
		'user_pass'  => wp_generate_password( 20 ),
		'user_email' => 'velotok-pm-' . $time . $suffix . '@example.test',
		'role'       => $contract['role'],
	)
);

$other_id = wp_insert_user(
	array(
		'user_login' => 'velotok_other_' . $time,
		'user_pass'  => wp_generate_password( 20 ),
		'user_email' => 'velotok-other-' . $time . $suffix . '@example.test',
		'role'       => 'subscriber',
	)
);

$post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'pending',
		'post_title'   => 'Статья на приёмке ' . $time,
		'post_content' => 'Текст статьи, подготовленной к публикации.',
	)
);

try {
	velotok_content_approval_wp_assert( $manager_id > 0 && ! is_wp_error( $manager_id ), 'Не удалось создать пользователя с ролью руководителя проекта.' );
	velotok_content_approval_wp_assert( $other_id > 0 && ! is_wp_error( $other_id ), 'Не удалось создать пользователя без права приёмки.' );
	velotok_content_approval_wp_assert( $post_id > 0 && ! is_wp_error( $post_id ), 'Не удалось создать статью для проверки приёмки.' );

	if ( $post_id > 0 && ! is_wp_error( $post_id ) ) {
		$rest_data = apply_filters(
			'wp_insert_post_data',
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			),
			array( 'ID' => $post_id )
		);

		velotok_content_approval_wp_assert( 'pending' === $rest_data['post_status'], 'REST-путь wp_insert_post_data без приёмки должен откатываться в pending.' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		velotok_content_approval_wp_assert( 'pending' === get_post_status( $post_id ), 'Прямой update_post в publish без приёмки должен откатываться в pending.' );
		velotok_content_approval_wp_assert( '' === get_post_meta( $post_id, $contract['meta']['approved_by'], true ), 'Без приёмки _content_approved_by не должен заполняться.' );

		$denied = velotok_core_content_approval_approve( $post_id, $other_id );

		velotok_content_approval_wp_assert( false === $denied['valid'], 'Пользователь без роли руководителя проекта не должен подтверждать контент.' );
		velotok_content_approval_wp_assert( 'CONTENT_APPROVAL_ROLE_FORBIDDEN' === $denied['code'], 'Для чужой роли нужен CONTENT_APPROVAL_ROLE_FORBIDDEN.' );
		velotok_content_approval_wp_assert( 'pending' === get_post_status( $post_id ), 'Отказ в приёмке не должен публиковать статью.' );
		velotok_content_approval_wp_assert( '' === get_post_meta( $post_id, $contract['meta']['approved_by'], true ), 'Отказ в приёмке не должен записывать ID.' );

		$approved = velotok_core_content_approval_approve( $post_id, $manager_id );

		velotok_content_approval_wp_assert( true === $approved['valid'], 'Руководитель проекта должен подтверждать контент.' );
		velotok_content_approval_wp_assert( (int) $manager_id === (int) get_post_meta( $post_id, $contract['meta']['approved_by'], true ), 'ID руководителя проекта должен сохраняться в _content_approved_by.' );
		velotok_content_approval_wp_assert( 'publish' === get_post_status( $post_id ), 'После подтверждения статья должна стать доступна на сайте (publish).' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		velotok_content_approval_wp_assert( 'publish' === get_post_status( $post_id ), 'Подтверждённая статья должна оставаться опубликованной.' );

		$revoked = velotok_core_content_approval_revoke( $post_id, $manager_id );

		velotok_content_approval_wp_assert( true === $revoked['valid'], 'Руководитель проекта должен отзывать приёмку.' );
		velotok_content_approval_wp_assert( 'pending' === get_post_status( $post_id ), 'После отзыва приёмки статья должна вернуться в pending.' );
		velotok_content_approval_wp_assert( '' === get_post_meta( $post_id, $contract['meta']['approved_by'], true ), 'После отзыва ID подтвердившего должен удаляться.' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		velotok_content_approval_wp_assert( 'pending' === get_post_status( $post_id ), 'Обход через update_post после отзыва должен снова откатываться в pending.' );
	}
} finally {
	if ( $post_id > 0 && ! is_wp_error( $post_id ) ) {
		wp_delete_post( $post_id, true );
	}

	if ( ! function_exists( 'wp_delete_user' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}

	if ( $manager_id > 0 && ! is_wp_error( $manager_id ) ) {
		wp_delete_user( $manager_id );
	}

	if ( $other_id > 0 && ! is_wp_error( $other_id ) ) {
		wp_delete_user( $other_id );
	}
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: приёмка контента человеком работает в WordPress: publish откатывается без подтверждения, ID руководителя проекта сохраняется.' . PHP_EOL;
