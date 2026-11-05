<?php
/**
 * Contract test for the mocked legal requirements stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/legal-requirements-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/legal-requirements-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer checks both the requirement description and the source
 * reference required by clarify-a2fd2f66 (TESTEB-121).
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/legal-requirements.php';
$override  = getenv( 'VELOTOK_LEGAL_REQUIREMENTS_STUB' );

if ( is_string( $override ) && '' !== $override ) {
	$stub_path = $override;
}

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки требований законодательства.' . PHP_EOL );
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
function velotok_legal_requirements_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

if ( ! function_exists( 'velotok_core_legal_requirements_contract' ) ) {
	velotok_legal_requirements_assert( false, 'Отсутствует контракт требований законодательства.' );
} else {
	$contract = velotok_core_legal_requirements_contract();

	velotok_legal_requirements_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_legal_requirements_assert( isset( $contract['spec'] ) && 'clarify-a2fd2f66' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_legal_requirements_assert( isset( $contract['parent_spec'] ) && 'ecommerce-store' === $contract['parent_spec'], 'Не зафиксирована родительская спецификация.' );
	velotok_legal_requirements_assert( isset( $contract['task'] ) && 'TESTEB-121' === $contract['task'], 'Контракт не помечен задачей TESTEB-121.' );
	velotok_legal_requirements_assert( isset( $contract['requirement'] ) && 'Требования законодательства' === $contract['requirement'], 'Неверное название требования.' );
	velotok_legal_requirements_assert( isset( $contract['topic'] ) && 'legal_requirements' === $contract['topic'], 'Неверный идентификатор тематики.' );
	velotok_legal_requirements_assert( isset( $contract['label'] ) && 'Требования законодательства' === $contract['label'], 'Неверная подпись тематики.' );
	velotok_legal_requirements_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_legal_requirements_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_legal_requirements_assert( isset( $contract['mocked_marker'] ) && 'MOCKED' === $contract['mocked_marker'], 'Не задан явный маркер MOCKED.' );
	velotok_legal_requirements_assert( defined( 'VELOTOK_CORE_LEGAL_REQUIREMENTS_MOCKED' ) && true === VELOTOK_CORE_LEGAL_REQUIREMENTS_MOCKED, 'Не задан mocked-маркер заглушки.' );
	velotok_legal_requirements_assert( isset( $contract['checks'] ) && array( 'requirement_description', 'source_link' ) === $contract['checks'], 'Заглушка должна проверять описание требования и ссылку на источник.' );
	velotok_legal_requirements_assert( isset( $contract['not_checked'] ) && in_array( 'legal_correctness', $contract['not_checked'], true ), 'Юридическая корректность должна быть вне объёма проверки.' );
	velotok_legal_requirements_assert( isset( $contract['error_codes']['requirement_required'] ) && 'LEGAL_REQUIREMENT_DESCRIPTION_REQUIRED' === $contract['error_codes']['requirement_required'], 'Не задан код отсутствующего описания требования.' );
	velotok_legal_requirements_assert( isset( $contract['error_codes']['source_required'] ) && 'LEGAL_REQUIREMENT_SOURCE_REQUIRED' === $contract['error_codes']['source_required'], 'Не задан код отсутствующей ссылки на источник.' );
	velotok_legal_requirements_assert( isset( $contract['open_questions']['legal_accuracy'] ), 'Не зафиксирован открытый вопрос о юридической достоверности.' );
	velotok_legal_requirements_assert( isset( $contract['fields']['requirement'] ) && is_array( $contract['fields']['requirement'] ), 'Не заданы поля описания требования.' );
	velotok_legal_requirements_assert( isset( $contract['fields']['source'] ) && is_array( $contract['fields']['source'] ), 'Не заданы поля ссылки на источник.' );
}

if ( function_exists( 'velotok_core_legal_requirements_check' ) ) {
	$valid_url = array(
		'title'      => 'Требования законодательства к ветроэнергетике',
		'summary'    => 'Новые нормативы регулирования и сертификации объектов зелёной энергетики.',
		'source_url' => 'https://example.test/legal/wind',
	);
	$result = velotok_core_legal_requirements_check( $valid_url );

	velotok_legal_requirements_assert( true === $result['valid'], 'Материал с описанием требования и ссылкой должен признаваться соответствующим.' );
	velotok_legal_requirements_assert( true === $result['compliant'], 'Соответствующий материал должен иметь compliant: true.' );
	velotok_legal_requirements_assert( true === $result['has_requirement'], 'Описание нормативного требования должно находиться.' );
	velotok_legal_requirements_assert( true === $result['has_source'], 'Ссылка на источник должна находиться.' );
	velotok_legal_requirements_assert( 'https://example.test/legal/wind' === $result['source_reference'], 'Ссылка на источник должна возвращаться.' );
	velotok_legal_requirements_assert( true === $result['mocked'], 'Результат проверки должен быть помечен mocked.' );
	velotok_legal_requirements_assert( 'MOCKED' === $result['mocked_marker'], 'Результат проверки должен содержать явный маркер MOCKED.' );
	velotok_legal_requirements_assert( array() === $result['missing'], 'У соответствующего материала не должно быть недостающих частей.' );
	velotok_legal_requirements_assert( null === $result['code'], 'У соответствующего материала не должно быть кода ошибки.' );

	$valid_citation = array(
		'title'   => 'Нормативные требования к солнечной энергетике',
		'content' => 'Материал описывает обязательную сертификацию оборудования для возобновляемой энергетики.',
		'source'  => 'Федеральный закон № 35-ФЗ',
	);
	$citation = velotok_core_legal_requirements_check( $valid_citation );

	velotok_legal_requirements_assert( true === $citation['valid'], 'Текстовые реквизиты акта должны приниматься как ссылка на источник.' );
	velotok_legal_requirements_assert( true === $citation['has_source'], 'Реквизиты акта должны распознаваться как источник.' );

	$valid_text_url = array(
		'title'   => 'Требования законодательства к солнечным электростанциям',
		'content' => 'Нормативные требования к подключению. Источник: https://example.test/legal/solar',
	);
	$text_url = velotok_core_legal_requirements_check( $valid_text_url );

	velotok_legal_requirements_assert( true === $text_url['valid'], 'URL в тексте материала должен приниматься как ссылка на источник.' );

	$without_source = array(
		'title'   => 'Требования законодательства к ветроэнергетике',
		'summary' => 'Нормативы регулирования и сертификации зелёной энергетики.',
	);
	$no_source = velotok_core_legal_requirements_check( $without_source );

	velotok_legal_requirements_assert( false === $no_source['valid'], 'Материал без ссылки на источник не должен признаваться соответствующим.' );
	velotok_legal_requirements_assert( false === $no_source['has_source'], 'Материал без ссылки не должен получать признак источника.' );
	velotok_legal_requirements_assert( array( 'source_link' ) === $no_source['missing'], 'Должна быть зафиксирована отсутствующая ссылка на источник.' );
	velotok_legal_requirements_assert( 'LEGAL_REQUIREMENT_SOURCE_REQUIRED' === $no_source['code'], 'Для материала без источника ожидается LEGAL_REQUIREMENT_SOURCE_REQUIRED.' );
	velotok_legal_requirements_assert( true === $no_source['mocked'], 'Проверка отсутствия источника должна быть помечена mocked.' );

	$placeholder_source = array(
		'title'  => 'Требования законодательства к зелёной энергетике',
		'source' => 'нет',
	);
	$placeholder = velotok_core_legal_requirements_check( $placeholder_source );

	velotok_legal_requirements_assert( false === $placeholder['has_source'], 'Заглушка-значение источника не должна считаться ссылкой.' );
	velotok_legal_requirements_assert( 'LEGAL_REQUIREMENT_SOURCE_REQUIRED' === $placeholder['code'], 'Placeholder источника должен давать LEGAL_REQUIREMENT_SOURCE_REQUIRED.' );

	$without_requirement = array(
		'title'      => 'Запуск ветроэнергетической станции',
		'content'    => 'Новости зелёной энергетики и описание события.',
		'source_url' => 'https://example.test/news/launch',
	);
	$no_requirement = velotok_core_legal_requirements_check( $without_requirement );

	velotok_legal_requirements_assert( false === $no_requirement['valid'], 'Материал без описания требования не должен признаваться соответствующим.' );
	velotok_legal_requirements_assert( false === $no_requirement['has_requirement'], 'Описание требования не должно находиться в новости.' );
	velotok_legal_requirements_assert( array( 'requirement_description' ) === $no_requirement['missing'], 'Должно быть зафиксировано отсутствующее описание требования.' );
	velotok_legal_requirements_assert( 'LEGAL_REQUIREMENT_DESCRIPTION_REQUIRED' === $no_requirement['code'], 'Для материала без требования ожидается LEGAL_REQUIREMENT_DESCRIPTION_REQUIRED.' );

	$non_green = array(
		'title'      => 'Требования к упаковке товаров интернет-магазина',
		'content'    => 'Нормативные требования к упаковке и маркировке.',
		'source_url' => 'https://example.test/legal/packaging',
	);
	$not_green = velotok_core_legal_requirements_check( $non_green );

	velotok_legal_requirements_assert( false === $not_green['valid'], 'Материал вне зелёной энергетики не должен признаваться соответствующим.' );
	velotok_legal_requirements_assert( false === $not_green['has_requirement'], 'Требование вне зелёной энергетики не должно засчитываться.' );

	$missing_both = velotok_core_legal_requirements_check( array( 'title' => 'Материал вне тематики' ) );

	velotok_legal_requirements_assert( false === $missing_both['valid'], 'Пустой материал не должен признаваться соответствующим.' );
	velotok_legal_requirements_assert( array( 'requirement_description', 'source_link' ) === $missing_both['missing'], 'Пустой материал должен фиксировать обе отсутствующие части.' );
	velotok_legal_requirements_assert( 'LEGAL_REQUIREMENT_DESCRIPTION_REQUIRED' === $missing_both['code'], 'Первой должна возвращаться ошибка отсутствующего описания.' );
}

velotok_legal_requirements_assert( function_exists( 'velotok_core_legal_requirements_matches' ), 'Отсутствует булев помощник проверки.' );
velotok_legal_requirements_assert( function_exists( 'velotok_core_validate_legal_requirements' ), 'Отсутствует alias проверки.' );
velotok_legal_requirements_assert( function_exists( 'legalRequirementsContract' ), 'Отсутствует короткий alias контракта.' );
velotok_legal_requirements_assert( function_exists( 'checkLegalRequirements' ), 'Отсутствует короткий alias проверки.' );

if ( function_exists( 'velotok_core_legal_requirements_matches' ) ) {
	$valid = array(
		'title'      => 'Требования законодательства к зелёной энергетике',
		'summary'    => 'Нормативы сертификации солнечных электростанций.',
		'source_url' => 'https://example.test/legal/green',
	);
	$invalid = array(
		'title'      => 'Требования законодательства к зелёной энергетике',
		'summary'    => 'Нормативы сертификации солнечных электростанций.',
	);

	velotok_legal_requirements_assert( true === velotok_core_legal_requirements_matches( $valid ), 'Булев помощник должен подтверждать соответствующий материал.' );
	velotok_legal_requirements_assert( false === velotok_core_legal_requirements_matches( $invalid ), 'Булев помощник должен отклонять материал без источника.' );
}

if ( function_exists( 'checkLegalRequirements' ) && function_exists( 'velotok_core_legal_requirements_check' ) ) {
	$material = array(
		'title'   => 'Требования законодательства к ветроэнергетике',
		'summary' => 'Нормативы регулирования зелёной энергетики.',
		'source'  => 'https://example.test/legal/wind',
	);

	velotok_legal_requirements_assert(
		velotok_core_legal_requirements_check( $material ) === checkLegalRequirements( $material ),
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
	velotok_legal_requirements_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт требований законодательства на месте.' . PHP_EOL;
