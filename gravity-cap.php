<?php
/**
 * Plugin Name: Gravity Forms Cap CAPTCHA
 * Plugin URI:  https://github.com/eightam/eightam-gravity-cap
 * Description: Adds a Cap proof-of-work CAPTCHA field to Gravity Forms. Lightweight, privacy-first spam protection.
 * Version:     1.2.0
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

define( 'EGCAP_VERSION', '1.2.0' );
define( 'EGCAP_PLUGIN_FILE', __FILE__ );
define( 'EGCAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

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

	wp_enqueue_script(
		'cap-widget',
		plugins_url( 'assets/js/cap.min.js', __FILE__ ),
		array(),
		EGCAP_VERSION,
		true
	);
}

/**
 * Add type="module" to the Cap widget script tag.
 */
add_filter( 'script_loader_tag', 'egcap_add_module_type', 10, 3 );

function egcap_add_module_type( $tag, $handle, $src ) {
	if ( 'cap-widget' !== $handle ) {
		return $tag;
	}
	return str_replace( '<script ', '<script type="module" ', $tag );
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
						'tooltip' => esc_html__( 'The URL of your self-hosted Cap server, e.g. https://captcha.example.com', 'gravity-cap' ),
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
