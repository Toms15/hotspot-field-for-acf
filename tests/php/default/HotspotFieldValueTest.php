<?php
/**
 * Tests for the value a Hotspot Field stores and returns.
 *
 * @package HotspotFieldForAcf
 */

/**
 * Saving Hotspots through ACF and reading them back with get_field().
 */
class HotspotFieldValueTest extends Hotspot_Field_Test_Case {

	/**
	 * A post to store values on.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Creates the post.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->post_id = self::factory()->post->create();
	}

	/**
	 * Saved Hotspots come back as an ordered list of x, y, label, description.
	 */
	public function test_saved_hotspots_round_trip_in_order(): void {
		$hotspots = array(
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
				'x'           => 3,
				'y'           => 99.5,
				'label'       => 'Rug',
				'description' => 'Hand woven.',
			),
		);

		$this->save_hotspots( $this->post_id, $hotspots );

		$this->assertSame(
			array(
				array(
					'x'           => 12.5,
					'y'           => 40.0,
					'label'       => 'Lamp',
					'description' => 'A brass lamp.',
				),
				array(
					'x'           => 80.0,
					'y'           => 7.25,
					'label'       => 'Chair',
					'description' => '',
				),
				array(
					'x'           => 3.0,
					'y'           => 99.5,
					'label'       => 'Rug',
					'description' => 'Hand woven.',
				),
			),
			get_field( 'hotspots', $this->post_id )
		);
	}

	/**
	 * The editor submits the list as JSON in the post form, which WordPress
	 * slashes; quotes, backslashes and line breaks survive.
	 */
	public function test_hotspots_submitted_as_json_by_the_editor_are_saved(): void {
		$this->save_hotspots(
			$this->post_id,
			wp_slash(
				wp_json_encode(
					array(
						array(
							'x'           => 25,
							'y'           => 50,
							'label'       => 'Window "south"',
							'description' => "Faces south.\nPath: C:\\photos\\window.jpg",
						),
					)
				)
			)
		);

		$this->assertSame(
			array(
				array(
					'x'           => 25.0,
					'y'           => 50.0,
					'label'       => 'Window "south"',
					'description' => "Faces south.\nPath: C:\\photos\\window.jpg",
				),
			),
			get_field( 'hotspots', $this->post_id )
		);
	}

	/**
	 * Positions are clamped to 0–100 and rounded to two decimals.
	 */
	public function test_positions_are_clamped_and_rounded(): void {
		$this->save_hotspots(
			$this->post_id,
			array(
				array(
					'x'     => -5,
					'y'     => 120,
					'label' => 'Outside',
				),
				array(
					'x'     => 33.33333,
					'y'     => '66.666',
					'label' => 'Thirds',
				),
				array(
					'x'     => 100.004,
					'y'     => 0.005,
					'label' => 'Edges',
				),
			)
		);

		$positions = array_map(
			static fn ( array $hotspot ): array => array( $hotspot['x'], $hotspot['y'] ),
			get_field( 'hotspots', $this->post_id )
		);

		$this->assertSame(
			array(
				array( 0.0, 100.0 ),
				array( 33.33, 66.67 ),
				array( 100.0, 0.01 ),
			),
			$positions
		);
	}

	/**
	 * HTML is stripped from Label and Description; Description keeps line breaks.
	 */
	public function test_html_is_stripped_and_description_line_breaks_are_kept(): void {
		$this->save_hotspots(
			$this->post_id,
			array(
				array(
					'x'           => 10,
					'y'           => 10,
					'label'       => '<strong>Bold</strong> <script>alert(1)</script>lamp',
					'description' => "First <em>line</em>\r\nSecond line\n\n<a href=\"https://example.org\">Third</a> line",
				),
			)
		);

		$hotspot = get_field( 'hotspots', $this->post_id )[0];

		$this->assertSame( 'Bold lamp', $hotspot['label'] );
		$this->assertSame( "First line\nSecond line\n\nThird line", $hotspot['description'] );
	}

	/**
	 * A Label never spans several lines.
	 */
	public function test_label_line_breaks_are_collapsed(): void {
		$this->save_hotspots(
			$this->post_id,
			array(
				array(
					'x'     => 10,
					'y'     => 10,
					'label' => "  Two\nlines  ",
				),
			)
		);

		$this->assertSame( 'Two lines', get_field( 'hotspots', $this->post_id )[0]['label'] );
	}

	/**
	 * Entries that are not Hotspots are dropped; the others keep their order.
	 */
	public function test_malformed_entries_are_dropped(): void {
		$this->save_hotspots(
			$this->post_id,
			array(
				'not a hotspot',
				array(
					'x'     => 1,
					'y'     => 2,
					'label' => 'First',
				),
				array(
					'y'     => 2,
					'label' => 'No x',
				),
				array(
					'x'     => 'left',
					'y'     => 2,
					'label' => 'Text x',
				),
				array(
					'x'     => 1,
					'y'     => array( 2 ),
					'label' => 'Array y',
				),
				array(
					'x'     => 1,
					'y'     => 2,
					'label' => array( 'Array label' ),
				),
				array(
					'x'           => 1,
					'y'           => 2,
					'label'       => 'Array description',
					'description' => array( 'nope' ),
				),
				null,
				array(
					'x'     => 3,
					'y'     => 4,
					'label' => 'Second',
				),
			)
		);

		$this->assertSame(
			array( 'First', 'Second' ),
			wp_list_pluck( get_field( 'hotspots', $this->post_id ), 'label' )
		);
	}

	/**
	 * Missing text keys default to empty strings; unknown keys are ignored.
	 */
	public function test_missing_text_defaults_to_empty_and_extra_keys_are_ignored(): void {
		$this->save_hotspots(
			$this->post_id,
			array(
				array(
					'x'     => 5,
					'y'     => 6,
					'color' => 'red',
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'x'           => 5.0,
					'y'           => 6.0,
					'label'       => '',
					'description' => '',
				),
			),
			get_field( 'hotspots', $this->post_id )
		);
	}

	/**
	 * A field that was never saved returns an empty list when read by its key.
	 * (Read by name, ACF itself returns null until the field is first saved,
	 * whatever the field type.)
	 */
	public function test_unsaved_field_returns_an_empty_list(): void {
		$this->assertSame( array(), get_field( 'field_hotspot_tests_hotspots', $this->post_id ) );
	}

	/**
	 * Saving nothing, or something that is not a list, returns an empty list.
	 *
	 * @dataProvider provide_empty_values
	 *
	 * @param mixed $value Value saved.
	 */
	public function test_empty_or_invalid_values_return_an_empty_list( $value ): void {
		$this->save_hotspots( $this->post_id, $value );

		$this->assertSame( array(), get_field( 'hotspots', $this->post_id ) );
	}

	/**
	 * Values that hold no Hotspots.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function provide_empty_values(): array {
		return array(
			'empty list'   => array( array() ),
			'empty string' => array( '' ),
			'empty JSON'   => array( '[]' ),
			'broken JSON'  => array( '[{"x":' ),
			'plain text'   => array( 'hello' ),
			'number'       => array( 42 ),
		);
	}
}
