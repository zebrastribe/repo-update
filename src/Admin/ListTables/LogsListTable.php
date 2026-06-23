<?php
/**
 * Logs list table.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Admin\ListTables;

use RepoUpdate\Logger\Logger;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Activity logs list table.
 */
final class LogsListTable extends \WP_List_Table {

	/**
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;

		parent::__construct(
			array(
				'plural'   => 'logs',
				'singular' => 'log',
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
			'created_at'    => __( 'Date', 'repo-update' ),
			'level'         => __( 'Level', 'repo-update' ),
			'action'        => __( 'Action', 'repo-update' ),
			'message'       => __( 'Message', 'repo-update' ),
			'repository_id' => __( 'Repository ID', 'repo-update' ),
		);
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items(): void {
		$this->items = $this->logger->get_logs( 200 );
	}

	/**
	 * Render a column.
	 *
	 * @param object $item        Row item.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		if ( ! isset( $item->{$column_name} ) ) {
			return '';
		}

		return esc_html( (string) $item->{$column_name} );
	}
}
