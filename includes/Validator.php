<?php
/**
 * Validator Class - JSON Schema Validation
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager;

/**
 * Data validation using JSON schema.
 */
class Validator {

	/**
	 * Validate data against a schema.
	 *
	 * @param mixed $data Data to validate.
	 * @param array $schema JSON schema.
	 * @return array Result with 'valid' boolean and 'errors' array.
	 */
	public function validate( $data, $schema ) {
		$errors = array();

		if ( empty( $schema ) || ! is_array( $schema ) ) {
			return array(
				'valid' => true,
				'errors' => array(),
			);
		}

		$this->validate_type( $data, $schema, $errors );
		$this->validate_properties( $data, $schema, $errors );
		$this->validate_required( $data, $schema, $errors );

		return array(
			'valid' => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Validate type.
	 *
	 * @param mixed $data Data.
	 * @param array $schema Schema.
	 * @param array &$errors Errors array.
	 */
	private function validate_type( $data, $schema, &$errors ) {
		if ( ! isset( $schema['type'] ) ) {
			return;
		}

		$type = $schema['type'];
		$actual_type = $this->get_type( $data );

		if ( $type !== $actual_type ) {
			$errors[] = sprintf( 'Expected type %s, got %s', $type, $actual_type );
		}
	}

	/**
	 * Validate properties.
	 *
	 * @param mixed $data Data.
	 * @param array $schema Schema.
	 * @param array &$errors Errors array.
	 */
	private function validate_properties( $data, $schema, &$errors ) {
		if ( ! isset( $schema['properties'] ) || ! is_array( $data ) ) {
			return;
		}

		foreach ( $schema['properties'] as $property => $property_schema ) {
			if ( isset( $data[ $property ] ) ) {
				$result = $this->validate( $data[ $property ], $property_schema );
				if ( ! $result['valid'] ) {
					foreach ( $result['errors'] as $error ) {
						$errors[] = sprintf( 'Property "%s": %s', $property, $error );
					}
				}

				// Check string length.
				if ( isset( $property_schema['minLength'] ) && is_string( $data[ $property ] ) ) {
					if ( strlen( $data[ $property ] ) < $property_schema['minLength'] ) {
						$errors[] = sprintf( 'Property "%s" must be at least %d characters', $property, $property_schema['minLength'] );
					}
				}

				if ( isset( $property_schema['maxLength'] ) && is_string( $data[ $property ] ) ) {
					if ( strlen( $data[ $property ] ) > $property_schema['maxLength'] ) {
						$errors[] = sprintf( 'Property "%s" must be at most %d characters', $property, $property_schema['maxLength'] );
					}
				}

				// Check number range.
				if ( isset( $property_schema['minimum'] ) && is_numeric( $data[ $property ] ) ) {
					if ( $data[ $property ] < $property_schema['minimum'] ) {
						$errors[] = sprintf( 'Property "%s" must be at least %s', $property, $property_schema['minimum'] );
					}
				}

				if ( isset( $property_schema['maximum'] ) && is_numeric( $data[ $property ] ) ) {
					if ( $data[ $property ] > $property_schema['maximum'] ) {
						$errors[] = sprintf( 'Property "%s" must be at most %s', $property, $property_schema['maximum'] );
					}
				}

				// Check format.
				if ( isset( $property_schema['format'] ) ) {
					if ( ! $this->validate_format( $data[ $property ], $property_schema['format'] ) ) {
						$errors[] = sprintf( 'Property "%s" must be a valid %s', $property, $property_schema['format'] );
					}
				}
			}
		}
	}

	/**
	 * Validate required fields.
	 *
	 * @param mixed $data Data.
	 * @param array $schema Schema.
	 * @param array &$errors Errors array.
	 */
	private function validate_required( $data, $schema, &$errors ) {
		if ( ! isset( $schema['required'] ) || ! is_array( $schema['required'] ) || ! is_array( $data ) ) {
			return;
		}

		foreach ( $schema['required'] as $required_field ) {
			if ( ! isset( $data[ $required_field ] ) ) {
				$errors[] = sprintf( 'Required property "%s" is missing', $required_field );
			}
		}
	}

	/**
	 * Validate format.
	 *
	 * @param mixed  $value Value.
	 * @param string $format Format.
	 * @return bool Valid or not.
	 */
	private function validate_format( $value, $format ) {
		switch ( $format ) {
			case 'email':
				return is_email( $value );
			case 'url':
				return filter_var( $value, FILTER_VALIDATE_URL );
			case 'date':
				return (bool) strtotime( $value );
			case 'date-time':
				return (bool) strtotime( $value );
			default:
				return true;
		}
	}

	/**
	 * Get type of value.
	 *
	 * @param mixed $value Value.
	 * @return string Type.
	 */
	private function get_type( $value ) {
		if ( is_null( $value ) ) {
			return 'null';
		}
		if ( is_bool( $value ) ) {
			return 'boolean';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return 'number';
		}
		if ( is_string( $value ) ) {
			return 'string';
		}
		if ( is_array( $value ) ) {
			// Check if associative (object) or indexed (array).
			if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
				return 'object';
			}
			return 'array';
		}
		return 'unknown';
	}
}
