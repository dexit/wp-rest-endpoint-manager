<?php
/**
 * Settings Admin Page
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Admin;

/**
 * Settings page using WordPress Settings API.
 */
class Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_wp_rem_export_settings', array( $this, 'ajax_export_settings' ) );
		add_action( 'wp_ajax_wp_rem_import_settings', array( $this, 'ajax_import_settings' ) );
	}

	/**
	 * AJAX handler for exporting settings.
	 */
	public function ajax_export_settings() {
		check_ajax_referer( 'wp_rest', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$options = array(
			'wp_rem_enable_logging' => get_option( 'wp_rem_enable_logging' ),
			'wp_rem_log_retention_days' => get_option( 'wp_rem_log_retention_days' ),
			'wp_rem_auto_cleanup' => get_option( 'wp_rem_auto_cleanup' ),
			'wp_rem_default_rate_limit' => get_option( 'wp_rem_default_rate_limit' ),
			'wp_rem_enable_cache' => get_option( 'wp_rem_enable_cache' ),
			'wp_rem_cache_duration' => get_option( 'wp_rem_cache_duration' ),
			'wp_rem_api_keys' => get_option( 'wp_rem_api_keys' ),
		);

		wp_send_json_success( array( 'settings' => $options ) );
	}

	/**
	 * AJAX handler for importing settings.
	 */
	public function ajax_import_settings() {
		check_ajax_referer( 'wp_rest', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$settings = isset( $_POST['settings'] ) ? json_decode( wp_unslash( $_POST['settings'] ), true ) : null;

		if ( ! $settings || ! is_array( $settings ) ) {
			wp_send_json_error( array( 'message' => 'Invalid settings data' ) );
		}

		foreach ( $settings as $key => $value ) {
			if ( strpos( $key, 'wp_rem_' ) === 0 ) {
				update_option( $key, $value );
			}
		}

		wp_send_json_success( array( 'message' => 'Settings imported successfully' ) );
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		// General settings.
		register_setting( 'wp_rem_settings', 'wp_rem_enable_logging' );
		register_setting( 'wp_rem_settings', 'wp_rem_log_retention_days' );
		register_setting( 'wp_rem_settings', 'wp_rem_auto_cleanup' );
		register_setting( 'wp_rem_settings', 'wp_rem_default_rate_limit' );
		register_setting( 'wp_rem_settings', 'wp_rem_enable_cache' );
		register_setting( 'wp_rem_settings', 'wp_rem_cache_duration' );
		register_setting( 'wp_rem_settings', 'wp_rem_api_keys' );
	}

	/**
	 * Render settings page.
	 */
	public function render() {
		if ( isset( $_POST['submit'] ) && check_admin_referer( 'wp_rem_settings_save', 'wp_rem_settings_nonce' ) ) {
			$this->save_settings();
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'wp-rest-endpoint-manager' ) . '</p></div>';
		}

		?>
		<div class="wrap wp-rem-settings">
			<h1><?php esc_html_e( 'REST Endpoint Manager Settings', 'wp-rest-endpoint-manager' ); ?></h1>

			<form method="post" action="">
				<?php wp_nonce_field( 'wp_rem_settings_save', 'wp_rem_settings_nonce' ); ?>

				<h2><?php esc_html_e( 'Logging Settings', 'wp-rest-endpoint-manager' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Logging', 'wp-rest-endpoint-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wp_rem_enable_logging" value="1" <?php checked( get_option( 'wp_rem_enable_logging', true ) ); ?> />
								<?php esc_html_e( 'Log all endpoint, ingest, and dispatch activity', 'wp-rest-endpoint-manager' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Log Retention (days)', 'wp-rest-endpoint-manager' ); ?></th>
						<td>
							<input type="number" name="wp_rem_log_retention_days" value="<?php echo esc_attr( get_option( 'wp_rem_log_retention_days', 90 ) ); ?>" min="1" max="365" />
							<p class="description"><?php esc_html_e( 'Logs older than this will be automatically deleted.', 'wp-rest-endpoint-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto Cleanup', 'wp-rest-endpoint-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wp_rem_auto_cleanup" value="1" <?php checked( get_option( 'wp_rem_auto_cleanup', true ) ); ?> />
								<?php esc_html_e( 'Automatically delete old logs daily', 'wp-rest-endpoint-manager' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Performance Settings', 'wp-rest-endpoint-manager' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Default Rate Limit', 'wp-rest-endpoint-manager' ); ?></th>
						<td>
							<input type="number" name="wp_rem_default_rate_limit" value="<?php echo esc_attr( get_option( 'wp_rem_default_rate_limit', 60 ) ); ?>" min="0" />
							<p class="description"><?php esc_html_e( 'Requests per minute (0 = unlimited)', 'wp-rest-endpoint-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Caching', 'wp-rest-endpoint-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wp_rem_enable_cache" value="1" <?php checked( get_option( 'wp_rem_enable_cache', false ) ); ?> />
								<?php esc_html_e( 'Cache GET responses by default', 'wp-rest-endpoint-manager' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cache Duration (seconds)', 'wp-rest-endpoint-manager' ); ?></th>
						<td>
							<input type="number" name="wp_rem_cache_duration" value="<?php echo esc_attr( get_option( 'wp_rem_cache_duration', 300 ) ); ?>" min="0" />
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'API Keys', 'wp-rest-endpoint-manager' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Authorized API Keys', 'wp-rest-endpoint-manager' ); ?></th>
						<td>
							<textarea name="wp_rem_api_keys" rows="5" class="large-text code"><?php echo esc_textarea( implode( "\n", get_option( 'wp_rem_api_keys', array() ) ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One API key per line', 'wp-rest-endpoint-manager' ); ?></p>
							<button type="button" class="button" id="generate-api-key"><?php esc_html_e( 'Generate New Key', 'wp-rest-endpoint-manager' ); ?></button>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Tools', 'wp-rest-endpoint-manager' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Export Settings', 'wp-rest-endpoint-manager' ); ?></th>
						<td>
							<button type="button" class="button" id="export-rem-settings"><?php esc_html_e( 'Export to JSON', 'wp-rest-endpoint-manager' ); ?></button>
							<p class="description"><?php esc_html_e( 'Download all plugin settings and configurations.', 'wp-rest-endpoint-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Import Settings', 'wp-rest-endpoint-manager' ); ?></th>
						<td>
							<input type="file" id="import-rem-settings-file" accept=".json" />
							<button type="button" class="button" id="import-rem-settings"><?php esc_html_e( 'Import from JSON', 'wp-rest-endpoint-manager' ); ?></button>
							<p class="description"><?php esc_html_e( 'Upload a JSON file to restore settings. WARNING: This will overwrite current settings.', 'wp-rest-endpoint-manager' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save settings.
	 */
	private function save_settings() {
		update_option( 'wp_rem_enable_logging', isset( $_POST['wp_rem_enable_logging'] ) ? 1 : 0 );
		update_option( 'wp_rem_log_retention_days', isset( $_POST['wp_rem_log_retention_days'] ) ? absint( $_POST['wp_rem_log_retention_days'] ) : 90 );
		update_option( 'wp_rem_auto_cleanup', isset( $_POST['wp_rem_auto_cleanup'] ) ? 1 : 0 );
		update_option( 'wp_rem_default_rate_limit', isset( $_POST['wp_rem_default_rate_limit'] ) ? absint( $_POST['wp_rem_default_rate_limit'] ) : 60 );
		update_option( 'wp_rem_enable_cache', isset( $_POST['wp_rem_enable_cache'] ) ? 1 : 0 );
		update_option( 'wp_rem_cache_duration', isset( $_POST['wp_rem_cache_duration'] ) ? absint( $_POST['wp_rem_cache_duration'] ) : 300 );

		if ( isset( $_POST['wp_rem_api_keys'] ) ) {
			$api_keys = array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( $_POST['wp_rem_api_keys'] ) ) ) );
			update_option( 'wp_rem_api_keys', $api_keys );
		}
	}
}
