<?php
// Scanner functions: pvs_scan_all_plugins(), PVS_Scanner::scan(); reports contain mocked => true and network => false.
$pvs_file = dirname( __DIR__, 2 ) . '/pvs/includes/class-pvs-scanner.php';

if ( is_readable( $pvs_file ) ) {
	require_once $pvs_file;
}
