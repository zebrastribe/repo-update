<?php
/**
 * Repository persistence layer.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Repository;

/**
 * Database access for configured repositories.
 */
final class RepositoryStore {

	/**
	 * @var Repository[]|null
	 */
	private static ?array $all_cache = null;

	/**
	 * @var Repository[]|null
	 */
	private static ?array $enabled_cache = null;

	/**
	 * Get repositories table name.
	 */
	public function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'repo_update_repositories';
	}

	/**
	 * Create database tables.
	 */
	public function create_tables(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$table   = $this->table_name();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner varchar(191) NOT NULL,
			name varchar(191) NOT NULL,
			token_encrypted text NOT NULL,
			type varchar(20) NOT NULL DEFAULT 'plugin',
			branch varchar(191) NOT NULL DEFAULT 'main',
			check_interval int(11) NOT NULL DEFAULT 0,
			notifications tinyint(1) NOT NULL DEFAULT 1,
			rollback_enabled tinyint(1) NOT NULL DEFAULT 1,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			target_slug varchar(191) NOT NULL,
			plugin_file varchar(191) NOT NULL DEFAULT '',
			notes text NULL,
			last_checked datetime NULL,
			last_updated datetime NULL,
			installed_version varchar(100) NOT NULL DEFAULT '',
			remote_version varchar(100) NOT NULL DEFAULT '',
			status varchar(50) NOT NULL DEFAULT 'unknown',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY target_unique (type, target_slug),
			KEY enabled (enabled)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop repositories table.
	 */
	public function drop_table(): void {
		global $wpdb;

		$wpdb->query( 'DROP TABLE IF EXISTS ' . $this->table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		self::invalidate_cache();
	}

	/**
	 * Invalidate in-memory caches.
	 */
	public static function invalidate_cache(): void {
		self::$all_cache     = null;
		self::$enabled_cache = null;
	}

	/**
	 * Get all repositories.
	 *
	 * @return Repository[]
	 */
	public function all(): array {
		if ( null !== self::$all_cache ) {
			return self::$all_cache;
		}

		global $wpdb;

		$rows = $wpdb->get_results( 'SELECT * FROM ' . $this->table_name() . ' ORDER BY owner, name' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		self::$all_cache = array_map( array( Repository::class, 'from_row' ), $rows ?: array() );

		return self::$all_cache;
	}

	/**
	 * Get enabled repositories.
	 *
	 * @return Repository[]
	 */
	public function enabled(): array {
		if ( null !== self::$enabled_cache ) {
			return self::$enabled_cache;
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table_name() . ' WHERE enabled = %d ORDER BY owner, name',
				1
			)
		);

		self::$enabled_cache = array_map( array( Repository::class, 'from_row' ), $rows ?: array() );

		return self::$enabled_cache;
	}

	/**
	 * Find repository by ID.
	 *
	 * @param int $id Repository ID.
	 */
	public function find( int $id ): ?Repository {
		foreach ( $this->all() as $repo ) {
			if ( $repo->id === $id ) {
				return $repo;
			}
		}

		return null;
	}

	/**
	 * Find by target slug and type.
	 *
	 * @param string $type        plugin|theme.
	 * @param string $target_slug Target identifier.
	 */
	public function find_by_target( string $type, string $target_slug ): ?Repository {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table_name() . ' WHERE type = %s AND target_slug = %s',
				$type,
				$target_slug
			)
		);

		return $row ? Repository::from_row( $row ) : null;
	}

	/**
	 * Save repository (insert or update).
	 *
	 * @param array<string, mixed> $data Repository data.
	 */
	public function save( array $data ): int {
		global $wpdb;

		$now   = current_time( 'mysql' );
		$table = $this->table_name();
		$id    = isset( $data['id'] ) ? (int) $data['id'] : 0;

		$record = array(
			'owner'             => sanitize_text_field( (string) $data['owner'] ),
			'name'              => sanitize_text_field( (string) $data['name'] ),
			'type'              => in_array( $data['type'] ?? '', array( 'plugin', 'theme' ), true ) ? $data['type'] : 'plugin',
			'branch'            => sanitize_text_field( (string) ( $data['branch'] ?? 'main' ) ),
			'check_interval'    => max( 0, (int) ( $data['check_interval'] ?? 0 ) ),
			'notifications'     => ! empty( $data['notifications'] ) ? 1 : 0,
			'rollback_enabled'  => ! empty( $data['rollback_enabled'] ) ? 1 : 0,
			'enabled'           => ! empty( $data['enabled'] ) ? 1 : 0,
			'target_slug'       => sanitize_text_field( (string) $data['target_slug'] ),
			'plugin_file'       => sanitize_text_field( (string) ( $data['plugin_file'] ?? '' ) ),
			'notes'             => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
			'installed_version' => sanitize_text_field( (string) ( $data['installed_version'] ?? '' ) ),
			'remote_version'    => sanitize_text_field( (string) ( $data['remote_version'] ?? '' ) ),
			'status'            => sanitize_text_field( (string) ( $data['status'] ?? 'unknown' ) ),
			'updated_at'        => $now,
		);

		if ( array_key_exists( 'token', $data ) && '' !== (string) $data['token'] ) {
			$encrypted = \RepoUpdate\Helpers\Encryption::encrypt( (string) $data['token'] );

			if ( false === $encrypted ) {
				return 0;
			}

			$record['token_encrypted'] = $encrypted;
		}

		if ( $id > 0 ) {
			$wpdb->update( $table, $record, array( 'id' => $id ), null, array( '%d' ) );
			self::invalidate_cache();

			return $id;
		}

		$record['token_encrypted'] = $record['token_encrypted'] ?? '';
		$record['created_at']      = $now;

		$wpdb->insert( $table, $record );
		self::invalidate_cache();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update repository status fields.
	 *
	 * @param int                  $id   Repository ID.
	 * @param array<string, mixed> $data Fields to update.
	 */
	public function update_status( int $id, array $data ): void {
		global $wpdb;

		$allowed = array(
			'last_checked'      => '%s',
			'last_updated'      => '%s',
			'installed_version' => '%s',
			'remote_version'    => '%s',
			'status'            => '%s',
		);

		$update = array();
		$format = array();

		foreach ( $allowed as $key => $type ) {
			if ( array_key_exists( $key, $data ) ) {
				$update[ $key ] = $data[ $key ];
				$format[]       = $type;
			}
		}

		if ( empty( $update ) ) {
			return;
		}

		$update['updated_at'] = current_time( 'mysql' );
		$format[]             = '%s';

		$wpdb->update( $this->table_name(), $update, array( 'id' => $id ), $format, array( '%d' ) );
		self::invalidate_cache();
	}

	/**
	 * Delete repository by ID.
	 *
	 * @param int $id Repository ID.
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$deleted = (bool) $wpdb->delete( $this->table_name(), array( 'id' => $id ), array( '%d' ) );
		self::invalidate_cache();

		return $deleted;
	}

	/**
	 * Toggle enabled state.
	 *
	 * @param int  $id      Repository ID.
	 * @param bool $enabled Enabled state.
	 */
	public function set_enabled( int $id, bool $enabled ): void {
		global $wpdb;

		$wpdb->update(
			$this->table_name(),
			array(
				'enabled'    => $enabled ? 1 : 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		self::invalidate_cache();
	}
}
