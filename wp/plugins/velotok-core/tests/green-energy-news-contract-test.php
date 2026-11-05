<?php
/**
 * Contract test for the mocked green energy news stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/green-energy-news-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/green-energy-news-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer checks both the event description and the event date
 * required by clarify-a2fd2f66 (TESTEB-122).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/green-energy-news.php';
$override  = getenv( 'VELOTOK_GREEN_ENERGY_NEWS_STUB' );

if ( is_string( $override ) && '' !== $override ) {
	$stub_path = $override;
}

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки новостей о зелёной энергетике.' . PHP_EOL );
	exit( 1 );
}

require_once $stub_path;

$failures = array();

/**
 * Assert a contract condition.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_green_energy_news_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_core_green_energy_news_contract' ) ) {
	velotok_green_energy_news_assert( false, 'Отсутствует контракт новостей о зелёной энергетике.' );
} else {
	$contract = velotok_core_green_energy_news_contract();

	velotok_green_energy_news_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_green_energy_news_assert( isset( $contract['spec'] ) && 'clarify-a2fd2f66' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_green_energy_news_assert( isset( $contract['parent_spec'] ) && 'ecommerce-store' === $contract['parent_spec'], 'Не зафиксирована родительская спецификация.' );
	velotok_green_energy_news_assert( isset( $contract['task'] ) && 'TESTEB-122' === $contract['task'], 'Контракт не помечен задачей TESTEB-122.' );
	velotok_green_energy_news_assert( isset( $contract['requirement'] ) && 'Новости о зелёной энергетике' === $contract['requirement'], 'Неверное название требования.' );
	velotok_green_energy_news_assert( isset( $contract['topic'] ) && 'news' === $contract['topic'], 'Неверный идентификатор тематики.' );
	velotok_green_energy_news_assert( isset( $contract['label'] ) && 'Новости о зелёной энергетике' === $contract['label'], 'Неверная подпись тематики.' );
	velotok_green_energy_news_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_green_energy_news_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_green_energy_news_assert( isset( $contract['mocked_marker'] ) && 'MOCKED' === $contract['mocked_marker'], 'Не задан явный маркер MOCKED.' );
	velotok_green_energy_news_assert( defined( 'VELOTOK_CORE_GREEN_ENERGY_NEWS_MOCKED' ) && true === VELOTOK_CORE_GREEN_ENERGY_NEWS_MOCKED, 'Не задан mocked-маркер заглушки.' );
	velotok_green_energy_news_assert( isset( $contract['checks'] ) && array( 'event_description', 'event_date' ) === $contract['checks'], 'Заглушка должна проверять описание события и дату события.' );
	velotok_green_energy_news_assert( isset( $contract['not_checked'] ) && in_array( 'event_authenticity', $contract['not_checked'], true ), 'Достоверность события должна быть вне объёма проверки.' );
	velotok_green_energy_news_assert( isset( $contract['error_codes']['event_required'] ) && 'GREEN_ENERGY_NEWS_EVENT_REQUIRED' === $contract['error_codes']['event_required'], 'Не задан код отсутствующего события.' );
	velotok_green_energy_news_assert( isset( $contract['error_codes']['date_required'] ) && 'GREEN_ENERGY_NEWS_DATE_REQUIRED' === $contract['error_codes']['date_required'], 'Не задан код отсутствующей даты.' );
	velotok_green_energy_news_assert( isset( $contract['error_codes']['date_invalid'] ) && 'GREEN_ENERGY_NEWS_DATE_INVALID' === $contract['error_codes']['date_invalid'], 'Не задан код некорректной даты.' );
	velotok_green_energy_news_assert( isset( $contract['open_questions']['news_sources'] ), 'Не зафиксирован открытый вопрос об источниках новостей.' );
}

if ( function_exists( 'velotok_core_green_energy_news_check' ) ) {
	$valid_iso = array(
		'title'      => 'Новости зелёной энергетики: запущена солнечная электростанция',
		'summary'    => 'Компания сообщила о запуске нового объекта возобновляемой энергетики.',
		'event_date' => '2025-04-15',
	);
	$result = velotok_core_green_energy_news_check( $valid_iso );

	velotok_green_energy_news_assert( true === $result['valid'], 'Новость с описанием события и датой должна признаваться соответствующей.' );
	velotok_green_energy_news_assert( true === $result['compliant'], 'Соответствующая новость должна иметь compliant: true.' );
	velotok_green_energy_news_assert( true === $result['has_event'], 'Описание события должно находиться.' );
	velotok_green_energy_news_assert( true === $result['has_date'], 'Дата события должна находиться.' );
	velotok_green_energy_news_assert( '2025-04-15' === $result['event_date'], 'Дата события должна возвращаться в нормализованном виде.' );
	velotok_green_energy_news_assert( true === $result['mocked'], 'Результат проверки должен быть помечен mocked.' );
	velotok_green_energy_news_assert( 'MOCKED' === $result['mocked_marker'], 'Результат проверки должен содержать явный маркер MOCKED.' );
	velotok_green_energy_news_assert( array() === $result['missing'], 'У соответствующей новости не должно быть недостающих частей.' );
	velotok_green_energy_news_assert( null === $result['code'], 'У соответствующей новости не должно быть кода ошибки.' );

	$valid_text_date = array(
		'title'   => 'Новости зелёной энергетики',
		'content' => '1 марта 2025 года компания сообщила о запуске ветропарка.',
	);
	$text_date = velotok_core_green_energy_news_check( $valid_text_date );

	velotok_green_energy_news_assert( true === $text_date['valid'], 'Дата внутри текста должна приниматься.' );
	velotok_green_energy_news_assert( '2025-03-01' === $text_date['event_date'], 'Текстовая дата должна нормализоваться.' );

	$valid_dot_date = array(
		'title'      => 'Событие зелёной энергетики: открыта гидроэлектростанция',
		'event_date' => '20.05.2025',
	);
	$dot_date = velotok_core_green_energy_news_check( $valid_dot_date );

	velotok_green_energy_news_assert( true === $dot_date['valid'], 'Дата в формате ДД.ММ.ГГГГ должна приниматься.' );
	velotok_green_energy_news_assert( '2025-05-20' === $dot_date['event_date'], 'Дата ДД.ММ.ГГГГ должна нормализоваться.' );

	$without_date = array(
		'title'   => 'Новости зелёной энергетики: запущена солнечная электростанция',
		'summary' => 'Компания сообщила о запуске нового объекта возобновляемой энергетики.',
	);
	$no_date = velotok_core_green_energy_news_check( $without_date );

	velotok_green_energy_news_assert( false === $no_date['valid'], 'Новость без даты события не должна признаваться соответствующей.' );
	velotok_green_energy_news_assert( false === $no_date['has_date'], 'Материал без даты не должен получать признак даты.' );
	velotok_green_energy_news_assert( array( 'event_date' ) === $no_date['missing'], 'Должна быть зафиксирована отсутствующая дата события.' );
	velotok_green_energy_news_assert( 'GREEN_ENERGY_NEWS_DATE_REQUIRED' === $no_date['code'], 'Для новости без даты ожидается GREEN_ENERGY_NEWS_DATE_REQUIRED.' );
	velotok_green_energy_news_assert( true === $no_date['mocked'], 'Проверка отсутствия даты должна быть помечена mocked.' );

	$invalid_date = array(
		'title'      => 'Новости зелёной энергетики: запущена солнечная электростанция',
		'event_date' => '31.02.2025',
	);
	$bad_date = velotok_core_green_energy_news_check( $invalid_date );

	velotok_green_energy_news_assert( false === $bad_date['valid'], 'Некорректная дата не должна признаваться соответствующей.' );
	velotok_green_energy_news_assert( 'GREEN_ENERGY_NEWS_DATE_INVALID' === $bad_date['code'], 'Для некорректной даты ожидается GREEN_ENERGY_NEWS_DATE_INVALID.' );

	$placeholder_date = array(
		'title'      => 'Новости зелёной энергетики: запущена солнечная электростанция',
		'event_date' => 'нет',
	);
	$placeholder = velotok_core_green_energy_news_check( $placeholder_date );

	velotok_green_energy_news_assert( false === $placeholder['has_date'], 'Заглушка-значение даты не должна считаться датой.' );
	velotok_green_energy_news_assert( 'GREEN_ENERGY_NEWS_DATE_REQUIRED' === $placeholder['code'], 'Placeholder даты должен давать GREEN_ENERGY_NEWS_DATE_REQUIRED.' );

	$without_event = array(
		'title'      => 'Солнечная энергетика',
		'content'    => 'Описание оборудования для возобновляемой энергетики.',
		'event_date' => '2025-04-15',
	);
	$no_event = velotok_core_green_energy_news_check( $without_event );

	velotok_green_energy_news_assert( false === $no_event['valid'], 'Материал без описания события не должен признаваться соответствующим.' );
	velotok_green_energy_news_assert( array( 'event_description' ) === $no_event['missing'], 'Должно быть зафиксировано отсутствующее описание события.' );
	velotok_green_energy_news_assert( 'GREEN_ENERGY_NEWS_EVENT_REQUIRED' === $no_event['code'], 'Для материала без события ожидается GREEN_ENERGY_NEWS_EVENT_REQUIRED.' );

	$non_green = array(
		'title'      => 'Новости магазина: скидки и новые поступления',
		'event_date' => '2025-04-15',
	);
	$not_green = velotok_core_green_energy_news_check( $non_green );

	velotok_green_energy_news_assert( false === $not_green['valid'], 'Новость вне зелёной энергетики не должна признаваться соответствующей.' );
	velotok_green_energy_news_assert( false === $not_green['has_event'], 'Событие вне зелёной энергетики не должно засчитываться.' );

	$missing_both = velotok_core_green_energy_news_check( array( 'title' => 'Материал вне тематики' ) );

	velotok_green_energy_news_assert( false === $missing_both['valid'], 'Пустой материал не должен признаваться соответствующим.' );
	velotok_green_energy_news_assert( array( 'event_description', 'event_date' ) === $missing_both['missing'], 'Пустой материал должен фиксировать обе отсутствующие части.' );
	velotok_green_energy_news_assert( 'GREEN_ENERGY_NEWS_EVENT_REQUIRED' === $missing_both['code'], 'Первой должна возвращаться ошибка отсутствующего события.' );
}

velotok_green_energy_news_assert( function_exists( 'velotok_core_green_energy_news_matches' ), 'Отсутствует булев помощник проверки.' );
velotok_green_energy_news_assert( function_exists( 'velotok_core_validate_green_energy_news' ), 'Отсутствует alias проверки.' );
velotok_green_energy_news_assert( function_exists( 'greenEnergyNewsContract' ), 'Отсутствует короткий alias контракта.' );
velotok_green_energy_news_assert( function_exists( 'checkGreenEnergyNews' ), 'Отсутствует короткий alias проверки.' );

if ( function_exists( 'velotok_core_green_energy_news_matches' ) ) {
	$valid = array(
		'title'      => 'Новости зелёной энергетики: запущена солнечная электростанция',
		'event_date' => '2025-04-15',
	);
	$invalid = array(
		'title' => 'Новости зелёной энергетики: запущена солнечная электростанция',
	);

	velotok_green_energy_news_assert( true === velotok_core_green_energy_news_matches( $valid ), 'Булев помощник должен подтверждать соответствующую новость.' );
	velotok_green_energy_news_assert( false === velotok_core_green_energy_news_matches( $invalid ), 'Булев помощник должен отклонять новость без даты.' );
}

if ( function_exists( 'checkGreenEnergyNews' ) && function_exists( 'velotok_core_green_energy_news_check' ) ) {
	$material = array(
		'title'      => 'Новости зелёной энергетики: запущена солнечная электростанция',
		'event_date' => '2025-04-15',
	);

	velotok_green_energy_news_assert(
		velotok_core_green_energy_news_check( $material ) === checkGreenEnergyNews( $material ),
		'Короткий alias должен возвращать тот же результат.'
	);
}

$source = (string) file_get_contents( $stub_path );

$forbidden = array(
	'get' . 'env(',
	'apache_get' . 'env(',
	'$_' . 'ENV',
	'$_' . 'SERVER',
	'file_get' . '_contents(',
	'fopen(',
	'curl_' . 'init(',
	'wp_remote_' . 'get(',
	'wp_remote_' . 'post(',
);

foreach ( $forbidden as $needle ) {
	velotok_green_energy_news_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт новостей о зелёной энергетике на месте.' . PHP_EOL;
