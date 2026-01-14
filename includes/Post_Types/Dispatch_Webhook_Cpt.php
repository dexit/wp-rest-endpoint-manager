<?php
/**
 * Dispatch Webhook Custom Post Type
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Post_Types;

/**
 * Registers and manages the Dispatch Webhook CPT.
 */
class Dispatch_Webhook_Cpt {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'dispatch_webhook';

	/**
	 * Register the custom post type.
	 */
	public function register() {
		$labels = array(
			'name'                  => _x( 'Dispatch Webhooks', 'Post Type General Name', 'wp-rest-endpoint-manager' ),
			'singular_name'         => _x( 'Dispatch Webhook', 'Post Type Singular Name', 'wp-rest-endpoint-manager' ),
			'menu_name'             => __( 'Dispatch Webhooks', 'wp-rest-endpoint-manager' ),
			'add_new_item'          => __( 'Add New Webhook', 'wp-rest-endpoint-manager' ),
		);

		$args = array(
			'label'                 => __( 'Dispatch Webhook', 'wp-rest-endpoint-manager' ),
			'description'           => __( 'Outgoing Webhooks', 'wp-rest-endpoint-manager' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', 'comments' ),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => false,
			'menu_icon'             => 'dashicons-upload',
			'show_in_rest'          => true,
		);

		register_post_type( self::POST_TYPE, $args );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_boxes' ), 10, 2 );
	}

	/**
	 * Add meta boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'rem_dispatch_config',
			__( 'Webhook Configuration', 'wp-rest-endpoint-manager' ),
			array( $this, 'render_config_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'rem_dispatch_triggers',
			__( 'Triggers & Events', 'wp-rest-endpoint-manager' ),
			array( $this, 'render_triggers_meta_box' ),
			self::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'rem_dispatch_actions',
			__( 'Custom WordPress Actions', 'wp-rest-endpoint-manager' ),
			array( $this, 'render_actions_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render config meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_config_meta_box( $post ) {
		wp_nonce_field( 'rem_dispatch_meta_box', 'rem_dispatch_meta_box_nonce' );

		$target_url = get_post_meta( $post->ID, '_rem_target_url', true );
		$http_method = get_post_meta( $post->ID, '_rem_http_method', true ) ?: 'POST';
		$payload_template = get_post_meta( $post->ID, '_rem_payload_template', true );
		$status = get_post_meta( $post->ID, '_rem_status', true ) ?: 'inactive';
		?>
		<div class="rem-meta-box">
			<p>
				<label for="rem_target_url"><strong><?php esc_html_e( 'Target URL', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<input type="url" id="rem_target_url" name="rem_target_url" value="<?php echo esc_url( $target_url ); ?>" class="widefat" placeholder="https://hooks.example.com/webhook" />
			</p>

			<p>
				<label for="rem_http_method"><strong><?php esc_html_e( 'HTTP Method', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<select id="rem_http_method" name="rem_http_method" class="widefat">
					<option value="POST" <?php selected( $http_method, 'POST' ); ?>>POST</option>
					<option value="PUT" <?php selected( $http_method, 'PUT' ); ?>>PUT</option>
					<option value="PATCH" <?php selected( $http_method, 'PATCH' ); ?>>PATCH</option>
					<option value="DELETE" <?php selected( $http_method, 'DELETE' ); ?>>DELETE</option>
				</select>
			</p>

			<p>
				<label for="rem_payload_template"><strong><?php esc_html_e( 'Payload Template', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<textarea id="rem_payload_template" name="rem_payload_template" rows="10" class="widefat code"><?php echo esc_textarea( $payload_template ); ?></textarea>
				<span class="description"><?php esc_html_e( 'JSON template with placeholders like {{post.title}}, {{user.email}}', 'wp-rest-endpoint-manager' ); ?></span>
			</p>

			<p>
				<label for="rem_status"><strong><?php esc_html_e( 'Status', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<select id="rem_status" name="rem_status" class="widefat">
					<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'wp-rest-endpoint-manager' ); ?></option>
					<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'wp-rest-endpoint-manager' ); ?></option>
				</select>
			</p>
		</div>
		<?php
	}

	/**
	 * Render triggers meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_triggers_meta_box( $post ) {
		$trigger_events = get_post_meta( $post->ID, '_rem_trigger_events', true ) ?: array();
		?>
		<div class="rem-meta-box">
			<p><strong><?php esc_html_e( 'WordPress Events to Trigger This Webhook:', 'wp-rest-endpoint-manager' ); ?></strong></p>
			
			<?php
			$common_triggers = array(
				'publish_post' => 'Post Published',
				'save_post' => 'Post Saved',
				'user_register' => 'User Registered',
				'woocommerce_order_status_completed' => 'WooCommerce Order Completed',
				'woocommerce_new_product' => 'WooCommerce New Product',
			);

			foreach ( $common_triggers as $hook => $label ) :
				?>
				<label style="display: block; margin-bottom: 8px;">
					<input type="checkbox" name="rem_trigger_events[]" value="<?php echo esc_attr( $hook ); ?>" <?php checked( in_array( $hook, (array) $trigger_events, true ) ); ?> />
					<?php echo esc_html( $label ); ?> <code><?php echo esc_html( $hook ); ?></code>
				</label>
			<?php endforeach; ?>

			<p>
				<label for="rem_custom_trigger"><strong><?php esc_html_e( 'Custom Hook Name', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<input type="text" id="rem_custom_trigger" class="widefat" placeholder="my_custom_hook" />
				<button type="button" class="button button-secondary" id="rem-add-trigger"><?php esc_html_e( 'Add Custom Trigger', 'wp-rest-endpoint-manager' ); ?></button>
			</p>
		</div>
		<?php
	}

	/**
	 * Render custom actions meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_actions_meta_box( $post ) {
		$custom_actions = get_post_meta( $post->ID, '_rem_custom_actions', true ) ?: array();
		?>
		<div class="rem-meta-box">
			<p class="description"><?php esc_html_e( 'Actions called after webhook is sent with ($webhook_id, $request_data, $response_data, $is_success).', 'wp-rest-endpoint-manager' ); ?></p>
			
			<div id="dispatch-custom-actions-list">
				<?php
				if ( ! empty( $custom_actions ) ) :
					foreach ( $custom_actions as $index => $action ) :
						?>
						<div class="action-item" style="margin-bottom: 8px;">
							<input type="text" name="rem_custom_actions[]" value="<?php echo esc_attr( $action ); ?>" class="widefat" placeholder="my_custom_action" style="font-size: 12px;" />
							<button type="button" class="button button-small remove-dispatch-action" style="margin-top: 3px;"><?php esc_html_e( 'Remove', 'wp-rest-endpoint-manager' ); ?></button>
						</div>
						<?php
					endforeach;
				endif;
				?>
			</div>
			
			<button type="button" class="button button-small" id="add-dispatch-action" style="margin-top: 8px;"><?php esc_html_e( 'Add Action', 'wp-rest-endpoint-manager' ); ?></button>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#add-dispatch-action').on('click', function() {
				$('#dispatch-custom-actions-list').append(`
					<div class="action-item" style="margin-bottom: 8px;">
						<input type="text" name="rem_custom_actions[]" value="" class="widefat" placeholder="my_custom_action" style="font-size: 12px;" />
						<button type="button" class="button button-small remove-dispatch-action" style="margin-top: 3px;"><?php esc_html_e( 'Remove', 'wp-rest-endpoint-manager' ); ?></button>
					</div>
				`);
			});

			$(document).on('click', '.remove-dispatch-action', function() {
				$(this).closest('.action-item').remove();
			});
		});
		</script>
		<?php
	}

	/**
	 * Save meta boxes.
	 *
	 * @param int     $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 */
	public function save_meta_boxes( $post_id, $post ) {
		if ( ! isset( $_POST['rem_dispatch_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['rem_dispatch_meta_box_nonce'], 'rem_dispatch_meta_box' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'rem_target_url' => 'esc_url_raw',
			'rem_http_method' => 'sanitize_text_field',
			'rem_payload_template' => 'wp_kses_post',
			'rem_status' => 'sanitize_text_field',
		);

		foreach ( $fields as $field => $sanitize_callback ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, '_' . $field, call_user_func( $sanitize_callback, $_POST[ $field ] ) );
			}
		}

		// Save trigger events.
		if ( isset( $_POST['rem_trigger_events'] ) && is_array( $_POST['rem_trigger_events'] ) ) {
			update_post_meta( $post_id, '_rem_trigger_events', array_map( 'sanitize_text_field', $_POST['rem_trigger_events'] ) );
		} else {
			update_post_meta( $post_id, '_rem_trigger_events', array() );
		}

		// Save custom actions.
		if ( isset( $_POST['rem_custom_actions'] ) && is_array( $_POST['rem_custom_actions'] ) ) {
			$actions = array_filter( array_map( 'sanitize_text_field', $_POST['rem_custom_actions'] ) );
			update_post_meta( $post_id, '_rem_custom_actions', $actions );
		} else {
			update_post_meta( $post_id, '_rem_custom_actions', array() );
		}
	}
}
