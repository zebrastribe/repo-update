<?php
/**
 * Dashboard admin page.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Admin;

use RepoUpdate\Admin\ListTables\DashboardListTable;
use RepoUpdate\Helpers\Capabilities;
use RepoUpdate\Repository\RepositoryManager;
use RepoUpdate\Rollback\RollbackManager;
use RepoUpdate\Settings\Settings;

/**
 * Main dashboard with repository overview.
 */
final class DashboardPage {

	/**
	 * @var RepositoryManager
	 */
	private RepositoryManager $repositories;

	/**
	 * @var RollbackManager
	 */
	private RollbackManager $rollback;

	/**
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * @param RepositoryManager $repositories Repository manager.
	 * @param RollbackManager   $rollback     Rollback manager.
	 * @param Settings          $settings     Settings.
	 */
	public function __construct( RepositoryManager $repositories, RollbackManager $rollback, Settings $settings ) {
		$this->repositories = $repositories;
		$this->rollback     = $rollback;
		$this->settings     = $settings;
	}

	/**
	 * Render dashboard page.
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'repo-update' ) );
		}

		$this->handle_actions();

		$table = new DashboardListTable( $this->repositories, $this->rollback );
		$table->prepare_items();

		?>
		<div class="wrap repo-update-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Repo Update Dashboard', 'repo-update' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=repo-update-repositories&action=add' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add Repository', 'repo-update' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php settings_errors( 'repo_update' ); ?>

			<?php $table->display(); ?>
		</div>
		<?php
	}

	/**
	 * Handle dashboard actions.
	 */
	private function handle_actions(): void {
		if ( empty( $_GET['repo_update_action'] ) || empty( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['repo_update_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id     = (int) $_GET['id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'repo_update_action_' . $id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! Capabilities::can_manage() ) {
			return;
		}

		$repo = $this->repositories->find( $id );

		if ( ! $repo ) {
			return;
		}

		switch ( $action ) {
			case 'check':
				if ( ! Capabilities::can_check_updates() ) {
					return;
				}

				$result = $this->repositories->check_repository( $repo );
				$this->add_notice( $result['message'], $result['success'] ? 'success' : 'error' );
				delete_site_transient( 'update_plugins' );
				delete_site_transient( 'update_themes' );
				break;

			case 'toggle':
				$this->repositories->toggle_enabled( $id );
				$this->add_notice( __( 'Repository status updated.', 'repo-update' ), 'success' );
				break;

			case 'rollback':
				if ( ! Capabilities::can_modify_target( $repo->type ) ) {
					$this->add_notice( __( 'You do not have permission to rollback this target.', 'repo-update' ), 'error' );
					return;
				}

				$result = $this->rollback->restore_backup( $repo );
				$this->add_notice( $result['message'], $result['success'] ? 'success' : 'error' );
				break;

			case 'delete_backup':
				if ( ! Capabilities::can_delete_backup( $repo->type ) ) {
					$this->add_notice( __( 'You do not have permission to delete this backup.', 'repo-update' ), 'error' );
					return;
				}

				$this->rollback->delete_backup( $repo->type, $repo->target_slug );
				$this->add_notice( __( 'Rollback backup deleted.', 'repo-update' ), 'success' );
				break;
		}
	}

	/**
	 * Add admin notice.
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type.
	 */
	private function add_notice( string $message, string $type ): void {
		add_settings_error( 'repo_update', 'repo_update_notice', $message, $type );
	}
}
