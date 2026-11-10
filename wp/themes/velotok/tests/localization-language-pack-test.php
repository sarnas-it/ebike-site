<?php
/**
 * Behaviour test for EBIKE-20: the ru_RU language packs are actually installed.
 *
 * Run from the project root:
 *   php wp/themes/velotok/tests/localization-language-pack-test.php
 *
 * The earlier localisation test only proved that the storefront locale is
 * forced to ru_RU and that a fallback dictionary translates the strings. This
 * test proves the second half of the requirement: the setup explicitly installs
 * the WordPress core and WooCommerce ru_RU language packs instead of relying on
 * the locale filter/WPLANG alone, and reports whether the .mo files are present.
 *
 * @package Velotok
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, 'This test must be run from the command line.' . PHP_EOL );
	exit( 1 );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$lang_dir = sys_get_temp_dir() . '/velotok-l10n-' . getmypid();
if ( ! is_dir( $lang_dir . '/plugins' ) && ! mkdir( $lang_dir . '/plugins', 0777, true ) && ! is_dir( $lang_dir . '/plugins' ) ) {
	fwrite( STDERR, 'Cannot create temporary language directory.' . PHP_EOL );
	exit( 1 );
}

if ( ! defined( 'WP_LANG_DIR' ) ) {
	define( 'WP_LANG_DIR', $lang_dir );
}

$GLOBALS['velotok_l10n_filters'] = array();
$failures                        = array();

/**
 * Register a failed assertion.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Failure message.
 * @return void
 */
function velotok_l10n_pack_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * WordPress filter registration stub.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $accepted Accepted args.
 * @return bool
 */
function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) {
	$GLOBALS['velotok_l10n_filters'][ $hook ][] = $callback;

	return true;
}

require_once dirname( __DIR__ ) . '/inc/localization.php';

/**
 * Remove the temporary language directory and its files.
 *
 * @param string $dir Directory.
 * @return void
 */
function velotok_l10n_pack_cleanup( $dir ) {
	foreach ( glob( $dir . '/plugins/*' ) as $file ) {
		@unlink( $file );
	}

	@rmdir( $dir . '/plugins' );
	@rmdir( $dir );
}

/*
 * 1. The required language pack functions exist.
 */
foreach ( array( 'velotok_localization_language_packs', 'velotok_localization_language_pack_commands', 'velotok_localization_install_language_packs', 'velotok_localization_language_packs_installed' ) as $function_name ) {
	velotok_l10n_pack_assert( function_exists( $function_name ), 'Не определена функция языковых пакетов: ' . $function_name . '.' );
}

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок языковых пакетов не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	velotok_l10n_pack_cleanup( $lang_dir );
	exit( 1 );
}

/*
 * 2. Core ru_RU and WooCommerce ru_RU are both required, and core is activated.
 */
$packs = velotok_localization_language_packs();
$by_slug = array();
foreach ( $packs as $pack ) {
	$by_slug[ $pack['type'] . ':' . $pack['slug'] ] = $pack;
}

velotok_l10n_pack_assert( isset( $by_slug['core:ru_RU'] ), 'Требуется языковой пакет ядра ru_RU.' );
velotok_l10n_pack_assert( isset( $by_slug['plugin:woocommerce'] ), 'Требуется языковой пакет WooCommerce ru_RU.' );
velotok_l10n_pack_assert(
	isset( $by_slug['core:ru_RU'] ) && ! empty( $by_slug['core:ru_RU']['activate'] ),
	'Языковой пакет ядра ru_RU должен активироваться.'
);
velotok_l10n_pack_assert(
	isset( $by_slug['plugin:woocommerce'] ) && 'ru_RU' === $by_slug['plugin:woocommerce']['language'],
	'Языковой пакет WooCommerce должен быть именно ru_RU.'
);

/*
 * 3. The generated WP-CLI commands really install both packs.
 */
$commands = velotok_localization_language_pack_commands();
velotok_l10n_pack_assert(
	in_array( 'language core install ru_RU --activate', $commands, true ),
	'Команда установки языкового пакета ядра ru_RU не сформирована.'
);
velotok_l10n_pack_assert(
	in_array( 'language plugin install woocommerce ru_RU', $commands, true ),
	'Команда установки языкового пакета WooCommerce ru_RU не сформирована.'
);

