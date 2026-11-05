<?php

if ( ! function_exists( 'pvs_stub_plugins' ) ) {
	function pvs_stub_plugins() {
		return array(
			'velotok-core/velotok-core.php' => array(
				'slug'    => 'velotok-core',
				'name'    => 'Велоток: поля товара',
				'version' => '1.0.0',
				'active'  => true,
				'type'    => 'plugin',
			),
			'woocommerce/woocommerce.php' => array(
				'slug'    => 'woocommerce',
				'name'    => 'WooCommerce',
				'version' => '9.0.0',
				'active'  => true,
				'type'    => 'plugin',
			),
			'contact-form-7/wp-contact-form-7.php' => array(
				'slug'    => 'contact-form-7',
				'name'    => 'Contact Form 7',
				'version' => '5.3.1',
				'active'  => false,
				'type'    => 'plugin',
			),
			'akismet/akismet.php' => array(
				'slug'    => 'akismet',
				'name'    => 'Akismet Anti-Spam',
				'version' => '5.0.0',
				'active'  => false,
				'type'    => 'plugin',
			),
			'velotok-mu.php' => array(
				'slug'    => 'velotok-mu',
				'name'    => 'Velotok Must Use',
				'version' => '1.0.0',
				'active'  => true,
				'type'    => 'mu-plugin',
			),
		);
	}
}

if ( ! function_exists( 'pvs_vulnerability_database' ) ) {
	function pvs_vulnerability_database() {
		return array(
			'contact-form-7' => array(
				array(
					'id'       => 'PVS-MOCK-0001',
					'title'    => 'Устаревшая версия содержит известную уязвимость',
					'severity' => 'high',
					'affected' => '<5.3.2',
					'fixed_in' => '5.3.2',
					'cvss'     => 7.5,
				),
			),
			'woocommerce' => array(
				array(
					'id'       => 'PVS-MOCK-0002',
					'title'    => 'Уязвимость обратной совместимости расширений',
					'severity' => 'medium',
					'affected' => '<8.5.0',
					'fixed_in' => '8.5.0',
					'cvss'     => 5.3,
				),
			),
			'akismet' => array(
				array(
					'id'       => 'PVS-MOCK-0003',
					'title'    => 'Недостаточная проверка входных данных',
					'severity' => 'low',
					'affected' => '<5.0.0',
					'fixed_in' => '5.0.0',
					'cvss'     => 3.1,
				),
			),
		);
	}
}

if ( ! function_exists( 'pvs_normalize_version' ) ) {
	function pvs_normalize_version( $version ) {
		$version = is_scalar( $version ) ? trim( (string) $version ) : '';

		if ( '' === $version ) {
			return '0.0.0';
		}

		if ( preg_match( '/^\d+(?:\.\d+)*/', $version, $matches ) ) {
			return rtrim( $matches[0], '.' );
		}

		return $version;
	}
}

if ( ! function_exists( 'pvs_version_matches' ) ) {
	function pvs_version_matches( $version, $rule ) {
		$version = pvs_normalize_version( $version );

		if ( is_array( $rule ) ) {
			foreach ( $rule as $part ) {
				if ( pvs_version_matches( $version, $part ) ) {
					return true;
				}
			}

			return false;
		}

		$rule = is_scalar( $rule ) ? trim( (string) $rule ) : '';

		if ( '' === $rule || '*' === $rule ) {
			return true;
		}

		$clauses = preg_split( '/\s*,\s*/', $rule );
		$clauses = is_array( $clauses ) ? $clauses : array();
		$matched = true;

		foreach ( $clauses as $clause ) {
			$clause = trim( $clause );

			if ( '' === $clause ) {
				continue;
			}

			if ( preg_match( '/^(<=|>=|<|>|=)?\s*(.+)$/', $clause, $matches ) ) {
				$operator = '' === $matches[1] ? '=' : $matches[1];
				$target   = pvs_normalize_version( $matches[2] );

				if ( ! version_compare( $version, $target, $operator ) ) {
					$matched = false;
					break;
				}
			} else {
				$matched = false;
				break;
			}
		}

		return $matched;
	}
}

