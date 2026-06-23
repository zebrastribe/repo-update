<?php
/**
 * GitHubClient unit tests.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Tests\Unit;

use Brain\Monkey\Functions;
use RepoUpdate\Tests\TestCase;
use RepoUpdate\API\GitHubClient;
use RepoUpdate\Settings\Settings;

/**
 * @covers \RepoUpdate\API\GitHubClient
 */
final class GitHubClientTest extends TestCase {

	private GitHubClient $client;

	protected function setUp(): void {
		parent::setUp();

		\Brain\Monkey\Functions\when( 'get_option' )->justReturn( array() );

		$settings = new Settings();
		$this->client = new GitHubClient( $settings );
	}

	public function test_get_package_url_format(): void {
		$url = $this->client->get_package_url( 'octocat', 'Hello-World', 'main' );

		$this->assertStringContainsString( 'api.github.com/repos/octocat/Hello-World/zipball/main', $url );
	}

	public function test_get_branches_uses_cache(): void {
		$cache_key = 'repo_update_branches_' . md5( 'octocat/Hello-World|' );

		Functions\expect( 'get_transient' )
			->once()
			->with( $cache_key )
			->andReturn( array( 'main', 'develop' ) );

		$result = $this->client->get_branches( 'octocat', 'Hello-World', '' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( array( 'main', 'develop' ), $result['branches'] );
	}

	public function test_get_remote_version_parses_plugin_header(): void {
		Functions\expect( 'get_transient' )->andReturn( false );
		Functions\expect( 'set_transient' )->andReturn( true );

		$header  = "<?php\n/**\n * Plugin Name: Demo\n * Version: 2.5.1\n */\n";
		$encoded = base64_encode( $header );

		Functions\expect( 'wp_remote_get' )->andReturn(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode( array( 'content' => $encoded ) ),
			)
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static fn( array $r ) => $r['response']['code'] ?? 0
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn( array $r ) => $r['body'] ?? ''
		);
		Functions\when( 'is_wp_error' )->justReturn( false );

		$result = $this->client->get_remote_version(
			'octocat',
			'plugin',
			'',
			'main',
			'plugin',
			'demo/demo.php',
			'demo/demo.php'
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( '2.5.1', $result['version'] );
	}
}