/*
 * 4. The installer passes every command to the runner, so the packs are
 *    installed by WP-CLI rather than merely written to the WPLANG option.
 */
$executed = array();
$results  = velotok_localization_install_language_packs(
	function ( $command ) use ( &$executed ) {
		$executed[] = $command;

		return true;
	}
);

velotok_l10n_pack_assert( $executed === $commands, 'Исполнитель установки должен получить все команды языковых пакетов.' );
velotok_l10n_pack_assert( ! in_array( false, $results, true ), 'Успешный исполнитель должен установить все языковые пакеты.' );

$failed = velotok_localization_install_language_packs(
	function () {
		return false;
	}
);
velotok_l10n_pack_assert( in_array( false, $failed, true ), 'Ошибка установки языкового пакета не должна скрываться.' );

/*
 * 5. The installed state is read from the actual ru_RU .mo files.
 */
$paths = velotok_localization_language_pack_paths();
velotok_l10n_pack_assert( $paths['core'] === $lang_dir . '/ru_RU.mo', 'Путь языкового пакета ядра должен указывать на ru_RU.mo.' );
velotok_l10n_pack_assert( $paths['woocommerce'] === $lang_dir . '/plugins/woocommerce-ru_RU.mo', 'Путь языкового пакета WooCommerce должен указывать на woocommerce-ru_RU.mo.' );

$installed = velotok_localization_language_packs_installed();
velotok_l10n_pack_assert( false === $installed['core'], 'Отсутствующий файл ru_RU.mo не должен считаться установленным.' );

file_put_contents( $lang_dir . '/ru_RU.mo', 'core' );
$installed = velotok_localization_language_packs_installed();
velotok_l10n_pack_assert( true === $installed['core'], 'Установленный ru_RU.mo должен определяться как языковой пакет ядра.' );
velotok_l10n_pack_assert( false === $installed['woocommerce'], 'Без файла woocommerce-ru_RU.mo пакет WooCommerce не установлен.' );

file_put_contents( $lang_dir . '/plugins/woocommerce-ru_RU.mo', 'woocommerce' );
$installed = velotok_localization_language_packs_installed();
velotok_l10n_pack_assert( true === $installed['woocommerce'], 'Установленный woocommerce-ru_RU.mo должен определяться как языковой пакет WooCommerce.' );

/*
 * 6. Setup and docker-compose wire the installer, so a fresh site gets the
 *    language packs instead of only the locale filter.
 */
$setup_script = file_get_contents( dirname( __DIR__, 3 ) . '/cli/setup.sh' );
$compose      = file_get_contents( dirname( __DIR__, 4 ) . '/docker-compose.yml' );

velotok_l10n_pack_assert(
	false !== strpos( $setup_script, 'velotok_localization_install_language_packs' ),
	'wp/cli/setup.sh должен вызывать установку языковых пакетов ru_RU.'
);
velotok_l10n_pack_assert(
	false !== strpos( $compose, 'velotok_localization_install_language_packs' ),
	'docker-compose.yml должен вызывать установку языковых пакетов ru_RU.'
);

/*
 * 7. The installation is not only a locale filter: the setup scripts contain
 *    the explicit WP-CLI commands that download the ru_RU pack files.
 */
velotok_l10n_pack_assert(
	false !== strpos( $setup_script, 'language core install ru_RU' ),
	'wp/cli/setup.sh должен явно ставить языковой пакет ядра ru_RU.'
);
velotok_l10n_pack_assert(
	false !== strpos( $compose, 'language core install ru_RU' ),
	'docker-compose.yml должен явно ставить языковой пакет ядра ru_RU.'
);
velotok_l10n_pack_assert(
	false !== strpos( $compose, 'language plugin install woocommerce ru_RU' ),
	'docker-compose.yml должен явно ставить языковой пакет WooCommerce ru_RU.'
);

velotok_l10n_pack_cleanup( $lang_dir );

if ( $failures ) {
	fwrite( STDERR, 'FAIL: ' . count( $failures ) . ' проверок языковых пакетов не прошли:' . PHP_EOL );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, ' - ' . $failure . PHP_EOL );
	}

	exit( 1 );
}

echo 'PASS: языковые пакеты ru_RU для ядра и WooCommerce устанавливаются (EBIKE-20).' . PHP_EOL;
exit( 0 );
