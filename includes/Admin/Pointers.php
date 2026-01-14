<?php
/**
 * Admin Pointers - Guided tours for the plugin UI
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Admin;

/**
 * Handles registration and display of WP Admin Pointers.
 */
class Pointers {

	/**
	 * Initialize pointers.
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_pointers' ), 20 );
	}

	/**
	 * Enqueue pointer scripts and styles.
	 */
	public function enqueue_pointers() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$pointers = $this->get_pointers_for_screen( $screen->id );
		if ( empty( $pointers ) ) {
			return;
		}

		// Check which pointers have already been dismissed.
		$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
		$active_pointers = array();

		foreach ( $pointers as $id => $pointer ) {
			if ( ! in_array( $id, $dismissed, true ) ) {
				$pointer['id'] = $id;
				$active_pointers[] = $pointer;
			}
		}

		if ( empty( $active_pointers ) ) {
			return;
		}

		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );

		wp_localize_script( 'wp-pointer', 'wpRemPointers', array(
			'pointers' => $active_pointers,
		) );

		wp_enqueue_script(
			'wp-rem-pointers',
			WP_REM_PLUGIN_URL . 'assets/js/admin-pointers.js',
			array( 'wp-pointer', 'jquery' ),
			WP_REM_VERSION,
			true
		);
	}

	/**
	 * Get pointers for a specific screen.
	 *
	 * @param string $screen_id The current screen ID.
	 * @return array Pointers configuration.
	 */
	private function get_pointers_for_screen( $screen_id ) {
		$pointers = array();

		// Dashboard pointers.
		if ( 'toplevel_page_wp-rem-dashboard' === $screen_id ) {
			$pointers['rem_welcome_pointer'] = array(
				'target' => 'h1',
				'options' => array(
					'content' => '<h3>' . __( 'Welcome to REST Manager', 'wp-rest-endpoint-manager' ) . '</h3>' .
								 '<p>' . __( 'This dashboard gives you an overview of your custom API activity. Let’s take a quick tour!', 'wp-rest-endpoint-manager' ) . '</p>',
					'position' => array( 'edge' => 'top', 'align' => 'left' ),
				),
			);

			$pointers['rem_stats_pointer'] = array(
				'target' => '.wp-rem-stats-grid',
				'options' => array(
					'content' => '<h3>' . __( 'Activity Highlights', 'wp-rest-endpoint-manager' ) . '</h3>' .
								 '<p>' . __( 'Monitor your endpoints, controllers, and webhooks at a glance.', 'wp-rest-endpoint-manager' ) . '</p>',
					'position' => array( 'edge' => 'bottom', 'align' => 'center' ),
				),
			);
		}

		// REST Endpoint CPT pointers.
		if ( 'edit-rest_endpoint' === $screen_id ) {
			$pointers['rem_new_endpoint_pointer'] = array(
				'target' => '.page-title-action',
				'options' => array(
					'content' => '<h3>' . __( 'Create Your First Route', 'wp-rest-endpoint-manager' ) . '</h3>' .
								 '<p>' . __( 'Click here to define a new custom REST API endpoint.', 'wp-rest-endpoint-manager' ) . '</p>',
					'position' => array( 'edge' => 'left', 'align' => 'center' ),
				),
			);
		}

		// Meta box specific pointers for rest_endpoint editor.
		if ( 'rest_endpoint' === $screen_id ) {
			$pointers['rem_namespace_pointer'] = array(
				'target' => 'input[name="rem_namespace"]',
				'options' => array(
					'content' => '<h3>' . __( 'API Namespace', 'wp-rest-endpoint-manager' ) . '</h3>' .
								 '<p>' . __( 'The first part of your URL, e.g., "my-plugin/v1".', 'wp-rest-endpoint-manager' ) . '</p>',
					'position' => array( 'edge' => 'top', 'align' => 'left' ),
				),
			);
			
			$pointers['rem_callback_pointer'] = array(
				'target' => 'select[name="rem_callback_type"]',
				'options' => array(
					'content' => '<h3>' . __( 'Flexible Logic', 'wp-rest-endpoint-manager' ) . '</h3>' .
								 '<p>' . __( 'Choose between proxying requests, using custom PHP controllers, or inline code.', 'wp-rest-endpoint-manager' ) . '</p>',
					'position' => array( 'edge' => 'bottom', 'align' => 'left' ),
				),
			);
		}

		return $pointers;
	}
}
