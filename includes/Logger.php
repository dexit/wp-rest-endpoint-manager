<?php
/**
 * Logger Class - Uses WordPress Comments for Extensive Logging
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager;

/**
 * Comprehensive logging system using WordPress comments.
 * Each log entry is a comment attached to its parent CPT post.
 */
class Logger {

	/**
	 * Comment types for different log categories.
	 */
	const TYPE_ENDPOINT_LOG = 'endpoint_log';
	const TYPE_INGEST_LOG = 'ingest_log';
	const TYPE_DISPATCH_LOG = 'dispatch_log';

	/**
	 * Log an endpoint request/response.
	 *
	 * @param int    $endpoint_id Post ID of the endpoint.
	 * @param array  $request_data Request data.
	 * @param array  $response_data Response data.
	 * @param string $status Status (success/error/warning).
	 * @param float  $execution_time Execution time in seconds.
	 * @return int|false Comment ID on success, false on failure.
	 */
	public function log_endpoint( $endpoint_id, $request_data, $response_data, $status = 'success', $execution_time = 0 ) {
		return $this->create_log(
			$endpoint_id,
			self::TYPE_ENDPOINT_LOG,
			array(
				'timestamp' => current_time( 'mysql' ),
				'status' => $status,
				'http_code' => $response_data['http_code'] ?? 200,
				'method' => $request_data['method'] ?? 'GET',
				'request' => array(
					'headers' => $request_data['headers'] ?? array(),
					'body' => $request_data['body'] ?? null,
					'query_params' => $request_data['query_params'] ?? array(),
					'ip_address' => $this->get_client_ip(),
					'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
				),
				'response' => array(
					'headers' => $response_data['headers'] ?? array(),
					'body' => $response_data['body'] ?? null,
					'size' => $response_data['size'] ?? 0,
				),
				'execution_time' => $execution_time,
				'memory_usage' => memory_get_peak_usage( true ),
				'error' => $response_data['error'] ?? null,
			)
		);
	}

	/**
	 * Log an ingest webhook request.
	 *
	 * @param int    $webhook_id Post ID of the webhook.
	 * @param array  $request_data Request data.
	 * @param array  $response_data Response data.
	 * @param string $status Status.
	 * @param float  $execution_time Execution time.
	 * @return int|false Comment ID on success, false on failure.
	 */
	public function log_ingest( $webhook_id, $request_data, $response_data, $status = 'success', $execution_time = 0 ) {
		return $this->create_log(
			$webhook_id,
			self::TYPE_INGEST_LOG,
			array(
				'timestamp' => current_time( 'mysql' ),
				'status' => $status,
				'http_code' => $response_data['http_code'] ?? 200,
				'method' => $request_data['method'] ?? 'POST',
				'request' => array(
					'headers' => $request_data['headers'] ?? array(),
					'body' => $request_data['body'] ?? null,
					'ip_address' => $this->get_client_ip(),
					'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
					'token_valid' => $request_data['token_valid'] ?? false,
				),
				'response' => array(
					'body' => $response_data['body'] ?? null,
				),
				'mapped_data' => $request_data['mapped_data'] ?? null,
				'actions_triggered' => $request_data['actions_triggered'] ?? array(),
				'execution_time' => $execution_time,
				'memory_usage' => memory_get_peak_usage( true ),
				'error' => $response_data['error'] ?? null,
			)
		);
	}

	/**
	 * Log a dispatch webhook request.
	 *
	 * @param int    $webhook_id Post ID of the webhook.
	 * @param array  $request_data Request data sent.
	 * @param array  $response_data Response received.
	 * @param string $status Status.
	 * @param int    $retry_count Retry attempt number.
	 * @return int|false Comment ID on success, false on failure.
	 */
	public function log_dispatch( $webhook_id, $request_data, $response_data, $status = 'success', $retry_count = 0 ) {
		return $this->create_log(
			$webhook_id,
			self::TYPE_DISPATCH_LOG,
			array(
				'timestamp' => current_time( 'mysql' ),
				'status' => $status,
				'http_code' => $response_data['http_code'] ?? 0,
				'method' => $request_data['method'] ?? 'POST',
				'request' => array(
					'url' => $request_data['url'] ?? '',
					'headers' => $request_data['headers'] ?? array(),
					'body' => $request_data['body'] ?? null,
					'timeout' => $request_data['timeout'] ?? 30,
				),
				'response' => array(
					'headers' => $response_data['headers'] ?? array(),
					'body' => $response_data['body'] ?? null,
					'size' => $response_data['size'] ?? 0,
					'time' => $response_data['time'] ?? 0,
				),
				'retry_count' => $retry_count,
				'triggered_by' => $request_data['triggered_by'] ?? '',
				'error' => $response_data['error'] ?? null,
			)
		);
	}

