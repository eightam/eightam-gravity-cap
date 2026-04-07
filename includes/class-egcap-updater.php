<?php
/**
 * Plugin auto-updater via GitHub Releases.
 *
 * Checks the public GitHub repository for new releases and integrates
 * with the WordPress plugin update mechanism.
 *
 * @package GravityCap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EGCAP_Updater {

	private const GITHUB_REPO  = 'eightam/eightam-gravity-cap';
	private const PLUGIN_SLUG  = 'eightam-gravity-cap';
	private const PLUGIN_FILE  = 'eightam-gravity-cap/gravity-cap.php';
	private const CACHE_KEY    = 'egcap_update_info';
	private const CACHE_EXPIRY = 12 * HOUR_IN_SECONDS;

	/**
	 * Initialize update hooks.
	 */
	public static function init() {
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'check_update' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache' ), 10, 2 );
	}

	/**
	 * Fetch the latest release from GitHub (cached).
	 *
	 * @return object|false
	 */
	private static function get_remote_info() {
		$cached = get_transient( self::CACHE_KEY );

		if ( false !== $cached ) {
			return $cached;
		}

		$url      = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';
		$response = wp_remote_get( $url, array(
			'timeout' => 10,
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
			),
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $release ) || empty( $release->tag_name ) ) {
			return false;
		}

		// Strip leading "v" from tag if present (e.g. "v1.2.0" -> "1.2.0").
		$version = ltrim( $release->tag_name, 'v' );

		// Find the zip asset, or fall back to the GitHub-generated zipball.
		$download_url = $release->zipball_url;
		if ( ! empty( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( str_ends_with( $asset->name, '.zip' ) ) {
					$download_url = $asset->browser_download_url;
					break;
				}
			}
		}

		$info = (object) array(
			'version'      => $version,
			'download_url' => $download_url,
			'changelog'    => $release->body ?? '',
			'homepage'     => $release->html_url ?? 'https://github.com/' . self::GITHUB_REPO,
		);

		set_transient( self::CACHE_KEY, $info, self::CACHE_EXPIRY );

		return $info;
	}

	/**
	 * Inject update info into the plugins transient.
	 *
	 * @param object $transient The update_plugins transient.
	 * @return object
	 */
	public static function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = self::get_remote_info();

		if ( false === $remote || empty( $remote->version ) ) {
			return $transient;
		}

		$current_version = $transient->checked[ self::PLUGIN_FILE ] ?? EGCAP_VERSION;

		if ( version_compare( $remote->version, $current_version, '>' ) ) {
			$transient->response[ self::PLUGIN_FILE ] = (object) array(
				'slug'         => self::PLUGIN_SLUG,
				'plugin'       => self::PLUGIN_FILE,
				'new_version'  => $remote->version,
				'url'          => $remote->homepage,
				'package'      => $remote->download_url,
				'tested'       => '',
				'requires'     => '5.0',
				'requires_php' => '7.4',
			);
		}

		return $transient;
	}

	/**
	 * Provide plugin info for the "View details" modal.
	 *
	 * @param false|object|array $result Current result.
	 * @param string             $action API action.
	 * @param object             $args   Request args.
	 * @return false|object
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || self::PLUGIN_SLUG !== ( $args->slug ?? '' ) ) {
			return $result;
		}

		$remote = self::get_remote_info();

		if ( false === $remote ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Gravity Forms Cap CAPTCHA',
			'slug'          => self::PLUGIN_SLUG,
			'version'       => $remote->version,
			'author'        => '<a href="https://8am.ch">8am GmbH</a>',
			'homepage'      => $remote->homepage,
			'download_link' => $remote->download_url,
			'tested'        => '',
			'requires'      => '5.0',
			'requires_php'  => '7.4',
			'sections'      => array(
				'description' => __( 'Adds a Cap proof-of-work CAPTCHA field to Gravity Forms.', 'gravity-cap' ),
				'changelog'   => $remote->changelog ?? '',
			),
		);
	}

	/**
	 * Clear cached info after an update.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance.
	 * @param array        $options  Update options.
	 */
	public static function clear_cache( $upgrader, $options ) {
		if ( 'update' === ( $options['action'] ?? '' )
			&& 'plugin' === ( $options['type'] ?? '' )
		) {
			delete_transient( self::CACHE_KEY );
		}
	}
}
