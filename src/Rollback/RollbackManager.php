<?php
/**
 * Rollback manager.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Rollback;

use RepoUpdate\Helpers\FilesystemHelper;
use RepoUpdate\Helpers\SlugHelper;
use RepoUpdate\Logger\Logger;
use RepoUpdate\Repository\Repository;

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

		return file_exists( $path . '/.repo-update-meta.json' );
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

		if ( ! FilesystemHelper::init() ) {
			return array(
				'success' => false,
				'message' => __( 'WordPress filesystem is not available for backup.', 'repo-update' ),
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

		if ( ! FilesystemHelper::copy_directory( $source, $backup_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create rollback backup.', 'repo-update' ),
			);
		}

		$meta = array(
			'type'        => $repo->type,
			'target_slug' => $repo->target_slug,
			'version'     => SlugHelper::get_installed_version( $repo->type, $repo->target_slug ),
			'created_at'  => current_time( 'mysql' ),
			'repository'  => $repo->full_name(),
		);

		$fs = FilesystemHelper::instance();
		$fs->put_contents( $backup_path . '/.repo-update-meta.json', wp_json_encode( $meta ) );

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
	 * Restore rollback backup atomically.
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

		if ( ! FilesystemHelper::init() ) {
			return array(
				'success' => false,
				'message' => __( 'WordPress filesystem is not available for restore.', 'repo-update' ),
			);
		}

		$temp_path = $target_path . '-repo-update-tmp';
		$old_path  = $target_path . '-repo-update-old';

		FilesystemHelper::delete_directory( $temp_path );
		FilesystemHelper::delete_directory( $old_path );

		if ( ! FilesystemHelper::copy_directory( $backup_path, $temp_path, array( '.repo-update-meta.json' ) ) ) {
			FilesystemHelper::delete_directory( $temp_path );

			return array(
				'success' => false,
				'message' => __( 'Failed to stage rollback backup.', 'repo-update' ),
			);
		}

		if ( is_dir( $target_path ) && ! FilesystemHelper::move( $target_path, $old_path ) ) {
			FilesystemHelper::delete_directory( $temp_path );

			return array(
				'success' => false,
				'message' => __( 'Failed to move current install aside for rollback.', 'repo-update' ),
			);
		}

		if ( ! FilesystemHelper::move( $temp_path, $target_path ) ) {
			if ( is_dir( $old_path ) ) {
				FilesystemHelper::move( $old_path, $target_path );
			}

			FilesystemHelper::delete_directory( $temp_path );

			return array(
				'success' => false,
				'message' => __( 'Failed to restore rollback backup.', 'repo-update' ),
			);
		}

		FilesystemHelper::delete_directory( $old_path );
		FilesystemHelper::delete_directory( $temp_path );

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
		FilesystemHelper::delete_directory( $this->get_backup_path( $type, $target_slug ) );
	}

	/**
	 * Remove a directory path.
	 *
	 * @param string $directory Directory path.
	 */
	public function remove_path( string $directory ): void {
		FilesystemHelper::delete_directory( $directory );
	}

	/**
	 * Delete all rollback backups.
	 */
	public function delete_all_backups(): void {
		FilesystemHelper::delete_directory( $this->get_backup_root() );
	}
}
