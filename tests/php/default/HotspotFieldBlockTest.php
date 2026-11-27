<?php
/**
 * Tests for Hotspot Fields inside ACF blocks.
 *
 * @package HotspotFieldForAcf
 */

/**
 * A Hotspot Field in an ACF block renders the block's own Hotspots on the
 * block's own image, through every entry point, on the frontend, in previews
 * and in the block editor's previews. Each test runs with both ACF block
 * versions (see tests/fixtures/hotspot-test-blocks).
 *
 * The post holding the block also has a top-level image field `photo` and a
 * Hotspot Field `hotspots` (see Hotspot_Field_Test_Case), with another image
 * and other Hotspots, so a test tells which object a render read.
 */
class HotspotFieldBlockTest extends Hotspot_Field_Test_Case {

	/**
	 * Hotspots stored in the block.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private const BLOCK_HOTSPOTS = array(
		array(
			'x'           => 25,
			'y'           => 50,
			'label'       => 'Block lamp',
			'description' => 'In the block.',
		),
		array(
			'x'           => 75.5,
			'y'           => 10,
			'label'       => 'Block chair',
			'description' => '',
		),
	);

	/**
	 * Hotspots stored on the post, outside the block.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private const POST_HOTSPOTS = array(
		array(
			'x'           => 5,
			'y'           => 5,
			'label'       => 'Post sofa',
			'description' => '',
		),
	);

	/**
	 * Hotspots with quotes and backslashes in their Labels and Descriptions.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private const QUOTED_HOTSPOTS = array(
		array(
			'x'           => 10,
			'y'           => 20,
			'label'       => 'Say "hi"',
			'description' => 'Saved in C:\\Rooms\\Lamp',
		),
		array(
			'x'           => 30,
			'y'           => 40,
			// Not at the end: ACF's block serializer breaks the block's
			// JSON when a value ends with a backslash, whatever the field.
			'label'       => 'Back\\slash',
			'description' => '',
		),
	);

	/**
	 * Image of the block's `photo` field.
	 *
	 * @var int
	 */
	private int $block_image;

	/**
	 * Image of the post's top-level `photo` field.
	 *
	 * @var int
	 */
	private int $post_image;

	/**
	 * Uploads the two images and registers a repeater for loops on other
	 * objects: `gallery` rows hold `photo` and `hotspots`, like the block.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->block_image = $this->create_image( 'Block image' );
		$this->post_image  = $this->create_image( 'Post image' );

		acf_add_local_field_group(
			array(
				'key'      => 'group_hotspot_block_tests_gallery',
				'title'    => 'Hotspot block tests gallery',
				'fields'   => array(
					array(
						'key'        => 'field_hotspot_block_tests_gallery',
						'label'      => 'Gallery',
						'name'       => 'gallery',
						'type'       => 'repeater',
						'sub_fields' => array(
							array(
								'key'           => 'field_hotspot_block_tests_gallery_photo',
								'label'         => 'Photo',
								'name'          => 'photo',
								'type'          => 'image',
								'return_format' => 'id',
							),
							array(
								'key'                => 'field_hotspot_block_tests_gallery_hotspots',
								'label'              => 'Hotspots',
								'name'               => 'hotspots',
								'type'               => 'hotspot',
								'linked_image_field' => 'photo',
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
	 * The block's Hotspot Field uses the block's Linked Image Field, not the
	 * post's field of the same name, and the block's Hotspots.
	 *
	 * @dataProvider block_names
	 *
	 * @param string $block_name Test block.
	 */
	public function test_renders_the_blocks_hotspots_on_the_blocks_image( string $block_name ): void {
		$this->go_to_post_with_block( $block_name, $this->block_image );

		$html = $this->render_blocks( get_the_content() );

		$this->assertRendersBlock( $html );
	}

