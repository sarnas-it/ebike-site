<?php
/**
 * Idempotent store provisioning for the local «Велоток» shop.
 *
 * Task EBIKE-23: a fresh WordPress install had no payment gateways and no
 * shipping zones, so checkout answered «No payment method provided». This
 * module declares the shop configuration (shipping zone, delivery methods,
 * payment gateways and the Russian language pack) and applies it through the
 * WooCommerce/WordPress APIs without duplicating existing records.
 *
 * Spec: checkout (requirement TESTEB-23, «checkout создаёт заказ»).
 *
 * @package Velotok_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'velotok_core_provisioning_env' ) ) {
	/**
	 * Read an environment value with a fallback.
	 *
	 * @param string $name    Variable name.
	 * @param string $default Default value.
	 * @return string
	 */
	function velotok_core_provisioning_env( $name, $default = '' ) {
		$value = getenv( $name );

		return ( false === $value || '' === $value ) ? $default : (string) $value;
	}
}

if ( ! function_exists( 'velotok_core_provisioning_option' ) ) {
	/**
	 * Read a WordPress option when WordPress is available.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function velotok_core_provisioning_option( $name, $default = false ) {
		return function_exists( 'get_option' ) ? get_option( $name, $default ) : $default;
	}
}

if ( ! function_exists( 'velotok_core_provisioning_update_option' ) ) {
	/**
	 * Write a WordPress option when WordPress is available.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	function velotok_core_provisioning_update_option( $name, $value ) {
		return function_exists( 'update_option' ) ? (bool) update_option( $name, $value ) : false;
	}
}

if ( ! function_exists( 'velotok_core_provisioning_normalize_locations' ) ) {
	/**
	 * Normalise zone locations coming from WooCommerce objects or arrays.
	 *
	 * @param array $locations Raw locations.
	 * @return array<int,array<string,string>>
	 */
	function velotok_core_provisioning_normalize_locations( $locations ) {
		$normalized = array();

		foreach ( (array) $locations as $location ) {
			$type = '';
			$code = '';

			if ( is_array( $location ) ) {
				$type = isset( $location['type'] ) ? (string) $location['type'] : '';
				$code = isset( $location['code'] ) ? (string) $location['code'] : '';
			} elseif ( is_object( $location ) ) {
				$type = isset( $location->type ) ? (string) $location->type : '';
				$code = isset( $location->code ) ? (string) $location->code : '';
			}

			if ( '' !== $type && '' !== $code ) {
				$normalized[] = array(
					'type' => $type,
					'code' => $code,
				);
			}
		}

		return $normalized;
	}
}

if ( ! function_exists( 'velotok_core_provisioning_checkout_shortcode' ) ) {
	/**
	 * Shortcode of the classic WooCommerce checkout used by the theme.
	 *
	 * The block checkout renders with JavaScript and ignores the theme template,
	 * so provisioning keeps the checkout page on the classic shortcode.
	 *
	 * @return string
	 */
	function velotok_core_provisioning_checkout_shortcode() {
		return '[woocommerce_checkout]';
	}
}

if ( ! function_exists( 'velotok_core_provisioning_checkout_has_block' ) ) {
	/**
	 * Whether content contains the WooCommerce checkout block.
	 *
	 * @param mixed $content Post content.
	 * @return bool
	 */
	function velotok_core_provisioning_checkout_has_block( $content ) {
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return false;
		}

		if ( false !== strpos( $content, 'wp:woocommerce/checkout' ) ) {
			return true;
		}

		if ( false !== strpos( $content, 'woocommerce/checkout' ) ) {
			return true;
		}

		return function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $content );
	}
}

if ( ! function_exists( 'velotok_core_provisioning_checkout_replace_blocks' ) ) {
	/**
	 * Replace the checkout block with the classic shortcode.
	 *
	 * @param mixed $content Post content.
	 * @return mixed
	 */
	function velotok_core_provisioning_checkout_replace_blocks( $content ) {
		if ( ! velotok_core_provisioning_checkout_has_block( $content ) ) {
			return $content;
		}

		$shortcode = velotok_core_provisioning_checkout_shortcode();

		$replaced = preg_replace(
			'/<!--\s*wp:woocommerce\/checkout(?:\s+\{[^}]*\})?\s*\/-->/',
			$shortcode,
			(string) $content
		);

		if ( is_string( $replaced ) ) {
			$content = $replaced;
		}

		$replaced = preg_replace(
			'/<!--\s*wp:woocommerce\/checkout(?:\s+\{[^}]*\})?\s*-->.*?<!--\s*\/wp:woocommerce\/checkout\s*-->/s',
			$shortcode,
			(string) $content
		);

		if ( is_string( $replaced ) ) {
			$content = $replaced;
		}

		if ( velotok_core_provisioning_checkout_has_block( $content ) ) {
			return $shortcode;
		}

		return $content;
	}
}

