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
 * here, so they all produce the same markup, apart from the Popover IDs:
 * those are unique per request, so a field rendered twice on a page (say a
 * shortcode in the content and hotspot_field_render() in the template) is
 * still valid HTML and each button opens its own Popover, with or without
 * JavaScript. The block editor shows ACF block previews rendered by
 * separate requests side by side, so there the IDs also carry a random part.
 */
final class Renderer {

	/**
	 * Handle of the frontend stylesheet.
	 */
	public const STYLE_HANDLE = 'hotspot-field-for-acf';

	/**
	 * Handle of the frontend script, which positions the Popovers and
	 * dispatches the `hotspotfield:open` event.
	 */
	public const SCRIPT_HANDLE = 'hotspot-field-for-acf';

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

		wp_register_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'assets/js/hotspot-field.js', PLUGIN_FILE ),
			array(),
			VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
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

		$hotspots = Hotspot_Value::normalize( $hotspots );
		$id_base  = wp_unique_id( self::id_prefix() );
		$buttons  = '';

		foreach ( $hotspots as $index => $hotspot ) {
			$buttons .= self::hotspot( $hotspot, $index, $id_base . '-' . ( $index + 1 ) );
		}

		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );

		return '<div class="hotspot-field">' . $image_html . $buttons . '</div>';
	}

	/**
	 * One Hotspot: its button and, right after it, the Popover it opens.
	 *
	 * @param array{x: float, y: float, label: string, description: string} $hotspot    Normalized Hotspot.
	 * @param int                                                           $index      Position in the list, from 0.
	 * @param string                                                        $popover_id ID of the Popover.
	 */
	private static function hotspot( array $hotspot, int $index, string $popover_id ): string {
		$name = '' !== $hotspot['label']
			? $hotspot['label']
			/* translators: %d: position of the Hotspot in the list, starting at 1. */
			: sprintf( __( 'Hotspot %d', 'hotspot-field-for-acf' ), $index + 1 );

		$description = '' !== $hotspot['description']
			? sprintf( '<p class="hotspot-field__popover-description">%s</p>', nl2br( esc_html( $hotspot['description'] ), false ) )
			: '';

		return sprintf(
			'<button type="button" class="hotspot-field__hotspot" style="%1$s" popovertarget="%2$s" data-hotspot="%3$s"><span class="hotspot-field__label">%4$s</span></button>'
			. '<div class="hotspot-field__popover" id="%2$s" popover="auto"><p class="hotspot-field__popover-label">%4$s</p>%5$s</div>',
			esc_attr( sprintf( 'left: %s%%; top: %s%%;', self::number( $hotspot['x'] ), self::number( $hotspot['y'] ) ) ),
			esc_attr( $popover_id ),
			esc_attr( (string) wp_json_encode( $hotspot ) ),
			esc_html( $name ),
			$description
		);
	}

	/**
	 * Prefix of the Popover IDs. A request counter alone keeps them unique on
	 * a page built by one request. In the admin and in REST and Ajax
	 * requests, which is where the block editor gets ACF block previews from,
	 * a request may build just one block's preview, and the editor shows
	 * several side by side: there the prefix also carries a random part.
	 */
	private static function id_prefix(): string {
		static $random = null;

		if ( ! is_admin() && ! wp_doing_ajax() && ! wp_is_serving_rest_request() ) {
			return 'hotspot-field-';
		}

		$random ??= substr( md5( wp_generate_uuid4() ), 0, 8 );

		return "hotspot-field-{$random}-";
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
