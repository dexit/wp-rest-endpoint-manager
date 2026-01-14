<?php
/**
 * Schema Custom Post Type
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Post_Types;

/**
 * Registers and manages the REST Schema CPT.
 */
class Schema_Cpt {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'rest_schema';

	/**
	 * Register the custom post type.
	 */
	public function register() {
		$labels = array(
			'name'                  => _x( 'Schemas', 'Post Type General Name', 'wp-rest-endpoint-manager' ),
			'singular_name'         => _x( 'Schema', 'Post Type Singular Name', 'wp-rest-endpoint-manager' ),
			'menu_name'             => __( 'Schemas', 'wp-rest-endpoint-manager' ),
			'add_new_item'          => __( 'Add New Schema', 'wp-rest-endpoint-manager' ),
			'edit_item'             => __( 'Edit Schema', 'wp-rest-endpoint-manager' ),
		);

		$args = array(
			'label'                 => __( 'Schema', 'wp-rest-endpoint-manager' ),
			'description'           => __( 'JSON Schema Definitions', 'wp-rest-endpoint-manager' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor' ),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => false,
			'menu_icon'             => 'dashicons-clipboard',
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
			'rem_schema_editor',
			__( 'Schema Definition', 'wp-rest-endpoint-manager' ),
			array( $this, 'render_schema_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'rem_schema_builder',
			__( 'Visual Schema Builder', 'wp-rest-endpoint-manager' ),
			array( $this, 'render_builder_meta_box' ),
			self::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render schema editor meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_schema_meta_box( $post ) {
		wp_nonce_field( 'rem_schema_meta_box', 'rem_schema_meta_box_nonce' );

		$schema_json = get_post_meta( $post->ID, '_rem_schema_json', true );
		$schema_type = get_post_meta( $post->ID, '_rem schema_type', true ) ?: 'general';

		if ( empty( $schema_json ) ) {
			$schema_json = $this->get_template_schema();
		}
		?>
		<div class="rem-meta-box">
			<p>
				<label for="rem_schema_type"><strong><?php esc_html_e( 'Schema Type', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<select id="rem_schema_type" name="rem_schema_type" class="widefat">
					<option value="request" <?php selected( $schema_type, 'request' ); ?>><?php esc_html_e( 'Request Schema', 'wp-rest-endpoint-manager' ); ?></option>
					<option value="response" <?php selected( $schema_type, 'response' ); ?>><?php esc_html_e( 'Response Schema', 'wp-rest-endpoint-manager' ); ?></option>
					<option value="general" <?php selected( $schema_type, 'general' ); ?>><?php esc_html_e( 'General Schema', 'wp-rest-endpoint-manager' ); ?></option>
				</select>
			</p>

			<p>
				<label for="rem_schema_json"><strong><?php esc_html_e( 'JSON Schema', 'wp-rest-endpoint-manager' ); ?></strong></label>
				<div id="schema-editor-container" style="height: 400px; border: 1px solid #ddd;"></div>
				<textarea id="rem_schema_json" name="rem_schema_json" style="display:none;"><?php echo esc_textarea( $schema_json ); ?></textarea>
			</p>

			<p>
				<button type="button" class="button button-secondary" id="rem-validate-schema">
					<?php esc_html_e( 'Validate Schema', 'wp-rest-endpoint-manager' ); ?>
				</button>
			</p>
		</div>

		<script>
		jQuery(document).ready(function($) {
			if (typeof initMonacoEditor === 'function') {
				initMonacoEditor('schema-editor-container', 'rem_schema_json', 'json');
			}
		});
		</script>
		<?php
	}

	/**
	 * Render visual builder meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_builder_meta_box( $post ) {
		?>
		<div class="rem-meta-box">
			<div id="schema-builder-app" class="schema-builder">
				<p><em><?php esc_html_e( 'Visual schema builder will be initialized here.', 'wp-rest-endpoint-manager' ); ?></em></p>
			</div>
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
		if ( ! isset( $_POST['rem_schema_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['rem_schema_meta_box_nonce'], 'rem_schema_meta_box' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['rem_schema_json'] ) ) {
			update_post_meta( $post_id, '_rem_schema_json', wp_unslash( $_POST['rem_schema_json'] ) );
		}

		if ( isset( $_POST['rem_schema_type'] ) ) {
			update_post_meta( $post_id, '_rem_schema_type', sanitize_text_field( $_POST['rem_schema_type'] ) );
		}
	}

	/**
	 * Get template schema.
	 *
	 * @return string Template JSON schema.
	 */
	private function get_template_schema() {
		return json_encode( array(
			'$schema' => 'http://json-schema.org/draft-07/schema#',
			'type' => 'object',
			'properties' => array(
				'name' => array(
					'type' => 'string',
					'minLength' => 1,
					'maxLength' => 100,
				),
				'email' => array(
					'type' => 'string',
					'format' => 'email',
				),
			),
			'required' => array( 'name' ),
		), JSON_PRETTY_PRINT );
	}
}
