<?php
/**
 * Settings admin page.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Admin;

use RepoUpdate\CronScheduler;
use RepoUpdate\Logger\Logger;
use RepoUpdate\Repository\RepositoryStore;
use RepoUpdate\Rollback\RollbackManager;
use RepoUpdate\Settings\Settings;

/**
 * Global plugin settings UI.
 */
final class SettingsPage {

	/**
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * @var RepositoryStore
	 */
	private RepositoryStore $store;

	/**
	 * @param Settings        $settings Plugin settings.
	 * @param Logger          $logger   Logger.
	 * @param RepositoryStore $store    Repository store.
	 */
	public function __construct( Settings $settings, Logger $logger, RepositoryStore $store ) {
		$this->settings = $settings;
		$this->logger   = $logger;
		$this->store    = $store;
	}

	/**
	 * Render settings page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'repo-update' ) );
		}

		$this->handle_save();
		$values = $this->settings->all();
		?>
		<div class="wrap repo-update-wrap">
			<h1><?php esc_html_e( 'Repo Update Settings', 'repo-update' ); ?></h1>
			<?php settings_errors( 'repo_update' ); ?>
			<form method="post">
				<?php wp_nonce_field( 'repo_update_save_settings', 'repo_update_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="check_interval"><?php esc_html_e( 'Default Update Check Interval (hours)', 'repo-update' ); ?></label></th>
						<td><input name="check_interval" id="check_interval" type="number" min="1" value="<?php echo esc_attr( (string) $values['check_interval'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="github_timeout"><?php esc_html_e( 'GitHub Request Timeout (seconds)', 'repo-update' ); ?></label></th>
						<td><input name="github_timeout" id="github_timeout" type="number" min="5" value="<?php echo esc_attr( (string) $values['github_timeout'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Logging', 'repo-update' ); ?></th>
						<td><label><input type="checkbox" name="logging_enabled" value="1" <?php checked( ! empty( $values['logging_enabled'] ) ); ?>> <?php esc_html_e( 'Enable logging', 'repo-update' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Uninstall', 'repo-update' ); ?></th>
						<td><label><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( ! empty( $values['delete_on_uninstall'] ) ); ?>> <?php esc_html_e( 'Delete settings, logs, and rollback copies on uninstall', 'repo-update' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Settings', 'repo-update' ) ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Maintenance', 'repo-update' ); ?></h2>
			<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Clear all logs?', 'repo-update' ) ); ?>');">
				<?php wp_nonce_field( 'repo_update_clear_logs', 'repo_update_clear_nonce' ); ?>
				<input type="hidden" name="repo_update_clear_logs" value="1">
				<?php submit_button( __( 'Clear Logs', 'repo-update' ), 'delete' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle settings save and maintenance actions.
	 */
	private function handle_save(): void {
		if ( ! empty( $_POST['repo_update_clear_logs'] ) ) {
			if ( isset( $_POST['repo_update_clear_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['repo_update_clear_nonce'] ) ), 'repo_update_clear_logs' ) && current_user_can( 'manage_options' ) ) {
				$this->logger->clear();
				add_settings_error( 'repo_update', 'repo_update_cleared', __( 'Logs cleared.', 'repo-update' ), 'success' );
			}
			return;
		}

		if ( empty( $_POST['repo_update_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['repo_update_nonce'] ) ), 'repo_update_save_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->settings->update(
			array(
				'check_interval'      => (int) ( $_POST['check_interval'] ?? 12 ),
				'github_timeout'    => (int) ( $_POST['github_timeout'] ?? 30 ),
				'logging_enabled'   => ! empty( $_POST['logging_enabled'] ),
				'delete_on_uninstall' => ! empty( $_POST['delete_on_uninstall'] ),
			)
		);

		CronScheduler::schedule( $this->settings->get_check_interval() );
		add_settings_error( 'repo_update', 'repo_update_saved', __( 'Settings saved.', 'repo-update' ), 'success' );
	}
}
