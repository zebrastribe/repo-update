<?php
/**
 * Plugin deactivation.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate;

/**
 * Handles plugin deactivation.
 */
final class Deactivator {

	/**
	 * Run deactivation routines.
	 */
	public static function deactivate(): void {
		CronScheduler::unschedule();
	}
}
