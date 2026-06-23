<?php
/**
 * Logs admin page.
 *
 * @package RepoUpdate
 */

declare(strict_types=1);

namespace RepoUpdate\Admin;

use RepoUpdate\Admin\ListTables\LogsListTable;
use RepoUpdate\Helpers\Capabilities;
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
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'repo-update' ) );
		}

		$table = new LogsListTable( $this->logger );
		$table->prepare_items();

		?>
		<div class="wrap repo-update-wrap">
			<h1><?php esc_html_e( 'Repo Update Logs', 'repo-update' ); ?></h1>
			<?php $table->display(); ?>
		</div>
		<?php
	}
}
