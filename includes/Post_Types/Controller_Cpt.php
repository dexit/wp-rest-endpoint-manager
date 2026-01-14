<?php
/**
 * Controller Custom Post Type
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Post_Types;

/**
 * Registers and manages the REST Controller CPT.
 */
class Controller_CPT {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'rest_controller';

	/**
	 * Register the custom post type.
	 */
	public function register() {
		$labels = array(
			'name'                  => _x( 'Controllers', 'Post Type General Name', 'wp-rest-endpoint-manager' ),
			'singular_name'         => _x( 'Controller', 'Post Type Singular Name', 'wp-rest-endpoint-manager' ),
			'menu_name'             => __( 'Controllers', 'wp-rest-endpoint-manager' ),
			'add_new_item'          => __( 'Add New Controller', 'wp-rest-endpoint-manager' ),
			'edit_item'             => __( 'Edit Controller', 'wp-rest-endpoint-manager' ),
			'view_item'             => __( 'View Controller', 'wp-rest-endpoint-manager' ),
		);

		$args = array(
			'label'                 => __( 'Controller', 'wp-rest-endpoint-manager' ),
			'description'           => __( 'Custom PHP Controller Classes', 'wp-rest-endpoint-manager' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', 'revisions' ),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => false,
			'menu_icon'             => 'dashicons-media-code',
			'show_in_rest'          => true,
			'capability_type'       => 'post',
		);

		register_post_type( self::POST_TYPE, $args );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_boxes' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'set_custom_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'custom_column_content' ), 10, 2 );
	}

	/**
	 * Add meta boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'rem_controller_code',
			__( 'Controller PHP Code', 'wp-rest-endpoint-manager' ),
			array( $this, 'render_code_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'rem_controller_info',
			__( 'Controller Information', 'wp-rest-endpoint-manager' ),
			array( $this, 'render_info_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render code meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_code_meta_box( $post ) {
		wp_nonce_field( 'rem_controller_meta_box', 'rem_controller_meta_box_nonce' );

		$controller_code = get_post_meta( $post->ID, '_rem_controller_code', true );
		$class_name = get_post_meta( $post->ID, '_rem_class_name', true );

		if ( empty( $controller_code ) ) {
			$controller_code = $this->get_template_code( $class_name ?: 'My_Controller' );
		}
		?>
		<div class="rem-meta-box">
			<p>
				<label for="rem_class_name"><strong><?php esc_html_e( 'Class Name', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<input type="text" id="rem_class_name" name="rem_class_name" value="<?php echo esc_attr( $class_name ); ?>" class="widefat" placeholder="My_Controller" />
			</p>

			<p>
				<label for="rem_controller_code"><strong><?php esc_html_e( 'PHP Class Code', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<div id="monaco-editor-container" style="height: 600px; border: 1px solid #ddd;"></div>
				<textarea id="rem_controller_code" name="rem_controller_code" style="display:none;"><?php echo esc_textarea( $controller_code ); ?></textarea>
			</p>

			<p id="rem_validation_errors" class="error-message" style="display:none;"></p>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Monaco editor will be initialized by code-editor.js
			if (typeof initMonacoEditor === 'function') {
				initMonacoEditor('monaco-editor-container', 'rem_controller_code', 'php');
			}
		});
		</script>
		<?php
	}

	/**
	 * Render info meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_info_meta_box( $post ) {
		$version = get_post_meta( $post->ID, '_rem_version', true ) ?: '1.0.0';
		$status = get_post_meta( $post->ID, '_rem_status', true ) ?: 'draft';
		$methods = get_post_meta( $post->ID, '_rem_methods', true );
		$last_validated = get_post_meta( $post->ID, '_rem_last_validated', true );
		?>
		<div class="rem-meta-box">
			<p>
				<label for="rem_version"><strong><?php esc_html_e( 'Version', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<input type="text" id="rem_version" name="rem_version" value="<?php echo esc_attr( $version ); ?>" class="widefat" />
			</p>

			<p>
				<label for="rem_status"><strong><?php esc_html_e( 'Status', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<select id="rem_status" name="rem_status" class="widefat">
					<option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'wp-rest-endpoint-manager' ); ?></option>
					<option value="testing" <?php selected( $status, 'testing' ); ?>><?php esc_html_e( 'Testing', 'wp-rest-endpoint-manager' ); ?></option>
					<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'wp-rest-endpoint-manager' ); ?></option>
				</select>
			</p>

			<?php if ( $last_validated ) : ?>
				<p>
					<strong><?php esc_html_e( 'Last Validated:', 'wp-rest-endpoint-manager' ); ?></strong><br />
					<?php echo esc_html( date_i18n( 'Y-m-d H:i:s', $last_validated ) ); ?>
				</p>
			<?php endif; ?>

			<?php if ( is_array( $methods ) && ! empty( $methods ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Detected Methods:', 'wp-rest-endpoint-manager' ); ?></strong><br />
					<?php foreach ( $methods as $method ) : ?>
						<code><?php echo esc_html( $method ); ?></code><br />
					<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<p>
				<button type="button" class="button button-secondary" id="rem-validate-controller">
					<?php esc_html_e( 'Validate Syntax', 'wp-rest-endpoint-manager' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	/**
	 * Save meta boxes.
	 *
	 * @param int     $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 */
	public function save_meta_boxes( $post_id, $post ) {
		if ( ! isset( $_POST['rem_controller_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['rem_controller_meta_box_nonce'], 'rem_controller_meta_box' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save controller code.
		if ( isset( $_POST['rem_controller_code'] ) ) {
			$code = wp_unslash( $_POST['rem_controller_code'] );
			update_post_meta( $post_id, '_rem_controller_code', $code );

			// Validate and detect methods.
			$validation = $this->validate_controller_code( $code );
			if ( $validation['valid'] ) {
				update_post_meta( $post_id, '_rem_methods', $validation['methods'] );
				update_post_meta( $post_id, '_rem_validation_errors', '' );
				update_post_meta( $post_id, '_rem_last_validated', time() );
			} else {
				update_post_meta( $post_id, '_rem_validation_errors', $validation['errors'] );
			}
		}

		// Save other fields.
		if ( isset( $_POST['rem_class_name'] ) ) {
			update_post_meta( $post_id, '_rem_class_name', sanitize_text_field( $_POST['rem_class_name'] ) );
		}

		if ( isset( $_POST['rem_version'] ) ) {
			update_post_meta( $post_id, '_rem_version', sanitize_text_field( $_POST['rem_version'] ) );
		}

		if ( isset( $_POST['rem_status'] ) ) {
			update_post_meta( $post_id, '_rem_status', sanitize_text_field( $_POST['rem_status'] ) );
		}
	}

	/**
	 * Validate controller code.
	 *
	 * @param string $code PHP code.
	 * @return array Validation result.
	 */
	private function validate_controller_code( $code ) {
		$result = array(
			'valid' => false,
			'methods' => array(),
			'errors' => '',
		);

		// Check PHP syntax.
		$syntax_check = php_check_syntax( $code );
		if ( ! $syntax_check['valid'] ) {
			$result['errors'] = $syntax_check['error'];
			return $result;
		}

		// Detect public methods.
		preg_match_all( '/public\s+function\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\(/', $code, $matches );
		if ( ! empty( $matches[1] ) ) {
			$result['methods'] = $matches[1];
		}

		$result['valid'] = true;
		return $result;
	}

	/**
	 * Get template controller code.
	 *
	 * @param string $class_name Class name.
	 * @return string Template code.
	 */
	private function get_template_code( $class_name ) {
		return <<<PHP
<?php
/**
 * Custom REST API Controller
 */
class {$class_name} {

	/**
	 * Handle GET request.
	 *
	 * @param WP_REST_Request \$request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get( \$request ) {
		\$data = array(
			'message' => 'Hello from {$class_name}!',
			'params' => \$request->get_params(),
		);

		return new WP_REST_Response( \$data, 200 );
	}

	/**
	 * Handle POST request.
	 *
	 * @param WP_REST_Request \$request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function post( \$request ) {
		\$body = \$request->get_json_params();

		// Your custom logic here
		
		return new WP_REST_Response( array(
			'success' => true,
			'data' => \$body,
		), 201 );
	}
}
PHP;
	}

	/**
	 * Set custom columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function set_custom_columns( $columns ) {
		$new_columns = array();
		$new_columns['cb'] = $columns['cb'];
		$new_columns['title'] = $columns['title'];
		$new_columns['class_name'] = __( 'Class Name', 'wp-rest-endpoint-manager' );
		$new_columns['methods'] = __( 'Methods', 'wp-rest-endpoint-manager' );
		$new_columns['status'] = __( 'Status', 'wp-rest-endpoint-manager' );
		$new_columns['date'] = $columns['date'];

		return $new_columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 */
	public function custom_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'class_name':
				$class_name = get_post_meta( $post_id, '_rem_class_name', true );
				echo $class_name ? '<code>' . esc_html( $class_name ) . '</code>' : '<em>Not set</em>';
				break;

			case 'methods':
				$methods = get_post_meta( $post_id, '_rem_methods', true );
				if ( is_array( $methods ) && ! empty( $methods ) ) {
					echo '<code>' . esc_html( implode( '(), ', $methods ) ) . '()</code>';
				}
				break;

			case 'status':
				$status = get_post_meta( $post_id, '_rem_status', true ) ?: 'draft';
				echo '<span class="rem-status-' . esc_attr( $status ) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
				break;
		}
	}
}

/**
 * Simple PHP syntax checker (since php_check_syntax doesn't exist).
 *
 * @param string $code PHP code to check.
 * @return array Result with 'valid' boolean and 'error' message.
 */
function php_check_syntax( $code ) {
	$result = array(
		'valid' => true,
		'error' => '',
	);

	// Create temporary file.
	$temp_file = tempnam( sys_get_temp_dir(), 'php_syntax_' );
	file_put_contents( $temp_file, $code );

	// Check syntax using php -l.
	$output = array();
	$return_var = 0;
	exec( 'php -l ' . escapeshellarg( $temp_file ) . ' 2>&1', $output, $return_var );

	if ( $return_var !== 0 ) {
		$result['valid'] = false;
		$result['error'] = implode( "\n", $output );
	}

	// Clean up.
	unlink( $temp_file );

	return $result;
}
