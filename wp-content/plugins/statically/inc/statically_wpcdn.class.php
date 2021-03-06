<?php

/**
 * Statically_WPCDN
 *
 * @since 0.5.0
 */

class Statically_WPCDN
{
	const CDN = 'https://cdn.statically.io/wp/';

	/**
	 * Sets up action handlers needed for Statically class.
	 */
	public static function hook() {
		$options = Statically::get_options();

		if ( $options['wpcdn'] ) {
			$GLOBALS['concatenate_scripts'] = false;

			add_action( 'wp_print_scripts', array( __CLASS__, 'cdnize_assets' ) );
			add_action( 'wp_print_styles', array( __CLASS__, 'cdnize_assets' ) );
			add_action( 'admin_print_scripts', array( __CLASS__, 'cdnize_assets' ) );
			add_action( 'admin_print_styles', array( __CLASS__, 'cdnize_assets' ) );
			add_action( 'wp_footer', array( __CLASS__, 'cdnize_assets' ) );
			add_filter( 'load_script_textdomain_relative_path', array( __CLASS__, 'fix_script_relative_path' ), 10, 2 );
		}
	}

	/**
	 * Sets up CDN URLs for assets that are enqueued by the WordPress Core.
	 */
	public static function cdnize_assets() {
		global $wp_scripts, $wp_styles, $wp_version;

		/**
		 * Filters Statically WP CDN's Core version number and locale. Can be used to override the values
		 * that Statically uses to retrieve assets. Expects the values to be returned in an array.
		 *
		 * @since 0.5.0
		 *
		 * @param array $values array( $version  = core assets version, i.e. 4.9.8, $locale = desired locale )
		 */
		list( $version, $locale ) = apply_filters(
			'statically_wpcdn_core_version_and_locale',
			array( $wp_version, get_locale() )
		);

		if ( self::is_public_version( $version ) ) {
			$site_url = trailingslashit( site_url() );
			foreach ( $wp_scripts->registered as $handle => $thing ) {
				if ( wp_startswith( $thing->src, self::CDN ) ) {
					continue;
				}
				$src = ltrim( str_replace( $site_url, '', $thing->src ), '/' );
				if ( self::is_js_or_css_file( $src ) && in_array( substr( $src, 0, 9 ), array( 'wp-admin/', 'wp-includ' ) ) ) {
					$wp_scripts->registered[ $handle ]->src = sprintf( self::CDN . 'c/%1$s/%2$s', $version, $src );
					$wp_scripts->registered[ $handle ]->ver = null;
				}
			}
			foreach ( $wp_styles->registered as $handle => $thing ) {
				if ( wp_startswith( $thing->src, self::CDN ) ) {
					continue;
				}
				$src = ltrim( str_replace( $site_url, '', $thing->src ), '/' );
				if ( self::is_js_or_css_file( $src ) && in_array( substr( $src, 0, 9 ), array( 'wp-admin/', 'wp-includ' ) ) ) {
					$wp_styles->registered[ $handle ]->src = sprintf( self::CDN . 'c/%1$s/%2$s', $version, $src );
					$wp_styles->registered[ $handle ]->ver = null;
				}
			}
		}

		self::cdnize_plugin_assets( 'statically', STATICALLY_VERSION );
	}

	/**
	 * Ensure use of the correct relative path when determining the JavaScript file names.
	 *
	 * @param string $relative The relative path of the script. False if it could not be determined.
	 * @param string $src      The full source url of the script.
	 * @return string The expected relative path for the CDN-ed URL.
	 */
	public static function fix_script_relative_path( $relative, $src ) {
		$strpos = strpos( $src, '/wp-includes/' );

		// We only treat URLs that have wp-includes in them. Cases like language textdomains
		// can also use this filter, they don't need to be touched because they are local paths.
		if ( false === $strpos ) {
			return $relative;
		}
		return substr( $src, 1 + $strpos );
	}

