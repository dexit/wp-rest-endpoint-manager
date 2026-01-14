<?php
/**
 * Ingest Webhook Custom Post Type
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Post_Types;

/**
 * Registers and manages the Ingest Webhook CPT.
 */
class Ingest_Webhook_Cpt {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'ingest_webhook';

	/**
	 * Register the custom post type.
	 */
	public function register() {
		$labels = array(
			'name'                  => _x( 'Ingest Webhooks', 'Post Type General Name', 'wp-rest-endpoint-manager' ),
			'singular_name'         => _x( 'Ingest Webhook', 'Post Type Singular Name', 'wp-rest-endpoint-manager' ),
			'menu_name'             => __( 'Ingest Webhooks', 'wp-rest-endpoint-manager' ),
			'add_new_item'          => __( 'Add New Webhook', 'wp-rest-endpoint-manager' ),
		);

		$args = array(
			'label'                 => __( 'Ingest Webhook', 'wp-rest-endpoint-manager' ),
			'description'           => __( 'Incoming Webhooks', 'wp-rest-endpoint-manager' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', 'comments' ),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => false,
			'menu_icon'             => 'dashicons-download',
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
			'rem_ingest_config',
			__( 'Webhook Configuration', 'wp-rest-endpoint-manager' ),
			array( $this, 'render_config_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'rem_ingest_actions',
			__( 'WordPress Actions & Filters', 'wp-rest-endpoint-manager' ),
			array( $this, 'render_actions_meta_box' ),
			self::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render config meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_config_meta_box( $post ) {
		wp_nonce_field( 'rem_ingest_meta_box', 'rem_ingest_meta_box_nonce' );

		$webhook_slug = get_post_meta( $post->ID, '_rem_webhook_slug', true ) ?: sanitize_title( $post->post_title );
		$webhook_token = get_post_meta( $post->ID, '_rem_webhook_token', true ) ?: wp_generate_password( 32, false );
		$status = get_post_meta( $post->ID, '_rem_status', true ) ?: 'inactive';
		$webhook_url = rest_url( 'rem/v1/ingest/' . $webhook_slug );
		?>
		<div class="rem-meta-box">
			<p>
				<label for="rem_webhook_slug"><strong><?php esc_html_e( 'Webhook Slug', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<input type="text" id="rem_webhook_slug" name="rem_webhook_slug" value="<?php echo esc_attr( $webhook_slug ); ?>" class="widefat" />
			</p>

			<p>
				<label for="rem_webhook_token"><strong><?php esc_html_e( 'Authentication Token', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<input type="text" id="rem_webhook_token" name="rem_webhook_token" value="<?php echo esc_attr( $webhook_token ); ?>" class="widefat" readonly />
				<button type="button" class="button button-secondary" id="rem-regenerate-token"><?php esc_html_e( 'Regenerate', 'wp-rest-endpoint-manager' ); ?></button>
			</p>

			<p>
				<strong><?php esc_html_e( 'Webhook URL:', 'wp-rest-endpoint-manager' ); ?></strong><br />
				<code style="word-break: break-all;"><?php echo esc_html( $webhook_url ); ?></code>
				<button type="button" class="button button-secondary" onclick="navigator.clipboard.writeText('<?php echo esc_js( $webhook_url ); ?>');"><?php esc_html_e( 'Copy', 'wp-rest-endpoint-manager' ); ?></button>
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
	 * Render actions & filters meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_actions_meta_box( $post ) {
		$custom_actions = get_post_meta( $post->ID, '_rem_custom_actions', true ) ?: array();
		$custom_filters = get_post_meta( $post->ID, '_rem_custom_filters', true ) ?: array();
		?>
		<div class="rem-meta-box">
			<h3><?php esc_html_e( 'Custom WordPress Actions', 'wp-rest-endpoint-manager' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Define custom actions to trigger when this webhook receives data. These will be called with ($mapped_data, $raw_data, $webhook_id).', 'wp-rest-endpoint-manager' ); ?></p>
			
			<div id="custom-actions-list">
				<?php
				if ( ! empty( $custom_actions ) ) :
					foreach ( $custom_actions as $index => $action ) :
						?>
						<div class="action-item" style="margin-bottom: 10px; padding: 10px; background: #f6f7f7; border-radius: 4px;">
							<input type="text" name="rem_custom_actions[]" value="<?php echo esc_attr( $action ); ?>" class="widefat" placeholder="my_custom_action" />
							<button type="button" class="button remove-action-item" style="margin-top: 5px;"><?php esc_html_e( 'Remove', 'wp-rest-endpoint-manager' ); ?></button>
						</div>
						<?php
					endforeach;
				endif;
				?>
			</div>
			
			<button type="button" class="button" id="add-custom-action"><?php esc_html_e( 'Add Action', 'wp-rest-endpoint-manager' ); ?></button>

			<hr style="margin: 20px 0;" />

			<h3><?php esc_html_e( 'Custom Data Transformation Filters', 'wp-rest-endpoint-manager' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Define custom filter names for data transformation. Use these in data mapping rules as transform type.', 'wp-rest-endpoint-manager' ); ?></p>
			
			<div id="custom-filters-list">
				<?php
				if ( ! empty( $custom_filters ) ) :
					foreach ( $custom_filters as $index => $filter ) :
						?>
						<div class="filter-item" style="margin-bottom: 10px; padding: 10px; background: #f6f7f7; border-radius: 4px;">
							<label style="display: block; margin-bottom: 5px;"><strong><?php esc_html_e( 'Filter Name:', 'wp-rest-endpoint-manager' ); ?></strong></label>
							<input type="text" name="rem_custom_filters[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $filter['name'] ?? '' ); ?>" class="widefat" placeholder="my_custom_transform" style="margin-bottom: 5px;" />
							
							<label style="display: block; margin-bottom: 5px;"><strong><?php esc_html_e( 'Description:', 'wp-rest-endpoint-manager' ); ?></strong></label>
							<input type="text" name="rem_custom_filters[<?php echo esc_attr( $index ); ?>][description]" value="<?php echo esc_attr( $filter['description'] ?? '' ); ?>" class="widefat" placeholder="What this filter does" />
							
							<button type="button" class="button remove-filter-item" style="margin-top: 5px;"><?php esc_html_e( 'Remove', 'wp-rest-endpoint-manager' ); ?></button>
						</div>
						<?php
					endforeach;
				endif;
				?>
			</div>
			
			<button type="button" class="button" id="add-custom-filter"><?php esc_html_e( 'Add Filter', 'wp-rest-endpoint-manager' ); ?></button>

			<div class="notice notice-info inline" style="margin-top: 15px;">
				<p><strong><?php esc_html_e( 'How to use:', 'wp-rest-endpoint-manager' ); ?></strong></p>
				<p><?php esc_html_e( 'Actions: Add your action name (e.g., "my_custom_action"), then use add_action() in your theme/plugin to hook into it.', 'wp-rest-endpoint-manager' ); ?></p>
				<p><?php esc_html_e( 'Filters: Add filter name, then use add_filter() to handle the transformation. The filter will be available as "wp_rem_data_mapper_transform_{name}".', 'wp-rest-endpoint-manager' ); ?></p>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Add action
			$('#add-custom-action').on('click', function() {
				$('#custom-actions-list').append(`
					<div class="action-item" style="margin-bottom: 10px; padding: 10px; background: #f6f7f7; border-radius: 4px;">
						<input type="text" name="rem_custom_actions[]" value="" class="widefat" placeholder="my_custom_action" />
						<button type="button" class="button remove-action-item" style="margin-top: 5px;"><?php esc_html_e( 'Remove', 'wp-rest-endpoint-manager' ); ?></button>
					</div>
				`);
			});

			// Remove action
			$(document).on('click', '.remove-action-item', function() {
				$(this).closest('.action-item').remove();
			});

			// Add filter
			let filterIndex = <?php echo count( $custom_filters ); ?>;
			$('#add-custom-filter').on('click', function() {
				$('#custom-filters-list').append(`
					<div class="filter-item" style="margin-bottom: 10px; padding: 10px; background: #f6f7f7; border-radius: 4px;">
						<label style="display: block; margin-bottom: 5px;"><strong><?php esc_html_e( 'Filter Name:', 'wp-rest-endpoint-manager' ); ?></strong></label>
						<input type="text" name="rem_custom_filters[${filterIndex}][name]" value="" class="widefat" placeholder="my_custom_transform" style="margin-bottom: 5px;" />
						
						<label style="display: block; margin-bottom: 5px;"><strong><?php esc_html_e( 'Description:', 'wp-rest-endpoint-manager' ); ?></strong></label>
						<input type="text" name="rem_custom_filters[${filterIndex}][description]" value="" class="widefat" placeholder="What this filter does" />
						
						<button type="button" class="button remove-filter-item" style="margin-top: 5px;"><?php esc_html_e( 'Remove', 'wp-rest-endpoint-manager' ); ?></button>
					</div>
				`);
				filterIndex++;
			});

			// Remove filter
			$(document).on('click', '.remove-filter-item', function() {
				$(this).closest('.filter-item').remove();
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
		if ( ! isset( $_POST['rem_ingest_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['rem_ingest_meta_box_nonce'], 'rem_ingest_meta_box' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'rem_webhook_slug' => 'sanitize_title',
			'rem_webhook_token' => 'sanitize_text_field',
			'rem_status' => 'sanitize_text_field',
		);

		foreach ( $fields as $field => $sanitize_callback ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, '_' . $field, call_user_func( $sanitize_callback, $_POST[ $field ] ) );
			}
		}

		// Save custom actions.
		if ( isset( $_POST['rem_custom_actions'] ) && is_array( $_POST['rem_custom_actions'] ) ) {
			$actions = array_filter( array_map( 'sanitize_text_field', $_POST['rem_custom_actions'] ) );
			update_post_meta( $post_id, '_rem_custom_actions', $actions );
		} else {
			update_post_meta( $post_id, '_rem_custom_actions', array() );
		}

		// Save custom filters.
		if ( isset( $_POST['rem_custom_filters'] ) && is_array( $_POST['rem_custom_filters'] ) ) {
			$filters = array();
			foreach ( $_POST['rem_custom_filters'] as $filter ) {
				if ( ! empty( $filter['name'] ) ) {
					$filters[] = array(
						'name' => sanitize_text_field( $filter['name'] ),
						'description' => sanitize_text_field( $filter['description'] ?? '' ),
					);
				}
			}
			update_post_meta( $post_id, '_rem_custom_filters', $filters );
		} else {
			update_post_meta( $post_id, '_rem_custom_filters', array() );
		}
	}
}
