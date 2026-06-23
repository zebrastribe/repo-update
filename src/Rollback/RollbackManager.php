<?php
/**
 * Rollback manager.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Rollback;

use RepoUpdate\Helpers\SlugHelper;
use RepoUpdate\Logger\Logger;
use RepoUpdate\Repository\Repository;
use WP_Filesystem_Base;

/**
 * Manages single-version rollback backups.
 */
final class RollbackManager {

	public const BACKUP_DIR = 'repo-update-backups';

	/**
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Get backup root directory.
	 */
	public function get_backup_root(): string {
		return WP_CONTENT_DIR . '/' . self::BACKUP_DIR;
	}

	/**
	 * Get backup path for a target.
	 *
	 * @param string $type        plugin|theme.
	 * @param string $target_slug Target slug.
	 */
	public function get_backup_path( string $type, string $target_slug ): string {
		$key = sanitize_key( $type . '-' . str_replace( array( '/', '\\' ), '-', $target_slug ) );

		return $this->get_backup_root() . '/' . $key;
	}

	/**
	 * Whether a rollback backup exists.
	 *
	 * @param string $type        plugin|theme.
	 * @param string $target_slug Target slug.
	 */
	public function has_backup( string $type, string $target_slug ): bool {
		$path = $this->get_backup_path( $type, $target_slug );

		return is_dir( $path ) && file_exists( $path . '/.repo-update-meta.json' );
	}

	/**
	 * Get backup metadata.
	 *
	 * @param string $type        plugin|theme.
	 * @param string $target_slug Target slug.
	 * @return array<string, mixed>|null
	 */
	public function get_backup_meta( string $type, string $target_slug ): ?array {
		$meta_file = $this->get_backup_path( $type, $target_slug ) . '/.repo-update-meta.json';

		if ( ! file_exists( $meta_file ) ) {
			return null;
		}

		$data = json_decode( (string) file_get_contents( $meta_file ), true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Create backup before update.
	 *
	 * @param Repository $repo Repository entity.
	 * @return array{success: bool, message: string}
	 */
	public function create_backup( Repository $repo ): array {
		if ( ! $repo->rollback_enabled ) {
			return array(
				'success' => true,
				'message' => __( 'Rollback disabled.', 'repo-update' ),
			);
		}

		$source = SlugHelper::get_install_path( $repo->type, $repo->target_slug );

		if ( ! is_dir( $source ) ) {
			return array(
				'success' => false,
				'message' => __( 'Install path not found for backup.', 'repo-update' ),
			);
		}

		$backup_path = $this->get_backup_path( $repo->type, $repo->target_slug );

		$this->delete_backup( $repo->type, $repo->target_slug );

		wp_mkdir_p( $this->get_backup_root() );

		$copied = $this->copy_directory( $source, $backup_path );

		if ( ! $copied ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create rollback backup.', 'repo-update' ),
			);
		}

		$meta = array(
			'type'         => $repo->type,
			'target_slug'  => $repo->target_slug,
			'version'      => SlugHelper::get_installed_version( $repo->type, $repo->target_slug ),
			'created_at'   => current_time( 'mysql' ),
			'repository'   => $repo->full_name(),
		);

		file_put_contents( $backup_path . '/.repo-update-meta.json', wp_json_encode( $meta ) );

		$this->logger->log(
			'rollback_create',
			sprintf( 'Created rollback backup for %s.', $repo->target_slug ),
			Logger::LEVEL_INFO,
			$repo->id,
			array( 'version' => $meta['version'] )
		);

		return array(
			'success' => true,
			'message' => __( 'Rollback backup created.', 'repo-update' ),
		);
	}

	/**
	 * Restore rollback backup.
	 *
	 * @param Repository $repo Repository entity.
	 * @return array{success: bool, message: string}
	 */
	public function restore_backup( Repository $repo ): array {
		$backup_path = $this->get_backup_path( $repo->type, $repo->target_slug );
		$target_path = SlugHelper::get_install_path( $repo->type, $repo->target_slug );

		if ( ! $this->has_backup( $repo->type, $repo->target_slug ) ) {
			return array(
				'success' => false,
				'message' => __( 'No rollback backup available.', 'repo-update' ),
			);
		}

		$this->delete_directory( $target_path );

		$restored = $this->copy_directory( $backup_path, $target_path, array( '.repo-update-meta.json' ) );

		if ( ! $restored ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to restore rollback backup.', 'repo-update' ),
			);
		}

		$meta = $this->get_backup_meta( $repo->type, $repo->target_slug );

		$this->logger->log(
			'rollback_restore',
			sprintf( 'Restored rollback for %s.', $repo->target_slug ),
			Logger::LEVEL_INFO,
			$repo->id,
			array( 'version' => $meta['version'] ?? '' )
		);

		return array(
			'success' => true,
			'message' => __( 'Rollback restored successfully.', 'repo-update' ),
		);
	}

	/**
	 * Delete rollback backup.
	 *
	 * @param string $type        plugin|theme.
	 * @param string $target_slug Target slug.
	 */
	public function delete_backup( string $type, string $target_slug ): void {
		$path = $this->get_backup_path( $type, $target_slug );

		if ( is_dir( $path ) ) {
			$this->delete_directory( $path );
		}
	}

	/**
	 * Remove a directory path.
	 *
	 * @param string $directory Directory path.
	 */
	public function remove_path( string $directory ): void {
		$this->delete_directory( $directory );
	}

	/**
	 * Delete all rollback backups.
	 */
	public function delete_all_backups(): void {
		$root = $this->get_backup_root();

		if ( is_dir( $root ) ) {
			$this->delete_directory( $root );
		}
	}

	/**
	 * Recursively copy a directory.
	 *
	 * @param string   $source      Source directory.
	 * @param string   $destination Destination directory.
	 * @param string[] $exclude     Files to exclude.
	 */
	private function copy_directory( string $source, string $destination, array $exclude = array() ): bool {
		if ( ! is_dir( $source ) ) {
			return false;
		}

		wp_mkdir_p( $destination );

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$relative = substr( $item->getPathname(), strlen( $source ) + 1 );

			if ( in_array( basename( $relative ), $exclude, true ) ) {
				continue;
			}

			$target = $destination . '/' . $relative;

			if ( $item->isDir() ) {
				wp_mkdir_p( $target );
			} else {
				wp_mkdir_p( dirname( $target ) );
				copy( $item->getPathname(), $target );
			}
		}

		return true;
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $directory Directory path.
	 */
	private function delete_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}

		rmdir( $directory );
	}
}
