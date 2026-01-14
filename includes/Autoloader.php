<?php
/**
 * Internal Autoloader for WP REST Endpoint Manager
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager;

/**
 * Basic PSR-4 Autoloader implementation.
 */
class Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload function.
	 *
	 * @param string $class The class name to load.
	 */
	public static function autoload( $class ) {
		// Only load classes in our namespace.
		$prefix = __NAMESPACE__ . '\\';
		$len    = strlen( $prefix );

		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		// Get the relative class name.
		$relative_class = substr( $class, $len );

		// Replace namespace separators with directory separators.
		$file = plugin_dir_path( __FILE__ ) . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

		// If the file exists, require it.
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
}
