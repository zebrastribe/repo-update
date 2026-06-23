<?php
/**
 * Plugin Name:       Repo Update
 * Plugin URI:        https://github.com/zebrastribe/repo-update
 * Description:       Integrate GitHub repositories with the native WordPress update system for plugins and themes.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Repo Update
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       repo-update
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'REPO_UPDATE_VERSION', '1.0.0' );
define( 'REPO_UPDATE_FILE', __FILE__ );
define( 'REPO_UPDATE_PATH', plugin_dir_path( __FILE__ ) );
define( 'REPO_UPDATE_URL', plugin_dir_url( __FILE__ ) );
define( 'REPO_UPDATE_BASENAME', plugin_basename( __FILE__ ) );

$repo_update_autoloader = REPO_UPDATE_PATH . 'vendor/autoload.php';

if ( file_exists( $repo_update_autoloader ) ) {
	require_once $repo_update_autoloader;
} else {
	require_once REPO_UPDATE_PATH . 'src/Autoloader.php';
	RepoUpdate\Autoloader::register();
}

/**
 * Returns the main plugin instance.
 *
 * @return RepoUpdate\Plugin
 */
function repo_update(): RepoUpdate\Plugin {
	return RepoUpdate\Plugin::instance();
}

repo_update()->boot();

register_activation_hook( __FILE__, array( RepoUpdate\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( RepoUpdate\Deactivator::class, 'deactivate' ) );
