<?php
/**
 * Tests for finding the Linked Image Field when rendering.
 *
 * @package HotspotFieldForAcf
 */

/**
 * The shortcode and hotspot_field_render() find the Linked Image Field at the
 * Hotspot Field's level first (the same field group, repeater row, flexible
 * content layout or group), then at enclosing levels, and accept its image
 * whatever the image field returns.
 */
class HotspotFieldLinkedImageTest extends Hotspot_Field_Test_Case {

	/**
	 * Hotspots used by the tests.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private const HOTSPOTS = array(
		array(
			'x'           => 25,
			'y'           => 50,
			'label'       => 'Lamp',
			'description' => '',
		),
	);

	/**
	 * Image of the top-level Linked Image Field.
	 *
	 * @var int
	 */
	private int $cover_id;

	/**
	 * Image of the first repeater row.
	 *
	 * @var int
	 */
	private int $kitchen_id;

	/**
	 * Image of the second repeater row.
	 *
	 * @var int
	 */
	private int $bedroom_id;

	/**
	 * Uploads three distinct images and registers the field group.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->cover_id   = $this->create_image( 'Cover' );
		$this->kitchen_id = $this->create_image( 'Kitchen' );
		$this->bedroom_id = $this->create_image( 'Bedroom' );

		$this->register_field_group( 'id' );
	}

	/**
	 * The top-level Linked Image Field is resolved whatever it returns: ID,
	 * array or URL.
	 *
	 * @dataProvider provide_return_formats
	 *
	 * @param string $return_format Return format of the image fields.
	 */
	public function test_resolves_a_top_level_linked_image_field_with_any_return_format( string $return_format ): void {
		$this->register_field_group( $return_format );
		$post_id = $this->create_post();

		$this->assertRendersImage( $this->cover_id, hotspot_field_render( 'house_hotspots', $post_id ) );
		$this->assertRendersImage( $this->cover_id, do_shortcode( sprintf( '[hotspot_field name="house_hotspots" post_id="%d"]', $post_id ) ) );
	}

	/**
	 * Inside a repeater row, each row's Hotspot Field uses that row's image,
	 * whatever the image field returns.
	 *
	 * @dataProvider provide_return_formats
	 *
	 * @param string $return_format Return format of the image fields.
	 */
	public function test_each_repeater_row_uses_its_own_image( string $return_format ): void {
		$this->register_field_group( $return_format );
		$post_id = $this->create_post();

		$this->assertSame(
			array(
				array( $this->kitchen_id, 1 ),
				array( $this->bedroom_id, 2 ),
			),
			$this->render_rows( 'rooms', $post_id, static fn () => hotspot_field_render( 'room_hotspots', $post_id ) )
		);
		$this->assertSame(
			array(
				array( $this->kitchen_id, 1 ),
				array( $this->bedroom_id, 2 ),
			),
			$this->render_rows( 'rooms', $post_id, static fn () => do_shortcode( '[hotspot_field name="room_hotspots"]' ) ),
			'The shortcode resolves the row in the same way.'
		);
	}

	/**
	 * A row whose Linked Image Field is empty renders nothing: it does not
	 * borrow an image from an enclosing level.
	 */
	public function test_a_row_with_an_empty_image_renders_nothing(): void {
		$post_id = $this->create_post();
		update_field(
			'field_linked_rooms',
			array(
				array(
					'field_linked_room_photo'    => '',
					'field_linked_room_hotspots' => self::HOTSPOTS,
				),
			),
			$post_id
		);

		$this->assertSame( array( '' ), $this->render_rows_raw( 'rooms', $post_id, static fn () => hotspot_field_render( 'room_hotspots' ) ) );
	}

	/**
	 * When the row has no field with the Linked Image Field's name, the image
	 * comes from the enclosing level: here the field group.
	 */
	public function test_a_row_without_the_image_field_falls_back_to_the_field_group(): void {
		$post_id = $this->create_post();

		$this->assertSame(
			array(
				array( $this->cover_id, 1 ),
				array( $this->cover_id, 1 ),
			),
			$this->render_rows( 'details', $post_id, static fn () => hotspot_field_render( 'detail_hotspots' ) )
		);
	}

	/**
	 * In a nested repeater, a row without the image field uses the enclosing
	 * row's image before the field group's.
	 */
	public function test_a_nested_row_falls_back_to_the_enclosing_row(): void {
		$post_id = $this->create_post();
		$images  = array();

		while ( have_rows( 'rooms', $post_id ) ) {
			the_row();

			while ( have_rows( 'room_details' ) ) {
				the_row();
				$images[] = $this->rendered_image_id( hotspot_field_render( 'room_detail_hotspots' ) );
			}
		}

		$this->assertSame( array( $this->kitchen_id, $this->bedroom_id ), $images );
	}