	/**
	 * Inside a block, the shortcode, hotspot_field_render() and
	 * hotspot_field_render_hotspots() fed with get_field() render the same
	 * markup.
	 *
	 * @dataProvider block_names
	 *
	 * @param string $block_name Test block.
	 */
	public function test_every_entry_point_renders_the_same_markup_inside_a_block( string $block_name ): void {
		$this->go_to_post_with_block( $block_name, $this->block_image );
		$content = get_the_content();

		$expected = $this->render_blocks( $content );
		$this->assertRendersBlock( $expected );

		$entry_points = array(
			'shortcode'                     => static fn () => do_shortcode( '[hotspot_field name="hotspots"]' ),
			'hotspot_field_render_hotspots' => static fn () => hotspot_field_render_hotspots( (array) get_field( 'hotspots' ), (int) get_field( 'photo' ) ),
		);

		foreach ( $entry_points as $entry_point => $render ) {
			$this->assertSame(
				$this->comparable( $expected ),
				$this->comparable( $this->render_blocks( $content, $render ) ),
				"Markup of {$entry_point}"
			);
		}
	}

	/**
	 * A block without an image renders nothing, rather than borrowing the
	 * post's image.
	 *
	 * @dataProvider block_names
	 *
	 * @param string $block_name Test block.
	 */
	public function test_a_block_without_an_image_renders_no_image( string $block_name ): void {
		$this->go_to_post_with_block( $block_name, null );

		$html = $this->render_blocks( get_the_content() );

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '<button', $html );
	}

	/**
	 * The same block twice on a page keeps the Popover IDs unique, and each
	 * button opens a Popover of its own block.
	 *
	 * @dataProvider block_names
	 *
	 * @param string $block_name Test block.
	 */
	public function test_the_same_block_twice_on_a_page_keeps_popover_ids_unique( string $block_name ): void {
		$block   = $this->block_markup( $block_name, $this->block_image, self::BLOCK_HOTSPOTS );
		$post_id = $this->create_post_with_blocks( $block . "\n\n" . $block );
		$this->go_to_post( $post_id );

		$html = $this->render_blocks( get_the_content() );

		$this->assertSame( 2 * count( self::BLOCK_HOTSPOTS ), substr_count( $html, '<button' ) );
		$this->assertPopoversWired( $html );
	}

	/**
	 * A have_rows() loop a theme left open on another object, whose rows
	 * have a Hotspot Field of the same name, does not change what a block
	 * renders: the block's own Hotspot Field.
	 *
	 * @dataProvider provide_loop_sources
	 *
	 * @param string $block_name Test block.
	 * @param string $source     `post` for another post, or `option`.
	 */
	public function test_a_block_ignores_a_rows_loop_left_open_on_another_object( string $block_name, string $source ): void {
		$foreign = $this->create_gallery( $source );
		$this->go_to_post_with_block( $block_name, $this->block_image );
		$this->open_foreign_rows_loop( 'gallery', $foreign );

		$this->assertRendersBlock( $this->render_blocks( get_the_content() ) );
		$this->assertRendersBlock( $this->render_blocks( get_the_content(), static fn () => do_shortcode( '[hotspot_field name="hotspots"]' ) ) );
	}

	/**
	 * A have_rows() loop the block's own template opens, on another object,
	 * shows each row's Hotspots on the row's image, as get_sub_field() reads
	 * the row's fields; once the loop is over, the block's own Hotspot Field
	 * renders again. With or without a loop left open around the block.
	 *
	 * @dataProvider provide_loop_sources
	 *
	 * @param string $block_name Test block.
	 * @param string $source     `post` for another post, or `option`.
	 */
	public function test_a_rows_loop_opened_by_the_block_shows_the_rows_hotspots( string $block_name, string $source ): void {
		$foreign = $this->create_gallery( $source );
		$this->go_to_post_with_block( $block_name, $this->block_image );
		$content = get_the_content();

		$render = function () use ( $foreign ): string {
			$rows = $this->render_foreign_rows(
				'gallery',
				$foreign,
				static fn () => hotspot_field_render( 'hotspots' ) . do_shortcode( '[hotspot_field name="hotspots"]' )
			);

			return implode( '', $rows ) . '<div class="after-loop">' . hotspot_field_render( 'hotspots' ) . '</div>';
		};

		foreach ( array( 'no loop around the block', 'a loop left open around the block' ) as $context ) {
			if ( 'a loop left open around the block' === $context ) {
				$this->open_foreign_rows_loop( 'gallery', $this->create_gallery( 'option' === $source ? 'post' : 'option' ) );
			}

			$xpath = $this->parse( $this->render_blocks( $content, $render ) );

			$row_images = array();
			foreach ( $xpath->query( '//img[not(ancestor::*[@class="after-loop"])]' ) as $image ) {
				$row_images[] = $image->getAttribute( 'src' );
			}
			$row_labels = array();
			foreach ( $xpath->query( '//button[not(ancestor::*[@class="after-loop"])]' ) as $button ) {
				$row_labels[] = trim( $button->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
			}

			$this->assertSame( array_fill( 0, 2, wp_get_attachment_image_url( $this->post_image, 'large' ) ), $row_images, "The row's image, with {$context}." );
			$this->assertSame( array( 'Foreign row', 'Foreign row' ), $row_labels, "The row's Hotspots, with {$context}." );

			$after = $xpath->query( '//*[@class="after-loop"]' )->item( 0 );
			$this->assertNotNull( $after );
			$this->assertRendersBlock( (string) $after->ownerDocument->saveHTML( $after ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
		}
	}

	/**
	 * A block rendered inside a row of a loop that an enclosing block's
	 * template opened shows its own Hotspots, as the loop is open around it;
	 * back in the enclosing block, the row's Hotspots render again.
	 *
	 * @dataProvider block_names
	 *
	 * @param string $block_name Test block.
	 */
	public function test_a_nested_block_ignores_the_loop_of_the_enclosing_block( string $block_name ): void {
		$foreign = $this->create_gallery( 'post' );
		$inner   = $this->block_markup( $block_name, $this->block_image, self::BLOCK_HOTSPOTS );
		$this->go_to_post_with_block( $block_name, $this->post_image );
		$depth = 0;

		$html = $this->render_blocks(
			get_the_content(),
			function () use ( $foreign, $inner, &$depth ): ?string {
				// The nested block renders its field with hotspot_field_render().
				if ( $depth > 0 ) {
					return null;
				}

				++$depth;
				$rows = $this->render_foreign_rows(
					'gallery',
					$foreign,
					static fn () => '<div class="inner">' . do_blocks( $inner ) . '</div><div class="row">' . hotspot_field_render( 'hotspots' ) . '</div>'
				);
				--$depth;

				return implode( '', $rows );
			}
		);

		$xpath = $this->parse( $html );
		$inner = $xpath->query( '//*[@class="inner"]' )->item( 0 );
		$row   = $xpath->query( '//*[@class="row"]//button' );

		$this->assertNotNull( $inner );
		$this->assertRendersBlock( (string) $inner->ownerDocument->saveHTML( $inner ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
		$this->assertSame( 1, $row->length );
		$this->assertSame( 'Foreign row', trim( $row->item( 0 )->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
	}

	/**
	 * Loops on another post and on options, for each test block.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function provide_loop_sources(): array {
		$cases = array();

		foreach ( self::block_names() as $version => list( $block_name ) ) {
			$cases[ "{$version}, loop on another post" ] = array( $block_name, 'post' );
			$cases[ "{$version}, loop on options" ]      = array( $block_name, 'option' );
		}

		return $cases;
	}

	/**
	 * In a preview, where the global post is the previewed post while ACF
	 * reads its latest revision, the blocks of the revision's content render
	 * their own Hotspots, through hotspot_field_render() and the shortcode,
	 * for a published post and a draft alike.
	 *
	 * @dataProvider provide_preview_cases
	 *
	 * @param string $block_name Test block.
	 * @param string $status     Status of the previewed post.
	 */
	public function test_a_preview_renders_the_blocks_of_the_revision( string $block_name, string $status ): void {
		$post_id = $this->create_post_with_blocks( $this->block_markup( $block_name, $this->post_image, self::POST_HOTSPOTS ), array( 'post_status' => $status ) );
		$this->set_post_fields( $post_id );

		$revision_id = $this->go_to_preview(
			$post_id,
			function ( int $revision_id ) use ( $block_name ): void {
				global $wpdb;

				// Revisions cannot be edited through wp_update_post().
				$wpdb->update( $wpdb->posts, array( 'post_content' => $this->block_markup( $block_name, $this->block_image, self::BLOCK_HOTSPOTS ) ), array( 'ID' => $revision_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				clean_post_cache( $revision_id );
			}
		);
		$content     = get_post( $revision_id )->post_content;

		$this->assertRendersBlock( $this->render_blocks( $content ) );
		$this->assertRendersBlock( $this->render_blocks( $content, static fn () => do_shortcode( '[hotspot_field name="hotspots"]' ) ) );
	}

	/**
	 * Published and draft posts, for each test block.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function provide_preview_cases(): array {
		$cases = array();

		foreach ( self::block_names() as $version => list( $block_name ) ) {
			$cases[ "{$version}, published post" ] = array( $block_name, 'publish' );
			$cases[ "{$version}, draft" ]          = array( $block_name, 'draft' );
		}

		return $cases;
	}

	/**
	 * The block editor's preview of a block, which ACF renders in a request
	 * of its own, shows the block's Hotspots on the block's image, each
	 * button wired to its Popover.
	 *
	 * @dataProvider block_names
	 *
	 * @param string $block_name Test block.
	 */
	public function test_the_block_editor_preview_renders_the_blocks_hotspots( string $block_name ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id = self::factory()->post->create();
		$this->set_post_fields( $post_id );

		$html = $this->render_block_preview( $this->block_markup( $block_name, $this->block_image, self::BLOCK_HOTSPOTS ), $post_id );

		$this->assertRendersBlock( $html );
	}

	/**
	 * The block editor's content loads the frontend stylesheet, so previews
	 * show Hotspots in place; block assets on the frontend do not, as there
	 * the stylesheet loads only where a Hotspot Field is rendered.
	 */
	public function test_the_block_editor_loads_the_frontend_stylesheet_for_previews(): void {
		wp_dequeue_style( HotspotFieldForAcf\Renderer::STYLE_HANDLE );

		$this->go_to( home_url( '/' ) );
		do_action( 'enqueue_block_assets' );
		$this->assertFalse( wp_style_is( HotspotFieldForAcf\Renderer::STYLE_HANDLE, 'enqueued' ), 'Not on the frontend.' );

		$this->in_block_editor( static fn () => do_action( 'enqueue_block_assets' ) );

		$this->assertTrue( wp_style_is( HotspotFieldForAcf\Renderer::STYLE_HANDLE, 'enqueued' ), 'In the block editor.' );
		wp_dequeue_style( HotspotFieldForAcf\Renderer::STYLE_HANDLE );
	}

	/**
	 * Quotes and backslashes in a block's Labels and Descriptions are kept,
	 * both once the post is saved and in the editor's preview of unsaved
	 * changes: ACF hands a block's values to the field as they are, not
	 * slashed like form data.
	 *
	 * @dataProvider block_names
	 *
	 * @param string $block_name Test block.
	 */
	public function test_quotes_and_backslashes_are_kept_in_a_block( string $block_name ): void {
		$hotspots = self::QUOTED_HOTSPOTS;
		$block    = $this->block_markup( $block_name, $this->block_image, $hotspots );
		$post_id  = $this->create_post_with_blocks( $block );
		$this->go_to_post( $post_id );

		$renders = array(
			'saved post'     => $this->render_blocks( get_the_content() ),
			'editor preview' => $this->render_block_preview( $block, $post_id ),
		);

		foreach ( $renders as $context => $html ) {
			$this->assertQuotedTexts( $html, $context );
		}
	}

	/**
	 * ACF validates a block's values as stored in the block, under the
	 * block's input names: a quote in one Label neither hides the Hotspots
	 * nor the one without a Label, and a Label of a backslash counts as a
	 * Label.
	 *
	 * @dataProvider provide_every_block
	 *
	 * @param string $block_name Test block.
	 */
	public function test_validation_reads_a_blocks_values_as_stored( string $block_name ): void {
		$hotspot = static fn ( string $label ): array => array(
			'x'           => 50,
			'y'           => 50,
			'label'       => $label,
			'description' => '',
		);

		$this->assertSame(
			array( 'Hotspot 2 needs a Label.' ),
			$this->validate_block( $this->block_markup( $block_name, $this->block_image, array( $hotspot( 'Say "hi"' ), $hotspot( '' ) ) ) )
		);
		$this->assertSame(
			array(),
			$this->validate_block( $this->block_markup( $block_name, $this->block_image, array( $hotspot( '\\' ) ) ) )
		);
	}

	/**
	 * A block that stores its values in the post's meta (`usePostMeta`)
	 * keeps quotes and backslashes in its Labels and Descriptions when the
	 * post is saved, as ACF hands the field the block's JSON unslashed under
	 * the post's ID; get_field() on the post and the block on the page show
	 * them.
	 */
	public function test_a_block_saving_to_post_meta_keeps_quotes_and_backslashes(): void {
		$hotspots = self::QUOTED_HOTSPOTS;
		$post_id  = $this->create_post_with_blocks( $this->block_markup( self::META_BLOCK, $this->block_image, $hotspots ) );

		$this->assertSame(
			array( 'Say "hi"', 'Back\\slash' ),
			array_column( (array) get_field( 'hotspots', $post_id ), 'label' ),
			'Labels read with get_field() on the post.'
		);
		$this->assertSame(
			array( 'Saved in C:\\Rooms\\Lamp', '' ),
			array_column( (array) get_field( 'hotspots', $post_id ), 'description' ),
			'Descriptions read with get_field() on the post.'
		);

		$this->go_to_post( $post_id );
		$this->assertQuotedTexts( $this->render_blocks( get_the_content() ), 'page' );
	}

	/**
	 * Every test block: one per ACF block version and the one saving to
	 * post meta.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_every_block(): array {
		return self::block_names() + array( 'ACF block saving to post meta' => array( self::META_BLOCK ) );
	}

	/**
	 * Creates a published post holding one test block, with other values in
	 * the post's own `photo` and `hotspots` fields, and makes it the current
	 * post.
	 *
	 * @param string   $block_name Test block.
	 * @param int|null $image_id   Image of the block; null for none.
	 */
	private function go_to_post_with_block( string $block_name, ?int $image_id ): void {
		$post_id = $this->create_post_with_blocks( $this->block_markup( $block_name, $image_id, self::BLOCK_HOTSPOTS ) );
		$this->set_post_fields( $post_id );
		$this->go_to_post( $post_id );
	}

	/**
	 * Stores a `gallery` row with the post image and one Hotspot, `Foreign
	 * row`, on another object than the post holding the block.
	 *
	 * @param string $source `post` for another post, or `option`.
	 * @return int|string ACF post ID of the gallery.
	 */
	private function create_gallery( string $source ) {
		$foreign = 'option' === $source ? 'option' : self::factory()->post->create();
		update_field(
			'field_hotspot_block_tests_gallery',
			array(
				array(
					'field_hotspot_block_tests_gallery_photo'    => $this->post_image,
					'field_hotspot_block_tests_gallery_hotspots' => array(
						array(
							'x'     => 50,
							'y'     => 50,
							'label' => 'Foreign row',
						),
					),
				),
			),
			$foreign
		);

		return $foreign;
	}

	/**
	 * Asserts that markup shows the Labels and Descriptions of
	 * QUOTED_HOTSPOTS as written.
	 *
	 * @param string $html    Markup.
	 * @param string $context Where the markup was rendered.
	 */
	private function assertQuotedTexts( string $html, string $context ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit naming.
		$texts = array();
		foreach ( $this->parse( $html )->query( '//button | //*[@popover]/p' ) as $node ) {
			$texts[] = trim( $node->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
		}

		$this->assertSame( array( 'Say "hi"', 'Say "hi"', 'Saved in C:\\Rooms\\Lamp', 'Back\\slash', 'Back\\slash' ), $texts, "Labels and Descriptions in the {$context}" );
	}

	/**
	 * Stores the post's own image and Hotspots, outside the block.
	 *
	 * @param int $post_id Post ID.
	 */
	private function set_post_fields( int $post_id ): void {
		update_field( 'field_hotspot_tests_photo', $this->post_image, $post_id );
		$this->save_hotspots( $post_id, self::POST_HOTSPOTS );
	}

	/**
	 * Makes a post the current post, as on its page.
	 *
	 * @param int $post_id Post ID.
	 */
	private function go_to_post( int $post_id ): void {
		$this->go_to( get_permalink( $post_id ) );
		$this->assertTrue( have_posts() );
		the_post();
		$this->assertSame( $post_id, get_the_ID() );
	}

	/**
	 * Asserts that markup shows the block's image with the block's Hotspots,
	 * in order, each button wired to its Popover.
	 *
	 * @param string $html Markup.
	 */
	private function assertRendersBlock( string $html ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit naming.
		$xpath  = $this->parse( $html );
		$images = $xpath->query( '//img' );

		$this->assertSame( 1, $images->length, 'One image is rendered.' );
		$this->assertSame( wp_get_attachment_image_url( $this->block_image, 'large' ), $images->item( 0 )->getAttribute( 'src' ), 'The block\'s image is rendered.' );

		$labels = array();
		foreach ( $xpath->query( '//button' ) as $button ) {
			$labels[] = trim( $button->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
		}
		$this->assertSame( array_column( self::BLOCK_HOTSPOTS, 'label' ), $labels, 'The block\'s Hotspots are rendered.' );

		$this->assertPopoversWired( $html );
	}

	/**
	 * Asserts that IDs are unique and every button opens a Popover with its
	 * Label.
	 *
	 * @param string $html Markup.
	 */
	private function assertPopoversWired( string $html ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit naming.
		$xpath = $this->parse( $html );
		$ids   = array();

		foreach ( $xpath->query( '//*[@id]' ) as $element ) {
			$ids[] = $element->getAttribute( 'id' );
		}
		$this->assertSame( array_unique( $ids ), $ids, 'IDs are unique.' );

		foreach ( $xpath->query( '//button' ) as $button ) {
			$popovers = $xpath->query( sprintf( '//*[@popover][@id="%s"]', $button->getAttribute( 'popovertarget' ) ) );

			$this->assertSame( 1, $popovers->length, 'The button opens a Popover.' );
			$this->assertStringContainsString( trim( $button->textContent ), $popovers->item( 0 )->textContent, 'The Popover belongs to the button.' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
		}
	}

	/**
	 * Markup with Popover IDs, unique per render, replaced by placeholders,
	 * and without the loading hints WordPress varies per image on a page.
	 *
	 * @param string $html Markup.
	 */
	private function comparable( string $html ): string {
		$placeholders = array();

		$html = (string) preg_replace_callback(
			'/ (id|popovertarget)="([^"]*)"/',
			static function ( array $matches ) use ( &$placeholders ): string {
				$placeholders[ $matches[2] ] ??= 'popover-' . ( count( $placeholders ) + 1 );

				return sprintf( ' %s="%s"', $matches[1], $placeholders[ $matches[2] ] );
			},
			$html
		);

		// Lazy-loaded images also get "auto" in their sizes.
		return (string) preg_replace( '/ (?:loading|fetchpriority|decoding|sizes)="[^"]*"/', '', $html );
	}

	/**
	 * Parses rendered markup.
	 *
	 * @param string $html Markup.
	 */
	private function parse( string $html ): DOMXPath {
		$document = new DOMDocument();
		libxml_use_internal_errors( true );
		$document->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
		libxml_clear_errors();

		return new DOMXPath( $document );
	}
}
