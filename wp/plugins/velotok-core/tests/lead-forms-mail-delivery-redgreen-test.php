<?php
/**
 * Red-green test for EBIKE-21: a lead form is successful only when the mail is
 * actually delivered, not merely stored by Flamingo.
 *
 * This test runs without WordPress/Docker. It exercises the same guard used by
 * wp/tests/lead-forms-e2e.sh and fails if the E2E script, compose mail catcher
 * or SMTP bridge stop enforcing real delivery.
 *
 * Run: php wp/plugins/velotok-core/tests/lead-forms-mail-delivery-redgreen-test.php
 *
 * @package Velotok_Core
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, 'This test must be run from the command line.' . PHP_EOL );
	exit( 1 );
}

$root     = dirname( __DIR__, 4 );
$failures = array();

/**
 * Record a failed assertion.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_lead_mail_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Run a command and return exit code, stdout and stderr.
 *
 * @param string $command Command.
 * @return array{code:int,out:string,err:string}
 */
function velotok_lead_mail_run( $command ) {
	$descriptors = array(
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);
	$process     = proc_open( $command, $descriptors, $pipes );

	if ( ! is_resource( $process ) ) {
		return array( 'code' => 127, 'out' => '', 'err' => 'proc_open failed' );
	}

	$out = stream_get_contents( $pipes[1] );
	$err = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$code = proc_close( $process );

	return array(
		'code' => (int) $code,
		'out'  => (string) $out,
		'err'  => (string) $err,
	);
}

if ( ! defined( 'VELOTOK_LEAD_MAIL_CHECK_LIBRARY_ONLY' ) ) {
	define( 'VELOTOK_LEAD_MAIL_CHECK_LIBRARY_ONLY', true );
}

$library = $root . '/wp/cli/lead-forms-mail-check.php';
$e2e     = $root . '/wp/tests/lead-forms-e2e.sh';
$compose = $root . '/docker-compose.yml';
$bridge  = $root . '/wp/mu-plugins/velotok-mailcatcher.php';

velotok_lead_mail_assert( is_readable( $library ), 'Не найден общий чекер wp/cli/lead-forms-mail-check.php.' );
velotok_lead_mail_assert( is_readable( $e2e ), 'Не найден E2E-тест wp/tests/lead-forms-e2e.sh.' );
velotok_lead_mail_assert( is_readable( $compose ), 'Не найден docker-compose.yml.' );
velotok_lead_mail_assert( is_readable( $bridge ), 'Не найден SMTP-мост wp/mu-plugins/velotok-mailcatcher.php.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}
	exit( 1 );
}

require $library;

// RED: the old behaviour accepted mail_failed. That must be rejected now.
velotok_lead_mail_assert( false === velotok_lead_mail_status_ok( 'mail_failed' ), 'mail_failed не должен считаться успешной отправкой.' );
velotok_lead_mail_assert( false === velotok_lead_mail_status_ok( '' ), 'Пустой статус не должен считаться успешной отправкой.' );
velotok_lead_mail_assert( false === velotok_lead_mail_response_valid( array( 'status' => 'mail_failed', 'invalid_fields' => array() ) ), 'CF7-ответ mail_failed должен быть красным.' );
velotok_lead_mail_assert( false === velotok_lead_mail_response_valid( array( 'status' => 'mail_sent', 'invalid_fields' => array( 'your-name' => 'bad' ) ) ), 'Ошибки валидации должны быть красными.' );
velotok_lead_mail_assert( true === velotok_lead_mail_response_valid( array( 'status' => 'mail_sent', 'invalid_fields' => array() ) ), 'CF7-ответ mail_sent без ошибок должен быть зелёным.' );

$good_message = array(
	'To'   => array( array( 'Address' => 'shop@example.test', 'Name' => 'Shop' ) ),
	'Text' => 'Новая заявка EBIKE21-test-drive-123',
	'HTML' => '<p>EBIKE21-test-drive-123</p>',
);
$wrong_recipient = $good_message;
$wrong_recipient['To'][0]['Address'] = 'other@example.test';
$wrong_body      = $good_message;
$wrong_body['Text'] = 'Новая заявка без маркера';
$wrong_body['HTML'] = '<p>Новая заявка без маркера</p>';

velotok_lead_mail_assert( true === velotok_lead_mail_delivery_matches( $good_message, 'shop@example.test', 'EBIKE21-test-drive-123' ), 'Доставленное письмо с получателем и маркером должно быть зелёным.' );
velotok_lead_mail_assert( false === velotok_lead_mail_delivery_matches( $wrong_recipient, 'shop@example.test', 'EBIKE21-test-drive-123' ), 'Письмо другому получателю должно быть красным.' );
velotok_lead_mail_assert( false === velotok_lead_mail_delivery_matches( $wrong_body, 'shop@example.test', 'EBIKE21-test-drive-123' ), 'Письмо без маркера заявки должно быть красным.' );
velotok_lead_mail_assert( true === velotok_lead_mail_delivery_matches( $good_message, 'shop@example.test, backup@example.test', 'EBIKE21-test-drive-123' ), 'Получатель из списка должен совпадать.' );

