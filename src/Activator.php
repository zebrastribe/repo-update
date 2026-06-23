<?php
/**
 * Plugin activation.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate;

use RepoUpdate\Logger\Logger;
use RepoUpdate\Repository\RepositoryStore;
use RepoUpdate\Settings\Settings;

/**
 * Handles plugin activation.
 */
final class Activator {

	/**
	 * Run activation routines.
	 */
	public static function activate(): void {
		$store    = new RepositoryStore();
		$logger   = new Logger( new Settings() );
		$settings = new Settings();

		$store->create_tables();
		$logger->create_table();
		$settings->set_defaults();

		CronScheduler::schedule( $settings->get_check_interval() );

		update_option( 'repo_update_db_version', REPO_UPDATE_VERSION );
	}
}
