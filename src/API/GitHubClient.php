<?php
/**
 * GitHub API client.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\API;

use RepoUpdate\Interfaces\ProviderInterface;
use RepoUpdate\Settings\Settings;

/**
 * GitHub REST API integration.
 */
final class GitHubClient implements ProviderInterface {

	private const API_BASE       = 'https://api.github.com';
	private const CACHE_TTL      = 900;
	private const MAX_RETRIES    = 2;

	/**
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function test_connection( string $owner, string $name, string $token ): array {
		$response = $this->request( '/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $name ), $token );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return array(
				'success' => false,
				'message' => $this->error_message_from_response( $response, $code ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Connection successful.', 'repo-update' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_branches( string $owner, string $name, string $token ): array {
		$cache_key = 'repo_update_branches_' . md5( $owner . '/' . $name . '|' . $token );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return array(
				'success'  => true,
				'branches' => $cached,
			);
		}

		$branches = array();
		$page     = 1;

		do {
			$response = $this->request(
				'/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $name ) . '/branches?per_page=100&page=' . $page,
				$token
			);

			if ( is_wp_error( $response ) ) {
				return array(
					'success' => false,
					'message' => $response->get_error_message(),
				);
			}

			$code = wp_remote_retrieve_response_code( $response );

			if ( 200 !== $code ) {
				return array(
					'success' => false,
					'message' => $this->error_message_from_response( $response, $code ),
				);
			}

			$body  = json_decode( wp_remote_retrieve_body( $response ), true );
			$batch = is_array( $body ) ? $body : array();

			foreach ( $batch as $branch ) {
				if ( ! empty( $branch['name'] ) ) {
					$branches[] = (string) $branch['name'];
				}
			}

			++$page;
		} while ( count( $batch ) === 100 );

		sort( $branches );
		set_transient( $cache_key, $branches, self::CACHE_TTL );

		return array(
			'success'  => true,
			'branches' => $branches,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_remote_version(
		string $owner,
		string $name,
		string $token,
		string $branch,
		string $type,
		string $target_slug,
		string $plugin_file = ''
	): array {
		$paths = $this->get_version_file_paths( $type, $target_slug, $plugin_file );

		foreach ( $paths as $path ) {
			$cache_key = 'repo_update_version_' . md5( $owner . '/' . $name . '|' . $branch . '|' . $path . '|' . $token );
			$cached    = get_transient( $cache_key );

			if ( is_string( $cached ) && '' !== $cached ) {
				return array(
					'success' => true,
					'version' => $cached,
				);
			}

			$response = $this->request(
				'/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $name ) . '/contents/' . $path . '?ref=' . rawurlencode( $branch ),
				$token
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				continue;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( empty( $body['content'] ) ) {
				continue;
			}

			$content = base64_decode( (string) $body['content'], true );

			if ( false === $content ) {
				continue;
			}

			$version = $this->parse_version_header( $content );

			if ( '' !== $version ) {
				set_transient( $cache_key, $version, self::CACHE_TTL );

				return array(
					'success' => true,
					'version' => $version,
				);
			}
		}

		return array(
			'success' => false,
			'message' => __( 'Could not read Version header from repository.', 'repo-update' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_package_url( string $owner, string $name, string $branch ): string {
		return sprintf(
			'https://api.github.com/repos/%s/%s/zipball/%s',
			rawurlencode( $owner ),
			rawurlencode( $name ),
			rawurlencode( $branch )
		);
	}

	/**
	 * Perform an authenticated GitHub API request.
	 *
	 * @param string $path  API path starting with /.
	 * @param string $token Personal access token (optional for public repos).
	 * @return array|\WP_Error
	 */
	public function request( string $path, string $token ) {
		$args = array(
			'timeout' => $this->settings->get_github_timeout(),
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'Repo-Update-WordPress-Plugin',
			),
		);

		if ( '' !== $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		$url      = self::API_BASE . $path;
		$attempt  = 0;
		$response = null;

		while ( $attempt <= self::MAX_RETRIES ) {
			$response = wp_remote_get( $url, $args );

			if ( is_wp_error( $response ) ) {
				++$attempt;
				sleep( min( 2, $attempt ) );
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( $code < 500 && 403 !== $code ) {
				break;
			}

			++$attempt;
			sleep( min( 2, $attempt ) );
		}

		return $response;
	}

	/**
	 * Candidate file paths for version headers.
	 *
	 * @param string $type        plugin|theme.
	 * @param string $target_slug Target slug.
	 * @param string $plugin_file Plugin file path.
	 * @return string[]
	 */
	private function get_version_file_paths( string $type, string $target_slug, string $plugin_file ): array {
		if ( 'theme' === $type ) {
			return array( $target_slug . '/style.css', 'style.css' );
		}

		$paths = array();

		if ( '' !== $plugin_file ) {
			$paths[] = $plugin_file;
		}

		$paths[] = $target_slug . '/' . basename( $plugin_file ?: $target_slug . '.php' );
		$paths[] = basename( $target_slug ) . '.php';

		return array_values( array_unique( $paths ) );
	}

	/**
	 * Parse Version from plugin or theme header content.
	 *
	 * @param string $content File contents.
	 */
	private function parse_version_header( string $content ): string {
		if ( preg_match( '/^[ \t\/*#@]*Version:(.+)$/mi', $content, $matches ) ) {
			return trim( $matches[1] );
		}

		return '';
	}

	/**
	 * Build a user-facing error message from a response.
	 *
	 * @param array $response HTTP response.
	 * @param int   $code     HTTP status code.
	 */
	private function error_message_from_response( array $response, int $code ): string {
		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$message = is_array( $body ) && ! empty( $body['message'] ) ? (string) $body['message'] : '';

		if ( 401 === $code ) {
			return __( 'Authentication failed. Check your personal access token.', 'repo-update' );
		}

		if ( 403 === $code ) {
			return __( 'GitHub API rate limit exceeded or access forbidden.', 'repo-update' );
		}

		if ( 404 === $code ) {
			return __( 'Repository not found or not accessible.', 'repo-update' );
		}

		return $message ?: sprintf(
			/* translators: %d: HTTP status code */
			__( 'GitHub API request failed with status %d.', 'repo-update' ),
			$code
		);
	}
}
