<?php
/**
 * Repository entity.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Repository;

/**
 * Data object for a configured GitHub repository.
 */
final class Repository {

	/**
	 * @var int
	 */
	public int $id = 0;

	/**
	 * @var string
	 */
	public string $owner = '';

	/**
	 * @var string
	 */
	public string $name = '';

	/**
	 * @var string
	 */
	public string $token_encrypted = '';

	/**
	 * @var string plugin|theme
	 */
	public string $type = 'plugin';

	/**
	 * @var string
	 */
	public string $branch = 'main';

	/**
	 * @var int Hours between checks; 0 uses global default.
	 */
	public int $check_interval = 0;

	/**
	 * @var bool
	 */
	public bool $notifications = true;

	/**
	 * @var bool
	 */
	public bool $rollback_enabled = true;

	/**
	 * @var bool
	 */
	public bool $enabled = true;

	/**
	 * @var string Plugin basename or theme slug.
	 */
	public string $target_slug = '';

	/**
	 * @var string Plugin file path relative to plugins dir.
	 */
	public string $plugin_file = '';

	/**
	 * @var string
	 */
	public string $notes = '';

	/**
	 * @var string|null
	 */
	public ?string $last_checked = null;

	/**
	 * @var string|null
	 */
	public ?string $last_updated = null;

	/**
	 * @var string
	 */
	public string $installed_version = '';

	/**
	 * @var string
	 */
	public string $remote_version = '';

	/**
	 * @var string
	 */
	public string $status = 'unknown';

	/**
	 * Create from database row.
	 *
	 * @param object $row Database row.
	 */
	public static function from_row( object $row ): self {
		$repo                     = new self();
		$repo->id                 = (int) $row->id;
		$repo->owner              = (string) $row->owner;
		$repo->name               = (string) $row->name;
		$repo->token_encrypted    = (string) $row->token_encrypted;
		$repo->type               = (string) $row->type;
		$repo->branch             = (string) $row->branch;
		$repo->check_interval     = (int) $row->check_interval;
		$repo->notifications      = (bool) $row->notifications;
		$repo->rollback_enabled   = (bool) $row->rollback_enabled;
		$repo->enabled            = (bool) $row->enabled;
		$repo->target_slug        = (string) $row->target_slug;
		$repo->plugin_file        = (string) ( $row->plugin_file ?? '' );
		$repo->notes              = (string) ( $row->notes ?? '' );
		$repo->last_checked       = $row->last_checked ? (string) $row->last_checked : null;
		$repo->last_updated       = $row->last_updated ? (string) $row->last_updated : null;
		$repo->installed_version  = (string) ( $row->installed_version ?? '' );
		$repo->remote_version     = (string) ( $row->remote_version ?? '' );
		$repo->status             = (string) ( $row->status ?? 'unknown' );

		return $repo;
	}

	/**
	 * Full repository name.
	 */
	public function full_name(): string {
		return $this->owner . '/' . $this->name;
	}

	/**
	 * Get decrypted token.
	 */
	public function get_token(): string {
		return \RepoUpdate\Helpers\Encryption::decrypt( $this->token_encrypted );
	}

	/**
	 * Plugin updater key.
	 */
	public function plugin_key(): string {
		return $this->plugin_file ?: $this->target_slug;
	}
}
