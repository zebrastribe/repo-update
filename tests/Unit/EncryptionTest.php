<?php
/**
 * Encryption unit tests.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Tests\Unit;

use Brain\Monkey\Functions;
use RepoUpdate\Tests\TestCase;
use RepoUpdate\Helpers\Encryption;

/**
 * @covers \RepoUpdate\Helpers\Encryption
 */
final class EncryptionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_salt' )->alias(
			static function ( string $scheme ): string {
				return 'test-salt-' . $scheme;
			}
		);
	}

	public function test_encrypt_decrypt_roundtrip(): void {
		$plain = 'ghp_testtoken1234567890';
		$enc   = Encryption::encrypt( $plain );

		$this->assertIsString( $enc );
		$this->assertNotSame( $plain, $enc );
		$this->assertSame( $plain, Encryption::decrypt( (string) $enc ) );
	}

	public function test_empty_string_returns_empty(): void {
		$this->assertSame( '', Encryption::encrypt( '' ) );
		$this->assertSame( '', Encryption::decrypt( '' ) );
	}

	public function test_decrypt_invalid_payload_returns_empty(): void {
		$this->assertSame( '', Encryption::decrypt( 'not-valid-base64!!!' ) );
		$this->assertSame( '', Encryption::decrypt( base64_encode( 'short' ) ) );
	}

	public function test_mask_token(): void {
		$this->assertSame( '****', Encryption::mask_token( 'abc' ) );
		$this->assertSame( '****7890', Encryption::mask_token( 'ghp_1234567890' ) );
	}
}
