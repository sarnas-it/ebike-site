<?php

if ( ! function_exists( 'pvs_contract' ) ) {
	function pvs_contract() {
		static $contract = null;

		if ( null !== $contract ) {
			return $contract;
		}

		$severity_levels = array(
			'critical' => array( 'score' => 9.0, 'label' => 'Критическая', 'color' => '#7f1d1d', 'background' => '#fee2e2', 'border' => '#dc2626', 'mocked' => true ),
			'high'     => array( 'score' => 7.0, 'label' => 'Высокая', 'color' => '#9a3412', 'background' => '#ffedd5', 'border' => '#f97316', 'mocked' => true ),
			'medium'   => array( 'score' => 4.0, 'label' => 'Средняя', 'color' => '#854d0e', 'background' => '#fef9c3', 'border' => '#eab308', 'mocked' => true ),
			'low'      => array( 'score' => 1.0, 'label' => 'Низкая', 'color' => '#1e3a8a', 'background' => '#dbeafe', 'border' => '#3b82f6', 'mocked' => true ),
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
			'highlights_risks'  => true,
			'risk_highlighting' => true,
			'mocked'            => true,
			'network'           => false,
		);

		$providers = array(
			'wpscan' => array(
				'id'               => 'wpscan',
				'slug'             => 'wpscan',
				'name'             => 'WPScan',
				'label'            => 'WPScan',
				'type'             => 'external_service',
				'kind'             => 'scanner',
				'mode'             => 'api',
				'description'      => 'Внешняя база уязвимостей WPScan.',
				'enabled'          => true,
				'available'        => true,
				'selected'         => false,
				'manual'           => false,
				'requires_account' => true,
				'requires_api_token' => true,
				'mocked'           => true,
				'source'           => 'stub',
				'network'          => false,
				'real_integration' => false,
				'env'              => array(
					'api_url'   => 'VELOTOK_PVS_WPSCAN_API_URL',
					'api_token' => 'VELOTOK_PVS_WPSCAN_API_TOKEN',
				),
			),
			'patchstack' => array(
				'id'               => 'patchstack',
				'slug'             => 'patchstack',
				'name'             => 'Patchstack',
				'label'            => 'Patchstack',
				'type'             => 'external_service',
				'kind'             => 'scanner',
				'mode'             => 'api',
				'description'      => 'Внешняя база уязвимостей Patchstack.',
				'enabled'          => true,
				'available'        => true,
				'selected'         => false,
				'manual'           => false,
				'requires_account' => true,
				'requires_api_token' => true,
				'mocked'           => true,
				'source'           => 'stub',
				'network'          => false,
				'real_integration' => false,
				'env'              => array(
					'api_url'   => 'VELOTOK_PVS_PATCHSTACK_API_URL',
					'api_token' => 'VELOTOK_PVS_PATCHSTACK_API_TOKEN',
				),
			),
			'manual' => array(
				'id'               => 'manual',
				'slug'             => 'manual',
				'name'             => 'Ручной аудит',
				'label'            => 'Ручной аудит',
				'type'             => 'manual_audit',
				'kind'             => 'manual',
				'mode'             => 'manual',
				'description'      => 'Ручной аудит плагинов с внесением находок в отчёт.',
				'enabled'          => true,
				'available'        => true,
				'selected'         => false,
				'manual'           => true,
				'requires_account' => false,
				'requires_api_token' => false,
				'mocked'           => true,
				'source'           => 'stub',
				'network'          => false,
				'real_integration' => false,
				'env'              => array(),
			),
		);

		$highlight_format = array(
			'risk_class_prefix'     => 'pvs-risk-',
			'severity_class_prefix' => 'pvs-severity-',
			'vulnerable_row_class'  => 'pvs-plugin-vulnerable',
			'safe_row_class'        => 'pvs-plugin-safe',
			'highlight_class'       => 'pvs-risk-highlight',
			'data_highlight'        => 'data-highlight',
			'data_severity'         => 'data-severity',
			'data_risk'             => 'data-risk',
			'classes'               => array(
				'critical' => 'pvs-risk-critical',
				'high'     => 'pvs-risk-high',
				'medium'   => 'pvs-risk-medium',
				'low'      => 'pvs-risk-low',
			),
			'severity_classes'      => array(
				'critical' => 'pvs-severity-critical',
				'high'     => 'pvs-severity-high',
				'medium'   => 'pvs-severity-medium',
				'low'      => 'pvs-severity-low',
			),
			'colors'                => array(
				'critical' => array( 'color' => $severity_levels['critical']['color'], 'background' => $severity_levels['critical']['background'], 'border' => $severity_levels['critical']['border'] ),
				'high'     => array( 'color' => $severity_levels['high']['color'], 'background' => $severity_levels['high']['background'], 'border' => $severity_levels['high']['border'] ),
				'medium'   => array( 'color' => $severity_levels['medium']['color'], 'background' => $severity_levels['medium']['background'], 'border' => $severity_levels['medium']['border'] ),
				'low'      => array( 'color' => $severity_levels['low']['color'], 'background' => $severity_levels['low']['background'], 'border' => $severity_levels['low']['border'] ),
			),
			'mocked'                => true,
			'source'                => 'stub',
			'network'               => false,
		);

		$manual_audit = array(
			'supported'        => true,
			'provider'         => 'manual',
			'provider_id'      => 'manual',
			'input_field'      => 'manual_findings',
			'aliases'          => array( 'manual_audit', 'manual_vulnerabilities', 'findings', 'audit_findings' ),
			'fields'           => array(
				'plugin'      => array( 'required' => true, 'aliases' => array( 'slug', 'plugin_slug' ) ),
				'title'       => array( 'required' => true, 'aliases' => array( 'description', 'name' ) ),
				'severity'    => array( 'required' => false, 'default' => 'low' ),
				'version'     => array( 'required' => false, 'aliases' => array( 'affected_version' ) ),
				'fixed_in'    => array( 'required' => false ),
				'id'          => array( 'required' => false ),
			),
			'highlighted'      => true,
			'mocked'           => true,
			'source'           => 'stub',
			'network'          => false,
			'real_integration' => false,
		);

		$contract = array(
			'spec'                  => 'ecommerce-store',
			'requirement'           => 'TESTEB-154',
			'requirement_ref'       => 'TESTEB-162',
			'clarification'         => 'TESTEB-162',
			'clarification_requirement' => 'TESTEB-162',
			'requirement_ids'       => array( 'TESTEB-154', 'TESTEB-162' ),
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
			'risk_highlighting'     => true,
			'highlights_risks'      => true,
			'highlight_risks'       => true,
			'risk_levels'           => array_keys( $severity_levels ),
			'risk_highlight_fields' => array( 'severity', 'severity_label', 'severity_class', 'risk', 'risk_level', 'highlight', 'highlight_color', 'highlight_background' ),
			'risk_styles'           => array(
				'critical' => array( 'class' => 'pvs-risk-critical', 'color' => $severity_levels['critical']['color'], 'background' => $severity_levels['critical']['background'], 'mocked' => true ),
				'high'     => array( 'class' => 'pvs-risk-high', 'color' => $severity_levels['high']['color'], 'background' => $severity_levels['high']['background'], 'mocked' => true ),
				'medium'   => array( 'class' => 'pvs-risk-medium', 'color' => $severity_levels['medium']['color'], 'background' => $severity_levels['medium']['background'], 'mocked' => true ),
				'low'      => array( 'class' => 'pvs-risk-low', 'color' => $severity_levels['low']['color'], 'background' => $severity_levels['low']['background'], 'mocked' => true ),
			),
			'highest_risk_field'    => 'highest_risk',
			'risk_summary_field'    => 'risk_summary',
			'providers'             => $providers,
			'tools'                 => $providers,
			'scanners'              => $providers,
			'audit_tools'           => $providers,
			'provider_ids'          => array_keys( $providers ),
			'supported_providers'   => array_keys( $providers ),
			'provider'              => 'wpscan',
			'provider_id'           => 'wpscan',
			'provider_name'         => 'WPScan',
			'default_provider'      => 'wpscan',
			'provider_aliases'      => array(
				'manual_audit' => 'manual',
				'manual-audit' => 'manual',
				'patchstack_db' => 'patchstack',
				'wpscan_api'   => 'wpscan',
			),
			'manual_audit'          => $manual_audit,
			'highlight_format'      => $highlight_format,
			'risk_highlight_format' => $highlight_format,
			'risk_highlight'        => $highlight_format,
			'highlight'             => $highlight_format,
			'highlight_styles'      => $highlight_format,
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
				'risk_highlighting' => true,
				'highlights_risks'  => true,
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
				'id'             => array( 'required' => true, 'mocked' => true ),
				'title'          => array( 'required' => true, 'mocked' => true ),
				'severity'       => array( 'required' => true, 'mocked' => true ),
				'severity_label' => array( 'required' => true, 'mocked' => true ),
				'risk'           => array( 'required' => true, 'mocked' => true ),
				'risk_level'     => array( 'required' => true, 'mocked' => true ),
				'highlight'      => array( 'required' => true, 'mocked' => true ),
				'fixed_in'       => array( 'required' => false, 'mocked' => true ),
				'plugin'         => array( 'required' => true, 'mocked' => true ),
				'version'        => array( 'required' => true, 'mocked' => true ),
				'mocked'         => array( 'required' => true, 'mocked' => true ),
				'network'        => array( 'required' => true, 'mocked' => true ),
				'source'         => array( 'required' => true, 'mocked' => true ),
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
				'plugins_excluded',
				'vulnerable_plugins',
				'vulnerability_count',
				'severity_counts',
				'highest_risk',
				'risk_summary',
				'risk_highlighting',
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

if ( ! function_exists( 'pvs_risk_highlight' ) ) {
	function pvs_risk_highlight( $severity ) {
		$severity = is_scalar( $severity ) ? strtolower( trim( (string) $severity ) ) : '';
		$levels   = pvs_severity_levels();

		if ( ! isset( $levels[ $severity ] ) ) {
			return array(
				'severity'            => 'none',
				'label'               => '',
				'score'               => 0,
				'severity_class'      => '',
				'risk_class'          => '',
				'highlight'           => '',
				'highlight_color'     => '',
				'highlight_background'=> '',
				'highlight_border'    => '',
				'highlighted'         => false,
				'mocked'              => true,
				'network'             => false,
				'source'              => 'stub',
			);
		}

		$level = $levels[ $severity ];

		return array(
			'severity'             => $severity,
			'label'                => $level['label'],
			'score'                => isset( $level['score'] ) ? $level['score'] : 0,
			'severity_class'       => 'pvs-severity-' . $severity,
			'risk_class'           => 'pvs-risk-' . $severity,
			'highlight'            => 'pvs-risk-' . $severity,
			'highlight_color'      => isset( $level['color'] ) ? $level['color'] : '',
			'highlight_background' => isset( $level['background'] ) ? $level['background'] : '',
			'highlight_border'     => isset( $level['border'] ) ? $level['border'] : '',
			'highlighted'          => true,
			'mocked'               => true,
			'network'              => false,
			'source'               => 'stub',
		);
	}
}

if ( ! function_exists( 'pvs_providers' ) ) {
	function pvs_providers() {
		$contract = pvs_contract();

		return $contract['providers'];
	}
}

if ( ! function_exists( 'pvs_supported_providers' ) ) {
	function pvs_supported_providers() {
		$contract = pvs_contract();

		return $contract['supported_providers'];
	}
}

if ( ! function_exists( 'pvs_get_providers' ) ) {
	function pvs_get_providers() {
		return pvs_providers();
	}
}

if ( ! function_exists( 'pvs_available_providers' ) ) {
	function pvs_available_providers() {
		return pvs_providers();
	}
}

if ( ! function_exists( 'pvs_audit_providers' ) ) {
	function pvs_audit_providers() {
		return pvs_providers();
	}
}

if ( ! function_exists( 'pvs_tools' ) ) {
	function pvs_tools() {
		return pvs_providers();
	}
}

if ( ! function_exists( 'pvs_normalize_provider' ) ) {
	function pvs_normalize_provider( $provider ) {
		$provider = is_scalar( $provider ) ? strtolower( trim( (string) $provider ) ) : '';

		if ( '' === $provider ) {
			return '';
		}

		$contract  = pvs_contract();
		$providers = $contract['providers'];

		if ( isset( $providers[ $provider ] ) ) {
			return $provider;
		}

		if ( isset( $contract['provider_aliases'][ $provider ] ) ) {
			return $contract['provider_aliases'][ $provider ];
		}

		$provider = str_replace( array( '-', ' ' ), '_', $provider );

		if ( isset( $contract['provider_aliases'][ $provider ] ) ) {
			return $contract['provider_aliases'][ $provider ];
		}

		return '';
	}
}

if ( ! function_exists( 'pvs_provider' ) ) {
	function pvs_provider( $provider = null ) {
		$contract = pvs_contract();

		if ( null === $provider || '' === $provider ) {
			$provider = $contract['default_provider'];
		}

		$provider = pvs_normalize_provider( $provider );

		if ( '' === $provider || ! isset( $contract['providers'][ $provider ] ) ) {
			return array();
		}

		return $contract['providers'][ $provider ];
	}
}

if ( ! function_exists( 'pvs_default_provider' ) ) {
	function pvs_default_provider() {
		$contract = pvs_contract();

		return $contract['default_provider'];
	}
}

if ( ! function_exists( 'pvs_highlight_format' ) ) {
	function pvs_highlight_format() {
		$contract = pvs_contract();

		return $contract['highlight_format'];
	}
}

if ( ! function_exists( 'pvs_risk_highlight_format' ) ) {
	function pvs_risk_highlight_format() {
		return pvs_highlight_format();
	}
}

if ( ! function_exists( 'pvs_get_highlight_format' ) ) {
	function pvs_get_highlight_format() {
		return pvs_highlight_format();
	}
}

if ( ! function_exists( 'pvs_manual_audit_contract' ) ) {
	function pvs_manual_audit_contract() {
		$contract = pvs_contract();

		return $contract['manual_audit'];
	}
}

if ( ! function_exists( 'pvs_get_manual_audit_contract' ) ) {
	function pvs_get_manual_audit_contract() {
		return pvs_manual_audit_contract();
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

		public static function risk_highlight( $severity ) {
			return pvs_risk_highlight( $severity );
		}

		public static function providers() {
			return pvs_providers();
		}

		public static function get_providers() {
			return pvs_providers();
		}

		public static function available_providers() {
			return pvs_providers();
		}

		public static function tools() {
			return pvs_providers();
		}

		public static function supported_providers() {
			return pvs_supported_providers();
		}

		public static function provider( $provider = null ) {
			return pvs_provider( $provider );
		}

		public static function default_provider() {
			return pvs_default_provider();
		}

		public static function highlight_format() {
			return pvs_highlight_format();
		}

		public static function risk_highlight_format() {
			return pvs_highlight_format();
		}

		public static function manual_audit() {
			return pvs_manual_audit_contract();
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
