<?php
/**
 * Admin Menu Registration
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Admin;

/**
 * Registers admin menu structure.
 */
class Admin_Menu {

	/**
	 * Register admin menu.
	 */
	public function register_menu() {
		// Main menu.
		add_menu_page(
			__( 'REST Endpoint Manager', 'wp-rest-endpoint-manager' ),
			__( 'REST Manager', 'wp-rest-endpoint-manager' ),
			'manage_options',
			'wp-rem-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-rest-api',
			30
		);

		// Dashboard submenu (duplicate to show in submenu).
		add_submenu_page(
			'wp-rem-dashboard',
			__( 'Dashboard', 'wp-rest-endpoint-manager' ),
			__( 'Dashboard', 'wp-rest-endpoint-manager' ),
			'manage_options',
			'wp-rem-dashboard',
			array( $this, 'render_dashboard' )
		);

		// REST Endpoints (CPT).
		add_submenu_page(
			'wp-rem-dashboard',
			__( 'REST Endpoints', 'wp-rest-endpoint-manager' ),
			__( 'REST Endpoints', 'wp-rest-endpoint-manager' ),
			'manage_options',
			'edit.php?post_type=rest_endpoint'
		);

		// Controllers (CPT).
		add_submenu_page(
			'wp-rem-dashboard',
			__( 'Controllers', 'wp-rest-endpoint-manager' ),
			__( 'Controllers', 'wp-rest-endpoint-manager' ),
			'manage_options',
			'edit.php?post_type=rest_controller'
		);

		// Schemas (CPT).
		add_submenu_page(
			'wp-rem-dashboard',
			__( 'Schemas', 'wp-rest-endpoint-manager' ),
			__( 'Schemas', 'wp-rest-endpoint-manager' ),
			'manage_options',
			'edit.php?post_type=rest_schema'
		);

		// Ingest Webhooks (CPT).
		add_submenu_page(
			'wp-rem-dashboard',
			__( 'Ingest Webhooks', 'wp-rest-endpoint-manager' ),
			__( 'Ingest Webhooks', 'wp-rest-endpoint-manager' ),
			'manage_options',
			'edit.php?post_type=ingest_webhook'
		);

		// Dispatch Webhooks (CPT).
		add_submenu_page(
			'wp-rem-dashboard',
			__( 'Dispatch Webhooks', 'wp-rest-endpoint-manager' ),
			__( 'Dispatch Webhooks', 'wp-rest-endpoint-manager' ),
			'manage_options',
			'edit.php?post_type=dispatch_webhook'
		);

		// Logs.
		add_submenu_page(
			'wp-rem-dashboard',
			__( 'Logs', 'wp-rest-endpoint-manager' ),
			__( 'Logs', 'wp-rest-endpoint-manager' ),
			'manage_options',
			'wp-rem-logs',
			array( $this, 'render_logs' )
		);

		// API Tester.
		add_submenu_page(
			'wp-rem-dashboard',
			__( 'API Tester', 'wp-rest-endpoint-manager' ),
			__( 'API Tester', 'wp-rest-endpoint-manager' ),
			'manage_options',
			'wp-rem-api-tester',
			array( $this, 'render_api_tester' )
		);

		// Settings.
		add_submenu_page(
			'wp-rem-dashboard',
			__( 'Settings', 'wp-rest-endpoint-manager' ),
			__( 'Settings', 'wp-rest-endpoint-manager' ),
			'manage_options',
			'wp-rem-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard() {
		$dashboard = new Dashboard();
		$dashboard->render();
	}

	/**
	 * Render logs page.
	 */
	public function render_logs() {
		$log_viewer = \WP_REST_Endpoint_Manager\Plugin::get_instance()->get_log_viewer();
		if ( $log_viewer ) {
			$log_viewer->render();
		}
	}

	/**
	 * Render API tester page.
	 */
	public function render_api_tester() {
		$api_tester = \WP_REST_Endpoint_Manager\Plugin::get_instance()->get_api_tester();
		if ( $api_tester ) {
			$api_tester->render();
		}
	}

	/**
	 * Render settings page.
	 */
	public function render_settings() {
		$settings = \WP_REST_Endpoint_Manager\Plugin::get_instance()->get_settings();
		if ( $settings ) {
			$settings->render();
		}
	}
}
