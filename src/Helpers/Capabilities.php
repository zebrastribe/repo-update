<?php
/**
 * Capability helpers.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Helpers;

/**
 * Centralized capability checks for admin actions.
 */
final class Capabilities {

	public const MANAGE = 'manage_options';

	/**
	 * Whether the user can access plugin configuration screens.
	 */
	public static function can_manage(): bool {
		return current_user_can( self::MANAGE );
	}

	/**
	 * Whether the user can check for remote updates.
	 */
	public static function can_check_updates(): bool {
		return current_user_can( self::MANAGE );
	}

	/**
	 * Whether the user can update or rollback a target.
	 *
	 * @param string $type plugin|theme.
	 */
	public static function can_modify_target( string $type ): bool {
		if ( 'theme' === $type ) {
			return current_user_can( 'update_themes' );
		}

		return current_user_can( 'update_plugins' );
	}

	/**
	 * Whether the user can delete rollback backups.
	 *
	 * @param string $type plugin|theme.
	 */
	public static function can_delete_backup( string $type ): bool {
		return self::can_modify_target( $type );
	}
}
