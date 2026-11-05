<?php

if ( ! function_exists( 'pvs_contract' ) ) {
	$pvs_contract_file = __DIR__ . '/class-pvs-contract.php';

	if ( is_readable( $pvs_contract_file ) ) {
		require_once $pvs_contract_file;
	}
}

if ( ! function_exists( 'pvs_scan_all_plugins' ) ) {
	$pvs_scanner_file = __DIR__ . '/class-pvs-scanner.php';

	if ( is_readable( $pvs_scanner_file ) ) {
		require_once $pvs_scanner_file;
	}
}

if ( ! function_exists( 'pvs_admin_state' ) ) {
	function &pvs_admin_state() {
		static $state = array( 'result' => null );

		return $state;
	}
}

if ( ! function_exists( 'pvs_admin_get_last_scan' ) ) {
	function pvs_admin_get_last_scan() {
		if ( function_exists( 'get_transient' ) ) {
			$result = get_transient( 'pvs_last_scan' );

			if ( is_array( $result ) ) {
				return $result;
			}
		}

		$state = &pvs_admin_state();

		return is_array( $state['result'] ) ? $state['result'] : null;
	}
}

if ( ! function_exists( 'pvs_admin_store_scan' ) ) {
	function pvs_admin_store_scan( $result ) {
		$state          = &pvs_admin_state();
		$state['result'] = is_array( $result ) ? $result : null;

		if ( is_array( $result ) && function_exists( 'set_transient' ) ) {
			$ttl = function_exists( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600;
			set_transient( 'pvs_last_scan', $result, $ttl );
		}

		return $result;
	}
}

if ( ! function_exists( 'pvs_admin_run_scan' ) ) {
	function pvs_admin_run_scan( $plugins = null, $options = array() ) {
		$result = pvs_scan_all_plugins( $plugins, $options );

		return pvs_admin_store_scan( $result );
	}
}

if ( ! function_exists( 'pvs_admin_request_options' ) ) {
	function pvs_admin_request_options( $source = null ) {
		$source  = is_array( $source ) ? $source : $_POST;
		$options = array();

		foreach ( array( 'provider', 'provider_id' ) as $provider_key ) {
			if ( isset( $source[ $provider_key ] ) && is_scalar( $source[ $provider_key ] ) ) {
				$provider = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $source[ $provider_key ] ) : strtolower( trim( (string) $source[ $provider_key ] ) );

				if ( '' !== $provider ) {
					$options['provider'] = $provider;
				}

				break;
			}
		}

		if ( isset( $source['manual_findings'] ) ) {
			$manual = $source['manual_findings'];

			if ( is_string( $manual ) ) {
				$decoded = json_decode( $manual, true );

				if ( is_array( $decoded ) ) {
					$manual = $decoded;
				}
			}

			if ( is_array( $manual ) ) {
				$options['manual_findings'] = $manual;
			}
		}

		return $options;
	}
}

if ( ! function_exists( 'pvs_admin_styles' ) ) {
	function pvs_admin_styles() {
		$levels = function_exists( 'pvs_severity_levels' ) ? pvs_severity_levels() : array();
		$css    = '.pvs-report{margin-top:16px}.pvs-report table{margin-top:12px}.pvs-risk{display:inline-block;padding:2px 8px;border-radius:999px;font-weight:600;border:1px solid transparent}.pvs-plugin-vulnerable{background:#fff7ed}.pvs-risk-highlight{box-shadow:inset 4px 0 0 #dc2626}.pvs-plugin-safe{background:#f0fdf4}.pvs-severity-critical,.pvs-severity-high,.pvs-severity-medium,.pvs-severity-low{font-weight:600}';

		foreach ( $levels as $severity => $level ) {
			if ( ! is_string( $severity ) || '' === $severity || ! is_array( $level ) ) {
				continue;
			}

			$selector = '.pvs-risk-' . $severity;
			$color    = isset( $level['color'] ) ? $level['color'] : 'inherit';
			$bg       = isset( $level['background'] ) ? $level['background'] : 'transparent';
			$border   = isset( $level['border'] ) ? $level['border'] : 'transparent';

			$css .= $selector . '{color:' . $color . ';background:' . $bg . ';border-color:' . $border . '}';
			$css .= '.pvs-severity-' . $severity . '{border-left:4px solid ' . $border . '}';
		}

		return $css;
	}
}

