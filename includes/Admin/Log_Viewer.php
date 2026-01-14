<?php
/**
 * Log Viewer Admin Page
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Admin;

use WP_REST_Endpoint_Manager\Logger;

/**
 * Log viewer with filtering, search, and export.
 */
class Log_Viewer {

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

		// Register AJAX handlers.
		add_action( 'wp_ajax_wp_rem_export_logs', array( $this, 'ajax_export_logs' ) );
		add_action( 'wp_ajax_wp_rem_delete_log', array( $this, 'ajax_delete_log' ) );
	}

	/**
	 * Render log viewer page.
	 */
	public function render() {
		// Get filter parameters.
		$endpoint_id = isset( $_GET['endpoint'] ) ? absint( $_GET['endpoint'] ) : 0;
		$log_type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

		// Get logs.
		$logs = $this->get_filtered_logs( $endpoint_id, $log_type, $status_filter );

		?>
		<div class="wrap wp-rem-logs">
			<h1><?php esc_html_e( 'Activity Logs', 'wp-rest-endpoint-manager' ); ?></h1>

			<div class="wp-rem-log-filters">
				<form method="get" action="">
					<input type="hidden" name="page" value="wp-rem-logs" />

					<select name="type" id="log-type-filter">
						<option value=""><?php esc_html_e( 'All Types', 'wp-rest-endpoint-manager' ); ?></option>
						<option value="endpoint_log" <?php selected( $log_type, 'endpoint_log' ); ?>><?php esc_html_e( 'Endpoint Logs', 'wp-rest-endpoint-manager' ); ?></option>
						<option value="ingest_log" <?php selected( $log_type, 'ingest_log' ); ?>><?php esc_html_e( 'Ingest Logs', 'wp-rest-endpoint-manager' ); ?></option>
						<option value="dispatch_log" <?php selected( $log_type, 'dispatch_log' ); ?>><?php esc_html_e( 'Dispatch Logs', 'wp-rest-endpoint-manager' ); ?></option>
					</select>

					<select name="status" id="log-status-filter">
						<option value=""><?php esc_html_e( 'All Statuses', 'wp-rest-endpoint-manager' ); ?></option>
						<option value="success" <?php selected( $status_filter, 'success' ); ?>><?php esc_html_e( 'Success', 'wp-rest-endpoint-manager' ); ?></option>
						<option value="error" <?php selected( $status_filter, 'error' ); ?>><?php esc_html_e( 'Error', 'wp-rest-endpoint-manager' ); ?></option>
						<option value="warning" <?php selected( $status_filter, 'warning' ); ?>><?php esc_html_e( 'Warning', 'wp-rest-endpoint-manager' ); ?></option>
					</select>

					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-rest-endpoint-manager' ); ?></button>
					<button type="button" class="button" id="export-logs"><?php esc_html_e( 'Export CSV', 'wp-rest-endpoint-manager' ); ?></button>
				</form>
			</div>

			<?php if ( ! empty( $logs ) ) : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 150px;"><?php esc_html_e( 'Date', 'wp-rest-endpoint-manager' ); ?></th>
							<th style="width: 100px;"><?php esc_html_e( 'Type', 'wp-rest-endpoint-manager' ); ?></th>
							<th><?php esc_html_e( 'Summary', 'wp-rest-endpoint-manager' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Status', 'wp-rest-endpoint-manager' ); ?></th>
							<th style="width: 150px;"><?php esc_html_e( 'Actions', 'wp-rest-endpoint-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<tr data-log-id="<?php echo esc_attr( $log['id'] ); ?>">
								<td><?php echo esc_html( $log['date'] ); ?></td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', str_replace( '_log', '', $log['type'] ) ) ) ); ?></td>
								<td><?php echo esc_html( $log['summary'] ); ?></td>
								<td>
									<span class="wp-rem-status-<?php echo esc_attr( $log['status'] ); ?>">
										<?php echo esc_html( ucfirst( $log['status'] ) ); ?>
									</span>
								</td>
								<td>
									<button class="button button-small view-log-details" data-log-id="<?php echo esc_attr( $log['id'] ); ?>">
										<?php esc_html_e( 'Details', 'wp-rest-endpoint-manager' ); ?>
									</button>
									<button class="button button-small delete-log" data-log-id="<?php echo esc_attr( $log['id'] ); ?>">
										<?php esc_html_e( 'Delete', 'wp-rest-endpoint-manager' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No logs found.', 'wp-rest-endpoint-manager' ); ?></p>
			<?php endif; ?>

			<!-- Log Detail Modal -->
			<div id="log-detail-modal" style="display:none;">
				<div class="log-detail-content">
					<span class="close-modal">&times;</span>
					<h2><?php esc_html_e( 'Log Details', 'wp-rest-endpoint-manager' ); ?></h2>
					<div id="log-detail-body"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get filtered logs.
	 *
	 * @param int    $endpoint_id Endpoint ID filter.
	 * @param string $log_type Log type filter.
	 * @param string $status Status filter.
	 * @return array Logs.
	 */
	private function get_filtered_logs( $endpoint_id, $log_type, $status ) {
		global $wpdb;

		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $log_type ) ) {
			$where[] = 'comment_type = %s';
			$params[] = $log_type;
		} else {
			$comment_types = array(
				Logger::TYPE_ENDPOINT_LOG,
				Logger::TYPE_INGEST_LOG,
				Logger::TYPE_DISPATCH_LOG,
			);
			$placeholders = implode( ',', array_fill( 0, count( $comment_types ), '%s' ) );
			$where[] = "comment_type IN ($placeholders)";
			$params = array_merge( $params, $comment_types );
		}

		if ( $endpoint_id > 0 ) {
			$where[] = 'comment_post_ID = %d';
			$params[] = $endpoint_id;
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$comments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_ID, comment_post_ID, comment_content, comment_date, comment_type
				FROM {$wpdb->comments}
				WHERE $where_clause
				ORDER BY comment_date DESC
				LIMIT 100",
				$params
			)
		);
		// phpcs:enable

		$logs = array();
		foreach ( $comments as $comment ) {
			$log_status = get_comment_meta( $comment->comment_ID, 'rem_log_status', true );

			// Apply status filter.
			if ( ! empty( $status ) && $log_status !== $status ) {
				continue;
			}

			$logs[] = array(
				'id' => $comment->comment_ID,
				'date' => $comment->comment_date,
				'type' => $comment->comment_type,
				'summary' => $comment->comment_content,
				'status' => $log_status ?: 'unknown',
				'post_id' => $comment->comment_post_ID,
			);
		}

		return $logs;
	}

	/**
	 * AJAX handler for exporting logs.
	 */
	public function ajax_export_logs() {
		check_ajax_referer( 'wp_rest', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$endpoint_id = isset( $_POST['endpoint_id'] ) ? absint( $_POST['endpoint_id'] ) : 0;
		$log_type = isset( $_POST['log_type'] ) ? sanitize_text_field( $_POST['log_type'] ) : '';

		$csv = $this->logger->export_logs_csv( $endpoint_id, $log_type );

		wp_send_json_success( array( 'csv' => $csv ) );
	}

	/**
	 * AJAX handler for deleting a log.
	 */
	public function ajax_delete_log() {
		check_ajax_referer( 'wp_rest', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;

		if ( $log_id > 0 ) {
			wp_delete_comment( $log_id, true );
			wp_send_json_success();
		}

		wp_send_json_error();
	}
}
