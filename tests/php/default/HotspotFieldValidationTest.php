<?php
/**
 * Tests for validating a Hotspot Field's content when content is saved.
 *
 * @package HotspotFieldForAcf
 */

/**
 * Validation through ACF's validation entry point, acf_validate_value(), with
 * the value as the editor submits it: the list as JSON, slashed like all form
 * data.
 */
class HotspotFieldValidationTest extends Hotspot_Field_Test_Case {

	/**
	 * Name of the Hotspot Field's input in the post form.
	 */
	private const INPUT = 'acf[field_hotspot_tests_hotspots]';

	/**
	 * Starts every test without validation errors.
	 */
	public function set_up(): void {
		parent::set_up();

		acf_reset_validation_errors();
	}

	/**
	 * Clears the errors the test added.
	 */
	public function tear_down(): void {
		acf_reset_validation_errors();

		parent::tear_down();
	}

	/**
	 * Hotspots that all have a Label pass validation.
	 */
	public function test_hotspots_with_labels_are_valid(): void {
		$this->assertTrue(
			$this->validate(
				array(
					$this->hotspot( 'Lamp' ),
					$this->hotspot( 'Chair "south"', "Line one.\nPath: C:\\photos" ),
				)
			)
		);
		$this->assertSame( array(), $this->errors() );
	}

	/**
	 * A Hotspot without a Label blocks the save with a message naming its row.
	 */
	public function test_hotspot_without_label_blocks_the_save_and_names_its_row(): void {
		$this->assertFalse(
			$this->validate(
				array(
					$this->hotspot( 'Lamp' ),
					$this->hotspot( '' ),
					$this->hotspot( 'Rug' ),
				)
			)
		);
		$this->assertSame( array( 'Hotspot 2 needs a Label.' ), $this->errors() );
	}

	/**
	 * Every row without a Label is named.
	 */
	public function test_every_hotspot_without_label_is_named(): void {
		$this->assertFalse(
			$this->validate(
				array(
					$this->hotspot( '' ),
					$this->hotspot( 'Lamp' ),
					$this->hotspot( '   ' ),
					$this->hotspot( '<strong></strong>' ),
				)
			)
		);
		$this->assertSame( array( 'Hotspots 1, 3, and 4 need a Label.' ), $this->errors() );
	}

	/**
	 * A required Hotspot Field with no Hotspots blocks the save.
	 */
	public function test_required_field_without_hotspots_blocks_the_save(): void {
		$this->assertFalse( $this->validate( array(), true ) );
		$this->assertSame( array( 'Add at least one Hotspot to Hotspots.' ), $this->errors() );
	}

	/**
	 * A required Hotspot Field also blocks the save when the editor submitted
	 * nothing at all.
	 */
	public function test_required_field_with_empty_submission_blocks_the_save(): void {
		$this->assertFalse( acf_validate_value( '', $this->field( true ), self::INPUT ) );
		$this->assertSame( array( 'Add at least one Hotspot to Hotspots.' ), $this->errors() );
	}

	/**
	 * A required Hotspot Field with Hotspots saves.
	 */
	public function test_required_field_with_hotspots_is_valid(): void {
		$this->assertTrue( $this->validate( array( $this->hotspot( 'Lamp' ) ), true ) );
		$this->assertSame( array(), $this->errors() );
	}

	/**
	 * A required Hotspot Field still needs a Label on every Hotspot.
	 */
	public function test_required_field_with_hotspot_without_label_names_its_row(): void {
		$this->assertFalse( $this->validate( array( $this->hotspot( '' ) ), true ) );
		$this->assertSame( array( 'Hotspot 1 needs a Label.' ), $this->errors() );
	}

	/**
	 * A Hotspot Field that is not required saves without Hotspots.
	 */
	public function test_optional_field_without_hotspots_is_valid(): void {
		$this->assertTrue( $this->validate( array() ) );
		$this->assertTrue( acf_validate_value( '', $this->field(), self::INPUT ) );
		$this->assertSame( array(), $this->errors() );
	}

	/**
	 * Validates a list of Hotspots as the editor submits it.
	 *
	 * @param array $hotspots Hotspots.
	 * @param bool  $required Whether the Hotspot Field is required.
	 * @return bool Whether the value is valid.
	 */
	private function validate( array $hotspots, bool $required = false ): bool {
		return acf_validate_value( wp_slash( wp_json_encode( $hotspots ) ), $this->field( $required ), self::INPUT );
	}

	/**
	 * The Hotspot Field of the test field group.
	 *
	 * @param bool $required Whether it is required.
	 */
	private function field( bool $required = false ): array {
		$field             = acf_get_field( 'field_hotspot_tests_hotspots' );
		$field['required'] = $required ? 1 : 0;

		return $field;
	}

	/**
	 * A Hotspot in the middle of the image.
	 *
	 * @param string $label       Label.
	 * @param string $description Description.
	 */
	private function hotspot( string $label, string $description = '' ): array {
		return array(
			'x'           => 50,
			'y'           => 50,
			'label'       => $label,
			'description' => $description,
		);
	}

	/**
	 * The messages of the validation errors ACF collected for the field.
	 *
	 * @return list<string>
	 */
	private function errors(): array {
		$errors = acf_get_validation_errors() ? acf_get_validation_errors() : array();

		$this->assertSame( array(), array_values( array_diff( array_column( $errors, 'input' ), array( self::INPUT ) ) ) );

		return array_column( $errors, 'message' );
	}
}
