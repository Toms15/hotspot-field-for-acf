<?php
/**
 * Tests for the plugin when ACF is older than 6.0.
 *
 * @package HotspotFieldForAcf
 */

/**
 * With an unsupported ACF the plugin adds no field type and shows a notice.
 */
class OldAcfTest extends WP_UnitTestCase {

	/**
	 * The Hotspot field type is not offered.
	 */
	public function test_hotspot_field_type_is_not_registered(): void {
		$this->assertTrue( version_compare( acf_get_setting( 'version' ), '6.0', '<' ), 'This suite must run with an ACF version below 6.0.' );

		$this->assertNull( acf_get_field_type( 'hotspot' ) );
	}

	/**
	 * Administrators see a notice asking for ACF 6.0 or later.
	 */
	public function test_administrators_see_a_requirement_notice(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertStringContainsString(
			'Hotspot Field for ACF requires Advanced Custom Fields (or ACF Pro) 6.0 or later.',
			get_echo( 'do_action', array( 'admin_notices' ) )
		);
	}
}
