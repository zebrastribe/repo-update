<?php
/**
 * Cron scheduling for update checks.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate;

use RepoUpdate\Logger\Logger;
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
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * @param RepositoryManager $repositories Repository manager.
	 * @param Settings          $settings     Plugin settings.
	 * @param Logger            $logger       Logger instance.
	 */
	public function __construct( RepositoryManager $repositories, Settings $settings, Logger $logger ) {
		$this->repositories = $repositories;
		$this->settings       = $settings;
		$this->logger         = $logger;
	}

	/**
	 * Register cron hooks.
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'run_checks' ) );
		add_filter( 'cron_schedules', array( $this, 'add_intervals' ) );
	}

	/**
	 * Add custom cron intervals.
	 *
	 * @param array<string, array<string, int|string>> $schedules Existing schedules.
	 * @return array<string, array<string, int|string>>
	 */
	public function add_intervals( array $schedules ): array {
		$schedules['repo_update_6hours'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 6 Hours', 'repo-update' ),
		);
		$schedules['repo_update_12hours'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 12 Hours', 'repo-update' ),
		);

		return $schedules;
	}

	/**
	 * Run update checks for due repositories.
	 */
	public function run_checks(): void {
		if ( ! $this->settings->is_logging_enabled() ) {
			// Logging setting only controls persistence; checks always run.
		}

		$this->repositories->check_all_due();
	}

	/**
	 * Schedule the cron event.
	 *
	 * @param int $interval_hours Global check interval in hours.
	 */
	public static function schedule( int $interval_hours ): void {
		self::unschedule();

		$recurrence = self::interval_to_recurrence( $interval_hours );

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), $recurrence, self::HOOK );
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

	/**
	 * Map hours to a WP cron recurrence slug.
	 *
	 * @param int $hours Interval in hours.
	 */
	public static function interval_to_recurrence( int $hours ): string {
		if ( $hours <= 1 ) {
			return 'hourly';
		}

		if ( $hours <= 6 ) {
			return 'repo_update_6hours';
		}

		if ( $hours <= 12 ) {
			return 'repo_update_12hours';
		}

		return 'twicedaily';
	}
}
