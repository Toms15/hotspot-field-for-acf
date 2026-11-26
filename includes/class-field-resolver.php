<?php
/**
 * Field resolver: finds a Hotspot Field and the image of its Linked Image
 * Field.
 *
 * @package HotspotFieldForAcf
 */

namespace HotspotFieldForAcf;

defined( 'ABSPATH' ) || exit;

/**
 * Finds a Hotspot Field by name and the image of its Linked Image Field, the
 * way ACF's own template functions see fields: inside `have_rows()` loops
 * (repeater rows, flexible content layouts, groups) the current row is the
 * innermost level, each enclosing row the next one, and the post's field
 * groups the outermost.
 *
 * The Hotspot Field is looked up from the innermost level outwards; its
 * Linked Image Field from the Hotspot Field's level outwards. A level that
 * has a field with the Linked Image Field's name is used even when that field
 * is empty: the image is never borrowed from an enclosing level then.
 */
final class Field_Resolver {

	/**
	 * Finds a Hotspot Field and its Linked Image Field's image.
	 *
	 * @param string   $name    The Hotspot Field's name.
	 * @param int|null $post_id Post ID; null for the current post (or the post
	 *                          of the current `have_rows()` loop).
	 * @return array{hotspots: array, image: mixed}|null The Hotspots and the
	 *         raw image value (attachment ID, image array or URL; anything
	 *         else when there is no image), or null when there is no such
	 *         Hotspot Field.
	 */
	public static function resolve( string $name, ?int $post_id ): ?array {
		if ( '' === $name || ! function_exists( 'acf_maybe_get_field' ) ) {
			return null;
		}

		$levels  = self::row_levels( $post_id );
		$post_id = $levels ? end( $levels )['post_id'] : ( $post_id ?? false );

		foreach ( $levels as $index => $level ) {
			$field = self::sub_field( $level, $name );

			if ( ! $field || ! self::is_hotspot_field( $field ) ) {
				continue;
			}

			// Named after its row, as ACF names sub fields, so ACF's cache of
			// formatted values tells the rows apart.
			$field['name'] = $level['prefix'] . $field['name'];
			$hotspots      = acf_format_value( $level['row'][ $field['key'] ] ?? null, $level['post_id'], $field );

			return array(
				'hotspots' => is_array( $hotspots ) ? $hotspots : array(),
				'image'    => self::image( $field['linked_image_field'], array_slice( $levels, $index ), $post_id ),
			);
		}

		// Not strict: also finds the field on posts where it was never saved.
		$field = acf_maybe_get_field( $name, $post_id, false );

		if ( ! is_array( $field ) || ! self::is_hotspot_field( $field ) ) {
			return null;
		}

		$hotspots = get_field( $field['key'], $post_id );

		return array(
			'hotspots' => is_array( $hotspots ) ? $hotspots : array(),
			'image'    => self::image( $field['linked_image_field'], array(), $post_id ),
		);
	}

	/**
	 * Whether a field is a Hotspot Field with a Linked Image Field.
	 *
	 * @param array $field Field.
	 */
	private static function is_hotspot_field( array $field ): bool {
		return 'hotspot' === ( $field['type'] ?? '' ) && is_string( $field['linked_image_field'] ?? null ) && '' !== $field['linked_image_field'];
	}

	/**
	 * The raw value of the Linked Image Field, from the first level that has
	 * a field with its name; the post's top-level field otherwise.
	 *
	 * @param string     $name    The Linked Image Field's name.
	 * @param array      $levels  Row levels, innermost first.
	 * @param int|string $post_id Post ID for the top level.
	 * @return mixed Raw value.
	 */
	private static function image( string $name, array $levels, $post_id ) {
		foreach ( $levels as $level ) {
			$field = self::sub_field( $level, $name );

			if ( $field ) {
				return $level['row'][ $field['key'] ] ?? null;
			}
		}

		return get_field( $name, $post_id, false );
	}

	/**
	 * The rows of the `have_rows()` loops that are open, innermost first: the
	 * current row, then each row that encloses it. Loops of another post than
	 * the requested one are left out.
	 *
	 * @param int|null $post_id Requested post ID; null for any.
	 * @return list<array{field: array, row: array, post_id: int|string, prefix: string}>
	 */
	private static function row_levels( ?int $post_id ): array {
		if ( ! function_exists( 'acf_get_loop' ) ) {
			return array();
		}

		$wanted = null === $post_id ? null : acf_get_valid_post_id( $post_id );
		$levels = array();
		$inner  = null;

		// ACF counts negative indexes from the innermost loop; past the
		// outermost one it returns false.
		for ( $i = -1, $loop = acf_get_loop( $i ); false !== $loop; $loop = acf_get_loop( --$i ) ) {
			if ( ! is_array( $loop ) || ( null !== $inner && ! self::encloses( $loop, $inner ) ) ) {
				break;
			}

			$row = is_array( $loop['value'] ?? null ) ? ( $loop['value'][ $loop['i'] ] ?? null ) : null;

			// phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Post IDs may be integers or numeric strings.
			if ( ! is_array( $row ) || ! is_array( $loop['field'] ?? null ) || ( null !== $wanted && $loop['post_id'] != $wanted ) ) {
				break;
			}

			$levels[] = array(
				'field'   => $loop['field'],
				'row'     => $row,
				'post_id' => $loop['post_id'],
				'prefix'  => sprintf( '%s_%d_', $loop['name'], $loop['i'] ),
			);
			$inner    = $loop;
		}

		return $levels;
	}

	/**
	 * Whether a loop is the one a nested loop was opened in: ACF names a
	 * nested loop after its enclosing loop, row and field.
	 *
	 * @param array $outer Enclosing loop candidate.
	 * @param array $inner Nested loop.
	 */
	private static function encloses( array $outer, array $inner ): bool {
		// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Post IDs may be integers or numeric strings.
		return $outer['post_id'] == $inner['post_id']
			&& str_starts_with( (string) $inner['name'], sprintf( '%s_%d_', $outer['name'], $outer['i'] ) );
	}

	/**
	 * The field of a row with a given name: for flexible content, among the
	 * sub fields of the row's layout.
	 *
	 * @param array{field: array, row: array} $level Row level.
	 * @param string                          $name  Field name.
	 * @return array|false Field, or false when the row has none with that name.
	 */
	private static function sub_field( array $level, string $name ) {
		$field = $level['field'];

		if ( 'flexible_content' !== ( $field['type'] ?? '' ) ) {
			return acf_get_sub_field( $name, $field );
		}

		foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
			if ( ( $layout['name'] ?? null ) === ( $level['row']['acf_fc_layout'] ?? '' ) ) {
				return acf_search_fields( $name, (array) ( $layout['sub_fields'] ?? array() ) );
			}
		}

		return false;
	}
}
