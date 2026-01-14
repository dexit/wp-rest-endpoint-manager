<?php
/**
 * REST Endpoint Custom Post Type
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Post_Types;

/**
 * Registers and manages the REST Endpoint CPT.
 */
class Rest_Endpoint_Cpt {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'rest_endpoint';

	/**
	 * Register the custom post type.
	 */
	public function register() {
		$labels = array(
			'name'                  => _x( 'REST Endpoints', 'Post Type General Name', 'wp-rest-endpoint-manager' ),
			'singular_name'         => _x( 'REST Endpoint', 'Post Type Singular Name', 'wp-rest-endpoint-manager' ),
			'menu_name'             => __( 'REST Endpoints', 'wp-rest-endpoint-manager' ),
			'name_admin_bar'        => __( 'REST Endpoint', 'wp-rest-endpoint-manager' ),
			'archives'              => __( 'Endpoint Archives', 'wp-rest-endpoint-manager' ),
			'attributes'            => __( 'Endpoint Attributes', 'wp-rest-endpoint-manager' ),
			'parent_item_colon'     => __( 'Parent Endpoint:', 'wp-rest-endpoint-manager' ),
			'all_items'             => __( 'REST Endpoints', 'wp-rest-endpoint-manager' ),
			'add_new_item'          => __( 'Add New Endpoint', 'wp-rest-endpoint-manager' ),
			'add_new'               => __( 'Add New', 'wp-rest-endpoint-manager' ),
			'new_item'              => __( 'New Endpoint', 'wp-rest-endpoint-manager' ),
			'edit_item'             => __( 'Edit Endpoint', 'wp-rest-endpoint-manager' ),
			'update_item'           => __( 'Update Endpoint', 'wp-rest-endpoint-manager' ),
			'view_item'             => __( 'View Endpoint', 'wp-rest-endpoint-manager' ),
			'view_items'            => __( 'View Endpoints', 'wp-rest-endpoint-manager' ),
			'search_items'          => __( 'Search Endpoint', 'wp-rest-endpoint-manager' ),
			'not_found'             => __( 'Not found', 'wp-rest-endpoint-manager' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'wp-rest-endpoint-manager' ),
		);

		$args = array(
			'label'                 => __( 'REST Endpoint', 'wp-rest-endpoint-manager' ),
			'description'           => __( 'Custom REST API Endpoints', 'wp-rest-endpoint-manager' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', 'comments' ),
			'taxonomies'            => array(),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => false, // We'll add it to our custom menu
			'menu_position'         => 30,
			'menu_icon'             => 'dashicons-rest-api',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'capability_type'       => 'post',
			'show_in_rest'          => true,
		);

		register_post_type( self::POST_TYPE, $args );

		// Add meta boxes.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_boxes' ), 10, 2 );

		// Customize admin columns.
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'set_custom_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'custom_column_content' ), 10, 2 );
	}

	/**
	 * Add meta boxes for endpoint configuration.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'rem_endpoint_config',
			__( 'Endpoint Configuration', 'wp-rest-endpoint-manager' ),
			array( $this, 'render_config_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'rem_endpoint_callback',
			__( 'Callback Configuration', 'wp-rest-endpoint-manager' ),
			array( $this, 'render_callback_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'rem_endpoint_security',
			__( 'Security & Performance', 'wp-rest-endpoint-manager' ),
			array( $this, 'render_security_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);

		add_meta_box(
			'rem_endpoint_testing',
			__( 'Testing & Documentation', 'wp-rest-endpoint-manager' ),
			array( $this, 'render_testing_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the endpoint configuration meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_config_meta_box( $post ) {
		wp_nonce_field( 'rem_endpoint_meta_box', 'rem_endpoint_meta_box_nonce' );

		$namespace = get_post_meta( $post->ID, '_rem_namespace', true );
		$route = get_post_meta( $post->ID, '_rem_route', true );
		$methods = get_post_meta( $post->ID, '_rem_methods', true );
		$status = get_post_meta( $post->ID, '_rem_status', true ) ?: 'inactive';

		if ( ! is_array( $methods ) ) {
			$methods = array();
		}
		?>
		<div class="rem-meta-box">
			<p>
				<label for="rem_namespace"><strong><?php esc_html_e( 'Namespace', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<input type="text" id="rem_namespace" name="rem_namespace" value="<?php echo esc_attr( $namespace ); ?>" class="widefat" placeholder="my-api/v1" />
				<span class="description"><?php esc_html_e( 'API namespace (e.g., "my-api/v1")', 'wp-rest-endpoint-manager' ); ?></span>
			</p>

			<p>
				<label for="rem_route"><strong><?php esc_html_e( 'Route Path', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<input type="text" id="rem_route" name="rem_route" value="<?php echo esc_attr( $route ); ?>" class="widefat" placeholder="/users" />
				<span class="description"><?php esc_html_e( 'Route path (e.g., "/users" or "/users/(?P<id>\d+)")', 'wp-rest-endpoint-manager' ); ?></span>
			</p>

			<p>
				<label><strong><?php esc_html_e( 'HTTP Methods', 'wp-rest-endpoint-manager' ); ?></strong></label><br />
				<?php foreach ( array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ) as $method ) : ?>
					<label style="display: inline-block; margin-right: 15px;">
						<input type="checkbox" name="rem_methods[]" value="<?php echo esc_attr( $method ); ?>" <?php checked( in_array( $method, $methods, true ) ); ?> />
						<?php echo esc_html( $method ); ?>
					</label>
				<?php endforeach; ?>
			</p>

			<p>
				<label for="rem_status"><strong><?php esc_html_e( 'Status', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<select id="rem_status" name="rem_status" class="widefat">
					<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'wp-rest-endpoint-manager' ); ?></option>
					<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'wp-rest-endpoint-manager' ); ?></option>
					<option value="testing" <?php selected( $status, 'testing' ); ?>><?php esc_html_e( 'Testing', 'wp-rest-endpoint-manager' ); ?></option>
				</select>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the callback configuration meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_callback_meta_box( $post ) {
		$callback_type = get_post_meta( $post->ID, '_rem_callback_type', true ) ?: 'proxy';
		$controller_id = get_post_meta( $post->ID, '_rem_controller_id', true );
		$inline_code = get_post_meta( $post->ID, '_rem_inline_code', true );
		$target_url = get_post_meta( $post->ID, '_rem_target_url', true );
		 ?>
		<div class="rem-meta-box">
			<p>
				<label for="rem_callback_type"><strong><?php esc_html_e( 'Callback Type', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<select id="rem_callback_type" name="rem_callback_type" class="widefat">
					<option value="proxy" <?php selected( $callback_type, 'proxy' ); ?>><?php esc_html_e( 'Proxy to URL', 'wp-rest-endpoint-manager' ); ?></option>
					<option value="controller" <?php selected( $callback_type, 'controller' ); ?>><?php esc_html_e( 'Controller Class', 'wp-rest-endpoint-manager' ); ?></option>
					<option value="inline" <?php selected( $callback_type, 'inline' ); ?>><?php esc_html_e( 'Inline PHP Code', 'wp-rest-endpoint-manager' ); ?></option>
					<option value="transform" <?php selected( $callback_type, 'transform' ); ?>><?php esc_html_e( 'Data Transform Only', 'wp-rest-endpoint-manager' ); ?></option>
				</select>
			</p>

			<div id="proxy-config" class="callback-config" style="display: <?php echo $callback_type === 'proxy' ? 'block' : 'none'; ?>;">
				<p>
					<label for="rem_target_url"><strong><?php esc_html_e( 'Target URL', 'wp-rest-endpoint-manager' ); ?></strong></label>
					<input type="url" id="rem_target_url" name="rem_target_url" value="<?php echo esc_url( $target_url ); ?>" class="widefat" placeholder="https://api.example.com/users" />
				</p>
			</div>

			<div id="controller-config" class="callback-config" style="display: <?php echo $callback_type === 'controller' ? 'block' : 'none'; ?>;">
				<p>
					<label for="rem_controller_id"><strong><?php esc_html_e( 'Controller', 'wp-rest-endpoint-manager' ); ?></strong></label>
					<select id="rem_controller_id" name="rem_controller_id" class="widefat">
						<option value=""><?php esc_html_e( '-- Select Controller --', 'wp-rest-endpoint-manager' ); ?></option>
						<?php
						$controllers = get_posts( array( 'post_type' => 'rest_controller', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
						foreach ( $controllers as $controller ) :
							?>
							<option value="<?php echo esc_attr( $controller->ID ); ?>" <?php selected( $controller_id, $controller->ID ); ?>>
								<?php echo esc_html( $controller->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
			</div>

			<div id="inline-config" class="callback-config" style="display: <?php echo $callback_type === 'inline' ? 'block' : 'none'; ?>;">
				<p>
					<label for="rem_inline_code"><strong><?php esc_html_e( 'Inline PHP Code', 'wp-rest-endpoint-manager' ); ?></strong></label>
					<textarea id="rem_inline_code" name="rem_inline_code" rows="10" class="widefat code-editor-target"><?php echo esc_textarea( $inline_code ); ?></textarea>
					<span class="description"><?php esc_html_e( 'PHP code that returns WP_REST_Response or array. Variables available: $request', 'wp-rest-endpoint-manager' ); ?></span>
				</p>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#rem_callback_type').on('change', function() {
				$('.callback-config').hide();
				var type = $(this).val();
				$('#' + type + '-config').show();
			});
		});
		</script>
		<?php
	}

	/**
	 * Render the security & performance meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_security_meta_box( $post ) {
		$auth_required = get_post_meta( $post->ID, '_rem_auth_required', true );
		$auth_type = get_post_meta( $post->ID, '_rem_auth_type', true ) ?: 'none';
		$rate_limit = get_post_meta( $post->ID, '_rem_rate_limit', true );
		$cache_enabled = get_post_meta( $post->ID, '_rem_cache_enabled', true );
		$cache_duration = get_post_meta( $post->ID, '_rem_cache_duration', true ) ?: 300;
		?>
		<div class="rem-meta-box">
			<p>
				<label>
					<input type="checkbox" name="rem_auth_required" value="1" <?php checked( $auth_required, '1' ); ?> />
					<strong><?php esc_html_e( 'Require Authentication', 'wp-rest-endpoint-manager' ); ?></strong>
				</label>
			</p>

			<p>
				<label for="rem_auth_type"><strong><?php esc_html_e( 'Auth Type', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<select id="rem_auth_type" name="rem_auth_type" class="widefat">
					<option value="none" <?php selected( $auth_type, 'none' ); ?>><?php esc_html_e( 'None', 'wp-rest-endpoint-manager' ); ?></option>
					<option value="api_key" <?php selected( $auth_type, 'api_key' ); ?>><?php esc_html_e( 'API Key', 'wp-rest-endpoint-manager' ); ?></option>
					<option value="jwt" <?php selected( $auth_type, 'jwt' ); ?>><?php esc_html_e( 'JWT', 'wp-rest-endpoint-manager' ); ?></option>
					<option value="oauth" <?php selected( $auth_type, 'oauth' ); ?>><?php esc_html_e( 'OAuth', 'wp-rest-endpoint-manager' ); ?></option>
				</select>
			</p>

			<p>
				<label for="rem_rate_limit"><strong><?php esc_html_e( 'Rate Limit (req/min)', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<input type="number" id="rem_rate_limit" name="rem_rate_limit" value="<?php echo esc_attr( $rate_limit ); ?>" class="widefat" min="0" placeholder="60" />
			</p>

			<p>
				<label>
					<input type="checkbox" name="rem_cache_enabled" value="1" <?php checked( $cache_enabled, '1' ); ?> />
					<strong><?php esc_html_e( 'Enable Caching', 'wp-rest-endpoint-manager' ); ?></strong>
				</label>
			</p>

			<p>
				<label for="rem_cache_duration"><strong><?php esc_html_e( 'Cache Duration (seconds)', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<input type="number" id="rem_cache_duration" name="rem_cache_duration" value="<?php echo esc_attr( $cache_duration ); ?>" class="widefat" min="0" />
			</p>
		</div>
		<?php
	}

	/**
	 * Render the testing & documentation meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_testing_meta_box( $post ) {
		$namespace = get_post_meta( $post->ID, '_rem_namespace', true );
		$route = get_post_meta( $post->ID, '_rem_route', true );
		$endpoint_url = rest_url( $namespace . $route );
		?>
		<div class="rem-meta-box">
			<p>
				<strong><?php esc_html_e( 'Endpoint URL:', 'wp-rest-endpoint-manager' ); ?></strong><br />
				<code style="word-break: break-all;"><?php echo esc_html( $endpoint_url ); ?></code>
			</p>

			<p>
				<button type="button" class="button button-secondary" id="rem-test-endpoint">
					<?php esc_html_e( 'Test Endpoint', 'wp-rest-endpoint-manager' ); ?>
				</button>
			</p>

			<p>
				<button type="button" class="button button-secondary" id="rem-copy-curl">
					<?php esc_html_e( 'Copy cURL Command', 'wp-rest-endpoint-manager' ); ?>
				</button>
			</p>

			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-rem-logs&endpoint=' . $post->ID ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'View Logs', 'wp-rest-endpoint-manager' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Save meta box data.
	 *
	 * @param int     $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 */
	public function save_meta_boxes( $post_id, $post ) {
		// Verify nonce.
		if ( ! isset( $_POST['rem_endpoint_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['rem_endpoint_meta_box_nonce'], 'rem_endpoint_meta_box' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save fields.
		$fields = array(
			'rem_namespace' => 'sanitize_text_field',
			'rem_route' => 'sanitize_text_field',
			'rem_status' => 'sanitize_text_field',
			'rem_callback_type' => 'sanitize_text_field',
			'rem_controller_id' => 'absint',
			'rem_inline_code' => 'wp_kses_post',
			'rem_target_url' => 'esc_url_raw',
			'rem_auth_type' => 'sanitize_text_field',
			'rem_rate_limit' => 'absint',
			'rem_cache_duration' => 'absint',
		);

		foreach ( $fields as $field => $sanitize_callback ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, '_' . $field, call_user_func( $sanitize_callback, $_POST[ $field ] ) );
			}
		}

		// Save checkbox fields.
		update_post_meta( $post_id, '_rem_auth_required', isset( $_POST['rem_auth_required'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_rem_cache_enabled', isset( $_POST['rem_cache_enabled'] ) ? '1' : '0' );

		// Save methods array.
		if ( isset( $_POST['rem_methods'] ) && is_array( $_POST['rem_methods'] ) ) {
			update_post_meta( $post_id, '_rem_methods', array_map( 'sanitize_text_field', $_POST['rem_methods'] ) );
		} else {
			update_post_meta( $post_id, '_rem_methods', array() );
		}
	}

	/**
	 * Set custom admin columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function set_custom_columns( $columns ) {
		$new_columns = array();
		$new_columns['cb'] = $columns['cb'];
		$new_columns['title'] = $columns['title'];
		$new_columns['endpoint_url'] = __( 'Endpoint URL', 'wp-rest-endpoint-manager' );
		$new_columns['methods'] = __( 'Methods', 'wp-rest-endpoint-manager' );
		$new_columns['status'] = __( 'Status', 'wp-rest-endpoint-manager' );
		$new_columns['logs'] = __( 'Logs', 'wp-rest-endpoint-manager' );
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
			case 'endpoint_url':
				$namespace = get_post_meta( $post_id, '_rem_namespace', true );
				$route = get_post_meta( $post_id, '_rem_route', true );
				if ( $namespace && $route ) {
					echo '<code>' . esc_html( rest_url( $namespace . $route ) ) . '</code>';
				} else {
					echo '<em>' . esc_html__( 'Not configured', 'wp-rest-endpoint-manager' ) . '</em>';
				}
				break;

			case 'methods':
				$methods = get_post_meta( $post_id, '_rem_methods', true );
				if ( is_array( $methods ) && ! empty( $methods ) ) {
					foreach ( $methods as $method ) {
						echo '<span class="rem-method-badge">' . esc_html( $method ) . '</span> ';
					}
				}
				break;

			case 'status':
				$status = get_post_meta( $post_id, '_rem_status', true );
				$status_class = 'rem-status-' . esc_attr( $status );
				$status_label = ucfirst( $status );
				echo '<span class="' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span>';
				break;

			case 'logs':
				$log_count = wp_count_comments( $post_id );
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=wp-rem-logs&endpoint=' . $post_id ) ) . '">' . esc_html( $log_count->total_comments ) . ' logs</a>';
				break;
		}
	}
}
