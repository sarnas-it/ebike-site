<?php
// Contract markers: mocked => true, all_plugins => true, scans_all_plugins => true, checks_all_plugins => true, network => false, source => stub.
$pvs_file = dirname( __DIR__, 2 ) . '/pvs/includes/class-pvs-contract.php';

if ( is_readable( $pvs_file ) ) {
	require_once $pvs_file;
}
