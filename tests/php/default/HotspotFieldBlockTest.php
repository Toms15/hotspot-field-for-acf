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
		$this->go_to_post_with_block( $block_name, $this->block_image );
		$this->open_foreign_rows_loop( 'gallery', $foreign );

		$this->assertRendersBlock( $this->render_blocks( get_the_content() ) );
		$this->assertRendersBlock( $this->render_blocks( get_the_content(), static fn () => do_shortcode( '[hotspot_field name="hotspots"]' ) ) );
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
		$hotspots = array(
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
		$block    = $this->block_markup( $block_name, $this->block_image, $hotspots );
		$post_id  = $this->create_post_with_blocks( $block );
		$this->go_to_post( $post_id );

		$renders = array(
			'saved post'     => $this->render_blocks( get_the_content() ),
			'editor preview' => $this->render_block_preview( $block, $post_id ),
		);

		foreach ( $renders as $context => $html ) {
			$xpath = $this->parse( $html );
			$texts = array();
			foreach ( $xpath->query( '//button | //*[@popover]/p' ) as $node ) {
				$texts[] = trim( $node->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
			}

			$this->assertSame( array( 'Say "hi"', 'Say "hi"', 'Saved in C:\\Rooms\\Lamp', 'Back\\slash', 'Back\\slash' ), $texts, "Labels and Descriptions in the {$context}" );
		}
	}

	/**
	 * ACF validates a block's values as stored in the block: a quote in one
	 * Label neither hides the Hotspots nor the one without a Label, and a
	 * Label of a backslash counts as a Label.
	 *
	 * @dataProvider block_names
	 *
	 * @param string $block_name Test block.
	 */
	public function test_validation_reads_a_blocks_values_as_stored( string $block_name ): void {
		$field_key = 'field_' . str_replace( array( 'acf/', '-' ), array( '', '_' ), $block_name ) . '_hotspots';
		$field     = acf_get_field( $field_key );
		$input     = "acf-block_0123456789abcdef[{$field_key}]";
		$hotspot   = static fn ( string $label ): array => array(
			'x'           => 50,
			'y'           => 50,
			'label'       => $label,
			'description' => '',
		);

		acf_reset_validation_errors();
		$this->assertFalse( acf_validate_value( (string) wp_json_encode( array( $hotspot( 'Say "hi"' ), $hotspot( '' ) ) ), $field, $input ) );
		$this->assertSame( array( 'Hotspot 2 needs a Label.' ), array_column( (array) acf_get_validation_errors(), 'message' ) );

		acf_reset_validation_errors();
		$this->assertTrue( acf_validate_value( (string) wp_json_encode( array( $hotspot( '\\' ) ) ), $field, $input ) );
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
