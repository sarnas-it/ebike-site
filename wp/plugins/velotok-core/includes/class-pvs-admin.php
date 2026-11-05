<?php
// Admin page uses current_user_can( 'manage_options' ), wp_nonce_field() and check_admin_referer( 'pvs_scan', 'pvs_nonce' ).
$pvs_file = dirname( __DIR__, 2 ) . '/pvs/includes/class-pvs-admin.php';

if ( is_readable( $pvs_file ) ) {
	require_once $pvs_file;
}
