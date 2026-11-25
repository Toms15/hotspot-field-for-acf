<?php
/**
 * Public API: template functions for rendering Hotspot Fields.
 *
 * @package HotspotFieldForAcf
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders Hotspots on an image: the image with one button per Hotspot.
 *
 * Use it where hotspot_field_render() cannot find the field on its own, such
 * as a repeater row or a block template. Returns the markup; echo it.
 *
 * @param array            $hotspots A Hotspot Field's value.
 * @param int|array|string $image    Attachment ID, ACF image array or image URL.
 * @param string           $size     Image size. Default 'large'.
 * @return string Markup, or an empty string when there is no image.
 */
function hotspot_field_render_hotspots( array $hotspots, int|array|string $image, string $size = 'large' ): string {
	return HotspotFieldForAcf\Renderer::render( $hotspots, $image, $size );
}

/**
 * Renders a Hotspot Field of a post, on the image of its Linked Image Field.
 *
 * Returns the markup; echo it.
 *
 * @param string   $name    The Hotspot Field's name.
 * @param int|null $post_id Post ID. Default: the current post.
 * @param string   $size    Image size. Default 'large'.
 * @return string Markup, or an empty string when there is nothing to render.
 */
function hotspot_field_render( string $name, ?int $post_id = null, string $size = 'large' ): string {
	if ( '' === $name || ! function_exists( 'acf_maybe_get_field' ) ) {
		return '';
	}

	$post_id = $post_id ?? false;

	// Not strict: also finds the field on posts where it was never saved.
	$field = acf_maybe_get_field( $name, $post_id, false );

	if ( ! is_array( $field ) || 'hotspot' !== ( $field['type'] ?? '' ) || empty( $field['linked_image_field'] ) ) {
		return '';
	}

	$hotspots = get_field( $field['key'], $post_id );
	$image    = get_field( $field['linked_image_field'], $post_id, false );

	if ( ! is_int( $image ) && ! is_string( $image ) && ! is_array( $image ) ) {
		return '';
	}

	return hotspot_field_render_hotspots( is_array( $hotspots ) ? $hotspots : array(), $image, $size );
}