if ( ! function_exists( 'pvs_admin_render_styles' ) ) {
	function pvs_admin_render_styles() {
		echo '<style id="pvs-risk-styles">' . pvs_admin_styles() . '</style>';
	}
}

if ( ! function_exists( 'pvs_admin_render_report' ) ) {
	function pvs_admin_render_report( $result ) {
		if ( ! is_array( $result ) ) {
			return '';
		}

		$escape = function ( $value ) {
			return function_exists( 'esc_html' ) ? esc_html( $value ) : htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
		};

		$provider = isset( $result['provider'] ) && is_scalar( $result['provider'] ) ? (string) $result['provider'] : 'wpscan';

		$html  = '<div class="pvs-report" data-mocked="true" data-network="false" data-source="stub" data-provider="' . $escape( $provider ) . '" data-all-plugins="' . ( ! empty( $result['all_plugins'] ) ? 'true' : 'false' ) . '" data-scan-id="' . $escape( isset( $result['scan_id'] ) ? $result['scan_id'] : '' ) . '">';
		$html .= '<p class="pvs-provider">' . $escape( sprintf( 'Источник проверки: %s.', isset( $result['provider_label'] ) ? $result['provider_label'] : $provider ) ) . '</p>';
		$html .= '<p>' . $escape( sprintf( 'Проверено плагинов: %d. Найдено уязвимостей: %d.', isset( $result['plugins_scanned'] ) ? $result['plugins_scanned'] : 0, isset( $result['vulnerability_count'] ) ? $result['vulnerability_count'] : 0 ) ) . '</p>';

		if ( ! empty( $result['severity_counts'] ) && is_array( $result['severity_counts'] ) ) {
			$html .= '<p class="pvs-risk-summary">';

			foreach ( $result['severity_counts'] as $severity => $count ) {
				$severity = is_scalar( $severity ) ? strtolower( trim( (string) $severity ) ) : '';
				$label    = function_exists( 'pvs_severity_label' ) ? pvs_severity_label( $severity ) : $severity;
				$html    .= '<span class="pvs-risk pvs-risk-' . $escape( $severity ) . '" data-severity="' . $escape( $severity ) . '">' . $escape( $label . ': ' . (int) $count ) . '</span> ';
			}

			$html .= '</p>';
		}

		if ( ! empty( $result['plugins'] ) && is_array( $result['plugins'] ) ) {
			$html .= '<table class="widefat"><thead><tr><th>' . $escape( 'Плагин' ) . '</th><th>' . $escape( 'Версия' ) . '</th><th>' . $escape( 'Статус' ) . '</th><th>' . $escape( 'Уязвимости' ) . '</th></tr></thead><tbody>';

			foreach ( $result['plugins'] as $plugin ) {
				if ( ! is_array( $plugin ) ) {
					continue;
				}

				$risk_level = isset( $plugin['risk_level'] ) && is_scalar( $plugin['risk_level'] ) ? (string) $plugin['risk_level'] : '';
				$row_class  = ! empty( $plugin['vulnerable'] ) ? 'pvs-plugin-vulnerable pvs-risk-highlight' : 'pvs-plugin-safe';

				if ( '' !== $risk_level && 'none' !== $risk_level ) {
					$row_class .= ' pvs-severity-' . $risk_level;
				}

				$highlight = isset( $plugin['highlight'] ) && is_scalar( $plugin['highlight'] ) ? (string) $plugin['highlight'] : '';
				$html     .= '<tr class="' . $escape( $row_class ) . '" data-risk="' . ( ! empty( $plugin['vulnerable'] ) ? 'true' : 'false' ) . '" data-severity="' . $escape( $risk_level ) . '" data-highlight="' . $escape( $highlight ) . '">';
				$html     .= '<td>' . $escape( isset( $plugin['name'] ) ? $plugin['name'] : '' ) . '</td>';
				$html     .= '<td>' . $escape( isset( $plugin['version'] ) ? $plugin['version'] : '' ) . '</td>';
				$html     .= '<td>' . $escape( ! empty( $plugin['active'] ) ? 'Активен' : 'Неактивен' ) . '</td>';
				$html     .= '<td>' . $escape( isset( $plugin['vulnerability_count'] ) ? (string) $plugin['vulnerability_count'] : '0' ) . '</td>';
				$html     .= '</tr>';
			}

			$html .= '</tbody></table>';
		}

		if ( ! empty( $result['vulnerabilities'] ) && is_array( $result['vulnerabilities'] ) ) {
			$html .= '<ul class="pvs-vulnerabilities">';

			foreach ( $result['vulnerabilities'] as $vulnerability ) {
				if ( ! is_array( $vulnerability ) ) {
					continue;
				}

				$severity = isset( $vulnerability['severity'] ) && is_scalar( $vulnerability['severity'] ) ? strtolower( trim( (string) $vulnerability['severity'] ) ) : 'low';
				$label    = isset( $vulnerability['severity_label'] ) ? $vulnerability['severity_label'] : ( function_exists( 'pvs_severity_label' ) ? pvs_severity_label( $severity ) : $severity );

				$highlight = isset( $vulnerability['highlight'] ) && is_scalar( $vulnerability['highlight'] ) ? (string) $vulnerability['highlight'] : 'pvs-risk-' . $severity;
				$html     .= '<li class="pvs-vulnerability pvs-severity-' . $escape( $severity ) . '" data-severity="' . $escape( $severity ) . '" data-risk="' . $escape( $severity ) . '" data-highlight="' . $escape( $highlight ) . '">';
				$html .= '<span class="pvs-vulnerability-plugin">' . $escape( isset( $vulnerability['plugin'] ) ? $vulnerability['plugin'] : '' ) . '</span>';
				$html .= ': ' . $escape( isset( $vulnerability['title'] ) ? $vulnerability['title'] : '' );
				$html .= ' <span class="pvs-risk pvs-risk-' . $escape( $severity ) . '">(' . $escape( $label ) . ')</span>';

				if ( ! empty( $vulnerability['fixed_in'] ) && is_scalar( $vulnerability['fixed_in'] ) ) {
					$html .= ' — ' . $escape( sprintf( 'исправлено в %s', (string) $vulnerability['fixed_in'] ) );
				}

				$html .= '</li>';
			}

			$html .= '</ul>';
		}

		if ( ! empty( $result['errors'] ) && is_array( $result['errors'] ) ) {
			$html .= '<ul class="pvs-errors">';

			foreach ( $result['errors'] as $error ) {
				if ( is_array( $error ) ) {
					$message = isset( $error['message'] ) ? $error['message'] : ( isset( $error['code'] ) ? $error['code'] : '' );
				} else {
					$message = $error;
				}

				$html .= '<li class="pvs-error">' . $escape( $message ) . '</li>';
			}

			$html .= '</ul>';
		}

		$html .= '</div>';

		return $html;
	}
}

