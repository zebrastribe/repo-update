<?php
/**
 * Plugin structure smoke tests.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Tests\Smoke;

use PHPUnit\Framework\TestCase;

/**
 * Verifies plugin deliverables and architecture files exist.
 */
final class PluginStructureTest extends TestCase {

	/**
	 * Required plugin files.
	 *
	 * @return string[]
	 */
	private function required_files(): array {
		return array(
			'repo-update.php',
			'uninstall.php',
			'README.md',
			'composer.json',
			'src/Plugin.php',
			'src/Container.php',
			'src/Activator.php',
			'src/Deactivator.php',
			'src/CronScheduler.php',
			'src/Autoloader.php',
			'src/API/GitHubClient.php',
			'src/Updater/UpdaterCoordinator.php',
			'src/Rollback/RollbackManager.php',
			'src/Repository/Repository.php',
			'src/Repository/RepositoryStore.php',
			'src/Repository/RepositoryManager.php',
			'src/Logger/Logger.php',
			'src/Settings/Settings.php',
			'src/Helpers/Encryption.php',
			'src/Helpers/FilesystemHelper.php',
			'src/Helpers/Capabilities.php',
			'src/Helpers/SlugHelper.php',
			'src/Upgrade/Migrator.php',
			'src/Admin/AdminMenu.php',
			'src/Admin/DashboardPage.php',
			'src/Admin/RepositoryPage.php',
			'src/Admin/SettingsPage.php',
			'src/Admin/LogsPage.php',
			'src/Admin/AjaxHandler.php',
			'src/Admin/ListTables/DashboardListTable.php',
			'assets/js/admin.js',
			'assets/css/admin.css',
			'index.php',
		);
	}

	public function test_required_files_exist(): void {
		$root = dirname( __DIR__, 2 );

		foreach ( $this->required_files() as $file ) {
			$this->assertFileExists( $root . '/' . $file, 'Missing file: ' . $file );
		}
	}

	public function test_plugin_header(): void {
		$contents = file_get_contents( dirname( __DIR__, 2 ) . '/repo-update.php' );

		$this->assertIsString( $contents );
		$this->assertStringContainsString( 'Plugin Name:', $contents );
		$this->assertStringContainsString( 'Repo Update', $contents );
		$this->assertStringContainsString( 'Text Domain:', $contents );
		$this->assertStringContainsString( 'repo-update', $contents );
		$this->assertStringContainsString( 'declare(strict_types=1);', $contents );
	}

	public function test_all_php_files_declare_strict_types_or_are_silent(): void {
		$root    = dirname( __DIR__, 2 );
		$phpfiles = $this->collect_php_files( $root );

		foreach ( $phpfiles as $file ) {
			$contents = file_get_contents( $file );
			$this->assertIsString( $contents );

			if ( str_contains( $contents, 'Silence is golden' ) ) {
				continue;
			}

			$this->assertStringContainsString(
				'declare(strict_types=1);',
				$contents,
				'Missing strict types in ' . str_replace( $root . '/', '', $file )
			);
		}
	}

	public function test_psr4_classes_are_autoloadable(): void {
		$classes = array(
			\RepoUpdate\Plugin::class,
			\RepoUpdate\Container::class,
			\RepoUpdate\API\GitHubClient::class,
			\RepoUpdate\Updater\UpdaterCoordinator::class,
			\RepoUpdate\Rollback\RollbackManager::class,
			\RepoUpdate\Repository\RepositoryManager::class,
			\RepoUpdate\Helpers\Encryption::class,
			\RepoUpdate\Upgrade\Migrator::class,
		);

		foreach ( $classes as $class ) {
			$this->assertTrue( class_exists( $class ), 'Class not autoloadable: ' . $class );
		}
	}

	public function test_uninstall_checks_wp_uninstall_plugin(): void {
		$contents = file_get_contents( dirname( __DIR__, 2 ) . '/uninstall.php' );

		$this->assertIsString( $contents );
		$this->assertStringContainsString( "defined( 'WP_UNINSTALL_PLUGIN' )", $contents );
	}

	/**
	 * @return string[]
	 */
	private function collect_php_files( string $dir ): array {
		$files = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$path = $file->getPathname();

				if ( str_contains( $path, '/vendor/' ) || str_contains( $path, '/tests/' ) ) {
					continue;
				}

				if ( in_array( basename( $path ), array( 'uninstall.php', 'repo-update.php' ), true ) ) {
					continue;
				}

				$files[] = $path;
			}
		}

		return $files;
	}
}
