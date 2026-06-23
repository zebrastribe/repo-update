<?php
/**
 * CronScheduler unit tests.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Tests\Unit;

use RepoUpdate\Tests\TestCase;
use RepoUpdate\CronScheduler;

/**
 * @covers \RepoUpdate\CronScheduler
 */
final class CronSchedulerTest extends TestCase {

	public function test_hook_constant(): void {
		$this->assertSame( 'repo_update_check_all', CronScheduler::HOOK );
	}
}
