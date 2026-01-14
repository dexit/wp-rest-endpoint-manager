<?php
/**
 * Data Mapper - Transform incoming webhook data
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Ingest;

/**
 * Maps incoming webhook data to WordPress data structures (ETL: Transform).
 */
class Data_Mapper {

	/**
	 * Map data according to rules.
	 *
	 * @param array $input_data Input data.
	 * @param array $mapping_rules Mapping rules.
	 * @return array Mapped data.
	 */
	public function map( $input_data, $mapping_rules ) {
		$output = array();

		foreach ( $mapping_rules as $output_key => $rule ) {
			$value = $this->extract_value( $input_data, $rule );
			
			if ( $value !== null ) {
				$output[ $output_key ] = $value;
			}
		}

		return $output;
	}

	/**
	 * Extract value from input data.
	 *
	 * @param array $data Input data.
	 * @param array $rule Extraction rule.
	 * @return mixed Extracted value.
	 */
	private function extract_value( $data, $rule ) {
		// Handle different rule types
		if ( is_string( $rule ) ) {
			// Simple field mapping
			return $this->get_nested_value( $data, $rule );
		}

		if ( ! is_array( $rule ) ) {
			return $rule;
		}

		// Complex rule with source, default, transform
		$value = null;

		if ( isset( $rule['source'] ) ) {
			$value = $this->get_nested_value( $data, $rule['source'] );
		} elseif ( isset( $rule['value'] ) ) {
			$value = $rule['value'];
		}

		// Apply default if value is null
		if ( $value === null && isset( $rule['default'] ) ) {
			$value = $rule['default'];
		}

		// Apply transformation
		if ( $value !== null && isset( $rule['transform'] ) ) {
			$value = $this->apply_transform( $value, $rule['transform'] );
		}

		// Apply type casting
		if ( $value !== null && isset( $rule['type'] ) ) {
			$value = $this->cast_type( $value, $rule['type'] );
		}

		return $value;
	}

	/**
	 * Get nested value using dot notation or JSONPath.
	 *
	 * @param array  $data Data array.
	 * @param string $path Path (dot notation or JSONPath).
	 * @return mixed Value or null.
	 */
	private function get_nested_value( $data, $path ) {
		// Support JSONPath-like syntax: $.user.name
		$path = ltrim( $path, '$.' );

		// Support bracket notation: user[0].name
		$path = preg_replace( '/\[(\d+)\]/', '.$1', $path );

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
	 * @param mixed  $value Value.
	 * @param string $transform Transformation name.
	 * @return mixed Transformed value.
	 */
	private function apply_transform( $value, $transform ) {
		switch ( $transform ) {
			case 'uppercase':
				return is_string( $value ) ? strtoupper( $value ) : $value;
			
			case 'lowercase':
				return is_string( $value ) ? strtolower( $value ) : $value;
			
			case 'trim':
				return is_string( $value ) ? trim( $value ) : $value;
			
			case 'sanitize_text':
				return sanitize_text_field( $value );
			
			case 'sanitize_email':
				return sanitize_email( $value );
			
			case 'sanitize_url':
				return esc_url_raw( $value );
			
			case 'strip_tags':
				return is_string( $value ) ? wp_strip_all_tags( $value ) : $value;
			
			case 'date_format':
				return is_numeric( $value ) ? date( 'Y-m-d H:i:s', $value ) : $value;
			
			case 'timestamp':
				return is_string( $value ) ? strtotime( $value ) : $value;
			
			case 'json_encode':
				return wp_json_encode( $value );
			
			case 'json_decode':
				return is_string( $value ) ? json_decode( $value, true ) : $value;
			
			case 'implode':
				return is_array( $value ) ? implode( ', ', $value ) : $value;
			
			case 'explode':
				return is_string( $value ) ? explode( ',', $value ) : $value;
			
			case 'count':
				return is_array( $value ) ? count( $value ) : ( $value ? 1 : 0 );
			
			default:
				// Custom transformation filter
				return apply_filters( 'wp_rem_data_mapper_transform_' . $transform, $value );
		}
	}

	/**
	 * Cast value to specific type.
	 *
	 * @param mixed  $value Value.
	 * @param string $type Type.
	 * @return mixed Casted value.
	 */
	private function cast_type( $value, $type ) {
		switch ( $type ) {
			case 'string':
				return (string) $value;
			
			case 'int':
			case 'integer':
				return (int) $value;
			
			case 'float':
			case 'double':
				return (float) $value;
			
			case 'bool':
			case 'boolean':
				return (bool) $value;
			
			case 'array':
				return (array) $value;
			
			default:
				return $value;
		}
	}
}
