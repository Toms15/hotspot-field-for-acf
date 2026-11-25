<?php
/**
 * The Hotspot Field ACF field type.
 *
 * @package HotspotFieldForAcf
 */

namespace HotspotFieldForAcf;

defined( 'ABSPATH' ) || exit;

/**
 * An ACF field type holding an ordered list of Hotspots placed on the image of
 * a Linked Image Field.
 */
class Hotspot_Field extends \acf_field {

	/**
	 * Sets the field type's identity and default settings.
	 */
	public function initialize(): void {
		$this->name     = 'hotspot';
		$this->label    = __( 'Hotspot', 'hotspot-field-for-acf' );
		$this->category = 'content';
		$this->defaults = array(
			'linked_image_field' => '',
		);
	}

	/**
	 * Renders the Hotspot Field's settings in the field group editor.
	 *
	 * @param array $field The field being edited.
	 */
	public function render_field_settings( $field ): void {
		acf_render_field_setting(
			$field,
			array(
				'label'        => __( 'Linked Image Field', 'hotspot-field-for-acf' ),
				'instructions' => __( 'The field name (not the label) of the image field whose image the Hotspots are placed on.', 'hotspot-field-for-acf' ),
				'type'         => 'text',
				'name'         => 'linked_image_field',
			)
		);
	}

	/**
	 * Renders the editor container for the field.
	 *
	 * @param array $field The field being rendered.
	 */
	public function render_field( $field ): void {
		printf(
			'<div class="hotspot-field" data-linked-image-field="%s"></div>',
			esc_attr( $field['linked_image_field'] ?? '' )
		);
	}
}
