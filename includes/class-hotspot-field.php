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
			'<div class="hotspot-field-editor" data-linked-image-field="%1$s"><input type="hidden" class="hotspot-field-editor__value" name="%2$s" value="%3$s" /><p class="hotspot-field-editor__instructions">%4$s</p><p class="hotspot-field-editor__message" role="status"></p><div class="hotspot-field-editor__canvas" hidden></div><ol class="hotspot-field-editor__list"></ol></div>',
			esc_attr( $field['linked_image_field'] ?? '' ),
			esc_attr( $field['name'] ),
			esc_attr( (string) wp_json_encode( Hotspot_Value::normalize( $field['value'] ?? array() ) ) ),
			esc_html__( 'Click the image to add a Hotspot. Drag a marker to move it, or select it and use the arrow keys (hold Shift for larger steps).', 'hotspot-field-for-acf' )
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
				'imageAlt'     => __( 'Image to place Hotspots on', 'hotspot-field-for-acf' ),
				/* translators: %d: position of the Hotspot in the list, starting at 1. */
				'hotspot'      => __( 'Hotspot %d', 'hotspot-field-for-acf' ),
				'label'        => __( 'Label', 'hotspot-field-for-acf' ),
				'description'  => __( 'Description', 'hotspot-field-for-acf' ),
				'missingLabel' => __( 'This Hotspot needs a Label.', 'hotspot-field-for-acf' ),
				'up'           => __( 'Up', 'hotspot-field-for-acf' ),
				'down'         => __( 'Down', 'hotspot-field-for-acf' ),
				'removeText'   => __( 'Remove', 'hotspot-field-for-acf' ),
				/* translators: %s: name of the Hotspot, such as "Hotspot 1: Lamp". */
				'moveUp'       => __( 'Move %s up', 'hotspot-field-for-acf' ),
				/* translators: %s: name of the Hotspot, such as "Hotspot 1: Lamp". */
				'moveDown'     => __( 'Move %s down', 'hotspot-field-for-acf' ),
				/* translators: %s: name of the Hotspot, such as "Hotspot 1: Lamp". */
				'remove'       => __( 'Remove %s', 'hotspot-field-for-acf' ),
				/* translators: 1: name of the Hotspot, such as "Hotspot 1: Lamp"; 2: distance from the image's left edge, such as "51%"; 3: distance from its top edge. */
				'position'     => __( '%1$s, %2$s across, %3$s down', 'hotspot-field-for-acf' ),
				/* translators: 1: name the Hotspot had before it moved, such as "Hotspot 3: Lamp"; 2: its new place in the list; 3: number of Hotspots. */
				'reordered'    => __( '%1$s moved to position %2$s of %3$s.', 'hotspot-field-for-acf' ),
				/* translators: %s: name of the removed Hotspot, such as "Hotspot 1: Lamp". */
				'removed'      => __( '%s removed.', 'hotspot-field-for-acf' ),
				/* translators: %s: label of the Linked Image Field, such as "Photo". */
				'noImage'      => __( 'No image chosen yet. Choose an image in the “%s” field to place Hotspots on it.', 'hotspot-field-for-acf' ),
				'notFound'     => __( 'Linked Image Field not found. Ask the site builder to check which image field this Hotspot Field is linked to.', 'hotspot-field-for-acf' ),
			)
		);
	}

	/**
	 * Blocks the save when the content is invalid: a Hotspot without a Label
	 * (the message names its row, numbered from 1 as in the editor), or a
	 * required Hotspot Field without Hotspots.
	 *
	 * Like every value ACF validates, it arrives slashed, as WordPress slashes
	 * form data; except the values of an ACF block, which ACF validates as
	 * stored in the block.
	 *
	 * @param bool|string $valid Whether the value is valid so far, or an error message.
	 * @param mixed       $value Submitted value: a list, or its JSON encoding.
	 * @param array       $field The field.
	 * @param string      $input Name of the field's input.
	 * @return bool|string True when valid, otherwise the error message.
	 */
	public function validate_value( $valid, $value, $field, $input ) {
		if ( is_string( $valid ) ) {
			return $valid;
		}

		$hotspots = Hotspot_Value::normalize( self::is_block( $input ) ? $value : self::unslash( $value ) );

		if ( ! $hotspots ) {
			if ( empty( $field['required'] ) ) {
				return $valid;
			}

			$field_label = trim( (string) ( $field['label'] ?? '' ) );

			if ( '' === $field_label ) {
				return __( 'Add at least one Hotspot.', 'hotspot-field-for-acf' );
			}

			return sprintf(
				/* translators: %s: label of the Hotspot Field. */
				__( 'Add at least one Hotspot to %s.', 'hotspot-field-for-acf' ),
				$field_label
			);
		}

		$without_label = array();
		foreach ( $hotspots as $index => $hotspot ) {
			if ( '' === $hotspot['label'] ) {
				$without_label[] = (string) ( $index + 1 );
			}
		}

		if ( ! $without_label ) {
			return $valid;
		}

		return sprintf(
			/* translators: %s: number of the Hotspot in the list, or a list of numbers, such as "1, 3, and 4". */
			_n( 'Hotspot %s needs a Label.', 'Hotspots %s need a Label.', count( $without_label ), 'hotspot-field-for-acf' ),
			wp_sprintf( '%l', $without_label )
		);
	}

	/**
	 * Normalizes the value before ACF stores it.
	 *
	 * Like every value ACF saves, it arrives slashed (as WordPress slashes form
	 * data) and update_metadata() unslashes it again, so it is unslashed before
	 * normalizing and the result is slashed for storage. A JSON string passed
	 * to update_field() may be slashed like form data
	 * (wp_slash( wp_json_encode( $hotspots ) )) or not; a list needs no
	 * slashing unless its text contains backslashes, as with ACF's own fields.
	 *
	 * The values of an ACF block are the exception: ACF hands them over as
	 * stored in the block and keeps what this returns as it is, so they are
	 * neither unslashed nor slashed. A block that stores its values in post
	 * meta (`usePostMeta`) hands over its JSON unslashed too, though saved
	 * under the post's ID: see unslash().
	 *
	 * @param mixed      $value   Submitted value: a list, or its JSON encoding.
	 * @param int|string $post_id Post ID.
	 * @param array      $field   The field.
	 * @return array The canonical list of Hotspots; slashed, except for a block.
	 */
	public function update_value( $value, $post_id, $field ): array {
		if ( self::is_block( $post_id ) ) {
			return Hotspot_Value::normalize( $value );
		}

		return wp_slash( Hotspot_Value::normalize( self::unslash( $value ) ) );
	}

	/**
	 * Unslashes a submitted value, unless it is JSON that arrived unslashed.
	 *
	 * Form data arrives slashed, but ACF saves the values of a block that
	 * stores them in post meta (`usePostMeta`) as read from the block: JSON
	 * that unslashing would break wherever a Label or Description holds a
	 * quote. Slashed JSON with any text in it is never valid JSON (`{\"` is
	 * a syntax error), so JSON that decodes as it is was not slashed.
	 *
	 * @param mixed $value Submitted value: a list, or its JSON encoding.
	 * @return mixed The value unslashed.
	 */
	private static function unslash( $value ) {
		if ( is_string( $value ) && is_array( json_decode( $value, true ) ) ) {
			return $value;
		}

		return wp_unslash( $value );
	}

	/**
	 * Whether ACF is saving or validating the values of an ACF block, which
	 * it names after the block: a post ID such as `block_…`, an input name
	 * such as `acf-block_…[field_…]`.
	 *
	 * @param mixed $id ACF post ID or input name.
	 */
	private static function is_block( $id ): bool {
		return is_string( $id ) && ( str_starts_with( $id, 'block_' ) || str_starts_with( $id, 'acf-block_' ) );
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
