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
			UNIQUE KEY target_unique (type, target_slug)
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
	}

	/**
	 * Get all repositories.
	 *
	 * @return Repository[]
	 */
	public function all(): array {
		global $wpdb;

		$rows = $wpdb->get_results( 'SELECT * FROM ' . $this->table_name() . ' ORDER BY owner, name' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( array( Repository::class, 'from_row' ), $rows ?: array() );
	}

	/**
	 * Get enabled repositories.
	 *
	 * @return Repository[]
	 */
	public function enabled(): array {
		return array_values(
			array_filter(
				$this->all(),
				static function ( Repository $repo ): bool {
					return $repo->enabled;
				}
			)
		);
	}

	/**
	 * Find repository by ID.
	 *
	 * @param int $id Repository ID.
	 */
	public function find( int $id ): ?Repository {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table_name() . ' WHERE id = %d',
				$id
			)
		);

		return $row ? Repository::from_row( $row ) : null;
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
			'owner'              => sanitize_text_field( (string) $data['owner'] ),
			'name'               => sanitize_text_field( (string) $data['name'] ),
			'type'               => in_array( $data['type'] ?? '', array( 'plugin', 'theme' ), true ) ? $data['type'] : 'plugin',
			'branch'             => sanitize_text_field( (string) ( $data['branch'] ?? 'main' ) ),
			'check_interval'     => max( 0, (int) ( $data['check_interval'] ?? 0 ) ),
			'notifications'      => ! empty( $data['notifications'] ) ? 1 : 0,
			'rollback_enabled'   => ! empty( $data['rollback_enabled'] ) ? 1 : 0,
			'enabled'            => ! empty( $data['enabled'] ) ? 1 : 0,
			'target_slug'        => sanitize_text_field( (string) $data['target_slug'] ),
			'plugin_file'        => sanitize_text_field( (string) ( $data['plugin_file'] ?? '' ) ),
			'notes'              => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
			'installed_version'  => sanitize_text_field( (string) ( $data['installed_version'] ?? '' ) ),
			'remote_version'     => sanitize_text_field( (string) ( $data['remote_version'] ?? '' ) ),
			'status'             => sanitize_text_field( (string) ( $data['status'] ?? 'unknown' ) ),
			'updated_at'         => $now,
		);

		if ( ! empty( $data['token'] ) ) {
			$record['token_encrypted'] = \RepoUpdate\Helpers\Encryption::encrypt( (string) $data['token'] );
		}

		if ( $id > 0 ) {
			if ( empty( $data['token'] ) ) {
				unset( $record['token_encrypted'] );
			}

			$wpdb->update( $table, $record, array( 'id' => $id ) );

			return $id;
		}

		if ( empty( $record['token_encrypted'] ) ) {
			return 0;
		}

		$record['created_at'] = $now;
		$wpdb->insert( $table, $record );

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
	}

	/**
	 * Delete repository by ID.
	 *
	 * @param int $id Repository ID.
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		return (bool) $wpdb->delete( $this->table_name(), array( 'id' => $id ), array( '%d' ) );
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
	}
}
