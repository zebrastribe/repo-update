<?php
/**
 * Slug and path helpers.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Helpers;

/**
 * Helpers for plugin and theme identification.
 */
final class SlugHelper {

	/**
	 * Get installed plugins as slug => file pairs.
	 *
	 * @return array<string, string>
	 */
	public static function get_installed_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		$result  = array();

		foreach ( $plugins as $file => $data ) {
			$slug           = dirname( $file );
			$result[ $file ] = sprintf(
				'%s (%s)',
				$data['Name'],
				$slug === '.' ? basename( $file ) : $slug
			);
		}

		return $result;
	}

	/**
	 * Get installed themes as slug => name pairs.
	 *
	 * @return array<string, string>
	 */
	public static function get_installed_themes(): array {
		$themes = wp_get_themes();
		$result = array();

		foreach ( $themes as $slug => $theme ) {
			$result[ $slug ] = $theme->get( 'Name' ) . ' (' . $slug . ')';
		}

		return $result;
	}

	/**
	 * Get installed version for a target.
	 *
	 * @param string $type        plugin|theme.
	 * @param string $target_slug Plugin basename or theme slug.
	 */
	public static function get_installed_version( string $type, string $target_slug ): string {
		if ( 'theme' === $type ) {
			$theme = wp_get_theme( $target_slug );

			return $theme->exists() ? (string) $theme->get( 'Version' ) : '';
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = WP_PLUGIN_DIR . '/' . $target_slug;

		if ( ! file_exists( $plugin_file ) ) {
			return '';
		}

		$data = get_plugin_data( $plugin_file, false, false );

		return (string) ( $data['Version'] ?? '' );
	}

	/**
	 * Get the directory path for a target install.
	 *
	 * @param string $type        plugin|theme.
	 * @param string $target_slug Plugin basename or theme slug.
	 */
	public static function get_install_path( string $type, string $target_slug ): string {
		if ( 'theme' === $type ) {
			return get_theme_root() . '/' . $target_slug;
		}

		$dir = dirname( $target_slug );

		if ( '.' === $dir ) {
			return WP_PLUGIN_DIR . '/' . basename( $target_slug, '.php' );
		}

		return WP_PLUGIN_DIR . '/' . $dir;
	}

	/**
	 * Guess the main plugin file inside a directory.
	 *
	 * @param string $slug Plugin directory slug.
	 */
	public static function guess_plugin_file( string $slug ): string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( array_keys( get_plugins() ) as $file ) {
			if ( strpos( $file, $slug . '/' ) === 0 ) {
				return $file;
			}

			if ( $file === $slug . '.php' ) {
				return $file;
			}
		}

		return $slug . '/' . $slug . '.php';
	}
}