	/**
	 * Create a log entry as a comment.
	 *
	 * @param int    $post_id Post ID to attach the comment to.
	 * @param string $comment_type Comment type.
	 * @param array  $log_data Log data array.
	 * @return int|false Comment ID on success, false on failure.
	 */
	private function create_log( $post_id, $comment_type, $log_data ) {
		// Check if logging is enabled.
		if ( ! get_option( 'wp_rem_enable_logging', true ) ) {
			return false;
		}

		// Create a human-readable comment content.
		$comment_content = $this->format_log_summary( $log_data );

		$comment_data = array(
			'comment_post_ID' => $post_id,
			'comment_content' => $comment_content,
			'comment_type' => $comment_type,
			'comment_author' => 'WP REST Endpoint Manager',
			'comment_author_email' => 'noreply@' . wp_parse_url( home_url(), PHP_URL_HOST ),
			'comment_approved' => 1,
			'comment_meta' => array(
				'rem_log_data' => wp_json_encode( $log_data ),
				'rem_log_status' => $log_data['status'],
				'rem_log_timestamp' => time(),
			),
		);

		$comment_id = wp_insert_comment( $comment_data );

		// Schedule cleanup if enabled.
		if ( $comment_id && get_option( 'wp_rem_auto_cleanup', true ) ) {
			$this->schedule_cleanup();
		}

		return $comment_id;
	}

	/**
	 * Format log data into a human-readable summary.
	 *
	 * @param array $log_data Log data.
	 * @return string Formatted summary.
	 */
	private function format_log_summary( $log_data ) {
		$status = strtoupper( $log_data['status'] );
		$method = $log_data['method'] ?? 'N/A';
		$http_code = $log_data['http_code'] ?? 'N/A';
		$execution_time = isset( $log_data['execution_time'] ) ? number_format( $log_data['execution_time'], 3 ) . 's' : 'N/A';

		$summary = sprintf(
			"[%s] %s Request - HTTP %s - %s",
			$status,
			$method,
			$http_code,
			$execution_time
		);

		if ( ! empty( $log_data['error'] ) ) {
			$summary .= "\nError: " . $log_data['error'];
		}

		return $summary;
	}

	/**
	 * Get logs for a specific post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $comment_type Optional comment type filter.
	 * @param array  $args Additional query arguments.
	 * @return array Array of log entries.
	 */
	public function get_logs( $post_id, $comment_type = '', $args = array() ) {
		$defaults = array(
			'post_id' => $post_id,
			'status' => 'approve',
			'number' => 100,
			'orderby' => 'comment_date_gmt',
			'order' => 'DESC',
		);

		if ( ! empty( $comment_type ) ) {
			$defaults['type'] = $comment_type;
		}

		$args = wp_parse_args( $args, $defaults );
		$comments = get_comments( $args );

		$logs = array();
		foreach ( $comments as $comment ) {
			$log_data = get_comment_meta( $comment->comment_ID, 'rem_log_data', true );
			if ( $log_data ) {
				$logs[] = array(
					'id' => $comment->comment_ID,
					'date' => $comment->comment_date,
					'summary' => $comment->comment_content,
					'data' => json_decode( $log_data, true ),
					'status' => get_comment_meta( $comment->comment_ID, 'rem_log_status', true ),
				);
			}
		}

		return $logs;
	}

	/**
	 * Delete logs older than retention period.
	 */
	public function cleanup_old_logs() {
		$retention_days = get_option( 'wp_rem_log_retention_days', 90 );
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		global $wpdb;

		// Get old log comments.
		$comment_types = array( self::TYPE_ENDPOINT_LOG, self::TYPE_INGEST_LOG, self::TYPE_DISPATCH_LOG );
		$placeholders = implode( ',', array_fill( 0, count( $comment_types ), '%s' ) );

		$query = $wpdb->prepare(
			"SELECT comment_ID FROM {$wpdb->comments} 
			WHERE comment_type IN ($placeholders) 
			AND comment_date < %s",
			array_merge( $comment_types, array( $cutoff_date ) )
		);

		$old_comments = $wpdb->get_col( $query );

		// Delete old comments.
		foreach ( $old_comments as $comment_id ) {
			wp_delete_comment( $comment_id, true );
		}

		return count( $old_comments );
	}

	/**
	 * Schedule log cleanup.
	 */
	private function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'wp_rem_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_rem_cleanup_logs' );
		}
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	private function get_client_ip() {
		$ip_keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ips = explode( ',', $_SERVER[ $key ] );
				$ip = trim( $ips[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	/**
	 * Export logs to CSV.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $comment_type Comment type.
	 * @return string CSV content.
	 */
	public function export_logs_csv( $post_id, $comment_type = '' ) {
		$logs = $this->get_logs( $post_id, $comment_type, array( 'number' => -1 ) );

		$csv = "Date,Status,Method,HTTP Code,Execution Time,IP Address,Error\n";

		foreach ( $logs as $log ) {
			$data = $log['data'];
			$csv .= sprintf(
				'"%s","%s","%s","%s","%s","%s","%s"' . "\n",
				$log['date'],
				$data['status'] ?? '',
				$data['method'] ?? '',
				$data['http_code'] ?? '',
				isset( $data['execution_time'] ) ? number_format( $data['execution_time'], 3 ) : '',
				$data['request']['ip_address'] ?? '',
				$data['error'] ?? ''
			);
		}

		return $csv;
	}
}

// Register cleanup cron.
add_action( 'wp_rem_cleanup_logs', array( new Logger(), 'cleanup_old_logs' ) );
