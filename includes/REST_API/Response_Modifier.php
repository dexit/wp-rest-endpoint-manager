<?php
/**
 * Response Modifier
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\REST_API;

/**
 * Modifies REST API responses based on configuration.
 */
class Response_Modifier {

	/**
	 * Transform data according to transformation rules.
	 *
	 * @param mixed $data Data to transform.
	 * @param array $transform Transformation rules.
	 * @return mixed Transformed data.
	 */
	public function transform( $data, $transform ) {
		if ( empty( $transform ) ) {
			return $data;
		}

		$result = array();

		foreach ( $transform as $output_key => $rule ) {
			if ( isset( $rule['source'] ) ) {
				$result[ $output_key ] = $this->extract_value( $data, $rule['source'] );
			} elseif ( isset( $rule['value'] ) ) {
				$result[ $output_key ] = $rule['value'];
			}

			// Apply transformations.
			if ( isset( $rule['transform'] ) && isset( $result[ $output_key ] ) ) {
				$result[ $output_key ] = $this->apply_transform( $result[ $output_key ], $rule['transform'] );
			}
		}

		return $result;
	}

	/**
	 * Extract value from data using dot notation.
	 *
	 * @param mixed  $data Data array/object.
	 * @param string $path Dot notation path (e.g., "user.name").
	 * @return mixed Extracted value or null.
	 */
	private function extract_value( $data, $path ) {
		$keys = explode( '.', $path );
		$value = $data;

		foreach ( $keys as $key ) {
			if ( is_array( $value ) && isset( $value[ $key ] ) ) {
				$value = $value[ $key ];
			} elseif ( is_object( $value ) && isset( $value->$key ) ) {
				$value = $value->$key;
			} else {
				return null;
			}
		}

		return $value;
	}

	/**
	 * Apply transformation function.
	 *
	 * @param mixed  $value Value to transform.
	 * @param string $transform Transformation type.
	 * @return mixed Transformed value.
	 */
	private function apply_transform( $value, $transform ) {
		switch ( $transform ) {
			case 'uppercase':
				return strtoupper( $value );
			case 'lowercase':
				return strtolower( $value );
			case 'trim':
				return trim( $value );
			case 'int':
				return (int) $value;
			case 'float':
				return (float) $value;
			case 'bool':
				return (bool) $value;
			case 'string':
				return (string) $value;
			case 'json_encode':
				return wp_json_encode( $value );
			case 'json_decode':
				return json_decode( $value, true );
			default:
				return $value;
		}
	}

	/**
	 * Add metadata to response.
	 *
	 * @param array $data Response data.
	 * @param array $metadata Metadata to add.
	 * @return array Modified response.
	 */
	public function add_metadata( $data, $metadata ) {
		return array_merge( $data, $metadata );
	}

	/**
	 * Wrap response in envelope.
	 *
	 * @param mixed $data Data.
	 * @param array $envelope Envelope structure.
	 * @return array Wrapped response.
	 */
	public function wrap( $data, $envelope = array() ) {
		$default_envelope = array(
			'success' => true,
			'data' => $data,
			'timestamp' => current_time( 'timestamp' ),
		);

		return array_merge( $default_envelope, $envelope );
	}
}
