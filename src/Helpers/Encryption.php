<?php
/**
 * Encryption helpers for stored tokens.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Helpers;

/**
 * Encrypts and decrypts sensitive values using WordPress salts.
 */
final class Encryption {

	/**
	 * Encrypt a plaintext string.
	 *
	 * @param string $plaintext Value to encrypt.
	 * @return string|false Base64-encoded ciphertext or false on failure.
	 */
	public static function encrypt( string $plaintext ) {
		if ( '' === $plaintext ) {
			return '';
		}

		$key    = self::get_key();
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			return false;
		}

		return base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt a ciphertext string.
	 *
	 * @param string $encoded Base64-encoded ciphertext.
	 */
	public static function decrypt( string $encoded ): string {
		if ( '' === $encoded ) {
			return '';
		}

		$decoded = base64_decode( $encoded, true );

		if ( false === $decoded || strlen( $decoded ) < 17 ) {
			return '';
		}

		$iv     = substr( $decoded, 0, 16 );
		$cipher = substr( $decoded, 16 );
		$key    = self::get_key();
		$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : $plain;
	}

	/**
	 * Derive an encryption key from WordPress salts.
	 */
	private static function get_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
	}

	/**
	 * Mask a token for display.
	 *
	 * @param string $token Personal access token.
	 */
	public static function mask_token( string $token ): string {
		if ( strlen( $token ) <= 4 ) {
			return '****';
		}

		return '****' . substr( $token, -4 );
	}
}
