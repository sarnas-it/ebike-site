<?php
/**
 * Human content approval guard.
 *
 * Content articles must be accepted by a human with the project manager role
 * before they can be published. The rule is enforced both for the editor
 * metabox and for programmatic updates (update_post / REST API): an attempt to
 * publish an unapproved article is reverted to the `pending` status.
 *
 * The pure helpers in this file are used by the contract tests. The WordPress
 * integration (role, metabox, filters) is registered only when the WordPress
 * API is available.
 *
 * Spec: clarify-096bb945 (TESTEB-68).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

/**
 * Return the content approval contract.
 *
 * @return array<string,mixed>
 */
function velotok_core_content_approval_contract() {
	static $contract = null;

	if ( null !== $contract ) {
		return $contract;
	}

	$contract = array(
		'spec'          => 'clarify-096bb945',
		'requirement'   => 'TESTEB-68',
		'source'        => 'wordpress',
		'mocked'        => false,
		'enforce'       => true,
		'role'          => 'project_manager',
		'role_slug'     => 'project_manager',
		'role_label'    => 'руководитель проекта',
		'role_display'  => 'Руководитель проекта',
		'role_aliases'  => array(
			'project_manager',
			'projectmanager',
			'project_manager_role',
			'руководитель_проекта',
		),
		'capability'    => 'approve_content',
		'post_types'    => array( 'post' ),
		'statuses'      => array(
			'pending'  => 'pending',
			'approved' => 'approved',
			'publish'  => 'publish',
		),
		'meta'          => array(
			'approved_by' => '_content_approved_by',
			'approved_at' => '_content_approved_at',
			'status'      => '_content_approval_status',
		),
		'meta_key'      => '_content_approved_by',
		'field'         => 'velotok_content_approval',
		'nonce'         => 'velotok_content_approval_nonce',
		'nonce_action'  => 'velotok_content_approval_save',
		'error'         => array(
			'required'       => 'CONTENT_APPROVAL_REQUIRED',
			'role_forbidden' => 'CONTENT_APPROVAL_ROLE_FORBIDDEN',
			'post_invalid'   => 'CONTENT_APPROVAL_POST_INVALID',
		),
	);

	return $contract;
}

/**
 * Normalize a role name or label for comparisons.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function velotok_core_content_approval_normalize_key( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	if ( function_exists( 'mb_strtolower' ) ) {
		$value = mb_strtolower( $value, 'UTF-8' );
	} else {
		$value = strtolower( $value );
	}

	$value = str_replace( 'ё', 'е', $value );

	return (string) preg_replace( '/[\s-]+/', '_', $value );
}

/**
 * Check whether a role name or label is the project manager role.
 *
 * @param mixed $role Role slug or label.
 * @return bool
 */
function velotok_core_content_approval_role_is_valid( $role ) {
	$contract = velotok_core_content_approval_contract();
	$key      = velotok_core_content_approval_normalize_key( $role );

	if ( '' === $key ) {
		return false;
	}

	foreach ( $contract['role_aliases'] as $alias ) {
		if ( velotok_core_content_approval_normalize_key( $alias ) === $key ) {
			return true;
		}
	}

	return velotok_core_content_approval_normalize_key( $contract['role'] ) === $key;
}

/**
 * Normalize a user into roles and capabilities.
 *
 * Accepts a user ID, a WP_User-like object or an array.
 *
 * @param mixed $user User data.
 * @return array{roles:string[],caps:array<string,mixed>}
 */
function velotok_core_content_approval_user_data( $user ) {
	$roles = array();
	$caps  = array();

	if ( is_numeric( $user ) ) {
		if ( function_exists( 'get_userdata' ) ) {
			$user = get_userdata( (int) $user );
		} else {
			$user = null;
		}
	}

	if ( is_object( $user ) ) {
		$vars = get_object_vars( $user );

		if ( isset( $vars['roles'] ) ) {
			$roles = (array) $vars['roles'];
		}

		if ( isset( $vars['role'] ) && is_scalar( $vars['role'] ) ) {
			$roles[] = (string) $vars['role'];
		}

		if ( isset( $vars['role_label'] ) && is_scalar( $vars['role_label'] ) ) {
			$roles[] = (string) $vars['role_label'];
		}

		foreach ( array( 'allcaps', 'capabilities', 'caps' ) as $cap_key ) {
			if ( isset( $vars[ $cap_key ] ) && is_array( $vars[ $cap_key ] ) ) {
				$caps = array_merge( $caps, $vars[ $cap_key ] );
			}
		}
	} elseif ( is_array( $user ) ) {
		foreach ( array( 'roles', 'role', 'role_slug', 'role_label' ) as $role_key ) {
			if ( ! isset( $user[ $role_key ] ) ) {
				continue;
			}

			if ( is_array( $user[ $role_key ] ) ) {
				$roles = array_merge( $roles, $user[ $role_key ] );
			} elseif ( is_scalar( $user[ $role_key ] ) ) {
				$roles[] = (string) $user[ $role_key ];
			}
		}

		foreach ( array( 'allcaps', 'capabilities', 'caps' ) as $cap_key ) {
			if ( isset( $user[ $cap_key ] ) && is_array( $user[ $cap_key ] ) ) {
				$caps = array_merge( $caps, $user[ $cap_key ] );
			}
		}
	}

	$roles = array_values( array_filter( array_map( 'strval', $roles ) ) );

	return array(
		'roles' => $roles,
		'caps'  => $caps,
	);
}

