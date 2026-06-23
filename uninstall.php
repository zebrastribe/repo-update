<?php
/**
 * Uninstall cleanup.
 *
 * @package RepoUpdate
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'repo_update_settings', array() );

if ( empty( $settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'repo_update_repositories' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'repo_update_logs' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

delete_option( 'repo_update_settings' );
delete_option( 'repo_update_db_version' );

require_once plugin_dir_path( __FILE__ ) . 'src/Helpers/FilesystemHelper.php';

\RepoUpdate\Helpers\FilesystemHelper::delete_directory( WP_CONTENT_DIR . '/repo-update-backups' );

wp_clear_scheduled_hook( 'repo_update_check_all' );