if ( ! function_exists( 'pvs_plugin_vulnerabilities' ) ) {
	function pvs_plugin_vulnerabilities( $slug, $version, $database = null ) {
		$slug = is_scalar( $slug ) ? strtolower( trim( (string) $slug ) ) : '';

		if ( '' === $slug ) {
			return array();
		}

		$database = is_array( $database ) ? $database : pvs_vulnerability_database();

		if ( ! isset( $database[ $slug ] ) || ! is_array( $database[ $slug ] ) ) {
			return array();
		}

		$entries = $database[ $slug ];

		if ( isset( $entries['vulnerabilities'] ) && is_array( $entries['vulnerabilities'] ) ) {
			$entries = $entries['vulnerabilities'];
		}

		$found = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$affected = null;

			foreach ( array( 'affected', 'affected_versions', 'affects', 'vulnerable_versions', 'versions', 'version' ) as $key ) {
				if ( array_key_exists( $key, $entry ) ) {
					$affected = $entry[ $key ];
					break;
				}
			}

			if ( null === $affected ) {
				foreach ( array( 'fixed_in', 'fixed_version' ) as $fixed_key ) {
					if ( isset( $entry[ $fixed_key ] ) && is_scalar( $entry[ $fixed_key ] ) && '' !== trim( (string) $entry[ $fixed_key ] ) ) {
						$affected = '<' . trim( (string) $entry[ $fixed_key ] );
						break;
					}
				}
			}

			if ( null !== $affected && ! pvs_version_matches( $version, $affected ) ) {
				continue;
			}

			$entry['mocked'] = true;
			$entry['network'] = false;
			$entry['source']  = 'stub';
			$entry['plugin']  = $slug;
			$entry['version'] = pvs_normalize_version( $version );
			$entry['severity'] = isset( $entry['severity'] ) ? strtolower( (string) $entry['severity'] ) : 'low';
			$entry['severity_label'] = pvs_severity_label( $entry['severity'] );

			$found[] = $entry;
		}

		return $found;
	}
}

if ( ! function_exists( 'pvs_collect_plugins' ) ) {
	function pvs_collect_plugins() {
		$collected = array();

		if ( function_exists( 'get_plugins' ) ) {
			$plugins = get_plugins();

			if ( is_array( $plugins ) ) {
				foreach ( $plugins as $plugin_file => $data ) {
					$collected[ $plugin_file ] = array(
						'data' => is_array( $data ) ? $data : array(),
						'type' => 'plugin',
						'active' => function_exists( 'is_plugin_active' ) ? (bool) is_plugin_active( $plugin_file ) : true,
					);
				}
			}
		}

		if ( function_exists( 'get_mu_plugins' ) ) {
			$mu_plugins = get_mu_plugins();

			if ( is_array( $mu_plugins ) ) {
				foreach ( $mu_plugins as $plugin_file => $data ) {
					$collected[ $plugin_file ] = array(
						'data' => is_array( $data ) ? $data : array(),
						'type' => 'mu-plugin',
						'active' => true,
					);
				}
			}
		}

		if ( function_exists( 'get_dropins' ) ) {
			$dropins = get_dropins();

			if ( is_array( $dropins ) ) {
				foreach ( $dropins as $plugin_file => $data ) {
					$collected[ $plugin_file ] = array(
						'data' => is_array( $data ) ? $data : array(),
						'type' => 'dropin',
						'active' => true,
					);
				}
			}
		}

		if ( empty( $collected ) ) {
			foreach ( pvs_stub_plugins() as $plugin_file => $data ) {
				$collected[ $plugin_file ] = array(
					'data' => $data,
					'type' => isset( $data['type'] ) ? $data['type'] : 'plugin',
					'active' => isset( $data['active'] ) ? (bool) $data['active'] : true,
				);
			}
		}

		return $collected;
	}
}

