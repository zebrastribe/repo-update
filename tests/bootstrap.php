<?php
/**
 * PHPUnit bootstrap.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

define( 'ABSPATH', '/tmp/wordpress/' );
define( 'REPO_UPDATE_VERSION', '1.1.0' );
define( 'REPO_UPDATE_FILE', dirname( __DIR__ ) . '/repo-update.php' );
define( 'REPO_UPDATE_PATH', dirname( __DIR__ ) . '/' );
define( 'REPO_UPDATE_URL', 'http://example.com/wp-content/plugins/repo-update/' );
define( 'REPO_UPDATE_BASENAME', 'repo-update/repo-update.php' );
define( 'WP_CONTENT_DIR', '/tmp/wordpress/wp-content' );
define( 'WP_PLUGIN_DIR', '/tmp/wordpress/wp-content/plugins' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data Data to encode.
	 */
	function wp_json_encode( $data ): string {
		return (string) json_encode( $data );
	}
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
