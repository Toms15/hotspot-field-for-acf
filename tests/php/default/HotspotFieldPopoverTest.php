<?php
/**
 * Tests for the frontend Popovers and the frontend assets.
 *
 * @package HotspotFieldForAcf
 */

/**
 * Each rendered Hotspot button opens a Popover with its Label and
 * Description; the frontend CSS and JS load only when something is rendered.
 */
class HotspotFieldPopoverTest extends Hotspot_Field_Test_Case {

	/**
	 * Handle of the frontend stylesheet and script.
	 */
	private const HANDLE = 'hotspot-field-for-acf';

	/**
	 * Hotspots used by the tests, in editor order.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private const HOTSPOTS = array(
		array(
			'x'           => 12.5,
			'y'           => 40,
			'label'       => 'Lamp',
			'description' => "A brass lamp.\nSecond line.",
		),
		array(
			'x'           => 80,
			'y'           => 7.25,
			'label'       => 'Chair',
			'description' => '',
		),
	);

	/**
	 * The test image.
	 *
	 * @var int
	 */
	private int $image_id;

	/**
	 * Uploads the test image and forgets assets enqueued by earlier tests.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->image_id = $this->create_image( 'A living room' );

		wp_dequeue_style( self::HANDLE );
		wp_dequeue_script( self::HANDLE );
	}

	/**
	 * Every button opens its own Popover, holding its Label and Description.
	 */
	public function test_each_button_controls_a_popover_with_its_label_and_description(): void {
		$xpath   = $this->parse( hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id ) );
		$buttons = $xpath->query( '//button' );

		$this->assertSame( 2, $buttons->length );

		$popover_ids = array();

		foreach ( $buttons as $index => $button ) {
			$popover = $this->popover_of( $xpath, $button );

			$this->assertSame( 'auto', $popover->getAttribute( 'popover' ), 'The Popover closes on Escape and outside clicks.' );
			$this->assertSame( self::HOTSPOTS[ $index ]['label'], $this->text_of( $xpath, $popover, 'hotspot-field__popover-label' ) );

			$popover_ids[] = $popover->getAttribute( 'id' );
		}