if ( ! function_exists( 'velotok_core_provisioning_apply_checkout_layout' ) ) {
	/**
	 * Keep the checkout page on the classic shortcode.
	 *
	 * Existing block checkout pages or empty checkout pages are migrated
	 * idempotently. Pages with custom content are left untouched.
	 *
	 * @param array $result Result accumulator (by reference).
	 * @return void
	 */
	function velotok_core_provisioning_apply_checkout_layout( &$result ) {
		$result['checkout_layout'] = array(
			'mode'      => 'classic',
			'shortcode' => velotok_core_provisioning_checkout_shortcode(),
			'template'  => 'woocommerce/checkout/form-checkout.php',
			'page_id'   => 0,
			'applied'   => false,
			'skipped'   => true,
		);

		if ( ! function_exists( 'get_option' ) || ! function_exists( 'get_post' ) || ! function_exists( 'wp_update_post' ) ) {
			$result['warnings'][] = 'WordPress post API is not available; the checkout page was not switched to the classic shortcode.';
			return;
		}

		$page_id = (int) get_option( 'woocommerce_checkout_page_id', 0 );
		$result['checkout_layout']['page_id'] = $page_id;

		if ( $page_id <= 0 ) {
			return;
		}

		$page = get_post( $page_id );

		if ( ! is_object( $page ) || empty( $page->ID ) ) {
			$result['warnings'][] = 'The WooCommerce checkout page could not be loaded; the classic shortcode was not applied.';
			return;
		}

		$content  = isset( $page->post_content ) ? (string) $page->post_content : '';
		$is_empty = '' === trim( $content );
		$is_block = velotok_core_provisioning_checkout_has_block( $content );

		$result['checkout_layout']['skipped'] = false;

		if ( ! $is_empty && ! $is_block ) {
			return;
		}

		$new_content = $is_empty
			? velotok_core_provisioning_checkout_shortcode()
			: velotok_core_provisioning_checkout_replace_blocks( $content );

		if ( $new_content === $content ) {
			return;
		}

		$updated = wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => $new_content,
			),
			true
		);

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $updated ) ) {
			$result['warnings'][] = 'The WooCommerce checkout page could not be updated to the classic shortcode.';
			return;
		}

		$result['checkout_layout']['applied'] = ! empty( $updated );
	}
}

