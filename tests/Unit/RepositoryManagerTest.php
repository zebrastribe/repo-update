<?php
/**
 * RepositoryManager unit tests.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Tests\Unit;

use Brain\Monkey\Functions;
use RepoUpdate\Tests\TestCase;
use RepoUpdate\Repository\RepositoryManager;
use RepoUpdate\Repository\RepositoryStore;
use RepoUpdate\Logger\Logger;
use RepoUpdate\Settings\Settings;
use RepoUpdate\API\GitHubClient;
use RepoUpdate\Repository\Repository;

/**
 * @covers \RepoUpdate\Repository\RepositoryManager
 */
final class RepositoryManagerTest extends TestCase {

	private RepositoryManager $manager;

	protected function setUp(): void {
		parent::setUp();

		\Brain\Monkey\Functions\when( 'get_option' )->justReturn( array() );

		$settings = new Settings();

		$settings = new Settings();

		$this->manager = new RepositoryManager(
			new RepositoryStore(),
			new Logger( $settings ),
			$settings,
			new GitHubClient( $settings )
		);
	}

	public function test_is_due_when_never_checked(): void {
		$repo = new Repository();

		$this->assertTrue( $this->manager->is_due( $repo, 12 ) );
	}

	public function test_is_due_respects_per_repo_interval(): void {
		$repo                 = new Repository();
		$repo->check_interval = 2;
		$repo->last_checked   = gmdate( 'Y-m-d H:i:s', time() - ( 3 * HOUR_IN_SECONDS ) );

		$this->assertTrue( $this->manager->is_due( $repo, 12 ) );

		$repo->last_checked = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
		$this->assertFalse( $this->manager->is_due( $repo, 12 ) );
	}

	public function test_is_not_due_when_recently_checked(): void {
		$repo                 = new Repository();
		$repo->check_interval = 12;
		$repo->last_checked   = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );

		$this->assertFalse( $this->manager->is_due( $repo, 12 ) );
	}

	public function test_is_due_when_global_interval_elapsed(): void {
		$repo               = new Repository();
		$repo->last_checked = gmdate( 'Y-m-d H:i:s', time() - ( 13 * HOUR_IN_SECONDS ) );

		$this->assertTrue( $this->manager->is_due( $repo, 12 ) );
	}
}
