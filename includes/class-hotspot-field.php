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
	 * Handle of the editor script and stylesheet.
	 */
	private const EDITOR_HANDLE = 'hotspot-field-for-acf-editor';

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
	 * Renders the editor container. The editor script builds the image, the
	 * markers and the list of Hotspots inside it and keeps the hidden input,
	 * which holds the list as JSON, up to date.
	 *
	 * @param array $field The field being rendered.
	 */
	public function render_field( $field ): void {
		printf(
			'<div class="hotspot-field-editor" data-linked-image-field="%1$s"><input type="hidden" class="hotspot-field-editor__value" name="%2$s" value="%3$s" /><p class="hotspot-field-editor__instructions">%4$s</p><div class="hotspot-field-editor__canvas" hidden></div><ol class="hotspot-field-editor__list"></ol></div>',
			esc_attr( $field['linked_image_field'] ?? '' ),
			esc_attr( $field['name'] ),
			esc_attr( (string) wp_json_encode( Hotspot_Value::normalize( $field['value'] ?? array() ) ) ),
			esc_html__( 'Click the image to add a Hotspot.', 'hotspot-field-for-acf' )
		);
	}

	/**
	 * Enqueues the editor script and stylesheet on screens that show the field.
	 */
	public function input_admin_enqueue_scripts(): void {
		wp_enqueue_style(
			self::EDITOR_HANDLE,
			plugins_url( 'assets/css/editor.css', PLUGIN_FILE ),
			array( 'acf-input' ),
			VERSION
		);

		wp_enqueue_script(
			self::EDITOR_HANDLE,
			plugins_url( 'assets/js/editor.js', PLUGIN_FILE ),
			array( 'acf-input' ),
			VERSION,
			true
		);

		wp_localize_script(
			self::EDITOR_HANDLE,
			'hotspotFieldEditor',
			array(
				'imageAlt'    => __( 'Image to place Hotspots on', 'hotspot-field-for-acf' ),
				/* translators: %d: position of the Hotspot in the list, starting at 1. */
				'hotspot'     => __( 'Hotspot %d', 'hotspot-field-for-acf' ),
				'label'       => __( 'Label', 'hotspot-field-for-acf' ),
				'description' => __( 'Description', 'hotspot-field-for-acf' ),
			)
		);
	}

	/**
	 * Normalizes the value before ACF stores it.
	 *
	 * Like every value ACF saves, it arrives slashed (as WordPress slashes form
	 * data) and update_metadata() unslashes it again, so it is unslashed before
	 * normalizing and the result is slashed for storage. A JSON string passed
	 * to update_field() must therefore be slashed like form data
	 * (wp_slash( wp_json_encode( $hotspots ) )); a list needs no slashing
	 * unless its text contains backslashes, as with ACF's own fields.
	 *
	 * @param mixed      $value   Submitted value: a list, or its JSON encoding.
	 * @param int|string $post_id Post ID.
	 * @param array      $field   The field.
	 * @return array The canonical list of Hotspots, slashed.
	 */
	public function update_value( $value, $post_id, $field ): array {
		return wp_slash( Hotspot_Value::normalize( wp_unslash( $value ) ) );
	}

	/**
	 * Returns the stored value as the canonical list of Hotspots.
	 *
	 * @param mixed      $value   Stored value.
	 * @param int|string $post_id Post ID.
	 * @param array      $field   The field.
	 * @return array The canonical list of Hotspots; empty when there are none.
	 */
	public function format_value( $value, $post_id, $field ): array {
		return Hotspot_Value::normalize( $value );
	}
}
