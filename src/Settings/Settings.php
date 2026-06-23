<?php
/**
 * Plugin settings.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Settings;

/**
 * Global plugin settings stored in wp_options.
 */
final class Settings {

	public const OPTION_KEY = 'repo_update_settings';

	/**
	 * Default settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $defaults = array(
		'check_interval'       => 12,
		'github_timeout'       => 30,
		'logging_enabled'      => true,
		'log_retention_days'   => 30,
		'delete_on_uninstall'  => true,
	);

	/**
	 * Set default settings on activation.
	 */
	public function set_defaults(): void {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, $this->defaults, '', false );
		}
	}

	/**
	 * Get all settings.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( $this->defaults, $stored );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$all = $this->all();

		return $all[ $key ] ?? $default;
	}

	/**
	 * Update settings.
	 *
	 * @param array<string, mixed> $values Settings to save.
	 */
	public function update( array $values ): void {
		$current = $this->all();
		$merged  = array_merge( $current, $values );

		$merged['check_interval']      = max( 1, (int) $merged['check_interval'] );
		$merged['github_timeout']      = max( 5, (int) $merged['github_timeout'] );
		$merged['log_retention_days']  = max( 1, (int) $merged['log_retention_days'] );
		$merged['logging_enabled']     = ! empty( $merged['logging_enabled'] );
		$merged['delete_on_uninstall'] = ! empty( $merged['delete_on_uninstall'] );

		update_option( self::OPTION_KEY, $merged, false );
	}

	/**
	 * Sanitize settings from the Settings API.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input ): array {
		return array(
			'check_interval'      => max( 1, (int) ( $input['check_interval'] ?? 12 ) ),
			'github_timeout'      => max( 5, (int) ( $input['github_timeout'] ?? 30 ) ),
			'logging_enabled'     => ! empty( $input['logging_enabled'] ),
			'log_retention_days'  => max( 1, (int) ( $input['log_retention_days'] ?? 30 ) ),
			'delete_on_uninstall' => ! empty( $input['delete_on_uninstall'] ),
		);
	}

	/**
	 * Global check interval in hours.
	 */
	public function get_check_interval(): int {
		return (int) $this->get( 'check_interval', 12 );
	}

	/**
	 * GitHub HTTP timeout in seconds.
	 */
	public function get_github_timeout(): int {
		return (int) $this->get( 'github_timeout', 30 );
	}

	/**
	 * Whether logging is enabled.
	 */
	public function is_logging_enabled(): bool {
		return (bool) $this->get( 'logging_enabled', true );
	}

	/**
	 * Log retention in days.
	 */
	public function get_log_retention_days(): int {
		return (int) $this->get( 'log_retention_days', 30 );
	}

	/**
	 * Whether to delete data on uninstall.
	 */
	public function should_delete_on_uninstall(): bool {
		return (bool) $this->get( 'delete_on_uninstall', true );
	}
}