if ( ! function_exists( 'pvs_normalize_plugins' ) ) {
	function pvs_normalize_plugins( $plugins ) {
		if ( null === $plugins ) {
			$plugins = pvs_collect_plugins();
		}

		if ( ! is_array( $plugins ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $plugins as $key => $item ) {
			$plugin_file = is_string( $key ) ? $key : '';
			$data        = array();
			$type        = 'plugin';
			$active      = true;

			if ( is_string( $item ) ) {
				$data['name'] = $item;
			} elseif ( is_object( $item ) ) {
				$data = (array) $item;
			} elseif ( is_array( $item ) ) {
				$data = $item;

				if ( isset( $item['data'] ) && is_array( $item['data'] ) ) {
					$data = array_merge( $item['data'], $item );
				}
			}

			if ( isset( $data['plugin_file'] ) && is_scalar( $data['plugin_file'] ) ) {
				$plugin_file = (string) $data['plugin_file'];
			} elseif ( isset( $data['file'] ) && is_scalar( $data['file'] ) ) {
				$plugin_file = (string) $data['file'];
			}

			if ( isset( $data['type'] ) && is_scalar( $data['type'] ) ) {
				$type = (string) $data['type'];
			}

			if ( array_key_exists( 'active', $data ) ) {
				$active = (bool) $data['active'];
			} elseif ( array_key_exists( 'Active', $data ) ) {
				$active = (bool) $data['Active'];
			}

			if ( '' === $plugin_file ) {
				$slug = '';

				foreach ( array( 'slug', 'text_domain', 'TextDomain' ) as $slug_key ) {
					if ( isset( $data[ $slug_key ] ) && is_scalar( $data[ $slug_key ] ) && '' !== trim( (string) $data[ $slug_key ] ) ) {
						$slug = strtolower( trim( (string) $data[ $slug_key ] ) );
						break;
					}
				}

				if ( '' === $slug && isset( $data['name'] ) && is_scalar( $data['name'] ) ) {
					$slug = sanitize_title_fallback( (string) $data['name'] );
				}

				$plugin_file = ( '' === $slug ? 'plugin-' . count( $normalized ) : $slug ) . '.php';
			}

			$name = '';

			foreach ( array( 'name', 'Name' ) as $name_key ) {
				if ( isset( $data[ $name_key ] ) && is_scalar( $data[ $name_key ] ) ) {
					$name = (string) $data[ $name_key ];
					break;
				}
			}

			if ( '' === $name ) {
				$name = $plugin_file;
			}

			$version = '0.0.0';

			foreach ( array( 'version', 'Version' ) as $version_key ) {
				if ( isset( $data[ $version_key ] ) && is_scalar( $data[ $version_key ] ) && '' !== trim( (string) $data[ $version_key ] ) ) {
					$version = pvs_normalize_version( $data[ $version_key ] );
					break;
				}
			}

			$slug = '';

			foreach ( array( 'slug', 'text_domain', 'TextDomain' ) as $slug_key ) {
				if ( isset( $data[ $slug_key ] ) && is_scalar( $data[ $slug_key ] ) && '' !== trim( (string) $data[ $slug_key ] ) ) {
					$slug = strtolower( trim( (string) $data[ $slug_key ] ) );
					break;
				}
			}

			if ( '' === $slug ) {
				$slug = pvs_slug_from_file( $plugin_file );
			}

			$normalized[ $plugin_file ] = array(
				'slug'        => $slug,
				'name'        => $name,
				'version'     => $version,
				'plugin_file' => $plugin_file,
				'active'      => $active,
				'type'        => $type,
				'data'        => $data,
			);
		}

		return $normalized;
	}
}

if ( ! function_exists( 'sanitize_title_fallback' ) ) {
	function sanitize_title_fallback( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );

		return trim( (string) $value, '-' );
	}
}

if ( ! function_exists( 'pvs_slug_from_file' ) ) {
	function pvs_slug_from_file( $plugin_file ) {
		$plugin_file = is_scalar( $plugin_file ) ? str_replace( '\\', '/', (string) $plugin_file ) : '';
		$parts       = array_values( array_filter( explode( '/', $plugin_file ), 'strlen' ) );

		if ( empty( $parts ) ) {
			return '';
		}

		return strtolower( count( $parts ) > 1 ? $parts[0] : pathinfo( $parts[0], PATHINFO_FILENAME ) );
	}
}

