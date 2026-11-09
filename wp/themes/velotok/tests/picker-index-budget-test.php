<?php
declare( strict_types=1 );

$script = __DIR__ . '/picker-index-budget.test.js';
$node   = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $node ) {
	echo 'SKIP: node недоступен, проверка JS-подборщика не запускалась.' . PHP_EOL;
	exit( 0 );
}

$command = escapeshellarg( $node ) . ' ' . escapeshellarg( $script );
passthru( $command, $status );

exit( (int) $status );
