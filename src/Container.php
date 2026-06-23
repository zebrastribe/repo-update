<?php
/**
 * Lightweight dependency injection container.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate;

use RepoUpdate\Admin\AdminMenu;
use RepoUpdate\Admin\AjaxHandler;
use RepoUpdate\Admin\DashboardPage;
use RepoUpdate\Admin\LogsPage;
use RepoUpdate\Admin\RepositoryPage;
use RepoUpdate\Admin\SettingsPage;
use RepoUpdate\API\GitHubClient;
use RepoUpdate\Interfaces\ProviderInterface;
use RepoUpdate\Logger\Logger;
use RepoUpdate\Repository\RepositoryManager;
use RepoUpdate\Repository\RepositoryStore;
use RepoUpdate\Rollback\RollbackManager;
use RepoUpdate\Settings\Settings;
use RepoUpdate\Updater\UpdaterCoordinator;

/**
 * Simple service container.
 */
final class Container {

	/**
	 * Resolved services.
	 *
	 * @var array<string, object>
	 */
	private array $services = array();

	/**
	 * Get a service by class name.
	 *
	 * @param string $id Service class name.
	 * @return object
	 */
	public function get( string $id ): object {
		if ( ! isset( $this->services[ $id ] ) ) {
			$this->services[ $id ] = $this->create( $id );
		}

		return $this->services[ $id ];
	}

	/**
	 * Create a service.
	 *
	 * @param string $id Service class name.
	 * @return object
	 */
	private function create( string $id ): object {
		$settings  = $this->getSettings();
		$logger    = $this->getLogger();
		$store     = $this->getRepositoryStore();
		$manager   = $this->getRepositoryManager();
		$github    = $this->getGitHubClient();
		$rollback  = $this->getRollbackManager();

		switch ( $id ) {
			case Settings::class:
				return $settings;
			case Logger::class:
				return $logger;
			case RepositoryStore::class:
				return $store;
			case RepositoryManager::class:
				return $manager;
			case GitHubClient::class:
				return $github;
			case ProviderInterface::class:
				return $github;
			case RollbackManager::class:
				return $rollback;
			case UpdaterCoordinator::class:
				return new UpdaterCoordinator( $manager, $github, $logger, $rollback, $settings );
			case CronScheduler::class:
				return new CronScheduler( $manager, $settings, $logger );
			case AdminMenu::class:
				return new AdminMenu(
					$this->get( DashboardPage::class ),
					$this->get( RepositoryPage::class ),
					$this->get( SettingsPage::class ),
					$this->get( LogsPage::class )
				);
			case DashboardPage::class:
				return new DashboardPage( $manager, $settings );
			case RepositoryPage::class:
				return new RepositoryPage( $manager, $github, $settings );
			case SettingsPage::class:
				return new SettingsPage( $settings, $logger, $store );
			case LogsPage::class:
				return new LogsPage( $logger );
			case AjaxHandler::class:
				return new AjaxHandler( $manager, $github, $logger, $settings, $rollback );
			default:
				throw new \InvalidArgumentException( sprintf( 'Unknown service: %s', $id ) );
		}
	}

	/**
	 * @return Settings
	 */
	private function getSettings(): Settings {
		if ( ! isset( $this->services[ Settings::class ] ) ) {
			$this->services[ Settings::class ] = new Settings();
		}

		return $this->services[ Settings::class ];
	}

	/**
	 * @return Logger
	 */
	private function getLogger(): Logger {
		if ( ! isset( $this->services[ Logger::class ] ) ) {
			$this->services[ Logger::class ] = new Logger( $this->getSettings() );
		}

		return $this->services[ Logger::class ];
	}

	/**
	 * @return RepositoryStore
	 */
	private function getRepositoryStore(): RepositoryStore {
		if ( ! isset( $this->services[ RepositoryStore::class ] ) ) {
			$this->services[ RepositoryStore::class ] = new RepositoryStore();
		}

		return $this->services[ RepositoryStore::class ];
	}

	/**
	 * @return RepositoryManager
	 */
	private function getRepositoryManager(): RepositoryManager {
		if ( ! isset( $this->services[ RepositoryManager::class ] ) ) {
			$this->services[ RepositoryManager::class ] = new RepositoryManager(
				$this->getRepositoryStore(),
				$this->getLogger()
			);
		}

		return $this->services[ RepositoryManager::class ];
	}

	/**
	 * @return GitHubClient
	 */
	private function getGitHubClient(): GitHubClient {
		if ( ! isset( $this->services[ GitHubClient::class ] ) ) {
			$this->services[ GitHubClient::class ] = new GitHubClient( $this->getSettings() );
		}

		return $this->services[ GitHubClient::class ];
	}

	/**
	 * @return RollbackManager
	 */
	private function getRollbackManager(): RollbackManager {
		if ( ! isset( $this->services[ RollbackManager::class ] ) ) {
			$this->services[ RollbackManager::class ] = new RollbackManager( $this->getLogger() );
		}

		return $this->services[ RollbackManager::class ];
	}
}
