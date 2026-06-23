<?php
/**
 * WordPress updater integration coordinator.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Updater;

use RepoUpdate\API\GitHubClient;
use RepoUpdate\Helpers\SlugHelper;
use RepoUpdate\Logger\Logger;
use RepoUpdate\Repository\Repository;
use RepoUpdate\Repository\RepositoryManager;
use RepoUpdate\Rollback\RollbackManager;
use RepoUpdate\Settings\Settings;

/**
 * Registers all updater-related WordPress hooks.
 */
final class UpdaterCoordinator {

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
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Repository currently being upgraded.
	 *
	 * @var Repository|null
	 */
	private ?Repository $upgrading_repo = null;

	/**
	 * Active package URL for authorized download.
	 *
	 * @var string
	 */
	private string $active_package_url = '';

	/**
	 * Active token for authorized download.
	 *
	 * @var string
	 */
	private string $active_download_token = '';

	/**
	 * @param RepositoryManager $repositories Repository manager.
	 * @param GitHubClient      $github       GitHub client.
	 * @param Logger            $logger       Logger.
	 * @param RollbackManager   $rollback     Rollback manager.
	 * @param Settings          $settings     Settings.
	 */
	public function __construct(
		RepositoryManager $repositories,
		GitHubClient $github,
		Logger $logger,
		RollbackManager $rollback,
		Settings $settings
	) {
		$this->repositories = $repositories;
		$this->github       = $github;
		$this->logger       = $logger;
		$this->rollback     = $rollback;
		$this->settings     = $settings;
	}

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'filter_plugin_updates' ) );
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'filter_theme_updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
		add_filter( 'themes_api', array( $this, 'theme_information' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'before_package_download' ), 10, 4 );
		add_filter( 'http_request_args', array( $this, 'authorize_package_download' ), 10, 2 );
		add_filter( 'upgrader_pre_install', array( $this, 'prepare_backup' ), 10, 2 );
		add_filter( 'upgrader_post_install', array( $this, 'fix_package_directory' ), 10, 3 );
		add_filter( 'upgrader_install_package_result', array( $this, 'ensure_install_destination' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'after_upgrade' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'update_notices' ) );
	}

	/**
	 * Inject plugin update data from cached repository state.
	 *
	 * @param object|false $transient Update transient.
	 * @return object|false
	 */
	public function filter_plugin_updates( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}

		foreach ( $this->repositories->enabled() as $repo ) {
			if ( 'plugin' !== $repo->type ) {
				continue;
			}

			$key = $repo->plugin_key();

			if ( ! isset( $transient->checked[ $key ] ) ) {
				continue;
			}

			$item = $this->build_plugin_update_item( $repo, $key );

			if ( null === $item ) {
				continue;
			}

			if ( $item['update_available'] ) {
				$transient->response[ $key ] = $item['data'];
			} else {
				$transient->no_update[ $key ] = $item['data'];
			}
		}

		return $transient;
	}

	/**
	 * Inject theme update data from cached repository state.
	 *
	 * @param object|false $transient Update transient.
	 * @return object|false
	 */
	public function filter_theme_updates( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}

		foreach ( $this->repositories->enabled() as $repo ) {
			if ( 'theme' !== $repo->type ) {
				continue;
			}

			$slug = $repo->target_slug;

			if ( ! isset( $transient->checked[ $slug ] ) ) {
				continue;
			}

			$item = $this->build_theme_update_item( $repo, $slug );

			if ( null === $item ) {
				continue;
			}

			if ( $item['update_available'] ) {
				$transient->response[ $slug ] = $item['data'];
			} else {
				$transient->no_update[ $slug ] = $item['data'];
			}
		}

		return $transient;
	}

	/**
	 * Build plugin update transient item.
	 *
	 * @param Repository $repo Repository.
	 * @param string     $key  Plugin key.
	 * @return array{update_available: bool, data: object}|null
	 */
	private function build_plugin_update_item( Repository $repo, string $key ): ?array {
		$installed = SlugHelper::get_installed_version( 'plugin', $repo->target_slug );

		if ( '' === $installed ) {
			return null;
		}

		$remote           = $repo->remote_version ?: $installed;
		$update_available = version_compare( $installed, $remote, '<' );

		return array(
			'update_available' => $update_available,
			'data'             => (object) array(
				'id'            => $key,
				'slug'          => dirname( $key ) === '.' ? basename( $key, '.php' ) : dirname( $key ),
				'plugin'        => $key,
				'new_version'   => $remote,
				'url'           => 'https://github.com/' . $repo->owner . '/' . $repo->name,
				'package'       => $this->github->get_package_url( $repo->owner, $repo->name, $repo->branch ),
				'icons'         => array(),
				'banners'       => array(),
				'banners_rtl'   => array(),
				'tested'        => '',
				'requires'      => '',
				'requires_php'  => '',
				'compatibility' => new \stdClass(),
			),
		);
	}

	/**
	 * Build theme update transient item.
	 *
	 * @param Repository $repo Repository.
	 * @param string     $slug Theme slug.
	 * @return array{update_available: bool, data: array<string, string>}|null
	 */
	private function build_theme_update_item( Repository $repo, string $slug ): ?array {
		$installed = SlugHelper::get_installed_version( 'theme', $slug );

		if ( '' === $installed ) {
			return null;
		}

		$remote           = $repo->remote_version ?: $installed;
		$update_available = version_compare( $installed, $remote, '<' );

		return array(
			'update_available' => $update_available,
			'data'             => array(
				'theme'       => $slug,
				'new_version' => $remote,
				'url'         => 'https://github.com/' . $repo->owner . '/' . $repo->name,
				'package'     => $this->github->get_package_url( $repo->owner, $repo->name, $repo->branch ),
				'requires'    => '',
				'requires_php'=> '',
			),
		);
	}

	/**
	 * Provide plugin details modal data.
	 *
	 * @param false|object|array $result Plugin info.
	 * @param string             $action API action.
	 * @param object             $args   Request args.
	 * @return false|object|array
	 */
	public function plugin_information( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}

		foreach ( $this->repositories->enabled() as $repo ) {
			if ( 'plugin' !== $repo->type ) {
				continue;
			}

			$slug = dirname( $repo->plugin_key() );
			$slug = ( '.' === $slug ) ? basename( $repo->plugin_key(), '.php' ) : $slug;

			if ( $slug !== $args->slug ) {
				continue;
			}

			return (object) array(
				'name'          => $repo->full_name(),
				'slug'          => $slug,
				'version'       => $repo->remote_version,
				'author'        => $repo->owner,
				'homepage'      => 'https://github.com/' . $repo->owner . '/' . $repo->name,
				'download_link' => $this->github->get_package_url( $repo->owner, $repo->name, $repo->branch ),
				'sections'      => array(
					'description' => $repo->notes ?: __( 'GitHub-hosted plugin.', 'repo-update' ),
					'changelog'   => sprintf(
						/* translators: 1: branch name, 2: version */
						__( 'Tracking branch %1$s at version %2$s.', 'repo-update' ),
						$repo->branch,
						$repo->remote_version
					),
				),
			);
		}

		return $result;
	}

	/**
	 * Provide theme details modal data.
	 *
	 * @param false|object|array $result Theme info.
	 * @param string             $action API action.
	 * @param object             $args   Request args.
	 * @return false|object|array
	 */
	public function theme_information( $result, string $action, $args ) {
		if ( 'theme_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}

		foreach ( $this->repositories->enabled() as $repo ) {
			if ( 'theme' !== $repo->type || $repo->target_slug !== $args->slug ) {
				continue;
			}

			return (object) array(
				'name'     => $repo->full_name(),
				'slug'     => $repo->target_slug,
				'version'  => $repo->remote_version,
				'author'   => $repo->owner,
				'homepage' => 'https://github.com/' . $repo->owner . '/' . $repo->name,
				'sections' => array(
					'description' => $repo->notes ?: __( 'GitHub-hosted theme.', 'repo-update' ),
				),
			);
		}

		return $result;
	}

	/**
	 * Prepare authorized download for a managed package.
	 *
	 * @param bool|\WP_Error $reply      Download response.
	 * @param string         $package    Package URL.
	 * @param \WP_Upgrader   $upgrader   Upgrader instance.
	 * @param array          $hook_extra Hook extra data.
	 * @return bool|\WP_Error
	 */
	public function before_package_download( $reply, string $package, $upgrader, array $hook_extra ) {
		$repo = $this->find_repo_from_hook_extra( $hook_extra );

		if ( ! $repo ) {
			return $reply;
		}

		$expected = $this->github->get_package_url( $repo->owner, $repo->name, $repo->branch );

		if ( $package !== $expected ) {
			return $reply;
		}

		$this->upgrading_repo         = $repo;
		$this->active_package_url     = $package;
		$this->active_download_token  = $repo->get_token();

		return $reply;
	}

	/**
	 * Add authorization header for the active package download only.
	 *
	 * @param array  $args Request args.
	 * @param string $url  Request URL.
	 * @return array
	 */
	public function authorize_package_download( array $args, string $url ): array {
		if ( '' === $this->active_package_url || $url !== $this->active_package_url ) {
			return $args;
		}

		if ( '' !== $this->active_download_token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->active_download_token;
		}

		return $args;
	}

	/**
	 * Create rollback backup before install.
	 *
	 * @param bool|\WP_Error $response   Install response.
	 * @param array          $hook_extra Hook extra data.
	 * @return bool|\WP_Error
	 */
	public function prepare_backup( $response, array $hook_extra ) {
		$repo = $this->upgrading_repo ?: $this->find_repo_from_hook_extra( $hook_extra );

		if ( ! $repo ) {
			return $response;
		}

		$this->upgrading_repo = $repo;
		$backup               = $this->rollback->create_backup( $repo );

		if ( ! $backup['success'] && $repo->rollback_enabled ) {
			$this->logger->log( 'rollback_create', $backup['message'], Logger::LEVEL_ERROR, $repo->id );

			return new \WP_Error( 'repo_update_backup_failed', $backup['message'] );
		}

		return $response;
	}

	/**
	 * Rename extracted GitHub archive folder to expected slug.
	 *
	 * @param bool  $response   Install response.
	 * @param array $hook_extra Hook extra data.
	 * @param array $result     Install result.
	 * @return bool|\WP_Error
	 */
	public function fix_package_directory( bool $response, array $hook_extra, array $result ) {
		if ( empty( $result['destination'] ) ) {
			return $response;
		}

		$repo = $this->upgrading_repo ?: $this->find_repo_from_hook_extra( $hook_extra );

		if ( ! $repo ) {
			return $response;
		}

		$expected    = basename( SlugHelper::get_install_path( $repo->type, $repo->target_slug ) );
		$current     = basename( $result['destination'] );
		$destination = $result['destination'];

		if ( $expected === $current ) {
			return $response;
		}

		$parent   = dirname( $destination );
		$new_dest = $parent . '/' . $expected;

		if ( is_dir( $new_dest ) ) {
			$this->rollback->remove_path( $new_dest );
		}

		\RepoUpdate\Helpers\FilesystemHelper::init();

		if ( ! \RepoUpdate\Helpers\FilesystemHelper::move( $destination, $new_dest ) ) {
			$this->logger->log(
				'update_install',
				sprintf( 'Failed to rename extracted package to %s.', $expected ),
				Logger::LEVEL_ERROR,
				$repo->id
			);

			return new \WP_Error( 'repo_update_rename_failed', __( 'Failed to normalize package directory after download.', 'repo-update' ) );
		}

		return $response;
	}

	/**
	 * Ensure package installs to the existing target path.
	 *
	 * @param array $result     Install result.
	 * @param array $hook_extra Hook extra data.
	 * @return array
	 */
	public function ensure_install_destination( array $result, array $hook_extra ): array {
		$repo = $this->upgrading_repo ?: $this->find_repo_from_hook_extra( $hook_extra );

		if ( ! $repo || empty( $result['destination'] ) ) {
			return $result;
		}

		$expected = SlugHelper::get_install_path( $repo->type, $repo->target_slug );

		if ( $result['destination'] !== $expected && is_dir( $expected ) ) {
			$result['destination'] = $expected;
		}

		return $result;
	}

	/**
	 * Log upgrade results and reset download state.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance.
	 * @param array        $options  Upgrade options.
	 */
	public function after_upgrade( $upgrader, array $options ): void {
		$repo = $this->upgrading_repo;

		$this->clear_download_state();

		if ( empty( $options['action'] ) || 'update' !== $options['action'] || ! $repo ) {
			return;
		}

		if ( empty( $options['success'] ) ) {
			$this->logger->log(
				'update_install',
				sprintf( 'Update failed for %s. Rollback may be available.', $repo->target_slug ),
				Logger::LEVEL_ERROR,
				$repo->id
			);
			set_transient( 'repo_update_failed_' . $repo->id, 1, DAY_IN_SECONDS );

			return;
		}

		$version = SlugHelper::get_installed_version( $repo->type, $repo->target_slug );
		$this->repositories->mark_updated( $repo->id, $version );

		$this->logger->log(
			'update_install',
			sprintf( 'Updated %s to version %s.', $repo->target_slug, $version ),
			Logger::LEVEL_INFO,
			$repo->id
		);

		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
	}

	/**
	 * Show admin notices for available updates and failures.
	 */
	public function update_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		foreach ( $this->repositories->enabled() as $repo ) {
			$notice = get_transient( 'repo_update_notice_' . $repo->id );

			if ( $notice && $repo->notifications ) {
				printf(
					'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: 1: repository name, 2: version */
							__( 'Update available for %1$s: version %2$s.', 'repo-update' ),
							$repo->full_name(),
							$notice
						)
					)
				);

				delete_transient( 'repo_update_notice_' . $repo->id );
			}

			if ( get_transient( 'repo_update_failed_' . $repo->id ) ) {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %s: repository name */
							__( 'Update failed for %s. A rollback backup may be available on the dashboard.', 'repo-update' ),
							$repo->full_name()
						)
					)
				);

				delete_transient( 'repo_update_failed_' . $repo->id );
			}
		}
	}

	/**
	 * Reset per-download authorization state.
	 */
	private function clear_download_state(): void {
		$this->upgrading_repo        = null;
		$this->active_package_url    = '';
		$this->active_download_token = '';
	}

	/**
	 * Find repository from upgrader hook extra data.
	 *
	 * @param array $hook_extra Hook extra data.
	 */
	private function find_repo_from_hook_extra( array $hook_extra ): ?Repository {
		if ( ! empty( $hook_extra['plugin'] ) ) {
			foreach ( $this->repositories->enabled() as $repo ) {
				if ( 'plugin' === $repo->type && $repo->plugin_key() === $hook_extra['plugin'] ) {
					return $repo;
				}
			}
		}

		if ( ! empty( $hook_extra['theme'] ) ) {
			foreach ( $this->repositories->enabled() as $repo ) {
				if ( 'theme' === $repo->type && $repo->target_slug === $hook_extra['theme'] ) {
					return $repo;
				}
			}
		}

		return null;
	}
}
