<?php
/**
 * The [hotspot_field] shortcode.
 *
 * @package HotspotFieldForAcf
 */

namespace HotspotFieldForAcf;

defined( 'ABSPATH' ) || exit;

/**
 * `[hotspot_field name="…" post_id="…" size="…"]` renders a Hotspot Field like
 * hotspot_field_render() does.
 */
final class Shortcode {

	/**
	 * The shortcode tag.
	 */
	public const TAG = 'hotspot_field';

	/**
	 * Registers the shortcode.
	 */
	public static function register(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
	}

	/**
	 * Renders the shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Markup.
	 */
	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'name'    => '',
				'post_id' => '',
				'size'    => 'large',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		$post_id = is_numeric( $atts['post_id'] ) ? (int) $atts['post_id'] : null;
		$size    = '' === trim( (string) $atts['size'] ) ? 'large' : trim( (string) $atts['size'] );

		return hotspot_field_render( trim( (string) $atts['name'] ), $post_id, $size );
	}
}