		$this->assertSame( "A brass lamp.\nSecond line.", $this->text_of( $xpath, $this->popover_of( $xpath, $buttons->item( 0 ) ), 'hotspot-field__popover-description' ) );
		$this->assertCount( 2, array_unique( $popover_ids ), 'Each button has its own Popover.' );
	}

	/**
	 * A Description's line breaks are kept as visible line breaks.
	 */
	public function test_description_line_breaks_are_rendered(): void {
		$xpath       = $this->parse( hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id ) );
		$description = $xpath->query( '//*[contains(@class, "hotspot-field__popover-description")]' )->item( 0 );

		$this->assertNotNull( $description );
		$this->assertSame( 1, $xpath->query( './/br', $description )->length );
	}

	/**
	 * Without a Description the Popover holds only the Label.
	 */
	public function test_popover_without_description_has_only_the_label(): void {
		$xpath   = $this->parse( hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id ) );
		$popover = $this->popover_of( $xpath, $xpath->query( '//button' )->item( 1 ) );

		$this->assertSame( 'Chair', $this->text_of( $xpath, $popover, 'hotspot-field__popover-label' ) );
		$this->assertSame( 0, $xpath->query( './/*[contains(@class, "hotspot-field__popover-description")]', $popover )->length );
	}

	/**
	 * Each Popover directly follows its button, so reading and Tab order
	 * follow the editor-defined order.
	 */
	public function test_popovers_follow_their_buttons_in_list_order(): void {
		$xpath = $this->parse( hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id ) );

		foreach ( $xpath->query( '//button' ) as $button ) {
			$next = $button->nextSibling; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
			while ( $next && ! $next instanceof DOMElement ) {
				$next = $next->nextSibling; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
			}

			$this->assertSame( $this->popover_of( $xpath, $button ), $next );
		}
	}

	/**
	 * Each button carries its Hotspot's data, as the `hotspotfield:open`
	 * event hands it to themes.
	 */
	public function test_buttons_carry_the_hotspot_data(): void {
		$xpath = $this->parse( hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id ) );
		$data  = array();

		foreach ( $xpath->query( '//button' ) as $button ) {
			$data[] = json_decode( $button->getAttribute( 'data-hotspot' ), true );
		}

		$this->assertSame(
			array(
				array(
					'x'           => 12.5,
					'y'           => 40,
					'label'       => 'Lamp',
					'description' => "A brass lamp.\nSecond line.",
				),
				array(
					'x'           => 80,
					'y'           => 7.25,
					'label'       => 'Chair',
					'description' => '',
				),
			),
			$data
		);
	}

	/**
	 * Label and Description are escaped in the Popover and in the button data.
	 */
	public function test_popover_content_is_escaped(): void {
		$html = hotspot_field_render_hotspots(
			array(
				array(
					'x'           => 10,
					'y'           => 20,
					'label'       => 'Tom & "Jerry" a < b',
					'description' => "<img src=x onerror=alert(1)>x > y & 'z'<script>alert(2)</script>",
				),
			),
			$this->image_id
		);

		$this->assertStringNotContainsString( 'onerror', $html );
		$this->assertStringNotContainsString( '<script', $html );

		$xpath   = $this->parse( $html );
		$button  = $xpath->query( '//button' )->item( 0 );
		$popover = $this->popover_of( $xpath, $button );

		$this->assertSame( 'Tom & "Jerry" a < b', $this->text_of( $xpath, $popover, 'hotspot-field__popover-label' ) );
		$this->assertSame( "x > y & 'z'", $this->text_of( $xpath, $popover, 'hotspot-field__popover-description' ) );
		$this->assertSame( 'Tom & "Jerry" a < b', json_decode( $button->getAttribute( 'data-hotspot' ), true )['label'] );
	}

	/**
	 * The frontend assets are registered on every page but not enqueued.
	 */
	public function test_assets_are_registered_but_not_enqueued_without_rendering(): void {
		$this->assertTrue( wp_style_is( self::HANDLE, 'registered' ) );
		$this->assertTrue( wp_script_is( self::HANDLE, 'registered' ) );
		$this->assertFalse( wp_style_is( self::HANDLE, 'enqueued' ) );
		$this->assertFalse( wp_script_is( self::HANDLE, 'enqueued' ) );
	}

	/**
	 * Rendering enqueues the frontend stylesheet and script, the script in the
	 * footer without blocking the page.
	 */
	public function test_rendering_enqueues_the_assets(): void {
		$this->assertNotSame( '', hotspot_field_render_hotspots( self::HOTSPOTS, $this->image_id ) );

		$this->assertTrue( wp_style_is( self::HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_script_is( self::HANDLE, 'enqueued' ) );
		$this->assertSame( 1, wp_scripts()->get_data( self::HANDLE, 'group' ) );
		$this->assertSame( 'defer', wp_scripts()->get_data( self::HANDLE, 'strategy' ) );
	}

	/**
	 * The shortcode enqueues the assets too.
	 */
	public function test_shortcode_enqueues_the_assets(): void {
		$post_id = self::factory()->post->create();
		update_field( 'field_hotspot_tests_photo', $this->image_id, $post_id );
		$this->save_hotspots( $post_id, self::HOTSPOTS );

		$this->assertNotSame( '', do_shortcode( sprintf( '[hotspot_field name="hotspots" post_id="%d"]', $post_id ) ) );

		$this->assertTrue( wp_style_is( self::HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_script_is( self::HANDLE, 'enqueued' ) );
	}

	/**
	 * When nothing is rendered, nothing is enqueued.
	 */
	public function test_nothing_is_enqueued_when_nothing_is_rendered(): void {
		$this->assertSame( '', hotspot_field_render_hotspots( self::HOTSPOTS, 0 ) );
		$this->assertSame( '', hotspot_field_render( 'no_such_field', self::factory()->post->create() ) );

		$this->assertFalse( wp_style_is( self::HANDLE, 'enqueued' ) );
		$this->assertFalse( wp_script_is( self::HANDLE, 'enqueued' ) );
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
	 * The Popover a button opens, through its `popovertarget` attribute.
	 *
	 * @param DOMXPath   $xpath  Parsed markup.
	 * @param DOMElement $button Button.
	 */
	private function popover_of( DOMXPath $xpath, DOMElement $button ): DOMElement {
		$id = $button->getAttribute( 'popovertarget' );
		$this->assertNotSame( '', $id, 'The button opens a Popover.' );

		$popovers = $xpath->query( sprintf( '//*[@id="%s"]', $id ) );
		$this->assertSame( 1, $popovers->length, 'The Popover exists once.' );
		$this->assertTrue( $popovers->item( 0 )->hasAttribute( 'popover' ) );

		return $popovers->item( 0 );
	}

	/**
	 * The text of the element with a class inside a Popover.
	 *
	 * @param DOMXPath   $xpath      Parsed markup.
	 * @param DOMElement $popover    Popover.
	 * @param string     $class_name Class name.
	 */
	private function text_of( DOMXPath $xpath, DOMElement $popover, string $class_name ): string {
		$elements = $xpath->query( sprintf( './/*[contains(concat(" ", @class, " "), " %s ")]', $class_name ), $popover );
		$this->assertSame( 1, $elements->length, "One .$class_name in the Popover." );

		return $elements->item( 0 )->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
	}
}
