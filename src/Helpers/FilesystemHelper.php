<?php
/**
 * WordPress Filesystem API wrapper.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Helpers;

/**
 * Filesystem operations via WP_Filesystem.
 */
final class FilesystemHelper {

	/**
	 * Initialize the WordPress filesystem.
	 */
	public static function init(): bool {
		global $wp_filesystem;

		if ( $wp_filesystem instanceof \WP_Filesystem_Base ) {
			return true;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		ob_start();
		$credentials = request_filesystem_credentials( '' );
		ob_end_clean();

		if ( false === $credentials ) {
			return WP_Filesystem();
		}

		return WP_Filesystem( $credentials );
	}

	/**
	 * Get the filesystem instance.
	 */
	public static function instance(): ?\WP_Filesystem_Base {
		if ( ! self::init() ) {
			return null;
		}

		global $wp_filesystem;

		return $wp_filesystem instanceof \WP_Filesystem_Base ? $wp_filesystem : null;
	}

	/**
	 * Recursively copy a directory.
	 *
	 * @param string   $source      Source path.
	 * @param string   $destination Destination path.
	 * @param string[] $exclude     Basenames to exclude.
	 */
	public static function copy_directory( string $source, string $destination, array $exclude = array() ): bool {
		$fs = self::instance();

		if ( ! $fs || ! $fs->is_dir( $source ) ) {
			return false;
		}

		if ( ! $fs->exists( $destination ) && ! $fs->mkdir( $destination ) ) {
			return false;
		}

		$entries = $fs->dirlist( $source, true, false );

		if ( ! is_array( $entries ) ) {
			return false;
		}

		return self::copy_entries( $fs, $source, $destination, $entries, $exclude );
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $directory Directory path.
	 */
	public static function delete_directory( string $directory ): bool {
		$fs = self::instance();

		if ( ! $fs || ! $fs->exists( $directory ) ) {
			return true;
		}

		return $fs->delete( $directory, true );
	}

	/**
	 * Move a file or directory.
	 *
	 * @param string $source      Source path.
	 * @param string $destination Destination path.
	 */
	public static function move( string $source, string $destination ): bool {
		$fs = self::instance();

		if ( ! $fs || ! $fs->exists( $source ) ) {
			return false;
		}

		if ( $fs->exists( $destination ) ) {
			$fs->delete( $destination, true );
		}

		return $fs->move( $source, $destination, true );
	}

	/**
	 * Copy directory entries recursively.
	 *
	 * @param \WP_Filesystem_Base                    $fs          Filesystem.
	 * @param string                                 $source      Source root.
	 * @param string                                 $destination Destination root.
	 * @param array<string, array<string, mixed>>    $entries     Directory listing.
	 * @param string[]                               $exclude     Excluded basenames.
	 */
	private static function copy_entries( \WP_Filesystem_Base $fs, string $source, string $destination, array $entries, array $exclude ): bool {
		foreach ( $entries as $name => $entry ) {
			if ( in_array( $name, $exclude, true ) ) {
				continue;
			}

			$from = trailingslashit( $source ) . $name;
			$to   = trailingslashit( $destination ) . $name;

			if ( 'd' === ( $entry['type'] ?? '' ) ) {
				if ( ! $fs->exists( $to ) && ! $fs->mkdir( $to ) ) {
					return false;
				}

				$children = $fs->dirlist( $from, true, false );

				if ( ! is_array( $children ) || ! self::copy_entries( $fs, $from, $to, $children, $exclude ) ) {
					return false;
				}

				continue;
			}

			if ( ! $fs->copy( $from, $to, true ) ) {
				return false;
			}
		}

		return true;
	}
}