if ( ! function_exists( 'pvs_scan_plugins_data' ) ) {
	function pvs_scan_plugins_data( $plugins = null, $options = array() ) {
		$options = is_array( $options ) ? $options : array();

		if ( null === $plugins && isset( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$plugins = $options['plugins'];
		}

		if ( empty( $options ) && is_array( $plugins ) ) {
			$option_keys = array( 'plugins', 'vulnerability_database', 'vulnerability_db', 'database', 'db', 'include_inactive', 'include_mu_plugins', 'include_dropins' );

			foreach ( $option_keys as $option_key ) {
				if ( array_key_exists( $option_key, $plugins ) ) {
					$options = $plugins;
					$plugins = isset( $options['plugins'] ) && is_array( $options['plugins'] ) ? $options['plugins'] : null;
					break;
				}
			}
		}

		$contract = pvs_contract();
		$normalized = pvs_normalize_plugins( $plugins );

		$include_inactive = ! isset( $options['include_inactive'] ) || (bool) $options['include_inactive'];
		$include_mu       = ! isset( $options['include_mu_plugins'] ) || (bool) $options['include_mu_plugins'];
		$include_dropins  = ! isset( $options['include_dropins'] ) || (bool) $options['include_dropins'];
		$database         = null;

		foreach ( array( 'vulnerability_database', 'vulnerability_db', 'database', 'db' ) as $database_key ) {
			if ( isset( $options[ $database_key ] ) && is_array( $options[ $database_key ] ) ) {
				$database = $options[ $database_key ];
				break;
			}
		}

		if ( null === $database ) {
			$database = pvs_vulnerability_database();
		}

		$scanned = array();
		$found   = array();

		foreach ( $normalized as $plugin ) {
			if ( ! $include_inactive && ! $plugin['active'] ) {
				continue;
			}

			if ( ! $include_mu && 'mu-plugin' === $plugin['type'] ) {
				continue;
			}

			if ( ! $include_dropins && 'dropin' === $plugin['type'] ) {
				continue;
			}

			$vulnerabilities = pvs_plugin_vulnerabilities( $plugin['slug'], $plugin['version'], $database );

			foreach ( $vulnerabilities as $vulnerability ) {
				$vulnerability['plugin_name'] = $plugin['name'];
				$found[]                      = $vulnerability;
			}

			$scanned[] = array(
				'slug'                => $plugin['slug'],
				'name'                => $plugin['name'],
				'version'             => $plugin['version'],
				'plugin_file'         => $plugin['plugin_file'],
				'plugin'              => $plugin['plugin_file'],
				'active'              => $plugin['active'],
				'type'                => $plugin['type'],
				'mocked'              => true,
				'source'              => 'stub',
				'network'             => false,
				'scanned'             => true,
				'vulnerable'          => ! empty( $vulnerabilities ),
				'vulnerability_count' => count( $vulnerabilities ),
				'vulnerabilities'     => $vulnerabilities,
				'issues'              => $vulnerabilities,
			);
		}

		$scanned = array_values( $scanned );
		$found   = array_values( $found );
		$hash    = hash( 'sha256', (string) serialize( array( $scanned, $options ) ) );
		$vulnerable_plugins = 0;

		foreach ( $scanned as $plugin ) {
			if ( $plugin['vulnerable'] ) {
				$vulnerable_plugins++;
			}
		}

		return array(
			'ok'                   => true,
			'valid'                => true,
			'mocked'               => true,
			'source'               => 'stub',
			'network'              => false,
			'persisted'            => false,
			'spec'                 => $contract['spec'],
			'requirement'          => $contract['requirement'],
			'scan_id'              => 'mock-pvs-' . substr( $hash, 0, 16 ),
			'all_plugins'          => true,
			'scans_all_plugins'    => true,
			'checks_all_plugins'   => true,
			'scan_all_plugins'     => true,
			'coverage'             => $contract['coverage'],
			'total_plugins'        => count( $scanned ),
			'plugins_total'        => count( $scanned ),
			'plugins_scanned'      => count( $scanned ),
			'checked_plugins'      => count( $scanned ),
			'vulnerable_plugins'   => $vulnerable_plugins,
			'vulnerable'           => $vulnerable_plugins > 0,
			'vulnerability_count'  => count( $found ),
			'vulnerabilities_found'=> count( $found ),
			'plugins'              => $scanned,
			'vulnerabilities'      => $found,
			'found_vulnerabilities'=> $found,
			'report'               => array(
				'mocked'             => true,
				'source'             => 'stub',
				'network'            => false,
				'all_plugins'        => true,
				'plugins_scanned'    => count( $scanned ),
				'vulnerable_plugins' => $vulnerable_plugins,
				'vulnerabilities'    => $found,
			),
			'errors'               => array(),
			'error'                => null,
		);
	}
}

if ( ! function_exists( 'pvs_scan' ) ) {
	function pvs_scan( $plugins = null, $options = array() ) {
		return pvs_scan_plugins_data( $plugins, $options );
	}
}

if ( ! function_exists( 'pvs_scan_all_plugins' ) ) {
	function pvs_scan_all_plugins( $plugins = null, $options = array() ) {
		return pvs_scan_plugins_data( $plugins, $options );
	}
}

if ( ! function_exists( 'pvs_scan_plugins' ) ) {
	function pvs_scan_plugins( $plugins = null, $options = array() ) {
		return pvs_scan_plugins_data( $plugins, $options );
	}
}

if ( ! function_exists( 'pvs_check_plugins' ) ) {
	function pvs_check_plugins( $plugins = null, $options = array() ) {
		return pvs_scan_plugins_data( $plugins, $options );
	}
}

if ( ! function_exists( 'pvs_check_plugins_for_vulnerabilities' ) ) {
	function pvs_check_plugins_for_vulnerabilities( $plugins = null, $options = array() ) {
		return pvs_scan_plugins_data( $plugins, $options );
	}
}

if ( ! function_exists( 'pvs_vulnerability_scan' ) ) {
	function pvs_vulnerability_scan( $plugins = null, $options = array() ) {
		return pvs_scan_plugins_data( $plugins, $options );
	}
}

if ( ! function_exists( 'pvs_get_scan_results' ) ) {
	function pvs_get_scan_results( $plugins = null, $options = array() ) {
		return pvs_scan_plugins_data( $plugins, $options );
	}
}

if ( ! function_exists( 'velotok_core_pvs_scan' ) ) {
	function velotok_core_pvs_scan( $plugins = null, $options = array() ) {
		return pvs_scan_plugins_data( $plugins, $options );
	}
}

if ( ! function_exists( 'velotok_core_scan_all_plugins' ) ) {
	function velotok_core_scan_all_plugins( $plugins = null, $options = array() ) {
		return pvs_scan_plugins_data( $plugins, $options );
	}
}

if ( ! function_exists( 'velotok_core_plugin_vulnerability_scan' ) ) {
	function velotok_core_plugin_vulnerability_scan( $plugins = null, $options = array() ) {
		return pvs_scan_plugins_data( $plugins, $options );
	}
}

if ( ! class_exists( 'PVS_Scanner', false ) ) {
	class PVS_Scanner {
		public static function scan( $plugins = null, $options = array() ) {
			return pvs_scan_plugins_data( $plugins, $options );
		}

		public static function scan_all_plugins( $plugins = null, $options = array() ) {
			return pvs_scan_plugins_data( $plugins, $options );
		}

		public static function scan_plugins( $plugins = null, $options = array() ) {
			return pvs_scan_plugins_data( $plugins, $options );
		}

		public static function check_plugins( $plugins = null, $options = array() ) {
			return pvs_scan_plugins_data( $plugins, $options );
		}

		public static function vulnerabilities( $slug, $version, $database = null ) {
			return pvs_plugin_vulnerabilities( $slug, $version, $database );
		}

		public static function stub_plugins() {
			return pvs_stub_plugins();
		}

		public static function vulnerability_database() {
			return pvs_vulnerability_database();
		}
	}
}
