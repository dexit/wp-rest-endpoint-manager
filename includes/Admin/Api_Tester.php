<?php
/**
 * API Tester Admin Page
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Admin;

/**
 * Built-in API tester (Postman-like interface).
 */
class API_Tester {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wp_rem_test_api', array( $this, 'ajax_test_api' ) );
	}

	/**
	 * Render API tester page.
	 */
	public function render() {
		$endpoints = $this->get_all_endpoints();

		?>
		<div class="wrap wp-rem-api-tester">
			<h1><?php esc_html_e( 'API Tester', 'wp-rest-endpoint-manager' ); ?></h1>

			<div class="wp-rem-tester-interface">
				<div class="request-builder">
					<h2><?php esc_html_e( 'Request', 'wp-rest-endpoint-manager' ); ?></h2>

					<div class="form-row">
						<label for="endpoint-select"><?php esc_html_e( 'Select Endpoint', 'wp-rest-endpoint-manager' ); ?></label>
						<select id="endpoint-select" class="widefat">
							<option value=""><?php esc_html_e( '-- Select an endpoint --', 'wp-rest-endpoint-manager' ); ?></option>
							<?php foreach ( $endpoints as $endpoint ) : ?>
								<option value="<?php echo esc_attr( $endpoint['url'] ); ?>" 
									data-methods="<?php echo esc_attr( wp_json_encode( $endpoint['methods'] ) ); ?>">
									<?php echo esc_html( $endpoint['title'] . ' - ' . $endpoint['url'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="form-row">
						<label><?php esc_html_e( 'Or enter custom URL', 'wp-rest-endpoint-manager' ); ?></label>
						<input type="url" id="custom-url" class="widefat" placeholder="<?php echo esc_attr( rest_url() ); ?>" />
					</div>

					<div class="form-row">
						<label for="http-method"><?php esc_html_e( 'HTTP Method', 'wp-rest-endpoint-manager' ); ?></label>
						<select id="http-method">
							<option value="GET">GET</option>
							<option value="POST">POST</option>
							<option value="PUT">PUT</option>
							<option value="PATCH">PATCH</option>
							<option value="DELETE">DELETE</option>
						</select>
					</div>

					<div class="form-row">
						<label for="request-headers"><?php esc_html_e( 'Headers (JSON)', 'wp-rest-endpoint-manager' ); ?></label>
						<textarea id="request-headers" rows="4" class="widefat code">{
  "Content-Type": "application/json"
}</textarea>
					</div>

					<div class="form-row">
						<label for="request-body"><?php esc_html_e( 'Request Body (JSON)', 'wp-rest-endpoint-manager' ); ?></label>
						<textarea id="request-body" rows="8" class="widefat code">{
  "example": "data"
}</textarea>
					</div>

					<div class="form-row">
						<button type="button" id="send-request" class="button button-primary button-large">
							<?php esc_html_e( 'Send Request', 'wp-rest-endpoint-manager' ); ?>
						</button>
						<button type="button" id="generate-curl" class="button button-secondary">
							<?php esc_html_e( 'Generate cURL', 'wp-rest-endpoint-manager' ); ?>
						</button>
					</div>
				</div>

				<div class="response-viewer">
					<h2><?php esc_html_e( 'Response', 'wp-rest-endpoint-manager' ); ?></h2>

					<div id="response-container" class="response-empty">
						<p><?php esc_html_e( 'Send a request to see the response here.', 'wp-rest-endpoint-manager' ); ?></p>
					</div>

					<div id="response-data" style="display:none;">
						<div class="response-meta">
							<strong><?php esc_html_e( 'Status:', 'wp-rest-endpoint-manager' ); ?></strong> 
							<span id="response-status"></span>
							<strong style="margin-left: 20px;"><?php esc_html_e( 'Time:', 'wp-rest-endpoint-manager' ); ?></strong> 
							<span id="response-time"></span>
							<strong style="margin-left: 20px;"><?php esc_html_e( 'Size:', 'wp-rest-endpoint-manager' ); ?></strong> 
							<span id="response-size"></span>
						</div>

						<h3><?php esc_html_e( 'Headers', 'wp-rest-endpoint-manager' ); ?></h3>
						<pre id="response-headers" class="code"></pre>

						<h3><?php esc_html_e( 'Body', 'wp-rest-endpoint-manager' ); ?></h3>
						<pre id="response-body" class="code"></pre>
					</div>
				</div>
			</div>

			<!-- cURL Command Modal -->
			<div id="curl-modal" style="display:none;">
				<div class="curl-modal-content">
					<span class="close-modal">&times;</span>
					<h2><?php esc_html_e( 'cURL Command', 'wp-rest-endpoint-manager' ); ?></h2>
					<pre id="curl-command" class="code"></pre>
					<button type="button" class="button" id="copy-curl"><?php esc_html_e( 'Copy to Clipboard', 'wp-rest-endpoint-manager' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get all active endpoints.
	 *
	 * @return array Endpoints.
	 */
	private function get_all_endpoints() {
		$posts = get_posts( array(
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

		$endpoints = array();
		foreach ( $posts as $post ) {
			$namespace = get_post_meta( $post->ID, '_rem_namespace', true );
			$route = get_post_meta( $post->ID, '_rem_route', true );
			$methods = get_post_meta( $post->ID, '_rem_methods', true );

			if ( $namespace && $route ) {
				$endpoints[] = array(
					'id' => $post->ID,
					'title' => $post->post_title,
					'url' => rest_url( $namespace . $route ),
					'methods' => $methods ?: array(),
				);
			}
		}

		return $endpoints;
	}

	/**
	 * AJAX handler for testing API.
	 */
	public function ajax_test_api() {
		check_ajax_referer( 'wp_rest', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';
		$method = isset( $_POST['method'] ) ? sanitize_text_field( $_POST['method'] ) : 'GET';
		$headers = isset( $_POST['headers'] ) ? json_decode( wp_unslash( $_POST['headers'] ), true ) : array();
		$body = isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : '';

		if ( empty( $url ) ) {
			wp_send_json_error( array( 'message' => 'URL is required' ) );
		}

		$start_time = microtime( true );

		$args = array(
			'method' => $method,
			'headers' => $headers ?: array(),
			'body' => $body,
			'timeout' => 30,
		);

		$response = wp_remote_request( $url, $args );
		$execution_time = microtime( true ) - $start_time;

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array(
				'message' => $response->get_error_message(),
				'time' => number_format( $execution_time, 3 ),
			) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_headers = wp_remote_retrieve_headers( $response );
		$response_body = wp_remote_retrieve_body( $response );

		wp_send_json_success( array(
			'status' => $response_code,
			'headers' => $response_headers->getAll(),
			'body' => $response_body,
			'time' => number_format( $execution_time, 3 ),
			'size' => strlen( $response_body ),
		) );
	}
}