if ( ! function_exists( 'velotok_core_provisioning_contract' ) ) {
	/**
	 * Declare the store configuration that provisioning must guarantee.
	 *
	 * The same data drives both the CLI provisioning command and the contract
	 * test, so the store cannot drift away from the checked configuration.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_core_provisioning_contract() {
		static $contract = null;

		if ( null !== $contract ) {
			return $contract;
		}

		$shop_id  = trim( (string) velotok_core_provisioning_option( 'velotok_yookassa_shop_id', velotok_core_provisioning_env( 'VELOTOK_YOOKASSA_SHOP_ID' ) ) );
		$secret   = trim( (string) velotok_core_provisioning_option( 'velotok_yookassa_secret_key', velotok_core_provisioning_env( 'VELOTOK_YOOKASSA_SECRET_KEY' ) ) );
		$yoo_on   = ( '' !== $shop_id && '' !== $secret ) ? 'yes' : 'no';

		$zone = array(
			'name'      => 'Россия',
			'locations' => array(
				array(
					'code' => 'RU',
					'type' => 'country',
				),
			),
			'methods'   => array(
				'cdek'         => array(
					'title'        => 'СДЭК',
					'base'         => '500',
					'per_kg'       => '100',
					'free_from'    => '5000',
					'eta_min_days' => '1',
					'eta_max_days' => '3',
					'test_mode'    => 'no',
				),
				'russian_post' => array(
					'title'        => 'Почта РФ',
					'base'         => '350',
					'per_kg'       => '80',
					'free_from'    => '7000',
					'eta_min_days' => '3',
					'eta_max_days' => '7',
					'test_mode'    => 'no',
				),
				'local_pickup' => array(
					'title' => 'Самовывоз из шоурума',
					'cost'  => '0',
				),
			),
		);

		$gateways = array(
			'cod'             => array(
				'option'        => 'woocommerce_cod_settings',
				'force_enabled' => true,
				'settings'      => array(
					'enabled'      => 'yes',
					'title'        => 'Наличными или картой при получении',
					'description'  => 'Оплата наличными или картой при получении заказа.',
					'instructions' => 'Менеджер свяжется для подтверждения заказа и доставки.',
				),
			),
			'velotok_yookassa' => array(
				'option'        => 'woocommerce_velotok_yookassa_settings',
				'force_enabled' => false,
				'settings'      => array(
					'enabled'     => $yoo_on,
					'title'       => 'ЮKassa',
					'description' => 'Оплата через ЮKassa. Включается после настройки реквизитов магазина.',
				),
			),
		);

		$locale = array(
			'language' => 'ru_RU',
			'core'     => array( 'ru_RU' ),
			'plugins'  => array( 'woocommerce' => 'ru_RU' ),
			'commands' => array(
				'language core install ru_RU --activate',
				'site switch-language ru_RU',
				'language plugin install woocommerce ru_RU',
			),
		);

		$contract = array(
			'task'                 => 'EBIKE-23',
			'requirement'          => 'EBIKE-23',
			'spec'                 => 'checkout',
			'checkout_requirement' => 'TESTEB-23',
			'source'               => 'woocommerce_store_provisioning',
			'idempotent'           => true,
			'zone'                 => $zone,
			'shipping_methods'     => $zone['methods'],
			'payment_gateways'     => $gateways,
			'gateways'             => $gateways,
			'locale'               => $locale,
			'language_pack'        => $locale,
			'checkout'             => array(
				'requirement'               => 'TESTEB-23',
				'creates_order'             => true,
				'requires_payment_gateway'  => true,
				'requires_shipping_method'  => true,
				'layout'                    => 'classic',
				'shortcode'                 => velotok_core_provisioning_checkout_shortcode(),
				'template'                  => 'woocommerce/checkout/form-checkout.php',
			),
		);

		return $contract;
	}
}

if ( ! function_exists( 'velotok_core_provisioning_plan' ) ) {
	/**
	 * Alias for the provisioning contract.
	 *
	 * @return array<string,mixed>
	 */
	function velotok_core_provisioning_plan() {
		return velotok_core_provisioning_contract();
	}
}

