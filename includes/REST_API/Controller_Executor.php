<?php
/**
 * Controller Executor
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\REST_API;

/**
 * Safely executes custom PHP controller classes.
 */
class Controller_Executor {

	/**
	 * Blocked functions for security.
	 *
	 * @var array
	 */
	private $blocked_functions = array(
		'eval', 'exec', 'shell_exec', 'system', 'passthru',
		'proc_open', 'popen', 'curl_exec', 'curl_multi_exec',
		'parse_ini_file', 'show_source', 'fopen', 'file_put_contents',
	);

	/**
	 * Execute controller method.
	 *
	 * @param int              $controller_id Controller post ID.
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function execute( $controller_id, $request ) {
		// Get controller code.
		$controller_code = get_post_meta( $controller_id, '_rem_controller_code', true );
		$class_name = get_post_meta( $controller_id, '_rem_class_name', true );
		$status = get_post_meta( $controller_id, '_rem_status', true );

		if ( empty( $controller_code ) || empty( $class_name ) ) {
			return new \WP_Error( 'invalid_controller', 'Controller not properly configured', array( 'status' => 500 ) );
		}

		if ( $status !== 'active' ) {
			return new \WP_Error( 'controller_inactive', 'Controller is not active', array( 'status' => 500 ) );
		}

		// Check for blocked functions.
		foreach ( $this->blocked_functions as $func ) {
			if ( stripos( $controller_code, $func ) !== false ) {
				return new \WP_Error(
					'blocked_function',
					sprintf( 'Controller contains blocked function: %s', $func ),
					array( 'status' => 500 )
				);
			}
		}

		try {
			// Execute controller code.
			eval( $controller_code );

			// Check if class exists.
			if ( ! class_exists( $class_name ) ) {
				return new \WP_Error( 'class_not_found', 'Controller class not found', array( 'status' => 500 ) );
			}

			// Instantiate controller.
			$controller = new $class_name();

			// Determine method to call based on HTTP method.
			$http_method = strtolower( $request->get_method() );
			$method_map = array(
				'get' => 'get',
				'post' => 'post',
				'put' => 'put',
				'patch' => 'patch',
				'delete' => 'delete',
			);

			$method = $method_map[ $http_method ] ?? 'handle';

			// Check if method exists.
			if ( ! method_exists( $controller, $method ) ) {
				return new \WP_Error(
					'method_not_found',
					sprintf( 'Controller method not found: %s', $method ),
					array( 'status' => 500 )
				);
			}

			// Call method.
			$result = $controller->$method( $request );

			// Ensure result is WP_REST_Response.
			if ( $result instanceof \WP_REST_Response ) {
				return $result;
			}

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Wrap in response.
			return new \WP_REST_Response( $result, 200 );

		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'controller_execution_error',
				$e->getMessage(),
				array( 'status' => 500, 'trace' => $e->getTraceAsString() )
			);
		}
	}
}
