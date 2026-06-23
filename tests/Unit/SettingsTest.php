<?php
/**
 * Settings unit tests.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Tests\Unit;

use Brain\Monkey\Functions;
use RepoUpdate\Tests\TestCase;
use RepoUpdate\Settings\Settings;

/**
 * @covers \RepoUpdate\Settings\Settings
 */
final class SettingsTest extends TestCase {

	public function test_sanitize_enforces_minimums(): void {
		$settings = new Settings();

		$result = $settings->sanitize(
			array(
				'check_interval'      => 0,
				'github_timeout'      => 1,
				'logging_enabled'     => '1',
				'log_retention_days'  => 0,
				'delete_on_uninstall' => '',
			)
		);

		$this->assertSame( 1, $result['check_interval'] );
		$this->assertSame( 5, $result['github_timeout'] );
		$this->assertTrue( $result['logging_enabled'] );
		$this->assertSame( 1, $result['log_retention_days'] );
		$this->assertFalse( $result['delete_on_uninstall'] );
	}

	public function test_all_merges_stored_with_defaults(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Settings::OPTION_KEY, array() )
			->andReturn( array( 'check_interval' => 24 ) );

		$settings = new Settings();
		$all      = $settings->all();

		$this->assertSame( 24, $all['check_interval'] );
		$this->assertSame( 30, $all['github_timeout'] );
		$this->assertTrue( $all['logging_enabled'] );
	}

	public function test_update_persists_sanitized_values(): void {
		$storage = array();

		Functions\when( 'get_option' )->alias(
			static function ( string $key, $default = false ) use ( &$storage ) {
				return $storage[ $key ] ?? $default;
			}
		);

		Functions\expect( 'update_option' )
			->once()
			->andReturnUsing(
				static function ( string $key, $value ) use ( &$storage ): bool {
					$storage[ $key ] = $value;

					return true;
				}
			);

		$settings = new Settings();
		$settings->update(
			array(
				'check_interval' => 6,
				'github_timeout' => 15,
			)
		);

		$this->assertSame( 6, $settings->get_check_interval() );
		$this->assertSame( 15, $settings->get_github_timeout() );
	}
}
