<?php
/**
 * Logs admin page.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Admin;

use RepoUpdate\Logger\Logger;

/**
 * Displays plugin activity logs.
 */
final class LogsPage {

	/**
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Render logs page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'repo-update' ) );
		}

		$logs = $this->logger->get_logs( 200 );
		?>
		<div class="wrap repo-update-wrap">
			<h1><?php esc_html_e( 'Repo Update Logs', 'repo-update' ); ?></h1>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'repo-update' ); ?></th>
						<th><?php esc_html_e( 'Level', 'repo-update' ); ?></th>
						<th><?php esc_html_e( 'Action', 'repo-update' ); ?></th>
						<th><?php esc_html_e( 'Message', 'repo-update' ); ?></th>
						<th><?php esc_html_e( 'Repository ID', 'repo-update' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No logs found.', 'repo-update' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $log->created_at ); ?></td>
								<td><?php echo esc_html( (string) $log->level ); ?></td>
								<td><?php echo esc_html( (string) $log->action ); ?></td>
								<td><?php echo esc_html( (string) $log->message ); ?></td>
								<td><?php echo esc_html( (string) ( $log->repository_id ?? '' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