/**
 * Return the user ID from a user ID, object or array.
 *
 * @param mixed $user User data.
 * @return int Zero when the ID cannot be determined.
 */
function velotok_core_content_approval_user_id( $user ) {
	if ( is_numeric( $user ) ) {
		return (int) $user;
	}

	if ( is_object( $user ) ) {
		$user = get_object_vars( $user );
	}

	if ( ! is_array( $user ) ) {
		return 0;
	}

	foreach ( array( 'ID', 'id', 'user_id' ) as $key ) {
		if ( isset( $user[ $key ] ) && is_numeric( $user[ $key ] ) ) {
			return (int) $user[ $key ];
		}
	}

	return 0;
}

/**
 * Check whether a human user may accept content.
 *
 * @param mixed $user User data.
 * @return bool
 */
function velotok_core_content_approval_user_is_authorized( $user ) {
	$contract = velotok_core_content_approval_contract();
	$data     = velotok_core_content_approval_user_data( $user );

	foreach ( $data['roles'] as $role ) {
		if ( velotok_core_content_approval_role_is_valid( $role ) ) {
			return true;
		}
	}

	if ( ! empty( $data['caps'][ $contract['capability'] ] ) ) {
		return true;
	}

	if ( is_object( $user ) && method_exists( $user, 'has_cap' ) ) {
		return (bool) $user->has_cap( $contract['capability'] );
	}

	return false;
}

/**
 * Normalize a post into an array.
 *
 * @param mixed $post Post data.
 * @return array<string,mixed>
 */
function velotok_core_content_approval_post_data( $post ) {
	if ( is_object( $post ) ) {
		$post = get_object_vars( $post );
	}

	return is_array( $post ) ? $post : array();
}

/**
 * Read a meta value from normalized post data.
 *
 * @param array<string,mixed> $post Post data.
 * @param string              $key  Meta key.
 * @return mixed
 */
function velotok_core_content_approval_post_meta( $post, $key ) {
	if ( isset( $post[ $key ] ) ) {
		return $post[ $key ];
	}

	if ( isset( $post['meta'] ) && is_array( $post['meta'] ) && isset( $post['meta'][ $key ] ) ) {
		return $post['meta'][ $key ];
	}

	return '';
}

/**
 * Remove a meta value from normalized post data.
 *
 * @param array<string,mixed> $post Post data.
 * @param string              $key  Meta key.
 * @return void
 */
function velotok_core_content_approval_unset_post_meta( &$post, $key ) {
	unset( $post[ $key ] );

	if ( isset( $post['meta'] ) && is_array( $post['meta'] ) ) {
		unset( $post['meta'][ $key ] );
	}
}

/**
 * Normalize an accepted flag.
 *
 * @param mixed $accepted Raw accepted flag.
 * @return bool
 */
function velotok_core_content_approval_is_accepted( $accepted ) {
	if ( is_bool( $accepted ) ) {
		return $accepted;
	}

	if ( is_numeric( $accepted ) ) {
		return 1 === (int) $accepted;
	}

	if ( is_string( $accepted ) ) {
		return in_array( strtolower( trim( $accepted ) ), array( '1', 'true', 'yes', 'on', 'accepted' ), true );
	}

	if ( is_array( $accepted ) ) {
		foreach ( array( 'accepted', 'content_accepted', 'is_accepted' ) as $key ) {
			if ( array_key_exists( $key, $accepted ) ) {
				return velotok_core_content_approval_is_accepted( $accepted[ $key ] );
			}
		}
	}

	return false;
}

