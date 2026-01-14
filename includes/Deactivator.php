<?php
/**
 * Plugin Deactivator
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager;

/**
 * Fired during plugin deactivation.
 */
class Deactivator {

	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate() {
		// Flush rewrite rules.
		flush_rewrite_rules();

		// Clear any scheduled events.
		wp_clear_scheduled_hook( 'wp_rem_cleanup_logs' );

		// Note: We don't delete post types or data on deactivation.
		// Users may want to reactivate the plugin later.
	}
}
