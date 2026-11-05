<?php

if ( ! function_exists( 'pvs_contract' ) ) {
	function pvs_contract() {
		static $contract = null;

		if ( null !== $contract ) {
			return $contract;
		}

		$severity_levels = array(
			'critical' => array( 'score' => 9.0, 'label' => 'Критическая', 'mocked' => true ),
			'high'     => array( 'score' => 7.0, 'label' => 'Высокая', 'mocked' => true ),
			'medium'   => array( 'score' => 4.0, 'label' => 'Средняя', 'mocked' => true ),
			'low'      => array( 'score' => 1.0, 'label' => 'Низкая', 'mocked' => true ),
		);

		$coverage = array(
			'scope'             => 'all_plugins',
			'all_plugins'       => true,
			'checks_all_plugins'=> true,
			'scans_all_plugins' => true,
			'includes_active'   => true,
			'includes_inactive' => true,
			'includes_mu_plugins' => true,
			'includes_dropins'  => true,
			'mocked'            => true,
			'network'           => false,
		);

		$contract = array(
			'spec'                  => 'ecommerce-store',
			'requirement'           => 'TESTEB-154',
			'parent_spec'           => 'ecommerce-store',
			'security_spec'         => 'ecommerce-store',
			'mocked'                => true,
			'source'                => 'stub',
			'network'               => false,
			'persisted'             => false,
			'all_plugins'           => true,
			'scans_all_plugins'     => true,
			'checks_all_plugins'    => true,
			'scan_all_plugins'      => true,
			'scan'                  => $coverage,
			'coverage'              => $coverage,
			'real_integration'      => array(
				'required'         => false,
				'network'          => false,
				'reads_env'        => false,
				'reads_files'      => false,
				'reads_database'   => false,
				'external_service' => false,
			),
			'markers'               => array(
				'mocked'            => true,
				'all_plugins'       => true,
				'scans_all_plugins' => true,
				'checks_all_plugins'=> true,
				'network'           => false,
				'source'            => 'stub',
			),
			'admin'                 => array(
				'page'          => 'pvs',
				'menu'          => 'tools.php',
				'capability'    => 'manage_options',
				'nonce_action'  => 'pvs_scan',
				'nonce_field'   => 'pvs_nonce',
				'form_action'   => 'pvs_scan',
				'ajax_action'   => 'pvs_scan',
				'mocked'        => true,
			),
			'plugin_sources'        => array(
				'plugins'     => true,
				'mu_plugins'  => true,
				'dropins'     => true,
				'inactive'    => true,
				'mocked'      => true,
			),
			'plugin_fields'         => array(
				'slug'        => array( 'required' => true, 'mocked' => true ),
				'name'        => array( 'required' => true, 'mocked' => true ),
				'version'     => array( 'required' => true, 'mocked' => true ),
				'plugin_file' => array( 'required' => true, 'mocked' => true ),
				'active'      => array( 'required' => true, 'mocked' => true ),
				'type'        => array( 'required' => true, 'mocked' => true ),
			),
			'vulnerability_fields'  => array(
				'id'         => array( 'required' => true, 'mocked' => true ),
				'title'      => array( 'required' => true, 'mocked' => true ),
				'severity'   => array( 'required' => true, 'mocked' => true ),
				'fixed_in'   => array( 'required' => false, 'mocked' => true ),
				'plugin'     => array( 'required' => true, 'mocked' => true ),
				'version'    => array( 'required' => true, 'mocked' => true ),
				'mocked'     => array( 'required' => true, 'mocked' => true ),
				'network'    => array( 'required' => true, 'mocked' => true ),
				'source'     => array( 'required' => true, 'mocked' => true ),
			),
			'severity_levels'       => $severity_levels,
			'severity_ids'          => array_keys( $severity_levels ),
			'error_codes'           => array(
				'invalid_input'     => 'PVS_INVALID_INPUT',
				'plugin_not_found'  => 'PVS_PLUGIN_NOT_FOUND',
				'scan_failed'       => 'PVS_SCAN_FAILED',
				'report_empty'      => 'PVS_REPORT_EMPTY',
			),
			'response_keys'         => array(
				'ok',
				'mocked',
				'source',
				'network',
				'scan_id',
				'all_plugins',
				'scans_all_plugins',
				'coverage',
				'total_plugins',
				'plugins_scanned',
				'vulnerable_plugins',
				'vulnerability_count',
				'plugins',
				'vulnerabilities',
				'errors',
				'error',
			),
			'future_source'         => 'env',
			'env'                   => array(
				'api_url'   => 'VELOTOK_PVS_API_URL',
				'api_token' => 'VELOTOK_PVS_API_TOKEN',
				'timeout'   => 'VELOTOK_PVS_TIMEOUT',
			),
			'open_questions'        => array(
				'provider'   => 'Какая внешняя база уязвимостей и протокол являются целевыми для реальной проверки.',
				'schedule'   => 'Как часто и по какому расписанию должна запускаться проверка всех плагинов.',
				'notify'     => 'Кому и каким способом отправлять отчёты о найденных уязвимостях.',
				'updates'    => 'Должна ли система автоматически обновлять уязвимые плагины.',
				'test_prod'  => 'Как разделяются тестовая и продуктовая базы уязвимостей.',
			),
		);

		return $contract;
	}
}