$e2e_source = (string) file_get_contents( $e2e );
velotok_lead_mail_assert( false !== strpos( $e2e_source, 'lead-forms-mail-check.php' ), 'E2E должен использовать общий чекер ответа и доставки.' );
velotok_lead_mail_assert( false !== strpos( $e2e_source, 'MAILPIT_URL' ), 'E2E должен проверять SMTP-ловушку Mailpit.' );
velotok_lead_mail_assert( false !== strpos( $e2e_source, '/api/v1/search' ), 'E2E должен искать реально доставленное письмо в Mailpit.' );
velotok_lead_mail_assert( false === strpos( $e2e_source, 'mail_failed' ), 'E2E не должен упоминать mail_failed как допустимый статус.' );

$compose_source = (string) file_get_contents( $compose );
velotok_lead_mail_assert( false !== strpos( $compose_source, 'mailpit:' ), 'docker-compose должен запускать SMTP-ловушку Mailpit.' );
velotok_lead_mail_assert( false !== strpos( $compose_source, 'VELOTOK_SMTP_HOST' ), 'WordPress должен получать адрес SMTP-ловушки.' );
velotok_lead_mail_assert( false !== strpos( $compose_source, 'wp/mu-plugins' ), 'SMTP-мост должен монтироваться в контейнер.' );

$bridge_source = (string) file_get_contents( $bridge );
velotok_lead_mail_assert( false !== strpos( $bridge_source, 'phpmailer_init' ), 'SMTP-мост должен перехватывать phpmailer_init.' );
velotok_lead_mail_assert( false !== strpos( $bridge_source, 'isSMTP' ), 'SMTP-мост должен включать SMTP-транспорт.' );

// Exercise the actual CLI guard as a subprocess: red input must exit non-zero,
// green input must exit zero.
$tmp = sys_get_temp_dir() . '/velotok-lead-mail-' . getmypid();
@mkdir( $tmp, 0777, true );
$failed_response = $tmp . '/failed.json';
$sent_response   = $tmp . '/sent.json';
$invalid_fields  = $tmp . '/invalid.json';
$good_delivery   = $tmp . '/delivery-good.json';
$bad_delivery    = $tmp . '/delivery-bad.json';

file_put_contents( $failed_response, '{"status":"mail_failed","invalid_fields":[]}' );
file_put_contents( $sent_response, '{"status":"mail_sent","invalid_fields":[]}' );
file_put_contents( $invalid_fields, '{"status":"mail_sent","invalid_fields":{"your-name":"required"}}' );
file_put_contents( $good_delivery, json_encode( $good_message ) );
file_put_contents( $bad_delivery, json_encode( $wrong_recipient ) );

$php = escapeshellarg( PHP_BINARY );
$lib = escapeshellarg( $library );

$red_failed = velotok_lead_mail_run( $php . ' ' . $lib . ' response ' . escapeshellarg( $failed_response ) );
velotok_lead_mail_assert( 0 !== $red_failed['code'], 'RED: CLI должен падать на mail_failed.' );

$red_invalid = velotok_lead_mail_run( $php . ' ' . $lib . ' response ' . escapeshellarg( $invalid_fields ) );
velotok_lead_mail_assert( 0 !== $red_invalid['code'], 'RED: CLI должен падать на invalid_fields.' );

$green_response = velotok_lead_mail_run( $php . ' ' . $lib . ' response ' . escapeshellarg( $sent_response ) );
velotok_lead_mail_assert( 0 === $green_response['code'] && 'mail_sent' === trim( $green_response['out'] ), 'GREEN: CLI должен принимать mail_sent.' );

$green_delivery = velotok_lead_mail_run( $php . ' ' . $lib . ' delivery ' . escapeshellarg( $good_delivery ) . ' shop@example.test EBIKE21-test-drive-123' );
velotok_lead_mail_assert( 0 === $green_delivery['code'], 'GREEN: CLI должен принимать доставленное письмо с маркером.' );

$red_delivery = velotok_lead_mail_run( $php . ' ' . $lib . ' delivery ' . escapeshellarg( $bad_delivery ) . ' shop@example.test EBIKE21-test-drive-123' );
velotok_lead_mail_assert( 0 !== $red_delivery['code'], 'RED: CLI должен падать, если письмо ушло другому получателю.' );

array_map( 'unlink', glob( $tmp . '/*' ) ?: array() );
@rmdir( $tmp );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}
	exit( 1 );
}

echo 'PASS: lead form delivery guard is red for mail_failed/undelivered mail and green only for mail_sent with a matching Mailpit message.' . PHP_EOL;
