<?php
/**
 * Tests for rendering Hotspots on the frontend.
 *
 * @package HotspotFieldForAcf
 */

/**
 * The shortcode, hotspot_field_render() and hotspot_field_render_hotspots().
 */
class HotspotFieldRenderTest extends Hotspot_Field_Test_Case {

	/**
	 * Hotspots used by most tests, in editor order.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private const HOTSPOTS = array(
		array(
			'x'           => 12.5,
			'y'           => 40,
			'label'       => 'Lamp',
			'description' => 'A brass lamp.',
		),
		array(
			'x'           => 80,
			'y'           => 7.25,
			'label'       => 'Chair',
			'description' => '',
		),
		array(
			'x'           => 0,
			'y'           => 100,
			'label'       => 'Rug',
			'description' => 'Hand woven.',
		),
	);

	/**
	 * The test image.
	 *
	 * @var int
	 */
	private int $image_id;

	/**
	 * Uploads the test image.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->image_id = $this->create_image( 'A living room' );
	}

	/**
	 * Each Hotspot becomes a button named by its Label, in list order, at its
	 * percentage position.
	 */
	public function test_renders_one_positioned_button_per_hotspot_in_order(): void {
		$html = hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id );

		$this->assertSame(
			array(
				array(
					'type' => 'button',
					'name' => 'Lamp',
					'left' => '12.5%',
					'top'  => '40%',
				),
				array(
					'type' => 'button',
					'name' => 'Chair',
					'left' => '80%',
					'top'  => '7.25%',
				),
				array(
					'type' => 'button',
					'name' => 'Rug',
					'left' => '0%',
					'top'  => '100%',
				),
			),
			$this->buttons( $html )
		);
	}

	/**
	 * The image comes from WordPress, with the alt text from the media library.
	 */
	public function test_renders_the_image_with_its_media_library_alt_text(): void {
		$image = $this->image( hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id ) );

		$this->assertSame( wp_get_attachment_image_url( $this->image_id, 'large' ), $image->getAttribute( 'src' ) );
		$this->assertSame( 'A living room', $image->getAttribute( 'alt' ) );
		$this->assertNotSame( '', $image->getAttribute( 'srcset' ) );
	}

	/**
	 * The image size can be chosen; it defaults to `large`.
	 */
	public function test_renders_the_requested_image_size(): void {
		$this->assertSame( '1024', $this->image( hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id ) )->getAttribute( 'width' ) );
		$this->assertSame( '150', $this->image( hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id, 'thumbnail' ) )->getAttribute( 'width' ) );
	}

	/**
	 * The image may be given as an attachment ID, an ACF image array or a URL.
	 */
	public function test_accepts_the_image_as_id_array_or_url(): void {
		$expected = hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id );

		$this->assertNotSame( '', $expected );
		$this->assertSame( $expected, hotspot_field_render_hotspots( self::HOTSPOTS, (string) $this->image_id ), 'ID as a string' );
		$this->assertSame( $expected, hotspot_field_render_hotspots( self::HOTSPOTS, acf_get_attachment( $this->image_id ) ), 'ACF image array' );
		$this->assertSame( $expected, hotspot_field_render_hotspots( self::HOTSPOTS, array( 'id' => $this->image_id ) ), 'Array with lowercase id' );
		$this->assertSame( $expected, hotspot_field_render_hotspots( self::HOTSPOTS, wp_get_attachment_url( $this->image_id ) ), 'URL' );
	}

	/**
	 * Without an image nothing is rendered, even with Hotspots.
	 *
	 * @dataProvider provide_missing_images
	 *
	 * @param mixed $image Image argument.
	 */
	public function test_renders_nothing_without_an_image( $image ): void {
		$this->assertSame( '', hotspot_field_render_hotspots( self::HOTSPOTS, $image ) );
	}

	/**
	 * Image arguments that do not lead to an image.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function provide_missing_images(): array {
		return array(
			'zero'              => array( 0 ),
			'empty string'      => array( '' ),
			'empty array'       => array( array() ),
			'missing ID'        => array( 999999 ),
			'unknown URL'       => array( 'https://example.org/not-in-the-media-library.jpg' ),
			'array without ID'  => array( array( 'url' => 'https://example.org/x.jpg' ) ),
			'not an attachment' => array( 1 ),
		);
	}

	/**
	 * A post or a non-image attachment is not an image.
	 */
	public function test_renders_nothing_for_a_post_or_a_non_image_attachment(): void {
		$post_id     = self::factory()->post->create();
		$document_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'application/pdf',
				'file'           => 'document.pdf',
			)
		);

		$this->assertSame( '', hotspot_field_render_hotspots( self::HOTSPOTS, $post_id ) );
		$this->assertSame( '', hotspot_field_render_hotspots( self::HOTSPOTS, $document_id ) );
	}

	/**
	 * Without Hotspots the image is still rendered, with no buttons.
	 */
	public function test_renders_the_image_alone_without_hotspots(): void {
		$html = hotspot_field_render_hotspots( array(), $this->image_id );

		$this->assertNotNull( $this->image( $html ) );
		$this->assertSame( array(), $this->buttons( $html ) );
	}

	/**
	 * Label text is escaped; HTML in it is stripped; malformed entries and
	 * positions that are not numbers never reach the markup.
	 */
	public function test_output_is_escaped(): void {
		$html = hotspot_field_render_hotspots(
			array(
				array(
					'x'     => 10,
					'y'     => 20,
					'label' => 'Tom & "Jerry" a < b',
				),
				array(
					'x'     => 30,
					'y'     => 40,
					'label' => '<img src=x onerror=alert(1)>Lamp<script>alert(2)</script>',
				),
				array(
					'x'     => '50%;background:url(evil)',
					'y'     => 40,
					'label' => 'Injected style',
				),
				'<script>alert(3)</script>',
			),
			$this->image_id
		);

		$this->assertStringContainsString( 'Tom &amp; &quot;Jerry&quot; a &lt; b', $html );
		$this->assertStringNotContainsString( 'onerror', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'evil', $html );
		$this->assertSame( array( 'Tom & "Jerry" a < b', 'Lamp' ), array_column( $this->buttons( $html ), 'name' ) );
	}

	/**
	 * The shortcode, hotspot_field_render() and hotspot_field_render_hotspots()
	 * produce identical markup for a field in a field group.
	 */
	public function test_all_entry_points_produce_identical_markup(): void {
		$post_id = $this->create_post_with_hotspots( self::HOTSPOTS );

		$expected = hotspot_field_render_hotspots( get_field( 'hotspots', $post_id ), $this->image_id );

		$this->assertCount( 3, $this->buttons( $expected ) );
		$this->assertSame( $expected, hotspot_field_render( 'hotspots', $post_id ) );
		$this->assertSame( $expected, do_shortcode( sprintf( '[hotspot_field name="hotspots" post_id="%d"]', $post_id ) ) );
	}

	/**
	 * Without a post ID, the shortcode and hotspot_field_render() use the
	 * current post.
	 */
	public function test_entry_points_default_to_the_current_post(): void {
		$post_id = $this->create_post_with_hotspots( self::HOTSPOTS );
		$this->create_post_with_hotspots( array( self::HOTSPOTS[0] ) );

		$this->go_to( get_permalink( $post_id ) );
		$this->assertTrue( have_posts() );
		the_post();

		$expected = $this->without_loading_hints( hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id ) );

		$this->assertSame( $expected, $this->without_loading_hints( hotspot_field_render( 'hotspots' ) ) );
		$this->assertSame( $expected, $this->without_loading_hints( do_shortcode( '[hotspot_field name="hotspots"]' ) ) );
	}

	/**
	 * The size can be passed to the shortcode and to hotspot_field_render().
	 */
	public function test_entry_points_pass_the_image_size(): void {
		$post_id  = $this->create_post_with_hotspots( self::HOTSPOTS );
		$expected = hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id, 'medium' );

		$this->assertSame( '300', $this->image( $expected )->getAttribute( 'width' ) );
		$this->assertSame( $expected, hotspot_field_render( 'hotspots', $post_id, 'medium' ) );
		$this->assertSame( $expected, do_shortcode( sprintf( '[hotspot_field name="hotspots" post_id="%d" size="medium"]', $post_id ) ) );
	}

	/**
	 * When the Linked Image Field is empty, nothing is rendered.
	 */
	public function test_entry_points_render_nothing_without_a_linked_image(): void {
		$post_id = self::factory()->post->create();
		$this->save_hotspots( $post_id, self::HOTSPOTS );

		$this->assertSame( '', hotspot_field_render( 'hotspots', $post_id ) );
		$this->assertSame( '', do_shortcode( sprintf( '[hotspot_field name="hotspots" post_id="%d"]', $post_id ) ) );
	}

	/**
	 * With an image but no Hotspots, the image is rendered alone.
	 */
	public function test_entry_points_render_the_image_alone_without_hotspots(): void {
		$post_id = $this->create_post_with_hotspots( array() );
		$html    = hotspot_field_render( 'hotspots', $post_id );

		$this->assertNotNull( $this->image( $html ) );
		$this->assertSame( array(), $this->buttons( $html ) );
	}

	/**
	 * On a post where the Hotspot Field was never saved, the image is rendered
	 * alone.
	 */
	public function test_entry_points_render_the_image_alone_when_hotspots_were_never_saved(): void {
		$post_id = self::factory()->post->create();
		update_field( 'field_hotspot_tests_photo', $this->image_id, $post_id );

		$expected = hotspot_field_render_hotspots( array(), $this->image_id );

		$this->assertNotNull( $this->image( $expected ) );
		$this->assertSame( $expected, hotspot_field_render( 'hotspots', $post_id ) );
		$this->assertSame( $expected, do_shortcode( sprintf( '[hotspot_field name="hotspots" post_id="%d"]', $post_id ) ) );
	}

	/**
	 * Names that are not a Hotspot Field render nothing.
	 */
	public function test_entry_points_render_nothing_for_other_fields(): void {
		$post_id = $this->create_post_with_hotspots( self::HOTSPOTS );

		$this->assertSame( '', hotspot_field_render( 'photo', $post_id ) );
		$this->assertSame( '', hotspot_field_render( 'no_such_field', $post_id ) );
		$this->assertSame( '', do_shortcode( sprintf( '[hotspot_field name="no_such_field" post_id="%d"]', $post_id ) ) );
		$this->assertSame( '', do_shortcode( sprintf( '[hotspot_field post_id="%d"]', $post_id ) ) );
	}

	/**
	 * Creates a post whose Linked Image Field holds the test image.
	 *
	 * @param array $hotspots Hotspots to save.
	 * @return int Post ID.
	 */
	private function create_post_with_hotspots( array $hotspots ): int {
		$post_id = self::factory()->post->create();
		update_field( 'field_hotspot_tests_photo', $this->image_id, $post_id );
		$this->save_hotspots( $post_id, $hotspots );

		return $post_id;
	}

	/**
	 * Markup without the loading hints WordPress adds per page (for example
	 * fetchpriority on the first image in the loop), which differ between
	 * renders on the same page by design.
	 *
	 * @param string $html Markup.
	 */
	private function without_loading_hints( string $html ): string {
		return (string) preg_replace( '/ (?:loading|fetchpriority|decoding)="[^"]*"/', '', $html );
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

	/**
	 * The rendered image.
	 *
	 * @param string $html Markup.
	 */
	private function image( string $html ): ?DOMElement {
		$images = $this->parse( $html )->query( '//img' );

		$this->assertLessThanOrEqual( 1, $images->length, 'At most one image is rendered.' );

		return $images->item( 0 );
	}

	/**
	 * The rendered buttons: type, accessible name and position.
	 *
	 * @param string $html Markup.
	 * @return array<int, array<string, string>>
	 */
	private function buttons( string $html ): array {
		$buttons = array();

		foreach ( $this->parse( $html )->query( '//button' ) as $button ) {
			$style = $button->getAttribute( 'style' );
			preg_match( '/(?:^|;)\s*left:\s*([^;]+?)\s*(?:;|$)/', $style, $left );
			preg_match( '/(?:^|;)\s*top:\s*([^;]+?)\s*(?:;|$)/', $style, $top );

			$buttons[] = array(
				'type' => $button->getAttribute( 'type' ),
				'name' => trim( $button->getAttribute( 'aria-label' ) ? $button->getAttribute( 'aria-label' ) : $button->textContent ), // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
				'left' => $left[1] ?? '',
				'top'  => $top[1] ?? '',
			);
		}

		return $buttons;
	}
}