if ( ! class_exists( 'PVS_Admin', false ) ) {
	class PVS_Admin {
		public static function init() {
			if ( function_exists( 'add_action' ) ) {
				add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
				add_action( 'admin_head', array( __CLASS__, 'render_styles' ) );
				add_action( 'admin_post_pvs_scan', array( __CLASS__, 'handle_scan' ) );
				add_action( 'wp_ajax_pvs_scan', array( __CLASS__, 'handle_ajax_scan' ) );
			}
		}

		public static function styles() {
			return pvs_admin_styles();
		}

		public static function render_styles() {
			pvs_admin_render_styles();
		}

		public static function register_menu() {
			if ( ! function_exists( 'add_management_page' ) ) {
				return;
			}

			add_management_page(
				'Проверка плагинов на уязвимости',
				'Проверка плагинов',
				'manage_options',
				'pvs',
				array( __CLASS__, 'render_page' )
			);
		}

		public static function user_can_scan() {
			if ( ! function_exists( 'current_user_can' ) ) {
				return true;
			}

			return current_user_can( 'manage_options' );
		}

		public static function nonce_field() {
			if ( function_exists( 'wp_nonce_field' ) ) {
				wp_nonce_field( 'pvs_scan', 'pvs_nonce' );
			}
		}

		public static function verify_nonce() {
			if ( function_exists( 'check_admin_referer' ) ) {
				check_admin_referer( 'pvs_scan', 'pvs_nonce' );

				return true;
			}

			return true;
		}

		public static function render_page() {
			if ( ! self::user_can_scan() ) {
				return;
			}

			$result = pvs_admin_get_last_scan();

			if ( null === $result ) {
				$result = pvs_admin_run_scan();
			}

			$escape = function ( $value ) {
				return function_exists( 'esc_html' ) ? esc_html( $value ) : htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
			};

			$providers = function_exists( 'pvs_providers' ) ? pvs_providers() : array();
			$selected  = isset( $result['provider'] ) && is_scalar( $result['provider'] ) ? (string) $result['provider'] : 'wpscan';

			echo '<div class="wrap">';
			echo '<h1>' . $escape( 'Проверка всех плагинов на уязвимости' ) . '</h1>';
			echo '<form method="post" action="' . $escape( function_exists( 'admin_url' ) ? admin_url( 'admin-post.php' ) : '' ) . '">';
			echo '<input type="hidden" name="action" value="pvs_scan" />';
			self::nonce_field();

			if ( ! empty( $providers ) && is_array( $providers ) ) {
				echo '<p><label for="pvs-provider">' . $escape( 'Источник проверки' ) . '</label> ';
				echo '<select id="pvs-provider" name="provider">';

				foreach ( $providers as $provider_id => $provider ) {
					if ( ! is_array( $provider ) ) {
						continue;
					}

					$label = isset( $provider['label'] ) ? $provider['label'] : $provider_id;

					echo '<option value="' . $escape( $provider_id ) . '"' . ( $provider_id === $selected ? ' selected="selected"' : '' ) . '>' . $escape( $label ) . '</option>';
				}

				echo '</select></p>';
			}

			echo '<p><label for="pvs-manual-findings">' . $escape( 'Находки ручного аудита (JSON)' ) . '</label><br />';
			echo '<textarea id="pvs-manual-findings" name="manual_findings" rows="4" cols="80" placeholder=\'[{"plugin":"slug","title":"...","severity":"high"}]\'></textarea></p>';
			echo '<button type="submit" class="button button-primary">' . $escape( 'Проверить все плагины' ) . '</button>';
			echo '</form>';
			echo pvs_admin_render_report( $result );
			echo '</div>';
		}

		public static function handle_scan() {
			if ( ! self::user_can_scan() ) {
				if ( function_exists( 'wp_die' ) ) {
					wp_die( 'Недостаточно прав для проверки плагинов.', 'PVS', array( 'response' => 403 ) );
				}

				return;
			}

			self::verify_nonce();
			pvs_admin_run_scan( null, pvs_admin_request_options( $_POST ) );

			if ( function_exists( 'wp_safe_redirect' ) && function_exists( 'admin_url' ) ) {
				wp_safe_redirect( admin_url( 'tools.php?page=pvs' ) );
				exit;
			}
		}

		public static function handle_ajax_scan() {
			if ( ! self::user_can_scan() ) {
				if ( function_exists( 'wp_send_json_error' ) ) {
					wp_send_json_error( array( 'message' => 'Недостаточно прав для проверки плагинов.' ), 403 );
				}

				return;
			}

			if ( function_exists( 'check_ajax_referer' ) ) {
				check_ajax_referer( 'pvs_scan', 'pvs_nonce' );
			}

			$result = pvs_admin_run_scan( null, pvs_admin_request_options( $_REQUEST ) );

			if ( function_exists( 'wp_send_json_success' ) ) {
				wp_send_json_success( $result );
			}

			return $result;
		}

		public static function scan() {
			return pvs_admin_run_scan();
		}

		public static function last_scan() {
			return pvs_admin_get_last_scan();
		}
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', array( 'PVS_Admin', 'init' ) );
}
