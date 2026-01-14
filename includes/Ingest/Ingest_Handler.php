<?php
/**
 * Ingest Handler - Receives and processes incoming webhooks
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Ingest;

use WP_REST_Endpoint_Manager\Logger;

/**
 * Handles incoming webhook requests with full ETL pipeline.
 */
class Ingest_Handler {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Data mapper instance.
	 *
	 * @var Data_Mapper
	 */
	private $data_mapper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = new Logger();
		$this->data_mapper = new Data_Mapper();
	}

	/**
	 * Register ingest routes.
	 */
	public function register_routes() {
		register_rest_route( 'rem/v1', '/ingest/(?P<slug>[a-zA-Z0-9-_]+)', array(
			'methods' => 'POST',
			'callback' => array( $this, 'handle_ingest' ),
			'permission_callback' => '__return_true', // Authentication handled internally
		) );
	}

	/**
	 * Handle incoming webhook request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function handle_ingest( $request ) {
		$start_time = microtime( true );
		$slug = $request['slug'];

		// STEP 1: EXTRACT - Find webhook configuration
		$webhook = $this->get_webhook_by_slug( $slug );
		if ( ! $webhook ) {
			return new \WP_Error( 'webhook_not_found', 'Webhook not found', array( 'status' => 404 ) );
		}

		$webhook_id = $webhook->ID;
		$status = get_post_meta( $webhook_id, '_rem_status', true );

		if ( $status !== 'active' ) {
			return new \WP_Error( 'webhook_inactive', 'Webhook is not active', array( 'status' => 403 ) );
		}

		// STEP 2: VALIDATE - Authenticate request
		$token_valid = $this->verify_token( $request, $webhook_id );
		if ( ! $token_valid ) {
			$this->log_ingest_request( $webhook_id, $request, array(
				'http_code' => 401,
				'error' => 'Invalid authentication token',
			), 'error', microtime( true ) - $start_time, false );

			return new \WP_Error( 'authentication_failed', 'Invalid authentication token', array( 'status' => 401 ) );
		}

		// Verify IP whitelist if configured
		$allowed_ips = get_post_meta( $webhook_id, '_rem_allowed_ips', true );
		if ( ! empty( $allowed_ips ) ) {
			$client_ip = $this->get_client_ip();
			$ip_list = array_map( 'trim', explode( ',', $allowed_ips ) );
			if ( ! in_array( $client_ip, $ip_list, true ) ) {
				$this->log_ingest_request( $webhook_id, $request, array(
					'http_code' => 403,
					'error' => 'IP address not allowed',
				), 'error', microtime( true ) - $start_time, false );

				return new \WP_Error( 'ip_not_allowed', 'IP address not allowed', array( 'status' => 403 ) );
			}
		}

		// STEP 3: PARSE - Extract payload
		$raw_data = $this->parse_request_data( $request );

		// STEP 4: VALIDATE - Validate against schema
		$validation_rules = get_post_meta( $webhook_id, '_rem_validation_rules', true );
		if ( ! empty( $validation_rules ) ) {
			$validation_schema = json_decode( $validation_rules, true );
			$validator = new \WP_REST_Endpoint_Manager\Validator();
			$validation_result = $validator->validate( $raw_data, $validation_schema );

			if ( ! $validation_result['valid'] ) {
				$this->log_ingest_request( $webhook_id, $request, array(
					'http_code' => 400,
					'error' => 'Validation failed',
					'errors' => $validation_result['errors'],
				), 'error', microtime( true ) - $start_time, true );

				return new \WP_Error(
					'validation_failed',
					'Payload validation failed',
					array( 'status' => 400, 'errors' => $validation_result['errors'] )
				);
			}
		}

		// STEP 5: TRANSFORM - Map incoming data
		$data_mapping = get_post_meta( $webhook_id, '_rem_data_mapping', true );
		$mapped_data = $raw_data;

		if ( ! empty( $data_mapping ) ) {
			$mapping_rules = json_decode( $data_mapping, true );
			if ( $mapping_rules ) {
				$mapped_data = $this->data_mapper->map( $raw_data, $mapping_rules );
			}
		}

		// STEP 6: LOAD - Trigger WordPress actions
		$actions_triggered = array();
		
		// Trigger default built-in action
		do_action( 'wp_rem_ingest_received', $webhook_id, $mapped_data, $raw_data );
		$actions_triggered[] = 'wp_rem_ingest_received';

		// Trigger custom actions configured in admin UI
		$custom_actions = get_post_meta( $webhook_id, '_rem_custom_actions', true );
		if ( ! empty( $custom_actions ) && is_array( $custom_actions ) ) {
			foreach ( $custom_actions as $action_name ) {
				do_action( $action_name, $mapped_data, $raw_data, $webhook_id );
				$actions_triggered[] = $action_name;
			}
		}

		// STEP 7: RESPOND
		$response_data = array(
			'success' => true,
			'message' => 'Webhook received and processed',
			'webhook_id' => $webhook_id,
			'actions_triggered' => $actions_triggered,
			'timestamp' => current_time( 'mysql' ),
		);

		$execution_time = microtime( true ) - $start_time;

		// Log successful request
		$this->log_ingest_request( $webhook_id, $request, array(
			'http_code' => 200,
			'body' => $response_data,
		), 'success', $execution_time, true, $mapped_data, $actions_triggered );

		return new \WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Get webhook by slug.
	 *
	 * @param string $slug Webhook slug.
	 * @return \WP_Post|null Webhook post or null.
	 */
	private function get_webhook_by_slug( $slug ) {
		$webhooks = get_posts( array(
			'post_type' => 'ingest_webhook',
			'post_status' => 'publish',
			'meta_key' => '_rem_webhook_slug',
			'meta_value' => $slug,
			'posts_per_page' => 1,
		) );

		return ! empty( $webhooks ) ? $webhooks[0] : null;
	}

	/**
	 * Verify authentication token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param int              $webhook_id Webhook ID.
	 * @return bool True if valid.
	 */
	private function verify_token( $request, $webhook_id ) {
		$expected_token = get_post_meta( $webhook_id, '_rem_webhook_token', true );
		if ( empty( $expected_token ) ) {
			return true; // No token required
		}

		// Check header
		$token = $request->get_header( 'X-Webhook-Token' );
		
		// Check query parameter
		if ( empty( $token ) ) {
			$token = $request->get_param( 'token' );
		}

		return hash_equals( $expected_token, $token );
	}

	/**
	 * Parse request data (ETL: Extract).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array Parsed data.
	 */
	private function parse_request_data( $request ) {
		$content_type = $request->get_content_type();

		// Try JSON first
		$json_params = $request->get_json_params();
		if ( ! empty( $json_params ) ) {
			return $json_params;
		}

		// Try body parameters
		$body_params = $request->get_body_params();
		if ( ! empty( $body_params ) ) {
			return $body_params;
		}

		// Try raw body
		$raw_body = $request->get_body();
		if ( ! empty( $raw_body ) ) {
			// Try to decode as JSON
			$decoded = json_decode( $raw_body, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $decoded;
			}

			// Return as string
			return array( 'raw' => $raw_body );
		}

		return array();
	}

	/**
	 * Log ingest request.
	 *
	 * @param int              $webhook_id Webhook ID.
	 * @param \WP_REST_Request $request Request.
	 * @param array            $response_data Response data.
	 * @param string           $status Status.
	 * @param float            $execution_time Execution time.
	 * @param bool             $token_valid Token validity.
	 * @param array            $mapped_data Mapped data.
	 * @param array            $actions_triggered Actions triggered.
	 */
	private function log_ingest_request( $webhook_id, $request, $response_data, $status, $execution_time, $token_valid = false, $mapped_data = null, $actions_triggered = array() ) {
		$request_data = array(
			'method' => $request->get_method(),
			'headers' => $request->get_headers(),
			'body' => $this->parse_request_data( $request ),
			'token_valid' => $token_valid,
			'mapped_data' => $mapped_data,
			'actions_triggered' => $actions_triggered,
		);

		$this->logger->log_ingest( $webhook_id, $request_data, $response_data, $status, $execution_time );
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
}
