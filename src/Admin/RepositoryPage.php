<?php
/**
 * Repository management admin page.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Admin;

use RepoUpdate\API\GitHubClient;
use RepoUpdate\Admin\ListTables\RepositoriesListTable;
use RepoUpdate\Helpers\Capabilities;
use RepoUpdate\Helpers\Encryption;
use RepoUpdate\Helpers\SlugHelper;
use RepoUpdate\Repository\Repository;
use RepoUpdate\Repository\RepositoryManager;
use RepoUpdate\Settings\Settings;

/**
 * CRUD UI for GitHub repositories.
 */
final class RepositoryPage {

	/**
	 * @var RepositoryManager
	 */
	private RepositoryManager $repositories;

	/**
	 * @var GitHubClient
	 */
	private GitHubClient $github;

	/**
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * @param RepositoryManager $repositories Repository manager.
	 * @param GitHubClient      $github       GitHub client.
	 * @param Settings          $settings     Settings.
	 */
	public function __construct( RepositoryManager $repositories, GitHubClient $github, Settings $settings ) {
		$this->repositories = $repositories;
		$this->github       = $github;
		$this->settings     = $settings;
	}

	/**
	 * Render repository page.
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'repo-update' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'add' === $action || 'edit' === $action ) {
			$this->handle_save();
			$this->render_form( $action );
			return;
		}

		$this->handle_delete();

		if ( ! empty( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error( 'repo_update', 'repo_update_saved', __( 'Repository saved.', 'repo-update' ), 'success' );
		}

		if ( ! empty( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error( 'repo_update', 'repo_update_deleted', __( 'Repository deleted.', 'repo-update' ), 'success' );
		}

		$this->render_list();
	}

	/**
	 * Render repository list.
	 */
	private function render_list(): void {
		$table = new RepositoriesListTable( $this->repositories );
		$table->prepare_items();
		?>
		<div class="wrap repo-update-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Repositories', 'repo-update' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=repo-update-repositories&action=add' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'repo-update' ); ?>
			</a>
			<hr class="wp-header-end">
			<?php settings_errors( 'repo_update' ); ?>
			<?php $table->display(); ?>
		</div>
		<?php
	}

