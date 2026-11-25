<?php
/**
 * Renderer: frontend markup for Hotspots on an image.
 *
 * @package HotspotFieldForAcf
 */

namespace HotspotFieldForAcf;

defined( 'ABSPATH' ) || exit;

/**
 * Produces the frontend markup of a Hotspot Field. Every render entry point
 * (shortcode, hotspot_field_render(), hotspot_field_render_hotspots()) ends up
 * here, so they all produce the same markup.
 */
final class Renderer {

	/**
	 * Handle of the frontend stylesheet.
	 */
	public const STYLE_HANDLE = 'hotspot-field-for-acf';

	/**
	 * Registers (does not enqueue) the frontend assets.
	 */
	public static function register_assets(): void {
		wp_register_style(
			self::STYLE_HANDLE,
			plugins_url( 'assets/css/hotspot-field.css', PLUGIN_FILE ),
			array(),
			VERSION
		);
	}

	/**
	 * Renders the image with one button per Hotspot, in list order.
	 *
	 * @param array            $hotspots Hotspots; normalized before use.
	 * @param int|array|string $image    Attachment ID, ACF image array or image URL.
	 * @param string           $size     Image size.
	 * @return string Markup, or an empty string when there is no image.
	 */
	public static function render( array $hotspots, int|array|string $image, string $size = 'large' ): string {
		$attachment_id = self::attachment_id( $image );

		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return '';
		}

		$image_html = wp_get_attachment_image( $attachment_id, $size );

		if ( '' === $image_html ) {
			return '';
		}

		$buttons = '';

		foreach ( Hotspot_Value::normalize( $hotspots ) as $hotspot ) {
			$buttons .= sprintf(
				'<button type="button" class="hotspot-field__hotspot" style="%s"><span class="hotspot-field__label">%s</span></button>',
				esc_attr( sprintf( 'left: %s%%; top: %s%%;', self::number( $hotspot['x'] ), self::number( $hotspot['y'] ) ) ),
				esc_html( $hotspot['label'] )
			);
		}

		wp_enqueue_style( self::STYLE_HANDLE );

		return '<div class="hotspot-field">' . $image_html . $buttons . '</div>';
	}

	/**
	 * The attachment ID behind an image given as ID, array or URL.
	 *
	 * @param int|array|string $image Attachment ID, ACF image array or image URL.
	 */
	private static function attachment_id( int|array|string $image ): int {
		if ( is_array( $image ) ) {
			$image = $image['ID'] ?? $image['id'] ?? 0;
		}

		if ( is_int( $image ) ) {
			return max( 0, $image );
		}

		if ( ! is_string( $image ) || '' === $image ) {
			return 0;
		}

		if ( ctype_digit( $image ) ) {
			return (int) $image;
		}

		return attachment_url_to_postid( $image );
	}

	/**
	 * A position formatted for CSS, independent of the locale.
	 *
	 * @param float $value Position in percent, with at most two decimals.
	 */
	private static function number( float $value ): string {
		return rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
	}
}
