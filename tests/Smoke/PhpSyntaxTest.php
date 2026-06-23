<?php
/**
 * PHP syntax smoke tests.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Tests\Smoke;

use PHPUnit\Framework\TestCase;

/**
 * Validates PHP syntax for all plugin source files.
 */
final class PhpSyntaxTest extends TestCase {

	public function test_all_plugin_php_files_have_valid_syntax(): void {
		$root  = dirname( __DIR__, 2 );
		$files = $this->collect_php_files( $root );

		$this->assertNotEmpty( $files );

		foreach ( $files as $file ) {
			$output = array();
			$code   = 0;
			exec( 'php -l ' . escapeshellarg( $file ) . ' 2>&1', $output, $code );

			$this->assertSame(
				0,
				$code,
				'Syntax error in ' . str_replace( $root . '/', '', $file ) . ': ' . implode( "\n", $output )
			);
		}
	}

	/**
	 * @return string[]
	 */
	private function collect_php_files( string $dir ): array {
		$files    = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
				continue;
			}

			$path = $file->getPathname();

			if ( str_contains( $path, '/vendor/' ) ) {
				continue;
			}

			$files[] = $path;
		}

		return $files;
	}
}
