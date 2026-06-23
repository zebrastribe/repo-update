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

$backup_root = WP_CONTENT_DIR . '/repo-update-backups';

if ( is_dir( $backup_root ) ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $backup_root, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() );
		} else {
			unlink( $item->getPathname() );
		}
	}

	rmdir( $backup_root );
}

wp_clear_scheduled_hook( 'repo_update_check_all' );