	/**
	 * In a flexible content layout, the layout's own image is used, and a
	 * layout without the image field falls back to the field group.
	 */
	public function test_flexible_content_layouts_resolve_like_rows(): void {
		$post_id = $this->create_post();

		$this->assertSame(
			array(
				array( $this->kitchen_id, 1 ),
				array( $this->cover_id, 1 ),
			),
			$this->render_rows( 'sections', $post_id, static fn () => hotspot_field_render( 'section_hotspots' ) )
		);
	}

	/**
	 * Inside a row, an explicit post ID of the row's post still resolves the
	 * row; another post's ID renders that post's top-level field.
	 */
	public function test_an_explicit_post_id_only_uses_rows_of_that_post(): void {
		$post_id = $this->create_post();
		$other   = self::factory()->post->create();
		update_field( 'field_linked_cover', $this->bedroom_id, $other );
		update_field( 'field_linked_house_hotspots', self::HOTSPOTS, $other );

		$this->assertSame(
			array(
				array( $this->kitchen_id, 1 ),
				array( $this->bedroom_id, 2 ),
			),
			$this->render_rows( 'rooms', $post_id, static fn () => hotspot_field_render( 'room_hotspots', $post_id ) )
		);
		$this->assertSame(
			array(
				array( $this->bedroom_id, 1 ),
				array( $this->bedroom_id, 1 ),
			),
			$this->render_rows( 'rooms', $post_id, static fn () => hotspot_field_render( 'house_hotspots', $other ) )
		);
	}

	/**
	 * A top-level Hotspot Field rendered inside a row still uses the
	 * top-level image, not the row's.
	 */
	public function test_a_top_level_hotspot_field_rendered_inside_a_row_uses_the_top_level_image(): void {
		$post_id = $this->create_post();
		$this->go_to_post( $post_id );

		$this->assertSame(
			array(
				array( $this->cover_id, 1 ),
				array( $this->cover_id, 1 ),
			),
			$this->render_rows( 'rooms', $post_id, static fn () => hotspot_field_render( 'house_hotspots' ) )
		);
	}

	/**
	 * Inside a loop over an options page's rows, a top-level Hotspot Field
	 * is still read from the current post, as get_field() would.
	 */
	public function test_a_top_level_hotspot_field_inside_an_options_loop_uses_the_current_post(): void {
		$post_id = $this->create_post();
		update_field(
			'field_linked_rooms',
			array( array( 'field_linked_room_photo' => $this->bedroom_id ) ),
			'option'
		);
		$this->go_to_post( $post_id );

		$html = array_merge(
			$this->render_foreign_rows( 'rooms', 'option', static fn () => hotspot_field_render( 'house_hotspots' ) ),
			$this->render_foreign_rows( 'rooms', 'option', static fn () => do_shortcode( '[hotspot_field name="house_hotspots"]' ) )
		);

		$this->assertCount( 2, $html );
		foreach ( $html as $markup ) {
			$this->assertRendersImage( $this->cover_id, $markup );
		}
	}

	/**
	 * A loop a theme left open on another post (say, to read its first row
	 * only) does not change where a top-level Hotspot Field is read from.
	 */
	public function test_a_top_level_hotspot_field_after_a_loop_left_open_on_another_post_uses_the_current_post(): void {
		$post_id = $this->create_post();
		$other   = $this->create_post();
		update_field( 'field_linked_cover', $this->bedroom_id, $other );
		$this->go_to_post( $post_id );
		$this->open_foreign_rows_loop( 'rooms', $other );

		$this->assertRendersImage( $this->cover_id, hotspot_field_render( 'house_hotspots' ) );
		$this->assertRendersImage( $this->cover_id, do_shortcode( '[hotspot_field name="house_hotspots"]' ) );
	}

	/**
	 * Inside a loop over a private post's rows, the shortcode in a public post
	 * renders the public post's top-level Hotspot Field, never the private
	 * post's.
	 */
	public function test_shortcode_inside_a_private_posts_loop_renders_the_current_posts_top_level_field(): void {
		$post_id = $this->create_post();
		$private = $this->create_post();
		wp_update_post(
			array(
				'ID'          => $private,
				'post_status' => 'private',
			)
		);
		update_field( 'field_linked_cover', $this->bedroom_id, $private );
		$this->go_to_post( $post_id );

		$html = $this->render_foreign_rows( 'rooms', $private, static fn () => do_shortcode( '[hotspot_field name="house_hotspots"]' ) );

		$this->assertCount( 2, $html );
		foreach ( $html as $markup ) {
			$this->assertRendersImage( $this->cover_id, $markup );
		}
	}

