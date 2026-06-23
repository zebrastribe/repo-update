<?php
/**
 * Repository status constants.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Constants;

/**
 * Known repository status slugs.
 */
final class RepositoryStatus {

	public const UNKNOWN          = 'unknown';
	public const UP_TO_DATE       = 'up_to_date';
	public const UPDATE_AVAILABLE = 'update_available';
	public const ERROR            = 'error';
	public const NOT_INSTALLED    = 'not_installed';
	public const DISABLED         = 'disabled';
}
