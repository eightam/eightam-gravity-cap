<?php
/**
 * Plugin Name: Gravity Forms Cap CAPTCHA
 * Plugin URI:  https://github.com/eightam/eightam-gravity-cap
 * Description: Adds a Cap proof-of-work CAPTCHA field to Gravity Forms. Lightweight, privacy-first spam protection.
 * Version:     1.2.4
 * Author:      8am GmbH
 * Author URI:  https://8am.ch
 * Text Domain: gravity-cap
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EGCAP_VERSION', '1.2.4' );
define( 'EGCAP_PLUGIN_FILE', __FILE__ );
define( 'EGCAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// CDN fallback when the Cap server's assets endpoint is unavailable.
// Pinned to a known-good version — bump when upgrading.
define( 'EGCAP_CDN_WIDGET_URL', 'https://cdn.jsdelivr.net/npm/@cap.js/widget@0.1.51/cap.min.js' );

// Self-updater via GitHub Releases (runs independently of Gravity Forms).
require_once EGCAP_PLUGIN_DIR . 'includes/class-egcap-updater.php';
EGCAP_Updater::init();

/**
 * Bootstrap the plugin after Gravity Forms has loaded.
 */
add_action( 'gform_loaded', 'egcap_init', 5 );

function egcap_init() {
	if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
		return;
	}

	load_plugin_textdomain( 'gravity-cap', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	GFForms::include_addon_framework();

	require_once EGCAP_PLUGIN_DIR . 'includes/class-gf-field-cap.php';

	GFAddOn::register( 'GF_Cap_Settings' );
	GF_Fields::register( new GF_Field_Cap() );
}

/**
 * Retrieve a plugin setting.
 *
 * @param string $key Setting key.
 * @return string
 */
function egcap_get_setting( $key ) {
	$settings = get_option( 'gravityformsaddon_gravity-cap_settings', array() );
	return isset( $settings[ $key ] ) ? trim( $settings[ $key ] ) : '';
}

/**
 * Check whether a form contains a Cap CAPTCHA field.
 *
 * @param array $form The form object.
 * @return bool
 */
function egcap_form_has_cap_field( $form ) {
	if ( empty( $form['fields'] ) ) {
		return false;
	}
	foreach ( $form['fields'] as $field ) {
		if ( $field->type === 'cap_captcha' ) {
			return true;
		}
	}
	return false;
}

/**
 * Enqueue the Cap widget script on forms that use it.
 *
 * @param array $form The form object.
 */
add_action( 'gform_enqueue_scripts', 'egcap_enqueue_scripts', 10, 2 );

function egcap_enqueue_scripts( $form, $ajax ) {
	if ( ! egcap_form_has_cap_field( $form ) ) {
		return;
	}

	$server_url = egcap_get_setting( 'cap_server_url' );
	if ( empty( $server_url ) ) {
		return;
	}

	if ( egcap_assets_server_available( $server_url ) ) {
		$widget_src = trailingslashit( $server_url ) . 'assets/widget.js';
	} else {
		$widget_src = EGCAP_CDN_WIDGET_URL;
	}

	wp_enqueue_script(
		'cap-widget',
		$widget_src,
		array(),
		null,
		true
	);
}

/**
 * Check whether the Cap server's assets endpoint is reachable.
 *
 * Result is cached in a transient (6h) and re-checked when settings are saved.
 *
 * @param string $server_url Configured Cap server URL.
 * @return bool True if widget.js is reachable on the Cap server.
 */
function egcap_assets_server_available( $server_url ) {
	$cache_key = 'egcap_assets_ok_' . md5( $server_url );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return '1' === $cached;
	}

	$ok = egcap_probe_assets_endpoint( $server_url );
	set_transient( $cache_key, $ok ? '1' : '0', 6 * HOUR_IN_SECONDS );

	return $ok;
}

/**
 * Probe the Cap server's /assets/widget.js endpoint.
 *
 * @param string $server_url Cap server base URL.
 * @return bool
 */
function egcap_probe_assets_endpoint( $server_url ) {
	$url = trailingslashit( $server_url ) . 'assets/widget.js';

	$response = wp_remote_head( $url, array(
		'timeout'     => 5,
		'redirection' => 2,
	) );

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	return $code >= 200 && $code < 400;
}

/**
 * Re-probe the assets endpoint whenever the settings are saved.
 */
add_action( 'update_option_gravityformsaddon_gravity-cap_settings', 'egcap_refresh_assets_probe', 10, 2 );
add_action( 'add_option_gravityformsaddon_gravity-cap_settings', 'egcap_refresh_assets_probe_added', 10, 2 );

function egcap_refresh_assets_probe( $old_value, $new_value ) {
	$server_url = isset( $new_value['cap_server_url'] ) ? trim( $new_value['cap_server_url'] ) : '';
	if ( empty( $server_url ) ) {
		return;
	}
	delete_transient( 'egcap_assets_ok_' . md5( $server_url ) );
	// Prime the cache immediately so the next page load uses the fresh result.
	egcap_assets_server_available( $server_url );
}

function egcap_refresh_assets_probe_added( $option, $value ) {
	egcap_refresh_assets_probe( array(), $value );
}

/**
 * Settings page under Forms > Settings > Cap CAPTCHA.
 */
class GF_Cap_Settings extends GFAddOn {

	protected $_version                  = EGCAP_VERSION;
	protected $_min_gravityforms_version = '2.5';
	protected $_slug                     = 'gravity-cap';
	protected $_path                     = 'eightam-gravity-cap/gravity-cap.php';
	protected $_full_path                = EGCAP_PLUGIN_FILE;
	protected $_title                    = 'Cap CAPTCHA Settings';
	protected $_short_title              = 'Cap CAPTCHA';

	private static $_instance = null;

	public static function get_instance() {
		if ( self::$_instance === null ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Cap CAPTCHA Configuration', 'gravity-cap' ),
				'fields' => array(
					array(
						'name'    => 'cap_server_url',
						'label'   => esc_html__( 'Cap Server URL', 'gravity-cap' ),
						'type'    => 'text',
						'class'   => 'medium',
						'tooltip' => esc_html__( 'The URL of your self-hosted Cap server, e.g. https://captcha.example.com. Enable ENABLE_ASSETS_SERVER on the Cap server to serve the widget directly; otherwise the plugin falls back to a public CDN.', 'gravity-cap' ),
					),
					array(
						'name'    => 'cap_site_key',
						'label'   => esc_html__( 'Site Key', 'gravity-cap' ),
						'type'    => 'text',
						'class'   => 'medium',
						'tooltip' => esc_html__( 'The site key from your Cap server dashboard.', 'gravity-cap' ),
					),
					array(
						'name'    => 'cap_secret',
						'label'   => esc_html__( 'API Secret', 'gravity-cap' ),
						'type'    => 'text',
						'class'   => 'medium',
						'tooltip' => esc_html__( 'The secret key used for server-side token verification.', 'gravity-cap' ),
					),
				),
			),
		);
	}
}
