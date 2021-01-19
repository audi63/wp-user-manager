<?php
/**
 * Registers a dropdown field for the forms.
 *
 * @package     wp-user-manager
 * @copyright   Copyright (c) 2018, Alessandro Tesoro
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register a dropdown field type.
 */
class WPUM_Field_Taxonomy extends WPUM_Field_Type {

	public function __construct() {
		$this->name  = esc_html__( 'Taxonomy', 'wp-user-manager' );
		$this->type  = 'taxonomy';
		$this->icon  = 'dashicons-tag';
		$this->group = 'advanced';
		$this->allow_default = true;
	}

	public function get_data_keys() {
		$keys = parent::get_data_keys();

		return array_merge( $keys, array_keys( $this->get_editor_settings()['general'] ) );
	}

	/**
	 * @return array
	 */
	public function get_editor_settings() {
		return [
			'general' => [
				'taxonomy'   => array(
					'type'   => 'select',
					'label'  => esc_html__( 'Taxonomy', 'wp-user-manager' ),
					'model'  => 'taxonomy',
					'required'  => true,
					'values' => [],
				),
				'field_type' => array(
					'type'   => 'select',
					'label'  => esc_html__( 'Field Type', 'wp-user-manager' ),
					'model'  => 'field_type',
					'default' => 'select',
					'required'  => true,
					'values' => array(
						array(
							'id'   => '',
							'name' => 'Select Field Type',
						),
						array(
							'id'   => 'select',
							'name' => 'Dropdown',
						),
						array(
							'id'   => 'multiselect',
							'name' => 'Multiselect',
						),
						array(
							'id'   => 'multicheckbox',
							'name' => 'Checkboxes',
						),
					),
				),
			],
		];
	}

	/**
	 * Format the output onto the profiles for the taxonomy field.
	 *
	 * @param object $field
	 * @param mixed $value
	 * @return string
	 */
	function get_formatted_output( $field, $value ) {
		if ( ! $field->is_primary() ) {
			$options = $field->get_meta( 'dropdown_options' );
			if ( is_array( $options ) ) {
				foreach ( $options as $key => $option ) {
					if( $option['value'] == $value ) {
						$value = $option['label'];
					}
				}
			}
		}

		return $value;
	}

}
