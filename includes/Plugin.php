<?php
/**
 * Main Plugin Class
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 */
class Plugin {

	/**
	 * The single instance of the class.
	 *
	 * @var Plugin
	 */
	protected static $instance = null;

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * @var Admin\API_Tester
	 */
	protected $api_tester;

	/**
	 * @var Admin\Log_Viewer
	 */
	protected $log_viewer;

	/**
	 * @var Admin\Settings
	 */
	protected $settings;

	/**
	 * Main Plugin Instance.
	 *
	 * Ensures only one instance of Plugin is loaded or can be loaded.
	 *
	 * @return Plugin - Main instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Plugin constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_rest_api_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		// Logger is initialized manually as it is used immediately.
		$this->logger = new Logger();
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		// Register custom post types.
		$rest_endpoint_cpt = new Post_Types\Rest_Endpoint_Cpt();
		$controller_cpt = new Post_Types\Controller_Cpt();
		$schema_cpt = new Post_Types\Schema_Cpt();
		$ingest_webhook_cpt = new Post_Types\Ingest_Webhook_Cpt();
		$dispatch_webhook_cpt = new Post_Types\Dispatch_Webhook_Cpt();

		add_action( 'init', array( $rest_endpoint_cpt, 'register' ) );
		add_action( 'init', array( $controller_cpt, 'register' ) );
		add_action( 'init', array( $schema_cpt, 'register' ) );
		add_action( 'init', array( $ingest_webhook_cpt, 'register' ) );
		add_action( 'init', array( $dispatch_webhook_cpt, 'register' ) );
		add_action( 'init', array( $this, 'register_post_meta' ) );

		// Register AJAX handlers.
		// These need to be registered early, especially for admin-ajax requests.
		$this->api_tester = new Admin\API_Tester();
		$this->log_viewer = new Admin\Log_Viewer();
		$this->settings = new Admin\Settings();

		// Register admin menu.
		$pointers = new Admin\Pointers();
		add_action( 'admin_init', array( $pointers, 'init' ) );
		
		$admin_menu = new Admin\Admin_Menu();
		add_action( 'admin_menu', array( $admin_menu, 'register_menu' ) );

		// Enqueue admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register custom post meta for REST API / Block Editor support.
	 */
	public function register_post_meta() {
		// REST Endpoint Meta
		$endpoint_meta = array(
			'_rem_namespace', '_rem_route', '_rem_methods', '_rem_auth_type', 
			'_rem_auth_config', '_rem_callback_type', '_rem_controller_id', 
			'_rem_callback_func', '_rem_status', '_rem_version'
		);

		foreach ( $endpoint_meta as $key ) {
			register_post_meta( 'rest_endpoint', $key, array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			) );
		}

		// Controller Meta
		register_post_meta( 'rest_controller', '_rem_controller_code', array(
			'show_in_rest' => true,
			'single'       => true,
			'type'         => 'string',
		) );

		// Schema Meta
		register_post_meta( 'rest_schema', '_rem_schema_json', array(
			'show_in_rest' => true,
			'single'       => true,
			'type'         => 'string',
		) );

