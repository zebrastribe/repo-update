<?php
/**
 * Logger service.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Logger;

use RepoUpdate\Settings\Settings;

/**
 * Persists admin-visible logs.
 */
final class Logger {

	public const LEVEL_INFO    = 'info';
	public const LEVEL_WARNING = 'warning';
	public const LEVEL_ERROR   = 'error';

	/**
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get logs table name.
	 */
	public function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'repo_update_logs';
	}

	/**
	 * Create logs table.
	 */
	public function create_table(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$table   = $this->table_name();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			repository_id bigint(20) unsigned NULL,
			level varchar(20) NOT NULL DEFAULT 'info',
			action varchar(50) NOT NULL DEFAULT '',
			message text NOT NULL,
			context longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY repository_id (repository_id),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop logs table.
	 */
	public function drop_table(): void {
		global $wpdb;

		$wpdb->query( 'DROP TABLE IF EXISTS ' . $this->table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Write a log entry.
	 *
	 * @param string               $action  Action identifier.
	 * @param string               $message Log message.
	 * @param string               $level   Log level.
	 * @param int|null             $repo_id Repository ID.
	 * @param array<string, mixed> $context Additional context.
	 */
	public function log(
		string $action,
		string $message,
		string $level = self::LEVEL_INFO,
		?int $repo_id = null,
		array $context = array()
	): void {
		if ( ! $this->settings->is_logging_enabled() ) {
			return;
		}

		global $wpdb;

		$context = $this->redact_sensitive( $context );

		$wpdb->insert(
			$this->table_name(),
			array(
				'repository_id' => $repo_id,
				'level'         => sanitize_key( $level ),
				'action'        => sanitize_key( $action ),
				'message'       => sanitize_text_field( $message ),
				'context'       => wp_json_encode( $context ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get recent logs.
	 *
	 * @param int $limit Max entries.
	 * @return object[]
	 */
	public function get_logs( int $limit = 100 ): array {
		global $wpdb;

		$limit = max( 1, min( 500, $limit ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table_name() . ' ORDER BY created_at DESC LIMIT %d',
				$limit
			)
		) ?: array();
	}

	/**
	 * Clear all logs.
	 */
	public function clear(): void {
		global $wpdb;

		$wpdb->query( 'TRUNCATE TABLE ' . $this->table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Remove sensitive values from context arrays.
	 *
	 * @param array<string, mixed> $context Context array.
	 * @return array<string, mixed>
	 */
	private function redact_sensitive( array $context ): array {
		foreach ( $context as $key => $value ) {
			if ( is_string( $key ) && preg_match( '/token|authorization|password/i', $key ) ) {
				$context[ $key ] = '[redacted]';
			}
		}

		return $context;
	}
}
