<?php

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

if ( ! function_exists( 'pvs_admin_render_report' ) ) {
	function pvs_admin_render_report( $result ) {
		if ( ! is_array( $result ) ) {
			return '';
		}

		$escape = function ( $value ) {
			return function_exists( 'esc_html' ) ? esc_html( $value ) : htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
		};

		$html = '<div class="pvs-report" data-mocked="true">';
		$html .= '<p>' . $escape( sprintf( 'Проверено плагинов: %d. Найдено уязвимостей: %d.', isset( $result['plugins_scanned'] ) ? $result['plugins_scanned'] : 0, isset( $result['vulnerability_count'] ) ? $result['vulnerability_count'] : 0 ) ) . '</p>';

		if ( ! empty( $result['plugins'] ) && is_array( $result['plugins'] ) ) {
			$html .= '<table class="widefat"><thead><tr><th>' . $escape( 'Плагин' ) . '</th><th>' . $escape( 'Версия' ) . '</th><th>' . $escape( 'Статус' ) . '</th><th>' . $escape( 'Уязвимости' ) . '</th></tr></thead><tbody>';

			foreach ( $result['plugins'] as $plugin ) {
				$html .= '<tr>';
				$html .= '<td>' . $escape( isset( $plugin['name'] ) ? $plugin['name'] : '' ) . '</td>';
				$html .= '<td>' . $escape( isset( $plugin['version'] ) ? $plugin['version'] : '' ) . '</td>';
				$html .= '<td>' . $escape( ! empty( $plugin['active'] ) ? 'Активен' : 'Неактивен' ) . '</td>';
				$html .= '<td>' . $escape( isset( $plugin['vulnerability_count'] ) ? (string) $plugin['vulnerability_count'] : '0' ) . '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table>';
		}

		if ( ! empty( $result['vulnerabilities'] ) && is_array( $result['vulnerabilities'] ) ) {
			$html .= '<ul class="pvs-vulnerabilities">';

			foreach ( $result['vulnerabilities'] as $vulnerability ) {
				$html .= '<li>';
				$html .= $escape( isset( $vulnerability['plugin'] ) ? $vulnerability['plugin'] : '' );
				$html .= ': ' . $escape( isset( $vulnerability['title'] ) ? $vulnerability['title'] : '' );
				$html .= ' (' . $escape( isset( $vulnerability['severity_label'] ) ? $vulnerability['severity_label'] : '' ) . ')';
				$html .= '</li>';
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
				add_action( 'admin_post_pvs_scan', array( __CLASS__, 'handle_scan' ) );
				add_action( 'wp_ajax_pvs_scan', array( __CLASS__, 'handle_ajax_scan' ) );
			}
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

			echo '<div class="wrap">';
			echo '<h1>' . $escape( 'Проверка всех плагинов на уязвимости' ) . '</h1>';
			echo '<form method="post" action="' . $escape( function_exists( 'admin_url' ) ? admin_url( 'admin-post.php' ) : '' ) . '">';
			echo '<input type="hidden" name="action" value="pvs_scan" />';
			self::nonce_field();
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
			pvs_admin_run_scan();

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

			$result = pvs_admin_run_scan();

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
