<?php
/**
 * Plugin Activator
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager;

/**
 * Fired during plugin activation.
 */
class Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate() {
		// Flush rewrite rules after registering custom post types.
		self::register_post_types();
		flush_rewrite_rules();

		// Create default options.
		self::create_default_options();

		// Set activation timestamp.
		update_option( 'wp_rem_activated_at', time() );
		update_option( 'wp_rem_version', WP_REM_VERSION );
	}

	/**
	 * Register all custom post types for flush_rewrite_rules().
	 */
	private static function register_post_types() {
		$rest_endpoint_cpt = new Post_Types\Rest_Endpoint_Cpt();
		$controller_cpt = new Post_Types\Controller_Cpt();
		$schema_cpt = new Post_Types\Schema_Cpt();
		$ingest_webhook_cpt = new Post_Types\Ingest_Webhook_Cpt();
		$dispatch_webhook_cpt = new Post_Types\Dispatch_Webhook_Cpt();

		$rest_endpoint_cpt->register();
		$controller_cpt->register();
		$schema_cpt->register();
		$ingest_webhook_cpt->register();
		$dispatch_webhook_cpt->register();
	}

	/**
	 * Create default plugin options.
	 */
	private static function create_default_options() {
		$defaults = array(
			'wp_rem_log_retention_days' => 90,
			'wp_rem_default_rate_limit' => 60,
			'wp_rem_enable_logging' => true,
			'wp_rem_log_level' => 'all',
			'wp_rem_auto_cleanup' => true,
			'wp_rem_default_auth' => 'none',
			'wp_rem_enable_cache' => false,
			'wp_rem_cache_duration' => 300,
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}
}
