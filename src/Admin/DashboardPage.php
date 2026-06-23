<?php
/**
 * Dashboard admin page.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Admin;

use RepoUpdate\Helpers\SlugHelper;
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
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * @param RepositoryManager $repositories Repository manager.
	 * @param Settings          $settings     Settings.
	 */
	public function __construct( RepositoryManager $repositories, Settings $settings ) {
		$this->repositories = $repositories;
		$this->settings     = $settings;
	}

	/**
	 * Render dashboard page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'repo-update' ) );
		}

		$this->handle_actions();
		$repos = $this->repositories->all();

		?>
		<div class="wrap repo-update-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Repo Update Dashboard', 'repo-update' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=repo-update-repositories&action=add' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add Repository', 'repo-update' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php settings_errors( 'repo_update' ); ?>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Repository', 'repo-update' ); ?></th>
						<th><?php esc_html_e( 'Type', 'repo-update' ); ?></th>
						<th><?php esc_html_e( 'Branch', 'repo-update' ); ?></th>
						<th><?php esc_html_e( 'Installed', 'repo-update' ); ?></th>
						<th><?php esc_html_e( 'Remote', 'repo-update' ); ?></th>
						<th><?php esc_html_e( 'Status', 'repo-update' ); ?></th>
						<th><?php esc_html_e( 'Last Checked', 'repo-update' ); ?></th>
						<th><?php esc_html_e( 'Last Updated', 'repo-update' ); ?></th>
						<th><?php esc_html_e( 'Rollback', 'repo-update' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'repo-update' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $repos ) ) : ?>
						<tr>
							<td colspan="10"><?php esc_html_e( 'No repositories configured yet.', 'repo-update' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $repos as $repo ) : ?>
							<?php
							$installed = SlugHelper::get_installed_version( $repo->type, $repo->target_slug );
							$rollback  = ( new RollbackManager( new \RepoUpdate\Logger\Logger( $this->settings ) ) )
								->has_backup( $repo->type, $repo->target_slug );
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $repo->full_name() ); ?></strong><br>
									<span class="description"><?php echo esc_html( $repo->target_slug ); ?></span>
								</td>
								<td><?php echo esc_html( ucfirst( $repo->type ) ); ?></td>
								<td><?php echo esc_html( $repo->branch ); ?></td>
								<td><?php echo esc_html( $installed ?: '—' ); ?></td>
								<td><?php echo esc_html( $repo->remote_version ?: '—' ); ?></td>
								<td><?php echo esc_html( $this->format_status( $repo->status ) ); ?></td>
								<td><?php echo esc_html( $repo->last_checked ?: '—' ); ?></td>
								<td><?php echo esc_html( $repo->last_updated ?: '—' ); ?></td>
								<td><?php echo $rollback ? esc_html__( 'Yes', 'repo-update' ) : esc_html__( 'No', 'repo-update' ); ?></td>
								<td class="repo-update-actions">
									<a href="<?php echo esc_url( $this->action_url( 'check', $repo->id ) ); ?>"><?php esc_html_e( 'Check now', 'repo-update' ); ?></a> |
									<?php if ( 'update_available' === $repo->status ) : ?>
										<a href="<?php echo esc_url( $this->update_url( $repo ) ); ?>"><?php esc_html_e( 'Update', 'repo-update' ); ?></a> |
									<?php endif; ?>
									<?php if ( $rollback ) : ?>
										<a href="<?php echo esc_url( $this->action_url( 'rollback', $repo->id ) ); ?>" class="repo-update-confirm" data-message="<?php esc_attr_e( 'Restore previous version?', 'repo-update' ); ?>"><?php esc_html_e( 'Rollback', 'repo-update' ); ?></a> |
									<?php endif; ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=repo-update-repositories&action=edit&id=' . $repo->id ) ); ?>"><?php esc_html_e( 'Edit', 'repo-update' ); ?></a> |
									<a href="<?php echo esc_url( $this->action_url( 'toggle', $repo->id ) ); ?>"><?php echo $repo->enabled ? esc_html__( 'Disable', 'repo-update' ) : esc_html__( 'Enable', 'repo-update' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
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

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$repo = $this->repositories->find( $id );

		if ( ! $repo ) {
			return;
		}

		switch ( $action ) {
			case 'check':
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
				$rollback = new RollbackManager( new \RepoUpdate\Logger\Logger( $this->settings ) );
				$result   = $rollback->restore_backup( $repo );
				$this->add_notice( $result['message'], $result['success'] ? 'success' : 'error' );
				break;
		}
	}

	/**
	 * Build action URL.
	 *
	 * @param string $action Action name.
	 * @param int    $id     Repository ID.
	 */
	private function action_url( string $action, int $id ): string {
		return wp_nonce_url(
			admin_url( 'admin.php?page=repo-update&repo_update_action=' . $action . '&id=' . $id ),
			'repo_update_action_' . $id
		);
	}

	/**
	 * Build native updater URL.
	 *
	 * @param \RepoUpdate\Repository\Repository $repo Repository.
	 */
	private function update_url( $repo ): string {
		if ( 'theme' === $repo->type ) {
			return admin_url( 'themes.php' );
		}

		return admin_url( 'plugins.php' );
	}

	/**
	 * Format status label.
	 *
	 * @param string $status Status slug.
	 */
	private function format_status( string $status ): string {
		$labels = array(
			'up_to_date'       => __( 'Up to date', 'repo-update' ),
			'update_available' => __( 'Update available', 'repo-update' ),
			'error'            => __( 'Error', 'repo-update' ),
			'not_installed'    => __( 'Not installed', 'repo-update' ),
			'unknown'          => __( 'Unknown', 'repo-update' ),
			'disabled'         => __( 'Disabled', 'repo-update' ),
		);

		return $labels[ $status ] ?? ucfirst( $status );
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
