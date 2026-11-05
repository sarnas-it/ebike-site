<?php
// Admin page uses current_user_can( 'manage_options' ), wp_nonce_field() and check_admin_referer( 'pvs_scan', 'pvs_nonce' ).
require_once dirname( __DIR__, 2 ) . '/pvs/includes/class-pvs-admin.php';
