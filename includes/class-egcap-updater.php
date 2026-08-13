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
	private const CANON_SLUG   = 'eightam-gravity-cap';
	private const CACHE_KEY    = 'egcap_update_info';
	private const CACHE_EXPIRY = 12 * HOUR_IN_SECONDS;

	/**
	 * Initialize update hooks.
	 */
	public static function init() {
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'check_update' ) );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'rename_source' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache' ), 10, 2 );
	}

	/**
	 * The plugin's basename as WordPress knows it, e.g.
	 * `eightam-gravity-cap/gravity-cap.php`.
	 *
	 * Derived at runtime rather than hardcoded: installs unpacked from a
	 * GitHub zipball or a versioned zip land in a directory such as
	 * `eightam-gravity-cap-1.2.5`, and a hardcoded basename would never
	 * match, silently disabling updates for exactly the installs that need
	 * them most.
	 *
	 * @return string
	 */
	private static function plugin_file() {
		return plugin_basename( EGCAP_PLUGIN_FILE );
	}

	/**
	 * The directory this plugin actually lives in, used as the update slug.
	 *
	 * @return string
	 */
	private static function plugin_slug() {
		return dirname( self::plugin_file() );
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

		$plugin_file     = self::plugin_file();
		$current_version = $transient->checked[ $plugin_file ] ?? EGCAP_VERSION;

		if ( version_compare( $remote->version, $current_version, '>' ) ) {
			$transient->response[ $plugin_file ] = (object) array(
				'slug'         => self::plugin_slug(),
				'plugin'       => $plugin_file,
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
		$slug = $args->slug ?? '';

		if ( 'plugin_information' !== $action
			|| ( self::CANON_SLUG !== $slug && self::plugin_slug() !== $slug )
		) {
			return $result;
		}

		$remote = self::get_remote_info();

		if ( false === $remote ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Gravity Forms Cap CAPTCHA',
			'slug'          => self::plugin_slug(),
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
	 * Normalize the unpacked directory name before WordPress installs it.
	 *
	 * Releases without an attached zip fall back to GitHub's zipball, which
	 * unpacks to `eightam-eightam-gravity-cap-<sha>/`. WordPress derives the
	 * install destination from that directory name, so an update would land
	 * in a brand new folder next to the existing one, leaving the old (and
	 * still active) copy in place. Rename the source to the directory the
	 * plugin already occupies so the update overwrites it.
	 *
	 * @param string       $source        Path to the unpacked files.
	 * @param string       $remote_source Path to the upload/download dir.
	 * @param \WP_Upgrader $upgrader      Upgrader instance.
	 * @param array        $hook_extra    Extra args; `plugin` is the basename.
	 * @return string|\WP_Error
	 */
	public static function rename_source( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		// Only touch our own upgrades — never rewrite another plugin's source.
		if ( self::plugin_file() !== ( $hook_extra['plugin'] ?? '' ) ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . self::plugin_slug();

		if ( untrailingslashit( $source ) === $desired || ! $wp_filesystem ) {
			return $source;
		}

		if ( ! $wp_filesystem->move( $source, $desired ) ) {
			return new WP_Error(
				'egcap_rename_failed',
				__( 'Could not rename the downloaded plugin directory.', 'gravity-cap' )
			);
		}

		return trailingslashit( $desired );
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
