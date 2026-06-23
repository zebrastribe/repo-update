<?php
/**
 * Repositories list table.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Admin\ListTables;

use RepoUpdate\Repository\Repository;
use RepoUpdate\Repository\RepositoryManager;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Repository management list table.
 */
final class RepositoriesListTable extends \WP_List_Table {

	/**
	 * @var RepositoryManager
	 */
	private RepositoryManager $repositories;

	/**
	 * @param RepositoryManager $repositories Repository manager.
	 */
	public function __construct( RepositoryManager $repositories ) {
		$this->repositories = $repositories;

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
			'repository' => __( 'Repository', 'repo-update' ),
			'type'       => __( 'Type', 'repo-update' ),
			'target'     => __( 'Target', 'repo-update' ),
			'branch'     => __( 'Branch', 'repo-update' ),
			'enabled'    => __( 'Enabled', 'repo-update' ),
			'actions'    => __( 'Actions', 'repo-update' ),
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
				return esc_html( $item->full_name() );
			case 'type':
				return esc_html( ucfirst( $item->type ) );
			case 'target':
				return esc_html( $item->target_slug );
			case 'branch':
				return esc_html( $item->branch );
			case 'enabled':
				return $item->enabled ? esc_html__( 'Yes', 'repo-update' ) : esc_html__( 'No', 'repo-update' );
			case 'actions':
				$edit = sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=repo-update-repositories&action=edit&id=' . $item->id ) ),
					esc_html__( 'Edit', 'repo-update' )
				);
				$delete = sprintf(
					'<a href="%s" class="repo-update-confirm" data-message="%s">%s</a>',
					esc_url( wp_nonce_url( admin_url( 'admin.php?page=repo-update-repositories&action=delete&id=' . $item->id ), 'repo_update_delete_' . $item->id ) ),
					esc_attr__( 'Delete this repository?', 'repo-update' ),
					esc_html__( 'Delete', 'repo-update' )
				);

				return $edit . ' | ' . $delete;
			default:
				return '';
		}
	}
}
