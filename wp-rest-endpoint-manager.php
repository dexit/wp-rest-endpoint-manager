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
 * Internal autoloader for plugin classes.
 */
require_once WP_REM_PLUGIN_DIR . 'includes/Autoloader.php';
WP_REST_Endpoint_Manager\Autoloader::register();

/**
 * Check for Composer dependencies.
 */
function wp_rem_check_dependencies() {
	if ( ! file_exists( WP_REM_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
		add_action( 'admin_notices', 'wp_rem_missing_dependencies_notice' );
		return false;
	}
	require_once WP_REM_PLUGIN_DIR . 'vendor/autoload.php';
	return true;
}

/**
 * Display admin notice if dependencies are missing.
 */
function wp_rem_missing_dependencies_notice() {
	?>
	<div class="notice notice-error">
		<p><?php echo wp_kses_post( __( '<strong>WP REST Endpoint Manager:</strong> Composer dependencies are missing. Please run <code>composer install</code> in the plugin directory or download a production-ready ZIP.', 'wp-rest-endpoint-manager' ) ); ?></p>
	</div>
	<?php
}

/**
 * Plugin activation hook.
 */
function activate_wp_rest_endpoint_manager() {
	if ( ! wp_rem_check_dependencies() ) {
		return;
	}
	WP_REST_Endpoint_Manager\Activator::activate();
}

/**
 * Plugin deactivation hook.
 */
function deactivate_wp_rest_endpoint_manager() {
	if ( file_exists( WP_REM_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
		require_once WP_REM_PLUGIN_DIR . 'vendor/autoload.php';
	}
	WP_REST_Endpoint_Manager\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wp_rest_endpoint_manager' );
register_deactivation_hook( __FILE__, 'deactivate_wp_rest_endpoint_manager' );

/**
 * Begin execution of the plugin.
 */
function run_wp_rest_endpoint_manager() {
	if ( ! wp_rem_check_dependencies() ) {
		return;
	}
	$plugin = WP_REST_Endpoint_Manager\Plugin::get_instance();
	$plugin->run();
}

run_wp_rest_endpoint_manager();