	/**
	 * Render add/edit form.
	 *
	 * @param string $action add|edit.
	 */
	private function render_form( string $action ): void {
		$id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$repo = $id ? $this->repositories->find( $id ) : null;

		if ( 'edit' === $action && ! $repo ) {
			wp_die( esc_html__( 'Repository not found.', 'repo-update' ) );
		}

		$values = $repo ? get_object_vars( $repo ) : array(
			'id'               => 0,
			'owner'            => '',
			'name'             => '',
			'type'             => 'plugin',
			'branch'           => 'main',
			'check_interval'   => 0,
			'notifications'    => true,
			'rollback_enabled' => true,
			'enabled'          => true,
			'target_slug'      => '',
			'plugin_file'      => '',
			'notes'            => '',
		);

		$plugins = SlugHelper::get_installed_plugins();
		$themes  = SlugHelper::get_installed_themes();
		?>
		<div class="wrap repo-update-wrap">
			<h1><?php echo 'edit' === $action ? esc_html__( 'Edit Repository', 'repo-update' ) : esc_html__( 'Add Repository', 'repo-update' ); ?></h1>
			<?php settings_errors( 'repo_update' ); ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'repo_update_save_repository', 'repo_update_nonce' ); ?>
				<input type="hidden" name="repo_update_form" value="1">
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) $values['id'] ); ?>">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="owner"><?php esc_html_e( 'Repository Owner', 'repo-update' ); ?></label></th>
						<td><input name="owner" id="owner" type="text" class="regular-text" value="<?php echo esc_attr( (string) $values['owner'] ); ?>" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="name"><?php esc_html_e( 'Repository Name', 'repo-update' ); ?></label></th>
						<td><input name="name" id="name" type="text" class="regular-text" value="<?php echo esc_attr( (string) $values['name'] ); ?>" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="token"><?php esc_html_e( 'Personal Access Token', 'repo-update' ); ?></label></th>
						<td>
							<input name="token" id="token" type="password" class="regular-text" value="" autocomplete="new-password">
							<p class="description">
								<?php
								if ( $repo && $repo->get_token() ) {
									printf(
										/* translators: %s: masked token */
										esc_html__( 'Current token: %s. Leave blank to keep existing.', 'repo-update' ),
										esc_html( Encryption::mask_token( $repo->get_token() ) )
									);
								} else {
									esc_html_e( 'Optional for public repositories. Required for private repos. Stored encrypted.', 'repo-update' );
								}
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Repository Type', 'repo-update' ); ?></th>
						<td>
							<label><input type="radio" name="type" value="plugin" <?php checked( $values['type'], 'plugin' ); ?>> <?php esc_html_e( 'Plugin', 'repo-update' ); ?></label><br>
							<label><input type="radio" name="type" value="theme" <?php checked( $values['type'], 'theme' ); ?>> <?php esc_html_e( 'Theme', 'repo-update' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="target_slug"><?php esc_html_e( 'Target', 'repo-update' ); ?></label></th>
						<td>
							<select name="target_slug" id="target_slug" class="regular-text">
								<optgroup label="<?php esc_attr_e( 'Plugins', 'repo-update' ); ?>" data-type="plugin">
									<?php foreach ( $plugins as $file => $label ) : ?>
										<option value="<?php echo esc_attr( $file ); ?>" data-type="plugin" <?php selected( $values['target_slug'], $file ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</optgroup>
								<optgroup label="<?php esc_attr_e( 'Themes', 'repo-update' ); ?>" data-type="theme">
									<?php foreach ( $themes as $slug => $label ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>" data-type="theme" <?php selected( $values['target_slug'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							</select>
							<p class="description"><?php esc_html_e( 'Installed plugin or theme to update.', 'repo-update' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="branch"><?php esc_html_e( 'Branch', 'repo-update' ); ?></label></th>
						<td>
							<select name="branch" id="branch" class="regular-text">
								<?php if ( ! empty( $values['branch'] ) ) : ?>
									<option value="<?php echo esc_attr( (string) $values['branch'] ); ?>" selected><?php echo esc_html( (string) $values['branch'] ); ?></option>
								<?php endif; ?>
							</select>
							<button type="button" class="button" id="repo-update-fetch-branches"><?php esc_html_e( 'Fetch Branches', 'repo-update' ); ?></button>
							<p class="description"><?php esc_html_e( 'Fetch branches from GitHub after entering owner, name, and token.', 'repo-update' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="check_interval"><?php esc_html_e( 'Update Check Interval (hours)', 'repo-update' ); ?></label></th>
						<td>
							<input name="check_interval" id="check_interval" type="number" min="0" step="1" value="<?php echo esc_attr( (string) $values['check_interval'] ); ?>">
							<p class="description"><?php esc_html_e( '0 uses the global default from Settings.', 'repo-update' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'repo-update' ); ?></th>
						<td>
							<label><input type="checkbox" name="notifications" value="1" <?php checked( ! empty( $values['notifications'] ) ); ?>> <?php esc_html_e( 'Enable update notifications', 'repo-update' ); ?></label><br>
							<label><input type="checkbox" name="rollback_enabled" value="1" <?php checked( ! empty( $values['rollback_enabled'] ) ); ?>> <?php esc_html_e( 'Enable rollback', 'repo-update' ); ?></label><br>
							<label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $values['enabled'] ) ); ?>> <?php esc_html_e( 'Enable repository', 'repo-update' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="notes"><?php esc_html_e( 'Notes', 'repo-update' ); ?></label></th>
						<td><textarea name="notes" id="notes" class="large-text" rows="4"><?php echo esc_textarea( (string) $values['notes'] ); ?></textarea></td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" class="button" id="repo-update-test-connection"><?php esc_html_e( 'Test Connection', 'repo-update' ); ?></button>
					<?php submit_button( __( 'Save Repository', 'repo-update' ), 'primary', 'submit', false ); ?>
				</p>
				<div id="repo-update-form-message"></div>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle form save.
	 */
	private function handle_save(): void {
		if ( empty( $_POST['repo_update_form'] ) ) {
			return;
		}

		if ( ! isset( $_POST['repo_update_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['repo_update_nonce'] ) ), 'repo_update_save_repository' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'repo-update' ) );
		}

		if ( ! Capabilities::can_manage() ) {
			return;
		}

		$type        = sanitize_key( wp_unslash( $_POST['type'] ?? 'plugin' ) );
		$target_slug = sanitize_text_field( wp_unslash( $_POST['target_slug'] ?? '' ) );
		$plugin_file = 'plugin' === $type ? $target_slug : '';

		if ( ! $this->repositories->validate_target( $type, $target_slug ) ) {
			add_settings_error( 'repo_update', 'repo_update_error', __( 'Selected target does not match the repository type.', 'repo-update' ), 'error' );
			return;
		}

		$data = array(
			'id'               => (int) ( $_POST['id'] ?? 0 ),
			'owner'            => sanitize_text_field( wp_unslash( $_POST['owner'] ?? '' ) ),
			'name'             => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'token'            => sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) ),
			'type'             => $type,
			'branch'           => sanitize_text_field( wp_unslash( $_POST['branch'] ?? 'main' ) ),
			'check_interval'   => (int) ( $_POST['check_interval'] ?? 0 ),
			'notifications'    => ! empty( $_POST['notifications'] ),
			'rollback_enabled' => ! empty( $_POST['rollback_enabled'] ),
			'enabled'          => ! empty( $_POST['enabled'] ),
			'target_slug'      => $target_slug,
			'plugin_file'      => $plugin_file,
			'notes'            => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
			'status'           => 'unknown',
		);

		if ( 0 === $data['id'] && '' === $data['token'] ) {
			$test = $this->github->test_connection( $data['owner'], $data['name'], '' );
			if ( ! $test['success'] ) {
				add_settings_error( 'repo_update', 'repo_update_error', __( 'A personal access token is required for this repository.', 'repo-update' ), 'error' );
				return;
			}
		}

		$id = $this->repositories->save( $data );

		if ( $id > 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=repo-update-repositories&saved=1' ) );
			exit;
		}

		add_settings_error( 'repo_update', 'repo_update_error', __( 'Failed to save repository.', 'repo-update' ), 'error' );
	}

	/**
	 * Handle delete action.
	 */
	private function handle_delete(): void {
		if ( empty( $_GET['action'] ) || 'delete' !== $_GET['action'] || empty( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$id = (int) $_GET['id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'repo_update_delete_' . $id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! Capabilities::can_manage() ) {
			return;
		}

		$this->repositories->delete( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=repo-update-repositories&deleted=1' ) );
		exit;
	}
}
