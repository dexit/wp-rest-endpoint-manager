<?php
/**
 * Dispatch Handler - Sends outgoing webhooks
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Dispatch;

use WP_REST_Endpoint_Manager\Logger;

/**
 * Manages outgoing webhook dispatch with queue and retry logic (ETL: Export).
 */
class Dispatch_Handler {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Template engine instance.
	 *
	 * @var Template_Engine
	 */
	private $template_engine;

	/**
	 * Queue manager instance.
	 *
	 * @var Queue_Manager
	 */
	private $queue_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = new Logger();
		$this->template_engine = new Template_Engine();
		$this->queue_manager = new Queue_Manager();
	}

	/**
	 * Initialize dispatch handler.
	 */
	public function init() {
		// Register event listeners for all active dispatch webhooks
		$this->register_event_listeners();

		// Register queue processor
		add_action( 'wp_rem_process_dispatch_queue', array( $this, 'process_queued_webhook' ), 10, 2 );
	}

	/**
	 * Register event listeners for active webhooks.
	 */
	private function register_event_listeners() {
		$webhooks = get_posts( array(
			'post_type' => 'dispatch_webhook',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => '_rem_status',
					'value' => 'active',
				),
			),
		) );

		foreach ( $webhooks as $webhook ) {
			$trigger_events = get_post_meta( $webhook->ID, '_rem_trigger_events', true );
			if ( is_array( $trigger_events ) ) {
				foreach ( $trigger_events as $event ) {
					add_action( $event, function() use ( $webhook, $event ) {
						$args = func_get_args();
						$this->trigger_webhook( $webhook->ID, $event, $args );
					}, 10, 10 );
				}
			}
		}
	}

	/**
	 * Trigger a webhook (ETL: Extract data from WordPress event).
	 *
	 * @param int    $webhook_id Webhook post ID.
	 * @param string $event_name Triggering event name.
	 * @param array  $event_data Event data passed to the hook.
	 */
	public function trigger_webhook( $webhook_id, $event_name, $event_data = array() ) {
		// Queue the webhook for async processing
		$this->queue_manager->enqueue( $webhook_id, array(
			'event_name' => $event_name,
			'event_data' => $event_data,
			'triggered_at' => current_time( 'mysql' ),
		) );
	}

	/**
	 * Process queued webhook (called by queue worker).
	 *
	 * @param int   $webhook_id Webhook ID.
	 * @param array $context Context data.
	 */
	public function process_queued_webhook( $webhook_id, $context ) {
		$this->send_webhook( $webhook_id, $context, 0 );
	}

	/**
	 * Send webhook immediately (ETL: Transform and Load).
	 *
	 * @param int   $webhook_id Webhook ID.
	 * @param array $context Context data.
	 * @param int   $retry_count Current retry attempt.
	 * @return array Result with success status.
	 */
	public function send_webhook( $webhook_id, $context, $retry_count = 0 ) {
		$start_time = microtime( true );

		// STEP 1: EXTRACT - Get webhook configuration
		$target_url = get_post_meta( $webhook_id, '_rem_target_url', true );
		$http_method = get_post_meta( $webhook_id, '_rem_http_method', true ) ?: 'POST';
		$payload_template = get_post_meta( $webhook_id, '_rem_payload_template', true );
		$headers_json = get_post_meta( $webhook_id, '_rem_headers', true );
		$timeout = get_post_meta( $webhook_id, '_rem_timeout', true ) ?: 30;
		$max_retries = get_post_meta( $webhook_id, '_rem_retry_count', true ) ?: 3;
		$retry_delay = get_post_meta( $webhook_id, '_rem_retry_delay', true ) ?: 60;

		if ( empty( $target_url ) ) {
			return array( 'success' => false, 'error' => 'No target URL configured' );
		}

		// STEP 2: TRANSFORM - Build payload from template
		$payload = $this->build_payload( $payload_template, $context );

		// STEP 3: TRANSFORM - Prepare headers
		$headers = array( 'Content-Type' => 'application/json' );
		if ( ! empty( $headers_json ) ) {
			$custom_headers = json_decode( $headers_json, true );
			if ( is_array( $custom_headers ) ) {
				$headers = array_merge( $headers, $custom_headers );
			}
		}

		// STEP 4: LOAD - Send HTTP request
		$request_args = array(
			'method' => $http_method,
			'headers' => $headers,
			'body' => wp_json_encode( $payload ),
			'timeout' => $timeout,
			'blocking' => true,
		);

		$response = wp_remote_request( $target_url, $request_args );

		// STEP 5: VALIDATE - Check response
		$request_data = array(
			'url' => $target_url,
			'method' => $http_method,
			'headers' => $headers,
			'body' => $payload,
			'timeout' => $timeout,
			'triggered_by' => $context['event_name'] ?? 'manual',
		);

		if ( is_wp_error( $response ) ) {
			// Handle error - retry if configured
			$response_data = array(
				'http_code' => 0,
				'error' => $response->get_error_message(),
			);

			$this->logger->log_dispatch( $webhook_id, $request_data, $response_data, 'error', $retry_count );

			// Retry logic
			if ( $retry_count < $max_retries ) {
				$this->schedule_retry( $webhook_id, $context, $retry_count + 1, $retry_delay );
				return array( 'success' => false, 'error' => $response->get_error_message(), 'will_retry' => true );
			}

			return array( 'success' => false, 'error' => $response->get_error_message() );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$response_headers = wp_remote_retrieve_headers( $response );
		$response_time = microtime( true ) - $start_time;

		$response_data = array(
			'http_code' => $http_code,
			'headers' => $response_headers->getAll(),
			'body' => json_decode( $response_body, true ) ?: $response_body,
			'size' => strlen( $response_body ),
			'time' => $response_time,
		);

		// Check if response indicates success (2xx status codes)
		$is_success = $http_code >= 200 && $http_code < 300;

		if ( ! $is_success && $retry_count < $max_retries ) {
			$this->logger->log_dispatch( $webhook_id, $request_data, $response_data, 'error', $retry_count );
			$this->schedule_retry( $webhook_id, $context, $retry_count + 1, $retry_delay );
			return array( 'success' => false, 'http_code' => $http_code, 'will_retry' => true );
		}

		// Log successful dispatch
		$status = $is_success ? 'success' : 'error';
		$this->logger->log_dispatch( $webhook_id, $request_data, $response_data, $status, $retry_count );

		// Trigger default post-dispatch action
		do_action( 'wp_rem_dispatch_sent', $webhook_id, $request_data, $response_data, $is_success );

		// Trigger custom actions configured in admin UI
		$custom_actions = get_post_meta( $webhook_id, '_rem_custom_actions', true );
		if ( ! empty( $custom_actions ) && is_array( $custom_actions ) ) {
			foreach ( $custom_actions as $action_name ) {
				do_action( $action_name, $webhook_id, $request_data, $response_data, $is_success );
			}
		}

		return array(
			'success' => $is_success,
			'http_code' => $http_code,
			'response' => $response_data,
		);
	}

	/**
	 * Build payload from template and context data.
	 *
	 * @param string $template Payload template with placeholders.
	 * @param array  $context Context data.
	 * @return array Built payload.
	 */
	private function build_payload( $template, $context ) {
		if ( empty( $template ) ) {
			return $context;
		}

		// Try to parse as JSON template
		$template_array = json_decode( $template, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// Not JSON, return as is
			return array( 'data' => $template );
		}

		// Render template with context data
		return $this->template_engine->render( $template_array, $context );
	}

	/**
	 * Schedule retry attempt.
	 *
	 * @param int   $webhook_id Webhook ID.
	 * @param array $context Context data.
	 * @param int   $retry_count Retry attempt number.
	 * @param int   $delay Delay in seconds.
	 */
	private function schedule_retry( $webhook_id, $context, $retry_count, $delay ) {
		// Use exponential backoff
		$backoff_delay = $delay * pow( 2, $retry_count - 1 );

		// Schedule using WordPress cron
		wp_schedule_single_event(
			time() + $backoff_delay,
			'wp_rem_process_dispatch_queue',
			array( $webhook_id, $context )
		);
	}

	/**
	 * Manually trigger a webhook (for testing).
	 *
	 * @param int   $webhook_id Webhook ID.
	 * @param array $test_data Optional test data.
	 * @return array Result.
	 */
	public function manual_trigger( $webhook_id, $test_data = array() ) {
		$context = array(
			'event_name' => 'manual_trigger',
			'event_data' => $test_data,
			'triggered_at' => current_time( 'mysql' ),
		);

		return $this->send_webhook( $webhook_id, $context, 0 );
	}
}
