<?php
/**
 * Main plugin bootstrap.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate;

use RepoUpdate\Admin\AdminMenu;
use RepoUpdate\Admin\AjaxHandler;
use RepoUpdate\Upgrade\Migrator;
use RepoUpdate\Updater\UpdaterCoordinator;

/**
 * Plugin singleton and composition root.
 */
final class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->container = new Container();
	}

	/**
	 * Get plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot plugin services.
	 */
	public function boot(): void {
		load_plugin_textdomain( 'repo-update', false, dirname( REPO_UPDATE_BASENAME ) . '/languages' );

		add_action( 'plugins_loaded', array( Migrator::class, 'maybe_upgrade' ) );

		$this->container->get( UpdaterCoordinator::class )->register();
		$this->container->get( CronScheduler::class )->register();

		if ( is_admin() ) {
			$this->container->get( AdminMenu::class )->register();
			$this->container->get( AjaxHandler::class )->register();
		}
	}

	/**
	 * Get the service container.
	 */
	public function container(): Container {
		return $this->container;
	}
}
