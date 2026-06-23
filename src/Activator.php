<?php
/**
 * Plugin activation.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate;

use RepoUpdate\Upgrade\Migrator;

/**
 * Handles plugin activation.
 */
final class Activator {

	/**
	 * Run activation routines.
	 */
	public static function activate(): void {
		Migrator::maybe_upgrade();
		CronScheduler::schedule();
	}
}