	/**
	 * The shortcode renders a row's Hotspot Field only when visitors may see
	 * the post the loop reads: not a private, draft or password-protected
	 * one; hotspot_field_render(), used by themes, still renders it.
	 *
	 * @dataProvider provide_hidden_posts
	 *
	 * @param array $post_data Post fields of the loop's post.
	 */
	public function test_shortcode_renders_no_row_of_a_post_visitors_cannot_see( array $post_data ): void {
		$post_id = $this->create_post();
		$hidden  = $this->create_post();
		wp_update_post( array( 'ID' => $hidden ) + $post_data );
		$this->go_to_post( $post_id );

		$this->assertSame( array( '', '' ), $this->render_foreign_rows( 'rooms', $hidden, static fn () => do_shortcode( '[hotspot_field name="room_hotspots"]' ) ) );
		$this->assertSame(
			array(
				array( $this->kitchen_id, 1 ),
				array( $this->bedroom_id, 2 ),
			),
			$this->summarize( $this->render_foreign_rows( 'rooms', $hidden, static fn () => hotspot_field_render( 'room_hotspots' ) ) )
		);
	}

	/**
	 * Posts that visitors cannot see.
	 *
	 * @return array<string, array{array}>
	 */
	public static function provide_hidden_posts(): array {
		return array(
			'private'            => array( array( 'post_status' => 'private' ) ),
			'draft'              => array( array( 'post_status' => 'draft' ) ),
			'password-protected' => array( array( 'post_password' => 'secret' ) ),
		);
	}

	/**
	 * The shortcode renders a row's Hotspot Field of another published post.
	 */
	public function test_shortcode_renders_a_row_of_another_published_post(): void {
		$post_id = $this->create_post();
		$other   = $this->create_post();
		$this->go_to_post( $post_id );

		$this->assertSame(
			array(
				array( $this->kitchen_id, 1 ),
				array( $this->bedroom_id, 2 ),
			),
			$this->summarize( $this->render_foreign_rows( 'rooms', $other, static fn () => do_shortcode( '[hotspot_field name="room_hotspots"]' ) ) )
		);
	}