if ( ! function_exists( 'velotok_core_provisioning_apply_zone' ) ) {
	/**
	 * Create or update the configured WooCommerce shipping zone.
	 *
	 * @param array $zone_config Zone declaration.
	 * @param array $result      Result accumulator (by reference).
	 * @return void
	 */
	function velotok_core_provisioning_apply_zone( $zone_config, &$result ) {
		if ( ! class_exists( 'WC_Shipping_Zone' ) || ! class_exists( 'WC_Shipping_Zones' ) ) {
			$result['warnings'][] = 'WooCommerce shipping zones API is not available; delivery methods were not created.';
			return;
		}

		$zone_name = isset( $zone_config['name'] ) ? (string) $zone_config['name'] : 'Россия';
		$zone_id   = 0;

		foreach ( (array) WC_Shipping_Zones::get_zones() as $zone_data ) {
			$name = '';

			if ( is_array( $zone_data ) ) {
				$name    = isset( $zone_data['zone_name'] ) ? (string) $zone_data['zone_name'] : '';
				$zone_id = isset( $zone_data['zone_id'] ) ? (int) $zone_data['zone_id'] : ( isset( $zone_data['id'] ) ? (int) $zone_data['id'] : 0 );
			} elseif ( is_object( $zone_data ) && method_exists( $zone_data, 'get_zone_name' ) ) {
				$name    = (string) $zone_data->get_zone_name();
				$zone_id = method_exists( $zone_data, 'get_id' ) ? (int) $zone_data->get_id() : 0;
			}

			if ( $zone_name === $name && $zone_id > 0 ) {
				break;
			}

			$zone_id = 0;
		}

		$created = false;

		if ( 0 === $zone_id ) {
			$zone    = new WC_Shipping_Zone();
			$created = true;
			$zone->set_zone_name( $zone_name );
		} else {
			$zone = new WC_Shipping_Zone( $zone_id );
		}

		$locations = $created || ! method_exists( $zone, 'get_zone_locations' )
			? array()
			: velotok_core_provisioning_normalize_locations( $zone->get_zone_locations() );
		$has_russia = false;

		foreach ( $locations as $location ) {
			if ( 'country' === $location['type'] && 'RU' === strtoupper( $location['code'] ) ) {
				$has_russia = true;
				break;
			}
		}

		if ( ! $has_russia ) {
			$locations[] = array(
				'code' => 'RU',
				'type' => 'country',
			);
		}

		if ( method_exists( $zone, 'set_locations' ) ) {
			$zone->set_locations( $locations );
		}

		if ( $created || ! $has_russia ) {
			$zone->save();
		}

		if ( $created ) {
			$zone_id = method_exists( $zone, 'get_id' ) ? (int) $zone->get_id() : 0;
		}

		$result['zone']['id']      = $zone_id;
		$result['zone']['name']    = $zone_name;
		$result['zone']['created'] = $created;

		$existing = array();

		if ( method_exists( $zone, 'get_shipping_methods' ) ) {
			foreach ( (array) $zone->get_shipping_methods( false ) as $method ) {
				$method_id = '';

				if ( is_object( $method ) && isset( $method->id ) ) {
					$method_id = (string) $method->id;
				} elseif ( is_array( $method ) && isset( $method['id'] ) ) {
					$method_id = (string) $method['id'];
				}

				if ( '' === $method_id ) {
					continue;
				}

				$instance = 0;

				if ( is_object( $method ) && isset( $method->instance_id ) ) {
					$instance = (int) $method->instance_id;
				} elseif ( is_array( $method ) && isset( $method['instance_id'] ) ) {
					$instance = (int) $method['instance_id'];
				}

				$existing[ $method_id ] = $instance;
			}
		}

		$methods = isset( $zone_config['methods'] ) && is_array( $zone_config['methods'] ) ? $zone_config['methods'] : array();

		foreach ( $methods as $method_id => $settings ) {
			if ( isset( $existing[ $method_id ] ) && $existing[ $method_id ] > 0 ) {
				$instance_id = (int) $existing[ $method_id ];
			} else {
				$instance_id = (int) $zone->add_shipping_method( $method_id );
			}

			if ( ! $instance_id ) {
				$result['warnings'][] = sprintf( 'Не удалось добавить способ доставки «%s».', $method_id );
				continue;
			}

			$option_key = 'woocommerce_' . $method_id . '_' . $instance_id . '_settings';
			$stored     = velotok_core_provisioning_option( $option_key, array() );
			$stored     = is_array( $stored ) ? $stored : array();
			$merged     = array_merge( $settings, $stored );
			$merged['enabled'] = 'yes';

			velotok_core_provisioning_update_option( $option_key, $merged );

			$result['zone']['methods'][ $method_id ] = array(
				'instance_id' => $instance_id,
				'option'      => $option_key,
				'enabled'     => 'yes',
			);
		}
	}
}

if ( ! function_exists( 'velotok_core_provisioning_apply_gateways' ) ) {
	/**
	 * Ensure the configured payment gateways exist and are enabled.
	 *
	 * Existing settings (including secrets and deliberate admin choices) are
	 * preserved; only missing defaults are added.
	 *
	 * @param array $gateways Gateway declarations.
	 * @param array $result   Result accumulator (by reference).
	 * @return void
	 */
	function velotok_core_provisioning_apply_gateways( $gateways, &$result ) {
		foreach ( (array) $gateways as $gateway_id => $gateway ) {
			$option = isset( $gateway['option'] ) ? (string) $gateway['option'] : 'woocommerce_' . $gateway_id . '_settings';
			$stored = velotok_core_provisioning_option( $option, array() );
			$stored = is_array( $stored ) ? $stored : array();
			$values = isset( $gateway['settings'] ) && is_array( $gateway['settings'] ) ? $gateway['settings'] : array();
			$merged = array_merge( $values, $stored );

			if ( ! empty( $gateway['force_enabled'] ) ) {
				$merged['enabled'] = 'yes';
			}

			velotok_core_provisioning_update_option( $option, $merged );

			$result['payment_gateways'][ $gateway_id ] = array(
				'option'  => $option,
				'enabled' => isset( $merged['enabled'] ) && 'yes' === (string) $merged['enabled'],
			);
		}
	}
}