/**
 * Guard a post status transition.
 *
 * Publishing (or scheduling) a content article without a recorded human
 * approval is reverted to `pending`. This is the single rule used by the
 * update_post filter and by the REST API path.
 *
 * @param mixed $post Post data.
 * @return array<string,mixed>
 */
function velotok_core_content_approval_guard_transition( $post ) {
	$contract = velotok_core_content_approval_contract();
	$data     = velotok_core_content_approval_post_data( $post );

	$post_type = isset( $data['post_type'] ) ? (string) $data['post_type'] : 'post';
	$status    = isset( $data['post_status'] ) ? (string) $data['post_status'] : ( isset( $data['status'] ) ? (string) $data['status'] : '' );
	$approved  = velotok_core_content_approval_post_meta( $data, $contract['meta']['approved_by'] );
	$approval  = velotok_core_content_approval_post_meta( $data, $contract['meta']['status'] );

	$approved_by = is_numeric( $approved ) ? (int) $approved : 0;
	$is_approved = $approved_by > 0 && ( '' === $approval || 'approved' === $approval );
	$is_content  = in_array( $post_type, $contract['post_types'], true );
	$needs_guard = in_array( $status, array( 'publish', 'future' ), true );

	$allowed = ! $contract['enforce'] || ! $is_content || ! $needs_guard || $is_approved;

	if ( ! $allowed ) {
		$data['post_status'] = 'pending';
	}

	return array(
		'valid'           => $allowed,
		'allowed'         => $allowed,
		'post'            => $data,
		'post_status'     => isset( $data['post_status'] ) ? $data['post_status'] : $status,
		'original_status' => $status,
		'approved_by'     => $approved_by,
		'approved'        => $is_approved,
		'code'            => $allowed ? null : $contract['error']['required'],
		'error'           => $allowed ? null : $contract['error']['required'],
		'mocked'          => false,
	);
}

/**
 * Apply a human acceptance decision to post data.
 *
 * The helper is pure: it returns the post-shaped array that should be saved.
 * WordPress persistence is handled by the integration below.
 *
 * @param mixed $post     Post data.
 * @param mixed $user     Accepting user.
 * @param mixed $accepted Acceptance flag.
 * @return array<string,mixed>
 */
function velotok_core_content_approval_apply( $post, $user, $accepted = true ) {
	$contract = velotok_core_content_approval_contract();
	$data     = velotok_core_content_approval_post_data( $post );
	$accepted = velotok_core_content_approval_is_accepted( $accepted );
	$user_id  = velotok_core_content_approval_user_id( $user );

	$result = array(
		'valid'       => false,
		'accepted'    => false,
		'post'        => $data,
		'post_status' => isset( $data['post_status'] ) ? $data['post_status'] : '',
		'approved_by' => 0,
		'code'        => null,
		'error'       => null,
		'mocked'      => false,
	);

	if ( ! velotok_core_content_approval_user_is_authorized( $user ) ) {
		$result['code']  = $contract['error']['role_forbidden'];
		$result['error'] = $contract['error']['role_forbidden'];

		return $result;
	}

	if ( $accepted ) {
		$data[ $contract['meta']['approved_by'] ] = $user_id;
		$data[ $contract['meta']['status'] ]      = 'approved';
		$data[ $contract['meta']['approved_at'] ] = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'c' );
		$data['post_status']                      = 'publish';

		$result['valid']       = true;
		$result['accepted']    = true;
		$result['post']        = $data;
		$result['post_status'] = 'publish';
		$result['approved_by'] = $user_id;

		return $result;
	}

	velotok_core_content_approval_unset_post_meta( $data, $contract['meta']['approved_by'] );
	velotok_core_content_approval_unset_post_meta( $data, $contract['meta']['approved_at'] );
	$data[ $contract['meta']['status'] ] = 'pending';

	if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
		$data['meta'][ $contract['meta']['status'] ] = 'pending';
	}

	$data['post_status'] = 'pending';

	$result['valid']       = true;
	$result['accepted']    = false;
	$result['post']        = $data;
	$result['post_status'] = 'pending';

	return $result;
}

/*
 * WordPress integration.
 *
 * The hooks below are registered only when WordPress is loaded. The pure
 * helpers above remain usable by the standalone contract tests.
 */

