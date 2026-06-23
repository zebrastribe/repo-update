<?php
/**
 * Admin AJAX handlers.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Admin;

use RepoUpdate\API\GitHubClient;
use RepoUpdate\Helpers\Capabilities;
use RepoUpdate\Logger\Logger;
use RepoUpdate\Repository\RepositoryManager;
use RepoUpdate\Rollback\RollbackManager;

/**
 * Handles AJAX requests from admin UI.
 */
final class AjaxHandler {

	/**
	 * @var RepositoryManager
	 */
	private RepositoryManager $repositories;

	/**
	 * @var GitHubClient
	 */
	private GitHubClient $github;

	/**
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * @var RollbackManager
	 */
	private RollbackManager $rollback;

	/**
	 * @param RepositoryManager $repositories Repository manager.
	 * @param GitHubClient      $github       GitHub client.
	 * @param Logger            $logger       Logger.
	 * @param RollbackManager   $rollback     Rollback manager.
	 */
	public function __construct(
		RepositoryManager $repositories,
		GitHubClient $github,
		Logger $logger,
		RollbackManager $rollback
	) {
		$this->repositories = $repositories;
		$this->github       = $github;
		$this->logger       = $logger;
		$this->rollback     = $rollback;
	}

	/**
	 * Register AJAX actions.
	 */
	public function register(): void {
		add_action( 'wp_ajax_repo_update_fetch_branches', array( $this, 'fetch_branches' ) );
		add_action( 'wp_ajax_repo_update_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_repo_update_delete_backup', array( $this, 'delete_backup' ) );
	}

	/**
	 * Fetch branches from GitHub.
	 */
	public function fetch_branches(): void {
		$this->authorize();

		$owner = sanitize_text_field( wp_unslash( $_POST['owner'] ?? '' ) );
		$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		$id    = (int) ( $_POST['id'] ?? 0 );

		if ( '' === $token && $id > 0 ) {
			$repo  = $this->repositories->find( $id );
			$token = $repo ? $repo->get_token() : '';
		}

		$result = $this->github->get_branches( $owner, $name, $token );

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ?? __( 'Failed to fetch branches.', 'repo-update' ) ) );
		}

		wp_send_json_success( array( 'branches' => $result['branches'] ?? array() ) );
	}

	/**
	 * Test GitHub connection.
	 */
	public function test_connection(): void {
		$this->authorize();

		$owner = sanitize_text_field( wp_unslash( $_POST['owner'] ?? '' ) );
		$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		$id    = (int) ( $_POST['id'] ?? 0 );

		if ( '' === $token && $id > 0 ) {
			$repo  = $this->repositories->find( $id );
			$token = $repo ? $repo->get_token() : '';
		}

		$result = $this->github->test_connection( $owner, $name, $token );

		if ( ! $result['success'] ) {
			$this->logger->log( 'github_error', $result['message'], Logger::LEVEL_ERROR );
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}

		wp_send_json_success( array( 'message' => $result['message'] ) );
	}

	/**
	 * Delete rollback backup for a repository.
	 */
	public function delete_backup(): void {
		$this->authorize();

		$id   = (int) ( $_POST['id'] ?? 0 );
		$repo = $this->repositories->find( $id );

		if ( ! $repo ) {
			wp_send_json_error( array( 'message' => __( 'Repository not found.', 'repo-update' ) ) );
		}

		if ( ! Capabilities::can_delete_backup( $repo->type ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'repo-update' ) ), 403 );
		}

		$this->rollback->delete_backup( $repo->type, $repo->target_slug );
		$this->logger->log( 'rollback_delete', __( 'Rollback backup deleted.', 'repo-update' ), Logger::LEVEL_INFO, $repo->id );

		wp_send_json_success( array( 'message' => __( 'Rollback backup deleted.', 'repo-update' ) ) );
	}

	/**
	 * Verify AJAX permissions.
	 */
	private function authorize(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'repo-update' ) ), 403 );
		}

		check_ajax_referer( 'repo_update_admin', 'nonce' );
	}
}
