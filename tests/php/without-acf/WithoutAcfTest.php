<?php
/**
 * Tests for the plugin when ACF is not active.
 *
 * @package HotspotFieldForAcf
 */

/**
 * Without ACF the plugin only explains what it needs.
 */
class WithoutAcfTest extends WP_UnitTestCase {

	/**
	 * Administrators see a notice asking for ACF 6.0 or later.
	 */
	public function test_administrators_see_a_requirement_notice(): void {
		$this->assertFalse( function_exists( 'acf' ), 'This suite must run without ACF.' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertStringContainsString(
			'Hotspot Field for ACF requires Advanced Custom Fields (or ACF Pro) 6.0 or later.',
			get_echo( 'do_action', array( 'admin_notices' ) )
		);
	}

	/**
	 * Users who cannot manage plugins are not shown the notice.
	 */
	public function test_other_users_do_not_see_the_notice(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertStringNotContainsString( 'Hotspot Field for ACF', get_echo( 'do_action', array( 'admin_notices' ) ) );
	}
}