if ( ! function_exists( 'pvs_get_contract' ) ) {
	function pvs_get_contract() {
		return pvs_contract();
	}
}

if ( ! function_exists( 'pvs_plugin_vulnerability_contract' ) ) {
	function pvs_plugin_vulnerability_contract() {
		return pvs_contract();
	}
}

if ( ! function_exists( 'pvs_vulnerability_scanner_contract' ) ) {
	function pvs_vulnerability_scanner_contract() {
		return pvs_contract();
	}
}

if ( ! function_exists( 'velotok_core_pvs_contract' ) ) {
	function velotok_core_pvs_contract() {
		return pvs_contract();
	}
}

if ( ! function_exists( 'velotok_core_plugin_vulnerability_contract' ) ) {
	function velotok_core_plugin_vulnerability_contract() {
		return pvs_contract();
	}
}

if ( ! function_exists( 'velotok_core_plugin_vulnerability_scanner_contract' ) ) {
	function velotok_core_plugin_vulnerability_scanner_contract() {
		return pvs_contract();
	}
}

if ( ! function_exists( 'velotok_core_security_scan_contract' ) ) {
	function velotok_core_security_scan_contract() {
		return pvs_contract();
	}
}

if ( ! function_exists( 'pvs_is_mocked' ) ) {
	function pvs_is_mocked() {
		return true;
	}
}

if ( ! function_exists( 'pvs_checks_all_plugins' ) ) {
	function pvs_checks_all_plugins() {
		return true;
	}
}

if ( ! function_exists( 'pvs_scans_all_plugins' ) ) {
	function pvs_scans_all_plugins() {
		return true;
	}
}

if ( ! function_exists( 'pvs_contract_markers' ) ) {
	function pvs_contract_markers() {
		$contract = pvs_contract();

		return $contract['markers'];
	}
}

if ( ! function_exists( 'pvs_severity_levels' ) ) {
	function pvs_severity_levels() {
		$contract = pvs_contract();

		return $contract['severity_levels'];
	}
}

if ( ! function_exists( 'pvs_severity_label' ) ) {
	function pvs_severity_label( $severity ) {
		$severity = is_scalar( $severity ) ? strtolower( trim( (string) $severity ) ) : '';
		$levels   = pvs_severity_levels();

		if ( isset( $levels[ $severity ] ) ) {
			return $levels[ $severity ]['label'];
		}

		return '';
	}
}

if ( ! class_exists( 'PVS_Contract', false ) ) {
	class PVS_Contract {
		public static function get_contract() {
			return pvs_contract();
		}

		public static function contract() {
			return pvs_contract();
		}

		public static function get() {
			return pvs_contract();
		}

		public static function data() {
			return pvs_contract();
		}

		public static function all_plugins() {
			return true;
		}

		public static function scan_all_plugins() {
			return true;
		}

		public static function checks_all_plugins() {
			return true;
		}

		public static function markers() {
			$contract = pvs_contract();

			return $contract['markers'];
		}

		public static function get_markers() {
			return self::markers();
		}

		public static function is_mocked() {
			return true;
		}

		public static function severity_levels() {
			return pvs_severity_levels();
		}

		public static function severity_label( $severity ) {
			return pvs_severity_label( $severity );
		}

		public static function env_name( $key ) {
			$contract = pvs_contract();

			if ( ! is_string( $key ) || ! isset( $contract['env'][ $key ] ) ) {
				return '';
			}

			return $contract['env'][ $key ];
		}
	}
}