		// Ingest Webhook Meta
		$ingest_meta = array( '_rem_webhook_slug', '_rem_webhook_token', '_rem_status', '_rem_custom_actions' );
		foreach ( $ingest_meta as $key ) {
			register_post_meta( 'ingest_webhook', $key, array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => ( $key === '_rem_custom_actions' ) ? 'array' : 'string',
				'show_in_rest' => array(
					'schema' => array(
						'type' => ( $key === '_rem_custom_actions' ) ? 'array' : 'string',
						'items' => array( 'type' => 'string' ),
					),
				),
			) );
		}

		// Dispatch Webhook Meta
		$dispatch_meta = array( '_rem_webhook_url', '_rem_status', '_rem_methods' );
		foreach ( $dispatch_meta as $key ) {
			register_post_meta( 'dispatch_webhook', $key, array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => ( $key === '_rem_methods' ) ? 'array' : 'string',
			) );
		}
	}

	/**
	 * Register all hooks related to REST API functionality.
	 */
	private function define_rest_api_hooks() {
		// Register custom endpoints.
		$endpoint_handler = new REST_API\Endpoint_Handler();
		add_action( 'rest_api_init', array( $endpoint_handler, 'register_routes' ) );

		// Register ingest endpoints.
		$ingest_handler = new Ingest\Ingest_Handler();
		add_action( 'rest_api_init', array( $ingest_handler, 'register_routes' ) );

		// Initialize dispatch handler.
		$dispatch_handler = new Dispatch\Dispatch_Handler();
		add_action( 'init', array( $dispatch_handler, 'init' ) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our admin pages.
		if ( strpos( $hook, 'wp-rem' ) === false && ! $this->is_our_cpt_page() ) {
			return;
		}

		// Enqueue styles.
		wp_enqueue_style(
			'wp-rem-admin',
			WP_REM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WP_REM_VERSION
		);

		// Enqueue scripts.
		wp_enqueue_script(
			'wp-rem-admin',
			WP_REM_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-api' ),
			WP_REM_VERSION,
			true
		);

		// Enqueue Monaco Editor for code editing.
		wp_enqueue_script(
			'wp-rem-monaco-loader',
			'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js',
			array(),
			'0.45.0',
			true
		);

		wp_enqueue_script(
			'wp-rem-code-editor',
			WP_REM_PLUGIN_URL . 'assets/js/code-editor.js',
			array( 'wp-rem-monaco-loader' ),
			WP_REM_VERSION,
			true
		);

		wp_enqueue_script(
			'wp-rem-autofill',
			WP_REM_PLUGIN_URL . 'assets/js/autofill-helpers.js',
			array( 'jquery' ),
			WP_REM_VERSION,
			true
		);

		// Localize script with admin data.
		wp_localize_script(
			'wp-rem-admin',
			'wpRemData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => rest_url(),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'pluginUrl' => WP_REM_PLUGIN_URL,
				'i18n' => array(
					'suggest_namespace' => __( 'Suggested Namespace', 'wp-rest-endpoint-manager' ),
					'common_patterns' => __( 'Common Patterns', 'wp-rest-endpoint-manager' ),
					'get_help' => __( 'Read data only. Idempotent.', 'wp-rest-endpoint-manager' ),
					'post_help' => __( 'Create new resources or process data.', 'wp-rest-endpoint-manager' ),
					'put_help' => __( 'Update existing resources.', 'wp-rest-endpoint-manager' ),
					'delete_help' => __( 'Remove resources.', 'wp-rest-endpoint-manager' ),
				),
			)
		);
	}

	/**
	 * Check if current page is one of our CPT edit pages.
	 *
	 * @return bool
	 */
	private function is_our_cpt_page() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		$our_cpts = array(
			'rest_endpoint',
			'rest_controller',
			'rest_schema',
			'ingest_webhook',
			'dispatch_webhook',
		);

		return in_array( $screen->post_type, $our_cpts, true );
	}

	/**
	 * Run the plugin.
	 */
	public function run() {
		// Plugin is initialized via hooks.
	}

	/**
	 * Get logger instance.
	 *
	 * @return Logger
	 */
	public function get_logger() {
		return $this->logger;
	}

	/**
	 * Get API Tester instance.
	 *
	 * @return Admin\API_Tester
	 */
	public function get_api_tester() {
		return $this->api_tester;
	}

	/**
	 * Get Log Viewer instance.
	 *
	 * @return Admin\Log_Viewer
	 */
	public function get_log_viewer() {
		return $this->log_viewer;
	}

	/**
	 * Get Settings instance.
	 *
	 * @return Admin\Settings
	 */
	public function get_settings() {
		return $this->settings;
	}
}
