<?php
/**
 * Repository business logic.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Repository;

use RepoUpdate\API\GitHubClient;
use RepoUpdate\Helpers\SlugHelper;
use RepoUpdate\Logger\Logger;
use RepoUpdate\Settings\Settings;

/**
 * Coordinates repository operations and update checks.
 */
final class RepositoryManager {

	/**
	 * @var RepositoryStore
	 */
	private RepositoryStore $store;

	/**
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * @param RepositoryStore $store  Repository store.
	 * @param Logger          $logger Logger instance.
	 */
	public function __construct( RepositoryStore $store, Logger $logger ) {
		$this->store  = $store;
		$this->logger = $logger;
	}

	/**
	 * Get all repositories.
	 *
	 * @return Repository[]
	 */
	public function all(): array {
		return $this->store->all();
	}

	/**
	 * Get enabled repositories.
	 *
	 * @return Repository[]
	 */
	public function enabled(): array {
		return $this->store->enabled();
	}

	/**
	 * Find repository by ID.
	 *
	 * @param int $id Repository ID.
	 */
	public function find( int $id ): ?Repository {
		return $this->store->find( $id );
	}

	/**
	 * Save repository configuration.
	 *
	 * @param array<string, mixed> $data Form data.
	 */
	public function save( array $data ): int {
		return $this->store->save( $data );
	}

	/**
	 * Delete repository.
	 *
	 * @param int $id Repository ID.
	 */
	public function delete( int $id ): bool {
		$repo = $this->find( $id );

		if ( $repo ) {
			$this->logger->log(
				'repository_delete',
				sprintf( 'Deleted repository %s.', $repo->full_name() ),
				Logger::LEVEL_INFO,
				$id
			);
		}

		return $this->store->delete( $id );
	}

	/**
	 * Toggle enabled state.
	 *
	 * @param int $id Repository ID.
	 */
	public function toggle_enabled( int $id ): void {
		$repo = $this->find( $id );

		if ( ! $repo ) {
			return;
		}

		$this->store->set_enabled( $id, ! $repo->enabled );
	}

	/**
	 * Check all repositories that are due.
	 */
	public function check_all_due(): void {
		$settings = new Settings();

		foreach ( $this->enabled() as $repo ) {
			if ( $this->is_due( $repo, $settings->get_check_interval() ) ) {
				$this->check_repository( $repo, new GitHubClient( $settings ) );
			}
		}
	}

	/**
	 * Check a single repository for updates.
	 *
	 * @param Repository       $repo   Repository entity.
	 * @param GitHubClient|null $client Optional GitHub client.
	 * @return array{success: bool, message: string, update_available?: bool}
	 */
	public function check_repository( Repository $repo, ?GitHubClient $client = null ): array {
		$client = $client ?: new GitHubClient( new Settings() );
		$token  = $repo->get_token();

		if ( '' === $token ) {
			$this->update_status( $repo->id, 'error', '', '', __( 'Missing access token.', 'repo-update' ) );

			return array(
				'success' => false,
				'message' => __( 'Missing access token.', 'repo-update' ),
			);
		}

		$installed = SlugHelper::get_installed_version( $repo->type, $repo->target_slug );

		if ( '' === $installed ) {
			$this->update_status( $repo->id, 'not_installed', $installed, '', __( 'Target is not installed.', 'repo-update' ) );

			return array(
				'success' => false,
				'message' => __( 'Target plugin or theme is not installed.', 'repo-update' ),
			);
		}

		$result = $client->get_remote_version(
			$repo->owner,
			$repo->name,
			$token,
			$repo->branch,
			$repo->type,
			$repo->target_slug,
			$repo->plugin_file
		);

		$this->logger->log(
			'update_check',
			$result['success']
				? sprintf( 'Checked %s: remote version %s.', $repo->full_name(), $result['version'] ?? '' )
				: sprintf( 'Check failed for %s: %s', $repo->full_name(), $result['message'] ?? '' ),
			$result['success'] ? Logger::LEVEL_INFO : Logger::LEVEL_ERROR,
			$repo->id
		);

		if ( ! $result['success'] ) {
			$this->update_status( $repo->id, 'error', $installed, '', $result['message'] ?? '' );

			return array(
				'success' => false,
				'message' => $result['message'] ?? __( 'Update check failed.', 'repo-update' ),
			);
		}

		$remote            = (string) $result['version'];
		$update_available  = version_compare( $installed, $remote, '<' );
		$status            = $update_available ? 'update_available' : 'up_to_date';

		$this->store->update_status(
			$repo->id,
			array(
				'last_checked'      => current_time( 'mysql' ),
				'installed_version' => $installed,
				'remote_version'    => $remote,
				'status'            => $status,
			)
		);

		if ( $update_available && $repo->notifications ) {
			set_transient( 'repo_update_notice_' . $repo->id, $remote, DAY_IN_SECONDS );
		}

		return array(
			'success'          => true,
			'message'          => $update_available
				? __( 'Update available.', 'repo-update' )
				: __( 'Up to date.', 'repo-update' ),
			'update_available' => $update_available,
		);
	}

	/**
	 * Determine whether a repository check is due.
	 *
	 * @param Repository $repo          Repository entity.
	 * @param int        $global_hours  Global interval in hours.
	 */
	public function is_due( Repository $repo, int $global_hours ): bool {
		$hours = $repo->check_interval > 0 ? $repo->check_interval : $global_hours;
		$hours = max( 1, $hours );

		if ( empty( $repo->last_checked ) ) {
			return true;
		}

		$last = strtotime( $repo->last_checked );

		return false === $last || ( time() - $last ) >= ( $hours * HOUR_IN_SECONDS );
	}

	/**
	 * Update repository status after an error.
	 *
	 * @param int    $id        Repository ID.
	 * @param string $status    Status slug.
	 * @param string $installed Installed version.
	 * @param string $remote    Remote version.
	 * @param string $message   Error message for logs.
	 */
	private function update_status( int $id, string $status, string $installed, string $remote, string $message ): void {
		$this->store->update_status(
			$id,
			array(
				'last_checked'      => current_time( 'mysql' ),
				'installed_version' => $installed,
				'remote_version'    => $remote,
				'status'            => $status,
			)
		);

		if ( '' !== $message ) {
			$this->logger->log( 'update_check', $message, Logger::LEVEL_ERROR, $id );
		}
	}

	/**
	 * Mark repository as updated.
	 *
	 * @param int    $id      Repository ID.
	 * @param string $version Installed version.
	 */
	public function mark_updated( int $id, string $version ): void {
		$this->store->update_status(
			$id,
			array(
				'last_updated'      => current_time( 'mysql' ),
				'installed_version' => $version,
				'remote_version'    => $version,
				'status'            => 'up_to_date',
			)
		);
	}
}
