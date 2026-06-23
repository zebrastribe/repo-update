<?php
/**
 * Admin menu registration.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Admin;

use RepoUpdate\Helpers\Capabilities;

/**
 * Registers admin pages and menus.
 */
final class AdminMenu {

	/**
	 * @var DashboardPage
	 */
	private DashboardPage $dashboard;

	/**
	 * @var RepositoryPage
	 */
	private RepositoryPage $repositories;

	/**
	 * @var SettingsPage
	 */
	private SettingsPage $settings;

	/**
	 * @var LogsPage
	 */
	private LogsPage $logs;

	/**
	 * @param DashboardPage  $dashboard     Dashboard page.
	 * @param RepositoryPage $repositories  Repository page.
	 * @param SettingsPage   $settings      Settings page.
	 * @param LogsPage       $logs          Logs page.
	 */
	public function __construct(
		DashboardPage $dashboard,
		RepositoryPage $repositories,
		SettingsPage $settings,
		LogsPage $logs
	) {
		$this->dashboard    = $dashboard;
		$this->repositories = $repositories;
		$this->settings     = $settings;
		$this->logs         = $logs;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
		add_action( 'admin_init', array( $this->settings, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . REPO_UPDATE_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Add settings link on plugins page.
	 *
	 * @param string[] $links Plugin action links.
	 * @return string[]
	 */
	public function plugin_action_links( array $links ): array {
		if ( ! Capabilities::can_manage() ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=repo-update' ) ),
			esc_html__( 'Dashboard', 'repo-update' )
		);

		return $links;
	}

	/**
	 * Add admin menu pages.
	 */
	public function add_menus(): void {
		add_menu_page(
			__( 'Repo Update', 'repo-update' ),
			__( 'Repo Update', 'repo-update' ),
			Capabilities::MANAGE,
			'repo-update',
			array( $this->dashboard, 'render' ),
			'dashicons-update',
			58
		);

		add_submenu_page(
			'repo-update',
			__( 'Dashboard', 'repo-update' ),
			__( 'Dashboard', 'repo-update' ),
			Capabilities::MANAGE,
			'repo-update',
			array( $this->dashboard, 'render' )
		);

		add_submenu_page(
			'repo-update',
			__( 'Repositories', 'repo-update' ),
			__( 'Repositories', 'repo-update' ),
			Capabilities::MANAGE,
			'repo-update-repositories',
			array( $this->repositories, 'render' )
		);

		add_submenu_page(
			'repo-update',
			__( 'Settings', 'repo-update' ),
			__( 'Settings', 'repo-update' ),
			Capabilities::MANAGE,
			'repo-update-settings',
			array( $this->settings, 'render' )
		);

		add_submenu_page(
			'repo-update',
			__( 'Logs', 'repo-update' ),
			__( 'Logs', 'repo-update' ),
			Capabilities::MANAGE,
			'repo-update-logs',
			array( $this->logs, 'render' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'repo-update' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'repo-update-admin',
			REPO_UPDATE_URL . 'assets/css/admin.css',
			array(),
			REPO_UPDATE_VERSION
		);

		wp_enqueue_script(
			'repo-update-admin',
			REPO_UPDATE_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			REPO_UPDATE_VERSION,
			true
		);

		wp_localize_script(
			'repo-update-admin',
			'repoUpdate',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'repo_update_admin' ),
				'i18n'    => array(
					'confirmDelete'   => __( 'Are you sure you want to delete this repository?', 'repo-update' ),
					'confirmRollback' => __( 'Are you sure you want to restore the previous version?', 'repo-update' ),
					'confirmClear'    => __( 'Are you sure you want to clear all logs?', 'repo-update' ),
					'loading'         => __( 'Loading...', 'repo-update' ),
					'error'           => __( 'Request failed.', 'repo-update' ),
					'branchesLoaded'  => __( 'Branches loaded.', 'repo-update' ),
					'fetchBranches'   => __( 'Fetch Branches', 'repo-update' ),
					'testConnection'  => __( 'Test Connection', 'repo-update' ),
					'connectionOk'    => __( 'Connection successful.', 'repo-update' ),
				),
			)
		);
	}
}
