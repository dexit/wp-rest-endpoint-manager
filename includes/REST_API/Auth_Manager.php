<?php
/**
 * Authentication Manager
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\REST_API;

/**
 * Manages authentication for REST endpoints.
 */
class Auth_Manager {

	/**
	 * Verify authentication.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @param string           $auth_type Authentication type.
	 * @return bool True if authenticated.
	 */
	public function verify( $request, $auth_type ) {
		switch ( $auth_type ) {
			case 'api_key':
				return $this->verify_api_key( $request );
			case 'jwt':
				return $this->verify_jwt( $request );
			case 'oauth':
				return $this->verify_oauth( $request );
			case 'none':
			default:
				return true;
		}
	}

	/**
	 * Verify API key.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool True if valid.
	 */
	private function verify_api_key( $request ) {
		$api_key = $request->get_header( 'X-API-Key' );
		if ( ! $api_key ) {
			$api_key = $request->get_param( 'api_key' );
		}

		if ( empty( $api_key ) ) {
			return false;
		}

		// Check against stored API keys.
		$valid_keys = get_option( 'wp_rem_api_keys', array() );
		return in_array( $api_key, $valid_keys, true );
	}

	/**
	 * Verify JWT token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool True if valid.
	 */
	private function verify_jwt( $request ) {
		$auth_header = $request->get_header( 'Authorization' );
		if ( ! $auth_header || ! preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
			return false;
		}

		$token = $matches[1];
		// Implement JWT validation here.
		// For now, return false as JWT requires external library.
		return false;
	}

	/**
	 * Verify OAuth.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool True if valid.
	 */
	private function verify_oauth( $request ) {
		// OAuth implementation would go here.
		// For now, fall back to WordPress authentication.
		return is_user_logged_in();
	}
}