if ( function_exists( 'add_action' ) ) {

	/**
	 * Register the project manager role with the approval capability.
	 *
	 * @return void
	 */
	function velotok_core_content_approval_register_role() {
		if ( ! function_exists( 'get_role' ) || ! function_exists( 'add_role' ) ) {
			return;
		}

		$contract = velotok_core_content_approval_contract();
		$role     = get_role( $contract['role'] );

		if ( null === $role ) {
			add_role(
				$contract['role'],
				$contract['role_display'],
				array(
					'read'                 => true,
					'edit_posts'           => true,
					'edit_published_posts' => true,
					'publish_posts'        => true,
					$contract['capability'] => true,
				)
			);
		} else {
			$role->add_cap( $contract['capability'] );
		}

		$admin = get_role( 'administrator' );

		if ( null !== $admin ) {
			$admin->add_cap( $contract['capability'] );
		}
	}

	/**
	 * Return post types protected by the approval rule.
	 *
	 * @return string[]
	 */
	function velotok_core_content_approval_post_types() {
		$contract = velotok_core_content_approval_contract();
		$types    = $contract['post_types'];

		if ( function_exists( 'apply_filters' ) ) {
			$types = (array) apply_filters( 'velotok_content_approval_post_types', $types );
		}

		return array_values( array_filter( array_map( 'strval', $types ) ) );
	}

	/**
	 * Enforce the approval rule before the post is written.
	 *
	 * This also covers wp_update_post(), wp_insert_post() and the REST API,
	 * because they all pass through wp_insert_post_data.
	 *
	 * @param array<string,mixed> $data    Prepared post data.
	 * @param array<string,mixed> $postarr Raw post data.
	 * @return array<string,mixed>
	 */
	function velotok_core_content_approval_filter_post_data( $data, $postarr ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$contract   = velotok_core_content_approval_contract();
		$post_type  = isset( $data['post_type'] ) ? (string) $data['post_type'] : '';
		$post_types = velotok_core_content_approval_post_types();

		if ( ! in_array( $post_type, $post_types, true ) ) {
			return $data;
		}

		$post_id    = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		$approved   = $post_id > 0 && function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, $contract['meta']['approved_by'], true ) : '';
		$approval   = $post_id > 0 && function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, $contract['meta']['status'], true ) : '';
		$candidate  = array(
			'post_type'                    => $post_type,
			'post_status'                  => isset( $data['post_status'] ) ? $data['post_status'] : '',
			$contract['meta']['approved_by'] => $approved,
			$contract['meta']['status']      => $approval,
		);
		$guard      = velotok_core_content_approval_guard_transition( $candidate );

		if ( ! $guard['allowed'] ) {
			$data['post_status'] = 'pending';
		}

		return $data;
	}

	/**
	 * Persist the approval decision for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $user    Accepting user.
	 * @return array<string,mixed>
	 */
	function velotok_core_content_approval_approve( $post_id, $user = null ) {
		$contract = velotok_core_content_approval_contract();
		$post_id  = (int) $post_id;

		if ( null === $user && function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
		}

		if ( ! velotok_core_content_approval_user_is_authorized( $user ) ) {
			return array(
				'valid'  => false,
				'post_id' => $post_id,
				'code'   => $contract['error']['role_forbidden'],
				'error'  => $contract['error']['role_forbidden'],
			);
		}

		if ( ! function_exists( 'get_post' ) || ! get_post( $post_id ) ) {
			return array(
				'valid'  => false,
				'post_id' => $post_id,
				'code'   => $contract['error']['post_invalid'],
				'error'  => $contract['error']['post_invalid'],
			);
		}

		$user_id = velotok_core_content_approval_user_id( $user );

		update_post_meta( $post_id, $contract['meta']['approved_by'], $user_id );
		update_post_meta( $post_id, $contract['meta']['status'], 'approved' );
		update_post_meta( $post_id, $contract['meta']['approved_at'], current_time( 'mysql' ) );

		velotok_core_content_approval_set_status( $post_id, 'publish' );

		return array(
			'valid'       => true,
			'post_id'     => $post_id,
			'approved_by' => $user_id,
			'post_status' => get_post_status( $post_id ),
			'code'        => null,
			'error'       => null,
		);
	}

	/**
	 * Revoke a previous approval and return the article to pending.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $user    Revoking user.
	 * @return array<string,mixed>
	 */
	function velotok_core_content_approval_revoke( $post_id, $user = null ) {
		$contract = velotok_core_content_approval_contract();
		$post_id  = (int) $post_id;

		if ( null === $user && function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
		}

		if ( ! velotok_core_content_approval_user_is_authorized( $user ) ) {
			return array(
				'valid'  => false,
				'post_id' => $post_id,
				'code'   => $contract['error']['role_forbidden'],
				'error'  => $contract['error']['role_forbidden'],
			);
		}

		$current_status = function_exists( 'get_post_status' ) ? get_post_status( $post_id ) : '';

		delete_post_meta( $post_id, $contract['meta']['approved_by'] );
		delete_post_meta( $post_id, $contract['meta']['approved_at'] );
		update_post_meta( $post_id, $contract['meta']['status'], 'pending' );

		if ( in_array( $current_status, array( 'publish', 'future' ), true ) ) {
			velotok_core_content_approval_set_status( $post_id, 'pending' );
		}

		return array(
			'valid'       => true,
			'post_id'     => $post_id,
			'approved_by' => 0,
			'post_status' => function_exists( 'get_post_status' ) ? get_post_status( $post_id ) : 'pending',
			'code'        => null,
			'error'       => null,
		);
	}

	/**
	 * Update a post status without recursing through save_post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $status  Target status.
	 * @return bool
	 */
	function velotok_core_content_approval_set_status( $post_id, $status ) {
		static $busy = array();

		$post_id = (int) $post_id;

		if ( isset( $busy[ $post_id ] ) ) {
			return false;
		}

		$busy[ $post_id ] = true;

		if ( function_exists( 'wp_update_post' ) ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => $status,
				)
			);
		}

		unset( $busy[ $post_id ] );

		return true;
	}

	/**
	 * Save the approval checkbox from the post editor.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $post    Post object.
	 * @return void
	 */
	function velotok_core_content_approval_save_metabox( $post_id, $post = null ) {
		if ( ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) )
			|| ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) ) {
			return;
		}

		if ( ! isset( $_POST[ velotok_core_content_approval_contract()['nonce'] ] ) ) {
			return;
		}

		$contract = velotok_core_content_approval_contract();
		$nonce    = sanitize_text_field( wp_unslash( $_POST[ $contract['nonce'] ] ) );

		if ( ! wp_verify_nonce( $nonce, $contract['nonce_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$checked = isset( $_POST[ $contract['field'] ] )
			&& velotok_core_content_approval_is_accepted( wp_unslash( $_POST[ $contract['field'] ] ) );
		$user    = wp_get_current_user();

		if ( $checked ) {
			velotok_core_content_approval_approve( $post_id, $user );
		} else {
			velotok_core_content_approval_revoke( $post_id, $user );
		}
	}

	/**
	 * Render the approval metabox.
	 *
	 * @param mixed $post Post object.
	 * @return void
	 */
	function velotok_core_content_approval_render_metabox( $post ) {
		$contract   = velotok_core_content_approval_contract();
		$post_id    = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : 0;
		$approved   = $post_id > 0 ? (int) get_post_meta( $post_id, $contract['meta']['approved_by'], true ) : 0;
		$authorized = velotok_core_content_approval_user_is_authorized( wp_get_current_user() );

		wp_nonce_field( $contract['nonce_action'], $contract['nonce'] );
		?>
		<p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $contract['field'] ); ?>" value="1" <?php checked( $approved > 0 ); ?> />
				<?php esc_html_e( 'Подтверждаю приёмку контента', 'velotok-core' ); ?>
			</label>
		</p>
		<p>
			<?php esc_html_e( 'Приёмку выполняет человек с ролью «Руководитель проекта». Без подтверждения статья остаётся в статусе «На ожидании».', 'velotok-core' ); ?>
		</p>
		<?php if ( $approved > 0 ) : ?>
			<p>
				<?php
				printf(
					/* translators: %d: user ID. */
					esc_html__( 'Подтвердил пользователь ID %d.', 'velotok-core' ),
					(int) $approved
				);
				?>
			</p>
		<?php endif; ?>
		<?php if ( ! $authorized ) : ?>
			<p>
				<?php esc_html_e( 'У текущего пользователя нет права подтверждать контент.', 'velotok-core' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Register the approval metabox for protected post types.
	 *
	 * @return void
	 */
	function velotok_core_content_approval_add_metabox() {
		foreach ( velotok_core_content_approval_post_types() as $post_type ) {
			add_meta_box(
				'velotok-content-approval',
				__( 'Приёмка контента', 'velotok-core' ),
				'velotok_core_content_approval_render_metabox',
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Bootstrap the content approval integration.
	 *
	 * @return void
	 */
	function velotok_core_content_approval_bootstrap() {
		velotok_core_content_approval_register_role();
	}

	add_action( 'init', 'velotok_core_content_approval_bootstrap' );
	add_filter( 'wp_insert_post_data', 'velotok_core_content_approval_filter_post_data', 10, 2 );
	add_action( 'add_meta_boxes', 'velotok_core_content_approval_add_metabox' );
	add_action( 'save_post', 'velotok_core_content_approval_save_metabox', 10, 2 );
}
