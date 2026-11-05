<?php
/**
 * Contract test for the mocked content thematic coverage stub.
 *
 * Standalone:
 *   php wp/plugins/velotok-core/tests/content-thematic-coverage-contract-test.php
 *
 * Inside WordPress:
 *   wp eval-file wp-content/plugins/velotok-core/tests/content-thematic-coverage-contract-test.php
 *
 * The test intentionally fails when the stub is removed, stops being marked as
 * mocked or no longer exposes the three required content areas and their
 * matching contracts.
 *
 * @package Velotok_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$stub_path = dirname( __DIR__ ) . '/includes/content-thematic-coverage.php';

if ( ! is_readable( $stub_path ) ) {
	fwrite( STDERR, 'FAIL: Не найден файл заглушки тематического охвата контента.' . PHP_EOL );
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
function velotok_content_contract_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Return a material fixture for a content topic.
 *
 * @param string $topic Topic key.
 * @return array<string,string>
 */
function velotok_content_material( $topic ) {
	$materials = array(
		'model_overview'     => array(
			'title'   => 'Обзор моделей солнечных панелей и инверторов для дома',
			'summary' => 'Сравнение характеристик и линейки оборудования для зелёной энергетики.',
		),
		'legal_requirements' => array(
			'title'      => 'Требования законодательства к ветроэнергетике',
			'summary'    => 'Новые нормативы регулирования и сертификации зелёной энергетики.',
			'source_url' => 'https://example.test/legal/wind',
		),
		'green_energy_news'  => array(
			'title'   => 'Новости зелёной энергетики: запущена солнечная электростанция',
			'summary' => 'Компания сообщила о запуске нового объекта возобновляемой энергетики.',
		),
	);

	return isset( $materials[ $topic ] ) ? $materials[ $topic ] : array();
}

$expected_topics = array( 'model_overview', 'legal_requirements', 'green_energy_news' );
$expected_labels = array(
	'model_overview'     => 'Обзор моделей в зелёной энергетике',
	'legal_requirements' => 'Требования законодательства',
	'green_energy_news'  => 'Новости о зелёной энергетике',
);

if ( ! function_exists( 'velotok_core_content_thematic_contract' ) ) {
	velotok_content_contract_assert( false, 'Отсутствует контракт тематического охвата контента.' );
} else {
	$contract = velotok_core_content_thematic_contract();

	velotok_content_contract_assert( is_array( $contract ), 'Контракт должен быть массивом.' );
	velotok_content_contract_assert( isset( $contract['mocked'] ) && true === $contract['mocked'], 'Контракт не помечен mocked: true.' );
	velotok_content_contract_assert( isset( $contract['spec'] ) && 'clarify-a2fd2f66' === $contract['spec'], 'Контракт ссылается на неверную спеку.' );
	velotok_content_contract_assert( isset( $contract['source'] ) && 'stub' === $contract['source'], 'Источником должна быть заглушка.' );
	velotok_content_contract_assert( isset( $contract['parent_spec'] ) && 'ecommerce-store' === $contract['parent_spec'], 'Не зафиксирована родительская спецификация.' );
	velotok_content_contract_assert( isset( $contract['membership'] ) && 'hybrid' === $contract['membership'], 'Способ определения принадлежности должен быть зафиксирован как гибридный.' );
	velotok_content_contract_assert( isset( $contract['open_questions'] ) && 5 === count( $contract['open_questions'] ), 'Не зафиксированы пять открытых вопросов.' );

	velotok_content_contract_assert(
		isset( $contract['required_topics'] ) && $expected_topics === $contract['required_topics'],
		'Тематический охват должен включать ровно три обязательные области.'
	);

	foreach ( $expected_topics as $topic ) {
		velotok_content_contract_assert( isset( $contract['topics'][ $topic ] ), sprintf( 'Не описана тематика «%s».', $topic ) );
		velotok_content_contract_assert(
			isset( $contract['topics'][ $topic ]['label'] ) && $expected_labels[ $topic ] === $contract['topics'][ $topic ]['label'],
			sprintf( 'Неверная подпись тематики «%s».', $topic )
		);
		velotok_content_contract_assert(
			isset( $contract['topics'][ $topic ]['mocked'] ) && true === $contract['topics'][ $topic ]['mocked'],
			sprintf( 'Тематика «%s» не помечена mocked: true.', $topic )
		);
		velotok_content_contract_assert(
			isset( $contract['topics'][ $topic ]['criteria']['topic_terms'] ) && is_array( $contract['topics'][ $topic ]['criteria']['topic_terms'] ),
			sprintf( 'Не заданы критерии тематики «%s».', $topic )
		);
	}
}

