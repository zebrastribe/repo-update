<?php
/**
 * Provider interface for future GitLab/Bitbucket support.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Interfaces;

/**
 * VCS provider contract.
 */
interface ProviderInterface {

	/**
	 * Test API connectivity and repository access.
	 *
	 * @param string $owner Repository owner.
	 * @param string $name  Repository name.
	 * @param string $token Personal access token.
	 * @return array{success: bool, message: string}
	 */
	public function test_connection( string $owner, string $name, string $token ): array;

	/**
	 * Fetch branch names for a repository.
	 *
	 * @param string $owner Repository owner.
	 * @param string $name  Repository name.
	 * @param string $token Personal access token.
	 * @return array{success: bool, branches?: string[], message?: string}
	 */
	public function get_branches( string $owner, string $name, string $token ): array;

	/**
	 * Get the Version header from a plugin or theme on a branch.
	 *
	 * @param string $owner       Repository owner.
	 * @param string $name        Repository name.
	 * @param string $token       Personal access token.
	 * @param string $branch      Branch name.
	 * @param string $type        plugin|theme.
	 * @param string $target_slug Target slug.
	 * @param string $plugin_file Plugin file relative to slug (plugins only).
	 * @return array{success: bool, version?: string, message?: string}
	 */
	public function get_remote_version(
		string $owner,
		string $name,
		string $token,
		string $branch,
		string $type,
		string $target_slug,
		string $plugin_file = ''
	): array;

	/**
	 * Build a downloadable package URL for a branch archive.
	 *
	 * @param string $owner  Repository owner.
	 * @param string $name   Repository name.
	 * @param string $branch Branch name.
	 */
	public function get_package_url( string $owner, string $name, string $branch ): string;
}
