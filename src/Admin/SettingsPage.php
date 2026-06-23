<?php
/**
 * Settings admin page.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Admin;

use RepoUpdate\CronScheduler;
use RepoUpdate\Helpers\Capabilities;
use RepoUpdate\Logger\Logger;
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
	 * @param Settings $settings Plugin settings.
	 * @param Logger   $logger   Logger.
	 */
	public function __construct( Settings $settings, Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Register Settings API fields.
	 */
	public function register(): void {
		register_setting(
			'repo_update',
			Settings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->settings, 'sanitize' ),
				'default'           => $this->settings->all(),
			)
		);
	}

	/**
	 * Render settings page.
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'repo-update' ) );
		}

		$this->handle_clear_logs();

		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			CronScheduler::schedule();
			add_settings_error( 'repo_update', 'repo_update_saved', __( 'Settings saved.', 'repo-update' ), 'success' );
		}

		$values = $this->settings->all();
		?>
		<div class="wrap repo-update-wrap">
			<h1><?php esc_html_e( 'Repo Update Settings', 'repo-update' ); ?></h1>
			<?php settings_errors( 'repo_update' ); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'repo_update' );
				?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="check_interval"><?php esc_html_e( 'Default Update Check Interval (hours)', 'repo-update' ); ?></label></th>
						<td><input name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[check_interval]" id="check_interval" type="number" min="1" value="<?php echo esc_attr( (string) $values['check_interval'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="github_timeout"><?php esc_html_e( 'GitHub Request Timeout (seconds)', 'repo-update' ); ?></label></th>
						<td><input name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[github_timeout]" id="github_timeout" type="number" min="5" value="<?php echo esc_attr( (string) $values['github_timeout'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="log_retention_days"><?php esc_html_e( 'Log Retention (days)', 'repo-update' ); ?></label></th>
						<td><input name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[log_retention_days]" id="log_retention_days" type="number" min="1" value="<?php echo esc_attr( (string) $values['log_retention_days'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Logging', 'repo-update' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[logging_enabled]" value="1" <?php checked( ! empty( $values['logging_enabled'] ) ); ?>> <?php esc_html_e( 'Enable logging', 'repo-update' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Uninstall', 'repo-update' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[delete_on_uninstall]" value="1" <?php checked( ! empty( $values['delete_on_uninstall'] ) ); ?>> <?php esc_html_e( 'Delete settings, logs, and rollback copies on uninstall', 'repo-update' ); ?></label></td>
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
	 * Handle log clearing.
	 */
	private function handle_clear_logs(): void {
		if ( empty( $_POST['repo_update_clear_logs'] ) ) {
			return;
		}

		if ( ! isset( $_POST['repo_update_clear_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['repo_update_clear_nonce'] ) ), 'repo_update_clear_logs' ) || ! Capabilities::can_manage() ) {
			return;
		}

		$this->logger->clear();
		add_settings_error( 'repo_update', 'repo_update_cleared', __( 'Logs cleared.', 'repo-update' ), 'success' );
	}
}
