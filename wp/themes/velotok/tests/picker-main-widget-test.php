<?php
/**
 * Проверка связки main.js + picker.js на всех комбинациях ответов.
 *
 * Запуск: php wp/themes/velotok/tests/picker-main-widget-test.php
 *
 * @package Velotok
 */

declare( strict_types=1 );

$script = __DIR__ . '/picker-main-widget.test.js';
$node   = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $node ) {
	echo 'SKIP: node недоступен, проверка виджета не запускалась.' . PHP_EOL;
	exit( 0 );
}

$command = escapeshellarg( $node ) . ' ' . escapeshellarg( $script );
passthru( $command, $status );

exit( (int) $status );