if ( ! function_exists( 'velotok_core_provisioning_apply_language' ) ) {
	/**
	 * Install/activate the Russian language pack through WP-CLI when possible.
	 *
	 * The commands are idempotent. In a plain PHP process (contract tests) or
	 * an offline environment they are skipped with a warning instead of
	 * failing the whole provisioning run.
	 *
	 * @param array $locale Locale declaration.
	 * @param array $result Result accumulator (by reference).
	 * @param array $args   Provisioning arguments.
	 * @return void
	 */
	function velotok_core_provisioning_apply_language( $locale, &$result, $args = array() ) {
		$result['language'] = array(
			'language' => isset( $locale['language'] ) ? (string) $locale['language'] : 'ru_RU',
			'applied'  => false,
			'skipped'  => false,
			'commands' => array(),
		);

		if ( ! empty( $args['skip_language'] ) ) {
			$result['language']['skipped'] = true;
			return;
		}

		if ( ! class_exists( 'WP_CLI' ) || ! method_exists( 'WP_CLI', 'runcommand' ) ) {
			$result['language']['skipped'] = true;
			$result['warnings'][]          = 'WP-CLI is not available; the Russian language pack was not installed.';
			return;
		}

		foreach ( (array) ( isset( $locale['commands'] ) ? $locale['commands'] : array() ) as $command ) {
			try {
				WP_CLI::runcommand(
					(string) $command,
					array(
						'exit_error' => false,
						'launch'     => false,
					)
				);
				$result['language']['commands'][] = (string) $command;
			} catch ( Exception $exception ) {
				$result['warnings'][] = sprintf( 'Language command failed: %s', $command );
			} catch ( Throwable $throwable ) {
				$result['warnings'][] = sprintf( 'Language command failed: %s', $command );
			}
		}

		$result['language']['applied'] = ! empty( $result['language']['commands'] );
	}
}

if ( ! function_exists( 'velotok_core_provision_store' ) ) {
	/**
	 * Apply the whole store configuration idempotently.
	 *
	 * @param array $args Optional arguments (`skip_language`, `skip_gateways`, `skip_delivery`).
	 * @return array<string,mixed>
	 */
	function velotok_core_provision_store( $args = array() ) {
		$contract = velotok_core_provisioning_contract();
		$args     = is_array( $args ) ? $args : array();
		$result   = array(
			'task'             => 'EBIKE-23',
			'requirement'      => 'EBIKE-23',
			'checkout'         => $contract['checkout'],
			'zone'             => array(
				'id'      => 0,
				'name'    => '',
				'created' => false,
				'methods' => array(),
			),
			'payment_gateways' => array(),
			'language'         => array(),
			'warnings'         => array(),
			'checkout_ready'   => false,
		);

		if ( empty( $args['skip_delivery'] ) ) {
			velotok_core_provisioning_apply_zone( $contract['zone'], $result );
		}

		if ( empty( $args['skip_gateways'] ) ) {
			velotok_core_provisioning_apply_gateways( $contract['payment_gateways'], $result );
		}

		velotok_core_provisioning_apply_language( $contract['locale'], $result, $args );
		velotok_core_provisioning_apply_checkout_layout( $result );

		$has_gateway = false;

		foreach ( $result['payment_gateways'] as $gateway ) {
			if ( ! empty( $gateway['enabled'] ) ) {
				$has_gateway = true;
				break;
			}
		}

		$has_method = false;

		foreach ( $result['zone']['methods'] as $method ) {
			if ( ! empty( $method['enabled'] ) ) {
				$has_method = true;
				break;
			}
		}

		$result['checkout_ready'] = $has_gateway && $has_method;

		return $result;
	}
}

if ( ! function_exists( 'velotok_core_provisioning_apply' ) ) {
	/**
	 * Alias for the provisioning runner.
	 *
	 * @param array $args Arguments.
	 * @return array<string,mixed>
	 */
	function velotok_core_provisioning_apply( $args = array() ) {
		return velotok_core_provision_store( $args );
	}
}

if ( ! function_exists( 'velotok_core_provisioning_checkout_ready' ) ) {
	/**
	 * Whether the provisioned store can offer checkout with payment and delivery.
	 *
	 * @param array|null $result Optional provisioning result.
	 * @return bool
	 */
	function velotok_core_provisioning_checkout_ready( $result = null ) {
		if ( null === $result ) {
			$result = velotok_core_provision_store();
		}

		return is_array( $result ) && ! empty( $result['checkout_ready'] );
	}
}
