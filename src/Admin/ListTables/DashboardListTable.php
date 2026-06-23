<?php
/**
 * Dashboard list table.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Admin\ListTables;

use RepoUpdate\Helpers\SlugHelper;
use RepoUpdate\Repository\Repository;
use RepoUpdate\Repository\RepositoryManager;
use RepoUpdate\Rollback\RollbackManager;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Dashboard overview table.
 */
final class DashboardListTable extends \WP_List_Table {

	/**
	 * @var RepositoryManager
	 */
	private RepositoryManager $repositories;

	/**
	 * @var RollbackManager
	 */
	private RollbackManager $rollback;

	/**
	 * @param RepositoryManager $repositories Repository manager.
	 * @param RollbackManager   $rollback     Rollback manager.
	 */
	public function __construct( RepositoryManager $repositories, RollbackManager $rollback ) {
		$this->repositories = $repositories;
		$this->rollback     = $rollback;

		parent::__construct(
			array(
				'plural'   => 'repositories',
				'singular' => 'repository',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Define columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'repository'    => __( 'Repository', 'repo-update' ),
			'type'          => __( 'Type', 'repo-update' ),
			'branch'        => __( 'Branch', 'repo-update' ),
			'installed'     => __( 'Installed', 'repo-update' ),
			'remote'        => __( 'Remote', 'repo-update' ),
			'status'        => __( 'Status', 'repo-update' ),
			'last_checked'  => __( 'Last Checked', 'repo-update' ),
			'last_updated'  => __( 'Last Updated', 'repo-update' ),
			'rollback'      => __( 'Rollback', 'repo-update' ),
			'actions'       => __( 'Actions', 'repo-update' ),
		);
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items(): void {
		$this->items = $this->repositories->all();
	}

	/**
	 * Render a column.
	 *
	 * @param Repository $item        Row item.
	 * @param string     $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'repository':
				return sprintf(
					'<strong>%s</strong><br><span class="description">%s</span>',
					esc_html( $item->full_name() ),
					esc_html( $item->target_slug )
				);
			case 'type':
				return esc_html( ucfirst( $item->type ) );
			case 'branch':
				return esc_html( $item->branch );
			case 'installed':
				$installed = SlugHelper::get_installed_version( $item->type, $item->target_slug );

				return esc_html( $installed ?: '—' );
			case 'remote':
				return esc_html( $item->remote_version ?: '—' );
			case 'status':
				return esc_html( $this->format_status( $item->status ) );
			case 'last_checked':
				return esc_html( $item->last_checked ?: '—' );
			case 'last_updated':
				return esc_html( $item->last_updated ?: '—' );
			case 'rollback':
				return $this->rollback->has_backup( $item->type, $item->target_slug )
					? esc_html__( 'Yes', 'repo-update' )
					: esc_html__( 'No', 'repo-update' );
			case 'actions':
				return $this->column_actions( $item );
			default:
				return '';
		}
	}

	/**
	 * Build action links for a row.
	 *
	 * @param Repository $repo Repository.
	 */
	private function column_actions( Repository $repo ): string {
		$links   = array();
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $this->action_url( 'check', $repo->id ) ),
			esc_html__( 'Check now', 'repo-update' )
		);

		if ( 'update_available' === $repo->status ) {
			$url     = 'theme' === $repo->type ? admin_url( 'themes.php' ) : admin_url( 'plugins.php' );
			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Update', 'repo-update' ) );
		}

		if ( $this->rollback->has_backup( $repo->type, $repo->target_slug ) ) {
			$links[] = sprintf(
				'<a href="%s" class="repo-update-confirm" data-message="%s">%s</a>',
				esc_url( $this->action_url( 'rollback', $repo->id ) ),
				esc_attr__( 'Restore previous version?', 'repo-update' ),
				esc_html__( 'Rollback', 'repo-update' )
			);
			$links[] = sprintf(
				'<a href="%s" class="repo-update-confirm" data-message="%s">%s</a>',
				esc_url( $this->action_url( 'delete_backup', $repo->id ) ),
				esc_attr__( 'Delete rollback backup?', 'repo-update' ),
				esc_html__( 'Delete backup', 'repo-update' )
			);
		}

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=repo-update-repositories&action=edit&id=' . $repo->id ) ),
			esc_html__( 'Edit', 'repo-update' )
		);

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $this->action_url( 'toggle', $repo->id ) ),
			$repo->enabled ? esc_html__( 'Disable', 'repo-update' ) : esc_html__( 'Enable', 'repo-update' )
		);

		return implode( ' | ', $links );
	}

	/**
	 * Build action URL.
	 *
	 * @param string $action Action name.
	 * @param int    $id     Repository ID.
	 */
	private function action_url( string $action, int $id ): string {
		return wp_nonce_url(
			admin_url( 'admin.php?page=repo-update&repo_update_action=' . $action . '&id=' . $id ),
			'repo_update_action_' . $id
		);
	}

	/**
	 * Format status label.
	 *
	 * @param string $status Status slug.
	 */
	private function format_status( string $status ): string {
		$labels = array(
			'up_to_date'       => __( 'Up to date', 'repo-update' ),
			'update_available' => __( 'Update available', 'repo-update' ),
			'error'            => __( 'Error', 'repo-update' ),
			'not_installed'    => __( 'Not installed', 'repo-update' ),
			'unknown'          => __( 'Unknown', 'repo-update' ),
			'disabled'         => __( 'Disabled', 'repo-update' ),
		);

		return $labels[ $status ] ?? ucfirst( $status );
	}
}
