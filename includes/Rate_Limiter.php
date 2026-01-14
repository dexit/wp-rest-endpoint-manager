<?php
/**
 * Rate Limiter Class
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager;

/**
 * Rate limiting implementation using WordPress transients.
 */
class Rate_Limiter {

	/**
	 * Check if request is within rate limit.
	 *
	 * @param string $identifier Unique identifier (IP, user ID, API key).
	 * @param int    $limit Requests per minute.
	 * @param string $scope Scope (endpoint ID or global).
	 * @return bool True if within limit, false if exceeded.
	 */
	public function is_allowed( $identifier, $limit, $scope = 'global' ) {
		if ( $limit <= 0 ) {
			return true; // No limit.
		}

		$transient_key = $this->get_transient_key( $identifier, $scope );
		$current_count = get_transient( $transient_key );

		if ( false === $current_count ) {
			// First request in this minute.
			set_transient( $transient_key, 1, 60 );
			return true;
		}

		if ( $current_count >= $limit ) {
			return false; // Rate limit exceeded.
		}

		// Increment counter.
		set_transient( $transient_key, $current_count + 1, 60 );
		return true;
	}

	/**
	 * Get remaining requests.
	 *
	 * @param string $identifier Unique identifier.
	 * @param int    $limit Requests per minute.
	 * @param string $scope Scope.
	 * @return int Remaining requests.
	 */
	public function get_remaining( $identifier, $limit, $scope = 'global' ) {
		if ( $limit <= 0 ) {
			return PHP_INT_MAX;
		}

		$transient_key = $this->get_transient_key( $identifier, $scope );
		$current_count = get_transient( $transient_key );

		if ( false === $current_count ) {
			return $limit;
		}

		return max( 0, $limit - $current_count );
	}

	/**
	 * Get transient key.
	 *
	 * @param string $identifier Identifier.
	 * @param string $scope Scope.
	 * @return string Transient key.
	 */
	private function get_transient_key( $identifier, $scope ) {
		return 'wp_rem_rl_' . md5( $scope . '_' . $identifier );
	}
}
