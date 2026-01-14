<?php
/**
 * Template Engine - Renders webhook payloads from templates
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Dispatch;

/**
 * Template engine for webhook payloads with placeholder support.
 */
class Template_Engine {

	/**
	 * Render template with context data.
	 *
	 * @param array $template Template structure.
	 * @param array $context Context data.
	 * @return array Rendered payload.
	 */
	public function render( $template, $context ) {
		if ( ! is_array( $template ) ) {
			return $this->render_value( $template, $context );
		}

		$result = array();

		foreach ( $template as $key => $value ) {
			$rendered_key = $this->render_value( $key, $context );
			$result[ $rendered_key ] = $this->render_value( $value, $context );
		}

		return $result;
	}

	/**
	 * Render a single value.
	 *
	 * @param mixed $value Value to render.
	 * @param array $context Context data.
	 * @return mixed Rendered value.
	 */
	private function render_value( $value, $context ) {
		if ( is_array( $value ) ) {
			return $this->render( $value, $context );
		}

		if ( ! is_string( $value ) ) {
			return $value;
		}

		// Replace placeholders: {{key.subkey}}
		return preg_replace_callback( '/\{\{([^}]+)\}\}/', function( $matches ) use ( $context ) {
			$placeholder = trim( $matches[1] );
			return $this->get_context_value( $context, $placeholder );
		}, $value );
	}

	/**
	 * Get value from context using dot notation.
	 *
	 * @param array  $context Context data.
	 * @param string $path Dot notation path.
	 * @return mixed Value or placeholder if not found.
	 */
	private function get_context_value( $context, $path ) {
		// Support WordPress-specific placeholders
		if ( $this->is_wordpress_placeholder( $path ) ) {
			return $this->get_wordpress_value( $path );
		}

		// Extract from event_data if exists
		if ( isset( $context['event_data'] ) && is_array( $context['event_data'] ) ) {
			$value = $this->extract_nested_value( $context['event_data'], $path );
			if ( $value !== null ) {
				return $value;
			}
		}

		// Extract from context directly
		$value = $this->extract_nested_value( $context, $path );
		if ( $value !== null ) {
			return $value;
		}

		// Return placeholder if not found
		return '{{' . $path . '}}';
	}

	/**
	 * Check if placeholder is WordPress-specific.
	 *
	 * @param string $path Path.
	 * @return bool True if WordPress placeholder.
	 */
	private function is_wordpress_placeholder( $path ) {
		$prefixes = array( 'post.', 'user.', 'site.', 'option.' );
		foreach ( $prefixes as $prefix ) {
			if ( strpos( $path, $prefix ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get WordPress-specific value.
	 *
	 * @param string $path Path (e.g., "post.title", "user.email").
	 * @return mixed Value.
	 */
	private function get_wordpress_value( $path ) {
		$parts = explode( '.', $path, 2 );
		$type = $parts[0];
		$field = $parts[1] ?? '';

		switch ( $type ) {
			case 'post':
				return $this->get_post_value( $field );
			
			case 'user':
				return $this->get_user_value( $field );
			
			case 'site':
				return $this->get_site_value( $field );
			
			case 'option':
				return get_option( $field );
			
			default:
				return null;
		}
	}

	/**
	 * Get post value.
	 *
	 * @param string $field Field name.
	 * @return mixed Value.
	 */
	private function get_post_value( $field ) {
		global $post;
		
		if ( ! $post ) {
			// Get from first event_data argument if it's a post ID
			return null;
		}

		switch ( $field ) {
			case 'id':
			case 'ID':
				return $post->ID;
			case 'title':
				return $post->post_title;
			case 'content':
				return $post->post_content;
			case 'excerpt':
				return $post->post_excerpt;
			case 'author':
				return $post->post_author;
			case 'date':
				return $post->post_date;
			case 'status':
				return $post->post_status;
			case 'type':
				return $post->post_type;
			case 'url':
			case 'permalink':
				return get_permalink( $post->ID );
			default:
				// Try to get meta
				return get_post_meta( $post->ID, $field, true );
		}
	}

	/**
	 * Get user value.
	 *
	 * @param string $field Field name.
	 * @return mixed Value.
	 */
	private function get_user_value( $field ) {
		$user = wp_get_current_user();
		
		if ( ! $user || ! $user->ID ) {
			return null;
		}

		switch ( $field ) {
			case 'id':
			case 'ID':
				return $user->ID;
			case 'login':
			case 'username':
				return $user->user_login;
			case 'email':
				return $user->user_email;
			case 'display_name':
			case 'name':
				return $user->display_name;
			case 'first_name':
				return $user->first_name;
			case 'last_name':
				return $user->last_name;
			default:
				return get_user_meta( $user->ID, $field, true );
		}
	}

	/**
	 * Get site value.
	 *
	 * @param string $field Field name.
	 * @return mixed Value.
	 */
	private function get_site_value( $field ) {
		switch ( $field ) {
			case 'url':
			case 'home_url':
				return home_url();
			case 'site_url':
				return site_url();
			case 'name':
			case 'title':
				return get_bloginfo( 'name' );
			case 'description':
				return get_bloginfo( 'description' );
			case 'admin_email':
				return get_bloginfo( 'admin_email' );
			default:
				return get_bloginfo( $field );
		}
	}

	/**
	 * Extract nested value using dot notation.
	 *
	 * @param array  $data Data.
	 * @param string $path Path.
	 * @return mixed Value or null.
	 */
	private function extract_nested_value( $data, $path ) {
		$keys = explode( '.', $path );
		$value = $data;

		foreach ( $keys as $key ) {
			// Handle array index: items[0]
			if ( preg_match( '/(.+)\[(\d+)\]/', $key, $matches ) ) {
				$key = $matches[1];
				$index = (int) $matches[2];
				
				if ( is_array( $value ) && isset( $value[ $key ] ) && isset( $value[ $key ][ $index ] ) ) {
					$value = $value[ $key ][ $index ];
				} else {
					return null;
				}
			} elseif ( is_array( $value ) && isset( $value[ $key ] ) ) {
				$value = $value[ $key ];
			} elseif ( is_object( $value ) && isset( $value->$key ) ) {
				$value = $value->$key;
			} else {
				return null;
			}
		}

		return $value;
	}
}
