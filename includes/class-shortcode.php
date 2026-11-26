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
 * hotspot_field_render() does, as long as visitors may see the post it is
 * read from.
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

		// A missing, zero or negative post ID means the current post.
		$post_id = is_numeric( $atts['post_id'] ) && (int) $atts['post_id'] > 0 ? (int) $atts['post_id'] : null;
		$size    = '' === trim( (string) $atts['size'] ) ? 'large' : trim( (string) $atts['size'] );

		if ( ! self::can_show( $post_id ) ) {
			return '';
		}

		$resolved = Field_Resolver::resolve( trim( (string) $atts['name'] ), $post_id );

		if ( null === $resolved || ! self::can_show_source( $resolved['post_id'], $post_id ) ) {
			return '';
		}

		return Field_Resolver::render( $resolved, $size );
	}

	/**
	 * Whether visitors may see a Hotspot Field read from another post than
	 * the one can_show() checked: a field of a have_rows() row is read from
	 * the loop's post, which a theme may have opened on any post.
	 *
	 * @param int|string $source_id ACF post ID the field was read from.
	 * @param int|null   $post_id   Requested post ID; null for the current post.
	 */
	private static function can_show_source( $source_id, ?int $post_id ): bool {
		if ( ! is_numeric( $source_id ) || (int) $source_id <= 0 ) {
			return true;
		}

		$checked_id = $post_id ?? (int) get_the_ID();

		return (int) $source_id === $checked_id || self::can_show( (int) $source_id );
	}

	/**
	 * Whether visitors may see the Hotspot Field of a post through the
	 * shortcode. Anyone who can write a shortcode could otherwise reveal
	 * Hotspots of drafts, private, trashed or password-protected posts.
	 *
	 * @param int|null $post_id Target post ID; null for the current post.
	 */
	private static function can_show( ?int $post_id ): bool {
		$current_id = (int) get_the_ID();
		$target_id  = $post_id ?? $current_id;

		if ( $target_id && post_password_required( $target_id ) ) {
			return false;
		}

		if ( null === $post_id || ! get_post( $post_id ) ) {
			return true;
		}

		// In previews the current post is the latest revision of the target.
		$is_current = $current_id && in_array( $post_id, array( $current_id, (int) wp_is_post_revision( $current_id ) ), true );

		return $is_current || is_post_publicly_viewable( $post_id );
	}
}