if ( function_exists( 'velotok_core_validate_content_topic' ) ) {
	foreach ( $expected_topics as $topic ) {
		$positive = velotok_core_validate_content_topic( velotok_content_material( $topic ), $topic );

		velotok_content_contract_assert( true === $positive['valid'], sprintf( 'Материал тематики «%s» должен признаваться соответствующим.', $topic ) );
		velotok_content_contract_assert( true === $positive['matched'], sprintf( 'Материал тематики «%s» должен получать признак совпадения.', $topic ) );
		velotok_content_contract_assert( true === $positive['mocked'], sprintf( 'Проверка тематики «%s» должна быть помечена mocked.', $topic ) );
		velotok_content_contract_assert( null === $positive['code'], sprintf( 'У соответствующего материала «%s» не должно быть ошибки.', $topic ) );
	}

	$negative_materials = array(
		'model_overview'     => array( 'title' => 'Обзор городских велосипедов для ежедневных поездок' ),
		'legal_requirements' => array( 'title' => 'Требования к упаковке товаров интернет-магазина' ),
		'green_energy_news'  => array( 'title' => 'Новости магазина: скидки и новые поступления' ),
	);

	foreach ( $negative_materials as $topic => $material ) {
		$negative = velotok_core_validate_content_topic( $material, $topic );

		velotok_content_contract_assert( false === $negative['valid'], sprintf( 'Посторонний материал не должен признаваться соответствующим тематике «%s».', $topic ) );
		velotok_content_contract_assert( false === $negative['matched'], sprintf( 'Посторонний материал не должен получать совпадение с тематикой «%s».', $topic ) );
		velotok_content_contract_assert( null !== $negative['code'], sprintf( 'Для несоответствия тематике «%s» должен быть код ошибки.', $topic ) );
	}

	$legal_without_source = velotok_core_validate_content_topic(
		array(
			'title'   => 'Требования законодательства к ветроэнергетике',
			'summary' => 'Нормативы регулирования и сертификации зелёной энергетики.',
		),
		'legal_requirements'
	);
	velotok_content_contract_assert( false === $legal_without_source['valid'], 'Материал о требованиях законодательства без ссылки на источник не должен признаваться соответствующим.' );
	velotok_content_contract_assert( in_array( 'source_link', $legal_without_source['missing'], true ), 'Отсутствие ссылки на источник должно фиксироваться в результате.' );

	$promotional = velotok_core_validate_content_topic(
		array( 'title' => 'Обзор моделей солнечных панелей: купить со скидкой' ),
		'model_overview'
	);
	velotok_content_contract_assert( false === $promotional['valid'], 'Продвижение конкретного продукта не должно считаться обзором моделей.' );
	velotok_content_contract_assert( true === $promotional['promotion_detected'], 'Продвижение в обзоре моделей должно выявляться.' );
	velotok_content_contract_assert( 'MODEL_PROMOTION_DETECTED' === $promotional['code'], 'Для продвижения должен возвращаться MODEL_PROMOTION_DETECTED.' );

	$manual = velotok_core_validate_content_topic(
		array( 'topic' => 'green_energy_news', 'title' => 'Пресс-релиз компании' ),
		'green_energy_news'
	);
	velotok_content_contract_assert( true === $manual['valid'], 'Явная тематическая метка должна поддерживаться гибридным правилом.' );
	velotok_content_contract_assert( 'manual' === $manual['classification'], 'Явная метка должна помечать способ определения как manual.' );

	$unknown = velotok_core_validate_content_topic( velotok_content_material( 'model_overview' ), 'unknown_topic' );
	velotok_content_contract_assert( false === $unknown['valid'], 'Неизвестная тематика не должна быть валидной.' );
	velotok_content_contract_assert( 'TOPIC_UNKNOWN' === $unknown['code'], 'Для неизвестной тематики должен возвращаться TOPIC_UNKNOWN.' );
}

if ( function_exists( 'velotok_core_classify_content' ) ) {
	$classified = velotok_core_classify_content( velotok_content_material( 'legal_requirements' ) );
	velotok_content_contract_assert( true === $classified['valid'], 'Классификатор должен распознавать материал.' );
	velotok_content_contract_assert( in_array( 'legal_requirements', $classified['matches'], true ), 'Классификатор должен относить материал к требованиям законодательства.' );
	velotok_content_contract_assert( true === $classified['mocked'], 'Классификатор должен быть помечен mocked.' );
}

if ( function_exists( 'velotok_core_check_content_coverage' ) ) {
	$complete = velotok_core_check_content_coverage(
		array(
			velotok_content_material( 'model_overview' ),
			velotok_content_material( 'legal_requirements' ),
			velotok_content_material( 'green_energy_news' ),
		)
	);

	velotok_content_contract_assert( true === $complete['complete'], 'Набор из трёх тематик должен покрывать охват полностью.' );
	velotok_content_contract_assert( true === $complete['valid'], 'Полный охват должен быть валидным.' );
	velotok_content_contract_assert( array() === $complete['missing'], 'У полного охвата не должно быть недостающих тематик.' );
	velotok_content_contract_assert( true === $complete['mocked'], 'Результат проверки охвата должен быть помечен mocked.' );
	velotok_content_contract_assert( 3 === $complete['required_count'], 'В охвате должно быть три обязательные области.' );

	$incomplete = velotok_core_check_content_coverage(
		array(
			velotok_content_material( 'model_overview' ),
			velotok_content_material( 'legal_requirements' ),
		)
	);

	velotok_content_contract_assert( false === $incomplete['complete'], 'Набор без новостей не должен считаться полным.' );
	velotok_content_contract_assert( array( 'green_energy_news' ) === $incomplete['missing'], 'Должна быть зафиксирована недостающая тематика новостей.' );
	velotok_content_contract_assert( in_array( 'CONTENT_COVERAGE_INCOMPLETE', $incomplete['errors'], true ), 'Для неполного охвата должен возвращаться CONTENT_COVERAGE_INCOMPLETE.' );

	$empty = velotok_core_check_content_coverage( array() );
	velotok_content_contract_assert( false === $empty['complete'], 'Пустой набор не должен считаться полным.' );
	velotok_content_contract_assert( $expected_topics === $empty['missing'], 'Пустой набор должен не покрывать все три тематики.' );
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
	velotok_content_contract_assert( false === strpos( $source, $needle ), sprintf( 'Stub не должен обращаться к внешнему источнику: %s', $needle ) );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'OK: mocked-контракт тематического охвата контента на месте.' . PHP_EOL;
