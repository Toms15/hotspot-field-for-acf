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
		$expected = $this->comparable( hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id ) );

		$this->assertNotSame( '', $expected );
		$this->assertSame( $expected, $this->comparable( hotspot_field_render_hotspots( self::HOTSPOTS, (string) $this->image_id ) ), 'ID as a string' );
		$this->assertSame( $expected, $this->comparable( hotspot_field_render_hotspots( self::HOTSPOTS, acf_get_attachment( $this->image_id ) ) ), 'ACF image array' );
		$this->assertSame( $expected, $this->comparable( hotspot_field_render_hotspots( self::HOTSPOTS, array( 'id' => $this->image_id ) ) ), 'Array with lowercase id' );
		$this->assertSame( $expected, $this->comparable( hotspot_field_render_hotspots( self::HOTSPOTS, wp_get_attachment_url( $this->image_id ) ) ), 'URL' );
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
		$this->assertSame( $this->comparable( $expected ), $this->comparable( hotspot_field_render( 'hotspots', $post_id ) ) );
		$this->assertSame( $this->comparable( $expected ), $this->comparable( do_shortcode( sprintf( '[hotspot_field name="hotspots" post_id="%d"]', $post_id ) ) ) );
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

		$expected = $this->without_loading_hints( $this->comparable( hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id ) ) );

		$this->assertSame( $expected, $this->without_loading_hints( $this->comparable( hotspot_field_render( 'hotspots' ) ) ) );
		$this->assertSame( $expected, $this->without_loading_hints( $this->comparable( do_shortcode( '[hotspot_field name="hotspots"]' ) ) ) );
	}

	/**
	 * The size can be passed to the shortcode and to hotspot_field_render().
	 */
	public function test_entry_points_pass_the_image_size(): void {
		$post_id  = $this->create_post_with_hotspots( self::HOTSPOTS );
		$expected = hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id, 'medium' );

		$this->assertSame( '300', $this->image( $expected )->getAttribute( 'width' ) );
		$this->assertSame( $this->comparable( $expected ), $this->comparable( hotspot_field_render( 'hotspots', $post_id, 'medium' ) ) );
		$this->assertSame( $this->comparable( $expected ), $this->comparable( do_shortcode( sprintf( '[hotspot_field name="hotspots" post_id="%d" size="medium"]', $post_id ) ) ) );
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
	 * A Hotspot without a Label is still named, after its place in the list.
	 */
	public function test_hotspot_without_a_label_is_named_after_its_position(): void {
		$html = hotspot_field_render_hotspots(
			array(
				self::HOTSPOTS[0],
				array(
					'x' => 50,
					'y' => 50,
				),
			),
			$this->image_id
		);

		$this->assertSame( array( 'Lamp', 'Hotspot 2' ), array_column( $this->buttons( $html ), 'name' ) );
	}

	/**
	 * The shortcode renders the Hotspot Field of another post only when that
	 * post is public.
	 */
	public function test_shortcode_renders_another_published_post(): void {
		$post_id = $this->create_post_with_hotspots( self::HOTSPOTS );

		$this->assertCount( 3, $this->buttons( do_shortcode( sprintf( '[hotspot_field name="hotspots" post_id="%d"]', $post_id ) ) ) );
	}

	/**
	 * The shortcode never reveals Hotspots of posts visitors cannot see.
	 *
	 * @dataProvider provide_non_public_posts
	 *
	 * @param array $post_data Post fields.
	 */
	public function test_shortcode_renders_nothing_for_a_non_public_post( array $post_data ): void {
		$post_id = $this->create_post_with_hotspots( self::HOTSPOTS, $post_data );

		$this->assertSame( '', do_shortcode( sprintf( '[hotspot_field name="hotspots" post_id="%d"]', $post_id ) ) );
	}

	/**
	 * Posts whose Hotspots the shortcode must not show to visitors.
	 *
	 * @return array<string, array{array}>
	 */
	public static function provide_non_public_posts(): array {
		return array(
			'draft'              => array( array( 'post_status' => 'draft' ) ),
			'pending'            => array( array( 'post_status' => 'pending' ) ),
			'private'            => array( array( 'post_status' => 'private' ) ),
			'trashed'            => array( array( 'post_status' => 'trash' ) ),
			'password-protected' => array( array( 'post_password' => 'secret' ) ),
		);
	}

	/**
	 * In the post itself (as in its preview) the shortcode renders the post's
	 * own Hotspots even before it is published.
	 */
	public function test_shortcode_renders_the_current_draft_post(): void {
		$post_id         = $this->create_post_with_hotspots( self::HOTSPOTS, array( 'post_status' => 'draft' ) );
		$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulates rendering inside the post.

		$this->assertCount( 3, $this->buttons( do_shortcode( '[hotspot_field name="hotspots"]' ) ) );
		$this->assertCount( 3, $this->buttons( do_shortcode( sprintf( '[hotspot_field name="hotspots" post_id="%d"]', $post_id ) ) ) );
	}

	/**
	 * In a preview of a draft the current post stays the draft while ACF
	 * reads its latest revision; the draft's Hotspots render, with or
	 * without its post ID.
	 */
	public function test_shortcode_renders_the_previewed_draft(): void {
		$post_id = $this->create_post_with_hotspots( self::HOTSPOTS, array( 'post_status' => 'draft' ) );
		$this->go_to_preview( $post_id );

		$this->assertCount( 3, $this->buttons( do_shortcode( '[hotspot_field name="hotspots"]' ) ) );
		$this->assertCount( 3, $this->buttons( do_shortcode( sprintf( '[hotspot_field name="hotspots" post_id="%d"]', $post_id ) ) ) );
	}

	/**
	 * Until its password is entered, a password-protected post's own
	 * Hotspots stay hidden too.
	 */
	public function test_shortcode_renders_nothing_in_the_current_post_until_its_password_is_entered(): void {
		$post_id         = $this->create_post_with_hotspots( self::HOTSPOTS, array( 'post_password' => 'secret' ) );
		$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulates rendering inside the post.

		$this->assertSame( '', do_shortcode( '[hotspot_field name="hotspots"]' ) );
	}

	/**
	 * A post ID that is not positive means the current post, so the password
	 * check still applies.
	 *
	 * @dataProvider provide_non_positive_post_ids
	 *
	 * @param string $post_id Shortcode post_id attribute.
	 */
	public function test_shortcode_treats_a_non_positive_post_id_as_the_current_post( string $post_id ): void {
		$protected_id    = $this->create_post_with_hotspots( self::HOTSPOTS, array( 'post_password' => 'secret' ) );
		$GLOBALS['post'] = get_post( $protected_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulates rendering on the protected post's page.

		$this->assertSame( '', do_shortcode( sprintf( '[hotspot_field name="hotspots" post_id="%s"]', $post_id ) ) );

		$public_id       = $this->create_post_with_hotspots( self::HOTSPOTS );
		$GLOBALS['post'] = get_post( $public_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulates rendering on a public post's page.

		$this->assertCount( 3, $this->buttons( do_shortcode( sprintf( '[hotspot_field name="hotspots" post_id="%s"]', $post_id ) ) ) );
	}

	/**
	 * Post IDs that are not positive.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_non_positive_post_ids(): array {
		return array(
			'zero'     => array( '0' ),
			'negative' => array( '-5' ),
		);
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
	 * @param array $hotspots  Hotspots to save.
	 * @param array $post_data Post fields; published by default.
	 * @return int Post ID.
	 */
	private function create_post_with_hotspots( array $hotspots, array $post_data = array() ): int {
		$post_id = self::factory()->post->create( $post_data );
		update_field( 'field_hotspot_tests_photo', $this->image_id, $post_id );
		$this->save_hotspots( $post_id, $hotspots );

		return $post_id;
	}

	/**
	 * Markup with its Popover IDs replaced by placeholders, after checking
	 * that each button's `popovertarget` names a Popover of the same markup.
	 * Popover IDs are unique per page, so they differ between renders; the
	 * rest of the markup is identical whatever the entry point.
	 *
	 * @param string $html Markup.
	 */
	private function comparable( string $html ): string {
		$xpath = $this->parse( $html );

		foreach ( $xpath->query( '//button' ) as $button ) {
			$target = $button->getAttribute( 'popovertarget' );

			$this->assertSame( 1, $xpath->query( sprintf( '//*[@popover][@id="%s"]', $target ) )->length, 'Each button opens a Popover of the same markup.' );
		}

		$placeholders = array();

		return (string) preg_replace_callback(
			'/ (id|popovertarget)="([^"]*)"/',
			static function ( array $matches ) use ( &$placeholders ): string {
				$placeholders[ $matches[2] ] ??= 'popover-' . ( count( $placeholders ) + 1 );

				return sprintf( ' %s="%s"', $matches[1], $placeholders[ $matches[2] ] );
			},
			$html
		);
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