	/**
	 * In a real preview the current post stays the previewed post while ACF
	 * reads its fields from the latest revision: the shortcode renders the
	 * revision's Hotspot Fields, top-level and in rows, with or without a
	 * post ID.
	 *
	 * @dataProvider provide_preview_statuses
	 *
	 * @param string $status Status of the previewed post.
	 */
	public function test_shortcode_renders_the_revision_in_a_preview( string $status ): void {
		$post_id = $this->create_post();
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $status,
			)
		);

		// The unsaved changes being previewed: another cover image.
		$this->go_to_preview(
			$post_id,
			function ( int $revision_id ): void {
				update_metadata( 'post', $revision_id, 'cover', (string) $this->bedroom_id );
			}
		);

		$this->assertRendersImage( $this->bedroom_id, do_shortcode( '[hotspot_field name="house_hotspots"]' ) );
		$this->assertRendersImage( $this->bedroom_id, do_shortcode( sprintf( '[hotspot_field name="house_hotspots" post_id="%d"]', $post_id ) ) );

		$this->assertSame(
			array(
				array( $this->kitchen_id, 1 ),
				array( $this->bedroom_id, 2 ),
			),
			$this->render_rows( 'rooms', false, static fn () => do_shortcode( '[hotspot_field name="room_hotspots"]' ) )
		);
		$this->assertSame(
			array(
				array( $this->kitchen_id, 1 ),
				array( $this->bedroom_id, 2 ),
			),
			$this->render_rows( 'rooms', $post_id, static fn () => do_shortcode( sprintf( '[hotspot_field name="room_hotspots" post_id="%d"]', $post_id ) ) )
		);
	}

	/**
	 * Statuses of posts an editor previews.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_preview_statuses(): array {
		return array(
			'published' => array( 'publish' ),
			'draft'     => array( 'draft' ),
		);
	}

	/**
	 * A sub field's name outside its row is not a top-level field: nothing is
	 * rendered.
	 */
	public function test_a_row_hotspot_field_outside_its_row_renders_nothing(): void {
		$post_id = $this->create_post();

		$this->assertSame( '', hotspot_field_render( 'room_hotspots', $post_id ) );
	}

	/**
	 * A Linked Image Field holding an image URL (for example imported content)
	 * is resolved to its attachment.
	 */
	public function test_resolves_a_stored_image_url(): void {
		$post_id = $this->create_post();
		update_post_meta( $post_id, 'cover', wp_get_attachment_url( $this->bedroom_id ) );

		$this->assertRendersImage( $this->bedroom_id, hotspot_field_render( 'house_hotspots', $post_id ) );
	}

	/**
	 * Return formats of ACF image fields.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_return_formats(): array {
		return array(
			'ID'    => array( 'id' ),
			'array' => array( 'array' ),
			'URL'   => array( 'url' ),
		);
	}

	/**
	 * Registers a field group with Hotspot Fields at several levels, all
	 * linked to image fields named `cover` or `photo`:
	 *
	 * - `cover` (image) and `house_hotspots` at the top level;
	 * - `rooms` (repeater): `photo`, `room_hotspots` (linked to `photo`), and
	 *   `room_details` (repeater) holding `room_detail_hotspots` linked to
	 *   `photo`, which only the enclosing row has;
	 * - `details` (repeater): `detail_hotspots` linked to `cover`, which only
	 *   the field group has;
	 * - `sections` (flexible content): layout `with_photo` has `cover` and
	 *   `section_hotspots`; layout `without_photo` has only `section_hotspots`.
	 *
	 * @param string $return_format Return format of every image field.
	 */
	private function register_field_group( string $return_format ): void {
		$image = static fn ( string $key, string $name ): array => array(
			'key'           => $key,
			'label'         => ucfirst( $name ),
			'name'          => $name,
			'type'          => 'image',
			'return_format' => $return_format,
		);

		$hotspots = static fn ( string $key, string $name, string $linked ): array => array(
			'key'                => $key,
			'label'              => 'Hotspots',
			'name'               => $name,
			'type'               => 'hotspot',
			'linked_image_field' => $linked,
		);

		acf_add_local_field_group(
			array(
				'key'      => 'group_linked_image_tests',
				'title'    => 'Linked Image Field tests',
				'fields'   => array(
					$image( 'field_linked_cover', 'cover' ),
					$hotspots( 'field_linked_house_hotspots', 'house_hotspots', 'cover' ),
					array(
						'key'        => 'field_linked_rooms',
						'label'      => 'Rooms',
						'name'       => 'rooms',
						'type'       => 'repeater',
						'sub_fields' => array(
							$image( 'field_linked_room_photo', 'photo' ),
							$hotspots( 'field_linked_room_hotspots', 'room_hotspots', 'photo' ),
							array(
								'key'        => 'field_linked_room_details',
								'label'      => 'Room details',
								'name'       => 'room_details',
								'type'       => 'repeater',
								'sub_fields' => array(
									$hotspots( 'field_linked_room_detail_hotspots', 'room_detail_hotspots', 'photo' ),
								),
							),
						),
					),
					array(
						'key'        => 'field_linked_details',
						'label'      => 'Details',
						'name'       => 'details',
						'type'       => 'repeater',
						'sub_fields' => array(
							$hotspots( 'field_linked_detail_hotspots', 'detail_hotspots', 'cover' ),
						),
					),
					array(
						'key'     => 'field_linked_sections',
						'label'   => 'Sections',
						'name'    => 'sections',
						'type'    => 'flexible_content',
						'layouts' => array(
							'layout_linked_with_photo'    => array(
								'key'        => 'layout_linked_with_photo',
								'name'       => 'with_photo',
								'label'      => 'With photo',
								'display'    => 'block',
								'sub_fields' => array(
									$image( 'field_linked_section_cover', 'cover' ),
									$hotspots( 'field_linked_section_hotspots', 'section_hotspots', 'cover' ),
								),
							),
							'layout_linked_without_photo' => array(
								'key'        => 'layout_linked_without_photo',
								'name'       => 'without_photo',
								'label'      => 'Without photo',
								'display'    => 'block',
								'sub_fields' => array(
									$hotspots( 'field_linked_section_hotspots_only', 'section_hotspots', 'cover' ),
								),
							),
						),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
			)
		);
	}

	/**
	 * Creates a post with values at every level: the cover image, two rooms
	 * with their own photo (one Hotspot in the first, two in the second), a
	 * nested detail in each room, two details and two sections.
	 *
	 * @return int Post ID.
	 */
	private function create_post(): int {
		$post_id = self::factory()->post->create();
		$two     = array( self::HOTSPOTS[0], self::HOTSPOTS[0] );

		update_field( 'field_linked_cover', $this->cover_id, $post_id );
		update_field( 'field_linked_house_hotspots', self::HOTSPOTS, $post_id );
		update_field(
			'field_linked_rooms',
			array(
				array(
					'field_linked_room_photo'    => $this->kitchen_id,
					'field_linked_room_hotspots' => self::HOTSPOTS,
					'field_linked_room_details'  => array( array( 'field_linked_room_detail_hotspots' => self::HOTSPOTS ) ),
				),
				array(
					'field_linked_room_photo'    => $this->bedroom_id,
					'field_linked_room_hotspots' => $two,
					'field_linked_room_details'  => array( array( 'field_linked_room_detail_hotspots' => self::HOTSPOTS ) ),
				),
			),
			$post_id
		);
		update_field(
			'field_linked_details',
			array(
				array( 'field_linked_detail_hotspots' => self::HOTSPOTS ),
				array( 'field_linked_detail_hotspots' => self::HOTSPOTS ),
			),
			$post_id
		);
		update_field(
			'field_linked_sections',
			array(
				array(
					'acf_fc_layout'                 => 'with_photo',
					'field_linked_section_cover'    => $this->kitchen_id,
					'field_linked_section_hotspots' => self::HOTSPOTS,
				),
				array(
					'acf_fc_layout'                      => 'without_photo',
					'field_linked_section_hotspots_only' => self::HOTSPOTS,
				),
			),
			$post_id
		);

		return $post_id;
	}

	/**
	 * Makes a post the current one, as on its page.
	 *
	 * @param int $post_id Post ID.
	 */
	private function go_to_post( int $post_id ): void {
		$this->go_to( get_permalink( $post_id ) );
		$this->assertTrue( have_posts() );
		the_post();
	}

	/**
	 * Renders something in each row of a top-level repeater or flexible
	 * content field.
	 *
	 * @param string           $selector Field name.
	 * @param int|string|false $post_id  ACF post ID; false for the current post.
	 * @param callable         $render   Returns the markup for the current row.
	 * @return list<string> Markup per row.
	 */
	private function render_rows_raw( string $selector, $post_id, callable $render ): array {
		$html = array();

		while ( have_rows( $selector, $post_id ) ) {
			the_row();
			$html[] = $render();
		}

		return $html;
	}

	/**
	 * Renders something in each row and reads back the image and the number
	 * of Hotspots of each render.
	 *
	 * @param string           $selector Field name.
	 * @param int|string|false $post_id  ACF post ID; false for the current post.
	 * @param callable         $render   Returns the markup for the current row.
	 * @return list<array{int, int}> Attachment ID and number of Hotspots per row.
	 */
	private function render_rows( string $selector, $post_id, callable $render ): array {
		return $this->summarize( $this->render_rows_raw( $selector, $post_id, $render ) );
	}

	/**
	 * Reads back the image and the number of Hotspots of each render.
	 *
	 * @param string[] $html Markup per row.
	 * @return list<array{int, int}> Attachment ID and number of Hotspots per row.
	 */
	private function summarize( array $html ): array {
		return array_map(
			fn ( string $markup ): array => array( $this->rendered_image_id( $markup ), substr_count( $markup, '<button' ) ),
			$html
		);
	}

	/**
	 * The test image that was rendered: 0 when none, -1 when another one.
	 *
	 * @param string $html Markup.
	 */
	private function rendered_image_id( string $html ): int {
		if ( ! preg_match( '/<img [^>]*\bsrc="([^"]+)"/', $html, $matches ) ) {
			return 0;
		}

		foreach ( array( $this->cover_id, $this->kitchen_id, $this->bedroom_id ) as $attachment_id ) {
			if ( wp_get_attachment_image_url( $attachment_id, 'large' ) === html_entity_decode( $matches[1] ) ) {
				return $attachment_id;
			}
		}

		return -1;
	}

	/**
	 * Asserts that markup renders an attachment with the Hotspots.
	 *
	 * @param int    $attachment_id Expected attachment ID.
	 * @param string $html          Markup.
	 */
	private function assertRendersImage( int $attachment_id, string $html ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit naming.
		$this->assertSame( $attachment_id, $this->rendered_image_id( $html ), 'Rendered image' );
		$this->assertSame( count( self::HOTSPOTS ), substr_count( $html, '<button' ), 'Rendered Hotspots' );
	}
}
