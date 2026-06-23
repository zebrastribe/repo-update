<?php
/**
 * Base test case with Brain Monkey lifecycle.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case.
 */
abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}
}
