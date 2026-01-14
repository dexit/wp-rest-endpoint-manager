<?php
/**
 * REST API Endpoint Handler
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\REST_API;

use WP_REST_Endpoint_Manager\Logger;
use WP_REST_Endpoint_Manager\Rate_Limiter;

/**
 * Dynamically registers and handles custom REST endpoints.
 */
class Endpoint_Handler {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Rate limiter instance.
	 *
	 * @var Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = new Logger();
		$this->rate_limiter = new Rate_Limiter();
	}

	/**
	 * Register all active custom endpoints.
	 */
	public function register_routes() {
		$endpoints = get_posts( array(
			'post_type' => 'rest_endpoint',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => '_rem_status',
					'value' => 'active',
				),
			),
		) );

		foreach ( $endpoints as $endpoint ) {
			$this->register_single_route( $endpoint->ID );
		}
	}

	/**
	 * Register a single route.
	 *
	 * @param int $endpoint_id Endpoint post ID.
	 */
	private function register_single_route( $endpoint_id ) {
		$namespace = get_post_meta( $endpoint_id, '_rem_namespace', true );
		$route = get_post_meta( $endpoint_id, '_rem_route', true );
		$methods = get_post_meta( $endpoint_id, '_rem_methods', true );

		if ( empty( $namespace ) || empty( $route ) || empty( $methods ) ) {
			return;
		}

		$args = array(
			'methods' => $methods,
			'callback' => function( $request ) use ( $endpoint_id ) {
				return $this->handle_request( $endpoint_id, $request );
			},
			'permission_callback' => function( $request ) use ( $endpoint_id ) {
				return $this->check_permission( $endpoint_id, $request );
			},
		);

		register_rest_route( $namespace, $route, $args );
	}

	/**
	 * Handle incoming request.
	 *
	 * @param int              $endpoint_id Endpoint post ID.
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	private function handle_request( $endpoint_id, $request ) {
		$start_time = microtime( true );

		// Check rate limit.
		$rate_limit = get_post_meta( $endpoint_id, '_rem_rate_limit', true );
		if ( $rate_limit ) {
			$identifier = $this->get_client_identifier( $request );
			if ( ! $this->rate_limiter->is_allowed( $identifier, $rate_limit, "endpoint_{$endpoint_id}" ) ) {
				$response = new \WP_Error(
					'rate_limit_exceeded',
					'Rate limit exceeded',
					array( 'status' => 429 )
				);
				$this->log_request( $endpoint_id, $request, $response, 'error', microtime( true ) - $start_time );
				return $response;
			}
		}

		// Check cache.
		$cache_enabled = get_post_meta( $endpoint_id, '_rem_cache_enabled', true );
		if ( $cache_enabled && $request->get_method() === 'GET' ) {
			$cache_key = 'wp_rem_cache_' . md5( $endpoint_id . serialize( $request->get_params() ) );
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				return new \WP_REST_Response( $cached, 200 );
			}
		}

		// Validate request schema.
		$request_schema_id = get_post_meta( $endpoint_id, '_rem_request_schema_id', true );
		if ( $request_schema_id ) {
			$validator = new \WP_REST_Endpoint_Manager\Validator();
			$schema_json = get_post_meta( $request_schema_id, '_rem_schema_json', true );
			if ( $schema_json ) {
				$schema = json_decode( $schema_json, true );
				$validation = $validator->validate( $request->get_json_params(), $schema );
				if ( ! $validation['valid'] ) {
					$response = new \WP_Error(
						'validation_failed',
						'Request validation failed',
						array( 'status' => 400, 'errors' => $validation['errors'] )
					);
					$this->log_request( $endpoint_id, $request, $response, 'error', microtime( true ) - $start_time );
					return $response;
				}
			}
		}

		// Execute callback based on type.
		$callback_type = get_post_meta( $endpoint_id, '_rem_callback_type', true );
		
		try {
			switch ( $callback_type ) {
				case 'controller':
					$response = $this->execute_controller( $endpoint_id, $request );
					break;
				case 'inline':
					$response = $this->execute_inline_code( $endpoint_id, $request );
					break;
				case 'proxy':
					$response = $this->execute_proxy( $endpoint_id, $request );
					break;
				case 'transform':
					$response = $this->execute_transform( $endpoint_id, $request );
					break;
				default:
					$response = new \WP_Error( 'invalid_callback_type', 'Invalid callback type', array( 'status' => 500 ) );
			}
		} catch ( \Exception $e ) {
			$response = new \WP_Error( 'execution_error', $e->getMessage(), array( 'status' => 500 ) );
		}

		$execution_time = microtime( true ) - $start_time;

		// Validate response schema.
		if ( ! is_wp_error( $response ) ) {
			$response_schema_id = get_post_meta( $endpoint_id, '_rem_response_schema_id', true );
			if ( $response_schema_id ) {
				$validator = new \WP_REST_Endpoint_Manager\Validator();
				$schema_json = get_post_meta( $response_schema_id, '_rem_schema_json', true );
				if ( $schema_json ) {
					$schema = json_decode( $schema_json, true );
					$response_data = $response->get_data();
					$validation = $validator->validate( $response_data, $schema );
					if ( ! $validation['valid'] ) {
						$response = new \WP_Error(
							'response_validation_failed',
							'Response validation failed',
							array( 'status' => 500, 'errors' => $validation['errors'] )
						);
					}
				}
			}
		}

		// Cache response if enabled.
		if ( $cache_enabled && $request->get_method() === 'GET' && ! is_wp_error( $response ) ) {
			$cache_duration = get_post_meta( $endpoint_id, '_rem_cache_duration', true ) ?: 300;
			set_transient( $cache_key, $response->get_data(), $cache_duration );
		}

		// Log request.
		$status = is_wp_error( $response ) ? 'error' : 'success';
		$this->log_request( $endpoint_id, $request, $response, $status, $execution_time );

		return $response;
	}

	/**
	 * Execute controller callback.
	 *
	 * @param int              $endpoint_id Endpoint ID.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	private function execute_controller( $endpoint_id, $request ) {
		$controller_id = get_post_meta( $endpoint_id, '_rem_controller_id', true );
		if ( ! $controller_id ) {
			return new \WP_Error( 'no_controller', 'No controller specified', array( 'status' => 500 ) );
		}

		$executor = new Controller_Executor();
		return $executor->execute( $controller_id, $request );
	}

	/**
	 * Execute inline PHP code.
	 *
	 * @param int              $endpoint_id Endpoint ID.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	private function execute_inline_code( $endpoint_id, $request ) {
		$inline_code = get_post_meta( $endpoint_id, '_rem_inline_code', true );
		if ( empty( $inline_code ) ) {
			return new \WP_Error( 'no_code', 'No inline code specified', array( 'status' => 500 ) );
		}

		// Execute code in isolated scope.
		$result = $this->eval_code( $inline_code, $request );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $result instanceof \WP_REST_Response ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Execute proxy request.
	 *
	 * @param int              $endpoint_id Endpoint ID.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	private function execute_proxy( $endpoint_id, $request ) {
		$target_url = get_post_meta( $endpoint_id, '_rem_target_url', true );
		if ( empty( $target_url ) ) {
			return new \WP_Error( 'no_target_url', 'No target URL specified', array( 'status' => 500 ) );
		}

		$method = $request->get_method();
		$args = array(
			'method' => $method,
			'headers' => $request->get_headers(),
			'body' => $request->get_body(),
			'timeout' => 30,
		);

		$response = wp_remote_request( $target_url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );

		return new \WP_REST_Response( json_decode( $body, true ), $code );
	}

	/**
	 * Execute transform.
	 *
	 * @param int              $endpoint_id Endpoint ID.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response Response.
	 */
	private function execute_transform( $endpoint_id, $request ) {
		$data = $request->get_json_params();
		
		// Apply transformation if specified.
		$transform_json = get_post_meta( $endpoint_id, '_rem_response_transform', true );
		if ( $transform_json ) {
			$transform = json_decode( $transform_json, true );
			if ( $transform ) {
				$modifier = new Response_Modifier();
				$data = $modifier->transform( $data, $transform );
			}
		}

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Check permission for request.
	 *
	 * @param int              $endpoint_id Endpoint ID.
	 * @param \WP_REST_Request $request Request.
	 * @return bool True if allowed.
	 */
	private function check_permission( $endpoint_id, $request ) {
		$auth_required = get_post_meta( $endpoint_id, '_rem_auth_required', true );
		
		if ( ! $auth_required ) {
			return true;
		}

		$auth_type = get_post_meta( $endpoint_id, '_rem_auth_type', true );
		$auth_manager = new Auth_Manager();

		return $auth_manager->verify( $request, $auth_type );
	}

	/**
	 * Evaluate PHP code safely.
	 *
	 * @param string           $code Code to evaluate.
	 * @param \WP_REST_Request $request Request object.
	 * @return mixed Result.
	 */
	private function eval_code( $code, $request ) {
		try {
			$result = eval( $code );
			return $result;
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'eval_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Log request.
	 *
	 * @param int                                 $endpoint_id Endpoint ID.
	 * @param \WP_REST_Request                    $request Request.
	 * @param \WP_REST_Response|\WP_Error         $response Response.
	 * @param string                              $status Status.
	 * @param float                               $execution_time Execution time.
	 */
	private function log_request( $endpoint_id, $request, $response, $status, $execution_time ) {
		$request_data = array(
			'method' => $request->get_method(),
			'headers' => $request->get_headers(),
			'body' => $request->get_body(),
			'query_params' => $request->get_query_params(),
		);

		if ( is_wp_error( $response ) ) {
			$response_data = array(
				'http_code' => $response->get_error_data()['status'] ?? 500,
				'error' => $response->get_error_message(),
			);
		} else {
			$response_data = array(
				'http_code' => $response->get_status(),
				'body' => $response->get_data(),
				'headers' => $response->get_headers(),
				'size' => strlen( wp_json_encode( $response->get_data() ) ),
			);
		}

		$this->logger->log_endpoint( $endpoint_id, $request_data, $response_data, $status, $execution_time );
	}

	/**
	 * Get client identifier.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string Identifier.
	 */
	private function get_client_identifier( $request ) {
		// Try to get API key from header.
		$api_key = $request->get_header( 'X-API-Key' );
		if ( $api_key ) {
			return 'api_key_' . md5( $api_key );
		}

		// Fall back to IP address.
		return 'ip_' . $this->get_client_ip();
	}

	/**
	 * Get client IP.
	 *
	 * @return string IP address.
	 */
	private function get_client_ip() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				return $_SERVER[ $key ];
			}
		}
		return '0.0.0.0';
	}
}