	/**
	 * Sets up CDN URLs for supported plugin assets.
	 *
	 * @param String $plugin_slug plugin slug string.
	 * @param String $current_version plugin version string.
	 * @return null|bool
	 */
	public static function cdnize_plugin_assets( $plugin_slug, $current_version ) {
		global $wp_scripts, $wp_styles;

		/**
		 * Filters Statically WP CDN's plugin slug and version number. Can be used to override the values
		 * that Statically uses to retrieve assets. For example, when testing a development version of Statically
		 * the assets are not yet published, so you may need to override the version value to either
		 * trunk, or the latest available version. Expects the values to be returned in an array.
		 *
		 * @since 0.5.0
		 *
		 * @param array $values array( $slug = the plugin repository slug, i.e. statically, $version = the plugin version, i.e. 6.6 )
		 */
		list( $plugin_slug, $current_version ) = apply_filters(
			'statically_wpcdn_plugin_slug_and_version',
			array( $plugin_slug, $current_version )
		);

		$assets               = self::get_plugin_assets( $plugin_slug, $current_version );
		$plugin_directory_url = plugins_url() . '/' . $plugin_slug . '/';

		if ( is_wp_error( $assets ) || ! is_array( $assets ) ) {
			return false;
		}

		foreach ( $wp_scripts->registered as $handle => $thing ) {
			if ( wp_startswith( $thing->src, self::CDN ) ) {
				continue;
			}
			if ( wp_startswith( $thing->src, $plugin_directory_url ) ) {
				$local_path = substr( $thing->src, strlen( $plugin_directory_url ) );
				if ( in_array( $local_path, $assets, true ) ) {
					$wp_scripts->registered[ $handle ]->src = sprintf( self::CDN . 'p/%1$s/%2$s/%3$s', $plugin_slug, $current_version, $local_path );
					$wp_scripts->registered[ $handle ]->ver = null;
				}
			}
		}
		foreach ( $wp_styles->registered as $handle => $thing ) {
			if ( wp_startswith( $thing->src, self::CDN ) ) {
				continue;
			}
			if ( wp_startswith( $thing->src, $plugin_directory_url ) ) {
				$local_path = substr( $thing->src, strlen( $plugin_directory_url ) );
				if ( in_array( $local_path, $assets, true ) ) {
					$wp_styles->registered[ $handle ]->src = sprintf( self::CDN . 'p/%1$s/%2$s/%3$s', $plugin_slug, $current_version, $local_path );
					$wp_styles->registered[ $handle ]->ver = null;
				}
			}
		}
	}

	/**
	 * Returns cdn-able assets for a given plugin.
	 *
	 * @param string $plugin plugin slug string.
	 * @param string $version plugin version number string.
	 * @return array|bool Will return false if not a public version.
	 */
	public static function get_plugin_assets( $plugin, $version ) {
		if ( 'statically' === $plugin && STATICALLY_VERSION === $version ) {
			if ( ! self::is_public_version( $version ) ) {
				return false;
			}

			$assets = array(); // The variable will be redefined in the included file.

			include STATICALLY_DIR . '/inc/statically.manifest.php';
			return $assets;
		}

		/**
		 * Used for other plugins to provide their bundled assets via filter to
		 * prevent the need of storing them in an option or an external api request
		 * to w.org.
		 *
		 * @since 0.5.0
		 *
		 * @param array $assets The assets array for the plugin.
		 * @param string $version The version of the plugin being requested.
		 */
		$assets = apply_filters( "statically_wpcdn_plugin_assets-{$plugin}", null, $version );
		if ( is_array( $assets ) ) {
			return $assets;
		}

		if ( ! self::is_public_version( $version ) ) {
			return false;
		}

		$url = sprintf( 'http://downloads.wordpress.org/plugin-checksums/%s/%s.json', $plugin, $version );

		if ( wp_http_supports( array( 'ssl' ) ) ) {
			$url = set_url_scheme( $url, 'https' );
		}

		$response = wp_remote_get( $url );

		$body = trim( wp_remote_retrieve_body( $response ) );
		$body = json_decode( $body, true );

		$return = time();
		if ( is_array( $body ) ) {
			$return = array_filter( array_keys( $body['files'] ), array( __CLASS__, 'is_js_or_css_file' ) );
		}

		return $return;
	}

	/**
	 * Checks a path whether it is a JS or CSS file.
	 *
	 * @param String $path file path.
	 * @return Boolean whether the file is a JS or CSS.
	 */
	public static function is_js_or_css_file( $path ) {
		return ( false === strpos( $path, '?' ) ) && in_array( substr( $path, -3 ), array( 'css', '.js' ), true );
	}

	/**
	 * Checks whether the version string indicates a production version.
	 *
	 * @param String  $version the version string.
	 * @param Boolean $include_beta_and_rc whether to count beta and RC versions as production.
	 * @return Boolean
	 */
	public static function is_public_version( $version, $include_beta_and_rc = false ) {
		if ( preg_match( '/^\d+(\.\d+)+$/', $version ) ) {
			// matches `1` `1.2` `1.2.3`.
			return true;
		} elseif ( $include_beta_and_rc && preg_match( '/^\d+(\.\d+)+(-(beta|rc|pressable)\d?)$/i', $version ) ) {
			// matches `1.2.3` `1.2.3-beta` `1.2.3-pressable` `1.2.3-beta1` `1.2.3-rc` `1.2.3-rc2`.
			return true;
		}
		// unrecognized version.
		return false;
	}
}
