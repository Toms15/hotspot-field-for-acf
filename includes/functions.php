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
 * Renders a Hotspot Field on the image of its Linked Image Field.
 *
 * Works for a field of a post's field groups and, inside a have_rows() loop,
 * for a field of the current row (repeater row, flexible content layout or
 * group). The Linked Image Field is looked up at the Hotspot Field's level
 * first, then at each enclosing level up to the post's field groups; its
 * image may be returned as an ID, an array or a URL. Returns the markup; echo
 * it.
 *
 * @param string   $name    The Hotspot Field's name.
 * @param int|null $post_id Post ID. Default: the current post, or the post of
 *                          the current have_rows() loop.
 * @param string   $size    Image size. Default 'large'.
 * @return string Markup, or an empty string when there is nothing to render.
 */
function hotspot_field_render( string $name, ?int $post_id = null, string $size = 'large' ): string {
	$resolved = HotspotFieldForAcf\Field_Resolver::resolve( $name, $post_id );

	if ( null === $resolved ) {
		return '';
	}

	$image = $resolved['image'];

	if ( ! is_int( $image ) && ! is_string( $image ) && ! is_array( $image ) ) {
		return '';
	}

	return hotspot_field_render_hotspots( $resolved['hotspots'], $image, $size );
}
