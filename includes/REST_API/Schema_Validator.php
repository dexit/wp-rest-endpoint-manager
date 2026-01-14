<?php
/**
 * Schema Validator
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\REST_API;

use WP_REST_Endpoint_Manager\Validator;

/**
 * Validates data against JSON Schema from Schema CPT.
 */
class Schema_Validator {

	/**
	 * Validator instance.
	 *
	 * @var Validator
	 */
	private $validator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->validator = new Validator();
	}

	/**
	 * Validate data against schema.
	 *
	 * @param mixed $data Data to validate.
	 * @param int   $schema_id Schema post ID.
	 * @return array Validation result with 'valid' and 'errors'.
	 */
	public function validate( $data, $schema_id ) {
		if ( ! $schema_id ) {
			return array( 'valid' => true, 'errors' => array() );
		}

		$schema_json = get_post_meta( $schema_id, '_rem_schema_json', true );
		if ( empty( $schema_json ) ) {
			return array( 'valid' => true, 'errors' => array() );
		}

		$schema = json_decode( $schema_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array(
				'valid' => false,
				'errors' => array( 'Invalid schema JSON: ' . json_last_error_msg() ),
			);
		}

		return $this->validator->validate( $data, $schema );
	}
}
