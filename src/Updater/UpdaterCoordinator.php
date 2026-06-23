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
	 * Active package URLs requiring auth headers.
	 *
	 * @var string[]
	 */
	private array $auth_urls = array();

	/**
	 * Repository currently being upgraded.
	 *
	 * @var Repository|null
	 */
	private ?Repository $upgrading_repo = null;

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
		add_filter( 'http_request_args', array( $this, 'authorize_package_download' ), 10, 2 );
		add_filter( 'upgrader_pre_install', array( $this, 'prepare_backup' ), 10, 2 );
		add_filter( 'upgrader_post_install', array( $this, 'fix_package_directory' ), 10, 3 );
		add_filter( 'upgrader_install_package_result', array( $this, 'ensure_install_destination' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'after_upgrade' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'update_notices' ) );
	}

	/**
	 * Inject plugin update data.
	 *
	 * @param object|false $transient Update transient.
	 * @return object|false
	 */
	public function filter_plugin_updates( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		if ( empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
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

			$installed = SlugHelper::get_installed_version( 'plugin', $repo->target_slug );

			if ( '' === $installed ) {
				continue;
			}

			if ( $this->repositories->is_due( $repo, $this->settings->get_check_interval() ) ) {
				$this->repositories->check_repository( $repo, $this->github );
				$repo = $this->repositories->find( $repo->id ) ?: $repo;
			}

			$remote  = $repo->remote_version ?: $installed;
			$package = $this->github->get_package_url( $repo->owner, $repo->name, $repo->branch );

			$this->auth_urls[] = $package;

			$update_available = version_compare( $installed, $remote, '<' );

			$item = (object) array(
				'id'            => $key,
				'slug'          => dirname( $key ) === '.' ? basename( $key, '.php' ) : dirname( $key ),
				'plugin'        => $key,
				'new_version'   => $remote,
				'url'           => 'https://github.com/' . $repo->owner . '/' . $repo->name,
				'package'       => $package,
				'icons'         => array(),
				'banners'       => array(),
				'banners_rtl'   => array(),
				'tested'        => '',
				'requires_php'  => '',
				'compatibility' => new \stdClass(),
			);

			if ( $update_available ) {
				$transient->response[ $key ] = $item;
			} else {
				$transient->no_update[ $key ] = $item;
			}
		}

		return $transient;
	}

	/**
	 * Inject theme update data.
	 *
	 * @param object|false $transient Update transient.
	 * @return object|false
	 */
	public function filter_theme_updates( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		if ( empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
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

			$installed = SlugHelper::get_installed_version( 'theme', $slug );

			if ( '' === $installed ) {
				continue;
			}

			if ( $this->repositories->is_due( $repo, $this->settings->get_check_interval() ) ) {
				$this->repositories->check_repository( $repo, $this->github );
				$repo = $this->repositories->find( $repo->id ) ?: $repo;
			}

			$remote  = $repo->remote_version ?: $installed;
			$package = $this->github->get_package_url( $repo->owner, $repo->name, $repo->branch );

			$this->auth_urls[] = $package;

			$update_available = version_compare( $installed, $remote, '<' );

			$item = array(
				'theme'       => $slug,
				'new_version' => $remote,
				'url'         => 'https://github.com/' . $repo->owner . '/' . $repo->name,
				'package'     => $package,
			);

			if ( $update_available ) {
				$transient->response[ $slug ] = $item;
			} else {
				$transient->no_update[ $slug ] = $item;
			}
		}

		return $transient;
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
	 * Add authorization header for GitHub package downloads.
	 *
	 * @param array  $args Request args.
	 * @param string $url  Request URL.
	 * @return array
	 */
	public function authorize_package_download( array $args, string $url ): array {
		if ( strpos( $url, 'api.github.com' ) === false && strpos( $url, 'github.com' ) === false ) {
			return $args;
		}

		foreach ( $this->repositories->enabled() as $repo ) {
			$package = $this->github->get_package_url( $repo->owner, $repo->name, $repo->branch );

			if ( strpos( $url, $repo->owner . '/' . $repo->name ) !== false || $url === $package ) {
				$token = $repo->get_token();

				if ( '' !== $token ) {
					$args['headers']['Authorization'] = 'Bearer ' . $token;
				}
			}
		}

		return $args;
	}

	/**
	 * Create rollback backup before install.
	 *
	 * @param bool|array $response Install response.
	 * @param array      $hook_extra Hook extra data.
	 * @return bool|array
	 */
	public function prepare_backup( $response, array $hook_extra ) {
		$repo = $this->find_repo_from_hook_extra( $hook_extra );

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
	 * @return bool
	 */
	public function fix_package_directory( bool $response, array $hook_extra, array $result ): bool {
		if ( empty( $result['destination'] ) ) {
			return $response;
		}

		$repo = $this->upgrading_repo ?: $this->find_repo_from_hook_extra( $hook_extra );

		if ( ! $repo ) {
			return $response;
		}

		$expected = basename( SlugHelper::get_install_path( $repo->type, $repo->target_slug ) );
		$current  = basename( $result['destination'] );

		if ( $expected === $current ) {
			return $response;
		}

		$parent      = dirname( $result['destination'] );
		$new_dest    = $parent . '/' . $expected;
		$destination = $result['destination'];

		if ( is_dir( $new_dest ) ) {
			$this->rollback->remove_path( $new_dest );
		}

		@rename( $destination, $new_dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return $response;
	}

	/**
	 * Ensure package installs to the existing target path.
	 *
	 * @param array $result Install result.
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
	 * Log successful upgrades.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance.
	 * @param array        $options  Upgrade options.
	 */
	public function after_upgrade( $upgrader, array $options ): void {
		if ( empty( $options['action'] ) || 'update' !== $options['action'] ) {
			return;
		}

		$repo = $this->upgrading_repo;

		if ( ! $repo ) {
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

		$this->upgrading_repo = null;

		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
	}

	/**
	 * Show admin notices for available updates.
	 */
	public function update_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		foreach ( $this->repositories->enabled() as $repo ) {
			$notice = get_transient( 'repo_update_notice_' . $repo->id );

			if ( ! $notice || ! $repo->notifications ) {
				continue;
			}

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
