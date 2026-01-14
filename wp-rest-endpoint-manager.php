<?php
/**
 * Plugin Name: WP REST Endpoint Manager
 * Plugin URI: https://github.com/dexit/wp-rest-endpoint-manager
 * Description: Complete WordPress REST API Management Suite with visual UI and custom code support. Manage routes, controllers, schemas, webhooks with extensive logging.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://github.com/dexit
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-rest-endpoint-manager
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'WP_REM_VERSION', '1.0.0' );
define( 'WP_REM_PLUGIN_FILE', __FILE__ );
define( 'WP_REM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_REM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_REM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Plugin activation hook.
 */
function activate_wp_rest_endpoint_manager() {
	require_once WP_REM_PLUGIN_DIR . 'includes/Activator.php';
	WP_REST_Endpoint_Manager\Activator::activate();
}

/**
 * Plugin deactivation hook.
 */
function deactivate_wp_rest_endpoint_manager() {
	require_once WP_REM_PLUGIN_DIR . 'includes/Deactivator.php';
	WP_REST_Endpoint_Manager\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wp_rest_endpoint_manager' );
register_deactivation_hook( __FILE__, 'deactivate_wp_rest_endpoint_manager' );

/**
 * Autoloader for plugin classes (Composer).
 */
if ( file_exists( WP_REM_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once WP_REM_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Begin execution of the plugin.
 */
function run_wp_rest_endpoint_manager() {
	$plugin = WP_REST_Endpoint_Manager\Plugin::get_instance();
	$plugin->run();
}

run_wp_rest_endpoint_manager();
