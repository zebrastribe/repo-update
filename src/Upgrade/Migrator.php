<?php
/**
 * Database and schema migrations.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Upgrade;

use RepoUpdate\Logger\Logger;
use RepoUpdate\Repository\RepositoryStore;
use RepoUpdate\Settings\Settings;

/**
 * Runs upgrade routines when the plugin version changes.
 */
final class Migrator {

	public const DB_VERSION_OPTION = 'repo_update_db_version';

	/**
	 * Maybe run pending migrations.
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( self::DB_VERSION_OPTION, '0' );

		if ( version_compare( (string) $installed, REPO_UPDATE_VERSION, '>=' ) ) {
			return;
		}

		$store    = new RepositoryStore();
		$logger   = new Logger( new Settings() );
		$settings = new Settings();

		$store->create_tables();
		$logger->create_table();
		$settings->set_defaults();

		update_option( self::DB_VERSION_OPTION, REPO_UPDATE_VERSION );
	}
}
