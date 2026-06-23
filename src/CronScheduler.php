<?php
/**
 * Cron scheduling for update checks.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate;

use RepoUpdate\Repository\RepositoryManager;
use RepoUpdate\Settings\Settings;

/**
 * Schedules and runs periodic update checks.
 */
final class CronScheduler {

	public const HOOK = 'repo_update_check_all';

	/**
	 * @var RepositoryManager
	 */
	private RepositoryManager $repositories;

	/**
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * @param RepositoryManager $repositories Repository manager.
	 * @param Settings          $settings     Plugin settings.
	 */
	public function __construct( RepositoryManager $repositories, Settings $settings ) {
		$this->repositories = $repositories;
		$this->settings     = $settings;
	}

	/**
	 * Register cron hooks.
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'run_checks' ) );
	}

	/**
	 * Run update checks for due repositories.
	 */
	public function run_checks(): void {
		$this->repositories->check_all_due();
	}

	/**
	 * Schedule the cron event (hourly; per-repo intervals enforced in manager).
	 */
	public static function schedule(): void {
		self::unschedule();

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::HOOK );
		}
	}

	/**
	 * Unschedule the cron event.
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}
}
