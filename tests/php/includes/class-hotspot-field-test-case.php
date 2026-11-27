<?php
/**
 * Shared setup for tests that use a Hotspot Field in a field group.
 *
 * @package HotspotFieldForAcf
 */

/**
 * Registers a field group with an image field named `photo` and a Hotspot
 * Field named `hotspots` whose Linked Image Field is `photo`.
 *
 * Also builds render contexts (see CODING_STANDARDS.md) with distinct
 * objects on each side, undone in tear_down():
 * - go_to_preview(): a real preview of a post;
 * - open_foreign_rows_loop() and render_foreign_rows(): a have_rows() loop
 *   on another post or on `option` than the current post;
 * - block_markup(), create_post_with_blocks(), render_blocks(),
 *   render_block_preview() and in_block_editor(): the ACF test blocks of
 *   `tests/fixtures/hotspot-test-blocks` on a page, and in the block
 *   editor and its previews.
 */
abstract class Hotspot_Field_Test_Case extends WP_UnitTestCase {

	/**
	 * Current user before go_to_preview() logged an editor in; null when no
	 * preview was started.
	 *
	 * @var int|null
	 */
	private ?int $user_before_preview = null;

	/**
	 * Registers the field group used by the tests.
	 */
	public function set_up(): void {
		parent::set_up();

		acf_add_local_field_group(
			array(
				'key'      => 'group_hotspot_tests',
				'title'    => 'Hotspot tests',
				'fields'   => array(
					array(
						'key'           => 'field_hotspot_tests_photo',
						'label'         => 'Photo',
						'name'          => 'photo',
						'type'          => 'image',
						'return_format' => 'id',
					),
					array(
						'key'                => 'field_hotspot_tests_hotspots',
						'label'              => 'Hotspots',
						'name'               => 'hotspots',
						'type'               => 'hotspot',
						'linked_image_field' => 'photo',
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
	 * Saves Hotspots on a post through ACF, as the editor's save does.
	 *
	 * @param int   $post_id  Post ID.
	 * @param mixed $hotspots Value to save.
	 */
	protected function save_hotspots( int $post_id, $hotspots ): void {
		update_field( 'field_hotspot_tests_hotspots', $hotspots, $post_id );
	}

	/**
	 * Uploads the test image into the media library.
	 *
	 * @param string $alt Alternative text stored in the media library.
	 * @return int Attachment ID.
	 */
	protected function create_image( string $alt = '' ): int {
		$attachment_id = self::factory()->attachment->create_upload_object( dirname( __DIR__, 2 ) . '/fixtures/hotspot-photo.jpg' );

		if ( '' !== $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}

		return $attachment_id;
	}

	/**
	 * The ACF test blocks, one per ACF block version; each holds an image
	 * field `photo` and a Hotspot Field `hotspots` linked to it, and renders
	 * it with hotspot_field_render() unless a test picks another entry point
	 * (see render_blocks()).
	 *
	 * @return array<string, array{string}> Block name, by ACF block version.
	 */
	public static function block_names(): array {
		return array(
			'ACF block version 2' => array( 'acf/hotspot-test-v2' ),
			'ACF block version 3' => array( 'acf/hotspot-test-v3' ),
		);
	}

	/**
	 * A test block as the block editor stores it in post content: its field
	 * values keyed by field key, the Hotspots as the editor's JSON.
	 *
	 * @param string   $block_name Test block name.
	 * @param int|null $image_id   Image of the block's `photo` field; null for none.
	 * @param array    $hotspots   Hotspots of the block's `hotspots` field.
	 * @return string Serialized block.
	 */
	protected function block_markup( string $block_name, ?int $image_id, array $hotspots ): string {
		$key_prefix = 'field_' . str_replace( array( 'acf/', '-' ), array( '', '_' ), $block_name );

		return serialize_block(
			array(
				'blockName'    => $block_name,
				'attrs'        => array(
					'name' => $block_name,
					'data' => array(
						"{$key_prefix}_photo"    => null === $image_id ? '' : (string) $image_id,
						"{$key_prefix}_hotspots" => (string) wp_json_encode( $hotspots ),
					),
					'mode' => 'preview',
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Creates a post holding blocks, saved as the block editor saves it:
	 * WordPress expects the content slashed, and ACF stores the blocks'
	 * values while saving.
	 *
	 * @param string $content   Serialized blocks.
	 * @param array  $post_data Other post fields.
	 * @return int Post ID.
	 */
	protected function create_post_with_blocks( string $content, array $post_data = array() ): int {
		return self::factory()->post->create( array( 'post_content' => wp_slash( $content ) ) + $post_data );
	}

	/**
	 * Renders blocks as the frontend does, with the test blocks' Hotspot
	 * Field rendered through the given entry point.
	 *
	 * @param string        $content Serialized blocks.
	 * @param callable|null $render  Returns the markup of a test block's
	 *                               Hotspot Field, inside the block; null
	 *                               for hotspot_field_render( 'hotspots' ).
	 * @return string Markup.
	 */
	protected function render_blocks( string $content, ?callable $render = null ): string {
		$filter = static fn ( $markup ) => null === $render ? $markup : $render();

		add_filter( 'hotspot_field_tests_block_markup', $filter );

		try {
			return do_blocks( $content );
		} finally {
			remove_filter( 'hotspot_field_tests_block_markup', $filter );
		}
	}

	/**
	 * Renders a block as ACF renders the block editor's preview of it, which
	 * the editor fetches from the admin with a request per block.
	 *
	 * @param string $content Serialized block.
	 * @param int    $post_id Post being edited.
	 * @return string Markup.
	 */
	protected function render_block_preview( string $content, int $post_id ): string {
		$blocks = parse_blocks( $content );

		$this->assertCount( 1, $blocks, 'One block to preview.' );

		return $this->in_block_editor( static fn () => acf_rendered_block( $blocks[0]['attrs'], '', true, $post_id ) );
	}

	/**
	 * Runs code as in a request of the block editor: on the admin screen for
	 * editing a post.
	 *
	 * @param callable $callback Code to run.
	 * @return mixed What the code returned.
	 */
	protected function in_block_editor( callable $callback ) {
		$screen_before = $GLOBALS['current_screen'] ?? null;
		set_current_screen( 'post' );

		try {
			$this->assertTrue( is_admin(), 'The block editor is in the admin.' );

			return $callback();
		} finally {
			$GLOBALS['current_screen'] = $screen_before; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the screen set above.
		}
	}

	/**
	 * Undoes the render contexts a test built: closes every have_rows() loop
	 * left open, ends the preview and restores the current user.
	 */
	public function tear_down(): void {
		$this->end_render_contexts();

		parent::tear_down();
	}

	/**
	 * Undoes the render contexts built by the helpers. Runs in tear_down();
	 * tests of the helpers call it to check the cleanup.
	 */
	protected function end_render_contexts(): void {
		while ( acf_get_loop( 'active' ) ) {
			acf_remove_loop( 'active' );
		}

		if ( null !== $this->user_before_preview ) {
			unset( $_GET['preview'], $_GET['preview_id'], $_GET['p'] );
			wp_set_current_user( $this->user_before_preview );
			$this->user_before_preview = null;
		}
	}

	/**
	 * Opens a real preview of a post, as an editor previewing changes sees it:
	 * the editor is logged in, the preview query (`p`, `preview`,
	 * `preview_id`) is in `$_GET`, is_preview() is true and the global post
	 * is the previewed post, while ACF resolves the post's ID to its latest
	 * revision, an autosave holding a copy of the post's fields.
	 *
	 * @param int           $post_id        Previewed post ID.
	 * @param callable|null $edit_revision  Receives the revision ID before the
	 *                                      preview opens, to store the
	 *                                      unsaved changes being previewed.
	 * @return int Revision ID ACF reads the fields from.
	 */
	protected function go_to_preview( int $post_id, ?callable $edit_revision = null ): int {
		if ( null === $this->user_before_preview ) {
			$this->user_before_preview = get_current_user_id();
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$revision_id = _wp_put_post_revision( get_post( $post_id ), true );
		$this->assertIsInt( $revision_id, 'The preview revision was created.' );
		acf_copy_postmeta( $post_id, $revision_id );

		if ( null !== $edit_revision ) {
			$edit_revision( $revision_id );
		}

		$this->go_to(
			add_query_arg(
				array(
					'p'          => $post_id,
					'preview'    => 'true',
					'preview_id' => $post_id,
				),
				home_url( '/' )
			)
		);
		$this->assertTrue( is_preview(), 'The request is a preview.' );
		$this->assertTrue( have_posts(), 'The preview found the post.' );
		the_post();

		$this->assertSame( $post_id, get_the_ID(), 'The global post is the previewed post.' );
		$this->assertSame( $revision_id, acf_get_valid_post_id( $post_id ), 'ACF reads the preview from the revision.' );

		return $revision_id;
	}

	/**
	 * Opens a have_rows() loop on another object than the current post and
	 * moves to its first row, leaving the loop open as a theme reading only
	 * one row would.
	 *
	 * @param string     $selector Repeater or flexible content field name.
	 * @param int|string $source   ACF post ID of the loop: another post, or `option`.
	 */
	protected function open_foreign_rows_loop( string $selector, $source ): void {
		$this->assertForeignSource( $source );
		$this->assertTrue( have_rows( $selector, $source ), 'The foreign loop has rows.' );
		the_row();
	}

	/**
	 * Renders something in each row of a have_rows() loop on another object
	 * than the current post.
	 *
	 * @param string     $selector Repeater or flexible content field name.
	 * @param int|string $source   ACF post ID of the loop: another post, or `option`.
	 * @param callable   $render   Returns the markup for the current row.
	 * @return list<string> Markup per row.
	 */
	protected function render_foreign_rows( string $selector, $source, callable $render ): array {
		$this->assertForeignSource( $source );

		$html = array();
		while ( have_rows( $selector, $source ) ) {
			the_row();
			$html[] = $render();
		}

		$this->assertNotEmpty( $html, 'The foreign loop has rows.' );

		return $html;
	}

	/**
	 * Asserts that a loop's object differs from the current post.
	 *
	 * @param int|string $source ACF post ID of the loop.
	 */
	private function assertForeignSource( $source ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit naming.
		$current_id = (int) get_the_ID();

		$this->assertGreaterThan( 0, $current_id, 'A foreign loop needs a current post.' );
		$this->assertNotSame( (string) $current_id, (string) acf_get_valid_post_id( $source ), 'The loop reads another object than the current post.' );
	}
}
