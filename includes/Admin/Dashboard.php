<?php
/**
 * Dashboard Admin Page
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Admin;

use WP_REST_Endpoint_Manager\Logger;

/**
 * Dashboard page with statistics and recent activity.
 */
class Dashboard {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = new Logger();
	}

	/**
	 * Render dashboard.
	 */
	public function render() {
		$stats = $this->get_statistics();
		$recent_logs = $this->get_recent_logs();

		?>
		<div class="wrap wp-rem-dashboard">
			<h1><?php esc_html_e( 'REST Endpoint Manager Dashboard', 'wp-rest-endpoint-manager' ); ?></h1>

			<div class="wp-rem-stats-grid">
				<div class="wp-rem-stat-card">
					<h3><?php esc_html_e( 'REST Endpoints', 'wp-rest-endpoint-manager' ); ?></h3>
					<div class="stat-number"><?php echo esc_html( $stats['endpoints'] ); ?></div>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=rest_endpoint' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Manage Endpoints', 'wp-rest-endpoint-manager' ); ?>
					</a>
				</div>

				<div class="wp-rem-stat-card">
					<h3><?php esc_html_e( 'Controllers', 'wp-rest-endpoint-manager' ); ?></h3>
					<div class="stat-number"><?php echo esc_html( $stats['controllers'] ); ?></div>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=rest_controller' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Manage Controllers', 'wp-rest-endpoint-manager' ); ?>
					</a>
				</div>

				<div class="wp-rem-stat-card">
					<h3><?php esc_html_e( 'Ingest Webhooks', 'wp-rest-endpoint-manager' ); ?></h3>
					<div class="stat-number"><?php echo esc_html( $stats['ingest_webhooks'] ); ?></div>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ingest_webhook' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Manage Ingest', 'wp-rest-endpoint-manager' ); ?>
					</a>
				</div>

				<div class="wp-rem-stat-card">
					<h3><?php esc_html_e( 'Dispatch Webhooks', 'wp-rest-endpoint-manager' ); ?></h3>
					<div class="stat-number"><?php echo esc_html( $stats['dispatch_webhooks'] ); ?></div>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=dispatch_webhook' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Manage Dispatch', 'wp-rest-endpoint-manager' ); ?>
					</a>
				</div>
			</div>

			<div class="wp-rem-activity">
				<h2><?php esc_html_e( 'Recent Activity', 'wp-rest-endpoint-manager' ); ?></h2>
				
				<?php if ( ! empty( $recent_logs ) ) : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'wp-rest-endpoint-manager' ); ?></th>
								<th><?php esc_html_e( 'Type', 'wp-rest-endpoint-manager' ); ?></th>
								<th><?php esc_html_e( 'Summary', 'wp-rest-endpoint-manager' ); ?></th>
								<th><?php esc_html_e( 'Status', 'wp-rest-endpoint-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent_logs as $log ) : ?>
								<tr>
									<td><?php echo esc_html( $log['date'] ); ?></td>
									<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $log['type'] ) ) ); ?></td>
									<td><?php echo esc_html( $log['summary'] ); ?></td>
									<td>
										<span class="wp-rem-status-<?php echo esc_attr( $log['status'] ); ?>">
											<?php echo esc_html( ucfirst( $log['status'] ) ); ?>
										</span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e( 'No recent activity.', 'wp-rest-endpoint-manager' ); ?></p>
				<?php endif; ?>

				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-rem-logs' ) ); ?>" class="button">
						<?php esc_html_e( 'View All Logs', 'wp-rest-endpoint-manager' ); ?>
					</a>
				</p>
			</div>

			<div class="wp-rem-quick-actions">
				<h2><?php esc_html_e( 'Quick Actions', 'wp-rest-endpoint-manager' ); ?></h2>
				<p>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=rest_endpoint' ) ); ?>" class="button button-primary button-large">
						<?php esc_html_e( 'Create REST Endpoint', 'wp-rest-endpoint-manager' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=rest_controller' ) ); ?>" class="button button-secondary button-large">
						<?php esc_html_e( 'Create Controller', 'wp-rest-endpoint-manager' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-rem-api-tester' ) ); ?>" class="button button-secondary button-large">
						<?php esc_html_e( 'Test API', 'wp-rest-endpoint-manager' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Get statistics.
	 *
	 * @return array Statistics.
	 */
	private function get_statistics() {
		return array(
			'endpoints' => wp_count_posts( 'rest_endpoint' )->publish,
			'controllers' => wp_count_posts( 'rest_controller' )->publish,
			'ingest_webhooks' => wp_count_posts( 'ingest_webhook' )->publish,
			'dispatch_webhooks' => wp_count_posts( 'dispatch_webhook' )->publish,
		);
	}

	/**
	 * Get recent logs (from all CPTs).
	 *
	 * @return array Recent logs.
	 */
	private function get_recent_logs() {
		global $wpdb;

		$comment_types = array(
			Logger::TYPE_ENDPOINT_LOG,
			Logger::TYPE_INGEST_LOG,
			Logger::TYPE_DISPATCH_LOG,
		);

		$placeholders = implode( ',', array_fill( 0, count( $comment_types ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$comments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_ID, comment_post_ID, comment_content, comment_date, comment_type
				FROM {$wpdb->comments}
				WHERE comment_type IN ($placeholders)
				ORDER BY comment_date DESC
				LIMIT 10",
				$comment_types
			)
		);
		// phpcs:enable

		$logs = array();
		foreach ( $comments as $comment ) {
			$status = get_comment_meta( $comment->comment_ID, 'rem_log_status', true );
			$logs[] = array(
				'id' => $comment->comment_ID,
				'date' => $comment->comment_date,
				'type' => $comment->comment_type,
				'summary' => $comment->comment_content,
				'status' => $status ?: 'unknown',
				'post_id' => $comment->comment_post_ID,
			);
		}

		return $logs;
	}
}
