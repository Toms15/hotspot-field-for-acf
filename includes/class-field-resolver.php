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
	 * ACF blocks that are rendering, outermost first: each one's ACF ID and
	 * how many `have_rows()` loops were open as it started.
	 *
	 * @var list<array{id: string, loops: int}>
	 */
	private static array $blocks = array();

	/**
	 * Follows ACF blocks as they render, through a template or a callback, so
	 * a Hotspot Field inside a block tells loops opened by the block from
	 * loops open around it.
	 */
	public static function register(): void {
		add_action( 'acf/blocks/pre_block_template_render', array( self::class, 'start_block' ) );
		add_action( 'acf/blocks/post_block_template_render', array( self::class, 'end_block' ) );
		add_filter( 'acf/register_block_type_args', array( self::class, 'follow_php_block_callback' ) );
		// After ACF adds its namespace to the block's name (priority 10).
		add_filter( 'block_type_metadata', array( self::class, 'follow_json_block_callback' ), 20 );
	}

	/**
	 * Follows the render callback of a block registered with
	 * acf_register_block_type(), as ACF announces template renders only.
	 *
	 * @param mixed $block Block type settings.
	 * @return mixed The settings, with the callback followed.
	 */
	public static function follow_php_block_callback( $block ) {
		if ( is_array( $block ) && is_callable( $block['render_callback'] ?? null ) ) {
			$block['render_callback'] = self::followed_callback( $block['render_callback'] );
		}

		return $block;
	}

	/**
	 * Follows the render callback (`acf.renderCallback`) of an ACF block
	 * registered from block.json, as ACF announces template renders only.
	 *
	 * @param mixed $metadata Block metadata.
	 * @return mixed The metadata, with the callback followed.
	 */
	public static function follow_json_block_callback( $metadata ) {
		if ( is_array( $metadata ) && is_array( $metadata['acf'] ?? null ) && is_callable( $metadata['acf']['renderCallback'] ?? null ) ) {
			$metadata['acf']['renderCallback'] = self::followed_callback( $metadata['acf']['renderCallback'] );
		}

		return $metadata;
	}

	/**
	 * A block render callback that remembers, while it runs, how many
	 * `have_rows()` loops were open as the block started rendering, as
	 * start_block() and end_block() do for templates.
	 *
	 * @param callable $callback The block's render callback.
	 */
	private static function followed_callback( callable $callback ): \Closure {
		return static function ( $block, ...$args ) use ( $callback ) {
			self::start_block( $block );

			try {
				return $callback( $block, ...$args );
			} finally {
				self::end_block();
			}
		};
	}

	/**
	 * Finds a Hotspot Field and its Linked Image Field's image.
	 *
	 * A Hotspot Field of the current `have_rows()` row (or of a row enclosing
	 * it) is read from that loop's post, as get_sub_field() does; its Linked
	 * Image Field falls back to the top-level fields of that same post. A
	 * top-level Hotspot Field is read from the requested post, or the current
	 * one, as get_field() does, whatever loop is open.
	 *
	 * @param string   $name    The Hotspot Field's name.
	 * @param int|null $post_id Post ID; null for the current post (or, for a
	 *                          field of the current row, the loop's post).
	 * @return array{hotspots: array, image: mixed, post_id: int|string}|null
	 *         The Hotspots, the raw image value (attachment ID, image array
	 *         or URL; anything else when there is no image) and the ACF post
	 *         ID they were read from (a post ID, or for example 'option' or a
	 *         block's ID), or null when there is no such Hotspot Field.
	 */
	public static function resolve( string $name, ?int $post_id ): ?array {
		if ( '' === $name || ! function_exists( 'acf_maybe_get_field' ) ) {
			return null;
		}

		$levels = self::row_levels( $post_id );

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
				'image'    => self::image( $field['linked_image_field'], array_slice( $levels, $index ), $level['post_id'] ),
				'post_id'  => $level['post_id'],
			);
		}

		$post_id = acf_get_valid_post_id( $post_id ?? false );

		// Not strict: also finds the field on posts where it was never saved.
		$field = acf_maybe_get_field( $name, $post_id, false );

		if ( ! is_array( $field ) || ! self::is_hotspot_field( $field ) ) {
			return null;
		}

		$hotspots = get_field( $field['key'], $post_id );

		return array(
			'hotspots' => is_array( $hotspots ) ? $hotspots : array(),
			'image'    => self::image( $field['linked_image_field'], array(), $post_id ),
			'post_id'  => $post_id,
		);
	}

	/**
	 * Renders what resolve() found.
	 *
	 * @param array{hotspots: array, image: mixed} $resolved Result of resolve().
	 * @param string                               $size     Image size.
	 * @return string Markup, or an empty string when there is no image.
	 */
	public static function render( array $resolved, string $size ): string {
		$image = $resolved['image'];

		if ( ! is_int( $image ) && ! is_string( $image ) && ! is_array( $image ) ) {
			return '';
		}

		return Renderer::render( $resolved['hotspots'], $image, $size );
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
	 * @param int|string $post_id ACF post ID for the top level.
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
	 * the requested one are left out. Without a requested post, inside an ACF
	 * block, so are the loops that were already open when the block started
	 * rendering: the block's fields belong to the block, while loops its own
	 * template opens, on the block or anywhere else, count as usual.
	 *
	 * @param int|null $post_id Requested post ID; null for the current one.
	 * @return list<array{field: array, row: array, post_id: int|string, prefix: string}>
	 */
	private static function row_levels( ?int $post_id ): array {
		if ( ! function_exists( 'acf_get_loop' ) ) {
			return array();
		}

		$wanted = null === $post_id ? null : acf_get_valid_post_id( $post_id );
		$block  = null === $post_id ? self::current_block_id() : null;
		$count  = self::open_loop_count();
		$first  = null === $block ? 0 : self::loops_open_before( $block );
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

			// A loop open around the block, unless it is the block's own.
			if ( null !== $block && $count + $i < $first && $loop['post_id'] !== $block ) {
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
	 * Remembers how many `have_rows()` loops are open as an ACF block starts
	 * rendering. A stack, as blocks nest (InnerBlocks).
	 *
	 * @param array $block The block being rendered.
	 */
	public static function start_block( $block ): void {
		self::$blocks[] = array(
			'id'    => is_array( $block ) ? (string) ( $block['id'] ?? '' ) : '',
			'loops' => self::open_loop_count(),
		);
	}

	/**
	 * Forgets the block that has rendered.
	 */
	public static function end_block(): void {
		array_pop( self::$blocks );
	}

	/**
	 * How many `have_rows()` loops were open as a block started rendering.
	 * For a block whose start was not followed, every loop counts as opened
	 * before it: a render callback wrapped by other code after registration,
	 * or one that was not yet callable at registration (a function defined
	 * after `init`, which ACF only checks at render time). Wrapping a
	 * callback that is not callable would make ACF skip the block's template.
	 *
	 * @param string $block_id The block's ACF ID.
	 */
	private static function loops_open_before( string $block_id ): int {
		$started = end( self::$blocks );

		return false !== $started && $block_id === $started['id'] ? $started['loops'] : PHP_INT_MAX;
	}

	/**
	 * How many `have_rows()` loops are open.
	 */
	private static function open_loop_count(): int {
		$count = 0;

		while ( false !== acf_get_loop( $count ) ) {
			++$count;
		}

		return $count;
	}

	/**
	 * The ACF ID of the block being rendered, whose fields get_field() reads
	 * when given no post ID.
	 *
	 * @return string|null Block ID, or null outside ACF blocks.
	 */
	private static function current_block_id(): ?string {
		$current = acf_get_valid_post_id( false );

		return is_string( $current ) && str_starts_with( $current, 'block_' ) ? $current : null;
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
